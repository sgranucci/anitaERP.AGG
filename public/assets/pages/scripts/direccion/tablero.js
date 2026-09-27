(function () {
    function listo(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    function pesos(n) {
        var v = Number(n) || 0;
        return v.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    listo(function () {
        var preset = document.getElementById('td-preset');
        var desdeInput = document.getElementById('td-desde');
        var hastaInput = document.getElementById('td-hasta');
        function ymd(fecha) {
            var mes = String(fecha.getMonth() + 1).padStart(2, '0');
            var diaN = String(fecha.getDate()).padStart(2, '0');
            return fecha.getFullYear() + '-' + mes + '-' + diaN;
        }
        function aplicarAtajo() {
            if (!preset || !desdeInput || !hastaInput) {
                return;
            }
            var modo = preset.value;
            if (modo === 'rango') {
                return;
            }
            var hoy = new Date();
            var desde = new Date(hoy.getFullYear(), hoy.getMonth(), hoy.getDate());
            var hasta = new Date(desde.getTime());
            if (modo === 'ayer') {
                desde.setDate(desde.getDate() - 1);
                hasta = new Date(desde.getTime());
            } else if (modo === 'dia') {
                if (desdeInput.value) {
                    var partes = desdeInput.value.split('-');
                    desde = new Date(Number(partes[0]), Number(partes[1]) - 1, Number(partes[2]));
                }
                hasta = new Date(desde.getTime());
            } else if (modo === 'mes') {
                desde = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
            } else if (modo === 'mes_anterior') {
                desde = new Date(hoy.getFullYear(), hoy.getMonth() - 1, 1);
                hasta = new Date(hoy.getFullYear(), hoy.getMonth(), 0);
            } else if (modo === 'anio') {
                desde = new Date(hoy.getFullYear(), 0, 1);
            }
            desdeInput.value = ymd(desde);
            hastaInput.value = ymd(hasta);
        }
        if (preset) {
            preset.addEventListener('change', aplicarAtajo);
        }
        [desdeInput, hastaInput].forEach(function (input) {
            if (!input) {
                return;
            }
            input.addEventListener('change', function () {
                if (preset) {
                    preset.value = desdeInput.value && desdeInput.value === hastaInput.value ? 'dia' : 'rango';
                }
            });
        });

        var datos = window.TABLERO_DIRECCION || {};
        if (window.Chart && datos.serie) {
            var lienzo = document.getElementById('td-chart-serie');
            if (lienzo) {
                new Chart(lienzo.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: datos.serie.map(function (p) { return p.etiqueta; }),
                        datasets: [
                            {
                                label: 'Ventas',
                                data: datos.serie.map(function (p) { return p.ventas; }),
                                borderColor: '#1A5276',
                                backgroundColor: 'rgba(26,82,118,.12)',
                                fill: true,
                                lineTension: 0.25,
                                pointRadius: 3
                            },
                            {
                                label: 'Compras',
                                data: datos.serie.map(function (p) { return p.compras; }),
                                borderColor: '#B9770E',
                                backgroundColor: 'rgba(185,119,14,.08)',
                                fill: false,
                                lineTension: 0.25,
                                pointRadius: 3
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        legend: { position: 'bottom' },
                        tooltips: {
                            callbacks: {
                                label: function (item, data) {
                                    var ds = data.datasets[item.datasetIndex].label;
                                    return ds + ': $ ' + pesos(item.yLabel);
                                }
                            }
                        },
                        scales: {
                            yAxes: [{
                                ticks: {
                                    callback: function (value) { return pesos(value); }
                                }
                            }]
                        }
                    }
                });
            }
            var barras = document.getElementById('td-chart-cartera');
            if (barras && datos.vencimientos) {
                new Chart(barras.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: datos.vencimientos.map(function (p) { return p.etiqueta; }),
                        datasets: [{
                            label: 'Cartera',
                            data: datos.vencimientos.map(function (p) { return p.monto; }),
                            backgroundColor: ['#922B21', '#B9770E', '#2471A3', '#1A5276']
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        legend: { display: false },
                        tooltips: {
                            callbacks: {
                                label: function (item) {
                                    return '$ ' + pesos(item.yLabel);
                                }
                            }
                        },
                        scales: {
                            yAxes: [{
                                ticks: {
                                    beginAtZero: true,
                                    callback: function (value) { return pesos(value); }
                                }
                            }]
                        }
                    }
                });
            }
        }

        var modal = document.getElementById('modal-tablero-detalle');
        var form = document.getElementById('form-tablero');
        if (!modal || !form || !window.jQuery) {
            return;
        }
        document.querySelectorAll('.td-kpi').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var bloque = btn.getAttribute('data-bloque');
                var params = new URLSearchParams(new FormData(form));
                params.set('bloque', bloque);
                var titulo = modal.querySelector('.modal-title');
                var cuerpo = modal.querySelector('.modal-body');
                titulo.textContent = btn.getAttribute('data-titulo') || 'Detalle';
                cuerpo.innerHTML = '<p class="text-muted mb-0">Cargando…</p>';
                window.jQuery(modal).modal('show');
                fetch(form.getAttribute('data-detalle') + '?' + params.toString(), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                }).then(function (res) {
                    if (!res.ok) {
                        throw new Error('detalle');
                    }
                    return res.json();
                }).then(function (data) {
                    titulo.textContent = data.titulo || titulo.textContent;
                    cuerpo.innerHTML = render(data);
                }).catch(function () {
                    cuerpo.innerHTML = '<p class="text-danger mb-0">No se pudo abrir el detalle.</p>';
                });
            });
        });

        function render(data) {
            var html = '';
            if (data.nota) {
                html += '<p class="td-nota">' + escapeHtml(data.nota) + '</p>';
            }
            (data.secciones || []).forEach(function (sec) {
                if (sec.titulo) {
                    html += '<h3 class="h6 mt-3">' + escapeHtml(sec.titulo) + '</h3>';
                }
                if (!sec.filas || !sec.filas.length) {
                    html += '<p class="td-vacio">Sin movimientos en este corte.</p>';
                    return;
                }
                html += '<div class="table-responsive"><table class="table table-sm td-tabla"><thead><tr>';
                (sec.columnas || []).forEach(function (col, i) {
                    var num = i === sec.columnas.length - 1 ? ' class="num"' : '';
                    html += '<th' + num + '>' + escapeHtml(col) + '</th>';
                });
                html += '</tr></thead><tbody>';
                sec.filas.forEach(function (fila) {
                    html += '<tr>';
                    (fila.celdas || []).forEach(function (celda, i) {
                        var num = i === fila.celdas.length - 1 ? ' class="num"' : '';
                        var texto = escapeHtml(celda);
                        if (i === 0 && fila.url) {
                            texto = '<a class="text-primary" target="_blank" rel="noopener" href="' + escapeAttr(fila.url) + '">' + texto + '</a>';
                        }
                        html += '<td' + num + '>' + texto + '</td>';
                    });
                    html += '</tr>';
                });
                html += '</tbody></table></div>';
            });
            return html || '<p class="td-vacio">Sin movimientos en este corte.</p>';
        }

        function escapeHtml(valor) {
            return String(valor == null ? '' : valor)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function escapeAttr(valor) {
            return escapeHtml(valor).replace(/'/g, '&#39;');
        }
    });
})();
