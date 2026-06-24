# Changelog

All notable changes to the VE Events plugin are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/),
and this project adheres to [Semantic Versioning](https://semver.org/).

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
