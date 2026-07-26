<?php
/*
Plugin Name: FreeMyInternet
Plugin URI: https://www.facebook.com/FreeMyInternet
Description: Automatically places the FreeMyInternet banner from <a href="http://freemyinternet.com">FreeMyInternet.com</a> on your WordPress website. Props to xenomancer of <a href="http://wordpress.org/plugins/censor-me/">Censor Me</a> for the initial idea.
Version: 0.01
Author: FreeMyInternet
Author URI: http://freemyinternet.com/
*/


/*
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

/*
 * Note: this was previously also hooked onto the `the_title` FILTER. The callback
 * echoes and has no return value, so every title on the site filtered to an empty
 * string, and a copy of the banner was emitted at each title -- including inside
 * <title>, nav menus and widgets. A banner is not a title transformation; the
 * filter registration was simply wrong and is removed.
 */
add_action( 'get_header', 'freemyinternet', 5 );
function freemyinternet() {
	echo '<a style="width:100%;height:100%;vertical-align:middle;text-align:center;background-color:#000;position:absolute;z-index:8888;top:0px;left:0px;background-image:url(http://freemyinternet.com/free_my_internet.jpg);background-position:center center;background-repeat:no-repeat;" href="http://freemyinternet.com"></a>';
}
