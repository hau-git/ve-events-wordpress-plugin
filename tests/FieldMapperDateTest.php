<?php
/**
 * Locks the ICS date → UTC conversion used by the iCal importer
 * (FieldMapper::ics_date_to_utc / is_date_only) so timezone and DATE-only
 * handling cannot silently drift.
 *
 * @package VE_Events
 */

declare( strict_types=1 );

namespace VEV\Tests;

use PHPUnit\Framework\TestCase;
use VEV\Import\FieldMapper;

final class FieldMapperDateTest extends TestCase {

	public function test_parser_timestamp_fast_path_wins(): void {
		// Index 2 (parser-precomputed UTC timestamp) takes precedence over the string.
		$ts = FieldMapper::ics_date_to_utc( array( array(), '20250115T143000', 1736952600 ), '20250115T143000' );
		$this->assertSame( 1736952600, $ts );
	}

	public function test_date_only_string(): void {
		$ts = FieldMapper::ics_date_to_utc( array( array(), '20250115' ), '20250115' );
		$this->assertSame( '2025-01-15 00:00:00', gmdate( 'Y-m-d H:i:s', (int) $ts ) );
	}

	public function test_utc_datetime_with_z_suffix(): void {
		$ts = FieldMapper::ics_date_to_utc( array( array(), '20250115T143000Z' ), '20250115T143000Z' );
		$this->assertSame( '2025-01-15 14:30:00', gmdate( 'Y-m-d H:i:s', (int) $ts ) );
	}

	public function test_tzid_param_converts_to_utc(): void {
		// 14:30 Berlin winter time (CET, +1) == 13:30 UTC.
		$ts = FieldMapper::ics_date_to_utc(
			array( array( 'TZID' => 'Europe/Berlin' ), '20250115T143000' ),
			'20250115T143000'
		);
		$this->assertSame( '2025-01-15 13:30:00', gmdate( 'Y-m-d H:i:s', (int) $ts ) );
	}

	public function test_floating_time_defaults_to_utc(): void {
		$ts = FieldMapper::ics_date_to_utc( array( array(), '20250115T143000' ), '20250115T143000' );
		$this->assertSame( '2025-01-15 14:30:00', gmdate( 'Y-m-d H:i:s', (int) $ts ) );
	}

	public function test_invalid_tzid_falls_back_to_utc(): void {
		$ts = FieldMapper::ics_date_to_utc(
			array( array( 'TZID' => 'Not/AZone' ), '20250115T143000' ),
			'20250115T143000'
		);
		$this->assertSame( '2025-01-15 14:30:00', gmdate( 'Y-m-d H:i:s', (int) $ts ) );
	}

	public function test_empty_string_returns_null(): void {
		$this->assertNull( FieldMapper::ics_date_to_utc( array( array() ), '' ) );
	}

	public function test_malformed_string_returns_null(): void {
		$this->assertNull( FieldMapper::ics_date_to_utc( array( array() ), 'not-a-date' ) );
	}

	public function test_is_date_only(): void {
		$this->assertTrue( FieldMapper::is_date_only( '20250115' ) );
		$this->assertFalse( FieldMapper::is_date_only( '20250115T143000' ) );
		$this->assertFalse( FieldMapper::is_date_only( '20250115T143000Z' ) );
		$this->assertFalse( FieldMapper::is_date_only( '' ) );
	}
}
