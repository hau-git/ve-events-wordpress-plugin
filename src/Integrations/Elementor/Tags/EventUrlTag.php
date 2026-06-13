<?php
/**
 * Elementor dynamic tag for the VE Events info URL / permalink.
 *
 * @package VE_Events
 */

namespace VEV\Integrations\Elementor\Tags;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use VEV\Constants;

/**
 * Renders the event info URL or permalink with an optional fallback.
 */
class EventUrlTag extends AbstractTag {

	/**
	 * Stable dynamic tag name.
	 *
	 * @return string Tag name.
	 */
	public function get_name(): string {
		return 've-events-url';
	}

	/**
	 * Human-readable tag title.
	 *
	 * @return string Tag title.
	 */
	public function get_title(): string {
		return __( 'Event URL / Permalink', 've-events' );
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
	protected function register_controls(): void {
		$this->add_control(
			'url_type',
			array(
				'label'   => __( 'URL Type', 've-events' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => array(
					'info_url'  => __( 'Info URL', 've-events' ),
					'permalink' => __( 'Event Permalink', 've-events' ),
				),
				'default' => 'info_url',
			)
		);

		$this->add_control(
			'fallback_to_permalink',
			array(
				'label'     => __( 'Fallback to Permalink', 've-events' ),
				'type'      => \Elementor\Controls_Manager::SWITCHER,
				'default'   => 'yes',
				'condition' => array(
					'url_type' => 'info_url',
				),
			)
		);
	}

	/**
	 * Render the resolved URL.
	 */
	public function render(): void {
		$url_type = $this->get_settings( 'url_type' );
		$fallback = $this->get_settings( 'fallback_to_permalink' );

		$post_id = $this->get_post_id();

		if ( ! $post_id || get_post_type( $post_id ) !== Constants::POST_TYPE ) {
			return;
		}

		$url = '';

		if ( 'info_url' === $url_type ) {
			$url = get_post_meta( $post_id, Constants::META_INFO_URL, true );

			if ( empty( $url ) && 'yes' === $fallback ) {
				$url = get_permalink( $post_id );
			}
		} else {
			$url = get_permalink( $post_id );
		}

		echo esc_url( $url );
	}
}
