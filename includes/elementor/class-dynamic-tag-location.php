<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VEV_Elementor_Dynamic_Tag_Location extends VEV_Elementor_Dynamic_Tag_Base {

	public function get_name(): string {
		return 've-events-location';
	}

	public function get_title(): string {
		return __( 'Event Location', 've-events' );
	}

	public function get_categories(): array {
		return [ \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY ];
	}

	protected function register_controls(): void {
		$this->add_control( 'location_field', [
			'label'   => __( 'Field', 've-events' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'options' => [
				've_location_name'    => __( 'Location Name', 've-events' ),
				've_location_address' => __( 'Location Address', 've-events' ),
			],
			'default' => 've_location_name',
		] );
	}

	public function render(): void {
		if ( ! $this->is_event_post() ) {
			return;
		}
		$field_key = $this->get_settings( 'location_field' );
		echo wp_kses_post( VEV_Fields::get_field_value( $field_key, $this->get_post_id() ) );
	}
}
