"use strict";

(function (global) {
    function hasJQuery() {
        return !!(global.jQuery && global.jQuery.fn);
    }

    function initDataTable(selector, options) {
        if (!hasJQuery()) {
            return false;
        }

        var $ = global.jQuery;
        var tableSelector = selector || "#customerList";
        if (!$.fn.DataTable) {
            return false;
        }

        try {
            if ($.fn.DataTable.isDataTable(tableSelector)) {
                return true;
            }
            $(tableSelector).DataTable(
                options || {
                    pageLength: 10,
                    lengthChange: false,
                    dom: "rtip",
                    order: []
                }
            );
            return true;
        } catch (err) {
            return false;
        }
    }

    function initChecklist(options) {
        if (!hasJQuery()) {
            return false;
        }

        var cfg = options || {};
        var $ = global.jQuery;
        var checkAllSelector = cfg.checkAllSelector || "#checkAllCustomer";
        var checkboxSelector = cfg.checkboxSelector || ".checkbox";
        var itemsWrapperSelector = cfg.itemsWrapperSelector || ".items-wrapper";
        var selectedClass = cfg.selectedClass || "selected";
        var itemSelector = cfg.itemSelector || ".single-items";

        $(checkAllSelector).off("change.bioternCustomers").on("change.bioternCustomers", function () {
            if (this.checked) {
                $(checkboxSelector).each(function () {
                    this.checked = true;
                    $(this).closest(itemSelector).addClass(selectedClass);
                });
                return;
            }
            $(checkboxSelector).each(function () {
                this.checked = false;
                $(this).closest(itemSelector).removeClass(selectedClass);
            });
        });

        $(checkboxSelector).off("click.bioternCustomers").on("click.bioternCustomers", function () {
            var hasUnchecked = false;
            $(checkboxSelector).each(function () {
                if (!this.checked) {
                    hasUnchecked = true;
                }
            });
            $(checkAllSelector).prop("checked", !hasUnchecked);
        });

        $(itemsWrapperSelector)
            .off("click.bioternCustomers", "input:checkbox")
            .on("click.bioternCustomers", "input:checkbox", function () {
                $(this).closest(itemSelector).toggleClass(selectedClass, this.checked);
            });

        $(itemsWrapperSelector + " input:checkbox:checked")
            .closest(itemSelector)
            .addClass(selectedClass);

        return true;
    }

    function initDefaults() {
        initDataTable("#customerList");
        initChecklist();
    }

    global.BioTernCustomersRuntime = {
        initDataTable: initDataTable,
        initChecklist: initChecklist,
        initDefaults: initDefaults
    };
})(window);
