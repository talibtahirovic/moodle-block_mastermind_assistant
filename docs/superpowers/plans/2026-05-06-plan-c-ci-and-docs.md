# Plan C — CI + docs

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Every push and PR runs the full `moodle-plugin-ci` matrix — Moodle 4.4 / 4.5 / 5.0 / 5.1 / 5.2 with the appropriate PHP version per release — and a `TESTING.md` at the repo root captures the critical paths and the user-research rationale that drove Plans A and B.

**Architecture:**
- A single GitHub Actions workflow at `.github/workflows/moodle-ci.yml` defines the matrix and runs every `moodle-plugin-ci` step listed in the spec.
- `TESTING.md` at the repo root documents the activation funnel critical paths, the user-research note (Thiago / Bahiana, May 2026), and how to run the test suite locally.

**Tech Stack:** GitHub Actions, [moodle-plugin-ci](https://github.com/moodlehq/moodle-plugin-ci), Markdown.

---

## Spec coverage map

| Spec task | Plan task |
|---|---|
| 7. Mandatory CI | T1 |
| 8. `TESTING.md` | T2 |

---

## File structure

**Create:**
- `.github/workflows/moodle-ci.yml`
- `TESTING.md` (repo root)

**Modify:**
- `README.md` — add a CI badge (small additive change)

---

## Task 1: GitHub Actions workflow

**Files:**
- Create: `.github/workflows/moodle-ci.yml`

**PHP / Moodle compatibility matrix** (per [Moodle's release notes](https://moodledev.io/general/releases)):

| Moodle | Branch ref | PHP min | PHP max |
|---|---|---|---|
| 4.4 | `MOODLE_404_STABLE` | 8.1 | 8.3 |
| 4.5 | `MOODLE_405_STABLE` | 8.1 | 8.3 |
| 5.0 | `MOODLE_500_STABLE` | 8.2 | 8.3 |
| 5.1 | `MOODLE_501_STABLE` | 8.2 | 8.4 |
| 5.2 | `MOODLE_502_STABLE` | 8.3 | 8.4 |

Matrix below uses one PHP per Moodle (the lowest supported) to keep CI runtime sane; add an `include:` for additional PHPs if needed later.

- [ ] **Step 1: Create `.github/workflows/moodle-ci.yml`**

```yaml
name: moodle-plugin-ci

on:
  push:
    branches: [main]
  pull_request:
    branches: [main]

jobs:
  test:
    runs-on: ubuntu-22.04

    services:
      postgres:
        image: postgres:14
        env:
          POSTGRES_USER: postgres
          POSTGRES_HOST_AUTH_METHOD: trust
        ports:
          - 5432:5432
        options: >-
          --health-cmd pg_isready
          --health-interval 10s
          --health-timeout 5s
          --health-retries 8

    strategy:
      fail-fast: false
      matrix:
        include:
          - moodle-branch: 'MOODLE_404_STABLE'
            php: '8.1'
            database: 'pgsql'
          - moodle-branch: 'MOODLE_405_STABLE'
            php: '8.1'
            database: 'pgsql'
          - moodle-branch: 'MOODLE_500_STABLE'
            php: '8.2'
            database: 'pgsql'
          - moodle-branch: 'MOODLE_501_STABLE'
            php: '8.2'
            database: 'pgsql'
          - moodle-branch: 'MOODLE_502_STABLE'
            php: '8.3'
            database: 'pgsql'

    steps:
      - name: Checkout plugin
        uses: actions/checkout@v4
        with:
          path: plugin

      - name: Setup PHP ${{ matrix.php }}
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          extensions: mbstring, pgsql, zip, gd, soap, intl, xml, xmlrpc
          ini-values: max_input_vars=5000
          coverage: none

      - name: Initialise moodle-plugin-ci
        run: |
          composer create-project -n --no-dev --prefer-dist moodlehq/moodle-plugin-ci ci ^4
          echo $(cd ci/bin; pwd) >> "$GITHUB_PATH"
          echo $(cd ci/vendor/bin; pwd) >> "$GITHUB_PATH"
          sudo locale-gen en_AU.UTF-8
          echo "NVM_DIR=$HOME/.nvm" >> "$GITHUB_ENV"

      - name: Install Moodle and the plugin
        run: moodle-plugin-ci install --plugin ./plugin --db-host=127.0.0.1
        env:
          DB: ${{ matrix.database }}
          MOODLE_BRANCH: ${{ matrix.moodle-branch }}

      - name: PHP Lint
        if: ${{ !cancelled() }}
        run: moodle-plugin-ci phplint

      - name: PHP Copy/Paste Detector
        if: ${{ !cancelled() }}
        run: moodle-plugin-ci phpcpd

      - name: PHP Mess Detector
        if: ${{ !cancelled() }}
        run: moodle-plugin-ci phpmd

      - name: Moodle Code Checker
        if: ${{ !cancelled() }}
        run: moodle-plugin-ci codechecker --max-warnings 0

      - name: Moodle PHPDoc Checker
        if: ${{ !cancelled() }}
        run: moodle-plugin-ci phpdoc

      - name: Validating
        if: ${{ !cancelled() }}
        run: moodle-plugin-ci validate

      - name: Check upgrade savepoints
        if: ${{ !cancelled() }}
        run: moodle-plugin-ci savepoints

      - name: Mustache Lint
        if: ${{ !cancelled() }}
        run: moodle-plugin-ci mustache

      - name: Grunt
        if: ${{ !cancelled() }}
        run: moodle-plugin-ci grunt --max-lint-warnings 0

      - name: PHPUnit tests
        if: ${{ !cancelled() }}
        run: moodle-plugin-ci phpunit

      - name: Behat features
        if: ${{ !cancelled() }}
        run: moodle-plugin-ci behat --profile chrome
```

Notes:
- The workflow uses Postgres (the moodle-plugin-ci recommended default) — you can switch to MariaDB by changing the `services` block and the matrix `database` values.
- `if: ${{ !cancelled() }}` makes every step run even if an earlier step failed, so the run reports all failures at once instead of stopping at the first.
- `--max-warnings 0` and `--max-lint-warnings 0` make warnings fail the build; this matches the spec's "all must pass".
- The Behat step uses `--profile chrome` which is the upstream default for moodle-plugin-ci v4.

- [ ] **Step 2: Sanity-check YAML locally**

```bash
cd /Applications/MAMP/htdocs/moodle-block_mastermind_assistant && \
  python3 -c "import yaml,sys; yaml.safe_load(open('.github/workflows/moodle-ci.yml'))" && \
  echo OK
```

Expected: `OK`.

- [ ] **Step 3: Push to a feature branch and observe the run**

```bash
git checkout -b ci/moodle-plugin-ci
git add .github/workflows/moodle-ci.yml
git commit -m "ci: moodle-plugin-ci matrix across 4.4–5.2"
git push -u origin ci/moodle-plugin-ci
```

Then open the GitHub Actions tab on the repo. Five jobs should appear, one per matrix row. Iterate until all five are green. Common failure modes:

| Failure | Likely cause | Fix |
|---|---|---|
| `phpdoc` fails on `lib.php` | Missing `@param/@return` annotations | Add per Moodle phpdoc rules |
| `codechecker` warns on long lines | Lines > 132 chars | Reflow |
| `behat` flaky on first run | DB seeding race | Re-run; if persistent, add `--auto-rerun` |
| `grunt` fails with "node_modules missing" | NPM cache | Add `cache: npm` step |

- [ ] **Step 4: Add CI badge to `README.md`**

At the very top of `README.md`, after the title, add:

```markdown
[![moodle-plugin-ci](https://github.com/<org>/moodle-block_mastermind_assistant/actions/workflows/moodle-ci.yml/badge.svg)](https://github.com/<org>/moodle-block_mastermind_assistant/actions/workflows/moodle-ci.yml)
```

Replace `<org>` with the actual GitHub org/user. (If unknown at write time, leave a `TODO(badge-url)` comment so it gets filled in on first push.)

- [ ] **Step 5: Commit and merge**

After all matrix rows go green:

```bash
git add README.md
git commit -m "docs: add CI badge"
git push
```

Open a PR and merge once the workflow goes green on the PR itself.

---

## Task 2: `TESTING.md` at repo root

**Files:**
- Create: `TESTING.md`

- [ ] **Step 1: Write `TESTING.md`**

```markdown
# Testing

This plugin has a single rule: **the activation funnel must stay test-covered.**

The settings-page Connect flow is the primary activation surface (see [User-research note](#user-research-note)). Regressions there mean admins can't connect, which means the plugin is functionally dead — even if every other feature works. Treat tests around it the same way you'd treat tests around a payment endpoint.

## Critical paths that MUST stay covered

| Path | Where it lives | Test |
|---|---|---|
| Settings page Connect button renders + reacts | `settings.php`, `amd/src/settings.js`, `amd/src/connect.js` | `tests/behat/settings_connect.feature` |
| API key field hidden by default; revealed on "Paste manually" | `settings.php`, `amd/src/settings.js` | `tests/behat/settings_connect.feature` |
| Connected state shows redacted key + Disconnect | `settings.php`, `amd/src/settings.js`, `classes/external/disconnect.php` | `tests/behat/settings_connect.feature`, `tests/external/disconnect_test.php` |
| Sticky block instance created on install | `db/install.php` | `tests/install_test.php::test_install_creates_sticky_block_instance` |
| Bell-icon notification sent to all admins on install | `db/install.php`, `classes/local/setup_helper.php` | `tests/install_test.php::test_install_sends_admin_notification` |
| Admin banner respects `setup_complete` flag | `lib.php`, `classes/external/complete_setup.php` | `tests/external/complete_setup_test.php` |
| Course nav node added for users with view capability | `lib.php` | `tests/navigation_test.php` |
| Connect nonce uniqueness + format | `classes/external/generate_connect_nonce.php` | `tests/external/generate_connect_nonce_test.php` |
| Dashboard URL constant + `forced_plugin_settings` override | `classes/api_client.php` | `tests/api_client_test.php` |
| Install must succeed even if ping fails | `db/install.php` | `tests/install_test.php::test_install_silent_on_ping_failure` |

## The rule

**Any change to** any of these files **requires running PHPUnit AND Behat locally before commit:**

- `settings.php`
- `db/install.php`
- `db/upgrade.php`
- `classes/api_client.php`
- `classes/local/setup_helper.php`
- `classes/external/generate_connect_nonce.php`
- `classes/external/disconnect.php`
- `classes/external/complete_setup.php`
- `lib.php`
- `amd/src/connect.js`
- `amd/src/settings.js`
- `amd/src/setup_banner.js`

CI runs the full suite on every push, but a green CI is a slow signal. Local runs catch regressions before the PR.

## Running tests locally

### PHPUnit

One-time setup (per Moodle install):

```bash
cd /path/to/moodle
php admin/tool/phpunit/cli/init.php
```

Run the plugin's PHPUnit suite:

```bash
cd /path/to/moodle
vendor/bin/phpunit blocks/mastermind_assistant/tests/
```

Run a single file:

```bash
vendor/bin/phpunit blocks/mastermind_assistant/tests/api_client_test.php
```

Run a single test method:

```bash
vendor/bin/phpunit --filter test_dashboard_url_uses_constant_by_default \
  blocks/mastermind_assistant/tests/api_client_test.php
```

### Behat

One-time setup:

```bash
cd /path/to/moodle
php admin/tool/behat/cli/init.php
```

Run the plugin's Behat features:

```bash
cd /path/to/moodle
vendor/bin/behat \
  --config=$(pwd)/behatdata/behatrun/behat/behat.yml \
  blocks/mastermind_assistant/tests/behat/
```

Run a single feature:

```bash
vendor/bin/behat \
  --config=$(pwd)/behatdata/behatrun/behat/behat.yml \
  blocks/mastermind_assistant/tests/behat/settings_connect.feature
```

If Behat is too heavy to set up locally, push to a feature branch and let CI exercise it. The matrix runs Behat on every supported Moodle version — local runs are an optimisation, not a gate.

### Quick lint loop

For JS-only changes:

```bash
cd /path/to/moodle
./node_modules/.bin/eslint blocks/mastermind_assistant/amd/src/ \
  --no-eslintrc --config .eslintrc
```

For PHP-only changes:

```bash
PATH=/path/to/php/bin:$PATH \
  php -l blocks/mastermind_assistant/<file>.php
```

For full pre-commit confidence run [moodle-plugin-ci](https://github.com/moodlehq/moodle-plugin-ci) locally — it mirrors the CI exactly.

## User-research note

The settings page is the primary activation surface because **real admins go straight to *Site administration → Plugins → Blocks → Mastermind Assistant → Settings* before they ever notice the in-page block**.

Two independent users at Escola Bahiana de Medicina e Saúde Pública (Thiago + a faculty member) confirmed this on 2026-05-06. Thiago activated the plugin manually by:

1. Going to mastermindassistant.ai
2. Creating an account
3. Copying his API key
4. Pasting it into the Moodle settings page

Six manual steps replacing what was meant to be one Connect click. The block lives in `side-pre`, which the default Boost theme renders on the right side and most users overlook entirely.

**Do not move activation UX out of the settings page** without re-validating with users. The block-side Connect button stays as a contextual companion, but the settings page is the funnel. Plans `2026-05-06-plan-a-settings-activation-surface.md` and `2026-05-06-plan-b-install-nudge-course-nav.md` document the implementation rationale.

## When you find a gap

If you find a bug that *should* have been caught by an existing test, write a failing test first that reproduces it, then fix the bug. The new test stays.

If you find a critical path missing from the table above, add a row + a test in the same PR that introduces or modifies it. Don't ship coverage debt.
```

- [ ] **Step 2: Commit**

```bash
git add TESTING.md
git commit -m "docs: TESTING.md — critical paths, local commands, user-research note"
```

---

## Self-review checklist (before merging Plan C)

- [ ] CI workflow YAML is valid (`yaml.safe_load` succeeds)
- [ ] All 5 matrix rows execute on a real GitHub Actions run
- [ ] All matrix rows are green
- [ ] CI badge URL points to the real repo (no leftover `<org>` placeholder)
- [ ] `TESTING.md` lists every critical-path file and its test
- [ ] `TESTING.md` includes the user-research note (Thiago / Bahiana, May 2026)
- [ ] `TESTING.md` includes the run-tests-locally commands
- [ ] PRs from forks correctly trigger the workflow (or document the policy if not)

---

## Cross-plan dependencies

This plan assumes Plans A and B have landed first — the test files referenced in `TESTING.md` exist (`tests/api_client_test.php`, `tests/install_test.php`, etc.). If Plan C is executed before A or B, the `TESTING.md` table rows for missing files will be aspirational; that's acceptable as long as the rows get filled in by the corresponding plan.

`moodle-plugin-ci behat` will fail until Plan A's `tests/behat/settings_connect.feature` lands. If you must merge Plan C in isolation, comment out the Behat step temporarily and add a TODO to re-enable when Plan A merges.
