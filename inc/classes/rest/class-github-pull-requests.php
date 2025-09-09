<?php
/**
 * Class GitHub_Pull_Requests which contains routes for GH PR's.
 *
 * @package OneUpdate
 */

namespace OneUpdate\REST;

use OneUpdate\Plugin_Configs\Constants;
use OneUpdate\Traits\Singleton;
use WP_REST_Server;

/**
 * Class GitHub_Pull_Requests
 */
class GitHub_Pull_Requests {

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	private const NAMESPACE = 'oneupdate/v1/github';

	/**
	 * Use Singleton trait.
	 */
	use Singleton;

	/**
	 * Protected class constructor
	 *
	 * @return void
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Setup WordPress hooks
	 *
	 * @return void
	 */
	public function setup_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		/**
		 * Register a route to get pull requests by pagination.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/pull-requests/(?P<owner>[a-zA-Z0-9._-]+)/(?P<repo>[a-zA-Z0-9._-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_pull_requests' ),
					'permission_callback' => array( self::class, 'permission_callback' ),
					'args'                => array(
						'owner'        => array(
							'required' => true,
							'type'     => 'string',
						),
						'repo'         => array(
							'required' => true,
							'type'     => 'string',
						),
						'pr_number'    => array(
							'required' => false,
							'type'     => 'integer',
						),
						'state'        => array(
							'required' => false,
							'type'     => 'string',
							'default'  => 'all',
							'enum'     => array( 'open', 'closed', 'all', 'merged' ),
						),
						'page'         => array(
							'required' => false,
							'type'     => 'integer',
							'default'  => 1,
						),
						'per_page'     => array(
							'required' => false,
							'type'     => 'integer',
							'default'  => 25,
							'maximum'  => 100,
							'minimum'  => 1,
						),
						'search_query' => array(
							'required' => false,
							'type'     => 'string',
						),
					),
				),
			)
		);
	}

	/**
	 * Permission callback for the routes.
	 *
	 * @return bool
	 */
	public static function permission_callback() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get pull requests by pagination.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_pull_requests( \WP_REST_Request $request ): \WP_REST_Response {
		$gh_owner     = sanitize_text_field( $request['owner'] );
		$gh_repo      = sanitize_text_field( $request['repo'] );
		$pr_number    = filter_var( $request->get_param( 'pr_number' ), FILTER_VALIDATE_INT ) ?? 0;
		$page         = filter_var( $request->get_param( 'page' ), FILTER_VALIDATE_INT ) ?? 1;
		$pr_state     = sanitize_text_field( $request->get_param( 'state' ) ) ?? 'all';
		$per_page     = filter_var( $request->get_param( 'per_page' ), FILTER_VALIDATE_INT ) ?? 25;
		$search_query = sanitize_text_field( $request->get_param( 'search_query' ) ) ?? '';

		$gh_token = get_option( Constants::ONEUPDATE_GH_TOKEN, '' );

		if ( empty( $gh_token ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'GitHub token is not set. Please set it in the OneUpdate settings.', 'oneupdate' ),
				),
				400
			);
		}

		// if pr_number is not provided, get all pull requests.
		if ( empty( $pr_number ) && empty( $search_query ) ) {
			return self::get_all_pull_requests( $gh_owner, $gh_repo, $pr_state, $gh_token, $per_page, $page );
		}

		// if pr_number is not provided but search_query is provided, search pull requests.
		if ( ! empty( $search_query ) ) {
			return self::search_pull_requests( $gh_owner, $gh_repo, $search_query, $gh_token, $per_page, $page, $pr_state );
		}

		// if pr_number is provided, get specific pull request.
		return self::get_specific_pull_request( $gh_owner, $gh_repo, $pr_number, $gh_token );
	}

	/**
	 * Get all pull requests for a given repo.
	 *
	 * @param string $gh_owner GitHub owner.
	 * @param string $gh_repo GitHub repo.
	 * @param string $pr_state State of pull requests to fetch. Default is 'open'.
	 * @param string $gh_token GitHub token.
	 * @param int    $per_page Number of pull requests per page. Default is 25.
	 * @param int    $page Page number. Default is 1.
	 *
	 * @return \WP_REST_Response
	 */
	private static function get_all_pull_requests( string $gh_owner, string $gh_repo, string $pr_state = 'open', string $gh_token, int $per_page = 25, int $page = 1 ): \WP_REST_Response {

		// gh api endpoint to get pull requests.
		$gh_api_endpoint = "https://api.github.com/repos/{$gh_owner}/{$gh_repo}/pulls?state={$pr_state}&per_page={$per_page}&page={$page}";

		$response = wp_safe_remote_get(
			$gh_api_endpoint,
			array(
				'headers' => array(
					'Authorization' => "Bearer {$gh_token}",
					'Accept'        => 'application/vnd.github.v3+json',
					'User-Agent'    => __( 'OneUpdate Plugin Loader', 'oneupdate' ),
				),
				'timeout' => 15, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout -- this is to avoid timeout issues.
			),
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => $response->get_error_message(),
				),
				500
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$headers     = wp_remote_retrieve_headers( $response );

		if ( 200 !== $status_code ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' =>
					sprintf(
						/* translation: %s github response code */
						'GitHub API returned status code %d.',
						$status_code,
					),
				),
				$status_code
			);
		}

		$total_count = 0;
		$total_pages = 1;

		if ( isset( $headers['link'] ) ) {
			$link_header = $headers['link'];

			// Parse the Link header to get last page.
			if ( preg_match( '/page=(\d+)>; rel="last"/', $link_header, $matches ) ) {
				$total_pages = (int) $matches[1] ?? 1;
				$total_count = $total_pages * $per_page; // Approximate.
			}
		}

		$pull_requests = json_decode( $body, true );

		$pull_requests = self::format_github_pull_requests_info( $pull_requests );

		$pull_requests_response = new \WP_REST_Response(
			array(
				'success'       => true,
				'pull_requests' => $pull_requests,
				'pagination'    => array(
					'current_page' => $page,
					'per_page'     => $per_page,
					'total_pages'  => $total_pages,
					'total_count'  => $total_count,
				),
			),
			200
		);

		$pull_requests_response->header( 'X-WP-Total', $total_count );
		$pull_requests_response->header( 'X-WP-TotalPages', $total_pages );

		return $pull_requests_response;
	}

	/**
	 * Search pull requests in a given repo.
	 *
	 * @param string $gh_owner GitHub owner.
	 * @param string $gh_repo GitHub repo.
	 * @param string $search_query Search query.
	 * @param string $gh_token GitHub token.
	 * @param int    $per_page Number of pull requests per page. Default is 25.
	 * @param int    $page Page number. Default is 1.
	 * @param string $pr_state State of pull requests to fetch. Default is 'all'.
	 *
	 * @return \WP_REST_Response
	 */
	private static function search_pull_requests( string $gh_owner, string $gh_repo, string $search_query, string $gh_token, int $per_page = 25, int $page = 1, string $pr_state = 'all' ): \WP_REST_Response {

		// If we have a specific search query, use search API with state filter.
		if ( ! empty( $search_query ) && 'all' !== $pr_state ) {
			return self::search_pull_requests_with_query_and_state( $gh_owner, $gh_repo, $search_query, $gh_token, $per_page, $page, $pr_state );
		}

		// If no search query or state is 'all', use the original search approach.
		if ( ! empty( $search_query ) ) {
			$gh_api_endpoint = "https://api.github.com/search/issues?q={$search_query}+repo:{$gh_owner}/{$gh_repo}+type:pr";

			// Add state to search query if not 'all'.
			if ( 'all' !== $pr_state ) {
				$gh_api_endpoint .= "+state:{$pr_state}";
			}

			$gh_api_endpoint .= "&per_page={$per_page}&page={$page}";
		} else {
			// Use pulls API for better state filtering when no search query.
			$gh_api_endpoint = "https://api.github.com/repos/{$gh_owner}/{$gh_repo}/pulls?state={$pr_state}&per_page={$per_page}&page={$page}";
		}

		$response = wp_safe_remote_get(
			$gh_api_endpoint,
			array(
				'headers' => array(
					'Authorization' => "Bearer {$gh_token}",
					'Accept'        => 'application/vnd.github.v3+json',
					'User-Agent'    => __( 'OneUpdate Plugin Loader', 'oneupdate' ),
				),
				'timeout' => 15, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout -- this is to avoid timeout issues.
			),
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => $response->get_error_message(),
				),
				500
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$headers     = wp_remote_retrieve_headers( $response );

		if ( 200 !== $status_code ) {
			return new \WP_REST_Response(
				array(
					'success'       => false,
					'message'       =>
						sprintf(
						/* translation: %s github response code */
							'GitHub API returned status code %d.',
							$status_code,
						),
					'response_body' => $body,
				),
				$status_code
			);
		}

		$results = json_decode( $body, true );

		// Handle different response formats.
		if ( ! empty( $search_query ) ) {
			// Search API response.
			$pull_requests = isset( $results['items'] ) ? self::format_github_pull_requests_info( $results['items'] ) : array();
			$total_count   = $results['total_count'] ?? 0;
		} else {
			// Pulls API response.
			$pull_requests = self::format_github_pull_requests_info( $results );
			$total_count   = self::get_total_count_from_headers( $headers, count( $pull_requests ) );
		}

		$total_pages = ceil( $total_count / $per_page );

		$response_data = new \WP_REST_Response(
			array(
				'success'       => true,
				'pull_requests' => $pull_requests,
				'pagination'    => array(
					'current_page' => $page,
					'per_page'     => $per_page,
					'total_pages'  => $total_pages,
					'total_count'  => $total_count,
				),
			),
			200
		);

		$response_data->header( 'X-WP-Total', $total_count );
		$response_data->header( 'X-WP-TotalPages', $total_pages );

		return $response_data;
	}

	/**
	 * Handle search with both query and state filters using multiple API calls if needed
	 *
	 * @param string $gh_owner GitHub owner.
	 * @param string $gh_repo GitHub repo.
	 * @param string $search_query Search query.
	 * @param string $gh_token GitHub token.
	 * @param int    $per_page Number of pull requests per page.
	 * @param int    $page Page number.
	 * @param string $pr_state State of pull requests to fetch.
	 *
	 * @return \WP_REST_Response
	 */
	private static function search_pull_requests_with_query_and_state( string $gh_owner, string $gh_repo, string $search_query, string $gh_token, int $per_page, int $page, string $pr_state ): \WP_REST_Response {

		// Use search API with state filter in query.
		$gh_api_endpoint = "https://api.github.com/search/issues?q={$search_query}+repo:{$gh_owner}/{$gh_repo}+type:pr+state:{$pr_state}&per_page={$per_page}&page={$page}";

		$response = wp_safe_remote_get(
			$gh_api_endpoint,
			array(
				'headers' => array(
					'Authorization' => "Bearer {$gh_token}",
					'Accept'        => 'application/vnd.github.v3+json',
					'User-Agent'    => __( 'OneUpdate Plugin Loader', 'oneupdate' ),
				),
				'timeout' => 15, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout -- this is to avoid timeout issues.
			),
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => $response->get_error_message(),
				),
				500
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		if ( 200 !== $status_code ) {
			return new \WP_REST_Response(
				array(
					'success'       => false,
					'message'       => sprintf(
						/* translation: %s github response code */
						'GitHub API returned status code %d.',
						$status_code,
					),
					'response_body' => $body,
				),
				$status_code
			);
		}

		$search_results = json_decode( $body, true );
		$pull_requests  = isset( $search_results['items'] ) ? self::format_github_pull_requests_info( $search_results['items'] ) : array();
		$total_count    = $search_results['total_count'] ?? 0;
		$total_pages    = ceil( $total_count / $per_page );

		$response_data = new \WP_REST_Response(
			array(
				'success'       => true,
				'pull_requests' => $pull_requests,
				'pagination'    => array(
					'current_page' => $page,
					'per_page'     => $per_page,
					'total_pages'  => $total_pages,
					'total_count'  => $total_count,
				),
			),
			200
		);

		$response_data->header( 'X-WP-Total', $total_count );
		$response_data->header( 'X-WP-TotalPages', $total_pages );

		return $response_data;
	}

	/**
	 * Extract total count from Link headers when using pulls API
	 *
	 * @param array $headers Response headers.
	 * @param int   $current_count Current count of items fetched.
	 *
	 * @return int Total count of items.
	 */
	private static function get_total_count_from_headers( array $headers, int $current_count ): int {
		if ( ! isset( $headers['link'] ) ) {
			return $current_count;
		}

		$link_header = $headers['link'];

		// Parse the Link header to get last page.
		if ( preg_match( '/page=(\d+)>; rel="last"/', $link_header, $matches ) ) {
			$last_page = (int) $matches[1];
			// This is an approximation.
			return $last_page * 25;
		}

		return $current_count;
	}

	/**
	 * Get a specific pull request by its number.
	 *
	 * @param string $gh_owner GitHub owner.
	 * @param string $gh_repo GitHub repo.
	 * @param int    $pr_number Pull request number.
	 * @param string $gh_token GitHub token.
	 *
	 * @return \WP_REST_Response
	 */
	private static function get_specific_pull_request( string $gh_owner, string $gh_repo, int $pr_number, string $gh_token ): \WP_REST_Response {

		// gh api endpoint to get a specific pull request.
		$gh_api_endpoint = "https://api.github.com/repos/{$gh_owner}/{$gh_repo}/pulls/{$pr_number}";

		$response = wp_safe_remote_get(
			$gh_api_endpoint,
			array(
				'headers' => array(
					'Authorization' => "Bearer {$gh_token}",
					'Accept'        => 'application/vnd.github.v3+json',
					'User-Agent'    => __( 'OneUpdate Plugin Loader', 'oneupdate' ),
				),
				'timeout' => 15, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout -- this is to avoid timeout issues.
			),
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => $response->get_error_message(),
				),
				500
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		if ( 200 !== $status_code ) {
			return new \WP_REST_Response(
				array(
					'success'       => false,
					'message'       => sprintf(
						/* translation: %s github response code */
						'GitHub API returned status code %d.',
						$status_code,
					),
					'response_body' => $body,
				),
				$status_code
			);
		}

		$pull_request = json_decode( $body, true );

		$pull_request = self::format_github_pull_requests_info( array( $pull_request ) );

		return new \WP_REST_Response(
			array(
				'success'      => true,
				'pull_request' => $pull_request,
			),
			200
		);
	}

	/**
	 * Format GitHub pull requests info to return only necessary fields.
	 *
	 * @param array $pull_requests Array of pull requests from GitHub API.
	 *
	 * @return array Formatted array of pull requests.
	 */
	private static function format_github_pull_requests_info( array $pull_requests ): array {
		$formatted_prs = array();
		foreach ( $pull_requests as $pr ) {
			$formatted_prs[] = array(
				'id'            => $pr['id'] ?? '',
				'url'           => $pr['url'] ?? '',
				'number'        => $pr['number'] ?? '',
				'title'         => $pr['title'] ?? '',
				'user'          => array(
					'login'      => $pr['user']['login'] ?? '',
					'avatar_url' => $pr['user']['avatar_url'] ?? '',
					'html_url'   => $pr['user']['html_url'] ?? '',
				),
				'labels'        => $pr['labels'] ?? '',
				'state'         => $pr['state'] ?? '',
				'created_at'    => $pr['created_at'] ?? '',
				'updated_at'    => $pr['updated_at'] ?? '',
				'closed_at'     => $pr['closed_at'] ?? '',
				'html_url'      => $pr['html_url'] ?? '',
				'body'          => $pr['body'] ?? '',
				'pr_branch'     => $pr['head']['ref'] ?? '',
				'base_branch'   => $pr['base']['ref'] ?? '',
				'merged_at'     => $pr['merged_at'] ?? null,
				'merged'        => $pr['merged'] ?? null,
				'merged_by'     => isset( $pr['merged_by'] ) ? array(
					'login'      => $pr['merged_by']['login'] ?? '',
					'avatar_url' => $pr['merged_by']['avatar_url'] ?? '',
					'html_url'   => $pr['merged_by']['html_url'] ?? '',
				) : null,

				'comments'      => $pr['comments'] ?? null,
				'commits'       => $pr['commits'] ?? null,
				'additions'     => $pr['additions'] ?? null,
				'deletions'     => $pr['deletions'] ?? null,
				'changed_files' => $pr['changed_files'] ?? null,
				'rebaseable'    => $pr['rebaseable'] ?? null,
				'draft'         => $pr['draft'] ?? null,
				'auto_merge'    => $pr['auto_merge'] ?? null,

			);
		}
		return $formatted_prs;
	}
}
