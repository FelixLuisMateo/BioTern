# JS Class Map

## Core Runtime Classes

- `AppCore.Storage` (`assets/js/global-ui-helpers.js`)
  - `get(key)`
  - `set(key, value)`

- `AppCore.Documents` (`assets/js/global-ui-helpers.js`)
  - `bindPrintButton(buttonId)`
  - `bindCloseButton(buttonId, fallbackHref, options)`
  - `hideBrokenImagesOnError()`
  - `loadSavedTemplateHtml(storageKey, containerId, options)`
  - `initMoaDocument(options)`

- `AppCore.Animations` (`assets/js/global-ui-helpers.js`)
  - `revealOnLoad(selector, className)`

- `AppCore.TemplateEditor` (`assets/js/global-ui-helpers.js`)
  - `create(options)`
  - `attachLogoDrag(editor, options)`

- `AppCore.Widgets` (`assets/js/global-ui-helpers.js`)
  - `initCircleProgressBatch(items)`

## Script Categories

- `document-generation`
  - `assets/js/generate-application-letter-runtime.js`
  - `assets/js/generate-endorsement-letter-runtime.js`
  - `assets/js/generate-moa-runtime.js`
  - `assets/js/generate-dau-moa-runtime.js`
  - `assets/js/generate-resume-runtime.js`

- `theme-and-ui`
  - `assets/js/theme-preferences-runtime.js`
  - `assets/js/theme-customizer-init.min.js` (shim)
  - `assets/js/theme-preload-init.min.js`
  - `assets/js/global-ui-helpers.js`

- `management`
  - `assets/js/ojt-view-runtime.js`
  - `assets/js/ojt-dashboard-runtime.js`
  - `assets/js/management-sections-runtime.js`
  - `assets/js/management-sections-create-runtime.js`
  - `assets/js/students-page-runtime.js`
  - `assets/js/students-view-runtime.js`
  - `assets/js/students-edit-runtime.js`

- `page-runtime`
  - `assets/js/pages-attendance-runtime.js`
  - `assets/js/analytics-page-runtime.js`
  - `assets/js/homepage-dashboard-runtime.js`
  - `assets/js/homepage-movable.js`

- `template-editors`
  - `assets/js/edit-moa-template-runtime.js`
  - `assets/js/edit-dau-moa-template-runtime.js`
  - `assets/js/edit-endorsement-template-runtime.js`
  - `assets/js/edit-application-template-runtime.js`

- `reports`
  - `assets/js/reports-project-init.min.js`
  - `assets/js/reports-sales-init.min.js`
  - `assets/js/reports-leads-init.min.js`
  - `assets/js/reports-tmesheets-init.min.js`

- `widgets`
  - `assets/js/widgets-lists-init.min.js`
  - `assets/js/widgets-miscellaneous-init.min.js`

## Duplicate Consolidation

- Canonical no-op init file: `assets/js/page-init-noop.min.js`
- Removed duplicate files:
  - `assets/js/apps-calendar-init.min.js`
  - `assets/js/leads-view-init.min.js`
  - `assets/js/widgets-statistics-init.min.js`
- Updated references:
  - `apps/apps-calendar.php`
  - `management/ojt-create.php`
  - `management/ojt-view.php`
  - `widgets/widgets-statistics.php`
