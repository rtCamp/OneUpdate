<?php
/**
 * Class S3 - this class has api endpoints for S3 uploads.
 *
 * @package OneUpdate
 */

namespace OneUpdate\REST;

use OneUpdate\Traits\Singleton;
use WP_REST_Server;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;

/**
 * Class S3
 */
class S3 {


	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	private const NAMESPACE = 'oneupdate/v1';

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
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes(): void {
		/**
		 * Register REST route for s3 upload.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/upload',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_s3_upload' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		/**
		 * Register REST route for s3 upload history.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/history',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_s3_upload_history' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		/**
		 * Register Route to perform S3 bucket health check.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/s3-health-check',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 's3_health_check' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	/**
	 * Perform S3 bucket health check.
	 *
	 * @return \WP_REST_Response| \WP_Error
	 */
	public function s3_health_check(): \WP_REST_Response|\WP_Error {
		$s3_credentials = get_option( 'oneupdate_s3_credentials' );
		if ( empty( $s3_credentials ) || ! is_array( $s3_credentials ) ) {
			return new \WP_REST_Response( array( 'message' => 'S3 credentials not set' ), 400 );
		}
		$s3 = self::get_s3_instance();
		try {
			// Attempt to list buckets to check connectivity.
			$result = $s3->listBuckets();
			if ( ! empty( $result['Buckets'] ) ) {
				return new \WP_REST_Response(
					array(
						'message' => 'S3 bucket is accessible',
						'buckets' => $result['Buckets'],
						'status'  => 'success',
					),
					200
				);
			} else {
				return new \WP_REST_Response(
					array(
						'message'        => 'No buckets found',
						'result'         => $result,
						's3'             => $s3,
						's3_credentials' => $s3_credentials,
					),
					404
				);
			}
		} catch ( AwsException $e ) {
			return new \WP_REST_Response( array( 'message' => 'S3 health check failed: ' . $e->getMessage() ), 500 );
		}
	}

	/**
	 * Handle S3 upload.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response| \WP_Error
	 */
	public function handle_s3_upload( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$files = $request->get_file_params();
		if ( empty( $files['file'] ) ) {
			return new \WP_REST_Response( array( 'message' => 'No file uploaded' ), 400 );
		}

		$file = $files['file'];
		if ( pathinfo( $file['name'], PATHINFO_EXTENSION ) !== 'zip' ) {
			return new \WP_REST_Response( array( 'message' => 'Only ZIP files are allowed' ), 400 );
		}
		$s3_credentials = get_option( 'oneupdate_s3_credentials' );
		$s3             = self::get_s3_instance();

		$s3_key = 'Uploads/' . uniqid() . '_' . basename( $file['name'] );
		try {
			$result = $s3->putObject(
				array(
					'Bucket'     => $s3_credentials['bucketName'] ?? '',
					'Key'        => $s3_key,
					'SourceFile' => $file['tmp_name'],
					'ACL'        => 'private',
				)
			);

			$presigned_url = $s3->createPresignedRequest(
				$s3->getCommand(
					'GetObject',
					array(
						'Bucket' => $s3_credentials['bucketName'] ?? '',
						'Key'    => $s3_key,
					)
				),
				'+1 hour'
			)->getUri()->__toString();

			global $wpdb;
			$table_name    = $wpdb->prefix . 'oneupdate_s3_zip_history';
			$insert_result = $wpdb->insert( // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- inserting private plugin data.
				$table_name,
				array(
					'file_name'     => $file['name'],
					's3_key'        => $s3_key,
					'presigned_url' => $presigned_url,
					'upload_time'   => current_time( 'mysql' ),
					'action'        => 'Uploaded',
				)
			);

			if ( false === $insert_result ) {
				return new \WP_REST_Response( array( 'message' => 'File uploaded to S3 but failed to save history: ' . $wpdb->last_error ), 500 );
			}

			return new \WP_REST_Response(
				array(
					'message'       => 'File uploaded successfully',
					'presigned_url' => $presigned_url,
				),
				200
			);
		} catch ( AwsException $e ) {
			return new \WP_REST_Response( array( 'message' => 'Upload failed: ' . $e->getMessage() ), 500 );
		}
	}

	/**
	 * Get S3 upload history.
	 *
	 * @return \WP_REST_Response| \WP_Error
	 */
	public function get_s3_upload_history(): \WP_REST_Response|\WP_Error {
		global $wpdb;
		$table_name = $wpdb->prefix . 'oneupdate_s3_zip_history';
		$query      = "SELECT * FROM $table_name ORDER BY upload_time DESC";
		$results    = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching -- Static query with no variables, caching not suitable for dynamic upload history.

		return new \WP_REST_Response( $results ? $results : array(), 200 );
	}

	/**
	 * Get S3 instance.
	 *
	 * @return S3Client
	 */
	public static function get_s3_instance(): S3Client {
		$s3_credentials = get_option( 'oneupdate_s3_credentials' );
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
}
