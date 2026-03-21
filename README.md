# VE Events

A lightweight WordPress Events plugin with native admin UI, Schema.org markup, Open Graph tags, iCal import, and first-class support for Elementor and JetEngine.

**Version:** 1.9.0 · **Requires:** WordPress 6.4+, PHP 8.0+

---

## Table of Contents

- [Features](#features)
- [Post Type & Taxonomies](#post-type--taxonomies)
- [Stored Meta Keys](#stored-meta-keys)
- [Virtual Meta Keys](#virtual-meta-keys)
- [Query Variables](#query-variables)
- [Settings](#settings)
- [Admin Interface](#admin-interface)
- [iCal Import](#ical-import)
- [Schema.org & SEO](#schemaorg--seo)
- [Elementor Dynamic Tags](#elementor-dynamic-tags)
- [JetEngine Integration](#jetengine-integration)
- [REST API](#rest-api)
- [PHP Usage Examples](#php-usage-examples)
- [Auto-Updates](#auto-updates)
- [Debugging](#debugging)

---

## Features

- Custom post type `ve_event` with 4 taxonomies (Category, Location, Topic, Series)
- Date & Time form rendered directly below the post title (above the content editor)
- Live date/time preview while editing
- All-day events, hide-end-time flag, event status (Cancelled, Postponed, …)
- 20+ virtual meta keys for formatted output — usable in JetEngine, Elementor, PHP, REST
- Frontend scope filtering: upcoming / ongoing / past / archived
- Advanced query filters: date range, month, time-of-day, weekday
- Schema.org `Event` JSON-LD with full `eventStatus`, location, performer, offers, EventSeries
- Open Graph / Twitter Card meta tags (auto-detects conflicting SEO plugins)
- Category color system — CSS custom properties output in `wp_head`
- iCal/ICS feed importer with cron scheduling and admin log viewer
- 7 Elementor dynamic tag types
- Backend calendar grid view (monthly, color-coded by category)
- Backend list view: month grouping, filter bar, sortable "When" column
- German translation included (`.po` / `.mo`)
- Auto-updates from GitHub releases

---

## Post Type & Taxonomies

**Post type:** `ve_event`
- Public, REST-enabled (`rest_base: events`)
- Supports: title, editor, excerpt, featured image, revisions
- Archive slug: configurable (default: `events`)
- Single slug: configurable (default: `event`)

| Taxonomy | Key | Type | Term Meta |
|----------|-----|------|-----------|
| Categories | `ve_event_category` | Hierarchical | `ve_category_color` (hex) |
| Locations | `ve_event_location` | Flat | `ve_location_address`, `ve_location_maps_url` |
| Topics | `ve_event_topic` | Flat | — |
| Series | `ve_event_series` | Hierarchical | — |

---

## Stored Meta Keys

All timestamps are UTC Unix integers. Private keys (underscore prefix) are stored in `wp_postmeta`.

| Meta Key | Type | Description |
|----------|------|-------------|
| `_vev_start_utc` | int | Start timestamp (UTC) |
| `_vev_end_utc` | int | End timestamp (UTC) |
| `_vev_all_day` | bool | All-day event flag |
| `_vev_hide_end` | bool | Hide end time in frontend listings |
| `_vev_speaker` | string | Speaker / host name |
| `_vev_special_info` | string | Additional notes (admission, dress code, …) |
| `_vev_info_url` | string | Info or ticket URL |
| `_vev_event_status` | string | Status override: `cancelled` · `postponed` · `rescheduled` · `movedOnline` · `""` |
| `_vev_start_hour` | int | Auto-synced: start hour in site timezone (0–23) |
| `_vev_weekday` | int | Auto-synced: ISO weekday (1=Mon … 7=Sun) |

`_vev_start_hour` and `_vev_weekday` are automatically written whenever `_vev_start_utc` changes.

---

## Virtual Meta Keys

Computed at runtime — not stored in the database. Available in JetEngine field queries, Elementor dynamic tags, PHP `get_post_meta()`, and the REST API.

### Date & Time

| Key | Description | Example |
|-----|-------------|---------|
| `ve_start_date` | Formatted start date | `14.06.2025` |
| `ve_start_time` | Formatted start time | `19:00` |
| `ve_end_date` | Formatted end date | `14.06.2025` |
| `ve_end_time` | Formatted end time | `22:00` |
| `ve_date_range` | Smart date range | `14.06.2025` / `14.–16.06.2025` |
| `ve_time_range` | Time range or "All day" | `19:00 – 22:00` |
| `ve_datetime_formatted` | Full date + time | `14.06.2025, 19:00 – 22:00` |

### Status

| Key | Description | Example |
|-----|-------------|---------|
| `ve_status` | Timeline status | `Upcoming` / `Ongoing` / `Past` / `Archived` |
| `ve_is_upcoming` | Boolean | `true` / `false` |
| `ve_is_ongoing` | Boolean | `true` / `false` |
| `ve_is_cancelled` | Boolean | `true` / `false` |
| `ve_event_status_label` | Human-readable override status | `Cancelled` / `Postponed` / … |
| `ve_event_status_color` | Hex color for status badge | `#d63638` |

### Taxonomy

| Key | Description |
|-----|-------------|
| `ve_location_name` | First assigned location term name |
| `ve_location_address` | Location address (term meta) |
| `ve_location_maps_url` | Maps URL — custom or auto-generated from address |
| `ve_category_name` | First assigned category term name |
| `ve_category_color` | Category hex color (term meta) |
| `ve_category_class` | CSS class name, e.g. `ve-cat-workshop` |
| `ve_series_name` | First assigned series term name |
| `ve_topic_names` | Comma-separated topic names |

---

## Query Variables

Use these in `WP_Query` args or as URL parameters on frontend archive/listing pages.

| Variable | Values | Description |
|----------|--------|-------------|
| `vev_event_scope` | `upcoming` · `ongoing` · `past` · `archived` · `all` | Filter by event timeline state |
| `vev_include_archived` | `0` · `1` | Include archived events in results |
| `vev_date_from` | `Y-m-d` or UTC timestamp | Events starting on or after this date |
| `vev_date_to` | `Y-m-d` or UTC timestamp | Events starting on or before this date |
| `vev_month` | `YYYY-MM` | Events in a specific month |
| `vev_time_from` | `0`–`23` | Events starting at or after this hour |
| `vev_time_to` | `0`–`23` | Events starting at or before this hour |
| `vev_weekday` | `1`–`7` or `"1,3,5"` | Events on specific ISO weekday(s) |

**Scope definitions:**
- `upcoming` — start > now
- `ongoing` — start ≤ now AND end ≥ now
- `past` — end < now but within grace period
- `archived` — end older than grace period
- `all` — no timeline filter

```php
// Show only upcoming events
$query = new WP_Query( [
    'post_type'       => 've_event',
    'vev_event_scope' => 'upcoming',
    'posts_per_page'  => 10,
] );

// Events on Friday evenings in June 2025
$query = new WP_Query( [
    'post_type'       => 've_event',
    'vev_month'       => '2025-06',
    'vev_weekday'     => '5',
    'vev_time_from'   => 18,
] );
```

---

## Settings

**Events → Settings** in the WordPress admin.

| Setting | Default | Description |
|---------|---------|-------------|
| `slug_single` | `event` | URL slug for single event pages |
| `slug_archive` | `events` | URL slug for the event archive |
| `disable_gutenberg` | `false` | Use Classic Editor instead of Gutenberg |
| `hide_end_same_day` | `true` | Hide end date when start and end are the same day |
| `grace_period` | `1` | Days to keep showing events after they end |
| `hide_archived_search` | `true` | Exclude archived events from WordPress search |
| `include_series_schema` | `true` | Add `EventSeries` superEvent to Schema.org output |
| `output_category_colors` | `true` | Output category color CSS variables in `wp_head` |
| `og_tags` | `auto` | Open Graph output: `auto` · `always` · `disabled` |
| `series_suggestions` | `false` | Show series auto-suggestion UI when editing events |

---

## Admin Interface

### Event Edit Page

The event form is rendered **directly below the post title**, before the content editor, so date and time are always the first thing you fill in.

**Date & Time** (always visible):
- Start date + time, End date + time
- All-day checkbox (disables time inputs)
- Hide end time checkbox
- Live preview: updates in real-time as you type

**Details** (WP metabox — below content editor by default, draggable):
- Speaker / Host
- Info / Ticket URL
- Additional Notes

**Event Status** (sidebar metabox — below Publish):
- Scheduled (default) · Cancelled · Postponed · Rescheduled · Moved Online
- Updates Schema.org `eventStatus` and shows a badge in the event list

### Event List

- **Views:** Upcoming · Past · All · Drafts · Calendar
- **Columns:** Title · When · Category · Location · Topic
- **Month grouping:** Visual separator rows between months
- **Filter bar:** Month · Category · Location · Topic dropdowns
- **Sortable:** "When" column sorts by start date

### Calendar Grid View

Accessible via the **Calendar** tab in the event list.

- Monthly grid (week starts Monday)
- Events shown as colored pills (category color)
- Click any event to open its edit page
- Previous / Next month navigation
- "Add Event" button with pre-filled date

### Taxonomy Forms

**Category edit page:** Color picker field — stored as hex, output as CSS custom property.

**Location edit page:** Address and Google Maps URL fields. Maps URL is auto-generated from the address if left empty.

---

## iCal Import

**Events → Import Feeds** — import events from any iCal/ICS feed (Google Calendar, Outlook, etc.).

- Add a feed URL and configure the import schedule
- Supported intervals: every 15 min · every 30 min · daily · weekly
- Field mapping: map iCal properties (SUMMARY, DTSTART, LOCATION, …) to event fields
- **Run Now** button for manual import
- Import log viewer with per-run success/error details
- Cron is automatically scheduled on feed publish and cleared on trash/delete

---

## Schema.org & SEO

### Schema.org JSON-LD

Output on single event pages (`<script type="application/ld+json">`):

```json
{
  "@context": "https://schema.org",
  "@type": "Event",
  "name": "Event Title",
  "startDate": "2025-06-14T19:00:00+02:00",
  "endDate": "2025-06-14T22:00:00+02:00",
  "eventStatus": "https://schema.org/EventScheduled",
  "location": {
    "@type": "Place",
    "name": "Location Name",
    "address": { "@type": "PostalAddress", "streetAddress": "…" }
  },
  "performer": { "@type": "Person", "name": "Speaker Name" },
  "offers": { "@type": "Offer", "url": "https://tickets.example.com" },
  "superEvent": { "@type": "EventSeries", "name": "Series Name", "url": "…" }
}
```

`eventStatus` values map automatically from `_vev_event_status`:

| Plugin value | Schema.org URL |
|---|---|
| *(empty)* | `EventScheduled` |
| `cancelled` | `EventCancelled` |
| `postponed` | `EventPostponed` |
| `rescheduled` | `EventRescheduled` |
| `movedOnline` | `EventMovedOnline` |

Filter the output: `add_filter( 've_schema_event', function( $schema ) { … } );`

### Open Graph & Twitter Card

Controlled by the `og_tags` setting:
- `auto` — outputs tags unless Yoast SEO, Rank Math, AIOSEO, or The SEO Framework is active
- `always` — always outputs tags
- `disabled` — never outputs tags

Outputs: `og:type`, `og:title`, `og:url`, `og:description`, `og:image`, `og:start_time`, `og:end_time`, Twitter Card tags.

### Category Color CSS

When `output_category_colors` is enabled, outputs in `wp_head`:

```css
:root {
  --vev-cat-workshop: #e84040;
  --vev-cat-concert: #2271b1;
}
.ve-cat-workshop { --vev-cat-color: #e84040; }
.ve-cat-concert  { --vev-cat-color: #2271b1; }
```

---

## Elementor Dynamic Tags

All tags are available under the **VE Events** group in the Elementor dynamic tags panel.

| Tag | Description |
|-----|-------------|
| Event Field | Any virtual or stored field (dropdown selector) |
| Event URL | Single event permalink |
| Location | Location name or full address |
| Location URL | Google Maps URL (custom or auto-generated) |
| Category | First assigned category name |
| Series | Series name and/or link |
| Topic | Topic name(s), comma-separated |

---

## JetEngine Integration

All virtual fields are available as **JetEngine dynamic fields**:

1. Add a **Dynamic Field** widget
2. Source: **Post**
3. Object Field: select from the **VE Events** group

All `ve_*` keys listed in [Virtual Meta Keys](#virtual-meta-keys) are available.

---

## REST API

Virtual fields are registered on the `ve_event` REST endpoint:

```
GET /wp-json/wp/v2/events/123
```

Response includes:

```json
{
  "ve_start_date": "14.06.2025",
  "ve_start_time": "19:00",
  "ve_end_date": "14.06.2025",
  "ve_end_time": "22:00",
  "ve_date_range": "14.06.2025",
  "ve_time_range": "19:00 – 22:00",
  "ve_datetime_formatted": "14.06.2025, 19:00 – 22:00",
  "ve_status": "Upcoming",
  "ve_location_name": "Gemeindehaus",
  "ve_location_address": "Musterstraße 1, 28213 Bremen",
  "ve_location_maps_url": "https://maps.google.com/?q=…",
  "ve_category_name": "Gottesdienst",
  "ve_category_color": "#e84040",
  "ve_series_name": "Sonntagsgottesdienste",
  "ve_topic_names": "Musik, Familie"
}
```

---

## PHP Usage Examples

```php
// Formatted date range
$range = get_post_meta( $post->ID, 've_date_range', true );
// → "14.06.2025" (single day)
// → "14.–16.06.2025" (multi-day)

// Time range
$time = get_post_meta( $post->ID, 've_time_range', true );
// → "19:00 – 22:00"
// → "All day"

// Full formatted output
$full = get_post_meta( $post->ID, 've_datetime_formatted', true );
// → "14.06.2025, 19:00 – 22:00"

// Status check
$is_upcoming = get_post_meta( $post->ID, 've_is_upcoming', true );

// Event status label + color
$label = get_post_meta( $post->ID, 've_event_status_label', true ); // → "Cancelled"
$color = get_post_meta( $post->ID, 've_event_status_color', true ); // → "#d63638"

// Location with maps link
$address = get_post_meta( $post->ID, 've_location_address', true );
$map_url = get_post_meta( $post->ID, 've_location_maps_url', true );

// Upcoming events query
$events = new WP_Query( [
    'post_type'       => 've_event',
    'vev_event_scope' => 'upcoming',
    'posts_per_page'  => 5,
] );

// Filter hook for Schema.org output
add_filter( 've_schema_event', function( array $schema ): array {
    $schema['organizer'] = [ '@type' => 'Organization', 'name' => 'My Church' ];
    return $schema;
} );
```

---

## Auto-Updates

The plugin checks GitHub for new releases and shows an update notification in the WordPress admin when a new version is available.

Repository: [github.com/hau-git/ve-events-wordpress-plugin](https://github.com/hau-git/ve-events-wordpress-plugin)

---

## Debugging

```php
// In wp-config.php
define( 'VEV_DEBUG', true );
```

Log file: `wp-content/uploads/ve-events.log`

Each log entry includes a timestamp and message. The file is appended to, not overwritten.
