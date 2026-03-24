"use strict";

(function (global) {
    function BottomNavigationState(options) {
        this.options = options || {};
        this.routeResolver = this.options.routeResolver;
    }

    BottomNavigationState.prototype.getNav = function () {
        return document.querySelector(".biotern-bottom-nav");
    };

    BottomNavigationState.prototype.getSheet = function () {
        return document.getElementById("bioternBottomSheet");
    };

    BottomNavigationState.prototype.setActiveRoute = function (nav) {
        var currentRoute = this.routeResolver.currentRouteKey();
        if (!currentRoute) {
            return;
        }

        var resolver = this.routeResolver;
        nav.querySelectorAll(".biotern-bottom-link[data-routes]").forEach(function (link) {
            var routes = resolver.routeListFromCsv(link.getAttribute("data-routes") || "");
            if (!routes.length || routes.indexOf(currentRoute) === -1) {
                return;
            }

            nav.querySelectorAll(".biotern-bottom-link.active").forEach(function (activeLink) {
                if (activeLink !== link) {
                    activeLink.classList.remove("active");
                    activeLink.removeAttribute("aria-current");
                }
            });

            link.classList.add("active");
            link.setAttribute("aria-current", "page");
        });
    };

    BottomNavigationState.prototype.setPanel = function (sheet, nav, target, trigger) {
        if (!target) {
            return;
        }

        sheet.dataset.activePanel = target;
        sheet.querySelectorAll(".biotern-bottom-sheet-content[data-panel]").forEach(function (panel) {
            panel.classList.toggle("is-active", panel.getAttribute("data-panel") === target);
        });

        nav.querySelectorAll(".biotern-bottom-link.is-open").forEach(function (button) {
            if (button !== trigger) {
                button.classList.remove("is-open");
            }
        });

        if (trigger) {
            trigger.classList.add("is-open");
        }
    };

    BottomNavigationState.prototype.openSheet = function (sheet, nav, target, trigger) {
        this.setPanel(sheet, nav, target, trigger);
        sheet.classList.add("is-open");
        sheet.setAttribute("aria-hidden", "false");
    };

    BottomNavigationState.prototype.closeSheet = function (sheet, nav) {
        sheet.classList.remove("is-open");
        sheet.setAttribute("aria-hidden", "true");
        nav.querySelectorAll(".biotern-bottom-link.is-open").forEach(function (button) {
            button.classList.remove("is-open");
        });
    };

    BottomNavigationState.prototype.bindSheetEvents = function (sheet, nav) {
        var self = this;

        nav.querySelectorAll(".biotern-bottom-link[data-panel-target]").forEach(function (button) {
            button.addEventListener("click", function (event) {
                event.preventDefault();

                var target = button.getAttribute("data-panel-target") || "";
                if (!target) {
                    return;
                }

                var isOpen = sheet.classList.contains("is-open");
                var isSamePanel = sheet.dataset.activePanel === target;
                if (isOpen && isSamePanel) {
                    self.closeSheet(sheet, nav);
                    return;
                }
                self.openSheet(sheet, nav, target, button);
            });
        });

        var backdrop = sheet.querySelector("[data-sheet-close]");
        if (backdrop) {
            backdrop.addEventListener("click", function () {
                self.closeSheet(sheet, nav);
            });
        }

        document.addEventListener("keydown", function (event) {
            if (event.key === "Escape") {
                self.closeSheet(sheet, nav);
            }
        });

        sheet.querySelectorAll(".biotern-bottom-sheet-link").forEach(function (link) {
            link.addEventListener("click", function () {
                self.closeSheet(sheet, nav);
            });
        });
    };

    BottomNavigationState.prototype.init = function () {
        var nav = this.getNav();
        if (!nav) {
            return;
        }

        this.setActiveRoute(nav);

        var sheet = this.getSheet();
        if (!sheet) {
            return;
        }
        this.bindSheetEvents(sheet, nav);
    };

    function boot() {
        var core = global.BioTernNavCore;
        if (!core || !core.RouteResolver) {
            return;
        }

        var state = new BottomNavigationState({
            routeResolver: core.RouteResolver
        });

        function runBottomNavState() {
            state.init();
        }

        if (global.BioTernRuntimeBoot && typeof global.BioTernRuntimeBoot.boot === "function") {
            global.BioTernRuntimeBoot.boot({
                name: "mobile-bottom-nav",
                run: runBottomNavState
            });
            return;
        }

        if (core.Dom && typeof core.Dom.onDomReady === "function") {
            core.Dom.onDomReady(runBottomNavState);
            return;
        }

        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", runBottomNavState);
        } else {
            runBottomNavState();
        }
    }

    boot();
})(window);
