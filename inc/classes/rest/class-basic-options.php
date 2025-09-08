<?php
/**
 * Class Basic_Options which contains basic rest routes for the plugin.
 *
 * @package OneUpdate
 */

namespace OneUpdate\REST;

use OneUpdate\Plugin_Configs\Constants;
use OneUpdate\Traits\Singleton;
use OneUpdate\Plugin_Configs\Secret_Key;
use WP_REST_Server;

/**
 * Class Basic_Options
 */
class Basic_Options {

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
		 * Register a route to get site type and set site type.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/site-type',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_site_type' ),
					'permission_callback' => function () {
						return current_user_can( 'manage_options' );
					},
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'set_site_type' ),
					'permission_callback' => function () {
						return current_user_can( 'manage_options' );
					},
					'args'                => array(
						'site_type' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		/**
		 * Register a route to get all public and private repo from rtCamp and wpcomvip organizations.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/github-repos',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_github_repos' ),
				'permission_callback' => function () {
						return current_user_can( 'manage_options' );
				},
			)
		);

		/**
		 * Register a route to get oneupdate_child_site_api_key option.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/secret-key',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( Secret_Key::class, 'get_secret_key' ),
					'permission_callback' => function () {
						return current_user_can( 'manage_options' );
					},
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( Secret_Key::class, 'regenerate_secret_key' ),
					'permission_callback' => function () {
						return current_user_can( 'manage_options' );
					},
				),
			)
		);

		/**
		 * Register a route to get and set S3 credentials.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/s3-credentials',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_s3_credentials' ),
					'permission_callback' => function () {
						return current_user_can( 'manage_options' );
					},
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'set_s3_credentials' ),
					'permission_callback' => function () {
						return current_user_can( 'manage_options' );
					},
					'args'                => array(
						's3_credentials' => array(
							'required'          => true,
							'type'              => 'array',
							'sanitize_callback' => function ( $value ) {
								return is_array( $value );
							},
						),
					),
				),
			)
		);
		/**
		 * Register a route which will store array of sites data like site name, site url, its GitHub repo and API key.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/shared-sites',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_shared_sites' ),
					'permission_callback' => function () {
						return current_user_can( 'manage_options' );
					},
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'set_shared_sites' ),
					'permission_callback' => function () {
						return current_user_can( 'manage_options' );
					},
					'args'                => array(
						'sites_data' => array(
							'required'          => true,
							'type'              => 'array',
							'sanitize_callback' => function ( $value ) {
								return is_array( $value );
							},
						),
					),
				),
			)
		);

		/**
		 * Register a route to get and set github repo token.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/github-token',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_github_token' ),
					'permission_callback' => function () {
						return current_user_can( 'manage_options' );
					},
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'set_github_token' ),
					'permission_callback' => function () {
						return current_user_can( 'manage_options' );
					},
					'args'                => array(
						'token' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			),
		);

		/**
		 * Register a route for health-check.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/health-check',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'health_check' ),
				'permission_callback' => 'oneupdate_validate_api_key',
			)
		);
	}

	/**
	 * Health check endpoint.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function health_check(): \WP_REST_Response|\WP_Error {
		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Health check passed successfully.', 'oneupdate' ),
			)
		);
	}

	/**
	 * Get all public and private GitHub repositories from rtCamp and wpcomvip organizations.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_github_repos(): \WP_REST_Response|\WP_Error {

		$github_token = get_option( Constants::ONEUPDATE_GH_TOKEN, '' );

		if ( empty( $github_token ) ) {
			return new \WP_Error( 'no_github_token', __( 'GitHub token not found.', 'oneupdate' ), array( 'status' => 404 ) );
		}

		// Loop to fetch all GitHub repos using pagination.
		$all_repos = array();
		$page      = 1;
		$per_page  = 100;

		do {
			$fetch_url = "https://api.github.com/user/repos?affiliation=owner,organization,collaborator&per_page={$per_page}&page={$page}";

			$response = wp_safe_remote_get(
				$fetch_url,
				array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $github_token,
						'Accept'        => 'application/vnd.github.v3+json',
						'User-Agent'    => 'OneUpdate Plugin Loader',
					),
					'timeout' => 30, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout -- this is to avoid timeout issues.
				)
			);

			if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
				return new \WP_Error(
					'github_api_error',
					__( 'Failed to fetch GitHub repositories.', 'oneupdate' ),
					array(
						'status' => 500,
						'error'  => is_wp_error( $response ) ? $response->get_error_message() : wp_remote_retrieve_response_code( $response ),
					)
				);
			}

			$repos = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( empty( $repos ) || ! is_array( $repos ) ) {
				break;
			}

			$all_repos = array_merge( $all_repos, $repos );

			++$page;

			$repos_count = count( $repos );
		} while ( $repos_count === $per_page );

		// Filter for specific organizations.
		$filtered_repos = array();
		foreach ( $all_repos as $repo ) {
				$filtered_repos[] = array(
					'slug' => $repo['full_name'],
					'name' => $repo['name'],
					'url'  => $repo['html_url'],
				);
		}

		if ( empty( $filtered_repos ) ) {
			return new \WP_Error( 'no_filtered_repos', __( 'No repositories found for rtCamp or wpcomvip.', 'oneupdate' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'repos'   => $filtered_repos,
				'count'   => count( $filtered_repos ),
			)
		);
	}

	/**
	 * Get the site type.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_site_type(): \WP_REST_Response|\WP_Error {

		$site_type = get_option( Constants::ONEUPDATE_SITE_TYPE, '' );

		return rest_ensure_response(
			array(
				'site_type' => $site_type,
			)
		);
	}

	/**
	 * Set the site type.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function set_site_type( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {

		$site_type = sanitize_text_field( $request->get_param( 'site_type' ) );

		update_option( Constants::ONEUPDATE_SITE_TYPE, $site_type );

		// set transient to indicating that site type has been set for infinite time.
		set_transient( Constants::ONEUPDATE_SITE_TYPE_TRANSIENT, true, 0 );

		return rest_ensure_response(
			array(
				'site_type' => $site_type,
			)
		);
	}

	/**
	 * Set the GitHub token.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function set_github_token( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {

		$github_token = sanitize_text_field( $request->get_param( 'token' ) );

		if ( empty( $github_token ) ) {
			return new \WP_Error( 'invalid_github_token', __( 'GitHub token is required.', 'oneupdate' ), array( 'status' => 400 ) );
		}

		// check if the token is valid.
		$response = wp_safe_remote_get(
			'https://api.github.com/user',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $github_token,
					'Accept'        => 'application/vnd.github.v3+json',
					'User-Agent'    => 'OneUpdate Plugin Loader',
				),
				'timeout' => 30, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout -- this is to avoid timeout issues.
			)
		);

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return new \WP_Error(
				'invalid_github_token',
				__( 'Invalid GitHub token provided.', 'oneupdate' ),
				array(
					'status' => 400,
					'error'  => is_wp_error( $response ) ? $response->get_error_message() : wp_remote_retrieve_response_code( $response ),
				)
			);
		}

		update_option( Constants::ONEUPDATE_GH_TOKEN, $github_token );

		return rest_ensure_response(
			array(
				'success'      => true,
				'github_token' => $github_token,
			)
		);
	}

	/**
	 * Get S3 credentials.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_s3_credentials(): \WP_REST_Response|\WP_Error {
		$s3_credentials = get_option( Constants::ONEUPDATE_S3_CREDENTIALS, array() );

		return rest_ensure_response(
			array(
				'success'        => true,
				's3_credentials' => $s3_credentials,
			)
		);
	}

	/**
	 * Set S3 credentials.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function set_s3_credentials( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {

		$body           = $request->get_body();
		$decoded_body   = json_decode( $body, true );
		$s3_credentials = $decoded_body['s3_credentials'] ?? array();
		if ( ! is_array( $s3_credentials ) ) {
			return new \WP_Error( 'invalid_s3_credentials', __( 'Invalid S3 credentials provided.', 'oneupdate' ), array( 'status' => 400 ) );
		}

		// Validate S3 credentials.
		$required_keys = array( 'accessKey', 'bucketName', 'endpoint', 'region', 'secretKey' );
		foreach ( $required_keys as $key ) {
			if ( ! isset( $s3_credentials[ $key ] ) || empty( $s3_credentials[ $key ] ) ) {
				return new \WP_Error( 'invalid_s3_credentials', __( 'Invalid S3 credentials provided.', 'oneupdate' ), array( 'status' => 400 ) );
			}
		}

		// Update S3 credentials in options.
		update_option( Constants::ONEUPDATE_S3_CREDENTIALS, $s3_credentials );

		return rest_ensure_response(
			array(
				'success'        => true,
				's3_credentials' => $s3_credentials,
			)
		);
	}

	/**
	 * Get shared sites data.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_shared_sites(): \WP_REST_Response|\WP_Error {
		$shared_sites = get_option( Constants::ONEUPDATE_SHARED_SITES, array() );
		return rest_ensure_response(
			array(
				'success'      => true,
				'shared_sites' => $shared_sites,
			)
		);
	}

	/**
	 * Set shared sites data.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function set_shared_sites( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {

		$body         = $request->get_body();
		$decoded_body = json_decode( $body, true );
		$sites_data   = $decoded_body['sites_data'] ?? array();

		// check if same url exists more than once or not.
		$urls = array();
		foreach ( $sites_data as $site ) {
			if ( isset( $site['siteUrl'] ) && in_array( $site['siteUrl'], $urls, true ) ) {
				return new \WP_Error( 'duplicate_site_url', __( 'Brand Site already exists.', 'oneupdate' ), array( 'status' => 400 ) );
			}
			$urls[] = $site['siteUrl'] ?? '';
		}

		// check if same github repo exists more than once or not.
		$gtihub_repos = array();
		foreach ( $sites_data as $site ) {
			if ( isset( $site['githubRepo'] ) && in_array( $site['githubRepo'], $gtihub_repos, true ) ) {
				return new \WP_Error( 'duplicate_github_repo', __( 'GitHub repository already exists in one of Brand sites.', 'oneupdate' ), array( 'status' => 400 ) );
			}
			$gtihub_repos[] = $site['githubRepo'] ?? '';
		}

		update_option( Constants::ONEUPDATE_SHARED_SITES, $sites_data );

		return rest_ensure_response(
			array(
				'success'    => true,
				'sites_data' => $sites_data,
			)
		);
	}

	/**
	 * Get the GitHub token.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_github_token(): \WP_REST_Response|\WP_Error {
		$github_token = get_option( Constants::ONEUPDATE_GH_TOKEN, '' );

		return rest_ensure_response(
			array(
				'success'      => true,
				'github_token' => $github_token,
			)
		);
	}
}
