/*
 * Class: module.pages.attendance-runtime
 * Used by:
 * - pages/attendance.php
 */

        function initAttendanceDataTable() {
            return $('#attendanceList').DataTable({
                "pageLength": 10,
                "ordering": true,
                "searching": true,
                "bLengthChange": true,
                "info": true,
                "paging": true,
                "autoWidth": false,
                "order": [[2, "desc"]],
                "columnDefs": [
                    { "orderable": false, "targets": [0, 10, 11, 12, 13] }
                ],
                "language": {
                    "emptyTable": "No attendance records found",
                    "lengthMenu": '<span class="attendance-length-prefix">Show</span> _MENU_ <span class="attendance-length-suffix">entries</span>'
                }
            });
        }

        var biometricAutoSyncInFlight = false;
        var biometricAutoSyncIntervalMs = 10000;
        var biometricAutoSyncRequest = null;
        var biometricAutoSyncStartedAt = 0;
        var biometricAutoSyncRequestTimeoutMs = 20000;

        function escapeHtml(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function showAttendanceSyncAlert(message, type) {
            var host = document.getElementById('attendanceSyncAlertHost');
            if (!host) {
                return;
            }

            host.innerHTML = [
                '<div class="alert alert-', escapeHtml(type || 'success'), ' alert-dismissible fade show" role="alert">',
                escapeHtml(message || ''),
                '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>',
                '</div>'
            ].join('');
        }

        function setManualSyncButtonBusy(isBusy) {
            var button = document.getElementById('manualSyncMachineButton');
            if (!button) {
                return;
            }

            button.disabled = !!isBusy;
            if (isBusy) {
                button.dataset.originalHtml = button.dataset.originalHtml || button.innerHTML;
                button.innerHTML = '<i class="feather-loader me-2"></i><span>Syncing...</span>';
            } else if (button.dataset.originalHtml) {
                button.innerHTML = button.dataset.originalHtml;
            }
        }

        function runBiometricAutoSync(options) {
            options = options || {};
            var manual = !!options.manual;
            var showToastOnError = !!options.showToastOnError;
            var reloadPage = options.reloadPage !== false;

            if (biometricAutoSyncInFlight && biometricAutoSyncRequest) {
                var elapsed = Date.now() - biometricAutoSyncStartedAt;
                if (elapsed > biometricAutoSyncRequestTimeoutMs) {
                    try {
                        biometricAutoSyncRequest.abort();
                    } catch (e) {}
                    biometricAutoSyncInFlight = false;
                    biometricAutoSyncRequest = null;
                }
            }

            if (biometricAutoSyncInFlight) {
                return;
            }

            biometricAutoSyncInFlight = true;
            biometricAutoSyncStartedAt = Date.now();
            if (manual) {
                setManualSyncButtonBusy(true);
            }

            biometricAutoSyncRequest = $.ajax({
                url: 'legacy_router.php?file=biometric_machine_sync.php&format=json',
                type: 'GET',
                dataType: 'json',
                cache: false,
                timeout: biometricAutoSyncRequestTimeoutMs,
                data: {
                    _ts: Date.now()
                }
            }).done(function(response) {
                if (response && response.success) {
                    if (reloadPage && !manual) {
                        window.location.reload();
                        return;
                    }
                    refreshAttendanceTable();
                    if (manual) {
                        showAttendanceSyncAlert(response.message || 'Machine sync complete.', 'success');
                    }
                } else if (showToastOnError) {
                    showToast((response && response.message) ? response.message : 'Machine sync failed.', 'danger');
                    if (manual) {
                        showAttendanceSyncAlert((response && response.message) ? response.message : 'Machine sync failed.', 'danger');
                    }
                }
            }).fail(function(xhr) {
                if (showToastOnError) {
                    showToast(manual ? 'Machine sync failed.' : 'Automatic machine sync failed.', 'danger');
                }
                if (manual) {
                    showAttendanceSyncAlert('Machine sync failed.', 'danger');
                }
            }).always(function() {
                biometricAutoSyncInFlight = false;
                biometricAutoSyncRequest = null;
                if (manual) {
                    setManualSyncButtonBusy(false);
                }
            });
        }

        // Initialize DataTable
        $(document).ready(function() {
            initAttendanceDataTable();

            function closeAttendanceActionsMenu() {
                var actionsMenu = document.getElementById('attendanceActionsMenu');
                var actionsToggle = document.querySelector('.page-header-actions-toggle[aria-controls="attendanceActionsMenu"]');

                if (actionsMenu) {
                    actionsMenu.classList.remove('is-open');
                    actionsMenu.classList.remove('show');
                }

                if (actionsToggle) {
                    actionsToggle.setAttribute('aria-expanded', 'false');
                    actionsToggle.classList.remove('is-open');
                }
            }

            $(document).on('click', '[data-bs-target="#attendanceFilterCollapse"], [data-bs-target="#collapseAttendanceStats"]', function() {
                window.setTimeout(closeAttendanceActionsMenu, 0);
            });

            ['attendanceFilterCollapse', 'collapseAttendanceStats'].forEach(function(id) {
                var collapseElement = document.getElementById(id);
                if (!collapseElement) {
                    return;
                }

                collapseElement.addEventListener('show.bs.collapse', function() {
                    closeAttendanceActionsMenu();
                });

                collapseElement.addEventListener('shown.bs.collapse', function() {
                    closeAttendanceActionsMenu();
                });
            });

            var $attendanceFilterForm = $('#attendanceFilterForm');
            ['#filter-course', '#filter-department', '#filter-section', '#filter-school-year'].forEach(function (selector) {
                if ($(selector).length) {
                    $(selector).select2({
                        width: '100%',
                        allowClear: false,
                        dropdownAutoWidth: false,
                        minimumResultsForSearch: Infinity,
                        dropdownParent: $attendanceFilterForm
                    });
                }
            });
            ['#filter-supervisor', '#filter-coordinator'].forEach(function (selector) {
                if ($(selector).length) {
                    $(selector).select2({
                        width: '100%',
                        allowClear: false,
                        dropdownAutoWidth: false,
                        dropdownParent: $attendanceFilterForm
                    });
                }
            });

            // Auto-submit attendance filters on change.
            var isSubmittingFilters = false;
            function submitAttendanceFilters() {
                if (isSubmittingFilters) return;
                var form = document.getElementById('attendanceFilterForm');
                if (!form) return;
                isSubmittingFilters = true;
                form.submit();
            }

            $('#attendanceFilterForm').on('change', 'input[name="date"], select[name="status"], select[name="source"], select[name="reports"], select[name="school_year"], select[name="course_id"], select[name="department_id"], select[name="section_id"], select[name="supervisor"], select[name="coordinator"]', function() {
                submitAttendanceFilters();
            });

            $('#filter-status, #filter-source, #filter-reports, #filter-course, #filter-department, #filter-section, #filter-school-year, #filter-supervisor, #filter-coordinator').on('select2:select select2:clear', function() {
                submitAttendanceFilters();
            });

            // Header quick-filters (status)
            // Use delegated binding so dynamically shown menu items are caught
            $(document).on('click', '.attendance-filter', function(e) {
                e.preventDefault();
                var type = $(this).data('type');
                var value = $(this).data('value');
                var params = new URLSearchParams(window.location.search);

                // Remove pagination or unrelated params
                params.delete('page');

                if (type === 'period') {
                    var today = new Date();
                    var yyyy = today.getFullYear();
                    var mm = String(today.getMonth() + 1).padStart(2, '0');
                    var dd = String(today.getDate()).padStart(2, '0');
                    if (value === 'today') {
                        params.set('date', yyyy + '-' + mm + '-' + dd);
                        params.delete('start_date');
                        params.delete('end_date');
                        params.delete('status');
                    } else if (value === 'week') {
                        // start of week (Monday)
                        var curr = new Date();
                        var first = new Date(curr.setDate(curr.getDate() - (curr.getDay() || 7) + 1));
                        var last = new Date();
                        var s_yyyy = first.getFullYear();
                        var s_mm = String(first.getMonth() + 1).padStart(2, '0');
                        var s_dd = String(first.getDate()).padStart(2, '0');
                        var e_yyyy = last.getFullYear();
                        var e_mm = String(last.getMonth() + 1).padStart(2, '0');
                        var e_dd = String(last.getDate()).padStart(2, '0');
                        params.set('start_date', s_yyyy + '-' + s_mm + '-' + s_dd);
                        params.set('end_date', e_yyyy + '-' + e_mm + '-' + e_dd);
                        params.delete('date');
                        params.delete('status');
                    } else if (value === 'month') {
                        var now = new Date();
                        var s_yyyy = now.getFullYear();
                        var s_mm = String(now.getMonth() + 1).padStart(2, '0');
                        params.set('start_date', s_yyyy + '-' + s_mm + '-01');
                        // last day of month
                        var lastDay = new Date(now.getFullYear(), now.getMonth()+1, 0);
                        var e_yyyy = lastDay.getFullYear();
                        var e_mm = String(lastDay.getMonth() + 1).padStart(2, '0');
                        var e_dd = String(lastDay.getDate()).padStart(2, '0');
                        params.set('end_date', e_yyyy + '-' + e_mm + '-' + e_dd);
                        params.delete('date');
                        params.delete('status');
                    }
                } else if (type === 'status') {
                    params.set('status', value);
                    // clear specific date range so status can apply broadly
                    params.delete('date');
                    params.delete('start_date');
                    params.delete('end_date');
                }

                // navigate via AJAX: fetch rows and replace table body without full reload
                var qs = params.toString();
                var fetchUrl = window.location.pathname + (qs ? ('?' + qs) : '') + (qs ? '&ajax=1' : '?ajax=1');
                // request rows
                $.get(fetchUrl, function(html) {
                    // destroy and reinit DataTable while replacing rows
                    if ($.fn.DataTable.isDataTable('#attendanceList')) {
                        $('#attendanceList').DataTable().clear().destroy();
                    }
                    $('#attendanceList tbody').html(html);
                    // re-init DataTable
                    initAttendanceDataTable();
                    // re-init tooltips
                    $('[data-bs-toggle="tooltip"]').each(function() { new bootstrap.Tooltip(this); });
                    // re-init dropdowns
                    var dropdownElements = document.querySelectorAll('[data-bs-toggle="dropdown"]');
                    dropdownElements.forEach(function(element) {
                        new bootstrap.Dropdown(element);
                    });
                }).fail(function() {
                    // fallback to full reload on error
                    window.location.href = window.location.pathname + (qs ? ('?' + qs) : '');
                });
            });

            // Handle Check All
            $('#checkAllAttendance').on('change', function() {
                $('.checkbox').prop('checked', this.checked);
                updateBulkActionsToolbar();
            });

            // Handle individual checkbox changes
            $(document).on('change', '.checkbox', function() {
                updateBulkActionsToolbar();
            });

            // Initialize tooltips
            $('[data-bs-toggle="tooltip"]').each(function() {
                new bootstrap.Tooltip(this);
            });

            $('#manualSyncMachineButton').on('click', function() {
                runBiometricAutoSync({
                    manual: true,
                    showToastOnError: true,
                    reloadPage: false
                });
            });
        });

        // View Details function
        function viewDetails(studentId) {
            var sid = parseInt(studentId, 10);
            if (!sid || sid <= 0) {
                showToast('Invalid student record', 'danger');
                return;
            }
            window.location.href = 'students-dtr.php?id=' + sid;
        }

        // Update bulk actions toolbar visibility and count
        function updateBulkActionsToolbar() {
            var selectedCount = $('.checkbox:checked').length;
            $('#selectedCount').text(selectedCount);
            
            // Show bulk toolbar only when multiple rows are selected.
            if (selectedCount > 1) {
                $('#bulkActionsToolbar').slideDown(200);
            } else {
                $('#bulkActionsToolbar').slideUp(200);
                if (selectedCount === 0) {
                    $('#checkAllAttendance').prop('checked', false);
                }
            }
        }

        // Clear selection
        function clearSelection() {
            $('.checkbox').prop('checked', false);
            $('#checkAllAttendance').prop('checked', false);
            updateBulkActionsToolbar();
        }

        // Helper function to get selected IDs
        function getSelectedIds() {
            var ids = [];
            $('.checkbox:checked').each(function() {
                var id = parseInt($(this).data('attendance-id'), 10);
                if (!isNaN(id)) {
                    ids.push(id);
                }
            });
            return [...new Set(ids)];
        }

        // Show toast notification
        function showToast(message, type = 'success') {
            // Remove existing toasts
            $('.toast-notification').remove();
            
            var toastHtml = '<div class="toast-notification alert alert-' + type + ' alert-dismissible fade show" role="alert" style="position: fixed; top: 20px; right: 20px; z-index: 99999; max-width: 400px;">' +
                message +
                '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
                '</div>';
            
            $('body').append(toastHtml);
            
            setTimeout(function() {
                $('.toast-notification').fadeOut(function() {
                    $(this).remove();
                });
            }, 4000);
        }

        function submitAttendanceAction(action, id, remarks) {
            var ids = Array.isArray(id) ? id : [id];
            ids = ids.filter(function(v) { return !!v; });
            var payload = {
                action: action,
                id: ids
            };
            if (typeof remarks === 'string') {
                payload.remarks = remarks;
            }

            $.ajax({
                type: 'POST',
                url: 'process_attendance.php',
                data: payload,
                dataType: 'text',
                success: function(response) {
                    if (typeof response === 'string') {
                        try {
                            response = JSON.parse(response);
                        } catch (e) {
                            var jsonStart = response.indexOf('{');
                            var jsonEnd = response.lastIndexOf('}');
                            if (jsonStart !== -1 && jsonEnd > jsonStart) {
                                try {
                                    response = JSON.parse(response.slice(jsonStart, jsonEnd + 1));
                                } catch (ignored) {}
                            }
                        }
                    }

                    if (response && response.success) {
                        showToast(response.message || 'Action completed successfully.', 'success');
                        refreshAttendanceTable();
                    } else {
                        showToast((response && response.message) ? response.message : 'Unable to complete the action.', 'danger');
                    }
                },
                error: function(xhr) {
                    var message = 'Error processing request';
                    if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    } else if (xhr && xhr.responseText) {
                        try {
                            var parsed = JSON.parse(xhr.responseText);
                            if (parsed && parsed.message) {
                                message = parsed.message;
                            }
                        } catch (e) {}
                    }
                    showToast(message, 'danger');
                }
            });
        }

        $(document).on('click', '[data-attendance-review-save]', function() {
            var form = $(this).closest('[data-attendance-review-form]');
            var id = parseInt(form.data('attendance-id'), 10);
            var action = String(form.find('[name="review_action"]').val() || 'approve');
            var note = String(form.find('[name="review_note"]').val() || '').trim();

            if (!id || isNaN(id)) {
                showToast('Invalid attendance record', 'danger');
                return;
            }

            if (action === 'reject' && !note) {
                showToast('Review note is required when rejecting.', 'warning');
                return;
            }

            submitAttendanceAction(action, [id], note);
        });

        function showConfirmModal(options) {
            var modalEl = document.getElementById('confirmModal');
            if (!modalEl) return;

            var modalTitle = modalEl.querySelector('.modal-title');
            var modalBody = modalEl.querySelector('.modal-body .confirm-message');
            var remarksWrap = modalEl.querySelector('.modal-body .confirm-remarks-wrap');
            var remarksInput = modalEl.querySelector('#confirmRemarks');
            var okBtn = modalEl.querySelector('#confirmModalOk');

            modalTitle.textContent = options.title || 'Confirm';
            modalBody.textContent = options.message || '';
            if (options.showRemarks) {
                remarksWrap.style.display = 'block';
                remarksInput.value = options.defaultRemarks || '';
            } else {
                remarksWrap.style.display = 'none';
                remarksInput.value = '';
            }

            okBtn.replaceWith(okBtn.cloneNode(true));
            okBtn = modalEl.querySelector('#confirmModalOk');

            okBtn.addEventListener('click', function() {
                var remarks = (remarksInput.value || '').trim();

                var instance = bootstrap.Modal.getInstance(modalEl);
                if (instance) instance.hide();

                if (typeof options.onConfirm === 'function') {
                    options.onConfirm(remarks);
                }
            });

            var modal = new bootstrap.Modal(modalEl, { backdrop: 'static' });
            modal.show();
        }

        // Refresh table after action
        function refreshAttendanceTable() {
            var currentUrl = window.location.href;
            $.get(currentUrl, function(html) {
                if ($.fn.DataTable.isDataTable('#attendanceList')) {
                    $('#attendanceList').DataTable().destroy();
                }
                var newTbody = $(html).find('#attendanceList tbody').html();
                $('#attendanceList tbody').html(newTbody);
                initAttendanceDataTable();
                // Reinitialize tooltips
                $('[data-bs-toggle="tooltip"]').each(function() {
                    new bootstrap.Tooltip(this);
                });
                // Reinitialize dropdowns - Bootstrap 5
                var dropdownElements = document.querySelectorAll('[data-bs-toggle="dropdown"]');
                dropdownElements.forEach(function(element) {
                    new bootstrap.Dropdown(element);
                });
                $('#checkAllAttendance').prop('checked', false);
                updateBulkActionsToolbar();
            });
        }

        // Individual record approval
        function approveAttendanceIndividual(id) {
            if (!id || id === 0) {
                showToast('Invalid attendance record', 'danger');
                return;
            }
            showConfirmModal({
                title: 'Approve Attendance',
                message: 'Are you sure you want to approve this attendance record?',
                showRemarks: false,
                onConfirm: function() {
                    submitAttendanceAction('approve', [id]);
                }
            });
        }

        // Bulk approval (from checkboxes)
        function approveAttendance() {
            var ids = getSelectedIds();
            if (ids.length === 0) {
                showToast('Please select at least one attendance record to approve', 'warning');
                return;
            }
            showConfirmModal({
                title: 'Approve Attendance',
                message: ids.length === 1 ? 'Are you sure you want to approve this attendance?' : ('Are you sure you want to approve ' + ids.length + ' attendance record(s)?'),
                showRemarks: false,
                onConfirm: function() {
                    submitAttendanceAction('approve', ids);
                }
            });
        }

        // Individual record rejection
        function rejectAttendanceIndividual(id) {
            if (!id || id === 0) {
                showToast('Invalid attendance record', 'danger');
                return;
            }
            showConfirmModal({
                title: 'Reject Attendance',
                message: 'Provide a reason for rejection (required):',
                showRemarks: true,
                onConfirm: function(remarks) {
                    if (!remarks) {
                        setTimeout(function() {
                            showConfirmModal({
                                title: 'Reject Attendance',
                                message: 'Rejection reason is required.',
                                showRemarks: true,
                                onConfirm: function(r) {
                                    if (!r) return;
                                    rejectAttendanceIndividual(id);
                                }
                            });
                        }, 250);
                        return;
                    }
                    submitAttendanceAction('reject', [id], remarks);
                }
            });
        }

        // Bulk rejection (from checkboxes)
        function rejectAttendance() {
            var ids = getSelectedIds();
            if (ids.length === 0) {
                showToast('Please select at least one attendance record to reject', 'warning');
                return;
            }
            showConfirmModal({
                title: 'Reject Attendance',
                message: 'Provide a reason for rejection (required):',
                showRemarks: true,
                onConfirm: function(remarks) {
                    if (!remarks) {
                        setTimeout(function() {
                            showConfirmModal({
                                title: 'Reject Attendance',
                                message: 'Rejection reason is required.',
                                showRemarks: true,
                                onConfirm: function(r) {
                                    if (!r) return;
                                    rejectAttendance();
                                }
                            });
                        }, 250);
                        return;
                    }
                    submitAttendanceAction('reject', ids, remarks);
                }
            });
        }

        // Edit attendance function (redirects to edit page)
        function editAttendance(id) {
            window.location.href = 'edit_attendance.php?id=' + id;
        }

        // Print attendance function
        function printAttendance(id) {
            window.open('print_attendance.php?id=' + id, 'Print', 'height=600,width=800');
        }

        // Send notification function
        function sendNotification(id) {
            alert('Sending notification for Attendance ID: ' + id);
            // Implement your notification logic here
        }

        // Individual record deletion
        function deleteAttendanceIndividual(id) {
            if (!id || id === 0) {
                showToast('Invalid attendance record', 'danger');
                return;
            }
            showConfirmModal({
                title: 'Delete Attendance',
                message: 'Are you sure you want to delete this attendance record? This action cannot be undone.',
                showRemarks: false,
                onConfirm: function() {
                    submitAttendanceAction('delete', [id]);
                }
            });
        }

        // Bulk deletion (from checkboxes)
        function deleteAttendance() {
            var ids = getSelectedIds();
            if (ids.length === 0) {
                showToast('Please select at least one attendance record to delete', 'warning');
                return;
            }
            showConfirmModal({
                title: 'Delete Attendance',
                message: ids.length === 1 ? 'Are you sure you want to delete this attendance record? This action cannot be undone.' : ('Are you sure you want to delete ' + ids.length + ' attendance record(s)? This action cannot be undone.'),
                showRemarks: false,
                onConfirm: function() {
                    submitAttendanceAction('delete', ids);
                }
            });
        }

        // Bulk action handler
        function performBulkAction(action) {
            var ids = getSelectedIds();
            
            if (ids.length === 0) {
                showToast('Please select at least one attendance record', 'warning');
                return;
            }

            if (action === 'approve') {
                approveAttendance();
            } else if (action === 'reject') {
                rejectAttendance();
            } else if (action === 'delete') {
                deleteAttendance();
            }
        }

        // Edit status inline via AJAX
        function changeStatus(id, newStatus) {
            if (confirm('Change status to ' + newStatus + '?')) {
                $.ajax({
                    type: 'POST',
                    url: 'process_attendance.php',
                    data: {
                        action: 'edit_status',
                        id: [id],
                        status: newStatus
                    },
                    dataType: 'text',
                    success: function(response) {
                        if (typeof response === 'string') {
                            try {
                                response = JSON.parse(response);
                            } catch (e) {
                                var jsonStart = response.indexOf('{');
                                var jsonEnd = response.lastIndexOf('}');
                                if (jsonStart !== -1 && jsonEnd > jsonStart) {
                                    try {
                                        response = JSON.parse(response.slice(jsonStart, jsonEnd + 1));
                                    } catch (ignored) {}
                                }
                            }
                        }

                        if (response.success) {
                            showToast(response.message, 'success');
                            refreshAttendanceTable();
                        } else {
                            showToast(response.message, 'danger');
                        }
                    },
                    error: function(xhr) {
                        var message = 'Error processing request';
                        if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                            message = xhr.responseJSON.message;
                        } else if (xhr && xhr.responseText) {
                            try {
                                var parsed = JSON.parse(xhr.responseText);
                                if (parsed && parsed.message) {
                                    message = parsed.message;
                                }
                            } catch (e) {}
                        }
                        showToast(message, 'danger');
                    }
                });
            }
        }

        (function () {
            document.addEventListener('DOMContentLoaded', function () {
                var darkBtn = document.querySelector('.dark-button');
                var lightBtn = document.querySelector('.light-button');

                function setDark(isDark) {
                    if (isDark) {
                        document.documentElement.classList.add('app-skin-dark');
                        try {
                            localStorage.setItem('app-skin', 'app-skin-dark');
                            localStorage.setItem('app_skin', 'app-skin-dark');
                            localStorage.setItem('theme', 'dark');
                            localStorage.setItem('app-skin-dark', 'app-skin-dark');
                        } catch (e) {}
                        if (darkBtn) darkBtn.style.display = 'none';
                        if (lightBtn) lightBtn.style.display = '';
                    } else {
                        document.documentElement.classList.remove('app-skin-dark');
                        try {
                            localStorage.setItem('app-skin', '');
                            localStorage.setItem('app_skin', '');
                            localStorage.setItem('theme', 'light');
                            localStorage.removeItem('app-skin-dark');
                        } catch (e) {}
                        if (darkBtn) darkBtn.style.display = '';
                        if (lightBtn) lightBtn.style.display = 'none';
                    }
                }

                var skin = '';
                try {
                    var appSkin = localStorage.getItem('app-skin');
                    var appSkinAlt = localStorage.getItem('app_skin');
                    var theme = localStorage.getItem('theme');
                    var legacy = localStorage.getItem('app-skin-dark');
                    if (appSkin !== null) skin = appSkin;
                    else if (appSkinAlt !== null) skin = appSkinAlt;
                    else if (theme !== null) skin = theme;
                    else if (legacy !== null) skin = legacy;
                } catch (e) {}
                setDark((typeof skin === 'string' && skin.indexOf('dark') !== -1) || document.documentElement.classList.contains('app-skin-dark'));

                if (darkBtn) darkBtn.addEventListener('click', function (e) { e.preventDefault(); setDark(true); });
                if (lightBtn) lightBtn.addEventListener('click', function (e) { e.preventDefault(); setDark(false); });
            });
        })();
