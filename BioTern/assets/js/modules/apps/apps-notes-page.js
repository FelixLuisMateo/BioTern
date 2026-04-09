(function () {
    var CATEGORY_LABELS = {
        internship: 'Internship',
        meeting: 'Meeting',
        requirement: 'Requirement',
        reminder: 'Reminder',
        personal: 'Personal'
    };

    var TYPE_LABELS = {
        text: 'Text',
        checklist: 'Checklist'
    };

    var state = {
        notes: [],
        filter: 'active',
        search: '',
        sort: 'recent',
        selectedId: 0,
        saveTimer: 0,
        checklistDraft: [],
        studentMode: false
    };

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatCount(value) {
        return value === 1 ? '1 note' : value + ' notes';
    }

    function formatDateTime(value) {
        if (!value) {
            return 'Just now';
        }
        var date = new Date(String(value).replace(' ', 'T'));
        if (isNaN(date.getTime())) {
            return 'Just now';
        }
        return new Intl.DateTimeFormat('en-PH', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit'
        }).format(date);
    }

    function sortNotes(notes) {
        return notes.slice().sort(function (a, b) {
            if ((a.is_deleted ? 1 : 0) !== (b.is_deleted ? 1 : 0)) {
                return (a.is_deleted ? 1 : 0) - (b.is_deleted ? 1 : 0);
            }
            if ((a.is_pinned ? 1 : 0) !== (b.is_pinned ? 1 : 0)) {
                return (b.is_pinned ? 1 : 0) - (a.is_pinned ? 1 : 0);
            }
            if (state.sort === 'title') {
                return String(a.title || '').localeCompare(String(b.title || ''));
            }
            return String(b.updated_at || '').localeCompare(String(a.updated_at || ''));
        });
    }

    function parseChecklist(content) {
        try {
            var parsed = JSON.parse(String(content || '[]'));
            if (Array.isArray(parsed)) {
                return parsed.map(function (item) {
                    return {
                        text: String(item && item.text || '').trim(),
                        done: !!(item && item.done)
                    };
                }).filter(function (item) {
                    return item.text !== '';
                });
            }
        } catch (error) {}
        return [];
    }

    function serializeChecklist(items) {
        return JSON.stringify((items || []).map(function (item) {
            return {
                text: String(item && item.text || '').trim(),
                done: !!(item && item.done)
            };
        }).filter(function (item) {
            return item.text !== '';
        }));
    }

    function checklistPreview(content) {
        var items = parseChecklist(content);
        if (!items.length) {
            return 'No checklist items yet.';
        }
        var done = items.filter(function (item) { return item.done; }).length;
        return done + '/' + items.length + ' items done';
    }

    function getPreview(note) {
        if (note.note_type === 'checklist') {
            return checklistPreview(note.content);
        }
        var clean = String(note.content || '').replace(/\s+/g, ' ').trim();
        if (!clean) {
            return 'No content yet. Start writing here.';
        }
        return clean.length > 120 ? clean.slice(0, 117) + '...' : clean;
    }

    function getSelectedNote() {
        return state.notes.find(function (note) {
            return Number(note.id) === Number(state.selectedId);
        }) || null;
    }

    function getFilteredNotes() {
        var search = state.search.trim().toLowerCase();
        return sortNotes(state.notes).filter(function (note) {
            if (state.filter === 'trash') {
                if (!note.is_deleted) {
                    return false;
                }
            } else {
                if (note.is_deleted) {
                    return false;
                }
                if (state.filter === 'active' && note.is_archived) {
                    return false;
                }
                if (state.filter === 'pinned' && !note.is_pinned) {
                    return false;
                }
            }
            if (!search) {
                return true;
            }
            var haystack = [note.title, note.content, CATEGORY_LABELS[note.category] || note.category, TYPE_LABELS[note.note_type] || note.note_type].join(' ').toLowerCase();
            return haystack.indexOf(search) !== -1;
        });
    }

    function setStatus(text) {
        var el = document.querySelector('[data-editor-status]');
        if (el) {
            el.textContent = text;
        }
    }

    function renderCounts() {
        var counts = { active: 0, pinned: 0, trash: 0 };
        state.notes.forEach(function (note) {
            if (note.is_deleted) {
                counts.trash += 1;
                return;
            }
            if (!note.is_archived) {
                counts.active += 1;
            }
            if (note.is_pinned) {
                counts.pinned += 1;
            }
        });

        Object.keys(counts).forEach(function (key) {
            var el = document.querySelector('[data-count-' + key + ']');
            if (el) {
                el.textContent = counts[key];
            }
        });
    }

    function renderList() {
        var list = document.querySelector('[data-notes-list]');
        var count = document.querySelector('[data-visible-count]');
        if (!list || !count) {
            return;
        }

        var filtered = getFilteredNotes();
        count.textContent = formatCount(filtered.length);

        if (!filtered.length) {
            list.innerHTML = '<div class="app-notes-list-empty">No notes match this view yet.</div>';
            return;
        }

        list.innerHTML = filtered.map(function (note) {
            var badges = [
                '<span class="app-notes-badge is-category-' + escapeHtml(note.category) + '">' + escapeHtml(CATEGORY_LABELS[note.category] || 'Note') + '</span>',
                '<span class="app-notes-badge is-' + escapeHtml(note.note_type) + '">' + escapeHtml(TYPE_LABELS[note.note_type] || 'Text') + '</span>'
            ];
            if (note.note_type === 'checklist') {
                badges.push('<span class="app-notes-badge is-progress">' + escapeHtml(checklistPreview(note.content)) + '</span>');
            }
            if (!state.studentMode && note.is_pinned) badges.push('<span class="app-notes-badge is-pinned">Pinned</span>');
            if (note.is_archived) badges.push('<span class="app-notes-badge is-archived">Archived</span>');
            if (note.is_deleted) badges.push('<span class="app-notes-badge is-deleted">Trash</span>');

            return [
                '<button type="button" class="app-notes-list-item',
                Number(state.selectedId) === Number(note.id) ? ' is-selected' : '',
                '" data-note-id="', escapeHtml(note.id), '" style="--note-accent:', escapeHtml(note.accent_color || '#2563eb'), '">',
                '<div class="app-notes-list-top">',
                '<h4 class="app-notes-list-title">', escapeHtml(note.title || 'Untitled note'), '</h4>',
                '<span class="app-notes-editor-time">', escapeHtml(formatDateTime(note.updated_at)), '</span>',
                '</div>',
                '<p class="app-notes-list-preview">', escapeHtml(getPreview(note)), '</p>',
                '<div class="app-notes-list-bottom"><div class="app-notes-meta-badges">', badges.join(''), '</div></div>',
                '</button>'
            ].join('');
        }).join('');
    }

    function syncEditorButtons(note) {
        ['pin', 'archive'].forEach(function (action) {
            var button = document.querySelector('[data-action="' + action + '"]');
            if (!button) return;
            var active = action === 'pin' ? !!note.is_pinned : !!note.is_archived;
            button.classList.toggle('is-active', active);
        });

        var restoreButton = document.querySelector('[data-action="restore"]');
        var deleteButton = document.querySelector('[data-action="delete"]');
        var duplicateButton = document.querySelector('[data-action="duplicate"]');
        if (restoreButton) restoreButton.classList.toggle('d-none', !note.is_deleted);
        if (deleteButton) deleteButton.classList.toggle('d-none', !!note.is_deleted);
        if (duplicateButton) duplicateButton.classList.toggle('d-none', !!note.is_deleted);
    }

    function renderChecklist(note) {
        var wrap = document.querySelector('[data-checklist-wrap]');
        var list = document.querySelector('[data-checklist-list]');
        var bodyWrap = document.querySelector('[data-editor-body-wrap]');
        if (!wrap || !list || !bodyWrap) {
            return;
        }

        if (!note || note.note_type !== 'checklist') {
            wrap.hidden = true;
            bodyWrap.hidden = false;
            return;
        }

        wrap.hidden = false;
        bodyWrap.hidden = true;

        state.checklistDraft = parseChecklist(note.content);
        if (!state.checklistDraft.length) {
            state.checklistDraft = [{ text: '', done: false }];
        }

        list.innerHTML = state.checklistDraft.map(function (item, index) {
            return [
                '<div class="app-notes-checklist-item" data-check-index="', index, '">',
                '<input type="checkbox" ', item.done ? 'checked' : '', ' data-check-done="', index, '">',
                '<input type="text" value="', escapeHtml(item.text), '" placeholder="Checklist item" data-check-text="', index, '">',
                '<button type="button" class="app-notes-checklist-remove" data-check-remove="', index, '" aria-label="Remove item"><i class="feather-x"></i></button>',
                '</div>'
            ].join('');
        }).join('');
    }

    function renderEditor() {
        var empty = document.querySelector('[data-editor-empty]');
        var form = document.querySelector('[data-editor-form]');
        var note = getSelectedNote();
        var updated = document.querySelector('[data-editor-updated]');
        var title = document.querySelector('[data-editor-title]');
        var body = document.querySelector('[data-editor-body]');
        var category = document.querySelector('[data-editor-category]');
        var type = document.querySelector('[data-editor-type]');
        var color = document.querySelector('[data-editor-color]');
        var kicker = document.querySelector('[data-editor-kicker]');

        if (!empty || !form || !updated || !title || !body || !category || !type || !kicker) {
            return;
        }

        if (!note) {
            empty.hidden = false;
            form.hidden = true;
            return;
        }

        empty.hidden = true;
        form.hidden = false;
        title.value = note.title || '';
        body.value = note.note_type === 'checklist' ? '' : (note.content || '');
        category.value = note.category || 'internship';
        type.value = note.note_type || 'text';
        if (color) {
            color.value = note.accent_color || '#2563eb';
        }
        updated.textContent = 'Updated ' + formatDateTime(note.updated_at);
        kicker.textContent = note.is_deleted ? 'In trash' : (note.is_archived ? 'Archived note' : 'Active note');
        setStatus('Saved');
        syncEditorButtons(note);
        renderChecklist(note);
    }

    function renderAll() {
        renderCounts();
        renderList();
        renderEditor();
    }

    function request(url, options) {
        return fetch(url, Object.assign({
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        }, options || {})).then(function (response) {
            return response.json().catch(function () {
                return { success: false, message: 'Invalid server response' };
            }).then(function (payload) {
                if (!response.ok || !payload.success) {
                    throw new Error(payload.message || ('Request failed (' + response.status + ')'));
                }
                return payload;
            });
        });
    }

    function syncSelectedAfterLoad() {
        var selected = getSelectedNote();
        if (selected) return;
        var filtered = getFilteredNotes();
        state.selectedId = filtered.length ? Number(filtered[0].id) : 0;
    }

    function loadNotes() {
        var root = document.querySelector('[data-notes-app]');
        if (!root) return Promise.resolve();
        state.studentMode = root.getAttribute('data-student-mode') === '1';
        return request(root.getAttribute('data-notes-endpoint'), { method: 'GET' }).then(function (payload) {
            state.notes = Array.isArray(payload.notes) ? payload.notes : [];
            syncSelectedAfterLoad();
            renderAll();
        }).catch(function (error) {
            var list = document.querySelector('[data-notes-list]');
            if (list) {
                list.innerHTML = '<div class="app-notes-list-empty">' + escapeHtml(error.message || 'Unable to load notes right now.') + '</div>';
            }
        });
    }

    function insertNote(note) {
        state.notes = [note].concat(state.notes.filter(function (item) {
            return Number(item.id) !== Number(note.id);
        }));
        state.selectedId = Number(note.id);
        renderAll();
    }

    function getEndpoint() {
        var root = document.querySelector('[data-notes-app]');
        return root ? root.getAttribute('data-notes-endpoint') : '';
    }

    function createNote(templateKey) {
        var endpoint = getEndpoint();
        if (!endpoint) return;
        var safeTemplate = templateKey || 'blank';
        if (state.studentMode && safeTemplate === 'meeting') {
            safeTemplate = 'blank';
        }
        request(endpoint, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'create',
                template: safeTemplate,
                category: 'internship'
            })
        }).then(function (payload) {
            if (payload.note) {
                insertNote(payload.note);
                var titleInput = document.querySelector('[data-editor-title]');
                if (titleInput) {
                    titleInput.focus();
                    titleInput.select();
                }
            }
        }).catch(function (error) {
            setStatus(error.message || 'Unable to create note');
        });
    }

    function updateSelectedLocal(fields) {
        var note = getSelectedNote();
        if (!note) return null;
        Object.assign(note, fields || {});
        return note;
    }

    function syncChecklistToNote() {
        var note = getSelectedNote();
        if (!note || note.note_type !== 'checklist') return null;
        note.content = serializeChecklist(state.checklistDraft);
        return note;
    }

    function saveSelectedNote(immediate) {
        var endpoint = getEndpoint();
        var note = getSelectedNote();
        if (!endpoint || !note || note.is_deleted) return;

        window.clearTimeout(state.saveTimer);
        var run = function () {
            setStatus('Saving...');
            request(endpoint, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'update',
                    id: note.id,
                    title: note.title,
                    content: note.content,
                    category: note.category,
                    note_type: note.note_type,
                    accent_color: note.accent_color,
                    is_pinned: note.is_pinned ? 1 : 0,
                    is_archived: note.is_archived ? 1 : 0
                })
            }).then(function (payload) {
                if (payload.note) {
                    var index = state.notes.findIndex(function (item) { return Number(item.id) === Number(payload.note.id); });
                    if (index !== -1) state.notes[index] = payload.note;
                    renderAll();
                }
            }).catch(function (error) {
                setStatus(error.message || 'Save failed');
            });
        };

        if (immediate) run();
        else state.saveTimer = window.setTimeout(run, 450);
    }

    function deleteSelectedNote() {
        var endpoint = getEndpoint();
        var note = getSelectedNote();
        if (!endpoint || !note) return;
        if (!window.confirm('Move this note to trash?')) return;

        request(endpoint, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ action: 'delete', id: note.id })
        }).then(function () {
            updateSelectedLocal({ is_deleted: 1, deleted_at: new Date().toISOString().slice(0, 19).replace('T', ' ') });
            syncSelectedAfterLoad();
            renderAll();
        }).catch(function (error) {
            setStatus(error.message || 'Delete failed');
        });
    }

    function restoreSelectedNote() {
        var endpoint = getEndpoint();
        var note = getSelectedNote();
        if (!endpoint || !note) return;

        request(endpoint, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ action: 'restore', id: note.id })
        }).then(function (payload) {
            if (payload.note) {
                var index = state.notes.findIndex(function (item) { return Number(item.id) === Number(payload.note.id); });
                if (index !== -1) state.notes[index] = payload.note;
                state.selectedId = Number(payload.note.id);
                renderAll();
            }
        }).catch(function (error) {
            setStatus(error.message || 'Restore failed');
        });
    }

    function duplicateSelectedNote() {
        var endpoint = getEndpoint();
        var note = getSelectedNote();
        if (!endpoint || !note) return;

        request(endpoint, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ action: 'duplicate', id: note.id })
        }).then(function (payload) {
            if (payload.note) {
                insertNote(payload.note);
            }
        }).catch(function (error) {
            setStatus(error.message || 'Duplicate failed');
        });
    }

    function bindEvents() {
        document.querySelectorAll('[data-create-note]').forEach(function (button) {
            button.addEventListener('click', function () {
                createNote(button.getAttribute('data-template') || 'blank');
            });
        });

        document.querySelectorAll('[data-template]').forEach(function (button) {
            button.addEventListener('click', function () {
                createNote(button.getAttribute('data-template') || 'blank');
            });
        });

        var createMenu = document.querySelector('[data-create-menu]');
        var createToggle = document.querySelector('[data-create-menu-toggle]');
        var createPanel = document.querySelector('[data-create-menu-panel]');
        if (createMenu && createToggle && createPanel) {
            createToggle.addEventListener('click', function () {
                var isOpen = !createPanel.hidden;
                createPanel.hidden = isOpen;
                createToggle.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
            });

            createMenu.addEventListener('click', function (event) {
                if (event.target.closest('[data-create-note]') || event.target.closest('[data-template]')) {
                    createPanel.hidden = true;
                    createToggle.setAttribute('aria-expanded', 'false');
                }
            });

            document.addEventListener('click', function (event) {
                if (!createMenu.contains(event.target)) {
                    createPanel.hidden = true;
                    createToggle.setAttribute('aria-expanded', 'false');
                }
            });
        }

        document.querySelectorAll('[data-filter]').forEach(function (button) {
            button.addEventListener('click', function () {
                state.filter = button.getAttribute('data-filter') || 'active';
                document.querySelectorAll('[data-filter]').forEach(function (item) {
                    item.classList.toggle('is-active', item === button);
                });
                syncSelectedAfterLoad();
                renderAll();
            });
        });

        var search = document.querySelector('[data-search-input]');
        if (search) {
            search.addEventListener('input', function () {
                state.search = search.value || '';
                syncSelectedAfterLoad();
                renderAll();
            });
        }

        var sortSelect = document.querySelector('[data-sort-select]');
        if (sortSelect) {
            sortSelect.addEventListener('change', function () {
                state.sort = sortSelect.value || 'recent';
                renderList();
            });
        }

        var list = document.querySelector('[data-notes-list]');
        if (list) {
            list.addEventListener('click', function (event) {
                var target = event.target.closest('[data-note-id]');
                if (!target) return;
                state.selectedId = Number(target.getAttribute('data-note-id') || 0);
                renderAll();
            });
        }

        var title = document.querySelector('[data-editor-title]');
        var body = document.querySelector('[data-editor-body]');
        var category = document.querySelector('[data-editor-category]');
        var type = document.querySelector('[data-editor-type]');
        var color = document.querySelector('[data-editor-color]');
        var checklistAdd = document.querySelector('[data-checklist-add]');
        var checklistList = document.querySelector('[data-checklist-list]');

        if (title) {
            title.addEventListener('input', function () {
                var note = updateSelectedLocal({ title: title.value || 'Untitled note' });
                if (note) {
                    renderList();
                    setStatus('Unsaved');
                    saveSelectedNote(false);
                }
            });
        }

        if (body) {
            body.addEventListener('input', function () {
                var note = updateSelectedLocal({ content: body.value || '' });
                if (note) {
                    renderList();
                    setStatus('Unsaved');
                    saveSelectedNote(false);
                }
            });
        }

        if (category) {
            category.addEventListener('change', function () {
                var note = updateSelectedLocal({ category: category.value || 'internship' });
                if (note) {
                    renderList();
                    setStatus('Unsaved');
                    saveSelectedNote(false);
                }
            });
        }

        if (type) {
            type.addEventListener('change', function () {
                var note = getSelectedNote();
                if (!note) return;
                note.note_type = type.value || 'text';
                if (note.note_type === 'checklist') {
                    state.checklistDraft = parseChecklist(note.content);
                    note.content = serializeChecklist(state.checklistDraft);
                }
                renderEditor();
                renderList();
                setStatus('Unsaved');
                saveSelectedNote(false);
            });
        }

        if (color) {
            color.addEventListener('input', function () {
                var note = updateSelectedLocal({ accent_color: color.value || '#2563eb' });
                if (note) {
                    renderList();
                    setStatus('Unsaved');
                    saveSelectedNote(false);
                }
            });
        }

        if (checklistAdd) {
            checklistAdd.addEventListener('click', function () {
                var note = getSelectedNote();
                if (!note || note.note_type !== 'checklist') return;
                state.checklistDraft.push({ text: '', done: false });
                syncChecklistToNote();
                renderChecklist(note);
                renderList();
                setStatus('Unsaved');
                saveSelectedNote(false);
            });
        }

        if (checklistList) {
            checklistList.addEventListener('input', function (event) {
                var textTarget = event.target.closest('[data-check-text]');
                if (!textTarget) return;
                var index = Number(textTarget.getAttribute('data-check-text') || -1);
                if (index < 0 || !state.checklistDraft[index]) return;
                state.checklistDraft[index].text = textTarget.value || '';
                syncChecklistToNote();
                renderList();
                setStatus('Unsaved');
                saveSelectedNote(false);
            });

            checklistList.addEventListener('change', function (event) {
                var doneTarget = event.target.closest('[data-check-done]');
                if (!doneTarget) return;
                var index = Number(doneTarget.getAttribute('data-check-done') || -1);
                if (index < 0 || !state.checklistDraft[index]) return;
                state.checklistDraft[index].done = !!doneTarget.checked;
                syncChecklistToNote();
                renderList();
                setStatus('Unsaved');
                saveSelectedNote(false);
            });

            checklistList.addEventListener('click', function (event) {
                var removeTarget = event.target.closest('[data-check-remove]');
                if (!removeTarget) return;
                var index = Number(removeTarget.getAttribute('data-check-remove') || -1);
                if (index < 0) return;
                state.checklistDraft.splice(index, 1);
                if (!state.checklistDraft.length) {
                    state.checklistDraft.push({ text: '', done: false });
                }
                var note = syncChecklistToNote();
                renderChecklist(note);
                renderList();
                setStatus('Unsaved');
                saveSelectedNote(false);
            });
        }

        document.querySelectorAll('[data-action]').forEach(function (button) {
            button.addEventListener('click', function () {
                var action = button.getAttribute('data-action');
                var note = getSelectedNote();
                if (!note) return;

                if (action === 'delete') return deleteSelectedNote();
                if (action === 'restore') return restoreSelectedNote();
                if (action === 'duplicate') return duplicateSelectedNote();
                if (note.is_deleted) return;

                if (action === 'pin') note.is_pinned = note.is_pinned ? 0 : 1;
                if (action === 'archive') note.is_archived = note.is_archived ? 0 : 1;

                renderAll();
                saveSelectedNote(true);
            });
        });

        document.addEventListener('keydown', function (event) {
            if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
                event.preventDefault();
                saveSelectedNote(true);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (!document.querySelector('[data-notes-app]')) return;
        bindEvents();
        loadNotes();
    });
})();
