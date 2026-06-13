<?php
/**
 * Tests the centralized date/time formatting, including the same-day collapse
 * and the all-day / hide-end branches.
 *
 * Timestamps are chosen against UTC (the stubbed site timezone) with formats
 * date=Y-m-d and time=H:i (see tests/bootstrap.php).
 *
 * @package VE_Events
 */

declare( strict_types=1 );

namespace VEV\Tests;

use PHPUnit\Framework\TestCase;
use VEV\Support\DateFormatter;

final class DateFormatterTest extends TestCase {

	// 2021-01-01 09:00:00 UTC.
	private const DAY1_0900 = 1_609_491_600;
	// 2021-01-01 11:00:00 UTC.
	private const DAY1_1100 = 1_609_498_800;
	// 2021-01-02 09:00:00 UTC.
	private const DAY2_0900 = 1_609_578_000;

	public function test_date_and_time_only(): void {
		$this->assertSame( '2021-01-01', DateFormatter::date_only( self::DAY1_0900 ) );
		$this->assertSame( '09:00', DateFormatter::time_only( self::DAY1_0900 ) );
		$this->assertSame( '', DateFormatter::date_only( 0 ) );
		$this->assertSame( '', DateFormatter::time_only( 0 ) );
	}

	public function test_date_range_same_day_collapses(): void {
		$data = array(
			'start_utc' => self::DAY1_0900,
			'end_utc'   => self::DAY1_1100,
			'all_day'   => false,
			'hide_end'  => false,
		);
		$this->assertSame( '2021-01-01', DateFormatter::date_range( $data ) );
	}

	public function test_date_range_multi_day(): void {
		$data = array(
			'start_utc' => self::DAY1_0900,
			'end_utc'   => self::DAY2_0900,
			'all_day'   => false,
			'hide_end'  => false,
		);
		$this->assertSame( '2021-01-01 – 2021-01-02', DateFormatter::date_range( $data ) );
	}

	public function test_time_range_all_day(): void {
		$data = array(
			'start_utc' => self::DAY1_0900,
			'end_utc'   => self::DAY1_1100,
			'all_day'   => true,
			'hide_end'  => false,
		);
		$this->assertSame( 'All day', DateFormatter::time_range( $data ) );
	}

	public function test_time_range_hide_end(): void {
		$data = array(
			'start_utc' => self::DAY1_0900,
			'end_utc'   => self::DAY1_1100,
			'all_day'   => false,
			'hide_end'  => true,
		);
		$this->assertSame( '09:00', DateFormatter::time_range( $data ) );
	}

	public function test_time_range_full(): void {
		$data = array(
			'start_utc' => self::DAY1_0900,
			'end_utc'   => self::DAY1_1100,
			'all_day'   => false,
			'hide_end'  => false,
		);
		$this->assertSame( '09:00 – 11:00', DateFormatter::time_range( $data ) );
	}

	public function test_datetime_full_same_day(): void {
		$data = array(
			'start_utc' => self::DAY1_0900,
			'end_utc'   => self::DAY1_1100,
			'all_day'   => false,
			'hide_end'  => false,
		);
		$this->assertSame( '2021-01-01, 09:00 – 11:00', DateFormatter::datetime_full( $data ) );
	}

	public function test_datetime_full_all_day_single(): void {
		$data = array(
			'start_utc' => self::DAY1_0900,
			'end_utc'   => self::DAY1_1100,
			'all_day'   => true,
			'hide_end'  => false,
		);
		$this->assertSame( '2021-01-01 (all day)', DateFormatter::datetime_full( $data ) );
	}

	public function test_schema_all_day_is_date_only(): void {
		$this->assertSame( '2021-01-01', DateFormatter::schema( self::DAY1_0900, true, new \DateTimeZone( 'UTC' ) ) );
	}

	public function test_schema_timed_is_iso8601(): void {
		$this->assertSame( '2021-01-01T09:00:00+00:00', DateFormatter::schema( self::DAY1_0900, false, new \DateTimeZone( 'UTC' ) ) );
	}
}
