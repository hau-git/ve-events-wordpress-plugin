<?php
/**
 * Wires up every admin component for VE Events on its original hook/priority.
 *
 * @package VE_Events
 */

namespace VEV\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers each admin component, preserving the legacy VEV_Admin hook set.
 */
final class Bootstrap {

	/**
	 * Initialize every admin component.
	 */
	public static function init(): void {
		EventForm::init();
		MetaBoxes::init();
		ListTable::init();
		ListFilters::init();
		SettingsPage::init();
		CalendarPage::init();
		CalendarAjax::init();
		Tools::init();
		SeriesSuggestions::init();
		Assets::init();

		TermMeta\LocationTermMeta::init();
		TermMeta\CategoryTermMeta::init();
	}
}
