/* global carpetaBase */
var ptrCampoLiquidacionSueldos = $();
var liquidacionSueldosBusquedaTimer = null;
var liquidacionSueldosModalAbriendose = false;

function campoLiquidacionSueldosDesde($el) {
    return $($el).closest('.tm-liquidacion-sueldos-campo');
}

function modalLiquidacionSueldosAbierto() {
    var $modal = $('#consultaliquidacion_sueldosModal');
    return $modal.hasClass('show') || liquidacionSueldosModalAbriendose;
}

function limpiarLiquidacionSueldos($campo, mantenerNumero, opciones) {
    if (!$campo || !$campo.length) {
        return;
    }
    opciones = opciones || {};
    $campo.find('.liquidacion_sueldos_id').val('');
    if (!mantenerNumero) {
        $campo.find('.numeroliquidacion_sueldos').val('').removeData('liquidacion-invalida');
    }
    $campo.find('.nombreliquidacion_sueldos').val('');
    if (opciones.dispararChange) {
        $campo.find('.liquidacion_sueldos_id').trigger('change.liquidacionSueldos');
    }
}

function aplicarLiquidacionSueldos($campo, data) {
    if (!$campo.length || !data || !data.id) {
        return;
    }
    $campo.find('.liquidacion_sueldos_id').val(data.id);
    $campo.find('.numeroliquidacion_sueldos').val(data.numero).removeData('liquidacion-invalida');
    $campo.find('.nombreliquidacion_sueldos').val(data.descripcion || '');
    // Alinear selector simple o checkboxes multiempresa con la liq elegida.
    if (data.empresa_id) {
        var selector = $campo.data('empresa-selector') || $campo.data('empresaSelector') || '#empresa_id';
        var $empresa = $(selector);
        if ($empresa.length && String($empresa.val() || '') !== String(data.empresa_id)) {
            $empresa.val(String(data.empresa_id)).trigger('change');
        }
        var $checks = $('input[name="empresa_ids[]"]');
        if ($checks.filter(':checkbox').length) {
            var ya = empresasIdsSeleccionadasReporte().indexOf(parseInt(data.empresa_id, 10)) !== -1;
            if (!ya && empresasIdsSeleccionadasReporte().length <= 1) {
                $checks.filter(':checkbox').prop('checked', false);
                $checks.filter(':checkbox[value="' + data.empresa_id + '"]').prop('checked', true).trigger('change');
            }
        }
    }
    $campo.find('.liquidacion_sueldos_id').trigger('change.liquidacionSueldos');
}

window.limpiarLiquidacionSueldos = limpiarLiquidacionSueldos;
window.aplicarLiquidacionSueldos = aplicarLiquidacionSueldos;
window.resolverLiquidacionSueldos = resolverLiquidacionSueldos;

function avisarLiquidacionSueldos(mensaje, $input) {
    var mostrar = function () {
        window.setTimeout(function () {
            window.alert(mensaje);
            if ($input && $input.length) {
                $input.focus();
            }
        }, 0);
    };
    var $modal = $('#consultaliquidacion_sueldosModal');
    if ($modal.hasClass('show') || liquidacionSueldosModalAbriendose) {
        $modal.one('hidden.bs.modal', mostrar).modal('hide');
    } else {
        mostrar();
    }
}

function empresasIdsSeleccionadasReporte() {
    var ids = [];
    $('input[name="empresa_ids[]"]').each(function () {
        var $el = $(this);
        if ($el.is(':checkbox') && !$el.is(':checked')) {
            return;
        }
        var v = parseInt($el.val(), 10) || 0;
        if (v > 0 && ids.indexOf(v) === -1) {
            ids.push(v);
        }
    });
    return ids;
}

function empresaLiquidacionSueldos($campo) {
    if ($campo && $campo.length) {
        var directo = $campo.attr('data-empresa-id') || $campo.data('empresaId') || $campo.data('empresa-id');
        if (directo !== undefined && directo !== null && String(directo).trim() !== '') {
            return String(directo).trim();
        }
        var selector = $campo.data('empresa-selector') || $campo.data('empresaSelector') || '';
        if (selector) {
            var desdeSelector = $(selector).val() || '';
            if (String(desdeSelector).trim() !== '') {
                return String(desdeSelector).trim();
            }
        }
    }
    var desdeId = $('#empresa_id').val() || '';
    if (String(desdeId).trim() !== '') {
        return String(desdeId).trim();
    }
    // Selector múltiple contable: una sola empresa tildeada → se usa para resolver el nro.
    var ids = empresasIdsSeleccionadasReporte();
    if (ids.length === 1) {
        return String(ids[0]);
    }
    return '';
}

function resolverLiquidacionSueldos($campo, id, numero, alertar) {
    if (!$campo.length) {
        return;
    }
    var url;
    if (parseInt(id || 0, 10) > 0) {
        url = carpetaBase + '/sueldos/liquidacion/leer/' + parseInt(id, 10);
    } else if (String(numero || '').trim() !== '') {
        url = carpetaBase + '/sueldos/liquidacion/leerpornumero/' +
            encodeURIComponent(String(numero).trim());
    } else {
        limpiarLiquidacionSueldos($campo, false, { dispararChange: true });
        return;
    }

    limpiarLiquidacionSueldos($campo, true);
    $.get(url, { empresa_id: empresaLiquidacionSueldos($campo) })
        .done(function (data) {
            aplicarLiquidacionSueldos($campo, data);
        })
        .fail(function (xhr) {
            var $input = $campo.find('.numeroliquidacion_sueldos');
            limpiarLiquidacionSueldos($campo, false, { dispararChange: true });
            $input.data('liquidacion-invalida', 1);
            if (!alertar) {
                return;
            }
            var mensaje = 'No se encontró la liquidación indicada.';
            if (xhr && xhr.status === 409 && xhr.responseJSON && xhr.responseJSON.error) {
                mensaje = xhr.responseJSON.error;
            }
            avisarLiquidacionSueldos(mensaje, $input);
        });
}

function buscarLiquidacionesSueldos(consulta) {
    $.ajax({
        url: carpetaBase + '/sueldos/liquidacion/consultaliquidacion',
        type: 'POST',
        dataType: 'json',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') ||
                ($('input[name="_token"]').first().val() || '')
        },
        data: {
            consulta: consulta || '',
            empresa_id: empresaLiquidacionSueldos(ptrCampoLiquidacionSueldos),
            empresa_ids: empresasIdsSeleccionadasReporte()
        }
    }).done(function (respuesta) {
        $('#datosliquidacion_sueldos').html(respuesta.data || '');
    });
}

function abrirModalLiquidacionSueldos($campo) {
    ptrCampoLiquidacionSueldos = $campo;
    liquidacionSueldosModalAbriendose = true;
    $('#consultaliquidacion_sueldos').val('');
    buscarLiquidacionesSueldos('');
    $('#consultaliquidacion_sueldosModal').modal('show');
}

$(document)
    .on('click', '.consultaliquidacion_sueldos', function (e) {
        e.preventDefault();
        abrirModalLiquidacionSueldos(campoLiquidacionSueldosDesde($(this)));
    })
    .on('click', '.eligeconsultaliquidacion_sueldos', function (e) {
        e.preventDefault();
        var id = $.trim($(this).closest('tr').find('.liquidacion_id').text());
        $('#consultaliquidacion_sueldosModal').modal('hide');
        resolverLiquidacionSueldos(ptrCampoLiquidacionSueldos, id, null, true);
    })
    .on('input', '.numeroliquidacion_sueldos', function () {
        $(this).removeData('liquidacion-invalida');
    })
    .on('blur', '.numeroliquidacion_sueldos', function () {
        if (!modalLiquidacionSueldosAbierto() && !$(this).data('liquidacion-invalida')) {
            resolverLiquidacionSueldos(
                campoLiquidacionSueldosDesde($(this)),
                null,
                $(this).val(),
                false
            );
        }
    })
    .on('input', '#consultaliquidacion_sueldos', function () {
        window.clearTimeout(liquidacionSueldosBusquedaTimer);
        var valor = $(this).val();
        liquidacionSueldosBusquedaTimer = window.setTimeout(function () {
            buscarLiquidacionesSueldos(valor);
        }, 250);
    });

document.addEventListener('keydown', function (e) {
    var $target = $(e.target);
    if ((e.key === 'F1' || e.keyCode === 112) && $target.hasClass('numeroliquidacion_sueldos')) {
        e.preventDefault();
        e.stopPropagation();
        abrirModalLiquidacionSueldos(campoLiquidacionSueldosDesde($target));
        return;
    }
    if (!(e.key === 'Enter' || e.keyCode === 13)) {
        return;
    }
    if (e.target.id === 'consultaliquidacion_sueldos') {
        e.preventDefault();
        var $primera = $('#datosliquidacion_sueldos .eligeconsultaliquidacion_sueldos').first();
        if ($primera.length) {
            $primera.trigger('click');
        }
        return;
    }
    if ($target.hasClass('numeroliquidacion_sueldos')) {
        e.preventDefault();
        e.stopPropagation();
        resolverLiquidacionSueldos(
            campoLiquidacionSueldosDesde($target),
            null,
            $target.val(),
            true
        );
    }
}, true);

$('#consultaliquidacion_sueldosModal')
    .on('show.bs.modal', function () {
        liquidacionSueldosModalAbriendose = true;
    })
    .on('shown.bs.modal', function () {
        liquidacionSueldosModalAbriendose = false;
        $('#consultaliquidacion_sueldos').focus();
    })
    .on('hidden.bs.modal', function () {
        liquidacionSueldosModalAbriendose = false;
    });

function actualizarLiquidacionFijaSuscripcion($select) {
    var $form = $select.closest('form');
    var $contenedor = $form.find('[data-liquidacion-fija-container]');
    var fija = $select.val() === 'fijo';
    $contenedor.toggleClass('d-none', !fija);
    $contenedor.find('.liquidacion_sueldos_id').prop('required', fija);
    if (!fija) {
        limpiarLiquidacionSueldos($contenedor.find('.tm-liquidacion-sueldos-campo'), false);
    }
}

$(document)
    .on('change', '.periodo-relativo-suscripcion', function () {
        actualizarLiquidacionFijaSuscripcion($(this));
    });

$(function () {
    $('.periodo-relativo-suscripcion').each(function () {
        actualizarLiquidacionFijaSuscripcion($(this));
    });
});
