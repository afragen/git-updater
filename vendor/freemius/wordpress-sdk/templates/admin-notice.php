<?php
	/**
	 * @package     Freemius
	 * @copyright   Copyright (c) 2015, Freemius, Inc.
	 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
	 * @since       1.0.3
	 */

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * @var array $VARS
	 */

	$dismiss_text = fs_text_x_inline( 'Dismiss', 'as close a window', 'dismiss' );

	$slug = '';
	$type = '';

	if ( ! empty( $VARS['manager_id'] ) ) {
		/**
		 * @var array $VARS
		 */
		$slug = $VARS['manager_id'];

		$type = WP_FS__MODULE_TYPE_PLUGIN;

		if ( false !== strpos( $slug, ':' ) ) {
			$parts = explode( ':', $slug );

			$slug = $parts[0];

			$parts_count = count( $parts );

			if ( 1 < $parts_count && WP_FS__MODULE_TYPE_THEME == $parts[1] ) {
				$type = $parts[1];
			}
		}
	}

	$attributes = array();
	if ( ! empty( $VARS['id'] ) ) {
		$attributes['data-id'] = $VARS['id'];
	}
	if ( ! empty( $VARS['manager_id'] ) ) {
		$attributes['data-manager-id'] = $VARS['manager_id'];
	}
	if ( ! empty( $slug ) ) {
		$attributes['data-slug'] = $slug;
	}
	if ( ! empty( $type ) ) {
		$attributes['data-type'] = $type;
	}

	$classes = array( 'fs-notice' );
	switch ( $VARS['type'] ) {
		case 'error':
			$classes[] = 'error';
			$classes[] = 'form-invalid';
			break;
		case 'promotion':
			$classes[] = 'updated';
			$classes[] = 'promotion';
			break;
		case 'warn':
			$classes[] = 'notice';
			$classes[] = 'notice-warning';
			break;
		case 'update':
		case 'success':
		default:
			$classes[] = 'updated';
			$classes[] = 'success';
			break;
	}
	if ( ! empty( $VARS['sticky'] ) ) {
		$classes[] = 'fs-sticky';
	}
	if ( ! empty( $VARS['plugin'] ) ) {
		$classes[] = 'fs-has-title';
	}
	if ( ! empty( $slug ) ) {
		$classes[] = "fs-slug-{$slug}";
	}
	if ( ! empty( $type ) ) {
		$classes[] = "fs-type-{$type}";
	}
?>
<div class="<?php echo fs_html_get_classname( $classes ); ?>" <?php echo fs_html_get_attributes( $attributes ); ?>>
	<?php if ( ! empty( $VARS['plugin'] ) ) : ?>
		<label class="fs-plugin-title">
			<?php echo esc_html( $VARS['plugin'] ); ?>
		</label>
	<?php endif ?>

	<?php if ( ! empty( $VARS['sticky'] ) && ( ! isset( $VARS['dismissible'] ) || false !== $VARS['dismissible'] ) ) : ?>
		<div class="fs-close">
			<i class="dashicons dashicons-no" title="<?php echo esc_attr( $dismiss_text ) ?>"></i>
			<span><?php echo esc_html( $dismiss_text ); ?></span>
		</div>
	<?php endif ?>

	<div class="fs-notice-body">
		<?php if ( ! empty( $VARS['title'] ) ) : ?>
			<strong><?php echo fs_html_get_sanitized_html( $VARS['title'] ); ?></strong>
		<?php endif ?>

		<?php echo fs_html_get_sanitized_html( $VARS['message'] ); ?>
	</div>
</div>
