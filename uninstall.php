<?php
/**
 * This will be executed when the plugin is uninstalled.
 *
 * @package OneUpdate
 */

use OneUpdate\Plugin_Configs\DB;

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'oneupdate_plugin_sync_deactivate' ) ) {

	/**
	 * Function to deactivate the plugin and clean up options.
	 */
	function oneupdate_plugin_sync_deactivate() {
		// Remove the site type option.
		delete_option( 'oneupdate_site_type' );
		// Remove the S3 credentials option.
		delete_option( 'oneupdate_s3_credentials' );
		// Remove the shared sites option.
		delete_option( 'oneupdate_shared_sites' );
		// Remove github token option.
		delete_option( 'oneupdate_gh_token' );
		// Remove oneupdate_site_type_transient transient.
		delete_transient( 'oneupdate_site_type_transient' );
		// Remove oneupdate_get_plugins transient.
		delete_transient( 'oneupdate_get_plugins' );
		// Remove oneupdate_child_site_api_key option.
		delete_option( 'oneupdate_child_site_api_key' );

		// remove oneupdate_s3_zip_history table.
		DB::remove_oneupdate_s3_zip_history_table();
	}
}
/**
 * Uninstall the plugin and clean up options.
 */
oneupdate_plugin_sync_deactivate();
