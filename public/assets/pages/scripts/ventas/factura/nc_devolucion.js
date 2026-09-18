var abriendoModalFacturaArticulo = false;
var buscaFacturaArticuloTimer = null;

function esTeclaF1FacturaArticuloNc(e) {
    return e.key === 'F1' || e.code === 'F1' || e.keyCode === 112;
}

function modalConsultaFacturaArticuloAbierto() {
    var $m = $('#consultafacturasarticuloModal');
    return $m.length && ($m.hasClass('show') || abriendoModalFacturaArticulo);
}

function parsearHtmlConsultaFacturaArticulo(respuesta) {
    var resp = String(respuesta || '').replace(/\\/g, '');
    try {
        var parsed = JSON.parse(resp);
        return parsed.data || '';
    } catch (e) {
        return resp;
    }
}

function clienteIdNcDevolucion() {
    return parseInt($('#cliente_id').val() || '0', 10) || 0;
}

function empresaIdNcDevolucion() {
    return parseInt($('#empresa_id').val() || '0', 10) || 0;
}

function articuloIdNcDevolucion() {
    return parseInt($('#nc_articulo_id').val() || '0', 10) || 0;
}

function urlGenerarNcDevolucion(ventaId, articuloId) {
    var base = window.NC_DEVOLUCION_URL_GENERAR || (carpetaBase + '/ventas/factura/generanotadecredito');
    var url = String(base).replace(/\/+$/, '') + '/' + parseInt(ventaId, 10);
    articuloId = parseInt(articuloId, 10) || 0;
    if (articuloId > 0) {
        url += (url.indexOf('?') >= 0 ? '&' : '?') + 'articulo_id=' + articuloId;
    }
    return url;
}

function buscarDatosFacturaArticulo(consulta) {
    var clienteId = clienteIdNcDevolucion();
    if (clienteId <= 0) {
        $('#datos-factura-articulo').html('<tr><td colspan="6" class="text-muted">Seleccione un cliente primero.</td></tr>');
        return;
    }
    $.ajax({
        url: carpetaBase + '/ventas/factura/consulta-facturas-por-articulo',
        type: 'POST',
        dataType: 'HTML',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        data: {
            consulta: consulta || '',
            cliente_id: clienteId,
            empresa_id: empresaIdNcDevolucion(),
            articulo_id: articuloIdNcDevolucion()
        }
    })
        .done(function (respuesta) {
            $('#datos-factura-articulo').html(parsearHtmlConsultaFacturaArticulo(respuesta));
        })
        .fail(function () {
            $('#datos-factura-articulo').html('<tr><td colspan="6">Error al consultar facturas</td></tr>');
        });
}

function abrirModalFacturasArticulo() {
    var clienteId = clienteIdNcDevolucion();
    if (clienteId <= 0) {
        alert('Seleccione el cliente antes de buscar facturas.');
        $('#codigocliente').focus();
        return;
    }
    abriendoModalFacturaArticulo = true;
    var nombre = $.trim($('#nombrecliente').val() || '');
    var codigoCli = $.trim($('#codigocliente').val() || '');
    var sku = $.trim($('#nc_articulo_id_codigo').val() || '');
    var desc = $.trim($('#nc_articulo_id_descripcion').val() || '');
    var txt = 'Cliente: ' + (codigoCli ? codigoCli + ' — ' : '') + (nombre || ('#' + clienteId));
    if (sku || desc) {
        txt += ' · Artículo: ' + (sku ? sku + ' — ' : '') + desc;
    } else {
        txt += ' · Últimas facturas (indique un artículo para filtrar).';
    }
    $('#consulta-factura-articulo-contexto').text(txt);
    $('#consulta-factura-articulo').val('');
    buscarDatosFacturaArticulo('');
    var $modal = $('#consultafacturasarticuloModal');
    $modal.one('shown.bs.modal', function () {
        abriendoModalFacturaArticulo = false;
        $('#consulta-factura-articulo').focus();
    });
    $modal.one('hidden.bs.modal', function () {
        abriendoModalFacturaArticulo = false;
    });
    $modal.modal('show');
}

function aplicarFacturaOrigenNc(ventaId, codigo) {
    ventaId = parseInt(ventaId, 10) || 0;
    if (ventaId <= 0) {
        alert('Esa factura no está en el ERP; no se pueden cargar los ítems.');
        return;
    }
    var actual = parseInt($('#venta_id').val() || '0', 10) || 0;
    var articuloId = articuloIdNcDevolucion();
    var destino = urlGenerarNcDevolucion(ventaId, articuloId);
    if (actual === ventaId && $('#formgeneral').attr('data-factura-proceso') === 'nc') {
        if (articuloId > 0 && typeof window.ncDevolucionMarcarArticulo === 'function') {
            window.ncDevolucionMarcarArticulo(articuloId);
        }
        return;
    }
    window.location.href = destino;
}

function ncDevolucionFilasOrigen() {
    return $('#tbody-tabla tr.item-factura, #tbody-tabla tr.item-pedido').filter(function () {
        return $(this).find('.nc-devolver').length > 0;
    });
}

function aplicarEstadoLineaNcDevolver($tr) {
    var $chk = $tr.find('.nc-devolver');
    if (!$chk.length) {
        return;
    }
    var activo = $chk.is(':checked') && !$chk.prop('disabled');
    $tr.toggleClass('nc-linea-excluida', !activo);
    $tr.find('input, select, textarea').not('.nc-devolver').each(function () {
        this.disabled = !activo;
    });
}

function aplicarEstadoTodasLineasNc() {
    ncDevolucionFilasOrigen().each(function () {
        aplicarEstadoLineaNcDevolver($(this));
    });
}

function ncDevolucionMarcarSegunCriterio(modo, articuloId) {
    articuloId = parseInt(articuloId, 10) || 0;
    ncDevolucionFilasOrigen().each(function () {
        var $tr = $(this);
        var $chk = $tr.find('.nc-devolver');
        if ($chk.prop('disabled')) {
            return;
        }
        var art = parseInt($tr.attr('data-articulo-id') || '0', 10) || 0;
        var marcar = false;
        if (modo === 'todas') {
            marcar = true;
        } else if (modo === 'ninguna') {
            marcar = false;
        } else if (modo === 'articulo') {
            marcar = articuloId > 0 && art === articuloId;
        } else if (modo === 'modulo') {
            var moduloId = parseInt($tr.attr('data-modulo-id') || '0', 10) || 0;
            var combId = parseInt($tr.attr('data-combinacion-id') || '0', 10) || 0;
            var refModulo = parseInt($('#nc-devolver-modulo-ref').val() || '0', 10) || 0;
            var refComb = parseInt($('#nc-devolver-comb-ref').val() || '0', 10) || 0;
            marcar = (refComb > 0 && combId === refComb) || (refModulo > 0 && moduloId === refModulo);
        }
        $chk.prop('checked', marcar);
        aplicarEstadoLineaNcDevolver($tr);
    });
    if (typeof calculaFactura === 'function') {
        calculaFactura();
    }
}

window.ncDevolucionMarcarArticulo = function (articuloId) {
    ncDevolucionMarcarSegunCriterio('articulo', articuloId);
};

window.ncDevolucionHayLineasActivas = function () {
    var hay = false;
    ncDevolucionFilasOrigen().each(function () {
        var $chk = $(this).find('.nc-devolver');
        if ($chk.length && $chk.is(':checked') && !$chk.prop('disabled')) {
            hay = true;
            return false;
        }
    });
    if (hay) {
        return true;
    }
    if (!ncDevolucionFilasOrigen().length) {
        return true;
    }
    return false;
};

function ncDevolucionValidarSubmit() {
    if ($('#formgeneral').attr('data-factura-proceso') !== 'nc') {
        return true;
    }
    if (!ncDevolucionFilasOrigen().length) {
        return true;
    }
    if (!window.ncDevolucionHayLineasActivas()) {
        alert('Marque al menos un artículo a devolver, o use Devolver todo.');
        return false;
    }
    var $wrap = $('#fce-nc-mostrador-wrap');
    var origenFce = $wrap.length && String($wrap.attr('data-nc-origen-fce') || '0') === '1';
    if (origenFce) {
        var totalFilas = ncDevolucionFilasOrigen().length;
        var activas = 0;
        ncDevolucionFilasOrigen().each(function () {
            var $chk = $(this).find('.nc-devolver');
            if ($chk.length && $chk.is(':checked') && !$chk.prop('disabled')) {
                activas += 1;
            }
        });
        if (activas < totalFilas) {
            var anul = $.trim($('#fce_anulacion').val() || '').toUpperCase();
            if (anul === '') {
                $('#fce_anulacion').val('N');
            }
        }
    }
    return true;
}

window.ncDevolucionValidarSubmit = ncDevolucionValidarSubmit;

function activa_eventos_nc_devolucion() {
    $(document).off('click.ncDevBuscar', '#btn-buscar-facturas-articulo').on('click.ncDevBuscar', '#btn-buscar-facturas-articulo', function (e) {
        e.preventDefault();
        abrirModalFacturasArticulo();
    });

    $(document).off('keydown.ncDevSku', '#nc_articulo_id_codigo').on('keydown.ncDevSku', '#nc_articulo_id_codigo', function (e) {
        if (esTeclaF1FacturaArticuloNc(e)) {
            return;
        }
        if (e.key === 'Enter' || e.keyCode === 13) {
            e.preventDefault();
            var id = articuloIdNcDevolucion();
            setTimeout(function () {
                if (articuloIdNcDevolucion() > 0 || id > 0) {
                    abrirModalFacturasArticulo();
                }
            }, 200);
        }
    });

    $(document).off('input.ncDevBuscar', '#consulta-factura-articulo').on('input.ncDevBuscar', '#consulta-factura-articulo', function () {
        var v = $(this).val() || '';
        clearTimeout(buscaFacturaArticuloTimer);
        buscaFacturaArticuloTimer = setTimeout(function () {
            buscarDatosFacturaArticulo(v);
        }, 250);
    });

    $(document).off('keydown.ncDevBuscar', '#consulta-factura-articulo').on('keydown.ncDevBuscar', '#consulta-factura-articulo', function (e) {
        if (e.key !== 'Enter' && e.keyCode !== 13) {
            return;
        }
        e.preventDefault();
        var $first = $('#datos-factura-articulo .eligeconsultafacturaarticulo').first();
        if ($first.length) {
            $first.trigger('click');
        }
    });

    $(document).off('click.ncDevElige', '.eligeconsultafacturaarticulo').on('click.ncDevElige', '.eligeconsultafacturaarticulo', function (e) {
        e.preventDefault();
        var $tr = $(this).closest('tr');
        var ventaId = parseInt($tr.attr('data-venta-id') || '0', 10) || 0;
        var codigo = $.trim($tr.find('.venta_codigo').first().text());
        if ($('#consultafacturasarticuloModal').hasClass('show')) {
            $('#consultafacturasarticuloModal').modal('hide');
        }
        setTimeout(function () {
            aplicarFacturaOrigenNc(ventaId, codigo);
        }, 0);
    });

    $(document).off('change.ncDevChk', '.nc-devolver').on('change.ncDevChk', '.nc-devolver', function () {
        aplicarEstadoLineaNcDevolver($(this).closest('tr'));
        if (typeof calculaFactura === 'function') {
            calculaFactura();
        }
    });

    $(document).off('click.ncDevTodo', '#nc-devolver-todo').on('click.ncDevTodo', '#nc-devolver-todo', function (e) {
        e.preventDefault();
        ncDevolucionMarcarSegunCriterio('todas', 0);
    });

    $(document).off('click.ncDevNinguno', '#nc-devolver-ninguno').on('click.ncDevNinguno', '#nc-devolver-ninguno', function (e) {
        e.preventDefault();
        ncDevolucionMarcarSegunCriterio('ninguna', 0);
    });

    $(document).off('click.ncDevArticulo', '#nc-devolver-articulo').on('click.ncDevArticulo', '#nc-devolver-articulo', function (e) {
        e.preventDefault();
        var art = articuloIdNcDevolucion();
        if (art <= 0) {
            alert('Indique un artículo en Referencia para marcar solo esas líneas.');
            $('#nc_articulo_id_codigo').focus();
            return;
        }
        ncDevolucionMarcarSegunCriterio('articulo', art);
    });

    $(document).off('click.ncDevModulo', '.nc-devolver-mismo-modulo').on('click.ncDevModulo', '.nc-devolver-mismo-modulo', function (e) {
        e.preventDefault();
        var $tr = $(this).closest('tr');
        $('#nc-devolver-modulo-ref').val($tr.attr('data-modulo-id') || '0');
        $('#nc-devolver-comb-ref').val($tr.attr('data-combinacion-id') || '0');
        ncDevolucionMarcarSegunCriterio('modulo', 0);
    });

    $(document).off('blur.ncDevCant', '#tbody-tabla .nc-linea-origen .cantidad, #tbody-tabla .nc-linea-origen .kilo')
        .on('blur.ncDevCant', '#tbody-tabla .nc-linea-origen .cantidad, #tbody-tabla .nc-linea-origen .kilo', function () {
            var $inp = $(this);
            var $tr = $inp.closest('tr');
            var max = parseFloat($tr.attr('data-cantidad-pendiente') || '');
            if (!(max > 0)) {
                return;
            }
            var val = parseFloat(String($inp.val() || '0').replace(',', '.'));
            if (isNaN(val) || val < 0) {
                val = 0;
            }
            if (val > max) {
                $inp.val(max.toFixed(2));
                if (typeof calculaFactura === 'function') {
                    calculaFactura();
                }
            }
        });
}

$(function () {
    if ($('#tm_articulo_nc_devolucion').length || $('.nc-devolver').length) {
        activa_eventos_nc_devolucion();
        aplicarEstadoTodasLineasNc();
        if (typeof calculaFactura === 'function' && $('#formgeneral').attr('data-factura-proceso') === 'nc') {
            calculaFactura();
        }
    }
});
