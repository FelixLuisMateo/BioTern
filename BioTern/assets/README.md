# Assets Structure

This folder is now organized by responsibility so CSS/JS files are easier to find and maintain.

## CSS

- `assets/css/`:
  - Global theme/core styles only (`bootstrap.min.css`, `theme.min.css`, `core.css`, `ui.css`, `theme.css`, `mobile.css`)
- `assets/css/apps/`: App page styles
- `assets/css/auth/`: Authentication page styles
- `assets/css/documents/`: Documents, generators, and editor page styles
- `assets/css/layout/`: Shared layout shells (`page_shell.css`, `page-editor-shell.css`)
- `assets/css/management/`: Management module styles
- `assets/css/pages/`: General standalone page styles
- `assets/css/reports/`: Report page styles

## JS

- `assets/js/`:
  - Global runtime scripts only (`common-init.min.js`, theme/runtime helpers, navigation/mobile globals)
- `assets/js/apps/`: App page scripts
- `assets/js/auth/`: Authentication page scripts
- `assets/js/documents/`: Documents, generators, and editor page scripts
- `assets/js/management/`: Management module scripts
- `assets/js/pages/`: General standalone page scripts
- `assets/js/reports/`: Report page scripts

## Rule Of Thumb

When adding new files:

1. Place by feature/module (`apps`, `reports`, `management`, etc.), not by file type alone.
2. Keep root `assets/css` and `assets/js` for shared/global files only.
3. Register paths through `$page_styles` and `$page_scripts` where possible.
