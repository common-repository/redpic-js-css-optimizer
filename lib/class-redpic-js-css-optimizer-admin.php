<?php
/**
 * RedPic JS&CSS Optimizer admin
 *
 * @package Redpic JS&CSS Optimizer
 */

/**
 * Class Redpic_Js_Css_Optimizer_Admin
 */
class Redpic_Js_Css_Optimizer_Admin {
	/**
	 * Redpic_Js_Css_Optimizer_Admin constructor.
	 */
	private function __construct() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_clear_cache', array( $this, 'clear_cache' ) );
		add_action(
			'admin_menu',
			function () {
				add_options_page(
					__( 'Redpic JS&CSS Optimizer', 'redpic-js-css-optimizer' ),
					__( 'Redpic JS&CSS Optimizer', 'redpic-js-css-optimizer' ),
					'manage_options',
					REDPIC_JS_CSS_OPTIMIZER_SLUG,
					array( $this, 'page_settings' )
				);
			}
		);
		add_filter(
			'plugin_action_links_redpic-js-css-optimizer/redpic-js-css-optimizer.php',
			function ( $links ) {
				$settings_link = '<a href="options-general.php?page=' . REDPIC_JS_CSS_OPTIMIZER_SLUG . '">' . __( 'Settings' ) . '</a>';
				array_unshift( $links, $settings_link );

				return $links;
			}
		);
	}

	/**
	 * Class initializer.
	 */
	public static function init() {
		new self();
	}

	/**
	 * Private clear cache method.
	 */
	private function cc() {
		$upload_dir = wp_upload_dir();
		$pattern    = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . REDPIC_JS_CSS_OPTIMIZER_CACHE_DIRECTORY_NAME . DIRECTORY_SEPARATOR . '*';
		array_map( 'unlink', array_filter( (array) glob( $pattern ) ) );
	}

	/**
	 * Clear cache endpoint.
	 */
	public function clear_cache() {
		$nonce = sanitize_text_field( $_POST['nonce'] );
		if ( ! wp_verify_nonce( $nonce, REDPIC_JS_CSS_OPTIMIZER_SLUG . '_clear_cache_nonce' ) || ! current_user_can( 'manage_options' ) ) {
			header( 'Location:' . $_SERVER['HTTP_REFERER'] . '&error=unauthenticated' );
			exit();
		}
		$this->cc();
		wp_safe_redirect( admin_url( 'options-general.php?page=' . REDPIC_JS_CSS_OPTIMIZER_SLUG . '&cache_cleared' ) );
		exit;
	}

	/**
	 * Print settings page.
	 */
	public function page_settings() {
		print '<div class="wrap"><h2>' . __( 'Redpic JS&CSS Optimizer', 'redpic-js-css-optimizer' ) . '</h2>';
		print '<form action="' . admin_url( 'admin-post.php' ) . '" method="post">';
		print '<p>' . __( 'After change JS or CSS files clear cache for regenerate files.', 'redpic-js-css-optimizer' ) . '</p>';
		print '<input type="hidden" name="nonce" value="' . wp_create_nonce( REDPIC_JS_CSS_OPTIMIZER_SLUG . '_clear_cache_nonce' ) . '" />';
		print '<input type="hidden" name="action" value="clear_cache" />';
		if ( array_key_exists( 'cache_cleared', $_GET ) ) {
			print '<div id="setting-error-settings_updated" class="updated settings-error notice is-dismissible"><p><strong>' . __( 'Cache cleared.', 'redpic-js-css-optimizer' ) . '</strong></p><button type="button" class="notice-dismiss"><span class="screen-reader-text">' . __( 'Hide notification.', 'redpic-js-css-optimizer' ) . '</span></button></div>';
		}
		print '<p><input type="submit" class="button-primary" value="' . __( 'Clear cache', 'redpic-js-css-optimizer' ) . '" /></p>';
		print '</form>';
		print '<form method="post" action="' . admin_url( 'options.php' ) . '">';
		print '<p>' . __( 'Use option', 'redpic-js-css-optimizer' ) . ' "' . __( 'Debug mode for authorized users', 'redpic-js-css-optimizer' ) . '" ' . __( 'to adjust your styles and scripts. System will ignore saved cache and files will be regenerated each request. When you finish - disable this option and clear the cache.', 'redpic-js-css-optimizer' ) . '</p>';

		settings_fields( REDPIC_JS_CSS_OPTIMIZER_SLUG );
		do_settings_sections( REDPIC_JS_CSS_OPTIMIZER_SLUG );
		print '<input type="hidden" name="_wp_http_referer" value="' . admin_url( 'options-general.php?page=' . REDPIC_JS_CSS_OPTIMIZER_SLUG ) . '" />';
		print '<input type="submit" class="button-primary" value="' . __( 'Save Changes' ) . '" />';
		print '</form></div>';
	}

	/**
	 * Register available settings.
	 */
	public function register_settings() {
		register_setting(
			REDPIC_JS_CSS_OPTIMIZER_SLUG,
			REDPIC_JS_CSS_OPTIMIZER_SLUG
		);
		add_settings_section(
			REDPIC_JS_CSS_OPTIMIZER_SLUG . '_settings',
			__( 'Settings' ),
			'',
			REDPIC_JS_CSS_OPTIMIZER_SLUG
		);
		add_settings_field(
			'optimize_js',
			__( 'Optimize JS', 'redpic-js-css-optimizer' ),
			array( $this, 'display_settings' ),
			REDPIC_JS_CSS_OPTIMIZER_SLUG,
			REDPIC_JS_CSS_OPTIMIZER_SLUG . '_settings',
			array(
				'type' => 'checkbox',
				'id'   => 'optimize_js',
				'desc' => '',
			)
		);
		add_settings_field(
			'optimize_css',
			__( 'Optimize CSS', 'redpic-js-css-optimizer' ),
			array( $this, 'display_settings' ),
			REDPIC_JS_CSS_OPTIMIZER_SLUG,
			REDPIC_JS_CSS_OPTIMIZER_SLUG . '_settings',
			array(
				'type' => 'checkbox',
				'id'   => 'optimize_css',
				'desc' => '',
			)
		);
		add_settings_field(
			'debug',
			__( 'Debug mode for authorized users', 'redpic-js-css-optimizer' ),
			array( $this, 'display_settings' ),
			REDPIC_JS_CSS_OPTIMIZER_SLUG,
			REDPIC_JS_CSS_OPTIMIZER_SLUG . '_settings',
			array(
				'type' => 'checkbox',
				'id'   => 'debug',
			)
		);
	}

	/**
	 * Print setting field.
	 *
	 * @param array $args Setting field arguments.
	 */
	public function display_settings( $args ) {
		$options = get_option( REDPIC_JS_CSS_OPTIMIZER_SLUG );
		if ( false === $options ) {
			$options = array(
				'optimize_js'  => 1,
				'optimize_css' => 1,
			);
			update_option( REDPIC_JS_CSS_OPTIMIZER_SLUG, $options );
		} else if ( !is_array( $options ) ) {
			$options = array();
		}
		switch ( $args['type'] ) {
			case 'checkbox':
				$checked = ( array_key_exists( $args['id'], $options ) && $options[ $args['id'] ] == 1 ) ? ' checked="checked"' : '';
				print '<label><input type="checkbox" id="' . $args['id'] . '" value="1" name="' . REDPIC_JS_CSS_OPTIMIZER_SLUG . '[' . $args['id'] . ']"' . $checked . ' /></label> ';
				break;
		}
	}
}
