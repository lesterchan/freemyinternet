# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

FreeMyInternet follows `_standards/STANDARDS.md` in the parent folder, which is
the contract for all nineteen plugins in the collection. Where this file and
that one disagree, that one wins.

It is the one plugin in the collection with no `WP_` prefix: classes are
`FreeMyInternet_*`, the option rows are `freemyinternet_*`, and `{{SLUG}}`,
`{{UNDER}}` and `{{UPPER}}` all collapse to one word.

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

Two rows, both per §2.1: `freemyinternet_options` (settings) and
`freemyinternet_version` (`plugin` + `db`). The migration folds in the pre-1.0.0
row named `freemyinternet` — the bare slug — which only ever existed inside the
unreleased major, so it is seen only on a development build.

**`enabled` defaults to false, and that is the whole point of the rebuild.** An
upgrade must not silently keep blacking a site out and a fresh activation must
not start. Do not "helpfully" default it on.

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
  drawn. A call in `render_page()` renders every queued notice twice. The
  consequence is easy to miss: anything the screen wants to *say* has to be
  queued **before** that printer runs, which is why the closed-window warning is
  added on `load-{$hook}` from `add_page()` and not while rendering. §4.2.2 has
  the rule and the other side of it; wp-ban carries the same comment.
* `maybe_upgrade()` runs on activation **and on every admin load**, because
  activation hooks do not fire on plugin update — the usual reason a migration
  never runs.

## Tests

`tests/test-schedule.php` is the interesting file: ten tests over the
open/closed window logic, which is the only real state machine here.
`test-title.php` is a regression guard — the released 0.01's banner callback was
hooked to `the_title` and **emptied every post title on the site** (commit
`e173d1c`). `tests/e2e/` covers the two presentations, the dismissal round trip
and the settings form.

freemyinternet is the collection's reference for assertion-failure messages:
§7.2's audit measured it at 0% missing while the collection average was 71%.

## Known discrepancies

None outstanding. Four were closed on 2026-08-02, and all four were the cost of
this plugin's agent having run before the spec settled — it is the one whose work
predates the fan-out (§1.1's floor change, §3.1's licence wording):

* The GPL block was the v2-**only** wording under a `License: GPLv2 or later`
  header and a `GPL-2.0-or-later` composer.json. §3.1 mandates the "or later"
  form, and this was the last plugin still contradicting itself.
* The 1.0.0 Upgrade Notice never named the removed global `freemyinternet()`,
  which was the entire plugin in 0.01 and fatals for anything still calling it.
* The changelog carried `CHANGED: Minimum WordPress 6.0 and PHP 7.4` directly
  under `BREAKING: Requires WordPress 6.8 and PHP 8.2`.
* `FreeMyInternet_Display::enqueue()` passed the boolean `$in_footer` with a
  comment explaining that the `$args` array form needed WP 6.3 and the floor was
  6.0. The floor is 6.8; it now passes `array( 'in_footer' => true )`.

A fifth was closed the same day, and it was a bug rather than drift: the
settings screen called `settings_errors()` under Settings, so "Settings saved."
printed twice. The 2026-08-02 end-to-end sweep found it
(`settings.spec.js:212`), and it is recorded here because the fix moved the
closed-window warning out of `render_page()` — see the trap above.

The Donations paragraph also appeared twice — once loose in `## Description` and
once under `### Donations` where §3.3 puts it. The loose copy is gone.

**The FSF postal address is drift, not a discrepancy.** This plugin, wp-showhide
and wp-relativedate carry `51 Franklin St` and the other sixteen carry
`59 Temple Place`. §3.1 elides that line, and 51 Franklin St is the address the
FSF actually uses, so the three are arguably right and the sixteen wrong.
Deciding which way the collection converges is a call for the collection, not for
this file.
