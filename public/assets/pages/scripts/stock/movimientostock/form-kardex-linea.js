(function ($) {
    'use strict';

    function operacionTipo() {
        return typeof window.msOperacionTipoTransaccion === 'function'
            ? window.msOperacionTipoTransaccion()
            : '';
    }

    function metaTipo() {
        return typeof window.msTipoTransaccionMeta === 'function'
            ? window.msTipoTransaccionMeta()
            : { origenBienUso: false, destinoBienUso: false };
    }

    function opcionDeposito(id, codigo, nombre, prefijo) {
        id = parseInt(id, 10) || 0;
        if (id <= 0) {
            return null;
        }
        var partes = [];
        if (prefijo) {
            partes.push(prefijo);
        }
        if (codigo) {
            partes.push(codigo);
        }
        if (nombre) {
            partes.push(nombre);
        }
        if (partes.length === (prefijo ? 1 : 0)) {
            partes.push('ID ' + id);
        }

        return { id: id, label: partes.join(' — ') };
    }

    window.depositosKardexMovimientoStock = function () {
        var op = operacionTipo();
        var opciones = [];

        if (op === 'T') {
            var origenBien = metaTipo().origenBienUso;
            var destinoBien = metaTipo().destinoBienUso;

            if (!origenBien && $('#tm_deposito_salida').is(':visible')) {
                var salida = window.depositoCampoInputs('deposito_salida_id');
                var o = opcionDeposito(
                    salida.$id.val(),
                    salida.$codigo.val(),
                    salida.$descripcion.val(),
                    'Origen'
                );
                if (o) {
                    opciones.push(o);
                }
            }
            if (!destinoBien && $('#tm_deposito_entrada').is(':visible')) {
                var entrada = window.depositoCampoInputs('deposito_entrada_id');
                var d = opcionDeposito(
                    entrada.$id.val(),
                    entrada.$codigo.val(),
                    entrada.$descripcion.val(),
                    'Destino'
                );
                if (d) {
                    opciones.push(d);
                }
            }

            return opciones;
        }

        var simple = window.depositoCampoInputs('deposito_id');
        var simpleOpt = opcionDeposito(
            simple.$id.val(),
            simple.$codigo.val(),
            simple.$descripcion.val(),
            ''
        );
        if (simpleOpt) {
            opciones.push(simpleOpt);
        }

        return opciones;
    };

    function refrescarBotonesKardexFilas() {
        document.querySelectorAll('#tabla-items-movimientostock tbody tr.item-pedido').forEach(function (tr) {
            if (typeof window.actualizarBotonKardexMovimientoStockFila === 'function') {
                window.actualizarBotonKardexMovimientoStockFila(tr);
            }
        });
    }

    $(document).on('change', '#tipotransaccion_stock_id, #deposito_id, #deposito_salida_id, #deposito_entrada_id', refrescarBotonesKardexFilas);
    $(document).on('change input', '#tabla-items-movimientostock .articulo_id, #tabla-items-movimientostock .codigoarticulo', function () {
        var tr = $(this).closest('tr').get(0);
        if (tr && typeof window.actualizarBotonKardexMovimientoStockFila === 'function') {
            window.actualizarBotonKardexMovimientoStockFila(tr);
        }
    });

    window.obtenerEmpresaIdFiltroSaldosKardex = function () {
        var el = document.getElementById('empresa_id');
        if (el && el.value) {
            return parseInt(el.value, 10) || 0;
        }

        return 0;
    };

    $(function () {
        var prevOnArticulo = window.onArticuloSeleccionado;
        window.onArticuloSeleccionado = function (dataArticulo, ctx) {
            if (typeof prevOnArticulo === 'function') {
                prevOnArticulo(dataArticulo, ctx);
            }
            if (ctx && ctx.row) {
                var tr = $(ctx.row).get(0);
                if (tr && $(tr).closest('#tabla-items-movimientostock').length
                    && typeof window.actualizarBotonKardexMovimientoStockFila === 'function') {
                    window.actualizarBotonKardexMovimientoStockFila(tr);
                }
            }
        };

        refrescarBotonesKardexFilas();
    });
}(jQuery));
