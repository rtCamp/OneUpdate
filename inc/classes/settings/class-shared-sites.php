<?php
/**
 * This file is to create admin page.
 *
 * @package OneUpdate
 */

namespace OneUpdate\Settings;

use OneUpdate\Traits\Singleton;

/**
 * Class Shared_Sites
 */
class Shared_Sites {

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
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
	}

	/**
	 * Add admin menu under media
	 *
	 * @return void
	 */
	public function add_admin_menu(): void {

		add_menu_page(
			__( 'Plugin Manager', 'oneupdate' ),
			__( 'OneUpdate', 'oneupdate' ),
			'manage_options',
			'oneupdate',
			'__return_null',
			'',
			2
		);

		// Add sub menu under forms inspector - this will rename the first submenu item.
		add_submenu_page(
			'oneupdate',
			__( 'Plugin Manager', 'oneupdate' ),
			'<span class="oneupdate-plugin-manager-page">' . __( 'Plugin Manager', 'oneupdate' ) . '</span>',
			'manage_options',
			'oneupdate',
			array( $this, 'render_oneupdate_plugin_manager' )
		);

		// Add your other submenu page.
		add_submenu_page(
			'oneupdate',
			__( 'Settings', 'oneupdate' ),
			__( 'Settings', 'oneupdate' ),
			'manage_options',
			'oneupdate-settings',
			array( $this, 'render_oneupdate_settings_page' )
		);

		// if site type is brand then remove the governing site menu.
		if ( 'governing-site' !== get_option( 'oneupdate_site_type', '' ) ) {
			remove_submenu_page( 'oneupdate', 'oneupdate' );
		}
	}

	/**
	 * Render admin page
	 *
	 * @return void
	 */
	public function render_oneupdate_plugin_manager(): void {
		// Check if the user has permission to manage options.
		if ( ! current_user_can( 'manage_options' ) || 'governing-site' !== get_option( 'oneupdate_site_type', '' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'oneupdate' ) );
		}
		?>
		<div class="wrap">
			<h1 class="oneupdate-heading"><?php esc_html_e( 'OneUpdate - Plugin Manager', 'oneupdate' ); ?></h1>
			<div id="oneupdate-plugin-manager"></div>
		</div>
		<?php
	}

	/**
	 * Render admin page
	 *
	 * @return void
	 */
	public function render_oneupdate_settings_page(): void {
		// Check if the user has permission to manage options.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'oneupdate' ) );
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Settings', 'oneupdate' ); ?></h1>
			<div id="oneupdate-settings-page"></div>
		</div>
		<?php
	}
}
