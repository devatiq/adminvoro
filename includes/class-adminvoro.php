<?php
/**
 * Core plugin coordinator.
 *
 * @package Adminvoro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads dependencies and wires feature modules.
 */
final class Adminvoro {
	/**
	 * Singleton instance.
	 *
	 * @var Adminvoro|null
	 */
	private static $instance = null;

	/**
	 * Get plugin instance.
	 *
	 * @return Adminvoro
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Activation callback.
	 *
	 * @return void
	 */
	public static function activate() {
		self::migrate_legacy_options();

		if ( false === get_option( ADMINVORO_OPTION, false ) ) {
			add_option( ADMINVORO_OPTION, self::get_default_options() );
		}

		if ( false === get_option( ADMINVORO_REDIRECTS_OPTION, false ) ) {
			add_option( ADMINVORO_REDIRECTS_OPTION, array() );
		}

		flush_rewrite_rules();
	}

	/**
	 * Deactivation callback.
	 *
	 * @return void
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * Run plugin.
	 *
	 * @return void
	 */
	public function run() {
		self::migrate_legacy_options();

		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		$this->load_dependencies();

		add_action( 'init', array( $this, 'bootstrap_features' ), 0 );

		if ( is_admin() ) {
			new Adminvoro_Admin();
		}
	}

	/**
	 * Instantiate frontend and shared features.
	 *
	 * @return void
	 */
	public function bootstrap_features() {
		new Adminvoro_Login();
		new Adminvoro_Redirects();
		new Adminvoro_Security();
		new Adminvoro_Performance();
		new Adminvoro_Admin_Branding();
	}

	/**
	 * Include class files.
	 *
	 * @return void
	 */
	private function load_dependencies() {
		require_once ADMINVORO_PATH . 'includes/class-adminvoro-login.php';
		require_once ADMINVORO_PATH . 'includes/class-adminvoro-redirects.php';
		require_once ADMINVORO_PATH . 'includes/class-adminvoro-security.php';
		require_once ADMINVORO_PATH . 'includes/class-adminvoro-performance.php';
		require_once ADMINVORO_PATH . 'includes/class-adminvoro-admin-branding.php';

		if ( is_admin() ) {
			require_once ADMINVORO_PATH . 'includes/class-adminvoro-admin.php';
		}
	}

	/**
	 * Copy legacy NexiSettings options to Adminvoro option names when needed.
	 *
	 * @return void
	 */
	public static function migrate_legacy_options() {
		$option_map = array(
			'nexisettings_options'  => ADMINVORO_OPTION,
			'nexisettings_redirects' => ADMINVORO_REDIRECTS_OPTION,
		);

		foreach ( $option_map as $legacy_option => $new_option ) {
			if ( false !== get_option( $new_option, false ) ) {
				continue;
			}

			$legacy_value = get_option( $legacy_option, false );

			if ( false !== $legacy_value ) {
				add_option( $new_option, $legacy_value );
			}
		}
	}

	/**
	 * Load plugin translations.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'adminvoro', false, dirname( ADMINVORO_BASENAME ) . '/languages' );
	}

	/**
	 * Default plugin options.
	 *
	 * @return array
	 */
	public static function get_default_options() {
		return array(
			'enable_custom_login'          => 0,
			'custom_login_slug'           => '',
			'login_block_action'          => '404',
			'login_block_custom_url'      => '',
			'login_logo_id'               => 0,
			'login_logo_url'              => '',
			'login_logo_text'             => '',
			'login_background_color'      => '',
			'login_text_color'            => '',
			'login_link_color'            => '',
			'login_logo_text_size'        => 18,
			'disable_xmlrpc'              => 0,
			'disable_user_enumeration'    => 0,
			'hide_wp_version'             => 0,
			'disable_emojis'              => 0,
			'disable_embeds'              => 0,
			'enable_admin_footer_text'    => 0,
			'custom_admin_footer_text'    => '',
		);
	}

	/**
	 * Get merged plugin options.
	 *
	 * @return array
	 */
	public static function get_options() {
		$options = get_option( ADMINVORO_OPTION, array() );

		if ( ! is_array( $options ) ) {
			$options = array();
		}

		return wp_parse_args( $options, self::get_default_options() );
	}

	/**
	 * Is custom login protection disabled by constant.
	 *
	 * @return bool
	 */
	public static function is_custom_login_disabled() {
		return defined( 'ADMINVORO_DISABLE_CUSTOM_LOGIN' ) && ADMINVORO_DISABLE_CUSTOM_LOGIN;
	}

	/**
	 * Reserved login slugs that must not be used.
	 *
	 * @return array
	 */
	public static function get_reserved_login_slugs() {
		return array(
			'wp-admin',
			'wp-content',
			'wp-includes',
			'wp-json',
			'wp-login',
			'login',
			'admin',
			'xmlrpc',
			'feed',
			'robots',
			'sitemap',
		);
	}

	/**
	 * Determine whether the current request is a protected WordPress subsystem.
	 *
	 * @return bool
	 */
	public static function is_protected_request_context() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return true;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}

		return false;
	}
}
