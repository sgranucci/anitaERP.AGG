(function () {
    'use strict';

    var app = document.getElementById('jornada-gastronomia-app');
    if (!app) {
        return;
    }

    var apiEstadoBase = app.getAttribute('data-api-estado') || '';
    var apiAbrir = app.getAttribute('data-api-abrir') || '';
    var apiCerrar = app.getAttribute('data-api-cerrar') || '';
    var puedeAbrir = app.getAttribute('data-puede-abrir') === '1';
    var puedeCerrar = app.getAttribute('data-puede-cerrar') === '1';

    var selectEmpresa = document.getElementById('empresa_id');
    var btnAbrir = document.getElementById('btn-abrir-jornada');
    var btnCerrar = document.getElementById('btn-cerrar-jornada');

    function empresaId() {
        return selectEmpresa ? parseInt(selectEmpresa.value, 10) || 0 : 0;
    }

    function csrfToken() {
        if (window.JORNADA_GASTRONOMIA && window.JORNADA_GASTRONOMIA.csrf) {
            return String(window.JORNADA_GASTRONOMIA.csrf);
        }
        if (app && app.getAttribute('data-csrf')) {
            return app.getAttribute('data-csrf');
        }
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta && meta.getAttribute('content')) {
            return meta.getAttribute('content');
        }
        if (typeof window.jQuery !== 'undefined') {
            var jq = window.jQuery;
            var fromInput = jq('input[name="_token"]').val();
            if (fromInput) {
                return String(fromInput);
            }
            var fromHidden = jq('#csrf_token').val();
            if (fromHidden) {
                return String(fromHidden);
            }
        }
        return '';
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
        if (d.errors && typeof d.errors === 'object') {
            var partes = [];
            Object.keys(d.errors).forEach(function (k) {
                var v = d.errors[k];
                if (Array.isArray(v)) {
                    partes = partes.concat(v);
                } else if (v) {
                    partes.push(String(v));
                }
            });
            if (partes.length) {
                return partes.join(' ');
            }
        }
        if (res.status === 403) {
            return 'Acceso denegado (403). Verifique permisos de jornada.';
        }
        if (res.status === 419) {
            var raw = (d.message || d.error || '').toString();
            if (/csrf/i.test(raw)) {
                return 'Token de seguridad vencido o inválido (CSRF). Recargue la página con F5 e intente abrir la jornada de nuevo.';
            }
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

    if (puedeAbrir && btnAbrir) {
        btnAbrir.addEventListener('click', function () {
            var fechaInput = document.getElementById('fecha_jornada_abrir');
            var obsInput = document.getElementById('observacion_abrir');
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
})();
