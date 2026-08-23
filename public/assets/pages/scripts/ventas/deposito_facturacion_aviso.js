(function ($) {
    'use strict';

    function mapa() {
        return window.DEPOSITO_FACTURA_UI || null;
    }

    function infoDeposito(transporteId) {
        var data = mapa();
        if (!data || !data.default) {
            return null;
        }
        var key = String(parseInt(transporteId, 10) || 0);
        if (key !== '0' && data.por_transporte_id && data.por_transporte_id[key]) {
            return data.por_transporte_id[key];
        }

        return data.default;
    }

    function etiquetaDeposito(info) {
        var codigo = (info && info.codigo) ? String(info.codigo) : '';
        var nombre = (info && info.nombre) ? String(info.nombre) : '';
        var texto = (codigo + ' ' + nombre).trim();

        return texto !== '' ? texto : ('depósito id ' + (info && info.id ? info.id : '?'));
    }

    function mensaje(info) {
        if (!info) {
            return '';
        }
        var deposito = etiquetaDeposito(info);
        if (info.desde_reparto) {
            var reparto = ((info.transporte_codigo || '') + ' ' + (info.transporte_nombre || '')).trim();
            return 'El stock se descontará del depósito del reparto'
                + (reparto !== '' ? ' ' + reparto : '')
                + ': ' + deposito + '.';
        }

        return 'El stock se descontará del depósito default de ventas: ' + deposito + '.';
    }

    function transporteIdActual() {
        var $campo = $('#transporte_id');
        if ($campo.length) {
            return $campo.val();
        }

        return '';
    }

    window.actualizarAvisoDepositoFacturacion = function (transporteId) {
        var $targets = $('.aviso-deposito-facturacion');
        if (!$targets.length) {
            return;
        }
        var info = infoDeposito(transporteId != null ? transporteId : transporteIdActual());
        var texto = mensaje(info);
        $targets.each(function () {
            var $el = $(this);
            if (!texto) {
                $el.addClass('d-none').text('');
                return;
            }
            $el.removeClass('d-none').text(texto);
        });
    };

    $(function () {
        window.actualizarAvisoDepositoFacturacion();
        $(document).on('change', '#transporte_id, .transporte_id', function () {
            window.actualizarAvisoDepositoFacturacion($(this).val());
        });
        $(document).on('hidden.bs.modal', '#consultatransporteModal', function () {
            window.actualizarAvisoDepositoFacturacion();
        });
        $(document).on('shown.bs.modal', '#facturarPedidoModal', function () {
            window.actualizarAvisoDepositoFacturacion();
        });
    });
})(jQuery);
