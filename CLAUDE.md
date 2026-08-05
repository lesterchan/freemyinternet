# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

Classes here are `FreeMyInternet_*` and the option rows are `freemyinternet_*` —
there is no `WP_` prefix, and the plugin's name is one word everywhere.

## What it is

A configurable protest overlay. `Settings → FreeMyInternet` writes a heading, a
message, a link, an image and a colour pair; the front end renders that as
either a full-screen blackout or a top banner, optionally within a scheduled
window and optionally dismissible.

This is a **rebuild**, not a refresh. The released 0.01 hard-coded the 2013
anti-censorship campaign: it blacked the site out unconditionally on activation,
using an image from a domain that no longer resolves. Everything the plugin now
does is configuration of what used to be a constant.

## Data

Two rows: `freemyinternet_options` for the settings and `freemyinternet_version`
for the `plugin` and `db` upgrade markers. **The markers are a row of their own
and must stay that way** — a marker inside the settings array has to be rescued
from the stored value on every save, because the settings form never posts one.

The migration folds in the pre-1.0.0 row named `freemyinternet` — the bare slug
— which only ever existed inside the unreleased major, so it is seen only on a
development build.

**`enabled` defaults to false, and that is the whole point of the rebuild.** An
upgrade must not silently keep blacking a site out and a fresh activation must
not start one. Do not "helpfully" default it on.

## Traps

* **Everything hangs off `wp_footer`, which is front-end only.** Not wp-admin,
  not wp-login.php. That is what makes it impossible for a misconfigured overlay
  to lock an owner out of their own dashboard. Do not move the render to
  `template_redirect` or an output buffer.
* **The CSS and JS are inline heredocs in `FreeMyInternet_Display`, not files.**
  Deliberate: a few kilobytes, printed only while a protest is actually running,
  and inlining removes the flash of an unstyled full-screen overlay. They are
  registered as **srcless handles** and attached with `wp_add_inline_style()` /
  `wp_add_inline_script()` rather than echoed, so output still goes through
  core's printers — which is what lets a CSP plugin filtering `script_loader_tag`
  attach its nonce. There is no `js/` or `css/` directory and there should not be.
* **`dismiss_key()` is an md5 of the notice's content and schedule.** The
  dismissal is stored in `localStorage` against that signature, so editing the
  protest text shows the overlay again to everyone who dismissed the previous
  one. Adding a field that changes what visitors read means adding it to
  `dismiss_key()` too, or dismissals will stick across a rewrite.
* **`message` is the only field allowed markup** (`wp_kses_post()`); everything
  else is `sanitize_text_field()`, `esc_url_raw()` or `sanitize_hex_color()`.
  URLs are sanitised on the way in *and* escaped on the way out, so a
  `javascript:` target planted by some other means still cannot reach the page.
* **An unparseable schedule bound is discarded, not defaulted to now.** An empty
  bound means "unbounded in that direction", so defaulting would close a window
  the owner meant to leave open. `test_an_unparseable_bound_is_ignored_rather_than_closing_the_window`
  pins it, and `test_the_bounds_are_read_in_the_site_timezone_rather_than_utc`
  pins the other half.
* Both bounds go through `wp_timezone()`, never UTC. A site owner types local
  time into a `datetime-local` field.
* **The settings screen prints no notices of its own, and must not start.** It
  is an `add_options_page()` screen, so `admin-header.php` requires
  `options-head.php` — which calls `settings_errors()` — before the page is
  drawn. A call in `render_page()` renders every queued notice twice, which is
  a bug this plugin actually shipped. The consequence is easy to miss: anything
  the screen wants to *say* has to be queued **before** that printer runs, which
  is why the closed-window warning is added on `load-{$hook}` from `add_page()`
  and not while rendering.
* `maybe_upgrade()` runs on activation **and on every admin load**, because
  activation hooks do not fire on plugin update — the usual reason a migration
  never runs.

## Migrations, and why they are tested through a browser

The admin path carries machinery WP-CLI does not, and `tests/e2e/upgrade.spec.js`
exists for that difference:

* **On the admin path `register_setting()` has already run**, so the sanitize
  callback is attached to the settings row and every write the migration makes
  goes through it. **A migration test that never registers the setting is
  testing WP-CLI**, not the path real sites take.
* **Read the row raw when the question is "was it written".** The options
  accessor merges the defaults over whatever is stored, so it answers
  identically for a row holding the defaults and for no row at all — which is
  the state a migration that read, deleted and never wrote leaves behind.
* **Seed the shipped defaults, not customised values.** A customised fixture
  cannot see that failure: its migrated result differs from the defaults, so the
  write lands whatever the read before it did.
* This plugin passes no `default` to `register_setting()`, so that trap is not
  armed here — but the write helper is built as though it were, so adding one
  later cannot quietly break the migration.

## Tests

`bin/test.sh` runs PHPUnit, `bin/test-multisite.sh` the network pass, and
`bin/test-e2e.sh` the Playwright suite. **Run them rather than trusting a note
about their last result** — CI is the authority, and this file cannot be.

`tests/test-schedule.php` is the interesting file: ten tests over the
open/closed window logic, which is the only real state machine here.
`test-title.php` is a regression guard — the released 0.01's banner callback was
hooked to `the_title` and **emptied every post title on the site** (commit
`e173d1c`). `tests/e2e/` covers the two presentations, the dismissal round trip,
the settings form and the migration.

**Every assertion in this suite carries a failure message**, including the ones
PHPUnit would report legibly on its own. It is the plugin to copy when writing
tests elsewhere.
