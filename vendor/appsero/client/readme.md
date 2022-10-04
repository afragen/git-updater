# Appsero - Client

- [Installation](#installation)
- [Insights](#insights)
- [Dynamic Usage](#dynamic-usage)


## Installation

You can install AppSero Client in two ways, via composer and manually.

### 1. Composer Installation

Add dependency in your project (theme/plugin):

```
composer require appsero/client
```

Now add `autoload.php` in your file if you haven't done already.

```php
require __DIR__ . '/vendor/autoload.php';
```

### 2. Manual Installation

Clone the repository in your project.

```
cd /path/to/your/project/folder
git clone https://github.com/AppSero/client.git appsero
```

Now include the dependencies in your plugin/theme.

```php
require __DIR__ . '/appsero/src/Client.php';
```

## Insights

AppSero can be used in both themes and plugins.

The `Appsero\Client` class has *three* parameters:

```php
$client = new Appsero\Client( $hash, $name, $file );
```

- **hash** (*string*, *required*) - The unique identifier for a plugin or theme.
- **name** (*string*, *required*) - The name of the plugin or theme.
- **file** (*string*, *required*) - The **main file** path of the plugin. For theme, path to `functions.php`

### Usage Example

Please refer to the **installation** step before start using the class.

You can obtain the **hash** for your plugin for the [Appsero Dashboard](https://dashboard.appsero.com). The 3rd parameter **must** have to be the main file of the plugin.

```php
/**
 * Initialize the tracker
 *
 * @return void
 */
function appsero_init_tracker_appsero_test() {

    if ( ! class_exists( 'Appsero\Client' ) ) {
        require_once __DIR__ . '/appsero/src/Client.php';
    }

    $client = new Appsero\Client( 'a4a8da5b-b419-4656-98e9-4a42e9044891', 'Akismet', __FILE__ );

    // Active insights
    $client->insights()->init();

    // Active automatic updater
    $client->updater();

    // Active license page and checker
    $args = array(
        'type'       => 'options',
        'menu_title' => 'Akismet',
        'page_title' => 'Akismet License Settings',
        'menu_slug'  => 'akismet_settings',
    );
    $client->license()->add_settings_page( $args );
}

appsero_init_tracker_appsero_test();
```

Make sure you call this function directly, never use any action hook to call this function.

> For plugins example code that needs to be used on your main plugin file.
> For themes example code that needs to be used on your themes `functions.php` file.

## More Usage

```php
$client = new Appsero\Client( 'a4a8da5b-b419-4656-98e9-4a42e9044892', 'Twenty Twelve', __FILE__ );
```

#### 1. Hiding the notice

Sometimes you wouldn't want to show the notice, or want to customize the notice message. You can do that as well.

```php
$client->insights()
       ->hide_notice()
       ->init();
```

#### 2. Customizing the notice message

```php
$client->insights()
       ->notice( 'My Custom Notice Message' )
       ->init();
```

#### 3. Adding extra data

You can add extra metadata from your theme or plugin. In that case, the **keys** has to be whitelisted from the Appsero dashboard.
`add_extra` method also support callback as parameter, If you need database call then callback is best for you.

```php
$metadata = array(
    'key'     => 'value',
    'another' => 'another_value'
);
$client->insights()
       ->add_extra( $metadata )
       ->init();
```

Or if you want to run a query then pass callback, we will call the function when it is necessary.

```php
$metadata = function () {
    $total_posts = wp_count_posts();

    return array(
        'total_posts' => $total_posts,
        'another'     => 'another_value'
    );
};
$client->insights()
       ->add_extra( $metadata )
       ->init();
```

#### 4. Set textdomain

You may set your own textdomain to translate text.

```php
$client->set_textdomain( 'your-project-textdomain' );
```




#### 5. Get Plugin Data
If you want to get the most used plugins with your plugin or theme, send the active plugins' data to Appsero.
```php
$client->insights()
       ->add_plugin_data()
       ->init();
```
---

#### 6. Set Notice Message
Change opt-in message text
```php
$client->insights()
       ->notice("Your custom notice text")
       ->init();
```
---

### Check License Validity

Check your plugin/theme is using with valid license or not, First create a global variable of `License` object then use it anywhere in your code.
If you are using it outside of same function make sure you global the variable before using the condition.

```php
$client = new Appsero\Client( 'a4a8da5b-b419-4656-98e9-4a42e9044892', 'Twenty Twelve', __FILE__ );

$args = array(
    'type'        => 'submenu',
    'menu_title'  => 'Twenty Twelve License',
    'page_title'  => 'Twenty Twelve License Settings',
    'menu_slug'   => 'twenty_twelve_settings',
    'parent_slug' => 'themes.php',
);

global $twenty_twelve_license;
$twenty_twelve_license = $client->license();
$twenty_twelve_license->add_settings_page( $args );

if ( $twenty_twelve_license->is_valid()  ) {
    // Your special code here
}

Or check by pricing plan title

if ( $twenty_twelve_license->is_valid_by( 'title', 'Business' ) ) {
    // Your special code here
}

// Set custom options key for storing the license info
$twenty_twelve_license->set_option_key( 'my_plugin_license' );
```

### Use your own license form

You can easily manage license by creating a form using HTTP request. Call `license_form_submit` method from License object.

```php
global $twenty_twelve_license; // License object
$twenty_twelve_license->license_form_submit([
    '_nonce'      => wp_create_nonce( 'Twenty Twelve' ), // create a nonce with name
    '_action'     => 'active', // active, deactive
    'license_key' => 'random-license-key', // no need to provide if you want to deactive
]);
if ( ! $twenty_twelve_license->error ) {
    // license activated
    $twenty_twelve_license->success; // Success message is here
} else {
    $twenty_twelve_license->error; // has error message here
}
```

### Set Custom Deactivation Reasons

First set your deactivation reasons in Appsero dashboard then map them in your plugin/theme using filter hook.

- **id** is the deactivation slug
- **text** is the deactivation title
- **placeholder** will show on textarea field
- **icon** You can set SVG icon with 23x23 size

```php
add_filter( 'appsero_custom_deactivation_reasons', function () {
    return [
        [
            'id'          => 'looks-buggy',
            'text'        => 'Looks buggy',
            'placeholder' => 'Can you please tell which feature looks buggy?',
            'icon'        => '',
        ],
        [
            'id'          => 'bad-ui',
            'text'        => 'Bad UI',
            'placeholder' => 'Could you tell us a bit more?',
            'icon'        => '',
        ],
    ];
} );
```

## Credits

Created and maintained by [Appsero](https://appsero.com).
