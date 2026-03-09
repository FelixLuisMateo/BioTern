"use strict";

(function () {
    var KEY_SCROLL = "biotern.sidebar.scrollTop";

    function getRouteKeyFromUrl(urlObj) {
        var qp = (urlObj.searchParams.get("file") || "").toLowerCase();
        if (qp) {
            qp = qp.replace(/\\/g, "/");
            var qpParts = qp.split("/");
            var qpLast = qpParts[qpParts.length - 1] || qp;
            if (qpLast && qpLast.endsWith(".php")) return qpLast;
        }

        var path = (urlObj.pathname || "").toLowerCase();
        var parts = path.split("/");
        var last = parts[parts.length - 1] || "";
        if (last.endsWith(".php")) return last;
        return "";
    }

    function getCurrentRouteKey() {
        try {
            return getRouteKeyFromUrl(new URL(window.location.href));
        } catch (e) {
            return "";
        }
    }

    function getLinkRouteKey(href) {
        try {
            return getRouteKeyFromUrl(new URL(href, window.location.origin));
        } catch (e) {
            return "";
        }
    }

    function getNav() {
        return document.querySelector(".nxl-navigation .nxl-navbar");
    }

    function getScrollContainer() {
        return document.querySelector(".nxl-navigation .navbar-content");
    }

    function persistState() {
        var nav = getNav();
        if (!nav) return;

        try {
            var sc = getScrollContainer();
            if (sc) localStorage.setItem(KEY_SCROLL, String(sc.scrollTop || 0));
        } catch (e) {}
    }

    function restoreState() {
        var nav = getNav();
        if (!nav) return;

        var currentRoute = getCurrentRouteKey();

        nav.querySelectorAll(".nxl-item.active").forEach(function (item) {
            item.classList.remove("active");
        });

        nav.querySelectorAll(".nxl-item.nxl-hasmenu.nxl-trigger").forEach(function (item) {
            item.classList.remove("nxl-trigger");
        });

        nav.querySelectorAll(".nxl-item .nxl-link[href]").forEach(function (a) {
            var linkKey = getLinkRouteKey(a.getAttribute("href") || "");
            if (linkKey && currentRoute && linkKey === currentRoute) {
                var item = a.closest(".nxl-item");
                if (item) item.classList.add("active");

                var parentMenu = a.closest(".nxl-item.nxl-hasmenu");
                if (parentMenu) parentMenu.classList.add("active", "nxl-trigger");
            }
        });

        try {
            var sc = getScrollContainer();
            var savedTop = parseInt(localStorage.getItem(KEY_SCROLL) || "0", 10);
            if (sc && !isNaN(savedTop) && savedTop > 0) {
                requestAnimationFrame(function () {
                    sc.scrollTop = savedTop;
                });
            }
        } catch (e) {}
    }

    restoreState();

    document.querySelectorAll(".nxl-navigation .nxl-item.nxl-hasmenu > .nxl-link").forEach(function (trigger) {
        trigger.addEventListener("click", function () {
            setTimeout(persistState, 0);
        });
    });
})();
