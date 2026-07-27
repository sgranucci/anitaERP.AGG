(function ($) {
    'use strict';

    function pad2(n) {
        return n < 10 ? '0' + n : String(n);
    }

    function syncPeriodoFromMonth() {
        var $mes = $('#periodo_mes');
        var $periodo = $('#periodo');
        if (!$mes.length || !$periodo.length) {
            return;
        }
        var val = String($mes.val() || '');
        if (/^\d{4}-\d{2}$/.test(val)) {
            $periodo.val(val.replace('-', ''));
        }
    }

    function ultimoDiaMes(anio, mes) {
        return new Date(anio, mes, 0).getDate();
    }

    function actualizarFechasDesdeLiquidacion() {
        syncPeriodoFromMonth();
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
        $('#periodo_mes, #liquidacion').on('change', actualizarFechasDesdeLiquidacion);

        var overlay = document.getElementById('ingresos-brutos-procesando-overlay');

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

        $('#form-ingresos-brutos').on('submit', function () {
            var form = this;
            syncPeriodoFromMonth();
            if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                return true;
            }
            mostrarOverlay();
            return true;
        });

        window.addEventListener('pageshow', ocultarOverlay);

        if (typeof activa_eventos_consultaprovincia === 'function') {
            activa_eventos_consultaprovincia();
        }
    });
})(jQuery);
