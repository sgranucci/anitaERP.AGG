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

    function ultimoDiaMes(anio, mes) {
        return new Date(anio, mes, 0).getDate();
    }

    function actualizarFechasDesdeLiquidacion() {
        syncPeriodoFromSelects();
        var periodo = String($('#periodo').val() || '');
        var liquidacion = parseInt($('#liquidacion').val(), 10) || 0;
        if (!/^\d{6}$/.test(periodo)) {
            return;
        }
        var anio = parseInt(periodo.substring(0, 4), 10);
        var mes = parseInt(periodo.substring(4, 6), 10);
        if (!Number.isFinite(anio) || !Number.isFinite(mes) || mes < 1 || mes > 12) {
            return;
        }
        var ultimo = ultimoDiaMes(anio, mes);
        var desdeDia = 1;
        var hastaDia = ultimo;
        if (liquidacion === 1) {
            hastaDia = 15;
        } else if (liquidacion === 2) {
            desdeDia = 16;
        }
        $('#fecha_desde').val(anio + '-' + pad2(mes) + '-' + pad2(desdeDia));
        $('#fecha_hasta').val(anio + '-' + pad2(mes) + '-' + pad2(hastaDia));
    }

    $(function () {
        actualizarFechasDesdeLiquidacion();
        $('#periodo_mes_num, #periodo_anio, #liquidacion').on('change', actualizarFechasDesdeLiquidacion);

        var overlay = document.getElementById('suss-procesando-overlay');

        function ocultarOverlay() {
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

        $('#form-suss').on('submit', function () {
            var form = this;
            syncPeriodoFromSelects();
            if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                return true;
            }
            mostrarOverlay();
            return true;
        });

        window.addEventListener('pageshow', ocultarOverlay);
    });
})(jQuery);
