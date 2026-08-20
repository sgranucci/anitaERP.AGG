var cuentacajaxcodigo;
var nombrexcodigo;
var codigoxcodigo;
var totalDebeAsiento = 0;
var totalHaberAsiento = 0;
var idMoneda = [];
var descripcionMoneda = [];
var flModificaAsiento = false;

(function ($) {
    'use strict';

    function ocultarForms() {
        $('.form1, .form2, .form3, .form4, .form5, .formasientoexterno').hide();
    }

    function armaSelectMoneda(ptrrenglon) {
        var select = $(ptrrenglon).find('.monedaasiento');
        var moneda_id = $(ptrrenglon).find('.monedaasiento_id_previo').val();
        select.empty();
        select.append('<option value="">-- Seleccionar --</option>');
        idMoneda.forEach(function (moneda) {
            if (String(moneda) !== String(moneda_id)) {
                select.append('<option value="' + moneda + '">' + descripcionMoneda[moneda] + '</option>');
            } else {
                select.append('<option value="' + moneda + '" selected>' + descripcionMoneda[moneda] + '</option>');
            }
        });
    }

    function agregaUnRenglon() {
        var renglon = $('#template-renglon-cuenta').html();
        $('#tbody-cuenta-table').append(renglon);
        actualizaRenglonesCuenta();
        $('#tbody-cuenta-table tr:last').find('.codigo').focus();
        activaEventosCuentas(false);
        flModificaAsiento = true;
    }

    function actualizaRenglonesCuenta() {
        var item = 1;
        $('#tbody-cuenta-table .iicuenta').each(function () {
            $(this).val(item++);
        });
    }

    function sumaMonto() {
        var total = 0;
        $('#tbody-cuenta-table .monto').each(function () {
            total += parseFloat($(this).val()) || 0;
        });
        if (typeof sumaMontosChequesIngresoEgreso === 'function') {
            var extra = sumaMontosChequesIngresoEgreso();
            total += (extra.extraHaber || 0) + (extra.extraDebe || 0);
        }
        $('.totales-pagoproveedor').html(
            '<div class="col-lg-12"><strong>Total medios: </strong>' +
            total.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) +
            '</div>'
        );
    }

    window.sumaMonto = sumaMonto;

    function activaEventosCuentas(flInicio) {
        if (!flInicio) {
            $('.consultacuentacaja').off('click');
            $('.codigo').off('change');
            $('.monto, .cotizacion, .moneda').off('change');
        }

        $('.codigo').on('change', function (event) {
            event.preventDefault();
            var codigo = $(this);
            var codigo_nuevo = codigo.val();
            $.get(carpetaBase + '/caja/cuentacaja/leercuentacajaporcodigo/' + codigo_nuevo, function (data) {
                if (data.id > 0) {
                    codigo.parents('tr').find('.cuentacaja_id').val(data.id);
                    codigo.parents('tr').find('.cuentacaja_id_previa').val(data.id);
                    codigo.parents('tr').find('.nombre').val(data.nombre);
                    codigo.parents('tr').find('.moneda').val(data.moneda_id);
                    flModificaAsiento = true;
                    codigo.parents('tr').find('.monto').focus();
                } else {
                    alert('No existe la cuenta de caja');
                    codigo.parents('tr').remove();
                }
            });
        });

        $('.consultacuentacaja').on('click', function () {
            cuentacajaxcodigo = $(this).parents('tr').find('.cuentacaja_id');
            nombrexcodigo = $(this).parents('tr').find('.nombre');
            codigoxcodigo = $(this).parents('tr').find('.codigo');
            if ($('#empresa_id').val()) {
                $('#consultacuentacajaModal').modal('show');
            } else {
                alert('Debe ingresar empresa');
            }
        });

        $('.monto, .cotizacion, .moneda').on('change', function () {
            sumaMonto();
            flModificaAsiento = true;
        });
    }

    function serializarComprobantes() {
        var datos = [];
        $('#tabla-deuda-proveedor tbody tr').each(function () {
            var $tr = $(this);
            var $chk = $tr.find('.pp-sel-deuda');
            var $monto = $tr.find('.pp-monto-aplicar');
            var monto = parseFloat($monto.val() || '0') || 0;
            if (!$chk.is(':checked') || monto <= 0) {
                return;
            }
            datos.push({
                proveedor_cuentacorriente_ids: $tr.data('cc-id'),
                montos: monto,
                moneda_ids: $monto.data('moneda'),
                cotizaciones: $monto.data('cotizacion'),
                cotizacion_aplicadas: $monto.data('cot-aplicada') || $monto.data('cotizacion'),
                diferencias_cambio: $monto.data('dc') || 0
            });
        });
        return JSON.stringify(datos);
    }

    function serializarRetenciones() {
        var raw = $('#pp-retenciones-json').val();
        if (!raw) {
            return '[]';
        }
        try {
            var res = JSON.parse(raw);
            var out = [];
            ['ganancias', 'iva', 'suss', 'iibb'].forEach(function (k) {
                var r = res[k] || {};
                if (!r.aplica || !(parseFloat(r.importe) > 0)) {
                    return;
                }
                var tipo = { ganancias: 'G', iva: 'I', suss: 'S', iibb: 'B' }[k];
                out.push({
                    tiporetencion: tipo,
                    montos: r.importe,
                    moneda_ids: $('#moneda_id').val() || 1,
                    cotizaciones: $('#cotizacion').val() || 1,
                    provincia_id: r.provincia_id || null
                });
            });
            return JSON.stringify(out);
        } catch (e) {
            return '[]';
        }
    }

    function generaAsientoContable() {
        var datosCuentasCaja = [];
        $('#tbody-cuenta-table tr').each(function () {
            var monto = Math.abs(parseFloat($(this).find('.monto').val()) || 0);
            if (monto <= 0) {
                return;
            }
            datosCuentasCaja.push({
                cuentacaja_ids: $(this).find('.cuentacaja_id').val(),
                moneda_ids: $(this).find('.moneda').val(),
                montos: monto,
                cotizaciones: $(this).find('.cotizacion').val(),
                observaciones: $(this).find('.observacion').val()
            });
        });

        var datosCuentasContables = [];
        if (!flModificaAsiento) {
            $('#cuenta-asiento-table .item-cuenta-asiento').each(function () {
                datosCuentasContables.push({
                    cuentacontable_ids: $(this).find('.cuentacontable_id').val(),
                    centrocostoasiento_ids: $(this).find('.centrocostoasiento').val(),
                    monedaasiento_ids: $(this).find('.monedaasiento').val(),
                    debeasientos: $(this).find('.debeasiento').val(),
                    haberasientos: $(this).find('.haberasiento').val(),
                    cotizacionasientos: $(this).find('.cotizacionasiento').val(),
                    observacionasientos: $(this).find('.observacionasiento').val(),
                    carga_cuentacontable_manuales: $(this).find('.carga_cuentacontable_manual').val()
                });
            });
        }

        var wrapper = '#tbody-cuenta-asiento-table';
        $.ajax({
            type: 'POST',
            url: carpetaBase + '/compras/pagoproveedor/api/genera-asiento',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            data: {
                empresa_id: $('#empresa_id').val(),
                proveedor_id: $('#proveedor_id').val(),
                fecha: $('#fecha').val(),
                datoscaja: JSON.stringify(datosCuentasCaja),
                datoscontables: JSON.stringify(datosCuentasContables),
                datoscheques_emitidos: typeof serializarChequesEmitidos === 'function' ? serializarChequesEmitidos() : '[]',
                datoscheques_recibidos: typeof serializarChequesRecibidos === 'function' ? serializarChequesRecibidos() : '[]',
                datoscomprobantes: serializarComprobantes(),
                datosretenciones: serializarRetenciones()
            },
            success: function (data) {
                if (data.mensaje !== 'ok') {
                    alert('Error en generación del asiento contable');
                    return;
                }
                $(wrapper).empty();
                $.each(data.asiento, function (index, value) {
                    $(wrapper).append(
                        '<tr class="item-cuenta-asiento">' +
                        '<td><div class="form-group row" id="cuentacontable">' +
                        '<input type="hidden" name="cuenta[]" class="form-control iicuentacontable" readonly value="' + (index + 1) + '" />' +
                        '<input type="hidden" class="cuentacontable_id" name="cuentacontable_ids[]" value="' + value.cuentacontable_id + '" >' +
                        '<input type="hidden" class="cuentacontable_id_previa" name="cuentacontable_id_previa[]" value="' + value.cuentacontable_id + '" >' +
                        '<button type="button" title="Consulta cuentas" style="padding:1;" class="btn-accion-tabla consultacuenta tooltipsC">' +
                        '<i class="fa fa-search text-primary"></i></button>' +
                        '<input type="text" style="WIDTH: 100px;HEIGHT: 38px" class="codigoasiento form-control" name="codigoasientos[]" value="' + value.codigo + '" >' +
                        '<input type="hidden" class="codigo_previo_cuentacontable" name="codigo_previo_cuentacontables[]" value="" >' +
                        '<input type="hidden" class="carga_cuentacontable_manual" name="carga_cuentacontable_manuales[]" value="' + (value.carga_cuentacontable_manual || 'N') + '" >' +
                        '</div></td>' +
                        '<td><input type="text" style="WIDTH: 250px; HEIGHT: 38px" class="nombrecuentacontable form-control" name="nombrecuentacontables[]" value="' + value.nombre + '" readonly></td>' +
                        '<td><select name="centrocostoasiento_ids[]" class="centrocostoasiento form-control"></select>' +
                        '<input type="hidden" class="centrocostoasiento_id_previo" name="centrocostoasiento_id_previo[]" value="' + (value.centrocosto_id || 0) + '" ></td>' +
                        '<td><select name="monedaasiento_ids[]" class="monedaasiento form-control required" required></select>' +
                        '<input type="hidden" class="monedaasiento_id_previo" name="monedaasiento_id_previo[]" value="' + value.moneda_id + '" ></td>' +
                        '<td><input type="number" style="text-align: right;" name="debeasientos[]" class="form-control debeasiento" value="' + (value.debe || '') + '"></td>' +
                        '<td><input type="number" style="text-align: right;" name="haberasientos[]" class="form-control haberasiento" value="' + (value.haber || '') + '"></td>' +
                        '<td><input type="number" name="cotizacionasientos[]" class="form-control cotizacionasiento" value="' + value.cotizacion + '"></td>' +
                        '<td><input type="text" name="observacionasientos[]" class="form-control observacionasiento" value="' + (value.observacion || '') + '"></td>' +
                        '<td><button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_cuenta_asiento tooltipsC">' +
                        '<i class="fa fa-times-circle text-danger"></i></button></td>' +
                        '</tr>'
                    );
                });
                $('#cuenta-asiento-table .item-cuenta-asiento').each(function () {
                    armaSelectMoneda(this);
                    if (typeof leeCentroCostoAsiento === 'function') {
                        leeCentroCostoAsiento($(this).find('.codigoasiento'));
                    }
                });
                if (typeof activa_eventosAsiento === 'function') {
                    activa_eventosAsiento(false);
                }
                if (typeof sumaMontoAsiento === 'function') {
                    sumaMontoAsiento();
                }
                totalDebeAsiento = parseFloat($('#totaldebeasiento').val()) || 0;
                totalHaberAsiento = parseFloat($('#totalhaberasiento').val()) || 0;
                flModificaAsiento = false;
            },
            error: function () {
                alert('Error grave en generación del asiento contable');
            }
        });
    }

    function muestraVentanaAsiento() {
        if (totalDebeAsiento === 0 && totalHaberAsiento === 0) {
            generaAsientoContable();
        } else if (flModificaAsiento) {
            generaAsientoContable();
        }
        ocultarForms();
        $('.formasientoexterno').show();
    }

    function completarProveedorEmitidos() {
        var $form = $('#form-pagoproveedor');
        $form.find('input[name="proveedor_emitido_ids[]"]').remove();
        var proveedorId = $('#proveedor_id').val() || '';
        $('#tbody-cheque-emitido-table tr').each(function () {
            $form.append($('<input type="hidden" name="proveedor_emitido_ids[]">').val(proveedorId));
        });
    }

    $(function () {
        $('#agrega_renglon_cuenta').on('click', function (e) {
            e.preventDefault();
            agregaUnRenglon();
        });
        $(document).on('click', '.eliminar_cuenta', function (e) {
            e.preventDefault();
            $(this).parents('tr').remove();
            actualizaRenglonesCuenta();
            sumaMonto();
            flModificaAsiento = true;
        });

        activaEventosCuentas(true);
        if (typeof activaEventosChequesIngresoEgreso === 'function') {
            activaEventosChequesIngresoEgreso();
        }

        $('#botonform1').on('click', function () {
            ocultarForms();
            $('.form1').show();
        });
        $('#botonform2').on('click', function () {
            ocultarForms();
            $('.form2').show();
            sumaMonto();
        });
        $('#botonform3').on('click', function () {
            ocultarForms();
            $('.form3').show();
            sumaMonto();
        });
        $('#botonform4').on('click', function () {
            ocultarForms();
            $('.form4').show();
        });
        $('#botonform5').on('click', function () {
            ocultarForms();
            $('.form5').show();
        });
        $('#botonform6').on('click', function () {
            muestraVentanaAsiento();
        });

        $(document).on('click', '.eligeconsultacuentacaja', function () {
            if (typeof cuentacajaxcodigoEmitido !== 'undefined' && cuentacajaxcodigoEmitido && cuentacajaxcodigoEmitido.length) {
                var seleccionE = $(this).parents('tr').children().html();
                var nombreE = $(this).parents('tr').find('.nombre').html();
                var codigoE = $(this).parents('tr').find('.codigo').html();
                var monedaE = $(this).parents('tr').find('.moneda_id').html();
                cuentacajaxcodigoEmitido.find('.cuentacaja_emitido_id').val(seleccionE);
                cuentacajaxcodigoEmitido.find('.codigo_emitido').val(codigoE);
                cuentacajaxcodigoEmitido.find('.nombre_emitido').val(nombreE);
                cuentacajaxcodigoEmitido.find('.moneda_emitido_id').val(monedaE);
                cuentacajaxcodigoEmitido = null;
                $('#consultacuentacajaModal').modal('hide');
                flModificaAsiento = true;
                return;
            }
            var seleccion = $(this).parents('tr').children().html();
            var nombre = $(this).parents('tr').find('.nombre').html();
            var codigo = $(this).parents('tr').find('.codigo').html();
            var moneda = $(this).parents('tr').find('.moneda_id').html();
            if (cuentacajaxcodigo) {
                cuentacajaxcodigo.val(seleccion);
                cuentacajaxcodigo.parents('tr').find('.cuentacaja_id_previa').val(seleccion);
                nombrexcodigo.val(nombre);
                codigoxcodigo.val(codigo);
                cuentacajaxcodigo.parents('tr').find('.moneda').val(moneda);
            }
            $('#consultacuentacajaModal').modal('hide');
            flModificaAsiento = true;
        });

        $.get(carpetaBase + '/configuracion/leermoneda', function (data) {
            $.each($.map(data, function (v) { return [v]; }), function (i, value) {
                idMoneda.push(value.id);
                descripcionMoneda[value.id] = value.abreviatura;
            });
        });

        $('#botonform0').on('click', function () {
            if (!$('#proveedor_id').val()) {
                alert('Debe seleccionar proveedor');
                return;
            }
            if (!$('#empresa_id').val()) {
                alert('Debe seleccionar empresa');
                return;
            }
            completarProveedorEmitidos();
            $('#form-pagoproveedor').trigger('submit');
        });

        $(document).on('click', '#agrega_renglon_cheque_emitido', function () {
            setTimeout(function () {
                var nombre = $('#descripcionproveedor').val() || '';
                $('#tbody-cheque-emitido-table tr:last .anombrede_emitido').val(nombre);
            }, 50);
        });
    });
}(jQuery));
