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

    function esCaba(tipo) {
        return tipo === 'retenciones_caba' || tipo === 'percepciones_caba';
    }

    function aplicarFisco() {
        var tipo = String($('#tipo').val() || '');
        var caba = esCaba(tipo);
        $('#liquidacion option').each(function () {
            var valor = parseInt(this.value, 10);
            this.disabled = caba && (valor === 1 || valor === 2);
        });
        if (caba && parseInt($('#liquidacion').val(), 10) !== 3) {
            $('#liquidacion').val('3');
        }

        var fiscos = window.iibbProvinciasFisco || {};
        var fisco = caba ? fiscos.caba : fiscos.arba;
        if (fisco && fisco.id) {
            $('#provincia_id').val(fisco.id);
            $('#codigoprovincia').val(fisco.codigo || '');
            $('#nombreprovincia').val(fisco.nombre || '');
        }
        actualizarFechasDesdeLiquidacion();
    }

    function refrescarTipos() {
        var empresaId = String($('#empresa_id').val() || '');
        var mapa = (window.iibbTiposPorEmpresa || {})[empresaId] || {};
        var actual = String($('#tipo').val() || '');
        var $tipo = $('#tipo');
        if (!$tipo.length) {
            return;
        }
        $tipo.empty();
        var claves = Object.keys(mapa);
        if (!claves.length) {
            $tipo.append($('<option>', { value: '', text: 'Sin agente IIBB configurado' }));
            return;
        }
        claves.forEach(function (clave) {
            $tipo.append($('<option>', {
                value: clave,
                text: mapa[clave],
                selected: clave === actual
            }));
        });
        if (actual && mapa[actual]) {
            $tipo.val(actual);
        }
        aplicarFisco();
    }

    $(function () {
        actualizarFechasDesdeLiquidacion();
        aplicarFisco();
        $('#periodo_mes_num, #periodo_anio, #liquidacion').on('change', actualizarFechasDesdeLiquidacion);
        $('#tipo').on('change', aplicarFisco);
        $('#empresa_id').on('change', refrescarTipos);

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
            syncPeriodoFromSelects();
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
