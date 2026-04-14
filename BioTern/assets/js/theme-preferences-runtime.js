/* Footer theme preferences runtime migrated from includes/footer.php */
(function () {
  "use strict";

  var uiStateCore = window.BioTernUiStateCore || null;
  var themeStateCore = window.BioTernThemeStateCore || null;

  function storageGet(key, fallbackValue) {
    if (uiStateCore && uiStateCore.storage && typeof uiStateCore.storage.get === "function") {
      return uiStateCore.storage.get(key, fallbackValue);
    }
    try {
      var value = localStorage.getItem(key);
      return value === null ? fallbackValue : value;
    } catch (e) {
      return fallbackValue;
    }
  }

  function storageSet(key, value) {
    if (uiStateCore && uiStateCore.storage && typeof uiStateCore.storage.set === "function") {
      return uiStateCore.storage.set(key, value);
    }
    try {
      localStorage.setItem(key, String(value));
      return true;
    } catch (e) {
      return false;
    }
  }

  function storageRemove(key) {
    if (uiStateCore && uiStateCore.storage && typeof uiStateCore.storage.remove === "function") {
      return uiStateCore.storage.remove(key);
    }
    try {
      localStorage.removeItem(key);
      return true;
    } catch (e) {
      return false;
    }
  }

  function storageHasAny(keys) {
    if (uiStateCore && uiStateCore.storage && typeof uiStateCore.storage.hasAny === "function") {
      return uiStateCore.storage.hasAny(keys);
    }
    try {
      for (var i = 0; i < keys.length; i += 1) {
        if (localStorage.getItem(keys[i]) !== null) {
          return true;
        }
      }
    } catch (e) {
      return false;
    }
    return false;
  }

  function onDomReady(callback) {
    if (uiStateCore && uiStateCore.dom && typeof uiStateCore.dom.onReady === "function") {
      uiStateCore.dom.onReady(callback);
      return;
    }
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", callback);
    } else {
      callback();
    }
  }

  function hydrateThemeRuntimeConfigFromBody() {
    if (!document || !document.body) {
      return;
    }

    var prefsRaw = document.body.getAttribute("data-theme-prefs") || "";
    var apiRaw = document.body.getAttribute("data-theme-api") || "";

    if (!window.__bioternThemePrefs && prefsRaw) {
      try {
        window.__bioternThemePrefs = JSON.parse(prefsRaw);
      } catch (err) {
        window.__bioternThemePrefs = {};
      }
    }

    if (!window.__bioternThemeApi && apiRaw) {
      window.__bioternThemeApi = apiRaw;
    }
  }

  function initFooterThemeRuntime() {
    if (window.__bioternFooterThemeRuntimeInit) {
      return;
    }
    window.__bioternFooterThemeRuntimeInit = true;

    hydrateThemeRuntimeConfigFromBody();
    var serverPrefs = window.__bioternThemePrefs || {};
    var themeUserId = 0;
    try {
      var bodyThemeUserIdRaw =
        document && document.body
          ? document.body.getAttribute("data-theme-user-id")
          : "0";
      var parsedThemeUserId = parseInt(bodyThemeUserIdRaw || "0", 10);
      themeUserId = Number.isFinite(parsedThemeUserId) && parsedThemeUserId > 0 ? parsedThemeUserId : 0;
    } catch (e) {
      themeUserId = 0;
    }

    function themePrefsStorageKey() {
      return themeUserId > 0
        ? "biotern_theme_preferences_user_" + String(themeUserId)
        : "biotern_theme_preferences";
    }

    function themePrefsCookieName() {
      return themeUserId > 0
        ? "biotern_theme_preferences_u_" + String(themeUserId)
        : "biotern_theme_preferences";
    }

    function loadLocalThemePrefs() {
      try {
        var stored = storageGet(themePrefsStorageKey(), null);
        if (!stored && themeUserId <= 0) {
          stored = storageGet("biotern_theme_preferences", null);
        }
        if (!stored) return null;
        var parsed = JSON.parse(stored);
        if (parsed && typeof parsed === "object") return parsed;
      } catch (e) {}
      return null;
    }

    var localPrefs = loadLocalThemePrefs();
    if (localPrefs) {
      serverPrefs = Object.assign({}, serverPrefs, localPrefs);
    }

    function normalizeMenuPreference(value) {
      if (
        themeStateCore &&
        typeof themeStateCore.normalizeMenuPreference === "function"
      ) {
        return themeStateCore.normalizeMenuPreference(value);
      }
      return value === "mini" || value === "expanded" ? value : "auto";
    }

    function normalizeScheme(value) {
      if (themeStateCore && typeof themeStateCore.normalizeScheme === "function") {
        return themeStateCore.normalizeScheme(value);
      }
      var normalized = String(value == null ? "" : value).toLowerCase().trim();
      normalized = normalized.replace(/[^a-z0-9-]+/g, "-").replace(/-+/g, "-").replace(/^-|-$/g, "");
      return normalized || "blue";
    }

    function normalizeSurfacesMode(value) {
      if (
        themeStateCore &&
        typeof themeStateCore.normalizeSurfacesMode === "function"
      ) {
        return themeStateCore.normalizeSurfacesMode(value);
      }
      return value === "independent" ? "independent" : "linked";
    }

    var runtimePrefs = {
      skin: serverPrefs.skin === "dark" ? "dark" : "light",
      menu: normalizeMenuPreference(serverPrefs.menu),
      font:
        typeof serverPrefs.font === "string" && serverPrefs.font !== ""
          ? serverPrefs.font
          : "app-font-family-montserrat",
      navigation: serverPrefs.navigation === "dark" ? "dark" : "light",
      header: serverPrefs.header === "dark" ? "dark" : "light",
      scheme:
        typeof serverPrefs.scheme === "string"
          ? normalizeScheme(serverPrefs.scheme)
          : "blue",
      surfaces:
        typeof serverPrefs.surfaces === "string"
          ? normalizeSurfacesMode(serverPrefs.surfaces)
          : "linked",
    };
    var defaultThemePrefs = {
      skin: "light",
      menu: "auto",
      font: "app-font-family-montserrat",
      scheme: "blue",
      navigation: "light",
      header: "light",
      surfaces: "linked",
    };

    var darkBtn =
      document.querySelector(".nxl-header .dark-light-theme .dark-button") ||
      document.querySelector(".nxl-header .dark-button") ||
      document.querySelector("a.dark-button,button.dark-button");
    var lightBtn =
      document.querySelector(".nxl-header .dark-light-theme .light-button") ||
      document.querySelector(".nxl-header .light-button") ||
      document.querySelector("a.light-button,button.light-button");
    var pageSkinLight = document.getElementById("theme-page-skin-light");
    var pageSkinDark = document.getElementById("theme-page-skin-dark");
    var pageMenu = document.getElementById("theme-page-menu");
    var pageFont = document.getElementById("theme-page-font");
    var pageScheme = document.getElementById("theme-page-scheme");
    var pageNavigation = document.getElementById("theme-page-navigation");
    var pageHeader = document.getElementById("theme-page-header");
    var pageSurfaces = document.getElementById("theme-page-surfaces");
    var pageSurfacesIndependent = document.getElementById(
      "theme-page-surfaces-independent"
    );
    var pageSurfacesCard = document.getElementById("theme-page-surfaces-card");
    var pageSave = document.getElementById("theme-page-save");
    var pageReset = document.getElementById("theme-page-reset");
    var allowedFonts =
      themeStateCore && Array.isArray(themeStateCore.allowedFonts)
        ? themeStateCore.allowedFonts.slice()
        : [
            "app-font-family-inter",
            "app-font-family-lato",
            "app-font-family-rubik",
            "app-font-family-cinzel",
            "app-font-family-nunito",
            "app-font-family-roboto",
            "app-font-family-ubuntu",
            "app-font-family-poppins",
            "app-font-family-raleway",
            "app-font-family-system-ui",
            "app-font-family-noto-sans",
            "app-font-family-fira-sans",
            "app-font-family-work-sans",
            "app-font-family-open-sans",
            "app-font-family-montserrat",
            "app-font-family-maven-pro",
            "app-font-family-quicksand",
            "app-font-family-josefin-sans",
            "app-font-family-ibm-plex-sans",
            "app-font-family-source-sans-pro",
            "app-font-family-montserrat-alt",
            "app-font-family-roboto-slab",
          ];

    function syncMenuToggleButtons() {
      var miniBtn = document.getElementById("menu-mini-button");
      var expandBtn = document.getElementById("menu-expend-button");
      if (!miniBtn || !expandBtn) return;

      var width = window.innerWidth || document.documentElement.clientWidth || 0;
      if (width <= 1024) {
        // Let responsive CSS control mobile visibility.
        miniBtn.style.display = "";
        expandBtn.style.display = "";
        return;
      }

      if (document.documentElement.classList.contains("minimenu")) {
        miniBtn.style.display = "none";
        expandBtn.style.display = "inline-flex";
      } else {
        expandBtn.style.display = "none";
        miniBtn.style.display = "inline-flex";
      }
    }

    function getSavedSkin() {
      try {
        var primary = storageGet("app-skin", null);
        if (primary !== null) return primary;
        var alt = storageGet("app_skin", null);
        if (alt !== null) return alt;
        var theme = storageGet("theme", null);
        if (theme !== null) return theme;
        var legacy = storageGet("app-skin-dark", null);
        if (legacy !== null) return legacy;
      } catch (e) {
        if (serverPrefs && (serverPrefs.skin === "dark" || serverPrefs.skin === "light")) {
          return serverPrefs.skin === "dark" ? "app-skin-dark" : "";
        }
        return runtimePrefs.skin === "dark" ? "app-skin-dark" : "";
      }

      if (serverPrefs && (serverPrefs.skin === "dark" || serverPrefs.skin === "light")) {
        return serverPrefs.skin === "dark" ? "app-skin-dark" : "";
      }

      if (runtimePrefs.skin === "dark") return "app-skin-dark";
      if (runtimePrefs.skin === "light") return "";
      if (serverPrefs.skin === "dark") return "app-skin-dark";
      if (serverPrefs.skin === "light") return "";
      return "";
    }

    function hasExplicitLocalSkinPreference() {
      return storageHasAny(["app-skin", "app_skin", "theme", "app-skin-dark"]);
    }

    function getSavedMenuMode() {
      var fallbackMenu = runtimePrefs && runtimePrefs.menu ? runtimePrefs.menu : serverPrefs.menu;
      if (themeStateCore && typeof themeStateCore.getSavedMenuMode === "function") {
        return themeStateCore.getSavedMenuMode(storageGet, fallbackMenu);
      }

      try {
        var menuState = storageGet(
          "nexel-classic-dashboard-menu-mini-theme"
        );
        if (menuState === "menu-mini-theme") return "mini";
        if (menuState === "menu-expend-theme") return "expanded";
      } catch (e) {}

      if (
        runtimePrefs.menu === "mini" ||
        runtimePrefs.menu === "expanded" ||
        runtimePrefs.menu === "auto"
      ) {
        return runtimePrefs.menu;
      }
      if (
        serverPrefs.menu === "mini" ||
        serverPrefs.menu === "expanded" ||
        serverPrefs.menu === "auto"
      ) {
        return serverPrefs.menu;
      }
      return "auto";
    }

    function getSavedFont() {
      try {
        var legacyFont = storageGet("font-family", null);
        if (legacyFont !== null && legacyFont !== "") {
          if (legacyFont === "default") {
            return "app-font-family-montserrat";
          }
          return legacyFont;
        }
      } catch (e) {
        return runtimePrefs.font || "app-font-family-montserrat";
      }

      if (typeof runtimePrefs.font === "string" && runtimePrefs.font !== "") {
        return runtimePrefs.font;
      }
      if (typeof serverPrefs.font === "string" && serverPrefs.font !== "") {
        return serverPrefs.font;
      }
      return "app-font-family-montserrat";
    }

    function getSavedNavigationMode() {
      try {
        var nav = storageGet("app-navigation", null);
        if (nav === "app-navigation-dark") return "dark";
        if (nav === "app-navigation-light") return "light";
      } catch (e) {}

      if (runtimePrefs.navigation === "dark" || runtimePrefs.navigation === "light") {
        return runtimePrefs.navigation;
      }
      if (serverPrefs.navigation === "dark" || serverPrefs.navigation === "light") {
        return serverPrefs.navigation;
      }
      return "light";
    }

    function getSavedScheme() {
      if (runtimePrefs.scheme) return normalizeScheme(runtimePrefs.scheme);
      if (serverPrefs.scheme) return normalizeScheme(serverPrefs.scheme);

      try {
        var stored = storageGet("app-theme-scheme", null);
        if (stored) return normalizeScheme(stored);
      } catch (e) {}
      return "blue";
    }

    function getSavedHeaderMode() {
      try {
        var hdr = storageGet("app-header", null);
        if (hdr === "app-header-dark") return "dark";
        if (hdr === "app-header-light") return "light";
      } catch (e) {}

      if (runtimePrefs.header === "dark" || runtimePrefs.header === "light") {
        return runtimePrefs.header;
      }
      if (serverPrefs.header === "dark" || serverPrefs.header === "light") {
        return serverPrefs.header;
      }
      return "light";
    }

    function getSavedSurfacesMode() {
      try {
        var stored = storageGet("app-surfaces", null);
        if (stored) {
          return normalizeSurfacesMode(stored);
        }
      } catch (e) {}

      if (runtimePrefs.surfaces) {
        return normalizeSurfacesMode(runtimePrefs.surfaces);
      }
      if (serverPrefs.surfaces) {
        return normalizeSurfacesMode(serverPrefs.surfaces);
      }
      return "linked";
    }

    function clearFontClasses() {
      if (themeStateCore && typeof themeStateCore.clearFontClasses === "function") {
        themeStateCore.clearFontClasses(document.documentElement);
        return;
      }
      var classes = document.documentElement.className || "";
      document.documentElement.className = classes
        .replace(/\bapp-font-family-[^\s]+\b/g, "")
        .replace(/\s{2,}/g, " ")
        .trim();
    }

    function applyFont(fontClass) {
      var nextFont = allowedFonts.indexOf(fontClass) !== -1 ? fontClass : "default";
      var currentFont = currentFontValue();
      runtimePrefs.font = nextFont;

      if (currentFont !== nextFont) {
        if (themeStateCore && typeof themeStateCore.applyFontClass === "function") {
          nextFont = themeStateCore.applyFontClass(document.documentElement, nextFont);
        } else {
          clearFontClasses();
          if (nextFont !== "default") {
            document.documentElement.classList.add(nextFont);
          }
        }
      }

      try {
        if (nextFont === "default") {
          storageRemove("font-family");
        } else {
          storageSet("font-family", nextFont);
        }
      } catch (e) {}
      return nextFont;
    }

    function applyNavigationMode(mode) {
      var next = mode === "dark" ? "dark" : "light";
      var current = document.documentElement.classList.contains("app-navigation-dark")
        ? "dark"
        : "light";
      runtimePrefs.navigation = next;
      if (current !== next) {
        document.documentElement.classList.remove("app-navigation-dark");
        if (next === "dark") {
          document.documentElement.classList.add("app-navigation-dark");
        }
      }
      try {
        storageSet(
          "app-navigation",
          next === "dark" ? "app-navigation-dark" : "app-navigation-light"
        );
      } catch (e) {}
      return next;
    }

    function applyHeaderMode(mode) {
      var next = mode === "dark" ? "dark" : "light";
      var current = document.documentElement.classList.contains("app-header-dark")
        ? "dark"
        : "light";
      runtimePrefs.header = next;
      if (current !== next) {
        document.documentElement.classList.remove("app-header-dark");
        if (next === "dark") {
          document.documentElement.classList.add("app-header-dark");
        }
      }
      try {
        storageSet(
          "app-header",
          next === "dark" ? "app-header-dark" : "app-header-light"
        );
      } catch (e) {}
      return next;
    }

    function applySurfaceMode(mode) {
      var next = normalizeSurfacesMode(mode);
      if (runtimePrefs.surfaces === next) {
        return next;
      }
      runtimePrefs.surfaces = next;
      try {
        storageSet("app-surfaces", next);
      } catch (e) {}
      return next;
    }

    function syncSurfaceControlsState() {
      var independent = runtimePrefs.surfaces === "independent";
      var navLightRadio = document.getElementById("theme-page-navigation-light");
      var navDarkRadio = document.getElementById("theme-page-navigation-dark");
      var headerLightRadio = document.getElementById("theme-page-header-light");
      var headerDarkRadio = document.getElementById("theme-page-header-dark");

      if (pageSurfaces) {
        pageSurfaces.value = independent ? "independent" : "linked";
      }
      if (pageSurfacesIndependent) {
        pageSurfacesIndependent.checked = independent;
      }
      if (pageSurfacesCard) {
        pageSurfacesCard.classList.toggle("is-surfaces-linked", !independent);
      }
      if (navLightRadio) navLightRadio.disabled = !independent;
      if (navDarkRadio) navDarkRadio.disabled = !independent;
      if (headerLightRadio) headerLightRadio.disabled = !independent;
      if (headerDarkRadio) headerDarkRadio.disabled = !independent;
    }

    function applyScheme(scheme) {
      var next = normalizeScheme(scheme);
      runtimePrefs.scheme = next;
      if (themeStateCore && typeof themeStateCore.applySchemeClass === "function") {
        next = themeStateCore.applySchemeClass(document.documentElement, next);
      } else {
        var root = document.documentElement;
        var classes = root.className ? root.className.split(/\s+/) : [];
        for (var i = 0; i < classes.length; i += 1) {
          if (classes[i] && classes[i].indexOf("app-theme-") === 0) {
            root.classList.remove(classes[i]);
          }
        }
        root.classList.add("app-theme-" + next);
      }
      try {
        storageSet("app-theme-scheme", next);
      } catch (e) {}
      return next;
    }

    function pickThemePayload(payload) {
      var src = payload || {};
      return {
        skin: src.skin,
        menu: src.menu,
        font: src.font,
        navigation: src.navigation,
        header: src.header,
        scheme: src.scheme,
        surfaces: src.surfaces,
      };
    }

    function persistThemeCookie(payload) {
      try {
        var jsonValue = JSON.stringify(payload || {});
        var cookieValue = encodeURIComponent(jsonValue);
        document.cookie =
          themePrefsCookieName() +
          "=" +
          cookieValue +
          "; path=/; max-age=" +
          60 * 60 * 24 * 30 +
          "; samesite=Lax";
        storageSet(themePrefsStorageKey(), jsonValue);
      } catch (e) {}
    }

    function saveThemePreferences(payload) {
      var cleanPayload = pickThemePayload(payload);
      persistThemeCookie(cleanPayload);
      serverPrefs = Object.assign({}, serverPrefs, cleanPayload);
      if (!window.fetch) return Promise.resolve({ ok: false });
      var endpoint = window.__bioternThemeApi || "api/theme-customizer.php";
      return fetch(endpoint, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "same-origin",
        body: JSON.stringify(cleanPayload),
      })
        .then(function (response) {
          if (!response || !response.ok) {
            return { __ok: false, __result: null };
          }
          return response.json().then(function (json) {
            return { __ok: true, __result: json };
          });
        })
        .then(function (result) {
          var ok = !!(result && result.__ok);
          var data = result ? result.__result : null;
          if (!ok || !data || !data.preferences) {
            return { ok: ok };
          }
          if (data.preferences.skin === "dark" || data.preferences.skin === "light") {
            runtimePrefs.skin = data.preferences.skin;
          }
          runtimePrefs.menu = normalizeMenuPreference(data.preferences.menu);
          if (typeof data.preferences.font === "string" && data.preferences.font !== "") {
            runtimePrefs.font = data.preferences.font;
          }
          if (
            data.preferences.navigation === "dark" ||
            data.preferences.navigation === "light"
          ) {
            runtimePrefs.navigation = data.preferences.navigation;
          }
          if (data.preferences.header === "dark" || data.preferences.header === "light") {
            runtimePrefs.header = data.preferences.header;
          }
          if (typeof data.preferences.scheme === "string") {
            runtimePrefs.scheme = normalizeScheme(data.preferences.scheme);
          }
          if (typeof data.preferences.surfaces === "string") {
            runtimePrefs.surfaces = normalizeSurfacesMode(data.preferences.surfaces);
          }
          return { ok: true };
        })
        .catch(function () {
          return { ok: false };
        });
    }

    function showThemeToast(icon, title) {
      var root = document.body || document.documentElement;
      if (!root) {
        console.log(title || "Saved");
        return;
      }

      var existing = document.getElementById("appThemeToast");
      if (existing && existing.parentNode) {
        existing.parentNode.removeChild(existing);
      }

      var toast = document.createElement("div");
      toast.id = "appThemeToast";
      toast.className = "app-theme-toast-static";
      toast.setAttribute("role", "status");
      toast.setAttribute("aria-live", "polite");

      var iconWrap = document.createElement("span");
      iconWrap.className = "app-theme-toast-static-icon";

      var iconEl = document.createElement("span");
      iconEl.className = "app-theme-toast-static-icon-glyph";
      iconEl.setAttribute("aria-hidden", "true");
      if (icon === "error") {
        iconEl.innerHTML =
          '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 9v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M12 17h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>';
      } else {
        iconEl.innerHTML =
          '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M20 6 9 17l-5-5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
      }
      iconWrap.appendChild(iconEl);

      var textWrap = document.createElement("span");
      textWrap.className = "app-theme-toast-static-text";
      textWrap.textContent = title || "Saved";

      toast.appendChild(iconWrap);
      toast.appendChild(textWrap);
      root.appendChild(toast);

      window.setTimeout(function () {
        if (toast && toast.parentNode) {
          toast.parentNode.removeChild(toast);
        }
      }, 2800);
    }

    function applyMenuMode(mode) {
      var nextMode = normalizeMenuPreference(mode);
      runtimePrefs.menu = nextMode;
      var width = window.innerWidth || document.documentElement.clientWidth || 0;
      if (nextMode === "mini") {
        if (themeStateCore && typeof themeStateCore.applyMenuModeToRoot === "function") {
          themeStateCore.applyMenuModeToRoot(document.documentElement, "mini", width);
        } else {
          document.documentElement.classList.add("minimenu");
        }
        try {
          storageSet("nexel-classic-dashboard-menu-mini-theme", "menu-mini-theme");
        } catch (e) {}
        syncMenuToggleButtons();
        return;
      }
      if (nextMode === "expanded") {
        if (themeStateCore && typeof themeStateCore.applyMenuModeToRoot === "function") {
          themeStateCore.applyMenuModeToRoot(document.documentElement, "expanded", width);
        } else {
          document.documentElement.classList.remove("minimenu");
        }
        try {
          storageSet("nexel-classic-dashboard-menu-mini-theme", "menu-expend-theme");
        } catch (e) {}
        syncMenuToggleButtons();
        return;
      }
      try {
        storageRemove("nexel-classic-dashboard-menu-mini-theme");
      } catch (e) {}
      if (themeStateCore && typeof themeStateCore.applyMenuModeToRoot === "function") {
        themeStateCore.applyMenuModeToRoot(document.documentElement, "auto", width);
      } else if (width >= 1024 && width <= 1600) {
        document.documentElement.classList.add("minimenu");
      } else {
        document.documentElement.classList.remove("minimenu");
      }
      syncMenuToggleButtons();
    }

    function currentSkinValue() {
      return document.documentElement.classList.contains("app-skin-dark")
        ? "dark"
        : "light";
    }

    function currentFontValue() {
      for (var i = 0; i < allowedFonts.length; i += 1) {
        if (document.documentElement.classList.contains(allowedFonts[i])) {
          return allowedFonts[i];
        }
      }
      return "default";
    }

    function currentNavigationValue() {
      return document.documentElement.classList.contains("app-navigation-dark")
        ? "dark"
        : "light";
    }

    function currentHeaderValue() {
      return document.documentElement.classList.contains("app-header-dark")
        ? "dark"
        : "light";
    }

    function currentSchemeValue() {
      if (themeStateCore && typeof themeStateCore.getActiveScheme === "function") {
        return normalizeScheme(themeStateCore.getActiveScheme(document.documentElement));
      }
      var classes = document.documentElement.className
        ? document.documentElement.className.split(/\s+/)
        : [];
      for (var i = 0; i < classes.length; i += 1) {
        if (classes[i] && classes[i].indexOf("app-theme-") === 0) {
          return normalizeScheme(classes[i].substring("app-theme-".length));
        }
      }
      return "blue";
    }

    function ensureSchemeOptionExists(schemeValue) {
      if (!pageScheme) {
        return;
      }
      var normalized = normalizeScheme(schemeValue);
      if (!normalized) {
        normalized = "blue";
      }
      for (var i = 0; i < pageScheme.options.length; i += 1) {
        if (normalizeScheme(pageScheme.options[i].value) === normalized) {
          return;
        }
      }
      var option = document.createElement("option");
      option.value = normalized;
      option.text = normalized
        .split("-")
        .map(function (part) {
          return part ? part.charAt(0).toUpperCase() + part.slice(1) : "";
        })
        .join(" ");
      pageScheme.appendChild(option);
    }

    function syncCustomSelectVisual(selectEl) {
      if (!selectEl) return;
      var wrap = selectEl.closest(".biotern-select-wrap");
      if (!wrap) return;

      var selectedOption = null;
      if (selectEl.options && selectEl.options.length) {
        var selectedIndex = selectEl.selectedIndex >= 0 ? selectEl.selectedIndex : 0;
        selectedOption = selectEl.options[selectedIndex] || null;
      }
      var selectedValue = selectedOption ? selectedOption.value : "";
      var selectedText = selectedOption ? selectedOption.text : "Select";

      var label = wrap.querySelector(".biotern-select-trigger-label");
      if (label) {
        label.textContent = selectedText;
      }

      var menu = wrap.querySelector(".biotern-select-menu");
      if (menu) {
        var buttons = menu.querySelectorAll(".biotern-select-option");
        for (var i = 0; i < buttons.length; i += 1) {
          var button = buttons[i];
          var isSelected = button.getAttribute("data-value") === selectedValue;
          button.classList.toggle("is-selected", isSelected);
          button.setAttribute("aria-selected", isSelected ? "true" : "false");
        }
      }
    }

    function setDark(isDark, persist) {
      var alreadyDark = document.documentElement.classList.contains("app-skin-dark");
      if (alreadyDark === !!isDark) {
        runtimePrefs.skin = isDark ? "dark" : "light";
        if (darkBtn) darkBtn.style.display = isDark ? "none" : "inline-flex";
        if (lightBtn) lightBtn.style.display = isDark ? "inline-flex" : "none";
        syncCustomizerInputs();
        return;
      }

      if (isDark) {
        runtimePrefs.skin = "dark";
        document.documentElement.classList.add("app-skin-dark");
        try {
          storageSet("app-skin", "app-skin-dark");
          storageSet("app-skin-dark", "app-skin-dark");
          storageSet("app_skin", "app-skin-dark");
          storageSet("theme", "dark");
        } catch (e) {}
        if (darkBtn) darkBtn.style.display = "none";
        if (lightBtn) lightBtn.style.display = "inline-flex";
        if (runtimePrefs.surfaces !== "independent") {
          applyNavigationMode("dark");
          applyHeaderMode("dark");
        }
        if (persist !== false) {
          saveThemePreferences({
            skin: "dark",
            menu: getSavedMenuMode(),
            font: currentFontValue(),
            scheme: currentSchemeValue(),
            navigation: currentNavigationValue(),
            header: currentHeaderValue(),
            surfaces: runtimePrefs.surfaces,
          });
        }
      } else {
        runtimePrefs.skin = "light";
        document.documentElement.classList.remove("app-skin-dark");
        try {
          storageSet("app-skin", "");
          storageSet("app-skin-dark", "");
          storageSet("app_skin", "");
          storageSet("theme", "light");
        } catch (e) {}
        if (darkBtn) darkBtn.style.display = "inline-flex";
        if (lightBtn) lightBtn.style.display = "none";
        if (runtimePrefs.surfaces !== "independent") {
          applyNavigationMode("light");
          applyHeaderMode("light");
        }
        if (persist !== false) {
          saveThemePreferences({
            skin: "light",
            menu: getSavedMenuMode(),
            font: currentFontValue(),
            scheme: currentSchemeValue(),
            navigation: currentNavigationValue(),
            header: currentHeaderValue(),
            surfaces: runtimePrefs.surfaces,
          });
        }
      }
      syncCustomizerInputs();
    }

    function syncCustomizerInputs() {
      var skin = currentSkinValue();
      var menu = getSavedMenuMode();
      var font = currentFontValue();
      var scheme = currentSchemeValue();
      var surfaces = getSavedSurfacesMode();
      var navigation = surfaces === "independent" ? currentNavigationValue() : skin;
      var header = surfaces === "independent" ? currentHeaderValue() : skin;
      var navLightRadio = document.getElementById("theme-page-navigation-light");
      var navDarkRadio = document.getElementById("theme-page-navigation-dark");
      var headerLightRadio = document.getElementById("theme-page-header-light");
      var headerDarkRadio = document.getElementById("theme-page-header-dark");

      runtimePrefs.surfaces = surfaces;
      if (pageSkinDark) pageSkinDark.checked = skin === "dark";
      if (pageSkinLight) pageSkinLight.checked = skin !== "dark";
      if (pageMenu) pageMenu.value = menu;
      if (pageFont) pageFont.value = font;
      if (pageScheme) {
        ensureSchemeOptionExists(scheme);
        pageScheme.value = scheme;
      }
      syncCustomSelectVisual(pageMenu);
      syncCustomSelectVisual(pageFont);
      syncCustomSelectVisual(pageScheme);
      if (pageNavigation) pageNavigation.value = navigation;
      if (pageHeader) pageHeader.value = header;
      if (navDarkRadio) navDarkRadio.checked = navigation === "dark";
      if (navLightRadio) navLightRadio.checked = navigation !== "dark";
      if (headerDarkRadio) headerDarkRadio.checked = header === "dark";
      if (headerLightRadio) headerLightRadio.checked = header !== "dark";
      syncSurfaceControlsState();

      try {
        document.dispatchEvent(
          new CustomEvent("biotern:theme-customizer-sync", {
            detail: {
              skin: skin,
              menu: menu,
              font: font,
              scheme: scheme,
              navigation: navigation,
              header: header,
              surfaces: surfaces,
            },
          })
        );
      } catch (e) {}
    }

    function bindCustomizerHeaderNavigationRadios() {
      var navLightRadio = document.getElementById("theme-page-navigation-light");
      var navDarkRadio = document.getElementById("theme-page-navigation-dark");
      var headerLightRadio = document.getElementById("theme-page-header-light");
      var headerDarkRadio = document.getElementById("theme-page-header-dark");

      if (navLightRadio && pageNavigation) {
        navLightRadio.addEventListener("change", function () {
          if (runtimePrefs.surfaces !== "independent") return;
          if (navLightRadio.checked) {
            pageNavigation.value = "light";
            applyNavigationMode("light");
            syncCustomizerInputs();
            scheduleAutoSave();
          }
        });
      }
      if (navDarkRadio && pageNavigation) {
        navDarkRadio.addEventListener("change", function () {
          if (runtimePrefs.surfaces !== "independent") return;
          if (navDarkRadio.checked) {
            pageNavigation.value = "dark";
            applyNavigationMode("dark");
            syncCustomizerInputs();
            scheduleAutoSave();
          }
        });
      }
      if (headerLightRadio && pageHeader) {
        headerLightRadio.addEventListener("change", function () {
          if (runtimePrefs.surfaces !== "independent") return;
          if (headerLightRadio.checked) {
            pageHeader.value = "light";
            applyHeaderMode("light");
            syncCustomizerInputs();
            scheduleAutoSave();
          }
        });
      }
      if (headerDarkRadio && pageHeader) {
        headerDarkRadio.addEventListener("change", function () {
          if (runtimePrefs.surfaces !== "independent") return;
          if (headerDarkRadio.checked) {
            pageHeader.value = "dark";
            applyHeaderMode("dark");
            syncCustomizerInputs();
            scheduleAutoSave();
          }
        });
      }
    }

    function bindCustomizerPageLinks() {
      var saveLink = document.getElementById("theme-page-save-link");
      var cancelLink = document.getElementById("theme-page-cancel-link");

      if (saveLink && pageSave) {
        saveLink.addEventListener("click", function (event) {
          event.preventDefault();
          pageSave.click();
        });
      }

      if (cancelLink && pageReset) {
        cancelLink.addEventListener("click", function (event) {
          event.preventDefault();
          pageReset.click();
        });
      }
    }

    function hideLegacyCustomizer() {
      try {
        var styleId = "biotern-legacy-theme-customizer-hidden";
        if (!document.getElementById(styleId)) {
          var style = document.createElement("style");
          style.id = styleId;
          style.textContent =
            ".theme-customizer,.cutomizer-open-trigger,.customizer-open-trigger{display:none!important;}";
          document.head.appendChild(style);
        }

        var panels = document.querySelectorAll(".theme-customizer");
        for (var i = 0; i < panels.length; i++) {
          panels[i].remove();
        }

        var openers = document.querySelectorAll(
          ".cutomizer-open-trigger,.customizer-open-trigger"
        );
        for (var j = 0; j < openers.length; j++) {
          openers[j].remove();
        }
      } catch (e) {}
    }

    var s = getSavedSkin();
    var hasLocalSkinPreference = hasExplicitLocalSkinPreference();
    var isDark = typeof s === "string" && s.indexOf("dark") !== -1;
    if (!hasLocalSkinPreference && document.documentElement.classList.contains("app-skin-dark")) {
      isDark = true;
    }
    applyFont(getSavedFont());
    applyScheme(getSavedScheme());
    applySurfaceMode(getSavedSurfacesMode());
    setDark(isDark, false);
    if (runtimePrefs.surfaces === "independent") {
      applyNavigationMode(getSavedNavigationMode());
      applyHeaderMode(getSavedHeaderMode());
    } else {
      var linkedMode = isDark ? "dark" : "light";
      applyNavigationMode(linkedMode);
      applyHeaderMode(linkedMode);
    }
    applyMenuMode(getSavedMenuMode());
    syncMenuToggleButtons();
    syncCustomizerInputs();
    bindCustomizerHeaderNavigationRadios();
    bindCustomizerPageLinks();
    hideLegacyCustomizer();

    window.BioTernThemeRuntime = window.BioTernThemeRuntime || {};
    window.BioTernThemeRuntime.cleanupLegacyCustomizer = hideLegacyCustomizer;

    window.addEventListener("resize", function () {
      syncMenuToggleButtons();
    });

    if (darkBtn)
      darkBtn.addEventListener("click", function (e) {
        e.preventDefault();
        setDark(true, true);
      });
    if (lightBtn)
      lightBtn.addEventListener("click", function (e) {
        e.preventDefault();
        setDark(false, true);
      });

    function resolveSelectedSkin(lightEl, darkEl) {
      if (darkEl && darkEl.checked) return "dark";
      if (lightEl && lightEl.checked) return "light";
      return currentSkinValue();
    }

    function resolveSelectedSurfacesMode() {
      if (pageSurfacesIndependent) {
        return pageSurfacesIndependent.checked ? "independent" : "linked";
      }
      if (pageSurfaces && pageSurfaces.value) {
        return normalizeSurfacesMode(pageSurfaces.value);
      }
      return getSavedSurfacesMode();
    }

    function resolveSelectedNavigation(surfacesMode, selectedSkin) {
      if (surfacesMode !== "independent") return selectedSkin === "dark" ? "dark" : "light";
      var navLightRadio = document.getElementById("theme-page-navigation-light");
      var navDarkRadio = document.getElementById("theme-page-navigation-dark");
      if (navDarkRadio && navDarkRadio.checked) return "dark";
      if (navLightRadio && navLightRadio.checked) return "light";
      if (pageNavigation && pageNavigation.value) return pageNavigation.value;
      return currentNavigationValue();
    }

    function resolveSelectedHeader(surfacesMode, selectedSkin) {
      if (surfacesMode !== "independent") return selectedSkin === "dark" ? "dark" : "light";
      var headerLightRadio = document.getElementById("theme-page-header-light");
      var headerDarkRadio = document.getElementById("theme-page-header-dark");
      if (headerDarkRadio && headerDarkRadio.checked) return "dark";
      if (headerLightRadio && headerLightRadio.checked) return "light";
      if (pageHeader && pageHeader.value) return pageHeader.value;
      return currentHeaderValue();
    }

    var autoSaveTimer = null;

    function applyAndSavePreferences(showToast) {
      var skin = resolveSelectedSkin(pageSkinLight, pageSkinDark);
      var surfaces = resolveSelectedSurfacesMode();
      var menu = normalizeMenuPreference(pageMenu ? pageMenu.value : getSavedMenuMode());
      var font = pageFont ? pageFont.value : currentFontValue();
      var scheme = pageScheme ? pageScheme.value : currentSchemeValue();
      var navigation = resolveSelectedNavigation(surfaces, skin);
      var header = resolveSelectedHeader(surfaces, skin);
      applySurfaceMode(surfaces);
      if (pageNavigation) pageNavigation.value = navigation;
      if (pageHeader) pageHeader.value = header;
      runtimePrefs.menu = menu;
      setDark(skin === "dark", false);
      applyMenuMode(menu);
      font = applyFont(font);
      scheme = applyScheme(scheme);
      if (runtimePrefs.surfaces === "independent") {
        navigation = applyNavigationMode(navigation);
        header = applyHeaderMode(header);
      } else {
        navigation = applyNavigationMode(skin);
        header = applyHeaderMode(skin);
      }
      saveThemePreferences({
        skin: skin,
        menu: menu,
        font: font,
        scheme: scheme,
        navigation: navigation,
        header: header,
        surfaces: runtimePrefs.surfaces,
      }).then(function (result) {
        if (result && result.ok) {
          if (showToast) {
            showThemeToast("success", "Theme preferences saved");
          }
        } else {
          showThemeToast("error", "Unable to save preferences");
        }
      });
      syncCustomizerInputs();
    }

    function scheduleAutoSave() {
      if (autoSaveTimer) {
        clearTimeout(autoSaveTimer);
      }
      autoSaveTimer = setTimeout(function () {
        applyAndSavePreferences(false);
      }, 450);
    }

    function bindAutoSaveInputs() {
      var root = document.querySelector(".theme-customizer-page");
      if (!root) return;
      var handler = function (event) {
        var target = event ? event.target : null;
        if (!target || target.id === "theme-page-reset") return;
        scheduleAutoSave();
      };
      root.addEventListener("change", handler);
      root.addEventListener("input", handler);
    }

    if (pageSave) {
      pageSave.addEventListener("click", function () {
        applyAndSavePreferences(true);
      });
    }

    if (pageScheme) {
      pageScheme.addEventListener("change", function () {
        var scheme = pageScheme.value || "blue";
        applyScheme(scheme);
        syncCustomizerInputs();
        scheduleAutoSave();
      });
    }

    if (pageSkinLight) {
      pageSkinLight.addEventListener("change", function () {
        if (!pageSkinLight.checked) return;
        setDark(false, false);
        syncCustomizerInputs();
        scheduleAutoSave();
      });
    }

    if (pageSkinDark) {
      pageSkinDark.addEventListener("change", function () {
        if (!pageSkinDark.checked) return;
        setDark(true, false);
        syncCustomizerInputs();
        scheduleAutoSave();
      });
    }

    if (pageMenu) {
      pageMenu.addEventListener("change", function () {
        var menu = normalizeMenuPreference(pageMenu.value);
        applyMenuMode(menu);
        syncCustomizerInputs();
        scheduleAutoSave();
      });
    }

    if (pageFont) {
      pageFont.addEventListener("change", function () {
        var font = pageFont.value || "app-font-family-montserrat";
        applyFont(font);
        syncCustomizerInputs();
        scheduleAutoSave();
      });
    }

    if (pageNavigation) {
      pageNavigation.addEventListener("change", function () {
        if (runtimePrefs.surfaces !== "independent") return;
        var navigation = pageNavigation.value || currentNavigationValue();
        applyNavigationMode(navigation);
        syncCustomizerInputs();
        scheduleAutoSave();
      });
    }

    if (pageHeader) {
      pageHeader.addEventListener("change", function () {
        if (runtimePrefs.surfaces !== "independent") return;
        var header = pageHeader.value || currentHeaderValue();
        applyHeaderMode(header);
        syncCustomizerInputs();
        scheduleAutoSave();
      });
    }

    if (pageSurfacesIndependent) {
      pageSurfacesIndependent.addEventListener("change", function () {
        var nextMode = pageSurfacesIndependent.checked ? "independent" : "linked";
        applySurfaceMode(nextMode);
        if (nextMode !== "independent") {
          var linkedMode = currentSkinValue();
          applyNavigationMode(linkedMode);
          applyHeaderMode(linkedMode);
        }
        syncCustomizerInputs();
        scheduleAutoSave();
      });
    }

    if (pageReset) {
      pageReset.addEventListener("click", function () {
        runtimePrefs = Object.assign({}, defaultThemePrefs);
        applySurfaceMode(defaultThemePrefs.surfaces);
        setDark(false, false);
        applyMenuMode(defaultThemePrefs.menu);
        applyFont(defaultThemePrefs.font);
        applyScheme(defaultThemePrefs.scheme);
        applyNavigationMode(defaultThemePrefs.navigation);
        applyHeaderMode(defaultThemePrefs.header);
        saveThemePreferences(defaultThemePrefs);
        syncCustomizerInputs();
      });
    }

    bindAutoSaveInputs();
  }

  onDomReady(initFooterThemeRuntime);
})();
