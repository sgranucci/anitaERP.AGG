/**
 * Ferli: catálogo LOCAL/FÁBRICA según depósito operativo (origen en TRA).
 * Incluye calzado con combinación e insumos sin combinación.
 */
(function ($) {
    'use strict';

    function msDepositoOperativoId() {
        var salidaVisible = $('#tm_deposito_salida').is(':visible');
        var salida = parseInt($('#deposito_salida_id').val(), 10) || 0;
        if (salidaVisible && salida > 0) {
            return salida;
        }
        return parseInt($('#deposito_id').val(), 10) || 0;
    }

    function msAplicarMetaCatalogo(ambito, canal, depositoId) {
        window.movimientoStockAmbitoCatalogo = ambito || 'FABRICA';
        window.movimientoStockCanalCatalogo = canal || 'FABRICA';
        var $modal = $('#consultaarticuloModal');
        if (!$modal.length) {
            return;
        }
        $modal.data('articuloCanal', window.movimientoStockCanalCatalogo);
        $modal.data('articuloOcultarPrecio', 1);
        if (depositoId > 0) {
            $modal.data('articuloDepositoId', depositoId);
        } else {
            $modal.removeData('articuloDepositoId');
        }
    }

    function msActualizarDatasetMarca(articulos, articulosTodos) {
        var $marca = $('#marca');
        if (!$marca.length) {
            return;
        }
        var lista = Array.isArray(articulos) ? articulos : [];
        var listaTodos = Array.isArray(articulosTodos) ? articulosTodos : lista;
        try {
            $marca.attr('data-articulo', JSON.stringify(lista));
            $marca.attr('data-articuloall', JSON.stringify(listaTodos));
        } catch (e) {
            // ignore
        }
    }

    function msRecargarCatalogoFerli() {
        if (!window.movimientoStockModoFerli) {
            return;
        }
        var url = window.movimientoStockCatalogoArticulosUrl;
        if (!url) {
            return;
        }
        var depId = msDepositoOperativoId();
        $.getJSON(url, { deposito_id: depId > 0 ? depId : 0 })
            .done(function (data) {
                if (!data) {
                    return;
                }
                msAplicarMetaCatalogo(data.ambito, data.canal, depId);
                msActualizarDatasetMarca(data.articulos || [], data.articulos_todos || data.articulos || []);
            });
    }

    window.msDepositoOperativoId = msDepositoOperativoId;
    window.msRecargarCatalogoFerli = msRecargarCatalogoFerli;
    window.msAplicarMetaCatalogoFerli = msAplicarMetaCatalogo;

    $(function () {
        if (!window.movimientoStockModoFerli) {
            return;
        }
        var depInicial = msDepositoOperativoId();
        msAplicarMetaCatalogo(
            window.movimientoStockAmbitoCatalogo || 'FABRICA',
            window.movimientoStockCanalCatalogo || 'FABRICA',
            depInicial
        );

        $(document).on(
            'change.msCatalogoFerli',
            '#deposito_id, #deposito_salida_id',
            function () {
                msRecargarCatalogoFerli();
            }
        );

        // TRA: al pasar a modo transferencia el origen puede haberse copiado del depósito simple.
        $(document).on('change.msCatalogoFerliTipo', '#tipotransaccion_stock_id', function () {
            setTimeout(msRecargarCatalogoFerli, 50);
        });
    });
})(jQuery);
