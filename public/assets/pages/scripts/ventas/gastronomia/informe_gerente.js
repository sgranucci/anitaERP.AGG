(function ($) {
    'use strict';

    var PALETTE = [
        '#3c8dbc', '#00a65a', '#f39c12', '#f56954', '#605ca8',
        '#d81b60', '#39cccc', '#ff851b', '#001f3f', '#01ff70',
    ];

    function fmtMoney(n) {
        return '$' + Number(n || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function fmtQty(n) {
        return Number(n || 0).toLocaleString('es-AR', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
    }

    function renderPie(canvasId, chartData) {
        var canvas = document.getElementById(canvasId);
        if (!canvas || typeof Chart === 'undefined') {
            return;
        }

        var labels = (chartData && chartData.labels) ? chartData.labels : [];
        var values = (chartData && chartData.values) ? chartData.values : [];

        if (!labels.length) {
            var parent = canvas.parentElement;
            if (parent) {
                parent.innerHTML = '<p class="text-muted text-center py-5 mb-0">Sin datos para graficar.</p>';
            }
            return;
        }

        var colors = labels.map(function (_l, i) {
            return PALETTE[i % PALETTE.length];
        });

        new Chart(canvas.getContext('2d'), {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: colors,
                }],
            },
            options: {
                maintainAspectRatio: false,
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 12, fontSize: 11 },
                },
                tooltips: {
                    callbacks: {
                        label: function (item, data) {
                            var label = data.labels[item.index] || '';
                            var val = data.datasets[0].data[item.index] || 0;
                            return label + ': ' + fmtMoney(val);
                        },
                    },
                },
            },
        });
    }

    function renderBar(canvasId, chartData) {
        var canvas = document.getElementById(canvasId);
        if (!canvas || typeof Chart === 'undefined') {
            return;
        }

        var labels = (chartData && chartData.labels) ? chartData.labels : [];
        var values = (chartData && chartData.values) ? chartData.values : [];
        var metric = (chartData && chartData.metric) ? chartData.metric : 'cantidad';

        if (!labels.length) {
            var parent = canvas.parentElement;
            if (parent) {
                parent.innerHTML = '<p class="text-muted text-center py-5 mb-0">Sin datos para graficar.</p>';
            }
            return;
        }

        new Chart(canvas.getContext('2d'), {
            type: 'horizontalBar',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: '#3c8dbc',
                    borderColor: '#367fa9',
                    borderWidth: 1,
                }],
            },
            options: {
                maintainAspectRatio: false,
                legend: { display: false },
                tooltips: {
                    callbacks: {
                        label: function (item, data) {
                            var val = data.datasets[0].data[item.index] || 0;
                            return metric === 'importe' ? fmtMoney(val) : fmtQty(val);
                        },
                    },
                },
                scales: {
                    xAxes: [{
                        ticks: {
                            beginAtZero: true,
                            callback: function (value) {
                                return metric === 'importe' ? fmtMoney(value) : fmtQty(value);
                            },
                        },
                    }],
                    yAxes: [{
                        ticks: {
                            fontSize: 10,
                        },
                    }],
                },
            },
        });
    }

    $(function () {
        $('#jornada_historial').on('change', function () {
            var v = $(this).val();
            if (v) {
                $('#fecha_jornada').val(v);
            }
        });

        var cfg = window.INFORME_GERENTE_GASTRONOMIA || {};
        var informe = cfg.informe;
        if (!informe || !informe.charts) {
            return;
        }

        renderPie('chart-ventas-turno', informe.charts.turno);
        renderPie('chart-ventas-pv', informe.charts.puntoventa);
        renderPie('chart-descuentos', informe.charts.descuento);
        renderPie('chart-recepciones-dia', informe.charts.recepciones_dia);
        renderPie('chart-recepciones-mes', informe.charts.recepciones_mes);
        renderBar('chart-articulos-dia', informe.charts.articulos_dia);
        renderBar('chart-articulos-mes', informe.charts.articulos_mes);
    });
}(jQuery));
