# Plan B — Install nudge + course-context surface

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** After install, every site administrator gets a persistent "do this next" prompt — both as a bell-icon notification (`message_send()`) and as a yellow dismissible banner on admin pages — that takes them straight to the settings page. Inside courses, surface a secondary navigation node so users in course context can find the block without scrolling the right column.

**Architecture:**
- `db/install.php` sets a `setup_complete = 0` config flag, sends a notification message to every site admin, and calls a small helper to display the banner on subsequent admin page loads.
- A `block_mastermind_assistant_before_footer` hook (or admin-page renderer override) injects a yellow banner whenever `setup_complete` is false and the current user can `moodle/site:config`.
- A new external function `complete_setup` flips the flag on banner dismiss.
- A `block_mastermind_assistant_extend_navigation_course` callback adds a secondary-navigation node on course pages.

**Tech Stack:** Moodle 4.4–5.2, PHP 8.1+, Moodle messaging API, AMD/RequireJS, PHPUnit.

---

## Spec coverage map

| Spec task | Plan task |
|---|---|
| 4. Post-install admin notification (bell icon + banner) | T2, T3, T4, T5 |
| 5. Course context surface (nav node) | T6 |
| 6. PHPUnit install + nav tests | T7, T8 |

---

## File structure

**Modify:**
- `version.php` — bump
- `db/install.php` — call setup helper after sticky block creation
- `db/upgrade.php` — backfill notification for already-installed sites
- `db/services.php` — register `complete_setup` web service
- `db/messages.php` — declare the message provider (CREATE — see Task 2)
- `block_mastermind_assistant.php` — implement `_extend_navigation_course` callback
- `lang/en/block_mastermind_assistant.php` — new strings
- `lang/de/block_mastermind_assistant.php` — new strings (German)
- `lib.php` — implement `_before_footer` hook for the banner (CREATE if missing)

**Create:**
- `classes/local/setup_helper.php` — sets flag, sends message, idempotent
- `classes/external/complete_setup.php` — flips flag on banner dismiss
- `db/messages.php` — message provider declaration
- `lib.php` — top-level hook callbacks (banner, nav)
- `amd/src/setup_banner.js` — handles banner dismiss → calls complete_setup → fades out
- `tests/setup_helper_test.php`
- `tests/install_test.php`
- `tests/external/complete_setup_test.php`
- `tests/navigation_test.php`

---

## Task 1: Bump version + new lang strings

**Files:**
- Modify: `version.php`
- Modify: `lang/en/block_mastermind_assistant.php`
- Modify: `lang/de/block_mastermind_assistant.php`

- [ ] **Step 1: Update `version.php`**

```php
$plugin->version = 2026050601;   // post-Plan-A
$plugin->release = 'v3.6.0';
```

- [ ] **Step 2: Add English strings**

```php
$string['messageprovider:setup_required'] = 'Setup reminder for Mastermind Assistant';
$string['setup_banner_body'] = 'Connect your Moodle to Mastermind to activate AI features. It is free to start — no credit card required.';
$string['setup_banner_cta'] = 'Open settings';
$string['setup_banner_dismiss'] = 'Dismiss';
$string['setup_banner_title'] = 'Mastermind Assistant installed';
$string['setup_message_body'] = 'Mastermind Assistant has been installed. Click below to connect your Moodle and activate AI features.';
$string['setup_message_subject'] = 'Connect Mastermind Assistant to activate AI features';
$string['nav_open_assistant'] = 'Mastermind Assistant';
$string['nav_open_assistant_desc'] = 'Open the AI course assistant for this course.';
```

- [ ] **Step 3: Add German strings**

```php
$string['messageprovider:setup_required'] = 'Einrichtungs-Erinnerung fuer Mastermind Assistant';
$string['setup_banner_body'] = 'Verbinden Sie Ihr Moodle mit Mastermind, um KI-Funktionen zu aktivieren. Der Einstieg ist kostenlos — keine Kreditkarte erforderlich.';
$string['setup_banner_cta'] = 'Einstellungen oeffnen';
$string['setup_banner_dismiss'] = 'Schliessen';
$string['setup_banner_title'] = 'Mastermind Assistant installiert';
$string['setup_message_body'] = 'Mastermind Assistant wurde installiert. Klicken Sie unten, um Ihr Moodle zu verbinden und KI-Funktionen zu aktivieren.';
$string['setup_message_subject'] = 'Mastermind Assistant verbinden, um KI-Funktionen zu aktivieren';
$string['nav_open_assistant'] = 'Mastermind Assistant';
$string['nav_open_assistant_desc'] = 'KI-Kursassistent fuer diesen Kurs oeffnen.';
```

- [ ] **Step 4: Commit**

```bash
git add version.php lang/
git commit -m "lang: install nudge + course nav strings (en, de)"
```

---

## Task 2: Declare the message provider (`db/messages.php`)

**Files:**
- Create: `db/messages.php`

Moodle's `message_send()` requires the component to declare which message types it can send. We declare one: `setup_required`.

- [ ] **Step 1: Create `db/messages.php`**

```php
<?php
// (license header)

defined('MOODLE_INTERNAL') || die();

$messageproviders = [
    'setup_required' => [
        'defaults' => [
            'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_LOGGEDIN + MESSAGE_DEFAULT_LOGGEDOFF,
            'email' => MESSAGE_PERMITTED,
        ],
        'capability' => 'moodle/site:config',
    ],
];
```

The `popup` default ensures the message appears in the bell-icon panel; `email` is permitted but off-by-default so admin email isn't spammed unless they opt in.

- [ ] **Step 2: Bump version and run upgrade so Moodle picks up the message provider**

```bash
PATH=/Applications/MAMP/bin/php/php8.3.14/bin:$PATH php /Applications/MAMP/htdocs/moodle/admin/cli/upgrade.php --non-interactive
```

- [ ] **Step 3: Commit**

```bash
git add db/messages.php
git commit -m "feat: declare setup_required message provider"
```

---

## Task 3: Setup helper (`classes/local/setup_helper.php`)

**Files:**
- Create: `classes/local/setup_helper.php`

Single source of truth for the setup state. Used by `install.php`, by the upgrade backfill, by the banner renderer, and by the `complete_setup` web service. Keeping logic here makes it cleanly testable.

- [ ] **Step 1: Write a failing test**

Create `tests/setup_helper_test.php`:

```php
<?php
// (license header)

namespace block_mastermind_assistant\local;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \block_mastermind_assistant\local\setup_helper
 */
class setup_helper_test extends \advanced_testcase {

    public function test_is_setup_complete_defaults_to_false(): void {
        $this->resetAfterTest();
        $this->assertFalse(setup_helper::is_setup_complete());
    }

    public function test_mark_setup_complete_persists(): void {
        $this->resetAfterTest();
        setup_helper::mark_setup_complete();
        $this->assertTrue(setup_helper::is_setup_complete());
    }

    public function test_send_install_notification_creates_message_for_each_admin(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();

        // Two site admins.
        $sink = $this->redirectMessages();
        setup_helper::send_install_notification();
        $messages = $sink->get_messages();
        $sink->close();

        $admins = get_admins();
        $this->assertCount(count($admins), $messages);
        foreach ($messages as $msg) {
            $this->assertEquals('block_mastermind_assistant', $msg->component);
            $this->assertEquals('setup_required', $msg->eventtype);
        }
    }

    public function test_send_install_notification_is_idempotent(): void {
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $sink = $this->redirectMessages();

        setup_helper::send_install_notification();
        setup_helper::send_install_notification(); // second call must not duplicate

        $admins = get_admins();
        $messages = $sink->get_messages();
        $sink->close();

        // Exactly one batch of messages, even with two calls.
        $this->assertCount(count($admins), $messages);
    }
}
```

- [ ] **Step 2: Run the test (expect failure)**

```bash
PATH=/Applications/MAMP/bin/php/php8.3.14/bin:$PATH \
  php /Applications/MAMP/htdocs/moodle/vendor/bin/phpunit \
  /Applications/MAMP/htdocs/moodle/blocks/mastermind_assistant/tests/setup_helper_test.php
```

Expected: `Class setup_helper not found`.

- [ ] **Step 3: Implement `classes/local/setup_helper.php`**

```php
<?php
// (license header)

namespace block_mastermind_assistant\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Coordinates post-install setup state for the plugin.
 *
 * Owns the `setup_complete` flag, sends the bell-icon notification to
 * site administrators, and exposes idempotency guarantees for both.
 */
class setup_helper {
    /** Plugin component name used by Moodle config + messages APIs. */
    public const COMPONENT = 'block_mastermind_assistant';

    /** Config flag name (boolean as int, 0/1). */
    public const FLAG_COMPLETE = 'setup_complete';

    /** Config flag set once notification has been delivered to avoid duplicates. */
    public const FLAG_NOTIFIED = 'setup_notification_sent';

    /**
     * Whether the admin has dismissed the banner / completed setup.
     */
    public static function is_setup_complete(): bool {
        $value = get_config(self::COMPONENT, self::FLAG_COMPLETE);
        return (string) $value === '1';
    }

    /**
     * Mark setup as complete. Idempotent.
     */
    public static function mark_setup_complete(): void {
        set_config(self::FLAG_COMPLETE, '1', self::COMPONENT);
    }

    /**
     * Reset (used by tests / re-install scenarios).
     */
    public static function reset_setup_state(): void {
        unset_config(self::FLAG_COMPLETE, self::COMPONENT);
        unset_config(self::FLAG_NOTIFIED, self::COMPONENT);
    }

    /**
     * Send a bell-icon notification to every site admin pointing at the settings page.
     *
     * Idempotent: subsequent calls are no-ops once the per-install flag is set.
     */
    public static function send_install_notification(): void {
        global $CFG;

        if ((string) get_config(self::COMPONENT, self::FLAG_NOTIFIED) === '1') {
            return;
        }

        require_once($CFG->dirroot . '/lib/messagelib.php');

        $admins = get_admins();
        $settingsurl = new \moodle_url('/admin/settings.php', [
            'section' => 'blocksettingmastermind_assistant',
        ]);

        foreach ($admins as $admin) {
            $message = new \core\message\message();
            $message->component = self::COMPONENT;
            $message->name = 'setup_required';
            $message->userfrom = \core_user::get_noreply_user();
            $message->userto = $admin;
            $message->subject = get_string('setup_message_subject', self::COMPONENT);
            $message->fullmessage = get_string('setup_message_body', self::COMPONENT);
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = '<p>'
                . s(get_string('setup_message_body', self::COMPONENT))
                . '</p>';
            $message->smallmessage = get_string('setup_message_subject', self::COMPONENT);
            $message->notification = 1;
            $message->contexturl = $settingsurl->out(false);
            $message->contexturlname = get_string('setup_banner_cta', self::COMPONENT);

            try {
                message_send($message);
            } catch (\Throwable $e) {
                debugging(
                    'Mastermind setup notification send failed for user '
                    . $admin->id . ': ' . $e->getMessage(),
                    DEBUG_DEVELOPER
                );
            }
        }

        set_config(self::FLAG_NOTIFIED, '1', self::COMPONENT);
    }
}
```

- [ ] **Step 4: Re-run the test (expect pass)**

```bash
PATH=/Applications/MAMP/bin/php/php8.3.14/bin:$PATH \
  php /Applications/MAMP/htdocs/moodle/vendor/bin/phpunit \
  /Applications/MAMP/htdocs/moodle/blocks/mastermind_assistant/tests/setup_helper_test.php
```

Expected: 4 passing.

- [ ] **Step 5: Commit**

```bash
git add classes/local/setup_helper.php tests/setup_helper_test.php
git commit -m "feat: setup_helper for install flag + bell-icon notification"
```

---

## Task 4: Wire `setup_helper` into install + upgrade

**Files:**
- Modify: `db/install.php`
- Modify: `db/upgrade.php`

- [ ] **Step 1: Update `db/install.php`**

After the existing `block_mastermind_assistant_send_install_ping($CFG);` call:

```php
    // Persistent post-install nudge: bell-icon notification to every site admin
    // PLUS a yellow banner on admin pages, both pointing at the settings page.
    // The banner stays until the admin dismisses it (calls complete_setup).
    \block_mastermind_assistant\local\setup_helper::send_install_notification();
```

- [ ] **Step 2: Update `db/upgrade.php` to backfill on existing installs**

Append a new block before `return true;`:

```php
    if ($oldversion < 2026050601) {
        // Sites already running the plugin pre-v3.6 won't have received the
        // post-install nudge. Send it once on upgrade — the helper is idempotent
        // so this is safe even if a fresh install path also runs it.
        if (!\block_mastermind_assistant\local\setup_helper::is_setup_complete()) {
            \block_mastermind_assistant\local\setup_helper::send_install_notification();
        }

        upgrade_block_savepoint(true, 2026050601, 'mastermind_assistant');
    }
```

- [ ] **Step 3: Write `tests/install_test.php`**

```php
<?php
// (license header)

namespace block_mastermind_assistant;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/mastermind_assistant/db/install.php');

/**
 * @covers ::xmldb_block_mastermind_assistant_install
 */
class install_test extends \advanced_testcase {

    public function test_install_creates_sticky_block_instance(): void {
        global $DB;
        $this->resetAfterTest();

        // Remove any existing sticky block from earlier installs in this test run.
        $DB->delete_records('block_instances', ['blockname' => 'mastermind_assistant']);

        xmldb_block_mastermind_assistant_install();

        $instances = $DB->get_records('block_instances', [
            'blockname' => 'mastermind_assistant',
        ]);
        $this->assertCount(1, $instances);

        $instance = reset($instances);
        $this->assertEquals(SYSCONTEXTID, $instance->parentcontextid);
        $this->assertEquals(1, $instance->showinsubcontexts);
        $this->assertEquals('side-pre', $instance->defaultregion);
    }

    public function test_install_sends_admin_notification(): void {
        $this->resetAfterTest();
        $this->preventResetByRollback();

        // Reset state so the helper actually fires.
        \block_mastermind_assistant\local\setup_helper::reset_setup_state();

        $sink = $this->redirectMessages();
        xmldb_block_mastermind_assistant_install();
        $messages = $sink->get_messages();
        $sink->close();

        $admins = get_admins();
        $this->assertCount(count($admins), $messages);
        $this->assertEquals('block_mastermind_assistant', $messages[0]->component);
        $this->assertEquals('setup_required', $messages[0]->eventtype);
    }

    public function test_install_silent_on_ping_failure(): void {
        $this->resetAfterTest();
        // Force the dashboard host to a guaranteed-unroutable target so the
        // curl call inside the ping fails fast.
        global $CFG;
        $CFG->forced_plugin_settings = $CFG->forced_plugin_settings ?? [];
        $CFG->forced_plugin_settings['block_mastermind_assistant'] = [
            'dashboard_url' => 'http://127.0.0.1:1', // guaranteed connection refused
        ];

        // Should not throw despite the unreachable endpoint.
        $result = xmldb_block_mastermind_assistant_install();
        $this->assertTrue($result);
    }
}
```

- [ ] **Step 4: Run the test**

```bash
PATH=/Applications/MAMP/bin/php/php8.3.14/bin:$PATH \
  php /Applications/MAMP/htdocs/moodle/vendor/bin/phpunit \
  /Applications/MAMP/htdocs/moodle/blocks/mastermind_assistant/tests/install_test.php
```

Expected: 3 passing.

- [ ] **Step 5: Commit**

```bash
git add db/install.php db/upgrade.php tests/install_test.php
git commit -m "feat(install): trigger setup_helper notification; backfill on upgrade"
```

---

## Task 5: Yellow banner — `lib.php` hook + dismiss web service + JS

**Files:**
- Create: `lib.php`
- Create: `classes/external/complete_setup.php`
- Modify: `db/services.php`
- Create: `amd/src/setup_banner.js`
- Create: `tests/external/complete_setup_test.php`

The banner must render on admin pages whenever `setup_complete` is false and the current user can `moodle/site:config`. We use Moodle's `_before_footer()` plugin hook (works for blocks: `block_mastermind_assistant_before_footer()` in `lib.php`).

- [ ] **Step 1: Create `lib.php` with the `_before_footer` hook**

```php
<?php
// (license header)

defined('MOODLE_INTERNAL') || die();

/**
 * Inject a one-time setup banner on admin pages until the admin dismisses it.
 *
 * Hooked via Moodle's plugin lifecycle. We render only on admin pages
 * (PAGE->pagelayout === 'admin' or context is system) and only when:
 *   - the current user has moodle/site:config, AND
 *   - the setup_complete flag is unset.
 *
 * @return string HTML to inject into the page footer (above </body>).
 */
function block_mastermind_assistant_before_footer(): string {
    global $PAGE, $OUTPUT, $USER;

    if (during_initial_install() || empty($USER->id) || isguestuser()) {
        return '';
    }

    if (\block_mastermind_assistant\local\setup_helper::is_setup_complete()) {
        return '';
    }

    $context = \context_system::instance();
    if (!has_capability('moodle/site:config', $context)) {
        return '';
    }

    // Limit to admin layouts so we don't pollute course pages.
    if (!in_array($PAGE->pagelayout ?? '', ['admin', 'mydashboard', 'frontpage'], true)) {
        return '';
    }

    $settingsurl = new \moodle_url('/admin/settings.php', [
        'section' => 'blocksettingmastermind_assistant',
    ]);

    $html = '<div id="mastermind-setup-banner" class="alert alert-warning"'
        . ' style="margin:1rem 0;display:flex;align-items:center;justify-content:space-between;gap:1rem;">'
        . '<div>'
        . '<strong>' . s(get_string('setup_banner_title', 'block_mastermind_assistant')) . '</strong> '
        . s(get_string('setup_banner_body', 'block_mastermind_assistant'))
        . '</div>'
        . '<div style="white-space:nowrap;">'
        . '<a class="btn btn-primary btn-sm" href="' . $settingsurl->out(false) . '">'
        . s(get_string('setup_banner_cta', 'block_mastermind_assistant'))
        . '</a> '
        . '<button type="button" class="btn btn-link btn-sm" id="mastermind-setup-dismiss">'
        . s(get_string('setup_banner_dismiss', 'block_mastermind_assistant'))
        . '</button>'
        . '</div>'
        . '</div>';

    $PAGE->requires->js_call_amd('block_mastermind_assistant/setup_banner', 'init');

    return $html;
}
```

Note: Moodle's `_before_footer` hook is only invoked if a `lib.php` file exists with this exact name. Pre-Moodle 4.5, the hook was sometimes resolved via `before_standard_html_head`; both are exercised in CI (Plan C). The banner uses pure HTML so it renders even if AMD fails to load.

- [ ] **Step 2: Create `classes/external/complete_setup.php`**

```php
<?php
// (license header)

namespace block_mastermind_assistant\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;

/**
 * Mark the post-install setup as complete (called when admin dismisses the banner).
 */
class complete_setup extends external_api {
    public static function execute_parameters() {
        return new external_function_parameters([]);
    }

    public static function execute(): array {
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('moodle/site:config', $context);

        \block_mastermind_assistant\local\setup_helper::mark_setup_complete();

        return ['success' => true];
    }

    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the dismiss succeeded'),
        ]);
    }
}
```

- [ ] **Step 3: Register in `db/services.php`**

Add to `$functions`:

```php
'block_mastermind_assistant_complete_setup' => [
    'classname'   => 'block_mastermind_assistant\external\complete_setup',
    'methodname'  => 'execute',
    'classpath'   => '',
    'description' => 'Mark the post-install setup banner as dismissed.',
    'type'        => 'write',
    'ajax'        => true,
    'capabilities' => 'moodle/site:config',
],
```

- [ ] **Step 4: Create `amd/src/setup_banner.js`**

```javascript
// (license header)

/**
 * Banner dismiss handler — calls complete_setup web service then fades out.
 *
 * @module     block_mastermind_assistant/setup_banner
 * @copyright  2026 The Namers <info@mastermindassistant.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/notification'], function(Ajax, Notification) {
    return {
        init: function() {
            var btn = document.getElementById('mastermind-setup-dismiss');
            if (!btn) {
                return;
            }
            btn.addEventListener('click', function() {
                btn.disabled = true;
                Ajax.call([{
                    methodname: 'block_mastermind_assistant_complete_setup',
                    args: {}
                }])[0].then(function() {
                    var banner = document.getElementById('mastermind-setup-banner');
                    if (banner && banner.parentNode) {
                        banner.parentNode.removeChild(banner);
                    }
                    return null;
                }).catch(function(err) {
                    btn.disabled = false;
                    Notification.exception(err);
                });
            });
        }
    };
});
```

- [ ] **Step 5: Build AMD**

```bash
cd /Applications/MAMP/htdocs/moodle && grunt amd --root=blocks/mastermind_assistant
```

- [ ] **Step 6: Test `complete_setup`**

Create `tests/external/complete_setup_test.php`:

```php
<?php
// (license header)

namespace block_mastermind_assistant\external;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \block_mastermind_assistant\external\complete_setup
 */
class complete_setup_test extends \advanced_testcase {

    public function test_marks_setup_complete(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $result = complete_setup::execute();

        $this->assertTrue($result['success']);
        $this->assertTrue(\block_mastermind_assistant\local\setup_helper::is_setup_complete());
    }

    public function test_requires_site_config(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->expectException(\required_capability_exception::class);
        complete_setup::execute();
    }
}
```

- [ ] **Step 7: Run all banner tests**

```bash
PATH=/Applications/MAMP/bin/php/php8.3.14/bin:$PATH \
  php /Applications/MAMP/htdocs/moodle/vendor/bin/phpunit \
  /Applications/MAMP/htdocs/moodle/blocks/mastermind_assistant/tests/external/complete_setup_test.php
```

Expected: 2 passing.

- [ ] **Step 8: Commit**

```bash
git add lib.php classes/external/complete_setup.php db/services.php amd/src/setup_banner.js amd/build/setup_banner.min.js* tests/external/complete_setup_test.php
git commit -m "feat: yellow setup banner on admin pages with dismiss endpoint"
```

---

## Task 6: Course-context surface — `_extend_navigation_course` callback

**Files:**
- Modify: `lib.php` (append callback)

The lower-friction option for 4.4–5.2 is hooking `block_mastermind_assistant_extend_navigation_course($navigation, $course, $context)` to add a node to the course's secondary navigation. Moodle resolves this callback by name; no service registration needed. Default block configuration is *not* used — modifying global course defaults from a block install is fragile because other plugins routinely overwrite it.

- [ ] **Step 1: Append to `lib.php`**

```php
/**
 * Add a secondary-navigation node so the assistant is discoverable inside courses.
 *
 * Implementation note (decision rationale, see plans/2026-05-06-plan-b):
 * We use the navigation extension callback instead of modifying $CFG defaultblocks
 * because the latter is a global override that other plugins routinely clobber on
 * their own install/upgrade. The nav node also surfaces on small viewports where
 * the side-pre region is hidden.
 *
 * @param navigation_node $navigation The course navigation node.
 * @param stdClass $course The course record.
 * @param context_course $context The course context.
 * @return void
 */
function block_mastermind_assistant_extend_navigation_course(
    navigation_node $navigation,
    stdClass $course,
    context_course $context
): void {
    if (!has_capability('block/mastermind_assistant:view', $context)) {
        return;
    }

    $url = new moodle_url('/course/view.php', [
        'id' => $course->id,
        'mastermind' => 1, // hint that the block should auto-open / focus
    ]);

    $node = navigation_node::create(
        get_string('nav_open_assistant', 'block_mastermind_assistant'),
        $url,
        navigation_node::TYPE_CUSTOM,
        null,
        'mastermind_assistant',
        new pix_icon('i/marker', '')
    );
    $node->showinflatnavigation = false;

    $navigation->add_node($node);
}
```

- [ ] **Step 2: Write `tests/navigation_test.php`**

```php
<?php
// (license header)

namespace block_mastermind_assistant;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/mastermind_assistant/lib.php');

/**
 * @covers ::block_mastermind_assistant_extend_navigation_course
 */
class navigation_test extends \advanced_testcase {

    public function test_node_added_for_users_with_view(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $context = \context_course::instance($course->id);
        $navigation = new \navigation_node(['type' => \navigation_node::TYPE_COURSE, 'text' => 'root']);

        block_mastermind_assistant_extend_navigation_course($navigation, $course, $context);

        $node = $navigation->find('mastermind_assistant', \navigation_node::TYPE_CUSTOM);
        $this->assertNotFalse($node);
        $this->assertEquals(
            get_string('nav_open_assistant', 'block_mastermind_assistant'),
            (string) $node->get_content()
        );
    }

    public function test_node_omitted_for_users_without_view(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        // Strip the view capability to confirm gating.
        $studentroleid = $this->getDataGenerator()->create_role();
        unassign_capability('block/mastermind_assistant:view', $studentroleid);
        $this->setUser($student);

        $context = \context_course::instance($course->id);
        $navigation = new \navigation_node(['type' => \navigation_node::TYPE_COURSE, 'text' => 'root']);

        block_mastermind_assistant_extend_navigation_course($navigation, $course, $context);

        $this->assertFalse($navigation->find('mastermind_assistant', \navigation_node::TYPE_CUSTOM));
    }
}
```

- [ ] **Step 3: Run navigation tests**

```bash
PATH=/Applications/MAMP/bin/php/php8.3.14/bin:$PATH \
  php /Applications/MAMP/htdocs/moodle/vendor/bin/phpunit \
  /Applications/MAMP/htdocs/moodle/blocks/mastermind_assistant/tests/navigation_test.php
```

Expected: 2 passing.

- [ ] **Step 4: Manual verification**

Open any course in MAMP. The course navigation (secondary tabs / hamburger menu, depending on theme) should now contain "Mastermind Assistant" pointing at the course page with `?mastermind=1`.

- [ ] **Step 5: Commit**

```bash
git add lib.php tests/navigation_test.php
git commit -m "feat: course nav node so the assistant is findable inside courses"
```

---

## Self-review checklist (before merging Plan B)

- [ ] All Plan B PHPUnit tests pass: `phpunit blocks/mastermind_assistant/tests/setup_helper_test.php blocks/mastermind_assistant/tests/install_test.php blocks/mastermind_assistant/tests/external/complete_setup_test.php blocks/mastermind_assistant/tests/navigation_test.php`
- [ ] On a fresh install, every site admin receives a bell-icon notification and sees the yellow banner on the next admin page visit
- [ ] Clicking the banner CTA navigates to the settings page
- [ ] Clicking Dismiss removes the banner; refreshing keeps it dismissed (flag persisted)
- [ ] Banner does NOT appear for non-admins
- [ ] Banner does NOT appear on course pages or front-page (only admin layouts)
- [ ] Course secondary nav has a "Mastermind Assistant" node for users with `block/mastermind_assistant:view`
- [ ] Course nav node hidden for users without the view capability
- [ ] Install ping failure does not break install (`test_install_silent_on_ping_failure`)
- [ ] Upgrade backfill does not duplicate notifications
