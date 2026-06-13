<?php
/**
 * Elementor dynamic tag for an arbitrary VE Events field.
 *
 * @package VE_Events
 */

namespace VEV\Integrations\Elementor\Tags;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use VEV\Constants;
use VEV\Fields\Registry;

/**
 * Renders any registered VE Events field by key.
 */
class EventFieldTag extends AbstractTag {

	/**
	 * Stable dynamic tag name.
	 *
	 * @return string Tag name.
	 */
	public function get_name(): string {
		return 've-events-field';
	}

	/**
	 * Human-readable tag title.
	 *
	 * @return string Tag title.
	 */
	public function get_title(): string {
		return __( 'Event Field', 've-events' );
	}

	/**
	 * Elementor categories the tag applies to.
	 *
	 * @return array Category constants.
	 */
	public function get_categories(): array {
		return array( \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY );
	}

	/**
	 * Register the tag's controls.
	 */
	protected function register_controls(): void {
		$field_options = Registry::get_fields_for_dropdown();

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

	/**
	 * Render the resolved field value.
	 */
	public function render(): void {
		$field_key = $this->get_settings( 'field_key' );

		if ( empty( $field_key ) ) {
			return;
		}

		$post_id = $this->get_post_id();

		if ( ! $post_id || get_post_type( $post_id ) !== Constants::POST_TYPE ) {
			return;
		}

		$value = Registry::get_formatted_value( $field_key, $post_id );

		echo wp_kses_post( $value );
	}
}
