<?php
/**
 * Locks the Schema.org eventAttendanceMode mapping, including the movedOnline
 * precedence rule.
 *
 * @package VE_Events
 */

declare( strict_types=1 );

namespace VEV\Tests;

use PHPUnit\Framework\TestCase;
use VEV\Support\AttendanceMode;

final class AttendanceModeTest extends TestCase {

	public function test_default_is_offline(): void {
		$this->assertSame( 'https://schema.org/OfflineEventAttendanceMode', AttendanceMode::schema_uri( '' ) );
		$this->assertSame( 'https://schema.org/OfflineEventAttendanceMode', AttendanceMode::schema_uri( 'nonsense' ) );
	}

	public function test_online_and_mixed(): void {
		$this->assertSame( 'https://schema.org/OnlineEventAttendanceMode', AttendanceMode::schema_uri( 'online' ) );
		$this->assertSame( 'https://schema.org/MixedEventAttendanceMode', AttendanceMode::schema_uri( 'mixed' ) );
	}

	public function test_moved_online_status_forces_online(): void {
		$this->assertSame( 'https://schema.org/OnlineEventAttendanceMode', AttendanceMode::schema_uri( '', 'movedOnline' ) );
		$this->assertSame( 'https://schema.org/OnlineEventAttendanceMode', AttendanceMode::schema_uri( 'mixed', 'movedOnline' ) );
	}

	public function test_other_statuses_do_not_override(): void {
		$this->assertSame( 'https://schema.org/OfflineEventAttendanceMode', AttendanceMode::schema_uri( '', 'cancelled' ) );
		$this->assertSame( 'https://schema.org/MixedEventAttendanceMode', AttendanceMode::schema_uri( 'mixed', 'postponed' ) );
	}

	public function test_is_online(): void {
		$this->assertFalse( AttendanceMode::is_online( '' ) );
		$this->assertTrue( AttendanceMode::is_online( 'online' ) );
		$this->assertTrue( AttendanceMode::is_online( 'mixed' ) );
		$this->assertTrue( AttendanceMode::is_online( '', 'movedOnline' ) );
	}

	public function test_labels(): void {
		$this->assertSame( 'Online', AttendanceMode::label( 'online' ) );
		$this->assertSame( 'Mixed (offline + online)', AttendanceMode::label( 'mixed' ) );
		$this->assertSame( '', AttendanceMode::label( '' ) );
	}

	public function test_options(): void {
		$this->assertSame( array( 'online', 'mixed' ), AttendanceMode::OPTIONS );
	}
}
