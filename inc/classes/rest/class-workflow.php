<?php
/**
 * Class Workflow - this contains the REST API endpoints for managing GitHub workflows.
 *
 * @package OneUpdate
 */

namespace OneUpdate\REST;

use OneUpdate\Traits\Singleton;
use OneUpdate\Cache;

/**
 * Class Workflow
 */
class Workflow {

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
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes(): void {
		/**
		 * Register a route to apply plugins to sites by creating PR's to privided github repo's.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/apply-plugins',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'apply_plugins_to_selected_sites' ),
				'permission_callback' => function () {
						return current_user_can( 'manage_options' );
				},
				'args'                => array(
					'sites'   => array(
						'required'          => true,
						'type'              => 'array',
						'sanitize_callback' => function ( $value ) {
							return is_array( $value );
						},
					),
					'plugins' => array(
						'required'          => true,
						'type'              => 'array',
						'sanitize_callback' => function ( $value ) {
							return is_array( $value );
						},
					),
				),
			)
		);

		/**
		 * Register a route to get all plugins.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/get_plugins',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_plugins' ),
				'permission_callback' => 'oneupdate_validate_api_key',
			)
		);

		/**
		 * Register a route to update options for oneupdate managed plugins.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/oneupdate-plugins-options',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_oneupdate_plugins_options' ),
					'permission_callback' => 'oneupdate_validate_api_key',
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_oneupdate_plugins_options' ),
					'permission_callback' => 'oneupdate_validate_api_key',
					'args'                => array(
						'options' => array(
							'required'          => true,
							'type'              => 'array',
							'sanitize_callback' => function ( $value ) {
								return is_array( $value );
							},
						),
					),
				),
			),
		);
		/**
		 * Register a route to apply private plugins to selected sites.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/apply-private-plugins',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'apply_private_plugins_to_selected_sites' ),
				'permission_callback' => function () {
						return current_user_can( 'manage_options' );
				},
				'args'                => array(
					'sites'   => array(
						'required'          => true,
						'type'              => 'array',
						'sanitize_callback' => function ( $value ) {
							return is_array( $value );
						},
					),
					'plugins' => array(
						'required'          => true,
						'type'              => 'array',
						'sanitize_callback' => function ( $value ) {
							return is_array( $value );
						},
					),
				),
			)
		);

		/**
		 * Register a route for plugin action execution.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/execute-plugin-action',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'execute_plugin_action' ),
				'permission_callback' => function () {
						return current_user_can( 'manage_options' );
				},
				'args'                => array(
					'action'           => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'slug'             => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'plugin_version'   => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'sites'            => array(
						'required'          => true,
						'type'              => 'array',
						'sanitize_callback' => function ( $value ) {
							return is_array( $value );
						},
					),
					'plugin_type'      => array(
						'required'          => false,
						'type'              => 'string',
						'default'           => 'public',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'plugin_path_info' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		/**
		 * Register a route for bulk plugin update.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/bulk-plugin-update',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'bulk_plugin_update' ),
				'permission_callback' => function () {
						return current_user_can( 'manage_options' );
				},
				'args'                => array(
					'plugins' => array(
						'required'          => true,
						'type'              => 'array',
						'sanitize_callback' => function ( $value ) {
							return is_array( $value );
						},
					),
				),
			)
		);

		/**
		 * Register a route for WebHook to trigger transient rebuild.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/webhook/rebuild-transient',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'webhook_rebuild_transient' ),
					'permission_callback' => array( $this, 'webhook_permission_callback' ),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'webhook_rebuild_transient' ),
					'args'                => array(
						'secret' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
					'permission_callback' => array( $this, 'webhook_permission_callback' ),
				),
			)
		);
	}

	/**
	 * Webhook permission callback.
	 *
	 * @return bool
	 */
	public function webhook_permission_callback(): bool {
		$secret       = isset( $_GET['secret'] ) ? sanitize_text_field( wp_unslash( $_GET['secret'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- no need for nonce as its called from webhook like vip or github.
		$valid_secret = get_option( 'oneupdate_child_site_public_key', 'default_public_key' );
		return hash_equals( $secret, $valid_secret );
	}

	/**
	 * Webhook to trigger transient rebuild.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function webhook_rebuild_transient(): \WP_REST_Response|\WP_Error {

		// Clear the transient.
		delete_transient( 'oneupdate_get_plugins' );

		// Rebuild the transient.
		Cache::build_plugins_transient();

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Transient rebuilt successfully.', 'oneupdate' ),
			)
		);
	}

	/**
	 * Bulk plugin update.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function bulk_plugin_update( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$body         = $request->get_body();
		$decoded_body = json_decode( $body, true );
		$plugins      = $decoded_body['plugins'] ?? array();

		if ( ! is_array( $plugins ) || empty( $plugins ) ) {
			return new \WP_Error( 'invalid_plugins', __( 'Invalid plugins provided.', 'oneupdate' ), array( 'status' => 400 ) );
		}

		$response = array();

		foreach ( $plugins as $plugin ) {
			$sites = $plugin['sites'] ?? array();
			foreach ( $sites as $site ) {
				if ( ! is_string( $site ) || empty( $site ) ) {
					return new \WP_Error( 'invalid_site', __( 'Invalid site provided.', 'oneupdate' ), array( 'status' => 400 ) );
				} else {
					$oneupdate_sites = $GLOBALS['oneupdate_sites'] ?? array();
					$plugin_slug     = $plugin['slug'] ?? '';
					$plugin_version  = $plugin['version'] ?? '';
					$github_repo     = $oneupdate_sites[ $site ]['gh_repo'] ?? '';
					$plugin_type     = $plugin['plugin_type'] ?? 'public';

					if ( ! empty( $github_repo ) && ! empty( $plugin_slug ) && ! empty( $plugin_version ) && 'public' === $plugin_type ) {
						// Trigger GitHub action to update the plugin.
						$github_response             = $this->trigger_github_action_for_pr_creation(
							$github_repo,
							'production',
							$plugin_slug,
							$plugin_version,
							'add_update',
							$oneupdate_sites[ $site ]['siteName'] ?? ''
						);
						$github_response['siteName'] = $GLOBALS['oneupdate_sites'][ $site ]['siteName'] ?? $site;
						$response[]                  = array(
							'github_response' => $github_response,
						);
					}
				}
			}
		}

		return rest_ensure_response(
			array(
				'success'  => true,
				'message'  => __( 'Bulk plugin update initiated successfully.', 'oneupdate' ),
				'response' => $response,
			)
		);
	}

	/**
	 * Execute plugin action.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function execute_plugin_action( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$body             = $request->get_body();
		$decoded_body     = json_decode( $body, true );
		$action           = $decoded_body['action'] ?? '';
		$slug             = $decoded_body['slug'] ?? '';
		$plugin_version   = $decoded_body['plugin_version'] ?? '';
		$sites            = $decoded_body['sites'] ?? array();
		$plugin_type      = $decoded_body['plugin_type'] ?? 'public';
		$plugin_path_info = $decoded_body['plugin_path_info'] ?? '';

		if ( ! in_array( $action, array( 'activate', 'deactivate', 'update', 'remove', 'change-version', 'install' ), true ) ) {
			return new \WP_Error( 'invalid_action', __( 'Invalid action provided.', 'oneupdate' ), array( 'status' => 400 ) );
		}

		if ( empty( $slug ) || ! is_string( $slug ) ) {
			return new \WP_Error( 'invalid_slug', __( 'Invalid plugin slug provided.', 'oneupdate' ), array( 'status' => 400 ) );
		}

		if ( ! is_array( $sites ) || empty( $sites ) ) {
			return new \WP_Error( 'invalid_sites', __( 'Invalid sites provided.', 'oneupdate' ), array( 'status' => 400 ) );
		}

		$output = array();
		$errors = array();
		if ( 'activate' === $action || 'deactivate' === $action || 'remove' === $action ) {
			foreach ( $sites as $site ) {
				$oneupdate_sites = $GLOBALS['oneupdate_sites'] ?? array();
				$public_key      = $oneupdate_sites[ $site ]['publicKey'] ?? '';

				$request_postfix = '/wp-json/' . self::NAMESPACE . '/oneupdate-plugins-options';
				// strip the trailing slash from the site URL.
				$site_url = rtrim( $oneupdate_sites[ $site ]['siteUrl'], '/' );

				if ( ! empty( $public_key ) ) {
					$response = wp_remote_post(
						$site_url . $request_postfix,
						array(
							'headers' => array(
								'Content-Type' => 'application/json',
								'X-OneUpdate-Plugins-Token' => $public_key,
							),
							'body'    => wp_json_encode(
								array(
									'options' => array(
										'plugins'     => array( $plugin_path_info ),
										'plugin_type' => $action,
									),
								)
							),
							'timeout' => 30, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout -- this is to avoid timeout issues.
						)
					);
					if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
						$errors[] = new \WP_Error(
							'plugin_action_error',
							sprintf(
								/* translators: %s is the site URL */
								__( 'Failed to execute plugin action on site %s.', 'oneupdate' ),
								$site
							),
							array(
								'status'   => 500,
								'response' => $response,
								'error'    => is_wp_error( $response ) ? $response->get_error_message() : wp_remote_retrieve_body( $response ),
							)
						);
					} else {
						$body         = wp_remote_retrieve_body( $response );
						$decoded_body = json_decode( $body, true );

						if ( isset( $decoded_body['success'] ) && $decoded_body['success'] ) {
							$output[] = array(
								'site'             => $site,
								'action'           => $action,
								'slug'             => $slug,
								'status'           => 'success',
								'response'         => $decoded_body,
								'plugin_info_path' => $plugin_path_info,
							);
						} else {
							$errors[] = new \WP_Error(
								'plugin_action_error',
								sprintf(
									/* translators: %s is the site URL */
									__( 'Failed to execute plugin action on site %s.', 'oneupdate' ),
									$site
								),
								array(
									'status'   => 500,
									'response' => $response,
									'error'    => isset( $decoded_body['message'] ) ? $decoded_body['message'] : __( 'Unknown error occurred.', 'oneupdate' ),
								)
							);
						}
					}
				}
			}
		}
		if ( 'update' === $action || 'change-version' === $action ) {
			foreach ( $sites as $site ) {
				$oneupdate_sites = $GLOBALS['oneupdate_sites'] ?? array();
				$public_key      = $oneupdate_sites[ $site ]['publicKey'] ?? '';
				$github_repo     = $oneupdate_sites[ $site ]['gh_repo'] ?? '';
				if ( empty( $github_repo ) ) {
					$errors[] = new \WP_Error(
						'no_github_repo',
						sprintf(
							/* translators: %s is the site URL */
							__( 'GitHub repository not found for site %s.', 'oneupdate' ),
							$site
						),
						array( 'status' => 404 )
					);
					continue;
				}

				$response = $this->trigger_github_action_for_pr_creation(
					$github_repo,
					'production',
					$slug,
					$plugin_version,
					'add_update',
					$oneupdate_sites[ $site ]['siteName'] ?? ''
				);

				if ( is_wp_error( $response ) ) {
					$errors[] = $response;
				} else {
					$output[] = array(
						'site'     => $site,
						'action'   => $action,
						'slug'     => $slug,
						'status'   => 'success',
						'response' => $response,
					);
				}
			}
		}
		if ( 'remove' === $action ) {
			foreach ( $sites as $site ) {
				$oneupdate_sites = $GLOBALS['oneupdate_sites'] ?? array();
				$public_key      = $oneupdate_sites[ $site ]['publicKey'] ?? '';
				$github_repo     = $oneupdate_sites[ $site ]['gh_repo'] ?? '';
				if ( empty( $github_repo ) ) {
					$errors[] = new \WP_Error(
						'no_github_repo',
						sprintf(
							/* translators: %s is the site URL */
							__( 'GitHub repository not found for site %s.', 'oneupdate' ),
							$site
						),
						array( 'status' => 404 )
					);
					continue;
				}

				$response = $this->trigger_github_action_for_pr_creation(
					$github_repo,
					'production',
					$slug,
					'',
					'remove',
					$oneupdate_sites[ $site ]['siteName'] ?? ''
				);

				if ( is_wp_error( $response ) ) {
					$errors[] = $response;
				} else {
					$output[] = array(
						'site'     => $site,
						'action'   => $action,
						'slug'     => $slug,
						'status'   => 'success',
						'response' => $response,
					);
				}
			}
		}
		if ( 'install' === $action ) {
			foreach ( $sites as $site ) {
				$oneupdate_sites = $GLOBALS['oneupdate_sites'] ?? array();
				$public_key      = $oneupdate_sites[ $site ]['publicKey'] ?? '';
				$github_repo     = $oneupdate_sites[ $site ]['gh_repo'] ?? '';
				if ( empty( $github_repo ) ) {
					$errors[] = new \WP_Error(
						'no_github_repo',
						sprintf(
							/* translators: %s is the site URL */
							__( 'GitHub repository not found for site %s.', 'oneupdate' ),
							$site
						),
						array( 'status' => 404 )
					);
					continue;
				}

				$response = $this->trigger_github_action_for_pr_creation(
					$github_repo,
					'production',
					$slug,
					$plugin_version,
					'add_update',
					$oneupdate_sites[ $site ]['siteName'] ?? ''
				);

				if ( is_wp_error( $response ) ) {
					$errors[] = $response;
				} else {
					$output[] = array(
						'site'     => $site,
						'action'   => $action,
						'slug'     => $slug,
						'status'   => 'success',
						'response' => $response,
					);
				}
			}
		}

		return rest_ensure_response(
			array(
				'success' => count( $errors ) === 0,
				'message' => __( 'Plugin action executed successfully.', 'oneupdate' ),
				'output'  => $output,
				'errors'  => $errors,
			)
		);
	}

	/**
	 * Apply plugins to selected sites.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function apply_private_plugins_to_selected_sites( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$body         = $request->get_body();
		$decoded_body = json_decode( $body, true );
		$sites_data   = $decoded_body['sites'] ?? array();
		$plugins      = $decoded_body['plugins'] ?? array();
		if ( ! is_array( $sites_data ) || ! is_array( $plugins ) ) {
			return new \WP_Error( 'invalid_data', __( 'Invalid data provided.', 'oneupdate' ), array( 'status' => 400 ) );
		}

		// for each sites, apply the plugins.
		$results = array();
		foreach ( $sites_data as $site_data ) {
			$site_name = $site_data['siteName'] ?? '';
			$site_url  = $site_data['siteUrl'] ?? '';
			$repo_url  = $site_data['githubRepo'] ?? '';

			if ( empty( $site_name ) || empty( $site_url ) || empty( $repo_url ) ) {
				$results[] = array(
					'site'    => $site_name,
					'status'  => 'error',
					'message' => __( 'Invalid site data provided.', 'oneupdate' ),
				);
				continue;
			}
			foreach ( $plugins as $private_plugin ) {
				$results[] = $this->trigger_github_action_for_private_plugin(
					$repo_url,
					$private_plugin,
					'production',
					$site_name
				);
			}
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'results' => $results,
			)
		);
	}

	/**
	 * Trigger GitHub action for private plugin.
	 *
	 * @param string $repo           The GitHub repository.
	 * @param string $private_plugin The private plugin zip URL.
	 * @param string $branch         The branch to create PR against.
	 * @param string $site_name      The site name for which the action is triggered.
	 *
	 * @return array|\WP_Error
	 */
	private function trigger_github_action_for_private_plugin( string $repo, string $private_plugin, string $branch, string $site_name ): array|\WP_Error {
		$github_token = get_option( 'oneupdate_gh_token', '' );

		if ( empty( $github_token ) ) {
			return new \WP_Error( 'no_github_token', __( 'GitHub token not found.', 'oneupdate' ), array( 'status' => 404 ) );
		}

		$action_url = "https://api.github.com/repos/{$repo}/actions/workflows/oneupdate-pr-creation-private.yml/dispatches";

		// pass the zip file as input to the GitHub action.
		$response = wp_safe_remote_post(
			$action_url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $github_token,
					'Accept'        => 'application/vnd.github.v3+json',
					'User-Agent'    => 'OneUpdate Plugin Loader',
				),
				'timeout' => 30, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout -- this is to avoid timeout issues.
				'body'    => wp_json_encode(
					array(
						'ref'    => $branch,
						'inputs' => array(
							'zip_url' => $private_plugin,
						),
					)
				),
			),
		);

		if ( is_wp_error( $response ) || 204 !== wp_remote_retrieve_response_code( $response ) ) {
			return new \WP_Error(
				'github_action_error',
				__( 'Failed to trigger GitHub action for PR creation.', 'oneupdate' ),
				array(
					'status' => 500,
					'error'  => is_wp_error( $response ) ? $response->get_error_message() : wp_remote_retrieve_response_code( $response ),
				)
			);
		}

		// If the request was successful, return the response.
		$response_code = wp_remote_retrieve_response_code( $response );

		if ( is_wp_error( $response ) || 204 !== $response_code ) {
			return new \WP_Error(
				'github_action_error',
				__( 'Failed to trigger GitHub action for PR creation.', 'oneupdate' ),
				array(
					'status' => 500,
					'error'  => is_wp_error( $response ) ? $response->get_error_message() : $response_code,
				)
			);
		}

		sleep( 2 ); // this is to make sure workflow is triggered.

		// Try to get the workflow run ID.
		$run_id = $this->get_latest_workflow_run_id( $repo, 'oneupdate-pr-creation-private.yml' );

		return array(
			'success'       => true,
			'repo'          => $repo,
			'branch'        => $branch,
			'message'       => __( 'GitHub Action workflow dispatched successfully', 'oneupdate' ),
			'response_code' => $response_code,
			'workflow_url'  => "https://github.com/{$repo}/actions/workflows/oneupdate-pr-creation-private.yml",
			'run_id'        => $run_id,
			'run_url'       => $run_id ? "https://github.com/{$repo}/actions/runs/{$run_id}" : null,
			'siteName'      => $site_name,
		);
	}

	/**
	 * Get onpress plugins options.
	 *
	 * @return WP_REST_Response|\WP_Error
	 */
	public function get_oneupdate_plugins_options(): \WP_REST_Response|\WP_Error {
		$options = get_option( 'oneupdate_plugins_options', array() );

		return rest_ensure_response(
			array(
				'success' => true,
				'options' => $options,
			)
		);
	}

	/**
	 * Update oneupdate plugins options.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_oneupdate_plugins_options( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$body            = $request->get_body();
		$decoded_body    = json_decode( $body, true );
		$request_options = $decoded_body['options'] ?? array();

		if ( ! is_array( $request_options ) ) {
			return new \WP_Error( 'invalid_options', __( 'Invalid options provided.', 'oneupdate' ), array( 'status' => 400 ) );
		}

		// from request options get plugins and plugin type.
		$plugins     = $request_options['plugins'] ?? array();
		$plugin_type = $request_options['plugin_type'] ?? 'add_update';

		// oneupdate_plugin_activate options.
		$oneupdate_plugin_activate = get_option( 'oneupdate_plugins_options', array() );

		// if plugin type is deactivate/remove then remove the plugin from options.
		if ( 'deactivate' === $plugin_type || 'remove' === $plugin_type ) {
			// get active plugins options.
			$active_plugins = get_option( 'active_plugins', array() );
			// remove the plugins from active plugins options.
			foreach ( $plugins as $plugin ) {
				if ( in_array( $plugin, $active_plugins, true ) ) {
					deactivate_plugins( $plugin, true );
					$active_plugins = array_diff( $active_plugins, array( $plugin ) );
				}
				if ( isset( $oneupdate_plugin_activate[ $plugin ] ) ) {
					unset( $oneupdate_plugin_activate[ $plugin ] );
				}
			}
			// update the active plugins options.
			update_option( 'active_plugins', $active_plugins );

		}
		if ( 'activate' === $plugin_type ) {
			// if plugin type is activate then activate the plugins.
			$active_plugins = get_option( 'active_plugins', array() );
			foreach ( $plugins as $plugin ) {
				if ( ! in_array( $plugin, $active_plugins, true ) ) {
					activate_plugin( $plugin, '', false, true );
					$active_plugins[] = $plugin;
				}
				if ( ! isset( $oneupdate_plugin_activate[ $plugin ] ) ) {
					$oneupdate_plugin_activate[ $plugin ] = $plugin;
				}
			}
		}

		update_option( 'oneupdate_plugins_options', $oneupdate_plugin_activate );

		if ( ! empty( $plugins ) ) {
			Cache::rebuild_transient_for_single_plugin(
				$plugins[0],
				'activate' === $plugin_type,
				'deactivate' === $plugin_type
			);
		}

		return rest_ensure_response(
			array(
				'success'     => true,
				'plugin_type' => $plugin_type,
				'plugins'     => $plugins,
			)
		);
	}

	/**
	 * Get all plugins.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_plugins(): \WP_REST_Response|\WP_Error {

		// check if oneupdate_get_plugins cache is set.
		$cached_plugins = get_transient( 'oneupdate_get_plugins' );
		if ( false !== $cached_plugins ) {
			return rest_ensure_response(
				array(
					'success' => true,
					'plugins' => json_decode( $cached_plugins ),
				)
			);
		}

		$reconstructed_plugins = Cache::build_plugins_transient();

		return rest_ensure_response(
			array(
				'success' => true,
				'plugins' => $reconstructed_plugins,
			)
		);
	}

	/**
	 * Apply plugins to selected sites.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function apply_plugins_to_selected_sites( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$body         = $request->get_body();
		$decoded_body = json_decode( $body, true );
		$sites        = $decoded_body['sites'] ?? array();
		$plugins      = $decoded_body['plugins'] ?? array();
		$plugin_type  = $decoded_body['plugin_type'] ?? 'add_update';
		$created_pr   = array();
		$error_logs   = array();

		foreach ( $sites as $site ) {
			if ( ! isset( $site['githubRepo'] ) ) {
				return new \WP_Error( 'invalid_site_data', __( 'Invalid site data provided.', 'oneupdate' ), array( 'status' => 400 ) );
			}

			foreach ( $plugins as $plugin ) {
				// Create GitHub PR for each plugin.
				$pr_response  = $this->trigger_github_action_for_pr_creation( $site['githubRepo'], 'production', $plugin['slug'], $plugin['version'], $plugin_type, $site['siteName'] ?? '' );
				$created_pr[] = $pr_response;
			}
			// set oneupdate_plugins_options for all sites.
			$request_postfix = '/wp-json/' . self::NAMESPACE . '/oneupdate-plugins-options';
			$site_url        = $site['siteUrl'] ?? '';
			$token           = $site['publicKey'] ?? '';
			if ( ! empty( $site_url ) ) {
				$site_url        = rtrim( $site_url, '/' );
				$request_postfix = $site_url . '/' . $request_postfix;
			}

			// if current site is same as site_url then use current site token.
			if ( empty( $token ) ) {
				$token = get_option( 'oneupdate_child_site_public_key', 'default_public_key' );
			}

			// create comma separated string array of plugins.
			$slug_array_of_plugins = array();
			foreach ( $plugins as $plugin ) {
				$slug_array_of_plugins[] = $plugin['slug'];
			}

			$response = wp_remote_post(
				$request_postfix,
				array(
					'headers' => array(
						'Content-Type'              => 'application/json',
						'X-OneUpdate-Plugins-Token' => $token,
					),
					'body'    => wp_json_encode(
						array(
							'options' => array(
								'plugins'     => $slug_array_of_plugins,
								'plugin_type' => $plugin_type,
							),
						)
					),
					'timeout' => 30, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout -- this is to avoid timeout issues.
				)
			);
			if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
				$error_logs[] = array(
					'site'     => $site,
					'error'    => is_wp_error( $response ) ? $response->get_error_message() : wp_remote_retrieve_response_code( $response ),
					'status'   => wp_remote_retrieve_response_code( $response ),
					'response' => $response,
				);
			}
		}

		return rest_ensure_response(
			array(
				'success'     => count( $error_logs ) === 0,
				'created_prs' => $created_pr,
				'logs'        => $error_logs,
			)
		);
	}

	/**
	 * Trigger GitHub action for PR creation.
	 *
	 * @param string $repo   The GitHub repository slug.
	 * @param string $branch The branch to create the PR against.
	 * @param string $plugin_slug The plugin slug.
	 * @param string $version The plugin version.
	 * @param string $plugin_type The type of plugin action (add_update, deactivate, remove).
	 * @param string $site_name The site name for which the action is triggered.
	 *
	 * @return array|\WP_Error
	 */
	private function trigger_github_action_for_pr_creation( string $repo, string $branch, string $plugin_slug, string $version, string $plugin_type, string $site_name ): array|\WP_Error {
		$github_token = get_option( 'oneupdate_gh_token' );

		if ( empty( $github_token ) ) {
			return new \WP_Error( 'no_github_token', __( 'GitHub token not found.', 'oneupdate' ), array( 'status' => 404 ) );
		}

		// construct plugin zip from plugin slug and version.
		$wordpress_plugin_api = 'https://downloads.wordpress.org/plugin/' . $plugin_slug . '.' . $version . '.zip';

		$action_url = "https://api.github.com/repos/{$repo}/actions/workflows/oneupdate-pr-creation.yml/dispatches";

		// pass the zip file as input to the GitHub action.
		$response = wp_safe_remote_post(
			$action_url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $github_token,
					'Accept'        => 'application/vnd.github.v3+json',
					'User-Agent'    => 'OneUpdate Plugin Loader',
				),
				'timeout' => 30, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout -- this is to avoid timeout issues.
				'body'    => wp_json_encode(
					array(
						'ref'    => $branch,
						'inputs' => array(
							'plugin_slug' => $plugin_slug,
							'version'     => $version,
							'zip_url'     => $wordpress_plugin_api,
							'plugin_type' => $plugin_type,
						),
					)
				),
			),
		);

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 204 ) {
			return new \WP_Error(
				'github_action_error',
				__( 'Failed to trigger GitHub action for PR creation.', 'oneupdate' ),
				array(
					'status'               => 500,
					'plugin'               => $plugin_slug,
					'version'              => $version,
					'branch'               => $branch,
					'repo'                 => $repo,
					'plugin_type'          => $plugin_type,
					'wordpress_plugin_api' => $wordpress_plugin_api,
					'response'             => $response,
					'error'                => is_wp_error( $response ) ? $response->get_error_message() : wp_remote_retrieve_response_code( $response ),
				)
			);
		}

		// If the request was successful, return the response.
		$response_code = wp_remote_retrieve_response_code( $response );

		if ( is_wp_error( $response ) || 204 !== $response_code ) {
			return new \WP_Error(
				'github_action_error',
				__( 'Failed to trigger GitHub action for PR creation.', 'oneupdate' ),
				array(
					'status'      => 500,
					'plugin'      => $plugin_slug,
					'version'     => $version,
					'branch'      => $branch,
					'repo'        => $repo,
					'plugin_type' => $plugin_type,
					'error'       => is_wp_error( $response ) ? $response->get_error_message() : $response_code,
				)
			);
		}

		sleep( 2 ); // this is to make sure workflow is triggered.

		// Try to get the workflow run ID.
		$run_id = $this->get_latest_workflow_run_id( $repo, 'oneupdate-pr-creation.yml' );

		return array(
			'success'       => true,
			'repo'          => $repo,
			'branch'        => $branch,
			'plugin'        => $plugin_slug,
			'version'       => $version,
			'message'       => __( 'GitHub Action workflow dispatched successfully', 'oneupdate' ),
			'response_code' => $response_code,
			'workflow_url'  => "https://github.com/{$repo}/actions/workflows/oneupdate-pr-creation.yml",
			'run_id'        => $run_id,
			'run_url'       => $run_id ? "https://github.com/{$repo}/actions/runs/{$run_id}" : null,
			'siteName'      => $site_name,
		);
	}

	/**
	 * Get the latest workflow run ID for a given repository and workflow filename.
	 *
	 * @param string $repo             The GitHub repository slug.
	 * @param string $workflow_filename The workflow filename.
	 *
	 * @return string|null The latest workflow run ID or null if not found.
	 */
	private function get_latest_workflow_run_id( string $repo, string $workflow_filename ): string|null {
		$github_token = get_option( 'oneupdate_gh_token' );

		if ( empty( $github_token ) ) {
			return null;
		}

		$runs_url = "https://api.github.com/repos/{$repo}/actions/workflows/{$workflow_filename}/runs?per_page=1";

		$response = wp_safe_remote_get(
			$runs_url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $github_token,
					'Accept'        => 'application/vnd.github.v3+json',
					'User-Agent'    => 'OneUpdate Plugin Loader',
				),
				'timeout' => 15, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout -- this is to avoid timeout issues.
			)
		);

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return null;
		}

		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $response_body['workflow_runs'] ) ) {
			return null;
		}

		return $response_body['workflow_runs'][0]['id'] ?? null;
	}
}
