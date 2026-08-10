<?php
/**
 * Admin dashboard: grouped sidebar navigation, routing, and save handlers.
 * Everything the client needs to manage is here — no code editing required.
 *
 * @package Wise_Mirror_Booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wise_Mirror_Admin {

	const CAP = 'manage_options';

	/**
	 * Grouped nav: top-level items are either a single page (no 'children')
	 * or a group with sub-pages. This is what renders as the sidebar, and
	 * what the tab-count reduction from the old flat list is built on.
	 */
	public static function nav_tree() {
		return array(
			'dashboard' => array(
				'label' => 'Dashboard',
				'icon'  => 'dashicons-chart-area',
				'children' => array(
					'dashboard-overview'  => 'Overview',
					'dashboard-analytics' => 'Analytics',
				),
			),
			'form-editor' => array( 'label' => 'Form Editor', 'icon' => 'dashicons-edit-page' ),
			'bookings' => array(
				'label' => 'Bookings',
				'icon'  => 'dashicons-calendar-alt',
				'children' => array(
					'bookings-mapping'      => 'Booking Mapping',
					'bookings-sessions'     => 'Session Management',
					'bookings-pricing'      => 'Pricing',
					'bookings-availability' => 'Availability',
					'bookings-submissions'  => 'Submissions',
					'bookings-payments'     => 'Payments',
				),
			),
			'ai-configuration' => array( 'label' => 'AI Configuration', 'icon' => 'dashicons-admin-generic' ),
			'api-manager'      => array( 'label' => 'API Manager', 'icon' => 'dashicons-rest-api' ),
			'system-settings'  => array( 'label' => 'System Settings', 'icon' => 'dashicons-admin-settings' ),
			'tools'            => array( 'label' => 'Tools', 'icon' => 'dashicons-admin-tools' ),
			'help'             => array( 'label' => 'Help & Documentation', 'icon' => 'dashicons-editor-help' ),
		);
	}

	/**
	 * Old flat tab slugs → new nested ones, so nothing that was bookmarked
	 * or linked from elsewhere just breaks.
	 */
	private static function legacy_redirects() {
		return array(
			'dashboard'        => 'dashboard-overview',
			'booking-settings' => 'bookings-mapping',
			'schedule'         => 'bookings-availability',
			'pricing-mapping'  => 'bookings-pricing',
			'stripe-settings'  => 'system-settings',
			'email-settings'   => 'system-settings',
			'email-template'   => 'system-settings',
			'form-builder'     => 'form-editor',
			'html-editor'      => 'form-editor',
			'css-editor'       => 'form-editor',
			'js-editor'        => 'form-editor',
			'submissions'      => 'bookings-submissions',
			'payments'         => 'bookings-payments',
			'logs'             => 'system-settings',
		);
	}

	/**
	 * Flat list of every valid page slug (leaf pages only).
	 */
	private static function all_pages() {
		$pages = array();
		foreach ( self::nav_tree() as $key => $item ) {
			if ( ! empty( $item['children'] ) ) {
				$pages = array_merge( $pages, array_keys( $item['children'] ) );
			} else {
				$pages[] = $key;
			}
		}
		return $pages;
	}

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_saves' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'wp_ajax_wise_admin_ai_test', array( __CLASS__, 'handle_ai_test' ) );
		add_action( 'wp_ajax_wise_admin_webhook_test', array( __CLASS__, 'handle_webhook_test' ) );
		add_action( 'wp_ajax_wise_admin_clear_cache', array( __CLASS__, 'handle_clear_cache' ) );
		add_action( 'wp_ajax_wise_admin_test_notification', array( __CLASS__, 'handle_test_notification' ) );
	}

	public static function handle_test_notification() {
		check_ajax_referer( 'wise_mirror_admin', 'nonce' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}
		$system = Wise_Mirror_Settings::system_settings();
		if ( empty( $system['notify_admin_email'] ) ) {
			wp_send_json_error( array( 'message' => 'No Notify Email address is set.' ) );
		}

		$email_settings = Wise_Mirror_Settings::email_settings();
		$headers = array();
		if ( ! empty( $email_settings['from_name'] ) && ! empty( $email_settings['from_email'] ) ) {
			$headers[] = 'From: ' . $email_settings['from_name'] . ' <' . $email_settings['from_email'] . '>';
		}

		$sent = wp_mail(
			$system['notify_admin_email'],
			'Wise Mirror Booking — Test Notification',
			"This is a test of the admin notification email.\n\nIf you received this, admin notifications are working correctly.\n\nDelivery method: " . ( 'smtp' === $email_settings['method'] ? 'SMTP' : 'WordPress default (wp_mail)' ),
			$headers
		);

		if ( $sent ) {
			wp_send_json_success( array( 'message' => 'Test email sent to ' . $system['notify_admin_email'] . '. Check your inbox (and spam folder).' ) );
		}
		wp_send_json_error( array( 'message' => 'wp_mail() reported failure — check the Logs tab for the specific error, and your Email delivery settings.' ) );
	}

	public static function handle_clear_cache() {
		check_ajax_referer( 'wise_mirror_admin', 'nonce' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}
		Wise_Mirror_Cache::flush_all();
		wp_send_json_success();
	}

	public static function handle_ai_test() {
		check_ajax_referer( 'wise_mirror_admin', 'nonce' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}
		$prompt = isset( $_POST['prompt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['prompt'] ) ) : 'Say hello.';
		$result = Wise_Mirror_Ai_Client::generate( $prompt );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( array( 'response' => $result ) );
	}

	public static function handle_webhook_test() {
		check_ajax_referer( 'wise_mirror_admin', 'nonce' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}
		$url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
		if ( ! $url ) {
			wp_send_json_error( array( 'message' => 'No URL provided.' ) );
		}
		$result = Wise_Mirror_Webhooks::send( $url, 'webhook.test', array( 'message' => 'Test payload from the Wise Mirror dashboard.' ) );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( array( 'sent' => true ) );
	}

	public static function register_menu() {
		add_menu_page(
			'Wise Mirror Booking',
			'Wise Mirror',
			self::CAP,
			'wise-mirror-booking',
			array( __CLASS__, 'render_page' ),
			'dashicons-calendar-alt',
			56
		);
	}

	public static function enqueue_assets( $hook ) {
		if ( 'toplevel_page_wise-mirror-booking' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'wise-mirror-admin', WISE_MIRROR_URL . 'assets/admin.css', array(), WISE_MIRROR_VERSION );
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_script( 'wise-mirror-admin', WISE_MIRROR_URL . 'assets/admin.js', array( 'jquery', 'jquery-ui-sortable' ), WISE_MIRROR_VERSION, true );

		wp_localize_script( 'wise-mirror-admin', 'WiseMirrorAdmin', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'wise_mirror_admin' ),
		) );

		$tab = self::current_tab();
		if ( 'form-editor' === $tab ) {
			wp_enqueue_code_editor( array( 'type' => 'text/html' ) );
			wp_enqueue_script( 'wp-theme-plugin-editor' );
		}
		if ( 'system-settings' === $tab ) {
			wp_enqueue_media();
		}
	}

	public static function current_tab() {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'dashboard-overview'; // phpcs:ignore
		$legacy = self::legacy_redirects();
		if ( isset( $legacy[ $tab ] ) ) {
			$tab = $legacy[ $tab ];
		}
		return in_array( $tab, self::all_pages(), true ) ? $tab : 'dashboard-overview';
	}

	private static function group_for_tab( $tab ) {
		foreach ( self::nav_tree() as $key => $item ) {
			if ( $key === $tab ) {
				return $key;
			}
			if ( ! empty( $item['children'] ) && isset( $item['children'][ $tab ] ) ) {
				return $key;
			}
		}
		return '';
	}

	public static function render_page() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wise-mirror-booking' ) );
		}

		$tab = self::current_tab();
		$active_group = self::group_for_tab( $tab );
		?>
		<div class="wrap wise-mirror-admin-wrap" id="wise-mirror-admin-root">
			<div class="wise-mirror-topbar">
				<h1>Wise Mirror Booking</h1>
				<div class="wise-mirror-topbar-right">
					<span class="wise-mirror-version">v<?php echo esc_html( WISE_MIRROR_VERSION ); ?></span>
					<button type="button" class="wise-mirror-theme-toggle" id="wise-mirror-theme-toggle" aria-label="Toggle dark mode">
						<span class="dashicons dashicons-lightbulb"></span>
					</button>
				</div>
			</div>

			<?php if ( isset( $_GET['wise_saved'] ) ) : // phpcs:ignore ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Saved.', 'wise-mirror-booking' ); ?></p></div>
			<?php endif; ?>

			<div class="wise-mirror-shell">
				<nav class="wise-mirror-sidebar" id="wise-mirror-sidebar">
					<?php foreach ( self::nav_tree() as $key => $item ) : ?>
						<?php if ( ! empty( $item['children'] ) ) : ?>
							<div class="wise-mirror-nav-group <?php echo $active_group === $key ? 'wise-open' : ''; ?>">
								<button type="button" class="wise-mirror-nav-group-toggle">
									<span class="dashicons <?php echo esc_attr( $item['icon'] ); ?>"></span>
									<?php echo esc_html( $item['label'] ); ?>
									<span class="wise-mirror-nav-caret dashicons dashicons-arrow-down-alt2"></span>
								</button>
								<div class="wise-mirror-nav-children">
									<?php foreach ( $item['children'] as $slug => $label ) : ?>
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=wise-mirror-booking&tab=' . $slug ) ); ?>"
										   class="<?php echo $tab === $slug ? 'wise-active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
									<?php endforeach; ?>
								</div>
							</div>
						<?php else : ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wise-mirror-booking&tab=' . $key ) ); ?>"
							   class="wise-mirror-nav-link <?php echo $tab === $key ? 'wise-active' : ''; ?>">
								<span class="dashicons <?php echo esc_attr( $item['icon'] ); ?>"></span>
								<?php echo esc_html( $item['label'] ); ?>
							</a>
						<?php endif; ?>
					<?php endforeach; ?>
				</nav>

				<main class="wise-mirror-main">
					<?php self::render_tab( $tab ); ?>
				</main>
			</div>
		</div>
		<?php
	}

	private static function render_tab( $tab ) {
		$view = WISE_MIRROR_DIR . 'admin/views/' . $tab . '.php';
		if ( file_exists( $view ) ) {
			include $view;
		} else {
			echo '<div class="wise-mirror-panel"><p>Coming soon.</p></div>';
		}
	}

	/**
	 * Handle all tab form submissions in one place.
	 */
	public static function handle_saves() {
		if ( empty( $_POST['wise_mirror_action'] ) || ! current_user_can( self::CAP ) ) {
			return;
		}

		$action = sanitize_key( wp_unslash( $_POST['wise_mirror_action'] ) );

		if ( ! isset( $_POST['wise_mirror_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['wise_mirror_nonce'] ), 'wise_mirror_save_' . $action ) ) {
			wp_die( esc_html__( 'Security check failed. Please go back and try again.', 'wise-mirror-booking' ) );
		}

		switch ( $action ) {
			case 'booking_mapping':
				update_option( 'wise_mirror_general_settings', Wise_Mirror_Settings::sanitize_general( wp_unslash( $_POST['general'] ?? array() ) ) );
				self::redirect_back( 'bookings-mapping' );
				break;

			case 'sessions':
				Wise_Mirror_Sessions::save_all( wp_unslash( $_POST['sessions'] ?? array() ) );
				self::redirect_back( 'bookings-sessions' );
				break;

			case 'pricing_settings':
				update_option( 'wise_mirror_general_settings', Wise_Mirror_Settings::sanitize_general( wp_unslash( $_POST['general'] ?? array() ) ) );
				self::redirect_back( 'bookings-pricing' );
				break;

			case 'schedule_settings':
				update_option( 'wise_mirror_schedule_settings', Wise_Mirror_Settings::sanitize_schedule( wp_unslash( $_POST['schedule'] ?? array() ) ) );
				self::redirect_back( 'bookings-availability' );
				break;

			case 'stripe_settings':
				update_option( 'wise_mirror_stripe_settings', Wise_Mirror_Settings::sanitize_stripe( wp_unslash( $_POST['stripe'] ?? array() ) ) );
				self::redirect_back( 'system-settings' );
				break;

			case 'email_settings':
				update_option( 'wise_mirror_email_settings', Wise_Mirror_Settings::sanitize_email_settings( wp_unslash( $_POST['email'] ?? array() ) ) );
				self::redirect_back( 'system-settings' );
				break;

			case 'email_template':
				update_option( 'wise_mirror_email_template', Wise_Mirror_Settings::sanitize_email_template( wp_unslash( $_POST['template'] ?? array() ) ) );
				self::redirect_back( 'system-settings' );
				break;

			case 'system_general':
				update_option( 'wise_mirror_system_settings', Wise_Mirror_Settings::sanitize_system( wp_unslash( $_POST['system'] ?? array() ) ) );
				self::redirect_back( 'system-settings' );
				break;

			case 'upload_settings':
				update_option( 'wise_mirror_upload_settings', Wise_Mirror_Settings::sanitize_upload_settings( wp_unslash( $_POST['uploads'] ?? array() ) ) );
				self::redirect_back( 'system-settings' );
				break;

			case 'html_editor':
				update_option( 'wise_mirror_form_html', Wise_Mirror_Settings::sanitize_code_field( $_POST['html'] ?? '' ) );
				update_option( 'wise_mirror_form_css', Wise_Mirror_Settings::sanitize_code_field( $_POST['css'] ?? '' ) );
				update_option( 'wise_mirror_form_js', Wise_Mirror_Settings::sanitize_code_field( $_POST['js'] ?? '' ) );
				Wise_Mirror_Cache::flush( 'active_sessions' );
				self::redirect_back( 'form-editor' );
				break;

			case 'reset_form':
				update_option( 'wise_mirror_form_html', Wise_Mirror_Settings::default_form_html() );
				update_option( 'wise_mirror_form_css', Wise_Mirror_Settings::default_form_css() );
				update_option( 'wise_mirror_form_js', Wise_Mirror_Settings::default_form_js() );
				self::redirect_back( 'form-editor' );
				break;

			case 'clear_logs':
				Wise_Mirror_Logger::clear();
				self::redirect_back( 'system-settings' );
				break;

			case 'ai_settings':
				update_option( 'wise_mirror_ai_settings', Wise_Mirror_Settings::sanitize_ai( wp_unslash( $_POST['ai'] ?? array() ) ) );
				self::redirect_back( 'ai-configuration' );
				break;

			case 'api_regenerate':
				Wise_Mirror_Api_Manager::generate( true );
				self::redirect_back( 'api-manager' );
				break;

			case 'api_toggle':
				Wise_Mirror_Api_Manager::set_enabled( ! empty( $_POST['enabled'] ) );
				self::redirect_back( 'api-manager' );
				break;

			case 'webhooks':
				update_option( 'wise_mirror_webhooks', Wise_Mirror_Webhooks::sanitize( wp_unslash( $_POST['webhooks'] ?? array() ) ) );
				self::redirect_back( 'api-manager' );
				break;

			case 'reset_plugin':
				if ( isset( $_POST['confirm'] ) && 'RESET' === $_POST['confirm'] ) {
					self::reset_plugin_settings();
				}
				self::redirect_back( 'tools' );
				break;
		}
	}

	private static function reset_plugin_settings() {
		$options = array(
			'wise_mirror_general_settings', 'wise_mirror_stripe_settings', 'wise_mirror_email_settings',
			'wise_mirror_email_template', 'wise_mirror_form_html', 'wise_mirror_form_css', 'wise_mirror_form_js',
			'wise_mirror_upload_settings', 'wise_mirror_schedule_settings', 'wise_mirror_ai_settings',
			'wise_mirror_system_settings', 'wise_mirror_webhooks', 'wise_mirror_sessions', 'wise_mirror_logs',
		);
		foreach ( $options as $option ) {
			delete_option( $option );
		}
		Wise_Mirror_Cache::flush_all();
		require_once WISE_MIRROR_DIR . 'includes/class-wise-mirror-activator.php';
		Wise_Mirror_Activator::activate();
	}

	private static function redirect_back( $tab ) {
		wp_safe_redirect( admin_url( 'admin.php?page=wise-mirror-booking&tab=' . $tab . '&wise_saved=1' ) );
		exit;
	}
}
