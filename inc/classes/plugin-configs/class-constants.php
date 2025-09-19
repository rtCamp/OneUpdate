<?php
/**
 * Class Constants -- this is to define plugin constants.
 *
 * @package OneUpdate
 */

namespace OneUpdate\Plugin_Configs;

use OneUpdate\Traits\Singleton;

/**
 * Class Constants
 */
class Constants {

	/**
	 * Plugin constant variables.
	 *
	 * @var array $constants
	 */
	public static $constants;

	/**
	 * Child site api key.
	 *
	 * @var string
	 */
	public const ONEUPDATE_API_KEY = 'oneupdate_child_site_api_key';

	/**
	 * Shared sites.
	 *
	 * @var string
	 */
	public const ONEUPDATE_SHARED_SITES = 'oneupdate_shared_sites';

	/**
	 * S3 credentials.
	 *
	 * @var string
	 */
	public const ONEUPDATE_S3_CREDENTIALS = 'oneupdate_s3_credentials';

	/**
	 * Site type.
	 *
	 * @var string
	 */
	public const ONEUPDATE_SITE_TYPE = 'oneupdate_site_type';

	/**
	 * Profile update requests.
	 *
	 * @var string
	 */
	public const ONEUPDATE_PROFILE_UPDATE_REQUESTS = 'oneupdate_profile_update_requests';

	/**
	 * New users.
	 *
	 * @var string
	 */
	public const ONEUPDATE_NEW_USERS = 'oneupdate_new_users';

	/**
	 * Site type transient.
	 *
	 * @var string
	 */
	public const ONEUPDATE_SITE_TYPE_TRANSIENT = 'oneupdate_site_type_transient';

	/**
	 * Plugins options.
	 *
	 * @var string
	 */
	public const ONEUPDATE_PLUGINS_OPTIONS = 'oneupdate_plugins_options';

	/**
	 * Github PAT token.
	 *
	 * @var string
	 */
	public const ONEUPDATE_GH_TOKEN = 'oneupdate_gh_token';

	/**
	 * Active plugins.
	 *
	 * @var string
	 */
	public const ONEUPDATE_ACTIVE_PLUGINS = 'active_plugins';

	/**
	 * S3 zip history table.
	 *
	 * @var string
	 */
	public const ONEUPDATE_S3_ZIP_HISTORY_TABLE = 'oneupdate_s3_zip_history';

	/**
	 * Governing site request origin url.
	 *
	 * @var string
	 */
	public const ONEUPDATE_GOVERNING_SITE_URL = 'oneupdate_governing_site_url';

	/**
	 * Use Singleton trait.
	 */
	use Singleton;

	/**
	 * Protected class constructor
	 */
	protected function __construct() {
		$this->define_constants();
	}

	/**
	 * Define plugin constants
	 */
	private function define_constants(): void {
		// future constants can be defined here.
	}
}
