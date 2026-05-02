
(() => {
    const app = document.querySelector('[data-storage-app]');
    if (!app) return;

    const endpoint = String(app.dataset.storageEndpoint || '').trim();
    const userRole = String(app.dataset.userRole || '').toLowerCase();
    const canManageShared = app.dataset.canManageShared === '1';
    const isStudent = userRole === 'student';
    const defaultUploadCategory = ['requirements', 'generated', 'internship', 'images', 'reports', 'other'].includes(String(app.dataset.defaultUploadCategory || '').trim().toLowerCase())
        ? String(app.dataset.defaultUploadCategory || '').trim().toLowerCase()
        : (isStudent ? 'requirements' : 'reports');
    const startUploadCategory = String(app.dataset.startUploadCategory || '').trim().toLowerCase();
    const startUploadTitle = String(app.dataset.startUploadTitle || '').trim();
    const startUploadNotes = String(app.dataset.startUploadNotes || '').trim();

    const state = {
        files: [], activity: [], requiredDocuments: [], historyCache: {},
        selectedId: 0, selectedIds: new Set(), scope: 'all', category: 'all',
        search: '', sort: 'recent', busy: false,
    };

    const els = {
        list: app.querySelector('[data-file-list]'),
        detailsEmpty: app.querySelector('[data-details-empty]'),
        detailsPanel: app.querySelector('[data-details-panel]'),
        visibleCount: app.querySelector('[data-visible-count]'),
        searchInput: app.querySelector('[data-search-input]'),
        sortSelect: app.querySelector('[data-sort-select]'),
        categorySelect: app.querySelector('[data-category-select]'),
        uploadPanel: app.querySelector('[data-upload-panel]'),
        uploadForm: app.querySelector('[data-upload-form]'),
        uploadId: app.querySelector('[data-upload-id]'),
        uploadAction: app.querySelector('[data-upload-action]'),
        uploadFile: app.querySelector('[data-upload-file]'),
        uploadTitle: app.querySelector('[data-upload-title-input]'),
        uploadCategory: app.querySelector('[data-upload-category]'),
        uploadScope: app.querySelector('[data-upload-scope]'),
        uploadAudienceWrap: app.querySelector('[data-upload-audience-wrap]'),
        uploadAudience: app.querySelector('[data-upload-audience]'),
        uploadTargetWrap: app.querySelector('[data-upload-target-wrap]'),
        uploadTargetUser: app.querySelector('[data-upload-target-user]'),
        uploadNotes: app.querySelector('[data-upload-notes]'),
        uploadTitleText: app.querySelector('[data-upload-title]'),
        uploadKicker: app.querySelector('[data-upload-kicker]'),
        uploadStatus: app.querySelector('[data-upload-status]'),
        uploadSubmit: app.querySelector('[data-upload-submit]'),
        uploadProgress: app.querySelector('[data-upload-progress]'),
        uploadProgressBar: app.querySelector('[data-upload-progress-bar]'),
        countAll: app.querySelector('[data-count-all]'),
        countMy: app.querySelector('[data-count-my]'),
        countShared: app.querySelector('[data-count-shared]'),
        countStarred: app.querySelector('[data-count-starred]'),
        countTrash: app.querySelector('[data-count-trash]'),
        dropzone: app.querySelector('[data-dropzone]'),
        dropzoneTitle: app.querySelector('[data-dropzone-title]'),
        dropzoneCopy: app.querySelector('[data-dropzone-copy]'),
        activityList: app.querySelector('[data-storage-activity]'),
        checklist: app.querySelector('[data-student-checklist]'),
        bulkbar: app.querySelector('[data-bulkbar]'),
        bulkToggleAll: app.querySelector('[data-bulk-toggle-all]'),
        bulkCount: app.querySelector('[data-bulk-count]'),
        bulkDelete: app.querySelector('[data-bulk-delete]'),
        bulkRestore: app.querySelector('[data-bulk-restore]'),
    };

    const scopeButtons = Array.from(app.querySelectorAll('[data-scope-filter]'));

    const escapeHtml = (value) => String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    const formatBytes = (bytes) => {
        const value = Number(bytes || 0);
        if (!value) return '0 B';
        const units = ['B', 'KB', 'MB', 'GB'];
        let size = value; let index = 0;
        while (size >= 1024 && index < units.length - 1) { size /= 1024; index += 1; }
        return `${size >= 10 || index === 0 ? size.toFixed(0) : size.toFixed(1)} ${units[index]}`;
    };
    const formatDateTime = (value) => {
        if (!value) return 'Just now';
        const parsed = new Date(String(value).replace(' ', 'T'));
        if (Number.isNaN(parsed.getTime())) return String(value);
        return parsed.toLocaleString(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
    };
    const fileTypeIcon = (type) => ({ image: 'feather-image', pdf: 'feather-file-text', spreadsheet: 'feather-grid', document: 'feather-file', archive: 'feather-package' }[type] || 'feather-paperclip');
    const categoryLabel = (category) => {
        if (category === 'requirements' && !isStudent) return 'Reports';
        return ({ requirements: 'Requirements', generated: 'Generated Docs', internship: 'Internship', images: 'Images', reports: 'Reports', other: 'Other' }[category] || 'Other');
    };
    const categoryValueForSave = (category) => {
        if (category === 'requirements' && !isStudent) return 'reports';
        return category || defaultUploadCategory;
    };
    const scopeLabel = (scope) => scope === 'shared' ? 'Shared' : 'Personal';
    const audienceLabel = (audience) => ({ all: 'All Users', student: 'Students', supervisor: 'Supervisors', user: 'Specific User' }[audience] || 'All Users');
    const activityLabel = (type) => ({ upload: 'Uploaded', update: 'Updated', replace: 'Replaced file', delete: 'Moved to trash', restore: 'Restored', toggle_star: 'Updated star', bulk_delete: 'Bulk delete', bulk_restore: 'Bulk restore' }[type] || 'Updated');

    function syncAudienceField() {
        if (!els.uploadAudienceWrap || !els.uploadAudience) return;
        const isShared = els.uploadScope && els.uploadScope.value === 'shared';
        els.uploadAudienceWrap.hidden = !isShared;
        if (!isShared) {
            els.uploadAudience.value = 'all';
        }
        if (els.uploadTargetWrap && els.uploadTargetUser) {
            const isSpecificUser = isShared && els.uploadAudience.value === 'user';
            els.uploadTargetWrap.hidden = !isSpecificUser;
            if (!isSpecificUser) {
                els.uploadTargetUser.value = '';
            }
        }
    }

    function getVisibleFiles() {
        const search = state.search.trim().toLowerCase();
        let files = state.files.filter((file) => {
            if (state.scope === 'trash') {
                if (!file.is_deleted) return false;
            } else {
                if (file.is_deleted) return false;
                if (state.scope === 'my' && !file.is_owner) return false;
                if (state.scope === 'shared' && file.scope !== 'shared') return false;
                if (state.scope === 'starred' && !file.is_starred) return false;
            }
            if (state.category !== 'all' && file.category !== state.category) return false;
            if (!search) return true;
            return [file.title, file.original_name, categoryLabel(file.category), file.scope, file.notes, file.uploader_name, file.shared_target_user_name].join(' ').toLowerCase().includes(search);
        });
        if (state.sort === 'name') files = files.sort((a, b) => a.title.localeCompare(b.title));
        else if (state.sort === 'size') files = files.sort((a, b) => Number(b.file_size || 0) - Number(a.file_size || 0));
        else files = files.sort((a, b) => String(b.updated_at || '').localeCompare(String(a.updated_at || '')));
        return files;
    }

    const getSelectedFile = () => state.files.find((file) => Number(file.id) === Number(state.selectedId)) || null;
    const getBulkEligibleFiles = () => getVisibleFiles().filter((file) => (state.scope === 'trash' ? file.can_restore : file.can_delete) && file.is_owner);
    function syncSelection() {
        const visible = getVisibleFiles();
        if (!visible.length) { state.selectedId = 0; return; }
        if (!visible.some((file) => Number(file.id) === Number(state.selectedId))) state.selectedId = Number(visible[0].id);
    }

    function setUploadMessage(message, isError = false) { if (!els.uploadStatus) return; els.uploadStatus.textContent = message; els.uploadStatus.style.color = isError ? '#fca5a5' : ''; }
    function setUploadProgress(value) {
        if (!els.uploadProgress || !els.uploadProgressBar) return;
        const safe = Math.max(0, Math.min(100, Number(value || 0)));
        els.uploadProgress.hidden = safe <= 0;
        els.uploadProgressBar.style.width = `${safe}%`;
    }
    function updateDropzoneLabel() {
        if (!els.dropzoneTitle || !els.dropzoneCopy || !els.uploadFile) return;
        const file = els.uploadFile.files && els.uploadFile.files[0] ? els.uploadFile.files[0] : null;
        if (file) { els.dropzoneTitle.textContent = file.name; els.dropzoneCopy.textContent = `${formatBytes(file.size)} selected`; }
        else { els.dropzoneTitle.textContent = 'Choose a file'; els.dropzoneCopy.textContent = 'Drag and drop a document here, or click to browse.'; }
    }
    function renderCounts() {
        const all = state.files.filter((file) => !file.is_deleted).length;
        const my = state.files.filter((file) => !file.is_deleted && file.is_owner).length;
        const shared = state.files.filter((file) => !file.is_deleted && file.scope === 'shared').length;
        const starred = state.files.filter((file) => !file.is_deleted && file.is_starred).length;
        const trash = state.files.filter((file) => file.is_deleted).length;
        if (els.countAll) els.countAll.textContent = String(all);
        if (els.countMy) els.countMy.textContent = String(my);
        if (els.countShared) els.countShared.textContent = String(shared);
        if (els.countStarred) els.countStarred.textContent = String(starred);
        if (els.countTrash) els.countTrash.textContent = String(trash);
    }

    function renderChecklist() {
        if (!els.checklist) return;
        if (!isStudent || !state.requiredDocuments.length) { els.checklist.innerHTML = ''; return; }
        els.checklist.innerHTML = state.requiredDocuments.map((doc) => `<div class="app-storage-checklist-item${doc.is_complete ? ' is-complete' : ''}"><span class="app-storage-check-indicator">${doc.is_complete ? '&#10003;' : '&#8226;'}</span><div><strong>${escapeHtml(doc.label)}</strong><small>${escapeHtml(doc.is_complete ? 'File found in your storage.' : doc.hint)}</small></div></div>`).join('');
    }

    function renderActivity() {
        if (!els.activityList) return;
        if (!state.activity.length) { els.activityList.innerHTML = '<div class="app-storage-empty-mini">No recent storage activity yet.</div>'; return; }
        els.activityList.innerHTML = state.activity.map((item) => `<div class="app-storage-activity-item"><span class="app-storage-activity-dot"></span><div><strong>${escapeHtml(activityLabel(item.action_type))}</strong><p>${escapeHtml(item.title || item.details || 'Storage update')}</p><small>${escapeHtml(formatDateTime(item.created_at))}</small></div></div>`).join('');
    }

    function renderBulkbar() {
        if (!els.bulkbar || !els.bulkCount || !els.bulkDelete || !els.bulkRestore || !els.bulkToggleAll) return;
        const eligible = getBulkEligibleFiles();
        const selectedVisible = eligible.filter((file) => state.selectedIds.has(Number(file.id)));
        const show = eligible.length > 0 && (state.scope === 'my' || state.scope === 'trash');
        els.bulkbar.hidden = !show;
        els.bulkCount.textContent = `${selectedVisible.length} selected`;
        els.bulkDelete.hidden = state.scope === 'trash';
        els.bulkRestore.hidden = state.scope !== 'trash';
        els.bulkToggleAll.checked = eligible.length > 0 && selectedVisible.length === eligible.length;
    }

    function renderList() {
        syncSelection();
        const visible = getVisibleFiles();
        if (els.visibleCount) els.visibleCount.textContent = `${visible.length} ${visible.length === 1 ? 'file' : 'files'}`;
        scopeButtons.forEach((button) => button.classList.toggle('is-active', button.dataset.scopeFilter === state.scope));
        if (els.categorySelect) els.categorySelect.value = state.category;
        renderBulkbar();
        if (!visible.length) {
            els.list.innerHTML = `<div class="app-storage-empty-state">${state.scope === 'trash' ? 'Trash is empty right now.' : 'No files match this view yet. Upload one to start building your BioTern file hub.'}</div>`;
            return;
        }
        els.list.innerHTML = visible.map((file) => {
            const activeClass = Number(file.id) === Number(state.selectedId) ? ' is-active' : '';
            const checked = state.selectedIds.has(Number(file.id)) ? ' checked' : '';
            const canBulk = (state.scope === 'trash' ? file.can_restore : file.can_delete) && file.is_owner;
            const scopeClass = file.scope === 'shared' ? ' is-warn' : ' is-success';
            const note = file.notes ? escapeHtml(file.notes) : 'No description added yet.';
            const badges = [`<span class="app-storage-badge${scopeClass}">${escapeHtml(scopeLabel(file.scope))}</span>`, `<span class="app-storage-badge">${escapeHtml(categoryLabel(file.category))}</span>`];
            if (file.scope === 'shared') {
                badges.push(`<span class="app-storage-badge">${escapeHtml(file.shared_audience === 'user' && file.shared_target_user_name ? `For ${file.shared_target_user_name}` : audienceLabel(file.shared_audience))}</span>`);
            }
            if (file.is_starred) badges.push('<span class="app-storage-badge">Starred</span>');
            if (file.is_deleted) badges.push('<span class="app-storage-badge is-trash">Deleted</span>');
            if (Number(file.version_count || 0) > 0) badges.push(`<span class="app-storage-badge">${Number(file.version_count)} version${Number(file.version_count) === 1 ? '' : 's'}</span>`);
            return `<div class="app-storage-item-wrap${activeClass}">${canBulk ? `<label class="app-storage-item-check"><input type="checkbox" data-bulk-id="${Number(file.id)}"${checked}><span></span></label>` : ''}<button type="button" class="app-storage-item${activeClass}" data-select-file="${Number(file.id)}"><div class="app-storage-file-row"><div class="app-storage-file-main"><span class="app-storage-file-icon"><i class="${fileTypeIcon(file.file_type)}"></i></span><div class="app-storage-file-copy"><h4>${escapeHtml(file.title)}</h4><p>${note}</p><div class="app-storage-file-meta"><span>${escapeHtml(file.original_name)}</span><span>${escapeHtml(formatBytes(file.file_size))}</span><span>${escapeHtml(formatDateTime(file.updated_at))}</span></div></div></div><div class="app-storage-badge-row">${badges.join('')}</div></div></button></div>`;
        }).join('');
    }

    function renderPreview(file) {
        if (!file || file.is_deleted) return '';
        if (file.file_type === 'image') return `<div class="app-storage-detail-block"><h4>Preview</h4><div class="app-storage-preview-frame"><img src="${escapeHtml(file.view_url)}" alt="${escapeHtml(file.title)}"></div></div>`;
        if (file.file_type === 'pdf') return `<div class="app-storage-detail-block"><h4>Preview</h4><div class="app-storage-preview-frame is-pdf"><iframe src="${escapeHtml(file.view_url)}" title="${escapeHtml(file.title)}"></iframe></div></div>`;
        if (file.file_type === 'document' || file.file_type === 'spreadsheet') return `<div class="app-storage-detail-block"><h4>Preview</h4><p>Preview is not available for this file type yet. Use download or open instead.</p></div>`;
        return '';
    }

    async function loadHistory(fileId) {
        if (!fileId) return [];
        if (state.historyCache[fileId]) return state.historyCache[fileId];
        try {
            const response = await fetch(`${endpoint}?action=history&id=${Number(fileId)}`, { credentials: 'same-origin' });
            const payload = await response.json();
            if (!response.ok || !payload.success) throw new Error(payload.message || 'Unable to load version history');
            state.historyCache[fileId] = Array.isArray(payload.versions) ? payload.versions : [];
        } catch (_error) { state.historyCache[fileId] = []; }
        return state.historyCache[fileId];
    }

    function renderHistory(versions) {
        if (!versions.length) return '<div class="app-storage-detail-block"><h4>Version History</h4><p>No previous versions saved yet.</p></div>';
        return `<div class="app-storage-detail-block"><h4>Version History</h4><div class="app-storage-history-list">${versions.map((version) => `<div class="app-storage-history-item"><div><strong>${escapeHtml(version.original_name)}</strong><small>${escapeHtml(formatDateTime(version.created_at))} · ${escapeHtml(formatBytes(version.file_size))}</small></div><a class="app-storage-action-button" href="${escapeHtml(version.download_url)}"><i class="feather-download"></i><span>Download</span></a></div>`).join('')}</div></div>`;
    }
    async function renderDetails() {
        const file = getSelectedFile();
        if (!file) {
            els.detailsEmpty.hidden = false;
            els.detailsPanel.hidden = true;
            els.detailsPanel.innerHTML = '';
            return;
        }
        const canDelete = Boolean(file.can_delete);
        const canEdit = Boolean(file.can_edit);
        const canRestore = Boolean(file.can_restore);
        const canStar = canEdit && !file.is_deleted;
        const note = file.notes ? escapeHtml(file.notes) : 'No notes added yet.';
        const versions = Number(file.version_count || 0) > 0 ? await loadHistory(file.id) : [];
        els.detailsEmpty.hidden = true;
        els.detailsPanel.hidden = false;
        els.detailsPanel.innerHTML = `<div class="app-storage-details-head"><div><span class="app-storage-kicker">Details</span><h3>${escapeHtml(file.title)}</h3></div><span class="app-storage-file-type-icon"><i class="${fileTypeIcon(file.file_type)}"></i></span></div><div class="app-storage-details-meta"><span>${escapeHtml(file.original_name)}</span><span>${escapeHtml(formatBytes(file.file_size))}</span><span>${escapeHtml(formatDateTime(file.updated_at))}</span></div><div class="app-storage-badge-row"><span class="app-storage-badge ${file.scope === 'shared' ? 'is-warn' : 'is-success'}">${escapeHtml(scopeLabel(file.scope))}</span><span class="app-storage-badge">${escapeHtml(categoryLabel(file.category))}</span>${file.scope === 'shared' ? `<span class="app-storage-badge">${escapeHtml(file.shared_audience === 'user' && file.shared_target_user_name ? `For ${file.shared_target_user_name}` : audienceLabel(file.shared_audience))}</span>` : ''}${file.is_starred ? '<span class="app-storage-badge">Starred</span>' : ''}${file.is_deleted ? '<span class="app-storage-badge is-trash">Deleted</span>' : ''}${Number(file.version_count || 0) > 0 ? `<span class="app-storage-badge">${Number(file.version_count)} versions</span>` : ''}</div>${renderPreview(file)}${canEdit ? `<div class="app-storage-detail-block"><h4>Quick Rename</h4><div class="app-storage-rename-row"><input type="text" class="form-control" value="${escapeHtml(file.title)}" data-rename-input><button type="button" class="app-storage-action-button" data-rename-save><i class="feather-check"></i><span>Save</span></button></div></div>` : ''}<div class="app-storage-detail-actions">${!file.is_deleted ? `<a class="app-storage-action-button" href="${escapeHtml(file.view_url)}" target="_blank" rel="noopener"><i class="feather-eye"></i><span>Open</span></a><a class="app-storage-action-button" href="${escapeHtml(file.download_url)}"><i class="feather-download"></i><span>Download</span></a>` : ''}${canStar ? `<button type="button" class="app-storage-action-button" data-toggle-star><i class="feather-star"></i><span>${file.is_starred ? 'Remove star' : 'Star file'}</span></button>` : ''}${canEdit ? `<button type="button" class="app-storage-action-button" data-edit-file><i class="feather-edit-3"></i><span>Edit</span></button>` : ''}${canRestore ? `<button type="button" class="app-storage-action-button" data-restore-file><i class="feather-rotate-ccw"></i><span>Restore</span></button>` : ''}${canDelete && !file.is_deleted ? `<button type="button" class="app-storage-action-button is-danger" data-delete-file><i class="feather-trash-2"></i><span>Delete</span></button>` : ''}</div><div class="app-storage-detail-block"><h4>About this file</h4><p>${note}</p></div><div class="app-storage-detail-block"><h4>Ownership</h4><p>Uploaded by ${escapeHtml(file.uploader_name || 'BioTern User')} on ${escapeHtml(formatDateTime(file.created_at))}.${file.scope === 'shared' && file.shared_audience === 'user' && file.shared_target_user_name ? ` Shared directly with ${escapeHtml(file.shared_target_user_name)}.` : ''}</p></div>${renderHistory(versions)}`;
    }

    async function render() {
        renderCounts();
        renderChecklist();
        renderActivity();
        renderList();
        await renderDetails();
    }

    async function fetchFiles() {
        if (!endpoint) return;
        try {
            const response = await fetch(endpoint, { credentials: 'same-origin' });
            const payload = await response.json();
            if (!response.ok || !payload.success) throw new Error(payload.message || `Unable to load files (${response.status})`);
            state.files = Array.isArray(payload.files) ? payload.files : [];
            state.activity = Array.isArray(payload.activity) ? payload.activity : [];
            state.requiredDocuments = Array.isArray(payload.required_documents) ? payload.required_documents : [];
            if (!state.selectedId && state.files.length) state.selectedId = Number(state.files[0].id);
            await render();
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Unable to load files right now.';
            els.list.innerHTML = `<div class="app-storage-empty-state">${escapeHtml(message)}</div>`;
            els.detailsEmpty.hidden = false;
            els.detailsPanel.hidden = true;
        }
    }

    function openUploadPanel(file = null, preset = null) {
        if (!els.uploadPanel || !els.uploadForm) return;
        els.uploadForm.reset();
        els.uploadPanel.hidden = false;
        setUploadProgress(0);
        if (file) {
            els.uploadAction.value = 'update';
            els.uploadId.value = String(file.id);
            if (els.uploadTitleText) els.uploadTitleText.textContent = 'Edit File';
            if (els.uploadKicker) els.uploadKicker.textContent = 'Update details';
            if (els.uploadSubmit) els.uploadSubmit.textContent = 'Save Changes';
            if (els.uploadTitle) els.uploadTitle.value = file.title || '';
            if (els.uploadCategory) els.uploadCategory.value = file.category || 'other';
            if (els.uploadScope) els.uploadScope.value = file.scope || 'personal';
            if (els.uploadAudience) els.uploadAudience.value = file.shared_audience || 'all';
            if (els.uploadTargetUser) els.uploadTargetUser.value = file.shared_target_user_id || '';
            if (els.uploadNotes) els.uploadNotes.value = file.notes || '';
            setUploadMessage('Update the file details here. Add a new file only if you want to replace the current one.');
        } else {
            els.uploadAction.value = 'upload';
            els.uploadId.value = '';
            if (els.uploadTitleText) els.uploadTitleText.textContent = 'Add to Storage';
            if (els.uploadKicker) els.uploadKicker.textContent = 'Upload file';
            if (els.uploadSubmit) els.uploadSubmit.textContent = 'Save File';
            if (els.uploadScope && !canManageShared) els.uploadScope.value = 'personal';
            if (els.uploadCategory) els.uploadCategory.value = defaultUploadCategory;
            if (els.uploadAudience) els.uploadAudience.value = 'all';
            if (els.uploadTargetUser) els.uploadTargetUser.value = '';
            setUploadMessage('PDF, images, Office files, and ZIP uploads are supported.');

            if (preset) {
                if (els.uploadCategory && preset.category) {
                    els.uploadCategory.value = preset.category;
                }
                if (els.uploadTitle && preset.title) {
                    els.uploadTitle.value = preset.title;
                }
                if (els.uploadNotes && preset.notes) {
                    els.uploadNotes.value = preset.notes;
                }
            }
        }
        syncAudienceField();
        updateDropzoneLabel();
    }

    function closeUploadPanel() { if (els.uploadPanel) els.uploadPanel.hidden = true; setUploadProgress(0); }
    async function postJson(payload) {
        const response = await fetch(endpoint, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || `Request failed (${response.status})`);
        return data;
    }

    function uploadWithProgress(formData) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', endpoint, true);
            xhr.withCredentials = true;
            xhr.upload.addEventListener('progress', (event) => { if (event.lengthComputable) setUploadProgress((event.loaded / event.total) * 100); });
            xhr.onload = () => { try { const data = JSON.parse(xhr.responseText || '{}'); if (xhr.status < 200 || xhr.status >= 300 || !data.success) return reject(new Error(data.message || `Request failed (${xhr.status})`)); resolve(data); } catch (_error) { reject(new Error('Invalid server response')); } };
            xhr.onerror = () => reject(new Error('Upload failed. Please try again.'));
            xhr.send(formData);
        });
    }
    async function handleUpload(event) {
        event.preventDefault();
        if (state.busy) return;
        const formData = new FormData(els.uploadForm);
        const action = String(formData.get('action') || 'upload');
        if (action === 'upload' && (!els.uploadFile || !els.uploadFile.files || !els.uploadFile.files.length)) { setUploadMessage('Please choose a file first.', true); return; }
        if (!canManageShared && formData.get('scope') === 'shared') formData.set('scope', 'personal');
        if (formData.get('scope') !== 'shared') formData.set('shared_audience', 'all');
        if (formData.get('shared_audience') !== 'user') formData.set('shared_target_user_id', '');
        state.busy = true;
        setUploadMessage(action === 'upload' ? 'Uploading file...' : 'Saving changes...');
        try {
            const data = await uploadWithProgress(formData);
            await fetchFiles();
            if (data.file && data.file.id) state.selectedId = Number(data.file.id);
            closeUploadPanel();
        } catch (error) {
            setUploadMessage(error instanceof Error ? error.message : 'Unable to save file right now.', true);
            setUploadProgress(0);
        } finally { state.busy = false; }
    }

    async function toggleStar() { const file = getSelectedFile(); if (!file) return; try { await postJson({ action: 'toggle_star', id: Number(file.id) }); await fetchFiles(); } catch (error) { window.alert(error instanceof Error ? error.message : 'Unable to update this file.'); } }
    async function deleteFile() { const file = getSelectedFile(); if (!file || !file.can_delete) return; if (!window.confirm(`Move "${file.title}" to trash?`)) return; try { await postJson({ action: 'delete', id: Number(file.id) }); state.scope = 'trash'; state.selectedIds.delete(Number(file.id)); await fetchFiles(); } catch (error) { window.alert(error instanceof Error ? error.message : 'Unable to delete this file.'); } }
    async function restoreFile() { const file = getSelectedFile(); if (!file || !file.can_restore) return; try { await postJson({ action: 'restore', id: Number(file.id) }); state.scope = 'all'; state.selectedIds.delete(Number(file.id)); await fetchFiles(); } catch (error) { window.alert(error instanceof Error ? error.message : 'Unable to restore this file.'); } }
    async function runBulk(action) { const ids = Array.from(state.selectedIds); if (!ids.length) return; const label = action === 'bulk_restore' ? 'restore' : 'move to trash'; if (!window.confirm(`Do you want to ${label} ${ids.length} selected file${ids.length === 1 ? '' : 's'}?`)) return; try { await postJson({ action, ids }); state.selectedIds.clear(); state.scope = action === 'bulk_restore' ? 'all' : 'trash'; await fetchFiles(); } catch (error) { window.alert(error instanceof Error ? error.message : 'Bulk action failed.'); } }

    app.addEventListener('click', async (event) => {
        const scopeButton = event.target.closest('[data-scope-filter]');
        if (scopeButton) { state.scope = String(scopeButton.dataset.scopeFilter || 'all'); state.selectedIds.clear(); await render(); return; }
        const bulkCheckbox = event.target.closest('[data-bulk-id]');
        if (bulkCheckbox) { const id = Number(bulkCheckbox.getAttribute('data-bulk-id') || 0); if (bulkCheckbox.checked) state.selectedIds.add(id); else state.selectedIds.delete(id); renderBulkbar(); event.stopPropagation(); return; }
        const selectButton = event.target.closest('[data-select-file]');
        if (selectButton) { state.selectedId = Number(selectButton.dataset.selectFile || 0); await render(); return; }
        if (event.target.closest('[data-open-upload]')) return openUploadPanel();
        if (event.target.closest('[data-close-upload]')) return closeUploadPanel();
        if (event.target.closest('[data-edit-file]')) { const file = getSelectedFile(); if (file && file.can_edit) openUploadPanel(file); return; }
        if (event.target.closest('[data-rename-save]')) { const file = getSelectedFile(); const renameInput = app.querySelector('[data-rename-input]'); if (file && file.can_edit && renameInput) { const nextTitle = String(renameInput.value || '').trim(); if (nextTitle !== '') { try { const data = await postJson({ action: 'update', id: Number(file.id), title: nextTitle, category: categoryValueForSave(file.category), scope: file.scope, shared_audience: file.shared_audience || 'all', shared_target_user_id: file.shared_target_user_id || '', notes: file.notes || '' }); if (data.file && data.file.id) state.selectedId = Number(data.file.id); await fetchFiles(); } catch (error) { window.alert(error instanceof Error ? error.message : 'Unable to rename this file.'); } } } return; }
        if (event.target.closest('[data-toggle-star]')) return toggleStar();
        if (event.target.closest('[data-delete-file]')) return deleteFile();
        if (event.target.closest('[data-restore-file]')) return restoreFile();
        if (event.target.closest('[data-bulk-delete]')) return runBulk('bulk_delete');
        if (event.target.closest('[data-bulk-restore]')) return runBulk('bulk_restore');
    });

    if (els.bulkToggleAll) {
        els.bulkToggleAll.addEventListener('change', () => {
            const eligible = getBulkEligibleFiles();
            if (els.bulkToggleAll.checked) eligible.forEach((file) => state.selectedIds.add(Number(file.id)));
            else eligible.forEach((file) => state.selectedIds.delete(Number(file.id)));
            renderBulkbar();
            app.querySelectorAll('[data-bulk-id]').forEach((input) => { input.checked = state.selectedIds.has(Number(input.getAttribute('data-bulk-id') || 0)); });
        });
    }
    if (els.searchInput) els.searchInput.addEventListener('input', async (event) => { state.search = String(event.target.value || ''); await render(); });
    if (els.sortSelect) els.sortSelect.addEventListener('change', async (event) => { state.sort = String(event.target.value || 'recent'); await render(); });
    if (els.categorySelect) els.categorySelect.addEventListener('change', async (event) => { state.category = String(event.target.value || 'all'); await render(); });
    if (els.uploadScope) els.uploadScope.addEventListener('change', syncAudienceField);
    if (els.uploadAudience) els.uploadAudience.addEventListener('change', syncAudienceField);
    if (els.uploadForm) els.uploadForm.addEventListener('submit', handleUpload);
    if (els.uploadFile) els.uploadFile.addEventListener('change', updateDropzoneLabel);
    if (els.dropzone && els.uploadFile) {
        els.dropzone.addEventListener('click', () => els.uploadFile.click());
        els.dropzone.addEventListener('keydown', (event) => { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); els.uploadFile.click(); } });
        ['dragenter', 'dragover'].forEach((type) => els.dropzone.addEventListener(type, (event) => { event.preventDefault(); els.dropzone.classList.add('is-dragover'); }));
        ['dragleave', 'drop'].forEach((type) => els.dropzone.addEventListener(type, (event) => { event.preventDefault(); els.dropzone.classList.remove('is-dragover'); }));
        els.dropzone.addEventListener('drop', (event) => { if (!event.dataTransfer || !event.dataTransfer.files || !event.dataTransfer.files.length) return; els.uploadFile.files = event.dataTransfer.files; updateDropzoneLabel(); });
    }
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeUploadPanel(); });
    updateDropzoneLabel();
    syncAudienceField();
    if (startUploadCategory) {
        const presetCategory = ['requirements', 'generated', 'internship', 'images', 'reports', 'other'].includes(startUploadCategory)
            ? startUploadCategory
            : defaultUploadCategory;
        state.category = presetCategory;
        openUploadPanel(null, {
            category: presetCategory,
            title: startUploadTitle,
            notes: startUploadNotes,
        });
    }
    fetchFiles();
})();








