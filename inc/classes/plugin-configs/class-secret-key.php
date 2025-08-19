<?php
/**
 * Create a secret key for OneUpdate site communication.
 *
 * @package OneUpdate
 */

namespace OneUpdate\Plugin_Configs;

use OneUpdate\Traits\Singleton;

/**
 * Class Secret_Key
 */
class Secret_Key {
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
		add_action( 'admin_init', array( $this, 'generate_secret_key' ) );
	}

	/**
	 * Generate a secret key for the site.
	 *
	 * @return void
	 */
	public function generate_secret_key(): void {
		$secret_key = get_option( 'oneupdate_child_site_public_key' );
		if ( empty( $secret_key ) ) {
			$secret_key = wp_generate_password( 128, false, false );
			// Store the secret key in the database.
			update_option( 'oneupdate_child_site_public_key', $secret_key );
		}
	}

	/**
	 * Get the secret key.
	 *
	 * @return \WP_REST_Response| \WP_Error
	 */
	public static function get_secret_key(): \WP_REST_Response|\WP_Error {
		$secret_key = get_option( 'oneupdate_child_site_public_key' );
		if ( empty( $secret_key ) ) {
			self::regenerate_secret_key();
			$secret_key = get_option( 'oneupdate_child_site_public_key' );
		}
		return rest_ensure_response(
			array(
				'secret_key' => $secret_key,
			)
		);
	}

	/**
	 * Regenerate the secret key.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function regenerate_secret_key(): \WP_REST_Response|\WP_Error {
		$regenerated_key = wp_generate_password( 128, false, false );
		// Update the option with the new key.
		update_option( 'oneupdate_child_site_public_key', $regenerated_key );

		return rest_ensure_response(
			array(
				'message'    => __( 'Secret key regenerated successfully.', 'oneupdate' ),
				'secret_key' => $regenerated_key,
			)
		);
	}
}
