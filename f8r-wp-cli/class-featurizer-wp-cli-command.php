<?php
/**
 * WP-Featurizer (F8R): Enable, disable, get status and lists registered features
 */

use WP_CLI\Formatter;


class Featurizer_WP_CLI_Command extends WP_CLI_Command {

	/**
	 * Lists all registered Features in the multisite installation
	 *
	 * ## EXAMPLES
	 *
	 *     # Output a simple list of all registered features
	 *     $ wp f8r list
	 *
	 *     +--------+-----------+----------+
	 *     | vendor | group     | feature  |
	 *     +--------+-----------+----------+
	 *     | hiveit | login     | feature1 |
	 *     | hiveit | login     | feature2 |
	 *     | hiveit | portfolio | feature1 |
	 *     | hiveit | portfolio | feature2 |
	 *     +--------+-----------+----------+
	 *
	 * @param $args
	 * @param $assoc_args
	 */
	public function list( $args, $assoc_args ) {
		if ( ! is_multisite() ) {
			WP_CLI::error( 'This is not a multisite installation.' );
		}

		global $f8r_registered_features;

		$feature_items = array();
		if ( $f8r_registered_features ) {
			foreach ( $f8r_registered_features as $vendor => $groups ) {
				foreach ( $groups as $group => $features ) {
					foreach ( $features as $feature => $enabled ) {
						$feature_items[] = array(
							'vendor'  => $vendor,
							'group'   => $group,
							'feature' => $feature
						);
					}
				}
			}
		} else {
			$feature_items[] = array(
				'vendor'  => '',
				'group'   => '',
				'feature' => ''
			);
		}

		$formatter_args = array(
			'format' => 'table',
			'fields' => array(
				'vendor',
				'group',
				'feature'
			)
		);

		$formatter = new Formatter( $formatter_args, null, 'site' );
		$formatter->display_items( $feature_items );
	}

	/**
	 * Get status of the registered feature of the current blog
	 *
	 * ## OPTIONS
	 *
	 * <vendor>
	 * : The vendor name
	 *
	 * <group>
	 * : The group name
	 *
	 * [<feature>]
	 * : The feature (optional)
	 *
	 * [--url=<url>]
	 * : Url of the blog
	 *
	 * ## EXAMPLES
	 *
	 *      # Get the status of a single feature
	 *      $ wp f8r get <vendor> <group> <feature> [--url]
	 *
	 *      # Checks for each feature in a group and returns only true
	 *      # if EVERY feature in the group is active Returns true | false | undefined (if feature group is not registered)
	 *      $ wp f8r get <vendor> <group> [--url]
	 *
	 *      Success: true / false / undefined (if feature is not registered)
	 *
	 * @param $args
	 * @param $assoc_args
	 */
	public function get( $args, $assoc_args ) {
		if ( ! is_multisite() ) {
			WP_CLI::error( 'This is not a multisite installation.' );
		}

		$blog_id = get_current_blog_id();
		$blog    = get_blog_details( $blog_id );
		if ( ! $blog ) {
			WP_CLI::error( 'Site not found.' );
		}

		if ( empty( $args ) || count( $args ) < 2 ) {
			WP_CLI::error( 'Please specify [<vendor> <group> <feature>]' );
		}

		$vendor  = $args[0] ?? '';
		$group   = $args[1] ?? '';
		$feature = $args[2] ?? '';

		// Is group or feature registered?
		if ( ! f8r_check_registered_features( 'f8r_is_feature_enabled', $vendor, $group, $feature ) ) {
			WP_CLI::warning( "undefined" );

			return;
		}

		$is_enabled = f8r_is_feature_enabled( $vendor, $group, $feature, $blog_id );
		WP_CLI::success( $is_enabled ? 'true' : 'false' );
	}

	/**
	 * Enable a Feature or all group features on the current blog
	 *
	 * ## OPTIONS
	 *
	 * <vendor>
	 * : The vendor name
	 *
	 * <group>
	 * : The group name
	 *
	 * [<feature>]
	 * : The feature (optional)
	 *
	 * [--url=<url>]
	 * : Url of the blog
	 *
	 * ## EXAMPLES
	 *
	 *      # Enable a single feature
	 *      $ wp f8r enable <vendor> <group> <feature> [--url]
	 *
	 *      Success: The feature feature1 for http://multisite.local/en/ is successfully enabled.
	 *
	 *      # Enables all features in a group
	 *      $ wp f8r enable <vendor> <group> [--url]
	 *
	 *      Success: The features in the group: login for http://multisite.local/en/ are successfully enabled.
	 *
	 *
	 * @param $args
	 * @param $assoc_args
	 */
	public function enable( $args, $assoc_args ) {
		if ( ! is_multisite() ) {
			WP_CLI::error( 'This is not a multisite installation.' );
		}

		$blog_id = get_current_blog_id();
		$blog    = get_blog_details( $blog_id );
		if ( ! $blog ) {
			WP_CLI::error( 'Site not found.' );
		}

		if ( empty( $args ) || count( $args ) < 2 ) {
			WP_CLI::error( 'Please specify [<vendor> <group> <feature>]' );
		}

		$vendor  = $args[0] ?? '';
		$group   = $args[1] ?? '';
		$feature = $args[2] ?? '';

		// Is group or feature registered?
		if ( ! f8r_check_registered_features( 'f8r_is_feature_enabled', $vendor, $group, $feature ) ) {
			WP_CLI::error( 'Please specify [<vendor> <group> <feature>]' );

			return;
		}

		$site_url = trailingslashit( $blog->siteurl );

		f8r_enable_feature( $vendor, $group, $feature, $blog_id );
		if ( $feature ) {
			WP_CLI::success( "The feature: {$feature} for {$site_url} is successfully enabled." );
		} else {
			WP_CLI::success( "The features in the group: {$group} for {$site_url} are successfully enabled." );
		}


	}

	/**
	 * Disable a Feature or all group features on the current blog
	 *
	 * ## OPTIONS
	 *
	 * <vendor>
	 * : The vendor name
	 *
	 * <group>
	 * : The group name
	 *
	 * [<feature>]
	 * : The feature (optional)
	 *
	 * [--url=<url>]
	 * : Url of the blog
	 *
	 * ## EXAMPLES
	 *
	 *      # Disable a single feature
	 *      $ wp f8r disable <vendor> <group> <feature> [--url]
	 *
	 *      Success: The feature feature1 for http://multisite.local/en/ is successfully disabled.
	 *
	 *      # Disables all features in a group
	 *      $ wp f8r disable <vendor> <group> [--url]
	 *
	 *      Success: The features in the group: login for http://multisite.local/en/ are successfully disabled.
	 *
	 *
	 * @param $args
	 * @param $assoc_args
	 */
	public function disable( $args, $assoc_args ) {
		if ( ! is_multisite() ) {
			WP_CLI::error( 'This is not a multisite installation.' );
		}

		$blog_id = get_current_blog_id();
		$blog    = get_blog_details( $blog_id );
		if ( ! $blog ) {
			WP_CLI::error( 'Site not found.' );
		}

		if ( empty( $args ) || count( $args ) < 2 ) {
			WP_CLI::error( 'Please specify [<vendor> <group> <feature>]' );
		}

		$vendor  = $args[0] ?? '';
		$group   = $args[1] ?? '';
		$feature = $args[2] ?? '';

		// Is group or feature registered?
		if ( ! f8r_check_registered_features( 'f8r_is_feature_enabled', $vendor, $group, $feature ) ) {
			WP_CLI::error( 'Please specify [<vendor> <group> <feature>]' );

			return;
		}

		$site_url = trailingslashit( $blog->siteurl );

		f8r_disable_feature( $vendor, $group, $feature, $blog_id );
		if ( $feature ) {
			WP_CLI::success( "The feature: {$feature} for {$site_url} is successfully disabled." );
		} else {
			WP_CLI::success( "The features in the group: {$group} for {$site_url} are successfully disabled." );
		}
	}
}


