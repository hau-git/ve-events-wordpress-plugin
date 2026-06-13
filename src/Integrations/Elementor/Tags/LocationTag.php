<?php
/**
 * Elementor dynamic tag for the VE Events location taxonomy.
 *
 * @package VE_Events
 */

namespace VEV\Integrations\Elementor\Tags;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the event location name or address.
 */
class LocationTag extends AbstractTaxonomyTag {

	/**
	 * Stable dynamic tag name.
	 *
	 * @return string Tag name.
	 */
	public function get_name(): string {
		return 've-events-location';
	}

	/**
	 * Human-readable tag title.
	 *
	 * @return string Tag title.
	 */
	public function get_title(): string {
		return __( 'Event Location', 've-events' );
	}

	/**
	 * Register the tag's controls.
	 */
	protected function register_controls(): void {
		$this->add_control(
			'location_field',
			array(
				'label'   => __( 'Field', 've-events' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => array(
					've_location_name'    => __( 'Location Name', 've-events' ),
					've_location_address' => __( 'Location Address', 've-events' ),
				),
				'default' => 've_location_name',
			)
		);
	}

	/**
	 * Resolve the field key from the selected control.
	 *
	 * @return string Field key.
	 */
	protected function get_field_key(): string {
		return (string) $this->get_settings( 'location_field' );
	}
}
