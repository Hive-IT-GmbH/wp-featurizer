# WP-Featurizer (F8R)

This plugin allows you to use Feature Flags in Pugins and Themes. This plugin is primarily intended to be used inside a WP-Multisite.
Sell your Features and control them using WP-CLI in a WP Multisite.

The Features should be stored globally in the network options. They should be mirrored into the [WordPress Object Cache](https://developer.wordpress.org/reference/classes/wp_object_cache/) system for best possible access performance. The cache should be updated on all relevant calls registering features or saving global values into it.
The feature state should be saved as per site option. It should as well be cached. Enabled / Disabled checks should thus mostly run against the cache instead of the database. Only if none exists, the values should be pulled from the option and a cache should be created.
It is highly advised to use some persistent cachich like WP-Redis or Memcache.

The cache key will be `<vendor>.<group>.<feature>`. A cache group of `f8r` will be used for all cache entries.

This Plugin might be required to be installed as a MU-Plugin to be available before all plugins and themes are loaded.

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

To check whether a Feature is enabled or not use the following code. 

### To prevent errors if F8R is not active on the current installation implement this function first in your Plugin / Theme:
```php
if ( ! function_exists( 'f8r_is_feature_enabled' ) {
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
		teaser_text_html,
		teaser_url
	})
```

## Using the WP-CLI API

Package: hive-it/featurizer

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

