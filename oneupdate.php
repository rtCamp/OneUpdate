<?php
/**
 * Plugin Name: OneUpdate
 * Plugin URI: https://github.com/rtCamp/OneUpdate/
 * Version: 1.0.0
 * Description: OneUpdate - Enterprise WordPress Plugin Manager Automate plugin updates across multiple WordPress sites with CI/CD integration. Creates pull requests for seamless development-to-production workflows.
 * Author: Utsav Patel, rtCamp
 * Author URI: https://rtcamp.com
 * Text Domain: oneupdate
 * Domain Path: /languages
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * Tested up to: 6.8
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package OneUpdate
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ONEUPDATE_PLUGIN_LOADER_VERSION', '1.0.0' );
define( 'ONEUPDATE_PLUGIN_LOADER_FEATURES_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'ONEUPDATE_PLUGIN_LOADER_RELATIVE_PATH', dirname( plugin_basename( __FILE__ ) ) );
define( 'ONEUPDATE_PLUGIN_LOADER_FEATURES_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );
define( 'ONEUPDATE_PLUGIN_LOADER_BUILD_PATH', ONEUPDATE_PLUGIN_LOADER_FEATURES_PATH . '/assets/build' );
define( 'ONEUPDATE_PLUGIN_LOADER_SRC_PATH', ONEUPDATE_PLUGIN_LOADER_FEATURES_PATH . '/assets/src' );
define( 'ONEUPDATE_PLUGIN_LOADER_BUILD_URI', untrailingslashit( plugin_dir_url( __FILE__ ) ) . '/assets/build' );
define( 'ONEUPDATE_PLUGIN_LOADER_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'ONEUPDATE_PLUGIN_LOADER_SLUG', 'oneupdate' );


// if autoload file does not exist then show notice that you are running the plugin from github repo so you need to build assets and install composer dependencies.
if ( ! file_exists( ONEUPDATE_PLUGIN_LOADER_FEATURES_PATH . '/vendor/autoload.php' ) ) {
	add_action(
		'admin_notices',
		function () {
			?>
		<div class="notice notice-error">
			<p>
				<?php
				printf(
					/* translators: %s is the plugin name. */
					esc_html__( 'You are running the %s plugin from the GitHub repository. Please build the assets and install composer dependencies to use the plugin.', 'oneupdate' ),
					'<strong>' . esc_html__( 'OneUpdate', 'oneupdate' ) . '</strong>'
				);
				?>
			</p>
			<p>
				<?php
				printf(
					/* translators: %s is the command to run. */
					esc_html__( 'Run the following commands in the plugin directory: %s', 'oneupdate' ),
					'<code>composer install && npm install && npm run build:prod</code>'
				);
				?>
			<p>
				<?php
				printf(
					/* translators: %s is the plugin name. */
					esc_html__( 'Please refer to the %s for more information.', 'oneupdate' ),
					sprintf(
						'<a href="%s" target="_blank">%s</a>',
						esc_url( 'https://github.com/rtCamp/OneUpdate' ),
						esc_html__( 'OneUpdate GitHub repository', 'oneupdate' )
					)
				);
				?>
			</p>
		</div>
			<?php
		}
	);
	return;
}

// phpcs:disable WordPressVIPMinimum.Files.IncludingFile.UsingCustomConstant
if ( file_exists( ONEUPDATE_PLUGIN_LOADER_FEATURES_PATH . '/vendor/autoload.php' ) ) {
	require_once ONEUPDATE_PLUGIN_LOADER_FEATURES_PATH . '/vendor/autoload.php';
}
// phpcs:enable WordPressVIPMinimum.Files.IncludingFile.UsingCustomConstant

/**
 * Load the plugin.
 */
function oneupdate_plugin_loader() {
	\OneUpdate\Plugin::get_instance();
}

add_action( 'plugins_loaded', 'oneupdate_plugin_loader' );

use OneUpdate\Plugin_Configs\DB;

/**
 * Create custom database table on plugin activation and schedule cron jobs.
 */
register_activation_hook(
	ONEUPDATE_PLUGIN_LOADER_PLUGIN_BASENAME,
	function () {

		// create database tables.
		DB::create_oneupdate_s3_zip_history_table();

		// Schedule cron jobs - clear any existing schedules first.
		wp_clear_scheduled_hook( 'oneupdate_s3_zip_cleanup_event' );

		// Schedule cron jobs.
		if ( ! wp_next_scheduled( 'oneupdate_s3_zip_cleanup_event' ) ) {
			wp_schedule_event( time(), 'hourly', 'oneupdate_s3_zip_cleanup_event' );
		}
	}
);

/**
 * Deactivate the plugin and clean up options.
 */
register_deactivation_hook(
	ONEUPDATE_PLUGIN_LOADER_PLUGIN_BASENAME,
	function () {
		wp_clear_scheduled_hook( 'oneupdate_s3_zip_cleanup_event' );

		// remove oneupdate_s3_zip_history_cleanup_event event even though its removed but to make sure its completely removed from cron jobs.
		wp_clear_scheduled_hook( 'oneupdate_s3_zip_history_cleanup_event' );
	}
);
