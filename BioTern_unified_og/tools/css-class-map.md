# CSS Class Map (Readable Naming)

Date: 2026-03-07
Scope: `BioTern_unified` shared and high-traffic page assets

## Naming Rules

- Use `app-` prefix for project-owned classes.
- Keep classes semantic and role-based (what it is), not visual-only (how it looks).
- Keep legacy classes for backward compatibility during migration windows.
- Prefer additive migration: `legacy-class app-new-class` before full cutover.

## Editor Layout Classes

- `app-editor-page`: page-level editor surface context
- `app-editor-topbar`: sticky editor toolbar container
- `app-editor-toolbar`: button/inputs group inside topbar
- `app-editor-status`: right-side status label (Saved/Unsaved)
- `app-editor-page-wrap`: centered editor content wrapper
- `app-editor-paper`: printable paper/card surface
- `app-editor-canvas`: editable content region

Used in:

- `pages/edit_application.php`
- `pages/edit_dau_moa.php`
- `pages/edit_moa.php`
- `pages/edit_endorsement.php`

Styled in:

- `assets/css/edit-application-template-page.css`
- `assets/css/edit-moa-dau-template-page.css`
- `assets/css/edit-endorsement-template-page.css`

Editor document-specific classes added:

- Application template (`pages/edit_application.php`):
- `app-application-container`
- `app-application-crest`
- `app-application-header`
- `app-application-title`
- `app-application-meta`
- `app-application-tel`
- `app-application-content`
- `app-application-heading`
- `app-application-mt-30`
- `app-application-mt-40`

- Endorsement template (`pages/edit_endorsement.php`):
- `app-endorsement-doc-header`
- `app-endorsement-doc-crest`
- `app-endorsement-doc-title`
- `app-endorsement-doc-meta`
- `app-endorsement-doc-content`
- `app-endorsement-doc-heading`
- `app-endorsement-doc-signoff`

- MOA/DAU editor body aliases (`pages/edit_moa.php`, `pages/edit_dau_moa.php`):
- `app-moa-row`
- `app-moa-col`
- `app-moa-right`

## Form Action Classes

- `app-form-actions`: shared action button row for create forms

Used in:

- `management/coordinators-create.php`
- `management/supervisors-create.php`
- `management/courses-create.php`
- `management/departments-create.php`
- `management/sections-create.php`

Styled in:

- `assets/css/management-create-shared-page.css` (consolidated)

## CSS File Classification Comments

Each module stylesheet should begin with:

- `Class:` module identifier (example: `module.pages.attendance`)
- `Used by:` explicit PHP file list that includes the stylesheet

This is now applied to key module CSS files:

- `assets/css/pages-attendance-page.css`
- `assets/css/management-students-page.css`
- `assets/css/management-ojt-page.css`
- `assets/css/edit-moa-dau-template-page.css`
- `assets/css/edit-application-template-page.css`
- `assets/css/edit-endorsement-template-page.css`
- `assets/css/management-create-shared-page.css`

This is now fully applied to all management/pages module stylesheets in scope:

- `assets/css/management-courses-page.css`
- `assets/css/management-create-shared-page.css`
- `assets/css/management-ojt-create-page.css`
- `assets/css/management-ojt-edit-page.css`
- `assets/css/management-ojt-page.css`
- `assets/css/management-ojt-view-page.css`
- `assets/css/management-ojt-workflow-board-page.css`
- `assets/css/management-sections-page.css`
- `assets/css/management-students-dtr-page.css`
- `assets/css/management-students-edit-page.css`
- `assets/css/management-students-page.css`
- `assets/css/management-students-view-page.css`
- `assets/css/pages-attendance-page.css`

## Existing Readable Classes Kept

- `attendance-bulk-toolbar-hidden`
- `attendance-selected-label`
- `attendance-selected-icon`
- `attendance-selected-count`
- `endorsement-doc-*`

## Attendance Classes

- `app-attendance-main-content`: attendance page main content wrapper
- `app-attendance-table-card`: table card container for attendance list and actions
- `app-attendance-bulk-toolbar-hidden`: hidden state for bulk toolbar block
- `app-attendance-bulk-toolbar`: visible toolbar container styling target
- `app-attendance-filter-form`: attendance filter form namespace for select/date/select2 styling
- `app-attendance-filter-link`: attendance quick-filter dropdown action link
- `app-attendance-selected-label`: attendance bulk selection status label
- `app-attendance-selected-icon`: attendance bulk selection status icon
- `app-attendance-selected-count`: attendance bulk selection counter

Used in:

- `pages/attendance.php`

Styled in:

- `assets/css/pages-attendance-page.css`

## Students Module Classes

- `app-students-main-content`: students listing main content wrapper
- `app-students-table-card`: students table card container
- `app-students-list-table`: students table semantic identifier
- `app-students-view-main-content`: students view page content wrapper
- `app-students-edit-main-content`: students edit page content wrapper
- `app-students-filter-form`: students list filter form namespace
- `app-students-print-sheet`: print-only student list sheet container

Students view readable aliases:

- `app-students-profile-stats`
- `app-students-stat-card`
- `app-students-hours-remaining-card`
- `app-students-completion-card`
- `app-students-profile-contact-item`
- `app-students-profile-contact-value`

Students edit readable aliases:

- `app-students-edit-form`
- `app-students-edit-form-hero`
- `app-students-edit-form-meta`
- `app-students-edit-section-card`
- `app-students-edit-save-actions`

Students DTR readable aliases:

- `app-students-dtr-toolbar`
- `app-students-dtr-meta-highlight`
- `app-students-dtr-chip`
- `app-students-dtr-summary-card`
- `app-students-dtr-summary-label`
- `app-students-dtr-summary-value`
- `app-students-dtr-desktop-table`
- `app-students-dtr-table`
- `app-students-dtr-mobile-list`
- `app-students-dtr-day-card`
- `app-students-dtr-day-top`
- `app-students-dtr-day-title`
- `app-students-dtr-slot-grid`
- `app-students-dtr-slot`
- `app-students-dtr-slot-label`
- `app-students-dtr-slot-value`
- `app-students-dtr-hours-row`
- `app-students-dtr-hours-label`
- `app-students-dtr-hours-value`
- `app-students-dtr-empty-note`

Used in:

- `management/students.php`
- `management/students-view.php`
- `management/students-edit.php`

## OJT Module Classes

- `app-ojt-page-header`: OJT dashboard page header wrapper
- `app-ojt-page-header-left`: OJT dashboard title/intro column
- `app-ojt-page-header-right`: OJT dashboard action buttons column
- `app-ojt-page-subtitle`: OJT dashboard subtitle label
- `app-ojt-dashboard-card`: OJT dashboard card container alias
- `app-ojt-kpi-value`: OJT KPI numeric value label
- `app-ojt-filter-card`: OJT filter card alias
- `app-ojt-filter-form`: OJT filter form namespace
- `app-ojt-list-table`: OJT student list table alias
- `app-ojt-student-link`: OJT student row profile link
- `app-ojt-chip`: OJT document progress status chip
- `app-ojt-chip-ok`: OJT progress chip positive state
- `app-ojt-chip-miss`: OJT progress chip missing state
- `app-ojt-risk-pill`: OJT risk flag badge
- `app-ojt-row-actions`: OJT row action button group
- `app-ojt-print-sheet`: OJT print sheet container
- `app-ojt-print-header`: OJT print sheet header block
- `app-ojt-print-crest`: OJT print sheet crest image
- `app-ojt-print-meta`: OJT print sheet address line
- `app-ojt-print-tel`: OJT print sheet contact line
- `app-ojt-print-title`: OJT print sheet title
- `app-ojt-print-meta-row`: OJT print section metadata row
- `app-ojt-print-col-index`: OJT print table index column
- `app-ojt-workflow-header-right-actions`: OJT workflow header action group
- `app-ojt-workflow-page-header`: OJT workflow board header row
- `app-ojt-workflow-surface-card`: OJT workflow board surface card wrapper
- `app-ojt-workflow-filter-form`: OJT workflow board filter form namespace
- `app-ojt-workflow-board-col`: OJT workflow board status column
- `app-ojt-workflow-board-head`: OJT workflow board status heading
- `app-ojt-workflow-card`: OJT workflow board student workflow card
- `app-ojt-workflow-meta`: OJT workflow board metadata text
- `app-ojt-workflow-note`: OJT workflow board review-note text
- `app-ojt-workflow-card-actions`: OJT workflow card action row
- `app-ojt-workflow-update-form`: OJT workflow card status update form
- `app-ojt-create-page-header`: OJT create page header wrapper
- `app-ojt-create-page-header-left`: OJT create page heading/breadcrumb column
- `app-ojt-create-page-header-actions`: OJT create page header action group
- `app-ojt-create-surface-card`: OJT create page card surface alias
- `app-ojt-create-main-content`: OJT create page main content wrapper
- `app-ojt-create-section-head`: OJT create section header row (title + action)
- `app-ojt-create-form-label`: OJT create form label alias
- `app-ojt-create-info-row`: OJT create lead info row wrapper
- `app-ojt-create-info-label`: OJT create lead info field label alias
- `app-ojt-create-input-group`: OJT create lead info input-group wrapper
- `app-ojt-create-header-items`: OJT create page header right-items wrapper
- `app-ojt-create-header-close-toggle`: OJT create mobile header close/back toggle
- `app-ojt-create-header-open-toggle`: OJT create mobile header open toggle
- `app-ojt-create-success-alert-trigger`: OJT create success action trigger button alias
- `app-ojt-create-lead-status`: OJT create lead-status section wrapper
- `app-ojt-create-general-info`: OJT create general-info section wrapper
- `app-ojt-edit-page-header`: OJT edit page header wrapper
- `app-ojt-edit-page-header-actions`: OJT edit page header action group
- `app-ojt-edit-surface-card`: OJT edit page card surface alias
- `app-ojt-edit-form-label`: OJT edit page form label alias
- `app-ojt-edit-section-subtitle`: OJT edit operational panel subtitle
- `app-ojt-edit-actions`: OJT edit control action row
- `app-ojt-view-page-header`: OJT view page header wrapper
- `app-ojt-view-page-header-left`: OJT view page title/breadcrumb column
- `app-ojt-view-tabs`: OJT view profile/document tab list
- `app-ojt-view-surface-card`: OJT view card surface alias
- `app-ojt-view-lead-info`: OJT view student information section wrapper
- `app-ojt-view-general-info`: OJT view general information section wrapper
- `app-ojt-view-overview-actions`: OJT view overview action buttons row
- `app-ojt-view-workflow-form`: OJT view per-document workflow update form
- `app-ojt-view-document-card`: OJT view document tab card wrapper
- `app-ojt-view-document-form`: OJT view document autofill form namespace
- `app-ojt-view-document-form-actions`: OJT view document form action row
- `app-ojt-view-form-label`: OJT view document form label alias
- `app-ojt-view-print-doc-option`: OJT view print selector option
- `app-ojt-view-print-doc-label`: OJT view print selector label text
- `app-ojt-view-print-doc-state`: OJT view print selector state text
- `app-ojt-view-print-doc-actions`: OJT view print action controls wrapper
- `app-ojt-view-print-doc-hint`: OJT view print action helper note
- `app-ojt-view-main-content`: OJT view page main content wrapper
- `app-ojt-student-info-card`: OJT student info summary card
- `app-ojt-edit-content`: OJT edit page content wrapper
- `app-ojt-student-context-card`: OJT edit student context card
- `app-ojt-controls-form`: OJT edit operational controls form card

Used in:

- `management/ojt.php`
- `management/ojt-workflow-board.php`
- `management/ojt-create.php`
- `management/ojt-view.php`
- `management/ojt-edit.php`

Styled in:

- `assets/css/management-ojt-page.css`

## Next Phase Suggestions

- Migrate remaining generic classes (`.topbar`, `.toolbar`, `.page-wrap`, `.paper`) to `app-*` only once all templates are dual-tagged.
- Fold repeated editor typography into `core.css` under an `Editor` section.
- De-duplicate near-identical runtime JS for MOA/DAU into shared editor runtime with page config.

## Document MOA Shared Styles

- `assets/css/generate-moa-common-page.css`: shared base typography/layout/print rules for MOA generators
- `assets/css/generate-moa-page.css`: generic MOA-specific spacing and print compensation deltas
- `assets/css/generate-dau-moa-page.css`: DAU MOA-specific spacing and section utility deltas

Used in:

- `pages/generate_moa.php`
- `pages/generate_dau_moa.php`

Readable MOA classes introduced:

- `app-moa-page`
- `app-moa-container`
- `app-moa-doc`
- `app-moa-row`
- `app-moa-col`
- `app-moa-right`
- `app-moa-text-right`
- `app-moa-text-center`
- `app-moa-notary-line`
- `app-moa-actions`
- `app-moa-tip-box`
- `app-moa-btn`
- `app-moa-action-btn`
- `app-moa-and-line`

MOA utility aliases:

- `app-moa-row-top-neg-12`
- `app-moa-center-neg-20`
- `app-moa-center-top-10-bottom-neg-10`
- `app-moa-center-gap-8`
- `app-moa-center-gap-6`
- `app-moa-mt-16`
- `app-moa-mt-neg-18`
- `app-moa-mt-neg-5`
- `app-moa-mt-0`
- `app-moa-mb-neg-4`
- `app-moa-mb-neg-12`
- `app-moa-nowrap`

## Letter Generator Classes

Endorsement letter aliases:

- `app-endorsement-letter-container`
- `app-endorsement-letter-header`
- `app-endorsement-letter-crest`
- `app-endorsement-letter-meta`
- `app-endorsement-letter-tel`
- `app-endorsement-letter-content`
- `app-endorsement-letter-signature`
- `app-endorsement-letter-ross-signatory`
- `app-endorsement-letter-ross-signature`
- `app-endorsement-letter-ross-signatory-text`
- `app-endorsement-letter-actions`
- `app-endorsement-letter-tip-box`
- `app-endorsement-letter-action-btn`
- `app-endorsement-letter-no-print`

Application letter aliases:

- `app-application-letter-container`
- `app-application-letter-header`
- `app-application-letter-crest`
- `app-application-letter-meta`
- `app-application-letter-tel`
- `app-application-letter-content`
- `app-application-letter-sheet-title`
- `app-application-letter-field-block`
- `app-application-letter-filled-val`
- `app-application-letter-filled-val-wide`
- `app-application-letter-filled-val-name`
- `app-application-letter-signature`
- `app-application-letter-small`
- `app-application-letter-mt-30`
- `app-application-letter-mt-40`
- `app-application-letter-approval-signature-line`
- `app-application-letter-actions`
- `app-application-letter-actions-inline-layout`
- `app-application-letter-tip-box`
- `app-application-letter-action-btn`
- `app-application-letter-no-print`

Used in:

- `pages/generate_endorsement_letter.php`
- `pages/generate_application_letter.php`

Styled in:

- `assets/css/generate-endorsement-letter-page.css`
- `assets/css/generate-application-letter-page.css`

Resume generator aliases:

- `app-resume-container`
- `app-resume-sheet`
- `app-resume-header`
- `app-resume-left`
- `app-resume-right`
- `app-resume-photo`
- `app-resume-photo-placeholder`
- `app-resume-contact`
- `app-resume-meta`
- `app-resume-two-col`
- `app-resume-col`
- `app-resume-section`
- `app-resume-small`
- `app-resume-print-btn`

Used in:

- `pages/generate_resume.php`

Styled in:

- `assets/css/generate-resume-page.css`
