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

    function lineaRestaStock($tr) {
        if (window.msPermiteSaldosNegativos) {
            return false;
        }
        if (typeof window.msDepositoOrigenRequiereControlStock === 'function'
            && !window.msDepositoOrigenRequiereControlStock()) {
            return false;
        }
        var op = operacionTipo();
        if (op === 'S') {
            return true;
        }
        if (op === 'T') {
            return $('#tm_deposito_salida').is(':visible');
        }
        if (op === 'C') {
            return String($tr.find('.ms-sentido-canje').val() || 'E').toUpperCase() === 'S';
        }
        return false;
    }

    function textoAvisoSaldo($tr, saldo) {
        if (!lineaRestaStock($tr) || saldo === null || saldo === undefined || Number.isNaN(Number(saldo))) {
            return '';
        }
        var num = Number(saldo);
        if (num <= 0.000001) {
            return 'sin saldo';
        }
        var cant = parseFloat($tr.find('.cantidad-stock').val() || $tr.find('.cantidad').val() || 0);
        if (cant > 0 && cant > num + 0.000001) {
            return 'no alcanza';
        }
        return '';
    }

    function tituloAvisoSaldo(texto) {
        if (texto === 'sin saldo') {
            return 'Este art\u00edculo no tiene saldo en el dep\u00f3sito. No se puede sacar.';
        }
        if (texto === 'no alcanza') {
            return 'La cantidad supera el saldo del dep\u00f3sito.';
        }
        return 'Saldo en dep\u00f3sito origen';
    }

    function avisoSaldoEl($tr) {
        var $aviso = $tr.find('.ms-saldo-aviso');
        if ($aviso.length) {
            return $aviso;
        }
        var $span = $tr.find('.ms-saldo-origen');
        if (!$span.length) {
            return $();
        }
        $aviso = $('<span class="ms-saldo-aviso d-none" role="status"></span>');
        $span.after($aviso);
        return $aviso;
    }

    function pintarAvisoSaldo($tr, saldo) {
        var texto = textoAvisoSaldo($tr, saldo);
        var $aviso = avisoSaldoEl($tr);
        var $span = $tr.find('.ms-saldo-origen');
        if ($aviso.length) {
            $aviso.text(texto).attr('title', texto ? tituloAvisoSaldo(texto) : '');
            $aviso.toggleClass('d-none', texto === '');
            $aviso.css({
                display: texto ? 'block' : '',
                marginTop: '1px',
                fontSize: '0.65rem',
                lineHeight: '1.05',
                fontWeight: '600',
                letterSpacing: '0.01em',
                color: '#7B241C'
            });
        }
        if ($span.length && saldo !== null && saldo !== undefined) {
            $span.attr('title', tituloAvisoSaldo(texto));
        }
        return texto;
    }

    function mostrarSaldoFila($tr, saldo, esError) {
        var $span = $tr.find('.ms-saldo-origen');
        if (!$span.length) {
            return;
        }
        if (saldo === null || saldo === undefined) {
            $tr.removeData('msSaldo');
            $span.text('—').removeClass('text-danger text-success font-weight-bold').addClass('text-muted');
            $span.attr('title', 'Saldo en dep\u00f3sito origen');
            pintarAvisoSaldo($tr, null);
            return;
        }
        var num = Number(saldo);
        $tr.data('msSaldo', num);
        $span.text(fmtSaldo(saldo))
            .removeClass('text-muted text-danger text-success font-weight-bold');
        var aviso = pintarAvisoSaldo($tr, num);
        if (esError || aviso || num < 0) {
            $span.addClass('text-danger font-weight-bold');
        } else {
            $span.addClass('text-success font-weight-bold');
        }
    }

    function repintarAvisoFila($tr) {
        var saldo = $tr.data('msSaldo');
        if (saldo === undefined) {
            return;
        }
        mostrarSaldoFila($tr, saldo);
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
            color_id: colorIdFila($tr),
            talle_id: talleIdFila($tr),
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

    function colorIdFila($tr) {
        return parseInt($tr.find('select.ms-color-id').val(), 10) || 0;
    }

    function talleIdFila($tr) {
        return parseInt($tr.find('select.ms-talle-id').val(), 10) || 0;
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

    $(document).on('change', '.ms-sentido-canje', function () {
        repintarAvisoFila($(this).closest('tr'));
    });

    $(document).on('input change', '#tabla-items-movimientostock .cantidad, #tabla-items-movimientostock .cantidad-stock', function () {
        repintarAvisoFila($(this).closest('tr'));
    });

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
