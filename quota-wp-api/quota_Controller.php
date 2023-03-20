<?php

/*use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;*/

class quota_Controller extends WP_REST_Controller {
	private string $quota_rest_namespace = 'quota/v1';
	private string $rest_route_base = '/site/(?P<siteurl>[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,}))';



	function register_rest_route() {
		add_action( 'rest_api_init', function () {
			register_rest_route( $this->quota_rest_namespace, '/list',
				[
					array(
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => [ $this, 'route_list_current_quotas' ],
						'permission_callback' => [ $this, 'check_if_user_is_authorized' ]
					),
				] );
			register_rest_route( $this->quota_rest_namespace, $this->rest_route_base,
				[
					array(
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => [ $this, 'route_get_current_quota' ],
						'permission_callback' => [ $this, 'check_if_user_is_authorized' ]
					),
				] );
			register_rest_route( $this->quota_rest_namespace, $this->rest_route_base . '/set/(?P<quota>([0-9-]+[mg]?))', [
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'route_set_quota' ],
					'permission_callback' => [ $this, 'check_if_user_is_authorized' ]
				)
			] );
			register_rest_route( $this->quota_rest_namespace, $this->rest_route_base . '/add/(?P<quota>([0-9-]+[mg]?))', [
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'route_add_quota' ],
					'permission_callback' => [ $this, 'check_if_user_is_authorized' ]
				)
			] );
			register_rest_route( $this->quota_rest_namespace, $this->rest_route_base . '/subtract/(?P<quota>([0-9-]+[mg]?))', [
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'route_subtract_quota' ],
					'permission_callback' => [ $this, 'check_if_user_is_authorized' ]
				)
			] );
		} );
	}
	function route_list_current_quotas( WP_REST_Request $r ) {


		$parameters = $r->get_query_params();
		$sites = get_sites();
		$filtered_sites = [];
		foreach ($sites as $site)
		{
			$quota = $this->determine_quotas($site->blog_id);
			$pass = !(isset($parameters["min-used"]) || isset($parameters["min-used-pct"]));
			if (isset($parameters["min-used"]) && $quota->quota_used >= $parameters["min-used"])
			{
				$pass = true;
			}
			if (isset($parameters["min-used-pct"]) && $quota->quota_used_percent >= $parameters["min-used-pct"])
			{
				$pass = true;
			}
			if ($pass)
			{
				$filtered_sites[] = $quota;
			}
		}

		return $this->parse_and_return_result( $filtered_sites );
	}
	function route_get_current_quota( WP_REST_Request $r ) {
		$blog_id = $this->get_blog_id_from_url( $r );
		if ( is_wp_error( $blog_id ) ) {
			return $blog_id;
		}
		$blog = $this->determine_quotas($blog_id);

		return $this->parse_and_return_result( $blog );
	}

	function route_set_quota( WP_REST_Request $r ) {
		$blog_id = $this->get_blog_id_from_url( $r );
		$quota = self::get_quota_in_mb_from_arg($r->get_param("quota"));

		return $this->parse_and_return_result( $this->set_quota($blog_id, $quota) );
	}

	function route_add_quota( WP_REST_Request $r ) {
		$blog_id = $this->get_blog_id_from_url( $r );
		$quota = self::get_quota_in_mb_from_arg($r->get_param("quota"));

		return $this->parse_and_return_result( $this->add_quota($blog_id, $quota) );
	}

	function route_subtract_quota( WP_REST_Request $r ) {
		$blog_id = $this->get_blog_id_from_url( $r );
		$quota = self::get_quota_in_mb_from_arg($r->get_param("quota"));

		return $this->parse_and_return_result( $this->add_quota($blog_id, -1 * $quota) );
	}
	/**
	 * Get the current blog id from given url. If site is not found a WP_Error is returned instead
	 *
	 * @param WP_REST_Request $r the request
	 *
	 * @return int|WP_Error the id of the blog or an WP_Error if blog was not found
	 */
	function get_blog_id_from_url( WP_REST_Request $r ): int|WP_Error {
		$blog_id = get_blog_id_from_url( $r->get_param( 'siteurl' ) );
		if ( $blog_id == 0 ) {
			return new WP_Error( 'site_not_found', 'Site not found.', [ 'status' => 404 ] );
		}

		return $blog_id;
	}

	/**
	 * Checks authorization of user for calling these endpoints
	 * @return bool true if user is authorized, false otherwise
	 */
	function check_if_user_is_authorized() {
		return current_user_can( 'manage_network' );
	}

	/**
	 *
	 * Parses and returns the result of routing methods. WP_Errors will be thrown directly. If the WP_Error is kind of "FeatureNotFound" a 404 will be returned. If everything is fine, the result is packed into a 200 response
	 *
	 * @param mixed $result the returned result of the routing methods, which can be a WP_Error object or array with data
	 *
	 * @return mixed WP_Error in case of failure or WP_REST_Response with result (or 404 response if feature not found)
	 */
	function parse_and_return_result( mixed $result ): mixed {
		if ( is_wp_error( $result ) ) {
			if ( $result->get_error_code() == 'FeatureNotFound' ) {
				return new WP_REST_Response( array( 'code'    => $result->get_error_code(),
				                                    'message' => $result->get_error_message(),
				                                    'data'    => null
				), 404 );
			} else {
				return $result;
			}
		} else {
			return new WP_REST_Response( array( 'result' => $result ), 200 );
		}
	}

	/**
	 * Parses a n argument value and calculates the megabyte value. If the given number has suffix "g" the value will be multiplied by 1024
	 * @param string $arg the argument in format <int><suffix g or m> like 23g
	 * @return int the numeric value representing the megabytes
	 */
	private static function get_quota_in_mb_from_arg(string $arg): int  {
		$matches[] = preg_match("/^([0-9]+)([gm]?)$/", $arg, $matches);
		$quota_in_mb = 0;
		if (count($matches) >= 3) {
			$quota_in_mb = intval($matches[1]);
			if (!empty($matches[2]) && $matches[2] == "g") {
				$quota_in_mb *= 1024;
			}
		} else {
			print_r($matches);
			WP_CLI::error( 'Error parsing Quota value' );
		}
		return $quota_in_mb;
	}

	/**
	 * Determine quotas
	 *
	 * @param $blog
	 *
	 * @return mixed
	 */
	private function determine_quotas( $blog_id ) {
		$blog = new stdClass();
		$blog->blog_id = $blog_id;
		$blog->url = trailingslashit( get_site_url( $blog_id ) );

		switch_to_blog( $blog->blog_id );

		// Get quota
		$quota       = get_space_allowed();
		$blog->quota = $quota;

		// Get quota used & quota used in percent
		$used = get_space_used();

		if ( $used >= $quota ) {
			$percentused = '100';
		} else {
			$percentused = ( $used / $quota ) * 100;
		}
		$blog->quota_used         = round( $used, 2 );
		$blog->quota_used_percent = round( $percentused, 2 );

		restore_current_blog();

		return $blog;
	}

	private function set_quota(int $blog_id, int $new_quota_in_mb)
	{
		switch_to_blog( $blog_id );
		$global_blog_upload_max_space = get_network_option( get_current_network_id(), 'blog_upload_space' );
		if ($new_quota_in_mb < 0) {
			return new WP_Error('Quota would be negative', 'Quota would be negative', ['status' => 422]);
		}
		if ( $new_quota_in_mb != (int) $global_blog_upload_max_space ) {
			update_option( 'blog_upload_space', $new_quota_in_mb );
		} else {
			delete_option( 'blog_upload_space' );
		}
		restore_current_blog();

		return $this->determine_quotas($blog_id);
	}

	private function add_quota(int $blog_id, int $amount_quota_in_mb)
	{
		switch_to_blog( $blog_id );
		$current_quota = $this->determine_quotas($blog_id);


		return $this->set_quota($blog_id, $current_quota->quota + $amount_quota_in_mb);
	}
}