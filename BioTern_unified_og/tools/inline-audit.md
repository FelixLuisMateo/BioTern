# Unified Inline Audit

Date: 2026-03-07
Scope: `BioTern_unified` application files (`*.php`, `*.blade.php`, `*.html`)
Excluded: `vendor/`, `docs/`

## Inventory Summary

- Files with `<style>` blocks: 46
- Files with inline `<script>` blocks (no `src`): 62
- Files with `style="..."` attributes: 58

## Directory Hotspots

- `pages/`: 152
- `widgets/`: 118
- `apps/`: 89
- `management/`: 67
- `documents/`: 50
- `reports/`: 48
- `auth/`: 23
- `includes/`: 15
- `settings/`: 15

## Shared Asset Map

Current reusable assets:

- CSS: `assets/css/theme.min.css`, `assets/css/homepage-dashboard.css`, `assets/css/settings-customizer-like.css`
- JS: `assets/js/common-init.min.js`, `assets/js/skin-init.js`, `assets/js/theme-preload-init.min.js`

Target migration assets:

- `assets/css/core.css`
- `assets/js/global-ui-helpers.js`
- `assets/js/theme-preferences-runtime.js`

Routing map:

- Shared layout rules/scripts from `includes/*` and root `index.php` -> `core.css`, `global-ui-helpers.js`, and `theme-preferences-runtime.js`
- High-traffic runtime pages (`pages/analytics.php`, `pages/attendance.php`, `pages/homepage.php`) -> `core.css`, `global-ui-helpers.js`, and `theme-preferences-runtime.js` with page-scoped sections
- Feature init/chart scripts remain in existing `assets/js/*-init.min.js` unless generalized
- Print/document rules remain isolated where strict print fidelity is needed

## Migration Batches

Batch 1: Shared layout

- `includes/header.php`
- `includes/footer.php`
- `includes/navigation.php`
- `index.php`

Batch 2: Core runtime pages

- `pages/analytics.php`
- `pages/attendance.php`
- `pages/homepage.php`
- `management/students.php`
- `management/students-view.php`
- `management/students-edit.php`
- `management/ojt.php`
- `management/ojt-view.php`

Batch 3: Document generators

- `documents/document_application.php`
- `documents/document_endorsement.php`
- `documents/document_moa.php`
- `documents/document_dau_moa.php`
- `pages/generate_moa.php`
- `pages/generate_dau_moa.php`
- `pages/generate_application_letter.php`
- `pages/generate_endorsement_letter.php`

Batch 4: Secondary modules

- `apps/*.php`
- `widgets/*.php`
- `settings/*.php`
- `reports/*.php`
- `public/help-knowledgebase.php`
- `api/register_fingerprint.php`

## Extraction Rules

- Move static CSS/JS out of templates into unified assets
- Keep truly dynamic inline values (example: progress `width: <?php ... ?>%`) until refactored safely
- Prefer reusable classes over one-off selectors
- Use page root classes for scoped page-specific rules

## Progress Log

- Completed: created `assets/css/core.css`, `assets/js/global-ui-helpers.js`, and `assets/js/theme-preferences-runtime.js`
- Completed: wired shared includes to core assets
- Completed: removed inline `<script>` and `<style>` blocks from shared includes
- Completed: moved shared footer runtime and year rendering into `assets/js/global-ui-helpers.js` and `assets/js/theme-preferences-runtime.js`
- Completed: extracted `pages/homepage.php` runtime into `assets/js/homepage-dashboard-runtime.js`
- Completed: extracted `management/students.php` runtime into `assets/js/students-page-runtime.js`
- Completed: extracted `management/students-view.php` runtime into `assets/js/students-view-runtime.js`
- Completed: extracted `management/students-edit.php` runtime into `assets/js/students-edit-runtime.js`
- Completed: extracted `management/ojt.php` runtime into `assets/js/ojt-dashboard-runtime.js`
- Completed: extracted `management/ojt-view.php` runtime into `assets/js/ojt-view-runtime.js`
- Completed: removed duplicated inline year footer scripts from 9 settings pages (`settings/settings-*.php`) by switching to server-rendered year markup
- Completed: extracted `pages/demo-biometric.php` inline `<style>` block into `assets/css/demo-biometric-page.css`
- Completed: extracted `pages/demo-biometric.php` inline runtime script into `assets/js/demo-biometric-runtime.js`
- Completed: extracted `public/help-knowledgebase.php` inline `<style>` block into `assets/css/help-knowledgebase-page.css`
- Completed: extracted `pages/theme-customizer.php` inline `<style>` block into `assets/css/theme-customizer-page.css`
- Completed: consolidated theme customizer page runtime into `assets/js/theme-preferences-runtime.js` and removed `assets/js/theme-customizer-page-runtime.js`
- Completed: extracted `pages/print_attendance.php` inline `<style>` block into `assets/css/print-attendance-page.css`
- Completed: extracted `pages/print_attendance.php` inline runtime script into `assets/js/print-attendance-runtime.js`
- Completed: removed inline print button `onclick` from `pages/print_attendance.php` and bound print behavior in `assets/js/print-attendance-runtime.js`
- Completed: removed repeated inline style attributes from `reports/reports-sales.php`, `reports/reports-project.php`, `reports/reports-timesheets.php`, and `reports/reports-ojt.php` by switching toast/progress/label styles to shared utility classes in `assets/css/core.css`
- Completed: removed repeated inline style attributes from `apps/apps-calendar.php`, `apps/apps-chat.php`, `apps/apps-email.php`, `apps/apps-notes.php`, `apps/apps-storage.php`, and `apps/apps-tasks.php` by switching toast/progress/label styles to shared utility classes in `assets/css/core.css`
- Completed: removed repeated inline style attributes from `widgets/widgets-tables.php`, `widgets/widgets-charts.php`, `widgets/widgets-miscellaneous.php`, `widgets/widgets-lists.php`, `widgets/widgets-statistics.php`, and `partials/customizer.html` by switching toast/progress/label styles to shared utility classes in `assets/css/core.css`
- Completed: extracted inline `<style>` and runtime `<script>` blocks from `pages/generate_endorsement_letter.php` into `assets/css/generate-endorsement-letter-page.css` and `assets/js/generate-endorsement-letter-runtime.js`
- Completed: extracted inline `<style>` and runtime `<script>` blocks from `pages/generate_application_letter.php` into `assets/css/generate-application-letter-page.css` and `assets/js/generate-application-letter-runtime.js`, and replaced inline button handlers with runtime listeners
- Completed: converted remaining inline `style` attributes in `pages/generate_application_letter.php` to page-scoped CSS classes in `assets/css/generate-application-letter-page.css`
- Completed: extracted inline `<style>` and runtime `<script>` blocks from `pages/generate_dau_moa.php` into `assets/css/generate-dau-moa-page.css` and `assets/js/generate-dau-moa-runtime.js`, and converted remaining inline `style` attributes to page-scoped CSS classes
- Completed: extracted inline `<style>` and runtime `<script>` blocks from `pages/generate_moa.php` into `assets/css/generate-moa-page.css` and `assets/js/generate-moa-runtime.js`, and converted remaining inline `style` attributes to page-scoped CSS classes
- Completed: extracted inline `<style>` and inline print handler from `pages/generate_resume.php` into `assets/css/generate-resume-page.css` and `assets/js/generate-resume-runtime.js`
- Completed: extracted inline `<style>` and inline runtime `<script>` blocks from `pages/edit_application.php` into `assets/css/edit-application-template-page.css`, `assets/js/edit-application-theme-bootstrap.js`, and `assets/js/edit-application-template-runtime.js`; converted remaining inline margin styles in default template to CSS classes
- Completed: extracted inline `<style>` block from `includes/navigation.php` into shared `assets/css/core.css` (mini-menu hover/label layout rules)
- Completed: extracted inline `<script>` blocks from `pages/analytics.php` into `assets/js/analytics-page-runtime.js` and moved chart/runtime datasets to HTML `data-*` attributes (`#analytics-data` and `#internship-pie-chart`)
- Completed: extracted remaining `management/*.php` inline `<style>` blocks into `assets/css/management-*-page.css`, moved `sections*` inline scripts into `assets/js/management-sections*-runtime.js`, and replaced remaining inline handlers/styles with delegated runtime (`data-action`, `data-confirm-message`, `data-hide-onerror`) plus shared utility classes in `assets/css/core.css`
- Completed: cleaned `pages/attendance.php`, `pages/edit_dau_moa.php`, `pages/edit_moa.php`, `pages/edit_endorsement.php`, and `pages/edit_attendance.php` by extracting inline `<style>`/inline `<script>` into page assets (`assets/css/pages-attendance-page.css`, `assets/js/pages-attendance-runtime.js`, `assets/css/edit-*-template-page.css`, `assets/js/edit-*-template-runtime.js`, `assets/js/edit-template-theme-bootstrap.js`, `assets/css/edit-attendance-page.css`) and replacing inline handlers with delegated `data-attendance-action` hooks
- Completed: removed exact duplicate CSS assets by consolidating to shared files (`assets/css/edit-moa-dau-template-page.css`, `assets/css/management-personnel-create-page.css`, `assets/css/management-create-form-actions-page.css`) and updating 7 page includes; duplicate CSS hash groups reduced to zero
- Completed: merged similar management create styles into `assets/css/management-create-shared-page.css` (covers coordinators/supervisors/courses/departments/sections create pages), deleted superseded files, and added stylesheet headers with `Class` + `Used by` annotations for traceable PHP-to-CSS mapping in key module CSS files
- Completed: finalized full `management-*.css` / `pages-*.css` classification sweep by adding `Class` + `Used by` headers to all remaining target module stylesheets (`13/13` coverage); validated with diagnostics and verified no exact duplicate CSS files remain after consolidation
- Completed: consolidated duplicate MOA generator styling by extracting shared rules from `assets/css/generate-moa-page.css` and `assets/css/generate-dau-moa-page.css` into `assets/css/generate-moa-common-page.css`, then rewired `pages/generate_moa.php` and `pages/generate_dau_moa.php` to include shared + page-specific CSS
- Completed: rolled out readable dual classes (`legacy + app-*`) for MOA generator layouts and utilities across `pages/generate_moa.php` and `pages/generate_dau_moa.php`, with alias coverage in `assets/css/generate-moa-common-page.css`, `assets/css/generate-moa-page.css`, and `assets/css/generate-dau-moa-page.css`
- Completed: organized editor template CSS into readable semantic classes with explicit usage comments in-file (`assets/css/edit-application-template-page.css`, `assets/css/edit-endorsement-template-page.css`, `assets/css/edit-moa-dau-template-page.css`) and wired dual classes in `pages/edit_application.php` and `pages/edit_endorsement.php`
- Completed: applied attendance/students readability pass by adding semantic dual classes (`legacy + app-*`) and explicit usage comments across `assets/css/pages-attendance-page.css`, `assets/css/management-students-page.css`, `assets/css/management-students-view-page.css`, `assets/css/management-students-edit-page.css`, and `assets/css/management-students-dtr-page.css`, then wired matching classes in `pages/attendance.php`, `management/students.php`, `management/students-view.php`, `management/students-edit.php`, and `management/students-dtr.php`
- Completed: added readable dual classes (`legacy + app-*`) and section usage comments for letter generators in `assets/css/generate-endorsement-letter-page.css` and `assets/css/generate-application-letter-page.css`, then wired semantic aliases in `pages/generate_endorsement_letter.php` and `pages/generate_application_letter.php`
- Completed: added readable dual classes (`legacy + app-*`) and module/section comments for resume generator in `assets/css/generate-resume-page.css`, then wired matching aliases in `pages/generate_resume.php`
- Completed: added readable dual classes (`legacy + app-*`) for OJT dashboard and print layout in `assets/css/management-ojt-page.css`, then wired matching aliases in `management/ojt.php`
- Completed: added readable dual classes (`legacy + app-*`) for OJT workflow board cards and columns in `assets/css/management-ojt-workflow-board-page.css`, then wired matching aliases in `management/ojt-workflow-board.php`
- Completed: added readable dual classes (`legacy + app-*`) for OJT create page responsive header and section wrappers in `assets/css/management-ojt-create-page.css`, then wired matching aliases in `management/ojt-create.php`
- Completed: expanded readable dual classes (`legacy + app-*`) for OJT edit responsive layout/card sections in `assets/css/management-ojt-edit-page.css`, then wired matching aliases in `management/ojt-edit.php`
- Completed: expanded readable dual classes (`legacy + app-*`) for OJT view tabs/cards/document forms/print options in `assets/css/management-ojt-view-page.css`, then wired matching aliases in `management/ojt-view.php`
- Completed: completed remaining OJT view document-tab alias wiring (MOA, Endorsement, Dau MOA) in `management/ojt-view.php` to align all tabs with `app-ojt-view-document-*` and `app-ojt-view-form-label`
- Completed: expanded OJT create field-level readable aliases in `management/ojt-create.php` (`app-ojt-create-main-content`, `app-ojt-create-section-head`, `app-ojt-create-form-label`) with matching selector support in `assets/css/management-ojt-create-page.css`
- Completed: expanded OJT create info-grid readability by aliasing repeated lead-info rows/labels/input-groups in `management/ojt-create.php` (`app-ojt-create-info-row`, `app-ojt-create-info-label`, `app-ojt-create-input-group`) with dark-mode and spacing support in `assets/css/management-ojt-create-page.css`
- Completed: resolved mobile overlap on `auth/idnotfound-404.php` by reusing responsive 404 classes from `assets/css/auth-404-minimal-page.css` and removing fixed inline heading sizing
- Completed: closed remaining pending alias set by adding attendance filter/selection aliases (`app-attendance-filter-link`, `app-attendance-selected-label`, `app-attendance-selected-icon`, `app-attendance-selected-count`) in `pages/attendance.php` + `assets/css/pages-attendance-page.css`, and OJT create header trigger aliases (`app-ojt-create-header-items`, `app-ojt-create-header-close-toggle`, `app-ojt-create-header-open-toggle`, `app-ojt-create-success-alert-trigger`) in `management/ojt-create.php` + `assets/css/management-ojt-create-page.css`

## Unexpected Asset Audit (2026-03-07)

- Audited newly added assets under `assets/js/*.min.js` and `assets/images/storage-icons/*`
- Risk identified: `assets/images/storage-icons/undefined.png` contains embedded Getty/iStock licensing metadata strings (`Getty`, `iStock`, `license`, `LicensorURL`)
- Usage identified: only referenced in `apps/apps-storage.php` (single occurrence)
- Mitigation applied: replaced `undefined.png` reference with existing local asset `assets/images/storage-icons/local-storage.png`
- `assets/images/storage-icons/onedrive.png` currently has no PHP usage reference and no detected Getty/iStock marker strings
- Operational note: many newly added minified init files are now widely referenced across app pages; treat as imported vendor/template payload and verify provenance before release
