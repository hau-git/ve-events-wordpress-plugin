# VE Events (WordPress Plugin)

A lightweight Events custom post type designed to stay close to WordPress core conventions and integrate cleanly with Elementor + JetEngine listings.

## Post type

- Post type key: `ve_event`
- Public + REST enabled (`show_in_rest = true`)
- Supports: title, editor, featured image, excerpt

## Taxonomies

- `ve_event_category` (hierarchical)
- `ve_event_location` (non-hierarchical)
- `ve_event_topic` (non-hierarchical, tag-like)
- `ve_event_series` (hierarchical)

## Meta keys (stored)

All timestamps are stored as UTC Unix timestamps (integers). Input/output is handled in the site time zone.

- `_vev_start_utc` (int)
- `_vev_end_utc` (int)
- `_vev_all_day` (bool/int)
- `_vev_hide_end` (bool/int)
- `_vev_speaker` (string)
- `_vev_special_info` (string)
- `_vev_info_url` (string, URL)

## Virtual meta keys (computed)

These are not stored in the database. They are computed at runtime via the `get_post_metadata` filter.
This allows JetEngine/Elementor listings to display them without any shortcodes.

- `vev_status` => upcoming|ongoing|past|archived
- `vev_status_label` => localized label
- `vev_is_live` => 1|0
- `vev_is_past` => 1|0
- `vev_timerange` => localized date/time range, respects `_vev_hide_end`
- `vev_start_local` => localized date/time
- `vev_end_local` => localized date/time

## Default frontend behavior

- Sort event queries by `_vev_start_utc` ascending (unless you already set a meta ordering)
- Hide events one day after the event end (`_vev_end_utc < now - 86400`)

Optional query var:
- `vev_event_scope`: upcoming|ongoing|past|archived|all

## Schema.org

On single event pages the plugin outputs an `Event` JSON-LD block.

## Debugging

- Enable via `WP_DEBUG` or `define('VEV_DEBUG', true);`
- Log file: `wp-content/uploads/ve-events.log`
