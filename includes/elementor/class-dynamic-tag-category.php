<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VEV_Elementor_Dynamic_Tag_Category extends VEV_Elementor_Dynamic_Tag_Base {

	public function get_name(): string {
		return 've-events-category';
	}

	public function get_title(): string {
		return __( 'Event Category', 've-events' );
	}

	public function get_categories(): array {
		return [ \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY ];
	}

	protected function register_controls(): void {
		$this->add_control( 'category_field', [
			'label'   => __( 'Field', 've-events' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'options' => [
				've_category_name'  => __( 'Category Name', 've-events' ),
				've_category_color' => __( 'Category Color', 've-events' ),
			],
			'default' => 've_category_name',
		] );
	}

	public function render(): void {
		if ( ! $this->is_event_post() ) {
			return;
		}
		$field_key = $this->get_settings( 'category_field' );
		echo wp_kses_post( VEV_Fields::get_field_value( $field_key, $this->get_post_id() ) );
	}
}
