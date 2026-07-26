<?php
/**
 * Plugin Name: FreeMyInternet
 * Plugin URI: https://lesterchan.net/portfolio/programming/php/
 * Description: Display a site-wide protest banner or full-screen blackout overlay, with an optional start and end date.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Lester 'GaMerZ' Chan
 * Author URI: https://lesterchan.net
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: freemyinternet
 * Domain Path: /languages
 *
 * @package FreeMyInternet
 */

/*
	Copyright 2026  Lester Chan  (email : lesterchan@gmail.com)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

// Plugin version.
define( 'FREEMYINTERNET_VERSION', '1.0.0' );

// Main plugin file, for resolving paths and URLs from the includes.
define( 'FREEMYINTERNET_MAIN_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-freemyinternet-options.php';
require_once __DIR__ . '/includes/class-freemyinternet-frontend.php';

FreeMyInternet_Frontend::init();

if ( is_admin() ) {
	require_once __DIR__ . '/includes/class-freemyinternet-admin.php';
	FreeMyInternet_Admin::init();
}
