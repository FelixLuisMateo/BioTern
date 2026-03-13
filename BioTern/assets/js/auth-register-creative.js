let currentRole = null;

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

        function selectRole(role) {
            currentRole = role;
            const roleSelection = document.getElementById('roleSelectionScreen');
            const loginLink = document.getElementById('loginLink');
            const loginLinkHidden = document.getElementById('loginLinkHidden');

            const forms = {
                'student': 'studentForm',
                'coordinator': 'coordinatorForm',
                'supervisor': 'supervisorForm',
                'admin': 'adminForm'
            };

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
            const roleSelection = document.getElementById('roleSelectionScreen');
            const loginLink = document.getElementById('loginLink');
            const loginLinkHidden = document.getElementById('loginLinkHidden');
            const forms = {
                'student': 'studentForm',
                'coordinator': 'coordinatorForm',
                'supervisor': 'supervisorForm',
                'admin': 'adminForm'
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
            const formIds = ['studentForm', 'coordinatorForm', 'supervisorForm', 'adminForm'];
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
            initFormStepper('studentForm');
            initFormStepper('coordinatorForm');
            initFormStepper('supervisorForm');
            initFormStepper('adminForm');

            const studentIdInput = document.querySelector('#studentForm input[name="student_id"]');
            if (studentIdInput) {
                const studentIdPattern = /^05-[0-9]{4,5}$/;

                studentIdInput.addEventListener('input', function() {
                    this.value = this.value.replace(/\s+/g, '');
                    if (this.value === '' || studentIdPattern.test(this.value)) {
                        this.setCustomValidity('');
                    } else {
                        this.setCustomValidity('Use format 05-1234 or 05-12345');
                    }
                });

                studentIdInput.addEventListener('blur', function() {
                    this.value = this.value.trim();
                });
            }

            const requestedRole = new URLSearchParams(window.location.search).get('role');
            if (requestedRole && requestedRole.toLowerCase() === 'student') {
                selectRole('student');
            }

            const params = new URLSearchParams(window.location.search);
            if (params.get('registered')) {
                const studentForm = document.getElementById('studentForm');
                if (studentForm) {
                    studentForm.reset();
                    setupAcademicFilters();
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

        function getCourseAllowedDepartmentIds(courseId) {
            const bucket = courseDepartmentMap[String(courseId)] || {};
            const ids = Object.keys(bucket).map(function(id) { return String(id); });
            // Fallback: if no course->department mapping exists yet, keep all departments visible.
            if (!ids.length) {
                const deptSelect = document.getElementById('studentDepartmentSelect');
                if (!deptSelect) return [];
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
            if (!deptSelect) return [];
            const selectedBefore = deptSelect.value;
            const allowedDepartmentIds = getCourseAllowedDepartmentIds(courseId);
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
            if (!courseSelect || !deptSelect) return;

            function applyFilters() {
                const selectedCourse = courseSelect.options[courseSelect.selectedIndex] || null;
                const courseId = selectedCourse ? selectedCourse.value : '';
                const courseCode = selectedCourse ? ((selectedCourse.getAttribute('data-course-code') || '').trim().toUpperCase()) : '';
                const isAct = courseCode === 'ACT';

                const allowedDeptIds = filterDepartmentOptions(courseId);
                const selectedDeptId = deptSelect.value || '';

                filterSectionOptions(courseId, selectedDeptId);
                filterRoleOptionsByDept('studentCoordinatorSelect', allowedDeptIds, selectedDeptId, isAct);
                filterRoleOptionsByDept('studentSupervisorSelect', allowedDeptIds, selectedDeptId, isAct);
            }

            if (courseSelect.dataset.academicBound !== '1') {
                courseSelect.addEventListener('change', applyFilters);
                deptSelect.addEventListener('change', applyFilters);
                courseSelect.dataset.academicBound = '1';
            }
            applyFilters();
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
