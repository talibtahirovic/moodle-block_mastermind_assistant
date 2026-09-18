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
 * Tests for the course data payload that leaves the plugin for the dashboard.
 *
 * @package    block_mastermind_assistant
 * @copyright  2026 The Namers <info@mastermindassistant.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_mastermind_assistant\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/gradelib.php');

/**
 * Tests for the course data payload that leaves the plugin for the dashboard.
 *
 * @covers \block_mastermind_assistant\external\get_course_data
 * @runTestsInSeparateProcesses
 */
final class get_course_data_test extends \advanced_testcase {

    /** @var string Keys that identify a learner. Never allowed anywhere in the outbound payload. */
    const IDENTITY_KEY_RE = '/^(userid|user_id|username|email|firstname|lastname|idnumber|phone1|phone2)$/i';

    /**
     * The feedback payload is aggregated and carries no user ids.
     */
    public function test_feedback_payload_is_aggregated_and_anonymous(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $feedback = $gen->create_module('feedback', ['course' => $course->id, 'name' => 'Course evaluation']);
        $itemid = $DB->insert_record('feedback_item', (object) [
            'feedback' => $feedback->id, 'template' => 0, 'name' => 'Overall satisfaction',
            'label' => '', 'presentation' => '', 'typ' => 'numeric', 'hasvalue' => 1,
            'position' => 1, 'required' => 0, 'dependitem' => 0, 'dependvalue' => '', 'options' => '',
        ]);
        $user = $gen->create_user();
        $compid = $DB->insert_record('feedback_completed', (object) [
            'feedback' => $feedback->id, 'userid' => $user->id, 'timemodified' => 1720000000,
            'random_response' => 0, 'anonymous_response' => 2, 'courseid' => 0,
        ]);
        $DB->insert_record('feedback_value', (object) [
            'course_id' => 0, 'item' => $itemid, 'completed' => $compid,
            'tmp_completed' => 0, 'value' => '4',
        ]);

        $result = get_course_data::execute($course->id);
        $this->assertTrue($result['success'], $result['data']);
        $data = json_decode($result['data'], true);

        $this->assertArrayHasKey('activities', $data['feedback']);
        $this->assertEqualsWithDelta(4.0, $data['feedback']['satisfaction'], 0.05);
        $this->assertSame('Course evaluation', $data['feedback']['activities'][0]['name']);
        // Anonymized: no userid anywhere in the feedback subtree.
        $this->assertStringNotContainsString('userid', json_encode($data['feedback']));
    }

    /**
     * No learner identity leaves the plugin: no per-user arrays and no
     * identity-shaped key anywhere in the serialised payload.
     */
    public function test_payload_carries_no_learner_identity(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $student = $gen->create_user(['firstname' => 'Zelda', 'lastname' => 'Quintrell',
            'email' => 'zelda.quintrell@example.com', 'username' => 'zquintrell']);
        $gen->enrol_user($student->id, $course->id, 'student');
        $teacher = $gen->create_user(['firstname' => 'Yorick', 'lastname' => 'Pemberton',
            'email' => 'yorick.pemberton@example.com', 'username' => 'ypemberton']);
        $gen->enrol_user($teacher->id, $course->id, 'editingteacher');
        $forum = $gen->create_module('forum', ['course' => $course->id]);
        $forumgen = $gen->get_plugin_generator('mod_forum');
        $discussion = $forumgen->create_discussion([
            'course' => $course->id, 'forum' => $forum->id, 'userid' => $student->id,
            'name' => 'Zelda asks about dosing',
        ]);
        $forumgen->create_post(['discussion' => $discussion->id, 'userid' => $teacher->id,
            'parent' => $discussion->firstpost, 'message' => 'Reply from Yorick Pemberton']);
        $itemrecord = $gen->create_grade_item(['courseid' => $course->id, 'itemname' => 'Final Quiz',
            'itemtype' => 'manual', 'grademax' => 10]);
        \grade_item::fetch(['id' => $itemrecord->id])->update_final_grade($student->id, 8.0);

        $result = get_course_data::execute($course->id);
        $this->assertTrue($result['success'], $result['data']);
        $json = $result['data'];
        $data = json_decode($json, true);

        foreach (['users', 'forum_posts', 'completions'] as $perlearnerkey) {
            $this->assertArrayNotHasKey($perlearnerkey, $data, "per-learner array '{$perlearnerkey}' must not be sent");
        }
        $this->assert_no_identity_keys($data);
        foreach (['Zelda', 'Quintrell', 'zquintrell', 'zelda.quintrell', 'Yorick', 'Pemberton',
            'ypemberton', 'yorick.pemberton'] as $needle) {
            $this->assertStringNotContainsString($needle, $json, "learner identity '{$needle}' leaked");
        }
    }

    /**
     * Enrolment is reported as counts: total, active and per role.
     */
    public function test_enrolment_is_aggregated_to_counts(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        foreach ([1, 2, 3] as $i) {
            $student = $gen->create_user(['lastaccess' => $i === 1 ? time() - HOURSECS : 0]);
            $gen->enrol_user($student->id, $course->id, 'student');
        }
        $teacher = $gen->create_user();
        $gen->enrol_user($teacher->id, $course->id, 'editingteacher');

        $result = get_course_data::execute($course->id);
        $this->assertTrue($result['success'], $result['data']);
        $data = json_decode($result['data'], true);

        $this->assertSame(4, $data['enrolment']['enrolled']);
        $this->assertSame(1, $data['enrolment']['active_30d']);
        $this->assertSame(['editingteacher' => 1, 'student' => 3], $data['enrolment']['roles']);
    }

    /**
     * Grade items keep their definition but carry a per-item summary instead
     * of per-learner grade rows.
     */
    public function test_grades_are_aggregated_per_item(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $itemrecord = $gen->create_grade_item(['courseid' => $course->id, 'itemname' => 'Final Quiz',
            'itemtype' => 'manual', 'grademax' => 10, 'gradepass' => 5]);
        $item = \grade_item::fetch(['id' => $itemrecord->id]);
        foreach ([8.0, 4.0, 6.0] as $grade) {
            $user = $gen->create_user();
            $gen->enrol_user($user->id, $course->id, 'student');
            $item->update_final_grade($user->id, $grade);
        }

        $result = get_course_data::execute($course->id);
        $this->assertTrue($result['success'], $result['data']);
        $data = json_decode($result['data'], true);

        $quiz = null;
        foreach ($data['grades'] as $gradeitem) {
            if ($gradeitem['item']['itemname'] === 'Final Quiz') {
                $quiz = $gradeitem;
            }
        }
        $this->assertNotNull($quiz);
        $this->assertArrayNotHasKey('grades', $quiz, 'per-learner grade rows must not be sent');
        $this->assertSame(3, $quiz['summary']['graded']);
        $this->assertEqualsWithDelta(6.0, $quiz['summary']['avg_finalgrade'], 0.01);
        $this->assertEqualsWithDelta(4.0, $quiz['summary']['min_finalgrade'], 0.01);
        $this->assertEqualsWithDelta(8.0, $quiz['summary']['max_finalgrade'], 0.01);
        $this->assertSame(2, $quiz['summary']['passed']);
    }

    /**
     * Activity completion is reported as counts overall and per activity.
     */
    public function test_completion_is_aggregated_per_activity(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['enablecompletion' => 1]);
        $page = $gen->create_module('page', ['course' => $course->id, 'name' => 'Welcome',
            'completion' => COMPLETION_TRACKING_MANUAL]);
        $states = [COMPLETION_COMPLETE, COMPLETION_COMPLETE, COMPLETION_INCOMPLETE];
        foreach ($states as $state) {
            $user = $gen->create_user();
            $gen->enrol_user($user->id, $course->id, 'student');
            $DB->insert_record('course_modules_completion', (object) [
                'coursemoduleid' => $page->cmid, 'userid' => $user->id, 'completionstate' => $state,
                'overrideby' => null, 'timemodified' => time(),
            ]);
        }

        $result = get_course_data::execute($course->id);
        $this->assertTrue($result['success'], $result['data']);
        $data = json_decode($result['data'], true);

        $this->assertSame(3, $data['completion_summary']['records']);
        $this->assertSame(2, $data['completion_summary']['completed']);
        $this->assertSame([[
            'coursemoduleid' => (int) $page->cmid, 'name' => 'Welcome', 'module' => 'page',
            'tracked' => 3, 'completed' => 2,
        ]], $data['completion_summary']['per_activity']);
    }

    /**
     * Forum activity is reported as counts per forum and posts per month,
     * without post text or authors.
     */
    public function test_forum_activity_is_counted_without_authors_or_text(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $user = $gen->create_user();
        $gen->enrol_user($user->id, $course->id, 'student');
        $forum = $gen->create_module('forum', ['course' => $course->id, 'name' => 'Questions']);
        $forumgen = $gen->get_plugin_generator('mod_forum');
        $discussion = $forumgen->create_discussion(['course' => $course->id, 'forum' => $forum->id,
            'userid' => $user->id, 'name' => 'Secret subject']);
        $forumgen->create_post(['discussion' => $discussion->id, 'userid' => $user->id,
            'parent' => $discussion->firstpost, 'message' => 'Secret reply body']);

        $result = get_course_data::execute($course->id);
        $this->assertTrue($result['success'], $result['data']);
        $data = json_decode($result['data'], true);

        $this->assertSame([[
            'name' => 'Questions', 'discussions' => 1, 'posts' => 2, 'replies' => 1,
        ]], $data['forums']);
        $this->assertSame(2, $data['forum_activity']['posts_total']);
        $this->assertSame(1, $data['forum_activity']['discussions_total']);
        $this->assertArrayHasKey('posts_by_month', $data['forum_activity']);
        $this->assertSame(2, array_sum($data['forum_activity']['posts_by_month']));
        $this->assertStringNotContainsString('Secret subject', $result['data']);
        $this->assertStringNotContainsString('Secret reply body', $result['data']);
    }

    /**
     * Recursively assert no identity-shaped key exists anywhere in the payload.
     *
     * @param array $data Payload subtree.
     * @param string $path Breadcrumb for failure messages.
     */
    private function assert_no_identity_keys(array $data, string $path = 'root'): void {
        foreach ($data as $key => $value) {
            if (is_string($key)) {
                $this->assertDoesNotMatchRegularExpression(self::IDENTITY_KEY_RE, $key,
                    "identity key at {$path}.{$key}");
            }
            if (is_array($value)) {
                $this->assert_no_identity_keys($value, $path . '.' . $key);
            }
        }
    }
}
