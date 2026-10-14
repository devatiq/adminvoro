<?php
/**
 * Adminvoro Toolkit uninstall cleanup.
 *
 * @package Adminvoro
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$adminvoro_options = array(
	'adminvoro_options',
	'adminvoro_redirects',
);

/**
 * Delete data for the current site only when explicitly requested.
 *
 * @param array $option_names Option names to delete.
 * @return void
 */
function adminvoro_maybe_delete_site_data( $option_names ) {
	$settings = get_option( 'adminvoro_options', array() );

	if ( ! is_array( $settings ) || empty( $settings['delete_data_on_uninstall'] ) ) {
		return;
	}

	foreach ( $option_names as $option_name ) {
		delete_option( $option_name );
	}
}

if ( is_multisite() ) {
	$adminvoro_site_ids = get_sites(
		array(
			'fields' => 'ids',
		)
	);

	foreach ( $adminvoro_site_ids as $adminvoro_site_id ) {
		switch_to_blog( $adminvoro_site_id );

		adminvoro_maybe_delete_site_data( $adminvoro_options );

		restore_current_blog();
	}
} else {
	adminvoro_maybe_delete_site_data( $adminvoro_options );
}
