# Assets Structure

This folder is now organized by responsibility so CSS/JS files are easier to find and maintain.

## CSS

- `assets/css/`:
  - Global/shared entry files only (`bootstrap.min.css`, `smacss.css`, `theme-base.css`, `theme-legacy.css`)
- `assets/css/modules/apps/`: App page styles
- `assets/css/modules/auth/`: Authentication page styles
- `assets/css/modules/documents/`: Documents, generators, and editor page styles
- `assets/css/layout/`: Shared layout shells (`page_shell.css`, `page-editor-shell.css`)
- `assets/css/modules/management/`: Management module styles
- `assets/css/modules/pages/`: General standalone page styles
- `assets/css/modules/reports/`: Report page styles
- `assets/css/state/`: State/responsive behavior
  - Includes mobile component packs (`mobile-components.css`, `mobile-components-extended.css`)
- `assets/css/theme/`: Theme/scheme layer

## JS

- `assets/js/`:
  - Global runtime scripts only (`common-init.min.js`, `global-ui-helpers.js`, theme/runtime helpers)
- `assets/js/modules/apps/`: App page scripts
- `assets/js/modules/auth/`: Authentication page scripts
- `assets/js/modules/documents/`: Documents, generators, and editor page scripts
- `assets/js/modules/management/`: Management module scripts
- `assets/js/modules/pages/`: General standalone page scripts
- `assets/js/modules/reports/`: Report page scripts
- `assets/js/modules/shared/`: Shared cross-domain runtimes
  - Includes mobile shared behaviors (for example `mobile-components-runtime.js`)

## Rule Of Thumb

When adding new files:

1. Place by feature/module (`apps`, `reports`, `management`, etc.), not by file type alone.
2. Keep root `assets/css` and `assets/js` for shared/global files only.
3. Register paths through `$page_styles` and `$page_scripts` where possible.
