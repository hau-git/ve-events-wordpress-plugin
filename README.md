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

### Naming Convention

| Prefix | Type | Description |
|--------|------|-------------|
| `_vev_*` | Stored | Database fields (underscore = private meta) |
| `ve_*` | Virtual | Computed/formatted output (primary) |
| `vev_*` | Legacy | Old virtual keys (backward compatibility) |

---

### Stored Meta Keys (Database)

These are stored in the database. All timestamps are UTC Unix timestamps (integers).

| Meta Key | Type | Description | Example |
|----------|------|-------------|---------|
| `_vev_start_utc` | int | Start timestamp (UTC Unix) | `1736935200` |
| `_vev_end_utc` | int | End timestamp (UTC Unix) | `1736946000` |
| `_vev_all_day` | bool | All-day event flag | `1` or `0` |
| `_vev_hide_end` | bool | Hide end time in display | `1` or `0` |
| `_vev_speaker` | string | Speaker/Presenter name | `"Dr. Jane Smith"` |
| `_vev_special_info` | string | Additional info text | `"Free parking"` |
| `_vev_info_url` | string | External info URL | `"https://example.com"` |

---

### Virtual Meta Keys (Primary - `ve_*`)

These are computed at runtime. Use these in JetEngine, Elementor, or PHP templates.

#### Date Only

| Meta Key | Description | Example Output |
|----------|-------------|----------------|
| `ve_start_date` | Start date (localized) | `"January 15, 2025"` |
| `ve_end_date` | End date (localized) | `"January 17, 2025"` |
| `ve_date_range` | Smart date range | `"January 15 – 17, 2025"` |

#### Time Only

| Meta Key | Description | Example Output |
|----------|-------------|----------------|
| `ve_start_time` | Start time only | `"2:00 PM"` |
| `ve_end_time` | End time only | `"5:00 PM"` |
| `ve_time_range` | Time range or "All day" | `"2:00 PM – 5:00 PM"` |

#### Date + Time Combined

| Meta Key | Description | Example Output |
|----------|-------------|----------------|
| `ve_datetime_formatted` | Full date & time | `"January 15, 2025, 2:00 PM – 5:00 PM"` |

#### Status

| Meta Key | Description | Example Output |
|----------|-------------|----------------|
| `ve_status` | Localized status text | `"Upcoming"` / `"Ongoing"` / `"Past"` |
| `ve_is_upcoming` | Boolean check | `true` / `false` |
| `ve_is_ongoing` | Boolean check | `true` / `false` |

---

### Legacy Meta Keys (`vev_*`)

These are kept for backward compatibility. New projects should use `ve_*` keys.

| Meta Key | Alias For | Description |
|----------|-----------|-------------|
| `vev_status` | `ve_status` | Status label |
| `vev_timerange` | `ve_datetime_formatted` | Full date/time range |
| `vev_start_local` | - | Start date+time combined |
| `vev_end_local` | - | End date+time combined |

---

## Usage Examples

### JetEngine Dynamic Field

In JetEngine Listing Grid -> Dynamic Field widget:
1. Source: **Post**
2. Object Field: Select from **VE Events** group
3. Choose a field like `ve_datetime_formatted`

### Elementor Pro Dynamic Tags

In any text widget:
1. Click the Dynamic Tags icon
2. Select **VE Events: Event Field**
3. Choose from available fields

### PHP Usage

```php
// Date only
$start_date = get_post_meta( $post_id, 've_start_date', true );
// => "January 15, 2025"

// Time only
$start_time = get_post_meta( $post_id, 've_start_time', true );
// => "2:00 PM"

// Date + Time combined
$datetime = get_post_meta( $post_id, 've_datetime_formatted', true );
// => "January 15, 2025, 2:00 PM – 5:00 PM"

// Date range (smart - same day vs multi-day)
$date_range = get_post_meta( $post_id, 've_date_range', true );
// => "January 15, 2025" (same day)
// => "January 15 – 17, 2025" (multi-day)

// Time range
$time_range = get_post_meta( $post_id, 've_time_range', true );
// => "2:00 PM – 5:00 PM"
// => "All day" (if all-day event)

// Status
$status = get_post_meta( $post_id, 've_status', true );
// => "Upcoming", "Ongoing", or "Past"

// Check status (boolean)
$is_upcoming = get_post_meta( $post_id, 've_is_upcoming', true );
// => true or false
```

### REST API

```
GET /wp-json/wp/v2/ve_event/123
```

Response:
```json
{
  "meta": {
    "_vev_start_utc": 1736935200,
    "_vev_end_utc": 1736946000,
    "ve_start_date": "January 15, 2025",
    "ve_start_time": "2:00 PM",
    "ve_date_range": "January 15, 2025",
    "ve_time_range": "2:00 PM – 5:00 PM",
    "ve_datetime_formatted": "January 15, 2025, 2:00 PM – 5:00 PM",
    "ve_status": "Upcoming"
  }
}
```

---

## Query Variables

| Variable | Values | Description |
|----------|--------|-------------|
| `vev_event_scope` | `upcoming`, `ongoing`, `past`, `archived`, `all` | Filter events by status |

```php
$args = array(
    'post_type' => 've_event',
    'vev_event_scope' => 'upcoming',
);
$query = new WP_Query( $args );
```

---

## Schema.org

On single event pages, the plugin outputs an `Event` JSON-LD block with:
- Event name, description, image
- Start/end dates (ISO 8601)
- Location, Performer, Offers

---

## Settings

Navigate to **Events -> Settings** for:
- URL slugs customization
- Gutenberg editor toggle
- Display options (hide end date same day)
- Visibility settings (grace period)
- Schema.org options

---

## Debugging

```php
define( 'VEV_DEBUG', true );
```

Log file: `wp-content/uploads/ve-events.log`

---

## Auto-Updates

The plugin checks GitHub for new releases.

**Repository**: https://github.com/hau-git/ve-events-wordpress-plugin
