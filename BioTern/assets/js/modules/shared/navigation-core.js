"use strict";

(function (global) {
    if (!global) {
        return;
    }
    if (global.BioTernNavCore) {
        return;
    }

    function normalize(value) {
        return String(value || "").toLowerCase().trim();
    }

    function toArray(listLike) {
        return Array.prototype.slice.call(listLike || []);
    }

    var RouteResolver = {
        normalize: normalize,

        keyFromUrlObject: function (urlObj) {
            if (!urlObj) {
                return "";
            }

            var queryFile = normalize(urlObj.searchParams ? urlObj.searchParams.get("file") : "");
            if (queryFile) {
                queryFile = queryFile.replace(/\\/g, "/");
                var qParts = queryFile.split("/");
                var qLast = qParts[qParts.length - 1] || queryFile;
                if (qLast && qLast.endsWith(".php")) {
                    return qLast;
                }
            }

            var path = normalize(urlObj.pathname || "");
            var parts = path.split("/");
            var last = parts[parts.length - 1] || "";
            if (last.endsWith(".php")) {
                return last;
            }
            return "";
        },

        currentRouteKey: function () {
            try {
                return this.keyFromUrlObject(new URL(global.location.href));
            } catch (err) {
                return "";
            }
        },

        routeKeyFromHref: function (href) {
            try {
                return this.keyFromUrlObject(new URL(href || "", global.location.origin));
            } catch (err) {
                return "";
            }
        },

        routeListFromCsv: function (raw) {
            return String(raw || "")
                .split(",")
                .map(function (value) {
                    return normalize(value);
                })
                .filter(Boolean);
        }
    };

    var Storage = {
        get: function (key, fallbackValue) {
            if (!key) {
                return fallbackValue;
            }
            try {
                var value = global.localStorage.getItem(key);
                return value === null ? fallbackValue : value;
            } catch (err) {
                return fallbackValue;
            }
        },

        set: function (key, value) {
            if (!key) {
                return false;
            }
            try {
                global.localStorage.setItem(key, String(value));
                return true;
            } catch (err) {
                return false;
            }
        },

        getNumber: function (key, fallbackValue) {
            var raw = this.get(key, "");
            var num = parseInt(raw, 10);
            return isNaN(num) ? fallbackValue : num;
        }
    };

    var Dom = {
        toArray: toArray,

        onDomReady: function (callback) {
            if (typeof callback !== "function") {
                return;
            }
            if (document.readyState === "loading") {
                document.addEventListener("DOMContentLoaded", callback);
                return;
            }
            callback();
        }
    };

    global.BioTernNavCore = {
        RouteResolver: RouteResolver,
        Storage: Storage,
        Dom: Dom
    };
})(window);

