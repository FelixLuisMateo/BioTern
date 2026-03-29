document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            var message = form.getAttribute('data-confirm');
            if (message && !window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    var presetSelect = document.getElementById('routerPresetSelect');
    var copyButton = document.getElementById('copyConnectorToMachineBtn');
    var connectorFields = {
        ip: document.getElementById('connectorIpField'),
        gateway: document.getElementById('connectorGatewayField'),
        mask: document.getElementById('connectorMaskField'),
        port: document.getElementById('connectorPortField')
    };
    var machineFields = {
        ip: document.getElementById('machineIpField'),
        gateway: document.getElementById('machineGatewayField'),
        mask: document.getElementById('machineMaskField'),
        port: document.getElementById('machinePortField')
    };

    function applyPreset() {
        if (!presetSelect) {
            return;
        }

        var option = presetSelect.options[presetSelect.selectedIndex];
        if (!option) {
            return;
        }

        if (connectorFields.ip) {
            connectorFields.ip.value = option.dataset.ip || '';
        }
        if (connectorFields.gateway) {
            connectorFields.gateway.value = option.dataset.gateway || '';
        }
        if (connectorFields.mask) {
            connectorFields.mask.value = option.dataset.mask || '';
        }
        if (connectorFields.port) {
            connectorFields.port.value = option.dataset.port || '5001';
        }
    }

    function copyConnectorToMachine() {
        if (machineFields.ip && connectorFields.ip) {
            machineFields.ip.value = connectorFields.ip.value;
        }
        if (machineFields.gateway && connectorFields.gateway) {
            machineFields.gateway.value = connectorFields.gateway.value;
        }
        if (machineFields.mask && connectorFields.mask) {
            machineFields.mask.value = connectorFields.mask.value;
        }
        if (machineFields.port && connectorFields.port) {
            machineFields.port.value = connectorFields.port.value;
        }
    }

    if (presetSelect) {
        presetSelect.addEventListener('change', applyPreset);
    }

    if (copyButton) {
        copyButton.addEventListener('click', copyConnectorToMachine);
    }
});
