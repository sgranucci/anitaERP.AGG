/* global carpetaBase */
var ptrCamionContext;
var camionModalAbriendo = false;

function esTeclaF1Camion(e) {
    return e && (e.key === 'F1' || e.code === 'F1' || e.keyCode === 112);
}

function modalConsultaCamionAbierto() {
    var m = document.getElementById('consultacamionModal');
    return !!(m && (m.classList.contains('show') || m.classList.contains('in')));
}

function modalConsultaCamionAbiertoOAbriendo() {
    return camionModalAbriendo || modalConsultaCamionAbierto();
}

function contextoCamionDesde($el) {
    var $ctx = $($el).closest('.tm-camion-campo');
    return $ctx.length ? $ctx : $();
}

function descripcionCamionDesdeData(data) {
    if (data && data.descripcion) {
        return data.descripcion;
    }
    var partes = [];
    if (data && data.dominio) {
        partes.push(String(data.dominio).trim());
    }
    if (data && data.habilitacion) {
        partes.push(String(data.habilitacion).trim());
    }
    return partes.filter(Boolean).join(' · ');
}

function actualizarLinkEditarCamion($ctx, camionId) {
    if (!$ctx || !$ctx.length) {
        return;
    }
    var $link = $ctx.find('.btn-link-editar-camion');
    if (!$link.length) {
        return;
    }
    var id = parseInt(camionId, 10) || 0;
    if (id > 0) {
        $link
            .attr('href', carpetaBase + '/ventas/camion/' + id + '/editar?origen=modal_consulta&vista=consulta')
            .removeClass('d-none');
    } else {
        $link.attr('href', '#').addClass('d-none');
    }
}

function precargarPrecintosDesdeCamion(data) {
    var $cant = $('#cantidad_precinto');
    if (!$cant.length) {
        return;
    }
    if (!data || data.cantidad_precinto === undefined || data.cantidad_precinto === null) {
        return;
    }
    $cant.val(data.cantidad_precinto);
}

function notificarCamionAplicado($ctx, data) {
    precargarPrecintosDesdeCamion(data);
    if (typeof window.onCamionAplicadoEnFormulario === 'function') {
        window.onCamionAplicadoEnFormulario(data, $ctx);
    }
}

function limpiarCamionEnContexto($ctx) {
    if (!$ctx || !$ctx.length) {
        return;
    }
    $ctx.find('.camion_id').first().val('');
    $ctx.find('.codigocamion').first().val('');
    $ctx.find('.descripcioncamion').first().val('');
    actualizarLinkEditarCamion($ctx, 0);
}

function aplicarCamionEnContexto($ctx, data) {
    if (!$ctx || !$ctx.length || !data || !data.id) {
        return;
    }
    $ctx.find('.camion_id').first().val(data.id);
    $ctx.find('.codigocamion').first().val(data.codigo != null ? data.codigo : '');
    $ctx.find('.descripcioncamion').first().val(descripcionCamionDesdeData(data));
    actualizarLinkEditarCamion($ctx, data.id);
    notificarCamionAplicado($ctx, data);
}

function parsearHtmlConsultaCamion(respuesta) {
    var resp = String(respuesta || '').replace(/\\/g, '');
    try {
        var parsed = JSON.parse(resp);
        return parsed.data || '';
    } catch (e) {
        return resp;
    }
}

function buscar_datos_camion(consulta) {
    $.ajax({
        url: carpetaBase + '/ventas/camion/consultacamion',
        type: 'POST',
        dataType: 'HTML',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') ||
                ($('input[name="_token"]').first().val() || '')
        },
        data: {
            consulta: consulta || '',
        },
    })
        .done(function (respuesta) {
            $('#datoscamion').html(parsearHtmlConsultaCamion(respuesta));
        })
        .fail(function () {
            $('#datoscamion').html('<tr><td colspan="7">Error al consultar camiones</td></tr>');
        });
}

function avisarCamionInvalido($input) {
    if (!$input || !$input.length) {
        return;
    }
    if ($input.data('camion-invalido-aviso')) {
        return;
    }
    $input.data('camion-invalido-aviso', 1);
    var $modal = $('#consultacamionModal');
    if ($modal.hasClass('show') || $modal.hasClass('in')) {
        $modal.modal('hide');
    }
    setTimeout(function () {
        alert('No se encontr\u00f3 el cami\u00f3n indicado.');
        $input.trigger('focus').select();
    }, 0);
}

function focusSiguienteCamion($ctx, opciones) {
    opciones = opciones || {};
    var selector = opciones.focusSiguiente || '';
    if (!selector && $ctx && $ctx.length) {
        selector = String($ctx.attr('data-focus-siguiente') || '').trim();
    }
    if (!selector) {
        return;
    }
    var $next = $(selector);
    if ($next.length) {
        setTimeout(function () {
            $next.trigger('focus').select();
        }, 0);
    }
}

function resolverPorCodigoCamion(codigo, $ctx, opciones) {
    opciones = opciones || {};
    var alertar = !!opciones.alertar;
    var $input = $ctx && $ctx.length ? $ctx.find('.codigocamion').first() : $();
    var cod = $.trim(codigo);

    if (cod === '') {
        limpiarCamionEnContexto($ctx);
        if (opciones.focusSiguiente) {
            focusSiguienteCamion($ctx, opciones);
        }
        return;
    }

    $.get(carpetaBase + '/ventas/leercamion/' + encodeURIComponent(cod))
        .done(function (data) {
            if (data && data.id) {
                if ($input.length) {
                    $input.removeData('camion-invalido-aviso');
                }
                aplicarCamionEnContexto($ctx, data);
                focusSiguienteCamion($ctx, opciones);
                return;
            }
            limpiarCamionEnContexto($ctx);
            if ($input.length) {
                $input.val(cod);
            }
            if (alertar) {
                avisarCamionInvalido($input);
            }
        })
        .fail(function () {
            limpiarCamionEnContexto($ctx);
            if ($input.length) {
                $input.val(cod);
            }
            if (alertar) {
                avisarCamionInvalido($input);
            }
        });
}

function abrirModalConsultaCamionDesdeInput($input) {
    camionModalAbriendo = true;
    ptrCamionContext = contextoCamionDesde($input);
    if (!ptrCamionContext.length) {
        ptrCamionContext = null;
    }
    $('#consultacamion').val('');
    $('#consultacamionModal').modal('show');
    buscar_datos_camion('');
}

function leerFilaCamionConsulta($link) {
    var $tr = $link.closest('tr');
    return {
        id: $.trim($tr.find('td.id').first().text()),
        codigo: $.trim($tr.find('td.codigo').first().text()),
        dominio: $.trim($tr.find('td.dominio').first().text()),
        habilitacion: $.trim($tr.find('td.habilitacion').first().text()),
        tipo: $.trim($tr.find('td.tipo').first().text()),
        cantidad_precinto: parseInt($.trim($tr.find('td.cantidad_precinto').first().text()), 10) || 0
    };
}

function elegirPrimeraFilaCamion() {
    var $first = $('#datoscamion .eligeconsultacamion').first();
    if ($first.length) {
        $first.trigger('click');
    }
}

function manejarF1CodigoCamionCapture(e) {
    if (!esTeclaF1Camion(e)) {
        return;
    }
    var target = e.target;
    if (!target || !target.classList || !target.classList.contains('codigocamion')) {
        return;
    }
    if (target.readOnly || target.disabled) {
        return;
    }
    if (modalConsultaCamionAbiertoOAbriendo()) {
        return;
    }
    e.preventDefault();
    e.stopImmediatePropagation();
    abrirModalConsultaCamionDesdeInput($(target));
}

function manejarEnterCodigoCamionCapture(e) {
    if (!(e && (e.key === 'Enter' || e.which === 13 || e.keyCode === 13))) {
        return;
    }
    var target = e.target;
    if (!target || !target.classList || !target.classList.contains('codigocamion')) {
        return;
    }
    if (target.readOnly || target.disabled) {
        return;
    }
    e.preventDefault();
    e.stopImmediatePropagation();
    var $input = $(target);
    var $ctx = contextoCamionDesde($input);
    resolverPorCodigoCamion($input.val(), $ctx.length ? $ctx : null, { alertar: true });
}

if (!window.__camionF1CaptureActivo) {
    document.addEventListener('keydown', manejarF1CodigoCamionCapture, true);
    document.addEventListener('keydown', manejarEnterCodigoCamionCapture, true);
    window.__camionF1CaptureActivo = true;
}

$(document).on('keyup', '#consultacamion', function () {
    buscar_datos_camion(String($(this).val() || '').trim());
});

$(document).on('keydown', '#consultacamion', function (e) {
    if (!(e.key === 'Enter' || e.which === 13 || e.keyCode === 13)) {
        return;
    }
    e.preventDefault();
    e.stopPropagation();
    elegirPrimeraFilaCamion();
});

function activa_eventos_consultacamion() {
    $('.consultacamion')
        .off('click.consultaCamion')
        .on('click.consultaCamion', function (event) {
            event.preventDefault();
            abrirModalConsultaCamionDesdeInput($(this));
        });

    $('#consultacamionModal')
        .off('shown.bs.modal.consultaCamion')
        .on('shown.bs.modal.consultaCamion', function () {
            camionModalAbriendo = false;
            var $input = $('#consultacamion');
            setTimeout(function () {
                $input.trigger('focus').select();
            }, 0);
        });

    $('#consultacamionModal')
        .off('hidden.bs.modal.consultaCamion')
        .on('hidden.bs.modal.consultaCamion', function () {
            camionModalAbriendo = false;
        });

    $('#aceptaconsultacamionModal')
        .off('click.consultaCamion')
        .on('click.consultaCamion', function () {
            $('#consultacamionModal').modal('hide');
        });

    $(document)
        .off('click.eligeconsultacamion')
        .on('click.eligeconsultacamion', '.eligeconsultacamion', function (event) {
            event.preventDefault();
            var data = leerFilaCamionConsulta($(this));
            var $ctx = ptrCamionContext && ptrCamionContext.length
                ? ptrCamionContext
                : $('.tm-camion-campo').first();
            $('#consultacamionModal').modal('hide');
            if (!data.id) {
                return;
            }
            aplicarCamionEnContexto($ctx, data);
            focusSiguienteCamion($ctx);
        });

    $(document)
        .off('input.camionCodigo', '.codigocamion')
        .on('input.camionCodigo', '.codigocamion', function () {
            $(this).removeData('camion-invalido-aviso');
        });

    $(document)
        .off('blur.camionCodigo', '.codigocamion')
        .on('blur.camionCodigo', '.codigocamion', function () {
            if (modalConsultaCamionAbiertoOAbriendo()) {
                return;
            }
            var $ctx = contextoCamionDesde($(this));
            resolverPorCodigoCamion($(this).val(), $ctx.length ? $ctx : null, { alertar: false });
        });
}

window.activa_eventos_consultacamion = activa_eventos_consultacamion;
window.aplicarCamionEnContexto = aplicarCamionEnContexto;
window.limpiarCamionEnContexto = limpiarCamionEnContexto;
window.resolverPorCodigoCamion = resolverPorCodigoCamion;

$(function () {
    if (typeof activa_eventos_consultacamion === 'function') {
        activa_eventos_consultacamion();
    }
    $('.tm-camion-campo').each(function () {
        var $ctx = $(this);
        actualizarLinkEditarCamion($ctx, parseInt($ctx.find('.camion_id').val(), 10) || 0);
    });
});
