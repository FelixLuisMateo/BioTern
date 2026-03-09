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
    if (window.jQuery && $("#date_of_birth").length && $.fn.datepicker) {
      $("#date_of_birth").datepicker({
        format: "yyyy-mm-dd",
        autoclose: true,
      });
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

  function initPolicyHoursPreview() {
    var previewInput = document.getElementById("policy_required_hours_preview");
    var courseField = document.getElementById("course_id");
    var trackField = document.getElementById("assignment_track");
    var internalTotalField = document.getElementById("internal_total_hours");
    var externalTotalField = document.getElementById("external_total_hours");
    var policyMapField = document.getElementById("course_policy_map_json");

    if (!previewInput || !courseField || !trackField || !policyMapField) {
      return;
    }

    var coursePolicyMap = {};
    try {
      coursePolicyMap = JSON.parse(policyMapField.value || "{}");
    } catch (error) {
      coursePolicyMap = {};
    }

    function toInt(value) {
      var parsed = parseInt(value, 10);
      return Number.isNaN(parsed) ? 0 : parsed;
    }

    function computeRequiredHours() {
      var courseId = String(toInt(courseField.value));
      var track = (trackField.value || "internal").toLowerCase() === "external" ? "external" : "internal";
      var coursePolicy = coursePolicyMap[courseId] || { internal: 0, external: 0, total: 0 };

      var internalFallback = toInt(internalTotalField ? internalTotalField.value : 0);
      var externalFallback = toInt(externalTotalField ? externalTotalField.value : 0);

      var required = 0;
      if (track === "external") {
        required = toInt(coursePolicy.external);
        if (required <= 0) {
          required = externalFallback;
        }
        if (required <= 0) {
          required = 250;
        }
      } else {
        required = toInt(coursePolicy.internal);
        if (required <= 0) {
          required = internalFallback;
        }
        if (required <= 0) {
          required = toInt(coursePolicy.total);
        }
        if (required <= 0) {
          required = 600;
        }
      }

      previewInput.value = String(required);
    }

    [courseField, trackField, internalTotalField, externalTotalField].forEach(function (field) {
      if (!field) {
        return;
      }
      field.addEventListener("change", computeRequiredHours);
      field.addEventListener("input", computeRequiredHours);
    });

    computeRequiredHours();
  }

  function initStudentsEditRuntime() {
    initSelect2Fields();
    initDateField();
    initFormValidation();
    initAutoHideAlerts();
    initPolicyHoursPreview();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initStudentsEditRuntime);
  } else {
    initStudentsEditRuntime();
  }
})();
