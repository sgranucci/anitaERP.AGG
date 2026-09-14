(function ($) {
    'use strict';

    var pedido_combinacion_ids = [];
    var ordentrabajo_ids = [];
    var nombrecliente = '';
    var descuentoCliente = 0;
    var offFactura = 0;

    function idsSeleccionados() {
        var ids = [];
        $('#tabla-picking-pedido .check-picking-linea:checked').each(function () {
            ids.push(parseInt($(this).val(), 10) || 0);
        });
        return ids.filter(function (id) { return id > 0; });
    }

    function mostrarOverlay(titulo) {
        var overlay = document.getElementById('picking-factura-overlay');
        if (!overlay) return;
        if (titulo) {
            var t = document.getElementById('picking-factura-titulo');
            if (t) t.textContent = titulo;
        }
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }

    function ocultarOverlay() {
        var overlay = document.getElementById('picking-factura-overlay');
        if (!overlay) return;
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }

    function leePuntoVenta(puntoventa_id) {
        if (!puntoventa_id) return;
        $.get(carpetaBase + '/ventas/chequeapuntoventa/' + puntoventa_id, function (data) {
            if (data.modofacturacion == 'E') {
                $('#div_formapago, #div_mercaderia, #div_incoterm, #div_leyendaexportacion').show();
            } else {
                $('#div_formapago, #div_mercaderia, #div_incoterm, #div_leyendaexportacion').hide();
            }
        });
    }

    function cargarSelectsModal(modal) {
        var datos = document.querySelector('#datosfactura');
        if (!datos) return;

        var sel_puntoventa = JSON.parse(datos.dataset.puntoventa || '[]');
        var sel_tipotransaccion = JSON.parse(datos.dataset.tipotransaccion || '[]');
        var puntoVentaDefault = $('#puntoventadefault_id').val();
        var puntoVentaRemitoDefault = $('#puntoventaremitodefault_id').val();
        var tipoTransaccionDefault = $('#tipotransacciondefault_id').val();

        var selectTipoTransaccion = modal.find('#tipotransaccion_id');
        selectTipoTransaccion.empty();
        selectTipoTransaccion.append('<option value="">-- Seleccionar tipo de transacción --</option>');
        $.each(sel_tipotransaccion, function (obj, item) {
            var op = (tipoTransaccionDefault == item.id) ? ' selected="selected"' : '';
            selectTipoTransaccion.append('<option value="' + item.id + '"' + op + '>' + item.abreviatura + '-' + item.nombre + '</option>');
        });

        var selectPuntoVenta = modal.find('#puntoventa_id');
        selectPuntoVenta.empty();
        selectPuntoVenta.append('<option value="">-- Seleccionar punto de venta --</option>');
        $.each(sel_puntoventa, function (obj, item) {
            var op = (puntoVentaDefault == item.id) ? ' selected="selected"' : '';
            selectPuntoVenta.append('<option value="' + item.id + '"' + op + '>' + item.codigo + '-' + item.nombre + '</option>');
        });

        var selectPuntoVentaRemito = modal.find('#puntoventaremito_id');
        selectPuntoVentaRemito.empty();
        selectPuntoVentaRemito.append('<option value="">-- Seleccionar punto de venta --</option>');
        $.each(sel_puntoventa, function (obj, item) {
            var op = (puntoVentaRemitoDefault == item.id) ? ' selected="selected"' : '';
            selectPuntoVentaRemito.append('<option value="' + item.id + '"' + op + '>' + item.codigo + '-' + item.nombre + '</option>');
        });

        if (datos.dataset.incoterm) {
            var sel_incoterm = JSON.parse(datos.dataset.incoterm || '[]');
            var selectIncoterm = modal.find('#incoterm_id');
            selectIncoterm.empty();
            selectIncoterm.append('<option value="">-- Seleccionar incoterm --</option>');
            $.each(sel_incoterm, function (obj, item) {
                selectIncoterm.append('<option value="' + item.id + '">' + item.nombre + '</option>');
            });
        }

        if (datos.dataset.formapago) {
            var sel_formapago = JSON.parse(datos.dataset.formapago || '[]');
            var selectFormapago = modal.find('#formapago_id');
            selectFormapago.empty();
            selectFormapago.append('<option value="">-- Seleccionar forma de pago --</option>');
            $.each(sel_formapago, function (obj, item) {
                selectFormapago.append('<option value="' + item.id + '">' + item.nombre + '</option>');
            });
        }

        if (datos.dataset.transporte) {
            var sel_transporte = JSON.parse(datos.dataset.transporte || '[]');
            var selectTransporte = modal.find('#transporte_id');
            if (selectTransporte.length) {
                selectTransporte.empty();
                selectTransporte.append('<option value="">-- Seleccionar transporte --</option>');
                $.each(sel_transporte, function (obj, item) {
                    selectTransporte.append('<option value="' + item.id + '">' + item.nombre + '</option>');
                });
            }
        }

        leePuntoVenta(puntoVentaDefault);
    }

    $(function () {
        $('#check-all-picking').on('change', function () {
            $('.check-picking-linea').prop('checked', $(this).is(':checked'));
        });

        $('#btn-facturar-picking').on('click', function () {
            var ids = idsSeleccionados();
            if (!ids.length) {
                alert('Seleccione al menos una línea');
                return;
            }

            var token = $('#csrf_token').val();
            $.post(carpetaBase + '/stock/picking-pedido/payload-factura', {
                pedido_combinacion_id: ids,
                _token: token
            })
                .done(function (data) {
                    if (data.error) {
                        alert(data.error);
                        return;
                    }
                    pedido_combinacion_ids = data.pedido_combinacion_ids || [];
                    ordentrabajo_ids = data.ordentrabajo_ids || [];
                    nombrecliente = data.nombrecliente || '';
                    descuentoCliente = 0;
                    offFactura = pedido_combinacion_ids.length;
                    $('#facturarOrdenTrabajoModal').modal('show');
                })
                .fail(function (xhr) {
                    var msg = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'No se pudo armar la factura';
                    alert(msg);
                });
        });

        $(document).on('shown.bs.modal', '#facturarOrdenTrabajoModal', function () {
            var modal = $(this);
            var hoy = new Date();
            modal.find('#fechafactura').val(hoy.toISOString().substring(0, 10));
            modal.find('#nombrecliente').val(nombrecliente);
            modal.find('.modal-title').text('Factura PICKING — ' + nombrecliente);
            modal.find('#descuentopie').val(descuentoCliente);
            modal.find('#facturarMedidasModal').empty();
            modal.find('#facturartotpares').val('');
            cargarSelectsModal(modal);
            alert('Va a facturar ' + offFactura + ' ítems de picking');
        });

        $('#cierraFacturarOrdenTrabajoModal').on('click', function () {
            $('#facturarOrdenTrabajoModal').modal('hide');
        });

        $('#aceptaFacturarOrdenTrabajoModal').on('click', function () {
            var token = $('#csrf_token').val();
            var puntoventa_id = $('#puntoventa_id').val();
            var tipotransaccion_id = $('#tipotransaccion_id').val();
            var descuentopie = $('#descuentopie').val();
            var descuentoimportepie = $('#descuentoimportepie').val();
            var descuentolinea = $('#descuentolinea').val();
            var fechafactura = $('#fechafactura').val();
            var leyendafactura = $('#leyendafactura').val();
            var cantidadbulto = $('#cantidadbulto').val();
            var puntoventaremito_id = $('#puntoventaremito_id').val();
            var formapago_id = $('#formapago_id').val();
            var incoterm_id = $('#incoterm_id').val();
            var mercaderia = $('#mercaderia').val();
            var leyendaexportacion = $('#leyendaexportacion').val();
            var transporte_id = $('#transporte_id').val();

            if (cantidadbulto < 1 || cantidadbulto > 999999) {
                alert('No permite facturar sin cargar bultos');
                return false;
            }

            $('#facturarOrdenTrabajoModal').modal('hide');
            mostrarOverlay('Emitiendo factura…');

            $.post(carpetaBase + '/ventas/facturarItemOt', {
                origen: 'picking',
                pedido_combinacion_id: pedido_combinacion_ids,
                ordentrabajo_id: ordentrabajo_ids,
                tipotransaccion_id: tipotransaccion_id,
                puntoventa_id: puntoventa_id,
                fechafactura: fechafactura,
                descuentopie: descuentopie,
                descuentoimportepie: descuentoimportepie,
                descuentolinea: descuentolinea,
                leyendafactura: leyendafactura,
                cantidadbulto: cantidadbulto,
                puntoventaremito_id: puntoventaremito_id,
                formapago_id: formapago_id,
                incoterm_id: incoterm_id,
                mercaderia: mercaderia,
                leyendaexportacion: leyendaexportacion,
                transporte_id: transporte_id,
                _token: token
            })
                .done(function (data, status) {
                    ocultarOverlay();
                    if (data.error != '') {
                        alert(data.error);
                    } else {
                        alert('Factura Número: ' + data.factura + '\nEstado: ' + status);
                        window.location.reload();
                    }
                })
                .fail(function (xhr) {
                    ocultarOverlay();
                    alert((xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'Error al facturar');
                });
        });

        $('#puntoventa_id').on('change', function () {
            leePuntoVenta($(this).val());
        });

        window.addEventListener('pageshow', ocultarOverlay);
    });
})(jQuery);
