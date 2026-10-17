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
 * Tests for report_scope.
 *
 * @package    block_mastermind_assistant
 * @copyright  2026 The Namers <info@mastermindassistant.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_mastermind_assistant\local;

/**
 * Report SQL can only ever see the validated course.
 *
 * @covers \block_mastermind_assistant\local\report_scope
 */
final class report_scope_test extends \advanced_testcase {
    /**
     * Two courses, each with one enrolled learner and one graded item.
     *
     * @return array [course A, course B, learner A, learner B]
     */
    private function two_courses(): array {
        global $DB;
        $gen = $this->getDataGenerator();
        $result = [];
        foreach (['A', 'B'] as $label) {
            $course = $gen->create_course(['shortname' => 'C' . $label]);
            $user = $gen->create_user([
                'firstname' => 'Learner' . $label,
                'email' => 'learner' . strtolower($label) . '@example.invalid',
            ]);
            $gen->enrol_user($user->id, $course->id, 'student');
            $itemid = $DB->insert_record('grade_items', (object) [
                'courseid' => $course->id, 'itemtype' => 'manual', 'itemname' => 'Item ' . $label,
                'grademax' => 100, 'grademin' => 0, 'timecreated' => time(), 'timemodified' => time(),
            ]);
            $DB->insert_record('grade_grades', (object) [
                'itemid' => $itemid, 'userid' => $user->id, 'finalgrade' => $label === 'A' ? 11 : 99,
                'timecreated' => time(), 'timemodified' => time(),
            ]);
            $result[] = [$course, $user];
        }
        return [$result[0][0], $result[1][0], $result[0][1], $result[1][1]];
    }

    /**
     * Run validated SQL the way generate_report does.
     *
     * @param string $sql
     * @param int $courseid
     * @return array
     */
    private function run_scoped(string $sql, int $courseid): array {
        global $DB;
        $verdict = report_sql_validator::validate($sql);
        $this->assertTrue($verdict['ok'], 'Expected the attack SQL to pass validation: ' . ($verdict['reason'] ?? ''));
        [$execsql, $params] = report_scope::prepare($sql, $courseid);
        return array_values(array_map(fn($r) => (array) $r, $DB->get_records_sql($execsql, $params)));
    }

    /**
     * A join that only mentions :courseid on {course} used to return every
     * course's grades; now it returns this course's only.
     */
    public function test_course_join_cannot_widen_grade_rows(): void {
        global $DB;
        $this->resetAfterTest();
        [$a] = $this->two_courses();
        $sql = 'SELECT gg.id, gg.finalgrade FROM {grade_grades} gg JOIN {course} c ON c.id = :courseid';

        // Control: bound but unscoped, the statement reads both courses (the gap).
        [$unscoped, $params] = report_sql_validator::prepare_courseid_bindings($sql, $a->id);
        $this->assertCount(2, $DB->get_records_sql($unscoped, $params));

        $rows = $this->run_scoped($sql, $a->id);

        $this->assertCount(1, $rows);
        $this->assertEquals(11, $rows[0]['finalgrade']);
    }

    /**
     * Learners of other courses are invisible, and {user} only exposes the
     * documented columns — 'u.*' cannot reach the password hash.
     */
    public function test_user_rows_and_columns_are_confined(): void {
        $this->resetAfterTest();
        [$a, , $learnera] = $this->two_courses();

        $rows = $this->run_scoped(
            'SELECT u.*, la.id AS lastaccessid FROM {user} u
               LEFT JOIN {user_lastaccess} la ON la.userid = u.id
               JOIN {course} c ON c.id = :courseid',
            $a->id
        );

        $this->assertSame([$learnera->email], array_column($rows, 'email'));
        $this->assertArrayNotHasKey('password', $rows[0]);
        $this->assertArrayNotHasKey('username', $rows[0]);
    }

    /**
     * Exports replay SQL from the browser; the same confinement applies.
     */
    public function test_export_replay_is_confined(): void {
        $this->resetAfterTest();
        [$a] = $this->two_courses();

        [, $rows] = report_exporter::prepare(
            'SELECT gg.finalgrade AS grade FROM {grade_grades} gg JOIN {course} c ON c.id = :courseid',
            $a->id
        );

        $this->assertSame(['11.00000'], array_map(fn($r) => (string) $r['grade'], $rows));
    }

    /**
     * Every policy table runs against this Moodle's schema (the column lists
     * exist) and is the same set as the validator's allow-list.
     */
    public function test_every_policy_table_executes(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();

        $this->assertEqualsCanonicalizing(report_sql_validator::ALLOWED_TABLES, array_keys(report_scope::policy()));
        foreach (array_keys(report_scope::policy()) as $table) {
            [$sql, $params] = report_scope::prepare('SELECT x.* FROM {' . $table . '} x', $course->id);
            $DB->get_records_sql($sql, $params, 0, 1);
        }
    }

    /**
     * Aliases are kept; unaliased tables get a generated one; following
     * keywords are left in place.
     */
    public function test_apply_keeps_or_adds_aliases(): void {
        // The AI may write 'AS alias'; the rewrite must keep it.
        $sql = report_scope::apply(
            // phpcs:ignore moodle.Strings.ForbiddenStrings.Found
            'SELECT c.id, cnt FROM {course} c JOIN {course_sections} AS cs ON cs.course = c.id ' .
            'LEFT JOIN {modules} ON 1 = 1 WHERE c.id = :courseid'
        );

        $this->assertStringContainsString(') c JOIN', $sql);
        $this->assertStringContainsString(') AS cs ON', $sql);
        $this->assertMatchesRegularExpression('/\) mms1 ON 1 = 1/', $sql);
        $this->assertStringContainsString('FROM {course} mmt WHERE mmt.id = :courseid', $sql);
        $this->assertStringNotContainsString('FROM {modules} mmt WHERE', $sql);
    }

    /**
     * A table without a policy is a programming error, not a silent pass.
     */
    public function test_unknown_table_throws(): void {
        $this->expectException(\coding_exception::class);
        report_scope::apply('SELECT x.id FROM {config} x');
    }
}
