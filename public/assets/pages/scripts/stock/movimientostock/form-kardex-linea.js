(function ($) {
    'use strict';

    function operacionTipo() {
        return String($('#tipotransaccion_stock_id option:selected').attr('data-operacion') || '').trim();
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
            var origenBien = String($('#tipotransaccion_stock_id option:selected').attr('data-origen-bien-uso') || '') === '1';
            var destinoBien = String($('#tipotransaccion_stock_id option:selected').attr('data-destino-bien-uso') || '') === '1';

            if (!origenBien && $('#tm_deposito_salida').is(':visible')) {
                var o = opcionDeposito(
                    $('#deposito_salida_id').val(),
                    $('#deposito_salida_codigo').val(),
                    $('#deposito_salida_descripcion').val(),
                    'Origen'
                );
                if (o) {
                    opciones.push(o);
                }
            }
            if (!destinoBien && $('#tm_deposito_entrada').is(':visible')) {
                var d = opcionDeposito(
                    $('#deposito_entrada_id').val(),
                    $('#deposito_entrada_codigo').val(),
                    $('#deposito_entrada_descripcion').val(),
                    'Destino'
                );
                if (d) {
                    opciones.push(d);
                }
            }

            return opciones;
        }

        var simple = opcionDeposito(
            $('#deposito_id').val(),
            $('#deposito_id_codigo').val(),
            $('#deposito_id_descripcion').val(),
            ''
        );
        if (simple) {
            opciones.push(simple);
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
