(function () {
    'use strict';

    function parseDateValue(value) {
        if (!value) {
            return null;
        }
        var date = new Date(value);
        return isNaN(date.getTime()) ? null : date;
    }

    function initGlobalDatepickers() {
        if (typeof Datepicker !== 'function') {
            return;
        }

        var fields = document.querySelectorAll('input[type="date"]:not([data-native-date="true"]):not([data-datepicker-bound="1"])');
        fields.forEach(function (input) {
            if (input.disabled || input.readOnly) {
                return;
            }

            var originalValue = input.value || '';
            var minDate = parseDateValue(input.getAttribute('min') || '');
            var maxDate = parseDateValue(input.getAttribute('max') || '');

            input.setAttribute('data-datepicker-bound', '1');
            input.setAttribute('data-original-type', 'date');
            input.setAttribute('type', 'text');
            input.setAttribute('autocomplete', 'off');
            if (!input.getAttribute('placeholder')) {
                input.setAttribute('placeholder', 'YYYY-MM-DD');
            }
            input.setAttribute('pattern', '^\\d{4}-\\d{2}-\\d{2}$');
            input.value = originalValue;

            var options = {
                format: 'yyyy-mm-dd',
                autohide: true,
                todayBtn: true,
                todayBtnMode: 1,
                clearBtn: !input.required,
                container: document.body
            };

            if (minDate) {
                options.minDate = minDate;
            }
            if (maxDate) {
                options.maxDate = maxDate;
            }

            new Datepicker(input, options);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initGlobalDatepickers);
    } else {
        initGlobalDatepickers();
    }
})();
