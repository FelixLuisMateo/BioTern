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
                    { "orderable": false, "targets": [0, 12] }
                ],
                "language": {
                    "emptyTable": "No attendance records found"
                }
            });
        }

        // Initialize DataTable
        $(document).ready(function() {
            initAttendanceDataTable();

            // Initialize Select2 for filter selects
            $('select[name="course_id"], select[name="department_id"], select[name="section_id"]').select2({
                width: 'resolve',
                theme: 'bootstrap-5',
                minimumResultsForSearch: Infinity
            });
            $('select[name="supervisor"], select[name="coordinator"]').select2({
                width: 'resolve',
                theme: 'bootstrap-5'
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

            $('#attendanceFilterForm').on('change', 'input[name="date"], select[name="course_id"], select[name="department_id"], select[name="section_id"], select[name="supervisor"], select[name="coordinator"]', function() {
                submitAttendanceFilters();
            });

            $('select[name="course_id"], select[name="department_id"], select[name="section_id"], select[name="supervisor"], select[name="coordinator"]').on('select2:select select2:clear', function() {
                submitAttendanceFilters();
            });

            // Header quick-filters (Today / This Week / This Month / status)
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

            // Delegate attendance row and bulk actions to avoid inline onclick handlers.
            $(document).on('click', '[data-attendance-action]', function(e) {
                var action = $(this).data('attendance-action');
                if (!action) return;
                e.preventDefault();

                var attendanceId = parseInt($(this).data('attendance-id'), 10);
                var studentId = parseInt($(this).data('student-id'), 10);
                var bulkAction = $(this).data('bulk-action');

                if (action === 'view-details') {
                    viewDetails(studentId);
                    return;
                }
                if (action === 'approve-individual') {
                    approveAttendanceIndividual(attendanceId);
                    return;
                }
                if (action === 'reject-individual') {
                    rejectAttendanceIndividual(attendanceId);
                    return;
                }
                if (action === 'edit-attendance') {
                    editAttendance(attendanceId);
                    return;
                }
                if (action === 'print-attendance') {
                    printAttendance(attendanceId);
                    return;
                }
                if (action === 'send-notification') {
                    sendNotification(attendanceId);
                    return;
                }
                if (action === 'delete-individual') {
                    deleteAttendanceIndividual(attendanceId);
                    return;
                }
                if (action === 'bulk-action') {
                    performBulkAction(bulkAction);
                    return;
                }
                if (action === 'clear-selection') {
                    clearSelection();
                }
            });

            // Initialize tooltips
            $('[data-bs-toggle="tooltip"]').each(function() {
                new bootstrap.Tooltip(this);
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
                dataType: 'json',
                success: function(response) {
                    if (response && response.success) {
                        showToast(response.message || 'Action completed successfully.', 'success');
                        refreshAttendanceTable();
                    } else {
                        showToast((response && response.message) ? response.message : 'Unable to complete the action.', 'danger');
                    }
                },
                error: function() {
                    showToast('Error processing request', 'danger');
                }
            });
        }

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
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            showToast(response.message, 'success');
                            refreshAttendanceTable();
                        } else {
                            showToast(response.message, 'danger');
                        }
                    },
                    error: function() {
                        showToast('Error processing request', 'danger');
                    }
                });
            }
        }
