var mayorPlanoCuentaCampoActivo = null;
var mayorPlanoCuentasSel = {};
var mayorPlanoCentrocostosSel = {};

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

    return digits.substring(0, 6) + '-' + digits.substring(6, 9);
}

function mayorPlanoEmpresaIdParaConsultaCuenta() {
    var $checks = $('#mpc_empresas_asignadas_hidden input[name="empresa_ids[]"]');
    if ($checks.length) {
        var $marcados = $checks.filter(':checked');
        var $fuente = $marcados.length ? $marcados : $checks.filter('[type="hidden"]');
        if ($fuente.length) {
            return parseInt($fuente.first().val(), 10) || 0;
        }
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

function mayorPlanoEsCampoPuntual($campo) {
    return $campo && $campo.length && $campo.hasClass('mpc-cuenta-puntual');
}

function mayorPlanoSincronizarHiddenCuentas() {
    var codigos = Object.keys(mayorPlanoCuentasSel)
        .map(function (c) {
            return parseInt(c, 10);
        })
        .filter(function (c) {
            return c > 0;
        })
        .sort(function (a, b) {
            return a - b;
        });

    $('#mpc_cuentas').val(codigos.join(','));

    var $aviso = $('#mpc-aviso-sin-cuentas-puntuales');
    if ($aviso.length) {
        $aviso.toggle(codigos.length === 0);
    }
}

function mayorPlanoRenderTablaCuentas() {
    var $tbody = $('#mpc-tbody-cuentas-seleccionadas');
    if (!$tbody.length) {
        return;
    }

    var codigos = Object.keys(mayorPlanoCuentasSel)
        .map(function (c) {
            return parseInt(c, 10);
        })
        .filter(function (c) {
            return c > 0;
        })
        .sort(function (a, b) {
            return a - b;
        });

    var html = '';
    codigos.forEach(function (codigo) {
        var item = mayorPlanoCuentasSel[String(codigo)] || {};
        var codigoFmt = item.codigo_fmt || mayorPlanoFormatearCodigoCuenta(codigo);
        var nombre = item.nombre || '';
        html +=
            '<tr data-codigo="' +
            codigo +
            '">' +
            '<td>' +
            $('<div>').text(codigoFmt).html() +
            '</td>' +
            '<td>' +
            $('<div>').text(nombre).html() +
            '</td>' +
            '<td class="text-center">' +
            '<button type="button" class="btn btn-outline-danger btn-xs mpc-btn-quitar-cuenta" title="Quitar">' +
            '<i class="fa fa-times"></i>' +
            '</button>' +
            '</td>' +
            '</tr>';
    });

    $tbody.html(html);
    mayorPlanoSincronizarHiddenCuentas();
}

function mayorPlanoAgregarCuenta(data) {
    var codigoRaw = mayorPlanoNormalizarCodigoCuenta(data.codigo || data.codigo_fmt || '');
    var codigo = parseInt(codigoRaw, 10) || 0;
    if (!codigo) {
        return false;
    }

    var key = String(codigo);
    if (mayorPlanoCuentasSel[key]) {
        return false;
    }

    mayorPlanoCuentasSel[key] = {
        codigo: codigo,
        codigo_fmt: mayorPlanoFormatearCodigoCuenta(data.codigo || codigo),
        nombre: data.nombre || '',
    };
    mayorPlanoRenderTablaCuentas();

    return true;
}

function mayorPlanoQuitarCuenta(codigo) {
    var key = String(parseInt(mayorPlanoNormalizarCodigoCuenta(codigo), 10) || 0);
    if (!key || key === '0' || !mayorPlanoCuentasSel[key]) {
        return;
    }

    delete mayorPlanoCuentasSel[key];
    mayorPlanoRenderTablaCuentas();
}

function mayorPlanoCargarCuentasDesdeDom() {
    mayorPlanoCuentasSel = {};
    $('#mpc-tbody-cuentas-seleccionadas tr').each(function () {
        var codigo = parseInt(mayorPlanoNormalizarCodigoCuenta($(this).attr('data-codigo')), 10) || 0;
        if (!codigo) {
            return;
        }
        mayorPlanoCuentasSel[String(codigo)] = {
            codigo: codigo,
            codigo_fmt: $(this).find('td').eq(0).text().trim(),
            nombre: $(this).find('td').eq(1).text().trim(),
        };
    });

    var hidden = $('#mpc_cuentas').val();
    if (hidden && Object.keys(mayorPlanoCuentasSel).length === 0) {
        String(hidden)
            .split(',')
            .forEach(function (token) {
                var codigo = parseInt(mayorPlanoNormalizarCodigoCuenta(token), 10) || 0;
                if (codigo > 0) {
                    mayorPlanoCuentasSel[String(codigo)] = {
                        codigo: codigo,
                        codigo_fmt: mayorPlanoFormatearCodigoCuenta(codigo),
                        nombre: '',
                    };
                }
            });
        mayorPlanoRenderTablaCuentas();
    } else {
        mayorPlanoSincronizarHiddenCuentas();
    }
}

function mayorPlanoResolverNombreCuenta($campo, onOk) {
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
            if (typeof onOk === 'function') {
                onOk({
                    codigo: data.codigo || codigo,
                    nombre: data.nombre || '',
                });
            }
        } else {
            alert('No existe la cuenta para la empresa seleccionada');
            mayorPlanoLimpiarCampoCuenta($campo);
        }
    });
}

function mayorPlanoAgregarCuentaDesdeCampo() {
    var $campo = $('.mpc-cuenta-puntual').first();
    if (!$campo.length) {
        return;
    }

    var codigo = mayorPlanoNormalizarCodigoCuenta($campo.find('.codigocuentacontable').val());
    if (!codigo) {
        alert('Ingrese un c\u00f3digo de cuenta');
        return;
    }

    var nombre = $campo.find('.nombrecuentacontable').val().trim();
    if (nombre) {
        if (!mayorPlanoAgregarCuenta({ codigo: codigo, nombre: nombre })) {
            alert('La cuenta ya est\u00e1 en la lista');
        } else {
            mayorPlanoLimpiarCampoCuenta($campo);
        }
        return;
    }

    mayorPlanoResolverNombreCuenta($campo, function (data) {
        if (!mayorPlanoAgregarCuenta(data)) {
            alert('La cuenta ya est\u00e1 en la lista');
        } else {
            mayorPlanoLimpiarCampoCuenta($campo);
        }
    });
}

/** Si el texto parece un código de cuenta (dígitos/guiones), busca sin formato. */
function mayorPlanoTextoConsultaModal(valor) {
    var trimmed = String(valor || '').trim();
    if (trimmed !== '' && /^[\d\-\s.]+$/.test(trimmed) && /\d/.test(trimmed)) {
        return mayorPlanoNormalizarCodigoCuenta(trimmed);
    }

    return trimmed;
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
            consulta: mayorPlanoTextoConsultaModal(consulta),
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

function mayorPlanoEsTeclaF1(e) {
    return e && (e.key === 'F1' || e.code === 'F1' || e.keyCode === 112);
}

function mayorPlanoModalConsultaAbierto() {
    var $modal = $('#consultacuentaModal');
    return $modal.hasClass('show') || $modal.is(':visible');
}

function mayorPlanoAbrirModalConsultaCuenta($campo) {
    var empresaId = mayorPlanoEmpresaIdParaConsultaCuenta();
    if (!empresaId) {
        alert('Debe seleccionar al menos una empresa');
        return false;
    }

    mayorPlanoCuentaCampoActivo = $campo && $campo.length ? $campo : null;
    $('#consultaempresa_id').val(empresaId);
    $('#consultacuentaModal').modal('show');
    return true;
}

function activaEventosMayorPlanoCuentaFiltro() {
    mayorPlanoCargarCuentasDesdeDom();

    $(document)
        .off('change.mpc', '.mpc-cuenta-campo .codigocuentacontable')
        .on('change.mpc', '.mpc-cuenta-campo .codigocuentacontable', function (event) {
            event.preventDefault();
            mayorPlanoResolverNombreCuenta($(this).closest('.mpc-cuenta-campo'));
        });

    $(document)
        .off('keydown.mpc', '.mpc-cuenta-campo .codigocuentacontable')
        .on('keydown.mpc', '.mpc-cuenta-campo .codigocuentacontable', function (event) {
            if (event.key === 'Enter' || event.keyCode === 13) {
                event.preventDefault();
                var $campo = $(this).closest('.mpc-cuenta-campo');
                if (mayorPlanoEsCampoPuntual($campo)) {
                    mayorPlanoAgregarCuentaDesdeCampo();
                } else {
                    mayorPlanoResolverNombreCuenta($campo);
                }
            }
        });

    document.removeEventListener('keydown', mayorPlanoOnKeydownF1Capture, true);
    document.addEventListener('keydown', mayorPlanoOnKeydownF1Capture, true);

    $(document)
        .off('click.mpc', '#mpc-btn-agregar-cuenta')
        .on('click.mpc', '#mpc-btn-agregar-cuenta', function (event) {
            event.preventDefault();
            mayorPlanoAgregarCuentaDesdeCampo();
        });

    $(document)
        .off('click.mpc', '.mpc-btn-quitar-cuenta')
        .on('click.mpc', '.mpc-btn-quitar-cuenta', function (event) {
            event.preventDefault();
            var codigo = $(this).closest('tr').attr('data-codigo');
            mayorPlanoQuitarCuenta(codigo);
        });

    $(document)
        .off('click.mpc', '.mpc-cuenta-campo .consultacuentacontable')
        .on('click.mpc', '.mpc-cuenta-campo .consultacuentacontable', function (event) {
            event.preventDefault();
            mayorPlanoAbrirModalConsultaCuenta($(this).closest('.mpc-cuenta-campo'));
        });

    $('#consultacuentaModal')
        .off('shown.bs.modal.mpc')
        .on('shown.bs.modal.mpc', function () {
            var valor = '';
            if (mayorPlanoCuentaCampoActivo && mayorPlanoCuentaCampoActivo.length) {
                // Sin guión: el LIKE del modal busca contra codigo numérico en BD.
                valor = mayorPlanoTextoConsultaModal(
                    mayorPlanoCuentaCampoActivo.find('.codigocuentacontable').val()
                );
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
                if (mayorPlanoEsCampoPuntual(mayorPlanoCuentaCampoActivo)) {
                    if (!mayorPlanoAgregarCuenta({ codigo: codigo, nombre: nombre })) {
                        alert('La cuenta ya est\u00e1 en la lista');
                    } else {
                        mayorPlanoLimpiarCampoCuenta(mayorPlanoCuentaCampoActivo);
                    }
                } else {
                    mayorPlanoCuentaCampoActivo.find('.codigocuentacontable').val(mayorPlanoFormatearCodigoCuenta(codigo));
                    mayorPlanoCuentaCampoActivo.find('.nombrecuentacontable').val(nombre);
                }
            }

            $('#consultacuentaModal').modal('hide');
        });

    $('#form-mayor-plano-cuenta')
        .off('submit.mpc')
        .on('submit.mpc', function () {
            mayorPlanoSincronizarHiddenCuentas();
        });

    $('#mpc-empresas-checkboxes, #mpc-empresas-dual, #empresa_id')
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

function mayorPlanoOnKeydownF1Capture(e) {
    if (!mayorPlanoEsTeclaF1(e)) {
        return;
    }

    var target = e.target;
    if (!target || target.disabled) {
        return;
    }

    var $target = $(target);
    var $campo = $target.closest('.mpc-cuenta-campo');
    if (!$campo.length) {
        return;
    }

    // Código editable, o nombre/lupa del mismo bloque de cuenta.
    var enCampoCuenta = $target.hasClass('codigocuentacontable')
        || $target.hasClass('nombrecuentacontable')
        || $target.closest('.consultacuentacontable').length > 0
        || $target.hasClass('consultacuentacontable');

    if (!enCampoCuenta) {
        return;
    }

    if (mayorPlanoModalConsultaAbierto()) {
        return;
    }

    e.preventDefault();
    e.stopPropagation();
    mayorPlanoAbrirModalConsultaCuenta($campo);
}

function mayorPlanoSincronizarHiddenCentrocostos() {
    var codigos = Object.keys(mayorPlanoCentrocostosSel).sort(function (a, b) {
        return a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' });
    });
    $('#mpc_centrocostos_codigo').val(codigos.join(','));
}

function mayorPlanoRenderCentrocostos() {
    var $tbody = $('#mpc-tbody-cc-seleccionados');
    if (!$tbody.length) {
        return;
    }
    var html = '';
    Object.keys(mayorPlanoCentrocostosSel)
        .sort(function (a, b) {
            return a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' });
        })
        .forEach(function (codigo) {
            var item = mayorPlanoCentrocostosSel[codigo] || {};
            html += '<tr data-codigo="' + $('<div>').text(codigo).html() + '">'
                + '<td>' + $('<div>').text(codigo).html() + '</td>'
                + '<td>' + $('<div>').text(item.nombre || '').html() + '</td>'
                + '<td class="text-center"><button type="button" class="btn btn-outline-danger btn-xs mpc-btn-quitar-cc" title="Quitar">'
                + '<i class="fa fa-times"></i></button></td></tr>';
        });
    $tbody.html(html);
    mayorPlanoSincronizarHiddenCentrocostos();
}

function mayorPlanoAgregarCentrocosto(codigo, nombre) {
    codigo = String(codigo || '').trim();
    if (!codigo || mayorPlanoCentrocostosSel[codigo]) {
        return false;
    }
    mayorPlanoCentrocostosSel[codigo] = { nombre: nombre || '' };
    mayorPlanoRenderCentrocostos();
    return true;
}

function mayorPlanoCargarCentrocostosDesdeDom() {
    mayorPlanoCentrocostosSel = {};
    $('#mpc-tbody-cc-seleccionados tr').each(function () {
        var codigo = String($(this).attr('data-codigo') || '').trim();
        if (codigo) {
            mayorPlanoCentrocostosSel[codigo] = { nombre: $(this).find('td').eq(1).text().trim() };
        }
    });
    if (!Object.keys(mayorPlanoCentrocostosSel).length) {
        String($('#mpc_centrocostos_codigo').val() || '').split(',').forEach(function (codigo) {
            codigo = codigo.trim();
            if (codigo) {
                mayorPlanoCentrocostosSel[codigo] = { nombre: '' };
            }
        });
    }
    mayorPlanoRenderCentrocostos();
}

function mayorPlanoAgregarCcDesdeCampo() {
    var $campo = $('.mpc-cc-puntual').first();
    var codigo = String($campo.find('.codigocentrocosto').val() || '').trim();
    if (!codigo) {
        alert('Ingrese un c\u00f3digo de centro de costo');
        return;
    }
    var nombre = String($campo.find('.descripcioncentrocosto').val() || '').trim();
    if (nombre) {
        if (!mayorPlanoAgregarCentrocosto(codigo, nombre)) {
            alert('El centro de costo ya est\u00e1 en la lista');
        } else {
            $campo.find('input').val('');
        }
        return;
    }
    leerCentrocostoPorCodigo(codigo, $campo.find('.codigocentrocosto').get(0), function (data) {
        if (data && mayorPlanoAgregarCentrocosto(data.codigo, data.nombre)) {
            $campo.find('input').val('');
        }
    });
}

$(function () {
    activaEventosMayorPlanoCuentaFiltro();
    mayorPlanoCargarCentrocostosDesdeDom();

    $(document).on('click.mpcCc', '#mpc-btn-agregar-cc', function (e) {
        e.preventDefault();
        mayorPlanoAgregarCcDesdeCampo();
    });
    $(document).on('click.mpcCc', '.mpc-btn-quitar-cc', function (e) {
        e.preventDefault();
        delete mayorPlanoCentrocostosSel[String($(this).closest('tr').attr('data-codigo') || '')];
        mayorPlanoRenderCentrocostos();
    });
    $(document).on('click.mpcCc', '.eligeconsultacentrocosto', function () {
        var $campo = ptrCentrocosto_id && ptrCentrocosto_id.length
            ? ptrCentrocosto_id.closest('.mpc-cc-puntual')
            : $();
        if (!$campo.length) {
            return;
        }
        var $tr = $(this).closest('tr');
        if (mayorPlanoAgregarCentrocosto($tr.find('.codigo').text().trim(), $tr.find('.nombre').text().trim())) {
            $campo.find('input').val('');
        }
    });
    $('#form-mayor-plano-cuenta').on('submit.mpcCc', mayorPlanoSincronizarHiddenCentrocostos);
});
