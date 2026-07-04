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
	'nexisettings_options',
	'nexisettings_redirects',
);

foreach ( $adminvoro_options as $adminvoro_option ) {
	delete_option( $adminvoro_option );
}

if ( is_multisite() ) {
	$adminvoro_site_ids = get_sites(
		array(
			'fields' => 'ids',
		)
	);

	foreach ( $adminvoro_site_ids as $adminvoro_site_id ) {
		switch_to_blog( $adminvoro_site_id );

		foreach ( $adminvoro_options as $adminvoro_option ) {
			delete_option( $adminvoro_option );
		}

		restore_current_blog();
	}
}
