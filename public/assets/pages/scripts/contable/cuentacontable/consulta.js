var cuentacontablexcodigo;
var nombrexcodigo;
var codigoxcodigo;
var ptrCuentacontableContext;
var consultaCuentaContableTimer = null;
var consultaCuentaContableAjax = null;
var CONSULTA_CUENTACONTABLE_DEBOUNCE_MS = 280;
var abriendoModalCuentaContable = false;

function esTeclaF1CuentaContable(e) {
    return e && (e.key === 'F1' || e.code === 'F1' || e.keyCode === 112);
}

function modalCuentaContableAbierto() {
    var $modal = $('#consultacuentaModal');
    return abriendoModalCuentaContable
        || ($modal.length > 0 && ($modal.hasClass('show') || $modal.hasClass('in') || $modal.is(':visible')));
}

/**
 * Si el preview del asiento reemplazó el DOM mientras el modal estaba abierto,
 * el ptr queda en un nodo muerto: reubicar por data-concepto-ivacompra-id.
 */
function contextoCuentaContableVivo($ctx) {
    if ($ctx && $ctx.length && document.contains($ctx.get(0))) {
        return $ctx;
    }
    var conceptoId = 0;
    if ($ctx && $ctx.length) {
        conceptoId = parseInt($ctx.attr('data-concepto-ivacompra-id') || '0', 10) || 0;
        if (conceptoId <= 0) {
            conceptoId = parseInt($ctx.closest('[data-concepto-ivacompra-id]').attr('data-concepto-ivacompra-id') || '0', 10) || 0;
        }
    }
    if (conceptoId > 0) {
        var $vivo = $('.cp-asiento-cuenta-editable[data-concepto-ivacompra-id="' + conceptoId + '"]').filter(function () {
            return document.contains(this);
        }).first();
        if ($vivo.length) {
            return $vivo;
        }
    }
    var debeIdx = 0;
    if ($ctx && $ctx.length) {
        debeIdx = parseInt($ctx.attr('data-debe-gasto-idx') || '0', 10) || 0;
        if (debeIdx <= 0) {
            debeIdx = parseInt($ctx.closest('[data-debe-gasto-idx]').attr('data-debe-gasto-idx') || '0', 10) || 0;
        }
    }
    if (debeIdx > 0) {
        var $vivoGasto = $('.cp-asiento-cuenta-editable[data-debe-gasto-idx="' + debeIdx + '"]').filter(function () {
            return document.contains(this);
        }).first();
        if ($vivoGasto.length) {
            return $vivoGasto;
        }
    }
    return ($ctx && $ctx.length) ? $ctx : null;
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
    $ctx = contextoCuentaContableVivo($ctx);
    if ($ctx && $ctx.length) {
        // Código/nombre antes del change: el preview del CP lee esos campos al sincronizar.
        var $codigoCtx = $ctx.find('.codigocuentacontable').first();
        if (!$codigoCtx.length) {
            $codigoCtx = $ctx.find('.codigoasiento').first();
        }
        $codigoCtx.val(data.codigo);
        $ctx.find('.nombrecuentacontable').first().val(data.nombre);
        $ctx.find('.cuentacontable_id_previa').val(data.id);
        $ctx.find('.codigo_previo').val(data.codigo);
        $ctx.find('.codigo_previo_cuentacontable').val(data.codigo);
        actualizarLinkEditarCuentaContable($ctx, data.id);
        $ctx.find('.cuentacontable_id').first().val(data.id).trigger('change');
        // Contexto de grilla/campo: no tocar otros .tm-cuentacontable-campo del form.
        return;
    }

    $('#codigocuentacontable').val(data.codigo);
    $('#nombrecuentacontable').val(data.nombre);
    $('#cuentacontable_id').val(data.id).trigger('change');
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
    var $ccAsiento = $tr.find('.centrocostoasiento');
    var tieneCcAsiento = $ccAsiento.length > 0;
    if (!$tr.length || (!$tr.find('.centrocosto').length && !tieneCcAsiento)) {
        return;
    }

    var $codigo = $tr.find('.codigocuentacontable').first();
    if (!$codigo.length) {
        $codigo = $tr.find('.codigoasiento').first();
    }
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
            if (tieneCcAsiento) {
                $ccAsiento.empty().append('<option value="0" selected>Sin CC</option>').attr('readonly', true);
            } else {
                $tr.find('.centrocosto').empty().append('<option value="0" selected>Sin CC</option>').attr('readonly', true);
                $tr.find('.centrocosto_id_previo').val('0');
            }
            return;
        }
        if (tieneCcAsiento) {
            $ccAsiento.attr('readonly', false);
        } else {
            $tr.find('.centrocosto').attr('readonly', false);
        }
    }

    if (tieneCcAsiento && typeof completarCentroCostoAsiento === 'function') {
        var ccPrevioAsiento = parseInt($tr.find('.centrocostoasiento_id_previo').val() || '0', 10) || 0;
        completarCentroCostoAsiento($codigo.get(0), cuentaId, ccPrevioAsiento);
        if (typeof marcaAsientoLineaManual === 'function') {
            marcaAsientoLineaManual($tr);
        }
        return;
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
    // Abrir el modal dispara blur del código: no resolver ni limpiar mientras abre/está abierto.
    if (modalCuentaContableAbierto()) {
        return;
    }

    $ctx = contextoCuentaContableVivo($ctx);
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
    $ctx = contextoCuentaContableVivo($ctx);
    ptrCuentacontableContext = $ctx && $ctx.length ? $ctx : null;
    cuentacontablexcodigo = $ctx && $ctx.length ? $ctx.find('.cuentacontable_id').first() : $('#cuentacontable_id');
    nombrexcodigo = $ctx && $ctx.length ? $ctx.find('.nombrecuentacontable').first() : $('#nombrecuentacontable');
    codigoxcodigo = $ctx && $ctx.length ? $ctx.find('.codigocuentacontable').first() : $('#codigocuentacontable');

    var empresaId = empresaIdParaConsultaCuentaContable($ctx);

    if (empresaId > 0) {
        abriendoModalCuentaContable = true;
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
            $el.hasClass('codigocuentacontable') || $el.is('#codigocuentacontable') ||
            $el.hasClass('codigoasiento') ||
            $el.is('#consultacuentacontable') ||
            $el.is('#consultadeposito, #consultapuntoventa, #consultatipotransaccionventa, #consultacuentacaja, #consultalistaprecio') ||
            $el.hasClass('codigopuntoventa') ||
            $el.hasClass('codigocuentacaja') ||
            $el.hasClass('codigolistaprecio') ||
            $el.hasClass('abreviaturatipotransaccionventa')
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
    .on('keyup.consultactaBuscar input.consultactaBuscar', '#consultacuentacontable', function (e) {
        if (e.which === 13 || e.key === 'Enter') {
            return;
        }
        programarBusquedaCuentaContable($(this).val());
    });

function elegirPrimeraCuentaContableDelModal() {
    var $btn = $('#datoscuentas .eligeconsultacuentacontable').first();
    if ($btn.length) {
        $btn.trigger('click');
        return true;
    }
    return false;
}

$(document)
    .off('keydown.consultaCtaEnter', '#consultacuentacontable')
    .on('keydown.consultaCtaEnter', '#consultacuentacontable', function (e) {
        if (e.which !== 13 && e.key !== 'Enter') {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        if (!elegirPrimeraCuentaContableDelModal()) {
            programarBusquedaCuentaContable($(this).val());
        }
    });

$(document)
    .off('submit.consultaCtaEnter', '#consultacuentaModal form')
    .on('submit.consultaCtaEnter', '#consultacuentaModal form', function (e) {
        e.preventDefault();
        elegirPrimeraCuentaContableDelModal();
        return false;
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
            if (modalCuentaContableAbierto()) {
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
            abriendoModalCuentaContable = true;

            var $ctx = $(this).closest('.tm-cuentacontable-campo');
            if (!$ctx.length) {
                $ctx = $(this).closest('tr');
            }

            abrirModalConsultaCuentaContableDesdeContexto($ctx.length ? $ctx : null);
        });

    $('#consultacuentaModal')
        .off('shown.bs.modal.consultacta')
        .on('shown.bs.modal.consultacta', function () {
            abriendoModalCuentaContable = false;
            $(this).find('[autofocus]').focus();
        })
        .off('hidden.bs.modal.consultacta')
        .on('hidden.bs.modal.consultacta', function () {
            abriendoModalCuentaContable = false;
        });

    $('#aceptaconsultacuentaModal').off('click.consultacta').on('click.consultacta', function () {
        if (!elegirPrimeraCuentaContableDelModal()) {
            $('#consultacuentaModal').modal('hide');
        }
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

        var $ctx = contextoCuentaContableVivo(ptrCuentacontableContext);
        if (!$ctx || !$ctx.length) {
            $ctx = null;
        }

        // Un solo apply sobre el nodo vivo (evita escribir en DOM reemplazado por el preview).
        if ($ctx && $ctx.length) {
            aplicarCuentaContableEnContexto($ctx, data);
            refrescarCentroCostoTrasCuenta($ctx, data);
            ptrCuentacontableContext = $ctx;
            cuentacontablexcodigo = $ctx.find('.cuentacontable_id').first();
            nombrexcodigo = $ctx.find('.nombrecuentacontable').first();
            codigoxcodigo = $ctx.find('.codigocuentacontable').first();
        } else if (cuentacontablexcodigo && cuentacontablexcodigo.length && document.contains(cuentacontablexcodigo.get(0))) {
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
            cuentacontablexcodigo.trigger('change');
        } else {
            $('#codigocuentacontable').val(data.codigo);
            $('#nombrecuentacontable').val(data.nombre);
            $('#cuentacontable_id').val(data.id).trigger('change');
        }

        $('#consultacuentaModal').modal('hide');
    });
}

$(function () {
    if (typeof activa_eventos_consulta_cuentacontable === 'function') {
        activa_eventos_consulta_cuentacontable();
    }
});
