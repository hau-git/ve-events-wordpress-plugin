<?php
/**
 * Locks the cross-feed identity helpers (ChurchDesk event id extraction +
 * title normalisation) used to merge the same event across feeds.
 *
 * @package VE_Events
 */

declare( strict_types=1 );

namespace VEV\Tests;

use PHPUnit\Framework\TestCase;
use VEV\Import\ChurchDesk\Identity;

final class ChurchDeskIdentityTest extends TestCase {

	public function test_plain_uid(): void {
		$this->assertSame( '38523886', Identity::from_ical_uid( '38523886@churchdesk.com' ) );
	}

	public function test_prefixed_uid(): void {
		$this->assertSame( '38523886', Identity::from_ical_uid( 'event-38523886@churchdesk.com' ) );
		$this->assertSame( '38523886', Identity::from_ical_uid( 'churchdesk-38523886@cal.churchdesk.com' ) );
	}

	public function test_from_url_fallback(): void {
		$this->assertSame(
			'38523886',
			Identity::from_ical_uid( 'opaque-uid-no-id', 'https://landing.churchdesk.com/de/e/38523886/krabbelgruppe' )
		);
	}

	public function test_long_digit_run_fallback(): void {
		$this->assertSame( '38523886', Identity::from_ical_uid( 'ABC38523886XYZ' ) );
	}

	public function test_returns_null_for_unrelated_uid(): void {
		$this->assertNull( Identity::from_ical_uid( 'abcd-efgh-ijkl@google.com' ) );
		$this->assertNull( Identity::from_ical_uid( '' ) );
	}

	public function test_normalize_title(): void {
		$this->assertSame( 'café international', Identity::normalize_title( "  Café   International \n" ) );
		$this->assertSame( 'krabbelgruppe', Identity::normalize_title( '<b>Krabbelgruppe</b>' ) );
		$this->assertSame(
			Identity::normalize_title( 'Gottesdienst' ),
			Identity::normalize_title( 'GOTTESDIENST ' )
		);
	}
}
