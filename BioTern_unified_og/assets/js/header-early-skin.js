"use strict";

(function () {
    var root = document.documentElement;
    var prefsRaw = root.getAttribute("data-biotern-theme-prefs") || "{}";
    var apiRaw = root.getAttribute("data-biotern-theme-api") || "\"api/theme-customizer.php\"";
    var serverPrefs = {};

    try {
        serverPrefs = JSON.parse(prefsRaw) || {};
    } catch (e) {
        serverPrefs = {};
    }

    var themeApi = "api/theme-customizer.php";
    try {
        themeApi = JSON.parse(apiRaw) || themeApi;
    } catch (e) {}

    window.__bioternThemePrefs = serverPrefs;
    window.__bioternThemeApi = themeApi;

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
        "app-font-family-maven-pro",
        "app-font-family-quicksand",
        "app-font-family-montserrat",
        "app-font-family-josefin-sans",
        "app-font-family-ibm-plex-sans",
        "app-font-family-montserrat-alt",
        "app-font-family-roboto-slab",
        "app-font-family-source-sans-pro"
    ];

    function clearFontClasses() {
        try {
            var cls = root.className || "";
            var cleaned = cls.replace(/\bapp-font-family-[^\s]+\b/g, "").replace(/\s{2,}/g, " ").trim();
            root.className = cleaned;
        } catch (e) {}
    }

    function applyFont(fontClass) {
        clearFontClasses();
        if (fontClass && allowedFonts.indexOf(fontClass) !== -1) {
            root.classList.add(fontClass);
        }
    }

    function getSavedFont() {
        if (typeof serverPrefs.font === "string" && serverPrefs.font !== "") {
            return serverPrefs.font;
        }

        try {
            var legacyFont = localStorage.getItem("font-family");
            return legacyFont !== null ? legacyFont : "default";
        } catch (e) {
            return "default";
        }
    }

    function applyNavigationMode(mode) {
        root.classList.remove("app-navigation-dark");
        if (mode === "dark") {
            root.classList.add("app-navigation-dark");
        }
    }

    function applyHeaderMode(mode) {
        root.classList.remove("app-header-dark");
        if (mode === "dark") {
            root.classList.add("app-header-dark");
        }
    }

    function getSavedNavigationMode() {
        if (serverPrefs.navigation === "dark" || serverPrefs.navigation === "light") {
            return serverPrefs.navigation;
        }
        try {
            var nav = localStorage.getItem("app-navigation");
            if (nav === "app-navigation-dark") return "dark";
        } catch (e) {}
        return "light";
    }

    function getSavedHeaderMode() {
        if (serverPrefs.header === "dark" || serverPrefs.header === "light") {
            return serverPrefs.header;
        }
        try {
            var hdr = localStorage.getItem("app-header");
            if (hdr === "app-header-dark") return "dark";
        } catch (e) {}
        return "light";
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
            return legacy !== null ? legacy : "";
        } catch (e) {}

        if (serverPrefs.skin === "dark") return "app-skin-dark";
        if (serverPrefs.skin === "light") return "";
        return "";
    }

    applyFont(getSavedFont());
    applyNavigationMode(getSavedNavigationMode());
    applyHeaderMode(getSavedHeaderMode());

    var skin = getSavedSkin();
    if (typeof skin === "string" && skin.indexOf("dark") !== -1) {
        root.classList.add("app-skin-dark");
    } else {
        root.classList.remove("app-skin-dark");
    }

    try {
        var menuState = localStorage.getItem("nexel-classic-dashboard-menu-mini-theme");
        if (!menuState) {
            if (serverPrefs.menu === "mini") {
                menuState = "menu-mini-theme";
            } else if (serverPrefs.menu === "expanded") {
                menuState = "menu-expend-theme";
            }
        }
        var width = window.innerWidth || root.clientWidth || 0;

        if (menuState === "menu-mini-theme") {
            root.classList.add("minimenu");
        } else if (menuState === "menu-expend-theme") {
            root.classList.remove("minimenu");
        } else {
            if (width >= 1024 && width <= 1600) {
                root.classList.add("minimenu");
            } else if (width > 1600) {
                root.classList.remove("minimenu");
            }
        }
    } catch (e) {}
})();
