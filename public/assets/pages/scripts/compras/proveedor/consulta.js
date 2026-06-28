var proveedorxcodigo = $();
var ptrproveedor_id = $();
var ptrnombreproveedor = $();
var ptrcodigoproveedor = $();

function actualizarCondicionPagoProveedorDesdeJson(data) {
    if (!$('#condicionpago_proveedor_show').length) {
        return;
    }
    if (data && data.condicionpagos && data.condicionpagos.nombre) {
        $('#condicionpago_proveedor_show').val(data.condicionpagos.nombre);
    } else {
        $('#condicionpago_proveedor_show').val('');
    }
}

function parsearHtmlConsultaProveedor(respuesta) {
    var resp = String(respuesta || '').replace(/\\/g, '');
    try {
        var parsed = JSON.parse(resp);
        return parsed.data || '';
    } catch (e) {
        return resp;
    }
}

function buscar_datos_proveedor(consulta) {
    $.ajax({
        url: carpetaBase + '/compras/proveedor/consultaproveedor',
        type: 'POST',
        dataType: 'HTML',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        data: {
            consulta: consulta
        },
    })
        .done(function (respuesta) {
            $('#datosproveedor').html(parsearHtmlConsultaProveedor(respuesta));
        })
        .fail(function () {
            console.log('error consulta proveedor');
        });
}

function resolverPtrProveedorDesdeBoton($btn) {
    var $ctx = $btn.closest('#div-proveedor, tr');
    if ($ctx.length) {
        return {
            $id: $ctx.find('#proveedor_id, .proveedor_id').first(),
            $nombre: $ctx.find('#nombreproveedor, .nombreproveedor').first(),
            $codigo: $ctx.find('#codigoproveedor, .codigoproveedor').first(),
        };
    }
    return {
        $id: $('#proveedor_id'),
        $nombre: $('#nombreproveedor'),
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

function limpiarProveedorEnPantalla() {
    $('#proveedor_id').val('');
    $('#codigoproveedor').val('');
    $('#nombreproveedor').val('');
    $('#proveedor').val('');
    actualizarCondicionPagoProveedorDesdeJson(null);
    actualizarLinkEditarProveedor(0);
    if (typeof window.cpLimpiarAvisoProveedorArca === 'function') {
        window.cpLimpiarAvisoProveedorArca();
    }
}

function limpiarProveedorEnPantallaManteniendoCodigo() {
    $('#proveedor_id').val('');
    $('#nombreproveedor').val('');
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
    var $ctx = $input.closest('#div-proveedor, tr');
    if (!$ctx.length) {
        $ctx = $('#div-proveedor');
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
    $('#nombreproveedor').val(data.nombre || '');
    $('#codigoproveedor').val(data.codigo || '');
    $('#proveedor').val(data.nombre || '');

    actualizarCondicionPagoProveedorDesdeJson(data);
    actualizarLinkEditarProveedor(data.id);

    if (typeof window.cpValidarProveedorArca === 'function') {
        window.cpValidarProveedorArca(data.id, data.condicioniva_id);
    }

    $('#proveedor_id').trigger('change.cpProveedorCargado');

    if (typeof window.ieComprobanteIvaAplicarProveedor === 'function' && $('#modal-ie-comprobante-iva').hasClass('show')) {
        window.ieComprobanteIvaAplicarProveedor(data.id, data.nombre || '');
    }
}

function leeUnProveedor(proveedorId, codigoproveedor) {
    var url = '';
    if ($.isNumeric(proveedorId) && parseInt(proveedorId, 10) > 0) {
        url = carpetaBase + '/compras/leerproveedor/' + proveedorId;
    } else if (codigoproveedor !== undefined && codigoproveedor !== null && String(codigoproveedor).trim() !== '') {
        url = carpetaBase + '/compras/leerproveedorporcodigo/' + encodeURIComponent(String(codigoproveedor).trim());
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
        limpiarProveedorEnPantallaManteniendoCodigo();
        alert('No se encontró el proveedor indicado.');
    }).fail(function () {
        limpiarProveedorEnPantallaManteniendoCodigo();
        alert('No se pudo cargar el proveedor.');
    });
}

// Enter en input: no dispara submit accidental, salvo formulario OC y código de proveedor.
$(document).off('keydown.ocNoEnterSubmitProveedor', 'input').on('keydown.ocNoEnterSubmitProveedor', 'input', function (e) {
    if (e.which !== 13) {
        return;
    }
    if ($(this).closest('#form-ordencompra-general').length) {
        return;
    }
    if ($(this).hasClass('codigoproveedor') || $(this).is('#codigoproveedor')) {
        return;
    }
    e.preventDefault();
    return false;
});

$(document)
    .off('keydown.cpCodigoProveedorEnter', '.codigoproveedor, #codigoproveedor')
    .on('keydown.cpCodigoProveedorEnter', '.codigoproveedor, #codigoproveedor', function (e) {
        if (e.which !== 13) {
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

            $.get(carpetaBase + '/compras/leerproveedor/' + proveedorId, function (data) {
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
