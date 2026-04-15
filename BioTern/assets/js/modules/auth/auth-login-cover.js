document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("authLoginForm");
  const toggle = document.getElementById("togglePassword");
  const pwd = document.getElementById("passwordInput");

  const getAnchor = (field) => field.closest(".input-group") || field;

  const getFieldKey = (field) => {
    if (field.id && String(field.id).trim() !== "") {
      return String(field.id).trim();
    }

    if (field.name && String(field.name).trim() !== "") {
      return String(field.name).trim();
    }

    return "login-field";
  };

  const getValidationMessage = (field) => {
    if (field.validity) {
      if (field.validity.valueMissing) {
        return "This field is required.";
      }

      if (field.validity.typeMismatch) {
        return "Please enter a valid value.";
      }

      if (field.validity.patternMismatch) {
        return field.getAttribute("title") || "Invalid format.";
      }
    }

    return field.validationMessage || "Please check this field.";
  };

  const clearInvalid = (field) => {
    field.classList.remove("is-invalid");

    const anchor = getAnchor(field);
    const key = getFieldKey(field);
    const parent = anchor.parentElement;
    if (!parent) {
      return;
    }

    const feedback = parent.querySelector(
      '.invalid-feedback.auth-login-field-feedback[data-field="' + key + '"]'
    );
    if (feedback) {
      feedback.remove();
    }
  };

  const markInvalid = (field, message) => {
    field.classList.add("is-invalid");

    const anchor = getAnchor(field);
    const key = getFieldKey(field);
    const parent = anchor.parentElement;
    if (!parent) {
      return;
    }

    let feedback = parent.querySelector(
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
  };

  const validateField = (field) => {
    if (field.disabled) {
      clearInvalid(field);
      return true;
    }

    const fieldType = String(field.type || "").toLowerCase();
    if (fieldType === "hidden") {
      clearInvalid(field);
      return true;
    }

    if (field.checkValidity()) {
      clearInvalid(field);
      return true;
    }

    markInvalid(field, getValidationMessage(field));
    return false;
  };

  if (form) {
    const requiredFields = Array.prototype.slice.call(
      form.querySelectorAll("input[required], select[required], textarea[required]")
    );

    form.addEventListener("submit", (event) => {
      let firstInvalidField = null;

      requiredFields.forEach((field) => {
        const isValid = validateField(field);
        if (!isValid && !firstInvalidField) {
          firstInvalidField = field;
        }
      });

      if (firstInvalidField) {
        event.preventDefault();
        firstInvalidField.focus();
      }
    });

    requiredFields.forEach((field) => {
      const revalidate = () => {
        if (!field.classList.contains("is-invalid")) {
          return;
        }

        validateField(field);
      };

      field.addEventListener("input", revalidate);
      field.addEventListener("change", revalidate);
      field.addEventListener("blur", revalidate);
    });
  }

  const eyeSVG =
    '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
  const eyeOffSVG =
    '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a21.86 21.86 0 0 1 5.06-6.94"></path><path d="M22.54 16.88A21.6 21.6 0 0 0 23 12s-4-8-11-8a10.94 10.94 0 0 0-5.94 1.94"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';

  if (!toggle || !pwd) {
    return;
  }

  const icon = toggle.querySelector("i");
  if (icon && !icon.innerHTML.trim()) {
    icon.innerHTML = eyeSVG;
    toggle.setAttribute("title", "Show password");
    toggle.setAttribute("aria-label", "Show password");
  }

  toggle.addEventListener("click", () => {
    const wasPassword = pwd.type === "password";
    pwd.type = wasPassword ? "text" : "password";
    const currentIcon = toggle.querySelector("i");
    if (currentIcon) {
      currentIcon.innerHTML = wasPassword ? eyeOffSVG : eyeSVG;
      toggle.setAttribute("title", wasPassword ? "Hide password" : "Show password");
      toggle.setAttribute("aria-label", wasPassword ? "Hide password" : "Show password");
    }
  });
});
