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
		} );
	}
	function route_status_feature(WP_REST_Request $r) {
		$blog_id = $this->get_blog_id_from_url($r);
		if (is_wp_error($blog_id)) {
			return $blog_id;
		}
		$result = f8r_is_feature_enabled($r->get_param('vendor'), $r->get_param('group'), $r->get_param('feature'), $blog_id);
		return new WP_REST_Response( array( 'err' => '', 'result' => $result, 'blub' => get_currentuserinfo() ), 200 );
	}
	function route_enable_feature(WP_REST_Request $r) {
		$blog_id = $this->get_blog_id_from_url($r);
		if (is_wp_error($blog_id)) {
			return $blog_id;
		}
		$result = f8r_enable_feature($r->get_param('vendor'), $r->get_param('group'), $r->get_param('feature'), $blog_id);
		return new WP_REST_Response( array( 'err' => '', 'result' => $result ), 200 );
	}

	function route_disable_feature(WP_REST_Request $r) {
		$blog_id = $this->get_blog_id_from_url($r);
		if (is_wp_error($blog_id)) {
			return $blog_id;
		}
		$result = f8r_disable_feature($r->get_param('vendor'), $r->get_param('group'), $r->get_param('feature'), $blog_id);
		return new WP_REST_Response( array( 'err' => '', 'result' => $result ), 200 );
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

	function check_if_user_is_authorized()
	{
		return current_user_can('manage_network');
	}
}