<?php



class f8r_Controller extends WP_REST_Controller {
	private $f8r_rest_namespace = 'featurizer/v1';
	private $rest_route_base = '/site/(?P<siteurl>[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,}))';

	function register_rest_route()
	{
		add_action( 'rest_api_init', function () {
			register_rest_route( $this->f8r_rest_namespace, $this->rest_route_base . '/feature/(?P<vendor>[a-zA-Z]+)/(?P<group>[a-zA-Z]+)/(?P<feature>[a-zA-Z_-]+)',
			[
				array (
					'methods' => WP_REST_Server::READABLE,
					'callback' =>  [$this, 'route_status_feature'],
					'permission_callback' => [$this, 'check_if_user_is_authorized']
				),
				array (
					'methods' => WP_REST_Server::EDITABLE,
					'callback' =>  [$this, 'route_enable_feature'],
					'permission_callback' => [$this, 'check_if_user_is_authorized']
				),
				array (
					'methods' => WP_REST_Server::DELETABLE,
					'callback' =>  [$this, 'route_disable_feature'],
					'permission_callback' => [$this, 'check_if_user_is_authorized']
				)
			]);
			register_rest_route( $this->f8r_rest_namespace, $this->rest_route_base . '/features',
				[
					array (
						'methods' => WP_REST_Server::READABLE,
						'callback' =>  [$this, 'route_list_features'],
						'permission_callback' => [$this, 'check_if_user_is_authorized']
					)
				]);
		} );
	}
	function route_list_features(WP_REST_Request $r) {
		$blog_id = $this->get_blog_id_from_url($r);
		if (is_wp_error($blog_id)) {
			return $blog_id;
		}
		$result = f8r_get_all_features($blog_id);
		return $this->parse_and_return_result($result);
	}
	function route_status_feature(WP_REST_Request $r) {
		$blog_id = $this->get_blog_id_from_url($r);
		if (is_wp_error($blog_id)) {
			return $blog_id;
		}
		$result = f8r_is_feature_enabled($r->get_param('vendor'), $r->get_param('group'), $r->get_param('feature'), $blog_id);
		return $this->parse_and_return_result($result);
	}

	function route_enable_feature(WP_REST_Request $r) {
		$blog_id = $this->get_blog_id_from_url($r);
		if (is_wp_error($blog_id)) {
			return $blog_id;
		}
		$result = f8r_enable_feature($r->get_param('vendor'), $r->get_param('group'), $r->get_param('feature'), $blog_id);
		return $this->parse_and_return_result($result);
	}

	function route_disable_feature(WP_REST_Request $r) {
		$blog_id = $this->get_blog_id_from_url($r);
		if (is_wp_error($blog_id)) {
			return $blog_id;
		}
		$result = f8r_disable_feature($r->get_param('vendor'), $r->get_param('group'), $r->get_param('feature'), $blog_id);
		return $this->parse_and_return_result($result);
	}


	/**
	 * Get the current blog id from given url. If site is not found a WP_Error is returned instead
	 * @param WP_REST_Request $r the request
	 *
	 * @return int|WP_Error the id of the blog or an WP_Error if blog was not found
	 */
	function get_blog_id_from_url(WP_REST_Request $r)
	{
		$blog_id = get_blog_id_from_url($r->get_param('siteurl'));
		if ($blog_id == 0) {
			return new WP_Error('site_not_found', 'Site not found.', ['status' => 404]);
		}
		return $blog_id;
	}

	/**
	 * Checks authorization of user for calling these endpoints
	 * @return bool true if user is authorized, false otherwise
	 */
	function check_if_user_is_authorized()
	{
		return current_user_can('manage_network');
	}

	/**
	 *
	 * Parses and returns the result of routing methods. WP_Errors will be thrown directly. If the WP_Error is kind of "FeatureNotFound" a 404 will be returned. If everything is fine, the result is packed into a 200 response
	 * @param mixed $result the returned result of the routing methods, which can be a WP_Error object or array with data
	 *
	 * @return mixed WP_Error in case of failure or WP_REST_Response with result (or 404 response if feature not found)
	 */
	function parse_and_return_result(mixed $result): mixed {
		if (is_wp_error($result)) {
			if ($result->get_error_code() == 'FeatureNotFound') {
				return new WP_REST_Response( array( 'code' => $result->get_error_code(), 'message' => $result->get_error_message(), 'data' => null), 404 );
			}
			else {
				return $result;
			}
		} else {
			return new WP_REST_Response( array( 'result' => $result ), 200 );
		}
	}
}