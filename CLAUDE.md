# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**VE Events** is a WordPress plugin (PHP 8.0+, WordPress 6.4+) that adds a custom `ve_event` post type with 4 taxonomies, 20+ virtual meta fields, Schema.org markup, iCal import, and first-class Elementor/JetEngine support. There are no build tools, no Composer dependencies, no npm packages, and no test suite — it is pure PHP/WordPress with no external dependencies beyond an embedded iCal parsing library.

## Development Workflow

There are no build, lint, or test commands. Development requires a local WordPress installation. To test changes:

1. Install/activate the plugin in a WordPress site (`wp-content/plugins/ve-events-wordpress-plugin/`)
2. Enable debug logging by adding `define( 'VEV_DEBUG', true );` to `wp-config.php`
3. Log output goes to `wp-content/uploads/ve-events.log`

WordPress's `WP_DEBUG` constant also enables logging.

## Architecture

### Initialization Flow

The plugin bootstraps via a static singleton pattern. `VEV_Events::init()` is called directly on `plugins_loaded`. It requires all class files immediately (no autoloader), then registers two hooks on `init`: priority 0 for textdomain, priority 1 for `init_components()`. `VEV_GitHub_Updater::init()` is called at file scope, outside the class.

The import module (`VEV_Import_Manager`) is only loaded and initialized when `is_admin()` or `DOING_CRON` — it is not available on frontend-only requests.

### Class Responsibilities

| Class | File | Purpose |
|---|---|---|
| `VEV_Events` | `ve-events.php` | Central hub: all constants, settings, logging, activation hooks |
| `VEV_Post_Type` | `includes/class-post-type.php` | Registers post type, 4 taxonomies, and REST fields |
| `VEV_Fields` | `includes/class-fields.php` | Field registry + all virtual field callbacks |
| `VEV_Query` | `includes/class-query.php` | `pre_get_posts` filtering: scope, date range, time-of-day, weekday |
| `VEV_Frontend` | `includes/class-frontend.php` | Schema.org JSON-LD, Open Graph tags, date/time formatting utilities |
| `VEV_Admin` | `includes/class-admin.php` | Admin list views (calendar + list), metaboxes, settings page |
| `VEV_JetEngine` | `includes/class-jetengine.php` | Registers virtual fields with JetEngine; outputs category color CSS |
| `VEV_Elementor` | `includes/class-elementor.php` | Registers 7 dynamic tag types; delegates to `includes/elementor/` |
| `VEV_GitHub_Updater` | `includes/class-github-updater.php` | Auto-update checks against GitHub releases |
| `VEV_Import_Manager` | `includes/import/class-import-manager.php` | Bootstraps import module; manages WP-Cron scheduling per feed |

All classes use static methods exclusively — there are no instance methods aside from `VEV_Import_Runner`.

### Naming Conventions

All constants live in `VEV_Events` and follow strict prefixes:

- **Post type:** `ve_event`
- **Taxonomies:** `ve_event_category`, `ve_event_location`, `ve_event_topic`, `ve_event_series`
- **Stored post meta keys** (in `wp_postmeta`): `_vev_*` prefix (underscore = hidden from UI). Examples: `_vev_start_utc`, `_vev_end_utc`, `_vev_all_day`
- **Computed/synced post meta** (stored but derived from `_vev_start_utc`): `_vev_start_hour`, `_vev_weekday`, `_vev_start_date`, `_vev_start_month`, `_vev_time_slot`
- **Virtual meta keys** (computed at runtime, not stored): `ve_*` prefix. Examples: `ve_start_date`, `ve_date_range`, `ve_category_color`
- **Query variables:** `vev_*` prefix. Examples: `vev_event_scope`, `vev_month`
- **Settings option:** `vev_settings` (single `wp_options` entry, array)
- **CSS classes/variables:** `ve-cat-{slug}`, `--vev-cat-{slug}`
- **PHP class names:** `VEV_` prefix

### Virtual Meta Field System

`VEV_Fields` maintains a registry of all fields (both stored and virtual). Virtual fields have a `callback` entry — when `get_post_meta()` is called with a `ve_*` key, the `get_post_metadata` filter in `VEV_Post_Type` intercepts it and delegates to `VEV_Fields::get_field_value()`, which invokes the callback. This means virtual fields are transparently accessible via standard WordPress APIs, JetEngine, Elementor dynamic tags, and the REST API without any special handling by callers.

Adding a new virtual field requires:
1. Adding an entry to `VEV_Fields::$fields` with a `callback`
2. Implementing the callback as a static method on `VEV_Fields`
3. The field becomes immediately available in JetEngine, Elementor, REST, and PHP

### Query Filtering

`VEV_Query` hooks into `pre_get_posts` and applies `meta_query` conditions based on the custom query variables. Key behaviours:
- **Default frontend ordering**: by `_vev_start_utc` ASC unless the caller sets `meta_key`/`orderby` explicitly
- **Archived cutoff**: by default, events older than `grace_period` hours are excluded from frontend queries. The cutoff is bypassed when an explicit date range (`vev_date_from`/`vev_date_to`/`vev_month`) is set, so past-month queries work correctly
- **Admin list views**: the `vev_view` URL parameter drives the upcoming/past tab filtering; `edit.php` admin queries are handled as a separate branch in `pre_get_posts`
- **Search extension**: frontend search joins against postmeta and taxonomy term names so speaker, notes, and taxonomy terms are searchable

### Settings

`VEV_Events::get_settings()` returns a cached array merged with defaults. Call `VEV_Events::flush_settings_cache()` after saving options (already done in `VEV_Admin`). Default values are defined inline in `get_settings()` — when adding a new setting, add its default there and add the UI field in `VEV_Admin`.

### Import Module

`VEV_Import_Manager` bootstraps all import sub-classes. The import post type (`vev_import_feed`) stores feed configuration. `VEV_Import_Runner` does the actual iCal fetch-and-parse using the embedded `ICal`/`Event` classes in `includes/import/lib/`. `VEV_Import_Logger` writes results to a custom DB table (created on activation). Cron events are keyed by `(feed_post_id)` and automatically scheduled/unscheduled as feed posts are published, trashed, or deleted.

### Extensibility Hooks

- **`ve_schema_event`** filter: modify the Schema.org JSON-LD array before output
- **`ve_category_color_css`** filter (in `VEV_JetEngine`): modify the generated category color CSS
- Elementor dynamic tags group name: `ve-events`

## Key Patterns

- **All timestamps** are stored as UTC Unix integers (`int`). Conversion to the site timezone happens only at display time using `wp_date()` and `wp_timezone()`.
- **`_vev_start_hour` and `_vev_weekday`** are always auto-synced in `VEV_Admin` whenever `_vev_start_utc` is saved. These stored values exist solely to enable fast `meta_query` filtering (JetEngine date-range and time-of-day filters require stored numeric values).
- **Open Graph output** checks for Yoast, Rank Math, AIOSEO, and The SEO Framework before outputting to avoid duplication when `og_tags` is `auto`.
- **`ve_location_maps_url`** auto-generates a Google Maps URL from `ve_location_address` if no custom URL is set.
- **i18n**: text domain is `ve-events`. All user-visible strings must use `__()` / `_e()` with this domain.
