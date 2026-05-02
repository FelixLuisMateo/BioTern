document.addEventListener("DOMContentLoaded", function () {
  var form = document.getElementById("authLoginForm");
  var passwordInput = document.getElementById("passwordInput");
  var toggleButton = document.getElementById("togglePassword");
  var eyeSvg =
    '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
  var eyeOffSvg =
    '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a21.86 21.86 0 0 1 5.06-6.94"></path><path d="M22.54 16.88A21.6 21.6 0 0 0 23 12s-4-8-11-8a10.94 10.94 0 0 0-5.94 1.94"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';

  function getAnchor(field) {
    return field.closest(".input-group") || field;
  }

  function getFieldKey(field) {
    if (field.id && String(field.id).trim() !== "") {
      return String(field.id).trim();
    }
    if (field.name && String(field.name).trim() !== "") {
      return String(field.name).trim();
    }
    return "login-field";
  }

  function getValidationMessage(field) {
    if (field.validity) {
      if (field.validity.valueMissing) return "This field is required.";
      if (field.validity.typeMismatch) return "Please enter a valid value.";
      if (field.validity.patternMismatch) return field.getAttribute("title") || "Invalid format.";
    }
    return field.validationMessage || "Please check this field.";
  }

  function clearInvalid(field) {
    var anchor = getAnchor(field);
    var parent = anchor.parentElement;
    var key = getFieldKey(field);
    if (!parent) return;

    field.classList.remove("is-invalid");
    var feedback = parent.querySelector(
      '.invalid-feedback.auth-login-field-feedback[data-field="' + key + '"]'
    );
    if (feedback) {
      feedback.remove();
    }
  }

  function markInvalid(field, message) {
    var anchor = getAnchor(field);
    var parent = anchor.parentElement;
    var key = getFieldKey(field);
    var feedback = null;
    if (!parent) return;

    field.classList.add("is-invalid");
    feedback = parent.querySelector(
      '.invalid-feedback.auth-login-field-feedback[data-field="' + key + '"]'
    );

    if (!feedback) {
      feedback = document.createElement("div");
      feedback.className =
        "invalid-feedback auth-login-field-feedback d-block app-theme-notify-inline app-theme-notify-inline--error";
      feedback.setAttribute("role", "alert");
      feedback.dataset.field = key;
      parent.appendChild(feedback);
    }

    feedback.textContent = message;
  }

  function validateField(field) {
    var fieldType = String(field.type || "").toLowerCase();
    if (field.disabled || fieldType === "hidden") {
      clearInvalid(field);
      return true;
    }

    if (field.checkValidity()) {
      clearInvalid(field);
      return true;
    }

    markInvalid(field, getValidationMessage(field));
    return false;
  }

  if (toggleButton && passwordInput) {
    document.querySelectorAll("#togglePassword").forEach(function (element, index) {
      if (index > 0) {
        element.remove();
      }
    });

    var icon = toggleButton.querySelector("i");
    if (icon) {
      icon.innerHTML = eyeSvg;
    }
    toggleButton.setAttribute("title", "Show password");
    toggleButton.setAttribute("aria-label", "Show password");

    toggleButton.addEventListener("click", function () {
      var reveal = passwordInput.type === "password";
      var currentIcon = toggleButton.querySelector("i");

      passwordInput.type = reveal ? "text" : "password";
      toggleButton.setAttribute("title", reveal ? "Hide password" : "Show password");
      toggleButton.setAttribute("aria-label", reveal ? "Hide password" : "Show password");

      if (currentIcon) {
        currentIcon.innerHTML = reveal ? eyeOffSvg : eyeSvg;
      }
    });
  }

  if (!form) {
    return;
  }

  var locationStatus = document.getElementById("deviceLocationStatus");
  var locationLat = document.getElementById("deviceLatitude");
  var locationLng = document.getElementById("deviceLongitude");
  var locationAccuracy = document.getElementById("deviceAccuracy");
  var ipLocationLabel = document.getElementById("ipLocationLabel");
  var ipLocationSource = document.getElementById("ipLocationSource");

  if (ipLocationLabel && ipLocationSource && window.fetch) {
    fetch("https://ipwho.is/?fields=success,city,region,country", {
      method: "GET",
      cache: "no-store",
    })
      .then(function (response) {
        return response && response.ok ? response.json() : null;
      })
      .then(function (data) {
        if (!data || !data.success) {
          return;
        }

        var parts = [data.city, data.region, data.country].filter(function (value, index, array) {
          value = String(value || "").trim();
          return value !== "" && array.indexOf(value) === index;
        });

        if (parts.length) {
          ipLocationLabel.value = parts.join(", ");
          ipLocationSource.value = "ipwho.is-browser";
        }
      })
      .catch(function () {});
  }

  if (locationStatus && locationLat && locationLng && locationAccuracy) {
    if (!navigator.geolocation) {
      locationStatus.value = "unsupported";
    } else if (window.isSecureContext === false) {
      locationStatus.value = "insecure_context";
    } else {
      locationStatus.value = "requesting";
      navigator.geolocation.getCurrentPosition(
        function (position) {
          var coords = position.coords || {};
          locationLat.value = typeof coords.latitude === "number" ? coords.latitude.toFixed(7) : "";
          locationLng.value = typeof coords.longitude === "number" ? coords.longitude.toFixed(7) : "";
          locationAccuracy.value = typeof coords.accuracy === "number" ? coords.accuracy.toFixed(2) : "";
          locationStatus.value = locationLat.value && locationLng.value ? "captured" : "not_available";
        },
        function (error) {
          var code = error && typeof error.code === "number" ? error.code : 0;
          if (code === 1) {
            locationStatus.value = "denied";
          } else if (code === 2) {
            locationStatus.value = "unavailable";
          } else if (code === 3) {
            locationStatus.value = "timeout";
          } else {
            locationStatus.value = "error";
          }
        },
        {
          enableHighAccuracy: false,
          timeout: 5000,
          maximumAge: 300000,
        }
      );
    }
  }

  var requiredFields = Array.prototype.slice.call(
    form.querySelectorAll("input[required], select[required], textarea[required]")
  );

  form.addEventListener("submit", function (event) {
    var firstInvalidField = null;

    requiredFields.forEach(function (field) {
      var isValid = validateField(field);
      if (!isValid && !firstInvalidField) {
        firstInvalidField = field;
      }
    });

    if (firstInvalidField) {
      event.preventDefault();
      firstInvalidField.focus();
    }
  });

  requiredFields.forEach(function (field) {
    var revalidate = function () {
      if (field.classList.contains("is-invalid")) {
        validateField(field);
      }
    };

    field.addEventListener("input", revalidate);
    field.addEventListener("change", revalidate);
    field.addEventListener("blur", revalidate);
  });
});
