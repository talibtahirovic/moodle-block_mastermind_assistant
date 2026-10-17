# Changelog

## v4.2.2 (2026101700) — 2026-10-17

### Security
- AI Report Builder: every report is now confined to the course it runs in. The
  validator only proved a statement mentioned `:courseid`, and exports replay SQL from
  the browser, so a teacher able to write SQL could read other courses' grades, posts
  or participants (`JOIN {course} c ON c.id = :courseid` beside unscoped rows). Each
  `{table}` is now replaced at execution time by a derived table holding only this
  course's rows and only the documented columns (`local\report_scope`), so `{user}`
  holds only the course's participants and `u.*` can no longer reach password hashes.
  Nothing extra is sent to the service: reports still run entirely in Moodle.
- Report SQL stays read-only: functions that change state or read a table named in a
  string (`nextval`, `setval`, `set_config`, `get_lock`, `lo_*`, `dblink*`,
  `*_to_xml`) and locking reads (`FOR SHARE`, `LOCK IN SHARE MODE`) are rejected, and
  columns must be qualified by alias, not by `{placeholder}`.

### Fixed
- Detailed metrics: "Primary Dropout Point" and "Most Incomplete Activity" now name the
  activity (e.g. "Module 4 knowledge check") instead of its module type ("page"). The
  dropout point is the activity learners most often touched last in the past 90 days.
- AI Report Builder: reports that list activities can now show every activity's name.
  The page, URL, file, book, folder, label, feedback, choice, database, H5P, IMS
  content, external tool, wiki, workshop and BigBlueButton tables are allow-listed for
  name lookups; their body text stays out (`content` is a denied column).

### Compatibility
- Dashboards from 2026-10-17 describe the new tables only to plugins declaring
  2026101700 or later, so older plugins keep receiving SQL their allow-list accepts.

## v4.2.1 (2026101600) — 2026-10-16

### Fixed
- The block's "Drop-off section" tile now names the section the fewest learners completed
  anything in, the same definition as the detailed metrics and the enterprise reports.
  It used to compare completed rows with all completion rows, which reads close to 100%
  everywhere on typical data, so the first section won by default.
- Sections whose activities don't track completion no longer count as a drop-off point,
  and a tie goes to the earlier section.

## v4.2.0 (2026092900) — 2026-09-29

Marketplace release. Includes the v4.1.0 privacy release (no learner identity leaves the
site) and the v4.1.1 maintenance fixes below.

### Added
- Evaluation data: every Feedback activity now carries its course-module id (`cmid`),
  so the dashboard's Course Improvement Brief can link each finding to the activity's own
  analysis page (`/mod/feedback/analysis.php?id=`). No learner data is added.

### Compatibility
- Dashboards before 2026-09-29 reject the new `cmid` key on the enterprise metrics
  ingest. Update the dashboard first, then the plugin.

## v4.1.1 (2026092800) — 2026-09-28

Maintenance release, no functional changes.

### Fixed
- Moodle coding style: resolved all codechecker errors and warnings (multi-line call
  formatting, unneeded `MOODLE_INTERNAL` checks in namespaced classes).
- Rebuilt `amd/build/recommendations.min.js` with Moodle grunt so the build matches
  `amd/src`.

## v4.1.0 (2026091700) — 2026-09-17

Privacy release. Ship before any third-party review of the plugin.

### Changed
- **No learner identity leaves the site.** The course payload sent for AI analysis
  (`get_course_data`) no longer contains enrolled-user rows (name, username, email),
  per-learner grade rows, per-learner completion rows or forum posts. They are replaced
  by aggregates: enrolment counts per role and active-learner count, per-item grade
  summaries (graded count, average, min, max, pass count), per-activity completion
  counts, forum post/discussion/reply counts per forum and posts per month.
- Every analysis call that forwards a browser-supplied payload (`get_full_analysis`,
  `get_ai_recommendations`, `get_updated_structure`) now passes it through
  `local\outbound_sanitizer`, which removes per-learner arrays and identity keys
  whatever the client sent.
- All dashboard requests declare `X-Plugin-Version` (and `X-Enterprise-Plugin-Version`
  when the enterprise companion is installed).
- The settings page warns when the dashboard reports a newer required version.
- Privacy provider and privacy strings updated to describe the aggregate-only payload
  and the handling of evaluation comments.

### Evaluation data
- Every free-text evaluation answer is now sent (values are read in pages of 500, the
  previous 50-comment cap is gone) as `{questionName, questionType, text}`, truncated at
  400 characters, so the dashboard can theme reflection questions ("what will you change
  in your practice?") apart from general comments and count themes against the true
  number of answers. The dashboard never stores the text.
- Refinement requests carry the evaluation aggregates (`evaluation_data`) so a refined
  analysis still sees learner feedback.
- The detailed-metrics modal shows each quoted comment with its question.

### Analysis modal
- One scroll region for the whole result instead of two independently scrolling
  columns, and the course page no longer scrolls behind an open modal.
- Evaluation insights shown as a highlighted card ("What learners are saying") with the
  themes as a list.

### Detailed metrics modal
- The evaluation block shows each survey's response count and its own average, a
  readable question table, and the five most recent answers with their question.

### Compatibility
- Dashboards accept identity-bearing payloads from plugin versions before 2026091700
  until **2027-03-31**, stripping the identity on arrival and logging a deprecation
  warning. From that date, or from any plugin declaring version 2026091700 or later,
  such payloads are refused with HTTP 400 `LEARNER_IDENTITY`.
