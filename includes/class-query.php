<?php
if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

final class VEV_Query {

        public static function init(): void {
                add_action( 'pre_get_posts', array( __CLASS__, 'pre_get_posts' ) );
                add_filter( 'query_vars', array( __CLASS__, 'register_query_vars' ) );
                add_filter( 'posts_join', array( __CLASS__, 'search_join' ), 10, 2 );
                add_filter( 'posts_where', array( __CLASS__, 'search_where' ), 10, 2 );
                add_filter( 'posts_search', array( __CLASS__, 'extend_search' ), 10, 2 );
                add_filter( 'posts_distinct', array( __CLASS__, 'search_distinct' ), 10, 2 );
        }

        public static function register_query_vars( array $vars ): array {
                $vars[] = VEV_Events::QV_SCOPE;
                $vars[] = VEV_Events::QV_INCLUDE_ARCHIVED;
                return $vars;
        }

        public static function pre_get_posts( \WP_Query $query ): void {
                if ( ! $query instanceof \WP_Query ) {
                        return;
                }

                $post_type = $query->get( 'post_type' );

                $is_event_query = false;
                $is_event_only_query = false;
                if ( is_string( $post_type ) ) {
                        $is_event_query      = ( VEV_Events::POST_TYPE === $post_type );
                        $is_event_only_query = $is_event_query;
                } elseif ( is_array( $post_type ) ) {
                        $is_event_query      = in_array( VEV_Events::POST_TYPE, $post_type, true );
                        $is_event_only_query = ( $is_event_query && 1 === count( $post_type ) );
                } else {
                        $is_event_query      = false;
                        $is_event_only_query = false;
                }

                if ( is_admin() && $query->is_main_query() ) {
                        if ( VEV_Events::POST_TYPE === $post_type && 'edit.php' === $GLOBALS['pagenow'] ) {
                                $vev_view    = isset( $_GET['vev_view'] ) ? sanitize_key( $_GET['vev_view'] ) : '';
                                $post_status = isset( $_GET['post_status'] ) ? sanitize_key( $_GET['post_status'] ) : '';

                                if ( '' === $vev_view && '' === $post_status ) {
                                        $vev_view = 'upcoming';
                                }

                                if ( '' !== $vev_view && '' === $post_status ) {
                                        $now        = time();
                                        $meta_query = (array) $query->get( 'meta_query' );
                                        if ( empty( $meta_query ) ) {
                                                $meta_query = array();
                                        }

                                        if ( 'upcoming' === $vev_view ) {
                                                $meta_query[] = array(
                                                        'relation' => 'OR',
                                                        array(
                                                                'key'     => VEV_Events::META_END_UTC,
                                                                'value'   => $now,
                                                                'compare' => '>=',
                                                                'type'    => 'NUMERIC',
                                                        ),
                                                        array(
                                                                'key'     => VEV_Events::META_END_UTC,
                                                                'compare' => 'NOT EXISTS',
                                                        ),
                                                );
                                        } elseif ( 'past' === $vev_view ) {
                                                $meta_query[] = array(
                                                        'key'     => VEV_Events::META_END_UTC,
                                                        'value'   => $now,
                                                        'compare' => '<',
                                                        'type'    => 'NUMERIC',
                                                );
                                        }

                                        if ( ! empty( $meta_query ) ) {
                                                $query->set( 'meta_query', $meta_query );
                                        }
                                }

                                $orderby = (string) $query->get( 'orderby' );
                                $order   = strtoupper( (string) $query->get( 'order' ) );
                                if ( 'DESC' !== $order ) {
                                        $order = 'ASC';
                                }

                                if ( 'vev_end' === $orderby ) {
                                        $query->set( 'meta_key', VEV_Events::META_END_UTC );
                                        $query->set( 'orderby', 'meta_value_num' );
                                        $query->set( 'order', $order );
                                } elseif ( '' === $orderby || 'vev_start' === $orderby ) {
                                        self::apply_ordering( $query, $order, true );
                                }
                        }
                        return;
                }

                if ( ! is_admin() && $is_event_only_query && ! $query->get( 'suppress_filters' ) ) {
                        $include_archived = (int) $query->get( VEV_Events::QV_INCLUDE_ARCHIVED );
                        $scope            = (string) $query->get( VEV_Events::QV_SCOPE );

                        $now = time();
                        $settings = VEV_Events::get_settings();
                        $grace_period = absint( $settings['grace_period'] ?? 1 );
                        $grace_seconds = $grace_period * DAY_IN_SECONDS;
                        $cutoff = $now - $grace_seconds;

                        $meta_query = (array) $query->get( 'meta_query' );
                        if ( empty( $meta_query ) ) {
                                $meta_query = array();
                        }

                        if ( 1 !== $include_archived ) {
                                $meta_query[] = array(
                                        'key'     => VEV_Events::META_END_UTC,
                                        'value'   => $cutoff,
                                        'compare' => '>=',
                                        'type'    => 'NUMERIC',
                                );
                        }

                        if ( $scope ) {
                                switch ( $scope ) {
                                        case 'upcoming':
                                                $meta_query[] = array(
                                                        'key'     => VEV_Events::META_START_UTC,
                                                        'value'   => $now,
                                                        'compare' => '>',
                                                        'type'    => 'NUMERIC',
                                                );
                                                break;

                                        case 'ongoing':
                                                $meta_query[] = array(
                                                        'key'     => VEV_Events::META_START_UTC,
                                                        'value'   => $now,
                                                        'compare' => '<=',
                                                        'type'    => 'NUMERIC',
                                                );
                                                $meta_query[] = array(
                                                        'key'     => VEV_Events::META_END_UTC,
                                                        'value'   => $now,
                                                        'compare' => '>=',
                                                        'type'    => 'NUMERIC',
                                                );
                                                break;

                                        case 'past':
                                                $meta_query[] = array(
                                                        'key'     => VEV_Events::META_END_UTC,
                                                        'value'   => $now,
                                                        'compare' => '<',
                                                        'type'    => 'NUMERIC',
                                                );
                                                $meta_query[] = array(
                                                        'key'     => VEV_Events::META_END_UTC,
                                                        'value'   => $cutoff,
                                                        'compare' => '>=',
                                                        'type'    => 'NUMERIC',
                                                );
                                                break;

                                        case 'archived':
                                                $meta_query[] = array(
                                                        'key'     => VEV_Events::META_END_UTC,
                                                        'value'   => $cutoff,
                                                        'compare' => '<',
                                                        'type'    => 'NUMERIC',
                                                );
                                                break;

                                        case 'all':
                                        default:
                                                break;
                                }
                        }

                        $query->set( 'meta_query', $meta_query );

                        $orderby = $query->get( 'orderby' );
                        $meta_key = (string) $query->get( 'meta_key' );

                        if ( empty( $orderby ) || ( 'date' === $orderby && '' === $meta_key ) ) {
                                self::apply_ordering( $query, (string) ( $query->get( 'order' ) ?: 'asc' ), false );
                        }
                }

                if ( ! is_admin() && $query->is_search() && ! $query->get( 'suppress_filters' ) ) {
                        $pt = $query->get( 'post_type' );
                        if ( empty( $pt ) ) {
                                $query->set( 'post_type', array( 'post', 'page', VEV_Events::POST_TYPE ) );
                        }
                }
        }

        private static function apply_ordering( \WP_Query $query, string $order, bool $force ): void {
                $order = strtoupper( $order );
                if ( 'DESC' !== $order ) {
                        $order = 'ASC';
                }

                if ( ! $force ) {
                        $orderby = (string) $query->get( 'orderby' );
                        $meta_key = (string) $query->get( 'meta_key' );
                        if ( 'meta_value_num' === $orderby && '' !== $meta_key ) {
                                return;
                        }
                }

                $query->set( 'meta_key', VEV_Events::META_START_UTC );
                $query->set( 'orderby', 'meta_value_num' );
                $query->set( 'order', $order );
        }

        public static function search_join( string $join, \WP_Query $query ): string {
                if ( is_admin() || ! $query->is_search() ) {
                        return $join;
                }

                global $wpdb;

                if ( false === strpos( $join, 'vev_pm' ) ) {
                        $join .= " LEFT JOIN {$wpdb->postmeta} vev_pm ON ({$wpdb->posts}.ID = vev_pm.post_id) ";
                }

                if ( false === strpos( $join, 'vev_pm_end' ) ) {
                        $join .= " LEFT JOIN {$wpdb->postmeta} vev_pm_end ON ({$wpdb->posts}.ID = vev_pm_end.post_id AND vev_pm_end.meta_key = '" . esc_sql( VEV_Events::META_END_UTC ) . "') ";
                }

                if ( false === strpos( $join, 'vev_tr' ) ) {
                        $join .= " LEFT JOIN {$wpdb->term_relationships} vev_tr ON ({$wpdb->posts}.ID = vev_tr.object_id) ";
                        $join .= " LEFT JOIN {$wpdb->term_taxonomy} vev_tt ON (vev_tr.term_taxonomy_id = vev_tt.term_taxonomy_id) ";
                        $join .= " LEFT JOIN {$wpdb->terms} vev_t ON (vev_tt.term_id = vev_t.term_id) ";
                }

                return $join;
        }

        public static function search_where( string $where, \WP_Query $query ): string {
                if ( is_admin() || ! $query->is_search() ) {
                        return $where;
                }

                $settings = VEV_Events::get_settings();
                if ( empty( $settings['hide_archived_search'] ) ) {
                        return $where;
                }

                global $wpdb;
                $now = time();
                $grace_period = absint( $settings['grace_period'] ?? 1 );
                $grace_seconds = $grace_period * DAY_IN_SECONDS;
                $cutoff = $now - $grace_seconds;

                $where .= $wpdb->prepare(
                        " AND ( {$wpdb->posts}.post_type != %s OR ( CAST( vev_pm_end.meta_value AS SIGNED ) >= %d ) )",
                        VEV_Events::POST_TYPE,
                        $cutoff
                );

                return $where;
        }

        public static function search_distinct( string $distinct, \WP_Query $query ): string {
                if ( is_admin() || ! $query->is_search() ) {
                        return $distinct;
                }
                return 'DISTINCT';
        }

        public static function extend_search( string $search, \WP_Query $query ): string {
                if ( is_admin() || ! $query->is_search() ) {
                        return $search;
                }

                global $wpdb;

                $term = (string) $query->get( 's' );
                $term = trim( $term );

                if ( '' === $term || '' === $search ) {
                        return $search;
                }

                $like = '%' . $wpdb->esc_like( $term ) . '%';

                $tax_list = array( VEV_Events::TAX_CATEGORY, VEV_Events::TAX_LOCATION, VEV_Events::TAX_TOPIC, VEV_Events::TAX_SERIES );
                $tax_in   = "'" . implode( "','", array_map( 'esc_sql', $tax_list ) ) . "'";

                $meta_keys = array( VEV_Events::META_SPEAKER, VEV_Events::META_SPECIAL, VEV_Events::META_INFO_URL );
                $meta_in   = "'" . implode( "','", array_map( 'esc_sql', $meta_keys ) ) . "'";

                $extra = $wpdb->prepare(
                        " OR ( {$wpdb->posts}.post_type = %s AND ( ( vev_pm.meta_key IN ($meta_in) AND vev_pm.meta_value LIKE %s ) OR ( vev_tt.taxonomy IN ($tax_in) AND vev_t.name LIKE %s ) ) )",
                        VEV_Events::POST_TYPE,
                        $like,
                        $like
                );

                $pos = strrpos( $search, '))' );
                if ( false === $pos ) {
                        return $search;
                }

                return substr( $search, 0, $pos ) . $extra . substr( $search, $pos );
        }
}
