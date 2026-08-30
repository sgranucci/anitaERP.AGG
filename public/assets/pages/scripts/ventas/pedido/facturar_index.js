(function ($) {
    if (typeof window.clienteEsDespacho !== 'function') {
        window.clienteEsDespacho = function (clienteId) {
            var id = parseInt(window.CLIENTE_DESPACHO_ID || 0, 10) || 0;
            return id > 0 && parseInt(clienteId, 10) === id;
        };
    }
    if (typeof window.mensajeClienteDespachoNoFacturable !== 'function') {
        window.mensajeClienteDespachoNoFacturable = function () {
            return 'El cliente DESPACHO no se factura. Use Transferir al despacho.';
        };
    }

    function urlContexto(pedidoId) {
        var base = (typeof carpetaBase !== 'undefined' ? carpetaBase : (window.carpetaBase || '')).replace(/\/$/, '');
        return base + '/ventas/pedido/' + pedidoId + '/contexto-facturacion';
    }

    function escaparAttr(valor) {
        return String(valor == null ? '' : valor)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function hidratarContexto(data) {
        $('#pedido_id').val(data.pedido_id);
        $('#codigopedido').val(data.codigo);
        $('#cliente_id').val(data.cliente_id);
        $('#nombrecliente').val(data.nombrecliente);
        $('#estadopedido').val(data.estadopedido);
        $('#estadocliente').val(data.estadocliente || '');
        $('#descuento').val(data.descuento);
        $('#lugarentrega').val(data.lugarentrega);
        $('#cliente_entrega_id').val(data.cliente_entrega_id);
        $('#cliente_entrega_id_previa').val(data.cliente_entrega_id);
        $('#entrega_nombre').val(data.entrega_nombre || data.lugarentrega);
        $('#totalcajaspedido').val((data.totales && data.totales.caja) || '0');
        $('#totalpiezaspedido').val((data.totales && data.totales.pieza) || '0');
        $('#totalkilospesados').val((data.totales && data.totales.pesada) || '0');

        var $tbody = $('#tbody-tabla').empty();
        $.each(data.items || [], function (_, item) {
            $tbody.append(
                '<tr class="item-pedido">'
                + '<td>'
                + '<input type="hidden" class="ids" value="' + escaparAttr(item.id) + '">'
                + '<input type="hidden" class="estados" value="' + escaparAttr(item.estado) + '">'
                + '</td>'
                + '<td>'
                + '<input type="hidden" class="articulo_id" value="' + escaparAttr(item.articulo_id) + '">'
                + '<input type="hidden" class="codigoarticulo" value="' + escaparAttr(item.sku) + '">'
                + '</td>'
                + '<td><input type="hidden" class="descripcionarticulo" value="' + escaparAttr(item.descripcion) + '"></td>'
                + '<td>'
                + '<select class="unidadmedida_id">'
                + '<option value="' + escaparAttr(item.unidadmedida_id) + '" selected>'
                + escaparAttr(item.unidadmedida)
                + '</option>'
                + '</select>'
                + '</td>'
                + '<td><input type="hidden" class="caja" value="' + escaparAttr(item.caja) + '"></td>'
                + '<td><input type="hidden" class="pieza" value="' + escaparAttr(item.pieza) + '"></td>'
                + '<td><input type="hidden" class="kilo" value="' + escaparAttr(item.kilo) + '"></td>'
                + '<td><input type="hidden" class="pesada" value="' + escaparAttr(item.pesada) + '"></td>'
                + '<td><input type="hidden" class="descuentoventa_id" value="' + escaparAttr(item.descuentoventa_id) + '"></td>'
                + '<td><input type="hidden" class="precio" value="' + escaparAttr(item.precio) + '"></td>'
                + '</tr>'
            );
        });
    }

    function avisarError(msg) {
        if (window.toastr) {
            toastr.error(msg, 'Facturar pedido', { timeOut: 9000, closeButton: true, progressBar: true });
        } else {
            alert(msg);
        }
    }

    function abrirPreviewFactura(pedidoId) {
        if (window.PedidoProcesoOverlay && typeof window.PedidoProcesoOverlay.iniciar === 'function') {
            window.PedidoProcesoOverlay.iniciar(['Preparando preview de factura…'], 'Facturar pedido');
        }

        $.getJSON(urlContexto(pedidoId))
            .done(function (data) {
                if (window.PedidoProcesoOverlay && typeof window.PedidoProcesoOverlay.detener === 'function') {
                    window.PedidoProcesoOverlay.detener();
                }
                if (data && data.error) {
                    avisarError(data.error);
                    return;
                }
                hidratarContexto(data);
                if (typeof generaFactura === 'function') {
                    generaFactura();
                    return;
                }
                avisarError('No se pudo abrir el preview de facturación.');
            })
            .fail(function (xhr) {
                if (window.PedidoProcesoOverlay && typeof window.PedidoProcesoOverlay.detener === 'function') {
                    window.PedidoProcesoOverlay.detener();
                }
                var msg = 'No se pudo preparar la facturación del pedido.';
                if (xhr && xhr.responseJSON && xhr.responseJSON.error) {
                    msg = String(xhr.responseJSON.error);
                }
                avisarError(msg);
            });
    }

    $(function () {
        $(document).on('click', '.btn-facturar-pedido-index', function (e) {
            e.preventDefault();
            var pedidoId = parseInt($(this).data('pedido-id'), 10) || 0;
            if (pedidoId <= 0) {
                return;
            }
            abrirPreviewFactura(pedidoId);
        });
    });
})(jQuery);
