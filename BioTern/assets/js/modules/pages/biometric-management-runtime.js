document.addEventListener('DOMContentLoaded', function () {
    function syncSectionsByCourse(courseSelectId, sectionSelectId) {
        var courseSelect = document.getElementById(courseSelectId);
        var sectionSelect = document.getElementById(sectionSelectId);
        if (!courseSelect || !sectionSelect) {
            return;
        }

        var selectedCourse = parseInt(courseSelect.value || '0', 10);
        var optionElements = sectionSelect.querySelectorAll('option');
        optionElements.forEach(function (option, index) {
            if (index === 0) {
                option.hidden = false;
                return;
            }

            var optionCourse = parseInt(option.getAttribute('data-course-id') || '0', 10);
            var visible = selectedCourse <= 0 || optionCourse === selectedCourse;
            option.hidden = !visible;
        });

        var currentOption = sectionSelect.options[sectionSelect.selectedIndex];
        if (currentOption && currentOption.hidden) {
            sectionSelect.value = '0';
        }
    }

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
    var bridgeEnabledField = document.getElementById('bridgeEnabled');
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
        if (bridgeEnabledField && bridgePresetSelect.value !== 'laptop_custom') {
            bridgeEnabledField.checked = true;
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

    function initMachineQueueWatch() {
        var toastContainer = document.getElementById('machineQueueToastContainer');
        if (!toastContainer) {
            return;
        }

        var watchUrl = toastContainer.getAttribute('data-machine-queue-watch-url') || 'biometric-machine.php?queue_watch_status=1';
        var seenCompletedIds = {};
        var autoRefreshTriggered = false;

        function trimText(value) {
            var text = (value || '').toString().trim();
            if (text.length > 180) {
                return text.slice(0, 180) + '...';
            }
            return text;
        }

        function escapeHtml(value) {
            return (value || '').toString()
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function showQueueToast(item) {
            var id = Number(item && item.id ? item.id : 0);
            if (!id || seenCompletedIds[id]) {
                return;
            }
            seenCompletedIds[id] = true;

            var status = ((item && item.status) || '').toString().toLowerCase();
            var commandName = ((item && item.command_name) || 'command').toString();
            var resultText = trimText(item && item.result_text ? item.result_text : '');
            var completedAt = ((item && item.completed_at) || '').toString();
            var isSuccess = status === 'succeeded';

            var title = isSuccess ? 'Queue Completed' : 'Queue Failed';
            var tone = isSuccess ? 'success' : 'danger';
            var body = '#' + id + ' ' + commandName + (isSuccess ? ' completed.' : ' failed.');

            if (resultText !== '') {
                body += ' ' + resultText;
            }
            if (completedAt !== '') {
                body += ' (' + completedAt + ')';
            }

            var wrapper = document.createElement('div');
            wrapper.className = 'toast align-items-center text-bg-' + tone + ' border-0 mb-2';
            wrapper.setAttribute('role', 'alert');
            wrapper.setAttribute('aria-live', 'assertive');
            wrapper.setAttribute('aria-atomic', 'true');
            wrapper.innerHTML = ''
                + '<div class="d-flex">'
                + '  <div class="toast-body">'
                + '    <div class="fw-semibold mb-1">' + escapeHtml(title) + '</div>'
                + '    <div>' + escapeHtml(body) + '</div>'
                + '  </div>'
                + '  <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>'
                + '</div>';

            toastContainer.appendChild(wrapper);

            if (window.bootstrap && window.bootstrap.Toast) {
                var toast = new window.bootstrap.Toast(wrapper, { delay: isSuccess ? 6000 : 9000 });
                wrapper.addEventListener('hidden.bs.toast', function () {
                    wrapper.remove();
                });
                toast.show();
            } else {
                wrapper.className = 'alert alert-' + tone + ' mb-2 shadow-sm';
                wrapper.innerHTML = '<div class="fw-semibold mb-1">' + escapeHtml(title) + '</div><div>' + escapeHtml(body) + '</div>';
                window.setTimeout(function () {
                    wrapper.remove();
                }, isSuccess ? 6000 : 9000);
            }

            if (isSuccess) {
                var shouldRefresh = ['rename_user', 'delete_user', 'clear_users'].indexOf(commandName) >= 0;
                if (shouldRefresh && !autoRefreshTriggered) {
                    autoRefreshTriggered = true;
                    window.setTimeout(function () {
                        try {
                            var url = new URL(window.location.href);
                            url.searchParams.set('load_users', '1');
                            if (url.searchParams.get('selected_user_id')) {
                                url.searchParams.set('load_user', '1');
                            }
                            window.location.href = url.toString();
                        } catch (error) {
                            window.location.reload();
                        }
                    }, 1200);
                }
            }
        }

        async function pollQueueStatus() {
            try {
                var separator = watchUrl.indexOf('?') === -1 ? '?' : '&';
                var response = await fetch(watchUrl + separator + '_ts=' + Date.now(), {
                    method: 'GET',
                    credentials: 'same-origin',
                    cache: 'no-store'
                });
                if (!response.ok) {
                    return;
                }

                var payload = await response.json();
                if (!payload || !payload.success || !Array.isArray(payload.completed)) {
                    return;
                }

                payload.completed.forEach(showQueueToast);
            } catch (error) {
                // Keep polling silent if a transient network error occurs.
            }
        }

        pollQueueStatus();
        window.setInterval(pollQueueStatus, 4000);
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

    var addCourseSelect = document.getElementById('add_course_id');
    if (addCourseSelect) {
        addCourseSelect.addEventListener('change', function () {
            syncSectionsByCourse('add_course_id', 'add_section_id');
        });
        syncSectionsByCourse('add_course_id', 'add_section_id');
    }

    var filterCourseSelect = document.getElementById('filter_course_id');
    if (filterCourseSelect) {
        filterCourseSelect.addEventListener('change', function () {
            syncSectionsByCourse('filter_course_id', 'filter_section_id');
        });
        syncSectionsByCourse('filter_course_id', 'filter_section_id');
    }

    refreshBridgeWorkerCommand();
    initMachineQueueWatch();
});
