(function ($) {
    'use strict';

    var $filaPickingLoteActiva = null;
    var debounceBusqueda = null;

    function esTeclaF1(e) {
        return e && (e.key === 'F1' || e.code === 'F1' || e.keyCode === 112);
    }

    function articuloCombinacionModuloDeFila($tr) {
        return {
            articuloId: parseInt($tr.find('.articulo').val(), 10) || 0,
            combinacionId: parseInt($tr.find('.combinacion').val(), 10) || 0,
            moduloId: parseInt($tr.find('.modulo').val(), 10) || 0,
        };
    }

    function renderFilas(filas) {
        var $tbody = $('#datoslotesstockpicking');
        $tbody.empty();
        if (!filas || !filas.length) {
            $tbody.append(
                '<tr><td colspan="5" class="text-center text-muted">Sin lotes/OT con saldo pendiente</td></tr>'
            );
            return;
        }
        $.each(filas, function (_i, fila) {
            var saldo = Number(fila.saldo || 0);
            var saldoTxt = saldo.toLocaleString('es-AR', { maximumFractionDigits: 0 });
            var $tr = $('<tr/>');
            $tr.append($('<td/>').text(fila.lote || ''));
            $tr.append($('<td/>').text(fila.modulo || (fila.modulo_id ? ('#' + fila.modulo_id) : '')));
            $tr.append($('<td/>').text(fila.deposito || ''));
            $tr.append($('<td class="text-right"/>').text(saldoTxt));
            var $btn = $('<button type="button" class="btn btn-warning btn-sm eligeconsultalotesstockpicking">Elegir</button>');
            $btn.attr('data-lote', fila.lote || '');
            $btn.attr('data-deposito-id', parseInt(fila.deposito_id, 10) || 0);
            $tr.append($('<td class="text-nowrap"/>').append($btn));
            $tbody.append($tr);
        });
    }

    function buscarLotesStock(texto) {
        if (!$filaPickingLoteActiva || !$filaPickingLoteActiva.length) {
            return;
        }
        var ctx = articuloCombinacionModuloDeFila($filaPickingLoteActiva);
        if (ctx.articuloId <= 0 || ctx.combinacionId <= 0) {
            renderFilas([]);
            $('#consultalotesstockpicking-contexto').text('Seleccione artículo y combinación en la línea.');
            return;
        }

        var soloModulo = $('#consultalotesstockpicking_solo_modulo').is(':checked');
        var token = $('#csrf_token').val();
        $('#consultalotesstockpicking-contexto').text(
            'Artículo #' + ctx.articuloId +
            ' · Combinación #' + ctx.combinacionId +
            (soloModulo && ctx.moduloId > 0 ? (' · Módulo #' + ctx.moduloId) : ' · Todos los módulos')
        );
        $('#datoslotesstockpicking').html(
            '<tr><td colspan="5" class="text-center text-muted"><i class="fa fa-spinner fa-spin"></i> Buscando…</td></tr>'
        );

        $.post(carpetaBase + '/stock/picking-pedido/consulta-lotes-stock', {
            articulo_id: ctx.articuloId,
            combinacion_id: ctx.combinacionId,
            modulo_id: ctx.moduloId,
            solo_modulo_linea: soloModulo ? 1 : 0,
            texto: texto || '',
            _token: token
        })
            .done(function (data) {
                if (data.error) {
                    renderFilas([]);
                    $('#consultalotesstockpicking-contexto').text(data.error);
                    return;
                }
                renderFilas(data.filas || []);
            })
            .fail(function (xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.error)
                    ? xhr.responseJSON.error
                    : 'No se pudo consultar el stock';
                renderFilas([]);
                $('#consultalotesstockpicking-contexto').text(msg);
            });
    }

    function abrirModalConsultaLotes($tr) {
        $filaPickingLoteActiva = $tr;
        var ctx = articuloCombinacionModuloDeFila($tr);
        if (ctx.articuloId <= 0 || ctx.combinacionId <= 0) {
            if (typeof pickingAviso === 'function') {
                pickingAviso('Seleccione artículo y combinación antes de consultar stock', 'error');
            } else {
                alert('Seleccione artículo y combinación antes de consultar stock');
            }
            return;
        }
        $('#consultalotesstockpicking').val('');
        $('#consultalotesstockpickingModal').modal('show');
        buscarLotesStock('');
    }

    function aplicarLoteElegido(lote, depositoId) {
        if (!$filaPickingLoteActiva || !$filaPickingLoteActiva.length) {
            return;
        }
        var $box = $filaPickingLoteActiva.find('.picking-box');
        if (($box.attr('data-picking-facturado') || 'N') === 'S' || ($box.attr('data-estado') || '') === 'facturado') {
            return;
        }
        if (($box.attr('data-picking-marcado') || 'N') === 'S' || ($box.attr('data-estado') || '') === 'preparado') {
            if (typeof pickingAviso === 'function') {
                pickingAviso('Quite el picking antes de cambiar el lote', 'error');
            }
            return;
        }
        $filaPickingLoteActiva.find('.picking-lote').val(lote || '');
        if (depositoId > 0) {
            $filaPickingLoteActiva.find('.picking-deposito').val(String(depositoId));
        }
        $filaPickingLoteActiva.find('.picking-lote').trigger('focus');
    }

    function manejarF1PickingLote(e) {
        if (!esTeclaF1(e)) {
            return;
        }
        var target = e.target;
        if (!target || !$(target).hasClass('picking-lote')) {
            return;
        }
        if ($(target).prop('readonly') || $(target).prop('disabled')) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        abrirModalConsultaLotes($(target).closest('tr'));
    }

    $(document)
        .off('click.consultaLotesStockPicking', '.consulta-lotes-stock-picking')
        .on('click.consultaLotesStockPicking', '.consulta-lotes-stock-picking', function (e) {
            e.preventDefault();
            var $tr = $(this).closest('tr');
            var $box = $tr.find('.picking-box');
            if (($box.attr('data-picking-facturado') || 'N') === 'S' || ($box.attr('data-estado') || '') === 'facturado') {
                return;
            }
            if (($box.attr('data-picking-marcado') || 'N') === 'S' || ($box.attr('data-estado') || '') === 'preparado') {
                if (typeof pickingAviso === 'function') {
                    pickingAviso('Quite el picking antes de cambiar el lote', 'error');
                }
                return;
            }
            abrirModalConsultaLotes($tr);
        });

    $(document)
        .off('click.eligeLoteStockPicking', '.eligeconsultalotesstockpicking')
        .on('click.eligeLoteStockPicking', '.eligeconsultalotesstockpicking', function () {
            var lote = $(this).attr('data-lote') || '';
            var depositoId = parseInt($(this).attr('data-deposito-id'), 10) || 0;
            aplicarLoteElegido(lote, depositoId);
            $('#consultalotesstockpickingModal').modal('hide');
        });

    $(document)
        .off('keyup.consultaLotesStockPicking', '#consultalotesstockpicking')
        .on('keyup.consultaLotesStockPicking', '#consultalotesstockpicking', function (e) {
            if (e.key === 'Enter' || e.keyCode === 13) {
                e.preventDefault();
                var $btn = $('#datoslotesstockpicking .eligeconsultalotesstockpicking').first();
                if ($btn.length) {
                    $btn.trigger('click');
                }
                return;
            }
            clearTimeout(debounceBusqueda);
            var texto = $(this).val();
            debounceBusqueda = setTimeout(function () {
                buscarLotesStock(texto);
            }, 300);
        });

    $(document)
        .off('keydown.consultaLotesStockPickingEnter', '#consultalotesstockpicking')
        .on('keydown.consultaLotesStockPickingEnter', '#consultalotesstockpicking', function (e) {
            if (e.key === 'Enter' || e.keyCode === 13) {
                e.preventDefault();
            }
        });

    $(document)
        .off('change.consultaLotesStockPickingModulo', '#consultalotesstockpicking_solo_modulo')
        .on('change.consultaLotesStockPickingModulo', '#consultalotesstockpicking_solo_modulo', function () {
            buscarLotesStock($('#consultalotesstockpicking').val());
        });

    $('#consultalotesstockpickingModal')
        .off('shown.bs.modal.consultaLotesStockPicking')
        .on('shown.bs.modal.consultaLotesStockPicking', function () {
            $('#consultalotesstockpicking').trigger('focus');
        })
        .off('hidden.bs.modal.consultaLotesStockPicking')
        .on('hidden.bs.modal.consultaLotesStockPicking', function () {
            if ($filaPickingLoteActiva && $filaPickingLoteActiva.length) {
                $filaPickingLoteActiva.find('.picking-lote').trigger('focus');
            }
        });

    if (!window.__pickingLoteF1CaptureActivo) {
        document.addEventListener('keydown', manejarF1PickingLote, true);
        window.__pickingLoteF1CaptureActivo = true;
    }

    window.abrirModalConsultaLotesStockPicking = abrirModalConsultaLotes;
})(jQuery);
