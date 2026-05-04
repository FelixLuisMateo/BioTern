(() => {
  const studentSelect = document.getElementById("studentSelect");
  const selectedStudentLabel = document.getElementById("selectedStudentLabel");
  const pickerRows = Array.from(document.querySelectorAll("[data-student-picker-row]"));
  const pickerSearch = document.getElementById("studentPickerSearch");
  const pickerYear = document.getElementById("studentPickerYear");
  const pickerSemester = document.getElementById("studentPickerSemester");
  const pickerCourse = document.getElementById("studentPickerCourse");
  const pickerSection = document.getElementById("studentPickerSection");
  const pickerApply = document.getElementById("studentPickerApply");
  const pickerEmpty = document.getElementById("studentPickerEmpty");
  const typeSelect = document.getElementById("typeSelect");
  const requiredHoursInput = document.getElementById("requiredHoursInput");
  const internalHoursInput = document.getElementById("internalHoursInput");
  const externalHoursInput = document.getElementById("externalHoursInput");

  if (!studentSelect || !typeSelect || !requiredHoursInput || !internalHoursInput || !externalHoursInput) {
    return;
  }

  const selectedStudentRow = () => {
    const value = studentSelect.value || "";
    return pickerRows.find((row) => row.getAttribute("data-student-id") === value) || null;
  };

  const syncRequiredHours = () => {
    const type = (typeSelect.value || "internal").toLowerCase();
    const internal = parseInt(internalHoursInput.value || "0", 10);
    const external = parseInt(externalHoursInput.value || "0", 10);

    if (type === "external") {
      requiredHoursInput.value = Number.isNaN(external) ? 0 : Math.max(0, external);
    } else {
      requiredHoursInput.value = Number.isNaN(internal) ? 0 : Math.max(0, internal);
    }
  };

  const applyStudentDefaults = () => {
    const row = selectedStudentRow();
    if (!row) return;

    const defaultTrack = (row.getAttribute("data-track") || "internal").toLowerCase();
    const internalDefault = parseInt(row.getAttribute("data-internal-hours") || "140", 10);
    const externalDefault = parseInt(row.getAttribute("data-external-hours") || "250", 10);

    if (!Number.isNaN(internalDefault)) {
      internalHoursInput.value = Math.max(0, internalDefault);
    }
    if (!Number.isNaN(externalDefault)) {
      externalHoursInput.value = Math.max(0, externalDefault);
    }

    if (defaultTrack === "external" || defaultTrack === "internal") {
      typeSelect.value = defaultTrack;
    }
    syncRequiredHours();
  };

  const selectStudent = (studentId, label) => {
    studentSelect.value = studentId || "";
    if (selectedStudentLabel) {
      selectedStudentLabel.textContent = label || "Select student";
    }
    pickerRows.forEach((row) => {
      const active = row.getAttribute("data-student-id") === String(studentId || "");
      row.classList.toggle("is-selected", active);
      const radio = row.querySelector('input[type="radio"]');
      if (radio) radio.checked = active;
    });
    applyStudentDefaults();
  };

  const filterPickerRows = () => {
    const needle = (pickerSearch && pickerSearch.value ? pickerSearch.value : "").trim().toLowerCase();
    const year = pickerYear ? pickerYear.value : "";
    const semester = pickerSemester ? pickerSemester.value : "";
    const course = pickerCourse ? pickerCourse.value : "";
    const section = pickerSection ? pickerSection.value : "";
    let visibleCount = 0;

    pickerRows.forEach((row) => {
      const matches =
        (!needle || (row.getAttribute("data-search") || "").includes(needle)) &&
        (!year || (row.getAttribute("data-school-year") || "") === year) &&
        (!semester || (row.getAttribute("data-semester") || "") === semester) &&
        (!course || (row.getAttribute("data-course-id") || "") === course) &&
        (!section || (row.getAttribute("data-section-id") || "") === section);
      row.classList.toggle("d-none", !matches);
      if (matches) visibleCount += 1;
    });

    if (pickerEmpty) pickerEmpty.classList.toggle("d-none", visibleCount > 0);
  };

  pickerRows.forEach((row) => {
    row.addEventListener("click", (event) => {
      const radio = row.querySelector('input[type="radio"]');
      if (radio && event.target !== radio) radio.checked = true;
    });
  });

  [pickerSearch, pickerYear, pickerSemester, pickerCourse, pickerSection].forEach((input) => {
    if (input) input.addEventListener("input", filterPickerRows);
    if (input) input.addEventListener("change", filterPickerRows);
  });

  if (pickerApply) {
    pickerApply.addEventListener("click", () => {
      const checked = document.querySelector('input[name="student_picker_choice"]:checked');
      if (!checked) return;
      const row = checked.closest("[data-student-picker-row]");
      selectStudent(checked.value, row ? row.getAttribute("data-label") : "");
    });
  }

  typeSelect.addEventListener("change", syncRequiredHours);
  internalHoursInput.addEventListener("input", syncRequiredHours);
  externalHoursInput.addEventListener("input", syncRequiredHours);

  filterPickerRows();
  applyStudentDefaults();
})();
