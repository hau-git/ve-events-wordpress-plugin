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
| `_vev_*` | Stored | Database fields (underscore = private) |
| `ve_*` | Virtual | Computed/formatted output |

---

### Stored Meta Keys (Database)

These are stored in the database. All timestamps are UTC Unix timestamps.

| Meta Key | Type | Description | Example |
|----------|------|-------------|---------|
| `_vev_start_utc` | int | Start timestamp (UTC) | `1736935200` |
| `_vev_end_utc` | int | End timestamp (UTC) | `1736946000` |
| `_vev_all_day` | bool | All-day event flag | `1` or `0` |
| `_vev_hide_end` | bool | Hide end time | `1` or `0` |
| `_vev_speaker` | string | Speaker name | `"Dr. Jane Smith"` |
| `_vev_special_info` | string | Additional info | `"Free parking"` |
| `_vev_info_url` | string | External URL | `"https://example.com"` |

---

### Virtual Meta Keys (Computed Output)

These are computed at runtime. Use in JetEngine, Elementor, or PHP.

#### Date Only

| Meta Key | Description | Example Output |
|----------|-------------|----------------|
| `ve_start_date` | Start date | `"January 15, 2025"` |
| `ve_end_date` | End date | `"January 17, 2025"` |
| `ve_date_range` | Smart date range | `"January 15 – 17, 2025"` |

#### Time Only

| Meta Key | Description | Example Output |
|----------|-------------|----------------|
| `ve_start_time` | Start time | `"2:00 PM"` |
| `ve_end_time` | End time | `"5:00 PM"` |
| `ve_time_range` | Time range | `"2:00 PM – 5:00 PM"` or `"All day"` |

#### Date + Time Combined

| Meta Key | Description | Example Output |
|----------|-------------|----------------|
| `ve_datetime_formatted` | Full date & time | `"January 15, 2025, 2:00 PM – 5:00 PM"` |

#### Status

| Meta Key | Description | Example Output |
|----------|-------------|----------------|
| `ve_status` | Status text | `"Upcoming"` / `"Ongoing"` / `"Past"` |
| `ve_is_upcoming` | Boolean | `true` / `false` |
| `ve_is_ongoing` | Boolean | `true` / `false` |

---

## Usage Examples

### JetEngine Dynamic Field

1. Add Dynamic Field widget
2. Source: **Post**
3. Object Field: Select from **VE Events** group (e.g., `ve_datetime_formatted`)

### Elementor Pro Dynamic Tags

1. Click Dynamic Tags icon in any text widget
2. Select **VE Events: Event Field**
3. Choose field

### PHP Usage

```php
// Date only
$date = get_post_meta( $post_id, 've_start_date', true );
// => "January 15, 2025"

// Time only
$time = get_post_meta( $post_id, 've_start_time', true );
// => "2:00 PM"

// Date range (smart)
$range = get_post_meta( $post_id, 've_date_range', true );
// => "January 15, 2025" (same day)
// => "January 15 – 17, 2025" (multi-day)

// Time range
$times = get_post_meta( $post_id, 've_time_range', true );
// => "2:00 PM – 5:00 PM"
// => "All day" (all-day event)

// Full date + time
$full = get_post_meta( $post_id, 've_datetime_formatted', true );
// => "January 15, 2025, 2:00 PM – 5:00 PM"

// Status
$status = get_post_meta( $post_id, 've_status', true );
// => "Upcoming", "Ongoing", or "Past"
```

### REST API

```
GET /wp-json/wp/v2/ve_event/123
```

```json
{
  "ve_start_date": "January 15, 2025",
  "ve_start_time": "2:00 PM",
  "ve_date_range": "January 15, 2025",
  "ve_time_range": "2:00 PM – 5:00 PM",
  "ve_datetime_formatted": "January 15, 2025, 2:00 PM – 5:00 PM",
  "ve_status": "Upcoming"
}
```

---

## Query Variables

```php
$args = array(
    'post_type' => 've_event',
    'vev_event_scope' => 'upcoming', // upcoming|ongoing|past|archived|all
);
```

---

## Schema.org

Outputs `Event` JSON-LD on single event pages with name, dates, location, performer.

---

## Settings

**Events -> Settings** for URL slugs, Gutenberg toggle, display options, visibility.

---

## Debugging

```php
define( 'VEV_DEBUG', true );
```

Log: `wp-content/uploads/ve-events.log`

---

## Auto-Updates

Checks GitHub for releases: https://github.com/hau-git/ve-events-wordpress-plugin
