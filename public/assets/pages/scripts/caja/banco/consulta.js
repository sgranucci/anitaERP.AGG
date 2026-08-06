/* global carpetaBase */
var ptrbanco_id;
var ptrnombrebanco;
var ptrcodigobanco;
var ptrCampoBanco = $();

function esTeclaF1Banco(e) {
    return e.key === 'F1' || e.code === 'F1' || e.keyCode === 112;
}

function modalConsultaBancoAbierto() {
    var $m = $('#consultabancoModal');
    return $m.length && $m.hasClass('show');
}

function campoBancoDesde($el) {
    var $campo = $($el).closest('.tm-banco-campo');
    if ($campo.length) {
        return $campo;
    }
    var $tr = $($el).closest('tr');
    return $tr.length ? $tr : $();
}

function apuntarPtrsDesdeCampo($campo) {
    ptrCampoBanco = $campo && $campo.length ? $campo : $();
    if (!ptrCampoBanco.length) {
        ptrbanco_id = undefined;
        ptrcodigobanco = undefined;
        ptrnombrebanco = undefined;
        return;
    }
    ptrbanco_id = ptrCampoBanco.find('.banco_id, .banco_recibido_id, .banco_reemplazo_id').first();
    ptrcodigobanco = ptrCampoBanco.find('.codigobanco, .codigobanco_recibido, .codigobanco_reemplazo').first();
    ptrnombrebanco = ptrCampoBanco.find('.nombrebanco, .nombrebanco_recibido, .nombrebanco_reemplazo').first();
}

function parsearHtmlConsultaBanco(respuesta) {
    var resp = String(respuesta || '').replace(/\\/g, '');
    try {
        var parsed = JSON.parse(resp);
        return parsed.data || '';
    } catch (e) {
        return resp;
    }
}

function buscar_datos_banco(consulta) {
    $.ajax({
        url: carpetaBase + '/caja/banco/consultabanco',
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
            $('#datosbanco').html(parsearHtmlConsultaBanco(respuesta));
        })
        .fail(function () {
            console.log('error consulta banco');
        });
}

function actualizarLinkEditarBanco($campo, bancoId) {
    if (!$campo || !$campo.length) {
        return;
    }
    var $link = $campo.find('.btn-link-editar-banco');
    if (!$link.length) {
        return;
    }
    var id = parseInt(bancoId || '0', 10);
    if (id > 0) {
        $link
            .attr('href', carpetaBase + '/caja/banco/' + id + '/editar?origen=modal_consulta&vista=consulta')
            .removeClass('d-none');
    } else {
        $link.attr('href', '#').addClass('d-none');
    }
}

function limpiarBancoEnCampo($campo, mantenerCodigo) {
    if (!$campo || !$campo.length) {
        return;
    }
    $campo.find('.banco_id, .banco_recibido_id, .banco_reemplazo_id').first().val('');
    if (!mantenerCodigo) {
        $campo.find('.codigobanco, .codigobanco_recibido, .codigobanco_reemplazo').first().val('');
    }
    $campo.find('.nombrebanco, .nombrebanco_recibido, .nombrebanco_reemplazo').first().val('');
    actualizarLinkEditarBanco($campo, 0);
    $campo.find('.banco_id').first().trigger('change.bancoConsulta');
}

function aplicarBancoEnCampo($campo, data) {
    if (!$campo || !$campo.length || !data || !data.id) {
        return;
    }
    var $id = $campo.find('.banco_id, .banco_recibido_id, .banco_reemplazo_id').first();
    var $codigo = $campo.find('.codigobanco, .codigobanco_recibido, .codigobanco_reemplazo').first();
    var $nombre = $campo.find('.nombrebanco, .nombrebanco_recibido, .nombrebanco_reemplazo').first();

    $id.val(data.id);
    if ($codigo.length) {
        $codigo.val(data.codigo != null ? data.codigo : '');
    }
    if ($nombre.length) {
        $nombre.val(data.nombre || '');
    }

    actualizarLinkEditarBanco($campo, data.id);
    $id.trigger('change.bancoConsulta');
    if ($id.is('#banco_id')) {
        $id.trigger('change');
    }
}

window.aplicarBancoEnCampo = aplicarBancoEnCampo;
window.limpiarBancoEnCampo = limpiarBancoEnCampo;

function leeUnBanco(bancoId, codigoBanco, $campo) {
    $campo = $campo && $campo.length ? $campo : ptrCampoBanco;
    if (!$campo || !$campo.length) {
        return;
    }

    var url;
    if ($.isNumeric(bancoId) && parseInt(bancoId, 10) > 0) {
        url = carpetaBase + '/caja/leerbanco/' + parseInt(bancoId, 10);
    } else if (codigoBanco !== undefined && codigoBanco !== null && String(codigoBanco).trim() !== '') {
        url = carpetaBase + '/caja/leerbancoporcodigo/' + encodeURIComponent(String(codigoBanco).trim());
    } else {
        limpiarBancoEnCampo($campo, false);
        return;
    }

    limpiarBancoEnCampo($campo, true);

    $.get(url)
        .done(function (data) {
            if (data && data.id) {
                aplicarBancoEnCampo($campo, data);
                return;
            }
            limpiarBancoEnCampo($campo, false);
            alert('No se encontr\u00f3 el banco indicado.');
        })
        .fail(function () {
            limpiarBancoEnCampo($campo, false);
            alert('No se pudo cargar el banco.');
        });
}

function aceptarCodigoBancoDesdeInput($input) {
    var $campo = campoBancoDesde($input);
    if (!$campo.length) {
        return;
    }
    apuntarPtrsDesdeCampo($campo);
    var codigo = String($input.val() || '').trim();
    if (codigo === '') {
        limpiarBancoEnCampo($campo, false);
        return;
    }
    leeUnBanco(null, codigo, $campo);
}

function abrirModalConsultaBanco($campo) {
    apuntarPtrsDesdeCampo($campo && $campo.length ? $campo : $('.tm-banco-campo').first());
    $('#consultabanco').val('');
    buscar_datos_banco('');
    $('#consultabancoModal').modal('show');
}

function leerFilaBancoConsulta($link) {
    var $tr = $link.closest('tr');
    return {
        id: $.trim($tr.find('td.id').first().text()) || $.trim($tr.children('td').first().text()),
        codigo: $.trim($tr.find('td.codigo').first().text()),
        nombre: $.trim($tr.find('td.nombre').first().text())
    };
}

document.addEventListener('keydown', function (e) {
    if (!(e.key === 'Enter' || e.code === 'Enter' || e.keyCode === 13 || e.which === 13)) {
        return;
    }
    var target = e.target;
    if (!target || target.readOnly || target.disabled) {
        return;
    }
    if (!target.classList.contains('codigobanco') &&
        !target.classList.contains('codigobanco_recibido') &&
        !target.classList.contains('codigobanco_reemplazo')) {
        return;
    }
    e.preventDefault();
    e.stopPropagation();
    $(target).data('banco-enter-procesado', 1);
    aceptarCodigoBancoDesdeInput($(target));
}, true);

$(document)
    .off('keydown.bancoCodigoEnter', '.codigobanco, .codigobanco_recibido, .codigobanco_reemplazo')
    .on('keydown.bancoCodigoEnter', '.codigobanco, .codigobanco_recibido, .codigobanco_reemplazo', function (e) {
        if (e.which !== 13 && e.key !== 'Enter') {
            return;
        }
        if ($(this).data('banco-enter-procesado')) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeData('banco-enter-procesado');
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        aceptarCodigoBancoDesdeInput($(this));
    });

// Si vacían el código (borrado / Tab), limpiar también nombre e id
$(document)
    .off('input.bancoCodigoVaciar blur.bancoCodigoVaciar', '.codigobanco, .codigobanco_recibido, .codigobanco_reemplazo')
    .on('input.bancoCodigoVaciar blur.bancoCodigoVaciar', '.codigobanco, .codigobanco_recibido, .codigobanco_reemplazo', function () {
        var $input = $(this);
        var $campo = campoBancoDesde($input);
        if (!$campo.length) {
            return;
        }
        if (String($input.val() || '').trim() !== '') {
            return;
        }
        limpiarBancoEnCampo($campo, true);
    });

document.addEventListener('keydown', function (e) {
    if (!esTeclaF1Banco(e)) {
        return;
    }
    var target = e.target;
    if (!target ||
        (!target.classList.contains('codigobanco') &&
            !target.classList.contains('codigobanco_recibido') &&
            !target.classList.contains('codigobanco_reemplazo'))) {
        return;
    }
    if (target.readOnly || target.disabled) {
        return;
    }
    if (modalConsultaBancoAbierto()) {
        return;
    }
    e.preventDefault();
    e.stopPropagation();
    abrirModalConsultaBanco(campoBancoDesde($(target)));
}, true);

$(document).on('keyup', '#consultabanco', function () {
    buscar_datos_banco(String($(this).val() || '').trim());
});

function activa_eventos_consultabanco() {
    $(document)
        .off('click.consultaBancoAbrir', '.consultabanco')
        .on('click.consultaBancoAbrir', '.consultabanco', function (event) {
            if ($(this).closest('#datosbanco').length) {
                return;
            }
            event.preventDefault();
            abrirModalConsultaBanco(campoBancoDesde($(this)));
        });

    $('#consultabancoModal')
        .off('shown.bs.modal.consultaBanco')
        .on('shown.bs.modal.consultaBanco', function () {
            $(this).find('#consultabanco').focus();
        });

    $('#aceptaconsultabancoModal')
        .off('click.consultaBancoAcepta')
        .on('click.consultaBancoAcepta', function () {
            $('#consultabancoModal').modal('hide');
        });

    $(document)
        .off('click.eligeConsultaBanco', '.eligeconsultabanco')
        .on('click.eligeConsultaBanco', '.eligeconsultabanco', function (event) {
            event.preventDefault();
            var fila = leerFilaBancoConsulta($(this));
            $('#consultabancoModal').modal('hide');
            if (!fila.id) {
                return;
            }
            if (ptrCampoBanco && ptrCampoBanco.length) {
                leeUnBanco(fila.id, null, ptrCampoBanco);
                return;
            }
            // Legacy (cobranza / cheques): ptrs seteados por el caller
            if (ptrbanco_id && ptrbanco_id.length) {
                $(ptrbanco_id).val(fila.id);
            }
            if (ptrnombrebanco && ptrnombrebanco.length) {
                $(ptrnombrebanco).val(fila.nombre);
            }
            if (ptrcodigobanco && ptrcodigobanco.length) {
                $(ptrcodigobanco).val(fila.codigo);
            }
            if ($('#banco_id').length && ptrbanco_id && ptrbanco_id.is('#banco_id')) {
                $('#banco_id').trigger('change');
            }
        });

    $(document)
        .off('click.consultaUnBanco', '.consultaunbanco')
        .on('click.consultaUnBanco', '.consultaunbanco', function (event) {
            event.preventDefault();
            var id = $.trim($(this).closest('tr').find('td.id').first().text()) ||
                $.trim($(this).closest('tr').children('td').first().text());
            if (!(parseInt(id, 10) > 0)) {
                return;
            }
            var url = carpetaBase + '/caja/banco/' + id + '/editar?origen=modal_consulta&vista=consulta';
            window.open(url, '_blank', 'noopener');
        });
}

window.activa_eventos_consultabanco = activa_eventos_consultabanco;
window.abrirModalConsultaBanco = abrirModalConsultaBanco;
window.leeUnBanco = leeUnBanco;

$(function () {
    activa_eventos_consultabanco();
});
