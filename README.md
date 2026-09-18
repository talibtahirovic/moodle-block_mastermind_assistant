# Mastermind Assistant for Moodle

[![moodle-plugin-ci](https://github.com/talibtahirovic/moodle-block_mastermind_assistant/actions/workflows/moodle-ci.yml/badge.svg)](https://github.com/talibtahirovic/moodle-block_mastermind_assistant/actions/workflows/moodle-ci.yml)

Mastermind Assistant is a Moodle block plugin that brings AI-powered course creation, content generation, and learning analytics directly into your Moodle LMS.

## Features

- **AI Course Creation** - Generate complete course structures from a text prompt or by uploading a syllabus document (PDF, DOCX, TXT).
- **Activity Content Generation** - Draft content for Pages, Quizzes, Assignments, Forums, Lessons, Glossaries, Books, and URL resources with a single click.
- **Course Insights** - View completion rates, average grades, drop-off sections, forum activity, and learner satisfaction at a glance.
- **Course Search & Copy** - Quickly find existing courses and duplicate them with updated dates.
- **AI Recommendations** - Get actionable suggestions to improve course design and learner engagement.

## Requirements

- Moodle 4.5 or later
- A Mastermind Assistant API key (register at https://mastermindassistant.ai)

## Installation

1. Download the plugin and extract it into `blocks/mastermind_assistant` inside your Moodle installation directory.
2. Log in as a site administrator and visit **Site administration > Notifications** to complete the installation.
3. Go to **Site administration > Plugins > Blocks > Mastermind Assistant** to configure your Dashboard URL and API key.

## Configuration

| Setting | Description |
|---|---|
| Dashboard URL | The URL of your Mastermind Dashboard instance |
| API Key | Your Mastermind API key (starts with `ma_live_`) |

After saving, click **Test Connection** to verify your credentials.

## Usage

Add the **Mastermind Assistant** block to any course page or the site dashboard. The block provides:

- Course insights and analytics on course pages
- AI content generation when editing activities
- Course search, copy, and AI-assisted course creation from the dashboard

## Privacy: what is sent to the service

Course analysis sends course structure (section and activity names, summaries, dates)
and aggregate figures only. Learner names, usernames, user IDs and email addresses are
never sent. Enrolment, grades, completion and forum activity leave the site as counts
and averages; evaluation (Feedback) answers are sent without user IDs, and the
dashboard removes contact details and sign-offs inside a comment before it is stored
or analysed, retaining only per-activity counts and themes rather than the text.
The plugin's privacy provider (`classes/privacy/provider.php`) declares each field.

## Compatibility and sunset

Every request declares `X-Plugin-Version`. Plugin versions before 2026091700 (v4.1.0)
sent per-learner rows; the dashboard keeps accepting those payloads until
**2027-03-31**, removing the identity fields on arrival and logging a deprecation
warning that names the plugin version. From 2027-03-31, and from any plugin that
declares version 2026091700 or later, an identity-bearing payload is refused with
HTTP 400 `LEARNER_IDENTITY`. The settings page shows a warning when the connected
dashboard reports a newer required version than the one installed. See `CHANGELOG.md`.

## License

This plugin is licensed under the [GNU GPL v3 or later](http://www.gnu.org/copyleft/gpl.html).

## Support

- Register: https://mastermindassistant.ai
- Contact: info@mastermindassistant.ai
