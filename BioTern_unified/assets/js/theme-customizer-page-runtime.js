(function () {
    "use strict";

    function initThemeCustomizerPageRuntime() {
        var navHidden = document.getElementById("theme-page-navigation");
        var navLight = document.getElementById("theme-page-navigation-light");
        var navDark = document.getElementById("theme-page-navigation-dark");
        var headerHidden = document.getElementById("theme-page-header");
        var headerLight = document.getElementById("theme-page-header-light");
        var headerDark = document.getElementById("theme-page-header-dark");

        function syncNavigationRadios() {
            if (!navHidden) return;
            if (navDark) navDark.checked = navHidden.value === "dark";
            if (navLight) navLight.checked = navHidden.value !== "dark";
        }

        function syncHeaderRadios() {
            if (!headerHidden) return;
            if (headerDark) headerDark.checked = headerHidden.value === "dark";
            if (headerLight) headerLight.checked = headerHidden.value !== "dark";
        }

        if (navLight && navHidden) {
            navLight.addEventListener("change", function () {
                if (navLight.checked) navHidden.value = "light";
            });
        }
        if (navDark && navHidden) {
            navDark.addEventListener("change", function () {
                if (navDark.checked) navHidden.value = "dark";
            });
        }

        if (headerLight && headerHidden) {
            headerLight.addEventListener("change", function () {
                if (headerLight.checked) headerHidden.value = "light";
            });
        }
        if (headerDark && headerHidden) {
            headerDark.addEventListener("change", function () {
                if (headerDark.checked) headerHidden.value = "dark";
            });
        }

        var observer = new MutationObserver(function () {
            syncNavigationRadios();
            syncHeaderRadios();
        });

        if (navHidden) {
            observer.observe(navHidden, { attributes: true, attributeFilter: ["value"] });
        }
        if (headerHidden) {
            observer.observe(headerHidden, { attributes: true, attributeFilter: ["value"] });
        }

        syncNavigationRadios();
        syncHeaderRadios();

        var saveLink = document.getElementById("theme-page-save-link");
        var cancelLink = document.getElementById("theme-page-cancel-link");
        var saveButton = document.getElementById("theme-page-save");
        var resetButton = document.getElementById("theme-page-reset");

        if (saveLink && saveButton) {
            saveLink.addEventListener("click", function (event) {
                event.preventDefault();
                saveButton.click();
            });
        }

        if (cancelLink && resetButton) {
            cancelLink.addEventListener("click", function (event) {
                event.preventDefault();
                resetButton.click();
            });
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initThemeCustomizerPageRuntime);
    } else {
        initThemeCustomizerPageRuntime();
    }
})();
