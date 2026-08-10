(function ($) {
    'use strict';

    var cfg = window.cumpleRequisicionSalaConfig || {};
    var timersSaldo = {};
    var cargasPendientes = 0;
    var timerResumenAviso = null;
    var ultimoResumenFirma = '';

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
        return cantidadEntregaFila($tr);
    }

    function skuFila($tr) {
        return $.trim($tr.find('td').eq(1).text() || '');
    }

    function codigoDepositoFila($tr) {
        return $.trim($tr.find('.codigodeposito').val() || '');
    }

    function saldoInsuficiente($tr, saldo, cantidad) {
        var qty = cantidad !== undefined ? cantidad : cantidadAValidarFila($tr);
        return qty > 0
            && saldo !== null
            && saldo !== undefined
            && !Number.isNaN(Number(saldo))
            && Number(saldo) + 1e-9 < qty;
    }

    function saldoMenorAlPendiente($tr, saldo) {
        var pendiente = pendienteFila($tr);
        return pendiente > 0
            && saldo !== null
            && saldo !== undefined
            && !Number.isNaN(Number(saldo))
            && Number(saldo) + 1e-9 < pendiente;
    }

    function limpiarEstadoEntregaFila($tr) {
        $tr.find('.input-estadoparcial').val('');
        $tr.find('.motivo-parcial-label').text('');
        $tr.find('.input-estado-linea').val('');
        $tr.find('.input-fecha-entrega').val('');
        $tr.find('.input-numeroremito').val('');
        $tr.find('.input-nombreresponsable').val('');
    }

    function forzarCantidadCero($tr) {
        var $input = $tr.find('.input-cantidad-entrega');
        if (!$input.length) {
            return;
        }
        $input.val('0');
        limpiarEstadoEntregaFila($tr);
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
            $tr.removeClass('fila-saldo-insuficiente fila-saldo-aviso');
            return;
        }

        if (saldo === null || saldo === undefined || esError) {
            $span.text('\u2014').removeClass('text-danger text-success font-weight-bold').addClass('text-muted');
            $tr.removeClass('fila-saldo-insuficiente fila-saldo-aviso');
            return;
        }

        var entregaInsuf = saldoInsuficiente($tr, saldo);
        var avisoPendiente = !entregaInsuf && saldoMenorAlPendiente($tr, saldo);
        $tr.toggleClass('fila-saldo-insuficiente', entregaInsuf);
        $tr.toggleClass('fila-saldo-aviso', avisoPendiente);

        $span.text(fmtSaldo(saldo))
            .removeClass('text-muted text-danger text-success font-weight-bold text-warning');

        if (Number(saldo) < 0 || entregaInsuf) {
            $span.addClass('text-danger font-weight-bold');
        } else if (avisoPendiente) {
            $span.addClass('text-warning font-weight-bold');
        } else {
            $span.addClass('text-success font-weight-bold');
        }
    }

    function escapeHtml(text) {
        return $('<div/>').text(text || '').html();
    }

    function asegurarBackdropLimpio() {
        // Evita UI "trabada" si un modal de dep\u00f3sito cerr\u00f3 mal.
        window.setTimeout(function () {
            if ($('.modal.show').length) {
                return;
            }
            $('body').removeClass('modal-open');
            $('.modal-backdrop').remove();
        }, 50);
    }

    function ocultarAvisoSaldo() {
        $('#cumple-alerta-aviso').addClass('d-none');
        ultimoResumenFirma = '';
    }

    function mostrarAvisoSaldo(titulo, lineas) {
        var $box = $('#cumple-alerta-aviso');
        if (!$box.length) {
            return;
        }
        var lista = Array.isArray(lineas) ? lineas.filter(Boolean) : [];
        var firma = titulo + '|' + lista.join('|');
        if (firma === ultimoResumenFirma && !$box.hasClass('d-none')) {
            return;
        }
        ultimoResumenFirma = firma;

        $box.find('.cumple-alerta-aviso-titulo').text(titulo || 'Atenci\u00f3n');
        var $ul = $box.find('.cumple-alerta-aviso-lista').empty();
        if (lista.length === 0) {
            $ul.append('<li>Revise los dep\u00f3sitos origen y las cantidades.</li>');
        } else {
            lista.forEach(function (linea) {
                $ul.append('<li>' + escapeHtml(linea) + '</li>');
            });
        }
        $box.removeClass('d-none');
    }

    function mostrarErrorSaldo(titulo, lineas) {
        var $box = $('#cumple-alerta-error');
        if (!$box.length) {
            window.alert((titulo || 'Error') + '\n' + (lineas || []).join('\n'));
            return;
        }
        $box.find('h4').html('<i class="icon fa fa-times"></i> ' + escapeHtml(titulo || 'Error'));
        var $ul = $box.find('ul').empty();
        (lineas || []).forEach(function (linea) {
            $ul.append('<li>' + escapeHtml(linea) + '</li>');
        });
        if (!(lineas || []).length) {
            $ul.append('<li>Revise los datos e intente nuevamente.</li>');
        }
        $box.removeClass('d-none');
        var top = $box.offset() ? $box.offset().top - 80 : 0;
        $('html, body').animate({ scrollTop: Math.max(top, 0) }, 200);
    }

    function recolectarInconsistenciasSaldos(opciones) {
        var opts = opciones || {};
        var soloConEntrega = !!opts.soloConEntrega;
        var problemas = [];
        var demanda = {};

        $('#tabla-lineas-cumple tbody tr.fila-cumple-linea').each(function () {
            var $tr = $(this);
            if ($tr.attr('data-controla-stock') === '0') {
                return;
            }
            var entrega = cantidadEntregaFila($tr);
            var pendiente = pendienteFila($tr);
            var articuloId = articuloIdFila($tr);
            var depositoId = depositoIdFila($tr);
            var saldoAttr = $tr.attr('data-saldo-origen');
            var sku = skuFila($tr) || 'Art\u00edculo';
            var depCod = codigoDepositoFila($tr);

            if (soloConEntrega) {
                if (entrega <= 0 || articuloId <= 0 || depositoId <= 0) {
                    return;
                }
                var clave = articuloId + ':' + depositoId;
                if (!demanda[clave]) {
                    demanda[clave] = {
                        sku: sku,
                        depCod: depCod,
                        cantidad: 0,
                        saldo: saldoAttr,
                        $trs: [],
                    };
                }
                demanda[clave].cantidad += entrega;
                demanda[clave].$trs.push($tr);
                if (demanda[clave].saldo === '' || demanda[clave].saldo === undefined) {
                    demanda[clave].saldo = saldoAttr;
                }
                return;
            }

            // Resumen informativo: saldo vs pendiente (aunque cantidad a\u00fan sea 0).
            if (pendiente <= 0 || articuloId <= 0 || depositoId <= 0) {
                return;
            }
            if (saldoAttr === '' || saldoAttr === undefined) {
                return;
            }
            var saldo = Number(saldoAttr);
            if (Number.isNaN(saldo)) {
                return;
            }
            if (saldo + 1e-9 < pendiente) {
                problemas.push(
                    sku
                    + ' (dep. '
                    + (depCod || depositoId)
                    + '): saldo '
                    + fmtSaldo(saldo)
                    + ', pendiente '
                    + fmtSaldo(pendiente)
                );
            }
        });

        if (soloConEntrega) {
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
                        + ' (dep. '
                        + (item.depCod || '')
                        + '): saldo '
                        + fmtSaldo(saldo)
                        + ', solicitado '
                        + fmtSaldo(item.cantidad)
                    );
                    item.$trs.forEach(function ($tr) {
                        forzarCantidadCero($tr);
                        if (!Number.isNaN(saldo)) {
                            mostrarSaldoFila($tr, saldo, true);
                        }
                    });
                }
            });
        }

        return problemas;
    }

    function programarResumenAviso() {
        clearTimeout(timerResumenAviso);
        timerResumenAviso = setTimeout(function () {
            if (cargasPendientes > 0) {
                return;
            }
            var problemas = recolectarInconsistenciasSaldos({ soloConEntrega: false });
            if (problemas.length === 0) {
                ocultarAvisoSaldo();
                return;
            }
            mostrarAvisoSaldo(
                'Hay l\u00edneas sin saldo suficiente en el dep\u00f3sito origen. '
                + 'Cambie el dep\u00f3sito o ajuste la cantidad antes de grabar.',
                problemas
            );
        }, 250);
    }

    function inicioCargaSaldo() {
        cargasPendientes += 1;
    }

    function finCargaSaldo() {
        cargasPendientes = Math.max(0, cargasPendientes - 1);
        if (cargasPendientes === 0) {
            programarResumenAviso();
        }
    }

    /**
     * Si la cantidad cargada supera el saldo, la deja en 0 sin alert (no bloquea la UI).
     */
    function ajustarCantidadSiSuperaSaldo($tr, saldo) {
        var entrega = cantidadEntregaFila($tr);
        if (!saldoInsuficiente($tr, saldo, entrega)) {
            return false;
        }
        forzarCantidadCero($tr);
        mostrarSaldoFila($tr, saldo, true);
        return true;
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

        inicioCargaSaldo();
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
            if (opts.ajustarCantidad && controla) {
                ajustarCantidadSiSuperaSaldo($tr, saldo);
            }
        }).fail(function () {
            mostrarSaldoFila($tr, null, true, true);
        }).always(function () {
            finCargaSaldo();
            asegurarBackdropLimpio();
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
        var opts = opciones || {};
        $('#tabla-lineas-cumple tbody tr.fila-cumple-linea').each(function () {
            programarSaldoFila($(this), opts);
        });
    }

    function validarSaldosAntesDeGrabar() {
        var problemas = recolectarInconsistenciasSaldos({ soloConEntrega: true });
        if (problemas.length === 0) {
            ocultarAvisoSaldo();
            return true;
        }

        mostrarErrorSaldo(
            'No se puede grabar: saldo insuficiente en dep\u00f3sito origen',
            ['Se dej\u00f3 cantidad 0 en esas l\u00edneas. Cambie dep\u00f3sito o cantidad y vuelva a grabar.'].concat(problemas)
        );
        programarResumenAviso();
        return false;
    }

    function aplicarDepositoATodasLasLineas($origen) {
        var $src = $origen && $origen.length
            ? $origen.closest('tr.fila-cumple-linea')
            : $('#tabla-lineas-cumple tbody tr.fila-cumple-linea').first();
        if (!$src.length) {
            return false;
        }
        var depId = $src.find('.deposito_id').val();
        var codigo = $src.find('.codigodeposito').val();
        var nombre = $src.find('.descripciondeposito').val();
        if (!depId) {
            mostrarAvisoSaldo(
                'Elija primero un dep\u00f3sito origen en una l\u00ednea.',
                []
            );
            return false;
        }
        $('#tabla-lineas-cumple tbody tr.fila-cumple-linea').each(function () {
            var $tr = $(this);
            $tr.find('.deposito_id').val(depId);
            $tr.find('.codigodeposito').val(codigo);
            $tr.find('.descripciondeposito').val(nombre);
        });
        refrescarSaldosOrigen({ ajustarCantidad: true });
        return true;
    }

    window.crsRefrescarSaldosOrigen = refrescarSaldosOrigen;
    window.crsValidarSaldosAntesDeGrabar = validarSaldosAntesDeGrabar;
    window.crsAplicarDepositoATodas = aplicarDepositoATodasLasLineas;
    window.crsMostrarErrorSaldo = mostrarErrorSaldo;
    window.crsOcultarAvisoSaldo = ocultarAvisoSaldo;
    window.crsCargarSaldoFila = function ($tr, ajustarCantidad) {
        programarSaldoFila($tr, { ajustarCantidad: !!ajustarCantidad });
    };

    $(document).on('change input', '#tabla-lineas-cumple .deposito_id', function () {
        var $tr = $(this).closest('tr.fila-cumple-linea');
        programarSaldoFila($tr, { ajustarCantidad: true });
    });

    $(document).on('blur', '#tabla-lineas-cumple .codigodeposito', function () {
        var $tr = $(this).closest('tr.fila-cumple-linea');
        programarSaldoFila($tr, { ajustarCantidad: true });
    });

    $(document).on('input change', '#tabla-lineas-cumple .input-cantidad-entrega', function () {
        var $tr = $(this).closest('tr.fila-cumple-linea');
        var saldoAttr = $tr.attr('data-saldo-origen');
        if ($tr.attr('data-controla-stock') === '0') {
            $tr.removeClass('fila-saldo-insuficiente fila-saldo-aviso');
            return;
        }
        if (saldoAttr === '' || saldoAttr === undefined) {
            programarSaldoFila($tr);
            return;
        }
        var saldo = Number(saldoAttr);
        mostrarSaldoFila($tr, saldo, true);
    });

    $(document).on('click', '#btn-crs-aplicar-deposito-todas', function () {
        aplicarDepositoATodasLasLineas();
    });

    $(function () {
        var prevOnDeposito = window.onDepositoAplicadoEnFormulario;
        window.onDepositoAplicadoEnFormulario = function (data, $ctx) {
            if (typeof prevOnDeposito === 'function') {
                prevOnDeposito(data, $ctx);
            }
            asegurarBackdropLimpio();
            var $tr = $ctx && $ctx.closest
                ? $ctx.closest('tr.fila-cumple-linea')
                : ($ctx && $ctx.length ? $ctx.closest('tr.fila-cumple-linea') : $());
            if ($tr && $tr.length) {
                programarSaldoFila($tr, { ajustarCantidad: true });
                return;
            }
            refrescarSaldosOrigen({ ajustarCantidad: true });
        };

        // Solo pinta saldos y un aviso consolidado; no alerta por \u00edtem.
        refrescarSaldosOrigen({ ajustarCantidad: false });
    });
}(jQuery));
