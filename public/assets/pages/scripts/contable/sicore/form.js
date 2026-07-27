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

    $(function () {
        $('#fecha_desde').on('change', ajustarHastaPorDesde);

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
