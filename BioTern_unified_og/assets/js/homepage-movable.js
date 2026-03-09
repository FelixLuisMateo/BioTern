(function () {
    var CARD_REGISTRY = {
        'overview-hero': { name: 'Overview Hero', init: initOverviewHeroCard },
        'kpi-strip': { name: 'KPI Strip', init: initKpiStripCard },
        'latest-attendance': { name: 'Latest Attendance Records', init: initLatestAttendanceCard },
        'biometric-status': { name: 'Biometric Registration Status', init: initBiometricStatusCard },
        'operations-pulse': { name: 'Operations Pulse', init: initOperationsPulseCard },
        'priority-items': { name: 'Priority Items', init: initPriorityItemsCard },
        'recent-activities': { name: 'Recent Activities & Logs', init: initRecentActivitiesCard },
        'admin-quick-actions': { name: 'Admin Quick Actions', init: initAdminQuickActionsCard },
        'coordinators': { name: 'Coordinators', init: initCoordinatorsCard },
        'supervisors': { name: 'Supervisors', init: initSupervisorsCard }
    };

    function initHomepageMovable() {
        var shell = document.querySelector('.main-content.dashboard-shell');
        var row = document.querySelector('.main-content.dashboard-shell > .row');
        if (!row) return;

        var storageKey = 'biotern-homepage-layout-v1';
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

        function setEditMode(enabled) {
            isEditMode = !!enabled;
            if (shell) {
                shell.classList.toggle('layout-edit-active', isEditMode);
            }

            cards.forEach(function (item) {
                item.setAttribute('draggable', isEditMode ? 'true' : 'false');
                var handle = item.querySelector('.dashboard-move-handle');
                if (handle) {
                    handle.setAttribute('draggable', isEditMode ? 'true' : 'false');
                }
            });

            var toggleBtn = document.getElementById('toggle-dashboard-layout');
            if (toggleBtn) {
                toggleBtn.innerHTML = isEditMode
                    ? '<i class="feather-check me-1"></i> Done'
                    : '<i class="feather-move me-1"></i> Edit Layout';
                toggleBtn.classList.toggle('btn-primary', isEditMode);
                toggleBtn.classList.toggle('btn-light-brand', !isEditMode);
            }
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

        var dragged = null;

        function saveOrder() {
            var order = Array.from(row.querySelectorAll('.dashboard-movable[data-move-key]')).map(function (item) {
                return item.dataset.moveKey;
            });
            localStorage.setItem(storageKey, JSON.stringify(order));
        }

        function clearTargets() {
            row.querySelectorAll('.dashboard-movable.drag-target').forEach(function (item) {
                item.classList.remove('drag-target');
            });
        }

        function resetLayout() {
            localStorage.removeItem(storageKey);
            applyOrder(defaultOrder);
        }

        var resetButton = document.getElementById('reset-dashboard-layout');
        if (resetButton) {
            resetButton.addEventListener('click', function () {
                resetLayout();
            });
        }

        var toggleLayoutButton = document.getElementById('toggle-dashboard-layout');
        if (toggleLayoutButton) {
            toggleLayoutButton.addEventListener('click', function () {
                setEditMode(!isEditMode);
            });
        }

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
            var handle = item.querySelector('.dashboard-move-handle');
            if (!handle) return;

            item.setAttribute('draggable', 'true');
            handle.setAttribute('draggable', 'true');

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

            handle.addEventListener('dragstart', beginDrag);
            item.addEventListener('dragstart', beginDrag);

            handle.addEventListener('dragend', function () {
                if (dragged) dragged.classList.remove('dragging');
                dragged = null;
                clearTargets();
            });

            item.addEventListener('dragover', function (event) {
                if (!isEditMode || !dragged || dragged === item) return;
                event.preventDefault();
                item.classList.add('drag-target');
                if (event.dataTransfer) {
                    event.dataTransfer.dropEffect = 'move';
                }
            });

            item.addEventListener('dragleave', function () {
                item.classList.remove('drag-target');
            });

            item.addEventListener('drop', function (event) {
                if (!isEditMode || !dragged || dragged === item) return;
                event.preventDefault();
                item.classList.remove('drag-target');

                var itemRect = item.getBoundingClientRect();
                var placeAfter = event.clientY > (itemRect.top + itemRect.height / 2);
                row.insertBefore(dragged, placeAfter ? item.nextSibling : item);

                saveOrder();
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
            var pagination = attendanceCardNode.querySelector('#latest-attendance-pagination');
            var pageLinks = pagination ? Array.from(pagination.querySelectorAll('[data-role="page"]')) : [];
            var pageSize = 5;
            var currentPage = 1;

            function renderAttendancePage(page, showAll) {
                if (!tableRows.length) return;
                var totalPages = Math.max(1, Math.ceil(tableRows.length / pageSize));
                currentPage = Math.min(totalPages, Math.max(1, page));
                var start = (currentPage - 1) * pageSize;
                var end = start + pageSize;

                tableRows.forEach(function (line, index) {
                    var visible = showAll || (index >= start && index < end);
                    line.style.display = visible ? '' : 'none';
                });

                pageLinks.forEach(function (link, index) {
                    var pageNumber = index + 1;
                    link.classList.toggle('active', pageNumber === currentPage && !showAll);
                    link.parentElement.style.display = pageNumber <= totalPages ? '' : 'none';
                });
            }

            attendanceCardNode._renderAttendancePage = renderAttendancePage;

            if (pagination) {
                pagination.addEventListener('click', function (event) {
                    var target = event.target.closest('a[data-role]');
                    if (!target) return;
                    event.preventDefault();

                    var role = target.getAttribute('data-role');
                    if (role === 'prev') {
                        renderAttendancePage(currentPage - 1, false);
                    } else if (role === 'next') {
                        renderAttendancePage(currentPage + 1, false);
                    } else if (role === 'page') {
                        var page = parseInt(target.getAttribute('data-page') || '1', 10);
                        renderAttendancePage(page, false);
                    } else if (role === 'view-all') {
                        renderAttendancePage(1, true);
                        pageLinks.forEach(function (link) { link.classList.remove('active'); });
                    }
                });
            }

            renderAttendancePage(1, false);
    }

    function initRecentActivitiesCard(cardNode) {
        var recentActivityCard = cardNode ? cardNode.querySelector('.card') : null;
        if (recentActivityCard) {
            var activityItems = recentActivityCard.querySelectorAll('.card-body .border-bottom');
            activityItems.forEach(function (item, index) {
                if (index >= 8) {
                    item.setAttribute('data-expand-detail', '1');
                    item.classList.add('detail-hidden');
                }
            });
        }
    }

    function initOverviewHeroCard() {}
    function initKpiStripCard() {}
    function initBiometricStatusCard() {}
    function initOperationsPulseCard() {}
    function initPriorityItemsCard() {}
    function initAdminQuickActionsCard() {}
    function initCoordinatorsCard() {}
    function initSupervisorsCard() {}

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initHomepageMovable);
    } else {
        initHomepageMovable();
    }
})();
