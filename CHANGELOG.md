# Changelog

## v4.5.1 (2026112102) — 2026-11-21

### Changed
- Code-quality release: nothing changes for teachers or administrators. The eight content
  generators share one helper for the list of course activities sent with a request; a
  malformed list from the browser now falls back to the course's visible activities, as an
  empty list already did. Language strings are in alphabetical order (same texts), the
  course-context form passes the JavaScript lint, and the tests share one fixture for
  courses with rated Feedback activities.

### Release notes
- Same behaviour, privacy declarations and texts as v4.5.0, and no database changes. Sites
  can upgrade at any time; the dashboard needs nothing new.

## v4.5.0 (2026112101) — 2026-11-21

### Privacy
- The privacy summary no longer says that identity a learner typed into an answer or
  submission is removed in Moodle before anything is sent. It now says that learners'
  free text is cleaned in Moodle but not completely: a name inside a sentence, another
  person's name in a submission, and anything inside a PDF or Word file can still reach
  the dashboard. English and German.
- The evaluation declaration now names the average rating per question, which has always
  been sent alongside the answers and answer counts.
- Every request a logged-in user makes to the dashboard carries a pseudonym of that user
  (the same one the enterprise companion sends with decisions), and the dashboard keeps it
  with AI requests, so it can tell requests apart by person without knowing who it is. No
  pseudonym is sent for guests, command-line scripts or scheduled tasks; during "log in as"
  it is the real user's. Declared in the privacy metadata and the README.

### Release notes
- Raise the version (proposed v4.5.0): a new class and request header, and changed
  language strings that sites see only after the upgrade.
- Deploy a dashboard that stores the pseudonym first; older dashboards ignore the header.
- The enterprise companion's next release (v1.5.1) records the real user during "log in as"
  as well, so both plugins name the same person for the same click.

## v4.4.0 (2026112100) — 2026-11-21

### Security
- Applying book chapters, lesson pages or selected quiz questions now writes only into an
  activity of the course passed in, and checks "Manage activities" on the activity itself.
  Before, a teacher of one course could write into another course's book, lesson or quiz
  by pairing their own course id with a foreign course-module id, and a prohibition set
  on the activity was not applied.
- The AI usage policy is now enforced on the server. Every web service that sends course
  or learner data to the Mastermind dashboard refuses until the user has accepted the
  policy; before, only the browser asked, so a direct web-service call skipped it.
  Listing grading items, applying a generated draft and other calls that send nothing
  to the AI service are unchanged.

### Privacy
- Evaluation answers (Feedback and Questionnaire) are redacted in Moodle before they are
  sent: links, email addresses, phone and id numbers, and signatures ("Regards, Jane
  Okonkwo", "-- Jane", "Prof. Lindqvist", "My name is …") become "[… removed]"
  placeholders. Same rules as the dashboard's own redaction, which still runs as a second
  pass. Applies to the course analysis and, through the shared collectors, to the
  enterprise companion's daily collection.
- Grading assist removes the student's own name, email address, username and id number
  from the submission text and essay answer before sending (every member's, for a group
  submission). A file is sent as "submission.pdf/.docx/.txt" instead of its original name,
  and a plain-text file is redacted like typed text. Names inside PDF and Word files can't
  be removed in Moodle.
- Grade averages and SCORM scores and rates behind fewer than five learners are no longer
  sent (sent as null); grade minimum and maximum are not sent at all. Counts are sent as
  before, and evaluation ratings keep going out with their n. The block's own screens in
  Moodle still show every figure.
- The course analysis no longer sends fields no prompt uses: forum statistics, audit flags
  and activity intros. The refinement path now gets the same treatment as a full analysis.
- The privacy declaration now lists everything sent to the dashboard: teacher-provided
  text and files, and the site address and administrator details sent when a site is
  connected.
- Generation events are no longer sent. Each time a draft or the analysis was generated,
  applied, regenerated or discarded, the block sent the action with the course and
  activity name to the dashboard, which stored nothing. The web service
  `block_mastermind_assistant_log_generation_feedback` is removed. The AI usage policy now
  says that the Mastermind service records usage figures for accounting, not the content
  of requests.

### Added
- The course analysis lists its recommendations under the summary, and each one names
  the sections, activities and figures it rests on ("Based on: Quiz 1: 3 of 12 learners
  completed"), linked to the activity or the course section. Links open in a new tab.
  With a dashboard older than this release the recommendations show without references.

### Changed
- Copying a course from the search results asks for confirmation first. Before, one
  click on "Copy" created the new course straight away.

### Release notes
- Needs a dashboard from 21 November 2026 or later (deployed). Older dashboards print a
  held-back SCORM figure as "null%" in the course-analysis prompt, and send no evidence
  references.
- Enterprise companion v1.5.0 requires this version (it uses `local\small_numbers`).
  Upgrade the block first.
- The upgrade removes the `log_generation_feedback` web service. The dashboard keeps
  answering its route until no site runs an older block.

## v4.3.0 (2026111200) — 2026-11-12

### Added
- Questionnaire evaluations (mod_questionnaire) are read alongside Feedback. Only
  evaluations count: a visible questionnaire with a rating (rate) or slider question, or
  one named as an evaluation. Sign-up, contact and other forms are never read.
  - Rating rows keep their own scale; "not applicable" answers are counted apart and
    never averaged. Rankings (rate with "no duplicate choices") are not ratings.
  - Radio and dropdown questions are sent as answer counts per choice, never as an
    average; text answers as comments.
  - Without the Questionnaire plugin installed, nothing changes.
- Feedback and Questionnaire results are merged into one evaluation result with one
  satisfaction figure on a 1-5 scale, weighted by the number of ratings in each.
- Activity selection: the include/exclude lists set per product in the Mastermind
  dashboard are applied before anything is read (with the enterprise companion v1.4.0).

### Changed
- Only participants' answers count; staff answers are left out. Both modules store the
  real user id even for anonymous activities: it is used only for this filter and never
  leaves the site.
- Only answers submitted on or after the course start date count, so answers from an
  earlier run of a reused course no longer mix in.
- Hidden, being-deleted and orphaned evaluation activities are skipped.
- Each rated item keeps its own scale (a 1-10 overall question stays 1-10); the
  satisfaction figure converts to 1-5.
- Completion: a course without completion criteria (or with completion off) shows
  "Not tracked" on the completion tile and reports no completion rate, instead of 0%.
- Evaluation table in the block: averages show their own scale ("/ 10"), an activity
  average appears only when all its rated items share one scale, and choice questions
  list the answer count per option.

### Privacy
- The privacy metadata now covers Questionnaire evaluations and states that only
  participants' answers are included.

### Compatibility
- The evaluation data keeps the exact shape the enterprise companion v1.3.x sends
  (activities and satisfaction), so sites on that version keep working.
- Needs the Mastermind dashboard of 2026-11-12 or later (live). Moodle 4.4 or later, as
  before; runs on Moodle 4.5 (PHP 8.1) and Moodle 5.2 (PHP 8.3).

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
