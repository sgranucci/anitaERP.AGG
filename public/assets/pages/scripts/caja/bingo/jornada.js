(function () {
    'use strict';

    var app = document.getElementById('jornada-bingo-app');
    if (!app) {
        return;
    }

    var apiAbrir = app.getAttribute('data-api-abrir') || '';
    var apiCerrar = app.getAttribute('data-api-cerrar') || '';
    var apiEliminar = app.getAttribute('data-api-eliminar') || '';
    var apiAnularCierre = app.getAttribute('data-api-anular-cierre') || '';
    var puedeAbrir = app.getAttribute('data-puede-abrir') === '1';
    var puedeCerrar = app.getAttribute('data-puede-cerrar') === '1';
    var puedeEliminar = app.getAttribute('data-puede-eliminar') === '1';
    var puedeAnularCierre = app.getAttribute('data-puede-anular-cierre') === '1';

    var selectEmpresa = document.getElementById('empresa_id');
    var btnAbrir = document.getElementById('btn-abrir-jornada');
    var btnCerrar = document.getElementById('btn-cerrar-jornada');

    function empresaId() {
        var fromSelect = selectEmpresa ? parseInt(selectEmpresa.value, 10) || 0 : 0;
        return fromSelect > 0 ? fromSelect : 0;
    }

    if (selectEmpresa) {
        selectEmpresa.addEventListener('change', function () {
            var form = document.getElementById('form-empresa-jornada-bingo');
            if (form) {
                form.submit();
                return;
            }
            var url = new URL(window.location.href);
            url.searchParams.set('empresa_id', String(selectEmpresa.value || ''));
            window.location.href = url.toString();
        });
    }

    function csrfToken() {
        return app.getAttribute('data-csrf') || '';
    }

    function postJson(url, body) {
        var token = csrfToken();
        if (!token) {
            return Promise.resolve({
                ok: false,
                status: 0,
                data: {
                    error: 'No se encontró el token CSRF en la página. Recargue (F5) e intente de nuevo.',
                    motivo: 'csrf',
                },
            });
        }

        var payload = Object.assign({}, body, { _token: token });

        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        }).then(function (r) {
            return r.text().then(function (text) {
                var data = null;
                try {
                    data = text ? JSON.parse(text) : {};
                } catch (e) {
                    data = { error: text && text.length < 500 ? text : null };
                }
                return { ok: r.ok, status: r.status, data: data };
            });
        });
    }

    function extraerMensajeError(res, fallback) {
        var d = res && res.data ? res.data : {};
        if (d.error && String(d.error).trim() !== '') {
            return String(d.error);
        }
        if (d.message && String(d.message).trim() !== '') {
            return String(d.message);
        }
        if (res.status === 403) {
            return 'Acceso denegado (403). Verifique permisos de jornada.';
        }
        if (res.status === 419) {
            return 'Sesión expirada (419). Recargue la página e inicie sesión de nuevo.';
        }
        if (res.status >= 500) {
            return 'Error del servidor (HTTP ' + res.status + ').';
        }
        return fallback;
    }

    function alertar(msg, esError) {
        if (typeof toastr !== 'undefined') {
            if (esError) {
                toastr.error(msg);
            } else {
                toastr.success(msg);
            }
        } else {
            window.alert(msg);
        }
    }

    function fechaMaximaJornadaAbrir() {
        var fechaInput = document.getElementById('fecha_jornada_abrir');
        if (fechaInput && fechaInput.max) {
            return fechaInput.max;
        }
        var d = new Date();
        var m = String(d.getMonth() + 1).padStart(2, '0');
        var day = String(d.getDate()).padStart(2, '0');
        return d.getFullYear() + '-' + m + '-' + day;
    }

    if (puedeAbrir && btnAbrir) {
        btnAbrir.addEventListener('click', function () {
            var fechaInput = document.getElementById('fecha_jornada_abrir');
            var obsInput = document.getElementById('observacion_abrir');
            var fechaVal = fechaInput ? fechaInput.value : '';
            var fechaMax = fechaMaximaJornadaAbrir();

            if (fechaVal && fechaVal > fechaMax) {
                alertar('La fecha de jornada no puede ser posterior a hoy (' + fechaMax + ').', true);
                return;
            }

            btnAbrir.disabled = true;

            postJson(apiAbrir, {
                empresa_id: empresaId(),
                fecha_jornada: fechaInput ? fechaInput.value : '',
                observacion: obsInput ? obsInput.value : '',
            }).then(function (res) {
                if (res.ok && res.data.ok) {
                    alertar(res.data.mensaje || 'Jornada abierta.', false);
                    window.location.reload();
                    return;
                }
                alertar(extraerMensajeError(res, 'No se pudo abrir la jornada.'), true);
                btnAbrir.disabled = false;
            }).catch(function () {
                alertar('Error de comunicación al abrir la jornada (sin respuesta del servidor).', true);
                btnAbrir.disabled = false;
            });
        });
    }

    if (puedeCerrar && btnCerrar) {
        btnCerrar.addEventListener('click', function () {
            if (!window.confirm('¿Confirma el cierre de la jornada? No podrá facturar hasta abrir una nueva.')) {
                return;
            }

            var obsInput = document.getElementById('observacion_cerrar');
            btnCerrar.disabled = true;

            postJson(apiCerrar, {
                empresa_id: empresaId(),
                observacion: obsInput ? obsInput.value : '',
            }).then(function (res) {
                if (res.ok && res.data.ok) {
                    alertar(res.data.mensaje || 'Jornada cerrada.', false);
                    window.location.reload();
                    return;
                }
                alertar(extraerMensajeError(res, 'No se pudo cerrar la jornada.'), true);
                btnCerrar.disabled = false;
            }).catch(function () {
                alertar('Error de comunicación al cerrar la jornada (sin respuesta del servidor).', true);
                btnCerrar.disabled = false;
            });
        });
    }

    var anulacionCierreActual = null;

    function leerCierreAnulableDesdePagina() {
        var raw = app.getAttribute('data-cierre-anulable') || '';
        if (!raw) {
            return null;
        }
        try {
            return JSON.parse(raw);
        } catch (e) {
            return null;
        }
    }

    function datosAnulacionDesdeBoton(btn) {
        var id = parseInt(btn.getAttribute('data-jornada-id'), 10) || 0;
        return {
            jornada_id: id,
            texto_confirmacion: btn.getAttribute('data-texto-confirmacion') || ('ANULAR-JORNADA-' + id),
            fecha_jornada_fmt: btn.getAttribute('data-fecha-jornada') || '',
            cierre_en_fmt: btn.getAttribute('data-cierre-en') || '',
            usuario_cierre: btn.getAttribute('data-usuario-cierre') || '',
        };
    }

    function abrirModalAnularCierre(datos) {
        anulacionCierreActual = datos;
        var detalle = document.getElementById('anular-cierre-jornada-detalle');
        var hint = document.getElementById('hint-confirmacion-anular-cierre-jornada');
        var confirmInput = document.getElementById('confirmacion_anular_cierre_jornada');
        var motivoInput = document.getElementById('motivo_anular_cierre_jornada');

        if (detalle) {
            detalle.innerHTML = '<ul class="mb-0 small">'
                + '<li><strong>Jornada #</strong>' + datos.jornada_id + ' — ' + (datos.fecha_jornada_fmt || '') + '</li>'
                + '<li><strong>Cerrada:</strong> ' + (datos.cierre_en_fmt || '—') + '</li>'
                + '<li><strong>Por:</strong> ' + (datos.usuario_cierre || '—') + '</li>'
                + '</ul>';
        }
        if (hint) {
            hint.textContent = 'Escriba exactamente: ' + (datos.texto_confirmacion || '');
        }
        if (confirmInput) {
            confirmInput.value = '';
        }
        if (motivoInput) {
            motivoInput.value = '';
        }

        if (typeof $ !== 'undefined' && $('#modal-anular-cierre-jornada').modal) {
            $('#modal-anular-cierre-jornada').modal('show');
        }
    }

    if (puedeAnularCierre) {
        var btnAnularToolbar = document.getElementById('btn-abrir-anular-cierre-jornada');
        if (btnAnularToolbar) {
            btnAnularToolbar.addEventListener('click', function () {
                var datos = leerCierreAnulableDesdePagina();
                if (datos) {
                    abrirModalAnularCierre(datos);
                }
            });
        }

        document.querySelectorAll('.js-anular-cierre-jornada').forEach(function (btn) {
            btn.addEventListener('click', function () {
                abrirModalAnularCierre(datosAnulacionDesdeBoton(btn));
            });
        });

        var formAnular = document.getElementById('form-anular-cierre-jornada');
        if (formAnular) {
            formAnular.addEventListener('submit', function (e) {
                e.preventDefault();
                if (!anulacionCierreActual) {
                    return;
                }

                var motivo = document.getElementById('motivo_anular_cierre_jornada');
                var confirmacion = document.getElementById('confirmacion_anular_cierre_jornada');
                var btnSubmit = document.getElementById('btn-submit-anular-cierre-jornada');

                if (btnSubmit) {
                    btnSubmit.disabled = true;
                }

                postJson(apiAnularCierre, {
                    jornada_id: anulacionCierreActual.jornada_id,
                    motivo: motivo ? motivo.value : '',
                    confirmacion: confirmacion ? confirmacion.value : '',
                }).then(function (res) {
                    if (res.ok && res.data.ok) {
                        alertar(res.data.mensaje || 'Cierre anulado.', false);
                        window.location.reload();
                        return;
                    }
                    alertar(extraerMensajeError(res, 'No se pudo anular el cierre.'), true);
                    if (btnSubmit) {
                        btnSubmit.disabled = false;
                    }
                }).catch(function () {
                    alertar('Error de comunicación al anular el cierre.', true);
                    if (btnSubmit) {
                        btnSubmit.disabled = false;
                    }
                });
            });
        }
    }

    if (puedeEliminar) {
        document.querySelectorAll('.js-eliminar-jornada').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var jornadaId = parseInt(btn.getAttribute('data-jornada-id'), 10) || 0;
                var fecha = btn.getAttribute('data-fecha-jornada') || '';
                if (jornadaId <= 0) {
                    return;
                }
                if (!window.confirm('¿Eliminar la apertura del ' + fecha + ' (#' + jornadaId + ')?\n\nSolo permitido si la jornada no tiene movimientos (comprobantes ni turnos).')) {
                    return;
                }

                postJson(apiEliminar, { jornada_id: jornadaId }).then(function (res) {
                    if (res.ok && res.data.ok) {
                        alertar(res.data.mensaje || 'Jornada eliminada.', false);
                        window.location.reload();
                        return;
                    }
                    alertar(extraerMensajeError(res, 'No se pudo eliminar la jornada.'), true);
                }).catch(function () {
                    alertar('Error de comunicación al eliminar la jornada.', true);
                });
            });
        });
    }
})();
