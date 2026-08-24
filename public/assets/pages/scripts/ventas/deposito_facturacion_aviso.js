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

    function $campoDepositoFactura() {
        var $ctx = $('#tm_deposito_factura');
        if ($ctx.length) {
            return $ctx;
        }
        var $hidden = $('#deposito_id');
        if ($hidden.length && $hidden.closest('.tm-deposito-campo').length) {
            return $hidden.closest('.tm-deposito-campo');
        }

        return $();
    }

    function aplicarCampoDepositoDesdeReparto(info) {
        var $ctx = $campoDepositoFactura();
        if (!$ctx.length || !info || !info.id) {
            return;
        }
        $ctx.find('.deposito_id').val(info.id);
        $ctx.find('.codigodeposito').val(info.codigo || '');
        $ctx.find('.descripciondeposito').val(info.nombre || '');
        if (typeof actualizarLinkEditarDeposito === 'function') {
            actualizarLinkEditarDeposito($ctx, info.id);
        }
    }

    window.actualizarAvisoDepositoFacturacion = function (transporteId, opciones) {
        opciones = opciones || {};
        var $targets = $('.aviso-deposito-facturacion');
        var info = infoDeposito(transporteId != null ? transporteId : transporteIdActual());
        if ($targets.length) {
            var texto = mensaje(info);
            $targets.each(function () {
                var $el = $(this);
                if (!texto) {
                    $el.addClass('d-none').text('');
                    return;
                }
                $el.removeClass('d-none').text(texto);
            });
        }
        if (opciones.sincronizarCampo) {
            aplicarCampoDepositoDesdeReparto(info);
        }
    };

    $(function () {
        window.actualizarAvisoDepositoFacturacion();
        $(document).on('change', '#transporte_id, .transporte_id', function () {
            window.actualizarAvisoDepositoFacturacion($(this).val(), { sincronizarCampo: true });
        });
        $(document).on('hidden.bs.modal', '#consultatransporteModal', function () {
            window.actualizarAvisoDepositoFacturacion();
        });
        $(document).on('shown.bs.modal', '#facturarPedidoModal', function () {
            window.actualizarAvisoDepositoFacturacion();
        });
    });
})(jQuery);
