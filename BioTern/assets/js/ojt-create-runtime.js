(() => {
  const studentSelect = document.getElementById("studentSelect");
  const typeSelect = document.getElementById("typeSelect");
  const requiredHoursInput = document.getElementById("requiredHoursInput");
  const internalHoursInput = document.getElementById("internalHoursInput");
  const externalHoursInput = document.getElementById("externalHoursInput");

  if (!studentSelect || !typeSelect || !requiredHoursInput || !internalHoursInput || !externalHoursInput) {
    return;
  }

  const selectedStudentOption = () => studentSelect.options[studentSelect.selectedIndex] || null;

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
    const option = selectedStudentOption();
    if (!option) return;

    const defaultTrack = (option.getAttribute("data-track") || "internal").toLowerCase();
    const internalDefault = parseInt(option.getAttribute("data-internal-hours") || "140", 10);
    const externalDefault = parseInt(option.getAttribute("data-external-hours") || "250", 10);

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

  studentSelect.addEventListener("change", applyStudentDefaults);
  typeSelect.addEventListener("change", syncRequiredHours);
  internalHoursInput.addEventListener("input", syncRequiredHours);
  externalHoursInput.addEventListener("input", syncRequiredHours);

  applyStudentDefaults();
})();
