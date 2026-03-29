document.addEventListener('DOMContentLoaded', function () {
    var root = document.querySelector('[data-calendar-app]');
    if (!root) {
        return;
    }

    var state = {
        currentDate: new Date(),
        selectedDateKey: formatDateKey(new Date()),
        events: []
    };

    var monthLabel = root.querySelector('[data-month-label]');
    var calendarGrid = root.querySelector('[data-calendar-grid]');
    var selectedDateLabel = root.querySelector('[data-selected-date-label]');
    var selectedSummary = root.querySelector('[data-selected-summary]');
    var selectedEvents = root.querySelector('[data-selected-events]');
    var upcomingBirthdays = root.querySelector('[data-upcoming-birthdays]');
    var monthEvents = root.querySelector('[data-month-events]');
    var endpoint = root.getAttribute('data-events-endpoint') || 'calendar_events.php';
    var navButtons = Array.prototype.slice.call(root.querySelectorAll('[data-action]'));
    var jumpMonth = root.querySelector('[data-jump-month]');
    var jumpYear = root.querySelector('[data-jump-year]');

    buildJumpControls();

    navButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            var action = button.getAttribute('data-action');
            if (action === 'prev') {
                state.currentDate = new Date(state.currentDate.getFullYear(), state.currentDate.getMonth() - 1, 1);
            } else if (action === 'next') {
                state.currentDate = new Date(state.currentDate.getFullYear(), state.currentDate.getMonth() + 1, 1);
            } else {
                state.currentDate = new Date();
                state.selectedDateKey = formatDateKey(new Date());
            }

            syncJumpControls();
            loadEvents().then(render).catch(renderError);
        });
    });

    if (jumpMonth) {
        jumpMonth.addEventListener('change', function () {
            state.currentDate = new Date(state.currentDate.getFullYear(), Number(jumpMonth.value), 1);
            loadEvents().then(render).catch(renderError);
        });
    }

    if (jumpYear) {
        jumpYear.addEventListener('change', function () {
            state.currentDate = new Date(Number(jumpYear.value), state.currentDate.getMonth(), 1);
            loadEvents().then(render).catch(renderError);
        });
    }

    syncJumpControls();
    loadEvents().then(render).catch(renderError);

    function buildJumpControls() {
        if (jumpMonth) {
            var monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
            jumpMonth.innerHTML = monthNames.map(function (name, index) {
                return '<option value="' + index + '">' + name + '</option>';
            }).join('');
        }

        if (jumpYear) {
            var currentYear = new Date().getFullYear();
            var years = [];
            for (var year = currentYear - 2; year <= currentYear + 4; year += 1) {
                years.push('<option value="' + year + '">' + year + '</option>');
            }
            jumpYear.innerHTML = years.join('');
        }
    }

    function syncJumpControls() {
        if (jumpMonth) {
            jumpMonth.value = String(state.currentDate.getMonth());
        }
        if (jumpYear) {
            jumpYear.value = String(state.currentDate.getFullYear());
        }
    }

    function buildUrl() {
        var days = buildMonthDays(state.currentDate);
        var firstVisible = days[0].key;
        var lastVisible = days[days.length - 1].key;
        return endpoint + '?from=' + encodeURIComponent(firstVisible + ' 00:00:00')
            + '&to=' + encodeURIComponent(lastVisible + ' 23:59:59');
    }

    function loadEvents() {
        return fetch(buildUrl(), {
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Unable to load calendar events (' + response.status + ')');
                }
                return response.json();
            })
            .then(function (payload) {
                if (!payload || payload.success === false) {
                    throw new Error(payload && payload.message ? payload.message : 'Calendar API returned an invalid response');
                }
                state.events = Array.isArray(payload.events) ? payload.events : [];
                syncSelectedDate();
            });
    }

    function syncSelectedDate() {
        var visibleKeys = buildMonthDays(state.currentDate).map(function (entry) {
            return entry.key;
        });
        if (visibleKeys.indexOf(state.selectedDateKey) === -1) {
            state.selectedDateKey = formatDateKey(new Date(state.currentDate.getFullYear(), state.currentDate.getMonth(), 1));
        }
    }

    function render() {
        syncJumpControls();
        renderMonthHeader();
        renderGrid();
        renderSelectedDay();
        renderUpcomingBirthdays();
        renderMonthTimeline();
    }

    function renderMonthHeader() {
        if (!monthLabel) {
            return;
        }
        monthLabel.textContent = state.currentDate.toLocaleDateString('en-PH', {
            month: 'long',
            year: 'numeric'
        });
    }

    function renderGrid() {
        var days = buildMonthDays(state.currentDate);
        var todayKey = formatDateKey(new Date());

        calendarGrid.innerHTML = '';

        days.forEach(function (day) {
            var dayEvents = eventsForDate(day.key);
            var cell = document.createElement('button');
            cell.type = 'button';
            cell.className = 'app-calendar-day'
                + (day.isCurrentMonth ? '' : ' is-outside')
                + (day.key === todayKey ? ' is-today' : '')
                + (day.key === state.selectedDateKey ? ' is-selected' : '');

            var head = document.createElement('div');
            head.className = 'app-calendar-day-head';

            var number = document.createElement('span');
            number.className = 'app-calendar-day-number';
            number.textContent = String(day.date.getDate());
            head.appendChild(number);

            if (dayEvents.length) {
                var badge = document.createElement('span');
                badge.className = 'app-calendar-day-badge';
                badge.textContent = dayEvents.length + ' event' + (dayEvents.length === 1 ? '' : 's');
                head.appendChild(badge);
            }

            var list = document.createElement('div');
            list.className = 'app-calendar-day-events';
            dayEvents.slice(0, 2).forEach(function (event) {
                var pill = document.createElement('div');
                pill.className = 'app-calendar-pill is-' + normalizeType(event.type);
                pill.textContent = formatDayCellLabel(event);
                list.appendChild(pill);
            });

            if (dayEvents.length > 2) {
                var overflow = document.createElement('div');
                overflow.className = 'app-calendar-overflow';
                overflow.textContent = '+' + (dayEvents.length - 2) + ' more';
                list.appendChild(overflow);
            }

            cell.appendChild(head);
            cell.appendChild(list);
            cell.addEventListener('click', function () {
                state.selectedDateKey = day.key;
                render();
            });

            calendarGrid.appendChild(cell);
        });
    }

    function renderSelectedDay() {
        var current = parseDateKey(state.selectedDateKey);
        var items = eventsForDate(state.selectedDateKey);

        if (selectedDateLabel) {
            selectedDateLabel.textContent = current.toLocaleDateString('en-PH', {
                weekday: 'long',
                month: 'long',
                day: 'numeric',
                year: 'numeric'
            });
        }

        if (selectedSummary) {
            selectedSummary.textContent = items.length
                ? items.length + ' event' + (items.length === 1 ? '' : 's') + ' scheduled for this day.'
                : 'No Philippine event, OJT birthday, or saved calendar item is scheduled for this day.';
        }

        selectedEvents.innerHTML = '';
        if (!items.length) {
            selectedEvents.innerHTML = '<div class="app-calendar-empty">This day is currently clear.</div>';
            return;
        }

        items.forEach(function (event) {
            var card = document.createElement(event.person && event.person.profile_url ? 'a' : 'article');
            card.className = 'app-calendar-event-card';
            if (event.person && event.person.profile_url) {
                card.href = event.person.profile_url;
                card.style.textDecoration = 'none';
            }

            var icon = document.createElement('div');
            icon.className = 'app-calendar-event-icon is-' + normalizeType(event.type);
            icon.innerHTML = event.type === 'birthday'
                ? '<i class="feather-gift"></i>'
                : (event.type === 'custom'
                    ? '<i class="feather-bookmark"></i>'
                    : '<i class="feather-flag"></i>');

            var meta = document.createElement('div');
            meta.className = 'app-calendar-event-meta';

            var tag = document.createElement('span');
            tag.className = 'app-calendar-event-tag';
            tag.textContent = event.category || readableType(event.type);
            meta.appendChild(tag);

            var title = document.createElement('h4');
            title.className = 'app-calendar-event-title';
            title.textContent = event.title;
            meta.appendChild(title);

            var desc = document.createElement('p');
            desc.className = 'app-calendar-event-desc';
            desc.textContent = event.description || defaultDescription(event);
            meta.appendChild(desc);

            if (event.person && (event.person.course || event.person.section)) {
                var person = document.createElement('p');
                person.className = 'app-calendar-event-desc';
                person.textContent = [event.person.course, event.person.section].filter(Boolean).join(' | ');
                meta.appendChild(person);
            } else if (event.location) {
                var location = document.createElement('p');
                location.className = 'app-calendar-event-desc';
                location.textContent = event.location;
                meta.appendChild(location);
            }

            card.appendChild(icon);
            card.appendChild(meta);
            selectedEvents.appendChild(card);
        });
    }

    function renderUpcomingBirthdays() {
        var selected = parseDateKey(state.selectedDateKey);
        var birthdays = state.events.filter(function (event) {
            return event.type === 'birthday';
        }).sort(function (left, right) {
            return Math.abs(parseDateKey(left.date).getTime() - selected.getTime())
                - Math.abs(parseDateKey(right.date).getTime() - selected.getTime());
        }).slice(0, 6);

        upcomingBirthdays.innerHTML = '';
        if (!birthdays.length) {
            upcomingBirthdays.innerHTML = '<div class="app-calendar-empty">No birthday data is available for this view.</div>';
            return;
        }

        birthdays.forEach(function (event) {
            var item = document.createElement(event.person && event.person.profile_url ? 'a' : 'article');
            item.className = 'app-calendar-mini-item';
            if (event.person && event.person.profile_url) {
                item.href = event.person.profile_url;
                item.style.textDecoration = 'none';
            }

            var avatar;
            if (event.person && event.person.avatar) {
                avatar = document.createElement('img');
                avatar.className = 'app-calendar-mini-avatar';
                avatar.src = event.person.avatar;
                avatar.alt = event.person.name || event.title;
            } else {
                avatar = document.createElement('div');
                avatar.className = 'app-calendar-mini-avatar is-icon';
                avatar.innerHTML = '<i class="feather-gift"></i>';
            }

            var meta = document.createElement('div');
            meta.className = 'app-calendar-mini-meta';

            var title = document.createElement('h4');
            title.className = 'app-calendar-mini-title';
            title.textContent = event.person && event.person.name ? event.person.name : event.title;
            meta.appendChild(title);

            var copy = document.createElement('p');
            copy.className = 'app-calendar-mini-copy';
            copy.textContent = event.person
                ? [event.person.course, event.person.section].filter(Boolean).join(' | ') || 'OJT birthday'
                : 'OJT birthday';
            meta.appendChild(copy);

            var date = document.createElement('span');
            date.className = 'app-calendar-mini-date';
            date.textContent = formatHumanDate(event.date);
            meta.appendChild(date);

            item.appendChild(avatar);
            item.appendChild(meta);
            upcomingBirthdays.appendChild(item);
        });
    }

    function renderMonthTimeline() {
        var month = state.currentDate.getMonth();
        var year = state.currentDate.getFullYear();
        var items = state.events.filter(function (event) {
            var date = parseDateKey(event.date);
            return date.getMonth() === month && date.getFullYear() === year && event.type === 'holiday';
        });

        monthEvents.innerHTML = '';
        if (!items.length) {
            monthEvents.innerHTML = '<div class="app-calendar-empty">No Philippine events for this month.</div>';
            return;
        }

        items.forEach(function (event) {
            var item = document.createElement('article');
            item.className = 'app-calendar-mini-item';

            var icon = document.createElement('div');
            icon.className = 'app-calendar-mini-avatar is-icon is-holiday';
            icon.innerHTML = '<i class="feather-flag"></i>';

            var meta = document.createElement('div');
            meta.className = 'app-calendar-mini-meta';

            var title = document.createElement('h4');
            title.className = 'app-calendar-mini-title';
            title.textContent = event.title;
            meta.appendChild(title);

            var copy = document.createElement('p');
            copy.className = 'app-calendar-mini-copy';
            copy.textContent = event.category || 'Philippine event';
            meta.appendChild(copy);

            var date = document.createElement('span');
            date.className = 'app-calendar-mini-date';
            date.textContent = formatHumanDate(event.date);
            meta.appendChild(date);

            item.appendChild(icon);
            item.appendChild(meta);
            monthEvents.appendChild(item);
        });
    }

    function eventsForDate(dateKey) {
        return state.events.filter(function (event) {
            var eventStart = String(event.start_at || event.date || '').slice(0, 10);
            var eventEnd = String(event.end_at || event.start_at || event.date || '').slice(0, 10);
            return eventStart <= dateKey && eventEnd >= dateKey;
        });
    }

    function buildMonthDays(date) {
        var first = new Date(date.getFullYear(), date.getMonth(), 1);
        var last = new Date(date.getFullYear(), date.getMonth() + 1, 0);
        var start = new Date(first);
        var end = new Date(last);

        start.setDate(first.getDate() - first.getDay());
        end.setDate(last.getDate() + (6 - last.getDay()));

        var days = [];
        var cursor = new Date(start);

        while (cursor <= end) {
            days.push({
                date: new Date(cursor),
                key: formatDateKey(cursor),
                isCurrentMonth: cursor.getMonth() === date.getMonth()
            });
            cursor.setDate(cursor.getDate() + 1);
        }

        return days;
    }

    function formatDateKey(date) {
        var year = date.getFullYear();
        var month = String(date.getMonth() + 1).padStart(2, '0');
        var day = String(date.getDate()).padStart(2, '0');
        return year + '-' + month + '-' + day;
    }

    function parseDateKey(key) {
        var parts = String(key).split('-');
        return new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
    }

    function normalizeType(type) {
        if (type === 'birthday' || type === 'custom') {
            return type;
        }
        return 'holiday';
    }

    function readableType(type) {
        if (type === 'birthday') {
            return 'Birthday';
        }
        if (type === 'custom') {
            return 'Saved Event';
        }
        return 'Philippine Event';
    }

    function formatDayCellLabel(event) {
        if (event.type === 'birthday') {
            if (event.person && event.person.name) {
                return firstName(event.person.name) + "'s Birthday";
            }
            return 'Birthday';
        }
        if (event.type === 'holiday') {
            return shortenLabel(event.title, 18);
        }
        return shortenLabel(event.title, 18);
    }

    function firstName(name) {
        return String(name || '').trim().split(/\s+/)[0] || 'OJT';
    }

    function shortenLabel(value, maxLength) {
        var text = String(value || '').trim();
        if (text.length <= maxLength) {
            return text;
        }
        return text.slice(0, Math.max(0, maxLength - 3)).trimEnd() + '...';
    }

    function defaultDescription(event) {
        if (event.type === 'birthday') {
            return 'Birthday reminder for an OJT in BioTern.';
        }
        if (event.type === 'custom') {
            return 'Saved event from the BioTern calendar.';
        }
        return 'Philippine holiday or observance.';
    }

    function formatHumanDate(key) {
        return parseDateKey(key).toLocaleDateString('en-PH', {
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        });
    }

    function renderError(error) {
        var message = error && error.message ? error.message : 'Unable to load calendar events right now.';
        calendarGrid.innerHTML = '<div class="app-calendar-empty" style="grid-column: 1 / -1;">' + escapeHtml(message) + '</div>';
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, function (character) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            }[character];
        });
    }
});


