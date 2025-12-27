<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VEV_Elementor_Dynamic_Tag_Event_URL extends VEV_Elementor_Dynamic_Tag_Base {

	public function get_name(): string {
		return 've-events-url';
	}

	public function get_title(): string {
		return __( 'Event URL', 've-events' );
	}

	public function get_categories(): array {
		return array( \Elementor\Modules\DynamicTags\Module::URL_CATEGORY );
	}

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

	public function render(): void {
		$url_type = $this->get_settings( 'url_type' );
		$fallback = $this->get_settings( 'fallback_to_permalink' );

		$post_id = $this->get_post_id();

		if ( ! $post_id || get_post_type( $post_id ) !== VEV_Events::POST_TYPE ) {
			return;
		}

		$url = '';

		if ( 'info_url' === $url_type ) {
			$url = get_post_meta( $post_id, '_vev_info_url', true );

			if ( empty( $url ) && 'yes' === $fallback ) {
				$url = get_permalink( $post_id );
			}
		} else {
			$url = get_permalink( $post_id );
		}

		echo esc_url( $url );
	}
}
