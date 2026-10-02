var ptrTipotransaccionVentaContext;
var abriendoModalTipotransaccionVenta = false;

function csrfTokenTipotransaccionVenta() {
    return $('meta[name="csrf-token"]').attr('content')
        || $('input[name="_token"]').first().val()
        || '';
}

function parsearHtmlConsultaTipotransaccionVenta(respuesta) {
    if (respuesta && typeof respuesta === 'object') {
        return respuesta.data || '';
    }
    var resp = String(respuesta || '');
    try {
        return JSON.parse(resp).data || '';
    } catch (e) {
        return resp;
    }
}

function actualizarLinkEditarTipotransaccionVenta($ctx, id) {
    if (!$ctx || !$ctx.length) {
        return;
    }
    var $link = $ctx.find('.btn-link-editar-tipotransaccion-venta');
    if (!$link.length) {
        return;
    }
    var tid = parseInt(id, 10) || 0;
    if (tid > 0) {
        $link.attr('href', carpetaBase + '/ventas/tipotransaccion/' + tid + '/editar?origen=modal_consulta&vista=consulta').removeClass('d-none');
    } else {
        $link.attr('href', '#').addClass('d-none');
    }
}

function catalogoTiposTransaccionVentaPagina() {
    var node = document.querySelector('#datosfactura');
    if (!node || !node.dataset || !node.dataset.tipotransaccion) {
        return [];
    }
    try {
        var raw = JSON.parse(node.dataset.tipotransaccion || '[]');
        return Array.isArray(raw) ? raw : Object.keys(raw || {}).map(function (k) { return raw[k]; });
    } catch (e) {
        return [];
    }
}

function enriquecerTipotransaccionVenta(data) {
    data = data || {};
    var id = String(data.id || '');
    var item = null;
    if (id !== '') {
        catalogoTiposTransaccionVentaPagina().some(function (row) {
            if (row && String(row.id) === id) {
                item = row;
                return true;
            }
            return false;
        });
    }
    if (item) {
        var cv = item.concepto_venta || item.conceptoVenta || {};
        data.abreviatura = data.abreviatura || item.abreviatura || '';
        data.nombre = data.nombre || item.nombre || '';
        data.codigo = data.codigo || item.codigo || '';
        data.operacion = data.operacion || item.operacion || '';
        data.concepto_venta_id = data.concepto_venta_id || item.concepto_venta_id || '';
        data.concepto_codigo = data.concepto_codigo || cv.codigo || '';
        data.concepto_nombre = data.concepto_nombre || cv.nombre || '';
        data.concepto_descripcion = data.concepto_descripcion || cv.descripcion || '';
        data.concepto_impuesto_id = data.concepto_impuesto_id || cv.impuesto_id || '';
    }
    return data;
}

function estamparMetaTipotransaccionVenta($hidden, data) {
    if (!$hidden || !$hidden.length) {
        return;
    }
    data = data || {};
    var conceptoId = parseInt(data.concepto_venta_id || '0', 10) || 0;
    $hidden.attr('data-abreviatura', data.abreviatura || '');
    $hidden.attr('data-codigo', data.codigo || '');
    $hidden.attr('data-operacion', data.operacion || '');
    $hidden.attr('data-usa-concepto', conceptoId > 0 ? '1' : '0');
    $hidden.attr('data-concepto-venta-id', conceptoId > 0 ? String(conceptoId) : '');
    $hidden.attr('data-concepto-codigo', data.concepto_codigo || '');
    $hidden.attr('data-concepto-nombre', data.concepto_nombre || '');
    $hidden.attr('data-concepto-descripcion', data.concepto_descripcion || '');
    $hidden.attr('data-concepto-impuesto-id', data.concepto_impuesto_id || '');
}

function aplicarTipotransaccionVentaEnContexto($ctx, data, dispararChange) {
    if (!$ctx || !$ctx.length) {
        return;
    }
    data = enriquecerTipotransaccionVenta(data);
    var $hidden = $ctx.find('.tipotransaccion_venta_id');
    var prev = String($hidden.val() || '');
    $hidden.val(data && data.id ? data.id : '');
    var abrevAplicada = data && data.abreviatura != null ? data.abreviatura : '';
    $ctx.find('.abreviaturatipotransaccionventa').val(abrevAplicada).data('ultima-valida', abrevAplicada);
    $ctx.find('.nombretipotransaccionventa').val(data && data.nombre != null ? data.nombre : '');
    estamparMetaTipotransaccionVenta($hidden, data);
    actualizarLinkEditarTipotransaccionVenta($ctx, data && data.id ? data.id : 0);
    if (dispararChange !== false && String($hidden.val() || '') !== prev) {
        $hidden.trigger('change');
    }
}

function limpiarTipotransaccionVentaEnContexto($ctx, mantenerAbrev) {
    if (!$ctx || !$ctx.length) {
        return;
    }
    var $hidden = $ctx.find('.tipotransaccion_venta_id');
    var prev = String($hidden.val() || '');
    $hidden.val('');
    $ctx.find('.abreviaturatipotransaccionventa').removeData('ultima-valida');
    if (!mantenerAbrev) {
        $ctx.find('.abreviaturatipotransaccionventa').val('');
    }
    $ctx.find('.nombretipotransaccionventa').val('');
    estamparMetaTipotransaccionVenta($hidden, {});
    actualizarLinkEditarTipotransaccionVenta($ctx, 0);
    if (prev !== '') {
        $hidden.trigger('change');
    }
}

window.metaTipotransaccionVenta = function ($el) {
    $el = $el && $el.length ? $el : $('#tipotransaccion_id');
    if (!$el.length) {
        return $();
    }
    if ($el.is('select')) {
        return $el.find('option:selected');
    }
    return $el;
};

window.aplicarTipotransaccionVentaPorItem = function ($ctx, item, dispararChange) {
    if (!$ctx || !$ctx.length) {
        $ctx = $('#tipotransaccion_id').closest('.tm-tipotransaccion-venta-campo');
    }
    aplicarTipotransaccionVentaEnContexto($ctx, item || {}, dispararChange !== false);
};

window.aplicarTipotransaccionVentaPorId = function (id, $ctx) {
    var item = null;
    catalogoTiposTransaccionVentaPagina().some(function (row) {
        if (row && String(row.id) === String(id)) {
            item = row;
            return true;
        }
        return false;
    });
    if (!item) {
        return false;
    }
    window.aplicarTipotransaccionVentaPorItem($ctx, item);
    return true;
};

function buscar_datos_tipotransaccion_venta(consulta) {
    $.ajax({
        url: carpetaBase + '/ventas/tipotransaccion/consultatipotransaccion',
        type: 'POST',
        dataType: 'json',
        headers: { 'X-CSRF-TOKEN': csrfTokenTipotransaccionVenta() },
        data: {
            consulta: consulta || '',
            _token: csrfTokenTipotransaccionVenta(),
        },
    })
        .done(function (respuesta) {
            $('#datostipotransaccionventa').html(parsearHtmlConsultaTipotransaccionVenta(respuesta));
            filtrarFilasConsultaTipotransaccionVenta();
        })
        .fail(function (xhr) {
            var msg = 'Error al consultar tipos de transacci\u00f3n';
            if (xhr && xhr.status === 403) {
                msg = 'Sin permiso para consultar tipos de transacci\u00f3n';
            }
            $('#datostipotransaccionventa').html('<tr><td colspan="5">' + msg + '</td></tr>');
        });
}

function tipoVentaDesdeCatalogoPorTexto(texto) {
    var buscada = $.trim(texto || '').toUpperCase();
    if (!buscada) {
        return null;
    }
    var porAbreviatura = null;
    var porCodigo = null;
    var porId = null;
    catalogoTiposTransaccionVentaPagina().some(function (row) {
        if (!row) {
            return false;
        }
        var abr = String(row.abreviatura || '').trim().toUpperCase();
        var cod = String(row.codigo || '').trim().toUpperCase();
        if (!porAbreviatura && abr === buscada) {
            porAbreviatura = row;
        }
        if (!porCodigo && cod === buscada) {
            porCodigo = row;
        }
        if (!porId && String(row.id) === buscada) {
            porId = row;
        }
        return false;
    });
    return porAbreviatura || porCodigo || porId;
}

function resolverTipotransaccionVentaPorAbreviatura(abrev, $ctx, alertar, callback) {
    var a = $.trim(abrev || '');
    if (a === '') {
        limpiarTipotransaccionVentaEnContexto($ctx, false);
        if (typeof callback === 'function') {
            callback(null);
        }
        return;
    }
    var local = tipoVentaDesdeCatalogoPorTexto(a);
    if (local && local.id) {
        if (tipotransaccionVentaPermitido(local.id)) {
            aplicarTipotransaccionVentaEnContexto($ctx, local);
            if (typeof callback === 'function') {
                callback(local);
            }
            return;
        }
        limpiarTipotransaccionVentaEnContexto($ctx, true);
        if (alertar) {
            alert('Ese tipo no corresponde a este comprobante');
        }
        if (typeof callback === 'function') {
            callback(null);
        }
        return;
    }
    $.ajax({
        url: carpetaBase + '/ventas/tipotransaccion/resolvertipotransaccion',
        type: 'GET',
        dataType: 'json',
        data: { abreviatura: a },
    })
        .done(function (data) {
            var ok = !!(data && data.id && tipotransaccionVentaPermitido(data.id));
            if (ok) {
                aplicarTipotransaccionVentaEnContexto($ctx, data);
                if (typeof callback === 'function') {
                    callback(data);
                }
                return;
            }
            limpiarTipotransaccionVentaEnContexto($ctx, true);
            if (alertar) {
                alert(data && data.id
                    ? 'Ese tipo no corresponde a este comprobante'
                    : 'Tipo de transacci\u00f3n no encontrado');
            }
            if (typeof callback === 'function') {
                callback(null);
            }
        })
        .fail(function () {
            limpiarTipotransaccionVentaEnContexto($ctx, true);
            if (alertar) {
                alert('Tipo de transacci\u00f3n no encontrado');
            }
            if (typeof callback === 'function') {
                callback(null);
            }
        });
}

function focoSiguienteTrasTipotransaccionVenta(input) {
    var form = input && input.form;
    if (!form) {
        var cliente = document.getElementById('codigocliente');
        if (cliente) {
            cliente.focus();
            if (cliente.select) {
                cliente.select();
            }
        }
        return;
    }
    var nodos = form.querySelectorAll('input, select, textarea');
    var lista = [];
    for (var i = 0; i < nodos.length; i++) {
        var el = nodos[i];
        if (el.type === 'hidden' || el.disabled || el.readOnly) {
            continue;
        }
        if (!el.offsetParent) {
            continue;
        }
        lista.push(el);
    }
    var idx = lista.indexOf(input);
    if (idx >= 0 && lista[idx + 1]) {
        lista[idx + 1].focus();
        if (lista[idx + 1].select) {
            lista[idx + 1].select();
        }
    }
}

function abreviaturaTipoVentaYaResuelta($input, $ctx) {
    var abrev = $.trim($input.val() || '');
    var idActual = parseInt(String($ctx.find('.tipotransaccion_venta_id').val() || '0'), 10);
    var ultima = $.trim(String($input.data('ultima-valida') || ''));
    return abrev !== '' && idActual > 0 && ultima.toUpperCase() === abrev.toUpperCase();
}

function manejarEnterAbreviaturaTipotransaccionVenta(e) {
    var target = e.target;
    if (!target || !target.classList || !target.classList.contains('abreviaturatipotransaccionventa')) {
        return;
    }
    if (target.readOnly || target.disabled) {
        return;
    }
    if (e.key === 'F1' || e.code === 'F1' || e.keyCode === 112) {
        e.preventDefault();
        e.stopImmediatePropagation();
        $(target).closest('.tm-tipotransaccion-venta-campo').find('.consultatipotransaccionventa').first().trigger('click');
        return;
    }
    if (e.key !== 'Enter' && e.code !== 'Enter' && e.which !== 13 && e.keyCode !== 13) {
        return;
    }
    e.preventDefault();
    e.stopImmediatePropagation();
    var $input = $(target);
    var $ctx = $input.closest('.tm-tipotransaccion-venta-campo');
    $input.data('enter-procesado', 1);
    if (abreviaturaTipoVentaYaResuelta($input, $ctx)) {
        focoSiguienteTrasTipotransaccionVenta(target);
        return;
    }
    resolverTipotransaccionVentaPorAbreviatura($input.val(), $ctx, true, function (data) {
        if (data && data.id) {
            focoSiguienteTrasTipotransaccionVenta(target);
            return;
        }
        $input.removeData('enter-procesado');
        try {
            target.focus();
            target.select();
        } catch (err) {
            // ignore
        }
    });
}

function manejarEnterModalTipotransaccionVenta(e) {
    var target = e.target;
    if (!target || target.id !== 'consultatipotransaccionventa') {
        return;
    }
    if (e.key !== 'Enter' && e.code !== 'Enter' && e.which !== 13 && e.keyCode !== 13) {
        return;
    }
    e.preventDefault();
    e.stopImmediatePropagation();
    var $btn = $('#datostipotransaccionventa .eligeconsultatipotransaccionventa').first();
    if ($btn.length) {
        $btn.trigger('click');
    }
}

function activarCapturaEnterTipotransaccionVenta() {
    if (window.__tipoVentaEnterCapture) {
        return;
    }
    document.addEventListener('keydown', manejarEnterAbreviaturaTipotransaccionVenta, true);
    document.addEventListener('keydown', manejarEnterModalTipotransaccionVenta, true);
    window.__tipoVentaEnterCapture = true;
}

function idsPermitidosConsultaTipotransaccionVenta() {
    if (!document.querySelector('#datosfactura') || !window.FacturacionCircuitoAfip
        || typeof window.FacturacionCircuitoAfip.idsPermitidosPagina !== 'function') {
        return null;
    }
    return window.FacturacionCircuitoAfip.idsPermitidosPagina();
}

function tipotransaccionVentaPermitido(id) {
    var ids = idsPermitidosConsultaTipotransaccionVenta();
    if (!ids || !ids.length) {
        return true;
    }
    return ids.indexOf(parseInt(id, 10) || 0) >= 0;
}

function filtrarFilasConsultaTipotransaccionVenta() {
    var ids = idsPermitidosConsultaTipotransaccionVenta();
    if (!ids || !ids.length) {
        return;
    }
    $('#datostipotransaccionventa tr').each(function () {
        var id = parseInt($(this).find('.id').text(), 10) || 0;
        if (ids.indexOf(id) < 0) {
            $(this).remove();
        }
    });
    if (!$('#datostipotransaccionventa tr').length) {
        $('#datostipotransaccionventa').html('<tr><td colspan="5">Sin resultados</td></tr>');
    }
}

function marcarAbreviaturasTipotransaccionVentaIniciales() {
    $('.abreviaturatipotransaccionventa').each(function () {
        var $input = $(this);
        var $ctx = $input.closest('.tm-tipotransaccion-venta-campo');
        var id = parseInt(String($ctx.find('.tipotransaccion_venta_id').val() || '0'), 10);
        var abrev = $.trim($input.val() || '');
        if (id > 0 && abrev !== '') {
            $input.data('ultima-valida', abrev);
        }
    });
}

function activa_eventos_consultatipotransaccionventa() {
    activarCapturaEnterTipotransaccionVenta();
    marcarAbreviaturasTipotransaccionVentaIniciales();

    $(document)
        .off('mousedown.consultaTipoVenta', '.consultatipotransaccionventa')
        .on('mousedown.consultaTipoVenta', '.consultatipotransaccionventa', function () {
            abriendoModalTipotransaccionVenta = true;
        });

    $(document)
        .off('click.consultaTipoVenta', '.consultatipotransaccionventa')
        .on('click.consultaTipoVenta', '.consultatipotransaccionventa', function () {
            ptrTipotransaccionVentaContext = $(this).closest('.tm-tipotransaccion-venta-campo, tr');
            abriendoModalTipotransaccionVenta = true;
            $('#consultatipotransaccionventaModal').modal('show');
        });

    $('#consultatipotransaccionventaModal')
        .off('hidden.bs.modal.consultaTipoVenta')
        .on('hidden.bs.modal.consultaTipoVenta', function () {
            abriendoModalTipotransaccionVenta = false;
        });

    $('#consultatipotransaccionventaModal')
        .off('shown.bs.modal.consultaTipoVenta')
        .on('shown.bs.modal.consultaTipoVenta', function () {
            var $input = $('#consultatipotransaccionventa');
            setTimeout(function () { $input.trigger('focus').select(); }, 0);
            buscar_datos_tipotransaccion_venta($input.val());
        });

    $(document)
        .off('keyup.consultaTipoVenta', '#consultatipotransaccionventa')
        .on('keyup.consultaTipoVenta', '#consultatipotransaccionventa', function (e) {
            if (e.which === 13 || e.key === 'Enter') {
                return;
            }
            buscar_datos_tipotransaccion_venta($(this).val());
        });

    $(document)
        .off('keydown.consultaTipoVenta', '#consultatipotransaccionventa')
        .on('keydown.consultaTipoVenta', '#consultatipotransaccionventa', function (e) {
            if (e.which !== 13 && e.key !== 'Enter') {
                return;
            }
            e.preventDefault();
            var $btn = $('#datostipotransaccionventa .eligeconsultatipotransaccionventa').first();
            if ($btn.length) {
                $btn.trigger('click');
            } else {
                buscar_datos_tipotransaccion_venta($(this).val());
            }
        });

    $(document)
        .off('click.eligeTipoVenta', '.eligeconsultatipotransaccionventa')
        .on('click.eligeTipoVenta', '.eligeconsultatipotransaccionventa', function () {
            var $tr = $(this).closest('tr');
            aplicarTipotransaccionVentaEnContexto(ptrTipotransaccionVentaContext, {
                id: $tr.find('.id').text().trim(),
                abreviatura: $tr.find('.abreviatura').text().trim(),
                nombre: $tr.find('.nombre').text().trim(),
            });
            $('#consultatipotransaccionventaModal').modal('hide');
        });

    $('#aceptaconsultatipotransaccionventaModal')
        .off('click.consultaTipoVenta')
        .on('click.consultaTipoVenta', function () {
            var $btn = $('#datostipotransaccionventa .eligeconsultatipotransaccionventa').first();
            if ($btn.length) {
                $btn.trigger('click');
            } else {
                $('#consultatipotransaccionventaModal').modal('hide');
            }
        });

    $(document)
        .off('keydown.abrevTipoVenta', '.abreviaturatipotransaccionventa')
        .on('keydown.abrevTipoVenta', '.abreviaturatipotransaccionventa', function (e) {
            var $input = $(this);
            if ($input.prop('readonly') || $input.prop('disabled')) {
                return;
            }
            if (e.key === 'F1' || e.keyCode === 112) {
                e.preventDefault();
                $input.closest('.tm-tipotransaccion-venta-campo').find('.consultatipotransaccionventa').first().trigger('click');
                return;
            }
            if (e.which === 13 || e.key === 'Enter') {
                e.preventDefault();
                resolverTipotransaccionVentaPorAbreviatura($input.val(), $input.closest('.tm-tipotransaccion-venta-campo'), true);
            }
        });

    $(document)
        .off('blur.abrevTipoVenta', '.abreviaturatipotransaccionventa')
        .on('blur.abrevTipoVenta', '.abreviaturatipotransaccionventa', function () {
            if (abriendoModalTipotransaccionVenta || $('#consultatipotransaccionventaModal').hasClass('show')) {
                return;
            }
            var $input = $(this);
            if ($input.data('enter-procesado')) {
                $input.removeData('enter-procesado');
                return;
            }
            var $ctx = $input.closest('.tm-tipotransaccion-venta-campo');
            var idActual = parseInt(String($ctx.find('.tipotransaccion_venta_id').val() || '0'), 10);
            var abrev = $.trim($input.val() || '');
            if (abrev === '') {
                limpiarTipotransaccionVentaEnContexto($ctx, false);
                return;
            }
            if (idActual > 0) {
                return;
            }
            resolverTipotransaccionVentaPorAbreviatura(abrev, $ctx, false);
        });

    $(document)
        .off('input.abrevTipoVenta', '.abreviaturatipotransaccionventa')
        .on('input.abrevTipoVenta', '.abreviaturatipotransaccionventa', function () {
            var $input = $(this);
            $input.removeData('ultima-valida');
            var $ctx = $input.closest('.tm-tipotransaccion-venta-campo');
            var $hidden = $ctx.find('.tipotransaccion_venta_id');
            var prev = String($hidden.val() || '');
            $hidden.val('');
            estamparMetaTipotransaccionVenta($hidden, {});
            $ctx.find('.nombretipotransaccionventa').val('');
            actualizarLinkEditarTipotransaccionVenta($ctx, 0);
            if (prev !== '') {
                $hidden.trigger('change');
            }
        });
}

$(function () {
    if ($('.tm-tipotransaccion-venta-campo, .consultatipotransaccionventa').length) {
        activa_eventos_consultatipotransaccionventa();
    }
});
