<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * External function to copy a course
 *
 * @package    block_mastermind_assistant
 * @copyright  2025 The Namers <info@mastermindassistant.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_mastermind_assistant\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use context_system;
use context_course;
use context_coursecat;
use Exception;
use backup_controller;
use restore_controller;
use backup;
use backup_setting;

class copy_course extends external_api {

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID to copy'),
            'categoryid' => new external_value(PARAM_INT, 'Target category ID', VALUE_DEFAULT, 1),
            'newcoursename' => new external_value(PARAM_TEXT, 'Custom name for copied course', VALUE_DEFAULT, '')
        ]);
    }

    /**
     * Copy an existing course using Moodle's backup/restore system
     * @param int $courseid
     * @param int $categoryid
     * @return array
     */
    public static function execute($courseid, $categoryid = 1, $newcoursename = '') {
        global $DB, $USER, $CFG;

        // Allow extra time for large course copies.
        \core_php_time_limit::raise(300);

        try {
            // Validate parameters
            $params = self::validate_parameters(self::execute_parameters(), [
                'courseid' => $courseid,
                'categoryid' => $categoryid,
                'newcoursename' => $newcoursename
            ]);

            // Check source course exists
            $sourcecourse = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);

            // Check capabilities
            $categorycontext = context_coursecat::instance($params['categoryid']);
            self::validate_context($categorycontext);
            require_capability('moodle/course:create', $categorycontext);
            
            $coursecontext = context_course::instance($params['courseid']);
            require_capability('moodle/backup:backupcourse', $coursecontext);
            require_capability('moodle/restore:restorecourse', $categorycontext);

            // Generate unique shortname
            $originalshortname = $sourcecourse->shortname;
            $newshortname = $originalshortname;
            $counter = 1;
            while ($DB->record_exists('course', ['shortname' => $newshortname])) {
                $newshortname = $originalshortname . '_copy' . $counter;
                $counter++;
            }

            // Step 1: Backup the source course
            $bc = new backup_controller(
                backup::TYPE_1COURSE,
                $params['courseid'],
                backup::FORMAT_MOODLE,
                backup::INTERACTIVE_NO,
                backup::MODE_IMPORT,  // Changed from MODE_COPY
                $USER->id
            );

            // Set backup settings — only touch settings that exist in this mode.
            // MODE_IMPORT does not expose user-related settings.
            $backupsettings = [
                'activities' => 1,
                'blocks' => 1,
                'filters' => 1,
                'users' => 0,
                'enrolments' => 0,
                'role_assignments' => 0,
                'comments' => 0,
                'userscompletion' => 0,
                'logs' => 0,
                'grade_histories' => 0,
            ];

            $plan = $bc->get_plan();
            foreach ($backupsettings as $name => $value) {
                try {
                    $setting = $plan->get_setting($name);
                    if ($setting->get_status() == backup_setting::NOT_LOCKED) {
                        $setting->set_value($value);
                    }
                } catch (\Exception $e) {
                    // Setting not available in this backup mode — skip it.
                    continue;
                }
            }

            // Execute backup
            $bc->execute_plan();
            $backupid = $bc->get_backupid();
            $backupbasepath = $bc->get_plan()->get_basepath();
            $bc->destroy();

            // Step 2: Create new course record
            $newcoursedata = new \stdClass();
            $newcoursedata->fullname = !empty($params['newcoursename'])
                ? $params['newcoursename']
                : $sourcecourse->fullname . ' (Copy)';
            $newcoursedata->shortname = $newshortname;
            $newcoursedata->category = $params['categoryid'];
            $newcoursedata->visible = $sourcecourse->visible;
            $newcoursedata->startdate = time();
            $newcoursedata->enddate = 0;
            $newcoursedata->idnumber = $sourcecourse->idnumber;
            $newcoursedata->format = $sourcecourse->format;
            $newcoursedata->showgrades = $sourcecourse->showgrades;
            $newcoursedata->newsitems = $sourcecourse->newsitems;
            $newcoursedata->maxbytes = $sourcecourse->maxbytes;
            $newcoursedata->enablecompletion = $sourcecourse->enablecompletion;

            // Create the course
            $newcourse = \create_course($newcoursedata);

            // Step 3: Restore backup into new course
            $rc = new restore_controller(
                $backupid,
                $newcourse->id,
                backup::INTERACTIVE_NO,
                backup::MODE_IMPORT,  // Changed from MODE_COPY
                $USER->id,
                backup::TARGET_NEW_COURSE
            );

            // Check precheck
            if (!$rc->execute_precheck()) {
                $precheckresults = $rc->get_precheck_results();
                if (is_array($precheckresults) && !empty($precheckresults['errors'])) {
                    $rc->destroy();
                    if (empty($CFG->keeptempdirectoriesonbackup)) {
                        fulldelete($backupbasepath);
                    }
                    throw new Exception('Restore precheck failed: ' . print_r($precheckresults['errors'], true));
                }
            }

            // Execute restore
            $rc->execute_plan();
            $rc->destroy();

            // Clean up backup files
            if (empty($CFG->keeptempdirectoriesonbackup)) {
                fulldelete($backupbasepath);
            }

            // Auto-enroll current user as editing teacher in the new course.
            $editingteacherrole = $DB->get_record('role', ['shortname' => 'editingteacher']);
            if ($editingteacherrole) {
                $enrolplugin = enrol_get_plugin('manual');
                $enrolinstance = $DB->get_record('enrol', [
                    'courseid' => $newcourse->id,
                    'enrol' => 'manual',
                    'status' => ENROL_INSTANCE_ENABLED,
                ]);
                if (!$enrolinstance) {
                    // Create a manual enrolment instance if none exists.
                    $enrolid = $enrolplugin->add_instance($newcourse);
                    $enrolinstance = $DB->get_record('enrol', ['id' => $enrolid]);
                }
                if ($enrolinstance) {
                    $enrolplugin->enrol_user($enrolinstance, $USER->id, $editingteacherrole->id);
                }
            }

            return [
                'success' => true,
                'courseid' => $newcourse->id,
                'coursename' => $newcourse->fullname,
                'courseurl' => (new \moodle_url('/course/view.php', ['id' => $newcourse->id]))->out(false)
            ];

        } catch (Exception $e) {
            debugging('copy_course failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [
                'success' => false,
                'error' => 'Failed to copy course: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Returns description of method result value
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'courseid' => new external_value(PARAM_INT, 'Copied course ID', VALUE_OPTIONAL),
            'coursename' => new external_value(PARAM_TEXT, 'Course name', VALUE_OPTIONAL),
            'courseurl' => new external_value(PARAM_URL, 'Course URL', VALUE_OPTIONAL),
            'error' => new external_value(PARAM_TEXT, 'Error message', VALUE_OPTIONAL),
        ]);
    }
}

