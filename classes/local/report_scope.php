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
 * Course scoping for report builder SQL.
 *
 * @package    block_mastermind_assistant
 * @copyright  2026 The Namers <info@mastermindassistant.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_mastermind_assistant\local;

/**
 * Confines a validated report statement to one course before it runs.
 *
 * report_sql_validator proves the statement is a single SELECT over
 * allow-listed {placeholder} tables, but it cannot prove the joins actually
 * filter by course: 'JOIN {course} c ON c.id = :courseid' passes while the
 * grade rows beside it stay site-wide. So instead of trusting the statement,
 * every {table} it names is replaced with a derived table that already holds
 * only this course's rows and only the columns the AI is told about. Whatever
 * the statement does, it cannot see another course, a password hash or a page
 * body. This applies equally to SQL from the dashboard and SQL replayed by the
 * browser for exports.
 */
class report_scope {
    /** Alias of the base table inside each scoped derived table. */
    private const INNER = 'mmt';

    /**
     * Words that may follow a table but are not an alias.
     *
     * @var string[]
     */
    private const NOT_ALIASES = [
        'on', 'where', 'join', 'left', 'right', 'inner', 'outer', 'cross', 'full', 'natural',
        'straight_join', 'group', 'order', 'having', 'limit', 'offset', 'using', 'window', 'for',
        'lock', 'union', 'and', 'or',
    ];

    /**
     * Course contexts: the course itself and its activities.
     */
    private const COURSE_CONTEXTS = "SELECT mcx.id FROM {context} mcx
         WHERE (mcx.contextlevel = 50 AND mcx.instanceid = :courseid)
            OR (mcx.contextlevel = 70 AND mcx.instanceid IN
                (SELECT mcm.id FROM {course_modules} mcm WHERE mcm.course = :courseid))";

    /**
     * Columns exposed per table, and the condition that keeps a row inside the
     * course (on the inner alias 'mmt'; '' means the table holds no course data).
     *
     * Keep in step with the dashboard's MOODLE_REPORTING_SCHEMA_REFERENCE.
     *
     * @return array<string, array{0: string[], 1: string}>
     */
    public static function policy(): array {
        $bycourse = 'mmt.course = :courseid';
        $bycourseid = 'mmt.courseid = :courseid';
        $named = ['id', 'course', 'name'];
        $policy = [
            'user' => [
                ['id', 'firstname', 'lastname', 'email', 'lastaccess', 'suspended', 'deleted'],
                "(mmt.id IN (SELECT mue.userid FROM {user_enrolments} mue
                               JOIN {enrol} me ON me.id = mue.enrolid
                              WHERE me.courseid = :courseid)
                  OR mmt.id IN (SELECT mra.userid FROM {role_assignments} mra
                                 WHERE mra.contextid IN (" . self::COURSE_CONTEXTS . ")))",
            ],
            'course' => [
                ['id', 'fullname', 'shortname', 'category', 'startdate', 'enddate', 'visible'],
                'mmt.id = :courseid',
            ],
            'course_sections' => [['id', 'course', 'section', 'name', 'visible'], $bycourse],
            'course_modules' => [
                ['id', 'course', 'module', 'instance', 'section', 'added', 'visible', 'completion', 'deletioninprogress'],
                $bycourse,
            ],
            'modules' => [['id', 'name'], ''],
            'enrol' => [['id', 'courseid', 'enrol', 'status'], $bycourseid],
            'user_enrolments' => [
                ['id', 'enrolid', 'userid', 'status', 'timestart', 'timeend', 'timecreated'],
                'mmt.enrolid IN (SELECT me.id FROM {enrol} me WHERE me.courseid = :courseid)',
            ],
            'role_assignments' => [
                ['id', 'roleid', 'contextid', 'userid', 'timemodified'],
                'mmt.contextid IN (' . self::COURSE_CONTEXTS . ')',
            ],
            'context' => [
                ['id', 'contextlevel', 'instanceid', 'path', 'depth'],
                'mmt.id IN (' . self::COURSE_CONTEXTS . ')',
            ],
            'grade_items' => [
                ['id', 'courseid', 'itemname', 'itemtype', 'itemmodule', 'iteminstance', 'grademax', 'grademin', 'hidden'],
                $bycourseid,
            ],
            'grade_grades' => [
                ['id', 'itemid', 'userid', 'rawgrade', 'finalgrade', 'timecreated', 'timemodified'],
                'mmt.itemid IN (SELECT mgi.id FROM {grade_items} mgi WHERE mgi.courseid = :courseid)',
            ],
            'course_completions' => [
                ['id', 'userid', 'course', 'timeenrolled', 'timestarted', 'timecompleted'],
                $bycourse,
            ],
            'course_modules_completion' => [
                ['id', 'coursemoduleid', 'userid', 'completionstate', 'timemodified'],
                'mmt.coursemoduleid IN (SELECT mcm.id FROM {course_modules} mcm WHERE mcm.course = :courseid)',
            ],
            'quiz' => [
                ['id', 'course', 'name', 'timeopen', 'timeclose', 'grade', 'sumgrades', 'attempts'],
                $bycourse,
            ],
            'quiz_attempts' => [
                ['id', 'quiz', 'userid', 'attempt', 'state', 'sumgrades', 'timestart', 'timefinish', 'preview'],
                'mmt.quiz IN (SELECT mq.id FROM {quiz} mq WHERE mq.course = :courseid)',
            ],
            'quiz_grades' => [
                ['id', 'quiz', 'userid', 'grade', 'timemodified'],
                'mmt.quiz IN (SELECT mq.id FROM {quiz} mq WHERE mq.course = :courseid)',
            ],
            'question' => [
                ['id', 'name', 'qtype', 'defaultmark', 'timecreated'],
                'mmt.id IN (SELECT mqv.questionid FROM {question_versions} mqv
                              JOIN {question_bank_entries} mqbe ON mqbe.id = mqv.questionbankentryid
                              JOIN {question_categories} mqc ON mqc.id = mqbe.questioncategoryid
                             WHERE mqc.contextid IN (' . self::COURSE_CONTEXTS . '))',
            ],
            'assign' => [['id', 'course', 'name', 'duedate', 'allowsubmissionsfromdate', 'grade'], $bycourse],
            'assign_submission' => [
                ['id', 'assignment', 'userid', 'status', 'attemptnumber', 'timecreated', 'timemodified'],
                'mmt.assignment IN (SELECT ma.id FROM {assign} ma WHERE ma.course = :courseid)',
            ],
            'assign_grades' => [
                ['id', 'assignment', 'userid', 'grade', 'grader', 'timecreated', 'timemodified'],
                'mmt.assignment IN (SELECT ma.id FROM {assign} ma WHERE ma.course = :courseid)',
            ],
            'forum' => [['id', 'course', 'name', 'type'], $bycourse],
            'forum_discussions' => [
                ['id', 'course', 'forum', 'name', 'userid', 'firstpost', 'timemodified'],
                $bycourse,
            ],
            'forum_posts' => [
                ['id', 'discussion', 'parent', 'userid', 'subject', 'message', 'created', 'modified'],
                'mmt.discussion IN (SELECT mfd.id FROM {forum_discussions} mfd WHERE mfd.course = :courseid)',
            ],
            'lesson' => [['id', 'course', 'name', 'grade', 'available', 'deadline'], $bycourse],
            'lesson_attempts' => [
                ['id', 'lessonid', 'pageid', 'userid', 'answerid', 'correct', 'timeseen'],
                'mmt.lessonid IN (SELECT ml.id FROM {lesson} ml WHERE ml.course = :courseid)',
            ],
            'glossary' => [$named, $bycourse],
            'glossary_entries' => [
                ['id', 'glossaryid', 'userid', 'concept', 'definition', 'approved', 'timecreated', 'timemodified'],
                'mmt.glossaryid IN (SELECT mg.id FROM {glossary} mg WHERE mg.course = :courseid)',
            ],
            'logstore_standard_log' => [
                ['id', 'eventname', 'component', 'action', 'target', 'crud', 'edulevel', 'objecttable', 'objectid',
                    'contextid', 'contextlevel', 'contextinstanceid', 'userid', 'courseid', 'relateduserid', 'timecreated'],
                $bycourseid,
            ],
            'groups' => [['id', 'courseid', 'name', 'timecreated'], $bycourseid],
            'groups_members' => [
                ['id', 'groupid', 'userid', 'timeadded'],
                'mmt.groupid IN (SELECT mgr.id FROM {groups} mgr WHERE mgr.courseid = :courseid)',
            ],
            'user_lastaccess' => [['id', 'userid', 'courseid', 'timeaccess'], $bycourseid],
            'scorm' => [$named, $bycourse],
            'scorm_attempt' => [
                ['id', 'userid', 'scormid', 'attempt'],
                'mmt.scormid IN (SELECT ms.id FROM {scorm} ms WHERE ms.course = :courseid)',
            ],
            'scorm_scoes_value' => [
                ['id', 'attemptid', 'scoid', 'elementid', 'value', 'timemodified'],
                'mmt.attemptid IN (SELECT msa.id FROM {scorm_attempt} msa
                                     JOIN {scorm} ms ON ms.id = msa.scormid
                                    WHERE ms.course = :courseid)',
            ],
            'scorm_element' => [['id', 'element'], ''],
        ];
        // Other activity types: name lookups only.
        $nametables = [
            'bigbluebuttonbn', 'book', 'choice', 'data', 'feedback', 'folder', 'h5pactivity', 'imscp',
            'label', 'lti', 'page', 'resource', 'url', 'wiki', 'workshop',
        ];
        foreach ($nametables as $table) {
            $policy[$table] = [$named, $bycourse];
        }
        return $policy;
    }

    /**
     * Replace every {table} in a validated statement with its course-scoped,
     * column-limited derived table. Run after report_sql_validator::validate()
     * and before prepare_courseid_bindings(), which binds every :courseid this
     * adds.
     *
     * @param string $sql Statement that passed report_sql_validator.
     * @return string The scoped statement.
     * @throws \coding_exception When a table has no policy (never for validated SQL).
     */
    public static function apply(string $sql): string {
        $policy = self::policy();
        $count = 0;
        return preg_replace_callback(
            '/\{([a-z0-9_]+)\}(\s+(?:as\s+)?([a-z_][a-z0-9_]*))?/i',
            function (array $m) use ($policy, &$count): string {
                $table = $m[1];
                if (!isset($policy[$table])) {
                    throw new \coding_exception('No report scope for table {' . $table . '}');
                }
                [$columns, $where] = $policy[$table];
                $select = implode(', ', array_map(fn($c) => self::INNER . '.' . $c, $columns));
                $derived = '(SELECT ' . $select . ' FROM {' . $table . '} ' . self::INNER
                    . ($where === '' ? '' : ' WHERE ' . $where) . ')';
                $follow = $m[2] ?? '';
                $word = strtolower($m[3] ?? '');
                if ($word !== '' && !in_array($word, self::NOT_ALIASES, true)) {
                    // Keep the statement's own alias (' u' or ' AS u').
                    return $derived . $follow;
                }
                // Unaliased table: a derived table needs a name; columns were
                // referenced unqualified, so any unique name works.
                $count++;
                return $derived . ' mms' . $count . $follow;
            },
            $sql
        );
    }

    /**
     * Turn a validated statement into SQL and params ready for
     * $DB->get_records_sql(): trailing LIMIT removed, every table scoped to the
     * course, every :courseid bound to the server-side course id.
     *
     * @param string $sql Statement that passed report_sql_validator.
     * @param int $courseid Server-side validated course id.
     * @return array [string $sql, array $params]
     */
    public static function prepare(string $sql, int $courseid): array {
        $scoped = self::apply(report_sql_validator::strip_trailing_limit($sql));
        return report_sql_validator::prepare_courseid_bindings($scoped, $courseid);
    }
}
