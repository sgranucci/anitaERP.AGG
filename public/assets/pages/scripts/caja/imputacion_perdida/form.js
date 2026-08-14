$(function () {
    $('#agrega_renglon_imputacion_perdida').on('click', agregaRenglonImputacionPerdidaEmpresa);
    $('#replicar_imputacion_perdida_todas').on('click', replicarImputacionPerdidaDesdePrimeraFila);
    $(document).on('click', '.eliminar_imputacion_perdida_empresa', borraRenglonImputacionPerdidaEmpresa);
    $(document).on('click', '.replicar_imputacion_perdida_empresas', replicarImputacionPerdidaDesdeFila);

    if (typeof activa_eventos_consulta_cuentacontable === 'function') {
        activa_eventos_consulta_cuentacontable();
    }
});

function agregaRenglonImputacionPerdidaEmpresa(event) {
    if (event && event.preventDefault) {
        event.preventDefault();
    }
    var html = $('#template-renglon-imputacion-perdida-empresa').html();
    var $tbody = $('#tbody-imputacion-perdida-empresas');
    var empresaDefault = $tbody.children('tr:last').find('.empresa').val();

    $tbody.append(html);

    var $nuevo = $tbody.children('tr:last');
    limpiarFilaImputacionPerdidaEmpresa($nuevo);
    if (empresaDefault) {
        $nuevo.find('.empresa').val(empresaDefault);
    }

    return $nuevo;
}

function borraRenglonImputacionPerdidaEmpresa(event) {
    event.preventDefault();
    var $tbody = $('#tbody-imputacion-perdida-empresas');
    if ($tbody.children('tr').length <= 1) {
        limpiarFilaImputacionPerdidaEmpresa($(this).closest('tr'));
        return;
    }
    $(this).closest('tr').remove();
}

function limpiarFilaImputacionPerdidaEmpresa($tr) {
    $tr.find('input[type="hidden"].cuentacontable_id').val('');
    $tr.find('.codigocuentacontable, .nombrecuentacontable').val('');
    $tr.find('.empresa').val('');
    $tr.find('.btn-link-editar-cuentacontable').addClass('d-none').attr('href', '#');
}

function empresasYaCargadasImputacionPerdida() {
    var ids = {};
    $('#tbody-imputacion-perdida-empresas .empresa').each(function () {
        var id = parseInt($(this).val(), 10) || 0;
        if (id > 0) {
            ids[id] = true;
        }
    });
    return ids;
}

function leerDatosFilaImputacionPerdida($tr) {
    var $cuenta = $tr.find('.tm-cuentacontable-campo').first();

    return {
        empresa_id: parseInt($tr.find('.empresa').val(), 10) || 0,
        cuentacontable_id: parseInt($cuenta.find('.cuentacontable_id').val(), 10) || 0,
    };
}

function replicarImputacionPerdidaDesdePrimeraFila(event) {
    event.preventDefault();
    var $origen = null;
    $('#tbody-imputacion-perdida-empresas tr.item-imputacion-perdida-empresa').each(function () {
        if ($origen) {
            return;
        }
        var datos = leerDatosFilaImputacionPerdida($(this));
        if (datos.empresa_id > 0 && datos.cuentacontable_id > 0) {
            $origen = $(this);
        }
    });

    if (!$origen) {
        alert('Complete primero una fila con empresa y cuenta contable.');
        return;
    }

    replicarImputacionPerdidaDesdeFila(event, $origen);
}

function replicarImputacionPerdidaDesdeFila(event, $trForzado) {
    if (event && event.preventDefault) {
        event.preventDefault();
    }

    var $tr = $trForzado && $trForzado.length ? $trForzado : $(this).closest('tr');
    var datos = leerDatosFilaImputacionPerdida($tr);

    if (datos.empresa_id <= 0) {
        alert('Debe seleccionar la empresa de origen.');
        return;
    }
    if (datos.cuentacontable_id <= 0) {
        alert('Debe cargar la cuenta contable de origen.');
        return;
    }

    var url = carpetaBase + '/caja/imputacion-perdida/replicar-cuentas/'
        + datos.empresa_id + '/' + datos.cuentacontable_id;

    $.getJSON(url)
        .done(function (lineas) {
            if (!$.isArray(lineas) || lineas.length === 0) {
                alert('No se encontraron cuentas equivalentes en las otras empresas.');
                return;
            }

            var yaCargadas = empresasYaCargadasImputacionPerdida();
            var agregadas = 0;
            var omitidas = 0;
            var sinCuenta = [];

            $.each(lineas, function (_i, value) {
                var empresaId = parseInt(value.empresa_id, 10) || 0;
                if (empresaId <= 0) {
                    return;
                }
                if (yaCargadas[empresaId]) {
                    omitidas++;
                    return;
                }
                if (!(parseInt(value.cuentacontable_id, 10) > 0)) {
                    sinCuenta.push(value.empresa_nombre || ('#' + empresaId));
                    return;
                }

                var $nuevo = agregaRenglonImputacionPerdidaEmpresa(null);
                aplicarLineaReplicadaImputacionPerdida($nuevo, value);
                yaCargadas[empresaId] = true;
                agregadas++;
            });

            var msg = 'Se agregaron ' + agregadas + ' empresa(s).';
            if (omitidas > 0) {
                msg += ' ' + omitidas + ' ya estaban cargadas.';
            }
            if (sinCuenta.length > 0) {
                msg += ' Sin cuenta equivalente: ' + sinCuenta.join(', ') + '.';
            }
            if (agregadas === 0 && omitidas === 0) {
                alert(msg);
            } else if (agregadas === 0) {
                alert('No había empresas pendientes para replicar.');
            } else {
                if (window.console && console.log) {
                    console.log(msg);
                }
            }
        })
        .fail(function () {
            alert('No se pudo replicar las cuentas a las otras empresas.');
        });
}

function aplicarLineaReplicadaImputacionPerdida($tr, value) {
    $tr.find('.empresa').val(value.empresa_id);

    var $cuenta = $tr.find('.tm-cuentacontable-campo').first();

    $cuenta.find('.cuentacontable_id').val(value.cuentacontable_id || '');
    $cuenta.find('.codigocuentacontable').val(value.codigocuentacontable || '');
    $cuenta.find('.nombrecuentacontable').val(value.nombrecuentacontable || '');
    if (typeof actualizarLinkEditarCuentaContable === 'function') {
        actualizarLinkEditarCuentaContable($cuenta, value.cuentacontable_id || 0);
    }
}
