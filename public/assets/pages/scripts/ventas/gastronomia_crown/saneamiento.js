(function () {
    'use strict';

    var cfg = window.SANEAMIENTO_CROWN || {};
    if (!cfg.csrf) {
        return;
    }

    var mensajeEl = document.getElementById('saneamiento-mensaje');
    var inputConfirm = document.getElementById('saneamiento-confirmacion');
    var btnTodas = document.getElementById('btn-cerrar-todas');

    function mostrarMensaje(texto, esError) {
        if (!mensajeEl) {
            return;
        }
        mensajeEl.textContent = texto || '';
        mensajeEl.classList.remove('d-none', 'text-danger', 'text-success');
        mensajeEl.classList.add(esError ? 'text-danger' : 'text-success');
    }

    function confirmacionActual() {
        return String((inputConfirm && inputConfirm.value) || '').trim();
    }

    function postCerrar(cuentaId) {
        if (!cfg.puedeEjecutar) {
            mostrarMensaje('No tiene permiso para ejecutar el saneamiento.', true);
            return;
        }

        var body = {
            _token: cfg.csrf,
            empresa_id: cfg.empresaId,
            sala_id: cfg.salaId,
        };
        if (cuentaId > 0) {
            body.cuenta_id = cuentaId;
        } else {
            var confirmacion = confirmacionActual();
            if (!confirmacion) {
                mostrarMensaje('Indique la confirmación ' + (cfg.confirmacion || 'CERRAR-N') + '.', true);
                if (inputConfirm) {
                    inputConfirm.focus();
                }
                return;
            }
            body.confirmacion = confirmacion;
        }

        if (btnTodas) {
            btnTodas.disabled = true;
        }

        fetch(cfg.urlCerrar, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': cfg.csrf,
            },
            body: JSON.stringify(body),
            credentials: 'same-origin',
        }).then(function (r) {
            return r.json().then(function (data) {
                return { okHttp: r.ok, data: data || {} };
            });
        }).then(function (res) {
            if (btnTodas) {
                btnTodas.disabled = false;
            }
            if (!res.okHttp || !res.data.ok) {
                mostrarMensaje((res.data && res.data.mensaje) || 'No se pudo cerrar.', true);
                return;
            }
            mostrarMensaje(res.data.mensaje || 'Listo.', false);
            var restante = res.data.diagnostico && Number(res.data.diagnostico.total || 0);
            if (restante === 0 && cfg.desdeJornada && cfg.urlJornada) {
                window.location.href = cfg.urlJornada;
                return;
            }
            window.location.reload();
        }).catch(function () {
            if (btnTodas) {
                btnTodas.disabled = false;
            }
            mostrarMensaje('Error de comunicación.', true);
        });
    }

    if (btnTodas) {
        btnTodas.addEventListener('click', function () {
            if (!window.confirm('¿Cerrar TODAS las cuentas abiertas sin facturar?')) {
                return;
            }
            postCerrar(0);
        });
    }

    document.querySelectorAll('.js-cerrar-una').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = parseInt(btn.getAttribute('data-cuenta-id'), 10) || 0;
            if (id <= 0) {
                return;
            }
            if (!window.confirm('¿Cerrar la cuenta #' + id + ' sin facturar?')) {
                return;
            }
            postCerrar(id);
        });
    });
})();
