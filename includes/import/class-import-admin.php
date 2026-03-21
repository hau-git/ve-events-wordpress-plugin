<?php
/**
 * Import Admin UI — feed list, feed edit form, run log.
 *
 * @package VE_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VEV_Import_Admin {

	const PAGE_SLUG = 'vev-import';
	const NONCE     = 'vev_import_nonce';

	public static function init(): void {
		add_action( 'admin_menu',            array( __CLASS__, 'add_menu'      ) );
		add_action( 'admin_post_vev_import_save',      array( __CLASS__, 'handle_save'    ) );
		add_action( 'admin_post_vev_import_delete',    array( __CLASS__, 'handle_delete'  ) );
		add_action( 'admin_post_vev_import_toggle',    array( __CLASS__, 'handle_toggle'  ) );
		add_action( 'wp_ajax_vev_import_run_now',      array( __CLASS__, 'ajax_run_now'   ) );
		add_action( 'wp_ajax_vev_import_test',         array( __CLASS__, 'ajax_test'      ) );
		add_action( 'wp_ajax_vev_import_clear_log',    array( __CLASS__, 'ajax_clear_log' ) );
		add_action( 'admin_enqueue_scripts',           array( __CLASS__, 'enqueue_assets' ) );
	}

	// -------------------------------------------------------------------------
	// Menu
	// -------------------------------------------------------------------------

	public static function add_menu(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		add_submenu_page(
			'edit.php?post_type=' . VEV_Events::POST_TYPE,
			__( 'Calendar Import', 've-events' ),
			__( 'Import', 've-events' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	public static function enqueue_assets( string $hook ): void {
		if ( strpos( $hook, self::PAGE_SLUG ) === false ) {
			return;
		}
		wp_enqueue_style( 'dashicons' );
	}

	// -------------------------------------------------------------------------
	// Router
	// -------------------------------------------------------------------------

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$action  = sanitize_key( $_GET['action'] ?? 'list' );
		$feed_id = absint( $_GET['feed_id'] ?? 0 );

		echo '<div class="wrap">';

		if ( $action === 'edit' ) {
			self::render_edit_page( $feed_id );
		} elseif ( $action === 'log' ) {
			self::render_log_page( $feed_id );
		} else {
			self::render_list_page();
		}

		echo '</div>';
	}

	// -------------------------------------------------------------------------
	// List page
	// -------------------------------------------------------------------------

	private static function render_list_page(): void {
		$feeds = VEV_Import_Feed::get_all();

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
					<th><?php esc_html_e( 'Name',        've-events' ); ?></th>
					<th><?php esc_html_e( 'URL',         've-events' ); ?></th>
					<th><?php esc_html_e( 'Schedule',    've-events' ); ?></th>
					<th><?php esc_html_e( 'Status',      've-events' ); ?></th>
					<th><?php esc_html_e( 'Last Run',    've-events' ); ?></th>
					<th><?php esc_html_e( 'Next Run',    've-events' ); ?></th>
					</tr>
			</thead>
			<tbody>
			<?php foreach ( $feeds as $feed ) :
				$cfg      = VEV_Import_Feed::get_config( $feed->ID );
				$active   = $cfg['active'];
				$schedules = VEV_Import_Feed::get_schedules();
				$schedule_label = $schedules[ $cfg['schedule'] ] ?? $cfg['schedule'];
				$last_run = $cfg['last_run'] ? wp_date( get_option( 'date_format' ) . ' H:i', $cfg['last_run'] ) : __( 'Never', 've-events' );
				$next_ts  = wp_next_scheduled( VEV_Import_Manager::CRON_HOOK, array( $feed->ID ) );
				$next_run = $next_ts ? wp_date( get_option( 'date_format' ) . ' H:i', $next_ts ) : '—';

				$edit_url   = self::page_url( array( 'action' => 'edit',   'feed_id' => $feed->ID ) );
				$log_url    = self::page_url( array( 'action' => 'log',    'feed_id' => $feed->ID ) );
				$delete_url = wp_nonce_url(
					admin_url( 'admin-post.php?action=vev_import_delete&feed_id=' . $feed->ID ),
					self::NONCE
				);
				$toggle_url = wp_nonce_url(
					admin_url( 'admin-post.php?action=vev_import_toggle&feed_id=' . $feed->ID ),
					self::NONCE
				);

				$last_status  = $cfg['last_status'];
				$counts       = $cfg['last_counts'] ?? array();
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
				<td class="vev-url-cell"><?php echo esc_html( $cfg['url'] ); ?></td>
				<td><?php echo esc_html( $schedule_label ); ?></td>
				<td>
					<?php
					if ( $last_status === 'success' ) {
						echo '<span class="dashicons dashicons-yes-alt vev-status-ok"></span> ';
						esc_html_e( 'OK', 've-events' );
					} elseif ( $last_status === 'error' ) {
						echo '<span class="dashicons dashicons-dismiss vev-status-err"></span> ';
						esc_html_e( 'Error', 've-events' );
					} elseif ( $last_status === 'partial' ) {
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

	private static function render_edit_page( int $feed_id ): void {
		$is_new = $feed_id === 0;
		$cfg    = $is_new
			? array(
				'title'          => '',
				'url'            => '',
				'schedule'       => 'daily',
				'field_map'      => VEV_Import_Feed::DEFAULT_FIELD_MAP,
				'tax_defaults'   => array(),
				'update_mode'    => 'if_newer',
				'delete_removed' => false,
				'post_status'    => 'publish',
				'http_timeout'   => 30,
				'active'         => true,
			)
			: VEV_Import_Feed::get_config( $feed_id );

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
					<th><?php esc_html_e( 'ICS URL', 've-events' ); ?></th>
					<td>
						<input type="url" name="url" value="<?php echo esc_attr( $cfg['url'] ); ?>" class="large-text" required>
						<p class="description"><?php esc_html_e( 'The .ics subscription URL (e.g. from Outlook, Google Calendar, etc.)', 've-events' ); ?></p>
						<?php if ( ! $is_new ) : ?>
						<button type="button" class="button" id="vev-test-btn"
							data-feed-id="<?php echo esc_attr( $feed_id ); ?>"
							data-nonce="<?php echo esc_attr( wp_create_nonce( 'vev_test_' . $feed_id ) ); ?>">
							<?php esc_html_e( 'Test Connection', 've-events' ); ?>
						</button>
						<span id="vev-test-result" style="margin-left:10px"></span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Schedule', 've-events' ); ?></th>
					<td>
						<select name="schedule">
						<?php foreach ( VEV_Import_Feed::get_schedules() as $key => $label ) : ?>
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
						<?php foreach ( VEV_Import_Feed::get_update_modes() as $key => $label ) : ?>
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
							<option value="draft"   <?php selected( $cfg['post_status'], 'draft' );   ?>><?php esc_html_e( 'Draft', 've-events' ); ?></option>
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
			</table>

			<!-- Field Mapping -->
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
				$sources = VEV_Field_Mapper::get_sources();
				$targets = VEV_Field_Mapper::get_targets();
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
			<?php foreach ( $tax_configs as $taxonomy => $label ) :
				$defaults = $cfg['tax_defaults'][ $taxonomy ] ?? array();
				$all_terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
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

	private static function render_log_page( int $feed_id ): void {
		$cfg  = VEV_Import_Feed::get_config( $feed_id );
		$logs = VEV_Import_Logger::get_for_feed( $feed_id, 50 );

		$back_url = self::page_url();
		?>
		<h1 class="wp-heading-inline">
			<?php printf( esc_html__( 'Import Log: %s', 've-events' ), esc_html( $cfg['title'] ) ); ?>
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
					<th><?php esc_html_e( 'Time (UTC)',  've-events' ); ?></th>
					<th><?php esc_html_e( 'Status',      've-events' ); ?></th>
					<th><?php esc_html_e( 'Created',     've-events' ); ?></th>
					<th><?php esc_html_e( 'Updated',     've-events' ); ?></th>
					<th><?php esc_html_e( 'Deleted',     've-events' ); ?></th>
					<th><?php esc_html_e( 'Skipped',     've-events' ); ?></th>
					<th><?php esc_html_e( 'Duration',    've-events' ); ?></th>
					<th><?php esc_html_e( 'Errors',      've-events' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $logs as $log ) :
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
						<summary><?php printf( esc_html__( '%d error(s)', 've-events' ), count( $log['errors'] ) ); ?></summary>
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

	public static function handle_save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 've-events' ) );
		}
		check_admin_referer( self::NONCE, '_vev_nonce' );

		$feed_id = (int) ( $_POST['feed_id'] ?? 0 );

		// Sanitize field_map
		$raw_map  = $_POST['field_map'] ?? array();
		$field_map = array();
		foreach ( VEV_Field_Mapper::get_sources() as $source_key => $_ ) {
			$field_map[ $source_key ] = array(
				'enabled'      => ! empty( $raw_map[ $source_key ]['enabled'] ),
				'target'       => sanitize_key( $raw_map[ $source_key ]['target'] ?? '' ),
				'create_terms' => ! empty( $raw_map[ $source_key ]['create_terms'] ),
			);
		}

		// Sanitize tax_defaults
		$raw_tax     = $_POST['tax_defaults'] ?? array();
		$tax_defaults = array();
		foreach ( $raw_tax as $taxonomy => $term_ids ) {
			$tax_defaults[ sanitize_key( $taxonomy ) ] = array_map( 'absint', (array) $term_ids );
		}

		$raw_url     = trim( wp_unslash( $_POST['url'] ?? '' ) );
		$valid_url   = filter_var( $raw_url, FILTER_VALIDATE_URL ) ? esc_url_raw( $raw_url ) : '';

		$data = array(
			'title'          => sanitize_text_field( $_POST['title']          ?? '' ),
			'url'            => $valid_url,
			'schedule'       => sanitize_key( $_POST['schedule']              ?? 'daily' ),
			'update_mode'    => sanitize_key( $_POST['update_mode']           ?? 'if_newer' ),
			'post_status'    => in_array( $_POST['post_status'] ?? '', array( 'publish', 'draft' ), true )
								? $_POST['post_status'] : 'publish',
			'http_timeout'   => min( 120, max( 5, (int) ( $_POST['http_timeout'] ?? 30 ) ) ),
			'delete_removed' => ! empty( $_POST['delete_removed'] ),
			'active'         => ! empty( $_POST['active'] ),
			'field_map'      => $field_map,
			'tax_defaults'   => $tax_defaults,
		);

		if ( $feed_id ) {
			VEV_Import_Feed::update( $feed_id, $data );
			VEV_Import_Manager::schedule_feed( $feed_id );
			$notice = 'saved';
		} else {
			$new_id = VEV_Import_Feed::create( $data );
			if ( ! is_wp_error( $new_id ) ) {
				VEV_Import_Manager::schedule_feed( $new_id );
			}
			$notice = 'saved';
		}

		wp_safe_redirect( self::page_url( array( 'notice' => $notice ) ) );
		exit;
	}

	public static function handle_delete(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 've-events' ) );
		}
		check_admin_referer( self::NONCE );

		$feed_id = (int) ( $_GET['feed_id'] ?? 0 );
		if ( $feed_id ) {
			VEV_Import_Manager::unschedule_feed( $feed_id );
			VEV_Import_Feed::delete( $feed_id );
		}

		wp_safe_redirect( self::page_url( array( 'notice' => 'deleted' ) ) );
		exit;
	}

	public static function handle_toggle(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 've-events' ) );
		}
		check_admin_referer( self::NONCE );

		$feed_id = (int) ( $_GET['feed_id'] ?? 0 );
		if ( $feed_id ) {
			$cfg = VEV_Import_Feed::get_config( $feed_id );
			VEV_Import_Feed::set_active( $feed_id, ! $cfg['active'] );
			if ( ! $cfg['active'] ) {
				VEV_Import_Manager::schedule_feed( $feed_id );
			} else {
				VEV_Import_Manager::unschedule_feed( $feed_id );
			}
		}

		wp_safe_redirect( self::page_url( array( 'notice' => 'toggled' ) ) );
		exit;
	}

	// -------------------------------------------------------------------------
	// AJAX
	// -------------------------------------------------------------------------

	public static function ajax_run_now(): void {
		$feed_id = (int) ( $_POST['feed_id'] ?? 0 );
		check_ajax_referer( 'vev_run_now_' . $feed_id, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions.' );
		}

		$result = VEV_Import_Manager::run_now( $feed_id );
		wp_send_json_success( $result );
	}

	public static function ajax_test(): void {
		$feed_id = (int) ( $_POST['feed_id'] ?? 0 );
		check_ajax_referer( 'vev_test_' . $feed_id, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions.' );
		}

		$cfg = VEV_Import_Feed::get_config( $feed_id );
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
			wp_send_json_success( array( 'count' => 0, 'preview' => array() ) );
		}

		$events  = $ical->events();
		$preview = array();
		foreach ( array_slice( $events, 0, 5 ) as $event ) {
			$preview[] = array(
				'summary' => $event->summary ?? '(no title)',
				'dtstart' => $event->dtstart ?? '',
				'uid'     => $event->uid     ?? '',
			);
		}

		wp_send_json_success( array( 'count' => count( $events ), 'preview' => $preview ) );
	}

	public static function ajax_clear_log(): void {
		$feed_id = (int) ( $_POST['feed_id'] ?? 0 );
		check_ajax_referer( 'vev_clear_log_' . $feed_id, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions.' );
		}

		VEV_Import_Logger::clear_for_feed( $feed_id );
		wp_send_json_success();
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private static function page_url( array $extra = array() ): string {
		$args = array_merge(
			array(
				'post_type' => VEV_Events::POST_TYPE,
				'page'      => self::PAGE_SLUG,
			),
			$extra
		);
		return add_query_arg( $args, admin_url( 'edit.php' ) );
	}

	private static function maybe_show_notice(): void {
		$notice = $_GET['notice'] ?? '';
		if ( ! $notice ) {
			return;
		}
		$messages = array(
			'saved'   => __( 'Feed saved.', 've-events' ),
			'deleted' => __( 'Feed deleted.', 've-events' ),
			'toggled' => __( 'Feed status updated.', 've-events' ),
		);
		$msg = $messages[ $notice ] ?? '';
		if ( $msg ) {
			printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $msg ) );
		}
	}

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
		</style>
		<script>
		(function($){
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
						var msg = '<?php echo esc_js( __( 'OK — found %d event(s).', 've-events' ) ); ?>'.replace('%d', res.data.count);
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
