<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VEV_Elementor_Dynamic_Tag_Event_Field extends VEV_Elementor_Dynamic_Tag_Base {

	public function get_name(): string {
		return 've-events-field';
	}

	public function get_title(): string {
		return __( 'Event Field', 've-events' );
	}

	public function get_categories(): array {
		return array( \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY );
	}

	protected function register_controls(): void {
		$field_options = VEV_Fields::get_fields_for_dropdown();

		$this->add_control(
			'field_key',
			array(
				'label'   => __( 'Field', 've-events' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => $field_options,
				'default' => 've_datetime_formatted',
			)
		);
	}

	public function render(): void {
		$field_key = $this->get_settings( 'field_key' );

		if ( empty( $field_key ) ) {
			return;
		}

		$post_id = $this->get_post_id();

		if ( ! $post_id || get_post_type( $post_id ) !== VEV_Events::POST_TYPE ) {
			return;
		}

		$value = VEV_Fields::get_formatted_value( $field_key, $post_id );

		echo wp_kses_post( $value );
	}
}
