/* Shared runtime: mobile filter/actions modalization for page-header */
(function (global) {
  "use strict";

  if (!global || global.BioTernMobileFilterActionsModal) {
    return;
  }

  function isMobile() {
    return global.matchMedia && global.matchMedia("(max-width: 991.98px)").matches;
  }

  function isElement(node) {
    return node && node.nodeType === 1;
  }

  function ensureModal(id, title) {
    var existing = document.getElementById(id);
    if (existing) {
      return existing;
    }
    var root = document.createElement("div");
    root.className = "modal fade biotern-popup-modal mobile-page-header-modal";
    root.id = id;
    root.tabIndex = -1;
    root.setAttribute("aria-hidden", "true");
    root.innerHTML =
      '<div class="modal-dialog modal-dialog-scrollable"><div class="modal-content">' +
      '<div class="modal-header"><h5 class="modal-title"></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>' +
      '<div class="modal-body"></div></div></div>';
    var titleNode = root.querySelector(".modal-title");
    if (titleNode) {
      titleNode.textContent = title;
    }
    document.body.appendChild(root);
    return root;
  }

  function firstFilterCard(contentRoot) {
    var all = allFilterCards(contentRoot);
    return all.length ? all[0] : null;
  }

  function allFilterCards(contentRoot) {
    if (!isElement(contentRoot)) {
      return [];
    }

    var matches = [];
    var seen = [];

    function pushUnique(node) {
      if (!node || !isElement(node)) {
        return;
      }
      if (seen.indexOf(node) !== -1) {
        return;
      }
      seen.push(node);
      matches.push(node);
    }

    var knownFilterForms = contentRoot.querySelectorAll(
      "form.filter-form, form.app-ojt-filter-form, form.login-logs-auto-filter, form.admin-logs-auto-filter, form.chatreports-auto-filter, form.chat-penalties-auto-filter, form.required-report-server-filters, .chatlogs-filter form, .logs-filter-wrap form, .report-filter-wrap form, .chatreports-filter-wrap, .attendance-exceptions-toolbar form, #studentsFilterForm, #ojtFilterForm"
    );
    for (var f = 0; f < knownFilterForms.length; f += 1) {
      var formNode = knownFilterForms[f];
      var formContainer =
        formNode.closest(".filter-panel, .filter-card, .app-ojt-filter-card, .logs-filter-wrap, .report-filter-wrap, .chatreports-filter-wrap, .chatlogs-filter, .attendance-exceptions-toolbar, .card") ||
        formNode;
      pushUnique(formContainer);
    }

    var cards = contentRoot.querySelectorAll(".card");
    for (var i = 0; i < cards.length; i += 1) {
      var card = cards[i];
      var header = card.querySelector(".card-header");
      var form = card.querySelector("form");
      if (!header || !form) {
        continue;
      }
      if (/filter/i.test(header.textContent || "")) {
        pushUnique(card);
      }
    }

    // Fallback: include likely GET-based filter forms (for pages where filter
    // controls are in plain cards/rows without dedicated filter class names).
    var getForms = contentRoot.querySelectorAll("form[method='get']");
    for (var gf = 0; gf < getForms.length; gf += 1) {
      var getForm = getForms[gf];
      if (!getForm || !isElement(getForm)) {
        continue;
      }
      if (getForm.classList.contains("chatreports-action-form")) {
        continue;
      }
      if (getForm.closest(".pagination, nav")) {
        continue;
      }
      var fieldCount = getForm.querySelectorAll("select, input[type='date'], input[type='search'], input[type='text'], input[type='number']").length;
      var hasSubmit = !!getForm.querySelector("button[type='submit'], input[type='submit']");
      if (fieldCount < 1 || !hasSubmit) {
        continue;
      }
      var host =
        getForm.closest(".logs-filter-wrap, .report-filter-wrap, .chatreports-filter-wrap, .chatlogs-filter, .attendance-exceptions-toolbar, .card") ||
        getForm;
      pushUnique(host);
    }
    return matches;
  }

  function syncCloneFromSource(sourceForm, cloneForm) {
    if (!sourceForm || !cloneForm) {
      return;
    }
    var sourceFields = sourceForm.querySelectorAll("input, select, textarea");
    for (var i = 0; i < sourceFields.length; i += 1) {
      var field = sourceFields[i];
      var name = field.getAttribute("name");
      if (!name) {
        continue;
      }
      var cloneField = cloneForm.querySelector('[name="' + name.replace(/"/g, '\\"') + '"]');
      if (!cloneField) {
        continue;
      }
      if (field.type === "checkbox" || field.type === "radio") {
        cloneField.checked = field.checked;
      } else {
        cloneField.value = field.value;
      }
    }
  }

  function syncSourceFromClone(sourceForm, cloneForm) {
    if (!sourceForm || !cloneForm) {
      return;
    }
    var cloneFields = cloneForm.querySelectorAll("input, select, textarea");
    for (var i = 0; i < cloneFields.length; i += 1) {
      var field = cloneFields[i];
      var name = field.getAttribute("name");
      if (!name) {
        continue;
      }
      var sourceField = sourceForm.querySelector('[name="' + name.replace(/"/g, '\\"') + '"]');
      if (!sourceField) {
        continue;
      }
      if (field.type === "checkbox" || field.type === "radio") {
        sourceField.checked = field.checked;
      } else {
        sourceField.value = field.value;
      }
      sourceField.dispatchEvent(new Event("change", { bubbles: true }));
    }
  }

  function findFilterSourceForm() {
    var explicit =
      document.querySelector("#studentsFilterForm") ||
      document.querySelector("#ojtFilterForm") ||
      document.querySelector("form.filter-form") ||
      document.querySelector("form.app-ojt-filter-form") ||
      document.querySelector("form.login-logs-auto-filter") ||
      document.querySelector("form.admin-logs-auto-filter") ||
      document.querySelector("form.chatreports-auto-filter") ||
      document.querySelector("form.chat-penalties-auto-filter") ||
      document.querySelector("form.required-report-server-filters");
    if (explicit) {
      return explicit;
    }

    var forms = document.querySelectorAll(".nxl-content form[method='get'], .main-content form[method='get'], form[method='get']");
    var best = null;
    var bestScore = -1;
    for (var i = 0; i < forms.length; i += 1) {
      var form = forms[i];
      if (!form || !form.querySelectorAll) {
        continue;
      }
      if (form.closest(".pagination, nav, .logs-pagination, .chatlogs-pagination")) {
        continue;
      }
      var controls = form.querySelectorAll("select, input[type='date'], input[type='search'], input[type='text'], input[type='number']");
      if (!controls.length) {
        continue;
      }
      var score = controls.length;
      if (form.className && /filter/i.test(form.className)) {
        score += 3;
      }
      if (form.closest(".logs-filter-wrap, .report-filter-wrap, .chatreports-filter-wrap, .chatlogs-filter, .attendance-exceptions-toolbar, .required-report-filters")) {
        score += 4;
      }
      if (score > bestScore) {
        best = form;
        bestScore = score;
      }
    }
    return best;
  }

  function ensureFilterModalFromSource(sourceForm, index) {
    if (!sourceForm) {
      return null;
    }
    var modalId = "mobileFiltersModal" + index;
    var modal = ensureModal(modalId, "Filters");
    var body = modal.querySelector(".modal-body");
    body.innerHTML = "";

    var cloneForm = sourceForm.cloneNode(true);
    cloneForm.classList.add("mobile-filter-form-clone");
    syncCloneFromSource(sourceForm, cloneForm);

    var footerRow = document.createElement("div");
    footerRow.className = "d-grid gap-2 mt-3";
    var applyBtn = document.createElement("button");
    applyBtn.type = "button";
    applyBtn.className = "btn btn-primary";
    applyBtn.textContent = "Apply Filters";
    footerRow.appendChild(applyBtn);
    cloneForm.appendChild(footerRow);
    body.appendChild(cloneForm);

    applyBtn.addEventListener("click", function () {
      syncSourceFromClone(sourceForm, cloneForm);
      var bsModal = global.bootstrap && global.bootstrap.Modal ? global.bootstrap.Modal.getOrCreateInstance(modal) : null;
      if (bsModal) {
        bsModal.hide();
      }
      sourceForm.submit();
    });

    modal.addEventListener("show.bs.modal", function () {
      syncCloneFromSource(sourceForm, cloneForm);
    });

    return modal;
  }

  function buildFilterModal(header, right, index) {
    var contentRoot =
      document.querySelector(".nxl-content .main-content") ||
      document.querySelector(".main-content") ||
      document.querySelector(".nxl-content") ||
      document;
    var filterCards = allFilterCards(contentRoot);
    var filterCard = filterCards.length ? filterCards[0] : null;
    if (!filterCard) {
      return;
    }
    var sourceForm = null;
    if (filterCard && filterCard.matches && filterCard.matches("form")) {
      sourceForm = filterCard;
    } else if (filterCard) {
      sourceForm = filterCard.querySelector("form");
    }
    if (!sourceForm) {
      sourceForm = findFilterSourceForm();
    }
    if (!sourceForm) {
      return;
    }

    for (var c = 0; c < filterCards.length; c += 1) {
      filterCards[c].classList.add("mobile-filters-hidden");
    }

    if (right.querySelector(".page-header-mobile-filter-toggle")) {
      return;
    }

    var modalId = "mobileFiltersModal" + index;
    var modal = ensureModal(modalId, "Filters");
    var body = modal.querySelector(".modal-body");

    function rebuildFilterBody() {
      body.innerHTML = "";
      var liveSource = sourceForm || findFilterSourceForm();
      if (!liveSource) {
        return;
      }
      var cloneForm = liveSource.cloneNode(true);
      cloneForm.classList.add("mobile-filter-form-clone");
      syncCloneFromSource(liveSource, cloneForm);

      var footerRow = document.createElement("div");
      footerRow.className = "d-grid gap-2 mt-3";
      var applyBtn = document.createElement("button");
      applyBtn.type = "button";
      applyBtn.className = "btn btn-primary";
      applyBtn.textContent = "Apply Filters";
      footerRow.appendChild(applyBtn);
      cloneForm.appendChild(footerRow);
      body.appendChild(cloneForm);

      applyBtn.addEventListener("click", function () {
        syncSourceFromClone(liveSource, cloneForm);
        var bsModal = global.bootstrap && global.bootstrap.Modal ? global.bootstrap.Modal.getOrCreateInstance(modal) : null;
        if (bsModal) {
          bsModal.hide();
        }
        liveSource.submit();
      });
    }

    rebuildFilterBody();
    modal.addEventListener("show.bs.modal", rebuildFilterBody);

    var btn = document.createElement("button");
    btn.type = "button";
    btn.className = "btn btn-sm btn-light-brand page-header-mobile-filter-toggle";
    btn.setAttribute("data-bs-toggle", "modal");
    btn.setAttribute("data-bs-target", "#" + modalId);
    btn.setAttribute("aria-label", "Open filters");
    btn.innerHTML = '<i class="feather-filter"></i><span>Filters</span>';
    right.insertBefore(btn, right.firstChild || null);
  }

  function buildSearchToggle(header, right, index) {
    if (!header || !right) {
      return;
    }
    if (right.querySelector(".page-header-mobile-search-toggle")) {
      return;
    }

    var searchHost =
      right.querySelector(".search-form") ||
      right.querySelector(".header-search-inline") ||
      right.querySelector(".header-search-inline-shell");
    var searchInput = searchHost
      ? searchHost.querySelector("input")
      : right.querySelector('input[type="search"], input[name*="search" i], input[id*="search" i]');

    if (!searchInput && !searchHost) {
      return;
    }

    var sourceNode = searchHost || searchInput.closest("form") || searchInput.parentElement;
    if (!sourceNode) {
      return;
    }

    var panelId = "mobilePageHeaderSearchPanel" + index;
    var existingPanel = document.getElementById(panelId);
    if (!existingPanel) {
      existingPanel = document.createElement("div");
      existingPanel.id = panelId;
      existingPanel.className = "mobile-page-header-search-panel";
      header.insertAdjacentElement("afterend", existingPanel);
    }

    var cloned = sourceNode.cloneNode(true);
    cloned.classList.add("mobile-page-header-search-clone");
    existingPanel.innerHTML = "";
    existingPanel.appendChild(cloned);

    sourceNode.classList.add("mobile-page-header-search-source-hidden");

    var btn = document.createElement("button");
    btn.type = "button";
    btn.className = "btn btn-sm btn-light-brand page-header-mobile-search-toggle";
    btn.setAttribute("aria-label", "Toggle search");
    btn.innerHTML = '<i class="feather-search"></i><span>Search</span>';
    btn.addEventListener("click", function () {
      var open = existingPanel.classList.toggle("is-open");
      btn.setAttribute("aria-expanded", open ? "true" : "false");
      if (open) {
        var firstInput = existingPanel.querySelector("input");
        if (firstInput) {
          setTimeout(function () {
            firstInput.focus();
          }, 10);
        }
      }
    });

    right.insertBefore(btn, right.firstChild || null);
  }

  function buildActionsModal(header, right, index) {
    var toggle =
      right.querySelector(".page-header-actions-toggle, .app-students-actions-toggle, .app-ojt-actions-toggle, .app-applications-actions-toggle, .app-page-header-auto-toggle, .page-header-right-open-toggle");
    var panel =
      right.querySelector(".page-header-actions, .app-students-actions-panel, .app-ojt-actions-panel, .app-applications-actions-panel, [class*='actions-panel']");
    var dropdownMenus = right.querySelectorAll(".dropdown-menu");
    var hasAnySource = !!(toggle || panel || (dropdownMenus && dropdownMenus.length));
    if (!hasAnySource) {
      return;
    }

    if (right.querySelector(".page-header-mobile-actions-toggle")) {
      return;
    }

    var modalId = "mobileActionsModal" + index;
    var modal = ensureModal(modalId, "Actions");
    var body = modal.querySelector(".modal-body");
    function rebuildActionsBody() {
      body.innerHTML = "";
      var cloneWrap = document.createElement("div");
      cloneWrap.className = "mobile-actions-grid";

      // Hard fallback: always expose Filters inside Actions modal when a filter form exists.
      var filterSourceForm = findFilterSourceForm();
      if (filterSourceForm) {
        var filterBtn = document.createElement("button");
        filterBtn.type = "button";
        filterBtn.className = "btn btn-light-brand";
        filterBtn.innerHTML = '<i class="feather-filter me-2"></i><span>Filters</span>';
        filterBtn.addEventListener("click", function () {
          var actionsModal = global.bootstrap && global.bootstrap.Modal ? global.bootstrap.Modal.getOrCreateInstance(modal) : null;
          if (actionsModal) {
            actionsModal.hide();
          }
          ensureFilterModalFromSource(filterSourceForm, index);
          var filterModalNode = document.getElementById("mobileFiltersModal" + index);
          var filterModal = global.bootstrap && global.bootstrap.Modal ? global.bootstrap.Modal.getOrCreateInstance(filterModalNode) : null;
          if (filterModal) {
            setTimeout(function () {
              filterModal.show();
            }, 100);
          }
        });
        cloneWrap.appendChild(filterBtn);
      }

      function pushActionNode(node) {
        if (!node || !node.cloneNode) {
          return;
        }
        if (
          node.matches &&
          node.matches(
            ".page-header-mobile-actions-toggle, .page-header-actions-toggle, .app-students-actions-toggle, .app-ojt-actions-toggle, .app-applications-actions-toggle, .app-page-header-auto-toggle, .page-header-right-open-toggle"
          )
        ) {
          return;
        }
        var clone = node.cloneNode(true);
        var clonedDesktopToggles = clone.querySelectorAll(
          ".page-header-actions-toggle, .app-students-actions-toggle, .app-ojt-actions-toggle, .app-applications-actions-toggle, .app-page-header-auto-toggle, .page-header-right-open-toggle"
        );
        for (var t = 0; t < clonedDesktopToggles.length; t += 1) {
          clonedDesktopToggles[t].remove();
        }
        clone.removeAttribute("id");

        // Keep parity with desktop actions: use only top-level controls.
        // For dropdown groups, include only the trigger control (not submenu items).
        var dropdownRoot = clone.matches && clone.matches(".dropdown") ? clone : clone.querySelector(".dropdown");
        if (dropdownRoot) {
          var dropdownTrigger = dropdownRoot.querySelector("a.btn, button.btn, .action-tile");
          if (dropdownTrigger) {
            var triggerClone = dropdownTrigger.cloneNode(true);
            triggerClone.removeAttribute("data-bs-toggle");
            triggerClone.removeAttribute("data-bs-target");
            triggerClone.removeAttribute("aria-expanded");
            triggerClone.classList.add("btn", "btn-light-brand");
            if (triggerClone.matches("a") && (!triggerClone.getAttribute("href") || /^javascript:/i.test(triggerClone.getAttribute("href")))) {
              triggerClone.setAttribute("href", "javascript:void(0)");
            }
            cloneWrap.appendChild(triggerClone);
            return;
          }
        }

        cloneWrap.appendChild(clone);
      }

      function pushActionDescendants(rootNode) {
        if (!rootNode || !rootNode.querySelectorAll) {
          return;
        }
        var candidates = rootNode.querySelectorAll(
          "a.action-tile, a.btn, button.btn, button.action-tile, [data-ojt-print-full], [data-ojt-print-selected]"
        );
        for (var c = 0; c < candidates.length; c += 1) {
          pushActionNode(candidates[c]);
        }
      }

      var primary = right.querySelector(".page-header-primary-actions");
      if (primary && primary.children && primary.children.length) {
        for (var p = 0; p < primary.children.length; p += 1) {
          pushActionNode(primary.children[p]);
        }
      }

      var sourceGrid =
        panel
          ? panel.querySelector(".dashboard-actions-grid") ||
            panel.querySelector(".page-header-right-items-wrapper") ||
            panel
          : null;

      if (sourceGrid && sourceGrid.children && sourceGrid.children.length) {
        for (var g = 0; g < sourceGrid.children.length; g += 1) {
          var sourceChild = sourceGrid.children[g];
          if (!sourceChild || !sourceChild.matches) {
            continue;
          }
          // Only include desktop quick-action items.
          if (
            sourceChild.matches(".action-tile, a.btn, button.btn, .dropdown, form") ||
            sourceChild.querySelector(".action-tile, a.btn, button.btn")
          ) {
            pushActionNode(sourceChild);
          }
        }
      } else if (sourceGrid) {
        if (
          sourceGrid.matches(".action-tile, a.btn, button.btn, .dropdown, form") ||
          sourceGrid.querySelector(".action-tile, a.btn, button.btn")
        ) {
          pushActionNode(sourceGrid);
        }
      }

      if (!cloneWrap.children.length) {
        // Fallback: grab only visible button-like controls from page-header-right.
        var visibleActions = right.querySelectorAll(".page-header-primary-actions > *, .page-header-right-items-wrapper > *, .dashboard-actions-grid > *");
        for (var x = 0; x < visibleActions.length; x += 1) {
          var visibleNode = visibleActions[x];
          if (
            visibleNode.matches(".action-tile, a.btn, button.btn, .dropdown, form") ||
            visibleNode.querySelector(".action-tile, a.btn, button.btn")
          ) {
            pushActionNode(visibleNode);
          }
        }
      }

      if (!cloneWrap.children.length) {
        // Deep fallback: if wrappers change, crawl descendants.
        pushActionDescendants(panel || right);
      }

      if (!cloneWrap.children.length && document.body) {
        // Last-resort fallback for OJT list pages so modal never renders empty.
        if (document.body.classList.contains("page-ojt-internal-list")) {
          var externalLink = document.createElement("a");
          externalLink.className = "btn btn-light-brand";
          externalLink.href = "ojt-external-list.php";
          externalLink.innerHTML = '<i class="feather-list me-2"></i><span>External List</span>';
          cloneWrap.appendChild(externalLink);
        } else if (document.body.classList.contains("page-ojt-external-list")) {
          var internalLink = document.createElement("a");
          internalLink.className = "btn btn-light-brand";
          internalLink.href = "ojt-internal-list.php";
          internalLink.innerHTML = '<i class="feather-list me-2"></i><span>Internal List</span>';
          cloneWrap.appendChild(internalLink);
        }
      }

      body.appendChild(cloneWrap);
    }

    if (panel) {
      panel.classList.remove("is-open");
      panel.classList.remove("show");
      panel.classList.add("mobile-actions-source-hidden");
    }
    if (toggle) {
      toggle.classList.add("mobile-actions-source-hidden");
    }

    var btn = document.createElement("button");
    btn.type = "button";
    btn.className = "btn btn-sm btn-light-brand page-header-mobile-actions-toggle";
    btn.setAttribute("data-bs-toggle", "modal");
    btn.setAttribute("data-bs-target", "#" + modalId);
    btn.setAttribute("aria-label", "Open actions");
    btn.innerHTML = '<i class="feather-grid"></i><span>Actions</span>';
    right.appendChild(btn);

    modal.addEventListener("show.bs.modal", function () {
      rebuildActionsBody();
    });
  }

  function cleanupDesktop() {
    var hiddenCards = document.querySelectorAll(".mobile-filters-hidden");
    for (var i = 0; i < hiddenCards.length; i += 1) {
      hiddenCards[i].classList.remove("mobile-filters-hidden");
    }
    var mobileButtons = document.querySelectorAll(".page-header-mobile-filter-toggle, .page-header-mobile-actions-toggle");
    for (var j = 0; j < mobileButtons.length; j += 1) {
      mobileButtons[j].remove();
    }
    var desktopToggles = document.querySelectorAll(".page-header-actions-toggle, .app-page-header-auto-toggle");
    for (var k = 0; k < desktopToggles.length; k += 1) {
      desktopToggles[k].style.display = "";
    }
    var hiddenSearchSources = document.querySelectorAll(".mobile-page-header-search-source-hidden");
    for (var s = 0; s < hiddenSearchSources.length; s += 1) {
      hiddenSearchSources[s].classList.remove("mobile-page-header-search-source-hidden");
    }
    var searchPanels = document.querySelectorAll(".mobile-page-header-search-panel");
    for (var p = 0; p < searchPanels.length; p += 1) {
      searchPanels[p].remove();
    }
  }

  function dedupeActionsButtons(right) {
    if (!right) {
      return;
    }
    var legacy = right.querySelectorAll(
      ".page-header-actions-toggle, .app-students-actions-toggle, .app-ojt-actions-toggle, .app-applications-actions-toggle, .page-header-right-open-toggle, .app-page-header-auto-toggle"
    );
    for (var i = 0; i < legacy.length; i += 1) {
      var legacyNode = legacy[i];
      legacyNode.classList.add("mobile-actions-source-hidden");
    }

    var mobileButtons = right.querySelectorAll(".page-header-mobile-actions-toggle");
    if (mobileButtons.length > 1) {
      for (var j = 0; j < mobileButtons.length - 1; j += 1) {
        mobileButtons[j].remove();
      }
    }

    // Hard dedupe: keep only one grid-style action trigger in the mobile header,
    // even when another runtime injects alternate toggle classes/buttons.
    var gridLike = [];
    var candidates = right.querySelectorAll("button, a");
    for (var g = 0; g < candidates.length; g += 1) {
      var node = candidates[g];
      if (!node || !node.querySelector) {
        continue;
      }
      var icon = node.querySelector("i");
      var iconClass = icon ? (icon.className || "") : "";
      var isGridIcon = /feather-grid/.test(iconClass);
      var isKnownToggle =
        node.matches(
          ".page-header-actions-toggle, .app-students-actions-toggle, .app-ojt-actions-toggle, .app-applications-actions-toggle, .page-header-right-open-toggle, .app-page-header-auto-toggle, .page-header-mobile-actions-toggle"
        );
      if (isGridIcon || isKnownToggle) {
        gridLike.push(node);
      }
    }

    if (gridLike.length > 1) {
      for (var r = 0; r < gridLike.length - 1; r += 1) {
        gridLike[r].remove();
      }
    }
  }

  function forceHideMobileFilterPanels() {
    if (!document.body || !isMobile()) {
      return;
    }
    var pageClass = document.body.className || "";
    var isTargetPage =
      pageClass.indexOf("page-ojt-internal-list") !== -1 ||
      pageClass.indexOf("page-ojt-external-list") !== -1 ||
      pageClass.indexOf("page-fingerprint-mapping") !== -1 ||
      pageClass.indexOf("page-attendance") !== -1 ||
      pageClass.indexOf("page-students") !== -1 ||
      pageClass.indexOf("students-page") !== -1 ||
      pageClass.indexOf("app-page-ojt-dashboard") !== -1;
    if (!isTargetPage) {
      return;
    }

    var panels = document.querySelectorAll(".bio-console-panel, .fingerprint-map-card, .filter-card, .app-ojt-filter-card");
    for (var i = 0; i < panels.length; i += 1) {
      var panel = panels[i];
      if (!panel || !panel.querySelector) {
        continue;
      }
      var form = panel.querySelector("form");
      var hasFilterForm =
        form &&
        (form.matches(".filter-form, .app-ojt-filter-form, .login-logs-auto-filter, .admin-logs-auto-filter") ||
          /filter/i.test((panel.textContent || "").slice(0, 220)));
      if (hasFilterForm) {
        // Do not hide full data panels (some pages keep filters + tables in one card).
        // Only hide the filter area itself when possible.
        if (panel.classList.contains("bio-console-panel")) {
          var filterBody = form.closest(".card-body");
          if (filterBody) {
            filterBody.style.display = "none";
          }
          var filterHeader = panel.querySelector(".card-header");
          if (filterHeader && /filter/i.test(filterHeader.textContent || "")) {
            filterHeader.style.display = "none";
          }
        } else {
          panel.style.display = "none";
        }
      }
    }
  }

  function boot() {
    if (!document.body || !document.body.classList.contains("mobile-bottom-nav")) {
      return;
    }
    if (!isMobile()) {
      cleanupDesktop();
      return;
    }
    var headers = document.querySelectorAll(".nxl-content > .page-header, .page-header");
    for (var i = 0; i < headers.length; i += 1) {
      var header = headers[i];
      if (!isElement(header)) {
        continue;
      }
      var right = header.querySelector(".page-header-right");
      if (!right) {
        // Some report pages render left + middle only; create a right action slot
        // so mobile filter/actions modal buttons can still mount.
        right = document.createElement("div");
        right.className = "page-header-right ms-auto";
        var rightItems = document.createElement("div");
        rightItems.className = "page-header-right-items d-flex";
        var rightWrap = document.createElement("div");
        rightWrap.className = "d-flex align-items-center gap-2 page-header-right-items-wrapper";
        rightItems.appendChild(rightWrap);
        right.appendChild(rightItems);
        header.appendChild(right);
      }

      // Filter/Search modalization should work independently from actions panel availability.
      buildFilterModal(header, right, i + 1);
      // Keep header controls minimal on filter-driven list/table pages:
      // if a filter toggle is present, skip adding search toggle.
      if (!right.querySelector(".page-header-mobile-filter-toggle")) {
        buildSearchToggle(header, right, i + 1);
      }

      // Actions modalization requires an actions source in this header.
      if (right.querySelector(".page-header-actions, .app-students-actions-panel, .app-ojt-actions-panel, .app-applications-actions-panel, [class*='actions-panel'], .page-header-actions-toggle, .app-page-header-auto-toggle")) {
        buildActionsModal(header, right, i + 1);
        dedupeActionsButtons(right);
      }
    }
    forceHideMobileFilterPanels();
  }

  global.BioTernMobileFilterActionsModal = {
    boot: boot,
  };

  global.addEventListener("resize", function () {
    boot();
  });

  if ("MutationObserver" in global) {
    var mobileObserver = new MutationObserver(function () {
      if (isMobile()) {
        boot();
      }
    });
    mobileObserver.observe(document.documentElement || document.body, {
      childList: true,
      subtree: true,
    });
  }

  if (global.BioTernRuntimeBoot && typeof global.BioTernRuntimeBoot.boot === "function") {
    global.BioTernRuntimeBoot.boot({
      name: "mobile-filter-actions-modal",
      run: boot,
    });
    return;
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})(window);
