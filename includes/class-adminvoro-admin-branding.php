<?php
/**
 * Admin branding.
 *
 * @package Adminvoro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles custom admin footer text.
 */
class Adminvoro_Admin_Branding {
	/**
	 * Plugin options.
	 *
	 * @var array
	 */
	private $options;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->options = Adminvoro::get_options();

		if ( ! empty( $this->options['enable_admin_footer_text'] ) && '' !== trim( $this->options['custom_admin_footer_text'] ) ) {
			add_filter( 'admin_footer_text', array( $this, 'filter_admin_footer_text' ) );
		}
	}

	/**
	 * Replace default admin footer text.
	 *
	 * @param string $text Existing footer text.
	 * @return string
	 */
	public function filter_admin_footer_text( $text ) {
		unset( $text );

		return wp_kses_post( $this->options['custom_admin_footer_text'] );
	}
}
