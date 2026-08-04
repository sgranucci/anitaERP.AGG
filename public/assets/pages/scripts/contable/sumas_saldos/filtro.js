/**
 * Balance SyS — consulta de cuentas (rango desde/hasta).
 * Misma UX que mayor plano: F1 / lupa abren modal; Enter resuelve por código.
 */
var sumasSaldosCuentaCampoActivo = null;

function sumasSaldosNormalizarCodigoCuenta(valor) {
    return String(valor || '').replace(/\D/g, '');
}

function sumasSaldosFormatearCodigoCuenta(codigo) {
    var digits = sumasSaldosNormalizarCodigoCuenta(codigo);
    if (digits.length < 9) {
        digits = digits.padStart(9, '0');
    }
    if (digits.length !== 9) {
        return String(codigo || '').trim();
    }

    return digits.substring(0, 6) + '-' + digits.substring(6, 9);
}

function sumasSaldosEmpresaIdParaConsultaCuenta() {
    var $checks = $('#sys_empresas_asignadas_hidden input[name="empresa_ids[]"]');
    if ($checks.length) {
        var $marcados = $checks.filter(':checked');
        var $fuente = $marcados.length ? $marcados : $checks.filter('[type="hidden"]');
        if ($fuente.length) {
            return parseInt($fuente.first().val(), 10) || 0;
        }
    }

    var $empresa = $('#sys_empresa_id, #empresa_id');
    if ($empresa.length) {
        if ($empresa.is('select')) {
            var valores = $empresa.val();
            if (Array.isArray(valores) && valores.length > 0) {
                return parseInt(valores[0], 10) || 0;
            }
            if (valores) {
                return parseInt(valores, 10) || 0;
            }
        } else {
            return parseInt($empresa.val(), 10) || 0;
        }
    }

    return 0;
}

function sumasSaldosLimpiarCampoCuenta($campo) {
    if (!$campo || !$campo.length) {
        return;
    }
    $campo.find('.codigocuentacontable').val('');
    $campo.find('.nombrecuentacontable').val('');
    $campo.find('.cuentacontable_id').val('');
}

function sumasSaldosResolverNombreCuenta($campo) {
    var codigo = sumasSaldosNormalizarCodigoCuenta($campo.find('.codigocuentacontable').val());
    var empresaId = sumasSaldosEmpresaIdParaConsultaCuenta();

    if (!codigo) {
        sumasSaldosLimpiarCampoCuenta($campo);
        return;
    }

    if (!empresaId) {
        alert('Debe seleccionar al menos una empresa');
        return;
    }

    var urlCta = carpetaBase + '/contable/cuentacontable/leercuentacontableporcodigo/' +
        empresaId + '/' + encodeURIComponent(codigo);

    $.get(urlCta, function (data) {
        if (data && data.id > 0) {
            $campo.find('.codigocuentacontable').val(sumasSaldosFormatearCodigoCuenta(data.codigo || codigo));
            $campo.find('.nombrecuentacontable').val(data.nombre || '');
            $campo.find('.cuentacontable_id').val(data.id);
        } else {
            alert('No existe la cuenta para la empresa seleccionada');
            sumasSaldosLimpiarCampoCuenta($campo);
        }
    }).fail(function () {
        alert('No se pudo resolver la cuenta');
        sumasSaldosLimpiarCampoCuenta($campo);
    });
}

function sumasSaldosAbrirModalConsulta($campo) {
    var empresaId = sumasSaldosEmpresaIdParaConsultaCuenta();
    if (!empresaId) {
        alert('Debe seleccionar al menos una empresa');
        return;
    }

    sumasSaldosCuentaCampoActivo = $campo && $campo.length ? $campo : null;
    $('#consultaempresa_id').val(empresaId);
    $('#consultacuentaModal').modal('show');
}

/** Si el texto parece un código de cuenta (dígitos/guiones), busca sin formato. */
function sumasSaldosTextoConsultaModal(valor) {
    var trimmed = String(valor || '').trim();
    if (trimmed !== '' && /^[\d\-\s.]+$/.test(trimmed) && /\d/.test(trimmed)) {
        return sumasSaldosNormalizarCodigoCuenta(trimmed);
    }

    return trimmed;
}

function sumasSaldosBuscarCuentasModal(consulta) {
    var empresaId = sumasSaldosEmpresaIdParaConsultaCuenta();
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
            consulta: sumasSaldosTextoConsultaModal(consulta),
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

function sumasSaldosEsTeclaF1(e) {
    return e && (e.key === 'F1' || e.code === 'F1' || e.keyCode === 112);
}

function sumasSaldosModalConsultaAbierto() {
    var $modal = $('#consultacuentaModal');
    return $modal.hasClass('show') || $modal.is(':visible');
}

function sumasSaldosOnKeydownF1Capture(e) {
    if (!sumasSaldosEsTeclaF1(e)) {
        return;
    }

    var target = e.target;
    if (!target || target.disabled) {
        return;
    }

    var $target = $(target);
    var $campo = $target.closest('.sys-cuenta-campo');
    if (!$campo.length) {
        return;
    }

    var enCampoCuenta = $target.hasClass('codigocuentacontable')
        || $target.hasClass('nombrecuentacontable')
        || $target.closest('.consultacuentacontable').length > 0
        || $target.hasClass('consultacuentacontable');

    if (!enCampoCuenta) {
        return;
    }

    if (sumasSaldosModalConsultaAbierto()) {
        return;
    }

    e.preventDefault();
    e.stopPropagation();
    sumasSaldosAbrirModalConsulta($campo);
}

function activaEventosSumasSaldosFiltro() {
    $(document)
        .off('change.syscta', '.sys-cuenta-campo .codigocuentacontable')
        .on('change.syscta', '.sys-cuenta-campo .codigocuentacontable', function (event) {
            event.preventDefault();
            sumasSaldosResolverNombreCuenta($(this).closest('.sys-cuenta-campo'));
        });

    $(document)
        .off('keydown.syscta', '.sys-cuenta-campo .codigocuentacontable')
        .on('keydown.syscta', '.sys-cuenta-campo .codigocuentacontable', function (event) {
            if (event.key === 'Enter' || event.keyCode === 13) {
                event.preventDefault();
                event.stopPropagation();
                sumasSaldosResolverNombreCuenta($(this).closest('.sys-cuenta-campo'));
            }
        });

    document.removeEventListener('keydown', sumasSaldosOnKeydownF1Capture, true);
    document.addEventListener('keydown', sumasSaldosOnKeydownF1Capture, true);

    // Capture por si otro handler frena Enter en código.
    document.addEventListener(
        'keydown',
        function (e) {
            var target = e.target;
            if (!target || !target.classList || !target.classList.contains('codigocuentacontable')) {
                return;
            }
            if (!$(target).closest('.sys-cuenta-campo').length) {
                return;
            }
            if (target.readOnly || target.disabled) {
                return;
            }

            if (e.key === 'Enter' || e.keyCode === 13) {
                e.preventDefault();
                e.stopPropagation();
                sumasSaldosResolverNombreCuenta($(target).closest('.sys-cuenta-campo'));
            }
        },
        true
    );

    $(document)
        .off('click.syscta', '.sys-cuenta-campo .consultacuentacontable')
        .on('click.syscta', '.sys-cuenta-campo .consultacuentacontable', function (event) {
            event.preventDefault();
            event.stopPropagation();
            sumasSaldosAbrirModalConsulta($(this).closest('.sys-cuenta-campo'));
        });

    $('#consultacuentaModal')
        .off('shown.bs.modal.syscta')
        .on('shown.bs.modal.syscta', function () {
            var valor = '';
            if (sumasSaldosCuentaCampoActivo && sumasSaldosCuentaCampoActivo.length) {
                // Sin guión: el LIKE del modal busca contra codigo numérico en BD.
                valor = sumasSaldosTextoConsultaModal(
                    sumasSaldosCuentaCampoActivo.find('.codigocuentacontable').val()
                );
            }
            $('#consultacuentacontable').val(valor);
            sumasSaldosBuscarCuentasModal(valor);
            $(this).find('[autofocus]').focus();
        });

    $(document)
        .off('keyup.syscta', '#consultacuentacontable')
        .on('keyup.syscta', '#consultacuentacontable', function () {
            sumasSaldosBuscarCuentasModal($(this).val().trim());
        });

    $('#aceptaconsultacuentaModal')
        .off('click.syscta')
        .on('click.syscta', function () {
            $('#consultacuentaModal').modal('hide');
        });

    $(document)
        .off('click.syscta', '.eligeconsultacuentacontable')
        .on('click.syscta', '.eligeconsultacuentacontable', function () {
            var $tr = $(this).closest('tr');
            var codigo = $tr.find('.codigocuentacontable').first().text().trim();
            var nombre = $tr.find('.nombrecuentacontable').first().text().trim();
            var id = $.trim($tr.find('.cuentacontable_id').first().text());

            if (sumasSaldosCuentaCampoActivo && sumasSaldosCuentaCampoActivo.length) {
                sumasSaldosCuentaCampoActivo.find('.codigocuentacontable').val(sumasSaldosFormatearCodigoCuenta(codigo));
                sumasSaldosCuentaCampoActivo.find('.nombrecuentacontable').val(nombre);
                sumasSaldosCuentaCampoActivo.find('.cuentacontable_id').val(id);
            }

            $('#consultacuentaModal').modal('hide');
        });
}

$(function () {
    activaEventosSumasSaldosFiltro();
});
