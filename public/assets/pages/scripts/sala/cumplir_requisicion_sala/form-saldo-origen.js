(function ($) {
    'use strict';

    var cfg = window.cumpleRequisicionSalaConfig || {};
    var timersSaldo = {};
    var avisosSaldoPendientes = {};

    function saldoOrigenUrl() {
        return cfg.urlSaldoOrigen || '';
    }

    function fmtSaldo(n) {
        if (n === null || n === undefined || Number.isNaN(Number(n))) {
            return '\u2014';
        }
        var num = Number(n);
        if (Math.abs(num - Math.trunc(num)) < 1e-9) {
            return String(Math.trunc(num));
        }
        return num.toFixed(6).replace(/0+$/, '').replace(/\.$/, '');
    }

    function articuloIdFila($tr) {
        return parseInt($tr.attr('data-articulo-id'), 10) || 0;
    }

    function depositoIdFila($tr) {
        return parseInt($tr.find('.deposito_id').val(), 10) || 0;
    }

    function pendienteFila($tr) {
        return parseFloat($tr.find('.input-cantidad-entrega').data('pendiente')) || 0;
    }

    function cantidadEntregaFila($tr) {
        return parseFloat($tr.find('.input-cantidad-entrega').val()) || 0;
    }

    function cantidadAValidarFila($tr) {
        var entrega = cantidadEntregaFila($tr);
        if (entrega > 0) {
            return entrega;
        }
        return pendienteFila($tr);
    }

    function skuFila($tr) {
        return $.trim($tr.find('td').eq(1).text() || '');
    }

    function saldoInsuficiente($tr, saldo, cantidad) {
        var qty = cantidad !== undefined ? cantidad : cantidadAValidarFila($tr);
        return qty > 0
            && saldo !== null
            && saldo !== undefined
            && !Number.isNaN(Number(saldo))
            && Number(saldo) + 1e-9 < qty;
    }

    function mostrarSaldoFila($tr, saldo, controlaStock, esError) {
        var $span = $tr.find('.ms-saldo-origen');
        if (!$span.length) {
            return;
        }

        $tr.attr('data-saldo-origen', saldo === null || saldo === undefined ? '' : String(saldo));
        $tr.attr('data-controla-stock', controlaStock === false ? '0' : '1');

        if (controlaStock === false) {
            $span.text('N/A').removeClass('text-danger text-success font-weight-bold').addClass('text-muted');
            $tr.removeClass('fila-saldo-insuficiente');
            return;
        }

        if (saldo === null || saldo === undefined || esError) {
            $span.text('\u2014').removeClass('text-danger text-success font-weight-bold').addClass('text-muted');
            $tr.removeClass('fila-saldo-insuficiente');
            return;
        }

        var insuficiente = saldoInsuficiente($tr, saldo);
        $tr.toggleClass('fila-saldo-insuficiente', insuficiente);

        $span.text(fmtSaldo(saldo))
            .removeClass('text-muted text-danger text-success font-weight-bold');

        if (Number(saldo) < 0 || insuficiente) {
            $span.addClass('text-danger font-weight-bold');
        } else {
            $span.addClass('text-success font-weight-bold');
        }
    }

    function mensajeSaldoInsuficiente($tr, saldo, cantidad) {
        var qty = cantidad !== undefined ? cantidad : cantidadAValidarFila($tr);
        return 'Saldo insuficiente para '
            + (skuFila($tr) || 'el art\u00edculo')
            + '. Saldo: ' + fmtSaldo(saldo)
            + ', requerido: ' + fmtSaldo(qty)
            + '. Cambie el dep\u00f3sito origen o la cantidad antes de grabar.';
    }

    function avisarSaldoInsuficiente($tr, saldo, forzar) {
        if (!saldoInsuficiente($tr, saldo)) {
            avisosSaldoPendientes[$tr.data('linea-id')] = false;
            return;
        }
        var key = String($tr.data('linea-id') || $tr.index());
        if (!forzar && avisosSaldoPendientes[key]) {
            return;
        }
        avisosSaldoPendientes[key] = true;
        alert(mensajeSaldoInsuficiente($tr, saldo));
    }

    function cargarSaldoFila($tr, opciones) {
        var opts = opciones || {};
        var saldoUrl = saldoOrigenUrl();
        if (!saldoUrl || !$tr.length) {
            return;
        }

        var depId = depositoIdFila($tr);
        var articuloId = articuloIdFila($tr);

        if (depId <= 0 || articuloId <= 0) {
            mostrarSaldoFila($tr, null, true);
            return;
        }

        $.get(saldoUrl, {
            articulo_id: articuloId,
            deposito_id: depId,
        }).done(function (data) {
            if (!data || data.ok === false) {
                mostrarSaldoFila($tr, null, true, true);
                return;
            }
            var controla = data.controla_stock !== false;
            var saldo = controla ? (data.saldo !== undefined ? data.saldo : 0) : null;
            mostrarSaldoFila($tr, saldo, controla);
            if (opts.avisar && controla) {
                avisarSaldoInsuficiente($tr, saldo, !!opts.forzarAviso);
            }
        }).fail(function () {
            mostrarSaldoFila($tr, null, true, true);
        });
    }

    function programarSaldoFila($tr, opciones) {
        var key = String($tr.data('linea-id') || $tr.index());
        clearTimeout(timersSaldo[key]);
        timersSaldo[key] = setTimeout(function () {
            cargarSaldoFila($tr, opciones);
        }, 200);
    }

    function refrescarSaldosOrigen(opciones) {
        $('#tabla-lineas-cumple tbody tr.fila-cumple-linea').each(function () {
            programarSaldoFila($(this), opciones);
        });
    }

    function validarSaldosAntesDeGrabar() {
        var problemas = [];
        var demanda = {};

        $('#tabla-lineas-cumple tbody tr.fila-cumple-linea').each(function () {
            var $tr = $(this);
            if ($tr.attr('data-controla-stock') === '0') {
                return;
            }
            var entrega = cantidadEntregaFila($tr);
            if (entrega <= 0) {
                return;
            }
            var articuloId = articuloIdFila($tr);
            var depositoId = depositoIdFila($tr);
            if (articuloId <= 0 || depositoId <= 0) {
                return;
            }
            var clave = articuloId + ':' + depositoId;
            if (!demanda[clave]) {
                demanda[clave] = {
                    articuloId: articuloId,
                    depositoId: depositoId,
                    sku: skuFila($tr),
                    cantidad: 0,
                    saldo: $tr.attr('data-saldo-origen'),
                    $tr: $tr,
                };
            }
            demanda[clave].cantidad += entrega;
            if (demanda[clave].saldo === '' || demanda[clave].saldo === undefined) {
                demanda[clave].saldo = $tr.attr('data-saldo-origen');
            }
        });

        Object.keys(demanda).forEach(function (clave) {
            var item = demanda[clave];
            if (item.saldo === '' || item.saldo === undefined || item.saldo === null) {
                problemas.push(
                    (item.sku || 'Art\u00edculo')
                    + ': no se pudo verificar el saldo del dep\u00f3sito origen. Espere a que cargue o revise el dep\u00f3sito.'
                );
                return;
            }
            var saldo = Number(item.saldo);
            if (Number.isNaN(saldo) || saldo + 1e-9 < item.cantidad) {
                problemas.push(
                    (item.sku || 'Art\u00edculo')
                    + ': saldo ' + fmtSaldo(saldo)
                    + ', solicitado ' + fmtSaldo(item.cantidad)
                );
                item.$tr.addClass('fila-saldo-insuficiente');
            }
        });

        if (problemas.length === 0) {
            return true;
        }

        alert(
            'No se puede grabar: hay l\u00edneas con saldo insuficiente en el dep\u00f3sito origen.\n\n'
            + problemas.join('\n')
        );
        return false;
    }

    window.crsRefrescarSaldosOrigen = refrescarSaldosOrigen;
    window.crsValidarSaldosAntesDeGrabar = validarSaldosAntesDeGrabar;
    window.crsCargarSaldoFila = function ($tr, avisar) {
        programarSaldoFila($tr, { avisar: !!avisar, forzarAviso: !!avisar });
    };

    $(document).on('change input', '#tabla-lineas-cumple .deposito_id', function () {
        var $tr = $(this).closest('tr.fila-cumple-linea');
        avisosSaldoPendientes[$tr.data('linea-id')] = false;
        programarSaldoFila($tr, { avisar: true, forzarAviso: true });
    });

    $(document).on('blur', '#tabla-lineas-cumple .codigodeposito', function () {
        var $tr = $(this).closest('tr.fila-cumple-linea');
        avisosSaldoPendientes[$tr.data('linea-id')] = false;
        programarSaldoFila($tr, { avisar: true, forzarAviso: true });
    });

    $(document).on('input change', '#tabla-lineas-cumple .input-cantidad-entrega', function () {
        var $tr = $(this).closest('tr.fila-cumple-linea');
        var saldoAttr = $tr.attr('data-saldo-origen');
        if ($tr.attr('data-controla-stock') === '0') {
            $tr.removeClass('fila-saldo-insuficiente');
            return;
        }
        if (saldoAttr === '' || saldoAttr === undefined) {
            programarSaldoFila($tr);
            return;
        }
        var saldo = Number(saldoAttr);
        var insuficiente = saldoInsuficiente($tr, saldo, cantidadEntregaFila($tr) || pendienteFila($tr));
        $tr.toggleClass('fila-saldo-insuficiente', insuficiente);
        var $span = $tr.find('.ms-saldo-origen');
        $span.removeClass('text-muted text-danger text-success font-weight-bold');
        if (insuficiente || saldo < 0) {
            $span.addClass('text-danger font-weight-bold');
        } else {
            $span.addClass('text-success font-weight-bold');
        }
    });

    $(function () {
        var prevOnDeposito = window.onDepositoAplicadoEnFormulario;
        window.onDepositoAplicadoEnFormulario = function (data, $ctx) {
            if (typeof prevOnDeposito === 'function') {
                prevOnDeposito(data, $ctx);
            }
            var $tr = $ctx && $ctx.closest
                ? $ctx.closest('tr.fila-cumple-linea')
                : ($ctx && $ctx.length ? $ctx.closest('tr.fila-cumple-linea') : $());
            if ($tr && $tr.length) {
                avisosSaldoPendientes[$tr.data('linea-id')] = false;
                programarSaldoFila($tr, { avisar: true, forzarAviso: true });
                return;
            }
            refrescarSaldosOrigen({ avisar: true });
        };

        refrescarSaldosOrigen({ avisar: true });
    });
}(jQuery));
