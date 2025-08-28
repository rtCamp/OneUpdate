<?php
/**
 * Add actions and filters for OneUpdate plugin.
 *
 * @package OneUpdate
 */

namespace OneUpdate;

use OneUpdate\Traits\Singleton;

/**
 * Class Hooks initializes the actions and filters.
 */
class Hooks {


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
		// create global variable called oneupdate_sites which has info like site name, site url, site id, gh_repo, etc.
		add_action( 'init', array( $this, 'create_global_oneupdate_sites' ), -1 );

		// add setup page link to plugins page.
		add_filter( 'plugin_action_links_' . ONEUPDATE_PLUGIN_LOADER_PLUGIN_BASENAME, array( $this, 'add_settings_page_link' ) );

		// add container for modal for site selection on activation.
		add_action( 'admin_footer', array( $this, 'add_site_selection_modal' ) );

		// add body class for site selection modal.
		add_filter( 'admin_body_class', array( $this, 'add_body_class_for_modal' ) );
		add_filter( 'admin_body_class', array( $this, 'add_body_class_for_missing_sites' ) );
	}

	/**
	 * Create global variable oneupdate_sites with site info.
	 *
	 * @param string $classes Existing body classes.
	 *
	 * @return string
	 */
	public function add_body_class_for_modal( $classes ): string {
		$current_screen = get_current_screen();
		if ( ! $current_screen || 'plugins' !== $current_screen->base ) {
			return $classes;
		}

		// get oneupdate_site_type_transient transient to check if site type is set.
		$site_type_transient = get_transient( 'oneupdate_site_type_transient' );
		if ( $site_type_transient ) {
			// If site type is already set, do not show the modal.
			return $classes;
		}

		// add oneupdate-site-selection-modal class to body.
		$classes .= ' oneupdate-site-selection-modal ';
		return $classes;
	}

	/**
	 * Add site selection modal to admin footer.
	 *
	 * @return void
	 */
	public function add_site_selection_modal(): void {
		$current_screen = get_current_screen();
		if ( ! $current_screen || 'plugins' !== $current_screen->base ) {
			return;
		}
		if ( ! defined( 'ONEUPDATE_PLUGIN_LOADER_SLUG' ) ) {
			return;
		}

		// get oneupdate_site_type_transient transient to check if site type is set.
		$site_type_transient = get_transient( 'oneupdate_site_type_transient' );
		if ( $site_type_transient ) {
			// If site type is already set, do not show the modal.
			return;
		}

		?>
		<div class="wrap">
			<div id="oneupdate-site-selection-modal" class="oneupdate-modal"></div>
		</div>
		<?php
	}

	/**
	 * Create global variable oneupdate_sites with site info.
	 *
	 * @return void
	 */
	public function create_global_oneupdate_sites(): void {
		if ( ! defined( 'ONEUPDATE_PLUGIN_LOADER_SLUG' ) ) {
			return;
		}

		$sites = get_option( 'oneupdate_shared_sites', array() );

		if ( ! empty( $sites ) && is_array( $sites ) ) {
			$oneupdate_sites = array();
			foreach ( $sites as $site ) {
				$oneupdate_sites[ $site['siteUrl'] ] = array(
					'siteName'  => $site['siteName'],
					'siteUrl'   => $site['siteUrl'],
					'gh_repo'   => $site['githubRepo'],
					'publicKey' => $site['publicKey'],
				);
			}

			// Set it in GLOBALS.
			$GLOBALS['oneupdate_sites'] = $oneupdate_sites;
		}
	}

	/**
	 * Add settings page link to plugins page.
	 *
	 * @param array $links Existing plugin action links.
	 * @return array Modified plugin action links.
	 */
	public function add_settings_page_link( $links ): array {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=oneupdate-settings' ) ),
			__( 'Settings', 'oneupdate' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Add body class for missing sites.
	 *
	 * @param string $classes Existing body classes.
	 *
	 * @return string
	 */
	public function add_body_class_for_missing_sites( $classes ): string {
		$current_screen = get_current_screen();

		if ( ! $current_screen ) {
			return $classes;
		}

		// get oneupdate_shared_sites option.
		$shared_sites = get_option( 'oneupdate_shared_sites', array() );

		// if shared_sites is empty or not an array, return the classes.
		if ( empty( $shared_sites ) || ! is_array( $shared_sites ) ) {
			$classes .= ' oneupdate-missing-brand-sites ';

			// remove plugin manager submenu.
			remove_submenu_page( 'oneupdate', 'oneupdate' );
			return $classes;
		}

		return $classes;
	}
}
