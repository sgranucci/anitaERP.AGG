(function ($) {
    'use strict';

    function parseIsoDate(value) {
        if (!value || typeof value !== 'string') {
            return null;
        }
        var parts = value.split('-');
        if (parts.length !== 3) {
            return null;
        }
        var y = parseInt(parts[0], 10);
        var m = parseInt(parts[1], 10) - 1;
        var d = parseInt(parts[2], 10);
        if (!Number.isFinite(y) || !Number.isFinite(m) || !Number.isFinite(d)) {
            return null;
        }
        return new Date(y, m, d);
    }

    function formatIsoDate(date) {
        var y = date.getFullYear();
        var m = date.getMonth() + 1;
        var d = date.getDate();
        return y + '-' + (m < 10 ? '0' : '') + m + '-' + (d < 10 ? '0' : '') + d;
    }

    function finDeMes(date) {
        return new Date(date.getFullYear(), date.getMonth() + 1, 0);
    }

    function mesAnteriorAlActual(date) {
        var hoy = new Date();
        var mesDesde = date.getFullYear() * 12 + date.getMonth();
        var mesActual = hoy.getFullYear() * 12 + hoy.getMonth();
        return mesDesde < mesActual;
    }

    function ajustarHastaPorDesde() {
        var $desde = $('#fecha_desde');
        var $hasta = $('#fecha_hasta');
        if (!$desde.length || !$hasta.length) {
            return;
        }

        var desde = parseIsoDate($desde.val());
        if (!desde || !mesAnteriorAlActual(desde)) {
            return;
        }

        $hasta.val(formatIsoDate(finDeMes(desde)));
    }

    function syncExportControlPeriodo(activo) {
        document.querySelectorAll('a[href*="listar_sicore"], a[href*="exportar_sicore"]').forEach(function (link) {
            try {
                var url = new URL(link.href, window.location.origin);
                if (activo) {
                    url.searchParams.set('control_periodo', '1');
                } else {
                    url.searchParams.delete('control_periodo');
                }
                link.href = url.pathname + url.search + url.hash;
            } catch (e) {
                // ignore malformed href
            }
        });
    }

    function setControlPeriodoVisible(activo) {
        document.querySelectorAll('.sicore-fila-control-periodo').forEach(function (tr) {
            tr.classList.toggle('d-none', !activo);
        });
        document.querySelectorAll('.sicore-ayuda-control-periodo').forEach(function (el) {
            el.classList.toggle('d-none', !activo);
        });

        var btn = document.getElementById('sicore-btn-control-periodo');
        if (btn) {
            btn.classList.toggle('btn-warning', activo);
            btn.classList.toggle('btn-outline-warning', !activo);
            btn.setAttribute('aria-pressed', activo ? 'true' : 'false');
        }

        var chk = document.getElementById('control_periodo');
        if (chk) {
            chk.checked = !!activo;
        }

        syncExportControlPeriodo(activo);
    }

    $(function () {
        $('#fecha_desde').on('change', ajustarHastaPorDesde);

        function syncCheckboxControlPeriodoConConciliar() {
            var conciliar = document.getElementById('conciliar_contable');
            var control = document.getElementById('control_periodo');
            if (!conciliar || !control) {
                return;
            }
            control.disabled = !conciliar.checked;
            if (!conciliar.checked) {
                control.checked = false;
            }
        }

        syncCheckboxControlPeriodoConConciliar();
        $('#conciliar_contable').on('change', syncCheckboxControlPeriodoConConciliar);

        $(document).on('click', '#sicore-btn-control-periodo', function (e) {
            e.preventDefault();
            var pressed = this.getAttribute('aria-pressed') === 'true';
            setControlPeriodoVisible(!pressed);
        });

        // Si el checkbox del filtro cambia y ya hay grilla, muestra/oculta el renglón.
        $('#control_periodo').on('change', function () {
            if (document.querySelector('.sicore-fila-control-periodo')) {
                setControlPeriodoVisible(this.checked);
            }
        });

        var overlay = document.getElementById('sicore-liquidacion-overlay');
        var hideTimer = null;

        function ocultarOverlay() {
            if (hideTimer) {
                clearTimeout(hideTimer);
                hideTimer = null;
            }
            if (!overlay) {
                return;
            }
            overlay.classList.add('d-none');
            overlay.style.display = '';
            overlay.setAttribute('aria-hidden', 'true');
        }

        function mostrarOverlay() {
            if (!overlay) {
                return;
            }
            overlay.classList.remove('d-none');
            overlay.style.display = 'flex';
            overlay.setAttribute('aria-hidden', 'false');
        }

        $(document).on('click', '.js-sicore-liquidacion-abrir', function (e) {
            e.preventDefault();
            $('#modal-sicore-liquidacion').modal('show');
        });

        // Descarga en iframe oculto: la pantalla original no navega.
        // Overlay informativo; se oculta al load del iframe, al foco o por timeout de seguridad.
        $('#form-sicore-liquidacion').on('submit', function () {
            var form = this;
            if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                return true;
            }
            $('#modal-sicore-liquidacion').modal('hide');
            mostrarOverlay();

            var $iframe = $('#sicore-liq-dl-frame');
            $iframe.off('load.sicoreLiq').on('load.sicoreLiq', function () {
                // Attachment suele disparar load al iniciar/terminar la respuesta.
                ocultarOverlay();
            });

            if (hideTimer) {
                clearTimeout(hideTimer);
            }
            // Seguridad: no dejar el banner eterno si el iframe no dispara load.
            hideTimer = setTimeout(ocultarOverlay, 120000);

            var onFocus = function () {
                setTimeout(ocultarOverlay, 400);
                window.removeEventListener('focus', onFocus);
            };
            window.addEventListener('focus', onFocus);

            return true;
        });

        window.addEventListener('pageshow', ocultarOverlay);
    });
})(jQuery);
