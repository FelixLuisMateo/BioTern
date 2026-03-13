(function () {
    'use strict';

    var root = document.querySelector('.doc-page-root');
    var cfg = {
        page: root ? (root.getAttribute('data-page') || '') : '',
        prefillStudentId: root ? (root.getAttribute('data-prefill-student-id') || '0') : '0',
        prefillGreetingPref: root ? (root.getAttribute('data-prefill-greeting-pref') || 'either') : 'either',
        prefillRecipientTitle: root ? (root.getAttribute('data-prefill-recipient-title') || 'auto') : 'auto'
    };

    function withOrdinalSuffix(dayValue) {
        var raw = (dayValue || '').toString().trim();
        if (!raw) return raw;
        if (!/^\d+$/.test(raw)) return raw;
        var n = parseInt(raw, 10);
        if (isNaN(n)) return raw;
        var mod100 = n % 100;
        var suffix = 'th';
        if (mod100 < 11 || mod100 > 13) {
            var mod10 = n % 10;
            if (mod10 === 1) suffix = 'st';
            else if (mod10 === 2) suffix = 'nd';
            else if (mod10 === 3) suffix = 'rd';
        }
        return String(n) + suffix;
    }

    function installHideOnErrorImages() {
        var imgs = document.querySelectorAll('img.js-hide-on-error');
        imgs.forEach(function (img) {
            img.addEventListener('error', function () {
                img.style.display = 'none';
            });
        });
    }

    function initApplicationPage() {
        var PREFILL_STUDENT_ID = parseInt(cfg.prefillStudentId || 0, 10) || 0;
        var APP_TEMPLATE_STORAGE_KEY = 'biotern_application_template_html_v1';
        var APP_FORM_STORAGE_KEY = 'biotern_application_form_values_v1';
        var APP_SELECTED_STUDENT_KEY = 'biotern_application_selected_student_v1';
        var select = $('#student_select');
        var inputName = document.getElementById('input_name');
        var inputPosition = document.getElementById('input_position');
        var inputCompany = document.getElementById('input_company');
        var inputCompanyAddress = document.getElementById('input_company_address');
        var inputHours = document.getElementById('input_hours');
        var btnFileEdit = document.getElementById('btn_file_edit_application');
        var letterContent = document.getElementById('letter_content');
        var selectedStudentId = null;
        var isFileEditMode = false;
        var hasLoadedSavedTemplate = false;
        var pageStorage = window.sessionStorage;

        function clearPageState() {
            try { pageStorage.removeItem(APP_TEMPLATE_STORAGE_KEY); } catch (err) {}
            try { pageStorage.removeItem(APP_FORM_STORAGE_KEY); } catch (err) {}
            try { pageStorage.removeItem(APP_SELECTED_STUDENT_KEY); } catch (err) {}
        }

        function getNavigationType() {
            try {
                var entries = performance.getEntriesByType('navigation');
                if (entries && entries.length && entries[0].type) return entries[0].type;
            } catch (err) {}
            return 'navigate';
        }

        if (PREFILL_STUDENT_ID <= 0 && getNavigationType() !== 'reload') {
            clearPageState();
        }

        function ensurePreviewHoursSpan() {
            if (!letterContent) return null;
            var previewHours = letterContent.querySelector('#ap_hours');
            if (previewHours) return previewHours;
            var paragraphs = letterContent.querySelectorAll('p');
            paragraphs.forEach(function (p) {
                if (previewHours) return;
                var text = (p.textContent || '').replace(/\s+/g, ' ').trim();
                if (text.indexOf('I am ') !== 0) return;
                if (text.indexOf('minimum of') === -1 || text.indexOf('hours') === -1) return;

                p.innerHTML = p.innerHTML.replace(
                    /minimum of\s*<strong>[\s\S]*?hours<\/strong>/i,
                    'minimum of <strong><span id="ap_hours">250</span> hours</strong>'
                );
                previewHours = letterContent.querySelector('#ap_hours');
            });

            return previewHours;
        }

        function getHoursValue() {
            return (inputHours.value || '250').toString();
        }

        function setHoursValue(value) {
            var normalized = (value || '250').toString();
            inputHours.value = normalized;
            var previewHours = ensurePreviewHoursSpan();
            if (previewHours) previewHours.textContent = normalized;
        }

        function saveFormState() {
            try {
                pageStorage.setItem(APP_FORM_STORAGE_KEY, JSON.stringify({
                    ap_name: inputName.value || '',
                    ap_position: inputPosition.value || '',
                    ap_company: inputCompany.value || '',
                    ap_address: inputCompanyAddress.value || '',
                    ap_hours: getHoursValue()
                }));
            } catch (err) {}
        }

        function saveSelectedStudentState(student) {
            try {
                if (!student || !student.id) {
                    pageStorage.removeItem(APP_SELECTED_STUDENT_KEY);
                    return;
                }
                pageStorage.setItem(APP_SELECTED_STUDENT_KEY, JSON.stringify({
                    id: String(student.id),
                    name: (student.name || '').toString(),
                    label: (student.label || '').toString()
                }));
            } catch (err) {}
        }

        function loadSelectedStudentState() {
            try {
                var saved = pageStorage.getItem(APP_SELECTED_STUDENT_KEY);
                if (!saved) return null;
                var data = JSON.parse(saved);
                if (!data || !data.id) return null;
                return data;
            } catch (err) {
                return null;
            }
        }

        function loadFormState() {
            try {
                var saved = pageStorage.getItem(APP_FORM_STORAGE_KEY);
                if (!saved) return false;
                var data = JSON.parse(saved);
                if (!data || typeof data !== 'object') return false;
                inputName.value = (data.ap_name || '').toString();
                inputPosition.value = (data.ap_position || '').toString();
                inputCompany.value = (data.ap_company || '').toString();
                inputCompanyAddress.value = (data.ap_address || '').toString();
                setHoursValue((data.ap_hours || '250').toString());
                return true;
            } catch (err) {
                return false;
            }
        }

        function saveApplicationTemplateHtml() {
            if (!letterContent) return;
            setHoursValue(getHoursValue());
            try { pageStorage.setItem(APP_TEMPLATE_STORAGE_KEY, letterContent.innerHTML); } catch (err) {}
        }

        function loadApplicationTemplateHtml() {
            if (!letterContent) return false;
            try {
                var saved = pageStorage.getItem(APP_TEMPLATE_STORAGE_KEY);
                if (!saved) return false;
                var temp = document.createElement('div');
                temp.innerHTML = saved;
                var extracted = temp.querySelector('.content') || temp.querySelector('#application_doc_content');
                if (extracted) {
                    letterContent.innerHTML = extracted.innerHTML;
                } else {
                    var oldHeader = temp.querySelector('.header');
                    if (oldHeader) oldHeader.remove();
                    var oldCrest = temp.querySelector('.crest');
                    if (oldCrest) oldCrest.remove();
                    letterContent.innerHTML = temp.innerHTML || saved;
                }
                hasLoadedSavedTemplate = true;
                setHoursValue(getHoursValue());
                return true;
            } catch (err) {
                return false;
            }
        }

        function openApplicationEditor(e) {
            if (e && typeof e.preventDefault === 'function') e.preventDefault();
            window.location.href = 'pages/edit_application.php?blank=1';
            return false;
        }

        select.select2({
            placeholder: '',
            ajax: {
                url: 'documents/document_application.php',
                dataType: 'json',
                delay: 250,
                data: function (params) { return { action: 'search_students', q: params.term }; },
                processResults: function (data) { return { results: data.results }; }
            },
            minimumInputLength: 1,
            width: 'resolve',
            dropdownParent: $(document.body),
            dropdownCssClass: 'select2-dropdown'
        });

        (function createOverlayInput() {
            var sel = document.getElementById('student_select');
            var container = sel && sel.nextElementSibling;
            if (!container) return;
            container.style.position = container.style.position || 'relative';

            var overlay = document.createElement('input');
            overlay.type = 'text';
            overlay.className = 'select2-overlay-input';
            overlay.placeholder = sel.getAttribute('data-placeholder') || 'Search by name or student id';
            overlay.autocomplete = 'off';
            container.appendChild(overlay);

            function openAndSync() {
                try { select.select2('open'); } catch (e) {}
                setTimeout(function () {
                    var fld = document.querySelector('.select2-container--open .select2-search__field');
                    if (!fld) return;
                    fld.value = overlay.value || '';
                    fld.dispatchEvent(new Event('input', { bubbles: true }));
                }, 0);
            }

            overlay.addEventListener('input', function () { openAndSync(); });
            overlay.addEventListener('keydown', function (e) {
                if (e.key && (e.key.length === 1 || e.key === 'Backspace')) {
                    openAndSync();
                }
            });

            $(document).on('select2:select select2:closing', '#student_select', function () {
                setTimeout(function () {
                    var txt = $('#student_select').find('option:selected').text() || '';
                    overlay.value = txt.replace(/\s+[\u2014-]\s+.*$/, '');
                }, 0);
            });

            container.addEventListener('click', function () { overlay.focus(); });
        })();

        $('#student_select').on('select2:select', function () {
            var id = select.val();
            if (!id) return;
            selectedStudentId = id;
            fetch('documents/document_application.php?action=get_student&id=' + encodeURIComponent(id))
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data) return;
                    var fullname = [data.first_name, data.middle_name, data.last_name].filter(Boolean).join(' ');
                    saveSelectedStudentState({
                        id: id,
                        name: fullname,
                        label: (fullname || 'Student') + ' - ' + (data.student_id || id)
                    });
                    document.getElementById('ap_student').textContent = fullname;
                    document.getElementById('ap_student_name').textContent = fullname;
                    document.getElementById('ap_student_address').textContent = data.address || '__________________________';
                    document.getElementById('ap_student_contact').textContent = data.phone || '__________________________';
                    document.getElementById('ap_date').textContent = new Date().toLocaleDateString();
                    clearRecipientCompanyFields();
                    loadApplicationLetterData(id);
                    updatePreviewFields();
                    updateGenerateLink(id);
                });
        });

        function loadApplicationLetterData(id) {
            if (!id) return;
            fetch('documents/document_application.php?action=get_application_letter&id=' + encodeURIComponent(id))
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data || typeof data !== 'object') return;
                    inputName.value = (data.application_person || '').toString();
                    inputPosition.value = (data.posistion || data.position || '').toString();
                    inputCompany.value = (data.company_name || '').toString();
                    inputCompanyAddress.value = (data.company_address || '').toString();
                    if (data.date) document.getElementById('ap_date').textContent = data.date;
                    updatePreviewFields();
                    updateGenerateLink(id);
                })
                .catch(function () {});
        }

        function prefillByStudentId(id) {
            if (!id) return;
            selectedStudentId = id;
            fetch('documents/document_application.php?action=get_student&id=' + encodeURIComponent(id))
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data || !data.id) return;
                    var fullname = [data.first_name, data.middle_name, data.last_name].filter(Boolean).join(' ');
                    var label = (fullname || 'Student') + ' - ' + (data.student_id || id);
                    var option = new Option(label, String(id), true, true);
                    select.append(option).trigger('change');
                    saveSelectedStudentState({ id: id, name: fullname, label: label });

                    var overlayInput = document.querySelector('.select2-overlay-input');
                    if (overlayInput) overlayInput.value = fullname || '';

                    document.getElementById('ap_student').textContent = fullname || '__________________________';
                    document.getElementById('ap_student_name').textContent = fullname || '__________________________';
                    document.getElementById('ap_student_address').textContent = data.address || '__________________________';
                    document.getElementById('ap_student_contact').textContent = data.phone || '__________________________';
                    document.getElementById('ap_date').textContent = new Date().toLocaleDateString();

                    clearRecipientCompanyFields();
                    loadApplicationLetterData(id);
                    updatePreviewFields();
                    updateGenerateLink(id);
                })
                .catch(function () {});
        }

        function clearRecipientCompanyFields() {
            inputName.value = '';
            inputPosition.value = '';
            inputCompany.value = '';
            inputCompanyAddress.value = '';
            document.getElementById('ap_name').textContent = '__________________________';
            document.getElementById('ap_position').textContent = '__________________________';
            document.getElementById('ap_company').textContent = '__________________________';
            document.getElementById('ap_address').textContent = '__________________________';
            setHoursValue('250');
            saveFormState();
        }

        function updatePreviewFields() {
            if (isFileEditMode) return;
            document.getElementById('ap_name').textContent = inputName.value || '__________________________';
            document.getElementById('ap_position').textContent = inputPosition.value || '__________________________';
            document.getElementById('ap_company').textContent = inputCompany.value || '__________________________';
            document.getElementById('ap_address').textContent = inputCompanyAddress.value || '__________________________';
            setHoursValue(getHoursValue());
            saveFormState();
            saveApplicationTemplateHtml();
        }

        function updateGenerateLink(id) {
            var finalId = id || selectedStudentId || select.val();
            var gen = document.getElementById('btn_generate');
            var params = new URLSearchParams();
            if (finalId) params.set('id', finalId);
            if (inputName.value) params.set('ap_name', inputName.value);
            if (inputPosition.value) params.set('ap_position', inputPosition.value);
            if (inputCompany.value) params.set('ap_company', inputCompany.value);
            if (inputCompanyAddress.value) params.set('ap_address', inputCompanyAddress.value);
            if (getHoursValue()) params.set('ap_hours', getHoursValue());
            try {
                if (pageStorage.getItem(APP_TEMPLATE_STORAGE_KEY)) {
                    params.set('use_saved_template', '1');
                }
            } catch (err) {}
            params.set('date', new Date().toLocaleDateString());
            var url = 'pages/generate_application_letter.php?' + params.toString();
            gen.dataset.url = url;
            return url;
        }

        inputName.addEventListener('input', function () { updatePreviewFields(); updateGenerateLink(selectedStudentId); });
        inputPosition.addEventListener('input', function () { updatePreviewFields(); updateGenerateLink(selectedStudentId); });
        inputCompany.addEventListener('input', function () { updatePreviewFields(); updateGenerateLink(selectedStudentId); });
        inputCompanyAddress.addEventListener('input', function () { updatePreviewFields(); updateGenerateLink(selectedStudentId); });
        inputHours.addEventListener('input', function () { updatePreviewFields(); updateGenerateLink(selectedStudentId); });

        btnFileEdit.addEventListener('click', function (e) {
            openApplicationEditor(e);
        });

        document.getElementById('btn_generate').addEventListener('click', function () {
            var url = updateGenerateLink(selectedStudentId || select.val());
            if (!url) return;
            window.location.href = url;
        });

        loadApplicationTemplateHtml();
        var hasSavedFormState = loadFormState();
        if (!hasSavedFormState) clearRecipientCompanyFields();
        updatePreviewFields();
        if (!hasLoadedSavedTemplate) document.getElementById('ap_date').textContent = new Date().toLocaleDateString();
        updateGenerateLink(selectedStudentId || select.val());

        if (PREFILL_STUDENT_ID > 0) {
            prefillByStudentId(PREFILL_STUDENT_ID);
        } else {
            var savedStudent = loadSelectedStudentState();
            if (savedStudent && savedStudent.id) {
                var option = new Option(savedStudent.label || savedStudent.name || ('Student - ' + savedStudent.id), String(savedStudent.id), true, true);
                select.append(option).trigger('change');
                var overlayInput = document.querySelector('.select2-overlay-input');
                if (overlayInput) overlayInput.value = savedStudent.name || '';
                prefillByStudentId(savedStudent.id);
            }
        }
    }

    function initMoaCommon(isDau) {
        var pageUrl = isDau ? 'documents/document_dau_moa.php' : 'documents/document_moa.php';
        var generateUrl = isDau ? 'pages/generate_dau_moa.php?' : 'pages/generate_moa.php?';
        var editorUrl = isDau ? 'pages/edit_dau_moa.php' : 'pages/edit_moa.php';
        var PREFILL_STUDENT_ID = parseInt(cfg.prefillStudentId || 0, 10) || 0;

        var select = $('#student_select');
        var partnerName = document.getElementById('moa_partner_name');
        var partnerRep = document.getElementById('moa_partner_rep');
        var partnerPosition = document.getElementById('moa_partner_position');
        var partnerAddress = document.getElementById('moa_partner_address');
        var companyReceipt = document.getElementById('moa_company_receipt');
        var totalHours = document.getElementById('moa_total_hours');
        var schoolRep = document.getElementById('moa_school_rep');
        var schoolPosition = document.getElementById('moa_school_position');
        var signedAt = document.getElementById('moa_signed_at');
        var signedDay = document.getElementById('moa_signed_day');
        var signedMonth = document.getElementById('moa_signed_month');
        var signedYear = document.getElementById('moa_signed_year');
        var presencePartnerRep = document.getElementById('moa_presence_partner_rep');
        var presenceSchoolAdmin = document.getElementById('moa_presence_school_admin');
        var presenceSchoolAdminPosition = document.getElementById('moa_presence_school_admin_position');
        var notaryCity = document.getElementById('moa_notary_city');
        var notaryAppeared1 = document.getElementById('moa_notary_appeared_1');
        var notaryAppeared2 = document.getElementById('moa_notary_appeared_2');
        var notaryDay = document.getElementById('moa_notary_day');
        var notaryMonth = document.getElementById('moa_notary_month');
        var notaryYear = document.getElementById('moa_notary_year');
        var notaryPlace = document.getElementById('moa_notary_place');
        var docNo = document.getElementById('moa_doc_no');
        var pageNo = document.getElementById('moa_page_no');
        var bookNo = document.getElementById('moa_book_no');
        var seriesNo = document.getElementById('moa_series_no');
        var btnGenerate = document.getElementById('btn_generate_moa');

        select.select2({
            placeholder: '',
            ajax: {
                url: pageUrl,
                dataType: 'json',
                delay: 250,
                data: function (params) { return { action: 'search_students', q: params.term }; },
                processResults: function (data) { return { results: data.results }; }
            },
            minimumInputLength: 1,
            width: 'resolve',
            dropdownParent: $(document.body),
            dropdownCssClass: 'select2-dropdown'
        });

        (function createOverlayInput() {
            var sel = document.getElementById('student_select');
            var container = sel && sel.nextElementSibling;
            if (!container) return;
            container.style.position = container.style.position || 'relative';

            var overlay = document.createElement('input');
            overlay.type = 'text';
            overlay.className = 'select2-overlay-input';
            overlay.placeholder = sel.getAttribute('data-placeholder') || 'Search by name or student id';
            overlay.autocomplete = 'off';
            container.appendChild(overlay);

            function openAndSync() {
                try { select.select2('open'); } catch (e) {}
                setTimeout(function () {
                    var fld = document.querySelector('.select2-container--open .select2-search__field');
                    if (!fld) return;
                    fld.value = overlay.value || '';
                    fld.dispatchEvent(new Event('input', { bubbles: true }));
                }, 0);
            }

            overlay.addEventListener('input', function () { openAndSync(); });
            overlay.addEventListener('keydown', function (e) {
                if (e.key && (e.key.length === 1 || e.key === 'Backspace')) openAndSync();
            });

            $(document).on('select2:select select2:closing', '#student_select', function () {
                setTimeout(function () {
                    var txt = $('#student_select').find('option:selected').text() || '';
                    overlay.value = txt.replace(/\s+[\u2014-]\s+.*$/, '');
                }, 0);
            });

            container.addEventListener('click', function () { overlay.focus(); });
        })();

        document.addEventListener('click', function (e) {
            var editBtn = e.target.closest('#btn_file_edit_moa');
            if (!editBtn) return;
            if (e && typeof e.preventDefault === 'function') e.preventDefault();
            window.open(editorUrl, '_blank');
        });

        $('#student_select').on('select2:select', function () {
            var id = select.val();
            if (!id) return;
            fetch(pageUrl + '?action=get_student&id=' + encodeURIComponent(id))
                .then(function (r) { return r.json(); })
                .then(function () {
                    loadMoaData(id);
                });
        });

        function loadMoaData(id) {
            if (!id) return;
            fetch(pageUrl + '?action=get_moa&id=' + encodeURIComponent(id))
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data || typeof data !== 'object') return;
                    partnerName.value = (data.company_name || '').toString();
                    partnerAddress.value = (data.company_address || '').toString();
                    partnerRep.value = (data.partner_representative || '').toString();
                    partnerPosition.value = (data.position || '').toString();
                    schoolRep.value = (isDau ? (data.school_representative || data.coordinator || '') : (data.coordinator || '')).toString();
                    schoolPosition.value = (data.school_posistion || data.school_position || '').toString();
                    signedAt.value = (isDau ? (data.signed_at || data.moa_address || '') : (data.moa_address || '')).toString();
                    presencePartnerRep.value = (isDau ? (data.witness_partner || data.witness || '') : (data.witness || '')).toString();
                    presenceSchoolAdmin.value = (data.school_administrator || '').toString();
                    presenceSchoolAdminPosition.value = (data.school_admin_position || '').toString();
                    notaryCity.value = (isDau ? (data.notary_city || data.notary_address || '') : (data.notary_address || '')).toString();
                    notaryPlace.value = (isDau ? (data.notary_place || data.acknowledgement_address || '') : (data.acknowledgement_address || '')).toString();
                    companyReceipt.value = (data.company_receipt || '').toString();
                    totalHours.value = (data.total_hours || '').toString();

                    if (data.signed_day || data.signed_month || data.signed_year) {
                        signedDay.value = (data.signed_day || '').toString();
                        signedMonth.value = (data.signed_month || '').toString();
                        signedYear.value = (data.signed_year || '').toString();
                    }
                    if (data.moa_date) {
                        var d = new Date(data.moa_date);
                        if (!isNaN(d.getTime())) {
                            signedDay.value = String(d.getDate()).padStart(2, '0');
                            signedMonth.value = d.toLocaleString('en-US', { month: 'long' });
                            signedYear.value = String(d.getFullYear());
                        }
                    }
                    if (data.notary_day || data.notary_month || data.notary_year) {
                        notaryDay.value = (data.notary_day || '').toString();
                        notaryMonth.value = (data.notary_month || '').toString();
                        notaryYear.value = (data.notary_year || '').toString();
                    }
                    if (data.acknowledgement_date) {
                        var ad = new Date(data.acknowledgement_date);
                        if (!isNaN(ad.getTime())) {
                            notaryDay.value = String(ad.getDate()).padStart(2, '0');
                            notaryMonth.value = ad.toLocaleString('en-US', { month: 'long' });
                            notaryYear.value = String(ad.getFullYear());
                        }
                    }
                    if (notaryAppeared1) {
                        notaryAppeared1.value = (isDau ? (data.witness_partner || data.witness || '') : (data.witness || '')).toString();
                    }

                    updatePreview();
                    updateGenerateLink();
                })
                .catch(function () {});
        }

        function prefillByStudentId(id) {
            if (!id) return;
            fetch(pageUrl + '?action=get_student&id=' + encodeURIComponent(id))
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data || !data.id) return;
                    if (select && select.length) {
                        var fullname = [data.first_name, data.middle_name, data.last_name].filter(Boolean).join(' ');
                        var label = (fullname || 'Student') + ' - ' + (data.student_id || id);
                        var option = new Option(label, String(id), true, true);
                        select.append(option).trigger('change');
                    }
                    loadMoaData(id);
                })
                .catch(function () {});
        }

        function updatePreview() {
            document.getElementById('pv_partner_company_name').textContent = partnerName.value || '__________________________';
            document.getElementById('pv_partner_name').textContent = partnerRep.value || '__________________________';
            document.getElementById('pv_partner_address').textContent = partnerAddress.value || '__________________________';
            document.getElementById('pv_company_receipt').textContent = companyReceipt.value || '__________________________';
            document.getElementById('pv_total_hours').textContent = (totalHours && totalHours.value) ? totalHours.value : '250';
            document.getElementById('pv_partner_rep').textContent = partnerRep.value || '__________________________';
            document.getElementById('pv_partner_position').textContent = partnerPosition.value || (isDau ? 'Barangay Dau PYAP President' : '______________, ');
            document.getElementById('pv_school_rep').textContent = schoolRep.value || '__________________';
            document.getElementById('pv_signed_at').textContent = signedAt.value || '__________________';
            document.getElementById('pv_signed_day').textContent = withOrdinalSuffix(signedDay.value) || '_____';
            document.getElementById('pv_signed_month').textContent = signedMonth.value || '__________________';
            document.getElementById('pv_signed_year').textContent = signedYear.value || '20__';
            document.getElementById('pv_presence_partner_rep').textContent = presencePartnerRep.value || '______________________________';
            document.getElementById('pv_presence_school_admin').textContent = presenceSchoolAdmin.value || '______________________';
            document.getElementById('pv_presence_school_admin_position').textContent = presenceSchoolAdminPosition.value || '______________________';
            document.getElementById('pv_school_position').textContent = schoolPosition.value || '__________________';
            document.getElementById('pv_notary_city').textContent = notaryCity.value || '__________________';
            var appeared1Value = (notaryAppeared1 && notaryAppeared1.value) || (presencePartnerRep && presencePartnerRep.value) || '';
            var pvNotaryAppeared1 = document.getElementById('pv_notary_appeared_1');
            if (pvNotaryAppeared1) pvNotaryAppeared1.textContent = appeared1Value || '__________________';
            var pvNotaryAppeared2 = document.getElementById('pv_notary_appeared_2');
            if (pvNotaryAppeared2) pvNotaryAppeared2.textContent = (notaryAppeared2 && notaryAppeared2.value) || '__________________';
            document.getElementById('pv_notary_day').textContent = withOrdinalSuffix(notaryDay.value) || '_____';
            document.getElementById('pv_notary_month').textContent = notaryMonth.value || '__________________';
            document.getElementById('pv_notary_year').textContent = notaryYear.value || '20___';
            document.getElementById('pv_notary_place').textContent = notaryPlace.value || '__________________';
            document.getElementById('pv_doc_no').textContent = docNo.value || '______';
            document.getElementById('pv_page_no').textContent = pageNo.value || '_____';
            document.getElementById('pv_book_no').textContent = bookNo.value || '_____';
            document.getElementById('pv_series_no').textContent = seriesNo.value || '_____';
            document.getElementById('moa_date').textContent = new Date().toLocaleDateString();
        }

        function updateGenerateLink() {
            var params = new URLSearchParams();
            if (partnerName.value) params.set('partner_name', partnerName.value);
            if (partnerRep.value) params.set('partner_rep', partnerRep.value);
            if (partnerPosition.value) params.set('partner_position', partnerPosition.value);
            if (partnerAddress.value) params.set('partner_address', partnerAddress.value);
            if (companyReceipt.value) params.set('company_receipt', companyReceipt.value);
            if (totalHours && totalHours.value) params.set('total_hours', totalHours.value);
            if (schoolRep.value) params.set('school_rep', schoolRep.value);
            if (schoolPosition && schoolPosition.value) params.set('school_position', schoolPosition.value);
            if (signedAt.value) params.set('signed_at', signedAt.value);
            if (signedDay.value) params.set('signed_day', withOrdinalSuffix(signedDay.value));
            if (signedMonth.value) params.set('signed_month', signedMonth.value);
            if (signedYear.value) params.set('signed_year', signedYear.value);
            if (presencePartnerRep.value) params.set('presence_partner_rep', presencePartnerRep.value);
            if (presenceSchoolAdmin.value) params.set('presence_school_admin', presenceSchoolAdmin.value);
            if (presenceSchoolAdminPosition && presenceSchoolAdminPosition.value) params.set('presence_school_admin_position', presenceSchoolAdminPosition.value);
            if (notaryCity.value) params.set('notary_city', notaryCity.value);
            if (notaryAppeared1 && notaryAppeared1.value) params.set('notary_appeared_1', notaryAppeared1.value);
            else if (presencePartnerRep && presencePartnerRep.value) params.set('notary_appeared_1', presencePartnerRep.value);
            if (notaryAppeared2 && notaryAppeared2.value) params.set('notary_appeared_2', notaryAppeared2.value);
            if (notaryDay.value) params.set('notary_day', withOrdinalSuffix(notaryDay.value));
            if (notaryMonth.value) params.set('notary_month', notaryMonth.value);
            if (notaryYear.value) params.set('notary_year', notaryYear.value);
            if (notaryPlace.value) params.set('notary_place', notaryPlace.value);
            if (docNo.value) params.set('doc_no', docNo.value);
            if (pageNo.value) params.set('page_no', pageNo.value);
            if (bookNo.value) params.set('book_no', bookNo.value);
            if (seriesNo.value) params.set('series_no', seriesNo.value);
            params.set('date', new Date().toLocaleDateString());
            var url = generateUrl + params.toString();
            btnGenerate.dataset.url = url;
            return url;
        }

        [
            partnerName, partnerRep, partnerPosition, partnerAddress, companyReceipt, totalHours, schoolRep, schoolPosition,
            signedAt, signedDay, signedMonth, signedYear,
            presencePartnerRep, presenceSchoolAdmin, presenceSchoolAdminPosition,
            notaryCity, notaryAppeared1, notaryAppeared2,
            notaryDay, notaryMonth, notaryYear, notaryPlace,
            docNo, pageNo, bookNo, seriesNo
        ].forEach(function (el) {
            if (!el) return;
            el.addEventListener('input', function () {
                updatePreview();
                updateGenerateLink();
            });
        });

        btnGenerate.addEventListener('click', function () {
            var url = updateGenerateLink();
            if (!url) return;
            window.location.href = url;
        });

        updatePreview();
        updateGenerateLink();
        if (PREFILL_STUDENT_ID > 0) prefillByStudentId(PREFILL_STUDENT_ID);
    }

    function initEndorsementPage() {
        var prefillId = parseInt(cfg.prefillStudentId || 0, 10) || 0;
        var PREFILL_GREETING_PREF = (cfg.prefillGreetingPref || 'either').toString();
        var PREFILL_RECIPIENT_TITLE = (cfg.prefillRecipientTitle || 'auto').toString();

        var select = $('#student_select');
        var inputRecipient = document.getElementById('input_recipient');
        var inputPosition = document.getElementById('input_position');
        var inputCompany = document.getElementById('input_company');
        var inputCompanyAddress = document.getElementById('input_company_address');
        var inputStudents = document.getElementById('input_students');
        var recipientTitleRadios = Array.prototype.slice.call(document.querySelectorAll('input[name="recipient_title"]'));
        var greetingRadios = Array.prototype.slice.call(document.querySelectorAll('input[name="greeting_preference"]'));
        var btnGenerate = document.getElementById('btn_generate');
        var btnFileEdit = document.getElementById('btn_file_edit');
        var selectedStudentId = prefillId > 0 ? String(prefillId) : '';

        function sanitizeStudentLines(raw) {
            return String(raw || '')
                .split(/\r?\n/)
                .map(function (x) { return x.trim(); })
                .filter(Boolean);
        }

        function inferTitleFromName(name) {
            var n = String(name || '').trim();
            if (!n) return 'none';
            var l = n.toLowerCase();
            if (l.startsWith('mr ') || l.startsWith('mr.') || l.startsWith('sir ')) return 'mr';
            if (l.startsWith('ms ') || l.startsWith('ms.') || l.startsWith('mrs ') || l.startsWith('mrs.') || l.startsWith('maam') || l.startsWith("ma'am") || l.startsWith('madam')) return 'ms';
            var first = l.replace(/[^a-z\s]/g, ' ').trim().split(/\s+/)[0] || '';
            var likelyMale = ['jomer', 'jomar', 'jose', 'juan', 'mark', 'michael', 'john', 'james', 'daniel', 'paul', 'peter', 'kevin', 'robert', 'edward', 'ross', 'ramirez', 'sanchez', 'felix', 'ivan'];
            var likelyFemale = ['anna', 'ana', 'maria', 'marie', 'jane', 'joy', 'kim', 'angel', 'diana', 'michelle', 'grace', 'sarah', 'liza', 'rose', 'patricia', 'christine', 'karen', 'claire'];
            if (likelyMale.indexOf(first) !== -1) return 'mr';
            if (likelyFemale.indexOf(first) !== -1) return 'ms';
            return 'none';
        }

        function resolveRecipientTitle() {
            var checked = recipientTitleRadios.find(function (r) { return r.checked; });
            var selected = checked ? checked.value : 'auto';
            if (selected === 'auto') return inferTitleFromName(inputRecipient.value);
            return selected;
        }

        function buildNameFromOptionText(text) {
            return String(text || '').replace(/\s*-\s*.*$/, '').trim();
        }

        function getSelectedStudentName() {
            var selected = select.select2('data') || [];
            var first = selected[0] || null;
            if (first && first.text) return buildNameFromOptionText(first.text);
            var txt = $('#student_select').find('option:selected').text() || '';
            return buildNameFromOptionText(txt);
        }

        function detectSalutation(name) {
            var resolvedTitle = resolveRecipientTitle();
            if (resolvedTitle === 'mr') return 'Dear Sir,';
            if (resolvedTitle === 'ms') return 'Dear Ma\'am,';
            if (resolvedTitle === 'none') return 'Dear Sir/Ma\'am,';

            var checked = greetingRadios.find(function (r) { return r.checked; });
            var pref = checked ? checked.value : 'either';
            if (pref === 'sir') return 'Dear Sir,';
            if (pref === 'maam') return 'Dear Ma\'am,';
            var n = String(name || '').toLowerCase().trim();
            if (n.startsWith('mr ') || n.startsWith('mr.') || n.startsWith('sir')) return 'Dear Sir,';
            if (n.startsWith('ms ') || n.startsWith('ms.') || n.startsWith('mrs ') || n.startsWith('mrs.') || n.startsWith('maam') || n.startsWith('ma\'am') || n.startsWith('madam')) return 'Dear Ma\'am,';
            return 'Dear Ma\'am,';
        }

        function appendSelectedStudentToTextarea() {
            var selectedName = getSelectedStudentName();
            if (!selectedName) return;
            var lines = sanitizeStudentLines(inputStudents.value);
            if (lines.indexOf(selectedName) === -1) {
                lines.push(selectedName);
                inputStudents.value = lines.join('\n');
            }
        }

        function formatRecipientName(name) {
            var n = String(name || '').trim();
            if (!n) return '__________________________';
            var rt = resolveRecipientTitle();
            if (rt === 'mr') return 'Mr. ' + n;
            if (rt === 'ms') return 'Ms. ' + n;
            if (rt === 'none') return 'Mr./Ms. ' + n;
            return n;
        }

        function updatePreview() {
            document.getElementById('pv_recipient').textContent = formatRecipientName(inputRecipient.value);
            document.getElementById('pv_position').textContent = inputPosition.value || '__________________________';
            document.getElementById('pv_company').textContent = inputCompany.value || '__________________________';
            document.getElementById('pv_company_address').textContent = inputCompanyAddress.value || '__________________________';
            document.getElementById('pv_salutation').textContent = detectSalutation(inputRecipient.value);

            var ul = document.getElementById('pv_students');
            var typed = sanitizeStudentLines(inputStudents.value);
            var selectedName = getSelectedStudentName();
            var lines = typed.length ? typed : (selectedName ? [selectedName] : []);
            ul.innerHTML = '';
            if (!lines.length) {
                var li = document.createElement('li');
                li.textContent = '__________________________';
                ul.appendChild(li);
            } else {
                lines.forEach(function (line) {
                    var _li = document.createElement('li');
                    _li.textContent = line;
                    ul.appendChild(_li);
                });
            }
        }

        function updateLinks() {
            var p = new URLSearchParams();
            var selectedId = select.val() || selectedStudentId;
            if (selectedId) p.set('id', String(selectedId));
            else if (prefillId > 0) p.set('id', String(prefillId));
            if (inputRecipient.value) p.set('recipient', inputRecipient.value);
            var rt = recipientTitleRadios.find(function (r) { return r.checked; });
            if (rt && rt.value) p.set('recipient_title', rt.value);
            if (inputPosition.value) p.set('position', inputPosition.value);
            if (inputCompany.value) p.set('company', inputCompany.value);
            if (inputCompanyAddress.value) p.set('company_address', inputCompanyAddress.value);
            var checked = greetingRadios.find(function (r) { return r.checked; });
            if (checked && checked.value) p.set('greeting_pref', checked.value);
            var typed = sanitizeStudentLines(inputStudents.value);
            var selectedName = getSelectedStudentName();
            var studentsValue = typed.length ? typed.join('\n') : selectedName;
            if (studentsValue) p.set('students', studentsValue);
            var genUrl = 'pages/generate_endorsement_letter.php?' + p.toString();
            btnGenerate.href = genUrl;
            btnFileEdit.href = 'pages/edit_endorsement.php?blank=1';
            return genUrl;
        }

        function applySavedEndorsement(data) {
            if (!data || typeof data !== 'object') return false;
            var changed = false;
            if (data.recipient_name) {
                inputRecipient.value = String(data.recipient_name);
                changed = true;
            }
            if (data.recipient_title) {
                var rt = String(data.recipient_title).toLowerCase();
                recipientTitleRadios.forEach(function (r) { r.checked = (r.value === rt); });
                changed = true;
            }
            if (data.recipient_position) {
                inputPosition.value = String(data.recipient_position);
                changed = true;
            }
            if (data.company_name) {
                inputCompany.value = String(data.company_name);
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
            if (data.greeting_preference) {
                var gp = String(data.greeting_preference).toLowerCase();
                greetingRadios.forEach(function (r) { r.checked = (r.value === gp); });
                changed = true;
            }
            return changed;
        }

        select.select2({
            placeholder: '',
            ajax: {
                url: 'documents/document_endorsement.php',
                dataType: 'json',
                delay: 250,
                data: function (params) { return { action: 'search_students', q: params.term }; },
                processResults: function (data) { return { results: data.results || [] }; }
            },
            minimumInputLength: 1,
            width: 'resolve',
            dropdownParent: $(document.body),
            dropdownCssClass: 'select2-dropdown'
        });

        function createSelectOverlay() {
            if (document.querySelector('.select2-overlay-input')) return;
            var sel = document.getElementById('student_select');
            if (!sel) return;
            var container = sel.nextElementSibling;
            if (!container || !container.classList || !container.classList.contains('select2')) return;
            container.style.position = 'relative';
            var overlay = document.createElement('input');
            overlay.type = 'text';
            overlay.className = 'select2-overlay-input';
            overlay.placeholder = sel.getAttribute('data-placeholder') || 'Search by name or student id';
            overlay.autocomplete = 'off';
            container.appendChild(overlay);

            function openAndSync() {
                try { select.select2('open'); } catch (e) {}
                setTimeout(function () {
                    var fld = document.querySelector('.select2-container--open .select2-search__field');
                    if (!fld) return;
                    fld.value = overlay.value || '';
                    fld.dispatchEvent(new Event('input', { bubbles: true }));
                }, 0);
            }

            overlay.addEventListener('input', function () { openAndSync(); });
            overlay.addEventListener('keydown', function (e) {
                if (e.key && (e.key.length === 1 || e.key === 'Backspace')) openAndSync();
            });

            $(document).on('select2:select select2:closing', '#student_select', function () {
                setTimeout(function () {
                    var txt = $('#student_select').find('option:selected').text() || '';
                    overlay.value = buildNameFromOptionText(txt);
                }, 0);
            });
            container.addEventListener('click', function () { overlay.focus(); });
        }

        select.on('select2:select', function () {
            var pickedId = String(select.val() || '');
            if (pickedId) selectedStudentId = pickedId;
            appendSelectedStudentToTextarea();
            if (pickedId) {
                fetch('documents/document_endorsement.php?action=get_endorsement&id=' + encodeURIComponent(pickedId))
                    .then(function (r) { return r.json(); })
                    .then(function (saved) {
                        applySavedEndorsement(saved);
                        updatePreview();
                        updateLinks();
                    })
                    .catch(function () {});
            }
            select.val(null).trigger('change');
            var overlay = document.querySelector('.select2-overlay-input');
            if (overlay) overlay.value = '';
            setTimeout(function () { if (overlay) overlay.focus(); }, 0);
            updatePreview();
            updateLinks();
        });

        select.on('select2:unselect change', function () {
            updatePreview();
            updateLinks();
        });

        [inputRecipient, inputPosition, inputCompany, inputCompanyAddress, inputStudents].forEach(function (el) {
            el.addEventListener('input', function () {
                updatePreview();
                updateLinks();
            });
        });

        recipientTitleRadios.forEach(function (r) {
            r.addEventListener('change', function () {
                updatePreview();
                updateLinks();
            });
        });

        greetingRadios.forEach(function (r) {
            r.addEventListener('change', function () {
                updatePreview();
                updateLinks();
            });
        });

        btnFileEdit.addEventListener('click', function (e) {
            e.preventDefault();
            var href = btnFileEdit.href || 'pages/edit_endorsement.php?blank=1';
            window.location.href = href;
        });

        btnGenerate.addEventListener('click', function (e) {
            e.preventDefault();
            var href = btnGenerate.href || updateLinks();
            if (!href) return;
            window.open(href, '_blank');
        });

        if (prefillId > 0) {
            fetch('documents/document_endorsement.php?action=get_endorsement&id=' + encodeURIComponent(prefillId))
                .then(function (r) { return r.json(); })
                .then(function (saved) {
                    var hasSaved = applySavedEndorsement(saved);
                    if (hasSaved) {
                        updatePreview();
                        updateLinks();
                        setTimeout(createSelectOverlay, 60);
                        return;
                    }
                    fetch('documents/document_endorsement.php?action=get_student&id=' + encodeURIComponent(prefillId))
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            var full = [data.first_name, data.middle_name, data.last_name].filter(Boolean).join(' ').trim();
                            if (full) {
                                var text = full + ' - ' + (data.student_id || '');
                                var o = new Option(text, String(prefillId), true, true);
                                selectedStudentId = String(prefillId);
                                select.append(o).trigger('change');
                            }
                            updatePreview();
                            updateLinks();
                            setTimeout(createSelectOverlay, 60);
                        });
                });
        }

        setTimeout(createSelectOverlay, 60);
        recipientTitleRadios.forEach(function (r) { r.checked = (r.value === PREFILL_RECIPIENT_TITLE); });
        if (!recipientTitleRadios.some(function (r) { return r.checked; }) && recipientTitleRadios.length) {
            var auto = recipientTitleRadios.find(function (r) { return r.value === 'auto'; });
            if (auto) auto.checked = true;
        }
        greetingRadios.forEach(function (r) { r.checked = (r.value === PREFILL_GREETING_PREF); });
        if (!greetingRadios.some(function (r) { return r.checked; }) && greetingRadios.length) {
            var either = greetingRadios.find(function (r) { return r.value === 'either'; });
            if (either) either.checked = true;
        }
        updatePreview();
        updateLinks();
    }

    window.addEventListener('load', function () {
        installHideOnErrorImages();

        if (cfg.page === 'application') initApplicationPage();
        if (cfg.page === 'moa') initMoaCommon(false);
        if (cfg.page === 'dau_moa') initMoaCommon(true);
        if (cfg.page === 'endorsement') initEndorsementPage();
    });
})();
