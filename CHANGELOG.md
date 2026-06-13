# Changelog

All notable changes to the VE Events plugin are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/),
and this project adheres to [Semantic Versioning](https://semver.org/).

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
