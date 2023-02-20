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
    public function listToDatabase()
    {
        $feature_list = f8r_get_all_features();
        $formatter_args = array(
            'format' => 'table',
            'fields' => array(
                'vendor',
                'group',
                'feature',
                // 'enabled'
            )
        );
        /*$feature_items = [];
        if ( $feature_list ) {
            foreach ( $feature_list as $vendor => $groups ) {
                foreach ( $groups as $group => $features ) {
                    foreach ( $features as $feature => $data ) {
                        $feature_items[] = array(
                            'vendor'  => $vendor,
                            'group'   => $group,
                            'feature' => $feature,
                            'enabled' => $data['enabled'],
                            'checked' => false
                        );
                    }
                }
            }
        } else {
            $feature_items[] = array(
                'vendor'  => '',
                'group'   => '',
                'feature' => '',
                'enabled' => false,
                'checked' => false
            );
        }*/

        /*
         * Checks following here
         */
        // Load necessary configurations
        $mw_features_container = get_option('mw_features_container');
        $mw_configurations_container = get_option('mw_configurations_container');
        $mw_customizer = get_option('theme_mods_enfold');
        $installed_plugins = get_plugins(); //TODO Raus und stattdessen unten direkt mit is_plugin_active arbeiten
        $shortcodes = [];
        $ignoredShortcodes = [];
        foreach (get_posts() as $wp_post)
        {
            $this->extractShortcodes($wp_post, $ignoredShortcodes, $shortcodes);
        }
        foreach (get_pages() as $wp_post)
        {
            $this->extractShortcodes($wp_post, $ignoredShortcodes, $shortcodes);
        }
        $shortcodes = array_unique($shortcodes);
        foreach ($shortcodes as $shortcode)
        {
            WP_CLI::log('The following shortcode was found and will be used for ShortCode Search: ' . $shortcode);
        }
        $ignoredShortcodes = array_unique($ignoredShortcodes);
        foreach ($ignoredShortcodes as $ignoredShortcode)
        {
            WP_CLI::log('The following shortcode will be ignored, because it seems to be escaped: ' . $ignoredShortcode);
        }


        $v = "maklerwerft";
        $g = "abmeldewerft";
        self::setFeatureEnabled($feature_list, $v, $g, 'enabled'            ,  array_search($g, array_column($installed_plugins, 'TextDomain'))); // TODO ist TextDomain das richtige Feld dafuer?
        $g = "accountdeaktivierung";
        self::setFeatureEnabled($feature_list, $v, $g, 'enabled'            , array_search($g, array_column($installed_plugins, 'TextDomain')));
        $g = "anfragewerft";
        self::setFeatureEnabled($feature_list, $v, $g, 'enabled'            , array_search($g, array_column($installed_plugins, 'TextDomain'))); // TODO Erfragen ob enabled oder Wishlist das richtige Feature ist
        $g = "benutzerwerft";
        self::setFeatureEnabled($feature_list, $v, $g, 'login_area'         , array_search($g, array_column($installed_plugins, 'TextDomain')));
        self::setFeatureEnabled($feature_list, $v, $g, 'XXXXXXXX'           , ($mw_configurations_container['mw_advanced_register_page_active']??false) == true); // TODO Featurename fehlt: Landungsbrücke
        self::setFeatureEnabled($feature_list, $v, $g, 'XXXXXXXX'           , ($mw_configurations_container['mw_maklervertrag_active']??false) == true); // TODO Featurename fehlt: Maklervertrag
        self::setFeatureEnabled($feature_list, $v, $g, 'XXXXXXXX'           ,!empty($mw_configurations_container['create_pdf_in_rent_process_file_part_ids'])); // TODO Featurename fehlt: Automatischer Mietprozess
        self::setFeatureEnabled($feature_list, $v, $g, 'XXXXXXXX'           ,($mw_configurations_container['mw_ben_agb_changed_active']??false) == true); // TODO Featurename fehlt: Datenschutz / AGB Änderungen
        $g = "bewerberwerft";
        self::setFeatureEnabled($feature_list, $v, $g, 'enabled'            , array_search($g, array_column($installed_plugins, 'TextDomain')));
        $g = "downloadwerft";
        self::setFeatureEnabled($feature_list, $v, $g, 'enabled'            ,  array_search($g, array_column($installed_plugins, 'TextDomain')));
        $g = "filialenwerft";
        self::setFeatureEnabled($feature_list, $v, $g, 'enabled'            ,  array_search($g, array_column($installed_plugins, 'TextDomain')));
        $g = "finanzierungswerft";
        self::setFeatureEnabled($feature_list, $v, $g, 'enabled'            ,  array_search($g, array_column($installed_plugins, 'TextDomain')));
        $g = "flowfactwerft";
        self::setFeatureEnabled($feature_list, $v, $g, 'enabled'            ,  array_search($g, array_column($installed_plugins, 'TextDomain')));
        $g = "geolagenWerft"; // TODO Hier wird das W groß geschrieben, wo kam das nochmal her und ist das entscheidend / richtig so?
        self::setFeatureEnabled($feature_list, $v, $g, 'enabled'            ,  array_search($g, array_column($installed_plugins, 'TextDomain')));
        $g = "immowerft";
        self::setFeatureEnabled($feature_list, $v, $g, 'advanced_card_sort' , ($mw_features_container['mw_card_sorting_active']??false) == true);
        self::setFeatureEnabled($feature_list, $v, $g, 'bonitaet'          , ($mw_configurations_container['mw_bonitaetscheck']??false) == true);
        self::setFeatureEnabled($feature_list, $v, $g, 'auction'            , ($mw_features_container['mw_auction_active']??false) == true);
        self::setFeatureEnabled($feature_list, $v, $g, 'XXXXXXXX'           , ($mw_configurations_container['immodepot_data_room_active']??false) == true); //TODO Featurename fehlt: Datenraum
        self::setFeatureEnabled($feature_list, $v, $g, 'immodepot'          , in_array('immowerft_online_property_depot', $shortcodes));
        self::setFeatureEnabled($feature_list, $v, $g, 'XXXXXXXXX'          , ($mw_customizer['ben_watchlist_active']??false) == true); //TODO Featurename fehlt: Merkliste im Login-Bereich
        self::setFeatureEnabled($feature_list, $v, $g, 'XXXXXXXXX'          , strcasecmp(trim($mw_customizer['immowerft_select_template_neubau']??''), 'immowerft_template_onoffice_auto') == 0); //TODO Featurename fehlt: Neubau-Generator
        self::setFeatureEnabled($feature_list, $v, $g, 'XXXXXXXXX'          , in_array('immowerft_new_building_units', $shortcodes)); // TODO Featurename fehlt: Neubau-Modul
        self::setFeatureEnabled($feature_list, $v, $g, 'ogulo'              , !empty($mw_customizer['immowerft_setting_ogulo_tour_firmen_key']));
        self::setFeatureEnabled($feature_list, $v, $g, 'enabled'            , array_search($g, array_column($installed_plugins, 'TextDomain')));
        $g = "kaeuferfinder";
        self::setFeatureEnabled($feature_list, $v, $g, 'enabled'            , array_search($g, array_column($installed_plugins, 'TextDomain')));
        $g = "kundenstimmenwerft";
        self::setFeatureEnabled($feature_list, $v, $g, 'enabled'            , array_search($g, array_column($installed_plugins, 'TextDomain')));
        $g = "marketingwerft";
        self::setFeatureEnabled($feature_list, $v, $g, 'enabled'            , array_search($g, array_column($installed_plugins, 'TextDomain')));
        $g = "marktkartenwerft";
        self::setFeatureEnabled($feature_list, $v, $g, 'enabled'            , array_search($g, array_column($installed_plugins, 'TextDomain')));
        $g = "mitarbeiterwerft";
        self::setFeatureEnabled($feature_list, $v, $g, 'enabled'            , array_search($g, array_column($installed_plugins, 'TextDomain')));
        $g = "onofficewerft";
        self::setFeatureEnabled($feature_list, $v, $g, 'enabled'            , array_search($g, array_column($installed_plugins, 'TextDomain'))); //TODO Featurename unsicher: Online-Exposé mit onOffice
        self::setFeatureEnabled($feature_list, $v, $g, 'XXXXXXXX'           ,  ($mw_configurations_container['mw_enable_sw_sync']??false) == true); //TODO Featurename fehlt: onOfficeWerftSync
        $g = "printwerft";
        self::setFeatureEnabled($feature_list, $v, $g, 'enabled'            , !empty($mw_customizer['printwerft_setting_template'])); //TODO Featurename unsicher: Dynamische PDF-Exposéerstellung
        self::setFeatureEnabled($feature_list, $v, $g, 'XXXXXXXXX'          , !empty(get_option('printwerft_lebenskreuzungen_firma'))); //TODO Featurename fehlt: Lebenskreuzungen
        $g = "propstackwerft";
        self::setFeatureEnabled($feature_list, $v, $g, 'enabled'            ,  array_search($g, array_column($installed_plugins, 'TextDomain')));
        $g = "terminwerft";
        self::setFeatureEnabled($feature_list, $v, $g, 'enabled'            ,  array_search($g, array_column($installed_plugins, 'TextDomain')));
        $g = "tippgeberwerft";
        self::setFeatureEnabled($feature_list, $v, $g, 'enabled'            ,  array_search($g, array_column($installed_plugins, 'TextDomain')));
        self::setFeatureEnabled($feature_list, $v, $g, 'XXXXXXXXX'          ,  !empty(get_option('mw_tippgeber_configurations_container')['mw_tippgeber_finanzierungstipp_special_sign'])); //TODO Featurename fehlt: Finanzierungstipp-Tool
        $g = "wertermittlungswerft";
        self::setFeatureEnabled($feature_list, $v, $g, 'enabled'            ,  array_search($g, array_column($installed_plugins, 'TextDomain')));

        // Check if there are features defined by plugins, which rules has not been implemented
        foreach ( $feature_list as $vendor => $groups ) {
            foreach ( $groups as $group => $features ) {
                foreach ( $features as $feature => $data ) {
                    if (!($data['checked'] ?? false))
                    {
                        WP_CLI::warning('Watch out! The following feature rule is not implemented and has not been checked, feature will be deactivated: ' . $vendor . "::" . $group . "::" . $feature);
                    }
	                // Directly save the enabled state to the feature. We don't wnat an array with 'checked' and 'enabled' at this point
					$feature_list[$vendor][$group][$feature] = $data["enabled"];
					if (!$data["enabled"])
					{
						unset($feature_list[$vendor][$group][$feature]);
					}
                }
            }
        }
        WP_CLI::confirm('Do you want to update the feature toggle list?');

        print_r($feature_list);
        if (update_option( 'f8r_features', $feature_list ))
        {
            WP_CLI::success('Die Einstellung wurde erfolgreich gesetzt');
        }
        else
        {
            WP_CLI::error('Der Wert konnte in der Datenbank nicht geupdated werden');
        }

        //$formatter = new Formatter( $formatter_args, null, 'site' );
        //$formatter->display_items( $feature_items );
    }

    private function setFeatureEnabled(array & $feature_list, string $vendor,string $group, string $feature, bool $enabled)
    {
        // Check if there is a feature check defined, but the corresponding feature has not been delivered by plugin / feature registration
        if (!isset($feature_list[$vendor][$group][$feature]))
        {
            WP_CLI::warning('The following feature-check has been configured but was not registered via module: ' . $vendor . '::' . $group . '::' . $feature);
        }
        else if ($feature_list[$vendor][$group][$feature]['enabled'] != $enabled)
        {
            WP_CLI::warning('The determined feature enablement (' . ($enabled?'true':'false') . ') differs from saved value (' . ($feature_list[$vendor][$group][$feature]['enabled']?'true':'false') . '): ' . $vendor . '::' . $group . '::' . $feature);
        }
        $feature_list[$vendor][$group][$feature]['enabled'] = $enabled;
        $feature_list[$vendor][$group][$feature]['checked'] = true;
    }

    /**
     * @param $wp_post
     * @param array $ignoredShortcodes
     * @param array $shortcodes
     * @return array
     */
    public function extractShortcodes($wp_post, array & $ignoredShortcodes, array & $shortcodes): array
    {
	    global $shortcode_tags;
		// print_r($shortcode_tags);
        $matches = [];
	    preg_match_all( '@\[([^<>&/\[\]\x00-\x20=]++)@', $wp_post->post_content, $matches );
	    print_r($matches);
		$tagnames = array_intersect( array_keys( $shortcode_tags ), $matches[1] );
	    print_r($tagnames);
	    $shortcodes = array_merge($shortcodes, $tagnames);
		return $shortcodes;
        /*$shortcodeCount = preg_match_all("^\[(.*?)\]^", $wp_post->post_content, $matches);
        if ($shortcodeCount > 0) {
            foreach ($matches[1] as $match) {
                // Getting shortcode name without params
                $matchParts = explode(' ', $match);
                // if the shortcode has been escaped it will start with a second square bracket
                if ($matchParts[0][0] == '[') {
                    $ignoredShortcodes[] = $matchParts[0];
                    continue; // Skip because escaped text
                }
                $shortcodes[] = $matchParts[0];
            }
        }*/
        return $matches;
    }
}


