<?php
require_once dirname(__DIR__) . '/config/db.php';

$page_title = 'BioTern || Calendar';
$page_styles = [
    'assets/css/modules/apps/apps-calendar-page.css',
];

$calendar_script_name = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$calendar_unified_pos = stripos($calendar_script_name, '/BioTern_unified/');
$calendar_base_href = ($calendar_unified_pos !== false)
    ? substr($calendar_script_name, 0, $calendar_unified_pos) . '/BioTern_unified/'
    : '/BioTern_unified/';
$calendar_events_endpoint = $calendar_base_href . 'calendar_events.php';

include 'includes/header.php';
?>
<div class="app-calendar-shell" data-calendar-app data-events-endpoint="<?php echo htmlspecialchars($calendar_events_endpoint, ENT_QUOTES, 'UTF-8'); ?>">
    <section class="app-calendar-hero">
        <div class="app-calendar-hero-copy">
            <span class="app-calendar-kicker">BioTern Calendar</span>
            <h1 class="app-calendar-title">Calendar</h1>
            <p class="app-calendar-subtitle">Philippine events, OJT birthdays, and saved schedule entries in one view.</p>
        </div>
    </section>

    <div class="row g-4 align-items-start">
        <div class="col-12 col-xxl-9">
            <section class="card app-calendar-board">
                <div class="card-body">
                    <div class="app-calendar-toolbar">
                        <div class="app-calendar-toolbar-copy">
                            <span class="app-calendar-toolbar-label">Monthly Planner</span>
                            <h2 class="app-calendar-month-title" data-month-label>Calendar</h2>
                            <p class="app-calendar-toolbar-subtitle">See the whole month at once, then drill into the day that matters.</p>
                        </div>
                        <div class="app-calendar-toolbar-actions">
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

<script src="assets/js/modules/apps/apps-calendar-page.js"></script>
<?php include 'includes/footer.php'; ?>
