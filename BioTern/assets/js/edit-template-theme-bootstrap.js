(function () {
    "use strict";

    var dark = false;
    try {
        var match = document.cookie.match(/(?:^|;\s*)biotern_theme_preferences=([^;]+)/);
        if (match && match[1]) {
            var prefs = JSON.parse(decodeURIComponent(match[1]));
            if (prefs && prefs.skin === "dark") dark = true;
        }
    } catch (e) {}

    try {
        var primary = localStorage.getItem("app-skin");
        var skin = primary !== null
            ? primary
            : (localStorage.getItem("app_skin")
                || localStorage.getItem("theme")
                || localStorage.getItem("app-skin-dark")
                || "");
        if (typeof skin === "string" && skin.indexOf("dark") !== -1) dark = true;
        if (primary !== null && primary.indexOf("dark") === -1) dark = false;
    } catch (e) {}

    if (dark) document.documentElement.classList.add("app-skin-dark");
})();
