"use strict";

(function (global) {
    if (!global) {
        return;
    }
    if (global.BioTernUiStateCore) {
        return;
    }

    function toStringValue(value) {
        return String(value == null ? "" : value);
    }

    function get(key, fallbackValue) {
        if (!key) {
            return fallbackValue;
        }
        try {
            var value = global.localStorage.getItem(key);
            return value === null ? fallbackValue : value;
        } catch (err) {
            return fallbackValue;
        }
    }

    function set(key, value) {
        if (!key) {
            return false;
        }
        try {
            global.localStorage.setItem(key, toStringValue(value));
            return true;
        } catch (err) {
            return false;
        }
    }

    function remove(key) {
        if (!key) {
            return false;
        }
        try {
            global.localStorage.removeItem(key);
            return true;
        } catch (err) {
            return false;
        }
    }

    function getFirst(keys) {
        var list = Array.isArray(keys) ? keys : [];
        for (var i = 0; i < list.length; i += 1) {
            var value = get(list[i], null);
            if (value) {
                return value;
            }
        }
        return null;
    }

    function hasAny(keys) {
        var list = Array.isArray(keys) ? keys : [];
        for (var i = 0; i < list.length; i += 1) {
            if (get(list[i], null) !== null) {
                return true;
            }
        }
        return false;
    }

    function getJson(key, fallbackValue) {
        var raw = get(key, null);
        if (!raw) {
            return fallbackValue;
        }
        try {
            return JSON.parse(raw);
        } catch (err) {
            return fallbackValue;
        }
    }

    function setJson(key, value) {
        try {
            return set(key, JSON.stringify(value));
        } catch (err) {
            return false;
        }
    }

    function onReady(callback) {
        if (typeof callback !== "function") {
            return;
        }
        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", callback);
            return;
        }
        callback();
    }

    global.BioTernUiStateCore = {
        storage: {
            get: get,
            set: set,
            remove: remove,
            getFirst: getFirst,
            hasAny: hasAny,
            getJson: getJson,
            setJson: setJson
        },
        dom: {
            onReady: onReady
        }
    };
})(window);

