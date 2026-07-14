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
    });
})(jQuery);
