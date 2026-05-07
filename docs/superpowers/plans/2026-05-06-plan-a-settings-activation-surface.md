# Plan A — Settings Activation Surface

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the plugin's admin settings page the primary activation surface — admins land there, click one button, and end up connected, exactly as Thiago manually did. Hide the Dashboard URL, gate the API Key field behind explicit "paste manually" intent, and surface "Connected ✓ / Disconnect" when a key is saved.

**Architecture:**
- The settings page injects a `Connect to Mastermind` button + status indicator above the existing fields.
- The existing block-side connect flow (`amd/src/connect.js`) is refactored to accept `init(buttonId, returnUrl)` and is reused for the settings-page button.
- `dashboard_url` becomes a class constant in `api_client`; the user-facing settings field is removed. Override is via `$CFG->forced_plugin_settings`.
- API Key field is hidden by default, revealed by an "Edit / Paste manually" disclosure.

**Tech Stack:** Moodle 4.4–5.2, PHP 8.1+, AMD/RequireJS, PHPUnit, Behat, ESLint.

---

## Spec coverage map

| Spec task | Plan task |
|---|---|
| 1. Connect button on settings page | T4, T5, T6 |
| 2. Remove Dashboard URL field, hardcode constant | T3, T7, T8 |
| 3. Conditional UI for API Key field | T6 |
| 6. PHPUnit + Behat tests | T9, T10, T11 |
| 4.4 / 4.5 / 5.0 / 5.1 / 5.2 support | T1 |

(Tasks 4, 5, 7, 8 are in Plans B and C.)

---

## File structure

**Modify:**
- `version.php` — bump version, lower `requires` to 4.4
- `settings.php` — replace Dashboard URL with Connect section + conditional API Key
- `classes/api_client.php` — add `DASHBOARD_URL` constant, helper `get_dashboard_url()`
- `amd/src/connect.js` — `init(buttonId, returnUrl)` signature
- `amd/src/settings.js` — settings page state machine (connect / paste / connected / disconnect)
- `db/upgrade.php` — drop stale `dashboard_url` config row
- `callback.php` — remove `set_config('dashboard_url', ...)`
- `lang/en/block_mastermind_assistant.php` — new keys
- `lang/de/block_mastermind_assistant.php` — new keys (German)

**Create:**
- `classes/external/disconnect.php` — clear API key (admin only)
- `db/services.php` — register `disconnect` web service (already a partial file; add new entry)
- `tests/api_client_test.php`
- `tests/external/generate_connect_nonce_test.php`
- `tests/external/disconnect_test.php`
- `tests/behat/settings_connect.feature`

---

## Task 1: Lower required Moodle version + bump plugin version

**Files:**
- Modify: `version.php`

- [ ] **Step 1: Update `version.php`**

```php
$plugin->component = 'block_mastermind_assistant';
$plugin->version = 2026050600;
$plugin->requires = 2024042200; // Moodle 4.4 or later.
$plugin->maturity = MATURITY_STABLE;
$plugin->release = 'v3.5.0';
```

The version code `2024042200` is Moodle 4.4.0's release date code. Lowering this allows installation on 4.4 sites; CI (Plan C) will exercise it.

- [ ] **Step 2: Commit**

```bash
git add version.php
git commit -m "chore: lower required Moodle to 4.4, bump plugin version"
```

---

## Task 2: Add new language strings (English + German)

**Files:**
- Modify: `lang/en/block_mastermind_assistant.php`
- Modify: `lang/de/block_mastermind_assistant.php`

Both files use ASCII-safe German conventions (`ae/oe/ue/ss`) per existing convention — match it.

- [ ] **Step 1: Append new English keys (alphabetical order maintained)**

Add to `lang/en/block_mastermind_assistant.php`:

```php
$string['connect_card_subtitle'] = 'One click takes you to mastermindassistant.ai, creates your account, and brings the API key back automatically.';
$string['connect_card_title'] = 'Connect to Mastermind';
$string['connect_disconnect'] = 'Disconnect';
$string['connect_disconnect_confirm_body'] = 'This will remove your saved API key. You can reconnect at any time.';
$string['connect_disconnect_confirm_title'] = 'Disconnect from Mastermind?';
$string['connect_disconnected'] = 'Disconnected from Mastermind.';
$string['connect_edit_key'] = 'Edit / replace key';
$string['connect_have_key'] = 'Have an API key already?';
$string['connect_last_verified'] = 'Last verified {$a} ago';
$string['connect_manual_paste_link'] = 'Paste it manually';
$string['connect_not_yet'] = 'Not connected yet';
$string['connect_status_connected'] = 'Connected ✓';
$string['settings_account_label'] = 'Connected as';
$string['settings_apikey_redacted'] = 'API key: {$a}';
```

Replace the existing `settings_dashboard_url` and `settings_dashboard_url_desc` keys (no longer surfaced) — leave them in place for backwards compat with any external translations, but mark with a comment:

```php
// Retained for compatibility; the dashboard URL field is no longer surfaced in the settings UI.
$string['settings_dashboard_url'] = 'Dashboard URL';
$string['settings_dashboard_url_desc'] = 'The URL of your Mastermind Dashboard instance (e.g. https://mastermindassistant.ai)';
```

- [ ] **Step 2: Append new German keys**

Add to `lang/de/block_mastermind_assistant.php`:

```php
$string['connect_card_subtitle'] = 'Ein Klick fuehrt Sie zu mastermindassistant.ai, erstellt Ihr Konto und uebertraegt den API-Schluessel automatisch zurueck.';
$string['connect_card_title'] = 'Mit Mastermind verbinden';
$string['connect_disconnect'] = 'Trennen';
$string['connect_disconnect_confirm_body'] = 'Dadurch wird Ihr gespeicherter API-Schluessel entfernt. Sie koennen sich jederzeit erneut verbinden.';
$string['connect_disconnect_confirm_title'] = 'Verbindung zu Mastermind trennen?';
$string['connect_disconnected'] = 'Verbindung zu Mastermind getrennt.';
$string['connect_edit_key'] = 'Schluessel bearbeiten / ersetzen';
$string['connect_have_key'] = 'Sie haben bereits einen API-Schluessel?';
$string['connect_last_verified'] = 'Zuletzt geprueft vor {$a}';
$string['connect_manual_paste_link'] = 'Manuell einfuegen';
$string['connect_not_yet'] = 'Noch nicht verbunden';
$string['connect_status_connected'] = 'Verbunden ✓';
$string['settings_account_label'] = 'Verbunden als';
$string['settings_apikey_redacted'] = 'API-Schluessel: {$a}';
```

- [ ] **Step 3: Verify no duplicates**

Run:

```bash
grep -c "connect_card_title" lang/en/block_mastermind_assistant.php
grep -c "connect_card_title" lang/de/block_mastermind_assistant.php
```

Expected: each prints `1`.

- [ ] **Step 4: Commit**

```bash
git add lang/
git commit -m "lang: add settings-page connect strings (en, de)"
```

---

## Task 3: API client — `DASHBOARD_URL` constant with `forced_plugin_settings` override

**Files:**
- Modify: `classes/api_client.php`

The existing class reads `dashboard_url` from `get_config()`. We replace that with a class constant, **but** preserve override via `$CFG->forced_plugin_settings['block_mastermind_assistant']['dashboard_url']`. Moodle's `get_config()` already returns the forced value when set, so the simplest approach is: keep calling `get_config()`, fall back to the constant if no value set.

- [ ] **Step 1: Write the failing test first (Task 9 will create the file; here we just author the constant)**

Skip test-first for this purely structural change; the test is in Task 9.

- [ ] **Step 2: Add constant + helper in `classes/api_client.php`**

At the top of the class (after `class api_client {`), add:

```php
    /** @var string Default dashboard base URL. Override only via $CFG->forced_plugin_settings. */
    public const DASHBOARD_URL = 'https://mastermindassistant.ai';

    /**
     * Resolve the dashboard base URL, respecting $CFG->forced_plugin_settings overrides.
     *
     * @return string Dashboard base URL with trailing slashes trimmed.
     */
    public static function get_dashboard_url(): string {
        $configured = get_config('block_mastermind_assistant', 'dashboard_url');
        if (is_string($configured) && $configured !== '') {
            return rtrim($configured, '/');
        }
        return rtrim(self::DASHBOARD_URL, '/');
    }
```

- [ ] **Step 3: Update constructor to use the helper**

Replace the existing constructor body:

```php
    public function __construct() {
        $this->baseurl = self::get_dashboard_url();
        $this->apikey = get_config('block_mastermind_assistant', 'api_key') ?: '';

        if (empty($this->apikey)) {
            throw new \moodle_exception('settings_not_configured', 'block_mastermind_assistant');
        }
    }
```

Note: `baseurl` is now always populated (constant fallback), so the empty check on `$this->baseurl` is gone — only the API key matters for "configured".

- [ ] **Step 4: Update `classes/external/generate_connect_nonce.php` to use the helper**

Replace the block at lines 87–91:

```php
        $dashboardbase = \block_mastermind_assistant\api_client::get_dashboard_url();
```

(Remove the `if (empty($dashboardbase))` fallback and the `rtrim` — the helper handles both.)

- [ ] **Step 5: Commit**

```bash
git add classes/api_client.php classes/external/generate_connect_nonce.php
git commit -m "feat: hardcode dashboard URL constant; respect forced_plugin_settings override"
```

---

## Task 4: Refactor `connect.js` to accept `init(buttonId, returnUrl)`

**Files:**
- Modify: `amd/src/connect.js`

Current `init` takes only `returnUrl` and hardcodes the button id `mastermind-connect-btn`. We make `buttonId` the first parameter so the settings page can mount on its own button id.

- [ ] **Step 1: Modify the `init` function signature**

Replace the bottom of `amd/src/connect.js` (lines 96–142):

```javascript
    return {
        /**
         * Initialise a Connect button.
         *
         * @param {string} buttonId DOM id of the button to mount on.
         * @param {string} returnUrl Local Moodle URL to redirect to after the callback.
         */
        init: function(buttonId, returnUrl) {
            var btn = document.getElementById(buttonId || 'mastermind-connect-btn');
            if (!btn) {
                return;
            }

            btn.addEventListener('click', function() {
                var originalLabel = btn.textContent;
                btn.disabled = true;

                Str.get_string('connect_connecting', 'block_mastermind_assistant').then(function(label) {
                    btn.textContent = label;
                    return null;
                }).catch(function() {
                    btn.textContent = '...';
                });

                Ajax.call([{
                    methodname: 'block_mastermind_assistant_generate_connect_nonce',
                    args: {returnurl: returnUrl || ''}
                }])[0].then(function(response) {
                    var connectUrl = response.connect_url;
                    var newWin = window.open(connectUrl, '_blank', 'noopener');

                    if (!newWin || newWin.closed || typeof newWin.closed === 'undefined') {
                        showFallback(connectUrl);
                    }

                    startPolling(btn, originalLabel);
                    return Str.get_string('connect_waiting', 'block_mastermind_assistant');
                }).then(function(label) {
                    btn.textContent = label;
                    return null;
                }).catch(function(err) {
                    btn.disabled = false;
                    btn.textContent = originalLabel;
                    Notification.exception(err);
                });
            });
        }
    };
```

The fallback to `'mastermind-connect-btn'` keeps existing block-side callers (anything still calling `init(returnUrl)` would pass a string into `buttonId`, but every existing call site we control will be updated in Step 2).

- [ ] **Step 2: Update existing callers**

Search for callers:

```bash
grep -rn "block_mastermind_assistant/connect" --include="*.php" --include="*.js" --include="*.mustache"
```

For each call site (e.g. in `block_mastermind_assistant.php` or templates that call `js_call_amd('block_mastermind_assistant/connect', 'init', ...)`):

```php
$PAGE->requires->js_call_amd(
    'block_mastermind_assistant/connect',
    'init',
    ['mastermind-connect-btn', $returnurl]
);
```

- [ ] **Step 3: Run ESLint to confirm no regressions**

```bash
cd /Applications/MAMP/htdocs/moodle && \
  ./node_modules/.bin/eslint /Applications/MAMP/htdocs/moodle-block_mastermind_assistant/amd/src/connect.js \
  --no-eslintrc --config .eslintrc
```

Expected: 0 errors.

- [ ] **Step 4: Commit**

```bash
git add amd/src/connect.js block_mastermind_assistant.php
git commit -m "refactor(connect): accept buttonId so any page can mount the connect flow"
```

---

## Task 5: Settings page — Connect section + conditional API Key field

**Files:**
- Modify: `settings.php`

The existing `settings.php` adds: Dashboard URL, API Key, info heading, Test Connection button. We rewrite to:

1. Connect section (heading) at the top — always present.
2. API Key field — wrapped in a hidden container, revealed by JS via the "Paste it manually" disclosure.
3. Info heading — kept (account & support).
4. Test Connection button — kept.
5. Dashboard URL field — **removed**.

We use `admin_setting_heading` with HTML for sections that are display-only (Connect card, account info card). The API Key remains a real `admin_setting_configtext` so save/load via the standard form works — only its visibility is JS-controlled.

- [ ] **Step 1: Replace `settings.php`**

```php
<?php
// (license header retained)

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    global $PAGE;

    $apikey = get_config('block_mastermind_assistant', 'api_key') ?: '';
    $isconnected = strpos($apikey, 'ma_live_') === 0 && strlen($apikey) >= 20;

    // 1. Connect card — primary activation surface.
    $connecthtml = render_mastermind_connect_card($isconnected, $apikey);
    $settings->add(new admin_setting_heading(
        'block_mastermind_assistant/connect_heading',
        get_string('connect_card_title', 'block_mastermind_assistant'),
        $connecthtml
    ));

    // 2. API Key field — wrapped in a container that JS hides unless the user
    //    explicitly chooses to paste manually or edit an existing key.
    //    The wrapper id is what settings.js targets.
    $apikeysetting = new admin_setting_configtext(
        'block_mastermind_assistant/api_key',
        get_string('settings_api_key', 'block_mastermind_assistant'),
        get_string('settings_api_key_desc', 'block_mastermind_assistant'),
        ''
    );
    $apikeysetting->set_updatedcallback('block_mastermind_assistant_apikey_updated');
    $settings->add($apikeysetting);

    // 3. Info heading — account & support.
    $infohtml = '<div style="margin-top: 0.5rem;">';
    $infohtml .= '<p>' . get_string('settings_register_desc', 'block_mastermind_assistant') . ' ';
    $infohtml .= '<a href="https://mastermindassistant.ai" target="_blank" rel="noopener">';
    $infohtml .= 'mastermindassistant.ai</a></p>';
    $infohtml .= '<p>' . get_string('settings_support_desc', 'block_mastermind_assistant') . ' ';
    $infohtml .= '<a href="mailto:info@mastermindassistant.ai">info@mastermindassistant.ai</a></p>';
    $infohtml .= '</div>';

    $settings->add(new admin_setting_heading(
        'block_mastermind_assistant/info_heading',
        get_string('settings_info_heading', 'block_mastermind_assistant'),
        $infohtml
    ));

    // 4. Test Connection button — visible after a key is saved.
    $testhtml = '<div id="mastermind-settings-info" style="margin-top: 1rem;">';
    $testhtml .= '<p style="margin-bottom: 0.5rem; color: #666; font-size: 0.85em;">';
    $testhtml .= get_string('test_connection_desc', 'block_mastermind_assistant');
    $testhtml .= '</p>';
    $testhtml .= '<button type="button" id="mastermind-test-connection" class="btn btn-secondary">';
    $testhtml .= get_string('test_connection', 'block_mastermind_assistant');
    $testhtml .= '</button>';
    $testhtml .= '<div id="mastermind-connection-result" style="margin-top: 0.75rem;"></div>';
    $testhtml .= '</div>';

    $settings->add(new admin_setting_heading(
        'block_mastermind_assistant/connection_heading',
        get_string('connection_status', 'block_mastermind_assistant'),
        $testhtml
    ));

    // Mount the settings JS module — handles connect button + paste/edit toggles.
    $callbackurl = (new moodle_url('/admin/settings.php', ['section' => 'blocksettingmastermind_assistant']))->out(false);
    $PAGE->requires->js_call_amd(
        'block_mastermind_assistant/settings',
        'init',
        [$callbackurl, $isconnected]
    );
}

/**
 * Render the connect card HTML for the settings page.
 *
 * @param bool $isconnected Whether a valid-looking API key is currently saved.
 * @param string $apikey Raw saved key (used for redacted display only).
 * @return string HTML.
 */
function render_mastermind_connect_card(bool $isconnected, string $apikey): string {
    $out = '<div class="mastermind-connect-card" style="padding:1rem;border:1px solid #dee2e6;border-radius:6px;background:#f8f9fa;">';
    $out .= '<p style="margin-bottom:1rem;color:#495057;">'
        . s(get_string('connect_card_subtitle', 'block_mastermind_assistant'))
        . '</p>';

    if ($isconnected) {
        // Connected state.
        $redacted = substr($apikey, 0, 11) . str_repeat('•', 8); // ma_live_xxxx••••••••
        $out .= '<div id="mastermind-connect-status-connected">';
        $out .= '<p style="margin-bottom:0.25rem;font-weight:600;color:#28a745;">'
            . s(get_string('connect_status_connected', 'block_mastermind_assistant'))
            . '</p>';
        $out .= '<p style="margin-bottom:0.5rem;font-size:0.9em;color:#666;">'
            . s(get_string('settings_apikey_redacted', 'block_mastermind_assistant', $redacted))
            . '</p>';
        $out .= '<button type="button" class="btn btn-outline-secondary btn-sm" id="mastermind-disconnect-btn">'
            . s(get_string('connect_disconnect', 'block_mastermind_assistant'))
            . '</button>';
        $out .= ' <button type="button" class="btn btn-link btn-sm" id="mastermind-edit-key-btn">'
            . s(get_string('connect_edit_key', 'block_mastermind_assistant'))
            . '</button>';
        $out .= '</div>';
    } else {
        // Disconnected state — primary CTA + manual paste disclosure.
        $out .= '<div id="mastermind-connect-status-disconnected">';
        $out .= '<p style="margin-bottom:0.5rem;color:#666;">'
            . s(get_string('connect_not_yet', 'block_mastermind_assistant'))
            . '</p>';
        $out .= '<button type="button" class="btn btn-primary" id="mastermind-connect-btn">'
            . s(get_string('connect_card_title', 'block_mastermind_assistant'))
            . '</button>';
        $out .= '<div id="mastermind-connect-fallback" hidden style="margin-top:0.5rem;font-size:0.85em;color:#666;"></div>';
        $out .= '<p style="margin-top:0.75rem;font-size:0.9em;">'
            . s(get_string('connect_have_key', 'block_mastermind_assistant'))
            . ' <a href="#" id="mastermind-paste-manually-link">'
            . s(get_string('connect_manual_paste_link', 'block_mastermind_assistant'))
            . '</a></p>';
        $out .= '</div>';
    }

    $out .= '</div>';
    return $out;
}

/**
 * Updated-callback for the api_key admin setting.
 *
 * Cleared so subsequent code can react to key changes (e.g. fetching account info)
 * without bloating settings.php further.
 *
 * @return void
 */
function block_mastermind_assistant_apikey_updated(): void {
    // Reserved for future use (e.g. clearing a cached account info row).
}
```

- [ ] **Step 2: Lint PHP**

```bash
PATH=/Applications/MAMP/bin/php/php8.3.14/bin:$PATH php -l settings.php
```

Expected: `No syntax errors detected`.

- [ ] **Step 3: Manually load the page in MAMP and confirm**

Visit `http://localhost:8888/moodle/admin/settings.php?section=blocksettingmastermind_assistant` (substitute your MAMP port). With no key saved you should see "Not connected yet" + a Connect button + a "Paste it manually" link.

- [ ] **Step 4: Commit**

```bash
git add settings.php
git commit -m "feat(settings): add Connect card; remove dashboard URL field"
```

---

## Task 6: Settings JS — state machine for connect / paste / disconnect

**Files:**
- Rewrite: `amd/src/settings.js`

The current `settings.js` only handles the Test Connection button. It needs to also handle:
- Click on `#mastermind-connect-btn` → delegate to the `connect` module
- Click on `#mastermind-paste-manually-link` → reveal the API Key field
- Click on `#mastermind-edit-key-btn` → reveal the API Key field (when connected)
- Click on `#mastermind-disconnect-btn` → call `disconnect` web service, then reload

The API Key form row in Moodle admin pages is rendered as a `<tr>` with class `admin_setting_configtext block_mastermind_assistant_api_key`. We hide that row by default unless the user clicks paste/edit, OR until a stored key exists. Use `document.querySelector('[data-fullname="block_mastermind_assistant/api_key"]')` to locate it.

- [ ] **Step 1: Rewrite `amd/src/settings.js`**

```javascript
// (license header retained)

/**
 * Settings page module for Mastermind Assistant.
 *
 * Coordinates the Connect button, manual-paste disclosure, Disconnect button,
 * and the existing Test Connection flow.
 *
 * @module     block_mastermind_assistant/settings
 * @copyright  2026 The Namers <info@mastermindassistant.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
    'core/ajax',
    'core/notification',
    'core/str',
    'core/templates',
    'block_mastermind_assistant/connect'
], function(Ajax, Notification, Str, Templates, Connect) {

    /**
     * Hide the API Key admin row by default.
     *
     * @return {?HTMLElement} The hidden row, or null if it could not be found.
     */
    function hideApiKeyRow() {
        var row = document.querySelector('[data-fullname="block_mastermind_assistant/api_key"]');
        if (row) {
            row.style.display = 'none';
        }
        return row;
    }

    /**
     * Show the API Key row (used when the user clicks "Paste manually" or "Edit key").
     */
    function showApiKeyRow() {
        var row = document.querySelector('[data-fullname="block_mastermind_assistant/api_key"]');
        if (row) {
            row.style.display = '';
            var input = row.querySelector('input[type="text"], input[type="password"]');
            if (input) {
                input.focus();
            }
        }
    }

    /**
     * Wire the Disconnect button.
     */
    function wireDisconnectButton() {
        var btn = document.getElementById('mastermind-disconnect-btn');
        if (!btn) {
            return;
        }
        btn.addEventListener('click', function() {
            Str.get_strings([
                {key: 'connect_disconnect_confirm_title', component: 'block_mastermind_assistant'},
                {key: 'connect_disconnect_confirm_body', component: 'block_mastermind_assistant'},
                {key: 'connect_disconnect', component: 'block_mastermind_assistant'}
            ]).then(function(strings) {
                Notification.saveCancel(strings[0], strings[1], strings[2], function() {
                    performDisconnect(btn);
                }).catch(Notification.exception);
                return null;
            }).catch(Notification.exception);
        });
    }

    /**
     * Call the disconnect web service then reload the settings page.
     *
     * @param {HTMLElement} btn The disconnect button.
     */
    function performDisconnect(btn) {
        btn.disabled = true;
        Ajax.call([{
            methodname: 'block_mastermind_assistant_disconnect',
            args: {}
        }])[0].then(function() {
            window.location.reload();
            return null;
        }).catch(function(err) {
            btn.disabled = false;
            Notification.exception(err);
        });
    }

    /**
     * Wire the "Paste it manually" disclosure link (disconnected state).
     */
    function wirePasteManually() {
        var link = document.getElementById('mastermind-paste-manually-link');
        if (!link) {
            return;
        }
        link.addEventListener('click', function(e) {
            e.preventDefault();
            showApiKeyRow();
        });
    }

    /**
     * Wire the "Edit / replace" link shown when already connected.
     */
    function wireEditKey() {
        var btn = document.getElementById('mastermind-edit-key-btn');
        if (!btn) {
            return;
        }
        btn.addEventListener('click', function() {
            showApiKeyRow();
        });
    }

    /**
     * Wire the existing Test Connection button (kept from the previous version).
     */
    function wireTestConnection() {
        var button = document.getElementById('mastermind-test-connection');
        var resultDiv = document.getElementById('mastermind-connection-result');
        if (!button || !resultDiv) {
            return;
        }

        button.addEventListener('click', function() {
            button.disabled = true;
            Str.get_string('testing_connection', 'block_mastermind_assistant').then(function(s) {
                button.textContent = s;
                resultDiv.innerHTML = '<div class="alert alert-info">' + escapeHtml(s) + '</div>';
                return null;
            }).catch(function() {
                button.textContent = '...';
            });

            var savedResponse = null;
            Ajax.call([{
                methodname: 'block_mastermind_assistant_test_connection',
                args: {}
            }])[0].then(function(response) {
                button.disabled = false;
                savedResponse = response;
                Str.get_string('test_connection', 'block_mastermind_assistant').then(function(s) {
                    button.textContent = s;
                    return null;
                }).catch(function() {
                    button.textContent = 'Test Connection';
                });

                var context = {
                    success: response.success,
                    tier: response.tier || '',
                    status: response.status || '',
                    message: response.message || ''
                };
                return Templates.renderForPromise('block_mastermind_assistant/connection_result', context);
            }).then(function(result) {
                if (result) {
                    resultDiv.innerHTML = result.html;
                }
                return null;
            }).catch(function(error) {
                button.disabled = false;
                var msg = (savedResponse && savedResponse.message) || (error && error.message) || '';
                resultDiv.innerHTML = '<div class="alert alert-danger">' + escapeHtml(msg) + '</div>';
                if (!savedResponse) {
                    Notification.exception(error);
                }
            });
        });
    }

    /**
     * Escape HTML entities.
     * @param {string} text
     * @return {string}
     */
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text || ''));
        return div.innerHTML;
    }

    return {
        /**
         * Initialise the settings-page interactions.
         *
         * @param {string} returnUrl Local Moodle URL to return to after callback.
         * @param {boolean} isConnected Whether a valid-looking API key is saved.
         */
        init: function(returnUrl, isConnected) {
            // Always hide the API key row first; user must click paste/edit to reveal it.
            hideApiKeyRow();

            if (!isConnected) {
                Connect.init('mastermind-connect-btn', returnUrl);
                wirePasteManually();
            } else {
                wireDisconnectButton();
                wireEditKey();
            }

            wireTestConnection();
        }
    };
});
```

- [ ] **Step 2: Lint**

```bash
cd /Applications/MAMP/htdocs/moodle && \
  ./node_modules/.bin/eslint /Applications/MAMP/htdocs/moodle-block_mastermind_assistant/amd/src/settings.js \
  --no-eslintrc --config .eslintrc
```

Expected: 0 errors.

- [ ] **Step 3: Build minified output**

```bash
cd /Applications/MAMP/htdocs/moodle && grunt amd --root=blocks/mastermind_assistant
```

Expected: clean build, `amd/build/settings.min.js` updated.

- [ ] **Step 4: Commit**

```bash
git add amd/src/settings.js amd/build/settings.min.js amd/build/settings.min.js.map
git commit -m "feat(settings): connect/paste/disconnect state machine"
```

---

## Task 7: Disconnect web service

**Files:**
- Create: `classes/external/disconnect.php`
- Modify: `db/services.php`

- [ ] **Step 1: Write the failing test (the test file is detailed in Task 11; sketch here)**

```php
public function test_disconnect_clears_api_key() {
    $this->resetAfterTest();
    $this->setAdminUser();
    set_config('api_key', 'ma_live_test123456789012', 'block_mastermind_assistant');

    \block_mastermind_assistant\external\disconnect::execute();

    $this->assertEmpty(get_config('block_mastermind_assistant', 'api_key'));
}
```

- [ ] **Step 2: Implement `classes/external/disconnect.php`**

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
 * Clear the saved API key (admin-only).
 */
class disconnect extends external_api {
    public static function execute_parameters() {
        return new external_function_parameters([]);
    }

    public static function execute(): array {
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('moodle/site:config', $context);

        unset_config('api_key', 'block_mastermind_assistant');

        return ['success' => true];
    }

    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the disconnect succeeded'),
        ]);
    }
}
```

- [ ] **Step 3: Register the web service in `db/services.php`**

Add this entry to the `$functions` array:

```php
'block_mastermind_assistant_disconnect' => [
    'classname'   => 'block_mastermind_assistant\external\disconnect',
    'methodname'  => 'execute',
    'classpath'   => '',
    'description' => 'Clear the saved Mastermind API key (admin only).',
    'type'        => 'write',
    'ajax'        => true,
    'capabilities' => 'moodle/site:config',
],
```

- [ ] **Step 4: Bump `version.php` and run upgrade**

```bash
PATH=/Applications/MAMP/bin/php/php8.3.14/bin:$PATH php /Applications/MAMP/htdocs/moodle/admin/cli/upgrade.php --non-interactive
```

Expected: services table updated, no errors.

- [ ] **Step 5: Commit**

```bash
git add classes/external/disconnect.php db/services.php version.php
git commit -m "feat: add disconnect web service for clearing API key"
```

---

## Task 8: Migrate stale `dashboard_url` config + update `callback.php`

**Files:**
- Modify: `db/upgrade.php`
- Modify: `callback.php`

- [ ] **Step 1: Update `callback.php`**

Remove the `set_config('dashboard_url', ...)` line so existing installs no longer write a stale row on each connect:

```php
set_config('api_key', $key, 'block_mastermind_assistant');
// dashboard_url is now a hardcoded constant; do not persist it.
```

- [ ] **Step 2: Add upgrade step in `db/upgrade.php`**

Append before the final `return true;`:

```php
    if ($oldversion < 2026050600) {
        // dashboard_url is now a constant in api_client::DASHBOARD_URL.
        // Drop the persisted row so admin pages no longer surface a stale value.
        // Sites with $CFG->forced_plugin_settings['block_mastermind_assistant']['dashboard_url']
        // continue to override via standard Moodle mechanisms.
        unset_config('dashboard_url', 'block_mastermind_assistant');

        upgrade_block_savepoint(true, 2026050600, 'mastermind_assistant');
    }
```

- [ ] **Step 3: Run upgrade**

```bash
PATH=/Applications/MAMP/bin/php/php8.3.14/bin:$PATH php /Applications/MAMP/htdocs/moodle/admin/cli/upgrade.php --non-interactive
```

- [ ] **Step 4: Verify the row is gone**

```bash
PATH=/Applications/MAMP/bin/php/php8.3.14/bin:$PATH php /Applications/MAMP/htdocs/moodle/admin/cli/cfg.php --component=block_mastermind_assistant
```

Expected: no `dashboard_url` row in the output.

- [ ] **Step 5: Commit**

```bash
git add db/upgrade.php callback.php
git commit -m "feat: migrate dashboard_url to constant; drop stale config row on upgrade"
```

---

## Task 9: PHPUnit — `tests/api_client_test.php`

**Files:**
- Create: `tests/api_client_test.php`

- [ ] **Step 1: Write the test file**

```php
<?php
// (license header)

namespace block_mastermind_assistant;

defined('MOODLE_INTERNAL') || die();

global $CFG;

/**
 * @covers \block_mastermind_assistant\api_client
 */
class api_client_test extends \advanced_testcase {

    public function test_dashboard_url_uses_constant_by_default(): void {
        $this->resetAfterTest();
        // No config row, no forced override.
        $this->assertEquals(
            'https://mastermindassistant.ai',
            api_client::get_dashboard_url()
        );
    }

    public function test_dashboard_url_strips_trailing_slash_from_constant(): void {
        $this->resetAfterTest();
        $this->assertStringEndsNotWith('/', api_client::get_dashboard_url());
    }

    public function test_dashboard_url_respects_persisted_setting(): void {
        $this->resetAfterTest();
        set_config('dashboard_url', 'https://staging.example.org/', 'block_mastermind_assistant');

        $this->assertEquals(
            'https://staging.example.org',
            api_client::get_dashboard_url()
        );
    }

    public function test_dashboard_url_respects_forced_plugin_settings(): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->forced_plugin_settings = $CFG->forced_plugin_settings ?? [];
        $CFG->forced_plugin_settings['block_mastermind_assistant'] = [
            'dashboard_url' => 'https://forced.example.org',
        ];

        $this->assertEquals(
            'https://forced.example.org',
            api_client::get_dashboard_url()
        );
    }

    public function test_constructor_throws_without_api_key(): void {
        $this->resetAfterTest();
        $this->expectException(\moodle_exception::class);
        new api_client();
    }
}
```

- [ ] **Step 2: Run the tests**

```bash
PATH=/Applications/MAMP/bin/php/php8.3.14/bin:$PATH \
  php /Applications/MAMP/htdocs/moodle/vendor/bin/phpunit \
  --testsuite=block_mastermind_assistant_testsuite \
  /Applications/MAMP/htdocs/moodle/blocks/mastermind_assistant/tests/api_client_test.php
```

If the testsuite is not yet registered, run from inside the moodle dir:

```bash
cd /Applications/MAMP/htdocs/moodle && \
  PATH=/Applications/MAMP/bin/php/php8.3.14/bin:$PATH \
  php admin/tool/phpunit/cli/init.php
```

then re-run.

Expected: 5 passing tests.

- [ ] **Step 3: Commit**

```bash
git add tests/api_client_test.php
git commit -m "test: api_client URL resolution (constant, persisted, forced)"
```

---

## Task 10: PHPUnit — `tests/external/generate_connect_nonce_test.php` and `disconnect_test.php`

**Files:**
- Create: `tests/external/generate_connect_nonce_test.php`
- Create: `tests/external/disconnect_test.php`

- [ ] **Step 1: Write `tests/external/generate_connect_nonce_test.php`**

```php
<?php
// (license header)

namespace block_mastermind_assistant\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;

/**
 * @covers \block_mastermind_assistant\external\generate_connect_nonce
 */
class generate_connect_nonce_test extends \advanced_testcase {

    public function test_generates_unique_nonce(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $first = generate_connect_nonce::execute('');
        $second = generate_connect_nonce::execute('');

        $this->assertNotEquals($first['nonce'], $second['nonce']);
    }

    public function test_nonce_format(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $result = generate_connect_nonce::execute('');

        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $result['nonce']);
    }

    public function test_connect_url_includes_callback_and_nonce(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();

        $result = generate_connect_nonce::execute('');

        $expectedcallback = $CFG->wwwroot . '/blocks/mastermind_assistant/callback.php';
        $this->assertStringContainsString(
            'callback_url=' . rawurlencode($expectedcallback),
            $result['connect_url']
        );
        $this->assertStringContainsString(
            'nonce=' . $result['nonce'],
            $result['connect_url']
        );
    }

    public function test_returnurl_validation_falls_back_to_my(): void {
        global $SESSION;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Non-local URL should be rejected by PARAM_LOCALURL or normalised to /my/.
        generate_connect_nonce::execute('relative-without-leading-slash');

        $this->assertEquals('/my/', $SESSION->mastermind_connect_return);
    }

    public function test_requires_site_config_capability(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->expectException(\required_capability_exception::class);
        generate_connect_nonce::execute('');
    }
}
```

- [ ] **Step 2: Write `tests/external/disconnect_test.php`**

```php
<?php
// (license header)

namespace block_mastermind_assistant\external;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \block_mastermind_assistant\external\disconnect
 */
class disconnect_test extends \advanced_testcase {

    public function test_disconnect_clears_api_key(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('api_key', 'ma_live_test123456789012', 'block_mastermind_assistant');

        $result = disconnect::execute();

        $this->assertTrue($result['success']);
        $this->assertFalse(get_config('block_mastermind_assistant', 'api_key'));
    }

    public function test_disconnect_requires_site_config(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->expectException(\required_capability_exception::class);
        disconnect::execute();
    }
}
```

- [ ] **Step 3: Run tests**

```bash
PATH=/Applications/MAMP/bin/php/php8.3.14/bin:$PATH \
  php /Applications/MAMP/htdocs/moodle/vendor/bin/phpunit \
  /Applications/MAMP/htdocs/moodle/blocks/mastermind_assistant/tests/external/
```

Expected: all passing.

- [ ] **Step 4: Commit**

```bash
git add tests/external/
git commit -m "test: connect-nonce + disconnect external functions"
```

---

## Task 11: Behat — `tests/behat/settings_connect.feature`

**Files:**
- Create: `tests/behat/settings_connect.feature`

- [ ] **Step 1: Write the feature file**

```gherkin
@block @block_mastermind_assistant
Feature: The settings page is the primary activation surface for Mastermind Assistant
  In order to connect to Mastermind without hunting for the block
  As an admin
  I should see a Connect button on the plugin settings page

  Background:
    Given I log in as "admin"

  Scenario: Settings page shows Connect CTA when no key is saved
    Given the following config values are set as admin:
      | api_key |  | block_mastermind_assistant |
    When I navigate to "Plugins > Blocks > Mastermind Assistant" in site administration
    Then I should see "Not connected yet"
    And "Connect to Mastermind" "button" should exist
    And I should not see "Dashboard URL"

  Scenario: API key field stays hidden until "Paste it manually" is clicked
    Given the following config values are set as admin:
      | api_key |  | block_mastermind_assistant |
    When I navigate to "Plugins > Blocks > Mastermind Assistant" in site administration
    Then "API Key" "field" should not be visible
    When I click on "Paste it manually" "link"
    Then "API Key" "field" should be visible

  Scenario: Connected state shows status and Disconnect
    Given the following config values are set as admin:
      | api_key | ma_live_abcdef1234567890 | block_mastermind_assistant |
    When I navigate to "Plugins > Blocks > Mastermind Assistant" in site administration
    Then I should see "Connected ✓"
    And "Disconnect" "button" should exist
    And "API Key" "field" should not be visible

  Scenario: Edit / replace key reveals the API Key field
    Given the following config values are set as admin:
      | api_key | ma_live_abcdef1234567890 | block_mastermind_assistant |
    When I navigate to "Plugins > Blocks > Mastermind Assistant" in site administration
    And I click on "Edit / replace key" "button"
    Then "API Key" "field" should be visible
```

The selectors `"... " "button"` / `"... " "link"` are Moodle Behat shorthand. The `"field"` visibility check uses Behat's standard `should be visible` matcher which honours `display:none`.

- [ ] **Step 2: Initialise Behat (one-time)**

```bash
cd /Applications/MAMP/htdocs/moodle && \
  PATH=/Applications/MAMP/bin/php/php8.3.14/bin:$PATH \
  php admin/tool/behat/cli/init.php
```

- [ ] **Step 3: Run the feature**

```bash
cd /Applications/MAMP/htdocs/moodle && \
  PATH=/Applications/MAMP/bin/php/php8.3.14/bin:$PATH \
  vendor/bin/behat --config=$(pwd)/behatdata/behatrun/behat/behat.yml \
  blocks/mastermind_assistant/tests/behat/settings_connect.feature
```

Note: Running Behat locally needs Selenium/Chrome. If you're not running locally, leave this to CI (Plan C). Verify the feature **parses** (no Gherkin syntax errors) by running with `--dry-run`:

```bash
cd /Applications/MAMP/htdocs/moodle && \
  PATH=/Applications/MAMP/bin/php/php8.3.14/bin:$PATH \
  vendor/bin/behat --config=$(pwd)/behatdata/behatrun/behat/behat.yml \
  --dry-run \
  blocks/mastermind_assistant/tests/behat/settings_connect.feature
```

Expected: 4 scenarios, 0 syntax failures.

- [ ] **Step 4: Commit**

```bash
git add tests/behat/settings_connect.feature
git commit -m "test(behat): settings-page connect flow scenarios"
```

---

## Self-review checklist (run before merging Plan A)

- [ ] All PHPUnit tests pass: `phpunit blocks/mastermind_assistant/tests/`
- [ ] Behat dry-run shows no Gherkin syntax errors
- [ ] ESLint clean: `eslint amd/src/connect.js amd/src/settings.js`
- [ ] PHP lint clean: `php -l settings.php callback.php classes/api_client.php classes/external/disconnect.php classes/external/generate_connect_nonce.php`
- [ ] On a fresh install with no key: settings page shows "Not connected yet" + Connect button + "Paste it manually" link
- [ ] After clicking "Paste it manually" the API Key text input becomes visible
- [ ] With a saved key: settings shows "Connected ✓" + Disconnect button + redacted key
- [ ] Disconnect clears the key and reloads to disconnected state
- [ ] No "Dashboard URL" field is visible anywhere in the settings UI
- [ ] German strings render with `ae/oe/ue/ss` (matching existing convention)
