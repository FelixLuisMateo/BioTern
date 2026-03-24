"use strict";

(function (global) {
    if (!global) {
        return;
    }

    /*
     * Prevent duplicate header-search bootstraps inside common-init.min.js.
     * We run a single shared runtime from modules/shared/header-search-runtime.js.
     */
    global.__bioternHeaderSearchUnifiedInit = true;
    global.__bioternHeaderSearchV2Init = true;
})(window);

