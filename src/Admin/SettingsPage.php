<?php
/**
 * Plugin settings page: submenu registration, settings API wiring, the tabbed
 * settings screen, and the Gutenberg toggle for events.
 *
 * @package VE_Events
 */

namespace VEV\Admin;

use VEV\Constants;
use VEV\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and renders the VE Events settings page.
 */
final class SettingsPage {

	/**
	 * Register the settings-page, settings-API, and editor hooks.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'add_settings_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_filter( 'use_block_editor_for_post_type', array( __CLASS__, 'maybe_disable_gutenberg' ), 10, 2 );
	}

	/**
	 * Disable Gutenberg for events when the setting is enabled.
	 *
	 * @param bool   $use_block_editor Whether to use the block editor.
	 * @param string $post_type        The post type being checked.
	 */
	public static function maybe_disable_gutenberg( bool $use_block_editor, string $post_type ): bool {
		if ( Constants::POST_TYPE !== $post_type ) {
			return $use_block_editor;
		}
		$settings = Settings::get();
		if ( ! empty( $settings['disable_gutenberg'] ) ) {
			return false;
		}
		return $use_block_editor;
	}

	/**
	 * Register the settings submenu page under the events menu.
	 */
	public static function add_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		add_submenu_page(
			'edit.php?post_type=' . Constants::POST_TYPE,
			__( 'VE Events Settings', 've-events' ),
			__( 'Settings', 've-events' ),
			'manage_options',
			'vev-settings',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	/**
	 * Register the settings group and flush hooks.
	 */
	public static function register_settings(): void {
		register_setting(
			'vev_settings_group',
			Constants::OPTION_SETTINGS,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( '\\VEV\\Settings', 'sanitize' ),
			)
		);

		add_action( 'update_option_' . Constants::OPTION_SETTINGS, array( __CLASS__, 'maybe_flush_rewrite_rules' ), 10, 2 );
		add_action(
			'update_option_' . Constants::OPTION_SETTINGS,
			static function () {
				Settings::flush_cache();
			},
			5
		);
	}

	/**
	 * Schedule a rewrite-rule flush when a URL slug changes.
	 *
	 * @param mixed $old_value Previous option value.
	 * @param mixed $new_value New option value.
	 */
	public static function maybe_flush_rewrite_rules( $old_value, $new_value ): void {
		$old_single  = $old_value['slug_single'] ?? 'event';
		$old_archive = $old_value['slug_archive'] ?? 'events';
		$new_single  = $new_value['slug_single'] ?? 'event';
		$new_archive = $new_value['slug_archive'] ?? 'events';

		if ( $old_single !== $new_single || $old_archive !== $new_archive ) {
			set_transient( 'vev_flush_rewrite_rules', 1, 60 );
		}
	}

	/**
	 * Render the tabbed settings screen.
	 */
	public static function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings   = Settings::get();
		$opt        = Constants::OPTION_SETTINGS;
		$import_url = add_query_arg(
			array(
				'post_type' => Constants::POST_TYPE,
				'page'      => 'vev-import',
			),
			admin_url( 'edit.php' )
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'VE Events', 've-events' ); ?></h1>
			<?php settings_errors(); ?>

			<nav class="nav-tab-wrapper" id="vev-settings-tabs">
				<a href="#tab-general"  class="nav-tab nav-tab-active"><?php esc_html_e( 'General', 've-events' ); ?></a>
				<a href="#tab-display"  class="nav-tab"><?php esc_html_e( 'Display', 've-events' ); ?></a>
				<a href="#tab-schema"   class="nav-tab"><?php esc_html_e( 'Schema &amp; SEO', 've-events' ); ?></a>
				<a href="#tab-series"   class="nav-tab"><?php esc_html_e( 'Series', 've-events' ); ?></a>
				<a href="#tab-docs"     class="nav-tab"><?php esc_html_e( 'Field Reference', 've-events' ); ?></a>
			</nav>

			<form method="post" action="options.php">
				<?php settings_fields( 'vev_settings_group' ); ?>

				<!-- TAB: General -->
				<div id="tab-general" class="vev-tab-panel">
					<h2><?php esc_html_e( 'URL Settings', 've-events' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Event Slug (Single)', 've-events' ); ?></th>
							<td>
								<input type="text" name="<?php echo esc_attr( $opt ); ?>[slug_single]" value="<?php echo esc_attr( $settings['slug_single'] ); ?>" class="regular-text" placeholder="event" />
								<p class="description"><?php esc_html_e( 'URL slug for single events, e.g. "event" or "veranstaltung".', 've-events' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Archive Slug', 've-events' ); ?></th>
							<td>
								<input type="text" name="<?php echo esc_attr( $opt ); ?>[slug_archive]" value="<?php echo esc_attr( $settings['slug_archive'] ); ?>" class="regular-text" placeholder="events" />
								<p class="description"><?php esc_html_e( 'URL slug for the event archive, e.g. "events" or "veranstaltungen".', 've-events' ); ?></p>
							</td>
						</tr>
					</table>
					<div class="notice notice-warning inline" style="margin:0 0 12px;">
						<p><?php esc_html_e( 'Changing slugs will automatically refresh permalinks. Existing event URLs will change.', 've-events' ); ?></p>
					</div>

					<h2><?php esc_html_e( 'Editor', 've-events' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Block Editor', 've-events' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[disable_gutenberg]" value="1" <?php checked( $settings['disable_gutenberg'] ); ?> />
									<?php esc_html_e( 'Disable Gutenberg for Events (use classic editor)', 've-events' ); ?>
								</label>
							</td>
						</tr>
					</table>

					<h2><?php esc_html_e( 'Calendar Import', 've-events' ); ?></h2>
					<p>
					<?php
						printf(
							/* translators: %s: URL to import page */
							wp_kses( __( 'Manage ICS import feeds (Google Calendar, Outlook, etc.) on the <a href="%s">Import Feeds</a> page.', 've-events' ), array( 'a' => array( 'href' => array() ) ) ),
							esc_url( $import_url )
						);
					?>
					</p>
					<a href="<?php echo esc_url( $import_url ); ?>" class="button"><?php esc_html_e( 'Manage Import Feeds', 've-events' ); ?></a>

					<h2><?php esc_html_e( 'Tools', 've-events' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Re-computes _vev_start_date, _vev_start_month and _vev_time_slot for all existing events. Run this once after updating the plugin.', 've-events' ); ?></p>
					<button type="button" class="button" id="vev-resync-meta"><?php esc_html_e( 'Sync computed fields (JetEngine filters)', 've-events' ); ?></button>
					<span id="vev-resync-result" style="margin-left:10px;color:#2271b1;"></span>
				</div>

				<!-- TAB: Display -->
				<div id="tab-display" class="vev-tab-panel" hidden>
					<h2><?php esc_html_e( 'Date &amp; Time Display', 've-events' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Hide Same-Day End Date', 've-events' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[hide_end_same_day]" value="1" <?php checked( $settings['hide_end_same_day'] ); ?> />
									<?php esc_html_e( 'Hide the end date when start and end are on the same day', 've-events' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Example output: "12.06.2025 · 10:00 – 12:00" instead of "12.06.2025 · 10:00 – 12.06.2025 · 12:00"', 've-events' ); ?></p>
							</td>
						</tr>
					</table>

					<h2><?php esc_html_e( 'Event Visibility', 've-events' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Grace Period After Event End', 've-events' ); ?></th>
							<td>
								<select name="<?php echo esc_attr( $opt ); ?>[grace_period]">
									<option value="0" <?php selected( $settings['grace_period'], 0 ); ?>><?php esc_html_e( 'Sofort ausblenden', 've-events' ); ?></option>
									<option value="1" <?php selected( $settings['grace_period'], 1 ); ?>><?php esc_html_e( '1 Stunde', 've-events' ); ?></option>
									<option value="2" <?php selected( $settings['grace_period'], 2 ); ?>><?php esc_html_e( '2 Stunden', 've-events' ); ?></option>
									<option value="4" <?php selected( $settings['grace_period'], 4 ); ?>><?php esc_html_e( '4 Stunden', 've-events' ); ?></option>
									<option value="6" <?php selected( $settings['grace_period'], 6 ); ?>><?php esc_html_e( '6 Stunden', 've-events' ); ?></option>
									<option value="12" <?php selected( $settings['grace_period'], 12 ); ?>><?php esc_html_e( '12 Stunden', 've-events' ); ?></option>
									<option value="24" <?php selected( $settings['grace_period'], 24 ); ?>><?php esc_html_e( '1 Tag (empfohlen)', 've-events' ); ?></option>
									<option value="72" <?php selected( $settings['grace_period'], 72 ); ?>><?php esc_html_e( '3 Tage', 've-events' ); ?></option>
									<option value="168" <?php selected( $settings['grace_period'], 168 ); ?>><?php esc_html_e( '7 Tage', 've-events' ); ?></option>
									<option value="999999" <?php selected( $settings['grace_period'], 999999 ); ?>><?php esc_html_e( 'Immer anzeigen', 've-events' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'How long events stay visible on the frontend after ending. Backend is never affected.', 've-events' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Search Results', 've-events' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[hide_archived_search]" value="1" <?php checked( $settings['hide_archived_search'] ); ?> />
									<?php esc_html_e( 'Exclude archived events from WordPress search results', 've-events' ); ?>
								</label>
							</td>
						</tr>
					</table>

					<h2><?php esc_html_e( 'Category Colors', 've-events' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Output Category Colors as CSS', 've-events' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[output_category_colors]" value="1" <?php checked( $settings['output_category_colors'] ); ?> />
									<?php esc_html_e( 'Emit CSS custom properties and utility classes for event category colors in wp_head', 've-events' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Outputs :root{--vev-cat-slug:#hex;} and .ve-cat-slug{--vev-cat-color:#hex;} for use with Elementor/CSS.', 've-events' ); ?></p>
							</td>
						</tr>
					</table>

					<h2><?php esc_html_e( 'Event Status Logic', 've-events' ); ?></h2>
					<table class="widefat striped" style="max-width:520px;">
						<thead><tr><th><?php esc_html_e( 'Status', 've-events' ); ?></th><th><?php esc_html_e( 'Condition', 've-events' ); ?></th></tr></thead>
						<tbody>
							<tr><td><code>upcoming</code></td><td><?php esc_html_e( 'Event has not started yet', 've-events' ); ?></td></tr>
							<tr><td><code>ongoing</code></td><td><?php esc_html_e( 'Currently running', 've-events' ); ?></td></tr>
							<tr><td><code>past</code></td><td><?php esc_html_e( 'Ended, within grace period', 've-events' ); ?></td></tr>
							<tr><td><code>archived</code></td><td><?php esc_html_e( 'Ended, grace period exceeded', 've-events' ); ?></td></tr>
						</tbody>
					</table>
				</div>

				<!-- TAB: Schema -->
				<div id="tab-schema" class="vev-tab-panel" hidden>
					<h2><?php esc_html_e( 'Schema.org Event Markup', 've-events' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Structured data is automatically output on single event pages as JSON-LD. Location address is included when set on the location term.', 've-events' ); ?></p>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Include Event Series', 've-events' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[include_series_schema]" value="1" <?php checked( $settings['include_series_schema'] ); ?> />
									<?php esc_html_e( 'Add series name to Schema.org as superEvent / EventSeries', 've-events' ); ?>
								</label>
							</td>
						</tr>
					</table>

					<h2><?php esc_html_e( 'Open Graph / Social Meta Tags', 've-events' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Outputs og: and twitter: meta tags on single event pages for social sharing previews.', 've-events' ); ?></p>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Open Graph Tags', 've-events' ); ?></th>
							<td>
								<select name="<?php echo esc_attr( $opt ); ?>[og_tags]">
									<option value="auto" <?php selected( $settings['og_tags'], 'auto' ); ?>><?php esc_html_e( 'Auto (skip if Yoast / Rank Math / AIOSEO active)', 've-events' ); ?></option>
									<option value="always" <?php selected( $settings['og_tags'], 'always' ); ?>><?php esc_html_e( 'Always output', 've-events' ); ?></option>
									<option value="disabled" <?php selected( $settings['og_tags'], 'disabled' ); ?>><?php esc_html_e( 'Disabled', 've-events' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'Auto: outputs OG tags only when no dedicated SEO plugin (Yoast, Rank Math, AIOSEO, SEO Framework) is active. Use "Always" to force output even alongside an SEO plugin. Use "Disabled" to turn off completely.', 've-events' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<!-- TAB: Series -->
				<div id="tab-series" class="vev-tab-panel" hidden>
					<h2><?php esc_html_e( 'Series Detection', 've-events' ); ?></h2>
					<p class="description"><?php esc_html_e( 'ICS import automatically groups events by UID into series. The feature below adds detection for manually created events.', 've-events' ); ?></p>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Series Suggestions', 've-events' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[series_suggestions]" value="1" <?php checked( $settings['series_suggestions'] ); ?> />
									<?php esc_html_e( 'Suggest series assignments for events with matching titles', 've-events' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'When saving an event without a series, the plugin finds other events with the same title. A notice in the editor lets you create a new series, assign to an existing one, or dismiss.', 've-events' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<!-- TAB: Documentation -->
				<div id="tab-docs" class="vev-tab-panel" hidden>
					<h2><?php esc_html_e( 'Stored Meta Keys', 've-events' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Use these for sorting, filtering, and meta conditions in JetEngine/WP_Query:', 've-events' ); ?></p>
					<table class="widefat striped" style="max-width:640px;">
						<thead><tr><th><?php esc_html_e( 'Key', 've-events' ); ?></th><th><?php esc_html_e( 'Description', 've-events' ); ?></th></tr></thead>
						<tbody>
							<tr><td><code>_vev_start_utc</code></td><td><?php esc_html_e( 'Start timestamp (UTC integer)', 've-events' ); ?></td></tr>
							<tr><td><code>_vev_end_utc</code></td><td><?php esc_html_e( 'End timestamp (UTC integer)', 've-events' ); ?></td></tr>
							<tr><td><code>_vev_all_day</code></td><td><?php esc_html_e( 'All-day event (1/0)', 've-events' ); ?></td></tr>
							<tr><td><code>_vev_hide_end</code></td><td><?php esc_html_e( 'Hide end time (1/0)', 've-events' ); ?></td></tr>
							<tr><td><code>_vev_speaker</code></td><td><?php esc_html_e( 'Speaker / Host', 've-events' ); ?></td></tr>
							<tr><td><code>_vev_special_info</code></td><td><?php esc_html_e( 'Special information', 've-events' ); ?></td></tr>
							<tr><td><code>_vev_info_url</code></td><td><?php esc_html_e( 'Info / Ticket URL', 've-events' ); ?></td></tr>
							<tr><td><code>_vev_event_status</code></td><td><?php esc_html_e( 'Status override: cancelled | postponed | rescheduled | movedOnline | (empty = scheduled)', 've-events' ); ?></td></tr>
							<tr><td><code>_vev_start_hour</code></td><td><?php esc_html_e( 'Start hour in site timezone (0–23) — auto-computed, use for time-of-day filtering', 've-events' ); ?></td></tr>
							<tr><td><code>_vev_weekday</code></td><td><?php esc_html_e( 'ISO weekday in site timezone (1=Mon … 7=Sun) — auto-computed, use for weekday filtering', 've-events' ); ?></td></tr>
						</tbody>
					</table>

					<h2 style="margin-top:24px;"><?php esc_html_e( 'Virtual / Computed Fields', 've-events' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Available as Elementor Dynamic Tags and JetEngine fields. Computed at runtime:', 've-events' ); ?></p>
					<table class="widefat striped" style="max-width:640px;">
						<thead><tr><th><?php esc_html_e( 'Key', 've-events' ); ?></th><th><?php esc_html_e( 'Description', 've-events' ); ?></th></tr></thead>
						<tbody>
							<tr><td><code>ve_start_date</code></td><td><?php esc_html_e( 'Start date (formatted)', 've-events' ); ?></td></tr>
							<tr><td><code>ve_start_time</code></td><td><?php esc_html_e( 'Start time (formatted)', 've-events' ); ?></td></tr>
							<tr><td><code>ve_end_date</code></td><td><?php esc_html_e( 'End date (formatted)', 've-events' ); ?></td></tr>
							<tr><td><code>ve_end_time</code></td><td><?php esc_html_e( 'End time (formatted)', 've-events' ); ?></td></tr>
							<tr><td><code>ve_date_range</code></td><td><?php esc_html_e( 'Smart date range', 've-events' ); ?></td></tr>
							<tr><td><code>ve_time_range</code></td><td><?php esc_html_e( 'Time range or "All day"', 've-events' ); ?></td></tr>
							<tr><td><code>ve_datetime_formatted</code></td><td><?php esc_html_e( 'Full date &amp; time', 've-events' ); ?></td></tr>
							<tr><td><code>ve_status</code></td><td><?php esc_html_e( 'upcoming / ongoing / past / archived', 've-events' ); ?></td></tr>
							<tr><td><code>ve_location_name</code></td><td><?php esc_html_e( 'Location name (taxonomy)', 've-events' ); ?></td></tr>
							<tr><td><code>ve_location_address</code></td><td><?php esc_html_e( 'Location address', 've-events' ); ?></td></tr>
							<tr><td><code>ve_location_maps_url</code></td><td><?php esc_html_e( 'Google Maps URL (auto or custom)', 've-events' ); ?></td></tr>
							<tr><td><code>ve_category_name</code></td><td><?php esc_html_e( 'Category name', 've-events' ); ?></td></tr>
							<tr><td><code>ve_category_color</code></td><td><?php esc_html_e( 'Category color (hex)', 've-events' ); ?></td></tr>
							<tr><td><code>ve_category_class</code></td><td><?php esc_html_e( 'CSS class for primary category, e.g. "ve-cat-konzert"', 've-events' ); ?></td></tr>
							<tr><td><code>ve_series_name</code></td><td><?php esc_html_e( 'Series name', 've-events' ); ?></td></tr>
							<tr><td><code>ve_topic_names</code></td><td><?php esc_html_e( 'Topic names (comma-separated)', 've-events' ); ?></td></tr>
							<tr><td><code>ve_is_upcoming</code></td><td><?php esc_html_e( '1 if event has not started yet', 've-events' ); ?></td></tr>
							<tr><td><code>ve_is_ongoing</code></td><td><?php esc_html_e( '1 if event is currently running', 've-events' ); ?></td></tr>
							<tr><td><code>ve_event_status_label</code></td><td><?php esc_html_e( 'Human-readable status: Cancelled / Postponed / Rescheduled / Moved Online', 've-events' ); ?></td></tr>
							<tr><td><code>ve_event_status_color</code></td><td><?php esc_html_e( 'Hex color for status badge (red / amber / blue)', 've-events' ); ?></td></tr>
							<tr><td><code>ve_is_cancelled</code></td><td><?php esc_html_e( '1 if event is cancelled', 've-events' ); ?></td></tr>
						</tbody>
					</table>

					<h2 style="margin-top:24px;"><?php esc_html_e( 'Query Vars (URL / WP_Query)', 've-events' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Pass these as URL parameters or WP_Query arguments for filtering:', 've-events' ); ?></p>
					<table class="widefat striped" style="max-width:640px;">
						<thead><tr><th><?php esc_html_e( 'Var', 've-events' ); ?></th><th><?php esc_html_e( 'Values', 've-events' ); ?></th><th><?php esc_html_e( 'Effect', 've-events' ); ?></th></tr></thead>
						<tbody>
							<tr><td><code>vev_event_scope</code></td><td><code>upcoming | ongoing | past | archived | all</code></td><td><?php esc_html_e( 'Filter by lifecycle status', 've-events' ); ?></td></tr>
							<tr><td><code>vev_include_archived</code></td><td><code>1</code></td><td><?php esc_html_e( 'Include archived events (default: hidden)', 've-events' ); ?></td></tr>
							<tr><td><code>vev_month</code></td><td><code>2025-06</code></td><td><?php esc_html_e( 'Show only events starting in this month (YYYY-MM). Bypasses archived cutoff.', 've-events' ); ?></td></tr>
							<tr><td><code>vev_date_from</code></td><td><code>2025-06-01</code></td><td><?php esc_html_e( 'Events starting on or after this date (Y-m-d or UTC timestamp). Bypasses archived cutoff.', 've-events' ); ?></td></tr>
							<tr><td><code>vev_date_to</code></td><td><code>2025-06-30</code></td><td><?php esc_html_e( 'Events starting on or before this date (Y-m-d or UTC timestamp). Bypasses archived cutoff.', 've-events' ); ?></td></tr>
							<tr><td><code>vev_time_from</code></td><td><code>18</code></td><td><?php esc_html_e( 'Events starting at or after this hour (0–23, site timezone)', 've-events' ); ?></td></tr>
							<tr><td><code>vev_time_to</code></td><td><code>22</code></td><td><?php esc_html_e( 'Events starting at or before this hour (0–23, site timezone)', 've-events' ); ?></td></tr>
							<tr><td><code>vev_weekday</code></td><td><code>5</code> <?php esc_html_e( 'or', 've-events' ); ?> <code>1,3,5</code></td><td><?php esc_html_e( 'ISO weekday(s): 1=Mon … 7=Sun. Comma-separated list allowed.', 've-events' ); ?></td></tr>
						</tbody>
					</table>

					<h2 style="margin-top:24px;"><?php esc_html_e( 'JetEngine Query Setup', 've-events' ); ?></h2>
					<table class="widefat striped" style="max-width:640px;">
						<thead><tr><th><?php esc_html_e( 'Setting', 've-events' ); ?></th><th><?php esc_html_e( 'Upcoming', 've-events' ); ?></th><th><?php esc_html_e( 'Past', 've-events' ); ?></th></tr></thead>
						<tbody>
							<tr><td><?php esc_html_e( 'Post Type', 've-events' ); ?></td><td><code>ve_event</code></td><td><code>ve_event</code></td></tr>
							<tr><td><?php esc_html_e( 'Order By', 've-events' ); ?></td><td><?php esc_html_e( 'Meta Value (Number)', 've-events' ); ?></td><td><?php esc_html_e( 'Meta Value (Number)', 've-events' ); ?></td></tr>
							<tr><td><?php esc_html_e( 'Meta Key', 've-events' ); ?></td><td><code>_vev_start_utc</code></td><td><code>_vev_start_utc</code></td></tr>
							<tr><td><?php esc_html_e( 'Order', 've-events' ); ?></td><td>ASC</td><td>DESC</td></tr>
							<tr><td><?php esc_html_e( 'Query Var', 've-events' ); ?></td><td>—</td><td><code>vev_event_scope = past</code></td></tr>
						</tbody>
					</table>
				</div>

				<div class="vev-tab-actions">
					<?php submit_button( null, 'primary', 'submit', false ); ?>
				</div>
			</form>
		</div>
		<?php
	}
}
