(function ($) {
    'use strict';

    var $filaPickingLoteActiva = null;
    var debounceBusqueda = null;

    function esTeclaF1(e) {
        return e && (e.key === 'F1' || e.code === 'F1' || e.keyCode === 112);
    }

    function textoOpcionSeleccionada($select) {
        if (!$select || !$select.length) {
            return '';
        }
        var $opt = $select.find('option:selected');
        if (!$opt.length || !$opt.val()) {
            return '';
        }
        return String($opt.text() || '').replace(/\s+/g, ' ').trim();
    }

    function articuloCombinacionModuloDeFila($tr) {
        var cantRaw = String($tr.find('.cantidad').val() || '').replace(/\./g, '').replace(',', '.');
        return {
            articuloId: parseInt($tr.find('.articulo').val(), 10) || 0,
            combinacionId: parseInt($tr.find('.combinacion').val(), 10) || 0,
            moduloId: parseInt($tr.find('.modulo').val(), 10) || 0,
            articuloTxt: textoOpcionSeleccionada($tr.find('.articulo')),
            combinacionTxt: textoOpcionSeleccionada($tr.find('.combinacion')),
            moduloTxt: textoOpcionSeleccionada($tr.find('.modulo')),
            paresLinea: parseFloat(cantRaw) || 0,
        };
    }

    function formatearPares(n) {
        return Number(n || 0).toLocaleString('es-AR', { maximumFractionDigits: 0 });
    }

    function escaparHtml(texto) {
        return String(texto == null ? '' : texto)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function badgePares(label, valor) {
        if (!(valor > 0)) {
            return '';
        }
        return '<span class="clsp-badge">' +
            '<span class="clsp-badge-label">' + escaparHtml(label) + '</span>' +
            '<span class="clsp-badge-num">' + escaparHtml(formatearPares(valor)) + '</span>' +
            '</span>';
    }

    function pintarContexto(html) {
        $('#consultalotesstockpicking-contexto').html(html || '');
    }

    function armarHtmlContexto(ctx, data) {
        var sku = (data && data.articulo_sku) ? String(data.articulo_sku).trim() : '';
        var desc = (data && data.articulo_descripcion) ? String(data.articulo_descripcion).trim() : '';
        var articuloTitulo = '';
        if (desc) {
            articuloTitulo = desc;
        } else if (ctx.articuloTxt) {
            articuloTitulo = ctx.articuloTxt;
        } else if (ctx.articuloId > 0) {
            articuloTitulo = 'Artículo #' + ctx.articuloId;
        }
        if (!sku && ctx.articuloTxt && articuloTitulo === ctx.articuloTxt) {
            // Si vino "descripcion-sku" desde el select, dejarlo como título.
            articuloTitulo = ctx.articuloTxt;
        }

        var combTxt = (data && data.combinacion_nombre)
            ? String(data.combinacion_nombre).trim()
            : (ctx.combinacionTxt || (ctx.combinacionId > 0 ? ('Combinación #' + ctx.combinacionId) : ''));

        var soloModulo = $('#consultalotesstockpicking_solo_modulo').is(':checked');
        var modTxt = '';
        var paresModulo = null;
        if (soloModulo && ctx.moduloId > 0) {
            modTxt = (data && data.modulo_nombre)
                ? String(data.modulo_nombre).trim()
                : (ctx.moduloTxt || ('Módulo #' + ctx.moduloId));
            paresModulo = (data && data.pares_modulo != null) ? Number(data.pares_modulo) : null;
        }

        var meta = [];
        if (combTxt) {
            meta.push(escaparHtml(combTxt));
        }
        if (soloModulo && modTxt) {
            meta.push('Módulo ' + escaparHtml(modTxt));
        } else if (!soloModulo) {
            meta.push('Todos los módulos');
        }

        var badges = '';
        if (paresModulo != null && !isNaN(paresModulo) && paresModulo > 0) {
            badges += badgePares('Pares módulo', paresModulo);
        }
        if (ctx.paresLinea > 0) {
            badges += badgePares('Pares línea', ctx.paresLinea);
        }

        var articuloHtml = '';
        if (sku && desc) {
            articuloHtml = '<span class="clsp-sku">' + escaparHtml(sku) + '</span> — ' + escaparHtml(desc);
        } else if (articuloTitulo) {
            articuloHtml = escaparHtml(articuloTitulo);
        } else {
            articuloHtml = 'Artículo';
        }

        return '<div class="clsp-banner">' +
            '<div class="clsp-articulo">' + articuloHtml + '</div>' +
            (meta.length ? '<div class="clsp-meta">' + meta.join(' · ') + '</div>' : '') +
            (badges ? '<div class="clsp-pares">' + badges + '</div>' : '') +
            '</div>';
    }

    function esModuloAbierto(ctx) {
        if (!ctx) {
            return false;
        }
        var txt = String(ctx.moduloTxt || '').toLowerCase();
        if (txt.indexOf('abierto') !== -1) {
            return true;
        }
        // En Ferli el módulo Abierto se agrega siempre con id 30 en el select.
        return parseInt(ctx.moduloId, 10) === 30;
    }

    function renderFilas(filas, opts) {
        opts = opts || {};
        var $tbody = $('#datoslotesstockpicking');
        $tbody.empty();
        if (!filas || !filas.length) {
            var hint = opts.soloModulo
                ? 'Sin lotes/OT con saldo para el módulo de la línea. Desmarcá «Solo módulo de la línea» para ver stock de otros módulos.'
                : 'Sin lotes/OT con saldo pendiente';
            $tbody.append(
                '<tr><td colspan="5" class="text-center text-muted">' + hint + '</td></tr>'
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
            $tr.append($('<td class="text-right"/>').html(
                '<strong style="font-size:1.1rem;color:#6E2C00;">' + escaparHtml(saldoTxt) + '</strong>'
            ));
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
            pintarContexto('<p class="clsp-error mb-0">Seleccione artículo y combinación en la línea.</p>');
            return;
        }

        var soloModulo = $('#consultalotesstockpicking_solo_modulo').is(':checked');
        var token = $('#csrf_token').val();
        pintarContexto(armarHtmlContexto(ctx, null));
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
                    renderFilas([], { soloModulo: soloModulo });
                    pintarContexto('<p class="clsp-error mb-0">' + escaparHtml(data.error) + '</p>');
                    return;
                }
                pintarContexto(armarHtmlContexto(ctx, data));
                renderFilas(data.filas || [], { soloModulo: soloModulo });
            })
            .fail(function (xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.error)
                    ? xhr.responseJSON.error
                    : 'No se pudo consultar el stock';
                renderFilas([], { soloModulo: soloModulo });
                pintarContexto('<p class="clsp-error mb-0">' + escaparHtml(msg) + '</p>');
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
        // Módulo "Abierto": el stock suele estar en módulos cerrados (ej. 12 D).
        // No filtrar por el módulo de la línea o el modal queda vacío.
        $('#consultalotesstockpicking_solo_modulo').prop('checked', !esModuloAbierto(ctx));
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
