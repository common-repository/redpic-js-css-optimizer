<?php
/**
 * RedPic JS&CSS Optimizer lib
 *
 * @package Redpic JS&CSS Optimizer
 */

/**
 * Class Redpic_Js_Css_Optimizer
 */
class Redpic_Js_Css_Optimizer {
	const VERIFY_SSL = false;

	/**
	 * @var string
	 */
	private $type;
	/**
	 * @var array
	 */
	private $options;
	/**
	 * @var WP_Styles|WP_Scripts
	 */
	private $wp_items;

	/**
	 * Redpic_Js_Css_Optimizer constructor.
	 *
	 * @param $type
	 */
	public function __construct( $type ) {
		if ( ! in_array( $type, array( 'js', 'css' ), true ) ) {
			wp_die( 'Redpic_Js_Css_Optimizer: Incorrect optimizer type.' );
		}
		$this->type = $type;

		$options = get_option( REDPIC_JS_CSS_OPTIMIZER_SLUG );
		if ( false === $options ) {
			$options = array(
				'optimize_js'  => 1,
				'optimize_css' => 1,
			);
			update_option( REDPIC_JS_CSS_OPTIMIZER_SLUG, $options );
		}
		if ( ! is_array( $options ) ) {
			$options = array();
		}
		$this->options = $options;
	}

	/**
	 * @param $name
	 *
	 * @return string
	 */
	public function get_option( $name ) {
		if ( array_key_exists( $name, $this->options ) ) {
			return $this->options[ $name ];
		}

		return '';
	}

	/**
	 * Run the optimizer.
	 */
	public function optimize() {
		switch ( $this->type ) {
			case 'js':
				add_action( 'wp_enqueue_scripts', array( $this, 'optimize_js' ), PHP_INT_MAX );
				break;
			case 'css':
				add_action( 'wp_enqueue_scripts', array( $this, 'optimize_css' ), PHP_INT_MAX );
		}
	}

	/**
	 * Optimize JavaScripts.
	 */
	public function optimize_js() {
		global $wp_scripts;
		$this->wp_items = $wp_scripts;
		$this->start_optimize();
	}

	/**
	 * Optimize CSS.
	 */
	public function optimize_css() {
		global $wp_styles;
		$this->wp_items = $wp_styles;
		$this->start_optimize();
	}

	/**
	 * Gets remote file content.
	 *
	 * @param string $url URL.
	 *
	 * @return string
	 */
	private static function get_contents( $url ) {
		$response = wp_remote_get( $url, array( 'sslverify' => self::VERIFY_SSL ) );
		if ( is_wp_error( $response ) || !in_array( wp_remote_retrieve_response_code( $response ), array( 200, 201, 202, 203, 204, 205, 206 ) ) ) {
			if ( preg_match( '#^' . preg_quote( get_site_url() ) . '/#isu', $url ) ) {
				$path = preg_replace( '#^' . preg_quote( get_site_url() ) . '/#isu', ABSPATH, $url );
				$ext = pathinfo( $path, PATHINFO_EXTENSION );
				if ( $ext !== 'php' ) {
					global $wp_filesystem;
					if ( empty( $wp_filesystem ) ) {
						require_once ABSPATH . 'wp-admin/includes/file.php';
						WP_Filesystem();
					}
					return $wp_filesystem->get_contents( $path );
				}
			}
		}

		return wp_remote_retrieve_body( $response );
	}

	/**
	 * Saves cache file to filesystem.
	 *
	 * @param string $file Filename.
	 * @param string $contents Content of file.
	 */
	private static function put_contents( $file, $contents ) {
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}
		$wp_filesystem->put_contents( $file, $contents );
	}

	/**
	 * @param array $handles
	 * @param array $result
	 *
	 * @return array
	 */
	private function get_recursive_handles( $handles, $result = array() ) {
		foreach ( $handles as $handle ) {
			if ( array_key_exists( $handle, $this->wp_items->registered ) && ! in_array( $handle, $result, true ) ) {
				if ( count( $this->wp_items->registered[ $handle ]->deps ) > 0 ) {
					$result = $this->get_recursive_handles( $this->wp_items->registered[ $handle ]->deps, $result );
				}
				if ( $this->wp_items->registered[ $handle ]->src ) {
					$result[] = $handle;
				}
			}
		}

		return $result;
	}

	/**
	 * @return array
	 */
	private function get_handles_info() {
		$handles      = self::get_recursive_handles( $this->wp_items->queue );
		$handles_info = array();
		foreach ( $handles as $handle ) {
			$in_footer  = (
				array_key_exists( 'group', $this->wp_items->registered[ $handle ]->extra ) &&
				1 === $this->wp_items->registered[ $handle ]->extra['group']
			) ? true : false;
			$data       = (
			array_key_exists( 'data', $this->wp_items->registered[ $handle ]->extra )
			) ? $this->wp_items->registered[ $handle ]->extra['data'] : '';
			$before_arr = (
			array_key_exists( 'before', $this->wp_items->registered[ $handle ]->extra )
			) ? $this->wp_items->registered[ $handle ]->extra['before'] : array();
			$after_arr  = (
			array_key_exists( 'after', $this->wp_items->registered[ $handle ]->extra )
			) ? $this->wp_items->registered[ $handle ]->extra['after'] : array();

			$before = '';
			$after  = '';
			if ( is_array( $before_arr ) ) {
				foreach ( $before_arr as $b ) {
					$before .= $b . "\n";
				}
			} else {
				$before = $before_arr;
			}
			if ( is_array( $after_arr ) ) {
				foreach ( $after_arr as $a ) {
					$after .= $a . "\n";
				}
			} else {
				$after = $after_arr;
			}
			$handles_info[] = array(
				'handle'    => $handle,
				'in_footer' => $in_footer,
				'before'    => $before . $data,
				'after'     => $after,
				'url'       => self::normalize_url( $this->wp_items->registered[ $handle ]->src ),
			);
		}

		return $handles_info;
	}

	/**
	 * Start optimizations.
	 */
	private function start_optimize() {
		if ( ! $this->get_option( 'optimize_' . $this->type ) ) {
			return;
		}
		$handles_info  = $this->get_handles_info();
		$header_unique = '';
		$footer_unique = '';
		foreach ( $handles_info as $handle_info ) {
			if ( ! $handle_info['in_footer'] ) {
				$header_unique .= $handle_info['handle'] . '|';
			} else {
				$footer_unique .= $handle_info['handle'] . '|';
			}
			switch ( $this->type ) {
				case 'js':
					wp_dequeue_script( $handle_info['handle'] );
					wp_deregister_script( $handle_info['handle'] );
					$this->wp_items->done[] = $handle_info['handle'];
					break;
				case 'css':
					wp_dequeue_style( $handle_info['handle'] );
					$this->wp_items->done[] = $handle_info['handle'];
			}
		}
		if ( 'js' === $this->type && ! array_key_exists( 'jquery', $this->wp_items->done ) ) {
			$this->wp_items->done[] = 'jquery';
		}
		list( $header_unique, $footer_unique ) = preg_replace( '#\|$#', '', array( $header_unique, $footer_unique ) );

		$header_unique_md5 = md5( $header_unique );
		$footer_unique_md5 = md5( $footer_unique );

		$upload_dir  = wp_upload_dir();
		$header_file = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . REDPIC_JS_CSS_OPTIMIZER_CACHE_DIRECTORY_NAME . DIRECTORY_SEPARATOR . $header_unique_md5 . '.' . $this->type;
		$footer_file = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . REDPIC_JS_CSS_OPTIMIZER_CACHE_DIRECTORY_NAME . DIRECTORY_SEPARATOR . $footer_unique_md5 . '.' . $this->type;
		$header_url  = $upload_dir['baseurl'] . DIRECTORY_SEPARATOR . REDPIC_JS_CSS_OPTIMIZER_CACHE_DIRECTORY_NAME . DIRECTORY_SEPARATOR . $header_unique_md5 . '.' . $this->type;
		$footer_url  = $upload_dir['baseurl'] . DIRECTORY_SEPARATOR . REDPIC_JS_CSS_OPTIMIZER_CACHE_DIRECTORY_NAME . DIRECTORY_SEPARATOR . $footer_unique_md5 . '.' . $this->type;

		$debug         = $this->get_option( 'debug' ) && is_user_logged_in();
		$header_exists = ( $debug ) ? false : is_file( $header_file );
		$footer_exists = ( $debug ) ? false : is_file( $footer_file );
		if ( ! $header_exists || ! $footer_exists ) {
			if ( ! is_dir( $upload_dir['basedir'] . DIRECTORY_SEPARATOR . REDPIC_JS_CSS_OPTIMIZER_CACHE_DIRECTORY_NAME ) ) {
				mkdir( $upload_dir['basedir'] . DIRECTORY_SEPARATOR . REDPIC_JS_CSS_OPTIMIZER_CACHE_DIRECTORY_NAME );
			}
			$header_concat = '';
			$footer_concat = '';
			foreach ( $handles_info as $handle_info ) {
				if ( ! $handle_info['in_footer'] && ! $header_exists ) {
					$content = self::get_contents( $handle_info['url'] );
					if ( 'css' === $this->type ) {
						$content = self::replace_css_paths( $content, $handle_info['url'] );
					}
					$header_concat .= $content . "\n";
				} elseif ( $handle_info['in_footer'] && ! $footer_exists ) {
					$content = self::get_contents( $handle_info['url'] );
					$footer_concat .= $content . "\n";
				}
			}
			if ( $header_concat ) {
				self::put_contents( $header_file, $header_concat );
			}
			if ( $footer_concat ) {
				self::put_contents( $footer_file, $footer_concat );
			}
		}
		$header_before = '';
		$footer_before = '';
		$header_after  = '';
		$footer_after  = '';
		foreach ( $handles_info as $handle_info ) {
			if ( $handle_info['before'] ) {
				if ( ! $handle_info['in_footer'] ) {
					$header_before .= $handle_info['before'] . "\n";
				} else {
					$footer_before .= $handle_info['before'] . "\n";
				}
			}
			if ( $handle_info['after'] ) {
				if ( ! $handle_info['in_footer'] ) {
					$header_after .= $handle_info['after'] . "\n";
				} else {
					$footer_after .= $handle_info['after'] . "\n";
				}
			}
		}
		switch ( $this->type ) {
			case 'js':
				wp_enqueue_script( 'redpic-js-optimizer-header', $header_url, '', REDPIC_JS_CSS_VERSION, false );
				wp_enqueue_script( 'redpic-js-optimizer-footer', $footer_url, '', REDPIC_JS_CSS_VERSION, true );
				if ( $header_before ) {
					wp_add_inline_script( 'redpic-js-optimizer-header', $header_before, 'before' );
				}
				if ( $footer_before ) {
					wp_add_inline_script( 'redpic-js-optimizer-footer', $footer_before, 'before' );
				}
				if ( $header_after ) {
					wp_add_inline_script( 'redpic-js-optimizer-header', $header_after, 'after' );
				}
				if ( $footer_after ) {
					wp_add_inline_script( 'redpic-js-optimizer-footer', $footer_after, 'after' );
				}
				break;
			case 'css':
				wp_enqueue_style( 'redpic-css-optimizer-header', $header_url, '', REDPIC_JS_CSS_VERSION );
				if ( $header_before ) {
					wp_add_inline_style( 'redpic-css-optimizer-header', $header_before );
				}
		}
	}

	/**
	 * Url normalization.
	 *
	 * @param string $url URL.
	 *
	 * @return string
	 */
	private static function normalize_url( $url ) {
		$domain = preg_replace( '#^((?:https?:)?//)#', '', get_site_url() );
		$path   = preg_replace( '#^((?:https?:)?//)' . preg_quote( $domain, '#' ) . '#', '', $url );
		if ( ! preg_match( '#^http#isu', $path ) ) {
			$path = preg_replace( '#^/#isu', '', $path );

			return get_site_url() . '/' . $path;
		}

		return $path;
	}

	/**
	 * Fix css paths.
	 *
	 * @param string $css Css content.
	 * @param string $url Url.
	 *
	 * @return string|string[]|null
	 */
	private static function replace_css_paths( $css, $url ) {
		$search  = '%url\s*\(\s*[\\\'"]?(?!(((?:https?:)?\/\/)|(?:data:?:)))([^\\\'")]+)[\\\'"]?\s*\)%';
		$replace = 'url("' . dirname( $url ) . '/$3")';

		$css = preg_replace( $search, $replace, $css );
		$css = preg_replace( '%url\(\"' . preg_quote( dirname( $url ), '%' ) . '//%', 'url("/', $css );

		return $css;
	}
}
