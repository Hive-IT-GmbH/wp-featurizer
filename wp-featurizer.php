<?php
/*
Plugin: WP-Featurizer (F8R)
Plugin URI: https://github.com/Hive-IT-GmbH/wp-featurizer
Description: This plugin allows you to use Feature Flags in Plugins and Themes. This plugin is primarily intended to be used inside a WP-Multisite. Sell your Features and control them using WP-CLI in a WP Multisite.
Version: 1.0
Author: Hive-IT-GmbH
Author URI: https://hive-it.de/
License: GPLv3 or later
Network: true
*/

if ( ! defined( 'ABSPATH' ) ) {
	return false;
}

/**
 * Register global features
 *
 * @param string $vendor
 * @param string $group
 * @param string $feature
 */
function f8r_register_feature( string $vendor, string $group, string $feature ) {

	$vendor       = sanitize_key( $vendor );
	$group        = sanitize_key( $group );
	$feature      = sanitize_key( $feature );
	$main_site_id = get_main_site_id();

	$registered_features = get_network_option( $main_site_id, 'f8r_features', array() );

	// Unchange already registered feature or register the feature with additional data
	$registered_features[ $vendor ][ $group ][ $feature ] = $registered_features[ $vendor ][ $group ][ $feature ] ?? array( 'teaser_title' => '', 'teaser_text_html' => '', 'teaser_url' => '' );
	update_network_option( $main_site_id, 'f8r_features', $registered_features );
}

/**
 * Enable feature
 *
 * @param string $vendor
 * @param string $group
 * @param string $feature
 * @param int|null $network_id (null = current network id)
 */
function f8r_enable_feature( string $vendor, string $group, string $feature = '', int $network_id = null ) {

	$vendor  = sanitize_key( $vendor );
	$group   = sanitize_key( $group );
	$feature = sanitize_key( $feature );

	// Is group or feature registered?
	$registered_features = get_network_option( get_main_site_id(), 'f8r_features', false );
	if ( ! $registered_features || ! isset( $registered_features[ $vendor ][ $group ] ) || ( ! isset( $registered_features[ $vendor ][ $group ][ $feature ] ) && '' !== $feature ) ) {
		_doing_it_wrong( 'f8r_enable_feature', sprintf( 'Feature: %s in group: %s not found!', $feature, $group ), '5.8.1' );

		return false;
	}

	$site_features = get_network_option( $network_id, 'f8r_features', false );

	if ( '' !== $feature ) {
		// enable single feature
		$site_features[ $vendor ][ $group ][ $feature ] = true;
	} else {
		// enable all group-features
		foreach ( $registered_features[ $vendor ][ $group ] as $group_feature => $value ) {
			$site_features[ $vendor ][ $group ][ $group_feature ] = true;
		}
	}
	update_network_option( $network_id, 'f8r_features', $site_features );

}

/**
 * Disable feature
 *
 * @param string $vendor
 * @param string $group
 * @param string $feature
 * @param int|null $network_id (null = current network id)
 */
function f8r_disable_feature( string $vendor, string $group, string $feature = '', int $network_id = null ) {
	$vendor  = sanitize_key( $vendor );
	$group   = sanitize_key( $group );
	$feature = sanitize_key( $feature );

	// Is group or feature registered?
	$registered_features = get_network_option( get_main_site_id(), 'f8r_features', false );
	if ( ! $registered_features || ! isset( $registered_features[ $vendor ][ $group ] ) || ( ! isset( $registered_features[ $vendor ][ $group ][ $feature ] ) && '' !== $feature ) ) {
		_doing_it_wrong( 'f8r_disable_feature', sprintf( 'Feature: %s in group: %s not found!', $feature, $group ), '5.8.1' );

		return false;
	}

	$site_features = get_network_option( $network_id, 'f8r_features', false );

	if ( '' !== $feature ) {
		// disable/remove single feature
		unset( $site_features[ $vendor ][ $group ][ $feature ] );
	} else {
		// disable/remove all group-features
		unset( $site_features[ $vendor ][ $group ] );
	}

	// remove empty data
	if ( empty( $site_features[ $vendor ] ) ) {
		unset( $site_features[ $vendor ] );
	}

	if ( empty( $site_features ) ) {
		delete_network_option( $network_id, 'f8r_features' );

		return;
	}

	update_network_option( $network_id, 'f8r_features', $site_features );
}

/**
 * Check for enabled features
 *
 * @param string $vendor
 * @param string $group
 * @param string $feature
 * @param int|null $network_id (null = current network id)
 *
 * @return bool
 */
function f8r_is_feature_enabled( string $vendor, string $group, string $feature = '', int $network_id = null ) {

	$vendor     = sanitize_key( $vendor );
	$group      = sanitize_key( $group );
	$feature    = sanitize_key( $feature );
	$is_enabled = true;

	// Is group or feature registered?
	$registered_features = get_network_option( get_main_site_id(), 'f8r_features', false );
	if ( ! $registered_features || ! isset( $registered_features[ $vendor ][ $group ] ) || ( ! isset( $registered_features[ $vendor ][ $group ][ $feature ] ) && '' !== $feature ) ) {
		_doing_it_wrong( 'f8r_is_feature_enabled', sprintf( 'Feature: %s in group: %s not found!', $feature, $group ), '5.8.1' );

		return false;
	}

	$site_features = get_network_option( $network_id, 'f8r_features', false );

	if ( '' !== $feature ) {
		// check against single feature
		if ( ! isset( $site_features[ $vendor ][ $group ][ $feature ] ) ) {
			$is_enabled = false;
		}
	} else {
		// check against group
		if ( ! isset( $site_features[ $vendor ][ $group ] ) ) {
			$is_enabled = false;
		}
		// Are all feature enabled?
		if ( $is_enabled ) {
			foreach ( $registered_features[ $vendor ][ $group ] as $registered_feature => $value ) {
				if ( ! isset( $site_features[ $vendor ][ $group ][ $registered_feature ] ) ) {
					$is_enabled = false;
					break;
				}
			}
		}
	}

	return (bool) $is_enabled;
}

/**
 * @param int|null $network_id (null = current network id)
 *
 * @return array
 */
function f8r_get_all_features( int $network_id = null ) {

	$all_features  = get_network_option( get_main_site_id(), 'f8r_features', false );
	$site_features = get_network_option( $network_id, 'f8r_features', false );

	if ( $all_features ) {
		foreach ( $all_features as $vendor => $groups ) {
			foreach ( $groups as $group => $features ) {
				foreach ( $features as $feature => $enabled ) {
					$all_features[ $vendor ][ $group ][ $feature ]['enabled'] = array_key_exists( $feature, $site_features[ $vendor ][ $group ] );
				}
			}
		}
	}

	return $all_features;
}

/**
 * Update feature
 *
 * @param array $feature_data
 *
 * @return false|void
 */
function f8r_update_feature( array $feature_data ) {

	$vendor           = sanitize_key( $feature_data['vendor'] );
	$group            = sanitize_key( $feature_data['group'] );
	$feature          = sanitize_key( $feature_data['feature'] );
	$teaser_title     = sanitize_text_field( $feature_data['teaser_title'] );
	$teaser_text_html = wp_kses_post( $feature_data['teaser_text_html'] );
	$teaser_url       = esc_url_raw( $feature_data['teaser_url'] );
	$main_site_id     = get_main_site_id();

	// Is feature registered?
	$registered_features = get_network_option( get_main_site_id(), 'f8r_features', false );
	if ( ! $registered_features || ! isset( $registered_features[ $vendor ][ $group ][ $feature ] ) ) {
		_doing_it_wrong( 'f8r_update_feature', sprintf( 'Feature: %s in group: %s not found!', $feature, $group ), '5.8.1' );

		return false;
	}

	$registered_features[ $vendor ][ $group ][ $feature ]['teaser_title']     = $teaser_title;
	$registered_features[ $vendor ][ $group ][ $feature ]['teaser_text_html'] = $teaser_text_html;
	$registered_features[ $vendor ][ $group ][ $feature ]['teaser_url']       = $teaser_url;
	update_network_option( $main_site_id, 'f8r_features', $registered_features );

}