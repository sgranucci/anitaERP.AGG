var ptrConceptoSolicitudpagoId = $();
var ptrCodigoConceptoSolicitudpago = $();
var ptrNombreConceptoSolicitudpago = $();

function esTeclaF1ConceptoSolicitudpago(e) {
    return e.key === 'F1' || e.code === 'F1' || e.keyCode === 112;
}

function modalConsultaConceptoSolicitudpagoAbierto() {
    var $m = $('#consultaconcepto_solicitudpagoModal');
    return $m.length && $m.hasClass('show');
}

function sectorIdParaConsultaConceptoSolicitudpago() {
    var sid = parseInt($('#sector_solicitudpago_id').val() || '0', 10);
    return sid > 0 ? sid : 0;
}

function actualizarAvisoFiltroSectorConcepto() {
    var $aviso = $('#consulta-concepto-sp-filtro-sector');
    if (!$aviso.length) {
        return;
    }
    var $sel = $('#sector_solicitudpago_id');
    var sid = sectorIdParaConsultaConceptoSolicitudpago();
    if (sid > 0) {
        var texto = $.trim($sel.find('option:selected').text() || '');
        $aviso.text('Filtrado por sector: ' + texto);
    } else {
        $aviso.text('Sin filtro de sector (se listan todos los conceptos activos).');
    }
}

function parsearHtmlConsultaConceptoSolicitudpago(respuesta) {
    var resp = String(respuesta || '').replace(/\\/g, '');
    try {
        var parsed = JSON.parse(resp);
        return parsed.data || '';
    } catch (e) {
        return resp;
    }
}

function buscar_datos_concepto_solicitudpago(consulta) {
    actualizarAvisoFiltroSectorConcepto();
    $.ajax({
        url: carpetaBase + '/solicitudpago/concepto_solicitudpago/consultaconcepto',
        type: 'POST',
        dataType: 'HTML',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        data: {
            consulta: consulta,
            sector_solicitudpago_id: sectorIdParaConsultaConceptoSolicitudpago() || ''
        }
    })
        .done(function (respuesta) {
            $('#datosconcepto_solicitudpago').html(parsearHtmlConsultaConceptoSolicitudpago(respuesta));
        })
        .fail(function () {
            console.log('error consulta concepto solicitudpago');
        });
}

function actualizarLinkEditarConceptoSolicitudpago(conceptoId) {
    var $link = $('.btn-link-editar-concepto-solicitudpago');
    if (!$link.length) {
        return;
    }
    var id = parseInt(conceptoId || '0', 10);
    if (id > 0) {
        $link
            .attr('href', carpetaBase + '/solicitudpago/concepto_solicitudpago/' + id + '/editar?origen=modal_consulta&vista=consulta')
            .removeClass('d-none');
    } else {
        $link.attr('href', '#').addClass('d-none');
    }
}

function limpiarConceptoSolicitudpagoEnPantalla() {
    $('#concepto_solicitudpago_id').val('');
    $('#concepto_solicitudpago_id_codigo').val('');
    $('#concepto_solicitudpago_id_nombre').val('');
    $('#concepto_forma_pago').val('');
    actualizarLinkEditarConceptoSolicitudpago(0);
    $('#concepto_solicitudpago_id').trigger('change.spConcepto');
}

function limpiarConceptoSolicitudpagoManteniendoCodigo() {
    $('#concepto_solicitudpago_id').val('');
    $('#concepto_solicitudpago_id_nombre').val('');
    $('#concepto_forma_pago').val('');
    actualizarLinkEditarConceptoSolicitudpago(0);
}

function aplicarFormapagosolDesdeConcepto(data) {
    var $sel = $('#formapagosol_id');
    if (!$sel.length) {
        return;
    }
    var ids = [];
    if (data && Array.isArray(data.formapagosol_ids)) {
        ids = data.formapagosol_ids
            .map(function (id) { return parseInt(id, 10); })
            .filter(function (id) { return id > 0; });
    }
    var preferido = parseInt((data && data.formapagosol_id) || 0, 10);
    if (preferido > 0 && ids.indexOf(preferido) === -1) {
        ids.unshift(preferido);
    }
    if (ids.length === 0) {
        return;
    }
    var elegido = ids[0];
    var $opt = $sel.find('option[value="' + elegido + '"]');
    if (!$opt.length && data && data.formapagosol_nombre) {
        $sel.append(
            $('<option></option>')
                .attr('value', String(elegido))
                .text(String(data.formapagosol_nombre))
        );
        $opt = $sel.find('option[value="' + elegido + '"]');
    }
    if ($opt.length) {
        $sel.val(String(elegido));
        // Algunos selects con plugins escuchan change; forzar valor visible.
        if ($sel.val() !== String(elegido)) {
            $sel.find('option').prop('selected', false);
            $opt.prop('selected', true);
            $sel.val(String(elegido));
        }
        $sel.trigger('change');
    }
}

function aplicarConceptoSolicitudpagoEnPantalla(data) {
    if (!data || !data.id) {
        limpiarConceptoSolicitudpagoEnPantalla();
        if (typeof window.spAplicarCuentasDesdeConcepto === 'function') {
            window.spAplicarCuentasDesdeConcepto([]);
        }
        if (typeof actualizarVisibilidadCuotas === 'function') {
            actualizarVisibilidadCuotas();
        }
        return;
    }

    $('#concepto_solicitudpago_id').val(data.id);
    $('#concepto_solicitudpago_id_codigo').val(data.codigo || '');
    $('#concepto_solicitudpago_id_nombre').val(data.nombre || '');
    $('#concepto_forma_pago').val(String(data.forma_pago || '').toUpperCase());
    actualizarLinkEditarConceptoSolicitudpago(data.id);
    aplicarFormapagosolDesdeConcepto(data);

    if (data.sector_solicitudpago_id) {
        var $sector = $('#sector_solicitudpago_id');
        if ($sector.length && String($sector.val() || '') !== String(data.sector_solicitudpago_id)) {
            window.__spIgnorarCambioSector = true;
            $sector.val(String(data.sector_solicitudpago_id));
            window.__spIgnorarCambioSector = false;
        }
    }

    if (typeof actualizarVisibilidadCuotas === 'function') {
        actualizarVisibilidadCuotas();
    }

    // Siempre desde servidor (modal no trae cuentas; evita grilla vacía).
    if (typeof window.spCargarCuentasDesdeServidor === 'function') {
        window.spCargarCuentasDesdeServidor(data.id);
    } else if (typeof window.spAplicarCuentasDesdeConcepto === 'function') {
        window.spAplicarCuentasDesdeConcepto(
            Array.isArray(data.cuentas) ? data.cuentas : []
        );
    }

    var dataEvento = $.extend({}, data, { __cuentasYaAplicadas: true });
    $('#concepto_solicitudpago_id').trigger('change.spConcepto', [dataEvento]);
}

function aceptarCodigoConceptoSolicitudpagoDesdeInput($input) {
    var codigo = String($input.val() || '').trim();
    if (codigo === '') {
        limpiarConceptoSolicitudpagoEnPantalla();
        if (typeof window.spAplicarCuentasDesdeConcepto === 'function') {
            window.spAplicarCuentasDesdeConcepto([]);
        }
        if (typeof actualizarVisibilidadCuotas === 'function') {
            actualizarVisibilidadCuotas();
        }
        return;
    }
    leeUnConceptoSolicitudpago(0, codigo);
}

function leeUnConceptoSolicitudpago(conceptoId, codigoConcepto) {
    var url = '';
    var params = {};
    var sectorId = sectorIdParaConsultaConceptoSolicitudpago();
    if (sectorId > 0) {
        params.sector_solicitudpago_id = sectorId;
    }
    var empresaId = parseInt($('#empresa_id').val() || '0', 10);
    if (empresaId > 0) {
        params.empresa_id = empresaId;
    }

    if ($.isNumeric(conceptoId) && parseInt(conceptoId, 10) > 0) {
        url = carpetaBase + '/solicitudpago/concepto_solicitudpago/leer/' + parseInt(conceptoId, 10);
    } else if (codigoConcepto !== undefined && codigoConcepto !== null && String(codigoConcepto).trim() !== '') {
        url = carpetaBase + '/solicitudpago/concepto_solicitudpago/leerporcodigo/' + encodeURIComponent(String(codigoConcepto).trim());
    } else {
        limpiarConceptoSolicitudpagoEnPantalla();
        return;
    }

    limpiarConceptoSolicitudpagoManteniendoCodigo();

    $.get(url, params)
        .done(function (data) {
            if (data && data.id) {
                aplicarConceptoSolicitudpagoEnPantalla(data);
                return;
            }
            limpiarConceptoSolicitudpagoEnPantalla();
            if (typeof window.spAplicarCuentasDesdeConcepto === 'function') {
                window.spAplicarCuentasDesdeConcepto([]);
            }
            alert('No se encontró el concepto indicado' + (sectorId > 0 ? ' para el sector seleccionado' : '') + '.');
        })
        .fail(function () {
            limpiarConceptoSolicitudpagoEnPantalla();
            if (typeof window.spAplicarCuentasDesdeConcepto === 'function') {
                window.spAplicarCuentasDesdeConcepto([]);
            }
            alert('No se pudo cargar el concepto' + (sectorId > 0 ? ' (revise el sector)' : '') + '.');
        });
}

function abrirModalConsultaConceptoSolicitudpago() {
    ptrConceptoSolicitudpagoId = $('#concepto_solicitudpago_id');
    ptrCodigoConceptoSolicitudpago = $('#concepto_solicitudpago_id_codigo');
    ptrNombreConceptoSolicitudpago = $('#concepto_solicitudpago_id_nombre');
    $('#consultaconcepto_solicitudpago').val('');
    buscar_datos_concepto_solicitudpago('');
    $('#consultaconcepto_solicitudpagoModal').modal('show');
}

function leerFilaConceptoSolicitudpagoConsulta($link) {
    var $tr = $link.closest('tr');
    return {
        id: $.trim($tr.find('td.concepto_id').first().text()),
        codigo: $.trim($tr.find('td.codigoconcepto').first().text()),
        nombre: $.trim($tr.find('td.nombreconcepto').first().text()),
        sector_solicitudpago_id: $.trim($tr.find('td.sector_solicitudpago_id').first().text()) || null,
        forma_pago: $.trim($tr.find('td.forma_pago').first().text()) || ''
    };
}

// Enter en código concepto: capture para ganar a bloqueos globales de otros consulta.js.
document.addEventListener('keydown', function (e) {
    if (!(e.key === 'Enter' || e.code === 'Enter' || e.keyCode === 13 || e.which === 13)) {
        return;
    }
    var target = e.target;
    if (!target || target.readOnly || target.disabled) {
        return;
    }
    if (!target.classList.contains('codigoconcepto_solicitudpago') && target.id !== 'concepto_solicitudpago_id_codigo') {
        return;
    }
    e.preventDefault();
    e.stopPropagation();
    $(target).data('sp-concepto-enter-procesado', 1);
    aceptarCodigoConceptoSolicitudpagoDesdeInput($(target));
}, true);

$(document)
    .off('keydown.spCodigoConceptoEnter', '.codigoconcepto_solicitudpago, #concepto_solicitudpago_id_codigo')
    .on('keydown.spCodigoConceptoEnter', '.codigoconcepto_solicitudpago, #concepto_solicitudpago_id_codigo', function (e) {
        if (e.which !== 13 && e.key !== 'Enter') {
            return;
        }
        if ($(this).data('sp-concepto-enter-procesado')) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeData('sp-concepto-enter-procesado');
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        aceptarCodigoConceptoSolicitudpagoDesdeInput($(this));
    });

document.addEventListener('keydown', function (e) {
    if (!esTeclaF1ConceptoSolicitudpago(e)) {
        return;
    }
    var target = e.target;
    if (!target || (!target.classList.contains('codigoconcepto_solicitudpago') && target.id !== 'concepto_solicitudpago_id_codigo')) {
        return;
    }
    if (target.readOnly || target.disabled) {
        return;
    }
    if (modalConsultaConceptoSolicitudpagoAbierto()) {
        return;
    }
    e.preventDefault();
    e.stopPropagation();
    abrirModalConsultaConceptoSolicitudpago();
}, true);

$(document).on('keyup', '#consultaconcepto_solicitudpago', function () {
    buscar_datos_concepto_solicitudpago(String($(this).val() || '').trim());
});

function activa_eventos_consultaconcepto_solicitudpago() {
    $(document)
        .off('click.consultaConceptoSpAbrir', '.consultaconcepto_solicitudpago')
        .on('click.consultaConceptoSpAbrir', '.consultaconcepto_solicitudpago', function (event) {
            if ($(this).closest('#datosconcepto_solicitudpago').length) {
                return;
            }
            event.preventDefault();
            abrirModalConsultaConceptoSolicitudpago();
        });

    $('#consultaconcepto_solicitudpagoModal')
        .off('shown.bs.modal.consultaConceptoSp')
        .on('shown.bs.modal.consultaConceptoSp', function () {
            $(this).find('#consultaconcepto_solicitudpago').focus();
        });

    $(document)
        .off('click.eligeConsultaConceptoSp', '.eligeconsultaconcepto_solicitudpago')
        .on('click.eligeConsultaConceptoSp', '.eligeconsultaconcepto_solicitudpago', function (event) {
            event.preventDefault();
            var fila = leerFilaConceptoSolicitudpagoConsulta($(this));
            $('#consultaconcepto_solicitudpagoModal').modal('hide');
            if (fila.id) {
                leeUnConceptoSolicitudpago(fila.id, null);
            }
        });
}

$(function () {
    activa_eventos_consultaconcepto_solicitudpago();
});
