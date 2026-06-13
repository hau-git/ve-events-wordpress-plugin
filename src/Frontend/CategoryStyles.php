<?php
/**
 * Outputs inline CSS custom properties for event category colors.
 *
 * @package VE_Events
 */

namespace VEV\Frontend;

use VEV\Constants;
use VEV\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Emits per-term category color variables and JetEngine filter styling.
 */
final class CategoryStyles {

	/**
	 * Register the wp_head output.
	 */
	public static function init(): void {
		add_action( 'wp_head', array( __CLASS__, 'output' ), 5 );
	}

	/**
	 * Print the inline category color stylesheet.
	 */
	public static function output(): void {
		$settings = Settings::get();
		if ( empty( $settings['output_category_colors'] ) ) {
			return;
		}
		$terms = get_terms(
			array(
				'taxonomy'   => Constants::TAX_CATEGORY,
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return;
		}
		$vars  = '';
		$rules = '';
		$jet   = '';
		update_termmeta_cache( $terms );

		foreach ( $terms as $term_id ) {
			$color = (string) get_term_meta( (int) $term_id, Constants::TERM_META_CATEGORY_COLOR, true );
			if ( ! $color || ! preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color ) ) {
				continue;
			}
			$term = get_term( (int) $term_id, Constants::TAX_CATEGORY );
			if ( ! $term || is_wp_error( $term ) ) {
				continue;
			}
			$slug   = sanitize_html_class( $term->slug );
			$color  = esc_attr( $color );
			$vars  .= '--vev-cat-' . $slug . ':' . $color . ';';
			$rules .= '.ve-cat-' . $slug . '{--vev-cat-color:' . $color . ';}';

			// JetEngine Smart Filter checkboxes – target by slug AND term_id.
			// (JetEngine uses either depending on filter configuration).
			foreach ( array( esc_attr( $term->slug ), (int) $term_id ) as $val ) {
				$base = '.jet-checkboxes-filter__item:has(input[value="' . $val . '"])';
				$jet .= $base . ' .jet-check-label{background-color:' . $color . ';padding:2px 8px;border-radius:3px;}';
				$jet .= $base . ' .jet-check-label::before{content:"";display:inline-block;width:10px;height:10px;border-radius:50%;background:' . $color . ';margin-right:6px;vertical-align:middle;}';
			}
		}
		if ( ! $rules ) {
			return;
		}
		echo '<style id="vev-category-colors">:root{' . $vars . '}' . $rules . $jet . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
