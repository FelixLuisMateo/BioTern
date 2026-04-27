(function () {
    'use strict';

    var root = document.querySelector('.application-document-builder');
    if (!root || !window.AppCore || !window.AppCore.TemplateEditor) {
        return;
    }

    var STORAGE_TEMPLATE = 'biotern_application_template_html_v6';
    var STORAGE_FORM = 'biotern_application_builder_form_v6';
    var STORAGE_STUDENT = 'biotern_application_builder_student_v6';
    var endpoint = 'documents/document_application.php';
    var prefillStudentId = parseInt(root.getAttribute('data-prefill-student-id') || '0', 10) || 0;
    var prefillCompanyKey = (root.getAttribute('data-prefill-company') || '').trim();

    var editor = document.getElementById('editor');
    var selectElement = document.getElementById('student_select');
    var studentSearchInput = null;
    var companySelectElement = document.getElementById('company_select');
    var companySearchInput = null;
    var inputName = document.getElementById('input_name');
    var inputPosition = document.getElementById('input_position');
    var inputCompany = document.getElementById('input_company');
    var inputCompanyAddress = document.getElementById('input_company_address');
    var inputHours = document.getElementById('input_hours');
    var inputDate = document.getElementById('builder_date');
    var printButton = document.getElementById('btn_print');
    var toggleEditButton = document.getElementById('btn_toggle_edit');
    var toolbar = document.getElementById('builder_toolbar');
    var selectedStudentId = null;
    var selectedCompanyKey = '';
    var templateRuntime = null;
    var isEditMode = false;
    var draftBaseline = '';
    var hasDraftChanges = false;

    function purgeLegacyTemplateState() {
        [
            'biotern_application_template_html_v1',
            'biotern_application_template_html_v2',
            'biotern_application_template_html_v3',
            'biotern_application_template_html_v4',
            'biotern_application_template_html_v5',
            'biotern_application_builder_form_v1',
            'biotern_application_builder_form_v2',
            'biotern_application_builder_form_v3',
            'biotern_application_builder_form_v4',
            'biotern_application_builder_form_v5',
            'biotern_application_builder_student_v1',
            'biotern_application_builder_student_v2',
            'biotern_application_builder_student_v3',
            'biotern_application_builder_student_v4',
            'biotern_application_builder_student_v5'
        ].forEach(storageRemove);
    }

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

    function setStatus(text) {
        if (templateRuntime && typeof templateRuntime.setStatus === 'function') {
            templateRuntime.setStatus(text);
        }
    }

    function serializeDraftState() {
        return JSON.stringify({
            recipient: inputName && inputName.value ? inputName.value : '',
            position: inputPosition && inputPosition.value ? inputPosition.value : '',
            company: inputCompany && inputCompany.value ? inputCompany.value : '',
            address: inputCompanyAddress && inputCompanyAddress.value ? inputCompanyAddress.value : '',
            hours: inputHours && inputHours.value ? inputHours.value : '',
            date: inputDate && inputDate.value ? inputDate.value : ''
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

    function ensureA4TemplateStructure() {
        if (!editor) {
            return;
        }

        var pages = Array.prototype.slice.call(editor.querySelectorAll('.a4-page'));
        if (!pages.length) {
            var firstPage = document.createElement('div');
            firstPage.className = 'a4-page';
            while (editor.firstChild) {
                firstPage.appendChild(editor.firstChild);
            }
            editor.appendChild(firstPage);
            pages = [firstPage];
        }

        pages.forEach(function (pageEl) {
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

    function setEditMode(nextEditMode) {
        isEditMode = !!nextEditMode;
        if (!editor) {
            return;
        }

        editor.setAttribute('contenteditable', isEditMode ? 'true' : 'false');
        editor.classList.toggle('is-locked', !isEditMode);

        if (toolbar) {
            toolbar.classList.toggle('is-disabled', !isEditMode);
            toolbar.setAttribute('aria-hidden', !isEditMode ? 'true' : 'false');
        }

        if (toggleEditButton) {
            toggleEditButton.classList.toggle('builder-edit-active', isEditMode);
            toggleEditButton.setAttribute('aria-pressed', isEditMode ? 'true' : 'false');
            toggleEditButton.textContent = isEditMode ? 'Lock Template' : 'Edit Template';
        }

        setStatus(isEditMode ? 'Template edit mode enabled.' : 'Template locked. Use Edit Template to change layout.');
    }

    function placeholderNode(id) {
        return editor ? editor.querySelector('#' + id) : null;
    }

    function setPlaceholder(id, value, fallback) {
        var node = placeholderNode(id);
        if (!node) {
            return;
        }
        var nextValue = value || fallback || '';
        if (!value && node.classList && node.classList.contains('app-fill-line')) {
            nextValue = '\u00A0';
        }
        node.textContent = nextValue;
    }

    function currentToday() {
        return new Date().toLocaleDateString(undefined, {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    }

    function saveFormState() {
        storageSet(STORAGE_FORM, JSON.stringify({
            recipient: inputName.value || '',
            position: inputPosition.value || '',
            company: inputCompany.value || '',
            address: inputCompanyAddress.value || '',
            hours: inputHours.value || '250',
            date: inputDate.value || currentToday()
        }));
    }

    function loadFormState() {
        var raw = storageGet(STORAGE_FORM);
        if (!raw) {
            return false;
        }

        try {
            var data = JSON.parse(raw);
            inputName.value = (data.recipient || '').toString();
            inputPosition.value = (data.position || '').toString();
            inputCompany.value = (data.company || '').toString();
            inputCompanyAddress.value = (data.address || '').toString();
            inputHours.value = (data.hours || '250').toString();
            inputDate.value = (data.date || currentToday()).toString();
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

    function clearRecipientFields() {
        inputName.value = '';
        inputPosition.value = '';
        inputCompany.value = '';
        inputCompanyAddress.value = '';
        inputHours.value = '250';
        saveFormState();
        updatePreviewFields();
    }

    function applyCompanyProfile(company, rowLabel) {
        if (!company || typeof company !== 'object') {
            return;
        }

        selectedCompanyKey = String(company.key || company.company_lookup_key || company.company_name || '');
        inputCompany.value = (company.company_name || '').toString();
        inputCompanyAddress.value = (company.company_address || '').toString();
        inputName.value = (company.contact_name || company.partner_representative || '').toString();
        inputPosition.value = (company.contact_position || company.partner_position || '').toString();

        if (companySearchInput) {
            companySearchInput.value = rowLabel || inputCompany.value || '';
        }

        setCompanySelectValue(company.key || inputCompany.value || '', rowLabel || inputCompany.value || '');
        updatePreviewFields();
        captureDraftBaseline();
    }

    function updatePreviewFields() {
        setPlaceholder('ap_date', inputDate.value || currentToday(), '__________');
        setPlaceholder('ap_name', inputName.value, '__________________________');
        setPlaceholder('ap_position', inputPosition.value, '__________________________');
        setPlaceholder('ap_company', inputCompany.value, '__________________________');
        setPlaceholder('ap_address', inputCompanyAddress.value, '__________________________');
        setPlaceholder('ap_hours', inputHours.value || '250', '250');
        saveFormState();
    }

    function applyStudentData(student) {
        var fullName = [student.first_name, student.middle_name, student.last_name]
            .filter(Boolean)
            .join(' ')
            .trim();

        setPlaceholder('ap_student', fullName, '__________________________');
        setPlaceholder('ap_student_name', fullName, '__________________________');
        setPlaceholder('ap_student_address', student.address || '', '__________________________');
        setPlaceholder('ap_student_contact', student.phone || '', '__________________________');
        setPlaceholder('ap_date', inputDate.value || currentToday(), '__________');
    }

    function loadApplicationRecord(studentId) {
        if (!studentId) {
            return Promise.resolve();
        }

        return fetch(endpoint + '?action=get_application_letter&id=' + encodeURIComponent(studentId), {
            credentials: 'same-origin'
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (!data || typeof data !== 'object') {
                    updatePreviewFields();
                    return;
                }

                inputName.value = (data.application_person || '').toString();
                inputPosition.value = (data.posistion || data.position || '').toString();
                inputCompany.value = (data.company_name || '').toString();
                inputCompanyAddress.value = (data.company_address || '').toString();
                inputDate.value = (data.date || inputDate.value || currentToday()).toString();
                if (companySearchInput) {
                    companySearchInput.value = inputCompany.value || '';
                }
                setCompanySelectValue(inputCompany.value || '', inputCompany.value || '');
                updatePreviewFields();
                captureDraftBaseline();
            })
            .catch(function () {
                updatePreviewFields();
                captureDraftBaseline();
            });
    }

    function loadCompanyProfile(companyIdentifier, rowLabel) {
        if (!companyIdentifier) {
            return Promise.resolve();
        }

        return fetch(endpoint + '?action=get_company_profile&company=' + encodeURIComponent(companyIdentifier), {
            credentials: 'same-origin'
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (company) {
                applyCompanyProfile(company, rowLabel);
            })
            .catch(function () {});
    }

    function prefillByStudentId(studentId) {
        if (!studentId) {
            return;
        }

        selectedStudentId = String(studentId);
        return fetch(endpoint + '?action=get_student&id=' + encodeURIComponent(studentId), {
            credentials: 'same-origin'
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (student) {
                if (!student || !student.id) {
                    return;
                }

                var fullName = [student.first_name, student.middle_name, student.last_name]
                    .filter(Boolean)
                    .join(' ')
                    .trim();
                var label = (fullName || 'Student') + ' - ' + (student.student_id || student.id);
                setSelectValue(student.id, label);
                if (studentSearchInput) {
                    studentSearchInput.value = label;
                }
                applyStudentData(student);
                saveStudentState({
                    id: String(student.id),
                    name: fullName,
                    label: label
                });
                return loadApplicationRecord(student.id);
            })
            .catch(function () {});
    }

    function prefillByCompanyKey(companyKey) {
        if (!companyKey) {
            return Promise.resolve();
        }

        selectedCompanyKey = String(companyKey);
        return loadCompanyProfile(companyKey);
    }

    function initStudentSelect() {
        if (!selectElement) {
            return;
        }

        // Prevent global select enhancers from rendering a second "Select" UI for this field.
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

        function selectStudentById(studentId) {
            if (!studentId) {
                return;
            }

            selectedStudentId = String(studentId);
            fetch(endpoint + '?action=get_student&id=' + encodeURIComponent(studentId), {
                credentials: 'same-origin'
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (student) {
                    if (!student || !student.id) {
                        return;
                    }

                    var fullName = [student.first_name, student.middle_name, student.last_name]
                        .filter(Boolean)
                        .join(' ')
                        .trim();
                    var label = (fullName || 'Student') + ' - ' + (student.student_id || student.id);

                    input.value = label;
                    setSelectValue(student.id, label);
                    applyStudentData(student);
                    clearRecipientFields();
                    saveStudentState({
                        id: String(student.id),
                        name: fullName,
                        label: label
                    });
                    closePanel();
                    return loadApplicationRecord(student.id);
                })
                .catch(function () {});
        }

        function renderResults(items) {
            list.innerHTML = '';
            currentItems = Array.isArray(items) ? items : [];
            activeIndex = -1;

            if (!currentItems.length) {
                setMessage('No results found');
                return;
            }

            setMessage('');
            currentItems.forEach(function (item, index) {
                var row = document.createElement('button');
                row.type = 'button';
                row.className = 'app-student-search-option';
                row.setAttribute('data-index', String(index));
                row.textContent = item && item.text ? String(item.text) : 'Student';
                row.addEventListener('click', function () {
                    selectStudentById(item && item.id ? item.id : '');
                });
                list.appendChild(row);
            });
        }

        function runSearch(term) {
            var token = ++requestToken;
            fetch(endpoint + '?action=search_students&q=' + encodeURIComponent(term), {
                credentials: 'same-origin'
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (data) {
                    if (token !== requestToken) {
                        return;
                    }
                    renderResults(data && data.results ? data.results : []);
                })
                .catch(function () {
                    if (token !== requestToken) {
                        return;
                    }
                    renderResults([]);
                });
        }

        input.addEventListener('focus', function () {
            openPanel();
            setMessage('Searching...');
            runSearch(input.value.trim());
        });

        input.addEventListener('input', function () {
            var term = input.value.trim();
            openPanel();

            if (searchTimer) {
                window.clearTimeout(searchTimer);
            }

            if (!term) {
                setMessage('Searching...');
                searchTimer = window.setTimeout(function () {
                    runSearch('');
                }, 140);
                return;
            }

            setMessage('Searching...');
            list.innerHTML = '';
            searchTimer = window.setTimeout(function () {
                runSearch(term);
            }, 220);
        });

        input.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closePanel();
                return;
            }

            if (event.key === 'ArrowDown') {
                if (!panel.classList.contains('is-open')) {
                    openPanel();
                }
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
                selectStudentById(currentItems[activeIndex].id || '');
            }
        });

        toggle.addEventListener('click', function () {
            if (panel.classList.contains('is-open')) {
                closePanel();
                return;
            }
            openPanel();
            setMessage('Searching...');
            runSearch(input.value.trim());
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

        function selectCompanyByKey(companyKey, rowLabel) {
            if (!companyKey) {
                return;
            }
            input.value = rowLabel || '';
            closePanel();
            loadCompanyProfile(companyKey, rowLabel || '');
        }

        function renderResults(items) {
            list.innerHTML = '';
            currentItems = Array.isArray(items) ? items : [];
            activeIndex = -1;

            if (!currentItems.length) {
                setMessage('No companies found');
                return;
            }

            setMessage('');
            currentItems.forEach(function (item, index) {
                var row = document.createElement('button');
                row.type = 'button';
                row.className = 'app-student-search-option';
                row.setAttribute('data-index', String(index));
                row.textContent = item && item.text ? String(item.text) : 'Company';
                row.addEventListener('click', function () {
                    selectCompanyByKey(item && item.id ? item.id : '', item && item.text ? item.text : '');
                });
                list.appendChild(row);
            });
        }

        function runSearch(term) {
            var token = ++requestToken;
            fetch(endpoint + '?action=search_companies&q=' + encodeURIComponent(term), {
                credentials: 'same-origin'
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (data) {
                    if (token !== requestToken) {
                        return;
                    }
                    renderResults(data && data.results ? data.results : []);
                })
                .catch(function () {
                    if (token !== requestToken) {
                        return;
                    }
                    renderResults([]);
                });
        }

        input.addEventListener('focus', function () {
            openPanel();
            setMessage('Searching...');
            runSearch(input.value.trim());
        });

        input.addEventListener('input', function () {
            var term = input.value.trim();
            openPanel();

            if (searchTimer) {
                window.clearTimeout(searchTimer);
            }

            setMessage('Searching...');
            list.innerHTML = '';
            searchTimer = window.setTimeout(function () {
                runSearch(term);
            }, 220);
        });

        input.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closePanel();
                return;
            }

            if (event.key === 'ArrowDown') {
                if (!panel.classList.contains('is-open')) {
                    openPanel();
                }
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
                selectCompanyByKey(currentItems[activeIndex].id || '', currentItems[activeIndex].text || '');
            }
        });

        toggle.addEventListener('click', function () {
            if (panel.classList.contains('is-open')) {
                closePanel();
                return;
            }
            openPanel();
            setMessage('Searching...');
            runSearch(input.value.trim());
            input.focus();
        });

        document.addEventListener('click', function (event) {
            if (!wrap.contains(event.target)) {
                closePanel();
            }
        });
    }

    function bindFieldUpdates() {
        [inputName, inputPosition, inputCompany, inputCompanyAddress, inputHours, inputDate].forEach(function (field) {
            if (!field) {
                return;
            }
            field.addEventListener('input', function () {
                updatePreviewFields();
                updateDraftState();
            });
        });
    }

    function initTemplateEditor() {
        templateRuntime = window.AppCore.TemplateEditor.create({
            editorId: 'editor',
            statusId: 'msg',
            storageKey: STORAGE_TEMPLATE,
            defaultTemplateId: 'application_default_template',
            loadMode: 'storage-or-default',
            resetMode: 'storage-or-default',
            saveButtonId: 'btn_save',
            resetButtonId: 'btn_reset',
            hideBrokenImagesOnError: true,
            preserveSelectionOnFormat: true,
            fontSizeMode: 'rich-span',
            onAfterLoad: function (editorNode, api) {
                ensureA4TemplateStructure();
                if (window.AppCore.TemplateEditor.attachLogoDrag) {
                    window.AppCore.TemplateEditor.attachLogoDrag(editorNode, {
                        setStatus: api.setStatus,
                        onChange: api.saveDebounced,
                        moveStatusText: 'Move logo, then release to save'
                    });
                }
            }
        });

        if (!templateRuntime) {
            return Promise.resolve();
        }

        return templateRuntime.init().then(function () {
            setStatus('Template ready.');
        });
    }

    function initPrintButton() {
        if (!printButton) {
            return;
        }

        printButton.addEventListener('click', function (event) {
            if (event && typeof event.preventDefault === 'function') {
                event.preventDefault();
            }
            ensureA4TemplateStructure();
            updatePreviewFields();
            window.print();
        });
    }

    function initEditToggle() {
        if (!toggleEditButton) {
            return;
        }

        toggleEditButton.addEventListener('click', function () {
            setEditMode(!isEditMode);
        });
    }

    initTemplateEditor().then(function () {
        purgeLegacyTemplateState();
        storageRemove(STORAGE_FORM);
        initStudentSelect();
        initCompanySelect();
        initDraftGuard();
        bindFieldUpdates();
        initPrintButton();
        initEditToggle();
        setEditMode(false);

        inputDate.value = currentToday();
        clearRecipientFields();
        captureDraftBaseline();

        var prefillPromise = Promise.resolve();
        if (prefillStudentId > 0) {
            prefillPromise = prefillByStudentId(prefillStudentId) || Promise.resolve();
        } else {
            saveStudentState(null);
            selectedStudentId = null;
            if (studentSearchInput) {
                studentSearchInput.value = '';
            }
        }

        prefillPromise.finally(function () {
            if (prefillCompanyKey) {
                (prefillByCompanyKey(prefillCompanyKey) || Promise.resolve()).finally(captureDraftBaseline);
                return;
            }
            captureDraftBaseline();
        });
    });
})();
