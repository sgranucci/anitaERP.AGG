// Localidad de lugares de entrega (ABM cliente): modal por fila, no combo en cascada.
// Lista a la izquierda + detalle a la derecha (mismo patrón que layouts del reporte definible).

function limpiarLocalidadEntrega($tr) {
    if (window.__sincronizandoProvinciaDesdeLocalidad) {
        return;
    }
    if (!$tr || !$tr.length) {
        return;
    }
    var $campo = $tr.find('.tm-localidad-campo').first();
    if (typeof aplicarLocalidadEnCampo === 'function' && $campo.length) {
        aplicarLocalidadEnCampo($campo, null);
        $tr.find('.codigospostales').val('');
        refrescarResumenEntrega($tr);
        return;
    }
    $tr.find('.localidad_id, .localidad_id_previa, .localidad_id_previas').val('');
    $tr.find('.codigolocalidad, .nombrelocalidad, .desc_localidad, .desc_localidades').val('');
    $tr.find('.codigospostales').val('');
    refrescarResumenEntrega($tr);
}

function escapeHtmlEntrega(texto) {
    return $('<div>').text(texto == null ? '' : String(texto)).html();
}

function textoResumenEntrega($item) {
    var nombre = $.trim($item.find('input[name="nombres[]"]').val() || '');
    var localidad = $.trim($item.find('.nombrelocalidad').val() || '');

    return {
        nombre: nombre !== '' ? nombre : '(sin nombre)',
        localidad: localidad !== '' ? localidad : '—',
    };
}

function refrescarResumenEntrega($item) {
    if (!$item || !$item.length) {
        return;
    }
    var idx = $item.attr('data-entrega-idx');
    var t = textoResumenEntrega($item);
    var $fila = $('#tbody-entregas-resumen tr.entrega-resumen[data-entrega-idx="' + idx + '"]');
    $fila.find('.entrega-resumen-nombre').text(t.nombre);
    $fila.find('.entrega-resumen-localidad').text(t.localidad);
    if ($item.hasClass('activa')) {
        $('#entrega-detalle-titulo').text(t.nombre === '(sin nombre)' ? 'Datos del lugar' : t.nombre);
    }
}

function reconstruirResumenEntregas() {
    var $tb = $('#tbody-entregas-resumen');
    if (!$tb.length) {
        return;
    }
    $tb.empty();
    $('#tbody-tabla .item-entrega').each(function (i) {
        var $item = $(this);
        $item.attr('data-entrega-idx', i);
        $item.find('.iientrega').val(i + 1);
        var t = textoResumenEntrega($item);
        $tb.append(
            '<tr class="entrega-resumen" data-entrega-idx="' + i + '">' +
                '<td class="entrega-resumen-nro text-center">' + (i + 1) + '</td>' +
                '<td class="entrega-resumen-nombre">' + escapeHtmlEntrega(t.nombre) + '</td>' +
                '<td class="entrega-resumen-localidad">' + escapeHtmlEntrega(t.localidad) + '</td>' +
                '<td class="text-nowrap text-center">' +
                    '<button type="button" title="Quitar este lugar" class="btn-accion-tabla eliminar tooltipsC">' +
                        '<i class="fa fa-times-circle text-danger"></i>' +
                    '</button>' +
                '</td>' +
            '</tr>'
        );
    });

    var hay = $('#tbody-tabla .item-entrega').length > 0;
    $('#entregas-vacio').toggle(!hay);
    $('#tabla-entregas-resumen').toggle(hay);
    $('#entrega-detalle-vacio').toggle(!hay);
    $('#panel-entrega-detalle-campos').toggle(hay);
}

function seleccionarEntrega(idx) {
    var $items = $('#tbody-tabla .item-entrega');
    if (!$items.length) {
        $('#entrega-detalle-titulo').text('Datos del lugar');
        return;
    }
    if (idx < 0) {
        idx = 0;
    }
    if (idx >= $items.length) {
        idx = $items.length - 1;
    }

    $items.removeClass('activa');
    $('#tbody-entregas-resumen .entrega-resumen').removeClass('activa');

    var $item = $items.eq(idx);
    $item.addClass('activa');
    $('#tbody-entregas-resumen .entrega-resumen[data-entrega-idx="' + idx + '"]').addClass('activa');
    refrescarResumenEntrega($item);
}

function actualizaRenglones() {
    reconstruirResumenEntregas();
    var $activa = $('#tbody-tabla .item-entrega.activa');
    if ($activa.length) {
        seleccionarEntrega(parseInt($activa.attr('data-entrega-idx'), 10) || 0);
        return;
    }
    if ($('#tbody-tabla .item-entrega').length) {
        seleccionarEntrega(0);
    } else {
        $('#entrega-detalle-titulo').text('Datos del lugar');
    }
}

function agregaRenglonEntrega() {
    var renglon = $('#template-renglon').html();
    $('#tbody-tabla').append(renglon);
    reconstruirResumenEntregas();
    activaEventoEntrega();
    if (typeof activa_eventos === 'function') {
        activa_eventos(false);
    }
    seleccionarEntrega($('#tbody-tabla .item-entrega').length - 1);
    $('#tbody-tabla .item-entrega.activa').find('input[name="nombres[]"]').trigger('focus');
}

function borraRenglonEntrega($origen) {
    var $resumen = $origen.closest('.entrega-resumen');
    var $item = $origen.closest('.item-entrega');
    var idx = 0;

    if ($resumen.length) {
        idx = parseInt($resumen.attr('data-entrega-idx'), 10) || 0;
        $('#tbody-tabla .item-entrega').eq(idx).remove();
    } else if ($item.length) {
        idx = $('#tbody-tabla .item-entrega').index($item);
        $item.remove();
    }

    reconstruirResumenEntregas();
    activaEventoEntrega();
    var n = $('#tbody-tabla .item-entrega').length;
    if (n > 0) {
        seleccionarEntrega(Math.min(idx, n - 1));
    } else {
        $('#entrega-detalle-titulo').text('Datos del lugar');
    }
}

function activaEventoEntrega() {
    $(document)
        .off('change.clienteEntrega', '#tab-lugares-entrega .tm-provincia-campo:not(.tm-provincia-iibb-campo) .provincia_id')
        .on('change.clienteEntrega', '#tab-lugares-entrega .tm-provincia-campo:not(.tm-provincia-iibb-campo) .provincia_id', function () {
            limpiarLocalidadEntrega($(this).closest('.item-entrega'));
        });

    if (typeof activa_eventos_consultazonavta === 'function') {
        activa_eventos_consultazonavta();
    }
}

function iniciarUiEntregasCliente() {
    if (!$('#tab-lugares-entrega').length) {
        return;
    }

    $(document)
        .off('click.clienteEntregaResumen', '#tbody-entregas-resumen .entrega-resumen')
        .on('click.clienteEntregaResumen', '#tbody-entregas-resumen .entrega-resumen', function (e) {
            if ($(e.target).closest('.eliminar').length) {
                return;
            }
            seleccionarEntrega(parseInt($(this).attr('data-entrega-idx'), 10) || 0);
        });

    $(document)
        .off('input.clienteEntregaResumen change.clienteEntregaResumen', '#tab-lugares-entrega .item-entrega input')
        .on('input.clienteEntregaResumen change.clienteEntregaResumen', '#tab-lugares-entrega .item-entrega input', function () {
            refrescarResumenEntrega($(this).closest('.item-entrega'));
        });

    $('#consultalocalidadModal, #consultaprovinciaModal, #consultazonavtaModal, #consultatransporteModal')
        .off('hidden.bs.modal.clienteEntregaResumen')
        .on('hidden.bs.modal.clienteEntregaResumen', function () {
            refrescarResumenEntrega($('#tbody-tabla .item-entrega.activa'));
        });

    actualizaRenglones();
}

$(function () {
    activaEventoEntrega();
    iniciarUiEntregasCliente();
});
