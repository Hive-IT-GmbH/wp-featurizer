<?php
/**
 * WP-Featurizer (F8R)
 *
 * THIS PLUGIN HAS TO BE INSTALLED AS MU-PLUGIN
 *
 * @package           Hive-IT-GmbH/wp-featurizer
 * @author            Hive-IT GmbH
 * @copyright         2021 Hive-IT GmbH
 * @license           GPL-3.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:   WP-Featurizer (F8R)
 * Plugin URI:    https://github.com/Hive-IT-GmbH/wp-featurizer
 * Description:   This plugin allows you to use Feature Flags in Plugins and Themes. This plugin requires a WP-Multisite and control over the code and options.
 * Version:       1.3.1
 * Author:        Hive-IT-GmbH
 * Author URI:    https://hive-it.de/
 * License:       GPLv3 or later
 * Network:       true
 */
define( 'WP_ENVIRONMENT_TYPE', 'local' );
if ( ! defined( 'ABSPATH' ) ) {
	return false;
}
include_once 'f8r-wp-api/f8r_Controller.php';

/**
 * Register global features
 *
 * @param string $vendor
 * @param string $group
 * @param string $feature
 */
function f8r_register_feature( string $vendor, string $group, string $feature ) {

	global $f8r_registered_features;

	$vendor  = sanitize_key( $vendor );
	$group   = sanitize_key( $group );
	$feature = sanitize_key( $feature );

	// Unchange already registered feature or register the feature
	$f8r_registered_features[ $vendor ][ $group ][ $feature ] = $f8r_registered_features[ $vendor ][ $group ][ $feature ] ?? array();
}

/**
 * Enable feature
 *
 * @param string $vendor
 * @param string $group
 * @param string $feature
 * @param int $blog_id
 *
 * @return bool
 */
function f8r_enable_feature( string $vendor, string $group, string $feature = '', int $blog_id = 0 ): bool {

	global $f8r_registered_features;
	$vendor  = sanitize_key( $vendor );
	$group   = sanitize_key( $group );
	$feature = sanitize_key( $feature );
	$blog_id = absint( $blog_id );

	// Is group or feature registered?
	if ( ! f8r_check_registered_features( 'f8r_enable_feature', $vendor, $group, $feature ) ) {
		return false;
	}
	
	if ( $blog_id == 0 ) {
		return false;
	} else {
		switch_to_blog( $blog_id );
	}

	// Already enabled
	if ( f8r_is_feature_enabled( $vendor, $group, $feature ) ) {
		return true;
	}

	$blog_features = get_option( 'f8r_features', array() );

	if ( '' !== $feature ) {
		// enable single feature
		$blog_features[ $vendor ][ $group ][ $feature ] = true;
	} else {
		// enable all group-features
		foreach ( $f8r_registered_features[ $vendor ][ $group ] as $group_feature => $value ) {
			$blog_features[ $vendor ][ $group ][ $group_feature ] = true;
		}
	}
	$updated = update_option( 'f8r_features', $blog_features );

	if ( $blog_id != 0 ) {
		restore_current_blog();
	}

	return $updated;
  
}

/**
 * Disable feature
 *
 * @param string $vendor
 * @param string $group
 * @param string $feature
 * @param int $blog_id
 *
 * @return bool
 */
function f8r_disable_feature( string $vendor, string $group, string $feature = '', int $blog_id = 0 ): bool {
	global $f8r_registered_features;

	$vendor  = sanitize_key( $vendor );
	$group   = sanitize_key( $group );
	$feature = sanitize_key( $feature );
	$blog_id = absint( $blog_id );	

	// Is group or feature registered?
	if ( ! f8r_check_registered_features( 'f8r_disable_feature', $vendor, $group, $feature ) ) {
		return false;
	}

	if ( $blog_id == 0 ) {
		return false;
	} else {
		switch_to_blog( $blog_id );
	}

	// Already disabled
	if ( ! f8r_is_feature_enabled( $vendor, $group, $feature ) ) {
		return true;
	}

	$blog_features = get_option( 'f8r_features', array() );

	// disable/remove all group-features
	foreach ( $f8r_registered_features[ $vendor ][ $group ] as $group_feature => $feature_data ) {
		if ( ( $group_feature == $feature && '' !== $feature ) || '' === $feature ) {
			unset( $blog_features[ $vendor ][ $group ][ $group_feature ]);
		}
		if ( empty( $blog_features[ $vendor ][ $group ][ $group_feature ] ) ) {
			unset( $blog_features[ $vendor ][ $group ][ $group_feature ] );
		}
	}

	// remove empty data
	if ( empty( $blog_features[ $vendor ][ $group ] ) ) {
		unset( $blog_features[ $vendor ][ $group ] );
	}

	if ( empty( $blog_features[ $vendor ] ) ) {
		unset( $blog_features[ $vendor ] );
	}
	$updated = false;
	if ( empty( $blog_features ) ) {
		$updated = delete_option( 'f8r_features' );
	} else {
		$updated = update_option( 'f8r_features', $blog_features );
	}

	restore_current_blog();

	return $updated;

}

/**
 * Check for enabled features
 *
 * @param string $vendor
 * @param string $group
 * @param string $feature
 * @param int $blog_id
 *
 * @return bool
 */
function f8r_is_feature_enabled( string $vendor, string $group, string $feature = '', int $blog_id = 0 ): bool {

	global $f8r_registered_features;

	$vendor     = sanitize_key( $vendor );
	$group      = sanitize_key( $group );
	$feature    = sanitize_key( $feature );
	$blog_id    = absint( $blog_id );
	$is_enabled = true;

	// Shortcircuit enabled check
	/**
	 * Filter
	 */
	$enabled_filter = apply_filters('f8r/is_feature_enabled', null, $vendor, $group, $feature);
	if ( null !== $enabled_filter ) {
		return $enabled_filter;
	}

	// Is group or feature registered?
	if ( ! f8r_check_registered_features( 'f8r_is_feature_enabled', $vendor, $group, $feature ) ) {
		// TODO throw error if feature does not exist?
		return false;
	}

	if ( $blog_id != 0 ) {
		switch_to_blog( $blog_id );
	}
	
	$blog_features = get_option( 'f8r_features', array() );

	if ( '' !== $feature ) {
		// check against single feature
		if ( ! ( $blog_features[ $vendor ][ $group ][ $feature ] ?? false ) ) {
			$is_enabled = false;
		}
	} else {
		// check against group
		if ( ! isset( $blog_features[ $vendor ][ $group ] ) ) {
			$is_enabled = false;
		}
		// Are all feature enabled?
		if ( $is_enabled ) {
			foreach ( $f8r_registered_features[ $vendor ][ $group ] as $registered_feature => $value ) {
				if ( ! ( $blog_features[ $vendor ][ $group ][ $registered_feature ] ?? false ) ) {
					$is_enabled = false;
					break;
				}
			}
		}
	}

	if ( $blog_id != 0 ) {
		restore_current_blog();
	}
	
	return (bool) $is_enabled;
}

/**
 * Get all features from current site
 *
 * @param int $blog_id
 *
 * @return array
 */
function f8r_get_all_features( int $blog_id = 0 ): array|null {
	global $f8r_registered_features;
	
	$all_features =	$f8r_registered_features;
	$global_features_data = get_site_option( 'f8r_features', array() );

	if ( $blog_id != 0 ) {
		switch_to_blog( $blog_id );
	}
	
	$blog_features = get_option( 'f8r_features', array() );

	if ( $all_features ) {
		// sorting vendors
		ksort( $all_features );
		foreach ( $all_features as $vendor => $groups ) {
			// sorting groups
			ksort( $all_features[ $vendor ] );
			foreach ( $groups as $group => $features ) {
				// sorting features
				ksort( $all_features[ $vendor ][ $group ] );
				foreach ( $features as $feature => $enabled ) {
                    $all_features[ $vendor ][ $group ][ $feature ]['enabled'] = $blog_features[ $vendor ][ $group ][ $feature ]['enabled'] ?? false;
					// Add global feature data
					$all_features[ $vendor ][ $group ][ $feature ]['teaser_title']     = $global_features_data[ $vendor ][ $group ][ $feature ]['teaser_title'] ?? '';
					$all_features[ $vendor ][ $group ][ $feature ]['teaser_text_html'] = $global_features_data[ $vendor ][ $group ][ $feature ]['teaser_text_html'] ?? '';
					$all_features[ $vendor ][ $group ][ $feature ]['teaser_url']       = $global_features_data[ $vendor ][ $group ][ $feature ]['teaser_url'] ?? '';
				}
			}
		}
	}
	if ( $blog_id != 0 ) {
		restore_current_blog();
	}

	return $all_features;
}

/**
 * Update global feature data
 *
 * @param array $feature_data
 *
 * @return bool
 */
function f8r_update_feature( array $feature_data ): bool {

	global $f8r_registered_features;

	$vendor           = sanitize_key( $feature_data['vendor'] ?? '' );
	$group            = sanitize_key( $feature_data['group'] ?? '' );
	$feature          = sanitize_key( $feature_data['feature'] ?? '' );
	$teaser_title     = sanitize_text_field( $feature_data['teaser_title'] ?? '' );
	$teaser_text_html = wp_kses_post( $feature_data['teaser_text_html'] ?? '' );
	$teaser_url       = esc_url_raw( $feature_data['teaser_url'] ?? '' );

	// Is feature registered?
	if ( ! f8r_check_registered_features( 'f8r_update_feature', $vendor, $group, $feature, true ) ) {
		return false;
	}

	$f8r_registered_features[ $vendor ][ $group ][ $feature ]['teaser_title']     = $teaser_title;
	$f8r_registered_features[ $vendor ][ $group ][ $feature ]['teaser_text_html'] = $teaser_text_html;
	$f8r_registered_features[ $vendor ][ $group ][ $feature ]['teaser_url']       = $teaser_url;
	update_site_option( 'f8r_features', $f8r_registered_features );

	return true;
}

/**
 * Check registered features
 *
 * @param string $function_name
 * @param string $vendor
 * @param string $group
 * @param string $feature
 * @param false $feature_check
 *
 * @return bool
 */
function f8r_check_registered_features( string $function_name, string $vendor, string $group, string $feature = '', $feature_check = false ): bool {
	global $f8r_registered_features;

	$function_name = sanitize_key( $function_name );
	$vendor        = sanitize_key( $vendor );
	$group         = sanitize_key( $group );
	$feature       = sanitize_key( $feature );

	// Is group or feature registered?
	if ( ! $f8r_registered_features || ! isset( $f8r_registered_features[ $vendor ][ $group ] ) || ( ! isset( $f8r_registered_features[ $vendor ][ $group ][ $feature ] ) && ( '' !== $feature || $feature_check ) ) ) {
		_doing_it_wrong( $function_name, sprintf( 'Feature: %s or group: %s not found!', $feature, $group ), '5.8.1' );

		return false;
	}

	return true;
}

// Work with wp-cli
if ( defined( 'WP_CLI' ) && WP_CLI && method_exists( 'WP_CLI', 'add_command' ) ) {
	require_once __DIR__ . '/f8r-wp-cli/class-featurizer-wp-cli-command.php';
	WP_CLI::add_command( 'f8r', 'Featurizer_WP_CLI_Command' );
}

$f8rController = new f8r_Controller();
$f8rController->register_rest_route();