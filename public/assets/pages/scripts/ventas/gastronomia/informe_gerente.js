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

    function renderPie(canvasId, chartData, options) {
        var canvas = document.getElementById(canvasId);
        if (!canvas || typeof Chart === 'undefined') {
            return;
        }

        var labels = (chartData && chartData.labels) ? chartData.labels : [];
        var values = (chartData && chartData.values) ? chartData.values : [];
        var opts = options || {};
        var chartType = opts.type || 'pie';
        var withPct = !!opts.withPct;

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
            type: chartType,
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
                            var text = label + ': ' + fmtMoney(val);
                            if (withPct) {
                                var total = (data.datasets[0].data || []).reduce(function (acc, n) {
                                    return acc + Math.abs(Number(n) || 0);
                                }, 0);
                                if (total > 0) {
                                    text += ' (' + ((Math.abs(val) / total) * 100).toFixed(1).replace('.', ',') + '%)';
                                }
                            }
                            return text;
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
                $('#fecha_desde').val(v);
                $('#fecha_hasta').val(v);
            }
        });

        var overlay = document.getElementById('informe-gerente-overlay');
        var exportSafetyTimer = null;

        function mostrarOverlay(titulo, subtitulo) {
            if (!overlay) {
                return;
            }
            if (titulo) {
                var t = document.getElementById('informe-gerente-overlay-titulo');
                if (t) {
                    t.textContent = titulo;
                }
            }
            if (subtitulo) {
                var s = document.getElementById('informe-gerente-overlay-subtitulo');
                if (s) {
                    s.textContent = subtitulo;
                }
            }
            overlay.classList.remove('d-none');
            overlay.style.display = 'flex';
            overlay.setAttribute('aria-hidden', 'false');
        }
        function ocultarOverlay() {
            if (!overlay) {
                return;
            }
            if (exportSafetyTimer) {
                clearTimeout(exportSafetyTimer);
                exportSafetyTimer = null;
            }
            overlay.classList.add('d-none');
            overlay.style.display = '';
            overlay.setAttribute('aria-hidden', 'true');
        }
        window.addEventListener('pageshow', ocultarOverlay);
        window.addEventListener('pagehide', ocultarOverlay);
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                ocultarOverlay();
            }
        });

        $('#form-informe-gerente').on('submit', function () {
            var form = this;
            if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                return;
            }
            mostrarOverlay(
                'Generando informe…',
                'Puede demorar según el período. No cierre la página.'
            );
        });

        // Descargas no navegan: el overlay no se oculta solo. Usar fetch+blob.
        $(document).on('click', 'a[href*="listar-gastronomia-informe-gerente"]', function (e) {
            var href = $(this).attr('href');
            if (!href || href === '#') {
                return;
            }
            e.preventDefault();

            mostrarOverlay(
                'Exportando…',
                'El archivo se descarga al terminar. Pulse Esc para cerrar este aviso.'
            );
            exportSafetyTimer = setTimeout(ocultarOverlay, 180000);

            fetch(href, {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            })
                .then(function (res) {
                    if (!res.ok) {
                        return res.text().then(function (body) {
                            var msg = 'HTTP ' + res.status;
                            if (body && body.indexOf('ZipArchive') !== -1) {
                                msg = 'Error al armar el archivo (permisos temporales).';
                            } else if (body && body.length < 400) {
                                msg = body.replace(/<[^>]+>/g, ' ').trim() || msg;
                            }
                            throw new Error(msg);
                        });
                    }
                    var cd = res.headers.get('Content-Disposition') || '';
                    var name = 'informe_gerente_gastronomia';
                    var m = /filename\*=UTF-8''([^;]+)|filename=\"?([^\";]+)\"?/i.exec(cd);
                    if (m) {
                        name = decodeURIComponent((m[1] || m[2] || name).trim());
                    } else if (/PPTX/i.test(href)) {
                        name += '.pptx';
                    } else if (/EXCEL/i.test(href)) {
                        name += '.xlsx';
                    } else if (/CSV/i.test(href)) {
                        name += '.csv';
                    } else if (/PDF/i.test(href)) {
                        name += '.pdf';
                    }
                    return res.blob().then(function (blob) {
                        return { blob: blob, name: name };
                    });
                })
                .then(function (data) {
                    var url = window.URL.createObjectURL(data.blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = data.name;
                    document.body.appendChild(a);
                    a.click();
                    a.remove();
                    setTimeout(function () {
                        window.URL.revokeObjectURL(url);
                    }, 1500);
                })
                .catch(function (err) {
                    var msg = (err && err.message) ? err.message : String(err);
                    window.alert('No se pudo exportar el informe.\n' + msg);
                })
                .finally(ocultarOverlay);
        });

        var cfg = window.INFORME_GERENTE_GASTRONOMIA || {};
        var informe = cfg.informe;
        if (!informe || !informe.charts) {
            return;
        }

        renderPie('chart-ventas-turno', informe.charts.turno);
        renderPie('chart-ventas-pv', informe.charts.puntoventa);
        renderPie('chart-medio-pago', informe.charts.medio_pago, { type: 'doughnut', withPct: true });
        renderPie('chart-descuentos', informe.charts.descuento);
        renderPie('chart-recepciones-dia', informe.charts.recepciones_dia);
        renderPie('chart-recepciones-mes', informe.charts.recepciones_mes);
        renderBar('chart-articulos-dia', informe.charts.articulos_dia);
        renderBar('chart-articulos-mes', informe.charts.articulos_mes);
    });
}(jQuery));
