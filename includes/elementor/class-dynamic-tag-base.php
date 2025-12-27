<?php
if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

abstract class VEV_Elementor_Dynamic_Tag_Base extends \Elementor\Core\DynamicTags\Tag {

        public function get_group(): string {
                return 've-events';
        }

        protected function get_post_id(): int {
                global $post;

                if ( isset( $post->ID ) && get_post_type( $post->ID ) === VEV_Events::POST_TYPE ) {
                        return (int) $post->ID;
                }

                $queried_id = get_queried_object_id();
                if ( $queried_id && get_post_type( $queried_id ) === VEV_Events::POST_TYPE ) {
                        return (int) $queried_id;
                }

                $post_id = get_the_ID();
                if ( $post_id && get_post_type( $post_id ) === VEV_Events::POST_TYPE ) {
                        return (int) $post_id;
                }

                if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
                        $preview_id = (int) ( $_GET['preview_id'] ?? 0 );
                        if ( $preview_id && get_post_type( $preview_id ) === VEV_Events::POST_TYPE ) {
                                return $preview_id;
                        }
                }

                return (int) ( $post_id ?: 0 );
        }

        protected function is_event_post(): bool {
                $post_id = $this->get_post_id();
                return $post_id && get_post_type( $post_id ) === VEV_Events::POST_TYPE;
        }
}
