(function () {
    'use strict';

    var app = document.getElementById('habilitacion-turno-bingo-app');
    if (!app) {
        return;
    }

    var apiEstado = app.getAttribute('data-api-estado') || '';
    var apiHabilitar = app.getAttribute('data-api-habilitar') || '';
    var apiCierreParcial = app.getAttribute('data-api-cierre-parcial') || '';
    var apiCerrar = app.getAttribute('data-api-cerrar') || '';
    var csrf = app.getAttribute('data-csrf') || '';
    var empresaId = parseInt(app.getAttribute('data-empresa-id') || '0', 10) || 0;

    var selectEmpresa = document.getElementById('empresa_id');
    if (selectEmpresa) {
        selectEmpresa.addEventListener('change', function () {
            var form = document.getElementById('form-filtro-empresa-habilitacion-turno');
            if (form) {
                form.submit();
            }
        });
    }

    function postJson(url, body) {
        var payload = Object.assign({}, body, { _token: csrf });
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        }).then(function (r) {
            return r.json().then(function (data) {
                return { ok: r.ok, data: data };
            });
        });
    }

    function alertError(msg) {
        if (window.Swal && Swal.fire) {
            Swal.fire({ icon: 'error', title: 'Error', text: msg || 'Operación rechazada.' });
        } else {
            window.alert(msg || 'Operación rechazada.');
        }
    }

    function reloadPage() {
        window.location.reload();
    }

    var btnHabilitar = document.getElementById('btn-habilitar-turno-bingo');
    if (btnHabilitar) {
        btnHabilitar.addEventListener('click', function () {
            var turnoId = parseInt((document.getElementById('hab_turno_bingo_id') || {}).value || '0', 10);
            var monto = parseFloat((document.getElementById('hab_monto') || {}).value || '0');
            var usuarioId = parseInt((document.getElementById('usuario_habilitado_id') || {}).value || '0', 10);
            var obs = (document.getElementById('hab_observacion') || {}).value || '';

            postJson(apiHabilitar, {
                empresa_id: empresaId,
                turno_bingo_id: turnoId,
                monto_habilitacion: monto,
                usuario_habilitado_id: usuarioId,
                observacion: obs,
            }).then(function (res) {
                if (res.ok && res.data && res.data.ok) {
                    reloadPage();
                    return;
                }
                alertError((res.data && res.data.error) || 'No se pudo habilitar el turno.');
            }).catch(function () {
                alertError('Error de comunicación.');
            });
        });
    }

    var btnParcial = document.getElementById('btn-cierre-parcial-bingo');
    if (btnParcial) {
        btnParcial.addEventListener('click', function () {
            postJson(apiCierreParcial, { empresa_id: empresaId }).then(function (res) {
                if (res.ok && res.data && res.data.ok) {
                    if (window.Swal && Swal.fire) {
                        Swal.fire({ icon: 'success', title: 'Cierre parcial', text: 'Registrado #' + res.data.numero_parcial });
                    }
                    reloadPage();
                    return;
                }
                alertError((res.data && res.data.error) || 'No se pudo registrar el cierre parcial.');
            });
        });
    }

    var btnCerrar = document.getElementById('btn-cerrar-turno-bingo');
    if (btnCerrar) {
        btnCerrar.addEventListener('click', function () {
            var montoRend = parseFloat((document.getElementById('cierre_monto_rendicion') || {}).value || '0');
            var efectivo = parseFloat((document.getElementById('cierre_medio_efectivo') || {}).value || '0');
            var redondeo = parseFloat((document.getElementById('cierre_redondeo') || {}).value || '0');
            var sobrante = parseFloat((document.getElementById('cierre_sobrante') || {}).value || '0');
            var obs = (document.getElementById('cierre_observacion') || {}).value || '';

            if (!window.confirm('¿Confirma el cierre definitivo del turno?')) {
                return;
            }

            postJson(apiCerrar, {
                empresa_id: empresaId,
                monto_rendicion: montoRend,
                redondeo: redondeo,
                sobrante_faltante: sobrante,
                medios_contado: [{ medio: 'Efectivo', monto: efectivo }],
                observacion_cierre: obs,
            }).then(function (res) {
                if (res.ok && res.data && res.data.ok) {
                    reloadPage();
                    return;
                }
                alertError((res.data && res.data.error) || 'No se pudo cerrar el turno.');
            });
        });
    }

    if (typeof jQuery !== 'undefined' && typeof activa_eventos_consultausuario === 'function') {
        activa_eventos_consultausuario();
    }

    if (typeof jQuery !== 'undefined') {
        jQuery(document).on('keydown', '#usuario_habilitado_id', function (e) {
            if (e.which === 13) {
                e.preventDefault();
                e.stopImmediatePropagation();
                jQuery(this).trigger('change');
            }
        });
    }
})();
