<?php
/**
 * Class S3_Upload - Handles S3 uploads of plugin zip files.
 *
 * @package OneUpdate
 */

namespace OneUpdate;

use OneUpdate\Traits\Singleton;
use Aws\Exception\AwsException;

use OneUpdate\REST\S3;

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
		add_action( 'oneupdate_s3_zip_history_cleanup_event', array( $this, 'oneupdate_s3_zip_history_cleanup_event' ) );
	}

	/**
	 * Handle S3 zip cleanup event.
	 *
	 * @return void
	 *
	 * @throws \Exception If there is an error deleting files from S3.
	 */
    // phpcs:disable -- its custom query to cleanup s3 bucket.
	public function oneupdate_s3_zip_cleanup_event(): void {
		$s3_credentials = get_option( 'oneupdate_s3_credentials' );

		global $wpdb;
		$table_name = $wpdb->prefix . 'oneupdate_s3_zip_history';
		$s3         = S3::get_s3_instance();

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
    // phpcs:enable.

	/**
	 * Handle S3 zip history cleanup event.
	 *
	 * @return void
	 *
	 * @throws \Exception If there is an error deleting files from S3.
	 */
    // phpcs:disable -- its custom query to cleanup s3 zip history.
    public function oneupdate_s3_zip_history_cleanup_event(): void {
		global $wpdb;
		$table_name    = $wpdb->prefix . 'oneupdate_s3_zip_history';
		$one_week_ago  = date( 'Y-m-d H:i:s', strtotime( '-1 week' ) );
		$batch_size    = 1000;
		$sleep_seconds = 2;

		// Count total records to delete.
		$total_records = $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM $table_name WHERE upload_time <= %s", $one_week_ago )
		);

		if ( $total_records > 0 ) {
			$offset = 0;
			while ( $offset < $total_records ) {
				$query_template = sprintf(
					'DELETE FROM `%s` WHERE upload_time <= %%s LIMIT %%d',
					esc_sql( $table_name )
				);

				$wpdb->query(
					$wpdb->prepare(
						$query_template,
						$one_week_ago,
						$batch_size
					)
				);
				$offset += $batch_size;
				if ( $offset < $total_records ) {
					sleep( $sleep_seconds );
				}
			}
		}
	}
    // phpcs:enable.
}
