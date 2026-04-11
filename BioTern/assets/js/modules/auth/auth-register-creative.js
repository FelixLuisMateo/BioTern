let currentRole = null;
const STUDENT_ONLY_REGISTRATION = true;

function parseJSONDataset(el, key, fallback) {
    if (!el) return fallback;
    const raw = el.dataset ? el.dataset[key] : "";
    if (!raw) return fallback;
    try {
        return JSON.parse(raw);
    } catch (err) {
        return fallback;
    }
}

const registerDataEl = document.getElementById("registerData");
const courseDepartmentMap = parseJSONDataset(registerDataEl, "courseMap", {});
const sectionRecords = parseJSONDataset(registerDataEl, "sectionRecords", []);
const studentDraftStorageKey = 'biotern.studentDraft.v1.' + window.location.pathname;

function escapeSelector(value) {
    if (window.CSS && typeof window.CSS.escape === 'function') {
        return window.CSS.escape(value);
    }
    return String(value).replace(/([ !"#$%&'()*+,./:;<=>?@[\\\]^`{|}~])/g, '\\$1');
}

function parseUsDateInput(value) {
    const raw = String(value || '').trim();
    const match = raw.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
    if (!match) return null;

    const month = Number(match[1]);
    const day = Number(match[2]);
    const year = Number(match[3]);
    if (month < 1 || month > 12 || day < 1 || year < 1900) return null;

    const candidate = new Date(year, month - 1, day);
    if (
        candidate.getFullYear() !== year ||
        candidate.getMonth() !== (month - 1) ||
        candidate.getDate() !== day
    ) {
        return null;
    }
    return candidate;
}

function parseDateInputValue(value) {
    const raw = String(value || '').trim();
    if (raw === '') return null;

    if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
        const candidate = new Date(raw + 'T00:00:00');
        if (!Number.isNaN(candidate.getTime())) {
            return candidate;
        }
    }

    return parseUsDateInput(raw);
}

function validateDobAgeField(field) {
    if (!field) return true;
    const raw = String(field.value || '').trim();
    if (raw === '') {
        field.setCustomValidity('This field is required.');
        return false;
    }

    const parsed = parseDateInputValue(raw);
    if (!parsed) {
        field.setCustomValidity('Please enter a valid date of birth.');
        return false;
    }

    const today = new Date();
    let age = today.getFullYear() - parsed.getFullYear();
    const monthDiff = today.getMonth() - parsed.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < parsed.getDate())) {
        age -= 1;
    }

    if (age < 17) {
        field.setCustomValidity('You must be at least 17 years old to apply.');
        return false;
    }

    field.setCustomValidity('');
    return true;
}

function normalizeStudentId(value) {
    const digits = String(value || '').replace(/\D/g, '');
    let body = digits;
    if (body.startsWith('05')) {
        body = body.substring(2);
    }
    body = body.substring(0, 5);
    return '05-' + body;
}

function setupFloatingTextFields() {
    const form = document.getElementById('studentForm');
    if (!form) return;

    const fields = Array.prototype.slice.call(
        form.querySelectorAll('input.form-control, textarea.form-control')
    );

    fields.forEach(function(field) {
        if (!field || field.dataset.floatingBound === '1') return;
        if (field.dataset.noFloating === '1') return;
        if (field.closest('.input-group')) return;

        const type = String(field.type || '').toLowerCase();
        if (type === 'hidden' || type === 'checkbox' || type === 'radio' || type === 'file') return;
        if (field.hasAttribute('readonly')) return;

        const originalPlaceholder = String(field.getAttribute('placeholder') || '').trim();
        const explicitLabel = field.id
            ? form.querySelector('label[for="' + escapeSelector(field.id) + '"]')
            : null;
        const explicitLabelText = explicitLabel ? String(explicitLabel.textContent || '').trim() : '';
        let floatingLabelText = explicitLabelText || originalPlaceholder;

        if (field.id === 'studentDateOfBirth') {
            floatingLabelText = 'Date of Birth (mm/dd/yyyy)';
        }
        if (field.id === 'studentStreetAddress') {
            floatingLabelText = 'Current Address';
        }

        if (!floatingLabelText) return;

        const parent = field.parentElement;
        if (!parent) return;

        const wrapper = document.createElement('div');
        wrapper.className = 'floating-field';

        parent.insertBefore(wrapper, field);
        wrapper.appendChild(field);

        let label = explicitLabel;
        if (label) {
            label.classList.remove('form-label', 'fs-12', 'mb-1', 'mb-2', 'mb-3');
            label.classList.add('floating-field-label');
            label.textContent = floatingLabelText;
            wrapper.appendChild(label);
        } else {
            label = document.createElement('label');
            label.className = 'floating-field-label';
            label.textContent = floatingLabelText;
            if (field.id) {
                label.setAttribute('for', field.id);
            }
            wrapper.appendChild(label);
        }

        field.setAttribute('placeholder', ' ');

        const syncState = function() {
            wrapper.classList.toggle('has-value', String(field.value || '').trim() !== '');
        };

        field.addEventListener('input', syncState);
        field.addEventListener('change', syncState);
        field.addEventListener('blur', syncState);
        field.addEventListener('focus', syncState);
        field.addEventListener('animationstart', syncState);
        syncState();

        // Browsers/password managers may inject values after DOMContentLoaded without firing input.
        setTimeout(syncState, 80);
        setTimeout(syncState, 300);
        setTimeout(syncState, 900);
        window.addEventListener('pageshow', syncState);

        field.dataset.floatingBound = '1';
    });
}

        function selectRole(role) {
            if (STUDENT_ONLY_REGISTRATION) {
                role = 'student';
            }
            currentRole = role;
            const roleSelection = document.getElementById('roleSelectionScreen');
            const loginLink = document.getElementById('loginLink');
            const loginLinkHidden = document.getElementById('loginLinkHidden');

            const forms = { 'student': 'studentForm' };

            // Hide role selection and login hint (guarded)
            if (roleSelection) {
                roleSelection.classList.add('hide-form');
                roleSelection.classList.remove('show-form');
            }
            if (loginLink) {
                loginLink.classList.add('hide-form');
                loginLink.classList.remove('show-form');
            }

            // Hide all forms then show the selected one
            Object.values(forms).forEach(formId => {
                const form = document.getElementById(formId);
                if (form) {
                    form.classList.add('hide-form');
                    form.classList.remove('show-form');
                }
            });

            const selectedForm = document.getElementById(forms[role]);
            if (selectedForm) {
                selectedForm.classList.add('show-form');
                selectedForm.classList.remove('hide-form');
                selectedForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
                initFormStepper(selectedForm.id);
                if (typeof selectedForm._showStep === 'function') {
                    selectedForm._showStep(1);
                }
            }

            if (loginLinkHidden) {
                loginLinkHidden.classList.remove('hide-form');
                loginLinkHidden.classList.add('show-form');
            }
        }

        function backToRoles() {
            if (STUDENT_ONLY_REGISTRATION) {
                selectRole('student');
                return;
            }
            const roleSelection = document.getElementById('roleSelectionScreen');
            const loginLink = document.getElementById('loginLink');
            const loginLinkHidden = document.getElementById('loginLinkHidden');
            const forms = {
                'student': 'studentForm'
            };

            // Hide forms
            Object.values(forms).forEach(formId => {
                const form = document.getElementById(formId);
                if (form) {
                    form.classList.add('hide-form');
                    form.classList.remove('show-form');
                }
            });

            // Show role selection and login hint (guarded)
            if (roleSelection) {
                roleSelection.classList.remove('hide-form');
                roleSelection.classList.add('show-form');
                roleSelection.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
            if (loginLink) {
                loginLink.classList.remove('hide-form');
                loginLink.classList.add('show-form');
            }
            if (loginLinkHidden) {
                loginLinkHidden.classList.add('hide-form');
                loginLinkHidden.classList.remove('show-form');
            }

            currentRole = null;
        }

        window.selectRole = selectRole;
        window.backToRoles = backToRoles;

        function enforceStudentOnlyForms() {
            if (!STUDENT_ONLY_REGISTRATION) return;
            const studentForm = document.getElementById('studentForm');
            if (studentForm) {
                studentForm.classList.add('show-form');
                studentForm.classList.remove('hide-form');
            }
        }

        function initFormStepper(formId) {
            const form = document.getElementById(formId);
            if (!form || form.dataset.stepperInited === '1') return;

            const panels = Array.prototype.slice.call(form.querySelectorAll('.step-panel'));
            if (!panels.length) return;
            const stepper = form.querySelector('.form-stepper');
            const dots = stepper ? Array.prototype.slice.call(stepper.querySelectorAll('.step-dot')) : [];
            const total = panels.length;
            let current = 1;

            function showStep(step) {
                current = Math.min(Math.max(step, 1), total);
                panels.forEach(panel => {
                    const panelStep = Number(panel.dataset.step || 0);
                    panel.classList.toggle('active', panelStep === current);
                });
                dots.forEach(dot => {
                    const dotStep = Number(dot.dataset.step || 0);
                    dot.classList.toggle('active', dotStep === current);
                    dot.classList.toggle('done', dotStep < current);
                });
                if (stepper) {
                    const labelEl = stepper.querySelector('.stepper-label');
                    const countEl = stepper.querySelector('.stepper-count');
                    if (labelEl) {
                        const activeDot = stepper.querySelector('.step-dot[data-step="' + current + '"]');
                        labelEl.textContent = activeDot && activeDot.dataset.label ? activeDot.dataset.label : ('Step ' + current);
                    }
                    if (countEl) {
                        countEl.textContent = 'Step ' + current + ' of ' + total;
                    }
                }
            }

            form.querySelectorAll('[data-step-action]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const action = btn.getAttribute('data-step-action');
                    if (action === 'next') {
                        const activePanel = panels.find(panel => panel.classList.contains('active'));
                        if (activePanel) {
                            const requiredFields = Array.prototype.slice.call(activePanel.querySelectorAll('input, select, textarea'));

                            const markInvalid = (field, msg) => {
                                field.classList.add('is-invalid');
                                const group = field.closest('.input-group');
                                const anchor = group || field;
                                let feedback = anchor.parentElement ? anchor.parentElement.querySelector('.invalid-feedback') : null;
                                if (!feedback) {
                                    feedback = document.createElement('div');
                                    feedback.className = 'invalid-feedback d-block';
                                    if (anchor.parentElement) anchor.parentElement.appendChild(feedback);
                                }
                                feedback.textContent = msg;
                            };

                            const clearInvalid = (field) => {
                                field.classList.remove('is-invalid');
                                const group = field.closest('.input-group');
                                const anchor = group || field;
                                const feedback = anchor.parentElement ? anchor.parentElement.querySelector('.invalid-feedback') : null;
                                if (feedback) feedback.remove();
                            };

                            let hasInvalid = false;
                            for (let i = 0; i < requiredFields.length; i++) {
                                const field = requiredFields[i];
                                if (field.disabled || !field.required) continue;
                                if (field.tagName === 'INPUT' && String(field.type || '').toLowerCase() === 'hidden') continue;
                                if (!field.checkValidity()) {
                                    hasInvalid = true;
                                    let msg = 'Please check this field.';
                                    if (field.validity) {
                                        if (field.validity.valueMissing) {
                                            msg = field.tagName === 'SELECT' ? 'Please select an item in the list.' : 'This field is required.';
                                        } else if (field.validity.typeMismatch) {
                                            msg = 'Please enter a valid value.';
                                        } else if (field.validity.patternMismatch) {
                                            msg = field.getAttribute('title') || 'Invalid format.';
                                        } else if (field.validity.tooShort || field.validity.tooLong) {
                                            msg = field.validationMessage || 'Please check the required length.';
                                        } else {
                                            msg = field.validationMessage || msg;
                                        }
                                    }
                                    markInvalid(field, msg);
                                } else {
                                    clearInvalid(field);
                                }
                            }
                            if (hasInvalid) return;
                        }
                        showStep(current + 1);
                    } else if (action === 'prev') {
                        showStep(current - 1);
                    }
                });
            });

            form._showStep = showStep;
            form.dataset.stepperInited = '1';
            showStep(1);

            form.addEventListener('input', function (e) {
                const field = e.target;
                if (!field || !field.classList || !field.classList.contains('is-invalid')) return;
                field.classList.remove('is-invalid');
                const group = field.closest('.input-group');
                const anchor = group || field;
                const feedback = anchor.parentElement ? anchor.parentElement.querySelector('.invalid-feedback') : null;
                if (feedback) feedback.remove();
            });
            form.addEventListener('change', function (e) {
                const field = e.target;
                if (!field || !field.classList || !field.classList.contains('is-invalid')) return;
                field.classList.remove('is-invalid');
                const group = field.closest('.input-group');
                const anchor = group || field;
                const feedback = anchor.parentElement ? anchor.parentElement.querySelector('.invalid-feedback') : null;
                if (feedback) feedback.remove();
            });
        }

        // Validate password matches confirm password for all forms
        function validatePasswordMatch(e) {
            const form = e.target;
            const password = form.querySelector('input[name="password"]');
            const confirmPassword = form.querySelector('input[name="confirm_password"]');
            
            if (password && confirmPassword) {
                if (password.value !== confirmPassword.value) {
                    e.preventDefault();
                    alert('Passwords do not match. Please check and try again.');
                    return false;
                }
            }
            return true;
        }

        // Attach validation to all forms when page loads
        document.addEventListener('DOMContentLoaded', function() {
            const formIds = ['studentForm'];
            formIds.forEach(formId => {
                const form = document.getElementById(formId);
                if (form) {
                    form.addEventListener('submit', validatePasswordMatch);
                }
            });
            
            // Setup password visibility toggle
            setupPasswordToggle();
            setupStudentHoursControls();
            setupAcademicFilters();
            setupStudentPhilippineAddress();
            setupFloatingTextFields();
            setupStudentDraftPersistence();
            setupStudentFinalReview();
            initFormStepper('studentForm');
            enforceStudentOnlyForms();
            if (STUDENT_ONLY_REGISTRATION) {
                selectRole('student');
            }

            const studentIdInput = document.querySelector('#studentForm input[name="student_id"]');
            if (studentIdInput) {
                const studentIdPattern = /^05-[0-9]{4,5}$/;

                if (!String(studentIdInput.value || '').trim()) {
                    studentIdInput.value = '05-';
                } else {
                    studentIdInput.value = normalizeStudentId(studentIdInput.value);
                }

                studentIdInput.addEventListener('focus', function() {
                    if (!String(this.value || '').startsWith('05-')) {
                        this.value = normalizeStudentId(this.value);
                    }
                });

                studentIdInput.addEventListener('keydown', function(e) {
                    const start = this.selectionStart || 0;
                    const end = this.selectionEnd || 0;
                    const blockedForPrefix = (e.key === 'Backspace' && start <= 3 && end <= 3)
                        || (e.key === 'Delete' && start < 3);
                    if (blockedForPrefix) {
                        e.preventDefault();
                    }
                });

                studentIdInput.addEventListener('input', function() {
                    this.value = normalizeStudentId(this.value);
                    if (studentIdPattern.test(this.value)) {
                        this.setCustomValidity('');
                    } else {
                        this.setCustomValidity('Use format 05-1234 or 05-12345');
                    }
                });

                studentIdInput.addEventListener('blur', function() {
                    this.value = normalizeStudentId(this.value);
                });
            }

            const dobInput = document.getElementById('studentDateOfBirth');
            if (dobInput) {
                dobInput.addEventListener('input', function() {
                    validateDobAgeField(this);
                });

                dobInput.addEventListener('blur', function() {
                    validateDobAgeField(this);
                });
            }

            const requestedRole = new URLSearchParams(window.location.search).get('role');
            if (requestedRole && requestedRole.toLowerCase() === 'student') {
                selectRole('student');
            }

            const params = new URLSearchParams(window.location.search);
            if (params.get('registered')) {
                clearStudentDraft();
                const studentForm = document.getElementById('studentForm');
                if (studentForm) {
                    studentForm.reset();
                    setupAcademicFilters();
                    setupStudentPhilippineAddress();
                    if (typeof studentForm._showStep === 'function') {
                        studentForm._showStep(1);
                    }
                }
            }

        });

        function setupStudentHoursControls() {
            const finishedSelect = document.getElementById('finishedInternalSelect');
            const externalInput = document.getElementById('externalTotalHoursInput');
            const internalInput = document.querySelector('#studentForm input[name="internal_total_hours"]');
            if (!finishedSelect || !externalInput || !internalInput) return;

            function syncExternalField() {
                if ((internalInput.value || '').trim() === '') {
                    internalInput.value = '140';
                }
                if ((externalInput.value || '').trim() === '') {
                    externalInput.value = '250';
                }
                internalInput.disabled = false;
                externalInput.disabled = false;
            }

            finishedSelect.addEventListener('change', syncExternalField);
            syncExternalField();
        }

        function getStudentDraft() {
            try {
                const raw = localStorage.getItem(studentDraftStorageKey);
                if (!raw) return null;
                const parsed = JSON.parse(raw);
                if (!parsed || typeof parsed !== 'object') return null;
                return parsed;
            } catch (err) {
                return null;
            }
        }

        function clearStudentDraft() {
            try {
                localStorage.removeItem(studentDraftStorageKey);
            } catch (err) {
                // ignore localStorage errors
            }
        }

        function saveStudentDraft() {
            const form = document.getElementById('studentForm');
            if (!form) return;
            const values = {};
            const fields = Array.prototype.slice.call(form.querySelectorAll('input, select, textarea'));
            fields.forEach(function(field) {
                if (!field) return;
                const fieldName = String(field.name || '').trim();
                const fieldId = String(field.id || '').trim();
                const key = fieldName !== '' ? ('name:' + fieldName) : (fieldId !== '' ? ('id:' + fieldId) : '');
                if (!key) return;
                if (field.type === 'password') return;
                if (field.type === 'checkbox' || field.type === 'radio') {
                    values[key] = !!field.checked;
                } else {
                    values[key] = field.value;
                }
            });

            const activePanel = form.querySelector('.step-panel.active');
            const activeStep = activePanel ? Number(activePanel.getAttribute('data-step') || 1) : 1;
            const payload = {
                values: values,
                activeStep: activeStep,
                updatedAt: Date.now()
            };

            try {
                localStorage.setItem(studentDraftStorageKey, JSON.stringify(payload));
            } catch (err) {
                // ignore localStorage errors
            }
        }

        function restoreStudentDraft() {
            const form = document.getElementById('studentForm');
            const draft = getStudentDraft();
            if (!form || !draft || !draft.values) return;

            Object.keys(draft.values).forEach(function(key) {
                if (key.indexOf('id:studentProvinceSelect') === 0 || key.indexOf('id:studentCitySelect') === 0 || key.indexOf('id:studentBarangaySelect') === 0) {
                    return;
                }

                let field = null;
                if (key.indexOf('name:') === 0) {
                    const fieldName = key.substring(5);
                    field = form.querySelector('[name="' + escapeSelector(fieldName) + '"]');
                } else if (key.indexOf('id:') === 0) {
                    const fieldId = key.substring(3);
                    field = document.getElementById(fieldId);
                }
                if (!field) return;

                if (field.type === 'checkbox' || field.type === 'radio') {
                    field.checked = !!draft.values[key];
                } else {
                    const value = draft.values[key];
                    if (value !== null && value !== undefined) {
                        field.value = String(value);
                    }
                }
            });

            if (typeof form._showStep === 'function' && Number(draft.activeStep) > 0) {
                form._showStep(Number(draft.activeStep));
            }
        }

        function setupStudentDraftPersistence() {
            const form = document.getElementById('studentForm');
            if (!form || form.dataset.draftBound === '1') {
                restoreStudentDraft();
                return;
            }

            form.addEventListener('input', saveStudentDraft);
            form.addEventListener('change', saveStudentDraft);
            form.addEventListener('click', function(e) {
                const target = e.target;
                if (target && target.matches('[data-step-action]')) {
                    setTimeout(saveStudentDraft, 0);
                }
            });

            form.dataset.draftBound = '1';
            restoreStudentDraft();
            saveStudentDraft();
        }

        function setupStudentFinalReview() {
            const form = document.getElementById('studentForm');
            const reviewModalEl = document.getElementById('studentReviewModal');
            const reviewBody = document.getElementById('studentReviewContent');
            const confirmBtn = document.getElementById('studentConfirmSubmitBtn');
            const applyBtn = document.getElementById('studentApplyBtn');
            if (!form || !reviewModalEl || !reviewBody || !confirmBtn || !applyBtn || typeof bootstrap === 'undefined') return;
            if (form.dataset.reviewBound === '1') return;

            const reviewModal = new bootstrap.Modal(reviewModalEl);
            let confirmed = false;

            function getFieldDisplay(name, fallback) {
                const fieldSelector = '[name="' + escapeSelector(name) + '"]';
                const resolvedField = form.querySelector(fieldSelector);
                if (!resolvedField) return fallback || '-';
                if (resolvedField.tagName === 'SELECT') {
                    const opt = resolvedField.options[resolvedField.selectedIndex];
                    if (!opt || !opt.value) return fallback || '-';
                    return String(opt.textContent || '').trim() || fallback || '-';
                }
                const value = String(resolvedField.value || '').trim();
                return value !== '' ? value : (fallback || '-');
            }

            function renderReview() {
                const rows = [
                    ['Student ID', getFieldDisplay('student_id')],
                    ['First Name', getFieldDisplay('first_name')],
                    ['Middle Name', getFieldDisplay('middle_name', '-')],
                    ['Last Name', getFieldDisplay('last_name')],
                    ['Address', String((document.getElementById('studentAddress') || {}).value || '-').trim() || '-'],
                    ['Course', getFieldDisplay('course_id')],
                    ['Department', getFieldDisplay('department_id')],
                    ['Section', getFieldDisplay('section')],
                    ['School Year', getFieldDisplay('school_year')],
                    ['Semester', getFieldDisplay('semester')],
                    ['Coordinator', getFieldDisplay('coordinator_id')],
                    ['Supervisor', getFieldDisplay('supervisor_id')],
                    ['Phone', getFieldDisplay('phone')],
                    ['Date of Birth', getFieldDisplay('date_of_birth')],
                    ['Gender', getFieldDisplay('gender')],
                    ['Emergency Contact', getFieldDisplay('emergency_contact')],
                    ['Emergency Contact Phone', getFieldDisplay('emergency_contact_phone')],
                    ['Username', getFieldDisplay('username')],
                    ['Account Email', getFieldDisplay('account_email')]
                ];

                let html = '<div class="table-responsive"><table class="table table-sm align-middle mb-0">';
                rows.forEach(function(row) {
                    html += '<tr><th class="text-muted" style="width: 42%;">' + row[0] + '</th><td>' + row[1] + '</td></tr>';
                });
                html += '</table></div>';
                reviewBody.innerHTML = html;
            }

            function openReviewModal() {
                const dobField = document.getElementById('studentDateOfBirth');
                if (dobField && !validateDobAgeField(dobField)) {
                    form.reportValidity();
                    return;
                }
                if (!form.checkValidity()) {
                    form.reportValidity();
                    return;
                }
                renderReview();
                reviewModal.show();
            }

            applyBtn.addEventListener('click', function(e) {
                e.preventDefault();
                openReviewModal();
            });

            form.addEventListener('submit', function(e) {
                if (confirmed) {
                    confirmed = false;
                    return;
                }
                e.preventDefault();
                openReviewModal();
            });

            confirmBtn.addEventListener('click', function() {
                confirmed = true;
                saveStudentDraft();
                reviewModal.hide();
                if (form.requestSubmit) {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
            });

            form.dataset.reviewBound = '1';
        }

        function setSelectLoading(selectEl, label) {
            if (!selectEl) return;
            selectEl.innerHTML = '';
            const opt = document.createElement('option');
            opt.value = '';
            opt.textContent = label;
            opt.disabled = true;
            opt.selected = true;
            selectEl.appendChild(opt);
        }

        function fillSelectOptions(selectEl, placeholder, records, codeKey, nameKey) {
            if (!selectEl) return;
            selectEl.innerHTML = '';
            const placeholderOption = document.createElement('option');
            placeholderOption.value = '';
            placeholderOption.textContent = placeholder;
            placeholderOption.disabled = true;
            placeholderOption.selected = true;
            selectEl.appendChild(placeholderOption);

            records.forEach(function(rec) {
                const code = String(rec[codeKey] || '').trim();
                const name = String(rec[nameKey] || '').trim();
                if (code === '' || name === '') return;
                const option = document.createElement('option');
                option.value = code;
                option.textContent = name;
                option.setAttribute('data-name', name);
                selectEl.appendChild(option);
            });
        }

        function setupStudentPhilippineAddress() {
            const provinceSelect = document.getElementById('studentProvinceSelect');
            const citySelect = document.getElementById('studentCitySelect');
            const barangaySelect = document.getElementById('studentBarangaySelect');
            const streetInput = document.getElementById('studentStreetAddress');
            const addressInput = document.getElementById('studentAddress');
            if (!provinceSelect || !citySelect || !barangaySelect || !addressInput) return;
            if (provinceSelect.dataset.addressBound === '1') {
                updateComposedAddress();
                return;
            }

            const psgcBase = 'https://psgc.gitlab.io/api';

            function selectedName(selectEl) {
                if (!selectEl) return '';
                const opt = selectEl.options[selectEl.selectedIndex];
                if (!opt || !opt.value) return '';
                return String(opt.getAttribute('data-name') || opt.textContent || '').trim();
            }

            function updateComposedAddress() {
                const province = selectedName(provinceSelect);
                const city = selectedName(citySelect);
                const barangay = selectedName(barangaySelect);
                const street = streetInput ? String(streetInput.value || '').trim() : '';
                const segments = [];
                if (street !== '') segments.push(street);
                if (barangay !== '') segments.push(barangay);
                if (city !== '') segments.push(city);
                if (province !== '') segments.push(province);
                addressInput.value = segments.join(', ');
            }

            function fetchList(endpoint) {
                return fetch(psgcBase + endpoint)
                    .then(function(response) {
                        if (!response.ok) {
                            throw new Error('Request failed with status ' + response.status);
                        }
                        return response.json();
                    });
            }

            const draft = getStudentDraft();
            const draftValues = draft && draft.values ? draft.values : {};
            const draftProvinceCode = String(draftValues['id:studentProvinceSelect'] || '');
            const draftCityCode = String(draftValues['id:studentCitySelect'] || '');
            const draftBarangayCode = String(draftValues['id:studentBarangaySelect'] || '');

            function loadProvinces() {
                provinceSelect.disabled = true;
                citySelect.disabled = true;
                barangaySelect.disabled = true;
                setSelectLoading(provinceSelect, 'Loading provinces...');
                setSelectLoading(citySelect, 'Select City / Municipality');
                setSelectLoading(barangaySelect, 'Select Barangay');

                return fetchList('/provinces/')
                    .then(function(records) {
                        const sorted = (Array.isArray(records) ? records : []).slice().sort(function(a, b) {
                            return String(a.name || '').localeCompare(String(b.name || ''));
                        });
                        fillSelectOptions(provinceSelect, 'Select Province', sorted, 'code', 'name');
                        provinceSelect.disabled = false;
                    })
                    .catch(function() {
                        setSelectLoading(provinceSelect, 'Unable to load provinces');
                    });
            }

            function loadCities(provinceCode) {
                citySelect.disabled = true;
                barangaySelect.disabled = true;
                setSelectLoading(citySelect, 'Loading cities/municipalities...');
                setSelectLoading(barangaySelect, 'Select Barangay');
                if (!provinceCode) {
                    setSelectLoading(citySelect, 'Select City / Municipality');
                    return Promise.resolve();
                }

                return fetchList('/provinces/' + encodeURIComponent(provinceCode) + '/cities-municipalities/')
                    .then(function(records) {
                        const sorted = (Array.isArray(records) ? records : []).slice().sort(function(a, b) {
                            return String(a.name || '').localeCompare(String(b.name || ''));
                        });
                        fillSelectOptions(citySelect, 'Select City / Municipality', sorted, 'code', 'name');
                        citySelect.disabled = false;
                    })
                    .catch(function() {
                        setSelectLoading(citySelect, 'Unable to load cities/municipalities');
                    });
            }

            function loadBarangays(cityCode) {
                barangaySelect.disabled = true;
                setSelectLoading(barangaySelect, 'Loading barangays...');
                if (!cityCode) {
                    setSelectLoading(barangaySelect, 'Select Barangay');
                    return Promise.resolve();
                }

                return fetchList('/cities-municipalities/' + encodeURIComponent(cityCode) + '/barangays/')
                    .then(function(records) {
                        const sorted = (Array.isArray(records) ? records : []).slice().sort(function(a, b) {
                            return String(a.name || '').localeCompare(String(b.name || ''));
                        });
                        fillSelectOptions(barangaySelect, 'Select Barangay', sorted, 'code', 'name');
                        barangaySelect.disabled = false;
                    })
                    .catch(function() {
                        setSelectLoading(barangaySelect, 'Unable to load barangays');
                    });
            }

            provinceSelect.addEventListener('change', function() {
                loadCities(provinceSelect.value);
                updateComposedAddress();
            });
            citySelect.addEventListener('change', function() {
                loadBarangays(citySelect.value);
                updateComposedAddress();
            });
            barangaySelect.addEventListener('change', updateComposedAddress);
            if (streetInput) {
                streetInput.addEventListener('input', updateComposedAddress);
            }

            provinceSelect.dataset.addressBound = '1';
            loadProvinces().then(function() {
                if (draftProvinceCode && provinceSelect.querySelector('option[value="' + escapeSelector(draftProvinceCode) + '"]')) {
                    provinceSelect.value = draftProvinceCode;
                    return loadCities(draftProvinceCode).then(function() {
                        if (draftCityCode && citySelect.querySelector('option[value="' + escapeSelector(draftCityCode) + '"]')) {
                            citySelect.value = draftCityCode;
                            return loadBarangays(draftCityCode).then(function() {
                                if (draftBarangayCode && barangaySelect.querySelector('option[value="' + escapeSelector(draftBarangayCode) + '"]')) {
                                    barangaySelect.value = draftBarangayCode;
                                }
                            });
                        }
                        return Promise.resolve();
                    });
                }
                return Promise.resolve();
            }).then(function() {
                updateComposedAddress();
            });
        }

        function getCourseAllowedDepartmentIds(courseId) {
            const bucket = courseDepartmentMap[String(courseId)] || {};
            const ids = Object.keys(bucket).map(function(id) { return String(id); });
            // Fallback: if no course->department mapping exists yet, keep all departments visible.
            if (!ids.length) {
                const deptSelect = document.getElementById('studentDepartmentSelect');
                if (!deptSelect || typeof deptSelect.options === 'undefined') return [];
                return Array.prototype.slice.call(deptSelect.options)
                    .filter(function(opt, idx) { return idx > 0 && String(opt.value || '').trim() !== ''; })
                    .map(function(opt) { return String(opt.value); });
            }
            return ids;
        }

        function setSelectPlaceholder(selectEl, text) {
            if (!selectEl) return;
            selectEl.innerHTML = '';
            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.disabled = true;
            placeholder.selected = true;
            placeholder.textContent = text;
            selectEl.appendChild(placeholder);
        }

        function filterDepartmentOptions(courseId) {
            const deptSelect = document.getElementById('studentDepartmentSelect');
            const allowedDepartmentIds = getCourseAllowedDepartmentIds(courseId);
            if (!deptSelect) return allowedDepartmentIds;
            if (typeof deptSelect.options === 'undefined') {
                if (allowedDepartmentIds.length === 1) {
                    deptSelect.value = allowedDepartmentIds[0];
                } else {
                    deptSelect.value = '';
                }
                return allowedDepartmentIds;
            }
            const selectedBefore = deptSelect.value;
            const allowedSet = new Set(allowedDepartmentIds.map(function(id) { return String(id); }));
            const allDepartmentIds = [];

            Array.prototype.slice.call(deptSelect.options).forEach(function(opt, index) {
                if (index === 0) return; // placeholder
                if (String(opt.value) === '0') {
                    opt.hidden = false;
                    opt.disabled = false;
                    return;
                }
                const deptId = String(opt.value);
                const show = allowedSet.size === 0 ? true : allowedSet.has(deptId);
                opt.hidden = !show;
                opt.disabled = !show;
                if (show) {
                    allDepartmentIds.push(deptId);
                }
            });

            if (selectedBefore !== '' && allDepartmentIds.indexOf(String(selectedBefore)) !== -1) {
                deptSelect.value = selectedBefore;
            } else if (allDepartmentIds.length === 1) {
                deptSelect.value = allDepartmentIds[0];
            } else {
                deptSelect.value = '';
            }

            return allDepartmentIds;
        }

        function filterSectionOptions(courseId, departmentId) {
            const sectionSelect = document.getElementById('studentSectionSelect');
            if (!sectionSelect) return;
            sectionSelect.innerHTML = '';
            const placeholderOption = document.createElement('option');
            placeholderOption.value = '';
            placeholderOption.textContent = 'Select Section';
            placeholderOption.selected = true;
            sectionSelect.appendChild(placeholderOption);

            const cId = String(courseId || '');
            const dId = String(departmentId || '');
            let inserted = 0;

            sectionRecords.forEach(function(rec) {
                const matchesCourse = (cId === '') || (String(rec.course_id) === cId);
                const matchesDept = (dId === '' || dId === '0') || (String(rec.department_id) === dId);
                if (!matchesCourse || !matchesDept) return;

                const code = (rec.code || '').trim();
                const name = (rec.name || '').trim();
                const formattedCode = code.replace(/\s*-\s*/g, ' - ');
                const formattedName = name.replace(/\s*-\s*/g, ' - ');
                const label = code && name
                    ? (code.toLowerCase() === name.toLowerCase()
                        ? formattedCode
                        : (formattedCode + ' - ' + formattedName))
                    : (formattedCode || formattedName || ('Section #' + rec.id));

                const option = document.createElement('option');
                option.value = code || String(rec.id);
                option.textContent = label;
                sectionSelect.appendChild(option);
                inserted++;
            });

            if (inserted === 0) {
                const emptyOption = document.createElement('option');
                emptyOption.value = '';
                emptyOption.disabled = true;
                emptyOption.textContent = 'No sections found in database';
                sectionSelect.appendChild(emptyOption);
            }
        }

        function filterRoleOptionsByDept(selectId, allowedDepartmentIds, selectedDepartmentId, isAct) {
            const select = document.getElementById(selectId);
            if (!select) return;

            const selectedDept = String(selectedDepartmentId || '');
            const allowedSet = new Set((allowedDepartmentIds || []).map(function(v) { return String(v); }));

            Array.prototype.slice.call(select.options).forEach(function(opt, index) {
                if (index === 0) return; // placeholder
                if (String(opt.value) === '0') {
                    opt.hidden = false;
                    opt.disabled = false;
                    return;
                }

                const deptId = String(opt.getAttribute('data-department-id') || '');
                let show = true;
                if (selectedDept !== '') {
                    show = deptId === selectedDept;
                } else if (allowedSet.size > 0) {
                    show = allowedSet.has(deptId);
                }

                opt.hidden = !show;
                opt.disabled = !show;
                const defaultLabel = opt.getAttribute('data-default-label') || opt.textContent;
                const actLabel = opt.getAttribute('data-act-label') || defaultLabel;
                opt.textContent = isAct ? actLabel : defaultLabel;
            });

            select.value = '';
        }

        function setupAcademicFilters() {
            const courseSelect = document.getElementById('studentCourseSelect');
            const deptSelect = document.getElementById('studentDepartmentSelect');
            if (!courseSelect) return;

            function applyFilters() {
                const selectedCourse = courseSelect.options[courseSelect.selectedIndex] || null;
                const courseId = selectedCourse ? selectedCourse.value : '';
                const courseCode = selectedCourse ? ((selectedCourse.getAttribute('data-course-code') || '').trim().toUpperCase()) : '';
                const isAct = courseCode === 'ACT';

                const allowedDeptIds = filterDepartmentOptions(courseId);
                const selectedDeptId = deptSelect ? (deptSelect.value || '') : '';

                filterSectionOptions(courseId, selectedDeptId);
                filterRoleOptionsByDept('studentCoordinatorSelect', allowedDeptIds, selectedDeptId, isAct);
                filterRoleOptionsByDept('studentSupervisorSelect', allowedDeptIds, selectedDeptId, isAct);
            }

            if (courseSelect.dataset.academicBound !== '1') {
                courseSelect.addEventListener('change', applyFilters);
                if (deptSelect && typeof deptSelect.addEventListener === 'function' && typeof deptSelect.options !== 'undefined') {
                    deptSelect.addEventListener('change', applyFilters);
                }
                courseSelect.dataset.academicBound = '1';
            }
            applyFilters();
        }

        function setupSelectValueTitles() {
            const selects = document.querySelectorAll('#studentForm select.form-control');
            if (!selects.length) return;

            selects.forEach(function(select) {
                if (!select || select.dataset.titleBound === '1') return;

                const syncTitle = function() {
                    const selectedOption = select.options[select.selectedIndex] || null;
                    const text = selectedOption ? String(selectedOption.textContent || '').trim() : '';
                    select.title = text;
                    if (text !== '') {
                        select.setAttribute('aria-label', text);
                    }
                };

                select.addEventListener('change', syncTitle);
                select.addEventListener('focus', syncTitle);
                syncTitle();
                select.dataset.titleBound = '1';
            });
        }

        // New function to handle password visibility toggle for both password and confirm password
        function setupPasswordToggle() {
            const toggles = document.querySelectorAll('.show-pass-toggle');
            // simple inline SVGs for eye and eye-off
            const eyeSVG = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
            const eyeOffSVG = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">\
                <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a21.86 21.86 0 0 1 5.06-6.94"></path>\
                <path d="M22.54 16.88A21.6 21.6 0 0 0 23 12s-4-8-11-8a10.94 10.94 0 0 0-5.94 1.94"></path>\
                <line x1="1" y1="1" x2="23" y2="23"></line>\
            </svg>';

            toggles.forEach(toggle => {
                // initialize icon if empty
                const icon = toggle.querySelector('i');
                if (icon && !icon.innerHTML.trim()) {
                    icon.innerHTML = eyeSVG;
                    toggle.setAttribute('title', 'Show password');
                    toggle.setAttribute('aria-label', 'Show password');
                }

                toggle.addEventListener('click', function() {
                    const targetId = this.getAttribute('data-target');
                    const targetField = document.getElementById(targetId);
                    if (targetField) {
                        const wasPassword = targetField.type === 'password';
                        targetField.type = wasPassword ? 'text' : 'password';
                        // swap icon
                        const icon = this.querySelector('i');
                        if (icon) {
                            icon.innerHTML = wasPassword ? eyeOffSVG : eyeSVG;
                            this.setAttribute('title', wasPassword ? 'Hide password' : 'Show password');
                            this.setAttribute('aria-label', wasPassword ? 'Hide password' : 'Show password');
                        }
                    }
                });
            });
        }

        function setupPasswordStrengthIndicator() {
            const passwordInput = document.getElementById('studentPassword');
            const indicator = document.getElementById('studentPasswordStrength');
            if (!passwordInput || !indicator) return;

            const text = indicator.querySelector('.password-strength-text');

            function scorePassword(value) {
                const password = String(value || '');
                if (password.length === 0) {
                    return { level: '', label: 'Password strength: Not entered' };
                }

                let score = 0;
                if (password.length >= 8) score += 1;
                if (password.length >= 12) score += 1;
                if (/[a-z]/.test(password) && /[A-Z]/.test(password)) score += 1;
                if (/\d/.test(password)) score += 1;
                if (/[^A-Za-z0-9]/.test(password)) score += 1;

                if (score <= 1) {
                    return { level: 'weak', label: 'Password strength: Weak' };
                }
                if (score <= 2) {
                    return { level: 'fair', label: 'Password strength: Fair' };
                }
                if (score <= 3) {
                    return { level: 'good', label: 'Password strength: Good' };
                }
                return { level: 'strong', label: 'Password strength: Strong' };
            }

            function syncIndicator() {
                const result = scorePassword(passwordInput.value);
                indicator.dataset.strength = result.level;
                if (text) {
                    text.textContent = result.label;
                }
            }

            passwordInput.addEventListener('input', syncIndicator);
            passwordInput.addEventListener('change', syncIndicator);
            syncIndicator();
        }

        /* Role carousel: arrow buttons + touch drag support */
        (function initRoleCarousel(){
            const row = document.getElementById('rolesRow');
            const prev = document.getElementById('rolesPrev');
            const next = document.getElementById('rolesNext');
            if (!row) return;

            const scrollBy = () => Math.round(row.clientWidth * 0.8);

            if (prev) prev.addEventListener('click', () => {
                row.scrollBy({ left: -scrollBy(), behavior: 'smooth' });
            });
            if (next) next.addEventListener('click', () => {
                row.scrollBy({ left: scrollBy(), behavior: 'smooth' });
            });

            // Click delegation: open role on card click (works even if pointer events are present)
            row.addEventListener('click', (e) => {
                const card = e.target.closest('.role-card');
                if (!card) return;
                const role = card.getAttribute('data-role');
                if (role) selectRole(role);
            });

            // Improve accessibility: keyboard arrows when focused (if scrollable)
            row.setAttribute('tabindex','0');
            row.addEventListener('keydown', (e)=>{
                if (e.key === 'ArrowRight') row.scrollBy({ left: scrollBy(), behavior: 'smooth' });
                if (e.key === 'ArrowLeft') row.scrollBy({ left: -scrollBy(), behavior: 'smooth' });
                if (e.key === 'Enter' || e.key === ' ') {
                    // if focused on a child card, trigger selection
                    const active = document.activeElement;
                    const card = active && active.classList && active.classList.contains('role-card') ? active : null;
                    if (card) {
                        const role = card.getAttribute('data-role');
                        if (role) selectRole(role);
                    }
                }
            });
        })();

        setupPasswordStrengthIndicator();
        setupSelectValueTitles();
