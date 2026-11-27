/* Tablero de compras: gráficos (Chart.js 2.x). Datos en window.__tableroCompras. */
(function () {
    'use strict';

    var D = window.__tableroCompras || null;
    if (!D || typeof Chart === 'undefined') {
        return;
    }

    var PALETA = ['#2471A3', '#E67E22', '#27AE60', '#C0392B', '#8E44AD', '#16A085', '#F1C40F', '#7F8C8D', '#D35400', '#2C3E50'];
    var COLOR_ESTADO = { 'Finalizada': '#27AE60', 'Entrega parcial': '#F1C40F', 'Pendiente': '#2471A3' };
    var COLOR_VENC = { 'A tiempo': '#2471A3', 'Vencida': '#C0392B' };
    var dibujados = {};

    function fmt(n, dec) {
        return Number(n || 0).toLocaleString('es-AR', { minimumFractionDigits: dec || 0, maximumFractionDigits: dec || 0 });
    }

    function color(i) {
        return PALETA[i % PALETA.length];
    }

    function colores(labels, mapa) {
        return labels.map(function (l, i) {
            return (mapa && mapa[l]) || color(i);
        });
    }

    function lienzo(id) {
        var el = document.getElementById(id);
        return el ? el.getContext('2d') : null;
    }

    function sinDatos(valores) {
        return !valores || !valores.length || valores.every(function (v) { return !v; });
    }

    function torta(id, labels, values, mapaColores) {
        var ctx = lienzo(id);
        if (!ctx) { return; }
        new Chart(ctx, {
            type: 'doughnut',
            data: { labels: labels, datasets: [{ data: values, backgroundColor: colores(labels, mapaColores) }] },
            options: {
                maintainAspectRatio: false,
                legend: { position: 'bottom' },
                tooltips: {
                    callbacks: {
                        label: function (item, data) {
                            var total = data.datasets[0].data.reduce(function (a, b) { return a + b; }, 0) || 1;
                            var v = data.datasets[0].data[item.index];
                            return data.labels[item.index] + ': ' + fmt(v) + ' (' + fmt(v * 100 / total, 1) + '%)';
                        }
                    }
                }
            }
        });
    }

    function barrasApiladas(id, series, mapaColores) {
        var ctx = lienzo(id);
        if (!ctx) { return; }
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: D.meses,
                datasets: series.map(function (s, i) {
                    return { label: s.label, data: s.data, backgroundColor: (mapaColores && mapaColores[s.label]) || color(i) };
                })
            },
            options: {
                maintainAspectRatio: false,
                legend: { position: 'bottom' },
                scales: {
                    xAxes: [{ stacked: true }],
                    yAxes: [{ stacked: true, ticks: { beginAtZero: true, callback: function (v) { return fmt(v); } } }]
                },
                tooltips: { mode: 'index', intersect: false }
            }
        });
    }

    function barrasHorizontales(id, labels, values, etiqueta, decimales, formatoValor) {
        var ctx = lienzo(id);
        if (!ctx) { return; }
        new Chart(ctx, {
            type: 'horizontalBar',
            data: { labels: labels, datasets: [{ label: etiqueta, data: values, backgroundColor: '#2471A3' }] },
            options: {
                maintainAspectRatio: false,
                legend: { display: false },
                scales: {
                    xAxes: [{ ticks: { beginAtZero: true, callback: function (v) { return formatoValor ? formatoValor(v) : fmt(v, decimales); } } }],
                    yAxes: [{ ticks: { autoSkip: false, fontSize: 10 } }]
                },
                tooltips: {
                    callbacks: {
                        label: function (item) {
                            var v = item.xLabel;
                            return etiqueta + ': ' + (formatoValor ? formatoValor(v) : fmt(v, decimales));
                        }
                    }
                }
            }
        });
    }

    function lineas(id, series, decimales) {
        var ctx = lienzo(id);
        if (!ctx) { return; }
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: D.meses,
                datasets: series.map(function (s, i) {
                    return {
                        label: s.label,
                        data: s.data,
                        borderColor: color(i),
                        backgroundColor: color(i),
                        fill: false,
                        lineTension: 0.15,
                        spanGaps: true,
                        borderWidth: 2,
                        pointRadius: 3
                    };
                })
            },
            options: {
                maintainAspectRatio: false,
                legend: { position: 'bottom' },
                scales: { yAxes: [{ ticks: { beginAtZero: true, callback: function (v) { return fmt(v, decimales); } } }] },
                tooltips: {
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        label: function (item, data) {
                            return data.datasets[item.datasetIndex].label + ': ' + (item.yLabel === null ? '—' : fmt(item.yLabel, decimales));
                        }
                    }
                }
            }
        });
    }

    function barras(id, label, values, decimales, colorBarra) {
        var ctx = lienzo(id);
        if (!ctx) { return; }
        new Chart(ctx, {
            type: 'bar',
            data: { labels: D.meses, datasets: [{ label: label, data: values, backgroundColor: colorBarra || '#2471A3' }] },
            options: {
                maintainAspectRatio: false,
                legend: { display: false },
                scales: { yAxes: [{ ticks: { beginAtZero: true, callback: function (v) { return fmt(v, decimales); } } }] },
                tooltips: {
                    callbacks: {
                        label: function (item) { return label + ': ' + fmt(item.yLabel, decimales); }
                    }
                }
            }
        });
    }

    /* Barra 100%: gestión del comprador vs. entrega del proveedor sobre el ciclo total. */
    function cicloCien() {
        var ctx = lienzo('tc-ciclo');
        if (!ctx) { return; }
        var g = D.ciclo.gestion || 0;
        var e = D.ciclo.entrega || 0;
        var total = g + e;
        if (total <= 0) { return; }
        new Chart(ctx, {
            type: 'horizontalBar',
            data: {
                labels: ['Ciclo'],
                datasets: [
                    { label: 'Gestión del comprador', data: [g * 100 / total], dias: g, backgroundColor: '#2471A3' },
                    { label: 'Entrega del proveedor', data: [e * 100 / total], dias: e, backgroundColor: '#E67E22' }
                ]
            },
            options: {
                maintainAspectRatio: false,
                legend: { position: 'bottom' },
                scales: {
                    xAxes: [{ stacked: true, ticks: { min: 0, max: 100, callback: function (v) { return v + '%'; } } }],
                    yAxes: [{ stacked: true }]
                },
                tooltips: {
                    callbacks: {
                        label: function (item, data) {
                            var ds = data.datasets[item.datasetIndex];
                            return ds.label + ': ' + fmt(ds.dias, 1) + ' días (' + fmt(item.xLabel, 1) + '%)';
                        }
                    }
                }
            }
        });
    }

    var constructores = {
        compras: function () {
            torta('tc-estados', D.estados.labels, D.estados.values, COLOR_ESTADO);
            barrasApiladas('tc-estados-mes', D.estados_mes, COLOR_ESTADO);
            torta('tc-pendientes', D.pendientes.labels, D.pendientes.values, COLOR_VENC);
            barrasApiladas('tc-vencidas-mes', D.vencidas_mes, COLOR_VENC);
            lineas('tc-diarias', [
                { label: 'Requisiciones por día', data: D.diarias.req },
                { label: 'OC por día', data: D.diarias.oc }
            ], 1);
            torta('tc-urgentes', D.urgentes.labels, D.urgentes.values, { 'Urgente': '#C0392B', 'No urgente': '#2471A3' });
            cicloCien();
        },
        compradores: function () {
            barrasHorizontales('tc-comp-oc', D.compradores_oc.labels, D.compradores_oc.values, 'OC', 0);
            barrasHorizontales('tc-comp-gestion', D.compradores_gestion.labels, D.compradores_gestion.values, 'Días de gestión', 1);
            lineas('tc-comp-oc-mes', D.compradores_oc_mes, 0);
            lineas('tc-comp-gestion-mes', D.compradores_gestion_mes, 1);
            torta('tc-empresas', D.empresas.labels, D.empresas.values);
        },
        proveedores: function () {
            barrasHorizontales('tc-prov-entrega', D.prov_entrega.labels, D.prov_entrega.values, 'Días de entrega', 1);
            barrasHorizontales('tc-prov-lineas', D.prov_lineas.labels, D.prov_lineas.values, 'Líneas', 0);
            barrasHorizontales('tc-prov-monto', D.prov_monto.labels, D.prov_monto.values, 'Monto $', 0, function (v) { return '$ ' + fmt(v, 0); });
            barras('tc-monto-mes', 'Monto $ por mes', D.monto_mes, 0, '#27AE60');
            barras('tc-prov-activos', 'Proveedores activos por mes', D.prov_activos_mes, 0, '#8E44AD');
        },
        precios: function () {
            if (!D.precios) { return; }
            lineas('tc-precios', D.precios.series.map(function (s) {
                return { label: s.nombre, data: s.valores };
            }), 2);
            barras('tc-precios-cantidad', 'Cantidad comprada', D.precios.cantidad, 2, '#E67E22');
        }
    };

    function dibujar(tab) {
        if (dibujados[tab] || !constructores[tab]) {
            return;
        }
        dibujados[tab] = true;
        constructores[tab]();
    }

    $(function () {
        // Los lienzos de una solapa oculta miden 0: se dibuja al mostrarla.
        var activa = $('#tablero-tabs a.active').data('tab');
        if (activa) {
            dibujar(activa);
        }
        $('#tablero-tabs a[data-toggle="tab"]').on('shown.bs.tab', function () {
            dibujar($(this).data('tab'));
        });
    });
}());
