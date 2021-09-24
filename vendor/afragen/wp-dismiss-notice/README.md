# WP Dismiss Notice

Add time dismissible admin notices to WordPress.
Fork of https://github.com/w3guy/persist-admin-notices-dismissal

## Instuctions

Initialize the class.

`new \WP_Dismiss_Notice();` in your project.

Admin notice format.

 You must add `dependency-installer` to the admin notice class as well as `data-dismissible='dependency-installer-<plugin basename>-<timeout>'`
 to the admin notice div class. <timeout> values are from one day '1' to 'forever'. Default timeout is 14 days.

Example using WooCommerce with a 14 day dismissible notice.

```html
<div class="notice-warning notice is-dismissible dependency-installer" data-dismissible="dependency-installer-woocommerce-14">...</div>
```

Example filter to adjust timeout.
Use this filter to adjust the timeout for the dismissal. Default is 14 days.
This example filter can be used to modify the default timeout.
The example filter will change the default timout for all plugin dependencies.
You can specify the exact plugin timeout by modifying the following line in the filter.

```php
$timeout = 'woocommerce' !== $source ? $timeout : 30;
```

```php
add_filter(
 'wp_plugin_dependency_timeout',
 function( $timeout, $source ) {
     $timeout = basename( __DIR__ ) !== $source ? $timeout : 30;
     return $timeout;
 },
 10,
 2
);
```

Example of creating admin notice from afragen/wp-dependency-installer

```php
	/**
	 * Display admin notices / action links.
	 *
	 * @return bool/string false or Admin notice.
	 */
	public function admin_notices() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			return false;
		}
		foreach ( $this->notices as $notice ) {
			$status      = isset( $notice['status'] ) ? $notice['status'] : 'notice-info';
			$class       = esc_attr( $status ) . ' notice is-dismissible dependency-installer';
			$source      = isset( $notice['source'] ) ? $notice['source'] : __( 'Dependency' );
			$label       = esc_html( $this->get_dismiss_label( $source ) );
			$message     = '';
			$action      = '';
			$dismissible = '';

			if ( isset( $notice['message'] ) ) {
				$message = esc_html( $notice['message'] );
			}

			if ( isset( $notice['action'] ) ) {
				$action = sprintf(
					' <a href="javascript:;" class="wpdi-button" data-action="%1$s" data-slug="%2$s">%3$s Now &raquo;</a> ',
					esc_attr( $notice['action'] ),
					esc_attr( $notice['slug'] ),
					esc_html( ucfirst( $notice['action'] ) )
				);
			}
			if ( isset( $notice['slug'] ) ) {
				/**
				 * Filters the dismissal timeout.
				 *
				 * @since 1.4.1
				 *
				 * @param string|int '14'               Default dismissal in days.
				 * @param  string     $notice['source'] Plugin slug of calling plugin.
				 * @return string|int Dismissal timeout in days.
				 */
				$timeout     = apply_filters( 'wp_plugin_dependency_timeout', '14', $source );
				$dependency  = dirname( $notice['slug'] );
				$dismissible = empty( $timeout ) ? '' : sprintf( 'dependency-installer-%1$s-%2$s', esc_attr( $dependency ), esc_attr( $timeout ) );
			}
			if ( WP_Dismiss_Notice::is_admin_notice_active( $dismissible ) ) {
				printf(
					'<div class="%1$s" data-dismissible="%2$s"><p><strong>[%3$s]</strong> %4$s%5$s</p></div>',
					esc_attr( $class ),
					esc_attr( $dismissible ),
					esc_html( $label ),
					esc_html( $message ),
					$action // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				);
			}
		}
	}
```
