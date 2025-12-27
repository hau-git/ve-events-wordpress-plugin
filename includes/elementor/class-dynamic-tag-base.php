<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class VEV_Elementor_Dynamic_Tag_Base extends \Elementor\Core\DynamicTags\Tag {

	public function get_group(): string {
		return 've-events';
	}

	protected function get_post_id(): int {
		$post_id = get_the_ID();

		if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			$document = \Elementor\Plugin::$instance->documents->get_current();
			if ( $document ) {
				$post_id = $document->get_main_id();
			}
		}

		return (int) $post_id;
	}

	protected function is_event_post(): bool {
		$post_id = $this->get_post_id();
		return $post_id && get_post_type( $post_id ) === VEV_Events::POST_TYPE;
	}
}
