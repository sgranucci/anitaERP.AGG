(function ($) {
    'use strict';

    var timersSaldo = {};

    function saldoOrigenUrl() {
        return window.movimientoStockSaldoOrigenUrl || '';
    }

    function fmtSaldo(n) {
        if (n === null || n === undefined || Number.isNaN(Number(n))) {
            return '—';
        }
        var num = Number(n);
        if (Math.abs(num - Math.trunc(num)) < 1e-9) {
            return String(Math.trunc(num));
        }
        return num.toFixed(6).replace(/0+$/, '').replace(/\.$/, '');
    }

    function operacionTipo() {
        return typeof window.msOperacionTipoTransaccion === 'function'
            ? window.msOperacionTipoTransaccion()
            : '';
    }

    function ctxDepositoOrigen() {
        if (operacionTipo() === 'T' && $('#tm_deposito_salida').is(':visible')) {
            return $('#tm_deposito_salida');
        }

        return $('#tm_deposito_movimientostock');
    }

    function depositoOrigenRequiereControlStock() {
        var $ctx = ctxDepositoOrigen();
        if (!$ctx.length) {
            return true;
        }

        var tipo = String($ctx.attr('data-tipodeposito') || '').trim();
        if (!tipo) {
            return true;
        }

        return tipo.toLowerCase() !== 'centro de consumo' && tipo.toUpperCase() !== 'M';
    }

    function depositoOrigenId() {
        if (!depositoOrigenRequiereControlStock()) {
            return 0;
        }

        var op = operacionTipo();
        if (op === 'T') {
            var origenBien = typeof window.msTipoTransaccionMeta === 'function'
                ? window.msTipoTransaccionMeta().origenBienUso
                : false;
            if (origenBien || !$('#tm_deposito_salida').is(':visible')) {
                return 0;
            }
            return parseInt($('#deposito_salida_id').val(), 10) || 0;
        }
        return parseInt($('#deposito_id').val(), 10) || 0;
    }

    function articuloIdFila($tr) {
        if (typeof window.msFilaArticuloId === 'function') {
            var id = parseInt(window.msFilaArticuloId($tr), 10) || 0;
            if (id > 0) {
                return id;
            }
        }
        return parseInt($tr.find('input.articulo_id[name="articulos_id[]"], .articulo_id').first().val(), 10) || 0;
    }

    function mostrarSaldoFila($tr, saldo, esError) {
        var $span = $tr.find('.ms-saldo-origen');
        if (!$span.length) {
            return;
        }
        if (saldo === null || saldo === undefined) {
            $span.text('—').removeClass('text-danger text-success font-weight-bold').addClass('text-muted');
            return;
        }
        $span.text(fmtSaldo(saldo))
            .removeClass('text-muted text-danger text-success font-weight-bold');
        if (esError) {
            $span.addClass('text-danger');
        } else {
            var num = Number(saldo);
            $span.addClass(num < 0 ? 'text-danger' : 'text-success font-weight-bold');
        }
    }

    function cargarSaldoFila($tr) {
        var saldoUrl = saldoOrigenUrl();
        if (!saldoUrl || !$tr.length) {
            return;
        }

        var depId = depositoOrigenId();
        var articuloId = articuloIdFila($tr);

        if (depId <= 0 || articuloId <= 0) {
            mostrarSaldoFila($tr, null);
            return;
        }

        $.get(saldoUrl, {
            articulo_id: articuloId,
            deposito_id: depId,
        }).done(function (data) {
            if (data && data.error) {
                mostrarSaldoFila($tr, null, true);
                return;
            }
            mostrarSaldoFila($tr, data && data.saldo !== undefined ? data.saldo : 0);
        }).fail(function () {
            mostrarSaldoFila($tr, null, true);
        });
    }

    function programarSaldoFila($tr) {
        var key = $tr.index();
        clearTimeout(timersSaldo[key]);
        timersSaldo[key] = setTimeout(function () {
            cargarSaldoFila($tr);
        }, 200);
    }

    function refrescarSaldosOrigen() {
        $('#tabla-items-movimientostock tbody tr.item-pedido').each(function () {
            programarSaldoFila($(this));
        });
    }

    window.msRefrescarSaldosOrigen = refrescarSaldosOrigen;
    window.msDepositoOrigenRequiereControlStock = depositoOrigenRequiereControlStock;

    $(document).on('change input', '#tabla-items-movimientostock .articulo_id, #tabla-items-movimientostock .codigoarticulo', function () {
        programarSaldoFila($(this).closest('tr'));
    });

    $(document).on('change', '#tipotransaccion_stock_id, #deposito_id, #deposito_salida_id', refrescarSaldosOrigen);

    $(document).on('click', '#agrega_renglon, #tabla-items-movimientostock .eliminar', function () {
        setTimeout(refrescarSaldosOrigen, 150);
    });

    $(function () {
        var prevOnDeposito = window.onDepositoAplicadoEnFormulario;
        window.onDepositoAplicadoEnFormulario = function (data, $ctx) {
            if (typeof prevOnDeposito === 'function') {
                prevOnDeposito(data, $ctx);
            }
            refrescarSaldosOrigen();
        };

        var prevOnArticulo = window.onArticuloSeleccionado;
        window.onArticuloSeleccionado = function (dataArticulo, ctx) {
            if (typeof prevOnArticulo === 'function') {
                prevOnArticulo(dataArticulo, ctx);
            }
            if (ctx && ctx.row && $(ctx.row).closest('#tabla-items-movimientostock').length) {
                programarSaldoFila($(ctx.row));
            }
        };

        refrescarSaldosOrigen();
    });
}(jQuery));
