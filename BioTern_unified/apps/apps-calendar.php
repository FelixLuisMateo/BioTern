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
                        <!-- Calendar events are now loaded dynamically. -->
                    </div>
                </div>
                <!-- [ Content Sidebar  ] end -->
                <!-- [ Main Area  ] start -->
                <div class="content-area" data-scrollbar-target="#psScrollbarInit">
                    <style>
                        @import url("assets/vendors/css/tui-date-picker.min.css");
                        @import url("assets/vendors/css/tui-time-picker.min.css");
                        @import url("assets/vendors/css/tui-calendar.min.css");

                        /* Elegant, theme-aware calendar redesign */
                        .calendar-toolbar-pro {
                            background: #ffffff;
                            border-bottom: none;
                            padding: 20px 32px 16px 32px;
                            border-radius: 18px 18px 0 0;
                            box-shadow: 0 2px 12px 0 rgba(15, 23, 42, 0.08);
                            display: flex;
                            align-items: center;
                            justify-content: space-between;
                        }
                        .calendar-toolbar-pro .calendar-action-btn {
                            align-items: center;
                            gap: 18px;
                        }
                        #staticMonthYear {
                            font-size: 1.25rem;
                            font-weight: 600;
                            color: #0f172a;
                            letter-spacing: 0.01em;
                            margin-right: 10px;
                        }
                        .calendar-toolbar-pro .move-day,
                        .calendar-toolbar-pro .move-today {
                            border-radius: 10px;
                            border: none;
                            background: #2563eb;
                            color: #fff;
                            width: 36px;
                            height: 36px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            font-size: 1.1rem;
                            margin: 0 2px;
                            box-shadow: 0 1px 4px 0 rgba(30,41,59,0.10);
                            transition: background 0.2s, color 0.2s, box-shadow 0.2s;
                            outline: none;
                        }
                        .calendar-toolbar-pro .move-day:hover,
                        .calendar-toolbar-pro .move-today:hover,
                        .calendar-toolbar-pro .move-day:focus,
                        .calendar-toolbar-pro .move-today:focus {
                            background: #1e40af;
                            color: #fff;
                            box-shadow: 0 2px 8px 0 rgba(30,41,59,0.18);
                        }
                        .calendar-toolbar-pro .move-day i,
                        .calendar-toolbar-pro .move-today i {
                            font-size: 1.1em;
                        }
                        .calendar-quick-create {
                            border-radius: 8px;
                            font-weight: 700;
                            letter-spacing: 0.01em;
                            background: #2563eb;
                            color: #fff;
                            border: none;
                            box-shadow: 0 1px 4px 0 rgba(30,41,59,0.10);
                            transition: background 0.2s;
                        }
                        .calendar-quick-create:hover {
                            background: #1e40af;
                        }
                        .calendar-event-status {
                            min-height: 20px;
                            font-size: 12px;
                        }
                        .content-area {
                            background: #f5f7fb;
                        }
                        #tui-calendar-init {
                            min-height: calc(100vh - 205px);
                            background: #ffffff;
                            border-radius: 0 0 18px 18px;
                            box-shadow: 0 4px 24px 0 rgba(15, 23, 42, 0.1);
                            border-top: none;
                            padding: 18px 18px 32px 18px;
                        }
                        /* Calendar grid light mode */
                        .tui-full-calendar-layout,
                        .tui-full-calendar-weekday-grid,
                        .tui-full-calendar-daygrid-cell {
                            background: #ffffff !important;
                        }
                        .tui-full-calendar-daygrid-cell {
                            border-color: #dbe3f0 !important;
                        }
                        .tui-full-calendar-weekday-grid-date {
                            color: #334155 !important;
                            font-weight: 500;
                        }
                        .tui-full-calendar-weekday-grid-date.tui-full-calendar-today {
                            background: #2563eb !important;
                            color: #fff !important;
                            border-radius: 50%;
                            font-weight: 700;
                        }
                        .tui-full-calendar-weekday-grid-date:hover {
                            background: #e8f0ff !important;
                            color: #1d4ed8 !important;
                        }
                        .tui-full-calendar-dayname {
                            color: #64748b !important;
                            font-weight: 700;
                            background: #ffffff !important;
                            border-color: #dbe3f0 !important;
                        }
                        .tui-full-calendar-dayname-container,
                        .tui-full-calendar-month-dayname {
                            background: #ffffff !important;
                            border-color: #dbe3f0 !important;
                        }
                        .modal-content {
                            border-radius: 18px;
                            box-shadow: 0 8px 32px 0 rgba(15, 23, 42, 0.16);
                            background: #ffffff;
                            color: #0f172a;
                            padding: 18px 24px 18px 24px;
                        }
                        .modal-header {
                            border-bottom: none;
                            padding-bottom: 0;
                            margin-bottom: 10px;
                        }
                        .modal-title {
                            font-weight: 700;
                            color: #3b82f6;
                            font-size: 1.3rem;
                            letter-spacing: 0.01em;
                        }
                        .modal-footer {
                            border-top: none;
                            padding-top: 0;
                            margin-top: 10px;
                        }
                        .form-control, .form-control-color, textarea, input[type="datetime-local"] {
                            border-radius: 10px;
                            border: 1.5px solid #cbd5e1;
                            background: #f8fafc;
                            color: #0f172a;
                            font-size: 1rem;
                            padding: 10px 14px;
                            margin-bottom: 8px;
                            transition: border-color 0.2s, background 0.2s, color 0.2s;
                        }
                        .form-control:focus, .form-control-color:focus, textarea:focus, input[type="datetime-local"]:focus {
                            border-color: #3b82f6;
                            background: #ffffff;
                            color: #0f172a;
                            outline: none;
                        }
                        input[type="datetime-local"]::-webkit-calendar-picker-indicator {
                            filter: none;
                        }
                        .form-label {
                            font-weight: 600;
                            color: #475569;
                            margin-bottom: 4px;
                        }
                        .form-check-input:checked {
                            background-color: #3b82f6;
                            border-color: #3b82f6;
                        }
                        .btn.btn-primary, .calendar-quick-create {
                            background: linear-gradient(90deg, #2563eb 0%, #3b82f6 100%);
                            border: none;
                            color: #fff;
                            font-weight: 600;
                            border-radius: 8px;
                            padding: 8px 22px;
                            box-shadow: 0 2px 8px 0 rgba(30,41,59,0.10);
                            letter-spacing: 0.01em;
                            transition: background 0.2s;
                        }
                        .btn.btn-primary:hover, .calendar-quick-create:hover {
                            background: #1e40af;
                        }
                        .btn.btn-outline-secondary {
                            border-radius: 8px;
                            border: 1.5px solid #cbd5e1;
                            color: #475569;
                            background: transparent;
                            font-weight: 600;
                            padding: 8px 22px;
                            transition: border-color 0.2s, color 0.2s;
                        }
                        .btn.btn-outline-secondary:hover {
                            border-color: #3b82f6;
                            color: #3b82f6;
                        }
                        /* Sidebar minimal */
                        .content-sidebar-header {
                            border-radius: 18px 0 0 0;
                            background: #ffffff;
                            box-shadow: 0 2px 8px 0 rgba(15, 23, 42, 0.08);
                            color: #0f172a;
                        }
                        .content-sidebar {
                            background: #ffffff;
                        }
                        body.app-skin-dark .calendar-toolbar-pro {
                            background: #1e293b;
                            box-shadow: 0 2px 12px 0 rgba(30, 41, 59, 0.12);
                        }
                        body.app-skin-dark #staticMonthYear {
                            color: #ffffff;
                        }
                        body.app-skin-dark .content-area {
                            background: #151c2c;
                        }
                        body.app-skin-dark #tui-calendar-init {
                            background: #232e47;
                            box-shadow: 0 4px 24px 0 rgba(30, 41, 59, 0.18);
                        }
                        body.app-skin-dark .tui-full-calendar-layout,
                        body.app-skin-dark .tui-full-calendar-weekday-grid,
                        body.app-skin-dark .tui-full-calendar-daygrid-cell {
                            background: #232e47 !important;
                        }
                        body.app-skin-dark .tui-full-calendar-daygrid-cell {
                            border-color: rgba(226, 232, 240, 0.18) !important;
                        }
                        body.app-skin-dark .tui-full-calendar-weekday-grid-date {
                            color: #cbd5e1 !important;
                        }
                        body.app-skin-dark .tui-full-calendar-weekday-grid-date:hover {
                            background: #334155 !important;
                            color: #ffffff !important;
                        }
                        body.app-skin-dark .tui-full-calendar-dayname,
                        body.app-skin-dark .tui-full-calendar-dayname-container,
                        body.app-skin-dark .tui-full-calendar-month-dayname {
                            color: #a5b4fc !important;
                            background: #151c2c !important;
                            border-color: rgba(226, 232, 240, 0.16) !important;
                        }
                        body.app-skin-dark .modal-content {
                            box-shadow: 0 8px 32px 0 rgba(30, 41, 59, 0.22);
                            background: #232e47;
                            color: #ffffff;
                        }
                        body.app-skin-dark .form-control,
                        body.app-skin-dark .form-control-color,
                        body.app-skin-dark textarea,
                        body.app-skin-dark input[type="datetime-local"] {
                            border-color: #334155;
                            background: #1a2236;
                            color: #ffffff;
                        }
                        body.app-skin-dark .form-control:focus,
                        body.app-skin-dark .form-control-color:focus,
                        body.app-skin-dark textarea:focus,
                        body.app-skin-dark input[type="datetime-local"]:focus {
                            background: #232e47;
                            color: #ffffff;
                        }
                        body.app-skin-dark input[type="datetime-local"]::-webkit-calendar-picker-indicator {
                            filter: invert(1) brightness(0.8) sepia(1) hue-rotate(180deg) saturate(3);
                        }
                        body.app-skin-dark .form-label {
                            color: #a5b4fc;
                        }
                        body.app-skin-dark .btn.btn-outline-secondary {
                            border-color: #334155;
                            color: #a5b4fc;
                        }
                        body.app-skin-dark .content-sidebar-header {
                            background: #232e47;
                            box-shadow: 0 2px 8px 0 rgba(30, 41, 59, 0.1);
                            color: #ffffff;
                        }
                        body.app-skin-dark .content-sidebar {
                            background: #151c2c;
                        }
                        /* Responsive */
                        @media (max-width: 768px) {
                            .calendar-toolbar-pro {
                                flex-direction: column;
                                align-items: flex-start;
                                padding: 16px 8px 10px 8px;
                            }
                            #staticMonthYear {
                                font-size: 1.3rem;
                            }
                            #tui-calendar-init {
                                padding: 8px 2px 16px 2px;
                            }
                        }
                    </style>
                    <div class="content-area-header sticky-top calendar-toolbar-pro">
                        <div class="page-header-left d-flex align-items-center gap-2">
                            <a href="javascript:void(0);" class="app-sidebar-open-trigger me-2">
                                <i class="feather-align-left fs-20"></i>
                            </a>
                            <div id="menu" class="d-flex align-items-center justify-content-between w-100">
                                <div class="d-flex calendar-action-btn align-items-center">
                                    <span id="staticMonthYear"></span>
                                    <button type="button" class="move-today ms-2" data-action="move-today" title="Go to Today">
                                        <i class="feather-clock calendar-icon fs-12" data-action="move-today"></i>
                                    </button>
                                    <button type="button" class="avatar-text avatar-md move-day ms-2" data-action="move-prev" title="Previous Month">
                                        <i class="feather-chevron-left fs-12" data-action="move-prev"></i>
                                    </button>
                                    <button type="button" class="avatar-text avatar-md move-day ms-1" data-action="move-next" title="Next Month">
                                        <i class="feather-chevron-right fs-12" data-action="move-next"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="page-header-right ms-auto">
                            <div class="hstack gap-2">
                                <button type="button" id="openEventModalBtn" class="btn btn-primary btn-sm calendar-quick-create">
                                    <i class="feather-plus me-1"></i>
                                    Add Event
                                </button>
                                <!-- Removed small date label near Add Event button -->
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
    <script>
    // Remove all events: forcibly clear demo/template events after every render and navigation
    window.ScheduleList = [];
    function forceClearCalendar() {
        if (window.cal && typeof window.cal.clear === 'function') {
            window.cal.clear();
        }
    }
    function updateStaticMonthYear() {
        if (!window.cal) return;
        var date = window.cal.getDate ? window.cal.getDate() : new Date();
        if (!(date instanceof Date)) {
            date = new Date(date);
        }
        var monthNames = [
            "January", "February", "March", "April", "May", "June",
            "July", "August", "September", "October", "November", "December"
        ];
        var text = monthNames[date.getMonth()] + ' ' + date.getFullYear();
        var el = document.getElementById('staticMonthYear');
        if (el) el.textContent = text;
    }
    var script = document.createElement('script');
    script.src = 'assets/js/apps-calendar-init.min.js';
    script.onload = function() {
        forceClearCalendar();
        updateStaticMonthYear();
        // Also clear after navigation or view change
        if (window.cal) {
            var origRender = window.cal.render;
            window.cal.render = function() {
                if (origRender) origRender.apply(window.cal, arguments);
                forceClearCalendar();
                updateStaticMonthYear();
            };
            var origChangeView = window.cal.changeView;
            window.cal.changeView = function() {
                if (origChangeView) origChangeView.apply(window.cal, arguments);
                forceClearCalendar();
                updateStaticMonthYear();
            };
            var origPrev = window.cal.prev;
            window.cal.prev = function() {
                if (origPrev) origPrev.apply(window.cal, arguments);
                forceClearCalendar();
                updateStaticMonthYear();
            };
            var origNext = window.cal.next;
            window.cal.next = function() {
                if (origNext) origNext.apply(window.cal, arguments);
                forceClearCalendar();
                updateStaticMonthYear();
            };
            var origToday = window.cal.today;
            window.cal.today = function() {
                if (origToday) origToday.apply(window.cal, arguments);
                forceClearCalendar();
                updateStaticMonthYear();
            };
        }
        // Also update on initial load
        setTimeout(updateStaticMonthYear, 100);
    };
    document.body.appendChild(script);

    // Always update month label after navigation button clicks
    document.addEventListener('DOMContentLoaded', function() {
        var navBtns = document.querySelectorAll('[data-action="move-prev"], [data-action="move-next"], [data-action="move-today"]');
        navBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                setTimeout(updateStaticMonthYear, 20);
            });
        });
        // Make Add Event button open the modal
        var addEventBtn = document.getElementById('openEventModalBtn');
        var eventModal = document.getElementById('calendarEventModal');
        if (addEventBtn && eventModal) {
            addEventBtn.addEventListener('click', function() {
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    var modal = bootstrap.Modal.getOrCreateInstance(eventModal);
                    modal.show();
                } else if (window.$ && window.$.fn && window.$.fn.modal) {
                    window.$(eventModal).modal('show');
                } else {
                    eventModal.style.display = 'block';
                    eventModal.classList.add('show');
                }
            });
        }
    });
    </script>
    <style>
    /* Remove scroll/overflow from calendar page */
    html, body, .main-content, .content-area, .content-sidebar {
        overflow: hidden !important;
    }
    </style>
    <script src="assets/js/apps-calendar-crud.js"></script>
    <!--! END: Apps Init !-->
</body>

</html>



