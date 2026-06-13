# ICal (vendored)

This directory contains a vendored copy of the **`johngrogg/ics-parser`** library
(also known as `u01jmg3/ics-parser`), version **3.2.0**, released under the
**MIT License** (<https://opensource.org/licenses/mit-license.php>).

Upstream: <https://github.com/u01jmg3/ics-parser>

## Local modifications

This copy is **vendored and locally modified**. Do not replace it with a pristine
upstream copy without re-applying the changes below:

- Namespace changed from `ICal` to `VEV_Import` (so it does not collide with other
  plugins bundling the same parser).
- `initUrl()` fetches remote calendars via the WordPress HTTP API
  (`wp_remote_get()`) instead of cURL, so it respects WordPress proxy/SSL settings.
- An `ABSPATH` guard was added to each file.

## Autoloading

These files keep the `VEV_Import` namespace (not the plugin's PSR-4 root `VEV\`),
so the PSR-4 autoloader does **not** load them. They are pulled in via explicit
`require_once` statements in `src/Import/Manager.php`.

## Formatting

**Do not auto-format these files** (no `phpcbf`/`phpcs` fixes). They intentionally
follow upstream coding style and are excluded from the project ruleset so that
future upstream merges stay clean.
