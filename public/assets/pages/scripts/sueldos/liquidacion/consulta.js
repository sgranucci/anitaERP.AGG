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

function limpiarLiquidacionSueldos($campo, mantenerNumero) {
    $campo.find('.liquidacion_sueldos_id').val('');
    if (!mantenerNumero) {
        $campo.find('.numeroliquidacion_sueldos').val('').removeData('liquidacion-invalida');
    }
    $campo.find('.nombreliquidacion_sueldos').val('');
}

function aplicarLiquidacionSueldos($campo, data) {
    if (!$campo.length || !data || !data.id) {
        return;
    }
    $campo.find('.liquidacion_sueldos_id').val(data.id);
    $campo.find('.numeroliquidacion_sueldos').val(data.numero).removeData('liquidacion-invalida');
    $campo.find('.nombreliquidacion_sueldos').val(data.descripcion || '');
}

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

function empresaLiquidacionSueldos($campo) {
    var selector = $campo.data('empresa-selector') || '#empresa_id';
    return $(selector).val() || '';
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
        limpiarLiquidacionSueldos($campo, false);
        return;
    }

    limpiarLiquidacionSueldos($campo, true);
    $.get(url, { empresa_id: empresaLiquidacionSueldos($campo) })
        .done(function (data) {
            aplicarLiquidacionSueldos($campo, data);
        })
        .fail(function () {
            var $input = $campo.find('.numeroliquidacion_sueldos');
            limpiarLiquidacionSueldos($campo, false);
            $input.data('liquidacion-invalida', 1);
            if (alertar) {
                avisarLiquidacionSueldos('No se encontró la liquidación indicada.', $input);
            }
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
            empresa_id: empresaLiquidacionSueldos(ptrCampoLiquidacionSueldos)
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
