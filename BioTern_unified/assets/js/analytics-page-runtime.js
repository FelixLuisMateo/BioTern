(function () {
    'use strict';

    function parseJSON(value, fallback) {
        if (!value) {
            return fallback;
        }
        try {
            return JSON.parse(value);
        } catch (error) {
            return fallback;
        }
    }

    function readData() {
        var dataEl = document.getElementById('analytics-data');
        if (!dataEl) {
            return null;
        }

        return {
            sparklineBounce: parseJSON(dataEl.dataset.sparklineBounce, []),
            sparklineActive: parseJSON(dataEl.dataset.sparklineActive, []),
            sparklineBiometric: parseJSON(dataEl.dataset.sparklineBiometric, []),
            sparklineApproval: parseJSON(dataEl.dataset.sparklineApproval, []),
            visitorsLabels: parseJSON(dataEl.dataset.visitorsLabels, []),
            visitorsStudents: parseJSON(dataEl.dataset.visitorsStudents, []),
            visitorsAttendances: parseJSON(dataEl.dataset.visitorsAttendances, []),
            visitorsInternships: parseJSON(dataEl.dataset.visitorsInternships, []),
            campaignLabels: parseJSON(dataEl.dataset.campaignLabels, []),
            campaignInternal: parseJSON(dataEl.dataset.campaignInternal, []),
            campaignExternal: parseJSON(dataEl.dataset.campaignExternal, []),
            socialOverviewLabels: parseJSON(dataEl.dataset.socialOverviewLabels, []),
            socialOverviewValues: parseJSON(dataEl.dataset.socialOverviewValues, []),
            goalProgress: parseJSON(dataEl.dataset.goalProgress, {})
        };
    }

    function initDateRange() {
        if (typeof window.$ === 'undefined' || typeof window.moment === 'undefined' || !window.$('#reportrange').length || !window.$.fn.daterangepicker) {
            return;
        }

        var start = window.moment().subtract(29, 'days');
        var end = window.moment();

        function setRangeLabel(s, e) {
            window.$('#reportrange span').html(s.format('MMM D, YY') + ' - ' + e.format('MMM D, YY'));
        }

        window.$('#reportrange').daterangepicker({
            startDate: start,
            endDate: end,
            ranges: {
                Today: [window.moment(), window.moment()],
                Yesterday: [window.moment().subtract(1, 'days'), window.moment().subtract(1, 'days')],
                'Last 7 Days': [window.moment().subtract(6, 'days'), window.moment()],
                'Last 30 Days': [window.moment().subtract(29, 'days'), window.moment()],
                'This Month': [window.moment().startOf('month'), window.moment().endOf('month')],
                'Last Month': [window.moment().subtract(1, 'month').startOf('month'), window.moment().subtract(1, 'month').endOf('month')]
            }
        }, setRangeLabel);

        setRangeLabel(start, end);
    }

    function renderSparkline(selector, seriesData, color) {
        var el = document.querySelector(selector);
        if (!el || typeof window.ApexCharts === 'undefined') {
            return;
        }

        new window.ApexCharts(el, {
            chart: { type: 'area', height: 80, sparkline: { enabled: true } },
            series: [{ name: 'Rate', data: seriesData }],
            stroke: { width: 1, curve: 'smooth' },
            fill: {
                opacity: [0.85, 0.25, 1, 1],
                gradient: {
                    inverseColors: false,
                    shade: 'light',
                    type: 'vertical',
                    opacityFrom: 0.5,
                    opacityTo: 0.1,
                    stops: [0, 100, 100, 100]
                }
            },
            yaxis: { min: 0, max: 100 },
            colors: [color],
            dataLabels: { enabled: false }
        }).render();
    }

    function initInternshipPieChart() {
        var pieEl = document.getElementById('internship-pie-chart');
        if (!pieEl || typeof window.ApexCharts === 'undefined') {
            return;
        }

        var pieValues = parseJSON(pieEl.dataset.pieValues, []);
        var pieLabels = parseJSON(pieEl.dataset.pieLabels, []);

        new window.ApexCharts(pieEl, {
            chart: { type: 'pie', height: 240 },
            series: pieValues,
            labels: pieLabels,
            colors: ['#ffc107', '#28a745', '#007bff', '#dc3545'],
            legend: { position: 'bottom' }
        }).render();
    }

    function initCharts(data) {
        if (typeof window.ApexCharts === 'undefined') {
            return;
        }

        renderSparkline('#bounce-rate', data.sparklineBounce, '#64748a');
        renderSparkline('#page-views', data.sparklineActive, '#3454d1');
        renderSparkline('#site-impressions', data.sparklineBiometric, '#e49e3d');
        renderSparkline('#conversions-rate', data.sparklineApproval, '#25b865');

        var visitorsEl = document.querySelector('#visitors-overview-statistics-chart');
        if (visitorsEl) {
            new window.ApexCharts(visitorsEl, {
                chart: { height: 370, type: 'area', stacked: false, toolbar: { show: false } },
                xaxis: {
                    categories: data.visitorsLabels,
                    axisBorder: { show: false },
                    axisTicks: { show: false },
                    labels: { style: { fontSize: '11px', colors: '#64748b' } }
                },
                yaxis: { labels: { style: { fontSize: '11px', color: '#64748b' } } },
                stroke: { curve: 'smooth', width: [1, 1, 1], dashArray: [3, 3, 3], lineCap: 'round' },
                grid: {
                    padding: { left: 0, right: 0 },
                    strokeDashArray: 3,
                    borderColor: '#ebebf3',
                    row: { colors: ['#ebebf3', 'transparent'], opacity: 0.02 }
                },
                legend: { show: false },
                colors: ['#3454d1', '#25b865', '#d13b4c'],
                dataLabels: { enabled: false },
                fill: {
                    type: 'gradient',
                    gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.3, stops: [0, 90, 100] }
                },
                series: [
                    { name: 'New Students', data: data.visitorsStudents, type: 'area' },
                    { name: 'Attendance Logs', data: data.visitorsAttendances, type: 'area' },
                    { name: 'New Internships', data: data.visitorsInternships, type: 'area' }
                ]
            }).render();
        }

        var campaignEl = document.querySelector('#campaign-alytics-bar-chart');
        if (campaignEl) {
            new window.ApexCharts(campaignEl, {
                chart: { type: 'bar', height: 370, toolbar: { show: false } },
                series: [
                    { name: 'Internal Internships', data: data.campaignInternal },
                    { name: 'External Internships', data: data.campaignExternal }
                ],
                plotOptions: { bar: { horizontal: false, endingShape: 'rounded', columnWidth: '30%' } },
                dataLabels: { enabled: false },
                stroke: { show: false, width: 1, colors: ['#fff'] },
                colors: ['#E1E3EA', '#3454d1'],
                xaxis: {
                    categories: data.campaignLabels,
                    axisBorder: { show: false },
                    axisTicks: { show: false },
                    labels: { style: { colors: '#64748b', fontFamily: 'Inter' } }
                },
                yaxis: { labels: { style: { color: '#64748b', fontFamily: 'Inter' } } },
                grid: { strokeDashArray: 3, borderColor: '#e9ecef' },
                tooltip: { style: { colors: '#64748b', fontFamily: 'Inter' } },
                legend: { show: false }
            }).render();
        }

        var socialOverviewEl = document.querySelector('#social-overview-chart');
        if (socialOverviewEl) {
            new window.ApexCharts(socialOverviewEl, {
                chart: { type: 'radar', height: 260, toolbar: { show: false } },
                series: [{ name: 'Coverage %', data: data.socialOverviewValues }],
                labels: data.socialOverviewLabels,
                colors: ['#3454d1'],
                yaxis: { min: 0, max: 100 },
                dataLabels: { enabled: true },
                stroke: { width: 2 },
                fill: { opacity: 0.2 },
                markers: { size: 4 }
            }).render();
        }

        initInternshipPieChart();
    }

    function initGoalProgress(data) {
        if (typeof window.$ === 'undefined' || !window.$.fn.circleProgress) {
            return;
        }

        var progress = data.goalProgress || {};
        window.$('.goal-progress-1').circleProgress({ max: 100, value: Number(progress.marketing || 0), textFormat: 'percent' });
        window.$('.goal-progress-2').circleProgress({ max: 100, value: Number(progress.teams || 0), textFormat: 'percent' });
        window.$('.goal-progress-3').circleProgress({ max: 100, value: Number(progress.ojt || 0), textFormat: 'percent' });
        window.$('.goal-progress-4').circleProgress({ max: 100, value: Number(progress.revenue || 0), textFormat: 'percent' });
    }

    function initCountdowns() {
        if (typeof window.$ === 'undefined' || !window.$.fn.timeTo) {
            return;
        }

        var base = new Date();
        window.$('[data-time-countdown="countdown_1"]').timeTo(new Date(base.getTime() + 10 * 24 * 60 * 60 * 1000));
        window.$('[data-time-countdown="countdown_2"]').timeTo(new Date(base.getTime() + 15 * 24 * 60 * 60 * 1000));
        window.$('[data-time-countdown="countdown_3"]').timeTo(new Date(base.getTime() + 20 * 24 * 60 * 60 * 1000));
        window.$('[data-time-countdown="countdown_4"]').timeTo(new Date(base.getTime() + 25 * 24 * 60 * 60 * 1000));
        window.$('[data-time-countdown="countdown_5"]').timeTo(new Date(base.getTime() + 30 * 24 * 60 * 60 * 1000));
    }

    function initAnalyticsPage() {
        var data = readData();
        if (!data) {
            return;
        }

        initDateRange();
        initCharts(data);
        initGoalProgress(data);
        initCountdowns();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAnalyticsPage);
    } else {
        initAnalyticsPage();
    }
})();
