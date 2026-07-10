<?php
/**
 * Import Admin UI — feed list, feed edit form, run log.
 *
 * @package VE_Events
 */

namespace VEV\Import;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use VEV\Constants;

/**
 * Renders the import admin pages and handles their form/AJAX requests.
 */
class AdminPage {

	const PAGE_SLUG = 'vev-import';
	const NONCE     = 'vev_import_nonce';

	/**
	 * Hooks the admin menu, form handlers, AJAX handlers and asset enqueue.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_post_vev_import_save', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_vev_import_delete', array( __CLASS__, 'handle_delete' ) );
		add_action( 'admin_post_vev_import_toggle', array( __CLASS__, 'handle_toggle' ) );
		add_action( 'wp_ajax_vev_import_run_now', array( __CLASS__, 'ajax_run_now' ) );
		add_action( 'wp_ajax_vev_import_test', array( __CLASS__, 'ajax_test' ) );
		add_action( 'wp_ajax_vev_import_clear_log', array( __CLASS__, 'ajax_clear_log' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	// -------------------------------------------------------------------------
	// Menu
	// -------------------------------------------------------------------------

	/**
	 * Registers the Import submenu page under the event post type menu.
	 */
	public static function add_menu(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		add_submenu_page(
			'edit.php?post_type=' . Constants::POST_TYPE,
			__( 'Calendar Import', 've-events' ),
			__( 'Import', 've-events' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Enqueues assets needed on the import admin screen.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public static function enqueue_assets( string $hook ): void {
		if ( strpos( $hook, self::PAGE_SLUG ) === false ) {
			return;
		}
		wp_enqueue_style( 'dashicons' );
	}

	// -------------------------------------------------------------------------
	// Router
	// -------------------------------------------------------------------------

	/**
	 * Routes the import admin page to the list, edit or log view.
	 */
	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only navigation params; capability checked above.
		$action  = sanitize_key( $_GET['action'] ?? 'list' );
		$feed_id = absint( $_GET['feed_id'] ?? 0 );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		echo '<div class="wrap">';

		if ( 'edit' === $action ) {
			self::render_edit_page( $feed_id );
		} elseif ( 'log' === $action ) {
			self::render_log_page( $feed_id );
		} else {
			self::render_list_page();
		}

		echo '</div>';
	}

	// -------------------------------------------------------------------------
	// List page
	// -------------------------------------------------------------------------

	/**
	 * Renders the list of configured import feeds.
	 */
	private static function render_list_page(): void {
		$feeds = Feed::get_all();

		$add_url = self::page_url( array( 'action' => 'edit' ) );

		?>
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Calendar Import Feeds', 've-events' ); ?></h1>
		<a href="<?php echo esc_url( $add_url ); ?>" class="page-title-action">
			<?php esc_html_e( 'Add Feed', 've-events' ); ?>
		</a>
		<hr class="wp-header-end">

		<?php self::maybe_show_notice(); ?>

		<?php if ( empty( $feeds ) ) : ?>
			<p><?php esc_html_e( 'No import feeds configured yet. Add one to get started.', 've-events' ); ?></p>
		<?php else : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Name', 've-events' ); ?></th>
					<th><?php esc_html_e( 'URL', 've-events' ); ?></th>
					<th><?php esc_html_e( 'Schedule', 've-events' ); ?></th>
					<th><?php esc_html_e( 'Status', 've-events' ); ?></th>
					<th><?php esc_html_e( 'Last Run', 've-events' ); ?></th>
					<th><?php esc_html_e( 'Next Run', 've-events' ); ?></th>
					</tr>
			</thead>
			<tbody>
			<?php
			foreach ( $feeds as $feed ) :
				$cfg            = Feed::get_config( $feed->ID );
				$active         = $cfg['active'];
				$schedules      = Feed::get_schedules();
				$schedule_label = $schedules[ $cfg['schedule'] ] ?? $cfg['schedule'];
				$last_run       = $cfg['last_run'] ? wp_date( get_option( 'date_format' ) . ' H:i', $cfg['last_run'] ) : __( 'Never', 've-events' );
				$next_ts        = wp_next_scheduled( Manager::CRON_HOOK, array( $feed->ID ) );
				$next_run       = $next_ts ? wp_date( get_option( 'date_format' ) . ' H:i', $next_ts ) : '—';

				$edit_url   = self::page_url(
					array(
						'action'  => 'edit',
						'feed_id' => $feed->ID,
					)
				);
				$log_url    = self::page_url(
					array(
						'action'  => 'log',
						'feed_id' => $feed->ID,
					)
				);
				$delete_url = wp_nonce_url(
					admin_url( 'admin-post.php?action=vev_import_delete&feed_id=' . $feed->ID ),
					self::NONCE
				);
				$toggle_url = wp_nonce_url(
					admin_url( 'admin-post.php?action=vev_import_toggle&feed_id=' . $feed->ID ),
					self::NONCE
				);

				$last_status = $cfg['last_status'];
				$counts      = $cfg['last_counts'] ?? array();
				?>
			<tr>
				<td class="column-name">
					<strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $cfg['title'] ); ?></a></strong>
					<?php if ( ! $active ) : ?>
						<span class="post-state">(<?php esc_html_e( 'Inactive', 've-events' ); ?>)</span>
					<?php endif; ?>
					<div class="row-actions">
						<span class="edit"><a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 've-events' ); ?></a> | </span>
						<span class="view"><a href="<?php echo esc_url( $log_url ); ?>"><?php esc_html_e( 'Log', 've-events' ); ?></a> | </span>
						<span class="inline hide-if-no-js">
							<button type="button"
								class="button-link vev-run-now"
								data-feed-id="<?php echo esc_attr( $feed->ID ); ?>"
								data-nonce="<?php echo esc_attr( wp_create_nonce( 'vev_run_now_' . $feed->ID ) ); ?>">
								<?php esc_html_e( 'Run now', 've-events' ); ?>
							</button>
							<span class="vev-run-result"></span> |
						</span>
						<span class="<?php echo $active ? 'deactivate' : 'activate'; ?>">
							<a href="<?php echo esc_url( $toggle_url ); ?>"><?php echo $active ? esc_html__( 'Deactivate', 've-events' ) : esc_html__( 'Activate', 've-events' ); ?></a> |
						</span>
						<span class="delete">
							<a href="<?php echo esc_url( $delete_url ); ?>"
								class="submitdelete"
								onclick="return confirm('<?php esc_attr_e( 'Delete this feed?', 've-events' ); ?>')"><?php esc_html_e( 'Delete', 've-events' ); ?></a>
						</span>
					</div>
				</td>
				<td class="vev-url-cell">
					<?php
					if ( 'churchdesk' === ( $cfg['type'] ?? 'ics_url' ) ) {
						$endpoints = \VEV\Import\ChurchDesk\SourceFactory::get_endpoints();
						printf(
							/* translators: 1: endpoint label, 2: organization id. */
							esc_html__( 'ChurchDesk · %1$s · org %2$s', 've-events' ),
							esc_html( $endpoints[ $cfg['cd_endpoint'] ] ?? $cfg['cd_endpoint'] ),
							esc_html( $cfg['cd_org_id'] )
						);
					} else {
						echo esc_html( $cfg['url'] );
					}
					?>
				</td>
				<td><?php echo esc_html( $schedule_label ); ?></td>
				<td>
					<?php
					if ( 'success' === $last_status ) {
						echo '<span class="dashicons dashicons-yes-alt vev-status-ok"></span> ';
						esc_html_e( 'OK', 've-events' );
					} elseif ( 'error' === $last_status ) {
						echo '<span class="dashicons dashicons-dismiss vev-status-err"></span> ';
						esc_html_e( 'Error', 've-events' );
					} elseif ( 'partial' === $last_status ) {
						echo '<span class="dashicons dashicons-warning vev-status-warn"></span> ';
						esc_html_e( 'Partial', 've-events' );
					} else {
						echo '—';
					}
					if ( $last_status && $counts ) {
						printf(
							' <small>(+%d ~%d -%d)</small>',
							(int) ( $counts['created'] ?? 0 ),
							(int) ( $counts['updated'] ?? 0 ),
							(int) ( $counts['deleted'] ?? 0 )
						);
					}
					?>
				</td>
				<td><?php echo esc_html( $last_run ); ?></td>
				<td><?php echo esc_html( $next_run ); ?></td>
			</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php endif; ?>

		<?php self::inline_js(); ?>
		<?php
	}

	// -------------------------------------------------------------------------
	// Edit page
	// -------------------------------------------------------------------------

	/**
	 * Renders the add/edit form for a single feed.
	 *
	 * @param int $feed_id Feed post ID (0 for a new feed).
	 */
	private static function render_edit_page( int $feed_id ): void {
		$is_new = 0 === $feed_id;
		$cfg    = $is_new
			? array(
				'title'            => '',
				'type'             => 'ics_url',
				'url'              => '',
				'schedule'         => 'daily',
				'field_map'        => Feed::DEFAULT_FIELD_MAP,
				'tax_defaults'     => array(),
				'update_mode'      => 'if_newer',
				'delete_removed'   => false,
				'merge_cross_feed' => false,
				'post_status'      => 'publish',
				'http_timeout'     => 30,
				'active'           => true,
				'cd_endpoint'      => 'pull_api',
				'cd_org_id'        => '',
				'cd_token'         => '',
				'cd_categories'    => array(),
				'cd_image_format'  => 'span7_16-9',
				'cd_import_image'  => true,
			)
			: Feed::get_config( $feed_id );

		$save_url = admin_url( 'admin-post.php' );

		$heading = $is_new
			? __( 'Add Import Feed', 've-events' )
			: __( 'Edit Import Feed', 've-events' );

		$back_url = self::page_url();
		?>
		<h1>
			<?php echo esc_html( $heading ); ?>
		</h1>
		<p><a href="<?php echo esc_url( $back_url ); ?>" class="page-title-action">&larr; <?php esc_html_e( 'All Feeds', 've-events' ); ?></a></p>

		<form method="post" action="<?php echo esc_url( $save_url ); ?>">
			<?php wp_nonce_field( self::NONCE, '_vev_nonce' ); ?>
			<input type="hidden" name="action"  value="vev_import_save">
			<input type="hidden" name="feed_id" value="<?php echo esc_attr( $feed_id ); ?>">

			<!-- Source -->
			<h2><?php esc_html_e( 'Source', 've-events' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Feed Name', 've-events' ); ?></th>
					<td><input type="text" name="title" value="<?php echo esc_attr( $cfg['title'] ); ?>" class="regular-text" required></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Source Type', 've-events' ); ?></th>
					<td>
						<select name="type" id="vev-source-type">
							<option value="ics_url" <?php selected( $cfg['type'], 'ics_url' ); ?>><?php esc_html_e( 'iCal / ICS URL', 've-events' ); ?></option>
							<option value="churchdesk" <?php selected( $cfg['type'], 'churchdesk' ); ?>><?php esc_html_e( 'ChurchDesk', 've-events' ); ?></option>
						</select>
					</td>
				</tr>
			</table>

			<!-- iCal panel -->
			<table class="form-table vev-panel vev-panel-ics_url">
				<tr>
					<th><?php esc_html_e( 'ICS URL', 've-events' ); ?></th>
					<td>
						<input type="url" name="url" value="<?php echo esc_attr( $cfg['url'] ); ?>" class="large-text">
						<p class="description"><?php esc_html_e( 'The .ics subscription URL (e.g. from Outlook, Google Calendar, etc.)', 've-events' ); ?></p>
					</td>
				</tr>
			</table>

			<!-- ChurchDesk panel -->
			<table class="form-table vev-panel vev-panel-churchdesk">
				<tr>
					<th><?php esc_html_e( 'Endpoint', 've-events' ); ?></th>
					<td>
						<select name="cd_endpoint" id="vev-cd-endpoint">
						<?php foreach ( \VEV\Import\ChurchDesk\SourceFactory::get_endpoints() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $cfg['cd_endpoint'], $key ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
						</select>
						<p class="description">
							<?php esc_html_e( 'Pull API: documented, versioned (api.churchdesk.com/v3.0.0), requires a partner token from ChurchDesk support. Calendar View: public portal endpoint, organization id only.', 've-events' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Organization ID', 've-events' ); ?></th>
					<td><input type="text" name="cd_org_id" value="<?php echo esc_attr( $cfg['cd_org_id'] ); ?>" class="regular-text"></td>
				</tr>
				<tr class="vev-cd-token-row">
					<th><?php esc_html_e( 'Partner Token', 've-events' ); ?></th>
					<td>
						<?php
						// Never echo the stored token back into the page. An empty
						// submit keeps the saved value (see Feed::save_meta()).
						$has_token   = '' !== (string) $cfg['cd_token'];
						$placeholder = $has_token
							? __( 'Token saved — leave blank to keep', 've-events' )
							: '';
						?>
						<input type="password" name="cd_token" value="" class="large-text" autocomplete="new-password" placeholder="<?php echo esc_attr( $placeholder ); ?>">
						<p class="description">
							<?php if ( $has_token ) : ?>
								<?php esc_html_e( 'A partner token is stored. Enter a new one to replace it, or leave blank to keep it.', 've-events' ); ?>
							<?php else : ?>
								<?php esc_html_e( 'Obtain a partner token from support@churchdesk.com (Pull API only).', 've-events' ); ?>
							<?php endif; ?>
						</p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Category Filter', 've-events' ); ?></th>
					<td>
						<input type="text" name="cd_categories" value="<?php echo esc_attr( implode( ',', array_map( 'intval', (array) $cfg['cd_categories'] ) ) ); ?>" class="regular-text" placeholder="14, 16">
						<p class="description"><?php esc_html_e( 'Optional comma-separated category IDs to limit the import. Leave empty for all categories.', 've-events' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Featured Image', 've-events' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="cd_import_image" value="1" <?php checked( $cfg['cd_import_image'] ); ?>>
							<?php esc_html_e( 'Import the event image as the featured image', 've-events' ); ?>
						</label>
						<p>
							<label>
								<?php esc_html_e( 'Image format', 've-events' ); ?>
								<input type="text" name="cd_image_format" id="vev-cd-image-format"
									value="<?php echo esc_attr( $cfg['cd_image_format'] ); ?>"
									list="vev-cd-formats" class="regular-text" placeholder="span6_16-9">
								<datalist id="vev-cd-formats">
								<?php
								$formats = array( 'span3_16-9', 'span4_16-9', 'span5_16-9', 'span6_16-9', 'span7_16-9', 'span12_16-9' );
								foreach ( $formats as $format ) :
									?>
									<option value="<?php echo esc_attr( $format ); ?>"></option>
								<?php endforeach; ?>
								</datalist>
							</label>
							<br>
							<span class="description"><?php esc_html_e( 'Pull API typically uses span7_16-9; Calendar View uses span6_16-9.', 've-events' ); ?></span>
						</p>
					</td>
				</tr>
			</table>

			<!-- Common source settings -->
			<table class="form-table">
				<?php if ( ! $is_new ) : ?>
				<tr>
					<th><?php esc_html_e( 'Connection', 've-events' ); ?></th>
					<td>
						<button type="button" class="button" id="vev-test-btn"
							data-feed-id="<?php echo esc_attr( $feed_id ); ?>"
							data-nonce="<?php echo esc_attr( wp_create_nonce( 'vev_test_' . $feed_id ) ); ?>">
							<?php esc_html_e( 'Test Connection', 've-events' ); ?>
						</button>
						<span id="vev-test-result" style="margin-left:10px"></span>
						<p class="description"><?php esc_html_e( 'Save the feed first, then test against the saved settings.', 've-events' ); ?></p>
					</td>
				</tr>
				<?php endif; ?>
				<tr>
					<th><?php esc_html_e( 'Schedule', 've-events' ); ?></th>
					<td>
						<select name="schedule">
						<?php foreach ( Feed::get_schedules() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $cfg['schedule'], $key ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Active', 've-events' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="active" value="1" <?php checked( $cfg['active'] ); ?>>
							<?php esc_html_e( 'Enable automatic import', 've-events' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'HTTP Timeout', 've-events' ); ?></th>
					<td>
						<input type="number" name="http_timeout" value="<?php echo esc_attr( $cfg['http_timeout'] ); ?>" min="5" max="120" style="width:80px">
						<?php esc_html_e( 'seconds', 've-events' ); ?>
					</td>
				</tr>
			</table>

			<!-- Import behaviour -->
			<h2><?php esc_html_e( 'Import Behaviour', 've-events' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Update Mode', 've-events' ); ?></th>
					<td>
						<?php foreach ( Feed::get_update_modes() as $key => $label ) : ?>
						<label style="display:block;margin-bottom:4px">
							<input type="radio" name="update_mode" value="<?php echo esc_attr( $key ); ?>" <?php checked( $cfg['update_mode'], $key ); ?>>
							<?php echo esc_html( $label ); ?>
						</label>
						<?php endforeach; ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Imported Events Status', 've-events' ); ?></th>
					<td>
						<select name="post_status">
							<option value="publish" <?php selected( $cfg['post_status'], 'publish' ); ?>><?php esc_html_e( 'Published', 've-events' ); ?></option>
							<option value="draft"   <?php selected( $cfg['post_status'], 'draft' ); ?>><?php esc_html_e( 'Draft', 've-events' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Removed Events', 've-events' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="delete_removed" value="1" <?php checked( $cfg['delete_removed'] ); ?>>
							<?php esc_html_e( 'Move to Trash if removed from source', 've-events' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Cross-feed Merge', 've-events' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="merge_cross_feed" value="1" <?php checked( $cfg['merge_cross_feed'] ); ?>>
							<?php esc_html_e( 'Match the same event from other feeds and enrich it (e.g. add the image) instead of creating a duplicate', 've-events' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Matches on the ChurchDesk event id (e.g. iCal export + API of the same organization), with a start-time + title fallback.', 've-events' ); ?></p>
					</td>
				</tr>
			</table>

			<!-- Field Mapping (iCal only) -->
			<div class="vev-panel vev-panel-ics_url">
			<h2><?php esc_html_e( 'Field Mapping', 've-events' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Define how ICS fields are mapped to event fields. Dates are always imported automatically.', 've-events' ); ?></p>
			<table class="wp-list-table widefat fixed" style="max-width:700px">
				<thead>
					<tr>
						<th style="width:40px"><?php esc_html_e( 'On', 've-events' ); ?></th>
						<th><?php esc_html_e( 'ICS Field', 've-events' ); ?></th>
						<th><?php esc_html_e( 'Maps to', 've-events' ); ?></th>
						<th style="width:130px"><?php esc_html_e( 'Create Terms', 've-events' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php
				$sources = FieldMapper::get_sources();
				$targets = FieldMapper::get_targets();
				$map     = $cfg['field_map'];

				foreach ( $sources as $source_key => $source_label ) :
					$row_cfg      = $map[ $source_key ] ?? array();
					$enabled      = ! empty( $row_cfg['enabled'] );
					$target       = $row_cfg['target'] ?? '';
					$create_terms = ! empty( $row_cfg['create_terms'] );
					$is_tax       = in_array( $target, array( 've_event_category', 've_event_location', 've_event_topic' ), true );
					?>
				<tr>
					<td><input type="checkbox" name="field_map[<?php echo esc_attr( $source_key ); ?>][enabled]" value="1" <?php checked( $enabled ); ?>></td>
					<td><strong><?php echo esc_html( $source_label ); ?></strong></td>
					<td>
						<select name="field_map[<?php echo esc_attr( $source_key ); ?>][target]">
							<option value="">— <?php esc_html_e( 'Skip', 've-events' ); ?> —</option>
							<?php foreach ( $targets as $t_key => $t_label ) : ?>
							<option value="<?php echo esc_attr( $t_key ); ?>" <?php selected( $target, $t_key ); ?>>
								<?php echo esc_html( $t_label ); ?>
							</option>
							<?php endforeach; ?>
						</select>
					</td>
					<td>
						<label>
							<input type="checkbox" name="field_map[<?php echo esc_attr( $source_key ); ?>][create_terms]" value="1" <?php checked( $create_terms ); ?>>
							<?php esc_html_e( 'Auto-create', 've-events' ); ?>
						</label>
					</td>
				</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			</div><!-- /.vev-panel-ics_url -->

			<!-- Default Taxonomies -->
			<h2><?php esc_html_e( 'Default Taxonomy Assignment', 've-events' ); ?></h2>
			<p class="description"><?php esc_html_e( 'These terms will always be assigned to every imported event (in addition to mapped terms).', 've-events' ); ?></p>
			<?php
			$tax_configs = array(
				've_event_category' => __( 'Default Category', 've-events' ),
				've_event_location' => __( 'Default Location', 've-events' ),
				've_event_topic'    => __( 'Default Topic', 've-events' ),
			);
			?>
			<table class="form-table">
			<?php
			foreach ( $tax_configs as $taxonomy => $label ) :
				$defaults  = $cfg['tax_defaults'][ $taxonomy ] ?? array();
				$all_terms = get_terms(
					array(
						'taxonomy'   => $taxonomy,
						'hide_empty' => false,
					)
				);
				?>
			<tr>
				<th><?php echo esc_html( $label ); ?></th>
				<td>
					<?php if ( is_wp_error( $all_terms ) || empty( $all_terms ) ) : ?>
						<em><?php esc_html_e( 'No terms found.', 've-events' ); ?></em>
					<?php else : ?>
					<select name="tax_defaults[<?php echo esc_attr( $taxonomy ); ?>][]" multiple style="min-width:200px;height:100px">
						<?php foreach ( $all_terms as $term ) : ?>
						<option value="<?php echo esc_attr( $term->term_id ); ?>"
							<?php echo in_array( (int) $term->term_id, array_map( 'intval', $defaults ), true ) ? 'selected' : ''; ?>>
							<?php echo esc_html( $term->name ); ?>
						</option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Hold Ctrl/Cmd to select multiple.', 've-events' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<?php endforeach; ?>
			</table>

			<?php submit_button( $is_new ? __( 'Add Feed', 've-events' ) : __( 'Save Feed', 've-events' ) ); ?>
		</form>
		<?php self::inline_js(); ?>
		<?php
	}

	// -------------------------------------------------------------------------
	// Log page
	// -------------------------------------------------------------------------

	/**
	 * Renders the run log for a single feed.
	 *
	 * @param int $feed_id Feed post ID.
	 */
	private static function render_log_page( int $feed_id ): void {
		$cfg  = Feed::get_config( $feed_id );
		$logs = Logger::get_for_feed( $feed_id, 50 );

		$back_url = self::page_url();
		?>
		<h1 class="wp-heading-inline">
			<?php /* translators: %s: feed name. */ printf( esc_html__( 'Import Log: %s', 've-events' ), esc_html( $cfg['title'] ) ); ?>
		</h1>
		<a href="<?php echo esc_url( $back_url ); ?>" class="page-title-action">&larr; <?php esc_html_e( 'All Feeds', 've-events' ); ?></a>
		<button type="button" class="page-title-action" id="vev-clear-log-btn"
			data-feed-id="<?php echo esc_attr( $feed_id ); ?>"
			data-nonce="<?php echo esc_attr( wp_create_nonce( 'vev_clear_log_' . $feed_id ) ); ?>">
			<?php esc_html_e( 'Clear Log', 've-events' ); ?>
		</button>

		<?php if ( empty( $logs ) ) : ?>
		<p style="margin-top:16px"><?php esc_html_e( 'No log entries yet.', 've-events' ); ?></p>
		<?php else : ?>
		<table class="wp-list-table widefat fixed striped" style="margin-top:16px">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Time (UTC)', 've-events' ); ?></th>
					<th><?php esc_html_e( 'Status', 've-events' ); ?></th>
					<th><?php esc_html_e( 'Created', 've-events' ); ?></th>
					<th><?php esc_html_e( 'Updated', 've-events' ); ?></th>
					<th><?php esc_html_e( 'Deleted', 've-events' ); ?></th>
					<th><?php esc_html_e( 'Skipped', 've-events' ); ?></th>
					<th><?php esc_html_e( 'Duration', 've-events' ); ?></th>
					<th><?php esc_html_e( 'Errors', 've-events' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php
			foreach ( $logs as $log ) :
				$status_class = match ( $log['status'] ) {
					'success' => 'dashicons-yes-alt vev-status-ok',
					'error'   => 'dashicons-dismiss vev-status-err',
					'partial' => 'dashicons-warning vev-status-warn',
					default   => 'dashicons-minus',
				};
				?>
			<tr>
				<td><?php echo esc_html( $log['run_time'] ); ?></td>
				<td><span class="dashicons <?php echo esc_attr( $status_class ); ?>"></span> <?php echo esc_html( ucfirst( $log['status'] ) ); ?></td>
				<td><?php echo (int) $log['created']; ?></td>
				<td><?php echo (int) $log['updated']; ?></td>
				<td><?php echo (int) $log['deleted']; ?></td>
				<td><?php echo (int) $log['skipped']; ?></td>
				<td><?php echo esc_html( number_format( $log['duration_ms'] / 1000, 2 ) ); ?>s</td>
				<td>
					<?php if ( ! empty( $log['errors'] ) ) : ?>
					<details>
						<summary><?php /* translators: %d: number of errors. */ printf( esc_html__( '%d error(s)', 've-events' ), count( $log['errors'] ) ); ?></summary>
						<ul style="margin:4px 0 0 16px">
						<?php foreach ( $log['errors'] as $err ) : ?>
							<li><?php echo esc_html( $err ); ?></li>
						<?php endforeach; ?>
						</ul>
					</details>
					<?php else : ?>
					—
					<?php endif; ?>
				</td>
			</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php endif; ?>

		<?php self::inline_js(); ?>
		<?php
	}

	// -------------------------------------------------------------------------
	// Form handlers
	// -------------------------------------------------------------------------

	/**
	 * Handles the feed create/update form submission.
	 */
	public static function handle_save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 've-events' ) );
		}
		check_admin_referer( self::NONCE, '_vev_nonce' );

		$feed_id = (int) ( $_POST['feed_id'] ?? 0 );

		// Sanitize field_map.
		$raw_map   = $_POST['field_map'] ?? array();
		$field_map = array();
		foreach ( FieldMapper::get_sources() as $source_key => $_ ) {
			$field_map[ $source_key ] = array(
				'enabled'      => ! empty( $raw_map[ $source_key ]['enabled'] ),
				'target'       => sanitize_key( $raw_map[ $source_key ]['target'] ?? '' ),
				'create_terms' => ! empty( $raw_map[ $source_key ]['create_terms'] ),
			);
		}

		// Sanitize tax_defaults.
		$raw_tax      = $_POST['tax_defaults'] ?? array();
		$tax_defaults = array();
		foreach ( $raw_tax as $taxonomy => $term_ids ) {
			$tax_defaults[ sanitize_key( $taxonomy ) ] = array_map( 'absint', (array) $term_ids );
		}

		$raw_url   = trim( wp_unslash( $_POST['url'] ?? '' ) );
		$valid_url = filter_var( $raw_url, FILTER_VALIDATE_URL ) ? esc_url_raw( $raw_url ) : '';

		$type     = in_array( $_POST['type'] ?? '', array( 'ics_url', 'churchdesk' ), true ) ? sanitize_key( $_POST['type'] ) : 'ics_url';
		$endpoint = in_array( $_POST['cd_endpoint'] ?? '', array( 'pull_api', 'calendar_view' ), true ) ? sanitize_key( $_POST['cd_endpoint'] ) : 'pull_api';

		// Parse comma-separated ChurchDesk category IDs.
		$cd_categories = array();
		$raw_cats      = sanitize_text_field( wp_unslash( $_POST['cd_categories'] ?? '' ) );
		if ( '' !== $raw_cats ) {
			foreach ( explode( ',', $raw_cats ) as $cat_id ) {
				$cat_id = absint( trim( $cat_id ) );
				if ( $cat_id ) {
					$cd_categories[] = $cat_id;
				}
			}
		}

		$data = array(
			'title'            => sanitize_text_field( $_POST['title'] ?? '' ),
			'type'             => $type,
			'url'              => $valid_url,
			'schedule'         => sanitize_key( $_POST['schedule'] ?? 'daily' ),
			'update_mode'      => sanitize_key( $_POST['update_mode'] ?? 'if_newer' ),
			'post_status'      => in_array( $_POST['post_status'] ?? '', array( 'publish', 'draft' ), true )
								? $_POST['post_status'] : 'publish',
			'http_timeout'     => min( 120, max( 5, (int) ( $_POST['http_timeout'] ?? 30 ) ) ),
			'delete_removed'   => ! empty( $_POST['delete_removed'] ),
			'merge_cross_feed' => ! empty( $_POST['merge_cross_feed'] ),
			'active'           => ! empty( $_POST['active'] ),
			'field_map'        => $field_map,
			'tax_defaults'     => $tax_defaults,
			'cd_endpoint'      => $endpoint,
			'cd_org_id'        => sanitize_text_field( wp_unslash( $_POST['cd_org_id'] ?? '' ) ),
			'cd_token'         => sanitize_text_field( wp_unslash( $_POST['cd_token'] ?? '' ) ),
			'cd_categories'    => $cd_categories,
			'cd_image_format'  => sanitize_text_field( wp_unslash( $_POST['cd_image_format'] ?? 'span7_16-9' ) ),
			'cd_import_image'  => ! empty( $_POST['cd_import_image'] ),
		);

		if ( $feed_id ) {
			Feed::update( $feed_id, $data );
			Manager::schedule_feed( $feed_id );
			$notice = 'saved';
		} else {
			$new_id = Feed::create( $data );
			if ( ! is_wp_error( $new_id ) ) {
				Manager::schedule_feed( $new_id );
			}
			$notice = 'saved';
		}

		wp_safe_redirect( self::page_url( array( 'notice' => $notice ) ) );
		exit;
	}

	/**
	 * Handles deleting a feed.
	 */
	public static function handle_delete(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 've-events' ) );
		}
		check_admin_referer( self::NONCE );

		$feed_id = (int) ( $_GET['feed_id'] ?? 0 );
		if ( $feed_id ) {
			Manager::unschedule_feed( $feed_id );
			Feed::delete( $feed_id );
		}

		wp_safe_redirect( self::page_url( array( 'notice' => 'deleted' ) ) );
		exit;
	}

	/**
	 * Handles toggling a feed's active status.
	 */
	public static function handle_toggle(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 've-events' ) );
		}
		check_admin_referer( self::NONCE );

		$feed_id = (int) ( $_GET['feed_id'] ?? 0 );
		if ( $feed_id ) {
			$cfg = Feed::get_config( $feed_id );
			Feed::set_active( $feed_id, ! $cfg['active'] );
			if ( ! $cfg['active'] ) {
				Manager::schedule_feed( $feed_id );
			} else {
				Manager::unschedule_feed( $feed_id );
			}
		}

		wp_safe_redirect( self::page_url( array( 'notice' => 'toggled' ) ) );
		exit;
	}

	// -------------------------------------------------------------------------
	// AJAX
	// -------------------------------------------------------------------------

	/**
	 * AJAX: runs a feed import immediately and returns its result.
	 */
	public static function ajax_run_now(): void {
		$feed_id = (int) ( $_POST['feed_id'] ?? 0 );
		check_ajax_referer( 'vev_run_now_' . $feed_id, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions.' );
		}

		$result = Manager::run_now( $feed_id );
		wp_send_json_success( $result );
	}

	/**
	 * AJAX: tests a feed's ICS URL and returns an event preview.
	 */
	public static function ajax_test(): void {
		$feed_id = (int) ( $_POST['feed_id'] ?? 0 );
		check_ajax_referer( 'vev_test_' . $feed_id, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions.' );
		}

		$cfg = Feed::get_config( $feed_id );

		if ( 'churchdesk' === ( $cfg['type'] ?? 'ics_url' ) ) {
			if ( empty( $cfg['cd_org_id'] ) ) {
				wp_send_json_error( 'No ChurchDesk organization id configured.' );
			}

			$result = \VEV\Import\ChurchDesk\SourceFactory::make( $cfg )->test();
			if ( empty( $result['ok'] ) ) {
				wp_send_json_error( $result['error'] ? $result['error'] : 'Connection failed.' );
			}

			wp_send_json_success(
				array(
					'count'   => $result['count'],
					'preview' => $result['sample'],
				)
			);
		}

		if ( empty( $cfg['url'] ) ) {
			wp_send_json_error( 'No URL configured.' );
		}

		try {
			$ical = new \VEV_Import\ICal();
			$ical->initUrl( $cfg['url'] );
		} catch ( \Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}

		if ( ! $ical->hasEvents() ) {
			wp_send_json_success(
				array(
					'count'   => 0,
					'preview' => array(),
				)
			);
		}

		$events  = $ical->events();
		$preview = array();
		foreach ( array_slice( $events, 0, 5 ) as $event ) {
			$preview[] = array(
				'summary' => $event->summary ?? '(no title)',
				'dtstart' => $event->dtstart ?? '',
				'uid'     => $event->uid ?? '',
			);
		}

		wp_send_json_success(
			array(
				'count'   => count( $events ),
				'preview' => $preview,
			)
		);
	}

	/**
	 * AJAX: clears all log entries for a feed.
	 */
	public static function ajax_clear_log(): void {
		$feed_id = (int) ( $_POST['feed_id'] ?? 0 );
		check_ajax_referer( 'vev_clear_log_' . $feed_id, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions.' );
		}

		Logger::clear_for_feed( $feed_id );
		wp_send_json_success();
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Builds an admin URL for the import page with optional extra query args.
	 *
	 * @param  array $extra Extra query args to merge.
	 * @return string
	 */
	private static function page_url( array $extra = array() ): string {
		$args = array_merge(
			array(
				'post_type' => Constants::POST_TYPE,
				'page'      => self::PAGE_SLUG,
			),
			$extra
		);
		return add_query_arg( $args, admin_url( 'edit.php' ) );
	}

	/**
	 * Prints a success notice based on the `notice` query arg.
	 */
	private static function maybe_show_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice key matched against a whitelist below.
		$notice = sanitize_key( $_GET['notice'] ?? '' );
		if ( ! $notice ) {
			return;
		}
		$messages = array(
			'saved'   => __( 'Feed saved.', 've-events' ),
			'deleted' => __( 'Feed deleted.', 've-events' ),
			'toggled' => __( 'Feed status updated.', 've-events' ),
		);
		$msg      = $messages[ $notice ] ?? '';
		if ( $msg ) {
			printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $msg ) );
		}
	}

	/**
	 * Outputs the inline CSS and JavaScript for the import admin screens.
	 */
	private static function inline_js(): void {
		?>
		<style>
		.vev-status-ok   { color: #00a32a; }
		.vev-status-err  { color: #d63638; }
		.vev-status-warn { color: #dba617; }
		.vev-url-cell    { word-break: break-all; font-size: 11px; color: #666; }
		.vev-url-cell:hover { color: inherit; }
		.vev-test-ok     { color: #00a32a; }
		.vev-test-err    { color: #d63638; }
		.column-name     { width: 28%; }
		.vev-panel       { display: none; }
		</style>
		<script>
		(function($){
			// Toggle source-type panels (iCal vs ChurchDesk).
			function vevToggleSource(){
				var type = $('#vev-source-type').val();
				$('.vev-panel').hide();
				$('.vev-panel-' + type).show();
			}
			// Hide the partner-token row for the calendar-view endpoint and pick a
			// sensible default image format per endpoint (without clobbering a custom one).
			function vevToggleEndpoint(){
				var ep = $('#vev-cd-endpoint').val();
				$('.vev-cd-token-row').toggle(ep === 'pull_api');
				var fmt = $('#vev-cd-image-format');
				if (fmt.length && (fmt.val() === '' || fmt.val() === 'span6_16-9' || fmt.val() === 'span7_16-9')) {
					fmt.val(ep === 'calendar_view' ? 'span6_16-9' : 'span7_16-9');
				}
			}
			$('#vev-source-type').on('change', vevToggleSource);
			$('#vev-cd-endpoint').on('change', vevToggleEndpoint);
			vevToggleSource();
			vevToggleEndpoint();

			// Run now
			$(document).on('click', '.vev-run-now', function(){
				var btn     = $(this);
				var feedId  = btn.data('feed-id');
				var nonce   = btn.data('nonce');
				btn.prop('disabled', true).text('<?php echo esc_js( __( 'Running…', 've-events' ) ); ?>');
				$.post(ajaxurl, { action: 'vev_import_run_now', feed_id: feedId, nonce: nonce }, function(res){
					if(res.success){
						var c = res.data.counts;
						btn.closest('.inline').find('.vev-run-result').html(
							'<span class="vev-status-ok dashicons dashicons-yes-alt"></span> '
							+ c.created+' / '+c.updated+' / '+c.deleted
						);
						btn.prop('disabled', false).text('<?php echo esc_js( __( 'Run now', 've-events' ) ); ?>');
					} else {
						btn.text('<?php echo esc_js( __( 'Error', 've-events' ) ); ?>').prop('disabled', false);
					}
				});
			});

			// Test connection
			$('#vev-test-btn').on('click', function(){
				var btn    = $(this);
				var feedId = btn.data('feed-id');
				var nonce  = btn.data('nonce');
				var result = $('#vev-test-result');
				btn.prop('disabled', true);
				result.text('<?php echo esc_js( __( 'Connecting…', 've-events' ) ); ?>');
				$.post(ajaxurl, { action: 'vev_import_test', feed_id: feedId, nonce: nonce }, function(res){
					btn.prop('disabled', false);
					if(res.success){
						var msg = '<?php /* translators: %d: number of events found. */ echo esc_js( __( 'OK — found %d event(s).', 've-events' ) ); ?>'.replace('%d', res.data.count);
						result.html('<span class="dashicons dashicons-yes-alt vev-test-ok"></span> '+msg);
						if(res.data.preview.length){
							var list = '<ul style="margin:6px 0 0 16px">';
							$.each(res.data.preview, function(i, ev){
								list += '<li>'+$('<span>').text(ev.dtstart+' — '+ev.summary).html()+'</li>';
							});
							list += '</ul>';
							result.append(list);
						}
					} else {
						result.html('<span class="dashicons dashicons-dismiss vev-test-err"></span> '+$('<span>').text(res.data||'Error').html());
					}
				});
			});

			// Clear log
			$('#vev-clear-log-btn').on('click', function(){
				if(!confirm('<?php echo esc_js( __( 'Clear all log entries for this feed?', 've-events' ) ); ?>')) return;
				var btn    = $(this);
				var feedId = btn.data('feed-id');
				var nonce  = btn.data('nonce');
				$.post(ajaxurl, { action: 'vev_import_clear_log', feed_id: feedId, nonce: nonce }, function(res){
					if(res.success) location.reload();
				});
			});
		})(jQuery);
		</script>
		<?php
	}
}
