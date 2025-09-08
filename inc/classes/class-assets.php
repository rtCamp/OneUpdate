<?php
/**
 * Enqueue assets for OneUpdate plugin.
 *
 * @package OneUpdate
 */

namespace OneUpdate;

use OneUpdate\Plugin_Configs\Constants;
use OneUpdate\Traits\Singleton;

/**
 * Enqueue assets for OneUpdate.
 */
class Assets {

	/**
	 * Use Singleton trait.
	 */
	use Singleton;

	/**
	 * Protected class constructor
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Setup WordPress hooks
	 */
	public function setup_hooks(): void {
		// Enqueue Admin scripts and styles.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ), 99 );
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook_suffix Admin page name.
	 *
	 * @return void
	 */
	public function enqueue_admin_scripts( $hook_suffix ) {

		if ( strpos( $hook_suffix, 'oneupdate-settings' ) !== false ) {
			remove_all_actions( 'admin_notices' );
			$this->register_script(
				'oneupdate-settings-script',
				'js/settings.js',
			);

			wp_localize_script(
				'oneupdate-settings-script',
				'OneUpdateSettings',
				array(
					'nonce'     => wp_create_nonce( 'wp_rest' ),
					'restUrl'   => esc_url( home_url( '/wp-json' ) ),
					'apiKey'    => get_option( Constants::ONEUPDATE_API_KEY, '' ),
					'restNonce' => wp_create_nonce( 'wp_rest' ),
					'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
					'setupUrl'  => admin_url( 'admin.php?page=oneupdate-settings' ),
				)
			);

			wp_enqueue_script( 'oneupdate-settings-script' );

		}

		if ( strpos( $hook_suffix, 'toplevel_page_oneupdate' ) !== false ) {
			// remove all admin notices.
			remove_all_actions( 'admin_notices' );
			setcookie( 'vip-go-cb', '1', time() + ( 86400 * 30 ), '/' ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.cookies_setcookie -- this is to avoid caching issue in vip environment.

			$this->register_script(
				'oneupdate-plugins-manager-script',
				'js/plugin-manager.js',
			);

			wp_localize_script(
				'oneupdate-plugins-manager-script',
				'OneUpdatePlugins',
				array(
					'nonce'     => wp_create_nonce( 'wp_rest' ),
					'restUrl'   => esc_url( home_url( '/wp-json' ) ),
					'apiKey'    => get_option( Constants::ONEUPDATE_API_KEY, '' ),
					'restNonce' => wp_create_nonce( 'wp_rest' ),
				)
			);

			wp_enqueue_script( 'oneupdate-plugins-manager-script' );

			$this->register_style(
				'oneupdate-plugins-manager-style',
				'css/plugin-card.css'
			);
			wp_enqueue_style( 'oneupdate-plugins-manager-style' );

		}

		if ( strpos( $hook_suffix, 'plugins' ) !== false ) {
			remove_all_actions( 'admin_notices' );
			$this->register_script(
				'oneupdate-setup-script',
				'js/plugin.js',
			);

			wp_localize_script(
				'oneupdate-setup-script',
				'OneUpdateSettings',
				array(
					'nonce'     => wp_create_nonce( 'wp_rest' ),
					'restUrl'   => esc_url( home_url( '/wp-json' ) ),
					'apiKey'    => get_option( Constants::ONEUPDATE_API_KEY, '' ),
					'restNonce' => wp_create_nonce( 'wp_rest' ),
					'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
					'setupUrl'  => admin_url( 'admin.php?page=oneupdate-settings' ),
				)
			);

			wp_enqueue_script( 'oneupdate-setup-script' );

		}

		if ( strpos( $hook_suffix, 'oneupdate-pull-requests' ) !== false ) {
			remove_all_actions( 'admin_notices' );
			$this->register_script(
				'oneupdate-pull-requests-script',
				'js/pull-requests.js',
			);

			$site_name_gh_repo = array();
			$oneupdate_sites   = $GLOBALS['oneupdate_sites'] ?? array();
			if ( ! empty( $oneupdate_sites ) && is_array( $oneupdate_sites ) ) {
				foreach ( $oneupdate_sites as $site ) {
					if ( ! empty( $site['siteName'] ) && ! empty( $site['gh_repo'] ) && in_array( $site['gh_repo'], $site_name_gh_repo, true ) === false ) {
						$site_name_gh_repo[ $site['gh_repo'] ] = $site['siteName'];
					}
				}
			}

			wp_localize_script(
				'oneupdate-pull-requests-script',
				'OneUpdatePullRequests',
				array(
					'restUrl'   => esc_url( home_url( '/wp-json' ) ),
					'apiKey'    => get_option( Constants::ONEUPDATE_API_KEY, '' ),
					'restNonce' => wp_create_nonce( 'wp_rest' ),
					'repos'     => $site_name_gh_repo,
				)
			);

			wp_enqueue_script( 'oneupdate-pull-requests-script' );

		}

		// load admin styles.
		$this->register_style( 'oneupdate-admin-style', 'css/admin.css' );
		wp_enqueue_style( 'oneupdate-admin-style' );
	}

	/**
	 * Get asset dependencies and version info from {handle}.asset.php if exists.
	 *
	 * @param string $file File name.
	 * @param array  $deps Script dependencies to merge with.
	 * @param string $ver  Asset version string.
	 *
	 * @return array
	 */
	public function get_asset_meta( $file, $deps = array(), $ver = false ) {
		$asset_meta_file = sprintf( '%s/js/%s.asset.php', untrailingslashit( ONEUPDATE_PLUGIN_LOADER_FEATURES_PATH . '/assets/build' ), basename( $file, '.' . pathinfo( $file )['extension'] ) );
		$asset_meta      = is_readable( $asset_meta_file )
			? require $asset_meta_file
			: array(
				'dependencies' => array(),
				'version'      => $this->get_file_version( $file, $ver ),
			);

		$asset_meta['dependencies'] = array_merge( $deps, $asset_meta['dependencies'] );

		return $asset_meta;
	}

	/**
	 * Register a new script.
	 *
	 * @param string           $handle    Name of the script. Should be unique.
	 * @param string|bool      $file       script file, path of the script relative to the assets/build/ directory.
	 * @param array            $deps      Optional. An array of registered script handles this script depends on. Default empty array.
	 * @param string|bool|null $ver       Optional. String specifying script version number, if not set, filetime will be used as version number.
	 * @param bool             $in_footer Optional. Whether to enqueue the script before </body> instead of in the <head>.
	 *                                    Default 'false'.
	 * @return bool Whether the script has been registered. True on success, false on failure.
	 */
	public function register_script( $handle, $file, $deps = array(), $ver = false, $in_footer = true ) {

		$file_path = sprintf( '%s/%s', ONEUPDATE_PLUGIN_LOADER_FEATURES_PATH . '/assets/build', $file );

		if ( ! \file_exists( $file_path ) ) {
			return false;
		}

		$src        = sprintf( ONEUPDATE_PLUGIN_LOADER_FEATURES_URL . '/assets/build/%s', $file );
		$asset_meta = $this->get_asset_meta( $file, $deps );

		// register each dependency styles.
		if ( ! empty( $asset_meta['dependencies'] ) ) {
			foreach ( $asset_meta['dependencies'] as $dependency ) {
				wp_enqueue_style( $dependency );
			}
		}

		return wp_register_script( $handle, $src, $asset_meta['dependencies'], $asset_meta['version'], $in_footer );
	}

	/**
	 * Register a CSS stylesheet.
	 *
	 * @param string           $handle Name of the stylesheet. Should be unique.
	 * @param string|bool      $file    style file, path of the script relative to the assets/build/ directory.
	 * @param array            $deps   Optional. An array of registered stylesheet handles this stylesheet depends on. Default empty array.
	 * @param string|bool|null $ver    Optional. String specifying script version number, if not set, filetime will be used as version number.
	 * @param string           $media  Optional. The media for which this stylesheet has been defined.
	 *                                 Default 'all'. Accepts media types like 'all', 'print' and 'screen', or media queries like
	 *                                 '(orientation: portrait)' and '(max-width: 640px)'.
	 *
	 * @return bool Whether the style has been registered. True on success, false on failure.
	 */
	public function register_style( $handle, $file, $deps = array(), $ver = false, $media = 'all' ) {

		$file_path = sprintf( '%s/%s', ONEUPDATE_PLUGIN_LOADER_FEATURES_PATH . '/assets/build', $file );

		if ( ! \file_exists( $file_path ) ) {
			return false;
		}

		$src     = sprintf( ONEUPDATE_PLUGIN_LOADER_FEATURES_URL . '/assets/build/%s', $file );
		$version = $this->get_file_version( $file, $ver );

		return wp_register_style( $handle, $src, $deps, $version, $media );
	}

	/**
	 * Get file version.
	 *
	 * @param string             $file File path.
	 * @param int|string|boolean $ver  File version.
	 *
	 * @return bool|false|int
	 */
	public function get_file_version( $file, $ver = false ) {
		if ( ! empty( $ver ) ) {
			return $ver;
		}

		$file_path = sprintf( '%s/%s', ONEUPDATE_PLUGIN_LOADER_FEATURES_PATH . '/assets/build', $file );

		return file_exists( $file_path ) ? filemtime( $file_path ) : false;
	}
}
