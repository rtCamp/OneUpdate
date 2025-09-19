<?php
/**
 * Class VIP_Plugin_Activation - this class handles the activation on VIP platforms.
 *
 * @package OneUpdate
 */

namespace OneUpdate\Plugin_Configs;

use OneUpdate\Traits\Singleton;

/**
 * Class VIP_Plugin_Activation
 */
class VIP_Plugin_Activation {

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
		$this->activate_vip_plugins();
	}

	/**
	 * Activate plugins on VIP platforms.
	 *
	 * @return void
	 */
	public function activate_vip_plugins(): void {

		if ( ! function_exists( 'wpcom_vip_load_plugin' ) ) {
			return; // Ensure the function exists before proceeding.
		}

		// get oneupdate_plugins_options option.
		$oneupdate_plugins_options = get_option( Constants::ONEUPDATE_PLUGINS_OPTIONS, array() );

		// activate all plugins that are in the oneupdate_plugins_options.
		if ( ! empty( $oneupdate_plugins_options ) ) {
			foreach ( $oneupdate_plugins_options as $plugin_data ) {
				// check given directory exists into plugins folder.
				if ( ! is_string( $plugin_data ) || 'hello.php' === $plugin_data || ! file_exists( WP_PLUGIN_DIR . '/' . $plugin_data ) ) {
					continue; // Skip invalid plugin data.
				}
				wpcom_vip_load_plugin( $plugin_data );
			}
		}
	}
}
