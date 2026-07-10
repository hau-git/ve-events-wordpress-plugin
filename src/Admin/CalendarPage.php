<?php
/**
 * Admin calendar grid view: an interactive month-at-a-glance page for events.
 *
 * The month grid is rendered server-side and re-used verbatim by CalendarAjax
 * for month navigation, quick-create, and drag-and-drop, so the browser only
 * ever swaps in authoritative HTML.
 *
 * @package VE_Events
 */

namespace VEV\Admin;

use VEV\Constants;
use VEV\Fields\Registry;
use VEV\Support\DateFormatter;
use VEV\Support\EventData;
use VEV\Support\EventStatus;

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
	 * Normalize a YYYY-MM string, falling back to the current month.
	 *
	 * @param string $month_qv Raw month value.
	 */
	public static function normalize_month( string $month_qv ): string {
		if ( ! preg_match( '/^\d{4}-\d{2}$/', $month_qv ) ) {
			return current_time( 'Y-m' );
		}
		[ , $m ] = array_map( 'intval', explode( '-', $month_qv ) );
		if ( $m < 1 || $m > 12 ) {
			return current_time( 'Y-m' );
		}
		return $month_qv;
	}

	/**
	 * Build the month context (label + prev/next month keys) for a YYYY-MM value.
	 *
	 * @param string $month_qv Normalized YYYY-MM value.
	 * @return array{month:string,label:string,prev:string,next:string,start:\DateTimeImmutable,end:\DateTimeImmutable,tz:\DateTimeZone}
	 */
	public static function month_context( string $month_qv ): array {
		[ $year, $month ] = array_map( 'intval', explode( '-', $month_qv ) );
		$tz               = wp_timezone();
		$start            = new \DateTimeImmutable( sprintf( '%04d-%02d-01 00:00:00', $year, $month ), $tz );
		$end              = $start->modify( 'last day of this month' )->setTime( 23, 59, 59 );

		return array(
			'month' => $month_qv,
			'label' => (string) wp_date( 'F Y', $start->getTimestamp(), $tz ),
			'prev'  => $start->modify( '-1 month' )->format( 'Y-m' ),
			'next'  => $start->modify( '+1 month' )->format( 'Y-m' ),
			'start' => $start,
			'end'   => $end,
			'tz'    => $tz,
		);
	}

	/**
	 * Render the interactive month app (header nav + grid) as an HTML string.
	 *
	 * This is the single fragment swapped by the client on every state change.
	 *
	 * @param string $month_qv Raw or normalized YYYY-MM value.
	 */
	public static function render_app_html( string $month_qv ): string {
		$month_qv = self::normalize_month( $month_qv );
		$ctx      = self::month_context( $month_qv );
		$base_url = admin_url( 'edit.php?post_type=' . Constants::POST_TYPE . '&page=vev-calendar' );

		ob_start();
		?>
		<div class="vev-cal-header">
			<a href="<?php echo esc_url( add_query_arg( 'vev_cal_month', $ctx['prev'], $base_url ) ); ?>" class="button vev-cal-nav" data-month="<?php echo esc_attr( $ctx['prev'] ); ?>" aria-label="<?php esc_attr_e( 'Previous month', 've-events' ); ?>">&#8592;</a>
			<h2 class="vev-cal-title"><?php echo esc_html( $ctx['label'] ); ?></h2>
			<a href="<?php echo esc_url( add_query_arg( 'vev_cal_month', $ctx['next'], $base_url ) ); ?>" class="button vev-cal-nav" data-month="<?php echo esc_attr( $ctx['next'] ); ?>" aria-label="<?php esc_attr_e( 'Next month', 've-events' ); ?>">&#8594;</a>
		</div>
		<?php
		echo self::render_grid_html( $month_qv ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- grid HTML escaped internally.
		return (string) ob_get_clean();
	}

	/**
	 * Render just the month grid (day-of-week header + day cells) as HTML.
	 *
	 * @param string $month_qv Raw or normalized YYYY-MM value.
	 */
	public static function render_grid_html( string $month_qv ): string {
		$month_qv = self::normalize_month( $month_qv );
		$ctx      = self::month_context( $month_qv );
		$tz       = $ctx['tz'];

		[ $year, $month ] = array_map( 'intval', explode( '-', $month_qv ) );

		$events_query = new \WP_Query(
			array(
				'post_type'                    => Constants::POST_TYPE,
				'post_status'                  => array( 'publish', 'draft', 'future' ),
				'posts_per_page'               => 300, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Bounded month view.
				'meta_query'                   => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- bounded month range.
					array(
						'key'     => Constants::META_START_UTC,
						'value'   => array( $ctx['start']->getTimestamp(), $ctx['end']->getTimestamp() ),
						'compare' => 'BETWEEN',
						'type'    => 'NUMERIC',
					),
				),
				'meta_key'                     => Constants::META_START_UTC, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- ordering by start.
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
				$p              = get_post();
				$start_utc      = (int) get_post_meta( $p->ID, Constants::META_START_UTC, true );
				$day            = (int) wp_date( 'j', $start_utc, $tz );
				$days[ $day ][] = $p;
			}
			wp_reset_postdata();
		}

		$first_dow     = (int) $ctx['start']->format( 'N' ); // 1 = Mon.
		$days_in_month = (int) $ctx['start']->format( 't' );

		$today_d = (int) current_time( 'j' );
		$today_m = (int) current_time( 'n' );
		$today_y = (int) current_time( 'Y' );

		$dow_labels = array(
			__( 'Mon', 've-events' ),
			__( 'Tue', 've-events' ),
			__( 'Wed', 've-events' ),
			__( 'Thu', 've-events' ),
			__( 'Fri', 've-events' ),
			__( 'Sat', 've-events' ),
			__( 'Sun', 've-events' ),
		);

		ob_start();
		echo '<div class="vev-cal-grid">';

		foreach ( $dow_labels as $dow_label ) {
			printf( '<div class="vev-cal-dow">%s</div>', esc_html( $dow_label ) );
		}

		for ( $i = 1; $i < $first_dow; $i++ ) {
			echo '<div class="vev-cal-day vev-cal-day--empty"></div>';
		}

		for ( $d = 1; $d <= $days_in_month; $d++ ) {
			$is_today  = ( $d === $today_d && $month === $today_m && $year === $today_y );
			$cls       = 'vev-cal-day' . ( $is_today ? ' vev-cal-day--today' : '' );
			$date_attr = sprintf( '%04d-%02d-%02d', $year, $month, $d );

			printf(
				'<div class="%s" data-date="%s">',
				esc_attr( $cls ),
				esc_attr( $date_attr )
			);
			echo '<div class="vev-cal-day-num">' . esc_html( (string) $d ) . '</div>';

			if ( ! empty( $days[ $d ] ) ) {
				foreach ( $days[ $d ] as $ev ) {
					echo self::render_event_chip( $ev ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- chip HTML escaped internally.
				}
			}
			echo '</div>';
		}

		$total    = $first_dow - 1 + $days_in_month;
		$trailing = ( 7 - ( $total % 7 ) ) % 7;
		for ( $i = 0; $i < $trailing; $i++ ) {
			echo '<div class="vev-cal-day vev-cal-day--empty"></div>';
		}

		echo '</div><!-- .vev-cal-grid -->';
		return (string) ob_get_clean();
	}

	/**
	 * Build the client-side payload consumed by the calendar popover.
	 *
	 * @param \WP_Post $ev Event post.
	 * @return array<string,mixed>
	 */
	public static function event_payload( \WP_Post $ev ): array {
		$id          = (int) $ev->ID;
		$data        = EventData::get( $id );
		$status_key  = EventStatus::for_post( $id );
		$post_status = (string) get_post_status( $ev );

		return array(
			'id'            => $id,
			'title'         => get_the_title( $id ),
			'date'          => DateFormatter::date_range( $data ),
			'time'          => DateFormatter::time_range( $data ),
			'allDay'        => (bool) $data['all_day'],
			'location'      => Registry::get_location_name( $id ),
			'category'      => Registry::get_category_name( $id ),
			'categoryColor' => Registry::get_category_color( $id ),
			'statusKey'     => $status_key,
			'statusLabel'   => EventStatus::label( $status_key ),
			'statusColor'   => EventStatus::color( $status_key ),
			'postStatus'    => $post_status,
			'editUrl'       => (string) get_edit_post_link( $id, 'raw' ),
			'viewUrl'       => (string) get_permalink( $id ),
		);
	}

	/**
	 * Render a single event chip for the grid.
	 *
	 * @param \WP_Post $ev Event post.
	 */
	private static function render_event_chip( \WP_Post $ev ): string {
		$payload     = self::event_payload( $ev );
		$bg          = $payload['categoryColor'] ? $payload['categoryColor'] : '#2271b1';
		$post_status = $payload['postStatus'];

		$classes = array( 'vev-cal-event' );
		if ( 'cancelled' === $payload['statusKey'] ) {
			$classes[] = 'vev-cal-event--cancelled';
		}
		if ( 'publish' !== $post_status ) {
			$classes[] = 'vev-cal-event--draft';
		}

		$label = $payload['title'];
		if ( 'draft' === $post_status ) {
			/* translators: %s: event title */
			$label = sprintf( __( '%s (draft)', 've-events' ), $payload['title'] );
		}

		return sprintf(
			'<a href="%s" class="%s" style="background:%s;" draggable="true" data-id="%d" data-event="%s" title="%s">%s</a>',
			esc_url( $payload['editUrl'] ),
			esc_attr( implode( ' ', $classes ) ),
			esc_attr( $bg ),
			(int) $payload['id'],
			esc_attr( (string) wp_json_encode( $payload ) ),
			esc_attr( $label ),
			esc_html( $label )
		);
	}

	/**
	 * Render the month calendar grid page.
	 */
	public static function render_calendar_view(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 've-events' ) );
		}

		// Read-only month navigation; no state change, so no nonce is required.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$month_qv = isset( $_GET['vev_cal_month'] ) ? sanitize_text_field( wp_unslash( $_GET['vev_cal_month'] ) ) : '';
		$month_qv = self::normalize_month( $month_qv );
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Events', 've-events' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . Constants::POST_TYPE ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Add New Event', 've-events' ); ?>
			</a>
			<hr class="wp-header-end">
			<?php ViewsNav::render( 'calendar' ); ?>

			<div id="vev-cal-app" data-month="<?php echo esc_attr( $month_qv ); ?>">
				<?php echo self::render_app_html( $month_qv ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- app HTML escaped internally. ?>
			</div>
		</div><!-- .wrap -->
		<?php
	}
}
