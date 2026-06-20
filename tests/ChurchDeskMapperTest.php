<?php
/**
 * Locks the ChurchDesk → ve_event field mapping so the importer keeps producing
 * the expected normalised rows.
 *
 * @package VE_Events
 */

declare( strict_types=1 );

namespace VEV\Tests;

use PHPUnit\Framework\TestCase;
use VEV\Import\ChurchDesk\Mapper;

final class ChurchDeskMapperTest extends TestCase {

	/**
	 * A representative event from the documented v3.0.0 /events response.
	 */
	private function sample_event(): array {
		return array(
			'id'           => '3408',
			'title'        => 'Gudstjeneste',
			'description'  => '<p>Alle er velkommen</p>',
			'summary'      => '<p>Alle er velkommen</p>',
			'startDate'    => '2015-10-25T10:00:00Z',
			'endDate'      => '2015-10-25T11:00:00Z',
			'location'     => 'Njalsgade 21G, 2300 Copenhagen',
			'locationName' => 'ChurchDesk',
			'locationObj'  => array(
				'address' => 'Njalsgade 21G',
				'zipcode' => '2300',
				'city'    => 'Copenhagen',
				'country' => 'Denmark',
			),
			'contributor'  => 'Christian Steffensen',
			'price'        => null,
			'showEndtime'  => '1',
			'allDay'       => false,
			'categories'   => array(
				array(
					'id'    => '14',
					'title' => 'Gudstjeneste',
					'color' => '3',
				),
			),
			'image'        => array(
				'span7_16-9' => 'https://example.com/img/span7.jpg',
				'title'      => 'photo.jpg',
			),
			'updatedAt'    => '2022-01-01 10:00:00',
		);
	}

	private function config(): array {
		return array(
			'post_status'     => 'publish',
			'cd_import_image' => true,
			'cd_image_format' => 'span7_16-9',
		);
	}

	public function test_iso_to_utc_z(): void {
		$this->assertSame( 1445767200, Mapper::iso_to_utc( '2015-10-25T10:00:00Z' ) );
	}

	public function test_iso_to_utc_offset(): void {
		// 12:00 at +02:00 is 10:00 UTC — identical to the Z example above.
		$this->assertSame( 1445767200, Mapper::iso_to_utc( '2015-10-25T12:00:00+02:00' ) );
	}

	public function test_iso_to_utc_empty(): void {
		$this->assertNull( Mapper::iso_to_utc( null ) );
		$this->assertNull( Mapper::iso_to_utc( '' ) );
	}

	public function test_basic_fields(): void {
		$row = Mapper::map( $this->sample_event(), $this->config() );

		$this->assertSame( '3408', $row['uid'] );
		$this->assertSame( 'Gudstjeneste', $row['post_data']['post_title'] );
		$this->assertSame( 1445767200, $row['meta']['_vev_start_utc'] );
		$this->assertSame( 1445770800, $row['meta']['_vev_end_utc'] );
		$this->assertSame( 0, $row['meta']['_vev_all_day'] );
		$this->assertSame( 'Christian Steffensen', $row['meta']['_vev_speaker'] );
	}

	public function test_show_endtime_inverts_to_hide_end(): void {
		$event                = $this->sample_event();
		$event['showEndtime'] = '1';
		$row                  = Mapper::map( $event, $this->config() );
		$this->assertSame( 0, $row['meta']['_vev_hide_end'] );

		$event['showEndtime'] = '0';
		$row                  = Mapper::map( $event, $this->config() );
		$this->assertSame( 1, $row['meta']['_vev_hide_end'] );
	}

	public function test_hide_end_time_alias(): void {
		$event = $this->sample_event();
		unset( $event['showEndtime'] );
		$event['hideEndTime'] = true;
		$row                  = Mapper::map( $event, $this->config() );
		$this->assertSame( 1, $row['meta']['_vev_hide_end'] );
	}

	public function test_all_day(): void {
		$event           = $this->sample_event();
		$event['allDay'] = true;
		$row             = Mapper::map( $event, $this->config() );
		$this->assertSame( 1, $row['meta']['_vev_all_day'] );
	}

	public function test_null_price_omitted(): void {
		$row = Mapper::map( $this->sample_event(), $this->config() );
		$this->assertArrayNotHasKey( '_vev_special_info', $row['meta'] );
	}

	public function test_category_with_color(): void {
		$row = Mapper::map( $this->sample_event(), $this->config() );
		$cat = $row['taxonomies']['ve_event_category'];

		$this->assertSame( array( 'Gudstjeneste' ), $cat['terms'] );
		$this->assertTrue( $cat['create_terms'] );
		$this->assertSame( '#b94a48', $cat['term_meta']['Gudstjeneste']['ve_category_color'] );
	}

	public function test_color_to_hex_default(): void {
		$this->assertSame( '#999999', Mapper::color_to_hex( 999 ) );
	}

	public function test_location_address_composition(): void {
		$row = Mapper::map( $this->sample_event(), $this->config() );
		$loc = $row['taxonomies']['ve_event_location'];

		$this->assertSame( array( 'ChurchDesk' ), $loc['terms'] );
		$this->assertSame(
			'Njalsgade 21G, 2300 Copenhagen, Denmark',
			$loc['term_meta']['ChurchDesk']['ve_location_address']
		);
	}

	public function test_image_url_selected_by_format(): void {
		$row = Mapper::map( $this->sample_event(), $this->config() );
		$this->assertSame( 'https://example.com/img/span7.jpg', $row['image_url'] );
	}

	public function test_image_skipped_when_disabled(): void {
		$config                    = $this->config();
		$config['cd_import_image'] = false;
		$row                       = Mapper::map( $this->sample_event(), $config );
		$this->assertNull( $row['image_url'] );
	}

	public function test_hash_is_stable_and_change_sensitive(): void {
		$event = $this->sample_event();
		$first = Mapper::hash( $event );
		$this->assertSame( $first, Mapper::hash( $event ) );

		$event['title'] = 'Changed';
		$this->assertNotSame( $first, Mapper::hash( $event ) );
	}

	public function test_missing_id_or_start_returns_null(): void {
		$event = $this->sample_event();
		unset( $event['id'] );
		$this->assertNull( Mapper::map( $event, $this->config() ) );

		$event = $this->sample_event();
		$event['startDate'] = null;
		$this->assertNull( Mapper::map( $event, $this->config() ) );
	}

	public function test_end_falls_back_to_start(): void {
		$event              = $this->sample_event();
		$event['endDate']   = null;
		$row                = Mapper::map( $event, $this->config() );
		$this->assertSame( $row['meta']['_vev_start_utc'], $row['meta']['_vev_end_utc'] );
	}
}
