var proveedorxcodigo = $();
var ptrproveedor_id = $();
var ptrnombreproveedor = $();
var ptrcodigoproveedor = $();

/** Evita reconsultar/alertar el mismo código inválido en cada blur (loop con alert). */
var ultimoCodigoProveedorIntentado = null;
var ultimoCodigoProveedorFallo = false;
var avisoProveedorMostrandose = false;

function actualizarCondicionPagoProveedorDesdeJson(data) {
    if ($('#condicionpago_proveedor_show').length) {
        if (data && data.condicionpagos && data.condicionpagos.nombre) {
            $('#condicionpago_proveedor_show').val(data.condicionpagos.nombre);
        } else {
            $('#condicionpago_proveedor_show').val('');
        }
    }

    // Cabecera OC (u otros formularios): defaults del maestro proveedor.
    if (!data) {
        return;
    }
    if ($('#condicionpago_id').length && data.condicionpago_id) {
        $('#condicionpago_id').val(String(data.condicionpago_id));
    }
    if ($('#condicioncompra_id').length && data.condicioncompra_id) {
        $('#condicioncompra_id').val(String(data.condicioncompra_id));
    }
    if ($('#condicionentrega_id').length && data.condicionentrega_id) {
        $('#condicionentrega_id').val(String(data.condicionentrega_id));
    }
}

function parsearHtmlConsultaProveedor(respuesta) {
    // No strippear "\": rompe class=\"...\" del JSON y el tbody queda vacío (Surmar ~2700 filas).
    if (respuesta && typeof respuesta === 'object') {
        return respuesta.data || '';
    }
    var resp = String(respuesta || '').trim();
    if (resp === '') {
        return '';
    }
    try {
        var parsed = JSON.parse(resp);
        return (parsed && parsed.data) ? parsed.data : '';
    } catch (e) {
        // Respuesta ya es HTML de filas (legado).
        if (resp.indexOf('<tr') >= 0) {
            return resp;
        }
        return '';
    }
}

function empresaIdConsultaProveedor() {
    var $emp = $('#empresa_id');
    if (!$emp.length || String($emp.val() || '').trim() === '') {
        $emp = $('#wz_empresa_id');
    }
    if (!$emp.length) {
        return '';
    }
    var v = parseInt(String($emp.val() || '0'), 10);
    return v > 0 ? String(v) : '';
}

function actualizarAvisoConsultaProveedor(empresaId) {
    var $aviso = $('#consultaproveedor-aviso');
    if (!$aviso.length) {
        return;
    }
    if (empresaId) {
        $aviso
            .removeClass('d-none')
            .text('Filtrando proveedores de la empresa id ' + empresaId + ' (y multiempresa).');
    } else {
        $aviso.addClass('d-none').text('');
    }
}

function urlAppCompras(path) {
    var base = (typeof window.carpetaBase === 'string') ? window.carpetaBase.replace(/\/$/, '') : '';
    var p = String(path || '');
    if (p.charAt(0) !== '/') {
        p = '/' + p;
    }
    return base + p;
}

function buscar_datos_proveedor(consulta) {
    var payload = {
        consulta: consulta,
        _token: $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').first().val()
    };
    var empresaId = empresaIdConsultaProveedor();
    if (empresaId !== '') {
        payload.empresa_id = empresaId;
    }
    actualizarAvisoConsultaProveedor(empresaId);
    $('#datosproveedor').html('<tr><td colspan="8" class="text-muted">Buscando…</td></tr>');
    $.ajax({
        url: urlAppCompras('/compras/proveedor/consultaproveedor'),
        type: 'POST',
        dataType: 'json',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        data: payload,
    })
        .done(function (respuesta) {
            var html = parsearHtmlConsultaProveedor(respuesta);
            if (!html) {
                html = '<tr><td colspan="8" class="text-muted">Sin resultados</td></tr>';
            }
            $('#datosproveedor').html(html);
        })
        .fail(function (xhr) {
            var msg = 'Error al consultar proveedores';
            if (xhr && xhr.status) {
                msg += ' (HTTP ' + xhr.status + ')';
            }
            $('#datosproveedor').html('<tr><td colspan="8" class="text-danger">' + msg + '</td></tr>');
        });
}

function resolverPtrProveedorDesdeBoton($btn) {
    var $ctx = $btn.closest('#div-proveedor, #div-proveedor-oc, .tm-proveedor-campo, tr');
    if ($ctx.length) {
        return {
            $id: $ctx.find('#proveedor_id, .proveedor_id').first(),
            $nombre: $ctx.find('#nombreproveedor, .nombreproveedor, #descripcionproveedor, .descripcionproveedor').first(),
            $codigo: $ctx.find('#codigoproveedor, .codigoproveedor').first(),
        };
    }
    return {
        $id: $('#proveedor_id'),
        $nombre: $('#nombreproveedor, #descripcionproveedor').first(),
        $codigo: $('#codigoproveedor'),
    };
}

function leerFilaProveedorConsulta($link) {
    var $tr = $link.closest('tr');
    return {
        id: $.trim($tr.find('td.proveedor_id').first().text()),
        nombre: $.trim($tr.find('td.nombreproveedor').first().text()),
        codigo: $.trim($tr.find('td.codigoproveedor').first().text()),
    };
}

function actualizarLinkEditarProveedor(proveedorId) {
    var $cfg = $('#cp-proveedor-arca-config');
    var tpl = $cfg.length ? String($cfg.data('url-editar-proveedor') || '') : '';
    var $link = $('.editarproveedor');
    if (!$link.length || !tpl) {
        return;
    }
    var id = parseInt(proveedorId || '0', 10);
    $link.attr('href', id > 0 ? tpl.replace('__ID__', String(id)) : '#');
}

function marcarProveedorConsultaOk(codigo) {
    ultimoCodigoProveedorIntentado = codigo != null && String(codigo).trim() !== ''
        ? String(codigo).trim()
        : null;
    ultimoCodigoProveedorFallo = false;
}

function marcarProveedorConsultaFallo(codigo) {
    ultimoCodigoProveedorIntentado = codigo != null && String(codigo).trim() !== ''
        ? String(codigo).trim()
        : null;
    ultimoCodigoProveedorFallo = true;
}

function limpiarEstadoConsultaProveedor() {
    ultimoCodigoProveedorIntentado = null;
    ultimoCodigoProveedorFallo = false;
}

function avisarProveedorNoCargado(mensaje) {
    if (avisoProveedorMostrandose) {
        return;
    }
    avisoProveedorMostrandose = true;
    try {
        alert(mensaje || 'No se pudo cargar el proveedor.');
    } finally {
        avisoProveedorMostrandose = false;
    }
    var $codigo = $('#codigoproveedor');
    if ($codigo.length && !$codigo.prop('readonly') && !$codigo.prop('disabled')) {
        $codigo.data('cp-skip-blur-once', 1);
        setTimeout(function () {
            $codigo.trigger('focus');
        }, 0);
    }
}

function limpiarProveedorEnPantalla() {
    limpiarEstadoConsultaProveedor();
    $('#proveedor_id').val('');
    $('#codigoproveedor').val('');
    $('#nombreproveedor, #descripcionproveedor, .descripcionproveedor').val('');
    $('#proveedor').val('');
    actualizarCondicionPagoProveedorDesdeJson(null);
    actualizarLinkEditarProveedor(0);
    if (typeof window.cpLimpiarAvisoProveedorArca === 'function') {
        window.cpLimpiarAvisoProveedorArca();
    }
}

function limpiarProveedorEnPantallaManteniendoCodigo() {
    $('#proveedor_id').val('');
    $('#nombreproveedor, #descripcionproveedor, .descripcionproveedor').val('');
    $('#proveedor').val('');
    actualizarCondicionPagoProveedorDesdeJson(null);
    actualizarLinkEditarProveedor(0);
    if (typeof window.cpLimpiarAvisoProveedorArca === 'function') {
        window.cpLimpiarAvisoProveedorArca();
    }
}

function esTeclaF1Proveedor(e) {
    return e.key === 'F1' || e.code === 'F1' || e.keyCode === 112;
}

function modalConsultaProveedorAbierto() {
    var $m = $('#consultaproveedorModal');
    return $m.length && $m.hasClass('show');
}

function resolverContextoProveedorDesdeInput($input) {
    var $ctx = $input.closest('#div-proveedor, #div-proveedor-oc, .tm-proveedor-campo, tr');
    if (!$ctx.length) {
        $ctx = $('#div-proveedor-oc').length ? $('#div-proveedor-oc') : $('#div-proveedor');
    }
    return $ctx;
}

function abrirModalConsultaProveedorDesdeInput($input) {
    var $ctx = resolverContextoProveedorDesdeInput($input);
    var $btn = $ctx.find('.consultaproveedor').first();
    var ctx = resolverPtrProveedorDesdeBoton($btn.length ? $btn : $input);
    ptrproveedor_id = ctx.$id;
    ptrnombreproveedor = ctx.$nombre;
    ptrcodigoproveedor = ctx.$codigo;
    proveedorxcodigo = ctx.$codigo;
    if (typeof buscar_datos_proveedor === 'function') {
        buscar_datos_proveedor('');
    }
    $('#consultaproveedorModal').modal('show');
}

function aceptarCodigoProveedorDesdeInput($input) {
    var codigo = String($input.val() || '').trim();
    if (codigo === '') {
        limpiarProveedorEnPantalla();
        return;
    }
    // Mismo código ya fallido y sin id cargado: no repetir GET ni alert (sale del loop blur/alert).
    if (
        ultimoCodigoProveedorFallo &&
        ultimoCodigoProveedorIntentado === codigo &&
        !(parseInt(String($('#proveedor_id').val() || '0'), 10) > 0)
    ) {
        return;
    }
    leeUnProveedor(0, codigo);
}

var timerLeeProveedorCodigo = null;

function programarLeeProveedorPorCodigo($input) {
    if (timerLeeProveedorCodigo) {
        clearTimeout(timerLeeProveedorCodigo);
    }
    timerLeeProveedorCodigo = setTimeout(function () {
        timerLeeProveedorCodigo = null;
        aceptarCodigoProveedorDesdeInput($input);
    }, 150);
}

function aplicarProveedorEnPantalla(data, ctx) {
    if (!data || !data.id) {
        limpiarProveedorEnPantalla();
        return;
    }

    marcarProveedorConsultaOk(data.codigo || '');

    var dest = ctx || resolverPtrProveedorDesdeBoton($('#div-proveedor'));
    if (dest.$id && dest.$id.length) {
        dest.$id.val(data.id);
    }
    if (dest.$nombre && dest.$nombre.length) {
        dest.$nombre.val(data.nombre || '');
    }
    if (dest.$codigo && dest.$codigo.length) {
        dest.$codigo.val(data.codigo || '');
    }

    $('#proveedor_id').val(data.id);
    $('#nombreproveedor, #descripcionproveedor, .descripcionproveedor').val(data.nombre || '');
    $('#codigoproveedor').val(data.codigo || '');
    $('#proveedor').val(data.nombre || '');

    actualizarCondicionPagoProveedorDesdeJson(data);
    actualizarLinkEditarProveedor(data.id);

    if (typeof window.cpValidarProveedorArca === 'function') {
        window.cpValidarProveedorArca(data.id, data.condicioniva_id);
    }
    if (typeof window.cpValidarProveedorArcaApoc === 'function') {
        window.cpValidarProveedorArcaApoc(data.id);
    }

    $('#proveedor_id').trigger('change.cpProveedorCargado');

    if (typeof window.ieComprobanteIvaAplicarProveedor === 'function' && $('#modal-ie-comprobante-iva').hasClass('show')) {
        window.ieComprobanteIvaAplicarProveedor(data.id, data.nombre || '');
    }
}

function leeUnProveedor(proveedorId, codigoproveedor) {
    var url = '';
    var codigoPedido = '';
    if ($.isNumeric(proveedorId) && parseInt(proveedorId, 10) > 0) {
        url = urlAppCompras('/compras/leerproveedor/' + proveedorId);
    } else if (codigoproveedor !== undefined && codigoproveedor !== null && String(codigoproveedor).trim() !== '') {
        codigoPedido = String(codigoproveedor).trim();
        url = urlAppCompras('/compras/leerproveedorporcodigo/' + encodeURIComponent(codigoPedido));
        var empresaId = empresaIdConsultaProveedor();
        if (empresaId !== '') {
            url += (url.indexOf('?') >= 0 ? '&' : '?') + 'empresa_id=' + encodeURIComponent(empresaId);
        }
    } else {
        limpiarProveedorEnPantalla();
        return;
    }

    limpiarProveedorEnPantallaManteniendoCodigo();

    $.get(url).done(function (data) {
        if (data && data.id) {
            aplicarProveedorEnPantalla(data);
            return;
        }
        marcarProveedorConsultaFallo(codigoPedido || String($('#codigoproveedor').val() || '').trim());
        limpiarProveedorEnPantallaManteniendoCodigo();
        avisarProveedorNoCargado('No se encontró el proveedor indicado.');
    }).fail(function () {
        marcarProveedorConsultaFallo(codigoPedido || String($('#codigoproveedor').val() || '').trim());
        limpiarProveedorEnPantallaManteniendoCodigo();
        avisarProveedorNoCargado('No se pudo cargar el proveedor.');
    });
}

// Enter en input: no dispara submit accidental, salvo formulario OC y códigos de consulta.
$(document).off('keydown.ocNoEnterSubmitProveedor', 'input').on('keydown.ocNoEnterSubmitProveedor', 'input', function (e) {
    if (e.which !== 13 && e.key !== 'Enter') {
        return;
    }
    if ($(this).closest('#form-ordencompra-general').length) {
        return;
    }
    if (
        $(this).hasClass('codigoproveedor') || $(this).is('#codigoproveedor') ||
        $(this).hasClass('codigoconcepto_solicitudpago') || $(this).is('#concepto_solicitudpago_id_codigo') ||
        $(this).hasClass('codigodeposito') ||
        $(this).hasClass('sku') || $(this).hasClass('codigoarticulo')
    ) {
        return;
    }
    e.preventDefault();
    return false;
});

// Enter en código proveedor: capture para ganar a bloqueos globales ($("input").keydown).
document.addEventListener('keydown', function (e) {
    if (!(e.key === 'Enter' || e.code === 'Enter' || e.keyCode === 13 || e.which === 13)) {
        return;
    }
    var target = e.target;
    if (!target || target.readOnly || target.disabled) {
        return;
    }
    if (!target.classList.contains('codigoproveedor') && target.id !== 'codigoproveedor') {
        return;
    }
    e.preventDefault();
    e.stopPropagation();
    $(target).data('cp-enter-procesado', 1);
    aceptarCodigoProveedorDesdeInput($(target));
}, true);

$(document)
    .off('keydown.cpCodigoProveedorEnter', '.codigoproveedor, #codigoproveedor')
    .on('keydown.cpCodigoProveedorEnter', '.codigoproveedor, #codigoproveedor', function (e) {
        if (e.which !== 13 && e.key !== 'Enter') {
            return;
        }
        // Si el capture ya lo procesó, no duplicar.
        if ($(this).data('cp-enter-procesado')) {
            e.preventDefault();
            e.stopPropagation();
            return;
        }
        $(this).data('cp-enter-procesado', 1);
        e.preventDefault();
        e.stopPropagation();
        aceptarCodigoProveedorDesdeInput($(this));
    });

document.addEventListener('keydown', function (e) {
    if (!esTeclaF1Proveedor(e)) {
        return;
    }
    var target = e.target;
    if (!target || (!target.classList.contains('codigoproveedor') && target.id !== 'codigoproveedor')) {
        return;
    }
    if (target.readOnly || target.disabled) {
        return;
    }
    if (modalConsultaProveedorAbierto()) {
        return;
    }
    e.preventDefault();
    e.stopPropagation();
    abrirModalConsultaProveedorDesdeInput($(target));
}, true);

$(document).on('keyup', '#consultaproveedor', function () {
    buscar_datos_proveedor(String($(this).val() || '').trim());
});

function activa_eventos_consultaproveedor() {
    $(document)
        .off('click.consultaProveedorAbrir', '.consultaproveedor')
        .on('click.consultaProveedorAbrir', '.consultaproveedor', function (event) {
            if ($(this).closest('#datosproveedor').length) {
                return;
            }
            event.preventDefault();
            var ctx = resolverPtrProveedorDesdeBoton($(this));
            ptrproveedor_id = ctx.$id;
            ptrnombreproveedor = ctx.$nombre;
            ptrcodigoproveedor = ctx.$codigo;
            proveedorxcodigo = ctx.$codigo;
            if (typeof buscar_datos_proveedor === 'function') {
                buscar_datos_proveedor('');
            }
            $('#consultaproveedorModal').modal('show');
        });

    $('#consultaproveedorModal')
        .off('shown.bs.modal.consultaProveedor')
        .on('shown.bs.modal.consultaProveedor', function () {
            $(this).find('[autofocus]').focus();
        });

    $('#aceptaconsultaproveedorModal')
        .off('click.consultaProveedor')
        .on('click.consultaProveedor', function () {
            $('#consultaproveedorModal').modal('hide');
        });

    $(document)
        .off('click.eligeConsultaProveedor', '.eligeconsultaproveedor')
        .on('click.eligeConsultaProveedor', '.eligeconsultaproveedor', function (event) {
            if (typeof window.wzCambiarProveedorLinea === 'number' || typeof window.wzCambiarProveedorGrupo === 'number') {
                return;
            }
            event.preventDefault();
            var fila = leerFilaProveedorConsulta($(this));
            if (!fila.id) {
                return;
            }
            $('#consultaproveedorModal').modal('hide');
            leeUnProveedor(fila.id, 0);
        });

    $(document)
        .off('click.verProveedorDesdeModal', '#datosproveedor .consultaproveedor')
        .on('click.verProveedorDesdeModal', '#datosproveedor .consultaproveedor', function (event) {
            event.preventDefault();
            var fila = leerFilaProveedorConsulta($(this));
            var id = parseInt(fila.id || '0', 10);
            if (id > 0 && typeof route === 'function') {
                document.location.href = route('editar_proveedor', id);
            }
        });

    $(document)
        .off('change.consultaProveedor blur.consultaProveedor', '.codigoproveedor, #codigoproveedor')
        .on('change.consultaProveedor blur.consultaProveedor', '.codigoproveedor, #codigoproveedor', function (event) {
            if (event.type === 'blur' && $(this).data('cp-enter-procesado')) {
                $(this).removeData('cp-enter-procesado');
                return;
            }
            if (event.type === 'blur' && $(this).data('cp-skip-blur-once')) {
                $(this).removeData('cp-skip-blur-once');
                return;
            }
            // Si el usuario corrige el código tras un fallo, permitir nueva consulta.
            if (event.type === 'change') {
                var codigoActual = String($(this).val() || '').trim();
                if (ultimoCodigoProveedorFallo && codigoActual !== ultimoCodigoProveedorIntentado) {
                    ultimoCodigoProveedorFallo = false;
                }
            }
            event.preventDefault();
            programarLeeProveedorPorCodigo($(this));
        });

    $('#proveedor_id')
        .off('change.consultaProveedor')
        .on('change.consultaProveedor', function (event) {
            event.preventDefault();
            var proveedorId = parseInt(String($(this).val() || '0'), 10);
            if (proveedorId > 0) {
                leeUnProveedor(proveedorId, 0);
            } else {
                limpiarProveedorEnPantalla();
            }
        });

    $('.proveedor_id')
        .off('change.consultaProveedorGrid')
        .on('change.consultaProveedorGrid', function (event) {
            event.preventDefault();
            var ptrrenglon = this;
            var proveedorId = parseInt(String($(this).val() || '0'), 10);
            if (proveedorId <= 0) {
                return;
            }

            $(ptrrenglon).closest('tr').find('.proveedor_id').val('');
            $(ptrrenglon).closest('tr').find('.codigoproveedor').val('');
            $(ptrrenglon).closest('tr').find('.nombreproveedor').val('');

            $.get(urlAppCompras('/compras/leerproveedor/' + proveedorId), function (data) {
                if (!data) {
                    return;
                }
                $(ptrrenglon).closest('tr').find('.proveedor_id').val(data.id);
                $(ptrrenglon).closest('tr').find('.codigoproveedor').val(data.codigo);
                $(ptrrenglon).closest('tr').find('.nombreproveedor').val(data.nombre);
                aplicarProveedorEnPantalla(data);
            });
        });
}

$(function () {
    if ($('#div-proveedor').length || $('.codigoproveedor').length || $('#consultaproveedorModal').length) {
        activa_eventos_consultaproveedor();
    }
});
