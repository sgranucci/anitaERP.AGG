$(function () {
    $('#agrega_renglon_centrocosto').on('click', function (event) {
        event.preventDefault();
        $('#tbody-centrocosto-table').append($('#template-renglon-centrocosto').html());
        numerar('.iicentrocosto');
    });

    $(document).on('click', '.eliminar_centrocosto', function (event) {
        event.preventDefault();
        var $body = $('#tbody-centrocosto-table');
        if ($body.find('tr.item-centrocosto').length <= 1) {
            limpiarFila($(this).closest('tr'));
            return;
        }
        $(this).closest('tr').remove();
        numerar('.iicentrocosto');
    });

    $('#agrega_renglon_concepto_ivacompra').on('click', function (event) {
        event.preventDefault();
        $('#tbody-concepto-ivacompra-table').append($('#template-renglon-concepto_ivacompra').html());
        numerar('.iiconcepto_ivacompra');
    });

    $(document).on('click', '.eliminar_concepto_ivacompra', function (event) {
        event.preventDefault();
        var $body = $('#tbody-concepto-ivacompra-table');
        if ($body.find('tr.item-concepto_ivacompra').length <= 1) {
            limpiarFila($(this).closest('tr'));
            return;
        }
        $(this).closest('tr').remove();
        numerar('.iiconcepto_ivacompra');
    });

    activarConceptoCatalogo();
});

function numerar(selector) {
    var item = 1;
    $(selector).each(function () {
        $(this).val(item++);
    });
}

function limpiarFila($tr) {
    $tr.find('input').not('[readonly]').val('');
    $tr.find('.centrocosto_id, .concepto_ivacompra_id, .descripcioncentrocosto, .nombre_concepto_ivacompra').val('');
    $tr.find('.codigocentrocosto, .codigo_concepto_ivacompra').val('').removeAttr('data-concepto-invalido').removeAttr('data-codigo-resuelto');
    $tr.find('.btn-link-editar-centrocosto, .btn-link-editar-concepto-tipo').addClass('d-none').attr('href', '#');
}

var ptrFilaConcepto = null;
var modalConceptoAbriendo = false;

function carpetaConceptoTipo() {
    return (typeof window.carpetaBase === 'string') ? window.carpetaBase.replace(/\/$/, '') : '';
}

function csrfConceptoTipo() {
    return $('meta[name="csrf-token"]').attr('content') || '';
}

function modalConceptoTipoAbierto() {
    var modal = document.getElementById('consultaconcepto_ivacompraModal');
    return modalConceptoAbriendo || (modal && modal.classList.contains('show'));
}

function asignarConceptoEnFila($fila, data) {
    if (!$fila || !$fila.length || !data || !data.id) {
        return;
    }
    $fila.find('.concepto_ivacompra_id').val(String(data.id));
    var $codigo = $fila.find('.codigo_concepto_ivacompra');
    $codigo.val(String(data.codigo || ''));
    $codigo.removeAttr('data-concepto-invalido');
    $codigo.attr('data-codigo-resuelto', String(data.codigo || ''));
    $fila.find('.nombre_concepto_ivacompra').val(String(data.nombre || ''));
    var $link = $fila.find('.btn-link-editar-concepto-tipo');
    if ($link.length) {
        $link.removeClass('d-none').attr(
            'href',
            carpetaConceptoTipo() + '/compras/concepto_ivacompra/' + data.id + '/editar?origen=modal_consulta&vista=consulta'
        );
    }
}

function buscarConceptoCatalogo(consulta) {
    $('#datosconcepto_ivacompra').html('<tr><td colspan="5" class="text-muted">Buscando…</td></tr>');
    $.ajax({
        url: carpetaConceptoTipo() + '/compras/concepto_ivacompra/consulta',
        type: 'POST',
        dataType: 'json',
        headers: { 'X-CSRF-TOKEN': csrfConceptoTipo() },
        data: { catalogo: 1, consulta: consulta || '' }
    }).done(function (respuesta) {
        var html = (respuesta && respuesta.data) ? respuesta.data : '';
        $('#datosconcepto_ivacompra').html(html || '<tr><td colspan="5" class="text-muted">Sin resultados</td></tr>');
    }).fail(function () {
        $('#datosconcepto_ivacompra').html('<tr><td colspan="5" class="text-danger">Error al buscar conceptos.</td></tr>');
    });
}

function abrirModalConcepto($fila) {
    ptrFilaConcepto = $fila;
    modalConceptoAbriendo = true;
    $('#consultaconcepto_ivacompra').val('');
    $('#consultaconcepto_ivacompra-aviso').text('Catálogo de conceptos de IVA compras.');
    $('#datosconcepto_ivacompra').html('');
    $('#consultaconcepto_ivacompraModal').modal('show');
}

function resolverConceptoPorCodigo($input, alertar) {
    if (modalConceptoTipoAbierto()) {
        return;
    }
    var $fila = $input.closest('tr.item-concepto_ivacompra');
    var codigo = String($input.val() || '').trim();
    if (!codigo) {
        $fila.find('.concepto_ivacompra_id, .nombre_concepto_ivacompra').val('');
        $input.removeAttr('data-concepto-invalido').removeAttr('data-codigo-resuelto');
        $fila.find('.btn-link-editar-concepto-tipo').addClass('d-none').attr('href', '#');
        return;
    }
    if ($input.attr('data-concepto-invalido') === codigo) {
        return;
    }
    if ($input.attr('data-codigo-resuelto') === codigo && parseInt(String($fila.find('.concepto_ivacompra_id').val() || '0'), 10) > 0) {
        return;
    }

    $.ajax({
        url: carpetaConceptoTipo() + '/compras/concepto_ivacompra/resolver',
        type: 'POST',
        dataType: 'json',
        headers: { 'X-CSRF-TOKEN': csrfConceptoTipo() },
        data: { catalogo: 1, valor: codigo }
    }).done(function (data) {
        if (data && data.ok && data.id > 0) {
            asignarConceptoEnFila($fila, data);
            return;
        }
        $input.attr('data-concepto-invalido', codigo);
        if (alertar) {
            avisarConcepto((data && data.mensaje) ? data.mensaje : 'No se encontró el concepto.');
            $input.trigger('focus');
        }
    }).fail(function (xhr) {
        $input.attr('data-concepto-invalido', codigo);
        if (alertar) {
            var msg = 'No se encontró el concepto.';
            if (xhr && xhr.responseJSON && xhr.responseJSON.mensaje) {
                msg = xhr.responseJSON.mensaje;
            }
            avisarConcepto(msg);
            $input.trigger('focus');
        }
    });
}

function avisarConcepto(mensaje) {
    window.setTimeout(function () {
        alert(mensaje);
    }, 0);
}

function activarConceptoCatalogo() {
    if (!$('#consultaconcepto_ivacompraModal').length) {
        return;
    }

    $(document).on('mousedown', '#form-general .consultaconcepto-tipo', function () {
        modalConceptoAbriendo = true;
    });

    $(document).on('click', '#form-general .consultaconcepto-tipo', function (event) {
        event.preventDefault();
        abrirModalConcepto($(this).closest('tr.item-concepto_ivacompra'));
    });

    $('#consultaconcepto_ivacompraModal').on('shown.bs.modal.tipoCompra', function () {
        modalConceptoAbriendo = false;
        $(this).find('#consultaconcepto_ivacompra').trigger('focus');
        if (ptrFilaConcepto) {
            buscarConceptoCatalogo('');
        }
    });

    $('#consultaconcepto_ivacompraModal').on('hidden.bs.modal.tipoCompra', function () {
        modalConceptoAbriendo = false;
        ptrFilaConcepto = null;
    });

    $(document).on('keyup', '#consultaconcepto_ivacompra', function (event) {
        if (!ptrFilaConcepto || !modalConceptoTipoAbierto()) {
            return;
        }
        if (event.key === 'Enter' || event.keyCode === 13) {
            return;
        }
        buscarConceptoCatalogo($(this).val());
    });

    $(document).on('keydown', '#consultaconcepto_ivacompra', function (event) {
        if (!ptrFilaConcepto || event.keyCode !== 13) {
            return;
        }
        event.preventDefault();
        var $primera = $('#datosconcepto_ivacompra .eligeconsultaconcepto_ivacompra').first();
        if ($primera.length) {
            $primera.trigger('click');
        }
    });

    $(document).on('click', '#datosconcepto_ivacompra .eligeconsultaconcepto_ivacompra', function () {
        if (!ptrFilaConcepto) {
            return;
        }
        var tr = $(this).closest('tr');
        asignarConceptoEnFila(ptrFilaConcepto, {
            id: parseInt(String(tr.find('.concepto_ivacompra_id_celda').text() || '0'), 10),
            codigo: String(tr.find('.codigo').text() || '').trim(),
            nombre: String(tr.find('.nombre').text() || '').trim()
        });
        $('#consultaconcepto_ivacompraModal').modal('hide');
    });

    $(document).on('keydown', '#form-general .codigo_concepto_ivacompra', function (event) {
        if (event.key === 'F1' || event.keyCode === 112) {
            event.preventDefault();
            abrirModalConcepto($(this).closest('tr.item-concepto_ivacompra'));
            return;
        }
        if (event.key === 'Enter' || event.keyCode === 13) {
            event.preventDefault();
            resolverConceptoPorCodigo($(this), true);
        }
    });

    $(document).on('blur', '#form-general .codigo_concepto_ivacompra', function () {
        resolverConceptoPorCodigo($(this), false);
    });

    $(document).on('input', '#form-general .codigo_concepto_ivacompra', function () {
        $(this).removeAttr('data-concepto-invalido').removeAttr('data-codigo-resuelto');
    });
}
