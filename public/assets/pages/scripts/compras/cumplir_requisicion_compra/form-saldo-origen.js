(function ($) {
    'use strict';

    var cfg = window.cumpleRequisicionCompraConfig || {};
    var timersSaldo = {};

    function saldoOrigenUrl() {
        return cfg.urlSaldoOrigen || '';
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

    function depositoOrigenId() {
        return parseInt($('#deposito_origen_id').val(), 10) || 0;
    }

    function articuloIdFila($tr) {
        return parseInt($tr.attr('data-articulo-id'), 10) || 0;
    }

    function pendienteFila($tr) {
        return parseFloat($tr.find('.input-cantidad-entrega').data('pendiente')) || 0;
    }

    function saldoInsuficiente($tr, saldo) {
        var pendiente = pendienteFila($tr);
        return pendiente > 0
            && saldo !== null
            && saldo !== undefined
            && !Number.isNaN(Number(saldo))
            && Number(saldo) < pendiente - 1e-9;
    }

    function mostrarSaldoFila($tr, saldo, esError) {
        var $span = $tr.find('.ms-saldo-origen');
        if (!$span.length) {
            return;
        }
        if (saldo === null || saldo === undefined) {
            $span.text('—').removeClass('text-danger text-success font-weight-bold').addClass('text-muted');
            $tr.removeClass('fila-saldo-insuficiente');
            return;
        }

        var insuficiente = !esError && saldoInsuficiente($tr, saldo);
        $tr.toggleClass('fila-saldo-insuficiente', insuficiente);

        $span.text(fmtSaldo(saldo))
            .removeClass('text-muted text-danger text-success font-weight-bold');

        if (esError || Number(saldo) < 0 || insuficiente) {
            $span.addClass('text-danger font-weight-bold');
        } else {
            $span.addClass('text-success font-weight-bold');
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
        $('#tabla-lineas-cumple tbody tr.fila-cumple-linea').each(function () {
            programarSaldoFila($(this));
        });
    }

    window.crcRefrescarSaldosOrigen = refrescarSaldosOrigen;

    $(document).on('change input', '#deposito_origen_id', refrescarSaldosOrigen);
    $(document).on('input change', '#tabla-lineas-cumple .input-cantidad-entrega', function () {
        programarSaldoFila($(this).closest('tr.fila-cumple-linea'));
    });

    $(function () {
        var prevOnDeposito = window.onDepositoAplicadoEnFormulario;
        window.onDepositoAplicadoEnFormulario = function (data, $ctx) {
            if (typeof prevOnDeposito === 'function') {
                prevOnDeposito(data, $ctx);
            }
            refrescarSaldosOrigen();
        };

        refrescarSaldosOrigen();
    });
}(jQuery));
