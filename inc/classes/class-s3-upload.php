<?php
/**
 * Class S3_Upload - Handles S3 uploads of plugin zip files.
 *
 * @package OneUpdate
 */

namespace OneUpdate;

use OneUpdate\Traits\Singleton;
use Aws\Exception\AwsException;
use OneUpdate\Plugin_Configs\Constants;

/**
 * Class S3_Upload
 */
class S3_Upload {

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
		add_action( 'oneupdate_s3_zip_cleanup_event', array( $this, 'oneupdate_s3_zip_cleanup_event' ) );
	}

	/**
	 * Handle S3 zip cleanup event.
	 *
	 * @return void
	 *
	 * @throws \Exception If there is an error deleting files from S3.
	 */
    // phpcs:disable -- its custom query to cleanup s3 bucket & history table.
	public function oneupdate_s3_zip_cleanup_event(): void {
		$s3_credentials = get_option( Constants::ONEUPDATE_S3_CREDENTIALS, array() );

		global $wpdb;
		$table_name = $wpdb->prefix . Constants::ONEUPDATE_S3_ZIP_HISTORY_TABLE;
		$s3         = Utils::get_s3_instance();

		$one_hour_ago  = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp', 1 ) - 3600 );
		$expired_files = $wpdb->get_results(
			$wpdb->prepare( "SELECT s3_key FROM $table_name WHERE upload_time <= %s", $one_hour_ago )
		);

		foreach ( $expired_files as $file ) {
			try {
				$s3->deleteObject(
					array(
						'Bucket' => $s3_credentials['bucketName'] ?? '',
						'Key'    => $file->s3_key,
					)
				);
			} catch ( AwsException $e ) {
				throw new \Exception(
					'aws_s3_error',
					sprintf( 
						/* translators: %s is the error message from AWS S3 */
						__( 'Error deleting file from S3: %s', 'oneupdate' ), 
						$e->getMessage()
					),
				);
			}
		}
		// delete expired records from the database.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $table_name WHERE upload_time <= %s",
				$one_hour_ago
			)
		);
	}
    // phpcs:enable.-- its custom query to cleanup s3 bucket & history table.
}
