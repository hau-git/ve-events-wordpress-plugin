# Changelog

All notable changes to the VE Events plugin are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [2.4.0] - 2026-07-10

Sustainability & hardening release — no new user-facing features, but the
plugin is safer, faster, more accessible, and easier to maintain.

### Security
- The ChurchDesk partner token now renders as a masked password field and is
  never echoed back into the admin form; an empty submit keeps the stored
  token. (Deliberately not encrypted at rest — WordPress has no key-management
  story, so encryption would not protect against a database-level attacker.)
- Remote feed fetches use `wp_safe_remote_get()` (rejects loopback/private
  hosts): the admin-supplied ICS URL in the vendored parser and the ChurchDesk
  API client (defense-in-depth).

### Performance
- The import log table is now pruned daily (`Logger::prune()` was implemented
  but never scheduled — it grew without bound on 15/30-minute feed schedules).
- `AbstractRunner::delete_removed()` primes the post meta cache once instead
  of issuing one query per tracked post.

### Accessibility
- The admin calendar is keyboard-operable: day cells are focusable buttons
  (Enter/Space opens quick-create), popovers are proper modal dialogs
  (`aria-modal`, labelled, focus trap, focus restoration), and the popover's
  Edit link is the documented keyboard path for rescheduling.

### Changed
- Single sources of truth consolidated: the event-status allow-list and
  post-state labels derive from `EventStatus::OPTIONS`/`label()`; all status
  reads go through `EventStatus::for_post()`; Schema.org and iCal export use
  the field registry's cached location/series lookups; registry keys are
  `Constants::*`-driven throughout.
- REST fields are now derived from the field registry instead of a second
  hand-maintained list — newly exposed: `ve_price_formatted`, `ve_ical_url`,
  `ve_event_status_label`, `ve_event_status_color`, `ve_category_class`, and
  `ve_is_cancelled`/`ve_is_upcoming`/`ve_is_ongoing` (as JSON booleans).

### Developer
- New `tests/FieldMapperDateTest.php` locks the ICS date → UTC conversion
  (TZID, Z-suffix, DATE-only, floating times, malformed input); 92 tests total.
- `composer check` runs lint + tests in one command; the gettext regeneration
  workflow is documented in CLAUDE.md.
- German translation completed (492/492 — the ChurchDesk strings were
  previously untranslated); `.pot` regenerated.
- New `release.yml` workflow attaches a lean plugin zip (runtime files only)
  to GitHub releases, which the self-updater prefers over the tag archive.

## [2.3.0] - 2026-07-08

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

## [2.2.0] - 2026-06-24

### Fixed
- **Backend calendar view** rendered unstyled because `admin-calendar.css` was gated on a
  hook suffix that doesn't fire on `edit.php?post_type=ve_event&page=vev-calendar`. It is now
  enqueued based on the `page` request parameter.
- **ChurchDesk calendar-view import returned only one event.** The endpoint wraps results in
  `{ count, items, totalCount }`; the parser now reads `items` and remaps the calendar-view
  field names (`eventCategories`, `imageObj.styles`, `locationObj.string`) onto the canonical
  shape, so categories (with colour), the composed address, and the event image import correctly.

### Added
- **Cross-feed merge** (per-feed opt-in, both feed types): when the same event arrives from
  more than one feed (e.g. the ChurchDesk iCal export *and* the ChurchDesk API of the same
  organization), it is matched on the ChurchDesk event id (with a start-time + title fallback)
  and the existing event is **enriched** — above all the featured image — instead of being
  duplicated. Enrichment is additive and non-destructive (never overwrites existing values,
  title, content or dates) and order-independent.
- Image format is now a free-text field with suggestions including `span6_16-9` (calendar-view)
  and `span7_16-9` (Pull API); the default follows the selected endpoint.

## [2.1.0] - 2026-06-20

### Added
- **ChurchDesk import** as a second feed source alongside iCal/ICS. A feed's
  **Source Type** can now be *iCal / ICS URL* or *ChurchDesk*. ChurchDesk feeds
  support two endpoints, selectable per feed:
  - **Pull API** — the documented, versioned API (`https://api.churchdesk.com/v3.0.0/events`),
    authenticated with an `organizationId` and a `partnerToken` (obtained from
    ChurchDesk support).
  - **Calendar View** — the public portal endpoint
    (`https://api2.churchdesk.com/collaboration/calendar-view`), authenticated
    with an `organizationId` only.
- ChurchDesk events are matched to `ve_event` fields: title, description,
  summary → excerpt, start/end (UTC), all-day, `showEndtime` → hide-end,
  contributor → speaker, price → special info, categories (with colour) →
  Category taxonomy, location (name + address) → Location taxonomy, and the
  event image → featured image (optional, via media sideload).
- Optional category-id filter and image-format selection per ChurchDesk feed.
- `ChurchDeskMapperTest` unit tests covering the field mapping (ISO→UTC,
  show-end inversion, category colour, address composition, change hash, …).

### Changed
- Refactored the import engine: the source-agnostic create/update/delete/match/
  log logic now lives in `VEV\Import\AbstractRunner`; `VEV\Import\IcsRunner`
  (iCal) and `VEV\Import\ChurchDeskRunner` extend it. `VEV\Import\Runner` is
  retained as a backward-compatible alias of `IcsRunner`. The iCal import is
  behaviourally unchanged.

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
