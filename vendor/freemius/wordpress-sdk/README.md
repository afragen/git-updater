Freemius WordPress SDK
======================

Welcome to the official repository for the Freemius SDK! Adding the SDK to your WordPress plugin, theme, or add-ons, enables all the benefits that come with using the [Freemius platform](https://freemius.com) such as:

* [Software Licensing](https://freemius.com/wordpress/software-licensing/)
* [Secure Checkout](https://freemius.com/wordpress/checkout/)
* [Subscriptions](https://freemius.com/wordpress/recurring-payments-subscriptions/)
* [Automatic Updates](https://freemius.com/wordpress/automatic-software-updates/)
* [Seamless EU VAT](https://freemius.com/wordpress/collecting-eu-vat-europe/)
* [Cart Abandonment Recovery](https://freemius.com/wordpress/cart-abandonment-recovery/)
* [Affiliate Platform](https://freemius.com/wordpress/affiliate-platform/)
* [Analytics & Usage Tracking](https://freemius.com/wordpress/insights/)
* [User Dashboard](https://freemius.com/wordpress/user-dashboard/)

* [Monetization](https://freemius.com/wordpress/)
* [Analytics](https://freemius.com/wordpress/insights/)
* [More...](https://freemius.com/wordpress/features-comparison/)

Freemius truly empowers developers to create prosperous subscription-based businesses.

If you're new to Freemius then we recommend taking a look at our [Getting Started](https://freemius.com/help/documentation/getting-started/) guide first.

If you're a WordPress plugin or theme developer and are interested in monetizing with Freemius then you can [sign-up for a FREE account](https://dashboard.freemius.com/register/):

https://dashboard.freemius.com/register/

Once you have your account setup and are familiar with how it all works you're ready to begin [integrating Freemius](https://freemius.com/help/documentation/wordpress-sdk/integrating-freemius-sdk/) into your WordPress product 

You can see some of the existing WordPress.org plugins & themes that are already utilizing the power of Freemius here:

* https://profiles.wordpress.org/freemius/#content-plugins
* https://includewp.com/freemius/#focus

## Code Documentation

You can find the SDK's documentation here:
https://freemius.com/help/documentation/wordpress-sdk/

## Integrating & Initializing the SDK

As part of the integration process, you'll need to [add the latest version](https://freemius.com/help/documentation/getting-started/#add_the_latest_wordpress_sdk_into_your_product) of the Freemius SDK into your WordPress project.

Then, when you've completed the [SDK integration form](https://freemius.com/help/documentation/getting-started/#fill_out_the_sdk_integration_form) a snippet of code is generated which you'll need to copy and paste into the top of your main plugin's PHP file, right after the plugin's header comment.

Note: For themes, this will be in the root `functions.php` file instead.

A typical SDK snippet will look similar to the following (your particular snippet may differ slightly depending on your integration):

```php
if ( ! function_exists( 'my_prefix_fs' ) ) {
    // Create a helper function for easy SDK access.
    function my_prefix_fs() {
        global $my_prefix_fs;

        if ( ! isset( $my_prefix_fs ) ) {
            // Include Freemius SDK.
            require_once dirname(__FILE__) . '/freemius/start.php';

            $my_prefix_fs = fs_dynamic_init( array(
                'id'                  => '1234',
                'slug'                => 'my-new-plugin',
                'premium_slug'        => 'my-new-plugin-premium',
                'type'                => 'plugin',
                'public_key'          => 'pk_bAEfta69seKymZzmf2xtqq8QXHz9y',
                'is_premium'          => true,
                // If your plugin is a serviceware, set this option to false.
                'has_premium_version' => true,
                'has_paid_plans'      => true,
                'is_org_compliant'    => true,
                'menu'                => array(
                    'slug'           => 'my-new-plugin',
                    'parent'         => array(
                        'slug' => 'options-general.php',
                    ),
                ),
                // Set the SDK to work in a sandbox mode (for development & testing).
                // IMPORTANT: MAKE SURE TO REMOVE SECRET KEY BEFORE DEPLOYMENT.
                'secret_key'          => 'sk_ubb4yN3mzqGR2x8#P7r5&@*xC$utE',
            ) );
        }

        return $my_prefix_fs;
    }

    // Init Freemius.
    my_prefix_fs();
    // Signal that SDK was initiated.
    do_action( 'my_prefix_fs_loaded' );
}

```

## Usage example

You can call anySDK methods by prefixing them with the shortcode function for your particular plugin/theme (specified when completing the SDK integration form in the Developer Dashboard):

```php
<?php my_prefix_fs()->get_upgrade_url(); ?>
```

Or when calling Freemius multiple times in a scope, it's recommended to use it with the global variable:

```php
<?php
    global $my_prefix_fs;
    $my_prefix_fs->get_account_url();
?>
```

There are many other SDK methods available that you can use to enhance the functionality of your WordPress product. Some of the more common use-cases are covered in the [Freemius SDK Gists](https://freemius.com/help/documentation/wordpress-sdk/gists/) documentation.

## Adding license based logic examples

Add marketing content to encourage your users to upgrade for your paid version:

```php
<?php
    if ( my_prefix_fs()->is_not_paying() ) {
        echo '<section><h1>' . esc_html__('Awesome Premium Features', 'my-plugin-slug') . '</h1>';
        echo '<a href="' . my_prefix_fs()->get_upgrade_url() . '">' .
            esc_html__('Upgrade Now!', 'my-plugin-slug') .
            '</a>';
        echo '</section>';
    }
?>
```

Add logic which will only be available in your premium plugin version:

```php
<?php
    // This "if" block will be auto removed from the Free version.
    if ( my_prefix_fs()->is__premium_only() ) {
    
        // ... premium only logic ...
        
    }
?>
```

To add a function which will only be available in your premium plugin version, simply add __premium_only as the suffix of the function name. Just make sure that all lines that call that method directly or by hooks, are also wrapped in premium only logic:

```php
<?php
    class My_Plugin {
        function init() {
            ...

            // This "if" block will be auto removed from the free version.
            if ( my_prefix_fs()->is__premium_only() ) {
                // Init premium version.
                $this->admin_init__premium_only();

                add_action( 'admin_init', array( &$this, 'admin_init_hook__premium_only' );
            }

            ...
        }

        // This method will be only included in the premium version.
        function admin_init__premium_only() {
            ...
        }

        // This method will be only included in the premium version.
        function admin_init_hook__premium_only() {
            ...
        }
    }
?>
```

Add logic which will only be executed for customers in your 'professional' plan:

```php
<?php
    if ( my_prefix_fs()->is_plan('professional', true) ) {
        // .. logic related to Professional plan only ...
    }
?>
```

Add logic which will only be executed for customers in your 'professional' plan or higher plans:

```php
<?php
    if ( my_prefix_fs()->is_plan('professional') ) {
        // ... logic related to Professional plan and higher plans ...
    }
?>
```

Add logic which will only be available in your premium plugin version AND will only be executed for customers in your 'professional' plan (and higher plans):

```php
<?php
    // This "if" block will be auto removed from the Free version.
    if ( my_prefix_fs()->is_plan__premium_only('professional') ) {
        // ... logic related to Professional plan and higher plans ...
    }
?>
```

Add logic only for users in trial:

```php
<?php
    if ( my_prefix_fs()->is_trial() ) {
        // ... logic for users in trial ...
    }
?>
```

Add logic for specified paid plan:

```php
<?php
    // This "if" block will be auto removed from the Free version.
    if ( my_prefix_fs()->is__premium_only() ) {
        if ( my_prefix_fs()->is_plan( 'professional', true ) ) {

            // ... logic related to Professional plan only ...

        } else if ( my_prefix_fs()->is_plan( 'business' ) ) {

            // ... logic related to Business plan and higher plans ...

        }
    }
?>
```

## Excluding files and folders from the free plugin version
There are [two ways](https://freemius.com/help/documentation/wordpress-sdk/software-licensing/#excluding_files_and_folders_from_the_free_plugin_version) to exclude files from your free version. 

1. Add `__premium_only` just before the file extension. For example, functions__premium_only.php will be only included in the premium plugin version. This works for all types of files, not only PHP.
2. Add `@fs_premium_only` a special meta tag to the plugin's main PHP file header. Example:
```php
<?php
	/**
	 * Plugin Name: My Very Awesome Plugin
	 * Plugin URI:  http://my-awesome-plugin.com
	 * Description: Create and manage Awesomeness right in WordPress.
	 * Version:     1.0.0
	 * Author:      Awesomattic
	 * Author URI:  http://my-awesome-plugin.com/me/
	 * License:     GPLv2
	 * Text Domain: myplugin
	 * Domain Path: /langs
	 *
	 * @fs_premium_only /lib/functions.php, /premium-files/
	 */

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}
    
    // ... my code ...
?>
```
In the example plugin header above, the file `/lib/functions.php` and the directory `/premium-files/` will be removed from the free plugin version.

# WordPress.org Compliance
Based on [WordPress.org Guidelines](https://wordpress.org/plugins/about/guidelines/) you are not allowed to submit a plugin that has premium code in it:
> All code hosted by WordPress.org servers must be free and fully-functional. If you want to sell advanced features for a plugin (such as a "pro" version), then you must sell and serve that code from your own site, we will not host it on our servers.

Therefore, if you want to deploy your free plugin's version to WordPress.org, make sure you wrap all your premium code with `if ( my_prefix_fs()->{{ method }}__premium_only() )` or use [some of the other methods](https://freemius.com/help/documentation/wordpress-sdk/software-licensing/) provided by the SDK to exclude premium features & files from the free version.

## Deployment
Zip your Freemius product’s root folder and [upload it in the Deployment section](https://freemius.com/help/documentation/selling-with-freemius/deployment/) in the *Freemius Developer's Dashboard*. 
The plugin/theme will automatically be scanned and processed by a custom-developed *PHP Processor* which will auto-generate two versions of your plugin:

1. **Premium version**: Identical to your uploaded version, including all code (except your `secret_key`). Will be enabled for download ONLY for your paying or in trial customers.
2. **Free version**: The code stripped from all your paid features (based on the logic added wrapped in `{ method }__premium_only()`). 

The free version is the one that you should give your users to download. Therefore, download the free generated version and upload to your site. Or, if your plugin was WordPress.org compliant and you made sure to exclude all your premium code with the different provided techniques, you can deploy the downloaded free version to the .org repo.

## License
Copyright (c) Freemius®, Inc.

Licensed under the GNU general public license (version 3).

## Contributing

Please see our [contributing guide](CONTRIBUTING.md).
