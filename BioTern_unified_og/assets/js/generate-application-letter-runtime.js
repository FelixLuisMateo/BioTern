(function () {
  "use strict";

  var docs = window.AppCore && window.AppCore.Documents ? window.AppCore.Documents : null;

  if (docs) {
    docs.hideBrokenImagesOnError();
  } else {
    document.addEventListener("error", function (event) {
      var target = event.target;
      if (target && target.matches && target.matches("img[data-hide-onerror='1']")) {
        target.style.display = "none";
      }
    }, true);
  }

  var pageRoot = document.querySelector(".main-content[data-print-date]") || document.body;
  var cfg = pageRoot && pageRoot.dataset ? pageRoot.dataset : {};

  function ensurePrintableHoursSpan(value) {
    var doc = document.getElementById("application_doc_content");
    if (!doc) return null;

    var existing = doc.querySelector("#ap_hours");
    if (existing) {
      existing.textContent = value || existing.textContent || "250";
      return existing;
    }

    var paragraphs = doc.querySelectorAll("p");
    paragraphs.forEach(function (p) {
      if (existing) return;
      var text = (p.textContent || "").replace(/\s+/g, " ").trim();
      if (text.indexOf("I am ") !== 0) return;
      if (text.indexOf("minimum of") === -1 || text.indexOf("hours") === -1) return;
      p.innerHTML = p.innerHTML.replace(
        /minimum of\s*<strong>[\s\S]*?hours<\/strong>/i,
        "minimum of <strong><span id=\"ap_hours\">" +
          String(value || "250")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;") +
          "</span> hours</strong>"
      );
      existing = doc.querySelector("#ap_hours");
    });

    return existing;
  }

  function setText(id, value) {
    var el = document.getElementById(id);
    if (!el) return;
    el.textContent = value || "";
  }

  function applyRuntimeValues() {
    setText("ap_date", cfg.printDate || "");
    setText("ap_name", cfg.apName || "");
    setText("ap_position", cfg.apPosition || "");
    setText("ap_company", cfg.apCompany || "");
    setText("ap_address", cfg.apCompanyAddress || "");
    ensurePrintableHoursSpan(cfg.apHours || "250");
    setText("ap_student", cfg.fullName || "");
    setText("ap_student_name", cfg.fullName || "");
    setText("ap_student_address", cfg.studentAddress || "");
    setText("ap_student_contact", cfg.studentPhone || "");
  }

  function escHtml(s) {
    return String(s || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/\"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function underlineLabelValue(label, extraClass) {
    var doc = document.getElementById("application_doc_content");
    if (!doc) return;
    var nodes = doc.querySelectorAll("p");
    nodes.forEach(function (p) {
      if (p.querySelector("input, textarea, select")) return;
      var text = (p.textContent || "").replace(/\s+/g, " ").trim();
      if (text.indexOf(label) !== 0) return;
      var value = text.slice(label.length).trim();
      var cls = "filled-val" + (extraClass ? " " + extraClass : "");
      p.innerHTML = label + " <span class=\"" + cls + "\">" + escHtml(value) + "</span>";
    });
  }

  function underlineIAmLine() {
    var doc = document.getElementById("application_doc_content");
    if (!doc) return;
    var nodes = doc.querySelectorAll("p");
    nodes.forEach(function (p) {
      var html = (p.innerHTML || "").trim();
      if (html.indexOf("I am") !== 0) return;
      if (html.indexOf("filled-val-name") !== -1) return;
      p.innerHTML = html.replace(
        /^I am\s*(.*?)\s*,\s*student of/i,
        "I am <span class=\"filled-val filled-val-name\">$1</span>, student of"
      );
    });
  }

  function forceApplicationUnderlines() {
    underlineLabelValue("Date:");
    underlineLabelValue("Mr./Ms.:");
    underlineLabelValue("Position:");
    underlineLabelValue("Name of Company:", "pv-wide");
    underlineLabelValue("Company Address:", "pv-wide");
    underlineLabelValue("Student Name:");
    underlineLabelValue("Student Home Address:", "filled-val-wide");
    underlineLabelValue("Contact No.:");
    underlineIAmLine();
  }

  applyRuntimeValues();
  forceApplicationUnderlines();

  if (docs) {
    docs.bindPrintButton("btn_print");
    docs.bindCloseButton("btn_close", "documents/document_application.php");
  } else {
    var printButton = document.getElementById("btn_print");
    if (printButton) {
      printButton.addEventListener("click", function (e) {
        e.preventDefault();
        window.print();
      });
    }

    var closeButton = document.getElementById("btn_close");
    if (closeButton) {
      closeButton.addEventListener("click", function (e) {
        e.preventDefault();
        if (window.opener && !window.opener.closed) {
          window.close();
          return;
        }
        if (window.history.length > 1) {
          window.history.back();
          return;
        }
        window.location.href = "documents/document_application.php";
      });
    }
  }

  if (cfg.useSavedTemplate === "1") {
    try {
      var saved = localStorage.getItem("biotern_application_template_html_v1");
      var doc = document.getElementById("application_doc_content");
      var pageCrest = document.querySelector(".crest");
      if (saved && doc) {
        var temp = document.createElement("div");
        temp.innerHTML = saved;
        var extracted = temp.querySelector(".content");
        var savedCrest = temp.querySelector(".crest");
        if (savedCrest && pageCrest) {
          var style = savedCrest.style || {};
          if (style.left) pageCrest.style.left = style.left;
          if (style.top) pageCrest.style.top = style.top;
          if (style.width) pageCrest.style.width = style.width;
          if (style.height) pageCrest.style.height = style.height;
        }
        if (!extracted) extracted = temp.querySelector("#application_doc_content");
        if (extracted) {
          doc.innerHTML = extracted.innerHTML;
        } else {
          var oldHeader = temp.querySelector(".header");
          if (oldHeader) oldHeader.remove();
          var oldCrest = temp.querySelector(".crest");
          if (oldCrest) oldCrest.remove();
          doc.innerHTML = temp.innerHTML || saved;
        }

        applyRuntimeValues();
        forceApplicationUnderlines();

        var title = doc.querySelector("h3");
        if (title) title.style.textAlign = "center";
      }
    } catch (err) {
      // keep server-rendered content if localStorage fails
    }
  }
})();
