<?php
/**
 * Admin calendar grid view: a month-at-a-glance page for events, plus the
 * shared sub-views navigation.
 *
 * @package VE_Events
 */

namespace VEV\Admin;

use VEV\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and renders the hidden calendar grid admin page.
 */
final class CalendarPage {

	/**
	 * Register the calendar page hook.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_calendar_page' ) );
	}

	/**
	 * Register the (hidden) calendar submenu page.
	 */
	public static function register_calendar_page(): void {
		add_submenu_page(
			null,
			__( 'Event Calendar', 've-events' ),
			__( 'Calendar', 've-events' ),
			'edit_posts',
			'vev-calendar',
			array( __CLASS__, 'render_calendar_view' )
		);
	}

	/**
	 * Output the sub-views navigation (Upcoming / Past / All / Drafts / Calendar).
	 *
	 * Used both by ListTable::admin_views() (list page, WP renders the UL) and
	 * render_calendar_view() (calendar page, we render the UL directly).
	 *
	 * @param string $current The active view key.
	 */
	private static function render_views_nav( string $current ): void {
		global $wpdb;

		$pt       = Constants::POST_TYPE;
		$now      = time();
		$base_url = admin_url( 'edit.php?post_type=' . $pt );
		$cal_url  = admin_url( 'edit.php?post_type=' . $pt . '&page=vev-calendar' );

		$count_upcoming = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
			 LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
			 WHERE p.post_type = %s AND p.post_status = 'publish'
			   AND (CAST(pm.meta_value AS SIGNED) >= %d OR pm.meta_value IS NULL)",
				Constants::META_END_UTC,
				$pt,
				$now
			)
		);

		$count_past = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
			 WHERE p.post_type = %s AND p.post_status = 'publish'
			   AND CAST(pm.meta_value AS SIGNED) < %d",
				Constants::META_END_UTC,
				$pt,
				$now
			)
		);

		$counts      = wp_count_posts( $pt );
		$count_all   = (int) ( $counts->publish ?? 0 );
		$count_draft = (int) ( $counts->draft ?? 0 );

		$items = array();

		$items['upcoming'] = sprintf(
			'<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
			esc_url( add_query_arg( 'vev_view', 'upcoming', $base_url ) ),
			'upcoming' === $current ? 'current' : '',
			__( 'Upcoming', 've-events' ),
			number_format_i18n( $count_upcoming )
		);
		$items['past']     = sprintf(
			'<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
			esc_url( add_query_arg( 'vev_view', 'past', $base_url ) ),
			'past' === $current ? 'current' : '',
			__( 'Past', 've-events' ),
			number_format_i18n( $count_past )
		);
		$items['all']      = sprintf(
			'<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
			esc_url( add_query_arg( 'vev_view', 'all', $base_url ) ),
			'all' === $current ? 'current' : '',
			__( 'All', 've-events' ),
			number_format_i18n( $count_all )
		);
		if ( $count_draft > 0 ) {
			$items['draft'] = sprintf(
				'<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
				esc_url( add_query_arg( 'post_status', 'draft', $base_url ) ),
				'draft' === $current ? 'current' : '',
				__( 'Drafts', 've-events' ),
				number_format_i18n( $count_draft )
			);
		}
		$items['calendar'] = sprintf(
			'<a href="%s" class="%s">%s</a>',
			esc_url( $cal_url ),
			'calendar' === $current ? 'current' : '',
			esc_html__( 'Calendar', 've-events' )
		);

		echo '<ul class="subsubsub">';
		$last = array_key_last( $items );
		foreach ( $items as $key => $html ) {
			printf(
				'<li class="%s">%s%s</li>',
				esc_attr( $key ),
				$html, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML built with escaping above.
				$key !== $last ? ' |' : ''
			);
		}
		echo '</ul>';
	}

	/**
	 * Render the month calendar grid page.
	 */
	public static function render_calendar_view(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 've-events' ) );
		}

		// Read-only month navigation; no state change, so no nonce is required.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$month_qv = isset( $_GET['vev_cal_month'] )
			? sanitize_text_field( wp_unslash( $_GET['vev_cal_month'] ) )
			: '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! preg_match( '/^\d{4}-\d{2}$/', $month_qv ) ) {
			$month_qv = current_time( 'Y-m' );
		}

		[ $year, $month ] = array_map( 'intval', explode( '-', $month_qv ) );
		$tz               = wp_timezone();
		$month_start      = new \DateTimeImmutable( sprintf( '%04d-%02d-01 00:00:00', $year, $month ), $tz );
		$month_end        = $month_start->modify( 'last day of this month' )->setTime( 23, 59, 59 );

		$prev_month = $month_start->modify( '-1 month' )->format( 'Y-m' );
		$next_month = $month_start->modify( '+1 month' )->format( 'Y-m' );
		$base_url   = admin_url( 'edit.php?post_type=' . Constants::POST_TYPE . '&page=vev-calendar' );

		// Query events this month.
		$events_query = new \WP_Query(
			array(
				'post_type'                    => Constants::POST_TYPE,
				'post_status'                  => 'publish',
				'posts_per_page'               => 300, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Bounded month view.
				'meta_query'                   => array(
					array(
						'key'     => Constants::META_START_UTC,
						'value'   => array( $month_start->getTimestamp(), $month_end->getTimestamp() ),
						'compare' => 'BETWEEN',
						'type'    => 'NUMERIC',
					),
				),
				'meta_key'                     => Constants::META_START_UTC,
				'orderby'                      => 'meta_value_num',
				'order'                        => 'ASC',
				Constants::QV_INCLUDE_ARCHIVED => 1,
			)
		);

		// Index events by day-of-month.
		$days = array();
		if ( $events_query->have_posts() ) {
			while ( $events_query->have_posts() ) {
				$events_query->the_post();
				$p         = get_post();
				$start_utc = (int) get_post_meta( $p->ID, Constants::META_START_UTC, true );
				$day       = (int) wp_date( 'j', $start_utc, $tz );

				$days[ $day ][] = $p;
			}
			wp_reset_postdata();
		}

		// Build category → color map.
		$cat_colors = array();
		$all_cats   = get_terms(
			array(
				'taxonomy'   => Constants::TAX_CATEGORY,
				'hide_empty' => false,
			)
		);
		if ( is_array( $all_cats ) ) {
			foreach ( $all_cats as $cat ) {
				$color = (string) get_term_meta( $cat->term_id, Constants::TERM_META_CATEGORY_COLOR, true );
				if ( $color ) {
					$cat_colors[ $cat->term_id ] = $color;
				}
			}
		}

		$first_dow     = (int) $month_start->format( 'N' ); // 1 = Mon.
		$days_in_month = (int) $month_start->format( 't' );
		$month_label   = wp_date( 'F Y', $month_start->getTimestamp(), $tz );

		$today_d = (int) current_time( 'j' );
		$today_m = (int) current_time( 'n' );
		$today_y = (int) current_time( 'Y' );
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Events', 've-events' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . Constants::POST_TYPE ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Add New Event', 've-events' ); ?>
			</a>
			<hr class="wp-header-end">
			<?php self::render_views_nav( 'calendar' ); ?>

			<div class="vev-cal-header">
				<a href="<?php echo esc_url( add_query_arg( 'vev_cal_month', $prev_month, $base_url ) ); ?>" class="button">&#8592;</a>
				<h2 class="vev-cal-title"><?php echo esc_html( (string) $month_label ); ?></h2>
				<a href="<?php echo esc_url( add_query_arg( 'vev_cal_month', $next_month, $base_url ) ); ?>" class="button">&#8594;</a>
			</div>

			<div class="vev-cal-grid">
				<?php
				// Day-of-week headers (Mon–Sun).
				$dow_labels = array(
					__( 'Mon', 've-events' ),
					__( 'Tue', 've-events' ),
					__( 'Wed', 've-events' ),
					__( 'Thu', 've-events' ),
					__( 'Fri', 've-events' ),
					__( 'Sat', 've-events' ),
					__( 'Sun', 've-events' ),
				);
				foreach ( $dow_labels as $dow_label ) {
					printf( '<div class="vev-cal-dow">%s</div>', esc_html( $dow_label ) );
				}

				// Empty leading cells.
				for ( $i = 1; $i < $first_dow; $i++ ) {
					echo '<div class="vev-cal-day vev-cal-day--empty"></div>';
				}

				// Day cells.
				for ( $d = 1; $d <= $days_in_month; $d++ ) {
					$is_today = ( $d === $today_d && $month === $today_m && $year === $today_y );
					$cls      = 'vev-cal-day' . ( $is_today ? ' vev-cal-day--today' : '' );
					echo '<div class="' . esc_attr( $cls ) . '">';
					echo '<div class="vev-cal-day-num">' . esc_html( (string) $d ) . '</div>';

					if ( ! empty( $days[ $d ] ) ) {
						foreach ( $days[ $d ] as $ev ) {
							$bg   = '#2271b1';
							$cats = get_the_terms( $ev->ID, Constants::TAX_CATEGORY );
							if ( is_array( $cats ) && ! empty( $cats ) ) {
								$cid = (int) $cats[0]->term_id;
								if ( isset( $cat_colors[ $cid ] ) ) {
									$bg = $cat_colors[ $cid ];
								}
							}
							printf(
								'<a href="%s" class="vev-cal-event" style="background:%s;" title="%s">%s</a>',
								esc_url( (string) get_edit_post_link( $ev->ID ) ),
								esc_attr( $bg ),
								esc_attr( $ev->post_title ),
								esc_html( $ev->post_title )
							);
						}
					}
					echo '</div>';
				}

				// Trailing empty cells to fill last row.
				$total    = $first_dow - 1 + $days_in_month;
				$trailing = ( 7 - ( $total % 7 ) ) % 7;
				for ( $i = 0; $i < $trailing; $i++ ) {
					echo '<div class="vev-cal-day vev-cal-day--empty"></div>';
				}
				?>
			</div><!-- .vev-cal-grid -->
		</div><!-- .wrap -->
		<?php
	}
}
