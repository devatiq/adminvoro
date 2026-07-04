<?php
/**
 * Admin settings UI.
 *
 * @package Adminvoro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds and saves Adminvoro admin screens.
 */
class Adminvoro_Admin {
	/**
	 * Admin page slug.
	 *
	 * @var string
	 */
	const MENU_SLUG = 'adminvoro';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_adminvoro_save_redirects', array( $this, 'save_redirects' ) );
		add_action( 'wp_ajax_adminvoro_save_options', array( $this, 'ajax_save_options' ) );
		add_action( 'wp_ajax_adminvoro_save_redirects', array( $this, 'ajax_save_redirects' ) );
		add_filter( 'plugin_action_links_' . ADMINVORO_BASENAME, array( $this, 'add_settings_link' ) );
	}

	/**
	 * Add top-level menu page.
	 *
	 * @return void
	 */
	public function add_menu_page() {
		add_menu_page(
			esc_html__( 'Adminvoro Toolkit', 'adminvoro' ),
			esc_html__( 'Adminvoro Toolkit', 'adminvoro' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_page' ),
			'dashicons-shield',
			58
		);
	}

	/**
	 * Register primary plugin setting.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'adminvoro_options_group',
			ADMINVORO_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_options' ),
				'default'           => Adminvoro::get_default_options(),
			)
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( 'toplevel_page_' . self::MENU_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style(
			'adminvoro-admin',
			ADMINVORO_URL . 'assets/css/admin.css',
			array(),
			ADMINVORO_VERSION
		);
		wp_enqueue_script(
			'adminvoro-admin',
			ADMINVORO_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			ADMINVORO_VERSION,
			true
		);
		wp_localize_script(
			'adminvoro-admin',
			'adminvoroSettingsAdmin',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'adminvoro_ajax_save' ),
				'chooseLogo'  => esc_html__( 'Choose login logo', 'adminvoro' ),
				'useLogo'     => esc_html__( 'Use this logo', 'adminvoro' ),
				'noLogo'      => esc_html__( 'No logo selected', 'adminvoro' ),
				'saving'      => esc_html__( 'Saving...', 'adminvoro' ),
				'saveFailed'  => esc_html__( 'Settings could not be saved. Please refresh and try again.', 'adminvoro' ),
				'ajaxError'   => esc_html__( 'A network error prevented saving. Please try again.', 'adminvoro' ),
			)
		);
	}

	/**
	 * Add settings link on Plugins screen.
	 *
	 * @param array $links Plugin action links.
	 * @return array
	 */
	public function add_settings_link( $links ) {
		$settings_link = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG ) ),
			esc_html__( 'Settings', 'adminvoro' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Sanitize primary options.
	 *
	 * @param array $input Submitted settings.
	 * @return array
	 */
	public function sanitize_options( $input ) {
		$input    = is_array( $input ) ? wp_unslash( $input ) : array();
		$existing = Adminvoro::get_options();
		$output   = $existing;
		$tab      = isset( $input['active_tab'] ) ? sanitize_key( $input['active_tab'] ) : '';

		switch ( $tab ) {
			case 'login-security':
				$output = $this->sanitize_login_security_options( $input, $existing, $output );
				break;
			case 'login-branding':
				$output = $this->sanitize_login_branding_options( $input, $output );
				break;
			case 'security':
				$output['disable_xmlrpc']           = empty( $input['disable_xmlrpc'] ) ? 0 : 1;
				$output['disable_user_enumeration'] = empty( $input['disable_user_enumeration'] ) ? 0 : 1;
				$output['hide_wp_version']          = empty( $input['hide_wp_version'] ) ? 0 : 1;
				break;
			case 'performance':
				$output['disable_emojis'] = empty( $input['disable_emojis'] ) ? 0 : 1;
				$output['disable_embeds'] = empty( $input['disable_embeds'] ) ? 0 : 1;
				break;
			case 'admin-branding':
				$output['enable_admin_footer_text'] = empty( $input['enable_admin_footer_text'] ) ? 0 : 1;
				$output['custom_admin_footer_text'] = isset( $input['custom_admin_footer_text'] ) ? wp_kses_post( $input['custom_admin_footer_text'] ) : '';
				break;
		}

		return wp_parse_args( $output, Adminvoro::get_default_options() );
	}

	/**
	 * Save primary plugin options through AJAX.
	 *
	 * @return void
	 */
	public function ajax_save_options() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'notices' => $this->render_notice_html( esc_html__( 'You do not have permission to manage Adminvoro Toolkit.', 'adminvoro' ), 'error' ),
				),
				403
			);
		}

		check_ajax_referer( 'adminvoro_ajax_save', 'nonce' );

		$input = isset( $_POST[ ADMINVORO_OPTION ] ) && is_array( $_POST[ ADMINVORO_OPTION ] ) ? wp_unslash( $_POST[ ADMINVORO_OPTION ] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$tab   = isset( $input['active_tab'] ) ? sanitize_key( wp_unslash( $input['active_tab'] ) ) : '';

		if ( '' === $tab ) {
			wp_send_json_error(
				array(
					'notices' => $this->render_notice_html( esc_html__( 'Settings could not be saved because the active tab was missing.', 'adminvoro' ), 'error' ),
				),
				400
			);
		}

		$this->clear_settings_errors();
		$options = $this->sanitize_options( $input );
		$this->update_options_without_resanitizing( $options );

		$errors     = get_settings_errors( ADMINVORO_OPTION );
		$has_errors = $this->settings_errors_have_errors( $errors );
		$notices    = $this->render_settings_errors_html( $errors );

		if ( ! $has_errors ) {
			$notices .= $this->render_notice_html( $this->get_success_message_for_tab( $tab, $options ), 'success' );
		}

		wp_send_json_success(
			array(
				'notices'          => $notices,
				'options'          => $options,
				'currentLoginHtml' => $this->get_current_login_notice_html( $options ),
				'logoUrl'          => $this->get_logo_preview_url( $options ),
			)
		);
	}

	/**
	 * Persist already-sanitized AJAX options without running the Settings API sanitizer again.
	 *
	 * The registered sanitize callback expects an active_tab marker. AJAX saves sanitize first,
	 * then store the final option array, so a second sanitize pass would otherwise restore
	 * the previous database value.
	 *
	 * @param array $options Sanitized plugin options.
	 * @return void
	 */
	private function update_options_without_resanitizing( $options ) {
		remove_filter( 'sanitize_option_' . ADMINVORO_OPTION, array( $this, 'sanitize_options' ), 10 );
		update_option( ADMINVORO_OPTION, $options );
		add_filter( 'sanitize_option_' . ADMINVORO_OPTION, array( $this, 'sanitize_options' ), 10, 1 );
	}

	/**
	 * Save redirects from the custom redirects form.
	 *
	 * @return void
	 */
	public function save_redirects() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Adminvoro Toolkit.', 'adminvoro' ) );
		}

		check_admin_referer( 'adminvoro_save_redirects' );

		$result = $this->process_redirect_save();

		$this->set_admin_notice( $result['message'], $result['type'] );

		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'redirects' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Save redirects through AJAX.
	 *
	 * @return void
	 */
	public function ajax_save_redirects() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'notices' => $this->render_notice_html( esc_html__( 'You do not have permission to manage Adminvoro Toolkit.', 'adminvoro' ), 'error' ),
				),
				403
			);
		}

		check_ajax_referer( 'adminvoro_ajax_save', 'nonce' );

		$result = $this->process_redirect_save();

		wp_send_json_success(
			array(
				'notices' => $this->render_notice_html( $result['message'], $result['type'] ),
			)
		);
	}

	/**
	 * Process submitted redirect rows.
	 *
	 * @return array
	 */
	private function process_redirect_save() {
		$rows      = isset( $_POST['adminvoro_redirects'] ) && is_array( $_POST['adminvoro_redirects'] ) ? wp_unslash( $_POST['adminvoro_redirects'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$redirects = Adminvoro_Redirects::sanitize_redirect_rows( $rows );
		$submitted = $this->count_submitted_redirect_rows( $rows );
		$skipped   = max( 0, $submitted - count( $redirects ) );

		update_option( ADMINVORO_REDIRECTS_OPTION, $redirects );

		if ( $skipped > 0 ) {
			return array(
				'message' => sprintf(
					/* translators: 1: Number of redirects saved. 2: Number of redirects skipped. */
					__( '%1$d redirect(s) saved. %2$d invalid row(s) skipped.', 'adminvoro' ),
					count( $redirects ),
					$skipped
				),
				'type'    => 'warning',
			);
		}

		return array(
			'message' => sprintf(
				/* translators: %d: Number of redirects saved. */
				_n( '%d redirect saved.', '%d redirects saved.', count( $redirects ), 'adminvoro' ),
				count( $redirects )
			),
			'type'    => 'success',
		);
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$options    = Adminvoro::get_options();
		$redirects  = Adminvoro_Redirects::get_redirects();
		$active_tab = $this->get_active_tab();

		?>
		<div class="wrap adminvoro-wrap">
			<div class="adminvoro-hero">
				<div>
					<p class="adminvoro-eyebrow"><?php esc_html_e( 'DASHBOARD TOOLKIT', 'adminvoro' ); ?></p>
					<h1><?php esc_html_e( 'Adminvoro Toolkit', 'adminvoro' ); ?></h1>
					<p><?php esc_html_e( 'Manage custom login URLs, login branding, redirects, and essential admin/site controls from one lightweight toolkit.', 'adminvoro' ); ?></p>
				</div>
				<div class="adminvoro-version">
					<?php
					printf(
						/* translators: %s: Plugin version. */
						esc_html__( 'Version %s', 'adminvoro' ),
						esc_html( ADMINVORO_VERSION )
					);
					?>
				</div>
			</div>

			<div class="adminvoro-notices" aria-live="polite">
				<?php settings_errors( ADMINVORO_OPTION ); ?>
				<?php $this->display_admin_notice(); ?>
			</div>

			<nav class="adminvoro-tabs" aria-label="<?php esc_attr_e( 'Adminvoro Toolkit sections', 'adminvoro' ); ?>">
				<?php foreach ( $this->get_tabs() as $tab_id => $label ) : ?>
					<a class="<?php echo esc_attr( $active_tab === $tab_id ? 'is-active' : '' ); ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => $tab_id ), admin_url( 'admin.php' ) ) ); ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<div class="adminvoro-panel">
				<?php
				switch ( $active_tab ) {
					case 'login-branding':
						$this->render_login_branding_tab( $options );
						break;
					case 'redirects':
						$this->render_redirects_tab( $redirects );
						break;
					case 'security':
						$this->render_security_tab( $options );
						break;
					case 'performance':
						$this->render_performance_tab( $options );
						break;
					case 'admin-branding':
						$this->render_admin_branding_tab( $options );
						break;
					case 'login-security':
					default:
						$this->render_login_security_tab( $options );
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Sanitize login security fields.
	 *
	 * @param array $input    Submitted settings.
	 * @param array $existing Existing settings.
	 * @param array $output   Output settings.
	 * @return array
	 */
	private function sanitize_login_security_options( $input, $existing, $output ) {
		$was_enabled = ! empty( $existing['enable_custom_login'] );
		$old_slug    = isset( $existing['custom_login_slug'] ) ? $existing['custom_login_slug'] : '';

		$output['enable_custom_login']     = empty( $input['enable_custom_login'] ) ? 0 : 1;
		$output['login_block_action']      = isset( $input['login_block_action'] ) && in_array( $input['login_block_action'], array( '404', 'home', 'custom_url' ), true ) ? $input['login_block_action'] : '404';
		$output['login_block_custom_url']  = isset( $input['login_block_custom_url'] ) ? $this->sanitize_local_page_url( $input['login_block_custom_url'] ) : '';

		if ( 'custom_url' === $output['login_block_action'] && '' === $output['login_block_custom_url'] ) {
			$output['login_block_action'] = '404';
			add_settings_error( ADMINVORO_OPTION, 'adminvoro-invalid-block-url', esc_html__( 'Enter a valid same-site custom page URL before selecting the custom page redirect option.', 'adminvoro' ), 'error' );
		}

		$raw_slug = isset( $input['custom_login_slug'] ) ? trim( sanitize_text_field( $input['custom_login_slug'] ) ) : '';
		$raw_slug = trim( $raw_slug, '/' );
		$slug     = sanitize_title( $raw_slug );

		if ( '' !== $raw_slug && preg_match( '#[\\/\\\\]#', $raw_slug ) ) {
			$slug = '';
			add_settings_error( ADMINVORO_OPTION, 'adminvoro-invalid-login-slug', esc_html__( 'The custom login slug cannot contain slashes.', 'adminvoro' ), 'error' );
		}

		if ( '' !== $slug && in_array( $slug, Adminvoro::get_reserved_login_slugs(), true ) ) {
			$slug = '';
			add_settings_error( ADMINVORO_OPTION, 'adminvoro-reserved-login-slug', esc_html__( 'That custom login slug is reserved by WordPress. Choose a different slug.', 'adminvoro' ), 'error' );
		}

		if ( ! empty( $output['enable_custom_login'] ) && '' === $slug ) {
			$output['enable_custom_login'] = 0;
			$output['custom_login_slug']  = $old_slug;
			add_settings_error( ADMINVORO_OPTION, 'adminvoro-login-disabled', esc_html__( 'Custom login protection was not enabled because the login slug is invalid or empty.', 'adminvoro' ), 'error' );
		} elseif ( '' !== $slug ) {
			$output['custom_login_slug'] = $slug;
		} else {
			$output['custom_login_slug'] = '';
		}

		if ( $was_enabled !== (bool) $output['enable_custom_login'] || $old_slug !== $output['custom_login_slug'] ) {
			add_action( 'shutdown', array( $this, 'flush_rewrite_rules' ) );
		}

		return $output;
	}

	/**
	 * Sanitize login branding fields.
	 *
	 * @param array $input  Submitted settings.
	 * @param array $output Output settings.
	 * @return array
	 */
	private function sanitize_login_branding_options( $input, $output ) {
		if ( ! empty( $input['reset_login_branding'] ) ) {
			$output['login_logo_id']          = 0;
			$output['login_logo_url']         = '';
			$output['login_logo_text']        = '';
			$output['login_background_color'] = '';
			$output['login_text_color']       = '';
			$output['login_link_color']       = '';
			$output['login_logo_text_size']   = 18;
			return $output;
		}

		$output['login_logo_id']          = isset( $input['login_logo_id'] ) ? absint( $input['login_logo_id'] ) : 0;
		$output['login_logo_url']         = isset( $input['login_logo_url'] ) ? esc_url_raw( $input['login_logo_url'] ) : '';
		$output['login_logo_text']        = isset( $input['login_logo_text'] ) ? wp_kses_post( $input['login_logo_text'] ) : '';
		$output['login_background_color'] = isset( $input['login_background_color'] ) ? $this->sanitize_hex_color_field( $input['login_background_color'] ) : '';
		$output['login_text_color']       = isset( $input['login_text_color'] ) ? $this->sanitize_hex_color_field( $input['login_text_color'] ) : '';
		$output['login_link_color']       = isset( $input['login_link_color'] ) ? $this->sanitize_hex_color_field( $input['login_link_color'] ) : '';
		$output['login_logo_text_size']   = isset( $input['login_logo_text_size'] ) ? $this->sanitize_login_text_size( $input['login_logo_text_size'] ) : 18;

		return $output;
	}

	/**
	 * Flush rewrite rules after custom login changes.
	 *
	 * @return void
	 */
	public function flush_rewrite_rules() {
		flush_rewrite_rules( false );
	}

	/**
	 * Count non-empty submitted redirect rows.
	 *
	 * @param array $rows Submitted redirect rows.
	 * @return int
	 */
	private function count_submitted_redirect_rows( $rows ) {
		$count = 0;

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || ! empty( $row['delete'] ) ) {
				continue;
			}

			$source      = isset( $row['source'] ) ? trim( (string) $row['source'] ) : '';
			$destination = isset( $row['destination'] ) ? trim( (string) $row['destination'] ) : '';

			if ( '' === $source && '' === $destination ) {
				continue;
			}

			$count++;
		}

		return $count;
	}

	/**
	 * Sanitize a same-site page URL for wp-login.php block redirects.
	 *
	 * @param mixed $url Submitted URL.
	 * @return string
	 */
	private function sanitize_local_page_url( $url ) {
		if ( ! is_scalar( $url ) ) {
			return '';
		}

		$url = trim( sanitize_text_field( wp_unslash( $url ) ) );

		if ( '' === $url || 0 === strpos( $url, '//' ) ) {
			return '';
		}

		if ( 0 === strpos( $url, '/' ) ) {
			return esc_url_raw( $url );
		}

		$parts     = wp_parse_url( $url );
		$home_host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );

		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) || empty( $home_host ) ) {
			return '';
		}

		if ( ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) {
			return '';
		}

		if ( strtolower( $parts['host'] ) !== strtolower( $home_host ) ) {
			return '';
		}

		return esc_url_raw( $url );
	}

	/**
	 * Sanitize a hex color field.
	 *
	 * @param mixed $color Submitted color.
	 * @return string
	 */
	private function sanitize_hex_color_field( $color ) {
		if ( ! is_scalar( $color ) ) {
			return '';
		}

		$color = sanitize_hex_color( wp_unslash( $color ) );

		return is_string( $color ) ? $color : '';
	}

	/**
	 * Sanitize custom login text size.
	 *
	 * @param mixed $size Submitted size.
	 * @return int
	 */
	private function sanitize_login_text_size( $size ) {
		$size = absint( $size );

		if ( $size < 12 ) {
			return 12;
		}

		if ( $size > 48 ) {
			return 48;
		}

		return $size;
	}

	/**
	 * Clear collected Settings API errors before an AJAX save.
	 *
	 * @return void
	 */
	private function clear_settings_errors() {
		global $wp_settings_errors;

		$wp_settings_errors = array();
	}

	/**
	 * Determine whether Settings API messages contain errors.
	 *
	 * @param array $errors Settings API messages.
	 * @return bool
	 */
	private function settings_errors_have_errors( $errors ) {
		foreach ( $errors as $error ) {
			if ( isset( $error['type'] ) && 'error' === $error['type'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Render Settings API messages as visible notices.
	 *
	 * @param array $errors Settings API messages.
	 * @return string
	 */
	private function render_settings_errors_html( $errors ) {
		$html = '';

		foreach ( $errors as $error ) {
			$type    = isset( $error['type'] ) ? sanitize_key( $error['type'] ) : 'info';
			$message = isset( $error['message'] ) ? $error['message'] : '';

			if ( '' === $message ) {
				continue;
			}

			$html .= $this->render_notice_html( $message, $type );
		}

		return $html;
	}

	/**
	 * Render a high-contrast admin notice.
	 *
	 * @param string $message Notice message.
	 * @param string $type    Notice type.
	 * @return string
	 */
	private function render_notice_html( $message, $type = 'success' ) {
		if ( 'updated' === $type ) {
			$type = 'success';
		}

		$type = in_array( $type, array( 'success', 'error', 'warning', 'info' ), true ) ? $type : 'info';

		ob_start();
		?>
		<div class="notice notice-<?php echo esc_attr( $type ); ?> adminvoro-notice is-dismissible">
			<p><?php echo wp_kses_post( $message ); ?></p>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get success message for a saved settings tab.
	 *
	 * @param string $tab     Active tab.
	 * @param array  $options Saved options.
	 * @return string
	 */
	private function get_success_message_for_tab( $tab, $options ) {
		if ( 'login-security' === $tab && ! empty( $options['enable_custom_login'] ) && ! empty( $options['custom_login_slug'] ) ) {
			return sprintf(
				/* translators: %s: Current custom login URL. */
				__( 'Login security saved. Current login URL: %s', 'adminvoro' ),
				esc_url( $this->get_current_login_url( $options ) )
			);
		}

		$messages = array(
			'login-security' => __( 'Login security settings saved.', 'adminvoro' ),
			'login-branding' => __( 'Login branding settings saved.', 'adminvoro' ),
			'security'       => __( 'Security settings saved.', 'adminvoro' ),
			'performance'    => __( 'Performance settings saved.', 'adminvoro' ),
			'admin-branding' => __( 'Admin branding settings saved.', 'adminvoro' ),
		);

		return isset( $messages[ $tab ] ) ? $messages[ $tab ] : __( 'Settings saved.', 'adminvoro' );
	}

	/**
	 * Get current login notice HTML.
	 *
	 * @param array $options Plugin options.
	 * @return string
	 */
	private function get_current_login_notice_html( $options ) {
		ob_start();

		if ( Adminvoro::is_custom_login_disabled() ) :
			?>
			<div class="adminvoro-alert adminvoro-alert-warning">
				<?php esc_html_e( 'Custom login protection is disabled because ADMINVORO_DISABLE_CUSTOM_LOGIN is defined as true.', 'adminvoro' ); ?>
			</div>
			<?php
		elseif ( ! empty( $options['enable_custom_login'] ) && '' !== $options['custom_login_slug'] ) :
			$current_login_url = $this->get_current_login_url( $options );
			?>
			<div class="adminvoro-alert adminvoro-alert-success">
				<strong><?php esc_html_e( 'Current login URL:', 'adminvoro' ); ?></strong>
				<a href="<?php echo esc_url( $current_login_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $current_login_url ); ?></a>
				<span><?php esc_html_e( 'Bookmark this URL before logging out.', 'adminvoro' ); ?></span>
			</div>
			<?php
		endif;

		return ob_get_clean();
	}

	/**
	 * Get login logo preview URL for AJAX responses.
	 *
	 * @param array $options Plugin options.
	 * @return string
	 */
	private function get_logo_preview_url( $options ) {
		if ( empty( $options['login_logo_id'] ) ) {
			return '';
		}

		$image = wp_get_attachment_image_src( absint( $options['login_logo_id'] ), 'medium' );

		if ( ! is_array( $image ) || empty( $image[0] ) ) {
			return '';
		}

		return esc_url_raw( $image[0] );
	}

	/**
	 * Render Login Security tab.
	 *
	 * @param array $options Plugin options.
	 * @return void
	 */
	private function render_login_security_tab( $options ) {
		?>
		<form method="post" action="options.php" class="adminvoro-form adminvoro-options-form">
			<?php settings_fields( 'adminvoro_options_group' ); ?>
			<input type="hidden" name="<?php echo esc_attr( ADMINVORO_OPTION ); ?>[active_tab]" value="login-security" />

			<div class="adminvoro-card">
				<div class="adminvoro-card-header">
					<div>
						<h2><?php esc_html_e( 'Custom Login URL', 'adminvoro' ); ?></h2>
						<p><?php esc_html_e( 'Move public login access away from wp-login.php while preserving a safe admin fallback.', 'adminvoro' ); ?></p>
					</div>
				</div>

				<div class="adminvoro-current-login-wrap">
					<?php echo wp_kses_post( $this->get_current_login_notice_html( $options ) ); ?>
				</div>

				<?php
				$this->render_toggle(
					ADMINVORO_OPTION . '[enable_custom_login]',
					'enable_custom_login',
					! empty( $options['enable_custom_login'] ),
					esc_html__( 'Enable custom login URL', 'adminvoro' ),
					esc_html__( 'When enabled, non-logged-in direct visits to wp-login.php are blocked according to the action below.', 'adminvoro' )
				);
				?>

				<label class="adminvoro-field">
					<span><?php esc_html_e( 'Custom login slug', 'adminvoro' ); ?></span>
					<div class="adminvoro-prefix-input">
						<span><?php echo esc_html( trailingslashit( home_url() ) ); ?></span>
						<input type="text" name="<?php echo esc_attr( ADMINVORO_OPTION ); ?>[custom_login_slug]" value="<?php echo esc_attr( $options['custom_login_slug'] ); ?>" placeholder="<?php esc_attr_e( 'my-login', 'adminvoro' ); ?>" />
					</div>
					<small><?php esc_html_e( 'Use letters, numbers, and hyphens only. Reserved slugs like wp-admin, wp-content, login, and admin are blocked.', 'adminvoro' ); ?></small>
				</label>

				<label class="adminvoro-field">
					<span><?php esc_html_e( 'When wp-login.php is visited', 'adminvoro' ); ?></span>
					<select name="<?php echo esc_attr( ADMINVORO_OPTION ); ?>[login_block_action]">
						<option value="404" <?php selected( $options['login_block_action'], '404' ); ?>><?php esc_html_e( 'Show 404', 'adminvoro' ); ?></option>
						<option value="home" <?php selected( $options['login_block_action'], 'home' ); ?>><?php esc_html_e( 'Redirect to homepage', 'adminvoro' ); ?></option>
						<option value="custom_url" <?php selected( $options['login_block_action'], 'custom_url' ); ?>><?php esc_html_e( 'Redirect to custom page URL', 'adminvoro' ); ?></option>
					</select>
					<small><?php esc_html_e( 'Logged-in users are never blocked from wp-login.php.', 'adminvoro' ); ?></small>
				</label>

				<label class="adminvoro-field adminvoro-custom-block-url-field <?php echo esc_attr( 'custom_url' === $options['login_block_action'] ? '' : 'is-hidden' ); ?>">
					<span><?php esc_html_e( 'Custom page URL', 'adminvoro' ); ?></span>
					<input type="text" name="<?php echo esc_attr( ADMINVORO_OPTION ); ?>[login_block_custom_url]" value="<?php echo esc_attr( $options['login_block_custom_url'] ); ?>" placeholder="<?php echo esc_attr( home_url( '/login-help/' ) ); ?>" />
					<small><?php esc_html_e( 'Use a same-site page URL such as /login-help/ or a full URL on this domain.', 'adminvoro' ); ?></small>
				</label>
			</div>

			<?php submit_button( esc_html__( 'Save Login Security', 'adminvoro' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Render Login Branding tab.
	 *
	 * @param array $options Plugin options.
	 * @return void
	 */
	private function render_login_branding_tab( $options ) {
		$logo_url = '';
		if ( ! empty( $options['login_logo_id'] ) ) {
			$image = wp_get_attachment_image_src( absint( $options['login_logo_id'] ), 'medium' );
			if ( is_array( $image ) && ! empty( $image[0] ) ) {
				$logo_url = $image[0];
			}
		}
		?>
		<form method="post" action="options.php" class="adminvoro-form adminvoro-options-form">
			<?php settings_fields( 'adminvoro_options_group' ); ?>
			<input type="hidden" name="<?php echo esc_attr( ADMINVORO_OPTION ); ?>[active_tab]" value="login-branding" />

			<div class="adminvoro-card">
				<div class="adminvoro-card-header">
					<div>
						<h2><?php esc_html_e( 'Login Page Branding', 'adminvoro' ); ?></h2>
						<p><?php esc_html_e( 'Customize the WordPress login screen with your brand assets and helper text.', 'adminvoro' ); ?></p>
					</div>
				</div>

				<div class="adminvoro-logo-control">
					<input type="hidden" class="adminvoro-logo-id" name="<?php echo esc_attr( ADMINVORO_OPTION ); ?>[login_logo_id]" value="<?php echo esc_attr( absint( $options['login_logo_id'] ) ); ?>" />
					<div class="adminvoro-logo-preview <?php echo esc_attr( empty( $logo_url ) ? 'is-empty' : '' ); ?>">
						<?php if ( ! empty( $logo_url ) ) : ?>
							<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php esc_attr_e( 'Selected login logo', 'adminvoro' ); ?>" />
						<?php else : ?>
							<span><?php esc_html_e( 'No logo selected', 'adminvoro' ); ?></span>
						<?php endif; ?>
					</div>
					<div class="adminvoro-logo-actions">
						<button type="button" class="button adminvoro-upload-logo"><?php esc_html_e( 'Choose Logo', 'adminvoro' ); ?></button>
						<button type="button" class="button adminvoro-remove-logo"><?php esc_html_e( 'Remove Logo', 'adminvoro' ); ?></button>
					</div>
				</div>

				<label class="adminvoro-field">
					<span><?php esc_html_e( 'Logo URL', 'adminvoro' ); ?></span>
					<input type="url" name="<?php echo esc_attr( ADMINVORO_OPTION ); ?>[login_logo_url]" value="<?php echo esc_attr( $options['login_logo_url'] ); ?>" placeholder="<?php echo esc_attr( home_url( '/' ) ); ?>" />
					<small><?php esc_html_e( 'Where users go when they click the login logo. Leave blank for WordPress default.', 'adminvoro' ); ?></small>
				</label>

				<label class="adminvoro-field">
					<span><?php esc_html_e( 'Text below logo', 'adminvoro' ); ?></span>
					<textarea name="<?php echo esc_attr( ADMINVORO_OPTION ); ?>[login_logo_text]" rows="4"><?php echo esc_textarea( $options['login_logo_text'] ); ?></textarea>
					<small><?php esc_html_e( 'Basic formatting is allowed. Unsafe HTML is removed when saved.', 'adminvoro' ); ?></small>
				</label>

				<div class="adminvoro-branding-grid">
					<label class="adminvoro-field">
						<span><?php esc_html_e( 'Login background color', 'adminvoro' ); ?></span>
						<input type="color" name="<?php echo esc_attr( ADMINVORO_OPTION ); ?>[login_background_color]" value="<?php echo esc_attr( ! empty( $options['login_background_color'] ) ? $options['login_background_color'] : '#f0f0f1' ); ?>" />
						<small><?php esc_html_e( 'Changes the login page background while keeping the form box white.', 'adminvoro' ); ?></small>
					</label>

					<label class="adminvoro-field">
						<span><?php esc_html_e( 'Outside text color', 'adminvoro' ); ?></span>
						<input type="color" name="<?php echo esc_attr( ADMINVORO_OPTION ); ?>[login_text_color]" value="<?php echo esc_attr( ! empty( $options['login_text_color'] ) ? $options['login_text_color'] : '#3c434a' ); ?>" />
						<small><?php esc_html_e( 'Applies to text outside the login form, including your custom message.', 'adminvoro' ); ?></small>
					</label>

					<label class="adminvoro-field">
						<span><?php esc_html_e( 'Outside link color', 'adminvoro' ); ?></span>
						<input type="color" name="<?php echo esc_attr( ADMINVORO_OPTION ); ?>[login_link_color]" value="<?php echo esc_attr( ! empty( $options['login_link_color'] ) ? $options['login_link_color'] : '#2271b1' ); ?>" />
						<small><?php esc_html_e( 'Applies to links outside the login form.', 'adminvoro' ); ?></small>
					</label>

					<label class="adminvoro-field">
						<span><?php esc_html_e( 'Text below logo size', 'adminvoro' ); ?></span>
						<input type="number" min="12" max="48" step="1" name="<?php echo esc_attr( ADMINVORO_OPTION ); ?>[login_logo_text_size]" value="<?php echo esc_attr( absint( $options['login_logo_text_size'] ) ); ?>" />
						<small><?php esc_html_e( 'Controls the custom message size in pixels. The message is centered automatically.', 'adminvoro' ); ?></small>
					</label>
				</div>
			</div>

			<?php submit_button( esc_html__( 'Save Login Branding', 'adminvoro' ), 'primary', 'submit', false ); ?>
			<button type="submit" class="button button-secondary" name="<?php echo esc_attr( ADMINVORO_OPTION ); ?>[reset_login_branding]" value="1"><?php esc_html_e( 'Reset to Default', 'adminvoro' ); ?></button>
		</form>
		<?php
	}

	/**
	 * Render Redirects tab.
	 *
	 * @param array $redirects Redirect rows.
	 * @return void
	 */
	private function render_redirects_tab( $redirects ) {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="adminvoro-form adminvoro-redirects-form">
			<input type="hidden" name="action" value="adminvoro_save_redirects" />
			<?php wp_nonce_field( 'adminvoro_save_redirects' ); ?>

			<div class="adminvoro-card">
				<div class="adminvoro-card-header">
					<div>
						<h2><?php esc_html_e( 'Redirect Manager', 'adminvoro' ); ?></h2>
						<p><?php esc_html_e( 'Create simple frontend 301 and 302 redirects without touching wp-admin, AJAX, cron, or REST requests.', 'adminvoro' ); ?></p>
					</div>
					<button type="button" class="button button-secondary adminvoro-add-redirect"><?php esc_html_e( 'Add Redirect', 'adminvoro' ); ?></button>
				</div>

				<div class="adminvoro-table-wrap">
					<table class="widefat fixed striped adminvoro-redirects-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Source URL', 'adminvoro' ); ?></th>
								<th><?php esc_html_e( 'Destination URL', 'adminvoro' ); ?></th>
								<th><?php esc_html_e( 'Type', 'adminvoro' ); ?></th>
								<th><?php esc_html_e( 'Enabled', 'adminvoro' ); ?></th>
								<th><?php esc_html_e( 'Delete', 'adminvoro' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							if ( empty( $redirects ) ) {
								$redirects = array(
									array(
										'id'          => '',
										'source'      => '',
										'destination' => '',
										'type'        => 301,
										'enabled'     => 1,
									),
								);
							}

							foreach ( array_values( $redirects ) as $index => $redirect ) :
								$this->render_redirect_row( $index, $redirect );
							endforeach;
							?>
						</tbody>
					</table>
				</div>
				<p class="description"><?php esc_html_e( 'Use root-relative URLs like /old-page or absolute http(s) URLs. Protocol-relative URLs are rejected.', 'adminvoro' ); ?></p>
			</div>

			<?php submit_button( esc_html__( 'Save Redirects', 'adminvoro' ) ); ?>
		</form>

		<template id="adminvoro-redirect-row-template">
			<?php
			$this->render_redirect_row(
				'__index__',
				array(
					'id'          => '',
					'source'      => '',
					'destination' => '',
					'type'        => 301,
					'enabled'     => 1,
				)
			);
			?>
		</template>
		<?php
	}

	/**
	 * Render Security tab.
	 *
	 * @param array $options Plugin options.
	 * @return void
	 */
	private function render_security_tab( $options ) {
		?>
		<form method="post" action="options.php" class="adminvoro-form adminvoro-options-form">
			<?php settings_fields( 'adminvoro_options_group' ); ?>
			<input type="hidden" name="<?php echo esc_attr( ADMINVORO_OPTION ); ?>[active_tab]" value="security" />
			<div class="adminvoro-card">
				<div class="adminvoro-card-header">
					<div>
						<h2><?php esc_html_e( 'Basic Security', 'adminvoro' ); ?></h2>
						<p><?php esc_html_e( 'Enable small hardening improvements that reduce common public signals and attack surfaces.', 'adminvoro' ); ?></p>
					</div>
				</div>
				<?php
				$this->render_toggle( ADMINVORO_OPTION . '[disable_xmlrpc]', 'disable_xmlrpc', ! empty( $options['disable_xmlrpc'] ), esc_html__( 'Disable XML-RPC', 'adminvoro' ), esc_html__( 'Turns off XML-RPC authentication and remote publishing endpoints through WordPress filters.', 'adminvoro' ) );
				$this->render_toggle( ADMINVORO_OPTION . '[disable_user_enumeration]', 'disable_user_enumeration', ! empty( $options['disable_user_enumeration'] ), esc_html__( 'Disable user enumeration', 'adminvoro' ), esc_html__( 'Blocks common ?author=1 discovery requests for logged-out visitors.', 'adminvoro' ) );
				$this->render_toggle( ADMINVORO_OPTION . '[hide_wp_version]', 'hide_wp_version', ! empty( $options['hide_wp_version'] ), esc_html__( 'Hide WordPress version', 'adminvoro' ), esc_html__( 'Removes the generator meta tag and generator output.', 'adminvoro' ) );
				?>
			</div>
			<?php submit_button( esc_html__( 'Save Security Settings', 'adminvoro' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Render Performance tab.
	 *
	 * @param array $options Plugin options.
	 * @return void
	 */
	private function render_performance_tab( $options ) {
		?>
		<form method="post" action="options.php" class="adminvoro-form adminvoro-options-form">
			<?php settings_fields( 'adminvoro_options_group' ); ?>
			<input type="hidden" name="<?php echo esc_attr( ADMINVORO_OPTION ); ?>[active_tab]" value="performance" />
			<div class="adminvoro-card">
				<div class="adminvoro-card-header">
					<div>
						<h2><?php esc_html_e( 'Performance Tweaks', 'adminvoro' ); ?></h2>
						<p><?php esc_html_e( 'Remove optional WordPress frontend assets that many sites do not need.', 'adminvoro' ); ?></p>
					</div>
				</div>
				<?php
				$this->render_toggle( ADMINVORO_OPTION . '[disable_emojis]', 'disable_emojis', ! empty( $options['disable_emojis'] ), esc_html__( 'Disable emojis', 'adminvoro' ), esc_html__( 'Removes WordPress emoji detection scripts, styles, filters, and DNS prefetch hints.', 'adminvoro' ) );
				$this->render_toggle( ADMINVORO_OPTION . '[disable_embeds]', 'disable_embeds', ! empty( $options['disable_embeds'] ), esc_html__( 'Disable embeds', 'adminvoro' ), esc_html__( 'Disables oEmbed discovery links and dequeues the wp-embed script on the frontend.', 'adminvoro' ) );
				?>
			</div>
			<?php submit_button( esc_html__( 'Save Performance Settings', 'adminvoro' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Render Admin Branding tab.
	 *
	 * @param array $options Plugin options.
	 * @return void
	 */
	private function render_admin_branding_tab( $options ) {
		?>
		<form method="post" action="options.php" class="adminvoro-form adminvoro-options-form">
			<?php settings_fields( 'adminvoro_options_group' ); ?>
			<input type="hidden" name="<?php echo esc_attr( ADMINVORO_OPTION ); ?>[active_tab]" value="admin-branding" />
			<div class="adminvoro-card">
				<div class="adminvoro-card-header">
					<div>
						<h2><?php esc_html_e( 'Admin Branding', 'adminvoro' ); ?></h2>
						<p><?php esc_html_e( 'Replace the default WordPress admin footer with your own message.', 'adminvoro' ); ?></p>
					</div>
				</div>
				<?php
				$this->render_toggle( ADMINVORO_OPTION . '[enable_admin_footer_text]', 'enable_admin_footer_text', ! empty( $options['enable_admin_footer_text'] ), esc_html__( 'Enable custom admin footer', 'adminvoro' ), esc_html__( 'When enabled, the footer text below replaces the default WordPress footer text.', 'adminvoro' ) );
				?>
				<label class="adminvoro-field">
					<span><?php esc_html_e( 'Custom admin footer text', 'adminvoro' ); ?></span>
					<textarea name="<?php echo esc_attr( ADMINVORO_OPTION ); ?>[custom_admin_footer_text]" rows="4"><?php echo esc_textarea( $options['custom_admin_footer_text'] ); ?></textarea>
					<small><?php esc_html_e( 'Basic formatting is allowed. Unsafe HTML is removed when saved.', 'adminvoro' ); ?></small>
				</label>
			</div>
			<?php submit_button( esc_html__( 'Save Admin Branding', 'adminvoro' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Render a toggle control.
	 *
	 * @param string $name        Field name.
	 * @param string $id          Field ID suffix.
	 * @param bool   $checked     Checked state.
	 * @param string $label       Field label.
	 * @param string $description Field description.
	 * @return void
	 */
	private function render_toggle( $name, $id, $checked, $label, $description ) {
		$field_id = 'adminvoro-' . sanitize_key( $id );
		?>
		<div class="adminvoro-toggle-row">
			<div>
				<label for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $label ); ?></label>
				<small><?php echo esc_html( $description ); ?></small>
			</div>
			<label class="adminvoro-switch" aria-label="<?php echo esc_attr( $label ); ?>">
				<input id="<?php echo esc_attr( $field_id ); ?>" type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( $checked ); ?> />
				<span></span>
			</label>
		</div>
		<?php
	}

	/**
	 * Render a redirect row.
	 *
	 * @param int|string $index    Row index.
	 * @param array      $redirect Redirect row.
	 * @return void
	 */
	private function render_redirect_row( $index, $redirect ) {
		$name = 'adminvoro_redirects[' . $index . ']';
		?>
		<tr>
			<td>
				<input type="hidden" name="<?php echo esc_attr( $name ); ?>[id]" value="<?php echo esc_attr( isset( $redirect['id'] ) ? $redirect['id'] : '' ); ?>" />
				<input type="text" name="<?php echo esc_attr( $name ); ?>[source]" value="<?php echo esc_attr( isset( $redirect['source'] ) ? $redirect['source'] : '' ); ?>" placeholder="/old-page" />
			</td>
			<td>
				<input type="text" name="<?php echo esc_attr( $name ); ?>[destination]" value="<?php echo esc_attr( isset( $redirect['destination'] ) ? $redirect['destination'] : '' ); ?>" placeholder="/new-page" />
			</td>
			<td>
				<select name="<?php echo esc_attr( $name ); ?>[type]">
					<option value="301" <?php selected( isset( $redirect['type'] ) ? absint( $redirect['type'] ) : 301, 301 ); ?>>301</option>
					<option value="302" <?php selected( isset( $redirect['type'] ) ? absint( $redirect['type'] ) : 301, 302 ); ?>>302</option>
				</select>
			</td>
			<td>
				<label class="adminvoro-switch adminvoro-switch-small">
					<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[enabled]" value="1" <?php checked( ! isset( $redirect['enabled'] ) || ! empty( $redirect['enabled'] ) ); ?> />
					<span></span>
				</label>
			</td>
			<td>
				<input type="hidden" class="adminvoro-delete-value" name="<?php echo esc_attr( $name ); ?>[delete]" value="0" />
				<button type="button" class="button-link-delete adminvoro-delete-row"><?php esc_html_e( 'Delete', 'adminvoro' ); ?></button>
			</td>
		</tr>
		<?php
	}

	/**
	 * Get available tabs.
	 *
	 * @return array
	 */
	private function get_tabs() {
		return array(
			'login-security' => __( 'Login Security', 'adminvoro' ),
			'login-branding' => __( 'Login Branding', 'adminvoro' ),
			'redirects'      => __( 'Redirects', 'adminvoro' ),
			'security'       => __( 'Security', 'adminvoro' ),
			'performance'    => __( 'Performance', 'adminvoro' ),
			'admin-branding' => __( 'Admin Branding', 'adminvoro' ),
		);
	}

	/**
	 * Get active tab.
	 *
	 * @return string
	 */
	private function get_active_tab() {
		$tabs = $this->get_tabs();
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'login-security'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return array_key_exists( $tab, $tabs ) ? $tab : 'login-security';
	}

	/**
	 * Get current login URL for display.
	 *
	 * @param array $options Plugin options.
	 * @return string
	 */
	private function get_current_login_url( $options ) {
		if ( empty( $options['custom_login_slug'] ) ) {
			return wp_login_url();
		}

		return home_url( '/' . trim( sanitize_title( $options['custom_login_slug'] ), '/' ) . '/' );
	}

	/**
	 * Set a short-lived admin notice.
	 *
	 * @param string $message Notice text.
	 * @param string $type    Notice type.
	 * @return void
	 */
	private function set_admin_notice( $message, $type = 'success' ) {
		set_transient(
			'adminvoro_admin_notice_' . get_current_user_id(),
			array(
				'message' => sanitize_text_field( $message ),
				'type'    => sanitize_key( $type ),
			),
			30
		);
	}

	/**
	 * Display and clear a stored admin notice.
	 *
	 * @return void
	 */
	private function display_admin_notice() {
		$key    = 'adminvoro_admin_notice_' . get_current_user_id();
		$notice = get_transient( $key );

		if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
			return;
		}

		delete_transient( $key );
		$type = isset( $notice['type'] ) && in_array( $notice['type'], array( 'success', 'error', 'warning', 'info' ), true ) ? $notice['type'] : 'success';
		?>
		<div class="notice notice-<?php echo esc_attr( $type ); ?> is-dismissible">
			<p><?php echo esc_html( $notice['message'] ); ?></p>
		</div>
		<?php
	}
}
