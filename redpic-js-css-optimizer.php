<?php
/**
 * RedPic JS&CSS Optimizer
 *
 * @package Redpic JS&CSS Optimizer
 */

/**
Plugin Name: RedPic JS&CSS optimizer
Description: js-css optimizer
Version:     1.6
Plugin URI:  https://wordpress.org/plugins/redpic-js-css-optimizer/
Author:      RedPic
Author URI:  https://profiles.wordpress.org/alekseyf
License:     GPL-2.0+
License URI: http://www.gnu.org/licenses/gpl-2.0.txt
Text Domain: redpic-js-css-optimizer
Domain Path: /languages

 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'REDPIC_JS_CSS_VERSION', '1.6' );
define( 'REDPIC_JS_CSS_OPTIMIZER_SLUG', 'redpic-js-css-optimizer' );
define( 'REDPIC_JS_CSS_OPTIMIZER_CACHE_DIRECTORY_NAME', 'redpic-cache' );

/**
 * Bootstrap frontend scripts.
 */
function redpic_js_css_optimizer() {
	require_once __DIR__ . '/lib/class-redpic-js-css-optimizer.php';
	$optimizer = new Redpic_Js_Css_Optimizer( 'js' );
	$optimizer->optimize();
	$optimizer = new Redpic_Js_Css_Optimizer( 'css' );
	$optimizer->optimize();
}
add_action( 'plugins_loaded', 'redpic_js_css_optimizer', PHP_INT_MAX );

function redpic_js_css_optimizer_load_textdomain() {
	load_plugin_textdomain(
		'redpic-js-css-optimizer',
		false,
		__DIR__ . '/languages/'
	);
}
add_action( 'plugins_loaded', 'redpic_js_css_optimizer_load_textdomain', 100 );

if ( is_admin() ) {
	/**
	 * Bootstrap admin scripts.
	 */
	function redpic_js_css_optimizer_admin() {
		require_once __DIR__ . '/lib/class-redpic-js-css-optimizer-admin.php';
		Redpic_Js_Css_Optimizer_Admin::init();
	}
	redpic_js_css_optimizer_admin();
}
