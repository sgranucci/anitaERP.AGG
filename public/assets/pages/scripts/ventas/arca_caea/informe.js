(function () {
    'use strict';

    var POLL_MS = 4000;
    var pollTimer = null;

    function overlayEl() {
        return document.getElementById('arca-caea-informe-overlay');
    }

    function mostrarOverlay(titulo, subtitulo) {
        var el = overlayEl();
        if (!el) {
            return;
        }
        var t = document.getElementById('arca-caea-informe-overlay-titulo');
        var s = document.getElementById('arca-caea-informe-overlay-subtitulo');
        if (t && titulo) {
            t.textContent = titulo;
        }
        if (s) {
            s.textContent = subtitulo || 'El proceso corre en segundo plano. Al terminar recibirás un mail.';
        }
        el.classList.remove('d-none');
        el.style.display = 'flex';
        el.setAttribute('aria-hidden', 'false');
    }

    function idDesdeAction(action) {
        if (!action) {
            return 0;
        }
        var m = String(action).match(/arca-caea\/(\d+)\/informar/i);
        return m ? parseInt(m[1], 10) : 0;
    }

    function botonesDeId(id) {
        return document.querySelectorAll('.js-arca-caea-informar-btn[data-arca-caea-id="' + id + '"]');
    }

    function leyendaDeId(id) {
        return document.querySelector('.js-arca-caea-leyenda[data-arca-caea-id="' + id + '"]');
    }

    function badgeDeId(id) {
        return document.querySelector('.js-arca-caea-badge[data-arca-caea-id="' + id + '"]');
    }

    function aplicarEstadoBoton(btn, activo, puedePresentar, leyenda) {
        if (!btn) {
            return;
        }
        var disabled = !!activo || !puedePresentar;
        btn.disabled = disabled;
        btn.setAttribute('title', activo
            ? (leyenda || 'Presentación en segundo plano…')
            : (puedePresentar
                ? 'Encolar presentación CAEA (segundo plano + mail)'
                : 'Sin comprobantes informables ahora en esta quincena'));

        if (activo) {
            btn.innerHTML = '<i class="fa fa-spinner fa-spin text-warning"></i>';
        } else {
            var color = puedePresentar ? 'text-primary' : 'text-muted';
            btn.innerHTML = '<i class="fa fa-paper-plane ' + color + '"></i>';
        }
    }

    function aplicarEstadoFila(id, estado) {
        var activo = !!(estado && estado.activo);
        var puede = !!(estado && estado.puede_presentar);
        var leyenda = (estado && estado.leyenda) || '';

        botonesDeId(id).forEach(function (btn) {
            aplicarEstadoBoton(btn, activo, puede, leyenda);
        });

        var leyEl = leyendaDeId(id);
        if (leyEl && activo && leyenda) {
            leyEl.textContent = leyenda;
        }

        var badge = badgeDeId(id);
        if (badge) {
            if (activo) {
                badge.innerHTML = '<span class="badge badge-warning"><i class="fa fa-spinner fa-spin"></i> Procesando</span>';
            }
        }
    }

    function marcarActivoLocal(id) {
        try {
            sessionStorage.setItem('arca-caea-activo-' + id, String(Date.now()));
        } catch (e) {}
        aplicarEstadoFila(id, {
            activo: true,
            puede_presentar: false,
            leyenda: 'Encolado: esperando worker…'
        });
        asegurarPoll();
    }

    function urlEstado(id) {
        var base = document.body.getAttribute('data-arca-caea-estado-url-template');
        if (base) {
            return base.replace('__ID__', String(id));
        }
        var carpeta = (typeof window.carpetaBase === 'string') ? window.carpetaBase : '';
        return carpeta + '/ventas/arca-caea/' + id + '/estado-informe';
    }

    function idsEnPantalla() {
        var ids = [];
        document.querySelectorAll('.js-arca-caea-informar-btn[data-arca-caea-id]').forEach(function (btn) {
            var id = parseInt(btn.getAttribute('data-arca-caea-id') || '0', 10);
            if (id > 0 && ids.indexOf(id) === -1) {
                ids.push(id);
            }
        });
        return ids;
    }

    function idsActivosLocales() {
        var ids = [];
        idsEnPantalla().forEach(function (id) {
            try {
                if (sessionStorage.getItem('arca-caea-activo-' + id)) {
                    ids.push(id);
                }
            } catch (e) {}
            if (btnPareceActivo(id) && ids.indexOf(id) === -1) {
                ids.push(id);
            }
        });
        return ids;
    }

    function btnPareceActivo(id) {
        var btn = document.querySelector('.js-arca-caea-informar-btn[data-arca-caea-id="' + id + '"]');
        return !!(btn && btn.getAttribute('data-proceso-activo') === '1');
    }

    function pollEstados() {
        var ids = idsActivosLocales();
        if (ids.length === 0) {
            detenerPoll();
            return;
        }

        ids.forEach(function (id) {
            fetch(urlEstado(id), {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (r) {
                if (!r.ok) {
                    throw new Error('HTTP ' + r.status);
                }
                return r.json();
            }).then(function (estado) {
                aplicarEstadoFila(id, estado);
                if (!estado.activo) {
                    try {
                        sessionStorage.removeItem('arca-caea-activo-' + id);
                    } catch (e) {}
                    // Terminó: refrescar contadores del listado.
                    window.location.reload();
                }
            }).catch(function () {
                // Silencio: reintenta en el próximo ciclo.
            });
        });
    }

    function asegurarPoll() {
        if (pollTimer) {
            return;
        }
        pollTimer = window.setInterval(pollEstados, POLL_MS);
        pollEstados();
    }

    function detenerPoll() {
        if (pollTimer) {
            window.clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    function enviarFormularioNativo(form) {
        HTMLFormElement.prototype.submit.call(form);
    }

    function encolarPresentacion(form) {
        if (!form || form.getAttribute('data-confirmado') === '1') {
            return false;
        }
        var confirmMsg = form.getAttribute('data-confirm-msg');
        if (confirmMsg && !window.confirm(confirmMsg)) {
            return false;
        }
        form.setAttribute('data-confirmado', '1');
        var id = idDesdeAction(form.getAttribute('action'));
        var titulo = form.getAttribute('data-overlay-titulo') || 'Presentando comprobantes CAEA…';
        var subtitulo = form.getAttribute('data-overlay-subtitulo') || '';
        mostrarOverlay(titulo, subtitulo);
        enviarFormularioNativo(form);
        if (id > 0) {
            marcarActivoLocal(id);
        }
        return true;
    }

    document.addEventListener('click', function (ev) {
        var btn = ev.target && ev.target.closest ? ev.target.closest('.js-arca-caea-informar-btn') : null;
        if (!btn || btn.disabled) {
            return;
        }
        var form = btn.closest('form');
        if (!form || !form.classList.contains('js-arca-caea-informar-form')) {
            return;
        }
        ev.preventDefault();
        ev.stopPropagation();
        encolarPresentacion(form);
    }, true);

    document.addEventListener('submit', function (ev) {
        var form = ev.target;
        if (!form || !form.classList || !form.classList.contains('js-arca-caea-informar-form')) {
            return;
        }
        if (form.getAttribute('data-confirmado') === '1') {
            return;
        }
        ev.preventDefault();
        if (form.querySelector('button[type="submit"]:disabled')) {
            return;
        }
        encolarPresentacion(form);
    }, true);

    document.addEventListener('DOMContentLoaded', function () {
        // Si el server ya marcó proceso activo, o quedó flag local tras el POST, poll.
        var hayActivo = false;
        idsEnPantalla().forEach(function (id) {
            var local = false;
            try {
                local = !!sessionStorage.getItem('arca-caea-activo-' + id);
            } catch (e) {}
            // El server manda: no dejar el avión apagado por un flag viejo de sessionStorage.
            if (!btnPareceActivo(id) && local) {
                try {
                    sessionStorage.removeItem('arca-caea-activo-' + id);
                } catch (e) {}
                local = false;
            }
            if (local || btnPareceActivo(id)) {
                hayActivo = true;
                if (local) {
                    aplicarEstadoFila(id, {
                        activo: true,
                        puede_presentar: false,
                        leyenda: 'Procesando en segundo plano…'
                    });
                }
            }
        });
        if (hayActivo) {
            asegurarPoll();
        }
    });
})();
