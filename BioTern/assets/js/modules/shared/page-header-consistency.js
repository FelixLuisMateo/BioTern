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
      ".page-header-actions, .page-header-right-items, .app-students-actions-panel, .app-ojt-actions-panel, .app-applications-actions-panel"
    );
  }

  function findToggle(right) {
    return directChildMatch(
      right,
      ".page-header-actions-toggle, .app-students-actions-toggle, .app-ojt-actions-toggle, .app-applications-actions-toggle, .page-header-right-open-toggle, .app-page-header-auto-toggle"
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

    var isBootstrapCollapse = toggle.getAttribute("data-bs-toggle") === "collapse" && panel.classList.contains("collapse");
    if (!isBootstrapCollapse) {
      toggle.addEventListener("click", function (event) {
        event.preventDefault();
        var next = !openState(panel);
        setOpen(panel, toggle, next);
      });
    } else {
      toggle.addEventListener("click", function () {
        global.setTimeout(function () {
          toggle.setAttribute("aria-expanded", panel.classList.contains("show") ? "true" : "false");
        }, 10);
      });
    }

    document.addEventListener("click", function (event) {
      if (!isMobileViewport()) {
        return;
      }
      if (header.contains(event.target)) {
        return;
      }
      setOpen(panel, toggle, false);
      if (isBootstrapCollapse && panel.classList.contains("show")) {
        panel.classList.remove("show");
      }
    });
  }

  function normalizeHeader(header) {
    if (!isElement(header) || header.dataset.phcReady === "1") {
      return;
    }
    header.dataset.phcReady = "1";

    var right = header.querySelector(".page-header-right");
    if (!right) {
      return;
    }

    var panel = findRightPanel(right);
    var toggle = findToggle(right);

    if (panel) {
      condenseHeaderActions(header, right, panel);
    }

    if (!toggle && panel && panelHasActions(panel)) {
      toggle = createAutoToggle(right);
    }

    if (toggle && panel) {
      bindPair(header, toggle, panel);
    }
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
