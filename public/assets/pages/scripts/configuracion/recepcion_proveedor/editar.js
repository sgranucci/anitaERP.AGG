var cuentaContableCampoActivo = null;

function empresaIdParaConsultaCuentaContable() {
    var empresaId = $('#empresa_id').val();

    if (!empresaId || empresaId === '0') {
        return 1;
    }

    return empresaId;
}

function limpiarCuentaCampo($campo) {
    $campo.find('.cuentacontable_id').val('');
    $campo.find('.codigocuentacontable').val('');
    $campo.find('.nombrecuentacontable').val('');
}

function reindexarTolerancias() {
    $('#tbody-tolerancia-recepcion .item-tolerancia-recepcion').each(function (indice) {
        $(this).find('[name^="tolerancias["]').each(function () {
            var name = $(this).attr('name');
            if (!name) {
                return;
            }
            $(this).attr('name', name.replace(/tolerancias\[[^\]]+\]/, 'tolerancias[' + indice + ']'));
        });
    });
}

function centrosCostoUsados() {
    var usados = {};
    $('#tbody-tolerancia-recepcion .item-tolerancia-recepcion').each(function () {
        var $select = $(this).find('.centrocosto-tolerancia-select');
        var $hidden = $(this).find('input[type="hidden"][name*="[centrocosto_id]"]');
        var valor = $select.length ? $select.val() : ($hidden.length ? $hidden.val() : '');
        if (valor) {
            usados[valor] = true;
        }
    });
    return usados;
}

function actualizarOpcionesCentroCosto() {
    var usados = centrosCostoUsados();
    $('#tbody-tolerancia-recepcion .centrocosto-tolerancia-select').each(function () {
        var valorActual = $(this).val();
        $(this).find('option').each(function () {
            var val = $(this).attr('value');
            if (!val) {
                return;
            }
            $(this).prop('disabled', !!usados[val] && val !== valorActual);
        });
    });
}

function agregarRenglonTolerancia(event) {
    event.preventDefault();

    var indice = $('#tbody-tolerancia-recepcion .item-tolerancia-recepcion').length;
    var renglon = $('#template-renglon-tolerancia-recepcion').html();
    if (!renglon) {
        return;
    }

    $('#tbody-tolerancia-recepcion').append(renglon.replace(/__INDEX__/g, indice));
    reindexarTolerancias();
    actualizarOpcionesCentroCosto();
}

function activaEventosConsultaCuentaContableRecepcion() {
    $(document).off('change.recepprov', '.tm-cuenta-campo .codigocuentacontable').on('change.recepprov', '.tm-cuenta-campo .codigocuentacontable', function (event) {
        event.preventDefault();

        var $campo = $(this).closest('.tm-cuenta-campo');
        var codigoNuevo = $(this).val().trim();
        var empresaId = empresaIdParaConsultaCuentaContable();

        if (!codigoNuevo) {
            limpiarCuentaCampo($campo);
            return;
        }

        var urlCta = carpetaBase + '/contable/cuentacontable/leercuentacontableporcodigo/' + empresaId + '/' + encodeURIComponent(codigoNuevo);

        $.get(urlCta, function (data) {
            if (data.id > 0) {
                $campo.find('.cuentacontable_id').val(data.id);
                $campo.find('.nombrecuentacontable').val(data.nombre);
            } else {
                alert('No existe la cuenta');
                limpiarCuentaCampo($campo);
            }
        });
    });

    $(document).off('click.recepprov', '.tm-cuenta-campo .consultacuentacontable').on('click.recepprov', '.tm-cuenta-campo .consultacuentacontable', function (event) {
        event.preventDefault();

        cuentaContableCampoActivo = $(this).closest('.tm-cuenta-campo');
        $('#consultaempresa_id').val(empresaIdParaConsultaCuentaContable());
        $('#consultacuentaModal').modal('show');
    });

    $('#consultacuentaModal').off('shown.bs.modal.recepprov').on('shown.bs.modal.recepprov', function () {
        $(this).find('[autofocus]').focus();
    });

    $('#aceptaconsultacuentaModal').off('click.recepprov').on('click.recepprov', function () {
        $('#consultacuentaModal').modal('hide');
    });

    $(document).off('click.recepprov', '.eligeconsultacuentacontable').on('click.recepprov', '.eligeconsultacuentacontable', function () {
        var $tr = $(this).closest('tr');
        var seleccion = $tr.find('.cuentacontable_id').first().text().trim();
        var codigo = $tr.find('.codigocuentacontable').first().text().trim();
        var nombre = $tr.find('.nombrecuentacontable').first().text().trim();

        if (cuentaContableCampoActivo && cuentaContableCampoActivo.length) {
            cuentaContableCampoActivo.find('.cuentacontable_id').val(seleccion);
            cuentaContableCampoActivo.find('.codigocuentacontable').val(codigo);
            cuentaContableCampoActivo.find('.nombrecuentacontable').val(nombre);
        }

        $('#consultacuentaModal').modal('hide');
    });
}

$(function () {
    activaEventosConsultaCuentaContableRecepcion();
    actualizarOpcionesCentroCosto();

    $('#empresa_id').on('change', function () {
        var empresaId = $(this).val();
        if (empresaId) {
            window.location.href = carpetaBase + '/configuracion/recepcion-proveedor?empresa_id=' + empresaId;
        }
    });

    $('#agrega-renglon-tolerancia').on('click', agregarRenglonTolerancia);

    $(document).on('click', '.eliminar-tolerancia', function (event) {
        event.preventDefault();
        $(this).closest('tr').remove();
        reindexarTolerancias();
        actualizarOpcionesCentroCosto();
    });

    $(document).on('change', '.centrocosto-tolerancia-select', function () {
        var codigo = $(this).find('option:selected').data('codigo') || '—';
        $(this).closest('tr').find('.codigo-centrocosto-texto').text(codigo);
        actualizarOpcionesCentroCosto();
    });
});
