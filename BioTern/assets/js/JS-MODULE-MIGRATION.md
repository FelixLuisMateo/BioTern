# BioTern JS Module Migration

This project now uses domain-based canonical JavaScript paths under:

- `assets/js/modules/apps/*`
- `assets/js/modules/auth/*`
- `assets/js/modules/documents/*`
- `assets/js/modules/management/*`
- `assets/js/modules/pages/*`
- `assets/js/modules/reports/*`
- `assets/js/modules/shared/*` (cross-domain reusable classes/utilities)

Shared runtime cores currently include:

- `assets/js/modules/shared/ui-state-core.js` (safe storage + DOM-ready helpers)
- `assets/js/modules/shared/navigation-core.js` (route + navigation helpers)
- `assets/js/modules/shared/header-search-runtime.js` (single header search implementation)
- `assets/js/modules/shared/common-init-guards.js` (disables duplicate legacy header-search blocks in `common-init.min.js`)
- `assets/js/modules/shared/theme-state-core.js` (theme preference normalization + root class application)
- `assets/js/modules/shared/runtime-boot.js` (shared safe boot/on-ready helper for init wrappers)

Root init wrappers now consistently consume `BioTernRuntimeBoot` when available:

- `assets/js/global-ui-helpers.js`
- `assets/js/customers-init.min.js`
- `assets/js/navigation-state.js`
- `assets/js/mobile-bottom-nav.js`
- `assets/js/theme-customizer-init.min.js`

## Current loading pattern

- Global app scripts remain in `assets/js/` and are loaded from `includes/footer.php`.
- Page/domain scripts are loaded via `$page_scripts` or page-level `<script>` tags and now point to `assets/js/modules/<domain>/...`.

## Canonical path rule

- Add or edit page/domain scripts only in `assets/js/modules/<domain>/`.
- Put reusable logic in class-style modules under `assets/js/modules/shared/` and consume it from page/domain scripts.
- Legacy domain folders (`assets/js/apps`, `assets/js/auth`, `assets/js/documents`, `assets/js/management`, `assets/js/pages`, `assets/js/reports`) are retired.

## Next steps

1. Convert remaining hardcoded domain `<script>` tags to `$page_scripts` where practical.
2. Continue moving duplicated logic out of `common-init.min.js` into shared modules.
3. Introduce lightweight JS linting/format checks for module folders.
