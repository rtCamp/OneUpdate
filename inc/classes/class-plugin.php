<?php
/**
 * Rudimentary plugin file.
 *
 * @package OneUpdate
 */

namespace OneUpdate;

use OneUpdate\Plugin_Configs\{ DB, Secret_Key, VIP_Plugin_Activation };
use OneUpdate\Traits\Singleton;

/**
 * Main plugin class which initializes the plugin.
 */
class Plugin {

	/**
	 * Use Singleton trait.
	 */
	use Singleton;

	/**
	 * Protected class constructor
	 */
	protected function __construct() {
		$this->load_plugin_classes();
		$this->load_plugin_configs();
		$this->load_taxonomies();
	}

	/**
	 * Load plugin classes
	 */
	public function load_plugin_classes(): void {
		Assets::get_instance();
		Hooks::get_instance();
		Settings::get_instance();
		REST::get_instance();
		Cache::get_instance();
		S3_Upload::get_instance();
	}

	/**
	 * Load plugin configs
	 */
	public function load_plugin_configs(): void {
		DB::get_instance();
		Secret_Key::get_instance();
		VIP_Plugin_Activation::get_instance();
	}

	/**
	 * Load taxonomies
	 */
	public function load_taxonomies(): void {
	}
}
