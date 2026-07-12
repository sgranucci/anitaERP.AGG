(function () {
    'use strict';

    var app = document.getElementById('bingo-rendicion-app');
    if (!app) {
        return;
    }

    var apiCalcular = app.getAttribute('data-api-calcular') || '';
    var apiGuardar = app.getAttribute('data-api-guardar') || '';
    var csrf = app.getAttribute('data-csrf') || '';
    var empresaId = parseInt(app.getAttribute('data-empresa-id') || '0', 10) || 0;
    var turnoId = parseInt(app.getAttribute('data-turno-id') || '0', 10) || 0;
    var modoEdicion = app.getAttribute('data-modo-edicion') === '1';

    var selectEmpresa = document.getElementById('empresa_id');
    if (selectEmpresa) {
        selectEmpresa.addEventListener('change', function () {
            var form = selectEmpresa.closest('form');
            if (form) {
                form.submit();
            }
        });
    }

    function formatMonto(valor) {
        return Number(valor || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function recolectarCartones() {
        var lineas = [];
        document.querySelectorAll('.js-carton-row').forEach(function (row) {
            var anulado = row.querySelector('.js-carton-anular')?.checked;
            var cantidad = parseInt(row.querySelector('.js-carton-cantidad')?.value || '0', 10) || 0;
            lineas.push({
                carton_id: parseInt(row.getAttribute('data-carton-id') || '0', 10),
                cantidad: anulado ? 0 : cantidad,
                precio_unitario: parseFloat(row.getAttribute('data-precio') || '0'),
                anulado: !!anulado,
            });
        });
        return lineas;
    }

    function recolectarMontosManuales() {
        var montos = {};
        document.querySelectorAll('.js-monto-manual').forEach(function (input) {
            var id = parseInt(input.getAttribute('data-concepto-id') || '0', 10);
            if (id > 0) {
                montos[id] = parseFloat(input.value || '0');
            }
        });
        return montos;
    }

    function postJson(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify(Object.assign({ _token: csrf, empresa_id: empresaId }, body)),
        }).then(function (r) {
            return r.json().then(function (data) {
                return { ok: r.ok, data: data };
            });
        });
    }

    function renderConceptos(calculo) {
        var lineas = calculo.lineas_concepto || [];
        var montosPorConcepto = {};

        lineas.forEach(function (linea) {
            var conceptoId = parseInt(linea.concepto_id || '0', 10);
            if (conceptoId > 0) {
                montosPorConcepto[conceptoId] = linea;
            }
        });

        document.querySelectorAll('.js-concepto-row').forEach(function (row) {
            var conceptoId = parseInt(row.getAttribute('data-concepto-id') || '0', 10);
            var esManual = row.getAttribute('data-es-manual') === '1';
            var esSaldo = row.getAttribute('data-es-saldo') === '1';
            var linea = montosPorConcepto[conceptoId];

            if (!linea || esManual) {
                return;
            }

            if (esSaldo) {
                var saldoCell = row.querySelector('.js-concepto-saldo-rendicion');
                if (saldoCell) {
                    saldoCell.textContent = '$' + formatMonto(linea.monto || calculo.saldo_final || 0);
                }
                return;
            }

            var pctCell = row.querySelector('.js-concepto-pct');
            if (pctCell) {
                pctCell.textContent = linea.porcentaje != null && Number(linea.porcentaje) > 0
                    ? formatMonto(linea.porcentaje)
                    : '';
            }

            var montoCell = row.querySelector('.js-concepto-monto-auto');
            if (montoCell) {
                montoCell.textContent = '$' + formatMonto(linea.monto || 0);
            }
        });

        var lblRecaudacion = document.getElementById('lbl-recaudacion');
        if (lblRecaudacion) {
            lblRecaudacion.textContent = '$' + formatMonto(calculo.total_cartones || 0);
        }
    }

    function recalcular() {
        postJson(apiCalcular, {
            cartones: recolectarCartones(),
            montos_manuales: recolectarMontosManuales(),
        }).then(function (res) {
            if (res.ok && res.data && res.data.calculo) {
                renderConceptos(res.data.calculo);
            }
        });
    }

    app.addEventListener('change', function (e) {
        if (e.target.matches('.js-carton-cantidad, .js-monto-manual')) {
            recalcular();
        }
    });

    app.addEventListener('keyup', function (e) {
        if (e.key === 'Enter' && e.target.matches('.js-carton-cantidad, .js-monto-manual')) {
            recalcular();
        }
    });

    document.querySelectorAll('.js-carton-anular').forEach(function (chk) {
        chk.addEventListener('change', function () {
            var row = chk.closest('.js-carton-row');
            if (!row) {
                return;
            }
            var input = row.querySelector('.js-carton-cantidad');
            if (chk.checked) {
                row.classList.add('bingo-carton-row-anulado');
                if (input) {
                    input.disabled = true;
                }
            } else {
                row.classList.remove('bingo-carton-row-anulado');
                if (input) {
                    input.disabled = false;
                }
            }
            recalcular();
        });
    });

    var btnGuardar = document.getElementById('btn-guardar-rendicion-bingo');
    if (btnGuardar) {
        btnGuardar.addEventListener('click', function () {
            var payload = {
                cartones: recolectarCartones(),
                montos_manuales: recolectarMontosManuales(),
                observacion: document.getElementById('rend_observacion')?.value || '',
            };

            if (modoEdicion && turnoId > 0) {
                payload.turno_operativo_id = turnoId;
            }

            postJson(apiGuardar, payload).then(function (res) {
                if (res.ok && res.data && res.data.ok) {
                    var pdfUrl = res.data.url_comprobante_pdf || '';
                    if (pdfUrl) {
                        window.open(pdfUrl, '_blank', 'noopener');
                    }
                    var url = modoEdicion
                        ? (app.getAttribute('data-url-cierres') || '')
                        : (app.getAttribute('data-url-habilitacion') || '');
                    if (window.Swal && Swal.fire) {
                        Swal.fire({
                            icon: 'success',
                            title: modoEdicion ? 'Rendición actualizada' : 'Turno cerrado',
                            text: res.data.mensaje || 'Presente la rendición en Caja → Rendiciones bingo.',
                        }).then(function () {
                            window.location.href = url || window.location.pathname;
                        });
                    } else {
                        window.alert(res.data.mensaje || 'Guardado.');
                        window.location.href = url || window.location.pathname;
                    }
                    return;
                }
                var msg = (res.data && res.data.error) || 'No se pudo guardar la rendición.';
                if (window.Swal && Swal.fire) {
                    Swal.fire({ icon: 'error', title: 'Error', text: msg });
                } else {
                    window.alert(msg);
                }
            });
        });
    }
})();
