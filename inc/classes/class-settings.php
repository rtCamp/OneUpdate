<?php
/**
 * Create OneUpdate settings page.
 *
 * @package OneUpdate
 */

namespace OneUpdate;

use OneUpdate\Settings\Shared_Sites;
use OneUpdate\Traits\Singleton;

/**
 * Class Settings
 */
class Settings {

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
		Shared_Sites::get_instance();
	}
}
