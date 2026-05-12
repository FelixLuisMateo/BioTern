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

  function initAvatarCropper() {
    var form = document.getElementById("editStudentForm");
    if (!form) return;

    var fileInput = form.querySelector("[data-student-avatar-file-input]");
    var hiddenInput = form.querySelector("[data-student-avatar-cropped-input]");
    var preview = form.querySelector("[data-student-avatar-preview]");
    var previewWrap = form.querySelector("[data-student-avatar-preview-wrap]");
    var emptyState = form.querySelector("[data-student-avatar-empty]");
    var modalEl = document.querySelector("[data-student-avatar-crop-modal]");
    var canvas = modalEl ? modalEl.querySelector("[data-student-avatar-crop-canvas]") : null;
    var zoomInput = modalEl ? modalEl.querySelector("[data-student-avatar-crop-zoom]") : null;
    var resetButton = modalEl ? modalEl.querySelector("[data-student-avatar-crop-reset]") : null;
    var applyButton = modalEl ? modalEl.querySelector("[data-student-avatar-crop-apply]") : null;
    var status = modalEl ? modalEl.querySelector("[data-student-avatar-crop-status]") : null;

    if (!fileInput || !hiddenInput || !modalEl || !canvas || !zoomInput || !resetButton || !applyButton) {
      return;
    }

    var cropModal = window.bootstrap && window.bootstrap.Modal
      ? window.bootstrap.Modal.getOrCreateInstance(modalEl)
      : null;
    var ctx = canvas.getContext("2d");
    if (!ctx) return;

    var state = {
      image: null,
      baseScale: 1,
      scale: 1,
      minScale: 1,
      maxScale: 4,
      offsetX: 0,
      offsetY: 0,
      dragging: false,
      dragX: 0,
      dragY: 0,
    };

    function setStatus(text) {
      if (status) status.textContent = text;
    }

    function clampOffset() {
      if (!state.image) return;
      var drawWidth = state.image.width * state.scale;
      var drawHeight = state.image.height * state.scale;

      if (drawWidth <= canvas.width) {
        state.offsetX = (canvas.width - drawWidth) / 2;
      } else {
        state.offsetX = Math.min(0, Math.max(canvas.width - drawWidth, state.offsetX));
      }

      if (drawHeight <= canvas.height) {
        state.offsetY = (canvas.height - drawHeight) / 2;
      } else {
        state.offsetY = Math.min(0, Math.max(canvas.height - drawHeight, state.offsetY));
      }
    }

    function draw() {
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      ctx.fillStyle = "#0b1220";
      ctx.fillRect(0, 0, canvas.width, canvas.height);
      if (!state.image) return;
      clampOffset();
      ctx.drawImage(
        state.image,
        state.offsetX,
        state.offsetY,
        state.image.width * state.scale,
        state.image.height * state.scale
      );
    }

    function resetToDefault() {
      if (!state.image) return;
      state.baseScale = Math.max(canvas.width / state.image.width, canvas.height / state.image.height);
      state.minScale = state.baseScale;
      state.maxScale = state.baseScale * 4;
      state.scale = state.baseScale;
      state.offsetX = (canvas.width - state.image.width * state.scale) / 2;
      state.offsetY = (canvas.height - state.image.height * state.scale) / 2;
      zoomInput.value = "100";
      draw();
      setStatus("Drag the image to position the crop area.");
    }

    function updateScaleFromZoom() {
      if (!state.image) return;
      var zoomPercent = parseInt(zoomInput.value, 10);
      if (isNaN(zoomPercent) || zoomPercent < 100) zoomPercent = 100;
      state.scale = Math.min(state.maxScale, Math.max(state.minScale, state.baseScale * (zoomPercent / 100)));
      draw();
      hiddenInput.value = "";
    }

    function croppedDataUrl() {
      try {
        return canvas.toDataURL("image/webp", 0.9);
      } catch (errWebp) {}
      try {
        return canvas.toDataURL("image/jpeg", 0.9);
      } catch (errJpeg) {}
      return canvas.toDataURL("image/png");
    }

    function applyCrop() {
      if (!state.image) return;
      var dataUrl = croppedDataUrl();
      hiddenInput.value = dataUrl;
      if (preview && dataUrl) {
        preview.src = dataUrl;
        if (previewWrap) previewWrap.classList.remove("d-none");
        if (emptyState) emptyState.classList.add("d-none");
      }
      try {
        fileInput.value = "";
      } catch (errReset) {
        // Some browsers block programmatic file reset; cropped data still takes priority.
      }
      setStatus("Crop is ready. Save the student to apply it.");
      if (cropModal) cropModal.hide();
    }

    function getPoint(event) {
      if (event.touches && event.touches.length) {
        return { x: event.touches[0].clientX, y: event.touches[0].clientY };
      }
      return { x: event.clientX, y: event.clientY };
    }

    function startDrag(event) {
      if (!state.image) return;
      var point = getPoint(event);
      state.dragging = true;
      state.dragX = point.x;
      state.dragY = point.y;
    }

    function moveDrag(event) {
      if (!state.dragging || !state.image) return;
      if (event.cancelable) event.preventDefault();
      var point = getPoint(event);
      state.offsetX += point.x - state.dragX;
      state.offsetY += point.y - state.dragY;
      state.dragX = point.x;
      state.dragY = point.y;
      draw();
    }

    function stopDrag() {
      state.dragging = false;
    }

    fileInput.addEventListener("change", function () {
      hiddenInput.value = "";
      var file = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
      if (!file) return;
      if (!/^image\//i.test(file.type)) {
        setStatus("Please select an image file.");
        return;
      }
      if (file.size > 5 * 1024 * 1024) {
        setStatus("Image must be 5MB or smaller.");
        fileInput.value = "";
        return;
      }

      var reader = new FileReader();
      reader.onload = function (event) {
        var rawPreview = String(event.target && event.target.result ? event.target.result : "");
        if (preview && rawPreview) {
          preview.src = rawPreview;
          if (previewWrap) previewWrap.classList.remove("d-none");
          if (emptyState) emptyState.classList.add("d-none");
        }
        var img = new Image();
        img.onload = function () {
          state.image = img;
          resetToDefault();
          if (cropModal) cropModal.show();
        };
        img.src = rawPreview;
      };
      reader.readAsDataURL(file);
    });

    zoomInput.addEventListener("input", updateScaleFromZoom);
    resetButton.addEventListener("click", function () {
      hiddenInput.value = "";
      resetToDefault();
    });
    applyButton.addEventListener("click", applyCrop);

    form.addEventListener("submit", function () {
      if (state.image && !hiddenInput.value) {
        applyCrop();
      }
      if (hiddenInput.value) {
        try {
          fileInput.value = "";
        } catch (errResetSubmit) {
          // Cropped payload is already stored in the hidden input.
        }
      }
    });

    canvas.addEventListener("mousedown", startDrag);
    window.addEventListener("mousemove", moveDrag);
    window.addEventListener("mouseup", stopDrag);
    canvas.addEventListener("touchstart", startDrag, { passive: true });
    canvas.addEventListener("touchmove", moveDrag, { passive: false });
    window.addEventListener("touchend", stopDrag, { passive: true });
    modalEl.addEventListener("hidden.bs.modal", stopDrag);
  }

  function initStudentsEditRuntime() {
    initSelect2Fields();
    initDateField();
    initFormValidation();
    initAvatarCropper();
    initAutoHideAlerts();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initStudentsEditRuntime);
  } else {
    initStudentsEditRuntime();
  }
})();
