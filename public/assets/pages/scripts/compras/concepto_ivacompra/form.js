$(function () {
    $('#agrega_renglon_condicioniva').on('click', agregaRenglonCondicioniva);
    $(document).on('click', '.eliminar_condicioniva', borraRenglonCondicioniva);

    $('#agrega_renglon_concepto_ivacompra').on('click', agregaRenglonConceptoIvacompraEmpresa);
    $('#replicar_concepto_ivacompra_todas').on('click', replicarConceptoIvacompraDesdePrimeraFila);
    $(document).on('click', '.eliminar_concepto_ivacompra_empresa', borraRenglonConceptoIvacompraEmpresa);
    $(document).on('click', '.replicar_concepto_ivacompra_empresas', replicarConceptoIvacompraDesdeFila);

    if (typeof activa_eventos_consulta_cuentacontable === 'function') {
        activa_eventos_consulta_cuentacontable();
    }
});

function agregaRenglonCondicioniva(event) {
    if (event && event.preventDefault) {
        event.preventDefault();
    }
    var renglon = $('#template-renglon-condicioniva').html();
    $('#tbody-condicioniva-table').append(renglon);
    actualizaRenglonesCondicioniva();
}

function borraRenglonCondicioniva(event) {
    if (event && event.preventDefault) {
        event.preventDefault();
    }
    var $tbody = $('#tbody-condicioniva-table');
    if ($tbody.children('tr').length <= 1) {
        $(this).closest('tr').find('select').val('');
        return;
    }
    $(this).closest('tr').remove();
    actualizaRenglonesCondicioniva();
}

function actualizaRenglonesCondicioniva() {
    var item = 1;
    $('#tbody-condicioniva-table .iicondicioniva').each(function () {
        $(this).val(item++);
    });
}

function agregaRenglonConceptoIvacompraEmpresa(event) {
    if (event && event.preventDefault) {
        event.preventDefault();
    }
    var html = $('#template-renglon-concepto-ivacompra-empresa').html();
    var $tbody = $('#tbody-concepto-ivacompra-empresas');
    var empresaDefault = $tbody.children('tr:last').find('.empresa').val();

    $tbody.append(html);

    var $nuevo = $tbody.children('tr:last');
    limpiarFilaConceptoIvacompraEmpresa($nuevo);
    if (empresaDefault) {
        $nuevo.find('.empresa').val(empresaDefault);
    }

    return $nuevo;
}

function borraRenglonConceptoIvacompraEmpresa(event) {
    event.preventDefault();
    var $tbody = $('#tbody-concepto-ivacompra-empresas');
    if ($tbody.children('tr').length <= 1) {
        limpiarFilaConceptoIvacompraEmpresa($(this).closest('tr'));
        return;
    }
    $(this).closest('tr').remove();
}

function limpiarFilaConceptoIvacompraEmpresa($tr) {
    $tr.find('input[type="hidden"].cuentacontable_id').val('');
    $tr.find('.codigocuentacontable, .nombrecuentacontable').val('');
    $tr.find('.empresa').val('');
    $tr.find('.btn-link-editar-cuentacontable').addClass('d-none').attr('href', '#');
}

function empresasYaCargadasConceptoIvacompra() {
    var ids = {};
    $('#tbody-concepto-ivacompra-empresas .empresa').each(function () {
        var id = parseInt($(this).val(), 10) || 0;
        if (id > 0) {
            ids[id] = true;
        }
    });
    return ids;
}

function leerDatosFilaConceptoIvacompra($tr) {
    var $campos = $tr.find('.tm-cuentacontable-campo');
    var $debe = $campos.eq(0);
    var $haber = $campos.eq(1);

    return {
        empresa_id: parseInt($tr.find('.empresa').val(), 10) || 0,
        cuentacontabledebe_id: parseInt($debe.find('.cuentacontable_id').val(), 10) || 0,
        cuentacontablehaber_id: parseInt($haber.find('.cuentacontable_id').val(), 10) || 0,
    };
}

function replicarConceptoIvacompraDesdePrimeraFila(event) {
    event.preventDefault();
    var $origen = null;
    $('#tbody-concepto-ivacompra-empresas tr.item-concepto-ivacompra-empresa').each(function () {
        if ($origen) {
            return;
        }
        var datos = leerDatosFilaConceptoIvacompra($(this));
        if (datos.empresa_id > 0 && (datos.cuentacontabledebe_id > 0 || datos.cuentacontablehaber_id > 0)) {
            $origen = $(this);
        }
    });

    if (!$origen) {
        alert('Complete primero una fila con empresa y al menos una cuenta contable.');
        return;
    }

    replicarConceptoIvacompraDesdeFila(event, $origen);
}

function replicarConceptoIvacompraDesdeFila(event, $trForzado) {
    if (event && event.preventDefault) {
        event.preventDefault();
    }

    var $tr = $trForzado && $trForzado.length ? $trForzado : $(this).closest('tr');
    var datos = leerDatosFilaConceptoIvacompra($tr);

    if (datos.empresa_id <= 0) {
        alert('Debe seleccionar la empresa de origen.');
        return;
    }
    if (datos.cuentacontabledebe_id <= 0 && datos.cuentacontablehaber_id <= 0) {
        alert('Debe cargar al menos una cuenta contable de origen.');
        return;
    }

    var debeId = datos.cuentacontabledebe_id > 0 ? datos.cuentacontabledebe_id : 0;
    var url = carpetaBase + '/compras/concepto_ivacompra/replicar-cuentas/'
        + datos.empresa_id + '/' + (debeId > 0 ? debeId : '0')
        + '?cuentacontablehaber_id=' + datos.cuentacontablehaber_id;

    $.getJSON(url)
        .done(function (lineas) {
            if (!$.isArray(lineas) || lineas.length === 0) {
                alert('No se encontraron cuentas equivalentes en las otras empresas.');
                return;
            }

            var yaCargadas = empresasYaCargadasConceptoIvacompra();
            var agregadas = 0;
            var omitidas = 0;

            $.each(lineas, function (_i, value) {
                var empresaId = parseInt(value.empresa_id, 10) || 0;
                if (empresaId <= 0) {
                    return;
                }
                if (yaCargadas[empresaId]) {
                    omitidas++;
                    return;
                }
                if (!(parseInt(value.cuentacontabledebe_id, 10) > 0) && !(parseInt(value.cuentacontablehaber_id, 10) > 0)) {
                    return;
                }

                var $nuevo = agregaRenglonConceptoIvacompraEmpresa(null);
                aplicarLineaReplicadaConceptoIvacompra($nuevo, value);
                yaCargadas[empresaId] = true;
                agregadas++;
            });

            if (agregadas === 0) {
                alert(omitidas > 0
                    ? 'No había empresas pendientes para replicar.'
                    : 'No se agregaron filas.');
            }
        })
        .fail(function () {
            alert('No se pudo replicar las cuentas a las otras empresas.');
        });
}

function aplicarLineaReplicadaConceptoIvacompra($tr, value) {
    $tr.find('.empresa').val(value.empresa_id);

    var $campos = $tr.find('.tm-cuentacontable-campo');
    var $debe = $campos.eq(0);
    var $haber = $campos.eq(1);

    $debe.find('.cuentacontable_id').val(value.cuentacontabledebe_id || '');
    $debe.find('.codigocuentacontable').val(value.codigocuentadebe || '');
    $debe.find('.nombrecuentacontable').val(value.nombrecuentadebe || '');
    if (typeof actualizarLinkEditarCuentaContable === 'function') {
        actualizarLinkEditarCuentaContable($debe, value.cuentacontabledebe_id || 0);
    }

    $haber.find('.cuentacontable_id').val(value.cuentacontablehaber_id || '');
    $haber.find('.codigocuentacontable').val(value.codigocuentahaber || '');
    $haber.find('.nombrecuentacontable').val(value.nombrecuentahaber || '');
    if (typeof actualizarLinkEditarCuentaContable === 'function') {
        actualizarLinkEditarCuentaContable($haber, value.cuentacontablehaber_id || 0);
    }
}
