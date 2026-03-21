<?php
if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

final class VEV_Admin {

        public static function init(): void {
                // Event form rendered directly below the title (works with Gutenberg + Classic)
                add_action( 'edit_form_after_title', array( __CLASS__, 'render_event_form' ) );
                add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
                add_action( 'save_post', array( __CLASS__, 'save_meta' ), 10, 2 );
                add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );
                add_action( 'admin_head', array( __CLASS__, 'output_list_styles' ) );

                add_filter( 'manage_' . VEV_Events::POST_TYPE . '_posts_columns', array( __CLASS__, 'admin_columns' ) );
                add_action( 'manage_' . VEV_Events::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'admin_column_content' ), 10, 2 );
                add_filter( 'manage_edit-' . VEV_Events::POST_TYPE . '_sortable_columns', array( __CLASS__, 'admin_sortable_columns' ) );

                add_filter( 'display_post_states', array( __CLASS__, 'display_post_states' ), 10, 2 );

                add_filter( 'views_edit-' . VEV_Events::POST_TYPE, array( __CLASS__, 'admin_views' ) );

                // List filter bar (month / category / location / topic dropdowns)
                add_action( 'restrict_manage_posts', array( __CLASS__, 'render_list_filter_bar' ) );
                add_action( 'pre_get_posts',         array( __CLASS__, 'apply_admin_list_filters' ) );

                add_action( 'admin_menu', array( __CLASS__, 'add_settings_page' ) );
                add_action( 'admin_menu', array( __CLASS__, 'register_calendar_page' ) );
                add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );

                add_filter( 'use_block_editor_for_post_type', array( __CLASS__, 'maybe_disable_gutenberg' ), 10, 2 );

                add_action( 'wp_ajax_vev_resync_computed_meta', array( __CLASS__, 'ajax_resync_computed_meta' ) );

                self::init_taxonomy_forms();
                self::init_series_suggestions();
        }

        public static function add_meta_boxes(): void {
                add_meta_box(
                        'vev_details',
                        __( 'Details', VEV_Events::TEXTDOMAIN ),
                        array( __CLASS__, 'render_details_metabox' ),
                        VEV_Events::POST_TYPE,
                        'normal',
                        'low'
                );
                add_meta_box(
                        'vev_event_status',
                        __( 'Event Status', VEV_Events::TEXTDOMAIN ),
                        array( __CLASS__, 'render_status_metabox' ),
                        VEV_Events::POST_TYPE,
                        'side',
                        'high'
                );
        }

        private static function init_series_suggestions(): void {
                add_action( 'save_post_' . VEV_Events::POST_TYPE, array( __CLASS__, 'detect_series_suggestion' ), 20 );
                add_action( 'admin_notices', array( __CLASS__, 'render_series_suggestion_notice' ) );
                add_action( 'wp_ajax_vev_series_suggestion', array( __CLASS__, 'handle_series_suggestion_ajax' ) );
        }

        private static function init_taxonomy_forms(): void {
                // Location: address + maps URL fields
                add_action( VEV_Events::TAX_LOCATION . '_add_form_fields',  array( __CLASS__, 'render_location_add_fields' ) );
                add_action( VEV_Events::TAX_LOCATION . '_edit_form_fields', array( __CLASS__, 'render_location_edit_fields' ) );
                add_action( 'created_' . VEV_Events::TAX_LOCATION, array( __CLASS__, 'save_location_term_meta' ) );
                add_action( 'edited_' . VEV_Events::TAX_LOCATION,  array( __CLASS__, 'save_location_term_meta' ) );

                // Category: color field
                add_action( VEV_Events::TAX_CATEGORY . '_add_form_fields',  array( __CLASS__, 'render_category_add_fields' ) );
                add_action( VEV_Events::TAX_CATEGORY . '_edit_form_fields', array( __CLASS__, 'render_category_edit_fields' ) );
                add_action( 'created_' . VEV_Events::TAX_CATEGORY, array( __CLASS__, 'save_category_term_meta' ) );
                add_action( 'edited_' . VEV_Events::TAX_CATEGORY,  array( __CLASS__, 'save_category_term_meta' ) );
        }

        public static function maybe_disable_gutenberg( bool $use_block_editor, string $post_type ): bool {
                if ( VEV_Events::POST_TYPE !== $post_type ) {
                        return $use_block_editor;
                }
                $settings = VEV_Events::get_settings();
                if ( ! empty( $settings['disable_gutenberg'] ) ) {
                        return false;
                }
                return $use_block_editor;
        }

        public static function add_settings_page(): void {
                if ( ! current_user_can( 'manage_options' ) ) {
                        return;
                }
                add_submenu_page(
                        'edit.php?post_type=' . VEV_Events::POST_TYPE,
                        __( 'VE Events Settings', VEV_Events::TEXTDOMAIN ),
                        __( 'Settings', VEV_Events::TEXTDOMAIN ),
                        'manage_options',
                        'vev-settings',
                        array( __CLASS__, 'render_settings_page' )
                );
        }

        public static function register_settings(): void {
                register_setting( 'vev_settings_group', VEV_Events::OPTION_SETTINGS, array(
                        'type' => 'array',
                        'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
                ) );

                add_action( 'update_option_' . VEV_Events::OPTION_SETTINGS, array( __CLASS__, 'maybe_flush_rewrite_rules' ), 10, 2 );
                add_action( 'update_option_' . VEV_Events::OPTION_SETTINGS, static function() { VEV_Events::flush_settings_cache(); }, 5 );
        }

        public static function maybe_flush_rewrite_rules( $old_value, $new_value ): void {
                $old_single  = $old_value['slug_single'] ?? 'event';
                $old_archive = $old_value['slug_archive'] ?? 'events';
                $new_single  = $new_value['slug_single'] ?? 'event';
                $new_archive = $new_value['slug_archive'] ?? 'events';

                if ( $old_single !== $new_single || $old_archive !== $new_archive ) {
                        set_transient( 'vev_flush_rewrite_rules', 1, 60 );
                }
        }

        public static function sanitize_settings( $input ): array {
                $sanitized = array();
                $sanitized['disable_gutenberg']     = ! empty( $input['disable_gutenberg'] );
                $sanitized['hide_end_same_day']     = ! empty( $input['hide_end_same_day'] );
                $sanitized['grace_period']          = absint( $input['grace_period'] ?? 1 );
                $sanitized['hide_archived_search']  = ! empty( $input['hide_archived_search'] );
                $sanitized['include_series_schema'] = ! empty( $input['include_series_schema'] );

                $slug_single  = isset( $input['slug_single'] ) ? sanitize_title( $input['slug_single'] ) : '';
                $slug_archive = isset( $input['slug_archive'] ) ? sanitize_title( $input['slug_archive'] ) : '';

                $reserved_slugs = array( 'post', 'page', 'attachment', 'revision', 'nav_menu_item', 'action', 'author', 'order', 'theme', 'category', 'tag', 'admin', 'wp-admin', 'wp-content', 'wp-includes' );

                if ( '' === $slug_single || in_array( $slug_single, $reserved_slugs, true ) || is_numeric( $slug_single ) ) {
                        $slug_single = 'event';
                }
                if ( '' === $slug_archive || in_array( $slug_archive, $reserved_slugs, true ) || is_numeric( $slug_archive ) ) {
                        $slug_archive = 'events';
                }

                if ( $slug_single === $slug_archive ) {
                        $slug_archive = $slug_single . 's';
                        if ( '' === $slug_archive || 's' === $slug_archive ) {
                                $slug_archive = 'events';
                        }
                }

                $sanitized['slug_single']             = $slug_single;
                $sanitized['slug_archive']            = $slug_archive;
                $sanitized['series_suggestions']      = ! empty( $input['series_suggestions'] );
                $sanitized['output_category_colors']  = ! empty( $input['output_category_colors'] );

                $og_tags_raw = $input['og_tags'] ?? 'auto';
                $sanitized['og_tags'] = in_array( $og_tags_raw, array( 'auto', 'always', 'disabled' ), true ) ? $og_tags_raw : 'auto';

                return $sanitized;
        }

        public static function render_settings_page(): void {
                if ( ! current_user_can( 'manage_options' ) ) {
                        return;
                }
                $settings = VEV_Events::get_settings();
                $opt      = VEV_Events::OPTION_SETTINGS;
                $import_url = add_query_arg( array( 'post_type' => VEV_Events::POST_TYPE, 'page' => 'vev-import' ), admin_url( 'edit.php' ) );
                ?>
                <div class="wrap">
                        <h1><?php esc_html_e( 'VE Events', VEV_Events::TEXTDOMAIN ); ?></h1>

                        <nav class="nav-tab-wrapper" id="vev-settings-tabs">
                                <a href="#tab-general"  class="nav-tab nav-tab-active"><?php esc_html_e( 'General',     VEV_Events::TEXTDOMAIN ); ?></a>
                                <a href="#tab-display"  class="nav-tab"><?php esc_html_e( 'Display',     VEV_Events::TEXTDOMAIN ); ?></a>
                                <a href="#tab-schema"   class="nav-tab"><?php esc_html_e( 'Schema &amp; SEO', VEV_Events::TEXTDOMAIN ); ?></a>
                                <a href="#tab-series"   class="nav-tab"><?php esc_html_e( 'Series',      VEV_Events::TEXTDOMAIN ); ?></a>
                                <a href="#tab-docs"     class="nav-tab"><?php esc_html_e( 'Field Reference', VEV_Events::TEXTDOMAIN ); ?></a>
                        </nav>

                        <form method="post" action="options.php">
                                <?php settings_fields( 'vev_settings_group' ); ?>

                                <!-- TAB: General -->
                                <div id="tab-general" class="vev-tab-panel">
                                        <h2><?php esc_html_e( 'URL Settings', VEV_Events::TEXTDOMAIN ); ?></h2>
                                        <table class="form-table" role="presentation">
                                                <tr>
                                                        <th scope="row"><?php esc_html_e( 'Event Slug (Single)', VEV_Events::TEXTDOMAIN ); ?></th>
                                                        <td>
                                                                <input type="text" name="<?php echo esc_attr( $opt ); ?>[slug_single]" value="<?php echo esc_attr( $settings['slug_single'] ); ?>" class="regular-text" placeholder="event" />
                                                                <p class="description"><?php esc_html_e( 'URL slug for single events, e.g. "event" or "veranstaltung".', VEV_Events::TEXTDOMAIN ); ?></p>
                                                        </td>
                                                </tr>
                                                <tr>
                                                        <th scope="row"><?php esc_html_e( 'Archive Slug', VEV_Events::TEXTDOMAIN ); ?></th>
                                                        <td>
                                                                <input type="text" name="<?php echo esc_attr( $opt ); ?>[slug_archive]" value="<?php echo esc_attr( $settings['slug_archive'] ); ?>" class="regular-text" placeholder="events" />
                                                                <p class="description"><?php esc_html_e( 'URL slug for the event archive, e.g. "events" or "veranstaltungen".', VEV_Events::TEXTDOMAIN ); ?></p>
                                                        </td>
                                                </tr>
                                        </table>
                                        <div class="notice notice-warning inline" style="margin:0 0 12px;">
                                                <p><?php esc_html_e( 'Changing slugs will automatically refresh permalinks. Existing event URLs will change.', VEV_Events::TEXTDOMAIN ); ?></p>
                                        </div>

                                        <h2><?php esc_html_e( 'Editor', VEV_Events::TEXTDOMAIN ); ?></h2>
                                        <table class="form-table" role="presentation">
                                                <tr>
                                                        <th scope="row"><?php esc_html_e( 'Block Editor', VEV_Events::TEXTDOMAIN ); ?></th>
                                                        <td>
                                                                <label>
                                                                        <input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[disable_gutenberg]" value="1" <?php checked( $settings['disable_gutenberg'] ); ?> />
                                                                        <?php esc_html_e( 'Disable Gutenberg for Events (use classic editor)', VEV_Events::TEXTDOMAIN ); ?>
                                                                </label>
                                                        </td>
                                                </tr>
                                        </table>

                                        <h2><?php esc_html_e( 'Calendar Import', VEV_Events::TEXTDOMAIN ); ?></h2>
                                        <p><?php
                                                printf(
                                                        /* translators: %s: URL to import page */
                                                        wp_kses( __( 'Manage ICS import feeds (Google Calendar, Outlook, etc.) on the <a href="%s">Import Feeds</a> page.', VEV_Events::TEXTDOMAIN ), array( 'a' => array( 'href' => array() ) ) ),
                                                        esc_url( $import_url )
                                                );
                                        ?></p>
                                        <a href="<?php echo esc_url( $import_url ); ?>" class="button"><?php esc_html_e( 'Manage Import Feeds', VEV_Events::TEXTDOMAIN ); ?></a>

                                        <h2><?php esc_html_e( 'Tools', VEV_Events::TEXTDOMAIN ); ?></h2>
                                        <p class="description"><?php esc_html_e( 'Re-computes _vev_start_date, _vev_start_month and _vev_time_slot for all existing events. Run this once after updating the plugin.', VEV_Events::TEXTDOMAIN ); ?></p>
                                        <button type="button" class="button" id="vev-resync-meta"><?php esc_html_e( 'Sync computed fields (JetEngine filters)', VEV_Events::TEXTDOMAIN ); ?></button>
                                        <span id="vev-resync-result" style="margin-left:10px;color:#2271b1;"></span>
                                        <script>
                                        document.getElementById('vev-resync-meta').addEventListener('click', function(e) {
                                                e.preventDefault();
                                                this.disabled = true;
                                                document.getElementById('vev-resync-result').textContent = '<?php esc_html_e( 'Running…', VEV_Events::TEXTDOMAIN ); ?>';
                                                var body = new URLSearchParams({
                                                        action: 'vev_resync_computed_meta',
                                                        _ajax_nonce: '<?php echo esc_js( wp_create_nonce( 'vev_resync_computed_meta' ) ); ?>'
                                                });
                                                fetch(ajaxurl, { method: 'POST', body: body })
                                                        .then(function(r) { return r.json(); })
                                                        .then(function(d) {
                                                                document.getElementById('vev-resync-result').textContent =
                                                                        d.success ? d.data.count + ' <?php esc_html_e( 'events synced.', VEV_Events::TEXTDOMAIN ); ?>' : '<?php esc_html_e( 'Error.', VEV_Events::TEXTDOMAIN ); ?>';
                                                        });
                                        });
                                        </script>
                                </div>

                                <!-- TAB: Display -->
                                <div id="tab-display" class="vev-tab-panel" hidden>
                                        <h2><?php esc_html_e( 'Date &amp; Time Display', VEV_Events::TEXTDOMAIN ); ?></h2>
                                        <table class="form-table" role="presentation">
                                                <tr>
                                                        <th scope="row"><?php esc_html_e( 'Hide Same-Day End Date', VEV_Events::TEXTDOMAIN ); ?></th>
                                                        <td>
                                                                <label>
                                                                        <input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[hide_end_same_day]" value="1" <?php checked( $settings['hide_end_same_day'] ); ?> />
                                                                        <?php esc_html_e( 'Hide the end date when start and end are on the same day', VEV_Events::TEXTDOMAIN ); ?>
                                                                </label>
                                                                <p class="description"><?php esc_html_e( 'Example output: "12.06.2025 · 10:00 – 12:00" instead of "12.06.2025 · 10:00 – 12.06.2025 · 12:00"', VEV_Events::TEXTDOMAIN ); ?></p>
                                                        </td>
                                                </tr>
                                        </table>

                                        <h2><?php esc_html_e( 'Event Visibility', VEV_Events::TEXTDOMAIN ); ?></h2>
                                        <table class="form-table" role="presentation">
                                                <tr>
                                                        <th scope="row"><?php esc_html_e( 'Grace Period After Event End', VEV_Events::TEXTDOMAIN ); ?></th>
                                                        <td>
                                                                <select name="<?php echo esc_attr( $opt ); ?>[grace_period]">
                                                                        <option value="0" <?php selected( $settings['grace_period'], 0 ); ?>><?php esc_html_e( '0 days – hide immediately', VEV_Events::TEXTDOMAIN ); ?></option>
                                                                        <option value="1" <?php selected( $settings['grace_period'], 1 ); ?>><?php esc_html_e( '1 day (recommended)', VEV_Events::TEXTDOMAIN ); ?></option>
                                                                        <option value="3" <?php selected( $settings['grace_period'], 3 ); ?>><?php esc_html_e( '3 days', VEV_Events::TEXTDOMAIN ); ?></option>
                                                                        <option value="7" <?php selected( $settings['grace_period'], 7 ); ?>><?php esc_html_e( '7 days', VEV_Events::TEXTDOMAIN ); ?></option>
                                                                </select>
                                                                <p class="description"><?php esc_html_e( 'How long events stay visible on the frontend after ending. Backend is never affected.', VEV_Events::TEXTDOMAIN ); ?></p>
                                                        </td>
                                                </tr>
                                                <tr>
                                                        <th scope="row"><?php esc_html_e( 'Search Results', VEV_Events::TEXTDOMAIN ); ?></th>
                                                        <td>
                                                                <label>
                                                                        <input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[hide_archived_search]" value="1" <?php checked( $settings['hide_archived_search'] ); ?> />
                                                                        <?php esc_html_e( 'Exclude archived events from WordPress search results', VEV_Events::TEXTDOMAIN ); ?>
                                                                </label>
                                                        </td>
                                                </tr>
                                        </table>

                                        <h2><?php esc_html_e( 'Category Colors', VEV_Events::TEXTDOMAIN ); ?></h2>
                                        <table class="form-table" role="presentation">
                                                <tr>
                                                        <th scope="row"><?php esc_html_e( 'Output Category Colors as CSS', VEV_Events::TEXTDOMAIN ); ?></th>
                                                        <td>
                                                                <label>
                                                                        <input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[output_category_colors]" value="1" <?php checked( $settings['output_category_colors'] ); ?> />
                                                                        <?php esc_html_e( 'Emit CSS custom properties and utility classes for event category colors in wp_head', VEV_Events::TEXTDOMAIN ); ?>
                                                                </label>
                                                                <p class="description"><?php esc_html_e( 'Outputs :root{--vev-cat-slug:#hex;} and .ve-cat-slug{--vev-cat-color:#hex;} for use with Elementor/CSS.', VEV_Events::TEXTDOMAIN ); ?></p>
                                                        </td>
                                                </tr>
                                        </table>

                                        <h2><?php esc_html_e( 'Event Status Logic', VEV_Events::TEXTDOMAIN ); ?></h2>
                                        <table class="widefat striped" style="max-width:520px;">
                                                <thead><tr><th><?php esc_html_e( 'Status', VEV_Events::TEXTDOMAIN ); ?></th><th><?php esc_html_e( 'Condition', VEV_Events::TEXTDOMAIN ); ?></th></tr></thead>
                                                <tbody>
                                                        <tr><td><code>upcoming</code></td><td><?php esc_html_e( 'Event has not started yet', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                        <tr><td><code>ongoing</code></td><td><?php esc_html_e( 'Currently running', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                        <tr><td><code>past</code></td><td><?php esc_html_e( 'Ended, within grace period', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                        <tr><td><code>archived</code></td><td><?php esc_html_e( 'Ended, grace period exceeded', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                </tbody>
                                        </table>
                                </div>

                                <!-- TAB: Schema -->
                                <div id="tab-schema" class="vev-tab-panel" hidden>
                                        <h2><?php esc_html_e( 'Schema.org Event Markup', VEV_Events::TEXTDOMAIN ); ?></h2>
                                        <p class="description"><?php esc_html_e( 'Structured data is automatically output on single event pages as JSON-LD. Location address is included when set on the location term.', VEV_Events::TEXTDOMAIN ); ?></p>
                                        <table class="form-table" role="presentation">
                                                <tr>
                                                        <th scope="row"><?php esc_html_e( 'Include Event Series', VEV_Events::TEXTDOMAIN ); ?></th>
                                                        <td>
                                                                <label>
                                                                        <input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[include_series_schema]" value="1" <?php checked( $settings['include_series_schema'] ); ?> />
                                                                        <?php esc_html_e( 'Add series name to Schema.org as superEvent / EventSeries', VEV_Events::TEXTDOMAIN ); ?>
                                                                </label>
                                                        </td>
                                                </tr>
                                        </table>

                                        <h2><?php esc_html_e( 'Open Graph / Social Meta Tags', VEV_Events::TEXTDOMAIN ); ?></h2>
                                        <p class="description"><?php esc_html_e( 'Outputs og: and twitter: meta tags on single event pages for social sharing previews.', VEV_Events::TEXTDOMAIN ); ?></p>
                                        <table class="form-table" role="presentation">
                                                <tr>
                                                        <th scope="row"><?php esc_html_e( 'Open Graph Tags', VEV_Events::TEXTDOMAIN ); ?></th>
                                                        <td>
                                                                <select name="<?php echo esc_attr( $opt ); ?>[og_tags]">
                                                                        <option value="auto" <?php selected( $settings['og_tags'], 'auto' ); ?>><?php esc_html_e( 'Auto (skip if Yoast / Rank Math / AIOSEO active)', VEV_Events::TEXTDOMAIN ); ?></option>
                                                                        <option value="always" <?php selected( $settings['og_tags'], 'always' ); ?>><?php esc_html_e( 'Always output', VEV_Events::TEXTDOMAIN ); ?></option>
                                                                        <option value="disabled" <?php selected( $settings['og_tags'], 'disabled' ); ?>><?php esc_html_e( 'Disabled', VEV_Events::TEXTDOMAIN ); ?></option>
                                                                </select>
                                                                <p class="description"><?php esc_html_e( 'Auto: outputs OG tags only when no dedicated SEO plugin (Yoast, Rank Math, AIOSEO, SEO Framework) is active. Use "Always" to force output even alongside an SEO plugin. Use "Disabled" to turn off completely.', VEV_Events::TEXTDOMAIN ); ?></p>
                                                        </td>
                                                </tr>
                                        </table>
                                </div>

                                <!-- TAB: Series -->
                                <div id="tab-series" class="vev-tab-panel" hidden>
                                        <h2><?php esc_html_e( 'Series Detection', VEV_Events::TEXTDOMAIN ); ?></h2>
                                        <p class="description"><?php esc_html_e( 'ICS import automatically groups events by UID into series. The feature below adds detection for manually created events.', VEV_Events::TEXTDOMAIN ); ?></p>
                                        <table class="form-table" role="presentation">
                                                <tr>
                                                        <th scope="row"><?php esc_html_e( 'Series Suggestions', VEV_Events::TEXTDOMAIN ); ?></th>
                                                        <td>
                                                                <label>
                                                                        <input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[series_suggestions]" value="1" <?php checked( $settings['series_suggestions'] ); ?> />
                                                                        <?php esc_html_e( 'Suggest series assignments for events with matching titles', VEV_Events::TEXTDOMAIN ); ?>
                                                                </label>
                                                                <p class="description"><?php esc_html_e( 'When saving an event without a series, the plugin finds other events with the same title. A notice in the editor lets you create a new series, assign to an existing one, or dismiss.', VEV_Events::TEXTDOMAIN ); ?></p>
                                                        </td>
                                                </tr>
                                        </table>
                                </div>

                                <!-- TAB: Documentation -->
                                <div id="tab-docs" class="vev-tab-panel" hidden>
                                        <h2><?php esc_html_e( 'Stored Meta Keys', VEV_Events::TEXTDOMAIN ); ?></h2>
                                        <p class="description"><?php esc_html_e( 'Use these for sorting, filtering, and meta conditions in JetEngine/WP_Query:', VEV_Events::TEXTDOMAIN ); ?></p>
                                        <table class="widefat striped" style="max-width:640px;">
                                                <thead><tr><th><?php esc_html_e( 'Key', VEV_Events::TEXTDOMAIN ); ?></th><th><?php esc_html_e( 'Description', VEV_Events::TEXTDOMAIN ); ?></th></tr></thead>
                                                <tbody>
                                                        <tr><td><code>_vev_start_utc</code></td><td><?php esc_html_e( 'Start timestamp (UTC integer)', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                        <tr><td><code>_vev_end_utc</code></td><td><?php esc_html_e( 'End timestamp (UTC integer)', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                        <tr><td><code>_vev_all_day</code></td><td><?php esc_html_e( 'All-day event (1/0)', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                        <tr><td><code>_vev_hide_end</code></td><td><?php esc_html_e( 'Hide end time (1/0)', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                        <tr><td><code>_vev_speaker</code></td><td><?php esc_html_e( 'Speaker / Host', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                        <tr><td><code>_vev_special_info</code></td><td><?php esc_html_e( 'Special information', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                        <tr><td><code>_vev_info_url</code></td><td><?php esc_html_e( 'Info / Ticket URL', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                        <tr><td><code>_vev_event_status</code></td><td><?php esc_html_e( 'Status override: cancelled | postponed | rescheduled | movedOnline | (empty = scheduled)', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                        <tr><td><code>_vev_start_hour</code></td><td><?php esc_html_e( 'Start hour in site timezone (0–23) — auto-computed, use for time-of-day filtering', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                        <tr><td><code>_vev_weekday</code></td><td><?php esc_html_e( 'ISO weekday in site timezone (1=Mon … 7=Sun) — auto-computed, use for weekday filtering', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                </tbody>
                                        </table>

                                        <h2 style="margin-top:24px;"><?php esc_html_e( 'Virtual / Computed Fields', VEV_Events::TEXTDOMAIN ); ?></h2>
                                        <p class="description"><?php esc_html_e( 'Available as Elementor Dynamic Tags and JetEngine fields. Computed at runtime:', VEV_Events::TEXTDOMAIN ); ?></p>
                                        <table class="widefat striped" style="max-width:640px;">
                                                <thead><tr><th><?php esc_html_e( 'Key', VEV_Events::TEXTDOMAIN ); ?></th><th><?php esc_html_e( 'Description', VEV_Events::TEXTDOMAIN ); ?></th></tr></thead>
                                                <tbody>
                                                        <tr><td><code>ve_start_date</code></td><td><?php esc_html_e( 'Start date (formatted)', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                        <tr><td><code>ve_start_time</code></td><td><?php esc_html_e( 'Start time (formatted)', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                        <tr><td><code>ve_end_date</code></td><td><?php esc_html_e( 'End date (formatted)', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                        <tr><td><code>ve_end_time</code></td><td><?php esc_html_e( 'End time (formatted)', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                        <tr><td><code>ve_date_range</code></td><td><?php esc_html_e( 'Smart date range', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                        <tr><td><code>ve_time_range</code></td><td><?php esc_html_e( 'Time range or "All day"', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                        <tr><td><code>ve_datetime_formatted</code></td><td><?php esc_html_e( 'Full date &amp; time', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                        <tr><td><code>ve_status</code></td><td><?php esc_html_e( 'upcoming / ongoing / past / archived', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                        <tr><td><code>ve_location_name</code></td><td><?php esc_html_e( 'Location name (taxonomy)', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                        <tr><td><code>ve_location_address</code></td><td><?php esc_html_e( 'Location address', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                        <tr><td><code>ve_location_maps_url</code></td><td><?php esc_html_e( 'Google Maps URL (auto or custom)', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                        <tr><td><code>ve_category_name</code></td><td><?php esc_html_e( 'Category name', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                        <tr><td><code>ve_category_color</code></td><td><?php esc_html_e( 'Category color (hex)', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                        <tr><td><code>ve_category_class</code></td><td><?php esc_html_e( 'CSS class for primary category, e.g. "ve-cat-konzert"', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                        <tr><td><code>ve_series_name</code></td><td><?php esc_html_e( 'Series name', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                        <tr><td><code>ve_topic_names</code></td><td><?php esc_html_e( 'Topic names (comma-separated)', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                        <tr><td><code>ve_is_upcoming</code></td><td><?php esc_html_e( '1 if event has not started yet', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                        <tr><td><code>ve_is_ongoing</code></td><td><?php esc_html_e( '1 if event is currently running', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                        <tr><td><code>ve_event_status_label</code></td><td><?php esc_html_e( 'Human-readable status: Cancelled / Postponed / Rescheduled / Moved Online', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                        <tr><td><code>ve_event_status_color</code></td><td><?php esc_html_e( 'Hex color for status badge (red / amber / blue)', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                        <tr><td><code>ve_is_cancelled</code></td><td><?php esc_html_e( '1 if event is cancelled', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                </tbody>
                                        </table>

                                        <h2 style="margin-top:24px;"><?php esc_html_e( 'Query Vars (URL / WP_Query)', VEV_Events::TEXTDOMAIN ); ?></h2>
                                        <p class="description"><?php esc_html_e( 'Pass these as URL parameters or WP_Query arguments for filtering:', VEV_Events::TEXTDOMAIN ); ?></p>
                                        <table class="widefat striped" style="max-width:640px;">
                                                <thead><tr><th><?php esc_html_e( 'Var', VEV_Events::TEXTDOMAIN ); ?></th><th><?php esc_html_e( 'Values', VEV_Events::TEXTDOMAIN ); ?></th><th><?php esc_html_e( 'Effect', VEV_Events::TEXTDOMAIN ); ?></th></tr></thead>
                                                <tbody>
                                                        <tr><td><code>vev_event_scope</code></td><td><code>upcoming | ongoing | past | archived | all</code></td><td><?php esc_html_e( 'Filter by lifecycle status', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                        <tr><td><code>vev_include_archived</code></td><td><code>1</code></td><td><?php esc_html_e( 'Include archived events (default: hidden)', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                        <tr><td><code>vev_month</code></td><td><code>2025-06</code></td><td><?php esc_html_e( 'Show only events starting in this month (YYYY-MM). Bypasses archived cutoff.', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                        <tr><td><code>vev_date_from</code></td><td><code>2025-06-01</code></td><td><?php esc_html_e( 'Events starting on or after this date (Y-m-d or UTC timestamp). Bypasses archived cutoff.', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                        <tr><td><code>vev_date_to</code></td><td><code>2025-06-30</code></td><td><?php esc_html_e( 'Events starting on or before this date (Y-m-d or UTC timestamp). Bypasses archived cutoff.', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                        <tr><td><code>vev_time_from</code></td><td><code>18</code></td><td><?php esc_html_e( 'Events starting at or after this hour (0–23, site timezone)', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                        <tr><td><code>vev_time_to</code></td><td><code>22</code></td><td><?php esc_html_e( 'Events starting at or before this hour (0–23, site timezone)', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                        <tr><td><code>vev_weekday</code></td><td><code>5</code> <?php esc_html_e( 'or', VEV_Events::TEXTDOMAIN ); ?> <code>1,3,5</code></td><td><?php esc_html_e( 'ISO weekday(s): 1=Mon … 7=Sun. Comma-separated list allowed.', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                </tbody>
                                        </table>

                                        <h2 style="margin-top:24px;"><?php esc_html_e( 'JetEngine Query Setup', VEV_Events::TEXTDOMAIN ); ?></h2>
                                        <table class="widefat striped" style="max-width:640px;">
                                                <thead><tr><th><?php esc_html_e( 'Setting', VEV_Events::TEXTDOMAIN ); ?></th><th><?php esc_html_e( 'Upcoming', VEV_Events::TEXTDOMAIN ); ?></th><th><?php esc_html_e( 'Past', VEV_Events::TEXTDOMAIN ); ?></th></tr></thead>
                                                <tbody>
                                                        <tr><td><?php esc_html_e( 'Post Type', VEV_Events::TEXTDOMAIN ); ?></td><td><code>ve_event</code></td><td><code>ve_event</code></td></tr>
                                                        <tr><td><?php esc_html_e( 'Order By', VEV_Events::TEXTDOMAIN ); ?></td><td><?php esc_html_e( 'Meta Value (Number)', VEV_Events::TEXTDOMAIN ); ?></td><td><?php esc_html_e( 'Meta Value (Number)', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                        <tr><td><?php esc_html_e( 'Meta Key', VEV_Events::TEXTDOMAIN ); ?></td><td><code>_vev_start_utc</code></td><td><code>_vev_start_utc</code></td></tr>
                                                        <tr><td><?php esc_html_e( 'Order', VEV_Events::TEXTDOMAIN ); ?></td><td>ASC</td><td>DESC</td></tr>
                                                        <tr><td><?php esc_html_e( 'Query Var', VEV_Events::TEXTDOMAIN ); ?></td><td>—</td><td><code>vev_event_scope = past</code></td></tr>
                                                </tbody>
                                        </table>
                                </div>

                                <div class="vev-tab-actions">
                                        <?php submit_button( null, 'primary', 'submit', false ); ?>
                                </div>
                        </form>
                </div>
                <script>
                (function(){
                        var tabs    = document.querySelectorAll('#vev-settings-tabs .nav-tab');
                        var panels  = document.querySelectorAll('.vev-tab-panel');
                        var actions = document.querySelector('.vev-tab-actions');

                        function activate(hash) {
                                var target = hash || '#tab-general';
                                var anyActive = false;
                                tabs.forEach(function(tab){
                                        var active = tab.getAttribute('href') === target;
                                        tab.classList.toggle('nav-tab-active', active);
                                        if(active) anyActive = true;
                                });
                                if(!anyActive) {
                                        tabs[0].classList.add('nav-tab-active');
                                        target = tabs[0].getAttribute('href');
                                }
                                panels.forEach(function(panel){
                                        panel.hidden = ('#' + panel.id) !== target;
                                });
                                // Move submit button inside active panel
                                var activePanel = document.querySelector(target);
                                if(activePanel && actions) {
                                        // Hide for docs tab
                                        actions.style.display = (target === '#tab-docs') ? 'none' : '';
                                }
                        }

                        tabs.forEach(function(tab){
                                tab.addEventListener('click', function(e){
                                        e.preventDefault();
                                        var hash = tab.getAttribute('href');
                                        history.replaceState(null, '', location.pathname + location.search + hash);
                                        activate(hash);
                                });
                        });

                        activate(location.hash || '#tab-general');
                })();
                </script>
                <style>
                        .vev-tab-panel { padding-top: 8px; }
                        .vev-tab-actions { margin-top: 16px; }
                </style>
                <?php
        }

        public static function register_calendar_page(): void {
                add_submenu_page(
                        null,
                        __( 'Event Calendar', VEV_Events::TEXTDOMAIN ),
                        __( 'Calendar', VEV_Events::TEXTDOMAIN ),
                        'edit_posts',
                        'vev-calendar',
                        array( __CLASS__, 'render_calendar_view' )
                );
        }

        public static function admin_assets( string $hook ): void {
                // Color picker for category taxonomy pages
                if ( in_array( $hook, array( 'edit-tags.php', 'term.php' ), true ) ) {
                        $screen = get_current_screen();
                        if ( $screen && VEV_Events::TAX_CATEGORY === $screen->taxonomy ) {
                                wp_enqueue_style( 'wp-color-picker' );
                                wp_enqueue_script( 'wp-color-picker' );
                                wp_add_inline_script( 'wp-color-picker',
                                        'jQuery(document).ready(function($){ $(".vev-color-picker").wpColorPicker(); });'
                                );
                        }
                }

                if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
                        return;
                }
                $screen = get_current_screen();
                if ( ! $screen || VEV_Events::POST_TYPE !== $screen->post_type ) {
                        return;
                }

                $js = <<<JS
(function() {
  'use strict';

  function toggleAllDay() {
    var allDay     = document.getElementById('vev_all_day');
    if (!allDay) return;
    var startTime  = document.getElementById('vev_start_time');
    var endTime    = document.getElementById('vev_end_time');
    var note       = document.getElementById('vev_all_day_note');
    if (startTime) startTime.disabled = allDay.checked;
    if (endTime)   endTime.disabled   = allDay.checked;
    if (note)      note.style.display = allDay.checked ? 'block' : 'none';
    updateDatePreview();
  }

  function syncEndDateMin() {
    var startDate = document.getElementById('vev_start_date');
    var startTime = document.getElementById('vev_start_time');
    var endDate   = document.getElementById('vev_end_date');
    var endTime   = document.getElementById('vev_end_time');
    if (!startDate || !endDate) return;
    var startVal = startDate.value;
    if (startVal) {
      endDate.min = startVal;
      if (endDate.value && endDate.value < startVal) endDate.value = startVal;
      if (!endDate.value) endDate.value = startVal;
    }
    if (startDate.value && endDate.value && startDate.value === endDate.value) {
      if (startTime && endTime && startTime.value) {
        endTime.min = startTime.value;
        if (endTime.value && endTime.value < startTime.value) endTime.value = startTime.value;
        if (!endTime.value) endTime.value = startTime.value;
      }
    } else if (endTime) {
      endTime.min = '';
    }
    updateDatePreview();
  }

  function updateDatePreview() {
    var preview = document.getElementById('vev_date_preview');
    if (!preview) return;
    var startDateEl = document.getElementById('vev_start_date');
    var startTimeEl = document.getElementById('vev_start_time');
    var endDateEl   = document.getElementById('vev_end_date');
    var endTimeEl   = document.getElementById('vev_end_time');
    var allDayEl    = document.getElementById('vev_all_day');
    var startDate   = startDateEl ? startDateEl.value : '';
    var startTime   = startTimeEl ? startTimeEl.value : '';
    var endDate     = endDateEl   ? endDateEl.value   : '';
    var endTime     = endTimeEl   ? endTimeEl.value   : '';
    var allDay      = allDayEl    ? allDayEl.checked   : false;
    if (!startDate) {
      preview.innerHTML = '<span style="color:#888;">\u2192 Enter a date</span>';
      return;
    }
    try {
      var locale  = (document.documentElement.lang || 'de-DE').replace(/_/g, '-');
      var dateFmt = new Intl.DateTimeFormat(locale, {weekday:'long', year:'numeric', month:'long', day:'numeric'});
      var timeFmt = new Intl.DateTimeFormat(locale, {hour:'2-digit', minute:'2-digit'});
      var sDate   = new Date(startDate + 'T00:00:00');
      var parts   = [];
      if (allDay) {
        if (endDate && endDate !== startDate) {
          parts.push(dateFmt.format(sDate) + ' \u2013 ' + dateFmt.format(new Date(endDate + 'T00:00:00')));
        } else {
          parts.push(dateFmt.format(sDate));
        }
        parts.push('<em style="color:#646970;">(All day)</em>');
      } else {
        parts.push(dateFmt.format(sDate));
        if (startTime) {
          var timeStr = timeFmt.format(new Date(startDate + 'T' + startTime));
          if (endDate || endTime) {
            var eD = endDate || startDate;
            var eT = endTime || startTime;
            timeStr += ' \u2013 ';
            if (endDate && endDate !== startDate) {
              timeStr += dateFmt.format(new Date(endDate + 'T00:00:00')) + ' ';
            }
            timeStr += timeFmt.format(new Date(eD + 'T' + eT));
          }
          parts.push('\u00B7 ' + timeStr);
        }
      }
      preview.innerHTML = parts.join(' ');
    } catch (e) {
      preview.innerHTML = startDate + (startTime ? ' ' + startTime : '');
    }
  }

  document.addEventListener('change', function(e) {
    if (!e.target) return;
    var id = e.target.id;
    if (id === 'vev_all_day') { toggleAllDay(); return; }
    if (id === 'vev_start_date' || id === 'vev_start_time' || id === 'vev_end_date' || id === 'vev_end_time') {
      syncEndDateMin();
    }
  });

  document.addEventListener('DOMContentLoaded', function() {
    toggleAllDay();
    syncEndDateMin();
    updateDatePreview();
  });
})();
JS;

                wp_register_script( 'vev-events-admin', '', array(), VEV_Events::VERSION, true );
                wp_add_inline_script( 'vev-events-admin', $js );
                wp_enqueue_script( 'vev-events-admin' );
        }

        public static function output_list_styles(): void {
                $screen = get_current_screen();
                if ( ! $screen || 'edit-' . VEV_Events::POST_TYPE !== $screen->id ) {
                        return;
                }
                ?>
                <style>
                .column-vev_when { width: 190px; }
                .vev-when-date { display: block; font-weight: 600; color: #1d2327; }
                .vev-when-time { display: block; color: #50575e; font-size: 12px; }
                .column-ve_event_topic { width: 120px; }
                .vev-month-separator td {
                        background: #f0f6fc !important;
                        border-top: 2px solid #2271b1 !important;
                        border-left: 4px solid #2271b1 !important;
                        font-weight: 700;
                        font-size: 13px;
                        color: #2271b1;
                        padding: 7px 12px !important;
                        letter-spacing: .03em;
                }
                .ve-status-badge { display:inline-block; padding:1px 6px; border-radius:3px; font-size:11px; font-weight:600; color:#fff; margin-top:3px; }
                .ve-status-cancelled    { background:#d63638; }
                .ve-status-postponed    { background:#dba617; }
                .ve-status-rescheduled  { background:#2271b1; }
                .ve-status-movedOnline  { background:#007cba; }
                </style>
                <?php
                add_action( 'admin_footer', array( __CLASS__, 'output_list_footer_js' ) );
        }

        public static function output_list_footer_js(): void {
                $screen = get_current_screen();
                if ( ! $screen || 'edit-' . VEV_Events::POST_TYPE !== $screen->id ) {
                        return;
                }
                ?>
                <script>
                (function() {
                        var rows = document.querySelectorAll('#the-list tr');
                        if (!rows.length) return;
                        var locale   = (document.documentElement.lang || 'de-DE').replace(/_/g, '-');
                        var monthFmt = new Intl.DateTimeFormat(locale, {month:'long', year:'numeric'});
                        var lastMonth = null;
                        rows.forEach(function(row) {
                                var span = row.querySelector('.vev-when-date[data-vev-month]');
                                if (!span) return;
                                var month = span.getAttribute('data-vev-month');
                                if (month === lastMonth) return;
                                lastMonth = month;
                                var p   = month.split('-');
                                var d   = new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1, 1);
                                var sep = document.createElement('tr');
                                sep.className = 'vev-month-separator';
                                var cols = row.querySelectorAll('td, th').length || 1;
                                sep.innerHTML = '<td colspan="' + cols + '">' + monthFmt.format(d) + '</td>';
                                row.parentNode.insertBefore(sep, row);
                        });
                })();
                </script>
                <?php
        }

        public static function render_event_form( \WP_Post $post ): void {
                if ( VEV_Events::POST_TYPE !== $post->post_type ) {
                        return;
                }

                wp_nonce_field( 'vev_save_event_meta', 'vev_event_meta_nonce' );

                $tz = wp_timezone();

                $start_utc    = (int) get_post_meta( $post->ID, VEV_Events::META_START_UTC, true );
                $end_utc      = (int) get_post_meta( $post->ID, VEV_Events::META_END_UTC, true );
                $all_day  = (bool) get_post_meta( $post->ID, VEV_Events::META_ALL_DAY, true );
                $hide_end = (bool) get_post_meta( $post->ID, VEV_Events::META_HIDE_END, true );

                $start_date = $start_utc ? wp_date( 'Y-m-d', $start_utc, $tz ) : '';
                $start_time = $start_utc ? wp_date( 'H:i', $start_utc, $tz ) : '';
                $end_date   = $end_utc ? wp_date( 'Y-m-d', $end_utc, $tz ) : '';
                $end_time   = $end_utc ? wp_date( 'H:i', $end_utc, $tz ) : '';

                $tz_string = (string) get_option( 'timezone_string' );
                ?>
                <div class="vev-event-form-wrap">
                <style>
                .vev-event-form-wrap { margin: 16px 0 0; }
                .vev-sections { border: 1px solid #c3c4c7; border-radius: 4px; overflow: hidden; background: #fff; }
                .vev-section { padding: 16px 20px; border-bottom: 1px solid #c3c4c7; }
                .vev-section:last-child { border-bottom: none; }
                .vev-section-title { display: flex; align-items: center; gap: 8px; margin: 0 0 14px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #50575e; }
                .vev-section-title .dashicons { font-size: 16px; width: 16px; height: 16px; line-height: 1.2; color: inherit; }
                .vev-section--status .vev-section-title { color: #d63638; }
                .vev-section--status.vev-status-default .vev-section-title { color: #50575e; }
                .vev-date-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
                .vev-detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
                .vev-full { grid-column: 1 / -1; }
                .vev-field { }
                .vev-field > label { font-weight: 600; display: block; margin-bottom: 5px; font-size: 12px; color: #50575e; text-transform: uppercase; letter-spacing: .04em; }
                .vev-field-row { display: flex; gap: 8px; align-items: center; }
                .vev-field input[type="date"],
                .vev-field input[type="time"] { padding: 5px 8px; border: 1px solid #8c8f94; border-radius: 3px; font-size: 13px; }
                .vev-field input[type="date"] { flex: 1; min-width: 0; }
                .vev-field input[type="time"] { width: 88px; flex-shrink: 0; }
                .vev-field input[type="text"],
                .vev-field input[type="url"],
                .vev-field textarea,
                .vev-field select { width: 100%; box-sizing: border-box; padding: 6px 8px; border: 1px solid #8c8f94; border-radius: 3px; font-size: 13px; }
                .vev-field textarea { resize: vertical; min-height: 60px; }
                .vev-field-help { margin: 4px 0 0; font-size: 11px; color: #646970; }
                .vev-checks { display: flex; gap: 20px; margin-top: 12px; flex-wrap: wrap; }
                .vev-check-label { display: flex; align-items: center; gap: 6px; font-size: 13px; cursor: pointer; }
                .vev-all-day-note { margin: 8px 0 0; padding: 8px 10px; background: #f6f7f7; border-left: 3px solid #8c8f94; border-radius: 2px; font-size: 12px; color: #50575e; }
                .vev-preview { margin-top: 12px; padding: 10px 14px; background: #f0f6fc; border: 1px solid #b6d4f0; border-radius: 3px; font-size: 13px; color: #1d2327; min-height: 38px; display: flex; align-items: center; gap: 6px; }
                .vev-tz-note { margin: 8px 0 0; font-size: 11px; color: #d63638; }
                .vev-dev-note { margin-top: 14px; }
                .vev-dev-note > summary { font-size: 11px; color: #646970; cursor: pointer; }
                .vev-dev-note-body { margin-top: 6px; padding: 8px 10px; background: #f6f7f7; border: 1px solid #dcdcde; border-radius: 3px; font-size: 11px; color: #646970; line-height: 1.8; }
                .vev-dev-note-body code { background: #e8eaeb; padding: 1px 4px; border-radius: 2px; }
                @media (max-width: 782px) {
                        .vev-date-grid, .vev-detail-grid { grid-template-columns: 1fr; }
                        .vev-full { grid-column: auto; }
                }
                </style>

                <div class="vev-sections">

                        <!-- ① Datum & Uhrzeit -->
                        <div class="vev-section">
                                <h3 class="vev-section-title">
                                        <span class="dashicons dashicons-calendar-alt"></span>
                                        <?php esc_html_e( 'Date &amp; Time', VEV_Events::TEXTDOMAIN ); ?>
                                </h3>

                                <div class="vev-date-grid">
                                        <div class="vev-field">
                                                <label for="vev_start_date"><?php esc_html_e( 'Start *', VEV_Events::TEXTDOMAIN ); ?></label>
                                                <div class="vev-field-row">
                                                        <input type="date" id="vev_start_date" name="vev_start_date" value="<?php echo esc_attr( $start_date ); ?>" />
                                                        <input type="time" id="vev_start_time" name="vev_start_time" value="<?php echo esc_attr( $start_time ); ?>" />
                                                </div>
                                        </div>
                                        <div class="vev-field">
                                                <label for="vev_end_date"><?php esc_html_e( 'End (optional)', VEV_Events::TEXTDOMAIN ); ?></label>
                                                <div class="vev-field-row">
                                                        <input type="date" id="vev_end_date" name="vev_end_date" value="<?php echo esc_attr( $end_date ); ?>" />
                                                        <input type="time" id="vev_end_time" name="vev_end_time" value="<?php echo esc_attr( $end_time ); ?>" />
                                                </div>
                                        </div>
                                </div>

                                <div class="vev-checks">
                                        <label class="vev-check-label">
                                                <input type="checkbox" id="vev_all_day" name="vev_all_day" value="1" <?php checked( $all_day ); ?> />
                                                <?php esc_html_e( 'All day', VEV_Events::TEXTDOMAIN ); ?>
                                        </label>
                                        <label class="vev-check-label">
                                                <input type="checkbox" id="vev_hide_end" name="vev_hide_end" value="1" <?php checked( $hide_end ); ?> />
                                                <?php esc_html_e( 'Hide end time in listings', VEV_Events::TEXTDOMAIN ); ?>
                                        </label>
                                </div>

                                <p class="vev-all-day-note" id="vev_all_day_note" style="display:none;">
                                        <?php esc_html_e( 'All-day events are stored as full-day ranges in your site time zone. Times will not be displayed.', VEV_Events::TEXTDOMAIN ); ?>
                                </p>

                                <?php if ( $tz_string && 'Europe/Berlin' !== $tz_string ) : ?>
                                <p class="vev-tz-note">
                                        <?php esc_html_e( 'For correct date output, set WordPress timezone to Europe/Berlin (Settings → General).', VEV_Events::TEXTDOMAIN ); ?>
                                </p>
                                <?php endif; ?>

                                <div class="vev-preview" id="vev_date_preview">
                                        <span style="color:#888;">&#8594; <?php esc_html_e( 'Enter a date above', VEV_Events::TEXTDOMAIN ); ?></span>
                                </div>
                        </div>

                </div><!-- .vev-sections -->
                </div><!-- .vev-event-form-wrap -->
                <?php
        }

        public static function render_details_metabox( \WP_Post $post ): void {
                $speaker  = (string) get_post_meta( $post->ID, VEV_Events::META_SPEAKER,  true );
                $special  = (string) get_post_meta( $post->ID, VEV_Events::META_SPECIAL,  true );
                $info_url = (string) get_post_meta( $post->ID, VEV_Events::META_INFO_URL, true );
                ?>
                <div class="vev-detail-grid" style="margin-top:4px;">
                        <div class="vev-field">
                                <label for="vev_speaker"><?php esc_html_e( 'Speaker / Host', VEV_Events::TEXTDOMAIN ); ?></label>
                                <input type="text" id="vev_speaker" name="vev_speaker" value="<?php echo esc_attr( $speaker ); ?>" />
                        </div>
                        <div class="vev-field">
                                <label for="vev_info_url"><?php esc_html_e( 'Info / Ticket URL', VEV_Events::TEXTDOMAIN ); ?></label>
                                <input type="url" id="vev_info_url" name="vev_info_url" value="<?php echo esc_attr( $info_url ); ?>" placeholder="https://..." />
                        </div>
                        <div class="vev-field vev-full">
                                <label for="vev_special_info"><?php esc_html_e( 'Additional Notes', VEV_Events::TEXTDOMAIN ); ?></label>
                                <textarea id="vev_special_info" name="vev_special_info" rows="2"><?php echo esc_textarea( $special ); ?></textarea>
                                <p class="vev-field-help"><?php esc_html_e( 'Short notes for listings: admission, dress code, registration info, etc.', VEV_Events::TEXTDOMAIN ); ?></p>
                        </div>
                </div>
                <details class="vev-dev-note">
                        <summary><?php esc_html_e( 'Developer: JetEngine / Elementor field keys', VEV_Events::TEXTDOMAIN ); ?></summary>
                        <div class="vev-dev-note-body">
                                <code><?php echo esc_html( VEV_Events::VIRTUAL_DATETIME ); ?></code>
                                &nbsp;<code><?php echo esc_html( VEV_Events::VIRTUAL_DATE_RANGE ); ?></code>
                                &nbsp;<code><?php echo esc_html( VEV_Events::VIRTUAL_TIME_RANGE ); ?></code>
                                &nbsp;<code><?php echo esc_html( VEV_Events::VIRTUAL_STATUS ); ?></code>
                                &nbsp;<code><?php echo esc_html( VEV_Events::VIRTUAL_STATUS_LABEL ); ?></code>
                                &nbsp;<code><?php echo esc_html( VEV_Events::VIRTUAL_IS_UPCOMING ); ?></code>
                        </div>
                </details>
                <?php
        }

        public static function render_status_metabox( \WP_Post $post ): void {
                $event_status = (string) get_post_meta( $post->ID, VEV_Events::META_EVENT_STATUS, true );
                ?>
                <div class="vev-field">
                        <select id="vev_event_status" name="vev_event_status" style="width:100%;">
                                <option value=""            <?php selected( $event_status, '' ); ?>><?php esc_html_e( 'Scheduled (default)', VEV_Events::TEXTDOMAIN ); ?></option>
                                <option value="cancelled"   <?php selected( $event_status, 'cancelled' ); ?>><?php esc_html_e( 'Cancelled',    VEV_Events::TEXTDOMAIN ); ?></option>
                                <option value="postponed"   <?php selected( $event_status, 'postponed' ); ?>><?php esc_html_e( 'Postponed',    VEV_Events::TEXTDOMAIN ); ?></option>
                                <option value="rescheduled" <?php selected( $event_status, 'rescheduled' ); ?>><?php esc_html_e( 'Rescheduled', VEV_Events::TEXTDOMAIN ); ?></option>
                                <option value="movedOnline" <?php selected( $event_status, 'movedOnline' ); ?>><?php esc_html_e( 'Moved Online', VEV_Events::TEXTDOMAIN ); ?></option>
                        </select>
                        <p class="vev-field-help" style="margin-top:6px;"><?php esc_html_e( 'Updates Schema.org eventStatus and shows a badge in the event list.', VEV_Events::TEXTDOMAIN ); ?></p>
                </div>
                <?php
        }

        public static function save_meta( int $post_id, \WP_Post $post ): void {
                if ( VEV_Events::POST_TYPE !== $post->post_type ) {
                        return;
                }

                if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
                        return;
                }

                if ( ! isset( $_POST['vev_event_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['vev_event_meta_nonce'] ) ), 'vev_save_event_meta' ) ) {
                        return;
                }

                if ( ! current_user_can( 'edit_post', $post_id ) ) {
                        return;
                }

                $tz = wp_timezone();

                $start_date = isset( $_POST['vev_start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['vev_start_date'] ) ) : '';
                $start_time = isset( $_POST['vev_start_time'] ) ? sanitize_text_field( wp_unslash( $_POST['vev_start_time'] ) ) : '';

                $end_date = isset( $_POST['vev_end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['vev_end_date'] ) ) : '';
                $end_time = isset( $_POST['vev_end_time'] ) ? sanitize_text_field( wp_unslash( $_POST['vev_end_time'] ) ) : '';

                $all_day  = isset( $_POST['vev_all_day'] ) ? 1 : 0;
                $hide_end = isset( $_POST['vev_hide_end'] ) ? 1 : 0;

                $speaker = isset( $_POST['vev_speaker'] ) ? sanitize_text_field( wp_unslash( $_POST['vev_speaker'] ) ) : '';
                $special = isset( $_POST['vev_special_info'] ) ? wp_kses_post( wp_unslash( $_POST['vev_special_info'] ) ) : '';
                $info_url = isset( $_POST['vev_info_url'] ) ? esc_url_raw( wp_unslash( $_POST['vev_info_url'] ) ) : '';

                $start_ts = self::parse_to_utc_timestamp( $start_date, $start_time, (bool) $all_day, true, $tz );
                $end_ts   = self::parse_to_utc_timestamp( $end_date ?: $start_date, $end_time ?: $start_time, (bool) $all_day, false, $tz );

                if ( ! $end_ts ) {
                        $end_ts = $start_ts;
                }

                if ( $start_ts && $end_ts && ! $all_day && $end_ts < $start_ts ) {
                        $end_ts = $start_ts;
                }

                if ( $start_ts ) {
                        update_post_meta( $post_id, VEV_Events::META_START_UTC, $start_ts );
                } else {
                        delete_post_meta( $post_id, VEV_Events::META_START_UTC );
                }

                if ( $end_ts ) {
                        update_post_meta( $post_id, VEV_Events::META_END_UTC, $end_ts );
                } else {
                        delete_post_meta( $post_id, VEV_Events::META_END_UTC );
                }

                update_post_meta( $post_id, VEV_Events::META_ALL_DAY, (int) $all_day );
                update_post_meta( $post_id, VEV_Events::META_HIDE_END, (int) $hide_end );

                if ( '' !== $speaker ) {
                        update_post_meta( $post_id, VEV_Events::META_SPEAKER, $speaker );
                } else {
                        delete_post_meta( $post_id, VEV_Events::META_SPEAKER );
                }

                if ( '' !== $special ) {
                        update_post_meta( $post_id, VEV_Events::META_SPECIAL, $special );
                } else {
                        delete_post_meta( $post_id, VEV_Events::META_SPECIAL );
                }

                if ( '' !== $info_url ) {
                        update_post_meta( $post_id, VEV_Events::META_INFO_URL, $info_url );
                } else {
                        delete_post_meta( $post_id, VEV_Events::META_INFO_URL );
                }

                $allowed_statuses = array( '', 'cancelled', 'postponed', 'rescheduled', 'movedOnline' );
                $event_status     = sanitize_text_field( wp_unslash( $_POST['vev_event_status'] ?? '' ) );
                $event_status     = in_array( $event_status, $allowed_statuses, true ) ? $event_status : '';
                if ( '' !== $event_status ) {
                        update_post_meta( $post_id, VEV_Events::META_EVENT_STATUS, $event_status );
                } else {
                        delete_post_meta( $post_id, VEV_Events::META_EVENT_STATUS );
                }

                VEV_Events::log(
                        sprintf(
                                'Event %d saved. start_utc=%s end_utc=%s all_day=%s hide_end=%s',
                                $post_id,
                                (string) $start_ts,
                                (string) $end_ts,
                                (string) $all_day,
                                (string) $hide_end
                        )
                );
        }

        private static function parse_to_utc_timestamp( string $date, string $time, bool $all_day, bool $is_start, \DateTimeZone $tz ): int {
                if ( '' === $date ) {
                        return 0;
                }

                try {
                        if ( $all_day ) {
                                $clock = $is_start ? '00:00:00' : '23:59:59';
                                $dt    = new \DateTimeImmutable( $date . ' ' . $clock, $tz );
                        } else {
                                $clean_time = $time ?: '00:00';
                                $dt         = new \DateTimeImmutable( $date . ' ' . $clean_time, $tz );
                        }

                        return (int) $dt->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'U' );
                } catch ( \Exception $e ) {
                        VEV_Events::log( 'Date parse error: ' . $e->getMessage() );
                        return 0;
                }
        }

        public static function admin_columns( array $columns ): array {
                $cols = array();

                if ( isset( $columns['cb'] ) ) {
                        $cols['cb'] = $columns['cb'];
                }
                $cols['title']    = __( 'Title', VEV_Events::TEXTDOMAIN );
                $cols['vev_when'] = __( 'When', VEV_Events::TEXTDOMAIN );

                $cols[ VEV_Events::TAX_CATEGORY ] = __( 'Category', VEV_Events::TEXTDOMAIN );
                $cols[ VEV_Events::TAX_LOCATION ] = __( 'Location', VEV_Events::TEXTDOMAIN );
                $cols[ VEV_Events::TAX_TOPIC ]    = __( 'Topic', VEV_Events::TEXTDOMAIN );
                $cols[ VEV_Events::TAX_SERIES ]   = __( 'Series', VEV_Events::TEXTDOMAIN );

                return $cols;
        }

        public static function admin_sortable_columns( array $columns ): array {
                $columns['vev_when'] = 'vev_when';
                return $columns;
        }

        public static function admin_column_content( string $column, int $post_id ): void {
                $tz = wp_timezone();

                switch ( $column ) {
                        case 'vev_when':
                                $start   = (int) get_post_meta( $post_id, VEV_Events::META_START_UTC, true );
                                $end     = (int) get_post_meta( $post_id, VEV_Events::META_END_UTC, true );
                                $all_day = (bool) get_post_meta( $post_id, VEV_Events::META_ALL_DAY, true );
                                if ( $start ) {
                                        $month    = wp_date( 'Y-m', $start, $tz );
                                        $date_str = wp_date( 'j. M Y', $start, $tz );
                                        printf(
                                                '<span class="vev-when-date" data-vev-month="%s">%s</span>',
                                                esc_attr( $month ),
                                                esc_html( $date_str )
                                        );
                                        if ( ! $all_day ) {
                                                $time_str = wp_date( 'H:i', $start, $tz );
                                                if ( $end && $end !== $start ) {
                                                        $time_str .= ' – ' . wp_date( 'H:i', $end, $tz );
                                                }
                                                printf( '<span class="vev-when-time">%s</span>', esc_html( $time_str ) );
                                        }
                                        $event_status = (string) get_post_meta( $post_id, VEV_Events::META_EVENT_STATUS, true );
                                        if ( $event_status ) {
                                                $label_map = array(
                                                        'cancelled'   => __( 'Cancelled',    VEV_Events::TEXTDOMAIN ),
                                                        'postponed'   => __( 'Postponed',    VEV_Events::TEXTDOMAIN ),
                                                        'rescheduled' => __( 'Rescheduled',  VEV_Events::TEXTDOMAIN ),
                                                        'movedOnline' => __( 'Moved Online', VEV_Events::TEXTDOMAIN ),
                                                );
                                                printf(
                                                        '<br><span class="ve-status-badge ve-status-%s">%s</span>',
                                                        esc_attr( $event_status ),
                                                        esc_html( $label_map[ $event_status ] ?? $event_status )
                                                );
                                        }
                                } else {
                                        echo '—';
                                }
                                break;

                        case VEV_Events::TAX_CATEGORY:
                        case VEV_Events::TAX_LOCATION:
                        case VEV_Events::TAX_TOPIC:
                        case VEV_Events::TAX_SERIES:
                                $terms = get_the_terms( $post_id, $column );
                                if ( is_array( $terms ) && ! empty( $terms ) ) {
                                        echo esc_html( implode( ', ', wp_list_pluck( $terms, 'name' ) ) );
                                } else {
                                        echo '—';
                                }
                                break;
                }
        }

        public static function admin_views( array $views ): array {
                $base_url = admin_url( 'edit.php?post_type=' . VEV_Events::POST_TYPE );
                $now      = time();

                $current_view = isset( $_GET['vev_view'] ) ? sanitize_key( $_GET['vev_view'] ) : '';
                $post_status  = isset( $_GET['post_status'] ) ? sanitize_key( $_GET['post_status'] ) : '';

                if ( '' === $current_view && '' === $post_status ) {
                        $current_view = 'upcoming';
                }

                global $wpdb;
                $pt = VEV_Events::POST_TYPE;

                $count_upcoming = (int) $wpdb->get_var( $wpdb->prepare(
                        "SELECT COUNT( DISTINCT p.ID ) FROM {$wpdb->posts} p
                         LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
                         WHERE p.post_type = %s AND p.post_status = 'publish'
                         AND ( CAST( pm.meta_value AS SIGNED ) >= %d OR pm.meta_value IS NULL )",
                        VEV_Events::META_END_UTC,
                        $pt,
                        $now
                ) );

                $count_past = (int) $wpdb->get_var( $wpdb->prepare(
                        "SELECT COUNT( DISTINCT p.ID ) FROM {$wpdb->posts} p
                         INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
                         WHERE p.post_type = %s AND p.post_status = 'publish'
                         AND CAST( pm.meta_value AS SIGNED ) < %d",
                        VEV_Events::META_END_UTC,
                        $pt,
                        $now
                ) );

                $counts      = wp_count_posts( $pt );
                $count_all   = (int) ( $counts->publish ?? 0 );
                $count_draft = (int) ( $counts->draft ?? 0 );
                $count_trash = (int) ( $counts->trash ?? 0 );

                $new_views = array();

                $class = ( 'upcoming' === $current_view ) ? 'current' : '';
                $new_views['upcoming'] = sprintf(
                        '<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
                        esc_url( add_query_arg( 'vev_view', 'upcoming', $base_url ) ),
                        $class,
                        __( 'Upcoming', VEV_Events::TEXTDOMAIN ),
                        number_format_i18n( $count_upcoming )
                );

                $class = ( 'past' === $current_view ) ? 'current' : '';
                $new_views['past'] = sprintf(
                        '<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
                        esc_url( add_query_arg( 'vev_view', 'past', $base_url ) ),
                        $class,
                        __( 'Past', VEV_Events::TEXTDOMAIN ),
                        number_format_i18n( $count_past )
                );

                $class = ( 'all' === $current_view ) ? 'current' : '';
                $new_views['all'] = sprintf(
                        '<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
                        esc_url( add_query_arg( 'vev_view', 'all', $base_url ) ),
                        $class,
                        __( 'All', VEV_Events::TEXTDOMAIN ),
                        number_format_i18n( $count_all )
                );

                if ( $count_draft > 0 ) {
                        $class = ( 'draft' === $post_status ) ? 'current' : '';
                        $new_views['draft'] = sprintf(
                                '<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
                                esc_url( add_query_arg( 'post_status', 'draft', $base_url ) ),
                                $class,
                                __( 'Drafts', VEV_Events::TEXTDOMAIN ),
                                number_format_i18n( $count_draft )
                        );
                }

                if ( $count_trash > 0 ) {
                        $class = ( 'trash' === $post_status ) ? 'current' : '';
                        $new_views['trash'] = sprintf(
                                '<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
                                esc_url( add_query_arg( 'post_status', 'trash', $base_url ) ),
                                $class,
                                __( 'Trash', VEV_Events::TEXTDOMAIN ),
                                number_format_i18n( $count_trash )
                        );
                }

                // Calendar Grid View
                $cal_url      = admin_url( 'edit.php?post_type=' . VEV_Events::POST_TYPE . '&page=vev-calendar' );
                $current_page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
                $new_views['calendar'] = sprintf(
                        '<a href="%s" class="%s">%s</a>',
                        esc_url( $cal_url ),
                        ( 'vev-calendar' === $current_page ) ? 'current' : '',
                        esc_html__( 'Calendar', VEV_Events::TEXTDOMAIN )
                );

                return $new_views;
        }

        public static function display_post_states( array $post_states, \WP_Post $post ): array {
                if ( VEV_Events::POST_TYPE !== $post->post_type ) {
                        return $post_states;
                }

                $data       = VEV_Frontend::get_event_data( $post->ID );
                $raw_status = VEV_Frontend::get_event_status( $data['start_utc'], $data['end_utc'] );
                if ( 'ongoing' === $raw_status ) {
                        $post_states['ve_ongoing'] = __( 'Ongoing', VEV_Events::TEXTDOMAIN );
                } elseif ( 'past' === $raw_status || 'archived' === $raw_status ) {
                        $post_states['ve_past'] = __( 'Past', VEV_Events::TEXTDOMAIN );
                }

                $event_status = (string) get_post_meta( $post->ID, VEV_Events::META_EVENT_STATUS, true );
                if ( 'cancelled' === $event_status ) {
                        $post_states['ve_cancelled']  = __( 'Cancelled',    VEV_Events::TEXTDOMAIN );
                } elseif ( 'postponed' === $event_status ) {
                        $post_states['ve_postponed']  = __( 'Postponed',    VEV_Events::TEXTDOMAIN );
                } elseif ( 'rescheduled' === $event_status ) {
                        $post_states['ve_rescheduled'] = __( 'Rescheduled', VEV_Events::TEXTDOMAIN );
                } elseif ( 'movedOnline' === $event_status ) {
                        $post_states['ve_movedonline'] = __( 'Moved Online', VEV_Events::TEXTDOMAIN );
                }

                return $post_states;
        }

        // -------------------------------------------------------------------------
        // Location term meta: address + maps URL
        // -------------------------------------------------------------------------

        public static function render_location_add_fields(): void {
                ?>
                <div class="form-field">
                        <label for="ve_location_address"><?php esc_html_e( 'Address', VEV_Events::TEXTDOMAIN ); ?></label>
                        <textarea name="ve_location_address" id="ve_location_address" rows="3"></textarea>
                        <p><?php esc_html_e( 'Full address used for automatic Google Maps link generation.', VEV_Events::TEXTDOMAIN ); ?></p>
                </div>
                <div class="form-field">
                        <label for="ve_location_maps_url"><?php esc_html_e( 'Custom Maps URL (optional)', VEV_Events::TEXTDOMAIN ); ?></label>
                        <input type="url" name="ve_location_maps_url" id="ve_location_maps_url" value="" />
                        <p><?php esc_html_e( 'Overrides the auto-generated Google Maps link. Leave empty to use the address above.', VEV_Events::TEXTDOMAIN ); ?></p>
                </div>
                <?php
        }

        public static function render_location_edit_fields( \WP_Term $term ): void {
                $address  = (string) get_term_meta( $term->term_id, VEV_Events::TERM_META_LOCATION_ADDRESS, true );
                $maps_url = (string) get_term_meta( $term->term_id, VEV_Events::TERM_META_LOCATION_MAPS_URL, true );
                $auto_url = $address ? 'https://maps.google.com/?q=' . rawurlencode( $address ) : '';
                ?>
                <tr class="form-field">
                        <th scope="row"><label for="ve_location_address"><?php esc_html_e( 'Address', VEV_Events::TEXTDOMAIN ); ?></label></th>
                        <td>
                                <textarea name="ve_location_address" id="ve_location_address" rows="3"><?php echo esc_textarea( $address ); ?></textarea>
                                <p class="description"><?php esc_html_e( 'Full address — used to auto-generate a Google Maps link.', VEV_Events::TEXTDOMAIN ); ?></p>
                        </td>
                </tr>
                <?php if ( $auto_url && ! $maps_url ) : ?>
                <tr class="form-field">
                        <th scope="row"><?php esc_html_e( 'Maps URL (auto)', VEV_Events::TEXTDOMAIN ); ?></th>
                        <td>
                                <a href="<?php echo esc_url( $auto_url ); ?>" target="_blank" rel="noopener noreferrer">
                                        <?php echo esc_html( $auto_url ); ?>
                                </a>
                                <p class="description"><?php esc_html_e( 'Auto-generated from the address. Enter a custom URL below to override.', VEV_Events::TEXTDOMAIN ); ?></p>
                        </td>
                </tr>
                <?php endif; ?>
                <tr class="form-field">
                        <th scope="row"><label for="ve_location_maps_url"><?php esc_html_e( 'Custom Maps URL', VEV_Events::TEXTDOMAIN ); ?></label></th>
                        <td>
                                <input type="url" name="ve_location_maps_url" id="ve_location_maps_url" value="<?php echo esc_attr( $maps_url ); ?>" class="regular-text" />
                                <p class="description"><?php esc_html_e( 'Optional. Overrides the auto-generated link above.', VEV_Events::TEXTDOMAIN ); ?></p>
                        </td>
                </tr>
                <?php
        }

        public static function save_location_term_meta( int $term_id ): void {
                if ( ! current_user_can( 'manage_categories' ) ) {
                        return;
                }
                $address  = isset( $_POST['ve_location_address'] )
                        ? sanitize_textarea_field( wp_unslash( $_POST['ve_location_address'] ) )
                        : '';
                $maps_url = isset( $_POST['ve_location_maps_url'] )
                        ? esc_url_raw( wp_unslash( $_POST['ve_location_maps_url'] ) )
                        : '';

                update_term_meta( $term_id, VEV_Events::TERM_META_LOCATION_ADDRESS, $address );
                update_term_meta( $term_id, VEV_Events::TERM_META_LOCATION_MAPS_URL, $maps_url );
        }

        // -------------------------------------------------------------------------
        // Category term meta: color
        // -------------------------------------------------------------------------

        public static function render_category_add_fields(): void {
                ?>
                <div class="form-field">
                        <label for="ve_category_color"><?php esc_html_e( 'Category Color', VEV_Events::TEXTDOMAIN ); ?></label>
                        <input type="text" name="ve_category_color" id="ve_category_color" value="" class="vev-color-picker" />
                        <p><?php esc_html_e( 'Choose a color for this category. Used in listings and calendar views.', VEV_Events::TEXTDOMAIN ); ?></p>
                </div>
                <?php
        }

        public static function render_category_edit_fields( \WP_Term $term ): void {
                $color = (string) get_term_meta( $term->term_id, VEV_Events::TERM_META_CATEGORY_COLOR, true );
                ?>
                <tr class="form-field">
                        <th scope="row"><label for="ve_category_color"><?php esc_html_e( 'Category Color', VEV_Events::TEXTDOMAIN ); ?></label></th>
                        <td>
                                <input type="text" name="ve_category_color" id="ve_category_color" value="<?php echo esc_attr( $color ); ?>" class="vev-color-picker" />
                                <p class="description"><?php esc_html_e( 'Choose a color for this category. Used in listings and calendar views.', VEV_Events::TEXTDOMAIN ); ?></p>
                        </td>
                </tr>
                <?php
        }

        public static function save_category_term_meta( int $term_id ): void {
                if ( ! current_user_can( 'manage_categories' ) ) {
                        return;
                }
                $color = isset( $_POST['ve_category_color'] )
                        ? sanitize_hex_color( wp_unslash( $_POST['ve_category_color'] ) )
                        : '';

                update_term_meta( $term_id, VEV_Events::TERM_META_CATEGORY_COLOR, $color ?? '' );
        }

        // -------------------------------------------------------------------------
        // Series suggestion system
        // -------------------------------------------------------------------------

        public static function detect_series_suggestion( int $post_id ): void {
                $settings = VEV_Events::get_settings();
                if ( empty( $settings['series_suggestions'] ) ) {
                        return;
                }

                if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
                        return;
                }

                if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
                        return;
                }

                // Skip import-generated posts
                if ( get_post_meta( $post_id, '_vev_import_feed_id', true ) ) {
                        return;
                }

                // Skip if already has a series assigned
                $existing = wp_get_object_terms( $post_id, VEV_Events::TAX_SERIES, array( 'fields' => 'ids' ) );
                if ( ! empty( $existing ) && ! is_wp_error( $existing ) ) {
                        delete_post_meta( $post_id, '_vev_series_suggestion' );
                        return;
                }

                $post = get_post( $post_id );
                if ( ! $post || empty( $post->post_title ) ) {
                        return;
                }

                $normalized_title = self::normalize_title( $post->post_title );

                // Find sibling events with same title
                $siblings = get_posts( array(
                        'post_type'      => VEV_Events::POST_TYPE,
                        'post_status'    => array( 'publish', 'draft', 'future' ),
                        'post__not_in'   => array( $post_id ),
                        'posts_per_page' => 50,
                        'fields'         => 'ids',
                        's'              => $post->post_title,
                        'exact'          => true,
                ) );

                // Filter by normalized title
                $matching_ids = array();
                foreach ( $siblings as $sibling_id ) {
                        $sibling_post = get_post( $sibling_id );
                        if ( $sibling_post && self::normalize_title( $sibling_post->post_title ) === $normalized_title ) {
                                $matching_ids[] = (int) $sibling_id;
                        }
                }

                if ( empty( $matching_ids ) ) {
                        delete_post_meta( $post_id, '_vev_series_suggestion' );
                        return;
                }

                // If siblings already have a series, auto-assign directly (no suggestion needed)
                foreach ( $matching_ids as $sibling_id ) {
                        $sibling_series = wp_get_object_terms( $sibling_id, VEV_Events::TAX_SERIES, array( 'fields' => 'ids' ) );
                        if ( ! empty( $sibling_series ) && ! is_wp_error( $sibling_series ) ) {
                                $series_term_id = (int) $sibling_series[0];
                                wp_set_object_terms( $post_id, $series_term_id, VEV_Events::TAX_SERIES, true );
                                delete_post_meta( $post_id, '_vev_series_suggestion' );
                                VEV_Events::log( sprintf( 'Auto-assigned post %d to existing series term %d', $post_id, $series_term_id ) );
                                return;
                        }
                }

                // No series found yet — store suggestion for manual review
                update_post_meta( $post_id, '_vev_series_suggestion', array(
                        'title'       => $post->post_title,
                        'sibling_ids' => $matching_ids,
                        'status'      => 'pending',
                ) );
        }

        public static function render_series_suggestion_notice(): void {
                $screen = get_current_screen();
                if ( ! $screen || ! in_array( $screen->base, array( 'post', 'post-new' ), true ) ) {
                        return;
                }

                global $post;
                if ( ! $post || VEV_Events::POST_TYPE !== $post->post_type ) {
                        return;
                }

                $suggestion = get_post_meta( $post->ID, '_vev_series_suggestion', true );
                if ( empty( $suggestion ) || 'pending' !== ( $suggestion['status'] ?? '' ) ) {
                        return;
                }

                $sibling_count = count( $suggestion['sibling_ids'] ?? array() );
                $series_terms  = get_terms( array(
                        'taxonomy'   => VEV_Events::TAX_SERIES,
                        'hide_empty' => false,
                ) );

                $post_id = $post->ID;
                $nonce   = wp_create_nonce( 'vev_series_suggestion_' . $post_id );
                ?>
                <div class="notice notice-warning" id="vev-series-suggestion" style="padding:12px 16px;">
                        <p><strong><?php esc_html_e( 'Series Suggestion', VEV_Events::TEXTDOMAIN ); ?></strong></p>
                        <p>
                                <?php
                                echo esc_html( sprintf(
                                        /* translators: 1: count of similar events, 2: event title */
                                        _n(
                                                'Found %1$d other event with the same title "%2$s". Would you like to add these events to a series?',
                                                'Found %1$d other events with the same title "%2$s". Would you like to add these events to a series?',
                                                $sibling_count,
                                                VEV_Events::TEXTDOMAIN
                                        ),
                                        $sibling_count,
                                        $suggestion['title']
                                ) );
                                ?>
                        </p>
                        <p>
                                <button type="button" class="button button-primary" id="vev-series-create"
                                        data-post-id="<?php echo esc_attr( $post_id ); ?>"
                                        data-nonce="<?php echo esc_attr( $nonce ); ?>">
                                        <?php esc_html_e( 'Create new series & assign', VEV_Events::TEXTDOMAIN ); ?>
                                </button>
                                &nbsp;
                                <?php if ( ! empty( $series_terms ) && ! is_wp_error( $series_terms ) ) : ?>
                                <select id="vev-series-existing-select" style="vertical-align:middle;">
                                        <option value=""><?php esc_html_e( '— or assign to existing series —', VEV_Events::TEXTDOMAIN ); ?></option>
                                        <?php foreach ( $series_terms as $term ) : ?>
                                                <option value="<?php echo esc_attr( $term->term_id ); ?>"><?php echo esc_html( $term->name ); ?></option>
                                        <?php endforeach; ?>
                                </select>
                                <button type="button" class="button" id="vev-series-assign"
                                        data-post-id="<?php echo esc_attr( $post_id ); ?>"
                                        data-nonce="<?php echo esc_attr( $nonce ); ?>">
                                        <?php esc_html_e( 'Assign', VEV_Events::TEXTDOMAIN ); ?>
                                </button>
                                &nbsp;
                                <?php endif; ?>
                                <button type="button" class="button" id="vev-series-dismiss"
                                        data-post-id="<?php echo esc_attr( $post_id ); ?>"
                                        data-nonce="<?php echo esc_attr( $nonce ); ?>">
                                        <?php esc_html_e( 'Dismiss', VEV_Events::TEXTDOMAIN ); ?>
                                </button>
                        </p>
                        <div id="vev-series-feedback" style="margin-top:8px;"></div>
                </div>
                <script>
                (function($) {
                        function vevSeriesAjax(action, termId) {
                                var postId = $('#vev-series-create').data('post-id');
                                var nonce  = $('#vev-series-create').data('nonce');
                                $.post(ajaxurl, {
                                        action:  'vev_series_suggestion',
                                        sub:     action,
                                        post_id: postId,
                                        term_id: termId || 0,
                                        nonce:   nonce
                                }, function(response) {
                                        if (response.success) {
                                                $('#vev-series-suggestion').slideUp();
                                        } else {
                                                $('#vev-series-feedback').html('<span style="color:#d63638;">' + (response.data || '<?php echo esc_js( __( 'Error. Please try again.', VEV_Events::TEXTDOMAIN ) ); ?>') + '</span>');
                                        }
                                });
                        }
                        $('#vev-series-create').on('click', function() { vevSeriesAjax('create'); });
                        $('#vev-series-dismiss').on('click', function() { vevSeriesAjax('dismiss'); });
                        $('#vev-series-assign').on('click', function() {
                                var termId = $('#vev-series-existing-select').val();
                                if (!termId) { alert('<?php echo esc_js( __( 'Please select a series first.', VEV_Events::TEXTDOMAIN ) ); ?>'); return; }
                                vevSeriesAjax('assign', termId);
                        });
                })(jQuery);
                </script>
                <?php
        }

        public static function ajax_resync_computed_meta(): void {
                check_ajax_referer( 'vev_resync_computed_meta' );
                if ( ! current_user_can( 'manage_options' ) ) {
                        wp_die( -1 );
                }
                $ids = get_posts( array(
                        'post_type'      => VEV_Events::POST_TYPE,
                        'post_status'    => 'any',
                        'posts_per_page' => -1,
                        'fields'         => 'ids',
                ) );
                foreach ( $ids as $id ) {
                        VEV_Post_Type::sync_computed_date_meta( 0, (int) $id, VEV_Events::META_START_UTC );
                }
                wp_send_json_success( array( 'count' => count( $ids ) ) );
        }

        public static function handle_series_suggestion_ajax(): void {
                $post_id = (int) ( $_POST['post_id'] ?? 0 );
                $nonce   = (string) ( $_POST['nonce'] ?? '' );
                $sub     = (string) ( $_POST['sub'] ?? '' );

                if ( ! wp_verify_nonce( $nonce, 'vev_series_suggestion_' . $post_id ) ) {
                        wp_send_json_error( __( 'Security check failed.', VEV_Events::TEXTDOMAIN ) );
                }

                if ( ! current_user_can( 'edit_post', $post_id ) ) {
                        wp_send_json_error( __( 'Permission denied.', VEV_Events::TEXTDOMAIN ) );
                }

                $suggestion = get_post_meta( $post_id, '_vev_series_suggestion', true );
                if ( empty( $suggestion ) ) {
                        wp_send_json_error( __( 'No suggestion found.', VEV_Events::TEXTDOMAIN ) );
                }

                $sibling_ids = array_map( 'intval', $suggestion['sibling_ids'] ?? array() );

                if ( 'dismiss' === $sub ) {
                        $suggestion['status'] = 'dismissed';
                        update_post_meta( $post_id, '_vev_series_suggestion', $suggestion );
                        wp_send_json_success();
                }

                if ( 'create' === $sub ) {
                        $post = get_post( $post_id );
                        if ( ! $post ) {
                                wp_send_json_error( __( 'Post not found.', VEV_Events::TEXTDOMAIN ) );
                        }
                        $new_term = wp_insert_term( $post->post_title, VEV_Events::TAX_SERIES );
                        if ( is_wp_error( $new_term ) ) {
                                wp_send_json_error( $new_term->get_error_message() );
                        }
                        $term_id = (int) $new_term['term_id'];
                        wp_set_object_terms( $post_id, $term_id, VEV_Events::TAX_SERIES, true );
                        foreach ( $sibling_ids as $sibling_id ) {
                                wp_set_object_terms( $sibling_id, $term_id, VEV_Events::TAX_SERIES, true );
                        }
                        delete_post_meta( $post_id, '_vev_series_suggestion' );
                        VEV_Events::log( sprintf( 'Created series term %d and assigned to post %d + %d siblings', $term_id, $post_id, count( $sibling_ids ) ) );
                        wp_send_json_success();
                }

                if ( 'assign' === $sub ) {
                        $term_id = (int) ( $_POST['term_id'] ?? 0 );
                        if ( ! $term_id || ! term_exists( $term_id, VEV_Events::TAX_SERIES ) ) {
                                wp_send_json_error( __( 'Invalid series term.', VEV_Events::TEXTDOMAIN ) );
                        }
                        wp_set_object_terms( $post_id, $term_id, VEV_Events::TAX_SERIES, true );
                        foreach ( $sibling_ids as $sibling_id ) {
                                wp_set_object_terms( $sibling_id, $term_id, VEV_Events::TAX_SERIES, true );
                        }
                        delete_post_meta( $post_id, '_vev_series_suggestion' );
                        wp_send_json_success();
                }

                wp_send_json_error( __( 'Unknown action.', VEV_Events::TEXTDOMAIN ) );
        }

        private static function normalize_title( string $title ): string {
                $title = mb_strtolower( trim( $title ), 'UTF-8' );
                $title = preg_replace( '/\s+/', ' ', $title );
                $title = preg_replace( '/[^\p{L}\p{N}\s]/u', '', $title ?? '' );
                return $title ?? '';
        }

        // -------------------------------------------------------------------------
        // Admin list: filter bar
        // -------------------------------------------------------------------------

        public static function render_list_filter_bar( string $post_type ): void {
                if ( VEV_Events::POST_TYPE !== $post_type ) {
                        return;
                }

                global $wpdb;

                // Month dropdown — aggregate distinct YYYY-MM from _vev_start_utc
                $months = $wpdb->get_col( $wpdb->prepare(
                        "SELECT DISTINCT DATE_FORMAT( FROM_UNIXTIME( CAST( pm.meta_value AS SIGNED ) ), '%%Y-%%m' ) AS ym
                         FROM {$wpdb->postmeta} pm
                         INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                         WHERE pm.meta_key = %s
                           AND p.post_type = %s
                           AND p.post_status != 'trash'
                           AND pm.meta_value > 0
                         ORDER BY ym DESC",
                        VEV_Events::META_START_UTC,
                        VEV_Events::POST_TYPE
                ) );

                $selected_month = isset( $_GET['vev_list_month'] )
                        ? sanitize_text_field( wp_unslash( $_GET['vev_list_month'] ) )
                        : '';

                echo '<select name="vev_list_month">';
                echo '<option value="">' . esc_html__( 'All months', VEV_Events::TEXTDOMAIN ) . '</option>';
                foreach ( $months as $ym ) {
                        if ( ! preg_match( '/^\d{4}-\d{2}$/', (string) $ym ) ) {
                                continue;
                        }
                        [ $y, $m ] = explode( '-', (string) $ym );
                        $label = wp_date( 'F Y', mktime( 0, 0, 0, (int) $m, 1, (int) $y ) );
                        printf(
                                '<option value="%s" %s>%s</option>',
                                esc_attr( $ym ),
                                selected( $selected_month, $ym, false ),
                                esc_html( (string) $label )
                        );
                }
                echo '</select>&nbsp;';

                // Category dropdown
                wp_dropdown_categories( array(
                        'taxonomy'        => VEV_Events::TAX_CATEGORY,
                        'name'            => VEV_Events::TAX_CATEGORY,
                        'show_option_all' => __( 'All categories', VEV_Events::TEXTDOMAIN ),
                        'hide_empty'      => false,
                        'selected'        => isset( $_GET[ VEV_Events::TAX_CATEGORY ] ) ? (int) $_GET[ VEV_Events::TAX_CATEGORY ] : 0,
                        'value_field'     => 'term_id',
                        'hierarchical'    => true,
                ) );
                echo '&nbsp;';

                // Location dropdown
                wp_dropdown_categories( array(
                        'taxonomy'        => VEV_Events::TAX_LOCATION,
                        'name'            => VEV_Events::TAX_LOCATION,
                        'show_option_all' => __( 'All locations', VEV_Events::TEXTDOMAIN ),
                        'hide_empty'      => false,
                        'selected'        => isset( $_GET[ VEV_Events::TAX_LOCATION ] ) ? (int) $_GET[ VEV_Events::TAX_LOCATION ] : 0,
                        'value_field'     => 'term_id',
                ) );
                echo '&nbsp;';

                // Topic dropdown
                wp_dropdown_categories( array(
                        'taxonomy'        => VEV_Events::TAX_TOPIC,
                        'name'            => VEV_Events::TAX_TOPIC,
                        'show_option_all' => __( 'All topics', VEV_Events::TEXTDOMAIN ),
                        'hide_empty'      => false,
                        'selected'        => isset( $_GET[ VEV_Events::TAX_TOPIC ] ) ? (int) $_GET[ VEV_Events::TAX_TOPIC ] : 0,
                        'value_field'     => 'term_id',
                ) );
        }

        public static function apply_admin_list_filters( \WP_Query $query ): void {
                if ( ! is_admin() || ! $query->is_main_query() ) {
                        return;
                }
                if ( VEV_Events::POST_TYPE !== $query->get( 'post_type' ) ) {
                        return;
                }

                $month = isset( $_GET['vev_list_month'] )
                        ? sanitize_text_field( wp_unslash( $_GET['vev_list_month'] ) )
                        : '';

                if ( $month && preg_match( '/^\d{4}-\d{2}$/', $month ) ) {
                        [ $y, $m ] = explode( '-', $month );
                        $tz       = wp_timezone();
                        $from     = ( new \DateTimeImmutable( $y . '-' . $m . '-01 00:00:00', $tz ) )->getTimestamp();
                        $last_day = (int) ( new \DateTimeImmutable( $y . '-' . $m . '-01', $tz ) )->format( 't' );
                        $to       = ( new \DateTimeImmutable( $y . '-' . $m . '-' . $last_day . ' 23:59:59', $tz ) )->getTimestamp();

                        $meta_query   = (array) $query->get( 'meta_query' );
                        $meta_query[] = array(
                                'key'     => VEV_Events::META_START_UTC,
                                'value'   => array( $from, $to ),
                                'compare' => 'BETWEEN',
                                'type'    => 'NUMERIC',
                        );
                        $query->set( 'meta_query', $meta_query );
                }
        }

        // -------------------------------------------------------------------------
        // Calendar Grid View
        // -------------------------------------------------------------------------

        /**
         * Outputs the subsubsub views navigation (Upcoming / Past / All / Drafts / Calendar).
         * Used both by admin_views() (list page, WP renders the UL) and render_calendar_view()
         * (calendar page, we render the UL directly).
         *
         * @param string $current The active view key.
         */
        private static function render_views_nav( string $current ): void {
                global $wpdb;

                $pt       = VEV_Events::POST_TYPE;
                $now      = time();
                $base_url = admin_url( 'edit.php?post_type=' . $pt );
                $cal_url  = admin_url( 'edit.php?post_type=' . $pt . '&page=vev-calendar' );

                $count_upcoming = (int) $wpdb->get_var( $wpdb->prepare(
                        "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
                         LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
                         WHERE p.post_type = %s AND p.post_status = 'publish'
                           AND (CAST(pm.meta_value AS SIGNED) >= %d OR pm.meta_value IS NULL)",
                        VEV_Events::META_END_UTC, $pt, $now
                ) );

                $count_past = (int) $wpdb->get_var( $wpdb->prepare(
                        "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
                         INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
                         WHERE p.post_type = %s AND p.post_status = 'publish'
                           AND CAST(pm.meta_value AS SIGNED) < %d",
                        VEV_Events::META_END_UTC, $pt, $now
                ) );

                $counts      = wp_count_posts( $pt );
                $count_all   = (int) ( $counts->publish ?? 0 );
                $count_draft = (int) ( $counts->draft ?? 0 );

                $items = array();

                $items['upcoming'] = sprintf(
                        '<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
                        esc_url( add_query_arg( 'vev_view', 'upcoming', $base_url ) ),
                        'upcoming' === $current ? 'current' : '',
                        __( 'Upcoming', VEV_Events::TEXTDOMAIN ),
                        number_format_i18n( $count_upcoming )
                );
                $items['past'] = sprintf(
                        '<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
                        esc_url( add_query_arg( 'vev_view', 'past', $base_url ) ),
                        'past' === $current ? 'current' : '',
                        __( 'Past', VEV_Events::TEXTDOMAIN ),
                        number_format_i18n( $count_past )
                );
                $items['all'] = sprintf(
                        '<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
                        esc_url( add_query_arg( 'vev_view', 'all', $base_url ) ),
                        'all' === $current ? 'current' : '',
                        __( 'All', VEV_Events::TEXTDOMAIN ),
                        number_format_i18n( $count_all )
                );
                if ( $count_draft > 0 ) {
                        $items['draft'] = sprintf(
                                '<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
                                esc_url( add_query_arg( 'post_status', 'draft', $base_url ) ),
                                'draft' === $current ? 'current' : '',
                                __( 'Drafts', VEV_Events::TEXTDOMAIN ),
                                number_format_i18n( $count_draft )
                        );
                }
                $items['calendar'] = sprintf(
                        '<a href="%s" class="%s">%s</a>',
                        esc_url( $cal_url ),
                        'calendar' === $current ? 'current' : '',
                        esc_html__( 'Calendar', VEV_Events::TEXTDOMAIN )
                );

                echo '<ul class="subsubsub">';
                $last = array_key_last( $items );
                foreach ( $items as $key => $html ) {
                        printf(
                                '<li class="%s">%s%s</li>',
                                esc_attr( $key ),
                                $html,
                                $key !== $last ? ' |' : ''
                        );
                }
                echo '</ul>';
        }

        public static function render_calendar_view(): void {
                if ( ! current_user_can( 'edit_posts' ) ) {
                        wp_die( esc_html__( 'You do not have permission to view this page.', VEV_Events::TEXTDOMAIN ) );
                }

                $month_qv = isset( $_GET['vev_cal_month'] )
                        ? sanitize_text_field( wp_unslash( $_GET['vev_cal_month'] ) )
                        : '';

                if ( ! preg_match( '/^\d{4}-\d{2}$/', $month_qv ) ) {
                        $month_qv = current_time( 'Y-m' );
                }

                [ $year, $month ] = array_map( 'intval', explode( '-', $month_qv ) );
                $tz          = wp_timezone();
                $month_start = new \DateTimeImmutable( sprintf( '%04d-%02d-01 00:00:00', $year, $month ), $tz );
                $month_end   = $month_start->modify( 'last day of this month' )->setTime( 23, 59, 59 );

                $prev_month = $month_start->modify( '-1 month' )->format( 'Y-m' );
                $next_month = $month_start->modify( '+1 month' )->format( 'Y-m' );
                $base_url   = admin_url( 'edit.php?post_type=' . VEV_Events::POST_TYPE . '&page=vev-calendar' );

                // Query events this month
                $events_query = new \WP_Query( array(
                        'post_type'      => VEV_Events::POST_TYPE,
                        'post_status'    => 'publish',
                        'posts_per_page' => 300,
                        'meta_query'     => array(
                                array(
                                        'key'     => VEV_Events::META_START_UTC,
                                        'value'   => array( $month_start->getTimestamp(), $month_end->getTimestamp() ),
                                        'compare' => 'BETWEEN',
                                        'type'    => 'NUMERIC',
                                ),
                        ),
                        'meta_key'  => VEV_Events::META_START_UTC,
                        'orderby'   => 'meta_value_num',
                        'order'     => 'ASC',
                        VEV_Events::QV_INCLUDE_ARCHIVED => 1,
                ) );

                // Index events by day-of-month
                $days = array();
                if ( $events_query->have_posts() ) {
                        while ( $events_query->have_posts() ) {
                                $events_query->the_post();
                                $p         = get_post();
                                $start_utc = (int) get_post_meta( $p->ID, VEV_Events::META_START_UTC, true );
                                $day       = (int) wp_date( 'j', $start_utc, $tz );
                                $days[ $day ][] = $p;
                        }
                        wp_reset_postdata();
                }

                // Build category → color map
                $cat_colors = array();
                $all_cats   = get_terms( array( 'taxonomy' => VEV_Events::TAX_CATEGORY, 'hide_empty' => false ) );
                if ( is_array( $all_cats ) ) {
                        foreach ( $all_cats as $cat ) {
                                $color = (string) get_term_meta( $cat->term_id, VEV_Events::TERM_META_CATEGORY_COLOR, true );
                                if ( $color ) {
                                        $cat_colors[ $cat->term_id ] = $color;
                                }
                        }
                }

                $first_dow     = (int) $month_start->format( 'N' ); // 1 = Mon
                $days_in_month = (int) $month_start->format( 't' );
                $month_label   = wp_date( 'F Y', $month_start->getTimestamp(), $tz );

                $today_d = (int) current_time( 'j' );
                $today_m = (int) current_time( 'n' );
                $today_y = (int) current_time( 'Y' );
                ?>
                <div class="wrap">
                        <h1 class="wp-heading-inline"><?php esc_html_e( 'Events', VEV_Events::TEXTDOMAIN ); ?></h1>
                        <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . VEV_Events::POST_TYPE ) ); ?>" class="page-title-action">
                                <?php esc_html_e( 'Add New Event', VEV_Events::TEXTDOMAIN ); ?>
                        </a>
                        <hr class="wp-header-end">
                        <?php self::render_views_nav( 'calendar' ); ?>

                        <style>
                        .vev-cal-header { display:flex; align-items:center; justify-content:flex-end; gap:16px; margin:16px 0 12px; }
                        .vev-cal-title  { margin:0; font-size:20px; font-weight:600; min-width:180px; text-align:center; }
                        .vev-cal-grid   { display:grid; grid-template-columns:repeat(7,1fr); border:1px solid #c3c4c7; border-radius:4px; overflow:hidden; background:#f6f7f7; }
                        .vev-cal-dow    { text-align:center; padding:8px 4px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:#50575e; border-right:1px solid #c3c4c7; border-bottom:1px solid #c3c4c7; }
                        .vev-cal-dow:last-child { border-right:none; }
                        .vev-cal-day    { border-right:1px solid #c3c4c7; border-bottom:1px solid #c3c4c7; padding:5px 6px; min-height:80px; background:#fff; }
                        .vev-cal-day:nth-child(7n) { border-right:none; }
                        .vev-cal-day--empty  { background:#fafafa; }
                        .vev-cal-day--today  { background:#f0f6fc; }
                        .vev-cal-day-num     { font-weight:700; font-size:12px; color:#1d2327; margin-bottom:3px; }
                        .vev-cal-day--today .vev-cal-day-num { color:#2271b1; }
                        .vev-cal-event       { display:block; padding:2px 5px; margin:2px 0; border-radius:3px; font-size:11px; line-height:1.4; color:#fff; text-decoration:none; background:#2271b1; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
                        .vev-cal-event:hover { opacity:.82; color:#fff; }
                        </style>

                        <div class="vev-cal-header">
                                <a href="<?php echo esc_url( add_query_arg( 'vev_cal_month', $prev_month, $base_url ) ); ?>" class="button">&#8592;</a>
                                <h2 class="vev-cal-title"><?php echo esc_html( (string) $month_label ); ?></h2>
                                <a href="<?php echo esc_url( add_query_arg( 'vev_cal_month', $next_month, $base_url ) ); ?>" class="button">&#8594;</a>
                        </div>

                        <div class="vev-cal-grid">
                                <?php
                                // Day-of-week headers (Mon–Sun)
                                $dow_labels = array(
                                        __( 'Mon', VEV_Events::TEXTDOMAIN ),
                                        __( 'Tue', VEV_Events::TEXTDOMAIN ),
                                        __( 'Wed', VEV_Events::TEXTDOMAIN ),
                                        __( 'Thu', VEV_Events::TEXTDOMAIN ),
                                        __( 'Fri', VEV_Events::TEXTDOMAIN ),
                                        __( 'Sat', VEV_Events::TEXTDOMAIN ),
                                        __( 'Sun', VEV_Events::TEXTDOMAIN ),
                                );
                                foreach ( $dow_labels as $dow_label ) {
                                        printf( '<div class="vev-cal-dow">%s</div>', esc_html( $dow_label ) );
                                }

                                // Empty leading cells
                                for ( $i = 1; $i < $first_dow; $i++ ) {
                                        echo '<div class="vev-cal-day vev-cal-day--empty"></div>';
                                }

                                // Day cells
                                for ( $d = 1; $d <= $days_in_month; $d++ ) {
                                        $is_today = ( $d === $today_d && $month === $today_m && $year === $today_y );
                                        $cls      = 'vev-cal-day' . ( $is_today ? ' vev-cal-day--today' : '' );
                                        echo '<div class="' . esc_attr( $cls ) . '">';
                                        echo '<div class="vev-cal-day-num">' . esc_html( (string) $d ) . '</div>';

                                        if ( ! empty( $days[ $d ] ) ) {
                                                foreach ( $days[ $d ] as $ev ) {
                                                        $bg    = '#2271b1';
                                                        $cats  = get_the_terms( $ev->ID, VEV_Events::TAX_CATEGORY );
                                                        if ( is_array( $cats ) && ! empty( $cats ) ) {
                                                                $cid = (int) $cats[0]->term_id;
                                                                if ( isset( $cat_colors[ $cid ] ) ) {
                                                                        $bg = $cat_colors[ $cid ];
                                                                }
                                                        }
                                                        printf(
                                                                '<a href="%s" class="vev-cal-event" style="background:%s;" title="%s">%s</a>',
                                                                esc_url( (string) get_edit_post_link( $ev->ID ) ),
                                                                esc_attr( $bg ),
                                                                esc_attr( $ev->post_title ),
                                                                esc_html( $ev->post_title )
                                                        );
                                                }
                                        }
                                        echo '</div>';
                                }

                                // Trailing empty cells to fill last row
                                $total    = $first_dow - 1 + $days_in_month;
                                $trailing = ( 7 - ( $total % 7 ) ) % 7;
                                for ( $i = 0; $i < $trailing; $i++ ) {
                                        echo '<div class="vev-cal-day vev-cal-day--empty"></div>';
                                }
                                ?>
                        </div><!-- .vev-cal-grid -->
                </div><!-- .wrap -->
                <?php
        }
}
