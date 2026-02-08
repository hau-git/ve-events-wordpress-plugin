<?php
if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

final class VEV_JetEngine {

        private static string $source_key = 've_events';

        public static function init(): void {
                if ( ! class_exists( 'Jet_Engine' ) ) {
                        return;
                }

                add_filter( 'jet-engine/listings/data/sources', array( __CLASS__, 'register_source' ) );
                add_filter( 'jet-engine/listings/dynamic-field/source-list', array( __CLASS__, 'add_to_source_list' ) );
                add_filter( 'jet-engine/listings/dynamic-field/field-options', array( __CLASS__, 'add_field_options' ), 10, 2 );
                add_filter( 'jet-engine/listings/dynamic-field/field-value', array( __CLASS__, 'get_field_value' ), 10, 3 );

                add_action( 'jet-engine/meta-boxes/register-custom-source', array( __CLASS__, 'register_meta_source' ) );
                add_filter( 'jet-engine/listings/dynamic-link/custom-url', array( __CLASS__, 'get_link_url' ), 10, 2 );
                add_filter( 'jet-engine/listings/dynamic-link/source-list', array( __CLASS__, 'add_link_source' ) );
        }

        public static function add_link_source( array $sources ): array {
                $sources['ve_events_info_url'] = __( 'VE Events: Info URL', 've-events' );
                return $sources;
        }

        public static function register_source( array $sources ): array {
                $sources[ self::$source_key ] = __( 'VE Events', 've-events' );
                return $sources;
        }

        public static function add_to_source_list( array $sources ): array {
                $sources[ self::$source_key ] = __( 'VE Events', 've-events' );
                return $sources;
        }

        public static function add_field_options( array $options, string $source ): array {
                if ( self::$source_key !== $source ) {
                        return $options;
                }

                return VEV_Fields::get_fields_for_dropdown();
        }

        public static function get_field_value( $value, array $settings, $source ) {
                $settings_source = $settings['dynamic_field_source'] ?? '';

                if ( self::$source_key !== $settings_source && self::$source_key !== $source ) {
                        return $value;
                }

                $field_key = $settings['dynamic_field_option'] ?? '';
                if ( empty( $field_key ) ) {
                        return $value;
                }

                $post_id = get_the_ID();
                if ( ! $post_id ) {
                        $post_id = jet_engine()->listings->data->get_current_object_id();
                }
                if ( ! $post_id ) {
                        return $value;
                }

                if ( get_post_type( $post_id ) !== VEV_Events::POST_TYPE ) {
                        return $value;
                }

                return VEV_Fields::get_formatted_value( $field_key, $post_id );
        }

        public static function register_meta_source(): void {
                if ( ! function_exists( 'jet_engine' ) ) {
                        return;
                }

                $fields = VEV_Fields::get_fields();
                $meta_fields = array();

                foreach ( $fields as $key => $field ) {
                        if ( 'virtual' === ( $field['type'] ?? '' ) ) {
                                continue;
                        }

                        $type = 'text';
                        switch ( $field['type'] ?? 'text' ) {
                                case 'datetime':
                                        $type = 'datetime-local';
                                        break;
                                case 'boolean':
                                        $type = 'switcher';
                                        break;
                                case 'textarea':
                                        $type = 'textarea';
                                        break;
                                case 'url':
                                        $type = 'text';
                                        break;
                        }

                        $meta_fields[] = array(
                                'name'  => $key,
                                'title' => $field['label'],
                                'type'  => $type,
                        );
                }

                if ( ! empty( $meta_fields ) && class_exists( 'Jet_Engine_Meta_Boxes' ) ) {
                        jet_engine()->meta_boxes->register_custom_group(
                                self::$source_key,
                                __( 'VE Events', 've-events' ),
                                $meta_fields,
                                array( VEV_Events::POST_TYPE )
                        );
                }
        }

        public static function get_link_url( $url, $settings ) {
                $source = $settings['dynamic_link_source'] ?? '';

                if ( 've_events_info_url' !== $source ) {
                        return $url;
                }

                $post_id = get_the_ID();
                if ( ! $post_id ) {
                        return $url;
                }

                $info_url = get_post_meta( $post_id, VEV_Events::META_INFO_URL, true );
                return $info_url ?: $url;
        }
}
