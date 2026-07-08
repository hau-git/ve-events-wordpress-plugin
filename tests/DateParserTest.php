<?php
/**
 * Verifies date/time → UTC conversion, including all-day clamping and the
 * empty/invalid fallbacks shared by the editor and calendar quick-create.
 *
 * @package VE_Events
 */

declare( strict_types=1 );

namespace VEV\Tests;

use PHPUnit\Framework\TestCase;
use VEV\Support\DateParser;

final class DateParserTest extends TestCase {

	private \DateTimeZone $berlin;

	protected function setUp(): void {
		$this->berlin = new \DateTimeZone( 'Europe/Berlin' );
	}

	private function local( int $ts ): string {
		return ( new \DateTimeImmutable( '@' . $ts ) )->setTimezone( $this->berlin )->format( 'Y-m-d H:i:s' );
	}

	public function test_timed_conversion(): void {
		$ts = DateParser::to_utc( '2026-07-10', '18:30', false, true, $this->berlin );
		$this->assertSame( '2026-07-10 18:30:00', $this->local( $ts ) );
	}

	public function test_empty_time_defaults_to_midnight(): void {
		$ts = DateParser::to_utc( '2026-07-10', '', false, true, $this->berlin );
		$this->assertSame( '2026-07-10 00:00:00', $this->local( $ts ) );
	}

	public function test_all_day_start_clock(): void {
		$ts = DateParser::to_utc( '2026-07-10', '18:30', true, true, $this->berlin );
		$this->assertSame( '2026-07-10 00:00:00', $this->local( $ts ) );
	}

	public function test_all_day_end_clock(): void {
		$ts = DateParser::to_utc( '2026-07-10', '', true, false, $this->berlin );
		$this->assertSame( '2026-07-10 23:59:59', $this->local( $ts ) );
	}

	public function test_empty_date_returns_zero(): void {
		$this->assertSame( 0, DateParser::to_utc( '', '18:30', false, true, $this->berlin ) );
	}

	public function test_utc_timezone_offset(): void {
		// 18:00 Berlin summer time (CEST, +2) == 16:00 UTC.
		$ts = DateParser::to_utc( '2026-07-10', '18:00', false, true, $this->berlin );
		$this->assertSame( '2026-07-10 16:00:00', gmdate( 'Y-m-d H:i:s', $ts ) );
	}
}
