/* Shared runtime: page-header consistency + mobile action toggles */
(function (global) {
  "use strict";

  if (!global || global.BioTernPageHeaderConsistency) {
    return;
  }

  function isMobileViewport() {
    return global.matchMedia && global.matchMedia("(max-width: 991.98px)").matches;
  }

  function isElement(node) {
    return node && node.nodeType === 1;
  }

  function directChildMatch(parent, selector) {
    if (!isElement(parent)) {
      return null;
    }
    var children = parent.children || [];
    for (var i = 0; i < children.length; i += 1) {
      if (children[i].matches(selector)) {
        return children[i];
      }
    }
    return null;
  }

  function findRightPanel(right) {
    return directChildMatch(
      right,
      ".page-header-actions, .page-header-right-items, .app-students-actions-panel, .app-ojt-actions-panel, .app-applications-actions-panel, [class*='actions-panel']"
    );
  }

  function findToggle(right) {
    return directChildMatch(
      right,
      ".page-header-actions-toggle, .app-students-actions-toggle, .app-ojt-actions-toggle, .app-applications-actions-toggle, .app-ojt-workflow-actions-toggle, .page-header-right-open-toggle, .app-page-header-auto-toggle, [class*='actions-toggle']"
    );
  }

  function panelHasActions(panel) {
    if (!panel) {
      return false;
    }
    return !!panel.querySelector("a, button, .dropdown, form");
  }

  function findPrimaryContainer(right) {
    if (!right) {
      return null;
    }
    return right.querySelector(".page-header-primary-actions");
  }

  function ensurePrimaryContainer(right, beforeNode) {
    var existing = findPrimaryContainer(right);
    if (existing) {
      return existing;
    }
    var primary = document.createElement("div");
    primary.className = "page-header-primary-actions d-none d-md-flex align-items-center gap-2";
    if (beforeNode && right.contains(beforeNode)) {
      right.insertBefore(primary, beforeNode);
    } else {
      right.appendChild(primary);
    }
    return primary;
  }

  function findActionsWrapper(panel) {
    if (!panel) {
      return null;
    }
    return panel.querySelector(".page-header-right-items-wrapper") || panel;
  }

  function collectActionNodes(wrapper) {
    if (!wrapper) {
      return [];
    }
    var nodes = [];
    var children = wrapper.children || [];
    for (var i = 0; i < children.length; i += 1) {
      var child = children[i];
      if (!isElement(child)) {
        continue;
      }
      if (child.classList.contains("page-header-primary-actions")) {
        continue;
      }
      if (child.matches(".page-header-actions-toggle, .app-page-header-auto-toggle")) {
        continue;
      }
      if (
        child.matches(".dropdown") ||
        child.matches("form") ||
        child.matches("a.btn") ||
        child.matches("button.btn")
      ) {
        nodes.push(child);
      }
    }
    return nodes;
  }

  function actionPriorityScore(node) {
    if (!node) {
      return 99;
    }
    var priorityAttr = node.getAttribute("data-action-priority");
    if (priorityAttr) {
      var parsed = parseInt(priorityAttr, 10);
      if (!isNaN(parsed)) {
        return parsed;
      }
    }
    var priorityClasses = ["btn-primary", "btn-success", "btn-warning", "btn-danger", "btn-info"];
    for (var i = 0; i < priorityClasses.length; i += 1) {
      var cls = priorityClasses[i];
      if (node.classList.contains(cls) || (node.querySelector && node.querySelector("." + cls))) {
        return 10 + i;
      }
    }
    return 50;
  }

  function pickPrimaryActions(actions) {
    if (!actions || actions.length === 0) {
      return [];
    }
    var indexed = actions.map(function (node, index) {
      return { node: node, index: index, score: actionPriorityScore(node) };
    });
    indexed.sort(function (a, b) {
      if (a.score !== b.score) {
        return a.score - b.score;
      }
      return a.index - b.index;
    });
    var picked = [];
    for (var i = 0; i < indexed.length && picked.length < 2; i += 1) {
      picked.push(indexed[i].node);
    }
    return picked;
  }

  function condenseHeaderActions(header, right, panel) {
    if (!header || header.dataset.phcCondensed === "1") {
      return;
    }
    var wrapper = findActionsWrapper(panel);
    var actions = collectActionNodes(wrapper);
    if (actions.length <= 2) {
      return;
    }
    var primaryActions = pickPrimaryActions(actions);
    if (primaryActions.length === 0) {
      return;
    }

    var primaryContainer = ensurePrimaryContainer(right, panel);

    actions.forEach(function (node) {
      if (node.parentNode) {
        node.parentNode.removeChild(node);
      }
    });

    primaryActions.forEach(function (node) {
      primaryContainer.appendChild(node);
    });

    actions.forEach(function (node) {
      if (primaryActions.indexOf(node) === -1) {
        wrapper.appendChild(node);
      }
    });

    header.classList.add("page-header-condensed-actions");
    if (panel) {
      panel.classList.add("page-header-actions");
      panel.classList.remove("d-md-flex");
      panel.classList.remove("d-lg-flex");
      panel.classList.remove("d-xl-flex");
    }

    header.dataset.phcCondensed = "1";
  }

  function createAutoToggle(right) {
    var existing = right.querySelector(".page-header-actions-toggle.app-page-header-auto-toggle");
    if (existing) {
      return existing;
    }
    var toggle = document.createElement("button");
    toggle.type = "button";
    toggle.className = "btn btn-light-brand page-header-actions-toggle app-page-header-auto-toggle";
    toggle.setAttribute("aria-expanded", "false");
    toggle.setAttribute("aria-label", "Header actions");
    toggle.innerHTML = '<i class="feather-grid me-1"></i><span>Actions</span>';
    right.appendChild(toggle);
    return toggle;
  }

  function revealToggleAcrossViewports(toggle, right) {
    if (!toggle || !right) {
      return;
    }

    var cursor = toggle.parentElement;
    while (cursor && cursor !== right) {
      if (cursor.classList) {
        cursor.classList.remove("d-none");
        cursor.classList.remove("d-md-none");
        cursor.classList.remove("d-lg-none");
      }
      cursor = cursor.parentElement;
    }
  }

  function normalizeTogglePresentation(toggle, right) {
    if (!toggle) {
      return;
    }

    revealToggleAcrossViewports(toggle, right);
    toggle.classList.add("page-header-actions-toggle");
    toggle.classList.add("btn");
    toggle.classList.add("btn-sm");
    toggle.classList.remove("app-students-actions-toggle");
    toggle.classList.remove("app-ojt-actions-toggle");
    toggle.classList.remove("app-applications-actions-toggle");
    toggle.classList.remove("app-ojt-workflow-actions-toggle");
    toggle.classList.remove("page-header-right-open-toggle");
    toggle.setAttribute("aria-label", "Header actions");
    toggle.setAttribute("type", "button");

    // Keep one unified menu behavior instead of per-page bootstrap collapse wiring.
    toggle.removeAttribute("data-bs-toggle");
    toggle.removeAttribute("data-bs-target");

    var icon = toggle.querySelector("i");
    var text = toggle.querySelector("span");
    if (!icon || !text || text.textContent.trim().toLowerCase() !== "actions") {
      toggle.innerHTML = '<i class="feather-grid me-1"></i><span>Actions</span>';
    }

    // Preserve existing page color style; default to light-brand only when none is present.
    var hasColorClass =
      toggle.classList.contains("btn-primary") ||
      toggle.classList.contains("btn-light-brand") ||
      toggle.classList.contains("btn-outline-secondary") ||
      toggle.classList.contains("btn-light") ||
      toggle.classList.contains("btn-secondary");
    if (!hasColorClass) {
      toggle.classList.add("btn-light-brand");
    }
  }

  function normalizePanelPresentation(panel, toggle) {
    if (!panel) {
      return;
    }

    panel.classList.add("page-header-actions");
    panel.classList.remove("collapse");
    panel.classList.remove("show");
    panel.classList.remove("d-md-flex");
    panel.classList.remove("d-lg-flex");
    panel.classList.remove("d-xl-flex");

    if (toggle && panel.id) {
      toggle.setAttribute("aria-controls", panel.id);
    }
  }

  function directChildButton(node) {
    if (!node || !node.children) {
      return null;
    }
    for (var i = 0; i < node.children.length; i += 1) {
      var child = node.children[i];
      if (child.matches("a.btn, button.btn")) {
        return child;
      }
    }
    return null;
  }

  function ensureActionTileClass(button) {
    if (!button) {
      return;
    }

    button.classList.add("action-tile");
    button.classList.remove("btn-sm");
    if (button.classList.contains("btn-primary")) {
      button.classList.add("action-tile-primary");
    }
  }

  function normalizeActionNode(node) {
    if (!node) {
      return;
    }

    if (node.matches("a.btn, button.btn")) {
      ensureActionTileClass(node);
      return;
    }

    if (node.matches(".dropdown")) {
      var trigger = directChildButton(node);
      ensureActionTileClass(trigger);
      node.classList.add("app-page-action-dropdown");
      return;
    }

    if (node.matches("form")) {
      node.classList.add("app-page-action-form");
      var formBtn = directChildButton(node);
      ensureActionTileClass(formBtn);
    }
  }

  function ensureHomepageActionsPanel(panel) {
    if (!panel) {
      return;
    }

    var wrapper = findActionsWrapper(panel);
    if (!wrapper) {
      return;
    }

    if (directChildMatch(panel, ".dashboard-actions-panel")) {
      var existingTiles = panel.querySelectorAll(".dashboard-actions-grid > *");
      for (var i = 0; i < existingTiles.length; i += 1) {
        normalizeActionNode(existingTiles[i]);
      }
      return;
    }

    var existingChildren = [];
    var children = Array.prototype.slice.call(wrapper.children || []);
    for (var j = 0; j < children.length; j += 1) {
      var child = children[j];
      if (!isElement(child)) {
        continue;
      }
      if (child.matches(".dashboard-actions-panel")) {
        return;
      }
      existingChildren.push(child);
    }

    var actionsPanel = document.createElement("div");
    actionsPanel.className = "dashboard-actions-panel app-page-actions-panel biotern-backdrop-glass";

    var meta = document.createElement("div");
    meta.className = "dashboard-actions-meta";
    meta.innerHTML = '<span class="text-muted fs-12">Quick Actions</span>';

    var grid = document.createElement("div");
    grid.className = "dashboard-actions-grid app-page-actions-grid";

    for (var k = 0; k < existingChildren.length; k += 1) {
      var node = existingChildren[k];
      grid.appendChild(node);
      normalizeActionNode(node);
    }

    actionsPanel.appendChild(meta);
    actionsPanel.appendChild(grid);
    wrapper.classList.add("app-page-actions-wrapper");
    wrapper.appendChild(actionsPanel);
  }

  function openState(panel) {
    if (!panel) {
      return false;
    }
    return panel.classList.contains("is-open") || panel.classList.contains("show");
  }

  function setOpen(panel, toggle, shouldOpen) {
    if (!panel || !toggle) {
      return;
    }
    panel.classList.toggle("is-open", !!shouldOpen);
    toggle.setAttribute("aria-expanded", shouldOpen ? "true" : "false");
  }

  function bindPair(header, toggle, panel) {
    if (!toggle || !panel || toggle.dataset.phcBound === "1") {
      return;
    }
    toggle.dataset.phcBound = "1";

    toggle.addEventListener("click", function (event) {
      event.preventDefault();
      var next = !openState(panel);
      setOpen(panel, toggle, next);
    });

    document.addEventListener("click", function (event) {
      if (header.contains(event.target)) {
        return;
      }
      setOpen(panel, toggle, false);
    });
  }

  function normalizeHeader(header) {
    if (!isElement(header) || header.dataset.phcReady === "1") {
      return;
    }
    if (header.dataset.phcSkip === "1") {
      return;
    }
    header.dataset.phcReady = "1";

    var right = header.querySelector(".page-header-right");
    if (!right) {
      return;
    }

    var panel = findRightPanel(right);
    var toggle = findToggle(right);

    if (!panel) {
      return;
    }

    // Preserve homepage action menu as-is.
    if (panel.id === "dashboardPageActions") {
      return;
    }

    if (!toggle) {
      var existingActions = collectActionNodes(findActionsWrapper(panel));
      if (existingActions.length > 2) {
        toggle = createAutoToggle(right);
      }
    }

    if (!toggle) {
      return;
    }

    normalizePanelPresentation(panel, toggle);
    condenseHeaderActions(header, right, panel);
    ensureHomepageActionsPanel(panel);
    normalizeTogglePresentation(toggle, right);
    bindPair(header, toggle, panel);
  }

  function normalizeAll(root) {
    var base = isElement(root) ? root : document;
    var headers = base.querySelectorAll ? base.querySelectorAll(".page-header") : [];
    for (var i = 0; i < headers.length; i += 1) {
      normalizeHeader(headers[i]);
    }
  }

  function bindResizeClose() {
    global.addEventListener("resize", function () {
      if (isMobileViewport()) {
        return;
      }
      var headers = document.querySelectorAll(".page-header");
      for (var i = 0; i < headers.length; i += 1) {
        var right = headers[i].querySelector(".page-header-right");
        if (!right) {
          continue;
        }
        var panel = findRightPanel(right);
        var toggle = findToggle(right);
        if (panel && toggle) {
          setOpen(panel, toggle, false);
        }
        if (panel && panel.classList.contains("show")) {
          panel.classList.remove("show");
        }
      }
    });
  }

  function observe() {
    if (!("MutationObserver" in global)) {
      return;
    }
    var observer = new MutationObserver(function (mutations) {
      for (var i = 0; i < mutations.length; i += 1) {
        var added = mutations[i].addedNodes;
        for (var j = 0; j < added.length; j += 1) {
          if (isElement(added[j])) {
            normalizeAll(added[j]);
          }
        }
      }
    });
    observer.observe(document.body, { childList: true, subtree: true });
  }

  function boot() {
    normalizeAll(document);
    bindResizeClose();
    observe();
  }

  global.BioTernPageHeaderConsistency = {
    boot: boot,
    refresh: normalizeAll,
  };

  global.AppCore = global.AppCore || {};
  global.AppCore.PageHeader = global.BioTernPageHeaderConsistency;

  if (global.BioTernRuntimeBoot && typeof global.BioTernRuntimeBoot.boot === "function") {
    global.BioTernRuntimeBoot.boot({
      name: "page-header-consistency",
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
