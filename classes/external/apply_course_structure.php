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
 * External function to apply course structure
 *
 * @package    block_mastermind_assistant
 * @copyright  2025 The Namers <info@mastermindassistant.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_mastermind_assistant\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use context_course;
use Exception;
use moodle_exception;
use stdClass;

class apply_course_structure extends external_api {

    public static function execute_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'structuretext' => new external_value(PARAM_RAW, 'AI-generated course structure text')
        ]);
    }

    public static function execute($courseid, $structuretext) {
        global $DB, $CFG, $USER;

        $startTime = microtime(true);

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'structuretext' => $structuretext
        ]);

        // Validate courseid.
        if (empty($params['courseid']) || !is_numeric($params['courseid'])) {
            throw new moodle_exception('invalidcourseid', 'error', '', $params['courseid']);
        }

        // Check if this might be a section ID instead of course ID.
        $courseexists = $DB->record_exists('course', ['id' => $params['courseid']]);
        if (!$courseexists) {
            $sectionrecord = $DB->get_record('course_sections', ['id' => $params['courseid']], 'id, course');
            if ($sectionrecord) {
                $params['courseid'] = $sectionrecord->course;
                $courseexists = $DB->record_exists('course', ['id' => $params['courseid']]);
            }
        }

        if (!$courseexists) {
            throw new moodle_exception('invalidcourseid', 'error', '', $params['courseid']);
        }

        $course = \get_course($params['courseid']);
        $context = context_course::instance($params['courseid']);
        self::validate_context($context);

        // Check capabilities.
        require_capability('block/mastermind_assistant:applychanges', $context);
        require_capability('moodle/course:manageactivities', $context);
        require_capability('moodle/course:update', $context);

        try {
            // Increase execution time limit for complex course changes
            set_time_limit(120); // 2 minutes

            // Parse the JSON structure
            $sections = self::parse_json_structure($params['structuretext']);

            if (empty($sections)) {
                return [
                    'success' => false,
                    'error' => 'No valid sections found in the structure text. Please check the AI response format.'
                ];
            }

            // Wrap apply operation in a transaction for data integrity.
            $transaction = $DB->start_delegated_transaction();
            try {
                $changes = self::apply_structure_changes($params['courseid'], $sections);

                $transaction->allow_commit();

                // Rebuild course cache after transaction commits.
                rebuild_course_cache($params['courseid'], true);

                return [
                    'success' => true,
                    'message' => self::format_changes_message($changes)
                ];

            } catch (\dml_missing_record_exception $e) {
                try { $transaction->rollback($e); } catch (Exception $ignore) { /* Already rolled back. */ }
                error_log("MM-Assistant: Database missing record error: " . $e->getMessage());

                return [
                    'success' => false,
                    'error' => 'Database integrity error during course updates. Changes have been rolled back.'
                ];
            } catch (Exception $e) {
                try { $transaction->rollback($e); } catch (Exception $ignore) { /* Already rolled back. */ }
                throw $e;
            }

        } catch (Exception $e) {
            error_log("MM-Assistant: Apply course structure error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error applying course structure: ' . $e->getMessage()
            ];
        }
    }

    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_TEXT, 'Success message with details', VALUE_OPTIONAL),
            'error' => new external_value(PARAM_TEXT, 'Error message', VALUE_OPTIONAL),
        ]);
    }

    /**
     * Parse the AI-generated JSON structure into actionable data
     * @param string $structuretext JSON string from AI
     * @return array
     */
    protected static function parse_json_structure($structuretext) {
        // First try to clean markdown code blocks if present
        $cleanJsonText = self::clean_markdown_response($structuretext);

        // Try to parse as JSON
        $data = json_decode($cleanJsonText, true);

        if ($data && isset($data['sections'])) {
            // Convert JSON format to internal format
            $sections = [];
            foreach ($data['sections'] as $section) {
                $activities = [];
                if (isset($section['activities'])) {
                    foreach ($section['activities'] as $activity) {
                        if (!empty($activity['activity_name'])) {
                            $activities[] = [
                                'name' => $activity['activity_name'],
                                'status' => $activity['status'] ?? 'NEW',
                                'type' => $activity['moodle_type'] ?? 'page'
                            ];
                        }
                    }
                }

                if (!empty($section['section_name'])) {
                    $sections[] = [
                        'name' => $section['section_name'],
                        'section_id' => $section['section_id'] ?? null, // Include section ID for matching
                        'status' => $section['status'] ?? 'NEW',
                        'description' => $section['description'] ?? '',
                        'activities' => $activities,
                        'objectives' => $section['learning_objectives'] ?? [],
                        'is_new' => $section['is_new_section'] ?? false
                    ];
                }
            }
            return $sections;
        }

        // Fallback: try to parse as old markdown format for backwards compatibility
        return self::parse_markdown_structure($structuretext);
    }

    /**
     * Fallback parser for markdown format (backwards compatibility)
     * @param string $structuretext
     * @return array
     */
    protected static function parse_markdown_structure($structuretext) {
        $sections = [];
        $lines = explode("\n", $structuretext);
        $currentSection = null;
        $inActivities = false;
        $inObjectives = false;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // Section headers
            if (preg_match('/^Section\s+(\d+):\s*(.+)$/i', $line, $matches)) {
                if ($currentSection) {
                    $sections[] = $currentSection;
                }
                $currentSection = [
                    'name' => trim($matches[2]),
                    'status' => 'NEW',
                    'description' => '',
                    'activities' => [],
                    'objectives' => []
                ];
                $inActivities = false;
                $inObjectives = false;
            }
            // Status line
            elseif (preg_match('/^Status:\s*(.+)$/i', $line, $matches)) {
                if ($currentSection) {
                    $currentSection['status'] = trim($matches[1]);
                }
            }
            // Description line
            elseif (preg_match('/^Description:\s*(.+)$/i', $line, $matches)) {
                if ($currentSection) {
                    $currentSection['description'] = trim($matches[1]);
                }
                $inActivities = false;
                $inObjectives = false;
            }
            // Activities section
            elseif (preg_match('/^Activities:/i', $line)) {
                $inActivities = true;
                $inObjectives = false;
            }
            // Learning objectives section
            elseif (preg_match('/^Learning\s+Objectives:/i', $line)) {
                $inObjectives = true;
                $inActivities = false;
            }
            // Activity or objective items
            elseif (preg_match('/^[-*•]\s*(.+)$/', $line, $matches)) {
                $item = trim($matches[1]);
                if ($currentSection) {
                    if ($inActivities) {
                        // Parse activity: "Activity Name (STATUS - type)" or "Activity Name (STATUS)"
                        if (preg_match('/^(.+?)\s+\((KEEP|NEW|MODIFIED)(?:\s*-\s*([^)]+))?\)$/', $item, $actMatches)) {
                            $activityName = trim($actMatches[1]);
                            if ($activityName === '') {
                                continue; // skip empty names
                            }
                            $currentSection['activities'][] = [
                                'name' => $activityName,
                                'status' => trim($actMatches[2]),
                                'type' => isset($actMatches[3]) ? trim($actMatches[3]) : 'page'
                            ];
                        }
                    } elseif ($inObjectives) {
                        if ($item !== 'Objective' && $item !== '[Objective]') {
                            $currentSection['objectives'][] = $item;
                        }
                    }
                }
            }
        }

        // Add the last section
        if ($currentSection) {
            $sections[] = $currentSection;
        }

        return $sections;
    }

    /**
     * Apply the parsed structure changes to the actual course
     * @param int $courseid
     * @param array $sections
     * @return array Changes made
     */
    protected static function apply_structure_changes($courseid, $sections) {
        global $DB;

        $changes = [
            'sections_added' => 0,
            'sections_modified' => 0,
            'activities_added' => 0,
            'activities_modified' => 0,
            'activities_failed' => 0
        ];

        $course = \get_course($courseid);

        if (!$course || !isset($course->id) || $course->id != $courseid) {
            throw new Exception("Failed to retrieve valid course object for courseid: {$courseid}");
        }

        // Check for any invalid course module references in sections before we start
        $sectionsToFix = [];
        $allSections = $DB->get_records('course_sections', ['course' => $courseid]);
        foreach ($allSections as $section) {
            if (!empty($section->sequence)) {
                $moduleIds = explode(',', $section->sequence);
                $validModuleIds = [];
                $foundInvalid = false;

                foreach ($moduleIds as $moduleId) {
                    if (is_numeric($moduleId) && !empty($moduleId)) {
                        if ($DB->record_exists('course_modules', ['id' => $moduleId])) {
                            $validModuleIds[] = $moduleId;
                        } else {
                            $foundInvalid = true;
                        }
                    }
                }

                // If we found invalid references, clean them up
                if ($foundInvalid) {
                    $newSequence = implode(',', $validModuleIds);
                    $DB->update_record('course_sections', (object)[
                        'id' => $section->id,
                        'sequence' => $newSequence
                    ]);
                    $sectionsToFix[] = $section->section;
                }
            }
        }

        if (!empty($sectionsToFix)) {
            // Clear course cache after fixing sequences
            \rebuild_course_cache($courseid, true);
        }

        $existingSections = $DB->get_records('course_sections', ['course' => $courseid], 'section');

        foreach ($sections as $sectionData) {
            $sectionName = $sectionData['name'];
            $sectionId = $sectionData['section_id'] ?? null;

            // Match by section ID first (most reliable)
            $existingSection = null;
            $matchType = 'none';

            if ($sectionId && is_numeric($sectionId)) {
                // Find section by ID
                foreach ($existingSections as $section) {
                    if ($section->id == $sectionId) {
                        $existingSection = $section;
                        $matchType = 'section_id';
                        break;
                    }
                }
            }

            // Fallback to name matching if ID match fails
            if (!$existingSection) {
                foreach ($existingSections as $section) {
                    // Exact match (case-sensitive)
                    if ($section->name === $sectionName) {
                        $existingSection = $section;
                        $matchType = 'exact_name';
                        break;
                    }
                    // Trimmed exact match
                    if (trim($section->name) === trim($sectionName)) {
                        $existingSection = $section;
                        $matchType = 'trimmed_name';
                        break;
                    }
                }
            }

            if ($sectionData['status'] === 'UNCHANGED') {
                // For unchanged sections, still process activities but don't modify section itself
                if ($existingSection) {
                    // Process activities in this unchanged section
                    foreach ($sectionData['activities'] as $activityData) {
                        $atype = $activityData['moodle_type'] ?? ($activityData['type'] ?? '');

                        if ($activityData['status'] === 'NEW') {
                            if (self::create_activity($course, $existingSection, array_merge($activityData, ['type' => $atype]))) {
                                $changes['activities_added']++;
                            } else {
                                $changes['activities_failed']++;
                            }
                        }
                    }
                }
                continue; // Skip section modification but activities were processed
            }

            if (!$existingSection) {
                // Create new section at the end
                $maxSection = $DB->get_field_sql("SELECT MAX(section) FROM {course_sections} WHERE course = ?", [$courseid]);
                $newSectionNumber = ($maxSection ?? 0) + 1;
                $existingSection = self::create_course_section($courseid, $newSectionNumber, $sectionData['name'], $sectionData['description']);
                if ($existingSection) {
                    $changes['sections_added']++;
                }
            } else {
                // Update existing section if modified
                if ($sectionData['status'] === 'MODIFIED' || $sectionData['status'] === 'NEW') {
                    self::update_course_section($existingSection, $sectionData['name'], $sectionData['description']);
                    $changes['sections_modified']++;
                }
            }

            // Handle activities in this section
            foreach ($sectionData['activities'] as $activityData) {
                $atype = $activityData['moodle_type'] ?? ($activityData['type'] ?? '');

                if ($activityData['status'] === 'KEEP') {
                    // Skip activities that should remain unchanged
                    continue;
                }

                if ($activityData['status'] === 'NEW') {
                    // Create new activity
                    $result = self::create_activity($course, $existingSection, array_merge($activityData, ['type' => $atype]));

                    if ($result) {
                        $changes['activities_added']++;
                    } else {
                        $changes['activities_failed']++;
                    }
                } elseif ($activityData['status'] === 'MODIFIED') {
                    // Try to find and modify existing activity (basic implementation)
                    // For now, we'll create a new one - in a full implementation you'd find and update
                    if (self::create_activity($course, $existingSection, array_merge($activityData, ['type' => $atype]))) {
                        $changes['activities_modified']++;
                    } else {
                        $changes['activities_failed']++;
                    }
                }
            }
        }

        // Rebuild course cache
        \rebuild_course_cache($courseid, true);

        // Final safety cleanup: ensure no section references invalid course_module IDs created during this run
        self::cleanup_invalid_cm_references($courseid);

        // One more pass: verify every CM referenced in section sequences still exists *and* has a context.
        // This catches cases where add_moduleinfo reserved an ID, updated the sequence, but later rolled back
        // the cm record (leaving a dangling ID that causes "invalid course module" errors on navigation).
        self::final_section_integrity_check($courseid);

        return $changes;
    }

    /**
     * Create a new course section
     * @param int $courseid
     * @param int $sectionnumber
     * @param string $name
     * @param string $description
     * @return stdClass
     */
    protected static function create_course_section($courseid, $sectionnumber, $name, $description) {
        global $DB;

        // Ensure the section number doesn't already exist
        while ($DB->record_exists('course_sections', ['course' => $courseid, 'section' => $sectionnumber])) {
            $sectionnumber++;
        }

        // Use Moodle's built-in function to create sections properly
        // This ensures the course format is updated correctly
        \course_create_sections_if_missing($courseid, $sectionnumber);

        // Now get the section that was just created
        $section = $DB->get_record('course_sections', [
            'course' => $courseid,
            'section' => $sectionnumber
        ]);

        if (!$section) {
            return null;
        }

        // Update the section name and description
        $section->name = $name;
        $section->summary = $description;
        $section->summaryformat = FORMAT_HTML;
        $section->visible = 1;

        $DB->update_record('course_sections', $section);

        // Rebuild course cache to ensure changes are visible
        \rebuild_course_cache($courseid, true);

        return $section;
    }

    /**
     * Update an existing course section
     * @param stdClass $section
     * @param string $name
     * @param string $description
     */
    protected static function update_course_section($section, $name, $description) {
        global $DB;

        $section->name = $name;
        $section->summary = $description;
        $section->summaryformat = FORMAT_HTML;

        $DB->update_record('course_sections', $section);
    }

    /**
     * Create a new activity in a section
     * @param stdClass $course
     * @param stdClass $section
     * @param array $activityData
     * @return bool Success status
     */
    protected static function create_activity($course, $section, $activityData) {
        $factory = new \block_mastermind_assistant\module_factory($course, $section);
        return $factory->create_from_ai($activityData);
    }

    /**
     * Set module-specific default values and required fields
     * @param stdClass $moduleinfo
     * @param string $modulename
     * @param string $name
     */
    protected static function set_module_defaults($moduleinfo, $modulename, $name) {
        switch ($modulename) {
            case 'page':
                $moduleinfo->intro = "Content page: {$name}";
                $moduleinfo->introformat = FORMAT_HTML;
                $moduleinfo->content = "<h3>{$name}</h3><p>This page contains content for the {$name} section.</p>";
                $moduleinfo->contentformat = FORMAT_HTML;
                $moduleinfo->display = 5; // Open in same window
                $moduleinfo->displayoptions = 'a:1:{s:10:"printheading";s:1:"1";}';
                break;

            case 'forum':
                $moduleinfo->intro = "Discussion forum for {$name}";
                $moduleinfo->introformat = FORMAT_HTML;
                $moduleinfo->type = 'general';
                $moduleinfo->assessed = 0;
                $moduleinfo->assesstimestart = 0;
                $moduleinfo->assesstimefinish = 0;
                $moduleinfo->scale = 1;
                $moduleinfo->maxbytes = 0;
                $moduleinfo->maxattachments = 1;
                $moduleinfo->forcesubscribe = 0;
                $moduleinfo->trackingtype = 1;
                $moduleinfo->rsstype = 0;
                $moduleinfo->rssarticles = 0;
                $moduleinfo->blockafter = 0;
                $moduleinfo->blockperiod = 0;
                $moduleinfo->warnafter = 0;
                break;

            case 'assign':
                $moduleinfo->intro = "Assignment: {$name}";
                $moduleinfo->introformat = FORMAT_HTML;
                $moduleinfo->alwaysshowdescription = 0;
                $moduleinfo->submissiondrafts = 0;
                $moduleinfo->requiresubmissionstatement = 0;
                $moduleinfo->sendnotifications = 1;
                $moduleinfo->sendlatenotifications = 1;
                $moduleinfo->duedate = strtotime('+2 weeks');
                $moduleinfo->allowsubmissionsfromdate = time();
                $moduleinfo->grade = 100;
                $moduleinfo->teamsubmission = 0;
                $moduleinfo->requireallteammemberssubmit = 0;
                $moduleinfo->blindmarking = 0;
                $moduleinfo->revealidentities = 0;
                $moduleinfo->attemptreopenmethod = 'none';
                $moduleinfo->maxattempts = -1;
                $moduleinfo->cutoffdate = 0;
                $moduleinfo->gradingduedate = 0;
                $moduleinfo->markingworkflow = 0;
                $moduleinfo->markingallocation = 0;

                // Required submission plugins
                $moduleinfo->assignsubmission_onlinetext_enabled = 1;
                $moduleinfo->assignsubmission_file_enabled = 1;
                $moduleinfo->assignsubmission_file_maxfiles = 20;
                $moduleinfo->assignsubmission_file_maxsizebytes = 0;
                $moduleinfo->assignsubmission_file_filetypes = '';
                $moduleinfo->assignsubmission_comments_enabled = 0;

                // Required feedback plugins
                $moduleinfo->assignfeedback_comments_enabled = 1;
                $moduleinfo->assignfeedback_file_enabled = 0;
                $moduleinfo->assignfeedback_offline_enabled = 0;
                break;

            case 'quiz':
                $moduleinfo->intro = "Quiz: {$name}";
                $moduleinfo->introformat = FORMAT_HTML;
                $moduleinfo->timeopen = 0;
                $moduleinfo->timeclose = 0;
                $moduleinfo->timelimit = 0;
                $moduleinfo->overduehandling = 'autoabandon';
                $moduleinfo->graceperiod = 0;
                $moduleinfo->preferredbehaviour = 'deferredfeedback';
                $moduleinfo->canredoquestions = 0;
                $moduleinfo->attempts = 0;
                $moduleinfo->attemptonlast = 0;
                $moduleinfo->grademethod = 1;
                $moduleinfo->decimalpoints = 2;
                $moduleinfo->questiondecimalpoints = -1;
                $moduleinfo->reviewattempt = 69888;
                $moduleinfo->reviewcorrectness = 4352;
                $moduleinfo->reviewmarks = 4352;
                $moduleinfo->reviewspecificfeedback = 4352;
                $moduleinfo->reviewgeneralfeedback = 4352;
                $moduleinfo->reviewrightanswer = 4352;
                $moduleinfo->reviewoverallfeedback = 4352;
                $moduleinfo->questionsperpage = 1;
                $moduleinfo->navmethod = 'free';
                $moduleinfo->shuffleanswers = 1;
                $moduleinfo->sumgrades = 1; // Must be > 0 for proper quiz creation
                $moduleinfo->grade = 10;
                $moduleinfo->showuserpicture = 0;
                $moduleinfo->showblocks = 0;
                $moduleinfo->password = '';
                $moduleinfo->subnet = '';
                $moduleinfo->delay1 = 0;
                $moduleinfo->delay2 = 0;
                $moduleinfo->browsersecurity = '-';
                break;

            case 'url':
                $moduleinfo->intro = "External link: {$name}";
                $moduleinfo->introformat = FORMAT_HTML;
                $moduleinfo->externalurl = 'https://example.com';
                $moduleinfo->display = 0;
                $moduleinfo->displayoptions = 'a:1:{s:10:"printheading";s:1:"1";}';
                break;

            case 'book':
                $moduleinfo->intro = "Book: {$name}";
                $moduleinfo->introformat = FORMAT_HTML;
                $moduleinfo->numbering = 1;
                $moduleinfo->customtitles = 0;
                break;

            case 'glossary':
                $moduleinfo->intro = "Glossary: {$name}";
                $moduleinfo->introformat = FORMAT_HTML;
                $moduleinfo->mainglossary = 0;
                $moduleinfo->showspecial = 1;
                $moduleinfo->showalphabet = 1;
                $moduleinfo->allowduplicatedentries = 0;
                $moduleinfo->allowcomments = 0;
                $moduleinfo->allowprintview = 1;
                $moduleinfo->usedynalink = 1;
                $moduleinfo->defaultapproval = 1;
                $moduleinfo->globalglossary = 0;
                $moduleinfo->entbypage = 10;
                $moduleinfo->displayformat = 'dictionary';
                $moduleinfo->assessed = 0;
                $moduleinfo->scale = 1;
                break;

            case 'wiki':
                $moduleinfo->intro = "Wiki: {$name}";
                $moduleinfo->introformat = FORMAT_HTML;
                $moduleinfo->wikimode = 'collaborative';
                $moduleinfo->defaultformat = 'html';
                $moduleinfo->forceformat = 1;
                $moduleinfo->editbegin = 0;
                $moduleinfo->editend = 0;
                break;

            case 'feedback':
                $moduleinfo->intro = "Feedback: {$name}";
                $moduleinfo->introformat = FORMAT_HTML;
                $moduleinfo->anonymous = 1;
                $moduleinfo->multiple_submit = 1;
                $moduleinfo->autonumbering = 1;
                $moduleinfo->site_after_submit = '';
                $moduleinfo->page_after_submit = 'Thank you for your feedback!';
                $moduleinfo->page_after_submitformat = FORMAT_HTML;
                $moduleinfo->publish_stats = 0;
                $moduleinfo->timeopen = 0;
                $moduleinfo->timeclose = 0;
                $moduleinfo->completionsubmit = 0;
                $moduleinfo->email_notification = 0;
                break;

            case 'lti':
                $moduleinfo->intro = "External tool: {$name}";
                $moduleinfo->introformat = FORMAT_HTML;
                $moduleinfo->typeid = 0;
                $moduleinfo->toolurl = 'https://example.com/lti-tool'; // Required: Cannot be empty
                $moduleinfo->securetoolurl = '';
                $moduleinfo->instructorchoicesendname = 1;
                $moduleinfo->instructorchoicesendemailaddr = 1;
                $moduleinfo->instructorchoiceallowroster = 0;
                $moduleinfo->instructorchoiceallowsetting = 0;
                $moduleinfo->launchcontainer = 1;
                $moduleinfo->resourcekey = 'example_key';
                $moduleinfo->password = 'example_secret';
                $moduleinfo->debuglaunch = 0;
                $moduleinfo->showtitlelaunch = 1;
                $moduleinfo->showdescriptionlaunch = 1;
                $moduleinfo->servicesalt = uniqid('lti_', true);
                $moduleinfo->grade = 100;
                break;

            case 'label':
                $moduleinfo->intro = $name;
                $moduleinfo->introformat = FORMAT_HTML;
                // Label uses intro as its main content and doesn't need additional fields
                break;

            default:
                // Fallback for any other activity type
                $moduleinfo->intro = $name;
                $moduleinfo->introformat = FORMAT_HTML;
                break;
        }
    }

    /**
     * Map AI activity types to Moodle module names
     * @param string $type
     * @return string
     */
    protected static function map_activity_type($type) {
        $mappings = [
            // Core Moodle activities
            'page' => 'page',
            'forum' => 'forum',
            'assignment' => 'assign',
            'quiz' => 'quiz',
            'url' => 'url',
            'book' => 'book',
            'glossary' => 'glossary',
            'wiki' => 'wiki',
            'wikipedia' => 'wiki',
            'feedback' => 'feedback',
            'external tool' => 'lti',
            'lti' => 'lti',
            'text and media area' => 'label',
            'label' => 'label',

            // Common aliases and variations
            'discussion' => 'forum',
            'reading' => 'page',
            'lecture' => 'page',
            'video' => 'page',
            'content' => 'page',
            'resource' => 'page',
            'file' => 'page',
            'link' => 'url',
            'external link' => 'url',
            'web link' => 'url',
            'assignment submission' => 'assign',
            'homework' => 'assign',
            'task' => 'assign',
            'test' => 'quiz',
            'examination' => 'quiz',
            'assessment' => 'quiz',
            'survey' => 'feedback',
            'questionnaire' => 'feedback',
            'poll' => 'feedback',
            'tool' => 'lti',
            'external activity' => 'lti',
            'third party' => 'lti',
            'text' => 'label',
            'media' => 'label',
            'description' => 'label'
        ];

        $type = strtolower(trim($type));

        // Direct match first
        if (isset($mappings[$type])) {
            return $mappings[$type];
        }

        // Try partial matches for flexibility
        foreach ($mappings as $key => $value) {
            if (strpos($type, $key) !== false || strpos($key, $type) !== false) {
                return $value;
            }
        }

        // Default fallback
        return 'page';
    }

    /**
     * Format the changes made into a readable message
     * @param array $changes
     * @return string
     */
    protected static function format_changes_message($changes) {
        $messages = [];

        if ($changes['sections_added'] > 0) {
            $messages[] = $changes['sections_added'] . ' new section(s) created';
        }
        if ($changes['sections_modified'] > 0) {
            $messages[] = $changes['sections_modified'] . ' section(s) updated';
        }
        if ($changes['activities_added'] > 0) {
            $messages[] = $changes['activities_added'] . ' new activit(ies) added';
        }
        if ($changes['activities_modified'] > 0) {
            $messages[] = $changes['activities_modified'] . ' activit(ies) modified';
        }
        if (isset($changes['activities_failed']) && $changes['activities_failed'] > 0) {
            $messages[] = $changes['activities_failed'] . ' activit(ies) failed to create';
        }

        if (empty($messages)) {
            return 'No changes were necessary.';
        }

        $result = implode(', ', $messages) . '.';

        // Add helpful note if there were failures
        if (isset($changes['activities_failed']) && $changes['activities_failed'] > 0) {
            $result .= ' Some activities could not be created due to missing modules or configuration issues.';
        }

        return $result;
    }

    /**
     * Clean markdown code blocks from AI response
     * @param string $response
     * @return string
     */
    protected static function clean_markdown_response(string $response): string {
        // Remove markdown code blocks
        if (strpos($response, '```json') !== false) {
            // Extract content between ```json and ```
            if (preg_match('/```json\s*(.*?)\s*```/s', $response, $matches)) {
                return trim($matches[1]);
            }
        } else if (strpos($response, '```') !== false) {
            // Extract content between ``` and ```
            if (preg_match('/```\s*(.*?)\s*```/s', $response, $matches)) {
                return trim($matches[1]);
            }
        }

        return trim($response);
    }

    /**
     * Remove any invalid course module references that might still linger in section sequences.
     * This can happen if a module creation partially fails after the sequence has been updated.
     * @param int $courseid
     * @return void
     */
    protected static function cleanup_invalid_cm_references(int $courseid): void {
        global $DB;

        $allSections = $DB->get_records('course_sections', ['course' => $courseid]);
        $cleaned = [];
        foreach ($allSections as $section) {
            if (empty($section->sequence)) {
                continue;
            }
            $moduleIds = array_filter(explode(',', $section->sequence));
            $validIds = [];
            $changed = false;
            foreach ($moduleIds as $mid) {
                if ($DB->record_exists('course_modules', ['id' => $mid])) {
                    $validIds[] = $mid;
                } else {
                    $changed = true;
                }
            }
            if ($changed) {
                $DB->update_record('course_sections', (object)[
                    'id' => $section->id,
                    'sequence' => implode(',', $validIds)
                ]);
                $cleaned[] = $section->section;
            }
        }
        if ($cleaned) {
            \rebuild_course_cache($courseid, true);
        }
    }

    /**
     * Final integrity scan – ensure every cmid appearing in a section sequence:
     *   1) exists in {course_modules}
     *   2) has a matching context row (contextlevel = CONTEXT_MODULE)
     * If either check fails, remove the cmid from the section sequence.
     * @param int $courseid
     */
    protected static function final_section_integrity_check(int $courseid): void {
        global $DB;

        $sections = $DB->get_records('course_sections', ['course' => $courseid]);
        $touched = [];

        foreach ($sections as $section) {
            if (empty($section->sequence)) {
                continue;
            }

            $originalSeq = array_filter(explode(',', $section->sequence));
            $cleanSeq = [];

            foreach ($originalSeq as $cmid) {
                if (!$cmid) {
                    continue;
                }

                $exists = $DB->record_exists('course_modules', ['id' => $cmid]);
                $ctxExists = $DB->record_exists('context', ['instanceid' => $cmid, 'contextlevel' => CONTEXT_MODULE]);

                if ($exists && $ctxExists) {
                    $cleanSeq[] = $cmid;
                } else {
                    $touched[$section->section][] = $cmid;
                }
            }

            if (implode(',', $cleanSeq) !== $section->sequence) {
                $DB->update_record('course_sections', (object)[
                    'id' => $section->id,
                    'sequence' => implode(',', $cleanSeq)
                ]);
            }
        }

        if (!empty($touched)) {
            \rebuild_course_cache($courseid, true);
        }
    }
}
