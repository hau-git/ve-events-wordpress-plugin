# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**VE Events** is a WordPress plugin (PHP 8.3+, WordPress 6.4+) that adds a custom `ve_event` post type with 4 taxonomies, 20+ virtual meta fields, Schema.org markup, Open Graph tags, iCal import **and export**, an interactive admin calendar, and first-class Elementor/JetEngine support. It ships with **zero runtime dependencies** beyond an embedded iCal parsing library — no Composer autoloader is loaded at runtime, no npm packages, no build step.

Composer is used for **dev tooling only** (PHP_CodeSniffer + PHPUnit). Frontend event display is currently delegated to Elementor dynamic tags / JetEngine; the plugin emits structured data and computed fields but ships no first-party single/archive templates.

## Development Workflow

Requires a local PHP 8.3+ and Composer for tooling. Install once with `composer install`.

| Command | What it does |
|---|---|
| `composer lint` | Runs `phpcs` (WordPress Coding Standards + PHPCompatibility, config in `.phpcs.xml*`) |
| `composer lint:fix` | Runs `phpcbf` to auto-fix fixable violations |
| `composer test` | Runs `phpunit` (config in `phpunit.xml`) |

The PHPUnit suite is **pure-logic only**: `tests/bootstrap.php` defines a handful of WordPress function stubs (`__`, `absint`, `wp_timezone`, `get_option`, `wp_date`) and registers the plugin's own autoloader. No WordPress install is required. Only classes under `src/Support/` (and other dependency-free helpers) are unit-testable this way — anything calling `get_post_meta`, `WP_Query`, `wp_insert_post`, etc. is verified manually against a WordPress install.

To test in WordPress:
1. Symlink/copy into `wp-content/plugins/ve-events-wordpress-plugin/` and activate.
2. Enable debug logging via `define( 'VEV_DEBUG', true );` in `wp-config.php` (or WordPress's own `WP_DEBUG`). Log output goes to `wp-content/uploads/ve-events.log`.

CI (`.github/workflows/`) runs lint + tests on push.

## Architecture

### Bootstrap flow

`ve-events.php` (the only root PHP file) requires `src/Autoloader.php`, registers the custom PSR-4 autoloader (`VEV\` → `src/`), then calls `VEV\Plugin::init( __FILE__ )`.

`Plugin::init()` registers two `init` hooks: priority 0 loads the textdomain, priority 1 runs `init_components()`. `init_components()` wires:

- Always: `PostType`, `ComputedMeta`, `Fields\Registry`, `Query\Bootstrap`, `Frontend\Bootstrap`, `Integrations\Bootstrap`, `Export\Endpoint`.
- `is_admin()` only: `Admin\Bootstrap`.
- `is_admin()` or `DOING_CRON`: `Import\Manager`.

Activation/deactivation hooks flush rewrite rules and (de)schedule import cron.

Every class is `final` and uses **static methods exclusively** with an `init()` that registers its own WordPress hooks. There are no service containers and no instances (aside from the bundled `ThirdParty\ICal` parser and `Import\Runner`).

### Class map

| Namespace / class | File | Purpose |
|---|---|---|
| `VEV\Plugin` | `src/Plugin.php` | Bootstrap, component wiring, activation, `Plugin::log()` |
| `VEV\Constants` | `src/Constants.php` | Single source of truth for every identifier (version, post type, taxonomies, meta/virtual/query-var keys, option name) |
| `VEV\Settings` | `src/Settings.php` | Settings option: defaults, per-request cache, `sanitize()` |
| `VEV\PostType` | `src/PostType.php` | Registers post type, 4 taxonomies, post meta, term meta |
| `VEV\ComputedMeta` | `src/ComputedMeta.php` | Syncs computed date meta from `_vev_start_utc` |
| `VEV\Compat` | `src/Compat.php` | Legacy `VEV_Events` constant shim for backward compatibility |
| `VEV\Fields\Registry` | `src/Fields/Registry.php` | Field definition map + all virtual-field callbacks (with per-request term cache) |
| `VEV\Query\QueryFilters` / `SearchFilters` | `src/Query/` | `pre_get_posts` shaping (scope/date/time/weekday) + search join extension |
| `VEV\Frontend\*` | `src/Frontend/` | `SchemaOutput` (JSON-LD), `OpenGraph`, `CategoryStyles`, `RestFields`, `ComputedMetaFilter` |
| `VEV\Admin\*` | `src/Admin/` | Editor form, metaboxes, list table, list filters + shared `ViewsNav`, settings, calendar page + `CalendarAjax`, tools, series suggestions, assets, term-meta UI |
| `VEV\Export\*` | `src/Export/` | `IcsBuilder` (pure RFC-5545) + `Endpoint` (single `.ics` download + subscribable feed) |
| `VEV\Import\*` | `src/Import/` | ICS feed config post type, cron, fetch/parse runner, field mapper, logger |
| `VEV\Integrations\*` | `src/Integrations/` | Elementor dynamic tags + JetEngine field registration |
| `VEV\Support\*` | `src/Support/` | Pure, unit-tested single-sources-of-truth (see below) |
| `VEV\ThirdParty\ICal\*` | `src/ThirdParty/ICal/` | Embedded ICS parser (RRULE expansion) |
| `VEV\Updater\GitHubUpdater` | `src/Updater/GitHubUpdater.php` | Self-hosted update checks against GitHub releases |

### Support layer (pure, unit-tested)

`src/Support/` holds the de-duplicated logic that used to be repeated across the admin, fields, and schema output. Prefer adding logic here (and a PHPUnit test) over inlining it:

- `EventData` — per-request cache of an event's core date meta (`start_utc`, `end_utc`, `all_day`, `hide_end`).
- `DateFormatter` — all timezone-aware date/time formatting and Schema.org ISO output.
- `Lifecycle` — `upcoming`/`ongoing`/`past`/`archived` computation and the grace-period cutoff.
- `EventStatus` — the manual override map (`cancelled`/`postponed`/`rescheduled`/`movedOnline`) → label, color, Schema.org URI, badge class.
- `AttendanceMode` — attendance mode (`online`/`mixed`/offline) → label + Schema.org URI (`movedOnline` status forces Online).
- `DateParser` — date+time (site TZ) → UTC timestamp (shared by the editor save and calendar AJAX).
- `Reschedule` — DST-safe shift of an event to a new start date, preserving wall-clock time and day-span.
- `EventDescription` — the description string used by schema and export.

## Key conventions

### Naming / prefixes (all defined in `Constants`)

- Post type: `ve_event`; taxonomies: `ve_event_category|location|topic|series`.
- **Stored meta** (`wp_postmeta`): `_vev_*` (underscore = hidden). Core: `_vev_start_utc`, `_vev_end_utc`, `_vev_all_day`, `_vev_hide_end`, `_vev_speaker`, `_vev_special_info`, `_vev_info_url`, `_vev_event_status`, plus organizer/price fields.
- **Computed stored meta** (auto-synced from `_vev_start_utc`): `_vev_start_hour`, `_vev_weekday`, `_vev_start_date`, `_vev_start_month`, `_vev_time_slot`.
- **Virtual meta** (computed at runtime, never stored): `ve_*` (e.g. `ve_date_range`, `ve_category_color`, `ve_ical_url`).
- Query vars: `vev_*`. Settings option: `vev_settings` (single array). AJAX actions: `wp_ajax_vev_*`.
- CSS: `.ve-cat-{slug}`, `--vev-cat-{slug}`. PHP classes: `VEV\` namespace.

### Timestamps

**All timestamps are stored as UTC Unix integers.** Conversion to the site timezone happens only at display time via `wp_date()` / `wp_timezone()`. All-day events store `00:00:00`–`23:59:59` of the local day.

### Computed-meta sync contract (important)

`ComputedMeta::init()` hooks `added_post_meta` and `updated_post_meta`. **Any** code path that writes `_vev_start_utc` via `update_post_meta()` automatically re-syncs `_vev_start_hour/_weekday/_start_date/_start_month/_time_slot`. This is why the calendar drag-and-drop and quick-create AJAX handlers write through `update_post_meta()` and never touch `$wpdb` directly — the denormalized filter fields stay correct for free. These stored numeric fields exist solely so JetEngine date-range / time-of-day / weekday filters can hit indexed `meta_query` values instead of computing at query time.

### Virtual field system

`Fields\Registry` holds a map of field definitions. Virtual fields carry a `callback`. When `get_post_meta()` is called with a `ve_*` key, `Frontend\ComputedMetaFilter` (a `get_post_metadata` filter) delegates to `Registry::get_field_value()`, which invokes the callback. So virtual fields are transparently readable via standard WP APIs, JetEngine, Elementor tags, and REST.

To add a virtual field: add an entry to `Registry::$fields` with a `callback`, implement the callback as a static method, and (if it should be documented) add a row to the Settings "Field Reference" tab. Taxonomy-based getters must go through `Registry::get_primary_term()` / `get_post_terms()` so they share the per-request term cache.

### Settings

`Settings::get()` returns defaults merged with the stored option, cached per request. Defaults live inline in `Settings::defaults()`; sanitization in `Settings::sanitize()`. When adding a setting, add its default, its sanitize line, and its UI field in `SettingsPage`. Call `Settings::flush_cache()` after saving (already wired on `update_option_vev_settings`).

### i18n

Text domain is `ve-events`. **Source strings are English**; German lives in `languages/ve-events-de_DE.po/.mo`. All user-visible strings use `__()`/`_e()`/`esc_html__()` etc. JS-facing strings are passed via `wp_localize_script` (there is no `wp-i18n` build step). Regenerate `languages/ve-events.pot` when strings change.

### Extensibility hooks

- `ve_schema_event` filter — modify the Schema.org JSON-LD array before output.
- `ve_category_color_css` filter — modify the generated category color CSS.
- Elementor dynamic-tag group: `ve-events`.
