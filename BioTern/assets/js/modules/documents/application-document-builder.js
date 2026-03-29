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

    var editor = document.getElementById('editor');
    var select = window.jQuery ? window.jQuery('#student_select') : null;
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
    var templateRuntime = null;
    var isEditMode = false;

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
        node.textContent = value || fallback || '';
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
                updatePreviewFields();
            })
            .catch(function () {
                updatePreviewFields();
            });
    }

    function prefillByStudentId(studentId) {
        if (!studentId || !select) {
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
                var option = new Option(label, String(student.id), true, true);
                select.append(option).trigger('change');
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

    function initStudentSelect() {
        if (!select) {
            return;
        }

        select.select2({
            placeholder: '',
            ajax: {
                url: endpoint,
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return { action: 'search_students', q: params.term };
                },
                processResults: function (data) {
                    return { results: data.results || [] };
                }
            },
            minimumInputLength: 1,
            width: 'resolve',
            dropdownParent: window.jQuery(document.body),
            dropdownCssClass: 'select2-dropdown'
        });

        select.on('select2:select', function () {
            var studentId = select.val();
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
                    applyStudentData(student);
                    clearRecipientFields();
                    saveStudentState({
                        id: String(student.id),
                        name: fullName,
                        label: (fullName || 'Student') + ' - ' + (student.student_id || student.id)
                    });
                    return loadApplicationRecord(student.id);
                })
                .catch(function () {});
        });
    }

    function bindFieldUpdates() {
        [inputName, inputPosition, inputCompany, inputCompanyAddress, inputHours, inputDate].forEach(function (field) {
            if (!field) {
                return;
            }
            field.addEventListener('input', updatePreviewFields);
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
        initStudentSelect();
        bindFieldUpdates();
        initPrintButton();
        initEditToggle();
        setEditMode(false);

        if (!loadFormState()) {
            inputDate.value = currentToday();
            clearRecipientFields();
        } else {
            updatePreviewFields();
        }

        var savedStudent = loadStudentState();
        if (prefillStudentId > 0) {
            prefillByStudentId(prefillStudentId);
        } else if (savedStudent && savedStudent.id) {
            prefillByStudentId(savedStudent.id);
        }
    });
})();
