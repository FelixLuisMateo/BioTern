"use strict";

(function (global) {
    function SidebarNavigationState(options) {
        this.options = options || {};
        this.routeResolver = this.options.routeResolver;
        this.storage = this.options.storage;
    }

    SidebarNavigationState.prototype.getNav = function () {
        return document.querySelector(".nxl-navigation .nxl-navbar");
    };

    SidebarNavigationState.prototype.getScrollContainer = function () {
        return document.querySelector(".nxl-navigation .navbar-content");
    };

    SidebarNavigationState.prototype.persistScroll = function () {
        var scrollContainer = this.getScrollContainer();
        if (!scrollContainer) {
            return;
        }
        this.storage.set(this.options.scrollStorageKey, scrollContainer.scrollTop || 0);
    };

    SidebarNavigationState.prototype.clearActiveState = function (nav) {
        nav.querySelectorAll(".nxl-item.active").forEach(function (item) {
            item.classList.remove("active");
        });

        nav.querySelectorAll(".nxl-item.nxl-hasmenu.nxl-trigger, .nxl-item.nxl-hasmenu.open").forEach(function (item) {
            item.classList.remove("nxl-trigger", "open");
        });

        nav.querySelectorAll(".nxl-submenu").forEach(function (submenu) {
            submenu.style.display = "none";
        });
    };

    SidebarNavigationState.prototype.applyRouteActiveState = function (nav) {
        var currentRoute = this.routeResolver.currentRouteKey();
        if (!currentRoute) {
            return;
        }

        var isMiniMenu = document.documentElement.classList.contains("minimenu");
        var self = this;

        nav.querySelectorAll(".nxl-item .nxl-link[href]").forEach(function (link) {
            var linkKey = self.routeResolver.routeKeyFromHref(link.getAttribute("href") || "");
            if (!linkKey || linkKey !== currentRoute) {
                return;
            }

            var item = link.closest(".nxl-item");
            if (item) {
                item.classList.add("active");
            }

            var parentMenu = link.closest(".nxl-item.nxl-hasmenu");
            if (!parentMenu) {
                return;
            }

            parentMenu.classList.add("active");
            if (isMiniMenu) {
                return;
            }

            parentMenu.classList.add("nxl-trigger");
            var submenu = parentMenu.querySelector(":scope > .nxl-submenu");
            if (submenu) {
                submenu.style.display = "block";
            }
        });
    };

    SidebarNavigationState.prototype.restoreScroll = function () {
        var scrollContainer = this.getScrollContainer();
        if (!scrollContainer) {
            return;
        }

        var savedTop = this.storage.getNumber(this.options.scrollStorageKey, 0);
        if (!savedTop || savedTop < 1) {
            return;
        }

        requestAnimationFrame(function () {
            scrollContainer.scrollTop = savedTop;
        });
    };

    SidebarNavigationState.prototype.bindEvents = function () {
        var self = this;
        document.querySelectorAll(".nxl-navigation .nxl-item.nxl-hasmenu > .nxl-link").forEach(function (trigger) {
            trigger.addEventListener("click", function () {
                setTimeout(function () {
                    self.persistScroll();
                }, 0);
            });
        });
    };

    SidebarNavigationState.prototype.init = function () {
        var nav = this.getNav();
        if (!nav) {
            return;
        }

        this.clearActiveState(nav);
        this.applyRouteActiveState(nav);
        this.restoreScroll();
        this.bindEvents();
    };

    function boot() {
        var core = global.BioTernNavCore;
        if (!core || !core.RouteResolver || !core.Storage) {
            return;
        }

        var state = new SidebarNavigationState({
            scrollStorageKey: "biotern.sidebar.scrollTop",
            routeResolver: core.RouteResolver,
            storage: core.Storage
        });

        function runSidebarState() {
            state.init();
        }

        if (global.BioTernRuntimeBoot && typeof global.BioTernRuntimeBoot.boot === "function") {
            global.BioTernRuntimeBoot.boot({
                name: "navigation-state",
                run: runSidebarState
            });
            return;
        }

        if (core.Dom && typeof core.Dom.onDomReady === "function") {
            core.Dom.onDomReady(runSidebarState);
            return;
        }

        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", runSidebarState);
        } else {
            runSidebarState();
        }
    }

    boot();
})(window);
