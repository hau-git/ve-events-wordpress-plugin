<?php
/**
 * Elementor dynamic tag for the VE Events category taxonomy.
 *
 * @package VE_Events
 */

namespace VEV\Integrations\Elementor\Tags;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the event category name or color.
 */
class CategoryTag extends AbstractTaxonomyTag {

	/**
	 * Stable dynamic tag name.
	 *
	 * @return string Tag name.
	 */
	public function get_name(): string {
		return 've-events-category';
	}

	/**
	 * Human-readable tag title.
	 *
	 * @return string Tag title.
	 */
	public function get_title(): string {
		return __( 'Event Category', 've-events' );
	}

	/**
	 * Register the tag's controls.
	 */
	protected function register_controls(): void {
		$this->add_control(
			'category_field',
			array(
				'label'   => __( 'Field', 've-events' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => array(
					've_category_name'  => __( 'Category Name', 've-events' ),
					've_category_color' => __( 'Category Color', 've-events' ),
				),
				'default' => 've_category_name',
			)
		);
	}

	/**
	 * Resolve the field key from the selected control.
	 *
	 * @return string Field key.
	 */
	protected function get_field_key(): string {
		return (string) $this->get_settings( 'category_field' );
	}
}
