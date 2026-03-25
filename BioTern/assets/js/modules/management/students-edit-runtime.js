/* Students edit page runtime extracted from inline script */
(function () {
  "use strict";

  function initSelect2Fields() {
    if (!window.jQuery) {
      return;
    }

    ["#course_id", "#department_id", "#section_id", "#status"].forEach(function (selector) {
      $(selector).each(function () {
        $(this).select2({
          allowClear: false,
          width: "100%",
          dropdownAutoWidth: false,
          theme: "default",
          dropdownParent: $("#editStudentForm"),
        });
      });
    });

    $("#supervisor_id").select2({
      placeholder: "Select supervisor",
      allowClear: false,
      minimumResultsForSearch: 0,
      width: "100%",
      dropdownAutoWidth: false,
      theme: "default",
      dropdownParent: $("#editStudentForm"),
    });

    $("#coordinator_id").select2({
      placeholder: "Select coordinator",
      allowClear: false,
      minimumResultsForSearch: 0,
      width: "100%",
      dropdownAutoWidth: false,
      theme: "default",
      dropdownParent: $("#editStudentForm"),
    });
  }

  function initDateField() {
    if (
      window.AppCore &&
      window.AppCore.DatePicker &&
      typeof window.AppCore.DatePicker.refresh === "function"
    ) {
      window.AppCore.DatePicker.refresh(document.getElementById("editStudentForm"));
    }
  }

  function initFormValidation() {
    var form = document.getElementById("editStudentForm");
    if (!form) {
      return;
    }

    form.addEventListener("submit", function (e) {
      var firstName = (document.getElementById("first_name") || {}).value || "";
      var lastName = (document.getElementById("last_name") || {}).value || "";
      var email = (document.getElementById("email") || {}).value || "";

      firstName = firstName.trim();
      lastName = lastName.trim();
      email = email.trim();

      if (!firstName || !lastName || !email) {
        e.preventDefault();
        alert("Please fill in all required fields!");
        return false;
      }

      var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(email)) {
        e.preventDefault();
        alert("Please enter a valid email address!");
        return false;
      }

      return true;
    });
  }

  function initAutoHideAlerts() {
    var alerts = document.querySelectorAll(".alert");
    alerts.forEach(function (alertNode) {
      setTimeout(function () {
        if (window.bootstrap && window.bootstrap.Alert) {
          var bsAlert = new window.bootstrap.Alert(alertNode);
          bsAlert.close();
        }
      }, 5000);
    });
  }

  function initStudentsEditRuntime() {
    initSelect2Fields();
    initDateField();
    initFormValidation();
    initAutoHideAlerts();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initStudentsEditRuntime);
  } else {
    initStudentsEditRuntime();
  }
})();
