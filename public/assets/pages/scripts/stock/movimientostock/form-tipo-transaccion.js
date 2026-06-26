(function ($) {
    'use strict';

    function $hiddenTipo() {
        return $('#tipotransaccion_stock_id');
    }

    function $ctxTipo() {
        return $('#tm_tipotransaccion_movimientostock');
    }

    function normalizarFlag(val) {
        return val === true || val === 1 || val === '1';
    }

    window.msTipoTransaccionMeta = function () {
        var $h = $hiddenTipo();
        if (!$h.length) {
            return {
                id: 0,
                operacion: '',
                manejaContabilidad: false,
                origenBienUso: false,
                destinoBienUso: false,
                nombre: '',
                abreviatura: '',
            };
        }

        return {
            id: parseInt($h.val(), 10) || 0,
            operacion: String($h.attr('data-operacion') || '').trim(),
            manejaContabilidad: String($h.attr('data-maneja-contabilidad') || '') === '1',
            origenBienUso: String($h.attr('data-origen-bien-uso') || '') === '1',
            destinoBienUso: String($h.attr('data-destino-bien-uso') || '') === '1',
            nombre: String($('#tipotransaccion_stock_id_descripcion').val() || '').trim(),
            abreviatura: String($('#tipotransaccion_stock_id_abreviatura').val() || '').trim(),
        };
    };

    window.msOperacionTipoTransaccion = function () {
        return msTipoTransaccionMeta().operacion;
    };

    window.actualizarLinkEditarTipotransaccionStock = function ($ctx, tipoId) {
        if (!$ctx || !$ctx.length) {
            $ctx = $ctxTipo();
        }
        var $link = $ctx.find('.btn-link-editar-tipotransaccion-stock');
        if (!$link.length) {
            return;
        }
        var id = parseInt(tipoId, 10) || 0;
        if (id > 0) {
            $link.attr('href', carpetaBase + '/stock/tipotransaccion_stock/' + id + '/editar?origen=modal_consulta&vista=consulta')
                .removeClass('d-none');
        } else {
            $link.attr('href', '#').addClass('d-none');
        }
    };

    window.msAplicarTipotransaccionStockEnCampo = function (data) {
        var $ctx = $ctxTipo();
        var $hidden = $hiddenTipo();
        if (!$hidden.length || !data) {
            return false;
        }

        var id = parseInt(data.id, 10) || 0;
        $hidden.val(id > 0 ? id : '');
        $hidden.attr('data-operacion', String(data.operacion || ''));
        $hidden.attr('data-maneja-contabilidad', normalizarFlag(data.maneja_contabilidad) ? '1' : '0');
        $hidden.attr('data-origen-bien-uso', normalizarFlag(data.origen_bien_uso) ? '1' : '0');
        $hidden.attr('data-destino-bien-uso', normalizarFlag(data.destino_bien_uso) ? '1' : '0');

        $('#tipotransaccion_stock_id_abreviatura').val(data.abreviatura || '');
        $('#tipotransaccion_stock_id_descripcion').val(data.nombre || data.descripcion || '');

        actualizarLinkEditarTipotransaccionStock($ctx, id);
        $hidden.trigger('change');

        return true;
    };

    window.limpiarCampoTipotransaccionStock = function () {
        msAplicarTipotransaccionStockEnCampo({
            id: 0,
            abreviatura: '',
            nombre: '',
            operacion: '',
            maneja_contabilidad: false,
            origen_bien_uso: false,
            destino_bien_uso: false,
        });
    };

    $(function () {
        if (!$hiddenTipo().length) {
            return;
        }
        actualizarLinkEditarTipotransaccionStock($ctxTipo(), parseInt($hiddenTipo().val(), 10) || 0);

        var defaultId = parseInt($('#tipotransacciondefault_id').val(), 10) || 0;
        var actualId = parseInt($hiddenTipo().val(), 10) || 0;
        if (defaultId > 0 && actualId <= 0) {
            $.get(carpetaBase + '/stock/leertipotransaccion_stock/' + defaultId)
                .done(function (data) {
                    if (data && data.id) {
                        msAplicarTipotransaccionStockEnCampo(data);
                    }
                });
        }
    });
}(jQuery));
