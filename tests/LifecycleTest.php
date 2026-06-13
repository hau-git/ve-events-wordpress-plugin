<?php
/**
 * Boundary tests for the lifecycle status logic (upcoming/ongoing/past/archived).
 *
 * @package VE_Events
 */

declare( strict_types=1 );

namespace VEV\Tests;

use PHPUnit\Framework\TestCase;
use VEV\Support\Lifecycle;

final class LifecycleTest extends TestCase {

	private const NOW   = 1_000_000;
	private const GRACE = 3600; // 1 hour.

	public function test_no_start_is_upcoming(): void {
		$this->assertSame( 'upcoming', Lifecycle::status( 0, 0, self::NOW, self::GRACE ) );
	}

	public function test_before_start_is_upcoming(): void {
		$this->assertSame( 'upcoming', Lifecycle::status( self::NOW + 100, self::NOW + 200, self::NOW, self::GRACE ) );
	}

	public function test_at_start_is_ongoing(): void {
		$this->assertSame( 'ongoing', Lifecycle::status( self::NOW, self::NOW + 200, self::NOW, self::GRACE ) );
	}

	public function test_within_range_is_ongoing(): void {
		$this->assertSame( 'ongoing', Lifecycle::status( self::NOW - 100, self::NOW + 100, self::NOW, self::GRACE ) );
	}

	public function test_at_end_is_ongoing(): void {
		$this->assertSame( 'ongoing', Lifecycle::status( self::NOW - 100, self::NOW, self::NOW, self::GRACE ) );
	}

	public function test_within_grace_is_past(): void {
		$end = self::NOW - 100;
		$this->assertSame( 'past', Lifecycle::status( $end - 100, $end, self::NOW, self::GRACE ) );
	}

	public function test_at_grace_boundary_is_past(): void {
		$end = self::NOW - self::GRACE;
		$this->assertSame( 'past', Lifecycle::status( $end - 100, $end, self::NOW, self::GRACE ) );
	}

	public function test_beyond_grace_is_archived(): void {
		$end = self::NOW - self::GRACE - 1;
		$this->assertSame( 'archived', Lifecycle::status( $end - 100, $end, self::NOW, self::GRACE ) );
	}

	public function test_missing_end_falls_back_to_start(): void {
		// End defaults to start; at start it is ongoing.
		$this->assertSame( 'ongoing', Lifecycle::status( self::NOW, 0, self::NOW, self::GRACE ) );
	}

	public function test_cutoff_uses_supplied_now(): void {
		$this->assertSame( self::NOW - self::GRACE, self::NOW - 3600 );
	}

	public function test_labels(): void {
		$this->assertSame( 'Upcoming', Lifecycle::label( 'upcoming' ) );
		$this->assertSame( 'Ongoing', Lifecycle::label( 'ongoing' ) );
		$this->assertSame( 'Past', Lifecycle::label( 'past' ) );
		$this->assertSame( 'Archived', Lifecycle::label( 'archived' ) );
		$this->assertSame( 'Upcoming', Lifecycle::label( 'anything-else' ) );
	}
}
