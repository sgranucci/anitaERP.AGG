$(function () {
    $('#agrega_renglon_apertura_gasto').on('click', agregaRenglonAperturaGastoEmpresa);
    $('#replicar_apertura_gasto_todas').on('click', replicarAperturaGastoDesdePrimeraFila);
    $(document).on('click', '.eliminar_apertura_gasto_empresa', borraRenglonAperturaGastoEmpresa);
    $(document).on('click', '.replicar_apertura_gasto_empresas', replicarAperturaGastoDesdeFila);

    if (typeof activa_eventos_consulta_cuentacontable === 'function') {
        activa_eventos_consulta_cuentacontable();
    }
    if (typeof activa_eventos_consultacentrocosto === 'function') {
        activa_eventos_consultacentrocosto();
    }
});

function agregaRenglonAperturaGastoEmpresa(event) {
    if (event && event.preventDefault) {
        event.preventDefault();
    }
    var html = $('#template-renglon-apertura-gasto-empresa').html();
    var $tbody = $('#tbody-apertura-gasto-empresas');
    var empresaDefault = $tbody.children('tr:last').find('.empresa').val();

    $tbody.append(html);

    var $nuevo = $tbody.children('tr:last');
    limpiarFilaAperturaGastoEmpresa($nuevo);
    if (empresaDefault) {
        $nuevo.find('.empresa').val(empresaDefault);
    }

    return $nuevo;
}

function borraRenglonAperturaGastoEmpresa(event) {
    event.preventDefault();
    var $tbody = $('#tbody-apertura-gasto-empresas');
    if ($tbody.children('tr').length <= 1) {
        limpiarFilaAperturaGastoEmpresa($(this).closest('tr'));
        return;
    }
    $(this).closest('tr').remove();
}

function limpiarFilaAperturaGastoEmpresa($tr) {
    $tr.find('input[type="hidden"].cuentacontable_id, input[type="hidden"].centrocosto_id').val('');
    $tr.find('.codigocuentacontable, .nombrecuentacontable, .codigocentrocosto, .descripcioncentrocosto').val('');
    $tr.find('.empresa').val('');
    $tr.find('.btn-link-editar-cuentacontable, .btn-link-editar-centrocosto').addClass('d-none').attr('href', '#');
}

function empresasYaCargadasAperturaGasto() {
    var ids = {};
    $('#tbody-apertura-gasto-empresas .empresa').each(function () {
        var id = parseInt($(this).val(), 10) || 0;
        if (id > 0) {
            ids[id] = true;
        }
    });
    return ids;
}

function leerDatosFilaAperturaGasto($tr) {
    var $camposCuenta = $tr.find('.tm-cuentacontable-campo');
    var $cuenta = $camposCuenta.eq(0);
    var $contrap = $camposCuenta.eq(1);
    var $cc = $tr.find('.tm-centrocosto-campo').first();

    return {
        empresa_id: parseInt($tr.find('.empresa').val(), 10) || 0,
        cuentacontable_id: parseInt($cuenta.find('.cuentacontable_id').val(), 10) || 0,
        contrapartida_id: parseInt($contrap.find('.cuentacontable_id').val(), 10) || 0,
        centrocosto_id: parseInt($cc.find('.centrocosto_id').val(), 10) || 0,
    };
}

function replicarAperturaGastoDesdePrimeraFila(event) {
    event.preventDefault();
    var $origen = null;
    $('#tbody-apertura-gasto-empresas tr.item-apertura-gasto-empresa').each(function () {
        if ($origen) {
            return;
        }
        var datos = leerDatosFilaAperturaGasto($(this));
        if (datos.empresa_id > 0 && datos.cuentacontable_id > 0) {
            $origen = $(this);
        }
    });

    if (!$origen) {
        alert('Complete primero una fila con empresa y cuenta contable.');
        return;
    }

    replicarAperturaGastoDesdeFila(event, $origen);
}

function replicarAperturaGastoDesdeFila(event, $trForzado) {
    if (event && event.preventDefault) {
        event.preventDefault();
    }

    var $tr = $trForzado && $trForzado.length ? $trForzado : $(this).closest('tr');
    var datos = leerDatosFilaAperturaGasto($tr);

    if (datos.empresa_id <= 0) {
        alert('Debe seleccionar la empresa de origen.');
        return;
    }
    if (datos.cuentacontable_id <= 0) {
        alert('Debe cargar la cuenta contable de origen.');
        return;
    }

    var url = carpetaBase + '/caja/apertura-gasto/replicar-cuentas/'
        + datos.empresa_id + '/' + datos.cuentacontable_id
        + '?contrapartida_id=' + datos.contrapartida_id
        + '&centrocosto_id=' + datos.centrocosto_id;

    $.getJSON(url)
        .done(function (lineas) {
            if (!$.isArray(lineas) || lineas.length === 0) {
                alert('No se encontraron cuentas equivalentes en las otras empresas.');
                return;
            }

            var yaCargadas = empresasYaCargadasAperturaGasto();
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

                var $nuevo = agregaRenglonAperturaGastoEmpresa(null);
                aplicarLineaReplicadaAperturaGasto($nuevo, value);
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
                // Feedback breve sin molestar demasiado
                if (window.console && console.log) {
                    console.log(msg);
                }
            }
        })
        .fail(function () {
            alert('No se pudo replicar las cuentas a las otras empresas.');
        });
}

function aplicarLineaReplicadaAperturaGasto($tr, value) {
    $tr.find('.empresa').val(value.empresa_id);

    var $camposCuenta = $tr.find('.tm-cuentacontable-campo');
    var $cuenta = $camposCuenta.eq(0);
    var $contrap = $camposCuenta.eq(1);
    var $cc = $tr.find('.tm-centrocosto-campo').first();

    $cuenta.find('.cuentacontable_id').val(value.cuentacontable_id || '');
    $cuenta.find('.codigocuentacontable').val(value.codigocuentacontable || '');
    $cuenta.find('.nombrecuentacontable').val(value.nombrecuentacontable || '');
    if (typeof actualizarLinkEditarCuentaContable === 'function') {
        actualizarLinkEditarCuentaContable($cuenta, value.cuentacontable_id || 0);
    }

    $contrap.find('.cuentacontable_id').val(value.cuentacontable_contrapartida_id || '');
    $contrap.find('.codigocuentacontable').val(value.codigocontrapartida || '');
    $contrap.find('.nombrecuentacontable').val(value.nombrecontrapartida || '');
    if (typeof actualizarLinkEditarCuentaContable === 'function') {
        actualizarLinkEditarCuentaContable($contrap, value.cuentacontable_contrapartida_id || 0);
    }

    $cc.find('.centrocosto_id').val(value.centrocosto_id || '');
    $cc.find('.codigocentrocosto').val(value.codigocentrocosto || '');
    $cc.find('.descripcioncentrocosto').val(value.nombrecentrocosto || '');
    if (typeof actualizarLinkEditarCentrocosto === 'function') {
        actualizarLinkEditarCentrocosto($cc, value.centrocosto_id || 0);
    }
}
