/* global carpetaBase */
var ptrCodigosenasaContext;
var codigosenasaModalAbriendo = false;

function esTeclaF1Codigosenasa(e) {
    return e && (e.key === 'F1' || e.code === 'F1' || e.keyCode === 112);
}

function modalConsultaCodigosenasaAbierto() {
    var m = document.getElementById('consultacodigosenasaModal');
    return !!(m && (m.classList.contains('show') || m.classList.contains('in')));
}

function modalConsultaCodigosenasaAbiertoOAbriendo() {
    return codigosenasaModalAbriendo || modalConsultaCodigosenasaAbierto();
}

function contextoCodigosenasaDesde($el) {
    var $ctx = $($el).closest('.tm-codigosenasa-campo');
    return $ctx.length ? $ctx : $();
}

function actualizarLinkEditarCodigosenasa($ctx, senasaId) {
    if (!$ctx || !$ctx.length) {
        return;
    }
    var $link = $ctx.find('.btn-link-editar-codigosenasa');
    if (!$link.length) {
        return;
    }
    var id = parseInt(senasaId, 10) || 0;
    if (id > 0) {
        $link
            .attr('href', carpetaBase + '/stock/codigosenasa/' + id + '/editar?origen=modal_consulta&vista=consulta')
            .removeClass('d-none');
    } else {
        $link.attr('href', '#').addClass('d-none');
    }
}

function limpiarCodigosenasaEnContexto($ctx) {
    if (!$ctx || !$ctx.length) {
        return;
    }
    $ctx.find('.codigosenasa_id').first().val('');
    $ctx.find('.codigocodigosenasa').first().val('');
    $ctx.find('.descripcioncodigosenasa').first().val('');
    actualizarLinkEditarCodigosenasa($ctx, 0);
}

function aplicarCodigosenasaEnContexto($ctx, data) {
    if (!$ctx || !$ctx.length || !data || !data.id) {
        return;
    }
    $ctx.find('.codigosenasa_id').first().val(data.id);
    $ctx.find('.codigocodigosenasa').first().val(data.codigo != null ? data.codigo : '');
    $ctx.find('.descripcioncodigosenasa').first().val(data.nombre != null ? data.nombre : '');
    actualizarLinkEditarCodigosenasa($ctx, data.id);
}

function parsearHtmlConsultaCodigosenasa(respuesta) {
    var resp = String(respuesta || '').replace(/\\/g, '');
    try {
        var parsed = JSON.parse(resp);
        return parsed.data || '';
    } catch (e) {
        return resp;
    }
}

function buscar_datos_codigosenasa(consulta) {
    $.ajax({
        url: carpetaBase + '/stock/codigosenasa/consultacodigosenasa',
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
            $('#datoscodigosenasa').html(parsearHtmlConsultaCodigosenasa(respuesta));
        })
        .fail(function () {
            $('#datoscodigosenasa').html('<tr><td colspan="6">Error al consultar c&oacute;digos SENASA</td></tr>');
        });
}

function avisarCodigosenasaInvalido($input) {
    if (!$input || !$input.length) {
        return;
    }
    if ($input.data('codigosenasa-invalido-aviso')) {
        return;
    }
    $input.data('codigosenasa-invalido-aviso', 1);
    var $modal = $('#consultacodigosenasaModal');
    if ($modal.hasClass('show') || $modal.hasClass('in')) {
        $modal.modal('hide');
    }
    setTimeout(function () {
        alert('No se encontr\u00f3 el c\u00f3digo SENASA indicado.');
        $input.trigger('focus').select();
    }, 0);
}

function focusSiguienteCodigosenasa($ctx, opciones) {
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

function resolverPorCodigoCodigosenasa(codigo, $ctx, opciones) {
    opciones = opciones || {};
    var alertar = !!opciones.alertar;
    var $input = $ctx && $ctx.length ? $ctx.find('.codigocodigosenasa').first() : $();
    var cod = $.trim(codigo);

    if (cod === '') {
        limpiarCodigosenasaEnContexto($ctx);
        if (opciones.focusSiguiente) {
            focusSiguienteCodigosenasa($ctx, opciones);
        }
        return;
    }

    $.get(carpetaBase + '/stock/leercodigosenasa/' + encodeURIComponent(cod))
        .done(function (data) {
            if (data && data.id) {
                if ($input.length) {
                    $input.removeData('codigosenasa-invalido-aviso');
                }
                aplicarCodigosenasaEnContexto($ctx, data);
                focusSiguienteCodigosenasa($ctx, opciones);
                return;
            }
            limpiarCodigosenasaEnContexto($ctx);
            if ($input.length) {
                $input.val(cod);
            }
            if (alertar) {
                avisarCodigosenasaInvalido($input);
            }
        })
        .fail(function () {
            limpiarCodigosenasaEnContexto($ctx);
            if ($input.length) {
                $input.val(cod);
            }
            if (alertar) {
                avisarCodigosenasaInvalido($input);
            }
        });
}

function abrirModalConsultaCodigosenasaDesdeInput($input) {
    codigosenasaModalAbriendo = true;
    ptrCodigosenasaContext = contextoCodigosenasaDesde($input);
    if (!ptrCodigosenasaContext.length) {
        ptrCodigosenasaContext = null;
    }
    $('#consultacodigosenasa').val('');
    $('#consultacodigosenasaModal').modal('show');
    buscar_datos_codigosenasa('');
}

function leerFilaCodigosenasaConsulta($link) {
    var $tr = $link.closest('tr');
    return {
        id: $.trim($tr.find('td.id').first().text()),
        codigo: $.trim($tr.find('td.codigo').first().text()),
        nombre: $.trim($tr.find('td.nombre').first().text()),
        registro: $.trim($tr.find('td.registro').first().text()),
        prefijo: $.trim($tr.find('td.prefijo').first().text())
    };
}

function elegirPrimeraFilaCodigosenasa() {
    var $first = $('#datoscodigosenasa .eligeconsultacodigosenasa').first();
    if ($first.length) {
        $first.trigger('click');
    }
}

function manejarF1CodigoCodigosenasaCapture(e) {
    if (!esTeclaF1Codigosenasa(e)) {
        return;
    }
    var target = e.target;
    if (!target || !target.classList || !target.classList.contains('codigocodigosenasa')) {
        return;
    }
    if (target.readOnly || target.disabled) {
        return;
    }
    if (modalConsultaCodigosenasaAbiertoOAbriendo()) {
        return;
    }
    e.preventDefault();
    e.stopImmediatePropagation();
    abrirModalConsultaCodigosenasaDesdeInput($(target));
}

function manejarEnterCodigoCodigosenasaCapture(e) {
    if (!(e && (e.key === 'Enter' || e.which === 13 || e.keyCode === 13))) {
        return;
    }
    var target = e.target;
    if (!target || !target.classList || !target.classList.contains('codigocodigosenasa')) {
        return;
    }
    if (target.readOnly || target.disabled) {
        return;
    }
    e.preventDefault();
    e.stopImmediatePropagation();
    var $input = $(target);
    var $ctx = contextoCodigosenasaDesde($input);
    resolverPorCodigoCodigosenasa($input.val(), $ctx.length ? $ctx : null, { alertar: true });
}

if (!window.__codigosenasaF1CaptureActivo) {
    document.addEventListener('keydown', manejarF1CodigoCodigosenasaCapture, true);
    document.addEventListener('keydown', manejarEnterCodigoCodigosenasaCapture, true);
    window.__codigosenasaF1CaptureActivo = true;
}

$(document).on('keyup', '#consultacodigosenasa', function () {
    buscar_datos_codigosenasa(String($(this).val() || '').trim());
});

$(document).on('keydown', '#consultacodigosenasa', function (e) {
    if (!(e.key === 'Enter' || e.which === 13 || e.keyCode === 13)) {
        return;
    }
    e.preventDefault();
    e.stopPropagation();
    elegirPrimeraFilaCodigosenasa();
});

function activa_eventos_consultacodigosenasa() {
    $('.consultacodigosenasa')
        .off('click.consultaCodigosenasa')
        .on('click.consultaCodigosenasa', function (event) {
            event.preventDefault();
            abrirModalConsultaCodigosenasaDesdeInput($(this));
        });

    $('#consultacodigosenasaModal')
        .off('shown.bs.modal.consultaCodigosenasa')
        .on('shown.bs.modal.consultaCodigosenasa', function () {
            codigosenasaModalAbriendo = false;
            var $input = $('#consultacodigosenasa');
            setTimeout(function () {
                $input.trigger('focus').select();
            }, 0);
        });

    $('#consultacodigosenasaModal')
        .off('hidden.bs.modal.consultaCodigosenasa')
        .on('hidden.bs.modal.consultaCodigosenasa', function () {
            codigosenasaModalAbriendo = false;
        });

    $('#aceptaconsultacodigosenasaModal')
        .off('click.consultaCodigosenasa')
        .on('click.consultaCodigosenasa', function () {
            $('#consultacodigosenasaModal').modal('hide');
        });

    $(document)
        .off('click.eligeconsultacodigosenasa')
        .on('click.eligeconsultacodigosenasa', '.eligeconsultacodigosenasa', function (event) {
            event.preventDefault();
            var data = leerFilaCodigosenasaConsulta($(this));
            var $ctx = ptrCodigosenasaContext && ptrCodigosenasaContext.length
                ? ptrCodigosenasaContext
                : $('.tm-codigosenasa-campo').first();
            $('#consultacodigosenasaModal').modal('hide');
            if (!data.id) {
                return;
            }
            aplicarCodigosenasaEnContexto($ctx, data);
            focusSiguienteCodigosenasa($ctx);
        });

    $(document)
        .off('input.codigosenasaCodigo', '.codigocodigosenasa')
        .on('input.codigosenasaCodigo', '.codigocodigosenasa', function () {
            $(this).removeData('codigosenasa-invalido-aviso');
        });

    $(document)
        .off('blur.codigosenasaCodigo', '.codigocodigosenasa')
        .on('blur.codigosenasaCodigo', '.codigocodigosenasa', function () {
            if (modalConsultaCodigosenasaAbiertoOAbriendo()) {
                return;
            }
            var $ctx = contextoCodigosenasaDesde($(this));
            resolverPorCodigoCodigosenasa($(this).val(), $ctx.length ? $ctx : null, { alertar: false });
        });
}

window.activa_eventos_consultacodigosenasa = activa_eventos_consultacodigosenasa;
window.aplicarCodigosenasaEnContexto = aplicarCodigosenasaEnContexto;
window.limpiarCodigosenasaEnContexto = limpiarCodigosenasaEnContexto;
window.resolverPorCodigoCodigosenasa = resolverPorCodigoCodigosenasa;

$(function () {
    if (typeof activa_eventos_consultacodigosenasa === 'function') {
        activa_eventos_consultacodigosenasa();
    }
    $('.tm-codigosenasa-campo').each(function () {
        var $ctx = $(this);
        actualizarLinkEditarCodigosenasa($ctx, parseInt($ctx.find('.codigosenasa_id').val(), 10) || 0);
    });
});
