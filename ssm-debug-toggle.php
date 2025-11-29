<?php
/**
 * Plugin Name: SSM Debug Toggle
 * Plugin URI:  https://example.com/
 * Description: ایک سادہ (WordPress) (admin) ٹول جو (wp-config.php) کے ذریعے (Debug Mode) کو (ON/OFF) کرتا ہے۔
 * Version:     1.0.0
 * Author:      SSM
 * Text Domain: ssm-debug-toggle
 */

/**
 * File: ssm-debug-toggle.php
 * کہاں پیسٹ کریں: فائل کے آخر میں
 * Part 1 — Core plugin bootstrap and debug toggling
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'ssm_debug_toggle_activate' ) ) :

	/**
	 * پلگ اِن (activation) پر بیسک سیٹ اپ
	 */
	function ssm_debug_toggle_activate() {
		// ورژن فلیگ سیٹ کریں تاکہ مستقبل میں مائیگریشن وغیرہ ممکن ہو۔
		update_option( 'ssm_debug_toggle_version', '1.0.0' );
	}

	register_activation_hook( __FILE__, 'ssm_debug_toggle_activate' );

endif;

if ( ! function_exists( 'ssm_debug_toggle_get_config_path' ) ) :

	/**
	 * (wp-config.php) کا درست (path) تلاش کریں۔
	 *
	 * @return string|null
	 */
	function ssm_debug_toggle_get_config_path() {
		$paths = array(
			ABSPATH . 'wp-config.php',
			dirname( ABSPATH ) . '/wp-config.php',
		);

		foreach ( $paths as $path ) {
			if ( @file_exists( $path ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				return $path;
			}
		}

		return null;
	}

endif;

if ( ! function_exists( 'ssm_debug_toggle_get_runtime_state' ) ) :

	/**
	 * موجودہ (runtime) (Debug) اسٹیٹ نکالیں۔
	 *
	 * @return array
	 */
	function ssm_debug_toggle_get_runtime_state() {
		$wp_debug         = defined( 'WP_DEBUG' ) ? (bool) WP_DEBUG : false;
		$wp_debug_log     = defined( 'WP_DEBUG_LOG' ) ? (bool) WP_DEBUG_LOG : false;
		$wp_debug_display = defined( 'WP_DEBUG_DISPLAY' ) ? (bool) WP_DEBUG_DISPLAY : false;

		$display_errors_ini = ini_get( 'display_errors' );
		$display_errors     = ( '1' === $display_errors_ini || 1 === $display_errors_ini || 'On' === $display_errors_ini );

		$mode = 'custom';

		if ( $wp_debug && $wp_debug_log && $wp_debug_display && $display_errors ) {
			$mode = 'on';
		} elseif ( ! $wp_debug && ! $wp_debug_log && ! $wp_debug_display && ! $display_errors ) {
			$mode = 'off';
		}

		return array(
			'mode'            => $mode,
			'wp_debug'        => $wp_debug,
			'wp_debug_log'    => $wp_debug_log,
			'wp_debug_display'=> $wp_debug_display,
			'display_errors'  => $display_errors,
		);
	}

endif;

if ( ! function_exists( 'ssm_debug_toggle_generate_block' ) ) :

	/**
	 * (wp-config.php) میں شامل ہونے والا (Debug) بلاک بنائیں۔
	 *
	 * @param string $mode on|off
	 *
	 * @return string
	 */
	function ssm_debug_toggle_generate_block( $mode ) {
		$on = ( 'on' === $mode );

		$lines   = array();
		$lines[] = '';
		$lines[] = '// ssm-debug-toggle start';
		$lines[] = "define( 'WP_DEBUG', " . ( $on ? 'true' : 'false' ) . " );";
		$lines[] = "define( 'WP_DEBUG_LOG', " . ( $on ? 'true' : 'false' ) . " );";
		$lines[] = "define( 'WP_DEBUG_DISPLAY', " . ( $on ? 'true' : 'false' ) . " );";
		$lines[] = "@ini_set( 'display_errors', " . ( $on ? "'1'" : "'0'" ) . " );";
		$lines[] = '// ssm-debug-toggle end';
		$lines[] = '';

		return implode( "\n", $lines ) . "\n";
	}

endif;

if ( ! function_exists( 'ssm_debug_toggle_update_config' ) ) :

	/**
	 * (wp-config.php) فائل کو اپ ڈیٹ کریں، (Debug) کو (on/off) پر سیٹ کریں۔
	 *
	 * @param string $mode on|off
	 *
	 * @return array [success => bool, message => string]
	 */
	function ssm_debug_toggle_update_config( $mode ) {
		$config_path = ssm_debug_toggle_get_config_path();

		if ( ! $config_path ) {
			return array(
				'success' => false,
				'message' => __( 'wp-config.php فائل نہیں ملی، براہِ کرم سرور سیٹنگ چیک کریں۔', 'ssm-debug-toggle' ),
			);
		}

		if ( ! is_readable( $config_path ) ) {
			return array(
				'success' => false,
				'message' => __( 'wp-config.php فائل پڑھنے کی اجازت نہیں، ہوسٹنگ پرمیشن چیک کریں۔', 'ssm-debug-toggle' ),
			);
		}

		if ( ! is_writable( $config_path ) ) {
			return array(
				'success' => false,
				'message' => __( 'wp-config.php فائل لکھنے کے قابل نہیں، براہِ کرم فائل پرمیشن درست کریں۔', 'ssm-debug-toggle' ),
			);
		}

		$contents = @file_get_contents( $config_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if ( false === $contents ) {
			return array(
				'success' => false,
				'message' => __( 'wp-config.php فائل پڑھنے میں مسئلہ آیا۔', 'ssm-debug-toggle' ),
			);
		}

		// پہلے پرانے (WP_DEBUG) وغیرہ والے (define) ہٹا دیں تاکہ ڈپلیکیشن نہ ہو۔
		$patterns = array(
			"/define\\(\\s*'WP_DEBUG'\\s*,\\s*(true|false)\\s*\\);\\s*/i",
			"/define\\(\\s*'WP_DEBUG_LOG'\\s*,\\s*(true|false)\\s*\\);\\s*/i",
			"/define\\(\\s*'WP_DEBUG_DISPLAY'\\s*,\\s*(true|false)\\s*\\);\\s*/i",
			"/@ini_set\\(\\s*'display_errors'\\s*,\\s*'?(0|1|On|Off)'?\\s*\\);\\s*/i",
			"/\\/\\/ ssm-debug-toggle start.*?\\/\\/ ssm-debug-toggle end\\s*/is",
		);

		$contents = preg_replace( $patterns, '', $contents );

		$block = ssm_debug_toggle_generate_block( $mode );

		$marker = "/* That's all, stop editing! Happy publishing. */";

		if ( false !== strpos( $contents, $marker ) ) {
			$contents = str_replace( $marker, $block . $marker, $contents );
		} else {
			$contents .= "\n" . $block;
		}

		$result = @file_put_contents( $config_path, $contents, LOCK_EX ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if ( false === $result ) {
			return array(
				'success' => false,
				'message' => __( 'wp-config.php فائل محفوظ نہیں ہو سکی، براہِ کرم دوبارہ کوشش کریں۔', 'ssm-debug-toggle' ),
			);
		}

		return array(
			'success' => true,
			'message' => ( 'on' === $mode )
				? __( 'Debug Mode آن کر دیا گیا ہے۔', 'ssm-debug-toggle' )
				: __( 'Debug Mode آف کر دیا گیا ہے۔', 'ssm-debug-toggle' ),
		);
	}

endif;

if ( ! function_exists( 'ssm_debug_toggle_admin_menu' ) ) :

	/**
	 * (admin menu) رجسٹر کریں۔
	 */
	function ssm_debug_toggle_admin_menu() {
		add_management_page(
			__( 'Debug Toggle', 'ssm-debug-toggle' ),
			__( 'Debug Toggle', 'ssm-debug-toggle' ),
			'manage_options',
			'ssm-debug-toggle',
			'ssm_debug_toggle_render_page'
		);
	}

	add_action( 'admin_menu', 'ssm_debug_toggle_admin_menu' );

endif;

if ( ! function_exists( 'ssm_debug_toggle_enqueue_assets' ) ) :

	/**
	 * صرف اپنے (screen) پر (assets) لوڈ کریں۔
	 *
	 * @param string $hook
	 */
	function ssm_debug_toggle_enqueue_assets( $hook ) {
		if ( 'tools_page_ssm-debug-toggle' !== $hook ) {
			return;
		}

		$plugin_url = plugin_dir_url( __FILE__ );
		$version    = '1.0.0';

		wp_enqueue_style(
			'ssm-debug-toggle-css',
			$plugin_url . 'assets/css/ssm-debug-toggle.css',
			array(),
			$version
		);

		wp_enqueue_script(
			'ssm-debug-toggle-js',
			$plugin_url . 'assets/js/ssm-debug-toggle.js',
			array( 'jquery' ),
			$version,
			true
		);

		$state       = ssm_debug_toggle_get_runtime_state();
		$config_path = ssm_debug_toggle_get_config_path();

		wp_localize_script(
			'ssm-debug-toggle-js',
			'ssmDebugToggleData',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'ssm_debug_toggle_nonce' ),
				'state'       => $state,
				'configFound' => ( null !== $config_path ),
				'messages'    => array(
					'onLabel'        => __( 'Debug آن ہے', 'ssm-debug-toggle' ),
					'offLabel'       => __( 'Debug آف ہے', 'ssm-debug-toggle' ),
					'customLabel'    => __( 'Debug کسٹم سیٹنگ پر ہے', 'ssm-debug-toggle' ),
					'turnOn'         => __( 'Debug آن کریں', 'ssm-debug-toggle' ),
					'turnOff'        => __( 'Debug آف کریں', 'ssm-debug-toggle' ),
					'unknownConfig'  => __( 'wp-config.php نہیں ملا، براہِ کرم سرور سیٹنگ چیک کریں۔', 'ssm-debug-toggle' ),
					'updating'       => __( 'برائے مہربانی انتظار کریں، سیٹنگ اپ ڈیٹ ہو رہی ہے…', 'ssm-debug-toggle' ),
					'success'        => __( 'سیٹنگ کامیابی سے اپ ڈیٹ ہو گئی۔', 'ssm-debug-toggle' ),
					'genericError'   => __( 'کوئی مسئلہ پیش آ گیا، براہِ کرم دوبارہ کوشش کریں۔', 'ssm-debug-toggle' ),
					'noPermission'   => __( 'آپ کو یہ عمل کرنے کی اجازت نہیں۔', 'ssm-debug-toggle' ),
				),
			)
		);
	}

	add_action( 'admin_enqueue_scripts', 'ssm_debug_toggle_enqueue_assets' );

endif;

if ( ! function_exists( 'ssm_debug_toggle_render_page' ) ) :

	/**
	 * (admin) پیج رینڈر کریں۔
	 */
	function ssm_debug_toggle_render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'آپ کو اس صفحہ تک رسائی کی اجازت نہیں۔', 'ssm-debug-toggle' ) );
		}

		$state       = ssm_debug_toggle_get_runtime_state();
		$config_path = ssm_debug_toggle_get_config_path();
		$mode        = isset( $state['mode'] ) ? $state['mode'] : 'custom';

		$status_label = '';
		if ( 'on' === $mode ) {
			$status_label = __( 'Debug اس وقت آن ہے۔', 'ssm-debug-toggle' );
		} elseif ( 'off' === $mode ) {
			$status_label = __( 'Debug اس وقت آف ہے۔', 'ssm-debug-toggle' );
		} else {
			$status_label = __( 'Debug کسٹم سیٹنگ پر ہے۔', 'ssm-debug-toggle' );
		}

		?>
		<div class="wrap ssm-debug-wrap">
			<h1><?php esc_html_e( 'SSM Debug Toggle', 'ssm-debug-toggle' ); ?></h1>

			<p class="description">
				<?php esc_html_e( 'یہ ٹول wp-config.php میں Debug سے متعلقہ لائنوں کو اپ ڈیٹ کرتا ہے تاکہ آپ آسانی سے Debug Mode کو آن یا آف کر سکیں۔', 'ssm-debug-toggle' ); ?>
			</p>

			<?php if ( ! $config_path ) : ?>
				<div class="notice notice-error">
					<p><?php esc_html_e( 'wp-config.php فائل نہیں ملی، براہِ کرم اپنی انسٹالیشن یا سرور کنفیگریشن چیک کریں۔', 'ssm-debug-toggle' ); ?></p>
				</div>
			<?php endif; ?>

			<div id="ssm-debug-toggle-root" class="ssm-debug-card" aria-live="polite">
				<noscript>
					<div class="notice notice-warning inline">
						<p><?php esc_html_e( 'یہ فیچر (JavaScript) کے بغیر کام نہیں کرے گا، براہِ کرم اپنے براؤزر میں JavaScript کو فعال کریں۔', 'ssm-debug-toggle' ); ?></p>
					</div>
				</noscript>

				<div class="ssm-debug-status-row">
					<span class="ssm-debug-status-label">
						<?php echo esc_html( $status_label ); ?>
					</span>
					<?php if ( $config_path ) : ?>
						<span class="ssm-debug-status-path">
							<?php
							printf(
								/* translators: %s: wp-config.php path */
								esc_html__( 'Config فائل: %s', 'ssm-debug-toggle' ),
								esc_html( $config_path )
							);
							?>
						</span>
					<?php endif; ?>
				</div>

				<div class="ssm-debug-actions-row">
					<button type="button" class="button button-primary ssm-debug-btn-on">
						<?php esc_html_e( 'Debug آن کریں', 'ssm-debug-toggle' ); ?>
					</button>
					<button type="button" class="button ssm-debug-btn-off">
						<?php esc_html_e( 'Debug آف کریں', 'ssm-debug-toggle' ); ?>
					</button>
				</div>

				<div class="ssm-debug-message" role="status" aria-live="polite"></div>
			</div>

			<script type="text/html" id="ssm-debug-toggle-template">
				<div class="ssm-debug-status-row">
					<span class="ssm-debug-status-label">{{statusText}}</span>
					<# if ( configPath ) { #>
					<span class="ssm-debug-status-path">
						<?php esc_html_e( 'Config فائل:', 'ssm-debug-toggle' ); ?>
						{{configPath}}
					</span>
					<# } #>
				</div>

				<div class="ssm-debug-actions-row">
					<button type="button" class="button button-primary ssm-debug-btn-on">
						<?php esc_html_e( 'Debug آن کریں', 'ssm-debug-toggle' ); ?>
					</button>
					<button type="button" class="button ssm-debug-btn-off">
						<?php esc_html_e( 'Debug آف کریں', 'ssm-debug-toggle' ); ?>
					</button>
				</div>

				<div class="ssm-debug-message" role="status" aria-live="polite"></div>
			</script>
		</div>
		<?php
	}

endif;

if ( ! function_exists( 'ssm_debug_toggle_ajax_get_state' ) ) :

	/**
	 * (AJAX) — موجودہ (Debug) اسٹیٹ واپس کریں۔
	 */
	function ssm_debug_toggle_ajax_get_state() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'آپ کو یہ عمل کرنے کی اجازت نہیں۔', 'ssm-debug-toggle' ),
					'code'    => 'no_permission',
				)
			);
		}

		check_ajax_referer( 'ssm_debug_toggle_nonce', 'nonce' );

		$state       = ssm_debug_toggle_get_runtime_state();
		$config_path = ssm_debug_toggle_get_config_path();

		wp_send_json_success(
			array(
				'state'       => $state,
				'configFound' => ( null !== $config_path ),
				'configPath'  => $config_path,
			)
		);
	}

	add_action( 'wp_ajax_ssm_debug_toggle_get_state', 'ssm_debug_toggle_ajax_get_state' );

endif;

if ( ! function_exists( 'ssm_debug_toggle_ajax_set_state' ) ) :

	/**
	 * (AJAX) — (Debug Mode) کو (ON/OFF) پر سیٹ کریں۔
	 */
	function ssm_debug_toggle_ajax_set_state() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'آپ کو یہ عمل کرنے کی اجازت نہیں۔', 'ssm-debug-toggle' ),
					'code'    => 'no_permission',
				)
			);
		}

		check_ajax_referer( 'ssm_debug_toggle_nonce', 'nonce' );

		$mode = isset( $_POST['mode'] ) ? sanitize_text_field( wp_unslash( $_POST['mode'] ) ) : '';

		if ( ! in_array( $mode, array( 'on', 'off' ), true ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'غلط موڈ منتخب کیا گیا ہے۔', 'ssm-debug-toggle' ),
					'code'    => 'invalid_mode',
				)
			);
		}

		$result = ssm_debug_toggle_update_config( $mode );

		if ( ! $result['success'] ) {
			wp_send_json_error(
				array(
					'message' => $result['message'],
					'code'    => 'update_failed',
				)
			);
		}

		// اپ ڈیٹ کے بعد رن ٹائم اسٹیٹ دوبارہ پڑھیں۔
		$state       = ssm_debug_toggle_get_runtime_state();
		$config_path = ssm_debug_toggle_get_config_path();

		wp_send_json_success(
			array(
				'message'    => $result['message'],
				'state'      => $state,
				'configPath' => $config_path,
			)
		);
	}

	add_action( 'wp_ajax_ssm_debug_toggle_set_state', 'ssm_debug_toggle_ajax_set_state' );

endif;
