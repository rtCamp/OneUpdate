<?php
/**
 * Utility functions for OneUpdate plugin.
 *
 * @package OneUpdate
 */

namespace OneUpdate;

use OneUpdate\Plugin_Configs\Constants;
use OneUpdate\Traits\Singleton;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;

/**
 * Class Utils
 */
class Utils {

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
	}

	/**
	 * Get current site type.
	 *
	 * @return string
	 */
	public static function get_site_type(): string {
		$site_type = get_option( Constants::ONEUPDATE_SITE_TYPE, '' );
		return $site_type;
	}

	/**
	 * Is brand site.
	 *
	 * @return bool
	 */
	public static function is_brand_site(): bool {
		return 'brand-site' === self::get_site_type();
	}

	/**
	 * Is governing site.
	 *
	 * @return bool
	 */
	public static function is_governing_site(): bool {
		return 'governing-site' === self::get_site_type();
	}

	/**
	 * Get S3 instance.
	 *
	 * @return S3Client
	 */
	public static function get_s3_instance(): S3Client {
		$s3_credentials = get_option( Constants::ONEUPDATE_S3_CREDENTIALS, array() );
		if ( empty( $s3_credentials ) || ! is_array( $s3_credentials ) ) {
			return new S3Client( array() ); // Return an empty S3Client.
		}
		$s3 = new S3Client(
			array(
				'version'                 => 'latest',
				'region'                  => $s3_credentials['region'] ?? '',
				'credentials'             => array(
					'key'    => $s3_credentials['accessKey'] ?? '',
					'secret' => $s3_credentials['secretKey'] ?? '',
				),
				'use_accelerate_endpoint' => true,
			)
		);

		// first check if the bucket has getBucketAccelerateConfiguration.

		try {
			$accelerate_config = $s3->getBucketAccelerateConfiguration(
				array(
					'Bucket' => $s3_credentials['bucketName'] ?? '',
				)
			);
			if ( ! empty( $accelerate_config['Status'] ) && 'Enabled' === $accelerate_config['Status'] ) {
				return $s3;
			}
		} catch ( AwsException $e ) {
			$s3 = new S3Client(
				array(
					'version'                 => 'latest',
					'region'                  => $s3_credentials['region'] ?? '',
					'endpoint'                => $s3_credentials['endpoint'] ?? '',
					'credentials'             => array(
						'key'    => $s3_credentials['accessKey'] ?? '',
						'secret' => $s3_credentials['secretKey'] ?? '',
					),
					'use_path_style_endpoint' => true, // use path style endpoint.
				)
			);
		}

		return $s3;
	}

	/**
	 * Get GitHub token.
	 *
	 * @return string
	 */
	public static function get_gh_token(): string {
		return get_option( Constants::ONEUPDATE_GH_TOKEN, '' );
	}

	/**
	 * Add query args to a URL.
	 *
	 * @param string $url The base URL.
	 * @param array  $args The query args to add.
	 *
	 * @return string The URL with the added query args.
	 */
	public static function add_query_args( string $url, array $args ): string {
		return add_query_arg( $args, $url );
	}
}
