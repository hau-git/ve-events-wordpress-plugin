<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VEV_Elementor_Dynamic_Tag_Location_URL extends VEV_Elementor_Dynamic_Tag_Base {

	public function get_name(): string {
		return 've-events-location-url';
	}

	public function get_title(): string {
		return __( 'Event Location Maps URL', 've-events' );
	}

	public function get_categories(): array {
		return [ \Elementor\Modules\DynamicTags\Module::URL_CATEGORY ];
	}

	protected function register_controls(): void {}

	public function render(): void {
		if ( ! $this->is_event_post() ) {
			return;
		}
		$url = VEV_Fields::get_field_value( 've_location_maps_url', $this->get_post_id() );
		echo esc_url( (string) $url );
	}
}
