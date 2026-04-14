"use strict";

(function (global) {
  if (!global) {
    return;
  }
  if (global.BioTernThemeStateCore) {
    return;
  }

  var ALLOWED_FONTS = [
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
    "app-font-family-maven-pro",
    "app-font-family-quicksand",
    "app-font-family-montserrat",
    "app-font-family-josefin-sans",
    "app-font-family-ibm-plex-sans",
    "app-font-family-source-sans-pro",
    "app-font-family-montserrat-alt",
    "app-font-family-roboto-slab",
  ];
  var THEME_CLASS_PREFIX = "app-theme-";

  function normalizeMenuPreference(value) {
    return value === "mini" || value === "expanded" ? value : "auto";
  }

  function normalizeScheme(value) {
    var normalized = String(value == null ? "" : value).toLowerCase().trim();
    normalized = normalized.replace(/[^a-z0-9-]+/g, "-").replace(/-+/g, "-").replace(/^-|-$/g, "");
    return normalized || "blue";
  }

  function clearSchemeClasses(root) {
    if (!root || !root.classList) {
      return;
    }
    var classesToRemove = [];
    for (var i = 0; i < root.classList.length; i += 1) {
      var className = root.classList.item(i);
      if (className && className.indexOf(THEME_CLASS_PREFIX) === 0) {
        classesToRemove.push(className);
      }
    }
    for (var j = 0; j < classesToRemove.length; j += 1) {
      root.classList.remove(classesToRemove[j]);
    }
  }

  function getActiveScheme(root) {
    var target = root || document.documentElement;
    if (!target || !target.classList) {
      return "blue";
    }
    for (var i = 0; i < target.classList.length; i += 1) {
      var className = target.classList.item(i);
      if (className && className.indexOf(THEME_CLASS_PREFIX) === 0) {
        return normalizeScheme(className.substring(THEME_CLASS_PREFIX.length));
      }
    }
    return "blue";
  }

  function applySchemeClass(root, scheme) {
    if (!root || !root.classList) {
      return normalizeScheme(scheme);
    }
    var nextScheme = normalizeScheme(scheme);
    clearSchemeClasses(root);
    root.classList.add(THEME_CLASS_PREFIX + nextScheme);
    return nextScheme;
  }

  function normalizeSurfacesMode(value) {
    return value === "independent" ? "independent" : "linked";
  }

  function readCookie(name) {
    var key = String(name || "") + "=";
    var parts = document.cookie ? document.cookie.split(";") : [];
    for (var i = 0; i < parts.length; i += 1) {
      var chunk = parts[i].trim();
      if (chunk.indexOf(key) === 0) {
        return chunk.substring(key.length);
      }
    }
    return "";
  }

  function readPreferencesCookie(cookieName) {
    try {
      var raw = readCookie(cookieName || "biotern_theme_preferences");
      if (!raw) {
        return null;
      }
      var parsed = JSON.parse(decodeURIComponent(raw));
      return parsed && typeof parsed === "object" ? parsed : null;
    } catch (err) {
      return null;
    }
  }

  function resolveModeFromStoredValue(value, darkClass) {
    var stored = String(value == null ? "" : value).toLowerCase();
    if (stored === "") {
      return "light";
    }
    return stored.indexOf("dark") !== -1 || stored === darkClass ? "dark" : "light";
  }

  function readSkinSource(storageGet) {
    return (
      storageGet("app-skin-dark", "") ||
      storageGet("app-skin", "") ||
      storageGet("app_skin", "") ||
      storageGet("theme", "") ||
      ""
    );
  }

  function inferInitialPreferences(options) {
    var opts = options || {};
    var serverPrefs = opts.serverPrefs || {};
    var storageGet = typeof opts.storageGet === "function" ? opts.storageGet : function () { return ""; };
    var storageHasAny =
      typeof opts.storageHasAny === "function"
        ? opts.storageHasAny
        : function () {
            return false;
          };

    var localSkinRaw = readSkinSource(storageGet);
    var hasLocalSkin = storageHasAny(["app-skin", "app_skin", "theme", "app-skin-dark"]);
    var serverSkin =
      serverPrefs && (serverPrefs.skin === "dark" || serverPrefs.skin === "light")
        ? serverPrefs.skin
        : "";
    var skin = hasLocalSkin
      ? localSkinRaw.indexOf("dark") !== -1
        ? "dark"
        : "light"
      : serverSkin !== ""
        ? serverSkin
        : "light";

    var localNavigation = storageGet("app-navigation", "");
    var navigation =
      localNavigation === "app-navigation-dark"
        ? "dark"
        : localNavigation === "app-navigation-light"
          ? "light"
          : serverPrefs && (serverPrefs.navigation === "dark" || serverPrefs.navigation === "light")
            ? serverPrefs.navigation
            : resolveModeFromStoredValue(storageGet("app-navigation", ""), "app-navigation-dark");

    var localHeader = storageGet("app-header", "");
    var header =
      localHeader === "app-header-dark"
        ? "dark"
        : localHeader === "app-header-light"
          ? "light"
          : serverPrefs && (serverPrefs.header === "dark" || serverPrefs.header === "light")
            ? serverPrefs.header
            : resolveModeFromStoredValue(storageGet("app-header", ""), "app-header-dark");

    var localScheme = storageGet("app-theme-scheme", "");
    var scheme =
      localScheme !== ""
        ? normalizeScheme(localScheme)
        : normalizeScheme(
            serverPrefs && typeof serverPrefs.scheme === "string" ? serverPrefs.scheme : "blue"
          );

    var localFont = storageGet("font-family", "");
    var font =
      localFont && localFont !== ""
        ? localFont
        : serverPrefs && typeof serverPrefs.font === "string"
          ? serverPrefs.font
          : "app-font-family-montserrat";
    if (font === "default") {
      font = "app-font-family-montserrat";
    }

    var localSurfaces = storageGet("app-surfaces", "");
    var surfaces =
      localSurfaces !== ""
        ? normalizeSurfacesMode(localSurfaces)
        : normalizeSurfacesMode(serverPrefs && serverPrefs.surfaces);

    if (surfaces !== "independent") {
      navigation = skin;
      header = skin;
    }

    return {
      skin: skin === "dark" ? "dark" : "light",
      menu: normalizeMenuPreference(serverPrefs ? serverPrefs.menu : "auto"),
      navigation: navigation === "dark" ? "dark" : "light",
      header: header === "dark" ? "dark" : "light",
      scheme: normalizeScheme(scheme),
      surfaces: surfaces,
      font: typeof font === "string" && font !== "" ? font : "default",
    };
  }

  function clearFontClasses(root) {
    if (!root) {
      return;
    }
    var classes = root.className || "";
    root.className = classes.replace(/\bapp-font-family-[^\s]+\b/g, "").replace(/\s{2,}/g, " ").trim();
  }

  function applyFontClass(root, fontClass) {
    if (!root) {
      return "default";
    }
    var nextFont = ALLOWED_FONTS.indexOf(fontClass) !== -1 ? fontClass : "default";
    clearFontClasses(root);
    if (nextFont !== "default") {
      root.classList.add(nextFont);
    }
    return nextFont;
  }

  function applyRootThemeClasses(root, preferences) {
    if (!root) {
      return;
    }
    var prefs = preferences || {};
    var surfaces = normalizeSurfacesMode(prefs.surfaces);
    var effectiveNavigation = surfaces === "independent" ? prefs.navigation : prefs.skin;
    var effectiveHeader = surfaces === "independent" ? prefs.header : prefs.skin;
    root.classList.remove("app-skin-dark", "app-navigation-dark", "app-header-dark");
    if (prefs.skin === "dark") {
      root.classList.add("app-skin-dark");
    }
    if (effectiveNavigation === "dark") {
      root.classList.add("app-navigation-dark");
    }
    if (effectiveHeader === "dark") {
      root.classList.add("app-header-dark");
    }
    applySchemeClass(root, prefs.scheme);
    applyFontClass(root, prefs.font);
  }

  function getSavedMenuMode(storageGet, fallbackMode) {
    var read = typeof storageGet === "function" ? storageGet : function () { return null; };
    try {
      var menuState = read("nexel-classic-dashboard-menu-mini-theme", null);
      if (menuState === "menu-mini-theme") {
        return "mini";
      }
      if (menuState === "menu-expend-theme") {
        return "expanded";
      }
    } catch (err) {}
    return normalizeMenuPreference(fallbackMode);
  }

  function applyMenuModeToRoot(root, menuMode, width) {
    if (!root) {
      return;
    }
    var mode = normalizeMenuPreference(menuMode);
    var viewport = typeof width === "number" ? width : 0;

    if (viewport <= 1024) {
      root.classList.remove("minimenu");
      return;
    }
    if (mode === "mini") {
      root.classList.add("minimenu");
      return;
    }
    if (mode === "expanded") {
      root.classList.remove("minimenu");
      return;
    }
    if (viewport >= 1024 && viewport <= 1600) {
      root.classList.add("minimenu");
      return;
    }
    root.classList.remove("minimenu");
  }

  global.BioTernThemeStateCore = {
    allowedFonts: ALLOWED_FONTS.slice(),
    normalizeMenuPreference: normalizeMenuPreference,
    normalizeScheme: normalizeScheme,
    clearSchemeClasses: clearSchemeClasses,
    getActiveScheme: getActiveScheme,
    applySchemeClass: applySchemeClass,
    normalizeSurfacesMode: normalizeSurfacesMode,
    readCookie: readCookie,
    readPreferencesCookie: readPreferencesCookie,
    inferInitialPreferences: inferInitialPreferences,
    clearFontClasses: clearFontClasses,
    applyFontClass: applyFontClass,
    applyRootThemeClasses: applyRootThemeClasses,
    getSavedMenuMode: getSavedMenuMode,
    applyMenuModeToRoot: applyMenuModeToRoot,
  };
})(window);
