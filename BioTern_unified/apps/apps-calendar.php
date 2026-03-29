<?php
require_once dirname(__DIR__) . '/config/db.php';

$page_title = 'BioTern || Calendar';
$page_styles = [
    'assets/vendors/css/datepicker.min.css',
    'assets/css/datepicker-global.css',
    'assets/css/modules/apps/apps-calendar-page.css',
];

$calendar_script_name = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$calendar_unified_pos = stripos($calendar_script_name, '/BioTern_unified/');
$calendar_base_href = ($calendar_unified_pos !== false)
    ? substr($calendar_script_name, 0, $calendar_unified_pos) . '/BioTern_unified/'
    : '/BioTern_unified/';
$calendar_events_endpoint = $calendar_base_href . 'calendar_events.php';
$calendar_user_role = strtolower(trim((string)($_SESSION['role'] ?? '')));
$calendar_can_manage_events = !in_array($calendar_user_role, ['student', 'supervisor'], true);
$calendar_toolbar_subtitle = $calendar_can_manage_events
    ? "Browse the month, check what's happening each day, and manage saved events."
    : "Browse the month and check holidays, birthdays, and important schedule updates.";

include 'includes/header.php';
?>
<div class="app-calendar-shell" data-calendar-app data-events-endpoint="<?php echo htmlspecialchars($calendar_events_endpoint, ENT_QUOTES, 'UTF-8'); ?>" data-can-manage-events="<?php echo $calendar_can_manage_events ? '1' : '0'; ?>">
    <div class="row g-4 align-items-start">
        <div class="col-12 col-xxl-9">
            <section class="card app-calendar-board">
                <div class="card-body">
                    <div class="app-calendar-toolbar">
                        <div class="app-calendar-toolbar-copy">
                            <span class="app-calendar-toolbar-label">Month View</span>
                            <h2 class="app-calendar-month-title" data-month-label>Calendar</h2>
                            <p class="app-calendar-toolbar-subtitle"><?php echo htmlspecialchars($calendar_toolbar_subtitle, ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                        <div class="app-calendar-toolbar-actions">
                            <?php if ($calendar_can_manage_events): ?>
                            <button type="button" class="app-calendar-create-button" data-add-event>
                                <i class="feather-plus"></i>
                                <span>Add Event</span>
                            </button>
                            <?php endif; ?>
                            <div class="app-calendar-jump-controls" aria-label="Jump to month and year">
                                <select class="form-select form-select-sm" data-jump-month></select>
                                <select class="form-select form-select-sm" data-jump-year></select>
                            </div>
                            <div class="app-calendar-nav-buttons" role="group" aria-label="Navigate month">
                                <button type="button" data-action="prev" aria-label="Previous month">
                                    <i class="feather-chevron-left"></i>
                                </button>
                                <button type="button" data-action="today">Today</button>
                                <button type="button" data-action="next" aria-label="Next month">
                                    <i class="feather-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="app-calendar-legend-bar">
                        <div class="app-calendar-legend">
                            <span><i class="app-calendar-dot is-holiday"></i>Philippine holidays and observances</span>
                            <span><i class="app-calendar-dot is-birthday"></i>OJT birthdays</span>
                            <span><i class="app-calendar-dot is-custom"></i>Saved calendar entries</span>
                            <span><i class="app-calendar-dot is-today"></i>Today</span>
                        </div>
                        <div class="app-calendar-legend-note">Tap any date to open its agenda.</div>
                    </div>

                    <div class="app-calendar-weekdays" aria-hidden="true">
                        <span>Sun</span>
                        <span>Mon</span>
                        <span>Tue</span>
                        <span>Wed</span>
                        <span>Thu</span>
                        <span>Fri</span>
                        <span>Sat</span>
                    </div>

                    <div class="app-calendar-grid" data-calendar-grid></div>
                </div>
            </section>
        </div>

        <div class="col-12 col-xxl-3">
            <div class="app-calendar-sidebar-stack">
                <section class="card app-calendar-sidecard">
                    <div class="card-body">
                        <div class="app-calendar-sidecard-head">
                            <span class="app-calendar-sidecard-kicker">Details</span>
                            <h3 data-selected-date-label>Choose a day</h3>
                        </div>
                        <div class="app-calendar-day-summary" data-selected-summary>Select a day on the calendar to see all matching events.</div>
                        <div class="app-calendar-day-list" data-selected-events></div>
                        <div class="app-calendar-sidecard-section">
                            <span class="app-calendar-sidecard-kicker">Upcoming Birthdays</span>
                            <h4 class="app-calendar-section-title">OJT birthday lineup</h4>
                        </div>
                        <div class="app-calendar-mini-list" data-upcoming-birthdays></div>
                        <div class="app-calendar-sidecard-section">
                            <span class="app-calendar-sidecard-kicker">Month Timeline</span>
                            <h4 class="app-calendar-section-title">Philippine events this month</h4>
                        </div>
                        <div class="app-calendar-mini-list" data-month-events></div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</div>

<?php if ($calendar_can_manage_events): ?>
<div class="app-calendar-event-panel" id="appCalendarEventPanel" hidden>
    <form class="app-calendar-modal" data-event-form>
        <div class="app-calendar-modal-header">
            <div class="app-calendar-modal-heading">
                <span class="app-calendar-sidecard-kicker mb-1">Saved Event</span>
                <h5 class="modal-title mb-0" data-event-panel-title>Add Event</h5>
                <p class="app-calendar-modal-subtitle" data-event-panel-subtitle>Create a custom schedule item for your BioTern calendar.</p>
            </div>
            <button type="button" class="btn-close" data-close-event-panel aria-label="Close"></button>
        </div>
        <div class="app-calendar-modal-body">
            <input type="hidden" id="appCalendarEventId" name="id" value="">
            <div class="app-calendar-field mb-3">
                <label class="form-label" for="appCalendarEventTitle">Title</label>
                <input type="text" class="form-control" id="appCalendarEventTitle" name="title" maxlength="255" required>
            </div>
            <div class="app-calendar-field mb-3">
                <label class="form-label" for="appCalendarEventLocation">Location</label>
                <input type="text" class="form-control" id="appCalendarEventLocation" name="location" maxlength="255">
            </div>
            <div class="row g-3">
                <div class="col-sm-6 app-calendar-field">
                    <label class="form-label" for="appCalendarEventStartDate">Start Date</label>
                    <input type="date" class="form-control" id="appCalendarEventStartDate" name="start_date" required>
                </div>
                <div class="col-sm-6 app-calendar-field">
                    <label class="form-label" for="appCalendarEventEndDate">End Date</label>
                    <input type="date" class="form-control" id="appCalendarEventEndDate" name="end_date" required>
                </div>
            </div>
            <div class="row g-3 mt-1">
                <div class="col-sm-6 app-calendar-field">
                    <label class="form-label" for="appCalendarEventStartTime">Start Time</label>
                    <select class="form-select" id="appCalendarEventStartTime" name="start_time" required></select>
                </div>
                <div class="col-sm-6 app-calendar-field">
                    <label class="form-label" for="appCalendarEventEndTime">End Time</label>
                    <select class="form-select" id="appCalendarEventEndTime" name="end_time" required></select>
                </div>
            </div>
            <div class="row g-3 mt-1 app-calendar-field-row">
                <div class="col-sm-7 app-calendar-field">
                    <label class="form-label" for="appCalendarEventColor">Color</label>
                    <input type="color" class="form-control form-control-color w-100" id="appCalendarEventColor" name="color" value="#2563eb" title="Choose event color">
                </div>
                <div class="col-sm-5 d-flex align-items-end app-calendar-field">
                    <div class="form-check app-calendar-check">
                        <input class="form-check-input" type="checkbox" id="appCalendarEventAllDay" name="is_all_day">
                        <label class="form-check-label" for="appCalendarEventAllDay">All-day event</label>
                    </div>
                </div>
            </div>
            <div class="app-calendar-field mt-3">
                <label class="form-label" for="appCalendarEventDescription">Description</label>
                <textarea class="form-control" id="appCalendarEventDescription" name="description" rows="4"></textarea>
            </div>
            <div class="app-calendar-form-status mt-3" data-event-form-status></div>
        </div>
        <div class="app-calendar-modal-footer app-calendar-modal-actions">
            <button type="button" class="btn btn-outline-danger d-none" data-event-delete>Delete Event</button>
            <button type="button" class="btn btn-outline-light" data-close-event-panel>Cancel</button>
            <button type="submit" class="btn btn-primary" data-event-submit>Save Event</button>
        </div>
    </form>
</div>
<?php endif; ?>

<script src="assets/vendors/js/datepicker.min.js"></script>
<script src="assets/js/global-datepicker-init.js"></script>
<script src="assets/js/modules/apps/apps-calendar-page.js"></script>
<?php include 'includes/footer.php'; ?>


