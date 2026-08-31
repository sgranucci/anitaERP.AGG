(function ($) {
    'use strict';

    function pad2(n) {
        return n < 10 ? '0' + n : String(n);
    }

    function syncPeriodoFromSelects() {
        var $periodo = $('#periodo');
        var mes = String($('#periodo_mes_num').val() || '');
        var anio = String($('#periodo_anio').val() || '');
        if (!$periodo.length || !/^\d{2}$/.test(mes) || !/^\d{4}$/.test(anio)) {
            return;
        }
        $periodo.val(anio + mes);
    }

    function empresaIdActual() {
        return parseInt($('#empresa_id').val(), 10) || 0;
    }

    function refrescarLiquidaciones(mantenerSeleccion) {
        syncPeriodoFromSelects();
        var empresaId = empresaIdActual();
        var periodo = String($('#periodo').val() || '');
        var cfg = (window.CANON_MUNICIPAL && window.CANON_MUNICIPAL.mapaConfig) || {};
        var meta = cfg[empresaId] || cfg[String(empresaId)] || null;
        if (meta && meta.periodicidad) {
            $('#periodicidad').val(meta.periodicidad);
        }
        if (!empresaId || !/^\d{6}$/.test(periodo) || !window.CANON_MUNICIPAL || !window.CANON_MUNICIPAL.urlLiquidaciones) {
            return;
        }

        var liquidacion = mantenerSeleccion
            ? (parseInt($('#liquidacion').val(), 10) || window.CANON_MUNICIPAL.liquidacionActual || 1)
            : 1;

        $.getJSON(window.CANON_MUNICIPAL.urlLiquidaciones, {
            empresa_id: empresaId,
            periodo: periodo,
            liquidacion: liquidacion
        }).done(function (data) {
            var $sel = $('#liquidacion');
            $sel.empty();
            var opciones = data.opciones || {};
            Object.keys(opciones).forEach(function (key) {
                $sel.append($('<option>', { value: key, text: opciones[key] }));
            });
            var liq = String(data.liquidacion || liquidacion);
            if ($sel.find('option[value="' + liq + '"]').length) {
                $sel.val(liq);
            } else {
                $sel.prop('selectedIndex', 0);
            }
            if (data.periodicidad) {
                $('#periodicidad').val(data.periodicidad);
            }
            if (data.fecha_desde) {
                $('#fecha_desde').val(data.fecha_desde);
            }
            if (data.fecha_hasta) {
                $('#fecha_hasta').val(data.fecha_hasta);
            }
            window.CANON_MUNICIPAL.liquidacionActual = parseInt($sel.val(), 10) || 1;
        });
    }

    $(function () {
        refrescarLiquidaciones(true);

        $('#empresa_id').on('change', function () {
            refrescarLiquidaciones(false);
        });
        $('#periodo_mes_num, #periodo_anio').on('change', function () {
            refrescarLiquidaciones(true);
        });
        $('#liquidacion').on('change', function () {
            refrescarLiquidaciones(true);
        });

        var overlay = document.getElementById('canon-municipal-procesando-overlay');
        var hideTimer = null;
        var $iframe = $('#canon-municipal-dl-frame');

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

        function mostrarOverlay(titulo, subtitulo) {
            if (!overlay) {
                return;
            }
            if (titulo) {
                var t = document.getElementById('canon-municipal-procesando-titulo');
                if (t) {
                    t.textContent = titulo;
                }
            }
            if (subtitulo) {
                var s = document.getElementById('canon-municipal-procesando-subtitulo');
                if (s) {
                    s.textContent = subtitulo;
                }
            }
            overlay.classList.remove('d-none');
            overlay.style.display = 'flex';
            overlay.setAttribute('aria-hidden', 'false');
        }

        function prepararDescargaOverlay() {
            mostrarOverlay(
                'Generando PDF…',
                'Esta pantalla sigue usable; el archivo se descarga al terminar.'
            );
            $iframe.off('load.canonDl').on('load.canonDl', function () {
                ocultarOverlay();
            });
            if (hideTimer) {
                clearTimeout(hideTimer);
            }
            // Seguridad: attachment a veces no dispara load del iframe.
            hideTimer = setTimeout(ocultarOverlay, 120000);
            var onFocus = function () {
                setTimeout(ocultarOverlay, 400);
                window.removeEventListener('focus', onFocus);
            };
            window.addEventListener('focus', onFocus);
        }

        $('#form-canon-municipal').on('submit', function () {
            var form = this;
            syncPeriodoFromSelects();
            if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                return true;
            }
            mostrarOverlay(
                'Consultando canon municipal…',
                'Cruza Flash y Posición financiera. Puede demorar. No cierre la página.'
            );
            return true;
        });

        // Nota municipal y PDF/Excel/CSV del listado: descarga en iframe oculto.
        $(document).on('click', '.js-canon-municipal-dl, a[href*="listar-canon-municipal"]', function (e) {
            var $a = $(this);
            var href = $a.attr('href');
            if (!href || href === '#') {
                return;
            }
            e.preventDefault();
            prepararDescargaOverlay();
            if ($iframe.length) {
                $iframe.attr('src', href);
            } else {
                window.location.href = href;
            }
        });

        window.addEventListener('pageshow', ocultarOverlay);
    });
})(jQuery);
