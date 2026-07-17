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
                bajaNpu: false,
            };
        }

        return {
            id: parseInt($h.val(), 10) || 0,
            operacion: String($h.attr('data-operacion') || '').trim(),
            manejaContabilidad: String($h.attr('data-maneja-contabilidad') || '') === '1',
            origenBienUso: String($h.attr('data-origen-bien-uso') || '') === '1',
            destinoBienUso: String($h.attr('data-destino-bien-uso') || '') === '1',
            requiereAprobacion: String($h.attr('data-requiere-aprobacion') || '') === '1',
            avisoOpcional: String($h.attr('data-aviso-opcional') || '') === '1',
            bajaNpu: String($h.attr('data-baja-npu') || '') === '1',
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
        $hidden.attr('data-requiere-aprobacion', normalizarFlag(data.requiere_aprobacion) ? '1' : '0');
        $hidden.attr('data-aviso-opcional', normalizarFlag(data.aviso_opcional) ? '1' : '0');
        $hidden.attr('data-baja-npu', normalizarFlag(data.baja_npu) ? '1' : '0');

        $('#tipotransaccion_stock_id_abreviatura').val(data.abreviatura || '');
        $('#tipotransaccion_stock_id_descripcion').val(data.nombre || data.descripcion || '');

        actualizarLinkEditarTipotransaccionStock($ctx, id);
        $hidden.trigger('change');
        if (typeof window.msAplicarModoBajaNpuEnTabla === 'function') {
            window.msAplicarModoBajaNpuEnTabla();
        }

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
            requiere_aprobacion: false,
            aviso_opcional: false,
            baja_npu: false,
        });
    };

    function enfocarCampoMovStock(selector) {
        var el = document.querySelector(selector);
        if (!el || el.readOnly || el.disabled) {
            return false;
        }
        el.focus();
        if (typeof el.select === 'function') {
            el.select();
        }
        return true;
    }

    window.enfocarTipoTransaccionMovimientoStock = function () {
        return enfocarCampoMovStock('#tipotransaccion_stock_id_abreviatura');
    };

    function selectorCodigoDepositoMovimientoStock() {
        if (msOperacionTipoTransaccion() === 'T' && $('#ms_panel_transferencia').is(':visible')) {
            return '#deposito_salida_id_codigo';
        }

        return '#deposito_id_codigo';
    }

    window.enfocarDepositoMovimientoStock = function () {
        return enfocarCampoMovStock(selectorCodigoDepositoMovimientoStock());
    };

    window.enfocarPrimerArticuloMovimientoStock = function () {
        return enfocarCampoMovStock('#tabla-items-movimientostock .codigoarticulo');
    };

    window.enfocarSiguienteCampoTrasTipoTransaccionMov = function () {
        if (!$('#formgeneral').length || !$('#tm_tipotransaccion_movimientostock').length) {
            return false;
        }

        return enfocarDepositoMovimientoStock();
    };

    function enfocarPrimerArticuloMovimientoStock() {
        return enfocarCampoMovStock('#tabla-items-movimientostock .codigoarticulo');
    }

    function tipoTransaccionCompletoEnFormulario() {
        var meta = msTipoTransaccionMeta();

        return meta.id > 0 && (meta.abreviatura !== '' || meta.nombre !== '');
    }

    function debeAplicarFocoInicialMovimientoStock() {
        if (!$('#formgeneral').length) {
            return false;
        }

        var params = new URLSearchParams(window.location.search);
        return params.get('vista') !== 'consulta';
    }

    function aplicarFocoInicialMovimientoStock() {
        if (!debeAplicarFocoInicialMovimientoStock()) {
            return;
        }

        if ($('#movimientostockid').length) {
            enfocarPrimerArticuloMovimientoStock();
            return;
        }

        if (!tipoTransaccionCompletoEnFormulario()) {
            enfocarTipoTransaccionMovimientoStock();
            return;
        }

        enfocarDepositoMovimientoStock();
    }

    function programarFocoInicialMovimientoStock() {
        if (!debeAplicarFocoInicialMovimientoStock()) {
            return;
        }

        window.setTimeout(aplicarFocoInicialMovimientoStock, 150);
    }

    function cargarTipoTransaccionSiIncompleto(onListo) {
        if (tipoTransaccionCompletoEnFormulario()) {
            if (typeof onListo === 'function') {
                onListo();
            }
            return;
        }

        var actualId = parseInt($hiddenTipo().val(), 10) || 0;
        var defaultId = parseInt($('#tipotransacciondefault_id').val(), 10) || 0;
        var loadId = actualId > 0 ? actualId : defaultId;

        if (loadId <= 0) {
            if (typeof onListo === 'function') {
                onListo();
            }
            return;
        }

        $.get(carpetaBase + '/stock/leertipotransaccion_stock/' + loadId)
            .done(function (data) {
                if (data && data.id) {
                    msAplicarTipotransaccionStockEnCampo(data);
                }
            })
            .always(function () {
                if (typeof onListo === 'function') {
                    onListo();
                }
            });
    }

    $(function () {
        if (!$hiddenTipo().length) {
            return;
        }
        actualizarLinkEditarTipotransaccionStock($ctxTipo(), parseInt($hiddenTipo().val(), 10) || 0);

        cargarTipoTransaccionSiIncompleto(function () {
            programarFocoInicialMovimientoStock();
        });
    });
}(jQuery));
