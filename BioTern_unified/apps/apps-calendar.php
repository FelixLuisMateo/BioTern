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

                        /* Unified calendar theme tokens */
                        .content-area {
                            --calendar-shell-bg: #f5f7fb;
                            --calendar-panel-bg: #ffffff;
                            --calendar-panel-shadow: 0 4px 24px 0 rgba(15, 23, 42, 0.1);
                            --calendar-toolbar-bg: #ffffff;
                            --calendar-toolbar-text: #0f172a;
                            --calendar-grid-bg: #ffffff;
                            --calendar-grid-border: #dbe3f0;
                            --calendar-grid-text: #334155;
                            --calendar-grid-hover: #f8fbff;
                            --calendar-dayname-bg: #ffffff;
                            --calendar-dayname-text: #64748b;
                            --calendar-sidebar-bg: #ffffff;
                            --calendar-sidebar-header-bg: #ffffff;
                            --calendar-sidebar-text: #0f172a;
                            background: var(--calendar-shell-bg);
                        }
                        .app-skin-dark .content-area {
                            --calendar-shell-bg: #151c2c;
                            --calendar-panel-bg: #232e47;
                            --calendar-panel-shadow: 0 4px 24px 0 rgba(30, 41, 59, 0.18);
                            --calendar-toolbar-bg: #1e293b;
                            --calendar-toolbar-text: #ffffff;
                            --calendar-grid-bg: #2a3550;
                            --calendar-grid-border: rgba(226, 232, 240, 0.16);
                            --calendar-grid-text: #f8fafc;
                            --calendar-grid-hover: #334155;
                            --calendar-dayname-bg: #1b2438;
                            --calendar-dayname-text: #c7d2fe;
                            --calendar-sidebar-bg: #151c2c;
                            --calendar-sidebar-header-bg: #232e47;
                            --calendar-sidebar-text: #ffffff;
                        }

                        /* Elegant, theme-aware calendar redesign */
                        .main-content {
                            gap: 0;
                        }
                        .content-sidebar {
                            width: 240px !important;
                            min-width: 240px !important;
                            max-width: 240px !important;
                            flex: 0 0 240px;
                        }
                        .content-sidebar-body {
                            padding: 0;
                        }
                        .content-area {
                            flex: 1 1 auto;
                            min-width: 0;
                        }
                        .calendar-toolbar-pro {
                            background: var(--calendar-toolbar-bg);
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
                            color: var(--calendar-toolbar-text);
                            letter-spacing: 0.01em;
                            margin-right: 10px;
                        }
                        .calendar-toolbar-pro .move-day,
                        .calendar-toolbar-pro .move-today {
                            border-radius: 12px;
                            border: 1px solid rgba(37, 99, 235, 0.12);
                            background: #eff6ff;
                            color: #1d4ed8;
                            width: 38px;
                            height: 38px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            font-size: 1rem;
                            margin: 0 2px;
                            box-shadow: none;
                            transition: background 0.2s, color 0.2s, border-color 0.2s, transform 0.2s;
                            outline: none;
                        }
                        .calendar-toolbar-pro .move-day:hover,
                        .calendar-toolbar-pro .move-today:hover,
                        .calendar-toolbar-pro .move-day:focus,
                        .calendar-toolbar-pro .move-today:focus {
                            background: #dbeafe;
                            color: #1e3a8a;
                            border-color: rgba(37, 99, 235, 0.2);
                            transform: translateY(-1px);
                        }
                        .calendar-toolbar-pro .move-day i,
                        .calendar-toolbar-pro .move-today i {
                            font-size: 0.95rem;
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
                        #tui-calendar-init {
                            min-height: calc(100vh - 205px);
                            background: var(--calendar-panel-bg);
                            border-radius: 0 0 18px 18px;
                            box-shadow: var(--calendar-panel-shadow);
                            border-top: none;
                            padding: 18px 18px 32px 18px;
                        }
                        /* Calendar grid light mode */
                        .tui-full-calendar-layout,
                        .tui-full-calendar-weekday-grid,
                        .tui-full-calendar-daygrid-cell {
                            background: var(--calendar-grid-bg) !important;
                        }
                        .tui-full-calendar-daygrid-cell {
                            border-color: var(--calendar-grid-border) !important;
                        }
                        .tui-full-calendar-weekday-grid-line {
                            cursor: pointer;
                            transition: background-color 0.2s ease;
                        }
                        .tui-full-calendar-weekday-grid-date {
                            color: var(--calendar-grid-text) !important;
                            font-weight: 500;
                        }
                        .tui-full-calendar-weekday-grid-line:hover {
                            background: var(--calendar-grid-hover) !important;
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
                            color: var(--calendar-dayname-text) !important;
                            font-weight: 700;
                            background: var(--calendar-dayname-bg) !important;
                            border-color: var(--calendar-grid-border) !important;
                        }
                        .tui-full-calendar-dayname-container,
                        .tui-full-calendar-month-dayname {
                            background: var(--calendar-dayname-bg) !important;
                            border-color: var(--calendar-grid-border) !important;
                        }
                        #calendarEventModal .modal-dialog {
                            max-width: 620px;
                            margin: 1rem auto;
                        }
                        #calendarEventModal .modal-content {
                            border: 0;
                            border-radius: 24px;
                            box-shadow: 0 20px 60px rgba(15, 23, 42, 0.18);
                            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
                            color: #0f172a;
                            padding: 0;
                            overflow: hidden;
                        }
                        #calendarEventModal .modal-header {
                            border-bottom: 1px solid rgba(148, 163, 184, 0.18);
                            padding: 18px 20px 14px;
                            margin-bottom: 0;
                            align-items: flex-start;
                        }
                        #calendarEventModal .modal-title {
                            font-weight: 700;
                            color: #3b82f6;
                            font-size: 1.3rem;
                            letter-spacing: 0.01em;
                        }
                        .calendar-modal-subtitle {
                            color: #64748b;
                            font-size: 0.93rem;
                            margin-top: 4px;
                        }
                        .calendar-modal-icon {
                            width: 40px;
                            height: 40px;
                            border-radius: 12px;
                            display: inline-flex;
                            align-items: center;
                            justify-content: center;
                            background: linear-gradient(135deg, #dbeafe 0%, #eff6ff 100%);
                            color: #2563eb;
                            box-shadow: inset 0 0 0 1px rgba(37, 99, 235, 0.12);
                            font-size: 1rem;
                            flex-shrink: 0;
                        }
                        .calendar-modal-header-copy {
                            min-width: 0;
                        }
                        #calendarEventModal .modal-body {
                            padding: 16px 20px 24px;
                        }
                        .calendar-form-grid {
                            display: grid;
                            gap: 12px;
                        }
                        .calendar-form-card {
                            background: rgba(255, 255, 255, 0.88);
                            border: 1px solid #e2e8f0;
                            border-radius: 16px;
                            padding: 14px;
                            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
                            backdrop-filter: blur(6px);
                        }
                        .calendar-form-card-title {
                            font-size: 0.84rem;
                            font-weight: 700;
                            letter-spacing: 0.08em;
                            text-transform: uppercase;
                            color: #64748b;
                            margin-bottom: 10px;
                        }
                        .calendar-form-card-title-row {
                            display: flex;
                            align-items: center;
                            justify-content: space-between;
                            gap: 12px;
                            margin-bottom: 10px;
                        }
                        .calendar-inline-meta {
                            display: flex;
                            align-items: center;
                            justify-content: space-between;
                            gap: 12px;
                            padding: 10px 12px;
                            border-radius: 14px;
                            background: linear-gradient(135deg, #eff6ff 0%, #f8fbff 100%);
                            border: 1px solid rgba(37, 99, 235, 0.12);
                        }
                        .calendar-inline-meta .form-check {
                            margin: 0;
                        }
                        .calendar-inline-meta-label {
                            font-size: 0.82rem;
                            color: #64748b;
                            margin-bottom: 2px;
                        }
                        .calendar-inline-meta-value {
                            font-size: 0.98rem;
                            font-weight: 700;
                            color: #0f172a;
                        }
                        .calendar-compact-switch {
                            display: inline-flex;
                            align-items: center;
                            gap: 8px;
                            padding: 6px 10px;
                            border-radius: 999px;
                            background: #eff6ff;
                            border: 1px solid rgba(37, 99, 235, 0.12);
                        }
                        .calendar-color-field {
                            display: inline-flex;
                            align-items: center;
                            gap: 10px;
                            padding: 8px 10px;
                            border-radius: 14px;
                            background: #f8fafc;
                            border: 1px solid #dbe4f0;
                        }
                        .calendar-color-swatch {
                            width: 22px;
                            height: 22px;
                            border-radius: 999px;
                            background: linear-gradient(135deg, #2563eb 0%, #60a5fa 100%);
                            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.3);
                            flex-shrink: 0;
                        }
                        .calendar-color-field .form-control-color {
                            width: 64px;
                            min-width: 64px;
                            margin-bottom: 0;
                            background: transparent;
                            border: 0;
                            box-shadow: none;
                            padding: 0;
                            min-height: 28px;
                        }
                        .calendar-timing-grid {
                            display: grid;
                            grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
                            gap: 16px;
                            align-items: end;
                        }
                        .calendar-time-group {
                            display: grid;
                            gap: 8px;
                            min-width: 0;
                        }
                        .calendar-time-group-head {
                            display: flex;
                            flex-direction: column;
                            gap: 2px;
                        }
                        .calendar-time-group-kicker {
                            font-size: 0.72rem;
                            font-weight: 700;
                            letter-spacing: 0.08em;
                            text-transform: uppercase;
                            color: #94a3b8;
                        }
                        .calendar-time-group-title {
                            font-size: 1rem;
                            font-weight: 700;
                            color: #0f172a;
                        }
                        .calendar-time-group-grid {
                            display: grid;
                            grid-template-columns: 1fr;
                            gap: 10px;
                        }
                        .calendar-picker-field {
                            display: flex;
                            align-items: center;
                            gap: 10px;
                            border-radius: 12px;
                            background: #ffffff;
                            border: 1px solid #cbd5e1;
                            padding: 0 12px;
                            transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
                            min-width: 0;
                            min-height: 46px;
                        }
                        .calendar-picker-field:focus-within {
                            border-color: #3b82f6;
                            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.12);
                        }
                        .calendar-picker-icon {
                            width: 30px;
                            height: 30px;
                            border-radius: 10px;
                            display: inline-flex;
                            align-items: center;
                            justify-content: center;
                            background: #eff6ff;
                            color: #2563eb;
                            flex-shrink: 0;
                        }
                        .calendar-picker-field .form-control {
                            margin-bottom: 0;
                            background: transparent;
                            border: 0;
                            box-shadow: none;
                            padding: 0;
                            min-height: 44px;
                            height: 44px;
                            line-height: 44px;
                            color-scheme: light;
                            min-width: 0;
                            width: 100%;
                            display: block;
                            appearance: none;
                            -webkit-appearance: none;
                        }
                        .calendar-picker-field .form-control:focus {
                            box-shadow: none !important;
                            background: transparent !important;
                        }
                        .calendar-hidden-datetime {
                            position: absolute;
                            width: 1px;
                            height: 1px;
                            padding: 0;
                            margin: -1px;
                            overflow: hidden;
                            clip: rect(0, 0, 0, 0);
                            white-space: nowrap;
                            border: 0;
                        }
                        .calendar-timing-note {
                            margin-top: 2px;
                            font-size: 0.84rem;
                            color: #64748b;
                            line-height: 1.45;
                        }
                        .calendar-timing-arrow {
                            width: 48px;
                            height: 48px;
                            border-radius: 999px;
                            display: inline-flex;
                            align-items: center;
                            justify-content: center;
                            background: #eff6ff;
                            color: #2563eb;
                            border: 1px solid rgba(37, 99, 235, 0.12);
                            margin-bottom: 4px;
                            flex-shrink: 0;
                        }
                        #calendarEventModal .modal-footer {
                            border-top: 1px solid rgba(148, 163, 184, 0.18);
                            padding: 12px 20px 16px;
                            margin-top: 0;
                            background: rgba(248, 250, 252, 0.82);
                        }
                        #calendarEventModal .form-control,
                        #calendarEventModal .form-control-color,
                        #calendarEventModal textarea,
                        #calendarEventModal input[type="datetime-local"] {
                            border-radius: 12px;
                            border: 1.5px solid #cbd5e1;
                            background: #f8fafc;
                            color: #0f172a;
                            font-size: 1rem;
                            padding: 10px 12px;
                            margin-bottom: 8px;
                            box-shadow: none;
                            transition: border-color 0.2s, background 0.2s, color 0.2s, box-shadow 0.2s;
                        }
                        #calendarEventModal .form-control:focus,
                        #calendarEventModal .form-control-color:focus,
                        #calendarEventModal textarea:focus,
                        #calendarEventModal input[type="datetime-local"]:focus {
                            border-color: #3b82f6;
                            background: #ffffff;
                            color: #0f172a;
                            outline: none;
                            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.12);
                        }
                        #calendarEventModal .form-control::placeholder,
                        #calendarEventModal textarea::placeholder {
                            color: #94a3b8;
                        }
                        #calendarEventModal .form-control-color {
                            min-height: 46px;
                            padding: 6px;
                            cursor: pointer;
                        }
                        #calendarEventModal input[type="datetime-local"]::-webkit-calendar-picker-indicator {
                            filter: none;
                        }
                        #calendarEventModal .form-label {
                            font-weight: 600;
                            color: #475569;
                            margin-bottom: 6px;
                        }
                        #calendarEventModal .form-check-input {
                            width: 2.5em;
                            height: 1.35em;
                            cursor: pointer;
                        }
                        #calendarEventModal .form-check-label {
                            font-weight: 600;
                            color: #0f172a;
                            cursor: pointer;
                        }
                        #calendarEventModal .form-check-input:checked {
                            background-color: #3b82f6;
                            border-color: #3b82f6;
                        }
                        .btn.btn-primary, .calendar-quick-create {
                            background: linear-gradient(90deg, #2563eb 0%, #3b82f6 100%);
                            border: none;
                            color: #fff;
                            font-weight: 600;
                            border-radius: 8px;
                            padding: 8px 18px;
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
                            padding: 8px 18px;
                            transition: border-color 0.2s, color 0.2s;
                        }
                        .btn.btn-outline-secondary:hover {
                            border-color: #3b82f6;
                            color: #3b82f6;
                        }
                        .btn.btn-outline-danger {
                            border-radius: 12px;
                            border-width: 1.5px;
                            padding: 8px 14px;
                            font-weight: 700;
                        }
                        /* Sidebar minimal */
                        .content-sidebar-header {
                            border-radius: 18px 0 0 0;
                            background: var(--calendar-sidebar-header-bg);
                            box-shadow: 0 2px 8px 0 rgba(15, 23, 42, 0.08);
                            color: var(--calendar-sidebar-text);
                        }
                        .content-sidebar {
                            background: var(--calendar-sidebar-bg);
                        }
                        .app-skin-dark .calendar-toolbar-pro {
                            box-shadow: 0 2px 12px 0 rgba(30, 41, 59, 0.12);
                        }
                        .app-skin-dark .calendar-toolbar-pro .move-day,
                        .app-skin-dark .calendar-toolbar-pro .move-today {
                            background: rgba(37, 99, 235, 0.14);
                            color: #bfdbfe;
                            border-color: rgba(96, 165, 250, 0.14);
                        }
                        .app-skin-dark .calendar-toolbar-pro .move-day:hover,
                        .app-skin-dark .calendar-toolbar-pro .move-today:hover,
                        .app-skin-dark .calendar-toolbar-pro .move-day:focus,
                        .app-skin-dark .calendar-toolbar-pro .move-today:focus {
                            background: rgba(37, 99, 235, 0.24);
                            color: #ffffff;
                            border-color: rgba(96, 165, 250, 0.24);
                        }
                        .app-skin-dark .tui-full-calendar-weekday-grid-date:hover {
                            color: #ffffff !important;
                        }
                        .app-skin-dark .tui-full-calendar-month-dayname-item,
                        .app-skin-dark .tui-full-calendar-weekday-border,
                        .app-skin-dark .tui-full-calendar-month-week-item,
                        .app-skin-dark .tui-full-calendar-month-week-item > div {
                            border-color: var(--calendar-grid-border) !important;
                        }
                        .app-skin-dark .calendar-toolbar-pro .app-sidebar-open-trigger,
                        .app-skin-dark .calendar-toolbar-pro .app-sidebar-open-trigger i {
                            color: #e2e8f0 !important;
                        }
                        .app-skin-dark #calendarEventModal .modal-content {
                            box-shadow: 0 20px 60px rgba(2, 6, 23, 0.45);
                            background: linear-gradient(180deg, #1f2937 0%, #111827 100%);
                            color: #ffffff;
                        }
                        .app-skin-dark #calendarEventModal .modal-header,
                        .app-skin-dark #calendarEventModal .modal-footer {
                            border-color: rgba(148, 163, 184, 0.16);
                        }
                        .app-skin-dark .calendar-modal-subtitle {
                            color: #94a3b8;
                        }
                        .app-skin-dark .calendar-modal-icon {
                            background: linear-gradient(135deg, rgba(37, 99, 235, 0.22) 0%, rgba(59, 130, 246, 0.08) 100%);
                            color: #93c5fd;
                            box-shadow: inset 0 0 0 1px rgba(96, 165, 250, 0.18);
                        }
                        .app-skin-dark .calendar-form-card {
                            background: rgba(30, 41, 59, 0.86);
                            border-color: rgba(148, 163, 184, 0.14);
                            box-shadow: 0 10px 30px rgba(2, 6, 23, 0.2);
                        }
                        .app-skin-dark .calendar-form-card-title,
                        .app-skin-dark .calendar-inline-meta-label {
                            color: #93a4bf;
                        }
                        .app-skin-dark .calendar-compact-switch {
                            background: rgba(37, 99, 235, 0.14);
                            border-color: rgba(96, 165, 250, 0.16);
                        }
                        .app-skin-dark .calendar-inline-meta {
                            background: linear-gradient(135deg, rgba(37, 99, 235, 0.16) 0%, rgba(30, 41, 59, 0.9) 100%);
                            border-color: rgba(96, 165, 250, 0.16);
                        }
                        .app-skin-dark .calendar-picker-field {
                            background: #1a2236;
                            border-color: #334155;
                        }
                        .app-skin-dark .calendar-picker-field:focus-within {
                            border-color: #60a5fa;
                            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.18);
                        }
                        .app-skin-dark .calendar-picker-icon {
                            background: rgba(37, 99, 235, 0.18);
                            color: #93c5fd;
                        }
                        .app-skin-dark .calendar-picker-field .form-control {
                            color-scheme: dark;
                        }
                        .app-skin-dark .calendar-color-field {
                            background: rgba(15, 23, 42, 0.42);
                            border-color: rgba(148, 163, 184, 0.14);
                        }
                        .app-skin-dark .calendar-timing-note {
                            color: #93a4bf;
                        }
                        .app-skin-dark .calendar-time-group-kicker {
                            color: #7f8da3;
                        }
                        .app-skin-dark .calendar-time-group-title {
                            color: #ffffff;
                        }
                        .app-skin-dark .calendar-timing-arrow {
                            background: rgba(37, 99, 235, 0.16);
                            color: #93c5fd;
                            border-color: rgba(96, 165, 250, 0.16);
                        }
                        .app-skin-dark #calendarEventModal .form-control,
                        .app-skin-dark #calendarEventModal .form-control-color,
                        .app-skin-dark #calendarEventModal textarea,
                        .app-skin-dark #calendarEventModal input[type="datetime-local"] {
                            border-color: #334155;
                            background: #1a2236;
                            color: #ffffff;
                        }
                        .app-skin-dark #calendarEventModal .form-control:focus,
                        .app-skin-dark #calendarEventModal .form-control-color:focus,
                        .app-skin-dark #calendarEventModal textarea:focus,
                        .app-skin-dark #calendarEventModal input[type="datetime-local"]:focus {
                            background: #232e47;
                            color: #ffffff;
                        }
                        .app-skin-dark #calendarEventModal input[type="datetime-local"]::-webkit-calendar-picker-indicator {
                            filter: invert(1) brightness(0.8) sepia(1) hue-rotate(180deg) saturate(3);
                        }
                        .app-skin-dark #calendarEventModal .form-label {
                            color: #a5b4fc;
                        }
                        .app-skin-dark .btn.btn-outline-secondary {
                            border-color: #334155;
                            color: #a5b4fc;
                        }
                        .app-skin-dark #calendarEventModal .modal-footer {
                            background: rgba(15, 23, 42, 0.58);
                        }
                        .app-skin-dark .content-sidebar-header {
                            box-shadow: 0 2px 8px 0 rgba(30, 41, 59, 0.1);
                        }
                        /* Responsive */
                        @media (max-width: 768px) {
                            .calendar-toolbar-pro {
                                flex-direction: column;
                                align-items: flex-start;
                                padding: 16px 8px 10px 8px;
                            }
                            .content-sidebar {
                                width: 100% !important;
                                min-width: 100% !important;
                                max-width: 100% !important;
                                flex-basis: 100%;
                            }
                            #staticMonthYear {
                                font-size: 1.3rem;
                            }
                            #tui-calendar-init {
                                padding: 8px 2px 16px 2px;
                            }
                            .calendar-timing-grid {
                                grid-template-columns: 1fr;
                            }
                            .calendar-time-group-grid {
                                grid-template-columns: 1fr;
                            }
                            .calendar-timing-arrow {
                                display: none;
                            }
                            #calendarEventModal .modal-dialog {
                                margin: 0.75rem;
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
                    <div class="d-flex align-items-start gap-3">
                        <div class="calendar-modal-icon">
                            <i class="feather-edit-3"></i>
                        </div>
                        <div class="calendar-modal-header-copy">
                            <h5 class="modal-title mb-1" id="calendarEventModalLabel">Calendar Event</h5>
                            <div class="calendar-modal-subtitle">Add the title, date, and details in one clean step.</div>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="calendarEventForm">
                    <input type="hidden" id="calendarEventId" value="">
                    <div class="modal-body">
                        <div class="calendar-form-grid">
                            <div class="calendar-form-card">
                                <div class="calendar-form-card-title">Event Details</div>
                                <div class="mb-3">
                                    <label for="calendarEventTitle" class="form-label">Title</label>
                                    <input type="text" class="form-control" id="calendarEventTitle" placeholder="What is this event about?" required>
                                </div>
                                <label for="calendarEventColor" class="form-label">Accent Color</label>
                                <div class="calendar-color-field">
                                    <div class="calendar-color-swatch" id="calendarEventColorSwatch" aria-hidden="true"></div>
                                    <input type="color" class="form-control form-control-color w-100" id="calendarEventColor" value="#0d6efd">
                                </div>
                                <div class="mt-2">
                                    <label for="calendarEventDescription" class="form-label">Description</label>
                                    <textarea id="calendarEventDescription" class="form-control" rows="4" placeholder="Add notes, reminders, or instructions"></textarea>
                                </div>
                            </div>
                            <div class="calendar-form-card">
                                <div class="calendar-form-card-title-row">
                                    <div class="calendar-form-card-title mb-0">Timing</div>
                                </div>
                                <div class="calendar-timing-grid">
                                    <div class="calendar-time-group">
                                        <div class="calendar-time-group-head">
                                            <div>
                                                <div class="calendar-time-group-kicker">Start</div>
                                                <div class="calendar-time-group-title">Begins</div>
                                            </div>
                                        </div>
                                        <div class="calendar-time-group-grid">
                                            <div class="calendar-picker-field">
                                                <span class="calendar-picker-icon">
                                                    <i class="feather-calendar"></i>
                                                </span>
                                                <input type="date" class="form-control" id="calendarEventStartDate" required>
                                            </div>
                                        </div>
                                        <input type="datetime-local" class="calendar-hidden-datetime" id="calendarEventStart">
                                    </div>
                                    <div class="calendar-timing-arrow" aria-hidden="true">
                                        <i class="feather-arrow-right"></i>
                                    </div>
                                    <div class="calendar-time-group">
                                        <div class="calendar-time-group-head">
                                            <div>
                                                <div class="calendar-time-group-kicker">End</div>
                                                <div class="calendar-time-group-title">Finishes</div>
                                            </div>
                                        </div>
                                        <div class="calendar-time-group-grid">
                                            <div class="calendar-picker-field">
                                                <span class="calendar-picker-icon">
                                                    <i class="feather-calendar"></i>
                                                </span>
                                                <input type="date" class="form-control" id="calendarEventEndDate" required>
                                            </div>
                                        </div>
                                        <input type="datetime-local" class="calendar-hidden-datetime" id="calendarEventEnd">
                                    </div>
                                </div>
                                <div class="calendar-timing-note">Pick the start and end dates for this event.</div>
                            </div>
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
        updateStaticMonthYear();
        // Keep the month label synced after navigation or view change.
        if (window.cal) {
            var origRender = window.cal.render;
            window.cal.render = function() {
                if (origRender) origRender.apply(window.cal, arguments);
                updateStaticMonthYear();
            };
            var origChangeView = window.cal.changeView;
            window.cal.changeView = function() {
                if (origChangeView) origChangeView.apply(window.cal, arguments);
                updateStaticMonthYear();
            };
            var origPrev = window.cal.prev;
            window.cal.prev = function() {
                if (origPrev) origPrev.apply(window.cal, arguments);
                updateStaticMonthYear();
            };
            var origNext = window.cal.next;
            window.cal.next = function() {
                if (origNext) origNext.apply(window.cal, arguments);
                updateStaticMonthYear();
            };
            var origToday = window.cal.today;
            window.cal.today = function() {
                if (origToday) origToday.apply(window.cal, arguments);
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
    <script>
    (function () {
        function onReady(callback) {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', callback);
                return;
            }
            callback();
        }

        function safeSet(key, value) {
            try {
                localStorage.setItem(key, value);
            } catch (e) {
            }
        }

        function safeRemove(key) {
            try {
                localStorage.removeItem(key);
            } catch (e) {
            }
        }

        function syncThemeButtons(isDark) {
            var darkBtn = document.querySelector('.dark-button');
            var lightBtn = document.querySelector('.light-button');
            if (darkBtn) {
                darkBtn.style.display = isDark ? 'none' : '';
            }
            if (lightBtn) {
                lightBtn.style.display = isDark ? '' : 'none';
            }
        }

        function applyCalendarSkin(isDark) {
            var root = document.documentElement;
            root.classList.toggle('app-skin-dark', !!isDark);

            safeSet('app-skin', isDark ? 'app-skin-dark' : '');
            safeSet('app_skin', isDark ? 'app-skin-dark' : '');
            if (isDark) {
                safeSet('theme', 'dark');
            } else {
                safeRemove('theme');
            }

            if (isDark) {
                safeSet('app-skin-dark', 'app-skin-dark');
            } else {
                safeSet('app-skin-dark', '');
            }

            syncThemeButtons(!!isDark);

            if (window.cal && typeof window.cal.render === 'function') {
                setTimeout(function () {
                    try {
                        window.cal.render(true);
                    } catch (e) {
                    }
                }, 10);
            }
        }

        onReady(function () {
            var darkBtn = document.querySelector('.dark-button');
            var lightBtn = document.querySelector('.light-button');

            syncThemeButtons(document.documentElement.classList.contains('app-skin-dark'));

            if (darkBtn) {
                darkBtn.addEventListener('click', function (event) {
                    event.preventDefault();
                    applyCalendarSkin(true);
                });
            }

            if (lightBtn) {
                lightBtn.addEventListener('click', function (event) {
                    event.preventDefault();
                    applyCalendarSkin(false);
                });
            }
        });
    })();
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



