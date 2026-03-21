<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VEV_Elementor_Dynamic_Tag_Topic extends VEV_Elementor_Dynamic_Tag_Base {

	public function get_name(): string {
		return 've-events-topic';
	}

	public function get_title(): string {
		return __( 'Event Topic(s)', 've-events' );
	}

	public function get_categories(): array {
		return [ \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY ];
	}

	protected function register_controls(): void {}

	public function render(): void {
		if ( ! $this->is_event_post() ) {
			return;
		}
		echo wp_kses_post( VEV_Fields::get_field_value( 've_topic_names', $this->get_post_id() ) );
	}
}
