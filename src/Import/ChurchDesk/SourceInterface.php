<?php
/**
 * ChurchDesk Source contract.
 *
 * @package VE_Events
 */

namespace VEV\Import\ChurchDesk;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A ChurchDesk event source (Pull API or calendar-view).
 *
 * Implementations encapsulate one endpoint's authentication, pagination and
 * response shape, always returning events in the canonical v3.0.0 shape that
 * {@see Mapper} understands.
 */
interface SourceInterface {

	/**
	 * Fetches all matching events as canonical event arrays.
	 *
	 * @return array[] List of canonical event arrays.
	 * @throws \RuntimeException On a hard fetch/transport/auth error.
	 */
	public function fetch(): array;

	/**
	 * Tests the connection and returns a small preview.
	 *
	 * @return array{ok:bool,count:int,sample:array,error:string}
	 */
	public function test(): array;
}
