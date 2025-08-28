<?php
/**
 * This file is to create db tables for storing logs of oneupdate plugin loader.
 *
 * @package OneUpdate
 */

namespace OneUpdate\Plugin_Configs;

use OneUpdate\Traits\Singleton;

/**
 * Class DB
 */
class DB {
	/**
	 * Use Singleton trait.
	 */
	use Singleton;

	/**
	 * Protected class constructor
	 */
	protected function __construct() {
		// Initialize the class and set up hooks.
	}

	/**
	 * Create database tables for storing logs.
	 *
	 * @return void
	 */
	public static function create_oneupdate_s3_zip_history_table(): void {
		global $wpdb;
		$table_name      = $wpdb->prefix . 'oneupdate_s3_zip_history';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        file_name VARCHAR(255) NOT NULL,
        s3_key VARCHAR(255) NOT NULL,
        presigned_url TEXT NOT NULL,
        upload_time DATETIME NOT NULL,
        action VARCHAR(50) NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}
