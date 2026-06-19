var mayorPlanoCuentaCampoActivo = null;

function mayorPlanoNormalizarCodigoCuenta(valor) {
    return String(valor || '').replace(/\D/g, '');
}

function mayorPlanoFormatearCodigoCuenta(codigo) {
    var digits = mayorPlanoNormalizarCodigoCuenta(codigo);
    if (digits.length < 9) {
        digits = digits.padStart(9, '0');
    }
    if (digits.length !== 9) {
        return String(codigo || '').trim();
    }

    return digits.substring(0, 6) + '-' + digits.substring(6, 3);
}

function mayorPlanoEmpresaIdParaConsultaCuenta() {
    var $hiddenEmpresas = $('#mpc_empresas_asignadas_hidden input[name="empresa_ids[]"]');
    if ($hiddenEmpresas.length) {
        return parseInt($hiddenEmpresas.first().val(), 10) || 0;
    }

    var $empresa = $('#mpc_empresa_id, #empresa_id');
    if (!$empresa.length) {
        return 0;
    }

    if ($empresa.is('select')) {
        var valores = $empresa.val();
        if (Array.isArray(valores) && valores.length > 0) {
            return parseInt(valores[0], 10) || 0;
        }
        if (valores) {
            return parseInt(valores, 10) || 0;
        }

        return 0;
    }

    return parseInt($empresa.val(), 10) || 0;
}

function mayorPlanoLimpiarCampoCuenta($campo) {
    $campo.find('.codigocuentacontable').val('');
    $campo.find('.nombrecuentacontable').val('');
}

function mayorPlanoResolverNombreCuenta($campo) {
    var codigo = mayorPlanoNormalizarCodigoCuenta($campo.find('.codigocuentacontable').val());
    var empresaId = mayorPlanoEmpresaIdParaConsultaCuenta();

    if (!codigo) {
        mayorPlanoLimpiarCampoCuenta($campo);
        return;
    }

    if (!empresaId) {
        $campo.find('.nombrecuentacontable').val('');
        return;
    }

    var urlCta = carpetaBase + '/contable/cuentacontable/leercuentacontableporcodigo/' + empresaId + '/' + codigo;

    $.get(urlCta, function (data) {
        if (data && data.id > 0) {
            $campo.find('.codigocuentacontable').val(mayorPlanoFormatearCodigoCuenta(data.codigo || codigo));
            $campo.find('.nombrecuentacontable').val(data.nombre || '');
        } else {
            alert('No existe la cuenta para la empresa seleccionada');
            mayorPlanoLimpiarCampoCuenta($campo);
        }
    });
}

function mayorPlanoBuscarCuentasModal(consulta) {
    var empresaId = mayorPlanoEmpresaIdParaConsultaCuenta();

    if (!empresaId) {
        $('#datoscuentas').html('<tr><td colspan="4">Seleccione al menos una empresa</td></tr>');
        return;
    }

    $('#consultaempresa_id').val(empresaId);

    $.ajax({
        url: carpetaBase + '/contable/cuentacontable/consultacuentacontable',
        type: 'POST',
        dataType: 'json',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
        },
        data: {
            consulta: consulta,
            empresa_id: empresaId,
        },
    })
        .done(function (respuesta) {
            var html = '';
            if (respuesta && typeof respuesta === 'object' && respuesta.data !== undefined) {
                html = respuesta.data;
            } else if (typeof respuesta === 'string') {
                try {
                    var parsed = JSON.parse(respuesta);
                    html = parsed.data || '';
                } catch (e) {
                    html = respuesta;
                }
            }
            $('#datoscuentas').html(html);
        })
        .fail(function () {
            $('#datoscuentas').html('<tr><td colspan="4">Error al consultar cuentas</td></tr>');
        });
}

function activaEventosMayorPlanoCuentaFiltro() {
    $(document)
        .off('change.mpc', '.mpc-cuenta-campo .codigocuentacontable')
        .on('change.mpc', '.mpc-cuenta-campo .codigocuentacontable', function (event) {
            event.preventDefault();
            mayorPlanoResolverNombreCuenta($(this).closest('.mpc-cuenta-campo'));
        });

    $(document)
        .off('click.mpc', '.mpc-cuenta-campo .consultacuentacontable')
        .on('click.mpc', '.mpc-cuenta-campo .consultacuentacontable', function (event) {
            event.preventDefault();

            var empresaId = mayorPlanoEmpresaIdParaConsultaCuenta();
            if (!empresaId) {
                alert('Debe seleccionar al menos una empresa');
                return;
            }

            mayorPlanoCuentaCampoActivo = $(this).closest('.mpc-cuenta-campo');
            $('#consultaempresa_id').val(empresaId);
            $('#consultacuentaModal').modal('show');
        });

    $('#consultacuentaModal')
        .off('shown.bs.modal.mpc')
        .on('shown.bs.modal.mpc', function () {
            var valor = '';
            if (mayorPlanoCuentaCampoActivo && mayorPlanoCuentaCampoActivo.length) {
                valor = mayorPlanoCuentaCampoActivo.find('.codigocuentacontable').val().trim();
            }

            $('#consultacuentacontable').val(valor);
            mayorPlanoBuscarCuentasModal(valor);
            $(this).find('[autofocus]').focus();
        });

    $(document)
        .off('keyup.mpc', '#consultacuentacontable')
        .on('keyup.mpc', '#consultacuentacontable', function () {
            var valor = $(this).val().trim();
            mayorPlanoBuscarCuentasModal(valor);
        });

    $('#aceptaconsultacuentaModal')
        .off('click.mpc')
        .on('click.mpc', function () {
            $('#consultacuentaModal').modal('hide');
        });

    $(document)
        .off('click.mpc', '.eligeconsultacuentacontable')
        .on('click.mpc', '.eligeconsultacuentacontable', function () {
            var $tr = $(this).closest('tr');
            var codigo = $tr.find('.codigocuentacontable').first().text().trim();
            var nombre = $tr.find('.nombrecuentacontable').first().text().trim();

            if (mayorPlanoCuentaCampoActivo && mayorPlanoCuentaCampoActivo.length) {
                mayorPlanoCuentaCampoActivo.find('.codigocuentacontable').val(mayorPlanoFormatearCodigoCuenta(codigo));
                mayorPlanoCuentaCampoActivo.find('.nombrecuentacontable').val(nombre);
            }

            $('#consultacuentaModal').modal('hide');
        });

    $('#mpc-empresas-dual, #empresa_id')
        .off('change.mpc reporte-empresas-cambiadas.mpc')
        .on('change.mpc reporte-empresas-cambiadas.mpc', function () {
            $('.mpc-cuenta-campo').each(function () {
                var $campo = $(this);
                if (mayorPlanoNormalizarCodigoCuenta($campo.find('.codigocuentacontable').val())) {
                    mayorPlanoResolverNombreCuenta($campo);
                } else {
                    mayorPlanoLimpiarCampoCuenta($campo);
                }
            });
        });
}

$(function () {
    activaEventosMayorPlanoCuentaFiltro();
});
