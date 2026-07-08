<?php
/**
 * Verifies the pure RFC 5545 builder: escaping, folding, STATUS mapping,
 * UTC/all-day date rendering, and the VCALENDAR envelope.
 *
 * @package VE_Events
 */

declare( strict_types=1 );

namespace VEV\Tests;

use PHPUnit\Framework\TestCase;
use VEV\Export\IcsBuilder;

final class IcsBuilderTest extends TestCase {

	private \DateTimeZone $berlin;

	protected function setUp(): void {
		$this->berlin = new \DateTimeZone( 'Europe/Berlin' );
	}

	private function berlin_ts( string $local ): int {
		return (int) ( new \DateTimeImmutable( $local, $this->berlin ) )->format( 'U' );
	}

	public function test_escape_text(): void {
		$this->assertSame( 'a\\,b\\;c', IcsBuilder::escape_text( 'a,b;c' ) );
		$this->assertSame( 'back\\\\slash', IcsBuilder::escape_text( 'back\\slash' ) );
		$this->assertSame( 'line1\\nline2', IcsBuilder::escape_text( "line1\nline2" ) );
	}

	public function test_status_map(): void {
		$this->assertSame( 'CANCELLED', IcsBuilder::status( 'cancelled' ) );
		$this->assertSame( 'TENTATIVE', IcsBuilder::status( 'postponed' ) );
		$this->assertSame( 'TENTATIVE', IcsBuilder::status( 'rescheduled' ) );
		$this->assertSame( 'CONFIRMED', IcsBuilder::status( '' ) );
		$this->assertSame( 'CONFIRMED', IcsBuilder::status( 'movedOnline' ) );
	}

	public function test_fold_wraps_long_lines_with_leading_space(): void {
		$long   = 'SUMMARY:' . str_repeat( 'x', 120 );
		$folded = IcsBuilder::fold( $long );
		$parts  = explode( "\r\n", $folded );
		$this->assertGreaterThan( 1, count( $parts ) );
		$this->assertSame( ' ', substr( $parts[1], 0, 1 ) );
		// First line stays within 75 octets.
		$this->assertLessThanOrEqual( 75, strlen( $parts[0] ) );
	}

	public function test_fold_is_multibyte_safe(): void {
		$line   = 'SUMMARY:' . str_repeat( 'ä', 60 ); // Umlauts are 2 bytes each.
		$folded = IcsBuilder::fold( $line );
		// Every continuation piece must still be valid UTF-8 (no split code points).
		foreach ( explode( "\r\n", $folded ) as $piece ) {
			$this->assertSame( $piece, mb_convert_encoding( $piece, 'UTF-8', 'UTF-8' ) );
		}
	}

	public function test_timed_vevent_uses_utc_z(): void {
		$vevent = IcsBuilder::vevent(
			array(
				'uid'     => 'x@example.com',
				'dtstamp' => $this->berlin_ts( '2026-07-01 10:00:00' ),
				'start'   => $this->berlin_ts( '2026-07-10 18:00:00' ), // 16:00 UTC.
				'end'     => $this->berlin_ts( '2026-07-10 20:00:00' ), // 18:00 UTC.
				'all_day' => false,
				'tz'      => $this->berlin,
				'summary' => 'Concert',
			)
		);

		$this->assertStringContainsString( 'DTSTART:20260710T160000Z', $vevent );
		$this->assertStringContainsString( 'DTEND:20260710T180000Z', $vevent );
		$this->assertStringContainsString( 'SUMMARY:Concert', $vevent );
		$this->assertStringContainsString( "END:VEVENT\r\n", $vevent );
	}

	public function test_all_day_vevent_uses_exclusive_end_date(): void {
		// Single all-day event: stored end is 23:59:59 of the same local day.
		$vevent = IcsBuilder::vevent(
			array(
				'uid'     => 'y@example.com',
				'dtstamp' => $this->berlin_ts( '2026-07-01 10:00:00' ),
				'start'   => $this->berlin_ts( '2026-07-10 00:00:00' ),
				'end'     => $this->berlin_ts( '2026-07-10 23:59:59' ),
				'all_day' => true,
				'tz'      => $this->berlin,
				'summary' => 'Holiday',
			)
		);

		$this->assertStringContainsString( 'DTSTART;VALUE=DATE:20260710', $vevent );
		// Exclusive end → next day.
		$this->assertStringContainsString( 'DTEND;VALUE=DATE:20260711', $vevent );
	}

	public function test_organizer_only_with_url(): void {
		$with = IcsBuilder::vevent(
			array(
				'uid'            => 'z@example.com',
				'dtstamp'        => 0,
				'start'          => 0,
				'end'            => 0,
				'all_day'        => false,
				'tz'             => $this->berlin,
				'organizer_name' => 'ACME Ltd',
				'organizer_url'  => 'https://acme.example',
			)
		);
		$this->assertStringContainsString( 'ORGANIZER;CN="ACME Ltd":https://acme.example', $with );

		$without = IcsBuilder::vevent(
			array(
				'uid'            => 'z2@example.com',
				'dtstamp'        => 0,
				'start'          => 0,
				'end'            => 0,
				'all_day'        => false,
				'tz'             => $this->berlin,
				'organizer_name' => 'ACME Ltd',
			)
		);
		$this->assertStringNotContainsString( 'ORGANIZER', $without );
	}

	public function test_calendar_envelope(): void {
		$vevent = IcsBuilder::vevent(
			array(
				'uid'     => 'a@example.com',
				'dtstamp' => 0,
				'start'   => 0,
				'end'     => 0,
				'all_day' => false,
				'tz'      => $this->berlin,
				'summary' => 'A',
			)
		);
		$cal = IcsBuilder::calendar( array( $vevent ), '-//Test//EN', 'My Cal', 12 );

		$this->assertStringStartsWith( "BEGIN:VCALENDAR\r\n", $cal );
		$this->assertStringContainsString( 'VERSION:2.0', $cal );
		$this->assertStringContainsString( 'PRODID:-//Test//EN', $cal );
		$this->assertStringContainsString( 'X-WR-CALNAME:My Cal', $cal );
		$this->assertStringContainsString( 'REFRESH-INTERVAL;VALUE=DURATION:PT12H', $cal );
		$this->assertStringContainsString( 'BEGIN:VEVENT', $cal );
		$this->assertStringEndsWith( "END:VCALENDAR\r\n", $cal );
	}
}
