# WP-Featurizer (F8R)

This plugin allows you to use Feature Flags in Plugins and Themes. This plugin can only be used inside a WP-Multisite.
Sell your Features and control them using WP-CLI in your own controlled WP Multisite.

## Install

Clone this plugin into the `wp-content/mu-plugins` folder. Make sure to add an mu-autoloader or add a php file to the `mu-plugins` folder and `require_once` the `wp-featurizer/wp-featurizer.php` in there.

## Registering Features

Featues have to be registered to be able to control them via WP-Admin and the Network Admin.
There is an API to register Features.

``` php
f8r_register_feature( string $vendor, string $group, string $feature );
```

Examples:
```php
f8r_register_feature( 'hiveit', 'login', 'password_reset' );
f8r_register_feature( 'hiveit', 'login', 'remember_me' );
f8r_register_feature( 'hiveit', 'portfolio', 'display_contactform' );
```

## Checkig for Features

To check whether a Feature is enabled or not use the following code

### To prevent errors if F8R is not active on the current installation implement this function first in your Plugin / Theme:
```php
if ( ! function_exists( 'f8r_is_feature_enabled' ) ): bool {
  function f8r_is_feature_enabled( $vendor = "", $group = "", $feature = "" ) {
	  return true;
  }
}
```

### Check for a single Feature:

```php
if ( f8r_is_feature_enabled( 'vendor', 'group', 'feature' ) ) {
	... do Stuff if Feature is enabled ...
}
```

### Check for a whole Feature Group:

```php
if ( f8r_is_feature_enabled( 'vendor', 'group' ) ) {
	... do Stuff if Feature group is enabled ...
}
```

## Managing Features

### Enable a single Feature

```php
f8r_enable_feature( 'vendor', 'group', 'feature' );
```

### Disable a single Feature

```php
f8r_disable_feature( 'vendor', 'group', 'feature' );
```

### Enable a Feature Group
Enables every feature in this group

```php
f8r_enable_feature( 'vendor', 'group' );
```

### Disable a Feature Group
Disables every feature in this group

```php
f8r_disable_feature( 'vendor', 'group' );
```

## WP-Admin API

### Get all Features

The `enabled` property is returned from the current site. The `teaser_` fields are taken from the global settings.

```
f8r_get_all_features(): array[
	f8r_feature: object {
		vendor: string,
		group: string,
		feature: string,
		teaser_title: string,
		teaser_text_html: string,
		teaser_url: string,
        	enabled: bool
	}
]
```

### Update a single Feature

Stores additional `teaser_` information into the database for a registered feature globally.

```
f8r_update_feature(f8r_feature: object {
		vendor,
		group,
		feature,
		teaser_title,
		teaser_text_html,
		teaser_url
	})
```

## Using the WP-CLI API

WP-Featurizer can be controlled using the WP-CLI

### Check for registered Features

``` bash
	wp f8r list
```

Lists all registered Features in the Multisite installation

### Get status of registered Feature

```bash
	wp f8r get <vendor> <group> <feature> [--url]
```

Returns `true | false | undefined (if feature is not registered)`

### Get status of registered Feature Group

```bash
	wp f8r get <vendor> <group> [--url]
```
Checks for each feature in a group and returns only `true` if EVERY feature in the group is active
Returns `true | false | undefined (if feature group is not registered)`

### Enable a Feature on the current site

```bash
	wp f8r enable <vendor> <group> <feature> [--url]
```

### Disable a Feature on the current site

``` bash
	wp f8r disable <vendor> <group> <feature> [--url]
```

### Enables all Features of a group

```bash 
	wp f8r enable <vendor> <group> [--url]
```

### Disables all Features of a group

```bash
	wp f8r disable <vendor> <group> [--url]
```

