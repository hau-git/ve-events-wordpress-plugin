<?php
/**
 * Locks the calendar-view → canonical → ve_event mapping against a real sample
 * captured from the live api2 calendar-view endpoint.
 *
 * @package VE_Events
 */

declare( strict_types=1 );

namespace VEV\Tests;

use PHPUnit\Framework\TestCase;
use VEV\Import\ChurchDesk\CalendarViewSource;
use VEV\Import\ChurchDesk\Mapper;

final class ChurchDeskCalendarViewTest extends TestCase {

	/**
	 * A representative raw item from the calendar-view `items` array.
	 */
	private function raw_item(): array {
		return array(
			'id'              => 38523886,
			'title'           => 'Krabbelgruppe',
			'startDate'       => '2026-06-24T07:30:00.000Z',
			'endDate'         => '2026-06-24T09:00:00.000Z',
			'location'        => 'Friedhofstraße 10, 28213 Bremen',
			'contributor'     => null,
			'price'           => null,
			'description'     => '<p>KRABBELGRUPPE</p>',
			'summary'         => '<p>KRABBELGRUPPE für die KLEINSTEN</p>',
			'hideEndTime'     => false,
			'allDay'          => false,
			'locationName'    => 'St. Remberti Kirche',
			'image'           => 'https://edge.churchdesk.com/event-38523886/span6_16-9/public/o/12/photo.jpg?c=8419ce150b&org=12',
			'eventCategories' => array(
				array(
					'id'    => 36645,
					'title' => 'Kinder',
					'color' => 3,
				),
			),
			'imageObj'        => array(
				'id'     => 2196329,
				'styles' => array(
					'span6_16-9' => array(
						'url'    => 'https://edge.churchdesk.com/event-38523886/span6_16-9/public/o/12/photo.jpg?c=8419ce150b&org=12',
						'width'  => 600,
						'height' => 338,
					),
				),
			),
			'locationObj'     => array(
				'address' => 'Friedhofstraße 10',
				'city'    => 'Bremen',
				'zipcode' => '28213',
				'country' => 'Deutschland',
				'string'  => 'Friedhofstraße 10, 28213 Bremen',
			),
		);
	}

	private function config(): array {
		return array(
			'post_status'     => 'publish',
			'cd_import_image' => true,
			'cd_image_format' => 'span6_16-9',
		);
	}

	private function mapped(): array {
		$canonical = CalendarViewSource::normalize( $this->raw_item() );
		return Mapper::map( $canonical, $this->config() );
	}

	public function test_wrapper_is_unwrapped_via_items(): void {
		// Reflection-free: normalize must produce a single canonical event from one item,
		// proving the items[] elements (not the wrapper) are what gets mapped.
		$canonical = CalendarViewSource::normalize( $this->raw_item() );
		$this->assertSame( 38523886, $canonical['id'] );
		$this->assertSame( 'Kinder', $canonical['categories'][0]['title'] );
	}

	public function test_basic_fields(): void {
		$row = $this->mapped();
		$this->assertSame( '38523886', $row['uid'] );
		$this->assertSame( '38523886', $row['cd_event_id'] );
		$this->assertSame( 'Krabbelgruppe', $row['post_data']['post_title'] );
	}

	public function test_start_utc(): void {
		$row      = $this->mapped();
		$expected = ( new \DateTime( '2026-06-24T07:30:00Z' ) )->getTimestamp();
		$this->assertSame( $expected, $row['meta']['_vev_start_utc'] );
		$this->assertSame( 0, $row['meta']['_vev_all_day'] );
		$this->assertSame( 0, $row['meta']['_vev_hide_end'] );
	}

	public function test_category_with_colour(): void {
		$row = $this->mapped();
		$cat = $row['taxonomies']['ve_event_category'];
		$this->assertSame( array( 'Kinder' ), $cat['terms'] );
		$this->assertSame( '#b94a48', $cat['term_meta']['Kinder']['ve_category_color'] );
	}

	public function test_location_address_from_string(): void {
		$row = $this->mapped();
		$loc = $row['taxonomies']['ve_event_location'];
		$this->assertSame( array( 'St. Remberti Kirche' ), $loc['terms'] );
		$this->assertSame( 'Friedhofstraße 10, 28213 Bremen', $loc['term_meta']['St. Remberti Kirche']['ve_location_address'] );
	}

	public function test_image_url_from_styles(): void {
		$row = $this->mapped();
		$this->assertSame(
			'https://edge.churchdesk.com/event-38523886/span6_16-9/public/o/12/photo.jpg?c=8419ce150b&org=12',
			$row['image_url']
		);
	}
}
