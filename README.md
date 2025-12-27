# VE Events (WordPress Plugin)

A lightweight Events custom post type designed to stay close to WordPress core conventions and integrate cleanly with Elementor + JetEngine listings.

## Post Type

- Post type key: `ve_event`
- Public + REST enabled (`show_in_rest = true`)
- Supports: title, editor, featured image, excerpt

## Taxonomies

| Taxonomy | Type | Description |
|----------|------|-------------|
| `ve_event_category` | Hierarchical | Event categories |
| `ve_event_location` | Non-hierarchical | Locations/Venues |
| `ve_event_topic` | Non-hierarchical | Topics/Tags |
| `ve_event_series` | Hierarchical | Event series |

---

## Meta Keys Reference

### Stored Meta Keys (Database)

These are stored in the database. All timestamps are UTC Unix timestamps (integers).

| Meta Key | Type | Description | Example Value |
|----------|------|-------------|---------------|
| `_vev_start_utc` | int | Start timestamp (UTC) | `1735689600` |
| `_vev_end_utc` | int | End timestamp (UTC) | `1735693200` |
| `_vev_all_day` | bool | All-day event flag | `1` or `0` |
| `_vev_hide_end` | bool | Hide end time in display | `1` or `0` |
| `_vev_speaker` | string | Speaker/Presenter name | `"Dr. Jane Smith"` |
| `_vev_special_info` | string | Additional info text | `"Free parking available"` |
| `_vev_info_url` | string | External info URL | `"https://example.com/event"` |

### Virtual Meta Keys (Computed/Formatted)

These are computed at runtime and return formatted, localized output. Perfect for JetEngine Dynamic Field widget and Elementor Dynamic Tags.

#### Date & Time Fields

| Meta Key | Output | Example |
|----------|--------|---------|
| `ve_start_date` | Start date only | `"January 15, 2025"` |
| `ve_start_time` | Start time only | `"2:00 PM"` |
| `ve_end_date` | End date only | `"January 15, 2025"` |
| `ve_end_time` | End time only | `"5:00 PM"` |
| `ve_date_range` | Date range (smart) | `"January 15, 2025"` or `"January 15 – 17, 2025"` |
| `ve_time_range` | Time range | `"2:00 PM – 5:00 PM"` |
| `ve_datetime_formatted` | Full date + time | `"January 15, 2025, 2:00 PM – 5:00 PM"` |

#### Status Fields

| Meta Key | Output | Example |
|----------|--------|---------|
| `ve_status` | Event status text | `"Upcoming"`, `"Ongoing"`, or `"Past"` |
| `ve_is_upcoming` | Boolean check | `true` / `false` |
| `ve_is_ongoing` | Boolean check | `true` / `false` |

#### Detail Fields

| Meta Key | Output | Example |
|----------|--------|---------|
| `_vev_speaker` | Speaker name | `"Dr. Jane Smith"` |
| `_vev_special_info` | Additional info | `"Free parking available"` |
| `_vev_info_url` | Info URL | `"https://example.com/event"` |

---

## Usage Examples

### JetEngine Dynamic Field Widget

In JetEngine Listing Grid → Dynamic Field widget:

1. Source: **Post**
2. Object Field: Select from **VE Events** group
3. Choose field like `ve_datetime_formatted` or `ve_time_range`

### Elementor Pro Dynamic Tags

In any text widget or field that supports Dynamic Tags:

1. Click the Dynamic Tags icon
2. Select **VE Events: Event Field**
3. Choose from available fields

### PHP Usage

```php
// Get formatted start date
$start_date = get_post_meta( $post_id, 've_start_date', true );
// Output: "January 15, 2025"

// Get time range
$time_range = get_post_meta( $post_id, 've_time_range', true );
// Output: "2:00 PM – 5:00 PM"

// Get full formatted date/time
$datetime = get_post_meta( $post_id, 've_datetime_formatted', true );
// Output: "January 15, 2025, 2:00 PM – 5:00 PM"

// Get event status
$status = get_post_meta( $post_id, 've_status', true );
// Output: "Upcoming", "Ongoing", or "Past"

// Check if upcoming (boolean)
$is_upcoming = get_post_meta( $post_id, 've_is_upcoming', true );
// Output: true or false
```

### REST API

All meta keys are exposed via WordPress REST API:

```
GET /wp-json/wp/v2/ve_event/123
```

Response includes:
```json
{
  "meta": {
    "_vev_start_utc": 1735689600,
    "_vev_end_utc": 1735693200,
    "ve_start_date": "January 15, 2025",
    "ve_time_range": "2:00 PM – 5:00 PM",
    "ve_status": "Upcoming"
  }
}
```

---

## Default Frontend Behavior

- Sort event queries by `_vev_start_utc` ascending
- Hide events after grace period (configurable in settings)

### Query Variable

Use `vev_event_scope` to filter events:

```php
$args = array(
    'post_type' => 've_event',
    'vev_event_scope' => 'upcoming', // upcoming|ongoing|past|archived|all
);
```

---

## Schema.org

On single event pages, the plugin outputs an `Event` JSON-LD block with:
- Event name, description, image
- Start/end dates (ISO 8601)
- Location (from taxonomy)
- Organizer info

---

## Settings

Navigate to **Events → Settings** for:

- **Editor**: Disable Gutenberg for events
- **Display**: Hide end date if same as start
- **Visibility**: Grace period, hide archived from search
- **Schema**: Include series in Schema.org markup
- **Slugs**: Customize URL slugs for post type and taxonomies

---

## Debugging

- Enable via `WP_DEBUG` or `define('VEV_DEBUG', true);`
- Log file: `wp-content/uploads/ve-events.log`

---

## Auto-Updates

The plugin checks GitHub for new releases and shows updates in WordPress Dashboard.

**Repository**: https://github.com/hau-git/ve-events-wordpress-plugin
