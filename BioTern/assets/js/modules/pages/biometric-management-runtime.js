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
    var bridgePresetSelect = document.getElementById('bridgePresetSelect');
    var copyButton = document.getElementById('copyConnectorToMachineBtn');
    var bridgeWorkerCommandField = document.getElementById('bridgeWorkerCommandField');
    var bridgeTaskInstallCommandField = document.getElementById('bridgeTaskInstallCommandField');
    var bridgeTaskStatusCommandField = document.getElementById('bridgeTaskStatusCommandField');
    var copyBridgeWorkerCmdBtn = document.getElementById('copyBridgeWorkerCmdBtn');
    var copyBridgeTaskInstallCmdBtn = document.getElementById('copyBridgeTaskInstallCmdBtn');
    var copyBridgeTaskStatusCmdBtn = document.getElementById('copyBridgeTaskStatusCmdBtn');
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
    var bridgeFields = {
        cloudBaseUrl: document.getElementById('bridgeCloudBaseUrlField'),
        ingestPath: document.getElementById('bridgeIngestPathField'),
        ingestApiToken: document.getElementById('bridgeIngestApiTokenField'),
        pollSeconds: document.getElementById('bridgePollSecondsField'),
        ip: document.getElementById('bridgeIpField'),
        gateway: document.getElementById('bridgeGatewayField'),
        mask: document.getElementById('bridgeMaskField'),
        port: document.getElementById('bridgePortField'),
        deviceNumber: document.getElementById('bridgeDeviceNumberField'),
        outputPath: document.getElementById('bridgeOutputPathField')
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

    function applyBridgePreset() {
        if (!bridgePresetSelect) {
            return;
        }

        var option = bridgePresetSelect.options[bridgePresetSelect.selectedIndex];
        if (!option) {
            return;
        }

        if (bridgeFields.cloudBaseUrl) {
            bridgeFields.cloudBaseUrl.value = option.dataset.cloudBaseUrl || '';
        }
        if (bridgeFields.ingestPath) {
            bridgeFields.ingestPath.value = option.dataset.ingestPath || '/api/f20h_ingest.php';
        }
        if (bridgeFields.ingestApiToken) {
            bridgeFields.ingestApiToken.value = option.dataset.ingestApiToken || '';
        }
        if (bridgeFields.pollSeconds) {
            bridgeFields.pollSeconds.value = option.dataset.pollSeconds || '30';
        }
        if (bridgeFields.ip) {
            bridgeFields.ip.value = option.dataset.ip || '';
        }
        if (bridgeFields.gateway) {
            bridgeFields.gateway.value = option.dataset.gateway || '';
        }
        if (bridgeFields.mask) {
            bridgeFields.mask.value = option.dataset.mask || '255.255.255.0';
        }
        if (bridgeFields.port) {
            bridgeFields.port.value = option.dataset.port || '5001';
        }
        if (bridgeFields.deviceNumber) {
            bridgeFields.deviceNumber.value = option.dataset.deviceNumber || '1';
        }
        if (bridgeFields.outputPath) {
            bridgeFields.outputPath.value = option.dataset.outputPath || '';
        }

        refreshBridgeWorkerCommand();
    }

    function buildBridgeWorkerCommand() {
        var siteBaseUrl = bridgeFields.cloudBaseUrl && bridgeFields.cloudBaseUrl.value
            ? bridgeFields.cloudBaseUrl.value.trim()
            : 'https://your-app.vercel.app';
        var bridgeToken = bridgeFields && document.getElementById('bridgeTokenField')
            ? document.getElementById('bridgeTokenField').value.trim()
            : 'YOUR_BRIDGE_TOKEN';

        if (!bridgeToken) {
            bridgeToken = 'YOUR_BRIDGE_TOKEN';
        }

        return 'powershell -NoProfile -ExecutionPolicy Bypass -File ".\\tools\\bridge-worker.ps1" -SiteBaseUrl "'
            + siteBaseUrl
            + '" -BridgeToken "'
            + bridgeToken
            + '" -WorkspaceRoot "."';
    }

    function buildBridgeTaskInstallCommand() {
        var siteBaseUrl = bridgeFields.cloudBaseUrl && bridgeFields.cloudBaseUrl.value
            ? bridgeFields.cloudBaseUrl.value.trim()
            : 'https://your-app.vercel.app';
        var bridgeToken = bridgeFields && document.getElementById('bridgeTokenField')
            ? document.getElementById('bridgeTokenField').value.trim()
            : 'YOUR_BRIDGE_TOKEN';

        if (!bridgeToken) {
            bridgeToken = 'YOUR_BRIDGE_TOKEN';
        }

        return 'powershell -NoProfile -ExecutionPolicy Bypass -File ".\\tools\\install-bridge-worker-task.ps1" -SiteBaseUrl "'
            + siteBaseUrl
            + '" -BridgeToken "'
            + bridgeToken
            + '" -TaskName "BioTernBridgeWorker"';
    }

    function refreshBridgeWorkerCommand() {
        if (!bridgeWorkerCommandField) {
            return;
        }

        bridgeWorkerCommandField.value = buildBridgeWorkerCommand();

        if (bridgeTaskInstallCommandField) {
            bridgeTaskInstallCommandField.value = buildBridgeTaskInstallCommand();
        }

        if (bridgeTaskStatusCommandField) {
            bridgeTaskStatusCommandField.value = 'powershell -NoProfile -ExecutionPolicy Bypass -File ".\\tools\\manage-bridge-worker-task.ps1" -Action status -TaskName "BioTernBridgeWorker"';
        }
    }

    function copyFieldValue(field) {
        if (!field) {
            return;
        }

        field.focus();
        field.select();

        var value = field.value;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(value);
        } else {
            document.execCommand('copy');
        }
    }

    if (presetSelect) {
        presetSelect.addEventListener('change', applyPreset);
    }

    if (copyButton) {
        copyButton.addEventListener('click', copyConnectorToMachine);
    }

    if (bridgePresetSelect) {
        bridgePresetSelect.addEventListener('change', applyBridgePreset);
    }

    if (bridgeFields.cloudBaseUrl) {
        bridgeFields.cloudBaseUrl.addEventListener('input', refreshBridgeWorkerCommand);
    }

    var bridgeTokenField = document.getElementById('bridgeTokenField');
    if (bridgeTokenField) {
        bridgeTokenField.addEventListener('input', refreshBridgeWorkerCommand);
    }

    if (copyBridgeWorkerCmdBtn) {
        copyBridgeWorkerCmdBtn.addEventListener('click', function () {
            copyFieldValue(bridgeWorkerCommandField);
        });
    }

    if (copyBridgeTaskInstallCmdBtn) {
        copyBridgeTaskInstallCmdBtn.addEventListener('click', function () {
            copyFieldValue(bridgeTaskInstallCommandField);
        });
    }

    if (copyBridgeTaskStatusCmdBtn) {
        copyBridgeTaskStatusCmdBtn.addEventListener('click', function () {
            copyFieldValue(bridgeTaskStatusCommandField);
        });
    }

    refreshBridgeWorkerCommand();
});
