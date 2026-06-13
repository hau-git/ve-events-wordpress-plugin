<?php
/**
 * Elementor dynamic tag for the VE Events location maps URL.
 *
 * @package VE_Events
 */

namespace VEV\Integrations\Elementor\Tags;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use VEV\Fields\Registry;

/**
 * Renders the event location maps URL.
 */
class LocationUrlTag extends AbstractTag {

	/**
	 * Stable dynamic tag name.
	 *
	 * @return string Tag name.
	 */
	public function get_name(): string {
		return 've-events-location-url';
	}

	/**
	 * Human-readable tag title.
	 *
	 * @return string Tag title.
	 */
	public function get_title(): string {
		return __( 'Event Location Maps URL', 've-events' );
	}

	/**
	 * Elementor categories the tag applies to.
	 *
	 * @return array Category constants.
	 */
	public function get_categories(): array {
		return array(
			\Elementor\Modules\DynamicTags\Module::URL_CATEGORY,
			\Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY,
		);
	}

	/**
	 * Register the tag's controls.
	 */
	protected function register_controls(): void {}

	/**
	 * Render the resolved maps URL.
	 */
	public function render(): void {
		if ( ! $this->is_event_post() ) {
			return;
		}
		$url = Registry::get_field_value( 've_location_maps_url', $this->get_post_id() );
		echo esc_url( (string) $url );
	}
}
