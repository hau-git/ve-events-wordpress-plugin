<?php
/**
 * Elementor dynamic tag for the VE Events series taxonomy.
 *
 * @package VE_Events
 */

namespace VEV\Integrations\Elementor\Tags;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the event series name.
 */
class SeriesTag extends AbstractTaxonomyTag {

	/**
	 * Stable dynamic tag name.
	 *
	 * @return string Tag name.
	 */
	public function get_name(): string {
		return 've-events-series';
	}

	/**
	 * Human-readable tag title.
	 *
	 * @return string Tag title.
	 */
	public function get_title(): string {
		return __( 'Event Series', 've-events' );
	}

	/**
	 * Register the tag's controls.
	 */
	protected function register_controls(): void {}

	/**
	 * Resolve the field key to render.
	 *
	 * @return string Field key.
	 */
	protected function get_field_key(): string {
		return 've_series_name';
	}
}
