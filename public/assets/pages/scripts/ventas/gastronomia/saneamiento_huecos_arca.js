/**
 * Saneamiento de huecos ARCA en cierre de turno (diagnóstico + lote).
 * Uso: window.GastronomiaSaneamientoHuecosArca.interceptarCierre({...})
 */
(function (window) {
    'use strict';

    function formatMoney(n) {
        var v = Number(n || 0);
        return v.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function postJson(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).content || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(body || {}),
            credentials: 'same-origin',
        }).then(function (r) {
            return r.json().then(function (data) {
                return { status: r.status, data: data };
            });
        });
    }

    /**
     * @param {object} opts
     * @param {string} opts.apiDiagnosticar
     * @param {string} opts.apiEjecutar
     * @param {object} [opts.payloadExtra]
     * @param {function} opts.onContinuarCierre  llamado si no hay huecos / tras sanear / usuario cierra igual
     * @param {number} [opts.cantidadHuecosEstado]  del poll; si 0 saltea modal
     */
    function interceptarCierre(opts) {
        var cantidad = Number((opts.cantidadHuecosEstado != null)
            ? opts.cantidadHuecosEstado
            : ((opts.estado && opts.estado.huecos_arca_pendientes && opts.estado.huecos_arca_pendientes.cantidad) || 0));

        if (cantidad <= 0) {
            opts.onContinuarCierre();
            return;
        }

        var modalEl = document.getElementById('modal-saneamiento-huecos-arca');
        if (!modalEl || typeof $ === 'undefined') {
            if (window.confirm(
                'Hay ' + cantidad + ' hueco(s) de numeración ARCA. ¿Continuar el cierre sin sanear ahora?'
            )) {
                opts.onContinuarCierre();
            }
            return;
        }

        var loading = document.getElementById('saneamiento-huecos-arca-loading');
        var aviso = document.getElementById('saneamiento-huecos-arca-aviso');
        var errorBox = document.getElementById('saneamiento-huecos-arca-error');
        var contenido = document.getElementById('saneamiento-huecos-arca-contenido');
        var tbody = document.getElementById('saneamiento-huecos-arca-tbody');
        var previewNc = document.getElementById('saneamiento-huecos-arca-preview-nc');
        var btnEjecutar = document.getElementById('btn-saneamiento-huecos-arca-ejecutar');
        var btnCerrarIgual = document.getElementById('btn-saneamiento-huecos-arca-cerrar-igual');

        function resetUi() {
            loading.classList.add('d-none');
            aviso.classList.add('d-none');
            aviso.textContent = '';
            errorBox.classList.add('d-none');
            errorBox.textContent = '';
            contenido.classList.add('d-none');
            tbody.innerHTML = '';
            previewNc.innerHTML = '';
            btnEjecutar.classList.add('d-none');
            btnCerrarIgual.classList.add('d-none');
            btnEjecutar.disabled = false;
        }

        resetUi();
        loading.classList.remove('d-none');
        $(modalEl).modal('show');

        var body = Object.assign({}, opts.payloadExtra || {});
        postJson(opts.apiDiagnosticar, body).then(function (res) {
            loading.classList.add('d-none');
            var data = res.data || {};
            if (!data.ok) {
                errorBox.textContent = data.error || 'No se pudo diagnosticar huecos ARCA.';
                errorBox.classList.remove('d-none');
                btnCerrarIgual.classList.remove('d-none');
                return;
            }

            if (data.arca_indisponible) {
                aviso.textContent = data.mensaje
                    || 'ARCA no respondió. Puede cerrar el turno; el saneamiento queda pendiente para la auditoría / artisan.';
                aviso.classList.remove('d-none');
                btnCerrarIgual.classList.remove('d-none');
                return;
            }

            var recuperables = data.recuperables || [];
            var inexistentes = data.inexistentes || [];
            if (recuperables.length === 0) {
                aviso.textContent = 'Los huecos no están autorizados en ARCA (no se recuperan). Puede cerrar el turno.';
                aviso.classList.remove('d-none');
                btnCerrarIgual.classList.remove('d-none');
                return;
            }

            contenido.classList.remove('d-none');
            recuperables.forEach(function (r) {
                var tr = document.createElement('tr');
                tr.innerHTML = '<td>' + r.numero + '</td>'
                    + '<td class="text-right">$ ' + formatMoney(r.imp_total) + '</td>'
                    + '<td>' + (r.sin_cuenta_referencia ? 'Sin cuenta referencia' : 'Recuperable') + '</td>';
                tbody.appendChild(tr);
            });
            inexistentes.forEach(function (r) {
                var tr = document.createElement('tr');
                tr.className = 'table-secondary';
                tr.innerHTML = '<td>' + r.numero + '</td><td></td><td>Inexistente en ARCA</td>';
                tbody.appendChild(tr);
            });

            var prev = data.preview_nc || {};
            previewNc.innerHTML = '<div><strong>NC consolidada:</strong> $ '
                + formatMoney(prev.importe_total)
                + ' · PeriodoAsoc '
                + (prev.periodo_asoc_desde || '')
                + ' · efectivo neto $ 0 · sin stock / sin réplica Anita ventas</div>'
                + '<div class="text-muted mt-1">' + (prev.leyenda || '') + '</div>';

            var sinRef = recuperables.some(function (r) { return r.sin_cuenta_referencia; });
            if (sinRef) {
                errorBox.textContent = 'Hay FAC recuperables sin cuenta de referencia en el turno; no se puede emitir el lote automáticamente.';
                errorBox.classList.remove('d-none');
                btnCerrarIgual.classList.remove('d-none');
                return;
            }

            btnEjecutar.classList.remove('d-none');
            btnCerrarIgual.classList.remove('d-none');

            btnEjecutar.onclick = function () {
                btnEjecutar.disabled = true;
                loading.classList.remove('d-none');
                var payload = Object.assign({}, body, {
                    numeros: recuperables.map(function (r) { return r.numero; }),
                });
                postJson(opts.apiEjecutar, payload).then(function (resExec) {
                    loading.classList.add('d-none');
                    var d = resExec.data || {};
                    if (!d.ok) {
                        errorBox.textContent = d.error || 'Error al ejecutar el lote.';
                        errorBox.classList.remove('d-none');
                        btnEjecutar.disabled = false;
                        return;
                    }
                    $(modalEl).modal('hide');
                    window.alert(d.mensaje || 'Lote saneado.');
                    opts.onContinuarCierre();
                }).catch(function (err) {
                    loading.classList.add('d-none');
                    btnEjecutar.disabled = false;
                    errorBox.textContent = (err && err.message) || 'Error de red al ejecutar el lote.';
                    errorBox.classList.remove('d-none');
                });
            };
        }).catch(function (err) {
            loading.classList.add('d-none');
            errorBox.textContent = (err && err.message) || 'Error de red al consultar ARCA.';
            errorBox.classList.remove('d-none');
            btnCerrarIgual.classList.remove('d-none');
        });

        btnCerrarIgual.onclick = function () {
            $(modalEl).modal('hide');
            opts.onContinuarCierre();
        };
    }

    window.GastronomiaSaneamientoHuecosArca = {
        interceptarCierre: interceptarCierre,
    };
})(window);
