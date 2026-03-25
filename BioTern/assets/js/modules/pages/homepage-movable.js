(function () {
    var CARD_REGISTRY = {
        'kpi-strip': { name: 'KPI Strip', init: initKpiStripCard },
        'latest-attendance': { name: 'Latest Attendance Records', init: initLatestAttendanceCard },
        'biometric-status': { name: 'Biometric Registration Status', init: initBiometricStatusCard },
        'operations-pulse': { name: 'Operations Pulse', init: initOperationsPulseCard },
        'priority-items': { name: 'Priority Items', init: initPriorityItemsCard },
        'recent-activities': { name: 'Recent Activities & Logs', init: initRecentActivitiesCard },
        'admin-quick-actions': { name: 'Admin Quick Actions', init: initAdminQuickActionsCard },
        'active-students': { name: 'Active Students', init: initActiveStudentsCard },
        'coordinators': { name: 'Coordinators', init: initCoordinatorsCard },
        'supervisors': { name: 'Supervisors', init: initSupervisorsCard }
    };

    function initHomepageMovable() {
        var shell = document.querySelector('.main-content.dashboard-shell');
        var row = document.querySelector('.main-content.dashboard-shell > .row');
        if (!row) return;

        var storageKey = 'biotern-homepage-layout-v2';
        var cards = Array.from(row.children).filter(function (item) {
            return item.classList.contains('dashboard-movable') && item.dataset.moveKey;
        });
        if (!cards.length) return;

        var byKey = {};
        cards.forEach(function (card) {
            byKey[card.dataset.moveKey] = card;
            var registry = CARD_REGISTRY[card.dataset.moveKey];
            if (registry && registry.name) {
                card.setAttribute('data-card-name', registry.name);
            }
        });

        var defaultOrder = cards.map(function (item) {
            return item.dataset.moveKey;
        });

        var isEditMode = false;
        var dragged = null;

        function getMovableCards() {
            return Array.from(row.querySelectorAll('.dashboard-movable[data-move-key]'));
        }

        function animateLayoutMutation(mutate) {
            var beforeRects = new Map();
            var nodes = getMovableCards();
            nodes.forEach(function (node) {
                beforeRects.set(node, node.getBoundingClientRect());
            });

            mutate();

            var afterRects = new Map();
            nodes.forEach(function (node) {
                afterRects.set(node, node.getBoundingClientRect());
            });

            nodes.forEach(function (node) {
                var before = beforeRects.get(node);
                var after = afterRects.get(node);
                if (!before || !after) return;

                var deltaX = before.left - after.left;
                var deltaY = before.top - after.top;
                if (Math.abs(deltaX) < 0.5 && Math.abs(deltaY) < 0.5) return;

                node.style.transition = 'none';
                node.style.transform = 'translate(' + deltaX + 'px, ' + deltaY + 'px)';
                node.style.zIndex = '5';

                requestAnimationFrame(function () {
                    node.style.transition = 'transform 240ms cubic-bezier(0.22, 1, 0.36, 1)';
                    node.style.transform = 'translate(0, 0)';
                });

                var clearTransition = function () {
                    node.style.transition = '';
                    node.style.transform = '';
                    node.style.zIndex = '';
                    node.removeEventListener('transitionend', clearTransition);
                };
                node.addEventListener('transitionend', clearTransition);
            });
        }

        function updateDraggableState() {
            cards.forEach(function (card) {
                card.setAttribute('draggable', isEditMode ? 'true' : 'false');
                card.classList.toggle('layout-draggable', isEditMode);
            });
        }

        function setEditMode(enabled) {
            isEditMode = !!enabled;
            if (shell) {
                shell.classList.toggle('layout-edit-active', isEditMode);
            }
            if (!isEditMode) {
                if (dragged) {
                    dragged.classList.remove('dragging');
                    dragged = null;
                }
                clearTargets();
            }
            updateDraggableState();

            var toggleButtons = document.querySelectorAll('#toggle-dashboard-layout, #toggle-dashboard-layout-mobile');
            toggleButtons.forEach(function (toggleBtn) {
                if (!toggleBtn) return;
                toggleBtn.innerHTML = isEditMode
                    ? '<i class="feather-check me-1"></i> Done'
                    : '<i class="feather-move me-1"></i> Edit Layout';
                toggleBtn.classList.toggle('btn-primary', isEditMode);
                toggleBtn.classList.toggle('btn-light-brand', !isEditMode);
            });
        }

        function applyOrder(order) {
            if (!Array.isArray(order) || !order.length) return;
            order.forEach(function (key) {
                if (byKey[key]) {
                    row.appendChild(byKey[key]);
                }
            });
        }

        try {
            var saved = JSON.parse(localStorage.getItem(storageKey) || '[]');
            if (Array.isArray(saved) && saved.length) {
                applyOrder(saved);
            }
        } catch (error) {
            console.warn('Homepage layout restore failed:', error);
        }

        function saveOrder() {
            var order = Array.from(row.querySelectorAll('.dashboard-movable[data-move-key]')).map(function (item) {
                return item.dataset.moveKey;
            });
            localStorage.setItem(storageKey, JSON.stringify(order));
        }

        function clearTargets() {
            row.querySelectorAll('.dashboard-movable.drag-target, .dashboard-movable.drag-before, .dashboard-movable.drag-after').forEach(function (item) {
                item.classList.remove('drag-target', 'drag-before', 'drag-after');
            });
        }

        function resetLayout() {
            localStorage.removeItem(storageKey);
            applyOrder(defaultOrder);
        }

        function swapCards(firstCard, secondCard) {
            if (!firstCard || !secondCard || firstCard === secondCard) return;
            var firstNext = firstCard.nextElementSibling;
            var secondNext = secondCard.nextElementSibling;

            if (firstNext === secondCard) {
                row.insertBefore(secondCard, firstCard);
                return;
            }

            if (secondNext === firstCard) {
                row.insertBefore(firstCard, secondCard);
                return;
            }

            row.insertBefore(firstCard, secondNext);
            row.insertBefore(secondCard, firstNext);
        }

        var resetButton = document.getElementById('reset-dashboard-layout');
        if (resetButton) {
            resetButton.addEventListener('click', function () {
                resetLayout();
            });
        }

        document.querySelectorAll('#toggle-dashboard-layout, #toggle-dashboard-layout-mobile').forEach(function (toggleLayoutButton) {
            toggleLayoutButton.addEventListener('click', function () {
                setEditMode(!isEditMode);
            });
        });

        window.BioTernHomepage = window.BioTernHomepage || {};
        window.BioTernHomepage.resetLayout = resetLayout;
        window.BioTernHomepage.setEditMode = setEditMode;

        row.querySelectorAll('[data-bs-toggle="remove"]').forEach(function (button) {
            var wrapper = button.closest('[data-bs-toggle="tooltip"]');
            if (wrapper) {
                wrapper.remove();
            } else {
                button.remove();
            }
        });

        cards.forEach(function (item) {
            item.querySelectorAll('a, button, input, select, textarea').forEach(function (ctrl) {
                ctrl.setAttribute('draggable', 'false');
            });

            function beginDrag(event) {
                if (!isEditMode) {
                    event.preventDefault();
                    return;
                }
                var interactiveTarget = event.target.closest('a, button, input, select, textarea, .dropdown-menu, .pagination-common-style');
                if (interactiveTarget) {
                    event.preventDefault();
                    return;
                }

                dragged = item;
                item.classList.add('dragging');
                if (event.dataTransfer) {
                    event.dataTransfer.effectAllowed = 'move';
                    event.dataTransfer.setData('text/plain', item.dataset.moveKey || 'move');
                }
            }

            item.addEventListener('dragstart', beginDrag);

            item.addEventListener('dragend', function () {
                if (dragged) dragged.classList.remove('dragging');
                dragged = null;
                clearTargets();
                saveOrder();
            });

            item.addEventListener('dragover', function (event) {
                if (!isEditMode) return;
                if (!dragged || dragged === item) return;
                event.preventDefault();
                clearTargets();
                item.classList.add('drag-target');
                if (event.dataTransfer) {
                    event.dataTransfer.dropEffect = 'move';
                }
            });

            item.addEventListener('dragleave', function () {
                item.classList.remove('drag-target');
            });

            item.addEventListener('drop', function (event) {
                if (!isEditMode) return;
                if (!dragged || dragged === item) return;
                event.preventDefault();
                item.classList.remove('drag-target', 'drag-before', 'drag-after');
                animateLayoutMutation(function () {
                    swapCards(dragged, item);
                });
            });
        });

        initRegisteredCards(row);

        row.querySelectorAll('[data-bs-toggle="expand"]').forEach(function (expandButton) {
            expandButton.removeAttribute('data-bs-toggle');
            expandButton.classList.add('dashboard-expand-control');
            expandButton.innerHTML = '<i class="feather-maximize-2"></i>';
        });

        row.querySelectorAll('.dashboard-expand-control').forEach(function (expandButton) {
            expandButton.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                var card = expandButton.closest('.card');
                if (!card) return;

                card.classList.toggle('maximize-animated');
                var showMore = card.classList.contains('maximize-animated');

                card.querySelectorAll('[data-expand-detail="1"]').forEach(function (element) {
                    if (showMore) {
                        element.classList.remove('detail-hidden');
                    } else {
                        element.classList.add('detail-hidden');
                    }
                });

                var scrollZone = card.querySelector('.card-body[style*="max-height"]');
                if (scrollZone) {
                    scrollZone.style.maxHeight = showMore ? '680px' : '400px';
                }

                var latestCardContainer = expandButton.closest('[data-move-key="latest-attendance"]');
                if (latestCardContainer && typeof latestCardContainer._renderAttendancePage === 'function') {
                    latestCardContainer._renderAttendancePage(1, showMore);
                }
            });
        });

        if (shell) {
            shell.classList.remove('widgets-preloading');
        }

        setEditMode(false);
    }

    function initRegisteredCards(row) {
        Object.keys(CARD_REGISTRY).forEach(function (key) {
            var cardNode = row.querySelector('[data-move-key="' + key + '"]');
            if (!cardNode) return;
            var initializer = CARD_REGISTRY[key].init;
            if (typeof initializer === 'function') {
                initializer(cardNode, row);
            }
        });
    }

    function initLatestAttendanceCard(attendanceCardNode) {
        if (!attendanceCardNode) return;

        var tableRows = Array.from(attendanceCardNode.querySelectorAll('tbody tr')).filter(function (line) {
            return line.querySelectorAll('td').length > 1;
        });

        if (!tableRows.length) return;
        tableRows.forEach(function (line) {
            line.style.display = '';
        });
    }

    function initRecentActivitiesCard(cardNode) {
        if (!cardNode) return;
    }

    function initKpiStripCard() {}
    function initBiometricStatusCard() {}
    function initOperationsPulseCard() {}
    function initPriorityItemsCard() {}
    function initAdminQuickActionsCard() {}
    function initActiveStudentsCard() {}
    function initCoordinatorsCard() {}
    function initSupervisorsCard() {}

    function initOjtOverviewChart() {
        try {
            if (typeof ApexCharts === 'undefined') {
                return;
            }

            var el = document.querySelector('#ojt-overview-pie');
            if (!el) {
                return;
            }

            var cfg = document.getElementById('homepage-runtime-config');
            var pending = Number((cfg && cfg.dataset.ojtPending) || 0);
            var ongoing = Number((cfg && cfg.dataset.ojtOngoing) || 0);
            var completed = Number((cfg && cfg.dataset.ojtCompleted) || 0);
            var cancelled = Number((cfg && cfg.dataset.ojtCancelled) || 0);

            var chart = new ApexCharts(el, {
                chart: { type: 'donut', height: 260 },
                series: [pending, ongoing, completed, cancelled],
                labels: ['Pending', 'Ongoing', 'Completed', 'Cancelled'],
                colors: ['#f6c23e', '#36b9cc', '#1cc88a', '#e74a3b'],
                legend: { position: 'bottom' },
                responsive: [
                    {
                        breakpoint: 768,
                        options: { chart: { height: 200 }, legend: { position: 'bottom' } },
                    },
                ],
            });

            chart.render();
        } catch (e) {
            console.error('OJT chart init error', e);
        }
    }

    function initSidebarMiniMenuCollapse() {
        function collapseSidebarMenus() {
            if (!document.documentElement.classList.contains('minimenu')) return;
            document
                .querySelectorAll(
                    '.nxl-navigation .nxl-item.nxl-hasmenu.open, .nxl-navigation .nxl-item.nxl-hasmenu.nxl-trigger'
                )
                .forEach(function (item) {
                    item.classList.remove('open', 'nxl-trigger');
                });
        }

        function runAfterToggle() {
            collapseSidebarMenus();
            setTimeout(collapseSidebarMenus, 80);
            setTimeout(collapseSidebarMenus, 220);
        }

        collapseSidebarMenus();

        ['menu-mini-button', 'menu-expend-button', 'mobile-collapse'].forEach(function (id) {
            var btn = document.getElementById(id);
            if (btn) btn.addEventListener('click', runAfterToggle);
        });

        var nav = document.querySelector('.nxl-navigation');
        if (window.MutationObserver && nav) {
            var observer = new MutationObserver(function () {
                if (document.documentElement.classList.contains('minimenu')) {
                    collapseSidebarMenus();
                }
            });
            observer.observe(nav, {
                subtree: true,
                attributes: true,
                attributeFilter: ['class'],
            });
        }
    }

    function applyProgressWidths() {
        document.querySelectorAll('[data-progress-width]').forEach(function (bar) {
            var raw = bar.getAttribute('data-progress-width');
            var value = Number(raw);
            if (!isFinite(value)) {
                value = 0;
            }
            value = Math.max(0, Math.min(100, value));
            bar.style.width = value + '%';
            bar.setAttribute('aria-valuenow', String(value));
        });
    }

    function downloadCSV(filename, rows) {
        if (!rows.length) {
            return;
        }
        var csv = rows
            .map(function (row) {
                return row
                    .map(function (value) {
                        var str = String(value == null ? '' : value);
                        if (str.search(/(\"|,|\\n)/g) >= 0) {
                            str = '\"' + str.replace(/\"/g, '\"\"') + '\"';
                        }
                        return str;
                    })
                    .join(',');
            })
            .join('\n');
        var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    function exportAttendanceCSV() {
        var table = document.getElementById('latest-attendance-table');
        if (!table) {
            return;
        }
        var rows = [];
        rows.push(['Student', 'Student ID', 'Attendance Date', 'Time In', 'Status']);
        table.querySelectorAll('tbody tr').forEach(function (tr) {
            var cols = tr.querySelectorAll('td');
            if (cols.length < 4) return;
            var studentName = cols[0].querySelector('.fw-semibold');
            var studentId = cols[0].querySelector('.text-muted');
            rows.push([
                studentName ? studentName.textContent.trim() : '',
                studentId ? studentId.textContent.trim() : '',
                cols[1].textContent.trim(),
                cols[2].textContent.trim(),
                cols[3].textContent.trim(),
            ]);
        });
        downloadCSV('latest-attendance.csv', rows);
    }

    function exportRecentActivitiesCSV() {
        var rows = [];
        rows.push(['Activity', 'Date']);
        document.querySelectorAll('.recent-activity-item').forEach(function (row) {
            var title = row.querySelector('.fw-semibold');
            var date = row.querySelector('.text-muted.border-bottom-dashed');
            rows.push([
                title ? title.textContent.trim() : '',
                date ? date.textContent.trim() : '',
            ]);
        });
        downloadCSV('recent-activities.csv', rows);
    }

    function toggleAttendanceCompact() {
        var table = document.getElementById('latest-attendance-table');
        if (!table) return;
        table.classList.toggle('table-sm');
    }

    function toggleActivitiesCompact() {
        document.body.classList.toggle('dashboard-activities-compact');
    }

    function goTo(path) {
        window.location.href = path;
    }

    function openAttendanceRecord(id) {
        if (!id) {
            goTo('attendance.php');
            return;
        }
        goTo('attendance.php?attendance_id=' + encodeURIComponent(String(id)));
    }

    function initHomepageRuntime() {
        initOjtOverviewChart();
        initSidebarMiniMenuCollapse();
        applyProgressWidths();
    }

    function initActionsPanelToggle() {
        var toggle = document.querySelector('.page-header-actions-toggle');
        var panel = document.getElementById('dashboardPageActions');
        if (!toggle || !panel) return;

        function setOpen(isOpen) {
            panel.classList.toggle('is-open', isOpen);
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        }

        setOpen(false);

        toggle.addEventListener('click', function (event) {
            event.preventDefault();
            var isOpen = panel.classList.contains('is-open');
            setOpen(!isOpen);
        });

        document.addEventListener('click', function (event) {
            if (!panel.classList.contains('is-open')) return;
            if (panel.contains(event.target) || toggle.contains(event.target)) return;
            setOpen(false);
        });
    }

    window.BioTernDashboard = {
        exportAttendanceCSV: exportAttendanceCSV,
        exportRecentActivitiesCSV: exportRecentActivitiesCSV,
        toggleAttendanceCompact: toggleAttendanceCompact,
        toggleActivitiesCompact: toggleActivitiesCompact,
        openAttendanceRecord: openAttendanceRecord,
        goToAttendance: function () {
            goTo('attendance.php');
        },
        goToStudents: function () {
            goTo('students.php');
        },
        goToApplicationsReview: function () {
            goTo('applications-review.php');
        },
        goToReports: function () {
            goTo('reports-timesheets.php');
        },
    };

    function initHomepage() {
        initHomepageMovable();
        initHomepageRuntime();
        initActionsPanelToggle();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initHomepage);
    } else {
        initHomepage();
    }
})();
