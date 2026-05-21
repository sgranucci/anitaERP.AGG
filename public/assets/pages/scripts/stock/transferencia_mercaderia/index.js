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
        if (depositoSalidaId()) {
            cargarInventario();
        } else {
            $('#tm_lista').empty();
            $('#tm_panel_filtro').hide();
            setEstado('');
            actualizarBotonTransferir();
        }
    }

    function tipotransaccionId() {
        return parseInt($('#tipotransaccion_id').val(), 10) || 0;
    }

    function guardarPreferencias() {
        if (!window.TM_URLS.preferencias) {
            return;
        }
        $.post(window.TM_URLS.preferencias, {
            _token: $('meta[name="csrf-token"]').attr('content'),
            deposito_salida_id: depositoSalidaId() || '',
            deposito_entrada_id: depositoEntradaId() || '',
            tipotransaccion_id: tipotransaccionId() || '',
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
        $btn.text('Transferir (' + n + ')');
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

    function renderLista(data) {
        filas = data || [];
        var $lista = $('#tm_lista').empty();

        if (!filas.length) {
            $lista.html(
                '<div class="tm-vacio">No hay artículos con saldo en este depósito ' +
                    '(o ninguno tiene depósito de entrega igual al de salida).</div>'
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

            if (articuloId && window.TM_URLS.articuloEditar) {
                $top.append(
                    $('<a class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener"/>')
                        .attr('href', window.TM_URLS.articuloEditar + articuloId + '/editar')
                        .attr('title', 'Ver artículo')
                        .html('<i class="fa fa-external-link"></i>')
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
                .attr('max', saldo)
                .prop('disabled', sinErp)
                .attr('placeholder', '0');
            var $btnTodo = $('<button type="button" class="btn btn-outline-primary btn-sm ml-2"/>')
                .text('Todo')
                .prop('disabled', sinErp)
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
        var tipo = tipotransaccionId();
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
            if (cant > saldo + 0.000001) {
                invalido = true;
            }
        });
        if (invalido) {
            alert('Alguna cantidad supera el saldo disponible.');
            return;
        }

        if (!confirm('¿Confirma la transferencia de ' + lineas.length + ' artículo(s)?')) {
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
                deposito_salida_id: depSal,
                deposito_entrada_id: depEnt,
                tipotransaccion_id: tipo,
                lineas: lineas,
            },
            dataType: 'json',
        })
            .done(function (resp) {
                if (resp.ok) {
                    setEstado(resp.mensaje || 'Transferencia registrada.');
                    alert(resp.mensaje || 'Listo.');
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

        $('#tipotransaccion_id').on('change', guardarPreferencias);

        $('#deposito_salida_id').on('change', function () {
            if ($(this).closest('#tm_deposito_salida').length) {
                notificarCambioDeposito();
            }
        });

        $('#deposito_entrada_id').on('change', guardarPreferencias);

        if (typeof activa_eventos_consultadeposito === 'function') {
            activa_eventos_consultadeposito();
        }

        if (depositoSalidaId()) {
            cargarInventario();
        }
    });
})(jQuery);
