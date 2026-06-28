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

function activaEventosConsultaCuentaContableAutomatica() {
    $(document).off('change.cuentaauto', '.tm-cuenta-campo .codigocuentacontable').on('change.cuentaauto', '.tm-cuenta-campo .codigocuentacontable', function (event) {
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

    $(document).off('click.cuentaauto', '.tm-cuenta-campo .consultacuentacontable').on('click.cuentaauto', '.tm-cuenta-campo .consultacuentacontable', function (event) {
        event.preventDefault();

        cuentaContableCampoActivo = $(this).closest('.tm-cuenta-campo');
        $('#consultaempresa_id').val(empresaIdParaConsultaCuentaContable());
        $('#consultacuentaModal').modal('show');
    });

    $('#consultacuentaModal').off('shown.bs.modal.cuentaauto').on('shown.bs.modal.cuentaauto', function () {
        $(this).find('[autofocus]').focus();
    });

    $('#aceptaconsultacuentaModal').off('click.cuentaauto').on('click.cuentaauto', function () {
        $('#consultacuentaModal').modal('hide');
    });

    $(document).off('click.cuentaauto', '.eligeconsultacuentacontable').on('click.cuentaauto', '.eligeconsultacuentacontable', function () {
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
    activaEventosConsultaCuentaContableAutomatica();

    $('#empresa_id').on('change', function () {
        var empresaId = $(this).val();
        if (empresaId) {
            window.location.href = carpetaBase + '/contable/cuentas-automaticas?empresa_id=' + empresaId;
        }
    });
});
