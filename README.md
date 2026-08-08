# FreeMyInternet
Contributors: GamerZ  
Donate link: https://lesterchan.net/site/donation/  
Tags: protest, blackout, banner, notice, censorship  
Requires at least: 6.8  
Tested up to: 7.0  
Stable tag: 1.0.0  
Requires PHP: 8.2  
License: GPLv2 or later  
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display a site-wide protest banner or full-screen blackout overlay, with an optional start and end date.

## Description

Put a protest notice on your site. Choose between a **full-screen blackout** that covers the whole site, and a **top banner** that leaves your content readable, then write your own heading, message, link and colours.

Everything is configured under `Settings -> FreeMyInternet`. Nothing is shown to visitors until you switch it on.

This plugin was written in June 2013 to support the FreeMyInternet campaign against the Media Development Authority of Singapore's licensing scheme, and originally displayed that campaign's banner and nothing else. Since 1.0.0 it is a general-purpose protest and blackout plugin, so it can be used for whatever you are protesting. The initial idea came from xenomancer's [Censor Me](https://wordpress.org/plugins/censor-me/ "Censor Me").

### Features

* Two presentations: full-screen blackout, or a top banner
* Your own heading, message, call-to-action link and colours
* Optional image
* **Schedule** — set a start and end date and the notice appears and disappears on its own
* **Dismissable** — an optional close button, remembered per visitor until you change the notice
* Never renders in `wp-admin` or on the login screen, so it cannot lock you out
* No extra HTTP requests — the few kilobytes of CSS and JavaScript are inlined, and only while a protest is actually running

### Donations
I spent most of my free time creating, updating, maintaining and supporting these plugins, if you really love my plugins and could spare me a couple of bucks, I will really appreciate it. If not feel free to use it without any obligations.

## Installation

1. Install and activate the plugin.
1. Go to `WP-Admin -> Settings -> FreeMyInternet` and write your heading, message, link and colours, and choose the full-screen blackout or the top banner.
1. Switch it on. **Nothing is shown to visitors until you do**, so you can set it up in advance and turn it on when you mean to.

## Usage

Use the `freemyinternet_should_display` filter to control where the notice appears — for example, to show it to logged-out visitors only:

```php
add_filter( 'freemyinternet_should_display', function ( $display, $options ) {
	return $display && ! is_user_logged_in();
}, 10, 2 );
```

Use the `freemyinternet_capability` filter to hand the settings screen to a capability other than `manage_options`:

```php
add_filter( 'freemyinternet_capability', function ( $capability, $context ) {
	return 'edit_pages';
}, 10, 2 );
```

## Frequently Asked Questions

### I activated the plugin and nothing happens

That is deliberate. Write a heading or a message under `Settings -> FreeMyInternet` and tick **Show the notice**. A notice with no heading and no message is never displayed.

### The notice is switched on but visitors do not see it

Check the schedule. If the end date has passed, or the start date has not arrived, nothing is rendered. The settings screen warns you when the current date is outside the window.

### Will it black out my dashboard?

No. The notice renders on the front end only — never in `wp-admin`, never on the login screen.

### How do I show it on some pages only?

Use the `freemyinternet_should_display` filter, described above.

### Can visitors close it?

Only if you tick **Let visitors dismiss it**. The dismissal is remembered in the visitor's browser until you change the notice's text or schedule, at which point it is shown again.

## Screenshots

1. Settings -> FreeMyInternet, where the notice, its colours and the window it appears in are set
2. The full-screen blackout, as a visitor meets it
3. The same notice as a top banner, which leaves the site readable

## Changelog

### 1.0.0
* BREAKING: Requires WordPress 6.8 and PHP 8.2.
* BREAKING: The notice is now **off by default**, and the built-in 2013 campaign banner is gone. See the upgrade notice below
* BREAKING: The global `freemyinternet()` function is removed. It was the whole plugin — hooked to `the_title` and `get_header` — and any theme or snippet calling it directly will fatal
* NEW: Rewritten as a general-purpose protest plugin — write your own heading, message, link, image and colours instead of a fixed campaign banner
* NEW: Two presentations — full-screen blackout, or a top banner that leaves the site readable
* NEW: Optional start and end date, outside which nothing is displayed
* NEW: Optional dismiss button, remembered per visitor until the notice changes
* NEW: Settings screen at `Settings -> FreeMyInternet`, built on the WordPress Settings API
* NEW: `freemyinternet_should_display` filter, for controlling where the notice appears
* NEW: `freemyinternet_capability` filter, for handing the settings screen to another capability
* CHANGED: Restructured into `includes/`, with every setting in the `freemyinternet_options` row and the plugin and schema versions in `freemyinternet_version`
* CHANGED: CSS and JavaScript are inlined instead of being served as separate files, and the jQuery dependency is gone
* CHANGED: The stylesheet is written with CSS logical properties, so one sheet serves both writing directions
* FIXED: The banner callback was registered on the `the_title` filter but returned nothing, which emptied **every title on the site** — in the document title, navigation menus, widgets and archive listings — and emitted a copy of the banner at each one
* FIXED: The banner image was loaded over plain HTTP from `freemyinternet.com`, which no longer resolves. The result was an opaque black layer covering the site with no image, no dismissal and a dead link

## Upgrade Notice

### 1.0.0

Requires WordPress 6.8 and PHP 8.2.

**The notice is off by default, and the built-in 2013 campaign banner is gone.** 0.01 blacked the site out the moment it was activated, using an image from a domain that no longer resolves, so visitors saw a black screen. Write your own notice under `Settings -> FreeMyInternet`, pick a presentation and tick **Show the notice**.

**The global `freemyinternet()` function is gone.** It was the entire plugin in 0.01, hooked to `the_title` and `get_header`, and a theme or snippet calling it directly will now stop the page with a fatal error. There is nothing to call in its place: the notice renders itself from the settings, and where it appears is decided by the `freemyinternet_should_display` filter.

**Fixed:** a bug that emptied every post title on the site.
