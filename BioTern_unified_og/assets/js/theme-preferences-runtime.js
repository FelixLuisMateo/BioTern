/* Footer theme preferences runtime migrated from includes/footer.php */
(function () {
  "use strict";

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

    function normalizeMenuPreference(value) {
      return value === "mini" || value === "expanded" ? value : "auto";
    }

    function normalizeScheme(value) {
      return value === "gray" ? "gray" : "blue";
    }

    var runtimePrefs = {
      skin: serverPrefs.skin === "dark" ? "dark" : "light",
      menu: normalizeMenuPreference(serverPrefs.menu),
      font:
        typeof serverPrefs.font === "string" && serverPrefs.font !== ""
          ? serverPrefs.font
          : "default",
      navigation: serverPrefs.navigation === "dark" ? "dark" : "light",
      header: serverPrefs.header === "dark" ? "dark" : "light",
      scheme:
        typeof serverPrefs.scheme === "string"
          ? normalizeScheme(serverPrefs.scheme)
          : "blue",
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
    var pageSave = document.getElementById("theme-page-save");
    var pageReset = document.getElementById("theme-page-reset");
    var allowedFonts = [
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
      "app-font-family-montserrat-alt",
      "app-font-family-roboto-slab",
      "app-font-family-source-sans-pro",
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
        var primary = localStorage.getItem("app-skin");
        if (primary !== null) return primary;
        var alt = localStorage.getItem("app_skin");
        if (alt !== null) return alt;
        var theme = localStorage.getItem("theme");
        if (theme !== null) return theme;
        var legacy = localStorage.getItem("app-skin-dark");
        if (legacy !== null) return legacy;
      } catch (e) {
        return runtimePrefs.skin === "dark" ? "app-skin-dark" : "";
      }

      if (runtimePrefs.skin === "dark") return "app-skin-dark";
      if (runtimePrefs.skin === "light") return "";
      if (serverPrefs.skin === "dark") return "app-skin-dark";
      if (serverPrefs.skin === "light") return "";
      return "";
    }

    function hasExplicitLocalSkinPreference() {
      try {
        return (
          localStorage.getItem("app-skin") !== null ||
          localStorage.getItem("app_skin") !== null ||
          localStorage.getItem("theme") !== null ||
          localStorage.getItem("app-skin-dark") !== null
        );
      } catch (e) {
        return false;
      }
    }

    function getSavedMenuMode() {
      try {
        var menuState = localStorage.getItem(
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
        var legacyFont = localStorage.getItem("font-family");
        if (legacyFont !== null && legacyFont !== "") {
          return legacyFont;
        }
      } catch (e) {
        return runtimePrefs.font || "default";
      }

      if (typeof runtimePrefs.font === "string" && runtimePrefs.font !== "") {
        return runtimePrefs.font;
      }
      if (typeof serverPrefs.font === "string" && serverPrefs.font !== "") {
        return serverPrefs.font;
      }
      return "default";
    }

    function getSavedNavigationMode() {
      try {
        var nav = localStorage.getItem("app-navigation");
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
      try {
        var stored = localStorage.getItem("app-theme-scheme");
        if (stored) return normalizeScheme(stored);
      } catch (e) {}

      if (runtimePrefs.scheme) return normalizeScheme(runtimePrefs.scheme);
      if (serverPrefs.scheme) return normalizeScheme(serverPrefs.scheme);
      return "blue";
    }

    function getSavedHeaderMode() {
      try {
        var hdr = localStorage.getItem("app-header");
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

    function clearFontClasses() {
      var classes = document.documentElement.className || "";
      document.documentElement.className = classes
        .replace(/\bapp-font-family-[^\s]+\b/g, "")
        .replace(/\s{2,}/g, " ")
        .trim();
    }

    function applyFont(fontClass) {
      var nextFont = allowedFonts.indexOf(fontClass) !== -1 ? fontClass : "default";
      runtimePrefs.font = nextFont;
      clearFontClasses();
      if (nextFont !== "default") {
        document.documentElement.classList.add(nextFont);
      }
      try {
        if (nextFont === "default") {
          localStorage.removeItem("font-family");
        } else {
          localStorage.setItem("font-family", nextFont);
        }
      } catch (e) {}
      return nextFont;
    }

    function applyNavigationMode(mode) {
      var next = mode === "dark" ? "dark" : "light";
      runtimePrefs.navigation = next;
      document.documentElement.classList.remove("app-navigation-dark");
      if (next === "dark") {
        document.documentElement.classList.add("app-navigation-dark");
      }
      try {
        localStorage.setItem(
          "app-navigation",
          next === "dark" ? "app-navigation-dark" : "app-navigation-light"
        );
      } catch (e) {}
      return next;
    }

    function applyHeaderMode(mode) {
      var next = mode === "dark" ? "dark" : "light";
      runtimePrefs.header = next;
      document.documentElement.classList.remove("app-header-dark");
      if (next === "dark") {
        document.documentElement.classList.add("app-header-dark");
      }
      try {
        localStorage.setItem(
          "app-header",
          next === "dark" ? "app-header-dark" : "app-header-light"
        );
      } catch (e) {}
      return next;
    }

    function applyScheme(scheme) {
      var next = normalizeScheme(scheme);
      runtimePrefs.scheme = next;
      document.documentElement.classList.remove("app-theme-gray");
      if (next === "gray") {
        document.documentElement.classList.add("app-theme-gray");
      }
      try {
        localStorage.setItem("app-theme-scheme", next);
      } catch (e) {}
      return next;
    }

    function saveThemePreferences(payload) {
      if (!window.fetch) return Promise.resolve({ ok: false });
      var endpoint = window.__bioternThemeApi || "api/theme-customizer.php";
      return fetch(endpoint, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload || {}),
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
          return { ok: true };
        })
        .catch(function () {
          return { ok: false };
        });
    }

    function showThemeToast(icon, title) {
      if (window.Swal && typeof window.Swal.mixin === "function") {
        window.Swal.mixin({
          toast: true,
          position: "top-end",
          showConfirmButton: false,
          timer: 3000,
          timerProgressBar: true,
          didOpen: function (toast) {
            toast.style.marginTop = "14px";
            toast.style.marginRight = "14px";
            toast.addEventListener("mouseenter", window.Swal.stopTimer);
            toast.addEventListener("mouseleave", window.Swal.resumeTimer);
          },
        }).fire({
          icon: icon || "success",
          title: title || "Saved",
        });
        return;
      }
      console.log(title || "Saved");
    }

    function applyMenuMode(mode) {
      var nextMode = normalizeMenuPreference(mode);
      runtimePrefs.menu = nextMode;
      var width = window.innerWidth || document.documentElement.clientWidth || 0;
      if (nextMode === "mini") {
        document.documentElement.classList.add("minimenu");
        try {
          localStorage.setItem("nexel-classic-dashboard-menu-mini-theme", "menu-mini-theme");
        } catch (e) {}
        syncMenuToggleButtons();
        return;
      }
      if (nextMode === "expanded") {
        document.documentElement.classList.remove("minimenu");
        try {
          localStorage.setItem("nexel-classic-dashboard-menu-mini-theme", "menu-expend-theme");
        } catch (e) {}
        syncMenuToggleButtons();
        return;
      }
      try {
        localStorage.removeItem("nexel-classic-dashboard-menu-mini-theme");
      } catch (e) {}
      if (width >= 1024 && width <= 1600) {
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
      return document.documentElement.classList.contains("app-theme-gray")
        ? "gray"
        : "blue";
    }

    function setDark(isDark, persist) {
      if (isDark) {
        runtimePrefs.skin = "dark";
        document.documentElement.classList.add("app-skin-dark");
        try {
          localStorage.setItem("app-skin", "app-skin-dark");
          localStorage.setItem("app-skin-dark", "app-skin-dark");
          localStorage.setItem("app_skin", "app-skin-dark");
          localStorage.setItem("theme", "dark");
        } catch (e) {}
        if (darkBtn) darkBtn.style.display = "none";
        if (lightBtn) lightBtn.style.display = "inline-flex";
        if (persist !== false) {
          saveThemePreferences({
            skin: "dark",
            menu: getSavedMenuMode(),
            font: currentFontValue(),
            scheme: currentSchemeValue(),
            navigation: currentNavigationValue(),
            header: currentHeaderValue(),
          });
        }
      } else {
        runtimePrefs.skin = "light";
        document.documentElement.classList.remove("app-skin-dark");
        try {
          localStorage.setItem("app-skin", "");
          localStorage.setItem("app-skin-dark", "");
          localStorage.setItem("app_skin", "");
          localStorage.setItem("theme", "light");
        } catch (e) {}
        if (darkBtn) darkBtn.style.display = "inline-flex";
        if (lightBtn) lightBtn.style.display = "none";
        if (persist !== false) {
          saveThemePreferences({
            skin: "light",
            menu: getSavedMenuMode(),
            font: currentFontValue(),
            scheme: currentSchemeValue(),
            navigation: currentNavigationValue(),
            header: currentHeaderValue(),
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
      var navigation = currentNavigationValue();
      var header = currentHeaderValue();
      var navLightRadio = document.getElementById("theme-page-navigation-light");
      var navDarkRadio = document.getElementById("theme-page-navigation-dark");
      var headerLightRadio = document.getElementById("theme-page-header-light");
      var headerDarkRadio = document.getElementById("theme-page-header-dark");

      if (pageSkinDark) pageSkinDark.checked = skin === "dark";
      if (pageSkinLight) pageSkinLight.checked = skin !== "dark";
      if (pageMenu) pageMenu.value = menu;
      if (pageFont) pageFont.value = font;
      if (pageScheme) pageScheme.value = scheme;
      if (pageNavigation) pageNavigation.value = navigation;
      if (pageHeader) pageHeader.value = header;
      if (navDarkRadio) navDarkRadio.checked = navigation === "dark";
      if (navLightRadio) navLightRadio.checked = navigation !== "dark";
      if (headerDarkRadio) headerDarkRadio.checked = header === "dark";
      if (headerLightRadio) headerLightRadio.checked = header !== "dark";
    }

    var s = getSavedSkin();
    var hasLocalSkinPreference = hasExplicitLocalSkinPreference();
    var isDark = typeof s === "string" && s.indexOf("dark") !== -1;
    if (!hasLocalSkinPreference && document.documentElement.classList.contains("app-skin-dark")) {
      isDark = true;
    }
    applyFont(getSavedFont());
    applyNavigationMode(getSavedNavigationMode());
    applyHeaderMode(getSavedHeaderMode());
    applyScheme(getSavedScheme());
    setDark(isDark, false);
    applyMenuMode(getSavedMenuMode());
    syncMenuToggleButtons();
    syncCustomizerInputs();

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

    if (pageSave) {
      pageSave.addEventListener("click", function () {
        var skin = resolveSelectedSkin(pageSkinLight, pageSkinDark);
        var menu = normalizeMenuPreference(pageMenu ? pageMenu.value : getSavedMenuMode());
        var font = pageFont ? pageFont.value : currentFontValue();
        var scheme = pageScheme ? pageScheme.value : currentSchemeValue();
        var navigation = pageNavigation
          ? pageNavigation.value
          : currentNavigationValue();
        var header = pageHeader ? pageHeader.value : currentHeaderValue();
        runtimePrefs.menu = menu;
        setDark(skin === "dark", false);
        applyMenuMode(menu);
        font = applyFont(font);
        scheme = applyScheme(scheme);
        navigation = applyNavigationMode(navigation);
        header = applyHeaderMode(header);
        saveThemePreferences({
          skin: skin,
          menu: menu,
          font: font,
          scheme: scheme,
          navigation: navigation,
          header: header,
        }).then(function (result) {
          if (result && result.ok) {
            showThemeToast("success", "Theme preferences saved");
          } else {
            showThemeToast("error", "Unable to save preferences");
          }
        });
        syncCustomizerInputs();
      });
    }

    if (pageScheme) {
      pageScheme.addEventListener("change", function () {
        var scheme = pageScheme.value || "blue";
        applyScheme(scheme);
        syncCustomizerInputs();
      });
    }

    if (pageReset) {
      pageReset.addEventListener("click", function () {
        runtimePrefs = {
          skin: "light",
          menu: "auto",
          font: "default",
          scheme: "blue",
          navigation: "light",
          header: "light",
        };
        setDark(false, false);
        applyMenuMode("auto");
        applyFont("default");
        applyScheme("blue");
        applyNavigationMode("light");
        applyHeaderMode("light");
        saveThemePreferences({
          skin: "light",
          menu: "auto",
          font: "default",
          scheme: "blue",
          navigation: "light",
          header: "light",
        }).then(function (result) {
          if (result && result.ok) {
            showThemeToast("success", "Theme settings reset to defaults");
          } else {
            showThemeToast("error", "Unable to reset settings");
          }
        });
        syncCustomizerInputs();
      });
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initFooterThemeRuntime);
  } else {
    initFooterThemeRuntime();
  }
})();
