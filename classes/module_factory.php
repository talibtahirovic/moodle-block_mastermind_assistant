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
 * Factory for creating Moodle activity modules
 *
 * @package    block_mastermind_assistant
 * @copyright  2026 The Namers <info@mastermindassistant.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_mastermind_assistant;

/**
 * Factory that creates all core Moodle activity modules.
 * Uses add_moduleinfo() for proper CM creation, section linking, and event triggering.
 */
class module_factory {
    /** @var \stdClass $course */
    private $course;
    /** @var \stdClass $section */
    private $section;

    /**
     * Constructor.
     *
     * @param \stdClass $course Course record.
     * @param \stdClass $section Section record to add modules to.
     */
    public function __construct(\stdClass $course, \stdClass $section) {
        $this->course  = $course;
        $this->section = $section;
    }

    /**
     * Create an activity from AI data.
     *
     * @param array $activitydata expects: name, moodle_type|type, intro(optional), etc.
     * @return bool success
     */
    public function create_from_ai(array $activitydata): bool {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->dirroot . '/course/lib.php');

        $modname = strtolower($activitydata['moodle_type'] ?? ($activitydata['type'] ?? ''));
        $name = $activitydata['name'] ?? 'Untitled activity';
        $intro = $activitydata['intro'] ?? '';

        // Check if module exists and is enabled.
        $module = $DB->get_record('modules', ['name' => $modname, 'visible' => 1]);
        if (!$module) {
            return false;
        }

        // Build moduleinfo object with all required fields.
        $moduleinfo = $this->build_moduleinfo($modname, $module->id, $name, $intro, $activitydata);
        if (!$moduleinfo) {
            return false;
        }

        try {
            $cm = \add_moduleinfo($moduleinfo, $this->course, null);

            if ($cm && isset($cm->coursemodule)) {
                return true;
            } else {
                return false;
            }
        } catch (\Throwable $e) {
            debugging("Exception creating {$modname} '{$name}': " . $e->getMessage());
            return false;
        }
    }

    /**
     * Build moduleinfo object with all required fields for each module type.
     *
     * @param string $modname Module name (e.g. page, quiz, forum).
     * @param int $moduleid Modules table ID.
     * @param string $name Activity name.
     * @param string $intro Activity intro/description HTML.
     * @param array $ai AI-supplied activity data.
     * @return \stdClass|null Moduleinfo object, or null if the module type is unsupported.
     */
    private function build_moduleinfo(string $modname, int $moduleid, string $name, string $intro, array $ai): ?\stdClass {
        $info = new \stdClass();
        $info->course = $this->course->id;
        $info->section = $this->section->section;
        $info->name = $name;
        $info->modulename = $modname;
        $info->module = $moduleid;
        $info->intro = $intro;
        $info->introformat = FORMAT_HTML;
        $info->visible = 1;
        $info->visibleoncoursepage = 1;
        $info->cmidnumber = ''; // Course module ID number (optional identifier).
        $info->groupmode = 0;
        $info->groupingid = 0;
        $info->completion = 0;
        $info->completionview = 0;
        $info->completionexpected = 0;

        // Add module-specific required fields.
        switch ($modname) {
            case 'assign':
                $info->grade = 100;
                $info->duedate = time() + (7 * 24 * 3600);
                $info->allowsubmissionsfromdate = time();
                $info->cutoffdate = 0;
                $info->gradingduedate = 0;
                $info->submissiondrafts = 0;
                $info->requiresubmissionstatement = 0;
                $info->sendnotifications = 0;
                $info->sendlatenotifications = 0; // REQUIRED: Missing field causing NULL error.
                $info->sendstudentnotifications = 0;
                $info->teamsubmission = 0;
                $info->requireallteammemberssubmit = 0;
                $info->blindmarking = 0;
                $info->attemptreopenmethod = 'none';
                $info->maxattempts = -1;
                $info->markingworkflow = 0;
                $info->markingallocation = 0;
                $info->assignsubmission_onlinetext_enabled = 1;
                $info->assignsubmission_file_enabled = 0;
                $info->assignfeedback_comments_enabled = 1;
                break;

            case 'book':
                $info->numbering = 0;
                $info->customtitles = 0;
                break;

            case 'chat':
                $info->chatmethod = 'ajax';
                $info->keepdays = 0;
                $info->studentlogs = 0;
                $info->chattime = time();
                $info->schedule = 0;
                break;

            case 'choice':
                $info->timeopen = 0;
                $info->timeclose = 0;
                $info->publish = 0;
                $info->allowmultiple = 0;
                $info->showresults = 0;
                $info->display = 0;
                $info->allowupdate = 0;
                $info->showunanswered = 0;
                $info->limitanswers = 0;
                // Need at least one option.
                $info->option = ['Option 1', 'Option 2'];
                $info->limit = [0, 0];
                break;

            case 'data':
                $info->scale = 0;
                $info->assessed = 0;
                break;

            case 'feedback':
                $info->anonymous = 1;
                $info->multiple_submit = 0;
                $info->autonumbering = 1;
                $info->page_after_submit_editor = ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0];
                $info->page_after_submit = ''; // Also needed for database.
                $info->page_after_submitformat = FORMAT_HTML;
                $info->site_after_submit = ''; // URL for redirect after submit.
                $info->publish_stats = 0;
                $info->timeopen = 0;
                $info->timeclose = 0;
                break;

            case 'folder':
                // No extra required fields.
                break;

            case 'forum':
                $info->type = 'general';
                $info->assessed = 0;
                $info->scale = 0;
                $info->grade_forum = 0; // Forum grade.
                $info->maxbytes = 0;
                $info->maxattachments = 1;
                $info->forcesubscribe = 0;
                $info->trackingtype = 1;
                $info->blockafter = 0;
                $info->blockperiod = 0;
                $info->warnafter = 0;
                break;

            case 'glossary':
                $info->allowduplicatedentries = 0;
                $info->displayformat = 'dictionary';
                $info->mainglossary = 0;
                $info->showspecial = 1;
                $info->showalphabet = 1;
                $info->showall = 1;
                $info->editalways = 0;
                $info->approved = 1;
                $info->globalglossary = 0;
                $info->assessed = 0; // Rating/grading.
                $info->scale = 0;
                break;

            case 'h5pactivity':
                $info->grading = 0;
                $info->grademethod = 0;
                $info->displayoptions = 0;
                $info->enabletracking = 1;
                break;

            case 'imscp':
                // No extra required fields beyond intro.
                break;

            case 'label':
                // Label uses intro as content.
                break;

            case 'lesson':
                $info->grade = 100;
                $info->practice = 0;
                $info->modattempts = 0;
                $info->usepassword = 0;
                $info->password = '';
                $info->dependency = 0;
                $info->conditions = 0;
                $info->progressbar = 0;
                $info->ongoing = 0;
                $info->review = 0;
                $info->maxattempts = 1;
                $info->retake = 1;
                $info->minquestions = 0;
                $info->mediafile = ''; // Media file path.
                $info->available = 0; // Availability date.
                $info->deadline = 0; // Deadline date.
                break;

            case 'lti':
                $info->toolurl = $ai['toolurl'] ?? 'https://example.com/lti';
                $info->instructorchoicesendname = 0;
                $info->instructorchoicesendemailaddr = 0;
                $info->instructorchoiceacceptgrades = 0;
                $info->launchcontainer = 1;
                $info->grade = 100;
                break;

            case 'page':
                // Use explicit content if provided; never fall back to intro (description)
                // to avoid showing the same text in both the description and page body.
                $info->content = !empty($ai['content']) ? $ai['content'] : '<p>Content coming soon.</p>';
                $info->contentformat = FORMAT_HTML;
                $info->display = 5;
                $info->displayoptions = 'a:1:{s:12:"printheading";s:1:"1";}';
                $info->printintro = 1; // Display description on course page.
                $info->printlastmodified = 0; // Display last modified date.
                break;

            case 'quiz':
                $info->timeopen = 0;
                $info->timeclose = 0;
                $info->timelimit = 0;
                $info->overduehandling = 'autosubmit';
                $info->graceperiod = 0;
                $info->preferredbehaviour = 'deferredfeedback';
                $info->canredoquestions = 0;
                $info->attempts = 0;
                $info->attemptonlast = 0;
                $info->grademethod = 1;
                $info->decimalpoints = 2;
                $info->questiondecimalpoints = -1;
                $info->reviewattempt = 69888;
                $info->reviewcorrectness = 4352;
                $info->reviewmarks = 4352;
                $info->reviewmaxmarks = 0;
                $info->reviewspecificfeedback = 4352;
                $info->reviewgeneralfeedback = 4352;
                $info->reviewrightanswer = 4352;
                $info->reviewoverallfeedback = 4352;
                $info->questionsperpage = 1;
                $info->navmethod = 'free';
                $info->shuffleanswers = 1;
                $info->sumgrades = 0;
                $info->grade = 100;
                $info->showuserpicture = 0;
                $info->showblocks = 0;
                $info->quizpassword = ''; // Note: quiz module expects 'quizpassword' not 'password'.
                $info->subnet = '';
                $info->delay1 = 0;
                $info->delay2 = 0;
                $info->browsersecurity = '-';
                break;

            case 'resource':
                $info->files = 0;
                $info->display = 0;
                $info->showsize = 1;
                $info->showtype = 0;
                $info->showdate = 0;
                $info->filterfiles = 0;
                break;

            case 'scorm':
                $info->maxgrade = 100;
                $info->grademethod = 1;
                $info->whatgrade = 0;
                $info->maxattempt = 0;
                $info->forcecompleted = 0;
                $info->forcenewattempt = 0;
                $info->displayattemptstatus = 1;
                $info->displaycoursestructure = 0;
                $info->updatefreq = 0;
                $info->width = '100%';
                $info->height = '500';
                break;

            case 'survey':
                $info->template = 0;
                break;

            case 'url':
                $info->externalurl = $ai['externalurl'] ?? $ai['url'] ?? 'https://example.com';
                $info->display = 0;
                $info->printintro = 1;
                break;

            case 'wiki':
                $info->wikimode = 'collaborative';
                $info->firstpagetitle = 'First Page';
                $info->defaultformat = 'html';
                $info->forceformat = 1;
                break;

            case 'workshop':
                $info->grade = 100;
                $info->gradinggrade = 100;
                $info->strategy = 'accumulative';
                $info->evaluation = 'best';
                $info->latesubmissions = 0;
                $info->maxbytes = 0;
                $info->nattachments = 0;
                $info->submissiontypetext = 1;
                $info->submissiontypefile = 1;
                // Required editor fields with itemid.
                $info->instructauthorseditor = ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0];
                $info->instructreviewerseditor = ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0];
                $info->conclusioneditor = ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0];
                // Grade categories.
                $info->gradecategory = 0;
                $info->gradinggradecategory = 0;
                // Date fields (0 = no restriction).
                $info->submissionstart = 0;
                $info->submissionend = 0;
                $info->assessmentstart = 0;
                $info->assessmentend = 0;
                break;

            default:
                return null;
        }

        return $info;
    }
}
