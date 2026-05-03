(function () {
    'use strict';

    var root = document.querySelector('.doc-page-root.moa-page');
    if (!root || !window.AppCore || !window.AppCore.TemplateEditor) {
        return;
    }

    var page = (root.getAttribute('data-page') || '').toLowerCase();
    if (page !== 'moa' && page !== 'dau_moa') {
        return;
    }

    var isDau = page === 'dau_moa';
    var prefillStudentId = parseInt(root.getAttribute('data-prefill-student-id') || '0', 10) || 0;
    var prefillCompanyKey = (root.getAttribute('data-prefill-company') || '').trim();
    var pageUrl = isDau ? 'documents/document_dau_moa.php' : 'documents/document_moa.php';
    var templateStorageKey = isDau ? 'biotern_dau_moa_template_html_v2' : 'biotern_moa_template_html_v2';
    var legacyStorageKey = isDau ? 'biotern_dau_moa_template_html_v1' : 'biotern_moa_template_html_v1';

    var editor = document.getElementById('moa_content');
    var select = window.jQuery ? window.jQuery('#student_select') : null;
    var companySelect = window.jQuery ? window.jQuery('#company_select') : null;

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

    var btnPrint = document.getElementById('btn_print');
    var btnPrintForm = document.getElementById('btn_print_moa');
    var btnToggleEdit = document.getElementById('btn_toggle_edit');
    var toolbar = document.getElementById('builder_toolbar');

    var templateRuntime = null;
    var isEditMode = false;
    var initialTemplateHtml = editor ? editor.innerHTML : '';
    var draftBaseline = '';
    var hasDraftChanges = false;

    function getStorage(key) {
        try {
            return window.localStorage.getItem(key) || '';
        } catch (err) {
            return '';
        }
    }

    function setStorage(key, value) {
        try {
            window.localStorage.setItem(key, value);
        } catch (err) {}
    }

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

    function setStatus(text) {
        if (templateRuntime && typeof templateRuntime.setStatus === 'function') {
            templateRuntime.setStatus(text);
        }
    }

    function serializeDraftState() {
        return JSON.stringify({
            partnerName: partnerName && partnerName.value ? partnerName.value : '',
            partnerRep: partnerRep && partnerRep.value ? partnerRep.value : '',
            partnerPosition: partnerPosition && partnerPosition.value ? partnerPosition.value : '',
            partnerAddress: partnerAddress && partnerAddress.value ? partnerAddress.value : '',
            companyReceipt: companyReceipt && companyReceipt.value ? companyReceipt.value : '',
            totalHours: totalHours && totalHours.value ? totalHours.value : '',
            schoolRep: schoolRep && schoolRep.value ? schoolRep.value : '',
            schoolPosition: schoolPosition && schoolPosition.value ? schoolPosition.value : '',
            signedAt: signedAt && signedAt.value ? signedAt.value : '',
            signedDay: signedDay && signedDay.value ? signedDay.value : '',
            signedMonth: signedMonth && signedMonth.value ? signedMonth.value : '',
            signedYear: signedYear && signedYear.value ? signedYear.value : '',
            presencePartnerRep: presencePartnerRep && presencePartnerRep.value ? presencePartnerRep.value : '',
            presenceSchoolAdmin: presenceSchoolAdmin && presenceSchoolAdmin.value ? presenceSchoolAdmin.value : '',
            presenceSchoolAdminPosition: presenceSchoolAdminPosition && presenceSchoolAdminPosition.value ? presenceSchoolAdminPosition.value : '',
            notaryCity: notaryCity && notaryCity.value ? notaryCity.value : '',
            notaryAppeared1: notaryAppeared1 && notaryAppeared1.value ? notaryAppeared1.value : '',
            notaryAppeared2: notaryAppeared2 && notaryAppeared2.value ? notaryAppeared2.value : '',
            notaryDay: notaryDay && notaryDay.value ? notaryDay.value : '',
            notaryMonth: notaryMonth && notaryMonth.value ? notaryMonth.value : '',
            notaryYear: notaryYear && notaryYear.value ? notaryYear.value : '',
            notaryPlace: notaryPlace && notaryPlace.value ? notaryPlace.value : '',
            docNo: docNo && docNo.value ? docNo.value : '',
            pageNo: pageNo && pageNo.value ? pageNo.value : '',
            bookNo: bookNo && bookNo.value ? bookNo.value : '',
            seriesNo: seriesNo && seriesNo.value ? seriesNo.value : ''
        });
    }

    function captureDraftBaseline() {
        draftBaseline = serializeDraftState();
        hasDraftChanges = false;
    }

    function updateDraftState() {
        hasDraftChanges = serializeDraftState() !== draftBaseline;
    }

    function clearFormFields() {
        [
            partnerName, partnerRep, partnerPosition, partnerAddress, companyReceipt, totalHours, schoolRep, schoolPosition,
            signedAt, signedDay, signedMonth, signedYear,
            presencePartnerRep, presenceSchoolAdmin, presenceSchoolAdminPosition,
            notaryCity, notaryAppeared1, notaryAppeared2,
            notaryDay, notaryMonth, notaryYear, notaryPlace,
            docNo, pageNo, bookNo, seriesNo
        ].forEach(function (field) {
            if (field) {
                field.value = '';
            }
        });

        if (select && select.length) {
            select.empty().trigger('change');
        }

        if (companySelect && companySelect.length) {
            companySelect.empty().trigger('change');
        }
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

    function setTextSafe(ids, value) {
        var list = Array.isArray(ids) ? ids : [ids];
        list.forEach(function (id) {
            var el = document.getElementById(id);
            if (el) {
                var nextValue = value;
                if ((!value || !String(value).trim()) && el.classList && el.classList.contains('moa-fill-line')) {
                    nextValue = '\u00A0';
                }
                el.textContent = nextValue;
            }
        });
    }

    function normalizeLoadedTemplateMarkup() {
        if (!editor) {
            return;
        }

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

        var directChildren = Array.prototype.slice.call(editor.children || []);
        var stack = editor.classList && editor.classList.contains('a4-pages-stack') ? editor : null;

        if (!stack) {
            for (var i = 0; i < directChildren.length; i += 1) {
                if (directChildren[i].classList && directChildren[i].classList.contains('a4-pages-stack')) {
                    stack = directChildren[i];
                    break;
                }
            }
        }

        if (!stack) {
            stack = document.createElement('div');
            stack.className = 'a4-pages-stack';
            while (editor.firstChild) {
                stack.appendChild(editor.firstChild);
            }
            editor.appendChild(stack);
        }

        if (stack === editor) {
            Array.prototype.slice.call(stack.children || []).forEach(function (child) {
                if (!child || !child.classList || !child.classList.contains('a4-pages-stack')) {
                    return;
                }
                while (child.firstChild) {
                    stack.insertBefore(child.firstChild, child);
                }
                stack.removeChild(child);
            });
        }

        var stackChildren = Array.prototype.slice.call(stack.children || []);
        var hasDirectPage = stackChildren.some(function (child) {
            return child.classList && child.classList.contains('a4-page');
        });

        if (!hasDirectPage) {
            var firstPage = document.createElement('div');
            firstPage.className = 'a4-page';
            while (stack.firstChild) {
                firstPage.appendChild(stack.firstChild);
            }
            stack.appendChild(firstPage);
        }

        var pages = Array.prototype.slice.call(stack.children || []).filter(function (child) {
            return child.classList && child.classList.contains('a4-page');
        });

        pages.forEach(function (pageEl) {
            pageEl.setAttribute('data-a4-width-mm', '210');
            pageEl.setAttribute('data-a4-height-mm', '297');
            pageEl.style.width = '210mm';
            pageEl.style.minHeight = '297mm';
            pageEl.style.boxSizing = 'border-box';
            if (!pageEl.style.padding) {
                pageEl.style.padding = '0.35in 1in 1in 1in';
            }
            if (!pageEl.style.background) {
                pageEl.style.background = '#ffffff';
            }
        });
    }

    function setEditMode(nextMode) {
        isEditMode = !!nextMode;
        if (!editor) {
            return;
        }

        editor.setAttribute('contenteditable', isEditMode ? 'true' : 'false');

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

    function updatePreview() {
        setTextSafe('pv_partner_company_name', partnerName && partnerName.value ? partnerName.value : '');
        setTextSafe('pv_partner_name', partnerRep && partnerRep.value ? partnerRep.value : '');
        setTextSafe('pv_partner_address', partnerAddress && partnerAddress.value ? partnerAddress.value : '');
        setTextSafe('pv_company_receipt', companyReceipt && companyReceipt.value ? companyReceipt.value : '');
        setTextSafe('pv_total_hours', totalHours && totalHours.value ? totalHours.value : '250');
        setTextSafe('pv_partner_rep', partnerRep && partnerRep.value ? partnerRep.value : '');
        setTextSafe('pv_partner_position', partnerPosition && partnerPosition.value ? partnerPosition.value : (isDau ? 'Barangay Dau PYAP President' : '______________, '));
        setTextSafe('pv_school_rep', schoolRep && schoolRep.value ? schoolRep.value : '');
        setTextSafe('pv_signed_at', signedAt && signedAt.value ? signedAt.value : '');
        setTextSafe('pv_signed_day', signedDay && signedDay.value ? withOrdinalSuffix(signedDay.value) : '');
        setTextSafe('pv_signed_month', signedMonth && signedMonth.value ? signedMonth.value : '');
        setTextSafe('pv_signed_year', signedYear && signedYear.value ? signedYear.value : '');
        setTextSafe(['pv_presence_partner_rep', 'company_receipt'], presencePartnerRep && presencePartnerRep.value ? presencePartnerRep.value : '______________________________');
        setTextSafe(['pv_presence_school_admin', 'presence_school_admin'], presenceSchoolAdmin && presenceSchoolAdmin.value ? presenceSchoolAdmin.value : '______________________');
        setTextSafe(['pv_presence_school_admin_position', 'presence_school_admin_position'], presenceSchoolAdminPosition && presenceSchoolAdminPosition.value ? presenceSchoolAdminPosition.value : '______________________');
        setTextSafe('pv_school_position', schoolPosition && schoolPosition.value ? schoolPosition.value : '__________________');
        setTextSafe('pv_notary_city', notaryCity && notaryCity.value ? notaryCity.value : '__________________');

        var appeared1Value = (notaryAppeared1 && notaryAppeared1.value) || (presencePartnerRep && presencePartnerRep.value) || '';
        var pvNotaryAppeared1 = document.getElementById('pv_notary_appeared_1');
        if (pvNotaryAppeared1) pvNotaryAppeared1.textContent = appeared1Value || '__________________';
        var pvNotaryAppeared2 = document.getElementById('pv_notary_appeared_2');
        if (pvNotaryAppeared2) pvNotaryAppeared2.textContent = (notaryAppeared2 && notaryAppeared2.value) || '__________________';

        setTextSafe('pv_notary_day', notaryDay && notaryDay.value ? withOrdinalSuffix(notaryDay.value) : '_____');
        setTextSafe('pv_notary_month', notaryMonth && notaryMonth.value ? notaryMonth.value : '__________________');
        setTextSafe('pv_notary_year', notaryYear && notaryYear.value ? notaryYear.value : '20___');
        setTextSafe('pv_notary_place', notaryPlace && notaryPlace.value ? notaryPlace.value : '__________________');
        setTextSafe('pv_doc_no', docNo && docNo.value ? docNo.value : '______');
        setTextSafe('pv_page_no', pageNo && pageNo.value ? pageNo.value : '_____');
        setTextSafe('pv_book_no', bookNo && bookNo.value ? bookNo.value : '_____');
        setTextSafe('pv_series_no', seriesNo && seriesNo.value ? seriesNo.value : '_____');
        setTextSafe('moa_date', new Date().toLocaleDateString());
    }

    function loadMoaData(id) {
        if (!id) return;
        fetch(pageUrl + '?action=get_moa&id=' + encodeURIComponent(id), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || typeof data !== 'object') return;
                if (partnerName) partnerName.value = (data.company_name || '').toString();
                if (partnerAddress) partnerAddress.value = (data.company_address || '').toString();
                if (partnerRep) partnerRep.value = (data.partner_representative || '').toString();
                if (partnerPosition) partnerPosition.value = (data.position || '').toString();
                if (schoolRep) schoolRep.value = (isDau ? (data.school_representative || data.coordinator || '') : (data.coordinator || '')).toString();
                if (schoolPosition) schoolPosition.value = (data.school_posistion || data.school_position || '').toString();
                if (signedAt) signedAt.value = (isDau ? (data.signed_at || data.moa_address || '') : (data.moa_address || '')).toString();
                if (presencePartnerRep) presencePartnerRep.value = (isDau ? (data.witness_partner || data.witness || '') : (data.witness || '')).toString();
                if (presenceSchoolAdmin) presenceSchoolAdmin.value = (data.school_administrator || '').toString();
                if (presenceSchoolAdminPosition) presenceSchoolAdminPosition.value = (data.school_admin_position || '').toString();
                if (notaryCity) notaryCity.value = (isDau ? (data.notary_city || data.notary_address || '') : (data.notary_address || '')).toString();
                if (notaryPlace) notaryPlace.value = (isDau ? (data.notary_place || data.acknowledgement_address || '') : (data.acknowledgement_address || '')).toString();
                if (companyReceipt) companyReceipt.value = (data.company_receipt || '').toString();
                if (totalHours) totalHours.value = (data.total_hours || '').toString();

                if (data.signed_day || data.signed_month || data.signed_year) {
                    if (signedDay) signedDay.value = (data.signed_day || '').toString();
                    if (signedMonth) signedMonth.value = (data.signed_month || '').toString();
                    if (signedYear) signedYear.value = (data.signed_year || '').toString();
                }
                if (data.moa_date) {
                    var d = new Date(data.moa_date);
                    if (!isNaN(d.getTime())) {
                        if (signedDay) signedDay.value = String(d.getDate()).padStart(2, '0');
                        if (signedMonth) signedMonth.value = d.toLocaleString('en-US', { month: 'long' });
                        if (signedYear) signedYear.value = String(d.getFullYear());
                    }
                }
                if (data.notary_day || data.notary_month || data.notary_year) {
                    if (notaryDay) notaryDay.value = (data.notary_day || '').toString();
                    if (notaryMonth) notaryMonth.value = (data.notary_month || '').toString();
                    if (notaryYear) notaryYear.value = (data.notary_year || '').toString();
                }
                if (data.acknowledgement_date) {
                    var ad = new Date(data.acknowledgement_date);
                    if (!isNaN(ad.getTime())) {
                        if (notaryDay) notaryDay.value = String(ad.getDate()).padStart(2, '0');
                        if (notaryMonth) notaryMonth.value = ad.toLocaleString('en-US', { month: 'long' });
                        if (notaryYear) notaryYear.value = String(ad.getFullYear());
                    }
                }
                if (notaryAppeared1) {
                    notaryAppeared1.value = (isDau ? (data.witness_partner || data.witness || '') : (data.witness || '')).toString();
                }

                updatePreview();
                captureDraftBaseline();
            })
            .catch(function () {});
    }

    function applyCompanyProfile(data) {
        if (!data || typeof data !== 'object') return;

        if (partnerName) partnerName.value = (data.company_name || data.name || '').toString();
        if (partnerAddress) partnerAddress.value = (data.company_address || data.address || '').toString();
        if (partnerRep) partnerRep.value = (data.partner_representative || data.contact_name || data.company_representative || data.supervisor_name || '').toString();
        if (partnerPosition) partnerPosition.value = (data.partner_position || data.contact_position || data.company_representative_position || data.supervisor_position || '').toString();

        if (companySelect && companySelect.length) {
            var optionLabel = (data.company_name || '').toString();
            var optionValue = (data.key || data.company_lookup_key || data.company_name || '').toString();
            if (optionValue) {
                var option = new Option(optionLabel || optionValue, optionValue, true, true);
                companySelect.empty().append(option).trigger('change.select2');
            }
        }

        updatePreview();
        captureDraftBaseline();
    }

    function applyCompanySearchItem(item) {
        if (!item || typeof item !== 'object') return;
        applyCompanyProfile({
            key: item.id || '',
            company_name: item.name || '',
            company_address: item.address || '',
            contact_name: item.contact_name || '',
            contact_position: item.contact_position || ''
        });
    }

    function loadCompanyProfile(companyKey) {
        if (!companyKey) return;
        fetch(pageUrl + '?action=get_company_profile&company=' + encodeURIComponent(companyKey), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                applyCompanyProfile(data);
            })
            .catch(function () {});
    }

    function prefillByStudentId(id) {
        if (!id || !select || !select.length) return;
        fetch(pageUrl + '?action=get_student&id=' + encodeURIComponent(id), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.id) return;
                var fullname = [data.first_name, data.middle_name, data.last_name].filter(Boolean).join(' ');
                var label = (fullname || 'Student') + ' - ' + (data.student_id || id);
                var option = new Option(label, String(id), true, true);
                select.append(option).trigger('change');
                loadMoaData(id);
            })
            .catch(function () {});
    }

    function initSelect() {
        if (!select || !select.length || typeof select.select2 !== 'function') return;
        select.select2({
            placeholder: select.attr('data-placeholder') || 'Search by name or student id',
            ajax: {
                url: pageUrl,
                dataType: 'json',
                delay: 250,
                data: function (params) { return { action: 'search_students', q: params.term }; },
                processResults: function (data) { return { results: data.results }; }
            },
            minimumInputLength: 1,
            width: 'resolve',
            dropdownParent: window.jQuery(document.body),
            dropdownCssClass: 'select2-dropdown biotern-doc-select2-dropdown'
        });

        select.on('select2:select', function () {
            var id = String(select.val() || '');
            if (!id) return;
            loadMoaData(id);
        });
    }

    function initCompanySelect() {
        if (!companySelect || !companySelect.length || typeof companySelect.select2 !== 'function') return;
        companySelect.select2({
            placeholder: companySelect.attr('data-placeholder') || 'Search company, address, or representative',
            ajax: {
                url: pageUrl,
                dataType: 'json',
                delay: 250,
                data: function (params) { return { action: 'search_companies', q: params.term }; },
                processResults: function (data) { return { results: data.results }; }
            },
            minimumInputLength: 1,
            width: 'resolve',
            dropdownParent: window.jQuery(document.body),
            dropdownCssClass: 'select2-dropdown biotern-doc-select2-dropdown'
        });

        companySelect.on('select2:select', function () {
            var companyKey = String(companySelect.val() || '');
            if (!companyKey) return;
            var picked = companySelect.select2('data') || [];
            applyCompanySearchItem(picked[0] || null);
            loadCompanyProfile(companyKey);
        });
    }

    function initTemplateEditor() {
        var legacyTemplate = getStorage(legacyStorageKey);
        if (!getStorage(templateStorageKey) && legacyTemplate) {
            setStorage(templateStorageKey, legacyTemplate);
        }

        // Clear stale failed-fetch placeholder from older runtime.
        var currentSaved = getStorage(templateStorageKey);
        if (/unable to load template/i.test(currentSaved)) {
            setStorage(templateStorageKey, '');
        }

        templateRuntime = window.AppCore.TemplateEditor.create({
            editorId: 'moa_content',
            statusId: 'msg',
            storageKey: templateStorageKey,
            loadMode: 'storage-or-default',
            resetMode: 'storage-or-default',
            defaultHtml: initialTemplateHtml,
            saveButtonId: 'btn_save',
            resetButtonId: 'btn_reset',
            preserveSelectionOnFormat: true,
            fontSizeMode: 'rich-span',
            onAfterLoad: function () {
                normalizeLoadedTemplateMarkup();
                ensureA4TemplateStructure();
                updatePreview();
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

    function printCurrent() {
        ensureA4TemplateStructure();
        updatePreview();
        if (!editor) {
            window.print();
            return;
        }

        var pagesHtml = Array.prototype.slice.call(editor.querySelectorAll('.a4-page')).map(function (page) {
            return page.outerHTML;
        }).join('');
        if (!pagesHtml) {
            pagesHtml = editor.innerHTML;
        }

        var styles = '';
        Array.prototype.slice.call(document.querySelectorAll('link[rel="stylesheet"], style')).forEach(function (node) {
            styles += node.outerHTML + '\n';
        });

        var printCss = [
            'html,body{background:#fff!important;margin:0!important;padding:0!important;width:210mm!important;min-height:297mm!important;}',
            '@page{size:A4 portrait;margin:0;}',
            '.no-print,.page-header,.nxl-navigation,.nxl-header{display:none!important;}',
            '#moa_content{display:block!important;margin:0!important;padding:0!important;background:#fff!important;width:210mm!important;max-width:210mm!important;}',
            '#moa_content.a4-pages-stack{display:block!important;gap:0!important;width:210mm!important;max-width:210mm!important;}',
            '#moa_content .a4-page{width:210mm!important;min-height:297mm!important;height:297mm!important;box-sizing:border-box!important;margin:0!important;padding:0.28in 0.42in 0.24in!important;background:#fff!important;box-shadow:none!important;page-break-after:always!important;break-after:page!important;overflow:hidden!important;}',
            '#moa_content .a4-page:first-child{padding:0.20in 0.38in 0.16in!important;}',
            '#moa_content .a4-page:last-child{page-break-after:auto!important;break-after:auto!important;}',
            '#moa_content,#moa_content *{font-family:"Arial Narrow",Arial,sans-serif!important;color:#000!important;}',
            '#moa_content{font-size:11.15pt!important;}',
            '#moa_content h5{font-size:12.0pt!important;margin:3px 0 7px!important;}',
            '#moa_content p,#moa_content li{font-size:11.15pt!important;line-height:1.26!important;margin-top:3px!important;margin-bottom:6px!important;}',
            '#moa_content .a4-page:first-child p,#moa_content .a4-page:first-child li{font-size:11.15pt!important;line-height:1.26!important;margin-top:3px!important;margin-bottom:6px!important;}',
            '#moa_content .a4-page:first-child ol{margin-top:8px!important;margin-bottom:8px!important;padding-left:0.27in!important;}',
            '#moa_content ol{margin-top:3px!important;margin-bottom:4px!important;padding-left:0.23in!important;}',
            '#moa_content li{margin-bottom:1px!important;}',
            '#moa_content .mt-12{margin-top:10px!important;}',
            '#moa_content .mt-16{margin-top:12px!important;}',
            '#moa_content .mt-24{margin-top:18px!important;}',
            '#moa_content .mt-40{margin-top:34px!important;}'
        ].join('');

        var printHtml =
            '<!doctype html><html><head><meta charset="utf-8">' +
            '<title>' + (isDau ? 'DAU Memorandum of Agreement' : 'Memorandum of Agreement') + '</title>' +
            '<base href="' + document.baseURI.replace(/"/g, '&quot;') + '">' +
            styles +
            '<style>' + printCss + '</style>' +
            '</head><body class="application-builder-page moa-builder-page"><div id="moa_content" class="a4-pages-stack">' + pagesHtml + '</div>' +
            '</body></html>';

        var printFrame = document.createElement('iframe');
        printFrame.setAttribute('aria-hidden', 'true');
        printFrame.style.position = 'fixed';
        printFrame.style.right = '0';
        printFrame.style.bottom = '0';
        printFrame.style.width = '0';
        printFrame.style.height = '0';
        printFrame.style.border = '0';
        printFrame.style.opacity = '0';

        var cleanupFrame = function () {
            setTimeout(function () {
                if (printFrame && printFrame.parentNode) {
                    printFrame.parentNode.removeChild(printFrame);
                }
            }, 1000);
        };

        printFrame.onload = function () {
            setTimeout(function () {
                var frameWindow = printFrame.contentWindow;
                if (!frameWindow) {
                    window.print();
                    cleanupFrame();
                    return;
                }
                frameWindow.focus();
                frameWindow.onafterprint = cleanupFrame;
                frameWindow.print();
                setTimeout(cleanupFrame, 2000);
            }, 250);
        };

        document.body.appendChild(printFrame);
        var frameDoc = printFrame.contentDocument || (printFrame.contentWindow && printFrame.contentWindow.document);
        if (!frameDoc) {
            window.print();
            cleanupFrame();
            return;
        }
        frameDoc.open();
        frameDoc.write(printHtml);
        frameDoc.close();
    }

    function initPrintButtons() {
        if (btnPrint) {
            btnPrint.addEventListener('click', function (event) {
                if (event && typeof event.preventDefault === 'function') {
                    event.preventDefault();
                }
                printCurrent();
            });
        }
        if (btnPrintForm) {
            btnPrintForm.addEventListener('click', function (event) {
                if (event && typeof event.preventDefault === 'function') {
                    event.preventDefault();
                }
                printCurrent();
            });
        }
    }

    function initNativePrintCleanup() {
        var nativePrintContainer = null;

        function cleanupNativePrintClone() {
            document.body.classList.remove('native-moa-printing');
            if (nativePrintContainer && nativePrintContainer.parentNode) {
                nativePrintContainer.parentNode.removeChild(nativePrintContainer);
            }
            nativePrintContainer = null;
        }

        window.addEventListener('beforeprint', function () {
            ensureA4TemplateStructure();
            updatePreview();

            cleanupNativePrintClone();
            var pagesHtml = Array.prototype.slice.call(editor.querySelectorAll('.a4-page')).map(function (page) {
                return page.outerHTML;
            }).join('');
            if (!pagesHtml) {
                return;
            }

            nativePrintContainer = document.createElement('div');
            nativePrintContainer.id = 'native_moa_print_content';
            nativePrintContainer.className = 'a4-pages-stack';
            nativePrintContainer.innerHTML = pagesHtml;
            document.body.appendChild(nativePrintContainer);
            document.body.classList.add('native-moa-printing');
        });

        window.addEventListener('afterprint', function () {
            cleanupNativePrintClone();
        });
    }

    function bindFieldInputs() {
        [
            partnerName, partnerRep, partnerPosition, partnerAddress, companyReceipt, totalHours, schoolRep, schoolPosition,
            signedAt, signedDay, signedMonth, signedYear,
            presencePartnerRep, presenceSchoolAdmin, presenceSchoolAdminPosition,
            notaryCity, notaryAppeared1, notaryAppeared2,
            notaryDay, notaryMonth, notaryYear, notaryPlace,
            docNo, pageNo, bookNo, seriesNo
        ].forEach(function (el) {
            if (!el) {
                return;
            }
            el.addEventListener('input', function () {
                updatePreview();
                updateDraftState();
            });
        });
    }

    initTemplateEditor().then(function () {
        initSelect();
        initCompanySelect();
        initDraftGuard();
        bindFieldInputs();
        initEditToggle();
        initPrintButtons();
        initNativePrintCleanup();
        setEditMode(false);

        clearFormFields();
        ensureA4TemplateStructure();
        updatePreview();
        captureDraftBaseline();

        var prefillPromise = Promise.resolve();
        if (prefillStudentId > 0) {
            prefillPromise = prefillByStudentId(prefillStudentId) || Promise.resolve();
        }
        prefillPromise.finally(function () {
            if (prefillCompanyKey) {
                loadCompanyProfile(prefillCompanyKey);
            }
        });
    }).catch(function () {
        setStatus('Template bootstrap recovered.');
        initSelect();
        initCompanySelect();
        initDraftGuard();
        bindFieldInputs();
        initEditToggle();
        initPrintButtons();
        initNativePrintCleanup();
        setEditMode(false);
        clearFormFields();
        ensureA4TemplateStructure();
        updatePreview();
        captureDraftBaseline();
        if (prefillCompanyKey) {
            loadCompanyProfile(prefillCompanyKey);
        }
    });
})();
