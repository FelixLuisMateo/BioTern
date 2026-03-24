# BioTern CSS Migration (SMACSS)

This project now uses `assets/css/smacss.css` as the shared stylesheet entrypoint.

## Current mapping

- Base: `theme.min.css` (vendor/template foundation)
- Layout: `layout/app-core-shell.css` (app shell + shared structure)
- Module:
  - `modules/app-core-utilities.css` (shared utility-like classes)
  - `modules/app-core-pages.css` (page-scoped shared overrides)
  - `modules/app-ui-interactions.css` (shared interaction/header/nav behavior)
  - `modules/pages/*` (general page modules loaded via `$page_styles`)
  - `modules/management/*` (management-domain modules)
  - `modules/documents/*` (documents-domain modules)
  - `modules/reports/*` (reports-domain modules)
  - `modules/auth/*` (auth screen modules)
  - `modules/apps/*` (app screen modules)
- Theme:
  - `theme/tokens.css`
  - `theme/surfaces.css`
  - `theme/forms-dark.css`
  - `theme/scheme-gray.css`
  - `theme/customizer.css`
  - `theme/template-print-lock.css`
- State:
  - `state/mobile-layout.css`
  - `state/mobile-components.css`

## Compatibility files

- `core.css` now imports the split core modules for backward compatibility.
- `ui.css` now imports `modules/app-ui-interactions.css` for backward compatibility.
- `theme.css` now imports the split theme modules for backward compatibility.
- `mobile.css` now imports the split state modules for backward compatibility.
- Legacy domain folders (`pages`, `management`, `documents`, `reports`, `auth`, `apps`) have been retired.
- Domain CSS now lives directly under `assets/css/modules/<domain>/`.

## Why this is transitional

`smacss.css` currently imports legacy files in compatibility order so we avoid visual regressions while reorganizing.

## Recent centralization (theme + UI)

- Moved shared dark form/select2 behavior into `theme/forms-dark.css`:
  - dark input/select/select2 tones
  - select2 bootstrap-5 dark overrides
  - dark file-input styling
  - select2 overlay-input dark text
- Strengthened gray-scheme neutralization in `theme/scheme-gray.css`:
  - removed duplicate gray-dark form/select2 block
  - neutral DataTables row/selected/hover styling
  - neutralized `.btn-light-brand` for gray scheme
- Removed duplicated global theme rules from page/domain modules (kept only page-specific styling):
  - `modules/documents/documents.css`
  - `modules/auth/auth-register-creative.css`
  - `modules/management/management-sections.css`
  - `modules/management/management-students.css`
  - `modules/management/management-create-shared.css`
  - `modules/management/management-students-edit.css`
  - `modules/management/management-ojt-shared.css`
  - `modules/pages/page-attendance.css`

## Rules for new CSS

- Put new reusable page structure rules in `layout/app-core-shell.css`.
- Put shared component interaction rules in `modules/app-ui-interactions.css`.
- Put page/domain-specific styles under `modules/` (for example `modules/pages/`, `modules/management/`, `modules/documents/`).
- Put color/skin/scheme overrides in the corresponding file under `theme/`.
- Put viewport/state-specific overrides in the corresponding file under `state/`.
- Avoid editing `theme.min.css` directly unless absolutely required.

## Next migration steps

1. Continue reducing `!important` usage by lowering selector conflicts in theme and module files.
2. Move remaining one-off inline style blocks into dedicated module files.
3. Expand SCSS source coverage so generated CSS is the main source of truth.
