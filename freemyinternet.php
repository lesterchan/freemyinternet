<?php
/**
 * Plugin Name: FreeMyInternet
 * Plugin URI: https://lesterchan.net/portfolio/programming/php/
 * Description: Display a site-wide protest banner or full-screen blackout overlay, with an optional start and end date.
 * Version: 1.0.0
 * Requires at least: 6.8
 * Requires PHP: 8.2
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
	it under the terms of the GNU General Public License, version 2, as
	published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

defined( 'ABSPATH' ) || exit;

define( 'FREEMYINTERNET_VERSION', '1.0.0' );
define( 'FREEMYINTERNET_DB_VERSION', '1' );
define( 'FREEMYINTERNET_SLUG', 'freemyinternet' );
define( 'FREEMYINTERNET_MAIN_FILE', __FILE__ );
define( 'FREEMYINTERNET_DIR', plugin_dir_path( __FILE__ ) );
define( 'FREEMYINTERNET_URL', plugin_dir_url( __FILE__ ) );

require_once FREEMYINTERNET_DIR . 'includes/class-freemyinternet.php';

FreeMyInternet::init();
