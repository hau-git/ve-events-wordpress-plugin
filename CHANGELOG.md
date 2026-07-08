# Changelog

All notable changes to the VE Events plugin are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [2.1.0] - 2026-07-08

### Added
- **Interactive admin calendar.** Month navigation is now AJAX (no full page
  reload, with browser history support and a no-JS fallback). Clicking an event
  opens a popover with its time, location, category, status, and edit/view
  links. Clicking an empty day opens a quick-create popover (title + start/end
  time + all-day, save as draft or publish). Events can be **dragged and
  dropped** onto another day to reschedule them (DST-safe). Drafts now appear on
  the grid. New: `src/Admin/CalendarAjax.php`, `assets/js/admin-calendar.js`.
- **iCal export.** Every published event has an "Add to Calendar" `.ics`
  download, exposed as the new virtual field `ve_ical_url`. An optional,
  subscribable feed of upcoming events (`?vev_ics=feed`, `webcal://` supported,
  filterable by category via `&vev_ics_cat=slug`) can be enabled under
  Settings → Schema & SEO, with ETag/Last-Modified conditional-request caching.
  New: `src/Export/IcsBuilder.php` (pure RFC 5545), `src/Export/Endpoint.php`.
- **New event fields:** Organizer + Organizer URL, Price + Currency +
  Availability, and Attendance Mode (offline/online/mixed). All are stored meta,
  exposed via REST and the field registry.
- **Richer Schema.org output:** `organizer` (Organization), `eventAttendanceMode`
  (with `movedOnline` forcing Online and a `VirtualLocation` for online/mixed
  events), and enriched `offers` (`price`, `priceCurrency`, `availability`,
  `validFrom`). New virtual field `ve_price_formatted`.

### Changed
- The event list and calendar page now share a single `Admin\ViewsNav` helper
  for the Upcoming/Past/All/Drafts/Trash/Calendar navigation and its counts
  (previously duplicated); the calendar page now also shows a Trash view.
- Date/time → UTC parsing was extracted from the editor into the shared,
  unit-tested `Support\DateParser`, reused by calendar quick-create.
- The grace-period setting labels are now English source strings (German lives
  in the translation files).

### Performance
- Virtual taxonomy field callbacks (`ve_category_*`, `ve_location_*`,
  `ve_series_name`, `ve_topic_names`) now share a per-request term cache,
  collapsing repeated `get_the_terms()` calls on listings.

### Developer
- New DST-safe `Support\Reschedule` and `Support\AttendanceMode` single sources
  of truth, each with PHPUnit coverage; new `DateParser` and `IcsBuilder` tests.
- `CLAUDE.md` rewritten to match the current `src/` architecture.

## [2.0.0] - 2026-06-13

Internal re-architecture. **No functional changes** — every feature, post type
slug (`ve_event`), meta key, virtual field key, query var, REST field, Schema.org
/ Open Graph output, iCal import, and Elementor/JetEngine integration behaves
exactly as in 1.9.x.

### Changed
- Restructured the codebase into a namespaced `src/` tree (root namespace
  `VEV\`) loaded by a lightweight custom PSR-4 autoloader. The 8 manual
  `require_once` calls in the bootstrap are gone.
- Split the two monolithic classes (`class-admin.php` ~1977 lines,
  `class-frontend.php`) into cohesive single-responsibility classes under
  `src/Admin/`, `src/Frontend/`, `src/Query/`, `src/Integrations/`, `src/Import/`.
- Introduced single sources of truth that previously had duplicated logic:
  - `VEV\Support\EventStatus` — the cancelled/postponed/rescheduled/movedOnline
    map (label, color, Schema.org URI, badge class), previously repeated 4×.
  - `VEV\Support\DateFormatter` — all timezone-aware date/time formatting,
    previously reimplemented 6×.
  - `VEV\Support\Lifecycle` — the upcoming/ongoing/past/archived logic and the
    grace-period cutoff, previously duplicated 3×.
- Extracted all inline admin CSS/JS (~400+ lines that were embedded in PHP)
  into versioned, enqueued files under `assets/css/` and `assets/js/`.
- Relocated the vendored iCal parser to `src/ThirdParty/ICal/` with a README
  documenting its upstream (`johngrogg/ics-parser` v3.2.0, MIT) and the local
  WP-HTTP modification.
- Standardized indentation to tabs (WordPress coding standard) across the codebase.

### Added
- `composer.json` (dev-only tooling — never shipped at runtime), `phpcs.xml.dist`
  (WordPress coding standard), `phpunit.xml.dist`, a unit-test suite for the new
  single-source-of-truth helpers, and a GitHub Actions CI workflow (lint + tests
  on PHP 8.0–8.3).
- `.gitignore`; removed committed `.DS_Store` files.

### Compatibility
- The legacy global class names `VEV_Events`, `VEV_Fields`, `VEV_Frontend`, and
  `VEV_Post_Type` are retained as thin backward-compatibility shims
  (`src/Compat.php`) so existing theme snippets and integrations that reference
  `VEV_Events::META_*`, `VEV_Fields::get_field_value()`, etc. keep working.
  These shims are kept for the 2.x series.

### Fixed
- Reconciled the version number, which was inconsistent across the plugin header,
  the internal constant, and the README.
