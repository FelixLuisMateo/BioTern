(function () {
    'use strict';

    var root = document.querySelector('.application-document-builder.endorsement-page');
    if (!root || !window.AppCore || !window.AppCore.TemplateEditor) {
        return;
    }

    var STORAGE_TEMPLATE = 'biotern_endorsement_template_html_v2';
    var STORAGE_FORM = 'biotern_endorsement_builder_form_v2';
    var STORAGE_STUDENT = 'biotern_endorsement_builder_student_v2';
    var LEGACY_TEMPLATE = 'biotern_endorsement_template_html_v1';
    var endpoint = 'documents/document_endorsement.php';

    var prefillStudentId = parseInt(root.getAttribute('data-prefill-student-id') || '0', 10) || 0;
    var prefillCompanyKey = (root.getAttribute('data-prefill-company') || '').trim();
    var prefillRecipientTitle = (root.getAttribute('data-prefill-recipient-title') || 'auto').toLowerCase();

    var editor = document.getElementById('editor');
    var selectElement = document.getElementById('student_select');
    var studentSearchInput = null;
    var companySelectElement = document.getElementById('company_select');
    var companySearchInput = null;
    var inputRecipient = document.getElementById('input_recipient');
    var inputPosition = document.getElementById('input_position');
    var inputCompany = document.getElementById('input_company');
    var inputCompanyAddress = document.getElementById('input_company_address');
    var inputStudents = document.getElementById('input_students');
    var btnPrint = document.getElementById('btn_print');
    var btnToggleEdit = document.getElementById('btn_toggle_edit');
    var toolbar = document.getElementById('builder_toolbar');
    var recipientTitleRadios = Array.prototype.slice.call(document.querySelectorAll('input[name="recipient_title"]'));

    var selectedStudentId = null;
    var selectedCompanyKey = '';
    var templateRuntime = null;
    var isEditMode = false;
    var draftBaseline = '';
    var hasDraftChanges = false;

    function storageGet(key) {
        try {
            return window.localStorage.getItem(key) || '';
        } catch (err) {
            return '';
        }
    }

    function storageSet(key, value) {
        try {
            window.localStorage.setItem(key, value);
        } catch (err) {}
    }

    function storageRemove(key) {
        try {
            window.localStorage.removeItem(key);
        } catch (err) {}
    }

    function purgeLegacyState() {
        var legacyTemplate = storageGet(LEGACY_TEMPLATE);
        if (!storageGet(STORAGE_TEMPLATE) && legacyTemplate) {
            storageSet(STORAGE_TEMPLATE, legacyTemplate);
        }
        storageRemove(LEGACY_TEMPLATE);

        // Keep template persistence, but avoid stale pretyped form/student values
        // unless the page is intentionally opened with an id prefill query.
        if (prefillStudentId <= 0) {
            storageRemove(STORAGE_FORM);
            storageRemove(STORAGE_STUDENT);
        }
    }

    function setStatus(text) {
        if (templateRuntime && typeof templateRuntime.setStatus === 'function') {
            templateRuntime.setStatus(text);
        }
    }

    function serializeDraftState() {
        return JSON.stringify({
            recipient: inputRecipient && inputRecipient.value ? inputRecipient.value : '',
            position: inputPosition && inputPosition.value ? inputPosition.value : '',
            company: inputCompany && inputCompany.value ? inputCompany.value : '',
            companyAddress: inputCompanyAddress && inputCompanyAddress.value ? inputCompanyAddress.value : '',
            students: inputStudents && inputStudents.value ? inputStudents.value : '',
            recipientTitle: (recipientTitleRadios.find(function (radio) { return radio.checked; }) || {}).value || ''
        });
    }

    function captureDraftBaseline() {
        draftBaseline = serializeDraftState();
        hasDraftChanges = false;
    }

    function updateDraftState() {
        hasDraftChanges = serializeDraftState() !== draftBaseline;
    }

    function initDraftGuard() {
        window.addEventListener('beforeunload', function (event) {
            if (!hasDraftChanges) {
                return;
            }

            var warning = 'You have unsaved form changes. Reloading will clear them.';
            event.preventDefault();
            event.returnValue = warning;
            return warning;
        });
    }

    function normalizeLoadedTemplateMarkup() {
        if (!editor) {
            return;
        }

        // Recover from legacy saved HTML that accidentally includes old editor wrappers.
        var replacementHtml = '';
        var nestedEditor = editor.querySelector('#editor');
        if (nestedEditor && nestedEditor !== editor && nestedEditor.innerHTML.trim() !== '') {
            replacementHtml = nestedEditor.innerHTML;
        }

        if (!replacementHtml) {
            var nestedCanvas = editor.querySelector('.app-editor-canvas');
            if (nestedCanvas && nestedCanvas.innerHTML.trim() !== '') {
                replacementHtml = nestedCanvas.innerHTML;
            }
        }

        if (!replacementHtml) {
            var nestedPaper = editor.querySelector('.app-editor-paper, .paper');
            if (nestedPaper && nestedPaper.innerHTML.trim() !== '') {
                replacementHtml = nestedPaper.innerHTML;
            }
        }

        if (replacementHtml) {
            editor.innerHTML = replacementHtml;
        }

        // Clean any leftover wrappers that can produce framed margins in print preview.
        var wrappers = editor.querySelectorAll('.app-editor-page-wrap, .page-wrap, .app-editor-paper, .paper, .app-editor-main-content, .main-content, .builder-paper-shell, .builder-paper, .builder-editor-surface, .doc-preview');
        if (wrappers.length) {
            wrappers.forEach(function (wrap) {
                if (!wrap || !wrap.parentNode || wrap === editor) {
                    return;
                }
                while (wrap.firstChild) {
                    wrap.parentNode.insertBefore(wrap.firstChild, wrap);
                }
                wrap.parentNode.removeChild(wrap);
            });
        }
    }

    function ensureA4TemplateStructure() {
        if (!editor) {
            return;
        }

        var directPages = Array.prototype.slice.call(editor.querySelectorAll('.a4-page'));
        if (directPages.length === 0) {
            var wrappedPage = document.createElement('div');
            wrappedPage.className = 'a4-page';
            while (editor.firstChild) {
                wrappedPage.appendChild(editor.firstChild);
            }
            editor.appendChild(wrappedPage);
            directPages = [wrappedPage];
        }

        directPages.forEach(function (pageEl) {
            pageEl.setAttribute('data-a4-width-mm', '210');
            pageEl.setAttribute('data-a4-height-mm', '297');
            pageEl.style.width = '210mm';
            pageEl.style.minHeight = '297mm';
            pageEl.style.boxSizing = 'border-box';
            if (!pageEl.style.padding) {
                pageEl.style.padding = '0.55in 0.9in 0.85in 0.9in';
            }
            if (!pageEl.style.background) {
                pageEl.style.background = '#ffffff';
            }
        });
    }

    function setEditMode(nextMode) {
        isEditMode = !!nextMode;
        editor.setAttribute('contenteditable', isEditMode ? 'true' : 'false');
        editor.classList.toggle('is-locked', !isEditMode);

        if (toolbar) {
            toolbar.classList.toggle('is-disabled', !isEditMode);
            toolbar.setAttribute('aria-hidden', !isEditMode ? 'true' : 'false');
        }

        if (btnToggleEdit) {
            btnToggleEdit.classList.toggle('builder-edit-active', isEditMode);
            btnToggleEdit.setAttribute('aria-pressed', isEditMode ? 'true' : 'false');
            btnToggleEdit.textContent = isEditMode ? 'Lock Template' : 'Edit Template';
        }

        setStatus(isEditMode ? 'Template edit mode enabled.' : 'Template locked.');
    }

    function getPlaceholder(idOrIds) {
        if (!editor) {
            return null;
        }
        var ids = Array.isArray(idOrIds) ? idOrIds : [idOrIds];
        for (var i = 0; i < ids.length; i += 1) {
            var node = editor.querySelector('#' + ids[i]);
            if (node) {
                return node;
            }
        }
        return null;
    }

    function setPlaceholderText(idOrIds, value, fallback) {
        var node = getPlaceholder(idOrIds);
        if (!node) {
            return;
        }
        var nextValue = value || fallback || '';
        if (!value && node.classList && node.classList.contains('endorsement-fill-line')) {
            nextValue = '\u00A0';
        }
        node.textContent = nextValue;
    }

    function sanitizeStudentLines(raw) {
        return String(raw || '')
            .split(/\r?\n/)
            .map(function (line) { return line.trim(); })
            .filter(Boolean);
    }

    function inferTitleFromName(name) {
        var n = String(name || '').trim().toLowerCase();
        if (!n) return 'none';
        if (n.indexOf('mr ') === 0 || n.indexOf('mr.') === 0 || n.indexOf('sir ') === 0) return 'mr';
        if (
            n.indexOf('ms ') === 0 || n.indexOf('ms.') === 0 || n.indexOf('mrs ') === 0 ||
            n.indexOf('mrs.') === 0 || n.indexOf('maam') === 0 || n.indexOf("ma'am") === 0 ||
            n.indexOf('madam') === 0
        ) return 'ms';
        return 'none';
    }

    function resolveRecipientTitle() {
        var checked = recipientTitleRadios.find(function (radio) { return radio.checked; });
        var selected = checked ? checked.value : 'auto';
        if (selected === 'auto') {
            return inferTitleFromName(inputRecipient.value);
        }
        return selected;
    }

    function detectSalutation() {
        var title = resolveRecipientTitle();
        if (title === 'mr') return 'Dear Sir,';
        if (title === 'ms') return "Dear Ma'am,";
        if (title === 'none') return "Dear Sir/Ma'am,";
        return "Dear Ma'am,";
    }

    function formatRecipientName(value) {
        var name = String(value || '').trim();
        if (!name) return '__________________________';
        var title = resolveRecipientTitle();
        if (title === 'mr') return 'Mr. ' + name;
        if (title === 'ms') return 'Ms. ' + name;
        if (title === 'none') return 'Mr./Ms. ' + name;
        return name;
    }

    function setSelectValue(value, label) {
        if (!selectElement) {
            return;
        }

        var target = String(value || '');
        if (!target) {
            selectElement.innerHTML = '';
            return;
        }

        var option = new Option(label || target, target, true, true);
        selectElement.innerHTML = '';
        selectElement.appendChild(option);
        selectElement.value = target;
    }

    function setCompanySelectValue(value, label) {
        if (!companySelectElement) {
            return;
        }

        var target = String(value || '');
        if (!target) {
            companySelectElement.innerHTML = '';
            return;
        }

        var option = new Option(label || target, target, true, true);
        companySelectElement.innerHTML = '';
        companySelectElement.appendChild(option);
        companySelectElement.value = target;
    }

    function companyDisplayName(company) {
        if (!company || typeof company !== 'object') {
            return '';
        }
        return (company.company_name || company.name || '').toString().trim();
    }

    function companyDisplayAddress(company) {
        if (!company || typeof company !== 'object') {
            return '';
        }
        return (company.company_address || company.address || '').toString().trim();
    }

    function companyContactName(company) {
        if (!company || typeof company !== 'object') {
            return '';
        }
        return (company.contact_name || company.company_representative || company.supervisor_name || company.partner_representative || '').toString().trim();
    }

    function companyContactPosition(company) {
        if (!company || typeof company !== 'object') {
            return '';
        }
        return (company.contact_position || company.company_representative_position || company.supervisor_position || company.partner_position || '').toString().trim();
    }

    function getCurrentStudentName() {
        if (!selectElement) {
            return '';
        }
        var selectedOption = '';
        if (selectElement.options && selectElement.selectedIndex >= 0) {
            selectedOption = (selectElement.options[selectElement.selectedIndex] || {}).text || '';
        }
        if (!selectedOption) {
            return '';
        }
        return String(selectedOption).replace(/\s*-\s*.*$/, '').trim();
    }

    function saveFormState() {
        storageSet(STORAGE_FORM, JSON.stringify({
            recipient: inputRecipient.value || '',
            position: inputPosition.value || '',
            company: inputCompany.value || '',
            companyAddress: inputCompanyAddress.value || '',
            students: inputStudents.value || '',
            recipientTitle: (recipientTitleRadios.find(function (radio) { return radio.checked; }) || {}).value || 'auto'
        }));
    }

    function loadFormState() {
        var raw = storageGet(STORAGE_FORM);
        if (!raw) {
            return false;
        }
        try {
            var data = JSON.parse(raw);
            inputRecipient.value = (data.recipient || '').toString();
            inputPosition.value = (data.position || '').toString();
            inputCompany.value = (data.company || '').toString();
            inputCompanyAddress.value = (data.companyAddress || '').toString();
            inputStudents.value = (data.students || '').toString();

            var savedTitle = String(data.recipientTitle || '').toLowerCase();
            recipientTitleRadios.forEach(function (radio) { radio.checked = (radio.value === savedTitle); });
            return true;
        } catch (err) {
            return false;
        }
    }

    function saveStudentState(student) {
        if (!student || !student.id) {
            storageRemove(STORAGE_STUDENT);
            return;
        }
        storageSet(STORAGE_STUDENT, JSON.stringify(student));
    }

    function loadStudentState() {
        var raw = storageGet(STORAGE_STUDENT);
        if (!raw) {
            return null;
        }
        try {
            return JSON.parse(raw);
        } catch (err) {
            return null;
        }
    }

    function clearFormFields() {
        inputRecipient.value = '';
        inputPosition.value = '';
        inputCompany.value = '';
        inputCompanyAddress.value = '';
        inputStudents.value = '';
        selectedStudentId = null;
        selectedCompanyKey = '';

        if (studentSearchInput) {
            studentSearchInput.value = '';
        }
        if (companySearchInput) {
            companySearchInput.value = '';
        }

        setSelectValue('', '');
        setCompanySelectValue('', '');
    }

    function updatePreview() {
        setPlaceholderText(['pv_recipient', 'ed_recipient'], formatRecipientName(inputRecipient.value), '__________________________');
        setPlaceholderText(['pv_position', 'ed_position'], inputPosition.value, '__________________________');
        setPlaceholderText(['pv_company', 'ed_company'], inputCompany.value, '__________________________');
        setPlaceholderText(['pv_company_address', 'ed_company_address'], inputCompanyAddress.value, '__________________________');
        var salutationText = detectSalutation();
        setPlaceholderText(['pv_salutation', 'ed_salutation'], salutationText, "Dear Ma'am,");

        // Fallback for older templates that have plain text salutation without id markers.
        if (!getPlaceholder(['pv_salutation', 'ed_salutation']) && editor) {
            var salutationParagraphs = editor.querySelectorAll('p');
            for (var pIdx = 0; pIdx < salutationParagraphs.length; pIdx += 1) {
                var paragraph = salutationParagraphs[pIdx];
                var text = (paragraph.textContent || '').trim();
                if (/^Dear\s+/i.test(text)) {
                    paragraph.textContent = salutationText;
                    break;
                }
            }
        }

        var lines = sanitizeStudentLines(inputStudents.value);
        if (!lines.length) {
            var selectedName = getCurrentStudentName();
            if (selectedName) {
                lines = [selectedName];
            }
        }

        var studentsList = getPlaceholder('pv_students');
        if (studentsList) {
            studentsList.innerHTML = '';
            if (!lines.length) {
                var emptyLine = document.createElement('li');
                emptyLine.textContent = '\u00A0';
                studentsList.appendChild(emptyLine);
            } else {
                lines.forEach(function (line) {
                    var item = document.createElement('li');
                    item.textContent = line;
                    studentsList.appendChild(item);
                });
            }
        } else {
            var legacyStudentsNode = getPlaceholder('ed_students');
            if (legacyStudentsNode) {
                var legacyParentList = legacyStudentsNode.parentElement && legacyStudentsNode.parentElement.tagName === 'UL'
                    ? legacyStudentsNode.parentElement
                    : null;
                if (legacyParentList) {
                    legacyParentList.innerHTML = '';
                    if (!lines.length) {
                        var legacyEmpty = document.createElement('li');
                        legacyEmpty.textContent = '\u00A0';
                        legacyParentList.appendChild(legacyEmpty);
                    } else {
                        lines.forEach(function (line) {
                            var legacyItem = document.createElement('li');
                            legacyItem.textContent = line;
                            legacyParentList.appendChild(legacyItem);
                        });
                    }
                } else {
                    legacyStudentsNode.textContent = lines.length ? lines.join(', ') : '\u00A0';
                }
            } else if (editor) {
                // Fallback for templates where the students list has no dedicated placeholder id.
                var listCandidates = editor.querySelectorAll('ul');
                var targetList = null;
                for (var ulIdx = 0; ulIdx < listCandidates.length; ulIdx += 1) {
                    var listText = (listCandidates[ulIdx].textContent || '').toLowerCase();
                    if (listText.indexOf('________________') !== -1 || listText.indexOf('student') !== -1) {
                        targetList = listCandidates[ulIdx];
                        break;
                    }
                }
                if (!targetList && listCandidates.length) {
                    targetList = listCandidates[0];
                }
                if (targetList) {
                    targetList.innerHTML = '';
                    if (!lines.length) {
                        var genericEmpty = document.createElement('li');
                        genericEmpty.textContent = '\u00A0';
                        targetList.appendChild(genericEmpty);
                    } else {
                        lines.forEach(function (line) {
                            var genericItem = document.createElement('li');
                            genericItem.textContent = line;
                            targetList.appendChild(genericItem);
                        });
                    }
                }
            }
        }

        saveFormState();
    }

    function applySavedEndorsement(data) {
        if (!data || typeof data !== 'object') {
            return false;
        }

        var changed = false;
        if (data.recipient_name) {
            inputRecipient.value = String(data.recipient_name);
            changed = true;
        }
        if (data.recipient_position) {
            inputPosition.value = String(data.recipient_position);
            changed = true;
        }
        if (data.company_name) {
            inputCompany.value = String(data.company_name);
            if (companySearchInput) {
                companySearchInput.value = inputCompany.value;
            }
            setCompanySelectValue(inputCompany.value, inputCompany.value);
            changed = true;
        }
        if (data.company_address) {
            inputCompanyAddress.value = String(data.company_address);
            changed = true;
        }
        if (data.students_to_endorse) {
            inputStudents.value = String(data.students_to_endorse);
            changed = true;
        }

        if (data.recipient_title) {
            var title = String(data.recipient_title).toLowerCase();
            recipientTitleRadios.forEach(function (radio) { radio.checked = (radio.value === title); });
            changed = true;
        }
        if (data.greeting_preference) {
            changed = true;
        }

        return changed;
    }

    function applyCompanyProfile(company, rowLabel) {
        if (!company || typeof company !== 'object') {
            return;
        }

        selectedCompanyKey = String(company.key || company.company_lookup_key || company.company_name || '');
        inputCompany.value = companyDisplayName(company);
        inputCompanyAddress.value = companyDisplayAddress(company);
        inputRecipient.value = companyContactName(company);
        inputPosition.value = companyContactPosition(company);

        if (companySearchInput) {
            companySearchInput.value = rowLabel || inputCompany.value || '';
        }

        setCompanySelectValue(company.key || inputCompany.value || '', rowLabel || inputCompany.value || '');
        updatePreview();
        captureDraftBaseline();
    }

    function loadCompanyProfile(companyIdentifier, rowLabel) {
        if (!companyIdentifier) {
            return Promise.resolve();
        }

        return fetch(endpoint + '?action=get_company_profile&company=' + encodeURIComponent(companyIdentifier), { credentials: 'same-origin' })
            .then(function (response) { return response.json(); })
            .then(function (company) {
                applyCompanyProfile(company, rowLabel);
            })
            .catch(function () {});
    }

    function loadStudentAndData(studentId, keepSelectionLabel) {
        if (!studentId) {
            return Promise.resolve();
        }

        selectedStudentId = String(studentId);

        return fetch(endpoint + '?action=get_student&id=' + encodeURIComponent(studentId), { credentials: 'same-origin' })
            .then(function (response) { return response.json(); })
            .then(function (student) {
                if (!student || !student.id) {
                    return null;
                }

                var fullName = [student.first_name, student.middle_name, student.last_name].filter(Boolean).join(' ').trim();
                var label = keepSelectionLabel || ((fullName || 'Student') + ' - ' + (student.student_id || student.id));

                setSelectValue(student.id, label);
                if (studentSearchInput) {
                    studentSearchInput.value = label;
                }

                saveStudentState({
                    id: String(student.id),
                    name: fullName,
                    label: label
                });

                var lines = sanitizeStudentLines(inputStudents.value);
                if (fullName && lines.indexOf(fullName) === -1) {
                    lines.push(fullName);
                    inputStudents.value = lines.join('\n');
                }

                return fetch(endpoint + '?action=get_endorsement&id=' + encodeURIComponent(student.id), { credentials: 'same-origin' });
            })
            .then(function (response) {
                if (!response) {
                    return null;
                }
                return response.json();
            })
            .then(function (saved) {
                applySavedEndorsement(saved);
                updatePreview();
                captureDraftBaseline();
            })
            .catch(function () {
                updatePreview();
                captureDraftBaseline();
            });
    }

    function prefillByCompanyKey(companyKey) {
        if (!companyKey) {
            return Promise.resolve();
        }
        selectedCompanyKey = String(companyKey);
        return loadCompanyProfile(companyKey);
    }

    function initSelect() {
        if (!selectElement) {
            return;
        }

        // Match application page: replace Select2 with custom student search widget.
        selectElement.setAttribute('data-ui-select', 'native');

        if (window.jQuery && window.jQuery.fn && typeof window.jQuery.fn.select2 === 'function') {
            var jqSelect = window.jQuery(selectElement);
            if (jqSelect.hasClass('select2-hidden-accessible')) {
                try {
                    jqSelect.select2('destroy');
                } catch (err) {}
            }
        }

        var select2Sibling = selectElement.nextElementSibling;
        if (select2Sibling && select2Sibling.classList && select2Sibling.classList.contains('select2')) {
            select2Sibling.parentNode.removeChild(select2Sibling);
        }

        var selectWrap = selectElement.parentElement;
        if (selectWrap && selectWrap.classList && selectWrap.classList.contains('biotern-select-wrap')) {
            var wrapParent = selectWrap.parentElement;
            if (wrapParent) {
                wrapParent.insertBefore(selectElement, selectWrap);
                wrapParent.removeChild(selectWrap);
            }
        }

        var field = selectElement.closest('.builder-field');
        if (!field) {
            return;
        }

        var wrap = document.createElement('div');
        wrap.className = 'app-student-search-wrap';

        var control = document.createElement('div');
        control.className = 'app-student-search-control';

        var input = document.createElement('input');
        input.type = 'text';
        input.className = 'form-control app-student-search-input';
        input.placeholder = selectElement.getAttribute('data-placeholder') || 'Search by name or student id';
        input.autocomplete = 'off';

        var toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'app-student-search-toggle';
        toggle.setAttribute('aria-label', 'Toggle student list');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.innerHTML = '<i class="feather-chevron-down"></i>';

        var panel = document.createElement('div');
        panel.className = 'app-student-search-panel';

        var message = document.createElement('div');
        message.className = 'app-student-search-message';

        var list = document.createElement('div');
        list.className = 'app-student-search-results';

        panel.appendChild(message);
        panel.appendChild(list);
        control.appendChild(input);
        control.appendChild(toggle);
        wrap.appendChild(control);
        wrap.appendChild(panel);

        selectElement.style.display = 'none';
        selectElement.setAttribute('aria-hidden', 'true');
        selectElement.setAttribute('tabindex', '-1');
        selectElement.insertAdjacentElement('afterend', wrap);

        studentSearchInput = input;

        var searchTimer = null;
        var requestToken = 0;
        var activeIndex = -1;
        var currentItems = [];

        function setMessage(text) {
            message.textContent = text;
        }

        function openPanel() {
            panel.classList.add('is-open');
            toggle.setAttribute('aria-expanded', 'true');
        }

        function closePanel() {
            panel.classList.remove('is-open');
            toggle.setAttribute('aria-expanded', 'false');
            activeIndex = -1;
        }

        function markActiveRow() {
            var rows = list.querySelectorAll('.app-student-search-option');
            for (var i = 0; i < rows.length; i += 1) {
                rows[i].classList.toggle('is-active', i === activeIndex);
            }
        }

        function selectStudentById(studentId, rowLabel) {
            if (!studentId) {
                return;
            }
            input.value = rowLabel || '';
            closePanel();
            loadStudentAndData(studentId, rowLabel || '');
        }

        function renderResults(items) {
            currentItems = items || [];
            list.innerHTML = '';
            activeIndex = -1;

            if (!currentItems.length) {
                setMessage('No students found.');
                return;
            }

            setMessage('Select a student to load profile and saved endorsement data.');
            currentItems.forEach(function (item) {
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'app-student-search-option';
                button.textContent = item.text || ('Student ' + item.id);
                button.addEventListener('click', function () {
                    selectStudentById(String(item.id || ''), item.text || '');
                });
                list.appendChild(button);
            });
        }

        function runSearch(term) {
            var value = String(term || '').trim();
            if (value.length < 1) {
                currentItems = [];
                list.innerHTML = '';
                setMessage('Please enter 1 or more characters');
                return;
            }

            requestToken += 1;
            var token = requestToken;
            setMessage('Searching...');

            fetch(endpoint + '?action=search_students&q=' + encodeURIComponent(value), { credentials: 'same-origin' })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (token !== requestToken) {
                        return;
                    }
                    renderResults(Array.isArray(data.results) ? data.results : []);
                })
                .catch(function () {
                    if (token !== requestToken) {
                        return;
                    }
                    currentItems = [];
                    list.innerHTML = '';
                    setMessage('Search failed. Try again.');
                });
        }

        input.addEventListener('focus', function () {
            openPanel();
            if (input.value.trim().length >= 1) {
                runSearch(input.value.trim());
            } else {
                setMessage('Please enter 1 or more characters');
            }
        });

        input.addEventListener('input', function () {
            if (searchTimer) {
                clearTimeout(searchTimer);
            }
            openPanel();
            searchTimer = setTimeout(function () {
                runSearch(input.value.trim());
            }, 220);
        });

        input.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closePanel();
                return;
            }

            if (event.key === 'ArrowDown') {
                if (currentItems.length > 0) {
                    event.preventDefault();
                    activeIndex = Math.min(activeIndex + 1, currentItems.length - 1);
                    markActiveRow();
                }
                return;
            }

            if (event.key === 'ArrowUp') {
                if (currentItems.length > 0) {
                    event.preventDefault();
                    activeIndex = Math.max(activeIndex - 1, 0);
                    markActiveRow();
                }
                return;
            }

            if (event.key === 'Enter' && activeIndex >= 0 && currentItems[activeIndex]) {
                event.preventDefault();
                var item = currentItems[activeIndex];
                selectStudentById(String(item.id || ''), item.text || '');
            }
        });

        toggle.addEventListener('click', function () {
            if (panel.classList.contains('is-open')) {
                closePanel();
                return;
            }
            openPanel();
            if (input.value.trim().length >= 1) {
                runSearch(input.value.trim());
            } else {
                setMessage('Please enter 1 or more characters');
            }
            input.focus();
        });

        document.addEventListener('click', function (event) {
            if (!wrap.contains(event.target)) {
                closePanel();
            }
        });
    }

    function initCompanySelect() {
        if (!companySelectElement) {
            return;
        }

        companySelectElement.setAttribute('data-ui-select', 'native');

        if (window.jQuery && window.jQuery.fn && typeof window.jQuery.fn.select2 === 'function') {
            var jqSelect = window.jQuery(companySelectElement);
            if (jqSelect.hasClass('select2-hidden-accessible')) {
                try {
                    jqSelect.select2('destroy');
                } catch (err) {}
            }
        }

        var select2Sibling = companySelectElement.nextElementSibling;
        if (select2Sibling && select2Sibling.classList && select2Sibling.classList.contains('select2')) {
            select2Sibling.parentNode.removeChild(select2Sibling);
        }

        var selectWrap = companySelectElement.parentElement;
        if (selectWrap && selectWrap.classList && selectWrap.classList.contains('biotern-select-wrap')) {
            var wrapParent = selectWrap.parentElement;
            if (wrapParent) {
                wrapParent.insertBefore(companySelectElement, selectWrap);
                wrapParent.removeChild(selectWrap);
            }
        }

        var field = companySelectElement.closest('.builder-field');
        if (!field) {
            return;
        }

        var wrap = document.createElement('div');
        wrap.className = 'app-student-search-wrap';

        var control = document.createElement('div');
        control.className = 'app-student-search-control';

        var input = document.createElement('input');
        input.type = 'text';
        input.className = 'form-control app-student-search-input';
        input.placeholder = companySelectElement.getAttribute('data-placeholder') || 'Search company, address, or representative';
        input.autocomplete = 'off';

        var toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'app-student-search-toggle';
        toggle.setAttribute('aria-label', 'Toggle company list');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.innerHTML = '<i class="feather-chevron-down"></i>';

        var panel = document.createElement('div');
        panel.className = 'app-student-search-panel';

        var message = document.createElement('div');
        message.className = 'app-student-search-message';

        var list = document.createElement('div');
        list.className = 'app-student-search-results';

        panel.appendChild(message);
        panel.appendChild(list);
        control.appendChild(input);
        control.appendChild(toggle);
        wrap.appendChild(control);
        wrap.appendChild(panel);

        companySelectElement.style.display = 'none';
        companySelectElement.setAttribute('aria-hidden', 'true');
        companySelectElement.setAttribute('tabindex', '-1');
        companySelectElement.insertAdjacentElement('afterend', wrap);

        companySearchInput = input;

        var searchTimer = null;
        var requestToken = 0;
        var activeIndex = -1;
        var currentItems = [];

        function setMessage(text) {
            message.textContent = text;
        }

        function openPanel() {
            panel.classList.add('is-open');
            toggle.setAttribute('aria-expanded', 'true');
        }

        function closePanel() {
            panel.classList.remove('is-open');
            toggle.setAttribute('aria-expanded', 'false');
            activeIndex = -1;
        }

        function markActiveRow() {
            var rows = list.querySelectorAll('.app-student-search-option');
            for (var i = 0; i < rows.length; i += 1) {
                rows[i].classList.toggle('is-active', i === activeIndex);
            }
        }

        function selectCompany(item) {
            if (!item || !item.id) {
                return;
            }
            var rowLabel = item.text || item.name || '';
            input.value = rowLabel || '';
            closePanel();
            applyCompanyProfile({
                key: item.id,
                company_name: item.name || '',
                company_address: item.address || '',
                contact_name: item.contact_name || '',
                contact_position: item.contact_position || ''
            }, rowLabel || '');
            loadCompanyProfile(item.id, rowLabel || '');
        }

        function renderResults(items) {
            currentItems = items || [];
            list.innerHTML = '';
            activeIndex = -1;

            if (!currentItems.length) {
                setMessage('No companies found.');
                return;
            }

            setMessage('Select a company to load the company profile data.');
            currentItems.forEach(function (item) {
                var button = document.createElement('button');
                var title = item && item.name ? String(item.name) : (item && item.text ? String(item.text) : 'Company');
                var subtitle = [
                    item && item.contact_name ? String(item.contact_name) : '',
                    item && item.contact_position ? String(item.contact_position) : '',
                    item && item.address ? String(item.address) : ''
                ].filter(Boolean).join(' - ');
                button.type = 'button';
                button.className = 'app-student-search-option';
                button.innerHTML = '<span class="app-search-option-title"></span><span class="app-search-option-subtitle"></span>';
                button.querySelector('.app-search-option-title').textContent = title;
                button.querySelector('.app-search-option-subtitle').textContent = subtitle || 'Select this company';
                button.addEventListener('click', function () {
                    selectCompany(item);
                });
                list.appendChild(button);
            });
        }

        function runSearch(term) {
            var value = String(term || '').trim();
            if (value.length < 1) {
                currentItems = [];
                list.innerHTML = '';
                setMessage('Please enter 1 or more characters');
                return;
            }

            requestToken += 1;
            var token = requestToken;
            setMessage('Searching...');

            fetch(endpoint + '?action=search_companies&q=' + encodeURIComponent(value), { credentials: 'same-origin' })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (token !== requestToken) {
                        return;
                    }
                    renderResults(Array.isArray(data.results) ? data.results : []);
                })
                .catch(function () {
                    if (token !== requestToken) {
                        return;
                    }
                    currentItems = [];
                    list.innerHTML = '';
                    setMessage('Search failed. Try again.');
                });
        }

        input.addEventListener('focus', function () {
            openPanel();
            if (input.value.trim().length >= 1) {
                runSearch(input.value.trim());
            } else {
                setMessage('Please enter 1 or more characters');
            }
        });

        input.addEventListener('input', function () {
            if (searchTimer) {
                clearTimeout(searchTimer);
            }
            openPanel();
            searchTimer = setTimeout(function () {
                runSearch(input.value.trim());
            }, 220);
        });

        input.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closePanel();
                return;
            }

            if (event.key === 'ArrowDown') {
                if (currentItems.length > 0) {
                    event.preventDefault();
                    activeIndex = Math.min(activeIndex + 1, currentItems.length - 1);
                    markActiveRow();
                }
                return;
            }

            if (event.key === 'ArrowUp') {
                if (currentItems.length > 0) {
                    event.preventDefault();
                    activeIndex = Math.max(activeIndex - 1, 0);
                    markActiveRow();
                }
                return;
            }

            if (event.key === 'Enter' && activeIndex >= 0 && currentItems[activeIndex]) {
                event.preventDefault();
                var item = currentItems[activeIndex];
                selectCompany(item);
            }
        });

        toggle.addEventListener('click', function () {
            if (panel.classList.contains('is-open')) {
                closePanel();
                return;
            }
            openPanel();
            if (input.value.trim().length >= 1) {
                runSearch(input.value.trim());
            } else {
                setMessage('Please enter 1 or more characters');
            }
            input.focus();
        });

        document.addEventListener('click', function (event) {
            if (!wrap.contains(event.target)) {
                closePanel();
            }
        });
    }

    function initTemplateEditor() {
        templateRuntime = window.AppCore.TemplateEditor.create({
            editorId: 'editor',
            statusId: 'msg',
            storageKey: STORAGE_TEMPLATE,
            defaultTemplateId: 'endorsement_default_template',
            loadMode: 'storage-or-default',
            resetMode: 'storage-or-default',
            resetConfirmMessage: 'Reset endorsement template to default?',
            resetStatusMessage: 'Reset to default',
            saveButtonId: 'btn_save',
            resetButtonId: 'btn_reset',
            hideBrokenImagesOnError: true,
            preserveSelectionOnFormat: true,
            fontSizeMode: 'rich-span',
            onAfterLoad: function (editorNode, api) {
                try {
                    normalizeLoadedTemplateMarkup();
                    ensureA4TemplateStructure();
                    if (window.AppCore.TemplateEditor.attachLogoDrag) {
                        window.AppCore.TemplateEditor.attachLogoDrag(editorNode, {
                            setStatus: api.setStatus,
                            onChange: api.saveDebounced,
                            moveStatusText: 'Move logo, then release to save'
                        });
                    }
                    updatePreview();
                } catch (err) {
                    api.setStatus('Template loaded with limited binding support.');
                }
            }
        });

        if (!templateRuntime) {
            return Promise.resolve();
        }

        return templateRuntime.init().then(function () {
            setStatus('Template ready.');
            updatePreview();
        });
    }

    function initEditToggle() {
        if (!btnToggleEdit) {
            return;
        }
        btnToggleEdit.addEventListener('click', function () {
            setEditMode(!isEditMode);
        });
    }

    function initPrintButton() {
        if (!btnPrint) {
            return;
        }
        btnPrint.addEventListener('click', function (event) {
            if (event && typeof event.preventDefault === 'function') {
                event.preventDefault();
            }
            ensureA4TemplateStructure();
            updatePreview();
            window.print();
        });
    }

    function bindInputs() {
        [inputRecipient, inputPosition, inputCompany, inputCompanyAddress, inputStudents].forEach(function (field) {
            if (!field) {
                return;
            }
            field.addEventListener('input', function () {
                updatePreview();
                updateDraftState();
            });
        });

        recipientTitleRadios.forEach(function (radio) {
            radio.addEventListener('change', function () {
                updatePreview();
                updateDraftState();
            });
        });

    }

    function applyPrefillDefaults() {
        recipientTitleRadios.forEach(function (radio) { radio.checked = (radio.value === prefillRecipientTitle); });
        if (!recipientTitleRadios.some(function (radio) { return radio.checked; }) && recipientTitleRadios.length) {
            recipientTitleRadios[0].checked = true;
        }

    }

    purgeLegacyState();
    storageRemove(STORAGE_FORM);
    applyPrefillDefaults();

    function finishInitBindings() {
        initSelect();
        initCompanySelect();
        initDraftGuard();
        bindInputs();
        initEditToggle();
        initPrintButton();
        setEditMode(false);

        clearFormFields();
        applyPrefillDefaults();
        updatePreview();
        captureDraftBaseline();

        var prefillPromise = Promise.resolve();
        if (prefillStudentId > 0) {
            prefillPromise = loadStudentAndData(prefillStudentId, '') || Promise.resolve();
        } else {
            if (studentSearchInput) {
                studentSearchInput.value = '';
            }
            setSelectValue('', '');
        }

        prefillPromise.finally(function () {
            if (prefillCompanyKey) {
                (prefillByCompanyKey(prefillCompanyKey) || Promise.resolve()).finally(captureDraftBaseline);
                return;
            }
            captureDraftBaseline();
        });
    }

    initTemplateEditor()
        .then(finishInitBindings)
        .catch(function () {
            setStatus('Template bootstrap recovered.');
            finishInitBindings();
        });
})();
