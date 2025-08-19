<?php
/**
 * Register All OneUpdate related REST API endpoints.
 *
 * @package OneUpdate
 */

namespace OneUpdate;

use OneUpdate\REST\{ Basic_Options, S3, Workflow };
use OneUpdate\Traits\Singleton;

/**
 * Class REST
 */
class REST {

	/**
	 * Use Singleton trait.
	 */
	use Singleton;

	/**
	 * REST constructor.
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
		Basic_Options::get_instance();
		Workflow::get_instance();
		S3::get_instance();

		// fix cors headers for REST API requests.
		add_filter( 'rest_pre_serve_request', array( $this, 'add_cors_headers' ), PHP_INT_MAX - 20, 4 );
	}

	/**
	 * Add CORS headers to REST API responses.
	 *
	 * @param bool $served Whether the request has been served.
	 * @return bool
	 */
	public function add_cors_headers( $served ): bool {
		header( 'Access-Control-Allow-Headers: X-OneUpdate-Plugins-Token, Content-Type, Authorization', false );
		return $served;
	}
}
