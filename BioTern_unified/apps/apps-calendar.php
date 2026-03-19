<?php
require_once dirname(__DIR__) . '/config/db.php';
$page_title = 'BioTern || Calendar';
include 'includes/header.php';
?>
            <!-- [ Main Content ] start -->
            <div class="main-content d-flex">
                <!-- [ Content Sidebar ] start -->
                <div class="content-sidebar content-sidebar-md" data-scrollbar-target="#psScrollbarInit">
                    <div class="content-sidebar-header bg-white sticky-top hstack justify-content-between">
                        <h4 class="fw-bolder mb-0">Calendar</h4>
                        <a href="javascript:void(0);" class="app-sidebar-close-trigger d-flex">
                            <i class="feather-x"></i>
                        </a>
                    </div>
                    <div class="content-sidebar-body">
                        <!--! BEGIN: [Events] !-->
                        <div class="p-4 c-pointer single-item schedule-item">
                            <div class="d-flex align-items-start">
                                <div class="wd-50 ht-50 bg-soft-primary text-primary lh-1 d-flex align-items-center justify-content-center flex-column rounded-2 schedule-date">
                                    <span class="fs-18 fw-bold mb-1 d-block">21</span>
                                    <span class="fs-10 text-semibold text-uppercase d-block">Dec</span>
                                </div>
                                <div class="ms-3 schedule-body">
                                    <div class="text-dark">
                                        <h6 class="fw-bold my-1 text-truncate-1-line">Standup Design Presentation</h6>
                                        <span class="fs-11 fw-normal text-muted">2:00pm - 5:00pm, Virtual Platform</span>
                                        <p class="fs-12 fw-normal text-muted my-3 text-truncate-2-line">Lorem ipsum quia dolor sit amet, consectetur, adipisci velit, abore et dolore magnam aliquam quaerat voluptatem.</p>
                                    </div>
                                    <div class="img-group lh-0 ms-3 justify-content-start">
                                        <a href="javascript:void(0)" class="avatar-image avatar-sm" data-bs-toggle="tooltip" data-bs-trigger="hover" title="Janette Dalton">
                                            <img src="assets/images/avatar/2.png" class="img-fluid" alt="image">
                                        </a>
                                        <a href="javascript:void(0)" class="avatar-image avatar-sm" data-bs-toggle="tooltip" data-bs-trigger="hover" title="Michael Ksen">
                                            <img src="assets/images/avatar/3.png" class="img-fluid" alt="image">
                                        </a>
                                        <a href="javascript:void(0)" class="avatar-image avatar-sm" data-bs-toggle="tooltip" data-bs-trigger="hover" title="Socrates Itumay">
                                            <img src="assets/images/avatar/4.png" class="img-fluid" alt="image">
                                        </a>
                                        <a href="javascript:void(0)" class="avatar-image avatar-sm" data-bs-toggle="tooltip" data-bs-trigger="hover" title="Marianne Audrey">
                                            <img src="assets/images/avatar/5.png" class="img-fluid" alt="image">
                                        </a>
                                        <a href="javascript:void(0)" class="avatar-image avatar-sm" data-bs-toggle="tooltip" data-bs-trigger="hover" title="Marianne Audrey">
                                            <img src="assets/images/avatar/6.png" class="img-fluid" alt="image">
                                        </a>
                                        <a href="javascript:void(0)" class="avatar-text avatar-sm" data-bs-toggle="tooltip" data-bs-trigger="hover" title="Explorer More">
                                            <i class="feather-more-horizontal"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!--! BEGIN: [Events] !-->
                        <div class="p-4 border-top c-pointer single-item schedule-item">
                            <div class="d-flex align-items-start">
                                <div class="wd-50 ht-50 bg-soft-danger text-danger lh-1 d-flex align-items-center justify-content-center flex-column rounded-2 schedule-date">
                                    <span class="fs-18 fw-bold mb-1 d-block">14</span>
                                    <span class="fs-10 text-semibold text-uppercase d-block">Dec</span>
                                </div>
                                <div class="ms-3 schedule-body">
                                    <div class="text-dark">
                                        <h6 class="fw-bold my-1 text-truncate-1-line">Company Start Concept</h6>
                                        <span class="fs-11 fw-normal text-muted">8:00am - 9:00am, Engineering Room</span>
                                        <p class="fs-12 fw-normal text-muted my-3 text-truncate-2-line">Lorem ipsum quia dolor sit amet, consectetur, adipisci velit, abore et dolore magnam aliquam quaerat voluptatem.</p>
                                    </div>
                                    <div class="img-group lh-0 ms-3 justify-content-start">
                                        <a href="javascript:void(0)" class="avatar-image avatar-sm" data-bs-toggle="tooltip" data-bs-trigger="hover" title="Janette Dalton">
                                            <img src="assets/images/avatar/2.png" class="img-fluid" alt="image">
                                        </a>
                                        <a href="javascript:void(0)" class="avatar-image avatar-sm" data-bs-toggle="tooltip" data-bs-trigger="hover" title="Michael Ksen">
                                            <img src="assets/images/avatar/3.png" class="img-fluid" alt="image">
                                        </a>
                                        <a href="javascript:void(0)" class="avatar-image avatar-sm" data-bs-toggle="tooltip" data-bs-trigger="hover" title="Socrates Itumay">
                                            <img src="assets/images/avatar/4.png" class="img-fluid" alt="image">
                                        </a>
                                        <a href="javascript:void(0)" class="avatar-image avatar-sm" data-bs-toggle="tooltip" data-bs-trigger="hover" title="Marianne Audrey">
                                            <img src="assets/images/avatar/5.png" class="img-fluid" alt="image">
                                        </a>
                                        <a href="javascript:void(0)" class="avatar-image avatar-sm" data-bs-toggle="tooltip" data-bs-trigger="hover" title="Marianne Audrey">
                                            <img src="assets/images/avatar/6.png" class="img-fluid" alt="image">
                                        </a>
                                        <a href="javascript:void(0)" class="avatar-text avatar-sm" data-bs-toggle="tooltip" data-bs-trigger="hover" title="Explorer More">
                                            <i class="feather-more-horizontal"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- [ Content Sidebar  ] end -->
                <!-- [ Main Area  ] start -->
                <div class="content-area" data-scrollbar-target="#psScrollbarInit">
                    <style>
                        @import url("assets/vendors/css/tui-date-picker.min.css");
                        @import url("assets/vendors/css/tui-time-picker.min.css");
                        @import url("assets/vendors/css/tui-calendar.min.css");

                        .calendar-toolbar-pro {
                            background: linear-gradient(135deg, #f8fbff 0%, #edf4ff 100%);
                            border-bottom: 1px solid #d9e5ff;
                            padding: 10px 14px;
                        }

                        .calendar-toolbar-pro .calendar-dropdown-btn,
                        .calendar-toolbar-pro .move-today,
                        .calendar-toolbar-pro .move-day {
                            border-radius: 10px;
                            border: 1px solid #cadbff;
                            background: #ffffff;
                            color: #0f172a;
                        }

                        .calendar-toolbar-pro .move-day {
                            width: 36px;
                            height: 36px;
                        }

                        .calendar-toolbar-pro .render-range {
                            font-weight: 700;
                            color: #1e293b;
                        }

                        .calendar-quick-create {
                            border-radius: 10px;
                            font-weight: 700;
                            letter-spacing: 0.01em;
                        }

                        .calendar-event-status {
                            min-height: 20px;
                            font-size: 12px;
                        }

                        .content-area {
                            background: #f6f9ff;
                        }

                        #tui-calendar-init {
                            min-height: calc(100vh - 205px);
                            background: #ffffff;
                            border-top: 1px solid #dbe7ff;
                        }

                        .app-skin-dark .calendar-toolbar-pro {
                            background: linear-gradient(135deg, #15274a 0%, #0f1d3b 100%);
                            border-bottom: 1px solid #2b3e68;
                        }

                        .app-skin-dark .calendar-toolbar-pro .calendar-dropdown-btn,
                        .app-skin-dark .calendar-toolbar-pro .move-today,
                        .app-skin-dark .calendar-toolbar-pro .move-day {
                            border-color: #3b4f79;
                            background: #102344;
                            color: #dce9ff;
                        }

                        .app-skin-dark .calendar-toolbar-pro .render-range {
                            color: #dce9ff;
                        }

                        .app-skin-dark .content-area {
                            background: #0d1b37;
                        }

                        .app-skin-dark #tui-calendar-init {
                            background: #0f1f40;
                            border-top-color: #243760;
                        }
                    </style>
                    <div class="content-area-header sticky-top calendar-toolbar-pro">
                        <div class="page-header-left d-flex align-items-center gap-2">
                            <a href="javascript:void(0);" class="app-sidebar-open-trigger me-2">
                                <i class="feather-align-left fs-20"></i>
                            </a>
                            <div id="menu" class="d-flex align-items-center justify-content-between">
                                <div class="d-flex calendar-action-btn">
                                    <div class="dropdown me-1">
                                        <button id="dropdownMenu-calendarType" class="dropdown-toggle calendar-dropdown-btn" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" data-bs-offset="0,17">
                                            <i id="calendarTypeIcon" class="feather-grid calendar-icon fs-12 me-1"></i>
                                            <span id="calendarTypeName">Monthly</span>
                                        </button>
                                        <ul class="dropdown-menu" role="menu" aria-labelledby="dropdownMenu-calendarType">
                                            <li role="presentation">
                                                <div class="dropdown-item c-pointer" role="menuitem" data-action="toggle-daily">
                                                    <i class="feather-list calendar-icon me-3"></i>
                                                    <span>Daily</span>
                                                </div>
                                            </li>
                                            <li role="presentation">
                                                <div class="dropdown-item c-pointer" role="menuitem" data-action="toggle-weekly">
                                                    <i class="feather-umbrella calendar-icon me-3"></i>
                                                    <span>Weekly</span>
                                                </div>
                                            </li>
                                            <li role="presentation">
                                                <div class="dropdown-item c-pointer" role="menuitem" data-action="toggle-weeks2">
                                                    <i class="feather-sliders calendar-icon me-3"></i>
                                                    <span>Weeks (2)</span>
                                                </div>
                                            </li>
                                            <li role="presentation">
                                                <div class="dropdown-item" role="menuitem" data-action="toggle-weeks3">
                                                    <i class="feather-framer calendar-icon me-3"></i>
                                                    <span>Weeks (3)</span>
                                                </div>
                                            </li>
                                            <li role="presentation">
                                                <div class="dropdown-item c-pointer" role="menuitem" data-action="toggle-monthly">
                                                    <i class="feather-grid calendar-icon me-3"></i>
                                                    <span>Monthly</span>
                                                </div>
                                            </li>
                                            <li role="presentation" class="dropdown-divider"></li>
                                            <li role="presentation">
                                                <div class="dropdown-item" role="menuitem" data-action="toggle-workweek">
                                                    <div class="custom-control custom-checkbox">
                                                        <input type="checkbox" class="custom-control-input chalendar-checkbox" id="viewWeekendsSchedules" value="toggle-workweek" checked="checked">
                                                        <label class="custom-control-label c-pointer" for="viewWeekendsSchedules">
                                                            <span class="fs-12 fw-bold">Show Weekends</span>
                                                        </label>
                                                    </div>
                                                </div>
                                            </li>
                                            <li role="presentation">
                                                <div class="dropdown-item" role="menuitem" data-action="toggle-start-day-1">
                                                    <div class="custom-control custom-checkbox">
                                                        <input type="checkbox" class="custom-control-input chalendar-checkbox" id="viewStartSchedules" value="toggle-start-day-1">
                                                        <label class="custom-control-label c-pointer" for="viewStartSchedules">
                                                            <span class="fs-12 fw-bold">Start Week on Monday</span>
                                                        </label>
                                                    </div>
                                                </div>
                                            </li>
                                            <li role="presentation">
                                                <div class="dropdown-item" role="menuitem" data-action="toggle-narrow-weekend">
                                                    <div class="custom-control custom-checkbox">
                                                        <input type="checkbox" class="custom-control-input chalendar-checkbox" id="viewNarrowerSchedules" value="toggle-narrow-weekend">
                                                        <label class="custom-control-label c-pointer" for="viewNarrowerSchedules">
                                                            <span class="fs-12 fw-bold">Narrower than weekdays</span>
                                                        </label>
                                                    </div>
                                                </div>
                                            </li>
                                        </ul>
                                    </div>
                                    <div class="menu-navi d-none d-sm-flex">
                                        <button type="button" class="move-today" data-action="move-today">
                                            <i class="feather-clock calendar-icon me-1 fs-12" data-action="move-today"></i>
                                            <span>Today</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="page-header-right ms-auto">
                            <div class="hstack gap-2">
                                <button type="button" id="importCelebrationsBtn" class="btn btn-warning btn-sm calendar-quick-create d-none">
                                    <i class="feather-star me-1"></i>
                                    PH Holidays + Celebrations
                                </button>
                                <button type="button" id="openEventModalBtn" class="btn btn-primary btn-sm calendar-quick-create">
                                    <i class="feather-plus me-1"></i>
                                    Add Event
                                </button>
                                <div id="renderRange" class="render-range d-none d-sm-flex"></div>
                                <div class="btn-group gap-1 menu-navi" role="group">
                                    <button type="button" class="avatar-text avatar-md move-day" data-action="move-prev">
                                        <i class="feather-chevron-left fs-12" data-action="move-prev"></i>
                                    </button>
                                    <button type="button" class="avatar-text avatar-md move-day" data-action="move-next">
                                        <i class="feather-chevron-right fs-12" data-action="move-next"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="content-area-body p-0">
                        <div id="tui-calendar-init"></div>
                    </div>
                </div>
                <!-- [ Content Area ] end -->
            </div>
            <!-- [ Main Content ] end -->
        </div>
    </main>
    <!--! ================================================================ !-->
    <!--! [End] Main Content !-->
    <!--! ================================================================ !-->
    <!--! ================================================================ !-->
    <!--! [Start] Search Modal !-->
    <!--! ================================================================ !-->
    <div class="modal fade-scale" id="searchModal" aria-hidden="true" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-top modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header search-form py-0">
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="feather-search fs-4 text-muted"></i>
                        </span>
                        <input type="text" class="form-control search-input-field" placeholder="Search...">
                        <span class="input-group-text">
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </span>
                    </div>
                </div>
                <div class="modal-body">
                    <div class="searching-for mb-5">
                        <h4 class="fs-13 fw-normal text-gray-600 mb-3">I'm searching for...</h4>
                        <div class="row g-1">
                            <div class="col-md-4 col-xl-2">
                                <a href="javascript:void(0);" class="d-flex align-items-center gap-2 px-3 lh-lg border rounded-pill">
                                    <i class="feather-compass"></i>
                                    <span>Recent</span>
                                </a>
                            </div>
                            <div class="col-md-4 col-xl-2">
                                <a href="javascript:void(0);" class="d-flex align-items-center gap-2 px-3 lh-lg border rounded-pill">
                                    <i class="feather-command"></i>
                                    <span>Command</span>
                                </a>
                            </div>
                            <div class="col-md-4 col-xl-2">
                                <a href="javascript:void(0);" class="d-flex align-items-center gap-2 px-3 lh-lg border rounded-pill">
                                    <i class="feather-users"></i>
                                    <span>Peoples</span>
                                </a>
                            </div>
                            <div class="col-md-4 col-xl-2">
                                <a href="javascript:void(0);" class="d-flex align-items-center gap-2 px-3 lh-lg border rounded-pill">
                                    <i class="feather-file"></i>
                                    <span>Files</span>
                                </a>
                            </div>
                            <div class="col-md-4 col-xl-2">
                                <a href="javascript:void(0);" class="d-flex align-items-center gap-2 px-3 lh-lg border rounded-pill">
                                    <i class="feather-video"></i>
                                    <span>Medias</span>
                                </a>
                            </div>
                            <div class="col-md-4 col-xl-2">
                                <a href="javascript:void(0);" class="d-flex align-items-center gap-2 px-3 lh-lg border rounded-pill">
                                    <span>More</span>
                                    <i class="feather-chevron-down"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="recent-result mb-5">
                        <h4 class="fs-13 fw-normal text-gray-600 mb-3">Recnet <span class="badge small bg-gray-200 rounded ms-1 text-dark">3</span></h4>
                        <div class="d-flex align-items-center justify-content-between mb-4">
                            <a href="javascript:void(0);" class="d-flex align-items-start gap-3">
                                <i class="feather-airplay fs-5"></i>
                                <div class="fs-13 fw-semibold">Overview Home redesign</div>
                            </a>
                            <a href="javascript:void(0);" class="badge border rounded text-dark">/<i class="feather-command ms-1"></i></a>
                        </div>
                        <div class="d-flex align-items-center justify-content-between mb-4">
                            <a href="javascript:void(0);" class="d-flex align-items-start gap-3">
                                <i class="feather-file-plus fs-5"></i>
                                <div class="fs-13 fw-semibold">Create new eocument</div>
                            </a>
                            <a href="javascript:void(0);" class="badge border rounded text-dark">N /<i class="feather-command ms-1"></i></a>
                        </div>
                        <div class="d-flex align-items-center justify-content-between">
                            <a href="javascript:void(0);" class="d-flex align-items-start gap-3">
                                <i class="feather-user-plus fs-5"></i>
                                <div class="fs-13 fw-semibold">Invite project colleagues</div>
                            </a>
                            <a href="javascript:void(0);" class="badge border rounded text-dark">P /<i class="feather-command ms-1"></i></a>
                        </div>
                    </div>
                    <div class="command-result mb-5">
                        <h4 class="fs-13 fw-normal text-gray-600 mb-3">Command <span class="badge small bg-gray-200 rounded ms-1 text-dark">5</span></h4>
                        <div class="d-flex align-items-center justify-content-between mb-4">
                            <a href="javascript:void(0);" class="d-flex align-items-start gap-3">
                                <i class="feather-user fs-5"></i>
                                <div class="fs-13 fw-semibold">My profile</div>
                            </a>
                            <a href="javascript:void(0);" class="badge border rounded text-dark">P /<i class="feather-command ms-1"></i></a>
                        </div>
                        <div class="d-flex align-items-center justify-content-between mb-4">
                            <a href="javascript:void(0);" class="d-flex align-items-start gap-3">
                                <i class="feather-users fs-5"></i>
                                <div class="fs-13 fw-semibold">Team profile</div>
                            </a>
                            <a href="javascript:void(0);" class="badge border rounded text-dark">T /<i class="feather-command ms-1"></i></a>
                        </div>
                        <div class="d-flex align-items-center justify-content-between mb-4">
                            <a href="javascript:void(0);" class="d-flex align-items-start gap-3">
                                <i class="feather-user-plus fs-5"></i>
                                <div class="fs-13 fw-semibold">Invite colleagues</div>
                            </a>
                            <a href="javascript:void(0);" class="badge border rounded text-dark">I /<i class="feather-command ms-1"></i></a>
                        </div>
                        <div class="d-flex align-items-center justify-content-between mb-4">
                            <a href="javascript:void(0);" class="d-flex align-items-start gap-3">
                                <i class="feather-briefcase fs-5"></i>
                                <div class="fs-13 fw-semibold">Create new project</div>
                            </a>
                            <a href="javascript:void(0);" class="badge border rounded text-dark">CP /<i class="feather-command ms-1"></i></a>
                        </div>
                        <div class="d-flex align-items-center justify-content-between">
                            <a href="javascript:void(0);" class="d-flex align-items-start gap-3">
                                <i class="feather-life-buoy fs-5"></i>
                                <div class="fs-13 fw-semibold">Support center</div>
                            </a>
                            <a href="javascript:void(0);" class="badge border rounded text-dark">SC /<i class="feather-command ms-1"></i></a>
                        </div>
                    </div>
                    <div class="file-result mb-4">
                        <h4 class="fs-13 fw-normal text-gray-600 mb-3">Files <span class="badge small bg-gray-200 rounded ms-1 text-dark">3</span></h4>
                        <div class="d-flex align-items-center justify-content-between mb-4">
                            <a href="javascript:void(0);" class="d-flex align-items-start gap-3">
                                <i class="feather-folder-plus fs-5"></i>
                                <div class="fs-13 fw-semibold">Overview Design Project <span class="fs-12 fw-normal text-muted">(56.74 MB)</span></div>
                            </a>
                            <a href="javascript:void(0);" class="file-download"><i class="feather-download"></i></a>
                        </div>
                        <div class="d-flex align-items-center justify-content-between mb-4">
                            <a href="javascript:void(0);" class="d-flex align-items-start gap-3">
                                <i class="feather-folder-plus fs-5"></i>
                                <div class="fs-13 fw-semibold">Admin Dashboard Project <span class="fs-12 fw-normal text-muted">(46.83 MB)</span></div>
                            </a>
                            <a href="javascript:void(0);" class="file-download"><i class="feather-download"></i></a>
                        </div>
                        <div class="d-flex align-items-center justify-content-between">
                            <a href="javascript:void(0);" class="d-flex align-items-start gap-3">
                                <i class="feather-folder-plus fs-5"></i>
                                <div class="fs-13 fw-semibold">Overview Home Project <span class="fs-12 fw-normal text-muted">(68.59 MB)</span></div>
                            </a>
                            <a href="javascript:void(0);" class="file-download"><i class="feather-download"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="calendarEventModal" tabindex="-1" aria-labelledby="calendarEventModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="calendarEventModalLabel">Calendar Event</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="calendarEventForm">
                    <input type="hidden" id="calendarEventId" value="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="calendarEventTitle" class="form-label">Title</label>
                            <input type="text" class="form-control" id="calendarEventTitle" required>
                        </div>
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label for="calendarEventStart" class="form-label">Start</label>
                                <input type="datetime-local" class="form-control" id="calendarEventStart" required>
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="calendarEventEnd" class="form-label">End</label>
                                <input type="datetime-local" class="form-control" id="calendarEventEnd" required>
                            </div>
                        </div>
                        <div class="mt-3 mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="calendarEventAllDay">
                                <label class="form-check-label" for="calendarEventAllDay">All day</label>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-12 col-md-8">
                                <label for="calendarEventLocation" class="form-label">Location</label>
                                <input type="text" class="form-control" id="calendarEventLocation" placeholder="Optional">
                            </div>
                            <div class="col-12 col-md-4">
                                <label for="calendarEventColor" class="form-label">Color</label>
                                <input type="color" class="form-control form-control-color w-100" id="calendarEventColor" value="#0d6efd">
                            </div>
                        </div>
                        <div class="mt-3">
                            <label for="calendarEventDescription" class="form-label">Description</label>
                            <textarea id="calendarEventDescription" class="form-control" rows="3" placeholder="Optional"></textarea>
                        </div>
                        <div id="calendarEventStatus" class="calendar-event-status mt-2"></div>
                    </div>
                    <div class="modal-footer justify-content-between">
                        <button type="button" id="calendarEventDeleteBtn" class="btn btn-outline-danger d-none">Delete</button>
                        <div class="d-flex gap-2 ms-auto">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" id="calendarEventSaveBtn" class="btn btn-primary">Save Event</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!--! ================================================================ !-->
    <!--! Footer Script !-->
    <!--! ================================================================ !-->
    <!--! BEGIN: Vendors JS !-->
    <script src="assets/vendors/js/vendors.min.js"></script>
    <!-- vendors.min.js {always must need to be top} -->
    <script src="assets/vendors/js/tui-code-snippet.min.js"></script>
    <script src="assets/vendors/js/tui-time-picker.min.js"></script>
    <script src="assets/vendors/js/tui-date-picker.min.js"></script>
    <script src="assets/vendors/js/moment.min.js"></script>
    <script src="assets/vendors/js/chance.min.js"></script>
    <script src="assets/vendors/js/tui-calendar.min.js"></script>
    <script src="assets/vendors/js/tui-calendars.min.js"></script>
    <script src="assets/vendors/js/tui-schedules.min.js"></script>
    <!--! END: Vendors JS !-->
    <!--! BEGIN: Apps Init  !-->
    <script src="assets/js/common-init.min.js"></script>
    <script src="assets/js/apps-calendar-init.min.js"></script>
    <script src="assets/js/apps-calendar-crud.js"></script>
    <!--! END: Apps Init !-->
</body>

</html>



