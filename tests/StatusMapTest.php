<?php
/**
 * Locks the manual event-status override map (label / color / schema URI) so
 * the de-duplicated EventStatus helper stays byte-compatible with the strings
 * the plugin emitted before the refactor.
 *
 * @package VE_Events
 */

declare( strict_types=1 );

namespace VEV\Tests;

use PHPUnit\Framework\TestCase;
use VEV\Support\EventStatus;

final class StatusMapTest extends TestCase {

	public function test_labels(): void {
		$this->assertSame( 'Cancelled', EventStatus::label( 'cancelled' ) );
		$this->assertSame( 'Postponed', EventStatus::label( 'postponed' ) );
		$this->assertSame( 'Rescheduled', EventStatus::label( 'rescheduled' ) );
		$this->assertSame( 'Moved Online', EventStatus::label( 'movedOnline' ) );
		$this->assertSame( '', EventStatus::label( '' ) );
		$this->assertSame( '', EventStatus::label( 'nonsense' ) );
	}

	public function test_colors(): void {
		$this->assertSame( '#d63638', EventStatus::color( 'cancelled' ) );
		$this->assertSame( '#dba617', EventStatus::color( 'postponed' ) );
		$this->assertSame( '#2271b1', EventStatus::color( 'rescheduled' ) );
		$this->assertSame( '#007cba', EventStatus::color( 'movedOnline' ) );
		$this->assertSame( '', EventStatus::color( '' ) );
	}

	public function test_schema_uris(): void {
		$this->assertSame( 'https://schema.org/EventCancelled', EventStatus::schema_uri( 'cancelled' ) );
		$this->assertSame( 'https://schema.org/EventPostponed', EventStatus::schema_uri( 'postponed' ) );
		$this->assertSame( 'https://schema.org/EventRescheduled', EventStatus::schema_uri( 'rescheduled' ) );
		$this->assertSame( 'https://schema.org/EventMovedOnline', EventStatus::schema_uri( 'movedOnline' ) );
		$this->assertSame( 'https://schema.org/EventScheduled', EventStatus::schema_uri( '' ) );
		$this->assertSame( 'https://schema.org/EventScheduled', EventStatus::schema_uri( 'nonsense' ) );
	}

	public function test_badge_class(): void {
		$this->assertSame( 've-status-cancelled', EventStatus::badge_class( 'cancelled' ) );
		$this->assertSame( 've-status-movedOnline', EventStatus::badge_class( 'movedOnline' ) );
	}

	public function test_options_order(): void {
		$this->assertSame(
			array( 'cancelled', 'postponed', 'rescheduled', 'movedOnline' ),
			EventStatus::OPTIONS
		);
	}
}
