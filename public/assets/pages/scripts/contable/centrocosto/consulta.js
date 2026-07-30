var ptrCentrocosto_id;
var ptrCodigoCentrocosto_id;
var ptrDescripcionCentrocosto;

window.centrocostoCampoInputs = function (hiddenInputId) {
    hiddenInputId = (hiddenInputId || '').replace(/_codigo$|_descripcion$/, '');
    return {
        $id: $('#' + hiddenInputId),
        $codigo: $('#' + hiddenInputId + '_codigo'),
        $descripcion: $('#' + hiddenInputId + '_descripcion'),
    };
};

function limpiarCentrocostoCampo(hiddenInputId) {
    var campos = window.centrocostoCampoInputs(hiddenInputId);
    campos.$id.val('').trigger('change');
    campos.$codigo.val('');
    campos.$descripcion.val('');
    var $ctx = campos.$id.closest('.tm-centrocosto-campo');
    actualizarLinkEditarCentrocosto($ctx, 0);
    return campos;
}

window.limpiarCentrocostoCampo = limpiarCentrocostoCampo;

function actualizarLinkEditarCentrocosto($ctx, centrocostoId) {
    if (!$ctx || !$ctx.length) {
        return;
    }
    var $link = $ctx.find('.btn-link-editar-centrocosto');
    if (!$link.length) {
        return;
    }
    var id = parseInt(centrocostoId, 10) || 0;
    if (id > 0) {
        $link
            .attr('href', carpetaBase + '/contable/centrocosto/' + id + '/editar?origen=modal_consulta&vista=consulta')
            .removeClass('d-none');
    } else {
        $link.attr('href', '#').addClass('d-none');
    }
}

function buscar_datos_centrocosto(consulta) {
    $.ajax({
        url: carpetaBase + '/contable/centrocosto/consultacentrocosto',
        type: 'POST',
        dataType: 'HTML',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
        },
        data: {
            consulta: consulta || '',
        },
    })
        .done(function (respuesta) {
            var html = '';
            if (typeof respuesta === 'string') {
                try {
                    html = JSON.parse(respuesta).data || '';
                } catch (e) {
                    html = respuesta.replace(/\\/g, '');
                }
            } else if (respuesta && typeof respuesta.data === 'string') {
                html = respuesta.data;
            }
            $('#datoscentrocosto').html(html);
        })
        .fail(function () {
            $('#datoscentrocosto').html('<tr><td colspan="5">Error al consultar centros de costo</td></tr>');
        });
}

function aplicarCentrocostoEnCampo($ctx, data) {
    if (!$ctx || !$ctx.length || !data) {
        return;
    }
    $ctx.find('.centrocosto_id').val(data.id || '').trigger('change');
    $ctx.find('.codigocentrocosto').val(data.codigo || '');
    $ctx.find('.descripcioncentrocosto').val(data.nombre || data.descripcion || '');
    actualizarLinkEditarCentrocosto($ctx, data.id);
}

function leerCentrocostoPorCodigo(codigo, ptrrenglon, onDone) {
    var cod = (codigo || '').trim();
    var $ctx = $(ptrrenglon).closest('.tm-centrocosto-campo');
    if (!cod) {
        if ($ctx.length) {
            $ctx.find('.centrocosto_id').val('').trigger('change');
            $ctx.find('.descripcioncentrocosto').val('');
            actualizarLinkEditarCentrocosto($ctx, 0);
        }
        if (typeof onDone === 'function') {
            onDone(null);
        }
        return;
    }

    if ($ctx.length) {
        $ctx.find('.centrocosto_id').val('');
        $ctx.find('.descripcioncentrocosto').val('');
    }

    $.getJSON(carpetaBase + '/contable/centrocosto/resolvercentrocosto', { valor: cod })
        .done(function (data) {
            if (!data || !data.ok) {
                if ($ctx.length) {
                    $ctx.find('.codigocentrocosto').val(cod);
                }
                alert(data && data.mensaje ? data.mensaje : 'Centro de costo no encontrado');
                if (typeof onDone === 'function') {
                    onDone(null);
                }
                return;
            }

            aplicarCentrocostoEnCampo($ctx, data);
            if (typeof onDone === 'function') {
                onDone(data);
            }
        })
        .fail(function () {
            if ($ctx.length) {
                $ctx.find('.codigocentrocosto').val(cod);
            }
            if (typeof onDone === 'function') {
                onDone(null);
            }
        });
}

function activa_eventos_consultacentrocosto() {
    $(document)
        .off('click.ccConsulta', '.consultacentrocosto')
        .on('click.ccConsulta', '.consultacentrocosto', function (e) {
            e.preventDefault();
            var $ctx = $(this).closest('.tm-centrocosto-campo');
            ptrCentrocosto_id = $ctx.find('.centrocosto_id');
            ptrCodigoCentrocosto_id = $ctx.find('.codigocentrocosto');
            ptrDescripcionCentrocosto = $ctx.find('.descripcioncentrocosto');
            var valor = (ptrCodigoCentrocosto_id.val() || '').trim();
            buscar_datos_centrocosto(valor);
            $('#consultacentrocosto').val(valor);
            $('#consultacentrocostoModal').modal('show');
        });

    $('#consultacentrocostoModal')
        .off('shown.bs.modal.ccConsulta')
        .on('shown.bs.modal.ccConsulta', function () {
            $(this).find('#consultacentrocosto').focus();
        });

    $(document)
        .off('keyup.ccConsultaBuscar', '#consultacentrocosto')
        .on('keyup.ccConsultaBuscar', '#consultacentrocosto', function () {
            buscar_datos_centrocosto($(this).val());
        });

    $(document)
        .off('click.ccConsultaElegir', '.eligeconsultacentrocosto')
        .on('click.ccConsultaElegir', '.eligeconsultacentrocosto', function (e) {
            e.preventDefault();
            var $tr = $(this).closest('tr');
            var id = $tr.find('.id').text().trim();
            var codigo = $tr.find('.codigo').text().trim();
            var nombre = $tr.find('.nombre').text().trim();

            if (ptrCentrocosto_id && ptrCentrocosto_id.length) {
                ptrCentrocosto_id.val(id).trigger('change');
            }
            if (ptrCodigoCentrocosto_id && ptrCodigoCentrocosto_id.length) {
                ptrCodigoCentrocosto_id.val(codigo);
            }
            if (ptrDescripcionCentrocosto && ptrDescripcionCentrocosto.length) {
                ptrDescripcionCentrocosto.val(nombre);
            }

            var $ctx = ptrCentrocosto_id && ptrCentrocosto_id.length
                ? ptrCentrocosto_id.closest('.tm-centrocosto-campo')
                : $();
            actualizarLinkEditarCentrocosto($ctx, id);
            $('#consultacentrocostoModal').modal('hide');
        });

    $(document)
        .off('change.ccConsultaCod blur.ccConsultaCod', '.tm-centrocosto-campo .codigocentrocosto')
        .on('change.ccConsultaCod blur.ccConsultaCod', '.tm-centrocosto-campo .codigocentrocosto', function () {
            leerCentrocostoPorCodigo($(this).val(), this);
        });

    $(document)
        .off('keydown.ccConsultaF1', '.tm-centrocosto-campo .codigocentrocosto')
        .on('keydown.ccConsultaF1', '.tm-centrocosto-campo .codigocentrocosto', function (e) {
            if (e.key === 'F1' || e.code === 'F1' || e.keyCode === 112) {
                e.preventDefault();
                $(this).closest('.tm-centrocosto-campo').find('.consultacentrocosto').trigger('click');
            }
        });
}

window.activa_eventos_consultacentrocosto = activa_eventos_consultacentrocosto;

$(function () {
    if (typeof activa_eventos_consultacentrocosto === 'function') {
        activa_eventos_consultacentrocosto();
    }
    $('.tm-centrocosto-campo').each(function () {
        var $ctx = $(this);
        actualizarLinkEditarCentrocosto($ctx, parseInt($ctx.find('.centrocosto_id').val(), 10) || 0);
    });
});
