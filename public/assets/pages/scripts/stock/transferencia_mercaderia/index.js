(function ($) {
    'use strict';

    var filas = [];
    var cargando = false;

    function depositoSalidaId() {
        return parseInt($('#deposito_salida_id').val(), 10) || 0;
    }

    function depositoEntradaId() {
        return parseInt($('#deposito_entrada_id').val(), 10) || 0;
    }

    function notificarCambioDeposito() {
        guardarPreferencias();
        cargarDestinatarios();
        if (depositoSalidaId()) {
            cargarInventario();
        } else {
            $('#tm_lista').empty();
            $('#tm_panel_filtro').hide();
            setEstado('');
            actualizarBotonTransferir();
        }
    }

    function tipotransaccionStockId() {
        return parseInt($('#tipotransaccion_stock_id').val(), 10) || 0;
    }

    function tipoRequiereAprobacion() {
        var $opt = $('#tipotransaccion_stock_id option:selected');
        return $opt.data('requiere-aprobacion') === 1 || $opt.data('requiereAprobacion') === 1;
    }

    function actualizarPanelDestinatario() {
        var show = tipoRequiereAprobacion() && depositoEntradaId() > 0;
        $('#tm_panel_destinatario').toggle(show);
        if (show) {
            cargarDestinatarios();
        }
    }

    function cargarDestinatarios() {
        var dep = depositoEntradaId();
        var $sel = $('#usuario_destino_id');
        if (!dep || !window.TM_URLS.destinatarios) {
            $sel.find('option:not(:first)').remove();
            return;
        }
        $.get(window.TM_URLS.destinatarios, { deposito_entrada_id: dep })
            .done(function (resp) {
                $sel.find('option:not(:first)').remove();
                (resp.opciones || []).forEach(function (o) {
                    var label = o.nombre + (o.principal ? ' (principal)' : '');
                    if (o.email) {
                        label += ' — ' + o.email;
                    }
                    $sel.append($('<option/>').val(o.id).text(label));
                });
            });
    }

    function guardarPreferencias() {
        if (!window.TM_URLS.preferencias) {
            return;
        }
        $.post(window.TM_URLS.preferencias, {
            _token: $('meta[name="csrf-token"]').attr('content'),
            deposito_salida_id: depositoSalidaId() || '',
            deposito_entrada_id: depositoEntradaId() || '',
            tipotransaccion_stock_id: tipotransaccionStockId() || '',
        });
    }

    function setEstado(msg, esError) {
        var $e = $('#tm_estado');
        $e.text(msg || '');
        $e.toggleClass('text-danger', !!esError);
    }

    function lineasConCantidad() {
        var out = [];
        $('#tm_lista .tm-item').each(function () {
            var $row = $(this);
            if ($row.is(':hidden')) {
                return;
            }
            var cant = parseFloat($row.find('.tm-cant').val());
            if (!cant || cant <= 0) {
                return;
            }
            var articuloId = parseInt($row.data('articulo-id'), 10);
            if (!articuloId) {
                return;
            }
            out.push({
                articulo_id: articuloId,
                cantidad: cant,
            });
        });
        return out;
    }

    function actualizarBotonTransferir() {
        var n = lineasConCantidad().length;
        var $btn = $('#tm_btn_transferir');
        $btn.prop('disabled', n === 0 || cargando);
        var label = tipoRequiereAprobacion() ? 'Enviar (' + n + ')' : 'Transferir (' + n + ')';
        $btn.text(label);
    }

    function urlConsultaArticulo(id) {
        if (!window.TM_URLS.articuloConsultaUrl) {
            return '#';
        }
        return String(window.TM_URLS.articuloConsultaUrl).replace('__ID__', String(id));
    }

    function focarFiltroSku() {
        var $filtro = $('#tm_filtro_desc');
        if (!$filtro.length || !$filtro.is(':visible')) {
            return;
        }
        setTimeout(function () {
            $filtro.trigger('focus');
        }, 0);
    }

    function aplicarFiltro() {
        var q = ($('#tm_filtro_desc').val() || '').toLowerCase().trim();
        $('#tm_lista .tm-item').each(function () {
            var $row = $(this);
            if (!q) {
                $row.show();
                return;
            }
            var texto = ($row.data('busqueda') || '').toLowerCase();
            $row.toggle(texto.indexOf(q) !== -1);
        });
        actualizarBotonTransferir();
    }

    function agregarFilaManual(f) {
        var existe = filas.some(function (x) {
            return parseInt(x.articulo_id, 10) === parseInt(f.articulo_id, 10);
        });
        if (!existe) {
            filas.push(f);
        }
        renderLista(filas);
        var $card = $('#tm_lista .tm-item[data-articulo-id="' + f.articulo_id + '"]');
        if ($card.length) {
            $card.find('.tm-cant').trigger('focus');
        }
    }

    function renderLista(data) {
        filas = data || [];
        var $lista = $('#tm_lista').empty();

        if (!filas.length) {
            $lista.html(
                '<div class="tm-vacio">No hay artículos cargados. Use «Cargar stock» o «Agregar artículo».</div>'
            );
            $('#tm_panel_filtro').hide();
            actualizarBotonTransferir();
            return;
        }

        $('#tm_panel_filtro').show();

        filas.forEach(function (f) {
            var articuloId = f.articulo_id;
            var sinErp = !articuloId;
            var sku = f.sku || f.sku_anita || '';
            var desc = f.descripcion || '(Sin descripción en ERP)';
            var saldo = parseFloat(f.saldo) || 0;
            var busqueda = (sku + ' ' + desc).toLowerCase();

            var $card = $('<div class="tm-item"/>')
                .toggleClass('tm-sin-erp', sinErp)
                .attr('data-busqueda', busqueda)
                .attr('data-articulo-id', articuloId || '')
                .attr('data-saldo', saldo);

            var $top = $('<div class="d-flex justify-content-between align-items-start"/>');
            $top.append(
                $('<div class="flex-grow-1 pr-2"/>').append(
                    $('<div class="tm-desc"/>').text(desc),
                    $('<div class="tm-meta"/>').text('SKU: ' + sku)
                )
            );

            if (articuloId && window.TM_URLS.articuloConsultaUrl) {
                $top.append(
                    $('<a class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener"/>')
                        .attr('href', urlConsultaArticulo(articuloId))
                        .attr('title', 'Consultar artículo')
                        .html('<i class="fa fa-edit"></i>')
                );
            }

            $card.append($top);

            var $fila = $('<div class="d-flex justify-content-between align-items-center mt-2"/>');
            $fila.append($('<span class="tm-meta"/>').text('Saldo'));
            $fila.append($('<span class="tm-saldo"/>').text(saldo));
            $card.append($fila);

            var $cantRow = $('<div class="d-flex justify-content-between align-items-center mt-2"/>');
            $cantRow.append($('<span class="tm-meta"/>').text('A transferir'));
            var $input = $('<input type="number" class="form-control tm-cant" min="0" step="any" inputmode="decimal"/>')
                .attr('max', saldo > 0 ? saldo : null)
                .prop('disabled', sinErp)
                .attr('placeholder', '0');
            var $btnTodo = $('<button type="button" class="btn btn-outline-primary btn-sm ml-2"/>')
                .text('Todo')
                .prop('disabled', sinErp || saldo <= 0)
                .on('click', function () {
                    $input.val(saldo).trigger('change');
                });
            $cantRow.append($('<div class="d-flex align-items-center"/>').append($input, $btnTodo));
            $card.append($cantRow);

            if (sinErp) {
                $card.append(
                    $('<div class="text-danger small mt-1"/>').text('Sin artículo en ERP: no se puede transferir.')
                );
            }

            $lista.append($card);
        });

        aplicarFiltro();
        focarFiltroSku();
    }

    function cargarInventario() {
        var dep = depositoSalidaId();
        if (!dep) {
            setEstado('Seleccione depósito de salida.', true);
            return;
        }

        cargando = true;
        $('#tm_btn_cargar').prop('disabled', true);
        setEstado('Consultando stock en Anita…');

        $.ajax({
            url: window.TM_URLS.inventario,
            method: 'GET',
            data: { deposito_salida_id: dep },
            dataType: 'json',
        })
            .done(function (resp) {
                if (!resp.ok) {
                    setEstado(resp.mensaje || 'Error al cargar.', true);
                    renderLista([]);
                    return;
                }
                setEstado(
                    resp.filas.length +
                        ' artículo(s) con saldo (depósito de entrega = depósito de salida).'
                );
                renderLista(resp.filas);
            })
            .fail(function (xhr) {
                var msg = 'Error de comunicación.';
                if (xhr.responseJSON && xhr.responseJSON.mensaje) {
                    msg = xhr.responseJSON.mensaje;
                }
                setEstado(msg, true);
                renderLista([]);
            })
            .always(function () {
                cargando = false;
                $('#tm_btn_cargar').prop('disabled', false);
                actualizarBotonTransferir();
            });
    }

    function grabarTransferencia() {
        var depSal = depositoSalidaId();
        var depEnt = depositoEntradaId();
        var tipo = tipotransaccionStockId();
        var lineas = lineasConCantidad();

        if (!depSal || !depEnt) {
            alert('Seleccione depósito de salida y de entrada.');
            return;
        }
        if (depSal === depEnt) {
            alert('Los depósitos deben ser distintos.');
            return;
        }
        if (!tipo) {
            alert('Seleccione tipo de transacción.');
            return;
        }
        if (!lineas.length) {
            alert('Indique cantidad en al menos un artículo.');
            return;
        }

        var invalido = false;
        $('#tm_lista .tm-item:visible').each(function () {
            var $row = $(this);
            var cant = parseFloat($row.find('.tm-cant').val());
            if (!cant || cant <= 0) {
                return;
            }
            var saldo = parseFloat($row.data('saldo')) || 0;
            if (saldo > 0 && cant > saldo + 0.000001) {
                invalido = true;
            }
        });
        if (invalido) {
            alert('Alguna cantidad supera el saldo disponible.');
            return;
        }

        var msgConfirm = tipoRequiereAprobacion()
            ? '¿Confirma el envío de ' + lineas.length + ' artículo(s)? Quedará pendiente de aprobación.'
            : '¿Confirma la transferencia de ' + lineas.length + ' artículo(s)?';
        if (!confirm(msgConfirm)) {
            return;
        }

        cargando = true;
        actualizarBotonTransferir();
        setEstado('Grabando movimiento de stock…');

        $.ajax({
            url: window.TM_URLS.guardar,
            method: 'POST',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content'),
                empresa_id: parseInt($('#empresa_id').val(), 10) || '',
                deposito_salida_id: depSal,
                deposito_entrada_id: depEnt,
                tipotransaccion_stock_id: tipo,
                usuario_destino_id: parseInt($('#usuario_destino_id').val(), 10) || '',
                lineas: lineas,
            },
            dataType: 'json',
        })
            .done(function (resp) {
                if (resp.ok) {
                    setEstado(resp.mensaje || 'Transferencia registrada.');
                    alert(resp.mensaje || 'Listo.');
                    if (resp.requiere_aprobacion) {
                        window.location.href = $('a[href*="pendientes"]').attr('href') || window.location.href;
                        return;
                    }
                    cargarInventario();
                } else {
                    setEstado(resp.mensaje || 'No se pudo grabar.', true);
                    alert(resp.mensaje || 'Error.');
                }
            })
            .fail(function (xhr) {
                var msg = 'Error al grabar.';
                if (xhr.responseJSON && xhr.responseJSON.mensaje) {
                    msg = xhr.responseJSON.mensaje;
                }
                setEstado(msg, true);
                alert(msg);
            })
            .always(function () {
                cargando = false;
                actualizarBotonTransferir();
            });
    }

    $(function () {
        $('#tm_btn_cargar').on('click', cargarInventario);
        $('#tm_btn_transferir').on('click', grabarTransferencia);
        $('#tm_filtro_desc').on('input', aplicarFiltro);
        $(document).on('input change', '.tm-cant', actualizarBotonTransferir);

        $('#tipotransaccion_stock_id').on('change', function () {
            guardarPreferencias();
            actualizarPanelDestinatario();
            actualizarBotonTransferir();
        });

        $('#deposito_salida_id').on('change', function () {
            notificarCambioDeposito();
        });

        $('#deposito_entrada_id').on('change', function () {
            guardarPreferencias();
            cargarDestinatarios();
            actualizarPanelDestinatario();
        });

        $('#empresa_id').on('change', function () {
            $('.tm-deposito-campo').each(function () {
                $(this).find('.deposito_id').val('').trigger('change');
                $(this).find('.codigodeposito').val('');
                $(this).find('.descripciondeposito').val('');
            });
            $('#tm_lista').empty();
            $('#tm_panel_filtro').hide();
            setEstado('');
            actualizarBotonTransferir();
        });

        $('#tm_btn_agregar_articulo').on('click', function () {
            if (typeof activa_eventos_consultaarticulo === 'function') {
                activa_eventos_consultaarticulo();
            }
            $('#consultaarticuloModal').modal('show');
        });

        window.onArticuloSeleccionado = function (dataArticulo) {
            if (!dataArticulo || !dataArticulo.id) {
                return;
            }
            agregarFilaManual({
                articulo_id: parseInt(dataArticulo.id, 10),
                sku: dataArticulo.sku || '',
                descripcion: dataArticulo.descripcion || '',
                saldo: 0,
            });
        };

        if (typeof activa_eventos_consultadeposito === 'function') {
            activa_eventos_consultadeposito();
        }

        actualizarPanelDestinatario();

        if (depositoSalidaId()) {
            cargarInventario();
        }
    });
})(jQuery);
