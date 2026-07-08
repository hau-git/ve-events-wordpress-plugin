<?php
/**
 * Verifies the DST-safe rescheduling math: local wall-clock time and day-span
 * are preserved when an event is moved to a new start date.
 *
 * @package VE_Events
 */

declare( strict_types=1 );

namespace VEV\Tests;

use PHPUnit\Framework\TestCase;
use VEV\Support\Reschedule;

final class RescheduleTest extends TestCase {

	private \DateTimeZone $berlin;

	protected function setUp(): void {
		$this->berlin = new \DateTimeZone( 'Europe/Berlin' );
	}

	/**
	 * Build a UTC timestamp from a Berlin-local date/time.
	 */
	private function berlin_ts( string $local ): int {
		return (int) ( new \DateTimeImmutable( $local, $this->berlin ) )->format( 'U' );
	}

	/**
	 * Format a UTC timestamp back to Berlin-local for assertions.
	 */
	private function local( int $ts ): string {
		return ( new \DateTimeImmutable( '@' . $ts ) )->setTimezone( $this->berlin )->format( 'Y-m-d H:i:s' );
	}

	public function test_same_summer_shift_preserves_time(): void {
		$start = $this->berlin_ts( '2026-07-10 18:00:00' );
		$end   = $this->berlin_ts( '2026-07-10 20:00:00' );

		$out = Reschedule::shift_to_date( $start, $end, '2026-07-15', $this->berlin );

		$this->assertSame( '2026-07-15 18:00:00', $this->local( $out['start'] ) );
		$this->assertSame( '2026-07-15 20:00:00', $this->local( $out['end'] ) );
	}

	public function test_shift_backwards(): void {
		$start = $this->berlin_ts( '2026-07-10 09:30:00' );
		$end   = $this->berlin_ts( '2026-07-10 10:30:00' );

		$out = Reschedule::shift_to_date( $start, $end, '2026-07-03', $this->berlin );

		$this->assertSame( '2026-07-03 09:30:00', $this->local( $out['start'] ) );
		$this->assertSame( '2026-07-03 10:30:00', $this->local( $out['end'] ) );
	}

	public function test_across_spring_dst_keeps_wall_clock(): void {
		// Spring-forward in 2026 is 2026-03-29 in Europe/Berlin.
		$start = $this->berlin_ts( '2026-03-27 20:00:00' );
		$end   = $this->berlin_ts( '2026-03-27 22:00:00' );

		$out = Reschedule::shift_to_date( $start, $end, '2026-03-30', $this->berlin );

		$this->assertSame( '2026-03-30 20:00:00', $this->local( $out['start'] ) );
		$this->assertSame( '2026-03-30 22:00:00', $this->local( $out['end'] ) );
	}

	public function test_across_fall_dst_keeps_wall_clock(): void {
		// Fall-back in 2026 is 2026-10-25 in Europe/Berlin.
		$start = $this->berlin_ts( '2026-10-23 20:00:00' );
		$end   = $this->berlin_ts( '2026-10-23 22:00:00' );

		$out = Reschedule::shift_to_date( $start, $end, '2026-10-26', $this->berlin );

		$this->assertSame( '2026-10-26 20:00:00', $this->local( $out['start'] ) );
		$this->assertSame( '2026-10-26 22:00:00', $this->local( $out['end'] ) );
	}

	public function test_multiday_span_preserved(): void {
		$start = $this->berlin_ts( '2026-07-10 18:00:00' );
		$end   = $this->berlin_ts( '2026-07-12 12:00:00' ); // 2-day span.

		$out = Reschedule::shift_to_date( $start, $end, '2026-07-20', $this->berlin );

		$this->assertSame( '2026-07-20 18:00:00', $this->local( $out['start'] ) );
		$this->assertSame( '2026-07-22 12:00:00', $this->local( $out['end'] ) );
	}

	public function test_all_day_bounds_preserved(): void {
		$start = $this->berlin_ts( '2026-07-10 00:00:00' );
		$end   = $this->berlin_ts( '2026-07-10 23:59:59' );

		$out = Reschedule::shift_to_date( $start, $end, '2026-08-01', $this->berlin );

		$this->assertSame( '2026-08-01 00:00:00', $this->local( $out['start'] ) );
		$this->assertSame( '2026-08-01 23:59:59', $this->local( $out['end'] ) );
	}

	public function test_zero_end_returns_end_equals_start(): void {
		$start = $this->berlin_ts( '2026-07-10 18:00:00' );

		$out = Reschedule::shift_to_date( $start, 0, '2026-07-15', $this->berlin );

		$this->assertSame( $out['start'], $out['end'] );
		$this->assertSame( '2026-07-15 18:00:00', $this->local( $out['start'] ) );
	}

	public function test_zero_start_returns_zeroes(): void {
		$out = Reschedule::shift_to_date( 0, 0, '2026-07-15', $this->berlin );
		$this->assertSame( array( 'start' => 0, 'end' => 0 ), $out );
	}
}
