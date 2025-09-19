<?php
/**
 * Class Cache - This is to manage oneupdate_get_plugins transient.
 *
 * @package OneUpdate
 */

namespace OneUpdate;

use OneUpdate\Traits\Singleton;

/**
 * Class Cache
 */
class Cache {

	/**
	 * Use Singleton trait.
	 */
	use Singleton;

	/**
	 * Cache constructor.
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Setup hooks.
	 *
	 * @return void
	 */
	public function setup_hooks(): void {

		// if current site type is governing site then do not run the cache hooks.
		if ( Utils::is_governing_site() ) {
			return;
		}

		// Clear the cache when the plugin is activated, deactivated, uninstalled, or updated.
		add_action( 'activated_plugin', array( $this, 'plugin_activation' ) );
		add_action( 'deactivated_plugin', array( $this, 'plugin_deactivation' ) );
		add_action( 'upgrader_process_complete', array( $this, 'clear_update_plugin_cache' ), 10, 2 );
		add_action( 'deleted_plugin', array( $this, 'remove_plugin_from_transient' ) );
	}

	/**
	 * Plugin activation hook.
	 *
	 * @param string $plugin The plugin being activated.
	 *
	 * @return void
	 */
	public function plugin_activation( $plugin ): void {
		// Clear the cache for the plugin being activated.
		self::rebuild_transient_for_single_plugin( $plugin, true, false );
	}

	/**
	 * Plugin deactivation hook.
	 *
	 * @param string $plugin The plugin being deactivated.
	 *
	 * @return void
	 */
	public function plugin_deactivation( $plugin ): void {
		// Clear the cache for the plugin being deactivated.
		self::rebuild_transient_for_single_plugin( $plugin, false, true );
	}

	/**
	 * Remove a plugin from the transient cache.
	 *
	 * @return void
	 */
	public function remove_plugin_from_transient(): void {
		// delete transient.
		delete_transient( 'oneupdate_get_plugins' );
	}

	/**
	 * Clear the cache after plugin update.
	 *
	 * @param \WP_Upgrader $upgrader The upgrader instance.
	 * @param array        $hook_extra Extra hook data.
	 *
	 * @return void
	 */
	public function clear_update_plugin_cache( $upgrader, $hook_extra ): void {
		// Check if the plugin being updated is the OneUpdate plugin.
		if ( isset( $hook_extra['action'] ) && 'update' === $hook_extra['action'] && isset( $hook_extra['type'] ) && 'plugin' === $hook_extra['type'] ) {
			// delete transient.
			delete_transient( 'oneupdate_get_plugins' );
		}
	}

	/**
	 * Build the plugins transient.
	 *
	 * @return array|\WP_Error
	 */
	public static function build_plugins_transient(): array|\WP_Error {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugins = get_plugins();

		if ( empty( $plugins ) ) {
			return new \WP_Error( 'no_plugins', __( 'No plugins found.', 'oneupdate' ), array( 'status' => 404 ) );
		}

		// add is_active field to each plugin.
		foreach ( $plugins as $slug => $plugin ) {
			$plugins[ $slug ]['is_active'] = is_plugin_active( $slug );
			// example this is plugin slug block-visibility/block-visibility.php.
			// I only want the slug part block-visibility.
			$plugins[ $slug ]['plugin_slug']         = explode( '/', $slug )[0];
			$plugins[ $slug ]['is_update_available'] = false;
			// if plugin is hello.php then its slug will be hello-dolly.
			if ( 'hello.php' === $slug ) {
				$plugins[ $slug ]['plugin_slug'] = 'hello-dolly';
			}
			// check if plugin is public or private by hitting https://api.wordpress.org/plugins/info/1.0/{plugin-slug}.json endpoint.
			$plugin_info = wp_safe_remote_get(
				"https://api.wordpress.org/plugins/info/1.0/{$plugins[$slug]['plugin_slug']}.json?fields=icons",
				array(
					'timeout' => 5, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout -- this is to avoid timeout issues.
					'headers' => array(
						'Accept' => 'application/json',
					),
				)
			);
			if ( is_wp_error( $plugin_info ) || wp_remote_retrieve_response_code( $plugin_info ) !== 200 ) {
				$plugins[ $slug ]['is_public'] = false;
			} else {
				$plugin_info                             = json_decode( wp_remote_retrieve_body( $plugin_info ), true );
				$plugins[ $slug ]['is_public']           = ! empty( $plugin_info['name'] );
				$plugins[ $slug ]['plugin_info']         = $plugin_info;
				$plugins[ $slug ]['is_update_available'] = version_compare(
					$plugins[ $slug ]['Version'],
					$plugin_info['version'] ?? '',
					'<'
				);
			}
		}

		// get_plugin_updates() returns an array of plugins that have updates available.
		if ( ! function_exists( 'get_plugin_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}
		$updates = get_plugin_updates();
		if ( ! empty( $updates ) ) {
			foreach ( $updates as $slug => $update ) {
				if ( isset( $plugins[ $slug ] ) ) {
					$plugins[ $slug ]['update'] = $update->update;
				}
			}
		}

		// reconstruct plugins array and make slug like this block-visibility instead of block-visibility/block-visibility.php.
		$reconstructed_plugins = array();
		foreach ( $plugins as $slug => $plugin ) {
			$plugin_slug                           = explode( '/', $slug )[0];
			$reconstructed_plugins[ $plugin_slug ] = $plugin;
			// add plugin slug to the plugin array.
			$reconstructed_plugins[ $plugin_slug ]['plugin_slug']      = $plugin_slug;
			$reconstructed_plugins[ $plugin_slug ]['plugin_path_info'] = $slug;
		}

		// set the cache for one hour.
		set_transient( 'oneupdate_get_plugins', wp_json_encode( $reconstructed_plugins ), HOUR_IN_SECONDS );

		// Return the reconstructed plugins array for rest response.
		return $reconstructed_plugins;
	}

	/**
	 * Rebuild the transient for a single plugin.
	 *
	 * @param string $plugin_slug The plugin slug.
	 * @param bool   $is_activation Whether the plugin is being activated.
	 * @param bool   $is_deactivation Whether the plugin is being deactivated.
	 *
	 * @return void
	 */
	public static function rebuild_transient_for_single_plugin( string $plugin_slug, bool $is_activation = false, bool $is_deactivation = false ) {
		$original_plugin_slug = $plugin_slug;
		if ( 'hello.php' === $plugin_slug ) {
			self::hello_dolly_plugin( $is_activation, $is_deactivation );
			return;
		} else {
			$plugin_slug = explode( '/', $plugin_slug )[0];
		}

		$existing_transient = get_transient( 'oneupdate_get_plugins' );
		if ( ! $existing_transient ) {
			self::build_plugins_transient();
		}
		$reconstructed_plugins = json_decode( $existing_transient, true );

		// add is_active field to the plugin.
		$reconstructed_plugins[ $plugin_slug ]['is_active'] = $is_activation ? true : ( $is_deactivation ? false : is_plugin_active( $original_plugin_slug ) );

		// set the cache for one hour.
		set_transient( 'oneupdate_get_plugins', wp_json_encode( $reconstructed_plugins ), HOUR_IN_SECONDS );
	}

	/**
	 * Special case for hello-dolly plugin.
	 *
	 * @param bool $is_activation Whether the plugin is being activated.
	 * @param bool $is_deactivation Whether the plugin is being deactivated.
	 *
	 * @return void
	 */
	public static function hello_dolly_plugin( bool $is_activation, bool $is_deactivation ): void {
		$original_plugin_slug = 'hello.php';
		$existing_transient   = get_transient( 'oneupdate_get_plugins' );
		if ( ! $existing_transient ) {
			self::build_plugins_transient();
		}
		$reconstructed_plugins = json_decode( $existing_transient, true );

		// add is_active field to the plugin.
		$reconstructed_plugins[ $original_plugin_slug ]['is_active'] = $is_activation ? true : ( $is_deactivation ? false : is_plugin_active( $original_plugin_slug ) );

		// set the cache for one hour.
		set_transient( 'oneupdate_get_plugins', wp_json_encode( $reconstructed_plugins ), HOUR_IN_SECONDS );
	}
}
