<?php
/**
 * Uninstaller: removes everything the plugin stored.
 *
 * @package FreeMyInternet
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Delete the plugin's options for the current site.
 *
 * @return void
 */
function freemyinternet_uninstall_site() {
	delete_option( 'freemyinternet_options' );
	delete_option( 'freemyinternet_version' );

	// The settings row was named after the slug alone before 1.0.0 shipped. It is
	// deleted by the upgrade routine, so this only catches an install that never
	// reached wp-admin between updating and being removed.
	delete_option( 'freemyinternet' );
}

if ( is_multisite() ) {
	// 'number' => 0 is required: WP_Site_Query defaults to 100, so without it a
	// network larger than that would leave the options behind on every site past
	// the hundredth, and uninstall would still report success.
	$site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $site_ids as $site_id ) {
		switch_to_blog( (int) $site_id );
		freemyinternet_uninstall_site();
		restore_current_blog();
	}
} else {
	freemyinternet_uninstall_site();
}
