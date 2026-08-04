var cuentacontablexcodigo;
var nombrexcodigo;
var codigoxcodigo;
var ptrCuentacontableContext;
var consultaCuentaContableTimer = null;
var consultaCuentaContableAjax = null;
var CONSULTA_CUENTACONTABLE_DEBOUNCE_MS = 280;

function esTeclaF1CuentaContable(e) {
    return e && (e.key === 'F1' || e.code === 'F1' || e.keyCode === 112);
}

function empresaIdParaConsultaCuentaContable($ctx) {
    var empresaId = 0;

    if ($ctx && $ctx.length) {
        var $tr = $ctx.is('tr') ? $ctx : $ctx.closest('tr');
        if ($tr.length && $tr.find('.empresa').length) {
            empresaId = parseInt($tr.find('.empresa').val(), 10) || 0;
        }
    }

    if (!empresaId) {
        empresaId = parseInt($('#consultaempresa_id').val(), 10) || 0;
    }
    if (!empresaId) {
        empresaId = parseInt($('#empresa_id').val(), 10) || 0;
    }

    return empresaId;
}

function contextoDesdeInputCodigoCuentaContable($input) {
    var $ctx = $input.closest('.tm-cuentacontable-campo');
    if (!$ctx.length) {
        $ctx = $input.closest('tr');
    }
    return $ctx.length ? $ctx : null;
}

function actualizarLinkEditarCuentaContable($ctx, cuentaId) {
    if (!$ctx || !$ctx.length) {
        return;
    }
    var $link = $ctx.find('.btn-link-editar-cuentacontable');
    if (!$link.length) {
        return;
    }
    var id = parseInt(cuentaId, 10) || 0;
    if (id > 0) {
        $link.attr('href', carpetaBase + '/contable/cuentacontable/' + id + '/editar?origen=modal_consulta&vista=consulta').removeClass('d-none');
    } else {
        $link.attr('href', '#').addClass('d-none');
    }
}

function aplicarCuentaContableEnContexto($ctx, data) {
    if ($ctx && $ctx.length) {
        $ctx.find('.cuentacontable_id').first().val(data.id);
        $ctx.find('.codigocuentacontable').first().val(data.codigo);
        $ctx.find('.nombrecuentacontable').first().val(data.nombre);
        $ctx.find('.cuentacontable_id_previa').val(data.id);
        $ctx.find('.codigo_previo').val(data.codigo);
        actualizarLinkEditarCuentaContable($ctx, data.id);
        // Contexto de grilla/campo: no tocar otros .tm-cuentacontable-campo del form.
        return;
    }

    $('#cuentacontable_id').val(data.id);
    $('#codigocuentacontable').val(data.codigo);
    $('#nombrecuentacontable').val(data.nombre);
    actualizarLinkEditarCuentaContable($('.tm-cuentacontable-campo').first(), data.id);
}

function limpiarCuentaContableEnContexto($ctx) {
    if ($ctx && $ctx.length) {
        $ctx.find('.cuentacontable_id').first().val('');
        $ctx.find('.codigocuentacontable').first().val('');
        $ctx.find('.nombrecuentacontable').first().val('');
        $ctx.find('.cuentacontable_id_previa').val('');
        $ctx.find('.codigo_previo').val('');
        actualizarLinkEditarCuentaContable($ctx, 0);
        return;
    }

    $('#cuentacontable_id').val('');
    $('#codigocuentacontable').val('');
    $('#nombrecuentacontable').val('');
    actualizarLinkEditarCuentaContable($('.tm-cuentacontable-campo').first(), 0);
}

function refrescarCentroCostoTrasCuenta($ctx, data) {
    if (!$ctx || !$ctx.length) {
        return;
    }

    var $tr = $ctx.is('tr') ? $ctx : $ctx.closest('tr');
    if (!$tr.length || !$tr.find('.centrocosto').length) {
        return;
    }

    var $codigo = $tr.find('.codigocuentacontable').first();
    if (!$codigo.length) {
        return;
    }

    var cuentaId = parseInt((data && data.id) || $tr.find('.cuentacontable_id').first().val(), 10) || 0;
    if (cuentaId <= 0) {
        return;
    }

    if (data && data.manejaccosto !== undefined) {
        var manejaCc = data.manejaccosto === 'S' || data.manejaccosto === '1' || data.manejaccosto === 1;
        if (!manejaCc) {
            $tr.find('.centrocosto').empty().append('<option value="0" selected>Sin CC</option>').attr('readonly', true);
            $tr.find('.centrocosto_id_previo').val('0');
            return;
        }
        $tr.find('.centrocosto').attr('readonly', false);
    }

    if (typeof completarCentroCosto === 'function') {
        completarCentroCosto($codigo.get(0), cuentaId, 0);
        return;
    }

    if (typeof leeCentroCosto === 'function') {
        $tr.find('.codigo_previo').val('');
        leeCentroCosto($codigo.get(0));
    }
}

function buscar_datos(consulta) {
    if (consultaCuentaContableAjax && consultaCuentaContableAjax.readyState !== 4) {
        consultaCuentaContableAjax.abort();
    }

    var empresa_id = empresaIdParaConsultaCuentaContable(ptrCuentacontableContext);
    var texto = (consulta === undefined || consulta === null) ? '' : String(consulta);

    consultaCuentaContableAjax = $.ajax({
        url: carpetaBase+'/contable/cuentacontable/consultacuentacontable',
        type: 'POST',
        dataType: 'json',
	    headers: {
        	'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    	},
        data: {
            consulta: texto,
            empresa_id: empresa_id
        },
    })
    .done(function(respuesta) {
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
    .fail (function(xhr, status) {
        if (status !== 'abort') {
            console.log("error");
        }
    });
}

function programarBusquedaCuentaContable(consulta) {
    clearTimeout(consultaCuentaContableTimer);
    consultaCuentaContableTimer = setTimeout(function () {
        buscar_datos(consulta);
    }, CONSULTA_CUENTACONTABLE_DEBOUNCE_MS);
}

function resolverPorCodigoCuentaContable(codigo, $ctx) {
    var codigoNuevo = $.trim(codigo);
    var empresaId = empresaIdParaConsultaCuentaContable($ctx);
    var codigoAnt = ($ctx && $ctx.length) ? $.trim($ctx.find('.codigo_previo').first().val() || '') : '';

    if (!codigoNuevo) {
        limpiarCuentaContableEnContexto($ctx);
        return;
    }

    if (!empresaId) {
        alert('Debe ingresar empresa');
        return;
    }

    var urlCta = carpetaBase + '/contable/cuentacontable/leercuentacontableporcodigo/' + empresaId + '/' + encodeURIComponent(codigoNuevo);

    $.get(urlCta, function(data) {
        if (data && data.id > 0) {
            aplicarCuentaContableEnContexto($ctx, data);

            if (codigoNuevo !== codigoAnt) {
                refrescarCentroCostoTrasCuenta($ctx, data);
            }
        } else {
            alert('No existe la cuenta');

            if ($ctx && $ctx.length && $ctx.is('tr') && $ctx.find('.cuentacontable_id_previa').length) {
                $ctx.remove();
            }

            limpiarCuentaContableEnContexto($ctx);
        }
    }).fail(function() {
        limpiarCuentaContableEnContexto($ctx);
    });
}

function abrirModalConsultaCuentaContableDesdeContexto($ctx) {
    ptrCuentacontableContext = $ctx && $ctx.length ? $ctx : null;
    cuentacontablexcodigo = $ctx && $ctx.length ? $ctx.find('.cuentacontable_id').first() : $('#cuentacontable_id');
    nombrexcodigo = $ctx && $ctx.length ? $ctx.find('.nombrecuentacontable').first() : $('#nombrecuentacontable');
    codigoxcodigo = $ctx && $ctx.length ? $ctx.find('.codigocuentacontable').first() : $('#codigocuentacontable');

    var empresaId = empresaIdParaConsultaCuentaContable($ctx);

    if (empresaId > 0) {
        $('#consultaempresa_id').val(empresaId);
        $('#consultacuentaModal').modal('show');
        clearTimeout(consultaCuentaContableTimer);
        buscar_datos('');
    } else {
        alert('Debe ingresar empresa');
    }
}

// Si pulsamos Enter en un input no envía el formulario, salvo códigos de consulta operativa.
$(document)
    .off('keydown.noEnterSubmitCuentacontable', 'input')
    .on('keydown.noEnterSubmitCuentacontable', 'input', function (e) {
        if (e.which !== 13 && e.key !== 'Enter') {
            return;
        }
        var $el = $(this);
        // Estos campos validan por código con Enter (handlers en consulta.js).
        if (
            $el.hasClass('codigoproveedor') || $el.is('#codigoproveedor') ||
            $el.hasClass('codigoconcepto_solicitudpago') || $el.is('#concepto_solicitudpago_id_codigo') ||
            $el.hasClass('codigodeposito') ||
            $el.hasClass('sku') || $el.hasClass('codigoarticulo') ||
            $el.hasClass('codigocuentacontable') || $el.is('#codigocuentacontable')
        ) {
            return;
        }
        e.preventDefault();
        return false;
    });

// Enter en código cuenta: capture para ganar a bloqueos globales.
document.addEventListener('keydown', function (e) {
    if (!(e.key === 'Enter' || e.code === 'Enter' || e.keyCode === 13 || e.which === 13)) {
        return;
    }
    var target = e.target;
    if (!target || target.readOnly || target.disabled) {
        return;
    }
    if (!target.classList.contains('codigocuentacontable') && target.id !== 'codigocuentacontable') {
        return;
    }
    e.preventDefault();
    e.stopPropagation();
    var $input = $(target);
    $input.data('cta-enter-procesado', 1);
    resolverPorCodigoCuentaContable($input.val(), contextoDesdeInputCodigoCuentaContable($input));
}, true);

$(document)
    .off('keydown.ctaCodigoCuentaEnter', '.codigocuentacontable, #codigocuentacontable')
    .on('keydown.ctaCodigoCuentaEnter', '.codigocuentacontable, #codigocuentacontable', function (e) {
        if (e.which !== 13 && e.key !== 'Enter') {
            return;
        }
        if ($(this).data('cta-enter-procesado')) {
            e.preventDefault();
            e.stopPropagation();
            return;
        }
        $(this).data('cta-enter-procesado', 1);
        e.preventDefault();
        e.stopPropagation();
        resolverPorCodigoCuentaContable($(this).val(), contextoDesdeInputCodigoCuentaContable($(this)));
    });

document.addEventListener('keydown', function (e) {
    if (!esTeclaF1CuentaContable(e)) {
        return;
    }
    var target = e.target;
    if (!target || (!target.classList.contains('codigocuentacontable') && target.id !== 'codigocuentacontable')) {
        return;
    }
    if (target.readOnly || target.disabled) {
        return;
    }
    if ($('#consultacuentaModal').hasClass('show') || $('#consultacuentaModal').is(':visible')) {
        return;
    }
    e.preventDefault();
    e.stopPropagation();
    abrirModalConsultaCuentaContableDesdeContexto(contextoDesdeInputCodigoCuentaContable($(target)));
}, true);

$(document).off('keyup.consultactaBuscar input.consultactaBuscar', '#consultacuentacontable')
    .on('keyup.consultactaBuscar input.consultactaBuscar', '#consultacuentacontable', function () {
        programarBusquedaCuentaContable($(this).val());
    });

function activa_eventos_consulta_cuentacontable()
{
    $(document)
        .off('change.consultacta blur.consultacta', '.codigocuentacontable')
        .on('change.consultacta blur.consultacta', '.codigocuentacontable', function (event) {
            var $input = $(this);
            if ($input.data('cta-enter-procesado')) {
                $input.removeData('cta-enter-procesado');
                return;
            }

            var $ctx = contextoDesdeInputCodigoCuentaContable($input);
            var esCampoTm = $ctx && $ctx.length && $ctx.hasClass('tm-cuentacontable-campo');
            var codigoActual = $.trim($input.val() || '');
            var codigoPrevio = ($ctx && $ctx.length)
                ? $.trim($ctx.find('.codigo_previo').first().val() || '')
                : '';

            // tm-cuentacontable-campo: solo blur (change se ignora para no duplicar).
            // Grilla asiento (tr): blur y change resuelven si el código cambió — si no,
            // el hidden cuentacontable_ids[] queda con el id viejo al grabar.
            if (event.type === 'blur') {
                if (!esCampoTm) {
                    if (!$ctx || !$ctx.length || codigoActual === codigoPrevio) {
                        return;
                    }
                }
            } else if (esCampoTm) {
                return;
            }

            event.preventDefault();
            resolverPorCodigoCuentaContable($input.val(), $ctx);
        });

    $(document)
        .off('click.consultacta', '.consultacuentacontable')
        .on('click.consultacta', '.consultacuentacontable', function (event) {
            event.preventDefault();

            var $ctx = $(this).closest('.tm-cuentacontable-campo');
            if (!$ctx.length) {
                $ctx = $(this).closest('tr');
            }

            abrirModalConsultaCuentaContableDesdeContexto($ctx.length ? $ctx : null);
        });

    $('#consultacuentaModal').off('shown.bs.modal.consultacta').on('shown.bs.modal.consultacta', function () {
        $(this).find('[autofocus]').focus();
    });

    $('#aceptaconsultacuentaModal').off('click.consultacta').on('click.consultacta', function () {
        $('#consultacuentaModal').modal('hide');
    });

    $(document).off('click.eligeconsultacuentacontable').on('click.eligeconsultacuentacontable', '.eligeconsultacuentacontable', function () {
        var $tr = $(this).closest('tr');
        var data = {
            id: $.trim($tr.find('.cuentacontable_id').first().text()),
            codigo: $.trim($tr.find('.codigocuentacontable').first().text()),
            nombre: $.trim($tr.find('.nombrecuentacontable').first().text()),
        };

        if (window.ptrIeCpFilaCuentaConcepto && window.ptrIeCpFilaCuentaConcepto.length
            && typeof window.ieComprobanteIvaAplicarCuenta === 'function') {
            window.ieComprobanteIvaAplicarCuenta(data.id, data.codigo, data.nombre);
            $('#consultacuentaModal').modal('hide');
            return;
        }

        var $ctx = ptrCuentacontableContext;
        if (!$ctx || !$ctx.length) {
            $ctx = null;
        }

        if (cuentacontablexcodigo && cuentacontablexcodigo.length) {
            cuentacontablexcodigo.val(data.id);
            if (nombrexcodigo && nombrexcodigo.length) {
                nombrexcodigo.val(data.nombre);
            }
            if (codigoxcodigo && codigoxcodigo.length) {
                codigoxcodigo.val(data.codigo);
            }
            cuentacontablexcodigo.parents('tr').find('.cuentacontable_id_previa').val(data.id);
            cuentacontablexcodigo.parents('tr').find('.codigo_previo').val(data.codigo);
            actualizarLinkEditarCuentaContable(cuentacontablexcodigo.closest('tr'), data.id);
        }

        if ($ctx && $ctx.length) {
            aplicarCuentaContableEnContexto($ctx, data);
            refrescarCentroCostoTrasCuenta($ctx, data);
        } else {
            $('#cuentacontable_id').val(data.id);
            $('#nombrecuentacontable').val(data.nombre);
            $('#codigocuentacontable').val(data.codigo);
        }

        $('#consultacuentaModal').modal('hide');
    });
}

$(function () {
    if (typeof activa_eventos_consulta_cuentacontable === 'function') {
        activa_eventos_consulta_cuentacontable();
    }
});
