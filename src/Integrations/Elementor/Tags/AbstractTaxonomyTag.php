<?php
/**
 * Shared base for VE Events taxonomy-backed Elementor dynamic tags.
 *
 * @package VE_Events
 */

namespace VEV\Integrations\Elementor\Tags;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use VEV\Fields\Registry;

/**
 * Common rendering for taxonomy-backed dynamic tags.
 *
 * Subclasses define their own stable name, title and resolved field key so
 * each tag preserves the exact identity Elementor persists in page data.
 */
abstract class AbstractTaxonomyTag extends AbstractTag {

	/**
	 * Elementor categories the tag applies to.
	 *
	 * @return array Category constants.
	 */
	public function get_categories(): array {
		return array( \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY );
	}

	/**
	 * Resolve the field key to render for this tag.
	 *
	 * @return string Field key.
	 */
	abstract protected function get_field_key(): string;

	/**
	 * Render the resolved field value.
	 */
	public function render(): void {
		if ( ! $this->is_event_post() ) {
			return;
		}
		echo wp_kses_post( Registry::get_field_value( $this->get_field_key(), $this->get_post_id() ) );
	}
}
