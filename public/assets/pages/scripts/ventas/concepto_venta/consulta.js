var ptrConceptoVentaId = $();
var ptrCodigoConceptoVenta = $();
var ptrNombreConceptoVenta = $();
var ptrFilaConceptoVenta = $();
var conceptoVentaInvalidoMarcado = false;
var abriendoModalConceptoVenta = false;

function esTeclaF1ConceptoVenta(e) {
    return e.key === 'F1' || e.code === 'F1' || e.keyCode === 112;
}

function modalConsultaConceptoVentaAbierto() {
    var $m = $('#consultaconceptoventaModal');
    return $m.length && ($m.hasClass('show') || abriendoModalConceptoVenta);
}

function parsearHtmlConsultaConceptoVenta(respuesta) {
    var resp = String(respuesta || '').replace(/\\/g, '');
    try {
        var parsed = JSON.parse(resp);
        return parsed.data || '';
    } catch (e) {
        return resp;
    }
}

function buscar_datos_concepto_venta(consulta) {
    $.ajax({
        url: carpetaBase + '/ventas/concepto-venta/consulta',
        type: 'POST',
        dataType: 'HTML',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        data: {
            consulta: consulta || ''
        }
    })
        .done(function (respuesta) {
            $('#datosconceptoventa').html(parsearHtmlConsultaConceptoVenta(respuesta));
        })
        .fail(function () {
            console.log('error consulta concepto venta');
        });
}

function actualizarLinkEditarConceptoVenta($ctx, conceptoId) {
    if (!$ctx || !$ctx.length) {
        return;
    }
    var $link = $ctx.find('.btn-link-editar-concepto-venta');
    if (!$link.length) {
        return;
    }
    var id = parseInt(conceptoId, 10) || 0;
    if (id > 0) {
        $link
            .attr('href', carpetaBase + '/ventas/concepto-venta/' + id + '/editar?origen=modal_consulta&vista=consulta')
            .removeClass('d-none');
    } else {
        $link.attr('href', '#').addClass('d-none');
    }
}

function dataConceptoDesdeFila($row) {
    return {
        id: $.trim($row.find('.concepto_venta_id').text()),
        codigo: $.trim($row.find('.codigoconceptoventa').text()),
        nombre: $.trim($row.find('.nombreconceptoventa').text()),
        descripcion: $.trim($row.find('.descripcionconceptoventa').text()),
        codigo_gtin: $.trim($row.find('.gtinconceptoventa').text()),
        impuesto_id: $.trim($row.attr('data-impuesto-id') || '')
    };
}

function aplicarConceptoVentaEnCampo($ctx, data) {
    if (!$ctx || !$ctx.length) {
        return;
    }
    $ctx.find('.concepto_venta_id').val(data.id);
    $ctx.find('.codigoconceptoventa').val(data.codigo);
    $ctx.find('.nombreconceptoventa').val(data.nombre || data.descripcion);
    actualizarLinkEditarConceptoVenta($ctx, data.id);
    if ($ctx.closest('#concepto-venta-comprobante-wrap').length) {
        aplicarConceptoVentaCabeceraALineasVacias();
    }
}

function facturaConceptoObligatorioSinArticulo() {
    if (window.FACTURA_CONCEPTO_OBLIGATORIO_SIN_ARTICULO) {
        return true;
    }
    var pvId = parseInt($('#puntoventa_id').val() || '0', 10);
    var lista = [];
    try {
        lista = JSON.parse((document.querySelector('#datosfactura') || {}).dataset.puntoventa || '[]');
    } catch (e) {
        lista = [];
    }
    if (!Array.isArray(lista)) {
        lista = Object.keys(lista || {}).map(function (k) { return lista[k]; });
    }
    var pv = lista.find(function (item) {
        return item && parseInt(item.id, 10) === pvId;
    });
    var ws = String((pv && pv.webservice) || '').toLowerCase();
    return ws === 'wsmtxca' || ws === 'mtxca' || ws === 'mtxsca';
}

function tipoSeleccionadoTieneConceptoAsignado() {
    var $opt = $('#tipotransaccion_id option:selected');
    return $opt.length && parseInt($opt.attr('data-concepto-venta-id') || '0', 10) > 0;
}

function tipoSeleccionadoEsNcNd() {
    var $opt = $('#tipotransaccion_id option:selected');
    if (!$opt.length) {
        return false;
    }
    var abr = String($opt.attr('data-abreviatura') || '').toUpperCase();
    var op = String($opt.attr('data-operacion') || '');
    return op === 'C' || abr.indexOf('ND') === 0;
}

function tipoSeleccionadoUsaConceptoVenta() {
    return tipoSeleccionadoTieneConceptoAsignado();
}

function dataConceptoDesdeTipoSeleccionado() {
    var $opt = $('#tipotransaccion_id option:selected');
    if (!$opt.length || !$opt.val()) {
        return null;
    }
    var id = parseInt($opt.attr('data-concepto-venta-id') || '0', 10);
    if (id <= 0) {
        return null;
    }
    return {
        id: String(id),
        codigo: $opt.attr('data-concepto-codigo') || '',
        nombre: $opt.attr('data-concepto-nombre') || '',
        descripcion: $opt.attr('data-concepto-descripcion') || '',
        impuesto_id: $opt.attr('data-concepto-impuesto-id') || ''
    };
}

function aplicarConceptoVentaCabeceraALineasVacias() {
    if (!tipoSeleccionadoEsNcNd()) {
        return;
    }
    var $cab = $('#concepto-venta-comprobante-wrap .tm-concepto-venta-campo');
    if (!$cab.length) {
        return;
    }
    var data = {
        id: $cab.find('.concepto_venta_id').val(),
        codigo: $cab.find('.codigoconceptoventa').val(),
        nombre: $cab.find('.nombreconceptoventa').val(),
        descripcion: $cab.find('.nombreconceptoventa').val(),
        impuesto_id: ''
    };
    if (!data.id) {
        return;
    }
    $('#tbody-tabla tr.item-factura, #tbody-tabla tr.item-pedido').each(function () {
        var $tr = $(this);
        if ($.trim($tr.find('.articulo_id').val() || '') !== '') {
            return;
        }
        aplicarConceptoVentaEnFilaFactura($tr, data);
    });
}

function limpiarConceptoVentaEnLineasSinArticulo() {
    $('#tbody-tabla tr.item-factura, #tbody-tabla tr.item-pedido').each(function () {
        var $tr = $(this);
        if ($.trim($tr.find('.articulo_id').val() || '') !== '') {
            return;
        }
        if ($.trim($tr.find('.concepto_venta_id').val() || '') === '') {
            return;
        }
        $tr.find('.concepto_venta_id').val('');
        facturaLineaModoConcepto($tr, false);
        $tr.find('.codigoarticulo').val('');
        $tr.find('.codigo_previo_articulo').val('');
        $tr.find('.descripcionarticulo').val('').prop('readonly', !!window.FL_FACTURA_LAYOUT_PEDIDO);
        $tr.find('.caja, .pieza').prop('readonly', false);
        $tr.find('.kilo').val('').prop('readonly', false);
        $tr.find('.cantidad').val('');
        $tr.find('.factura-iva-linea').val('');
        $tr.find('.precio').val('').prop('readonly', !!window.FL_FACTURA_LAYOUT_PEDIDO);
    });
    facturaActualizarColumnasGrilla();
}

function actualizarVisibilidadConceptoCabecera() {
    var $wrap = $('#concepto-venta-comprobante-wrap');
    if (!$wrap.length) {
        return;
    }
    var mostrar = tipoSeleccionadoTieneConceptoAsignado();
    $wrap.toggleClass('d-none', !mostrar);
    $wrap.find('input, select, button').prop('disabled', !mostrar);
    if (!mostrar) {
        limpiarConceptoVentaEnCampo($wrap.find('.tm-concepto-venta-campo'));
    }
}

function sincronizarConceptoVentaDesdeTipo() {
    actualizarVisibilidadConceptoCabecera();
    var data = dataConceptoDesdeTipoSeleccionado();
    var $cab = $('#concepto-venta-comprobante-wrap .tm-concepto-venta-campo');
    if (data && $cab.length && tipoSeleccionadoTieneConceptoAsignado()) {
        aplicarConceptoVentaEnCampo($cab, data);
    } else if ($cab.length) {
        limpiarConceptoVentaEnCampo($cab);
        if (!tipoSeleccionadoEsNcNd()) {
            limpiarConceptoVentaEnLineasSinArticulo();
        }
    }
    actualizarAvisoConceptoVentaFactura();
}

function actualizarAvisoConceptoVentaFactura() {
    actualizarVisibilidadConceptoCabecera();
    var $aviso = $('#aviso-concepto-venta-tipo');
    var $label = $('#concepto-venta-comprobante-wrap label').first();
    if (!$aviso.length || $('#concepto-venta-comprobante-wrap').hasClass('d-none')) {
        return;
    }
    $aviso.text('Default del comprobante. En el renglón se puede cambiar y completar el detalle.');
    $label.removeClass('requerido');
}

window.facturaConceptoObligatorioSinArticulo = facturaConceptoObligatorioSinArticulo;
window.sincronizarConceptoVentaDesdeTipo = sincronizarConceptoVentaDesdeTipo;
window.aplicarConceptoVentaCabeceraALineasVacias = aplicarConceptoVentaCabeceraALineasVacias;
window.actualizarAvisoConceptoVentaFactura = actualizarAvisoConceptoVentaFactura;

function limpiarConceptoVentaEnCampo($ctx) {
    if (!$ctx || !$ctx.length) {
        return;
    }
    $ctx.find('.concepto_venta_id').val('');
    $ctx.find('.codigoconceptoventa').val('');
    $ctx.find('.nombreconceptoventa').val('');
    actualizarLinkEditarConceptoVenta($ctx, 0);
}

function filaEsConceptoVenta($tr) {
    return $tr && $tr.length && $.trim($tr.find('.concepto_venta_id').val() || '') !== ''
        && $.trim($tr.find('.articulo_id').val() || '') === '';
}

function facturaHayLineasArticulo($excepto) {
    var hay = false;
    $('#tbody-tabla tr.item-factura, #tbody-tabla tr.item-pedido').each(function () {
        if ($excepto && $excepto.length && this === $excepto[0]) {
            return;
        }
        if ($.trim($(this).find('.articulo_id').val() || '') !== '') {
            hay = true;
            return false;
        }
    });
    return hay;
}

function facturaGrillaSoloConceptos() {
    var hayArticulo = facturaHayLineasArticulo();
    var hayConcepto = false;
    $('#tbody-tabla tr.item-factura, #tbody-tabla tr.item-pedido').each(function () {
        if ($.trim($(this).find('.concepto_venta_id').val() || '') !== '') {
            hayConcepto = true;
            return false;
        }
    });
    return hayConcepto && !hayArticulo;
}

function facturaHayLineasConcepto() {
    var hay = false;
    $('#tbody-tabla tr.item-factura, #tbody-tabla tr.item-pedido').each(function () {
        if ($.trim($(this).find('.concepto_venta_id').val() || '') !== '') {
            hay = true;
            return false;
        }
    });
    return hay;
}

function facturaActualizarColumnasGrilla() {
    var soloConcepto = facturaGrillaSoloConceptos();
    var $table = $('#itemspedido-table');
    if (!$table.length) {
        return;
    }
    $table.toggleClass('factura-grilla-concepto', soloConcepto);
    $table.toggleClass('factura-grilla-con-iva', facturaHayLineasConcepto());
    var $thKilo = $table.find('thead th.factura-col-kilo');
    if ($thKilo.length) {
        $thKilo.text(soloConcepto ? 'Cantidad' : 'Kilos');
    }
}

function facturaLineaModoConcepto($tr, esConcepto, opciones) {
    if (!$tr || !$tr.length) {
        return;
    }
    var $actual = $tr.find('.descripcionarticulo').first();
    if (!$actual.length) {
        return;
    }
    var valor = $actual.val() || '';
    var name = $actual.attr('name') || 'descripcionarticulos[]';

    if (esConcepto) {
        $tr.addClass('item-concepto-venta item-concepto-completo').removeClass('item-concepto-comentario');
        $tr.find('.factura-ta-leyenda-linea').val('');
        facturaRefreshLeyendaBadge($tr);
        $tr.find('.caja, .pieza').val('0').prop('readonly', true);
        $tr.find('.kilo').attr('title', 'Cantidad').attr('placeholder', 'Cant.');
        if (!$actual.is('textarea')) {
            var $ta = $('<textarea></textarea>');
            $ta.addClass('descripcionarticulo form-control factura-detalle-concepto');
            $ta.attr({
                name: name,
                rows: 3,
                placeholder: 'Detalle (ej. AUTO FIAT UNO dominio XXX)'
            });
            $ta.val(valor);
            $actual.replaceWith($ta);
        } else {
            $actual.addClass('factura-detalle-concepto').prop('readonly', false)
                .attr('placeholder', 'Detalle (ej. AUTO FIAT UNO dominio XXX)');
        }
        return;
    }

    $tr.removeClass('item-concepto-venta item-concepto-comentario item-concepto-completo');
    $tr.find('.caja, .pieza').prop('readonly', false);
    $tr.find('.kilo').removeAttr('title').attr('placeholder', '');
    if ($actual.is('textarea')) {
        var $inp = $('<input type="text">');
        $inp.addClass('descripcionarticulo form-control');
        $inp.attr('name', name);
        if (window.FL_FACTURA_LAYOUT_PEDIDO) {
            $inp.css({ width: '220px', height: '38px' }).prop('readonly', true);
        } else {
            $inp.css({ width: '700px', height: '38px' });
        }
        $inp.val(valor);
        $actual.replaceWith($inp);
        return;
    }
    $actual.removeClass('factura-detalle-concepto')
        .prop('readonly', !!window.FL_FACTURA_LAYOUT_PEDIDO)
        .attr('placeholder', '');
    if (window.FL_FACTURA_LAYOUT_PEDIDO) {
        $actual.css({ width: '220px', height: '38px' });
    }
}

function facturaRefreshLeyendaBadge($tr) {
    if (!$tr || !$tr.length) {
        return;
    }
    var texto = $.trim($tr.find('.factura-ta-leyenda-linea').val() || '');
    var $badge = $tr.find('.factura-leyenda-badge');
    var $btn = $tr.find('.factura-abrir-leyenda-linea');
    $badge.text(texto).attr('title', texto);
    $btn.toggleClass('tiene-leyenda', texto !== '');
}

window.filaEsConceptoVenta = filaEsConceptoVenta;
window.facturaRefreshLeyendaBadge = facturaRefreshLeyendaBadge;
window.facturaHayLineasArticulo = facturaHayLineasArticulo;
window.facturaActualizarColumnasGrilla = facturaActualizarColumnasGrilla;
window.facturaLineaModoConcepto = facturaLineaModoConcepto;

function aplicarConceptoVentaEnFilaFactura($tr, data) {
    if (!$tr || !$tr.length) {
        return;
    }
    $tr.find('.concepto_venta_id').val(data.id);
    $tr.find('.articulo_id').val('');
    $tr.find('.articulo_id_previa').val('');
    $tr.find('.articulo_id_previo').val('');
    $tr.find('.categoria_id').val('');
    $tr.find('.subcategoria_id').val('');
    $tr.find('.listaprecio_id').val('');
    $tr.find('.codigoarticulo').val(data.codigo);
    $tr.find('.codigo_previo_articulo').val(data.codigo);
    facturaLineaModoConcepto($tr, true);
    facturaActualizarColumnasGrilla();
    var texto = data.descripcion || data.nombre || '';
    $tr.find('.descripcionarticulo').val(texto).prop('readonly', false);
    if (data.impuesto_id) {
        $tr.find('.impuesto_id, .factura-iva-linea').val(data.impuesto_id);
    }
    $tr.find('.caja, .pieza').val('0').prop('readonly', true);
    var $kilo = $tr.find('.kilo');
    if ($kilo.length) {
        var kiloVal = parseFloat(String($kilo.val() || '').replace(',', '.'));
        if (!kiloVal) {
            $kilo.val('1');
        }
        $kilo.prop('readonly', false);
        $tr.find('.cantidad').val($kilo.val());
    } else {
        var $cant = $tr.find('.cantidad');
        if ($cant.length && (!parseFloat(String($cant.val() || '').replace(',', '.')))) {
            $cant.val('1');
        }
    }
    $tr.find('.precio').prop('readonly', false);
    aplicarPrecioConceptoSiVacio($tr, data.precio);
    if ((!data.precio || parseFloat(data.precio) <= 0) && data.codigo) {
        completarPrecioConceptoDesdeServidor($tr, data.codigo);
    }
    if (typeof calculaFactura === 'function') {
        calculaFactura();
    }
    setTimeout(function () {
        var $desc = $tr.find('.descripcionarticulo');
        var $iva = $tr.find('.factura-iva-linea');
        $desc.attr('placeholder', 'Detalle (ej. AUTO FIAT UNO dominio XXX)');
        if ($iva.length && !$.trim($iva.val() || '')) {
            $iva.trigger('focus');
            return;
        }
        $desc.trigger('focus').trigger('select');
    }, 0);
}

function fechaFacturaParaConceptoVenta() {
    return $.trim($('#fechafactura').val() || '') || '';
}

function tipotransaccionIdParaConceptoVenta() {
    return parseInt($('#tipotransaccion_id').val() || '0', 10) || 0;
}

function aplicarPrecioConceptoSiVacio($tr, precio) {
    var valor = parseFloat(String(precio || '0').replace(',', '.'));
    if (!(valor > 0)) {
        return;
    }
    var $p = $tr.find('.precio');
    var actual = parseFloat(String($p.val() || '0').replace(',', '.'));
    if (!actual) {
        $p.val(valor);
    }
}

function completarPrecioConceptoDesdeServidor($tr, codigo) {
    var qs = [];
    var fecha = fechaFacturaParaConceptoVenta();
    var tipo = tipotransaccionIdParaConceptoVenta();
    if (fecha) {
        qs.push('fecha=' + encodeURIComponent(fecha));
    }
    if (tipo > 0) {
        qs.push('tipotransaccion_id=' + tipo);
    }
    $.get(carpetaBase + '/ventas/concepto-venta/por-codigo/' + encodeURIComponent(codigo) + (qs.length ? '?' + qs.join('&') : ''), function (resp) {
        if (resp && resp.ok) {
            aplicarPrecioConceptoSiVacio($tr, resp.precio);
            if (typeof calculaFactura === 'function') {
                calculaFactura();
            }
        }
    });
}

function abrirModalConceptoVenta($origen) {
    var $tr = $origen.closest('tr.item-factura, tr.item-pedido');
    var $ctx = $origen.closest('.tm-concepto-venta-campo');
    ptrFilaConceptoVenta = $tr.length ? $tr : $();
    ptrConceptoVentaId = $ctx.length ? $ctx.find('.concepto_venta_id') : $();
    ptrCodigoConceptoVenta = $ctx.length ? $ctx.find('.codigoconceptoventa') : $();
    ptrNombreConceptoVenta = $ctx.length ? $ctx.find('.nombreconceptoventa') : $();
    abriendoModalConceptoVenta = true;
    $('#consultaconceptoventaModal').modal('show');
    buscar_datos_concepto_venta('');
}

function avisarConceptoVentaInvalido() {
    if (typeof window.liberarPantallaModalesBloqueados === 'function') {
        window.liberarPantallaModalesBloqueados();
    }
    setTimeout(function () {
        alert('Concepto de venta inexistente o inactivo.');
    }, 0);
}

function resolverPorCodigoConceptoVenta(codigo, $ctx, $tr, avisar) {
    if (!codigo) {
        if ($ctx && $ctx.length) {
            limpiarConceptoVentaEnCampo($ctx);
        }
        return;
    }
    var qs = [];
    var fecha = fechaFacturaParaConceptoVenta();
    var tipo = tipotransaccionIdParaConceptoVenta();
    if (fecha) {
        qs.push('fecha=' + encodeURIComponent(fecha));
    }
    if (tipo > 0) {
        qs.push('tipotransaccion_id=' + tipo);
    }
    $.get(carpetaBase + '/ventas/concepto-venta/por-codigo/' + encodeURIComponent(codigo) + (qs.length ? '?' + qs.join('&') : ''), function (data) {
        if (data && data.ok) {
            if ($tr && $tr.length) {
                aplicarConceptoVentaEnFilaFactura($tr, data);
            } else {
                aplicarConceptoVentaEnCampo($ctx, data);
            }
            conceptoVentaInvalidoMarcado = false;
        } else {
            if ($ctx && $ctx.length) {
                $ctx.find('.concepto_venta_id').val('');
                $ctx.find('.nombreconceptoventa').val('');
                actualizarLinkEditarConceptoVenta($ctx, 0);
            }
            if ($tr && $tr.length) {
                $tr.find('.concepto_venta_id').val('');
            }
            if (avisar && !conceptoVentaInvalidoMarcado) {
                conceptoVentaInvalidoMarcado = true;
                avisarConceptoVentaInvalido();
            }
        }
    }).fail(function () {
        if (avisar && !conceptoVentaInvalidoMarcado) {
            conceptoVentaInvalidoMarcado = true;
            avisarConceptoVentaInvalido();
        }
    });
}

function activa_eventos_consultaconceptoventa() {
    $(document).off('click.consultaconceptoventa').on('click.consultaconceptoventa', '.consultaconceptoventa', function () {
        abrirModalConceptoVenta($(this));
    });

    $('#consultaconceptoventaModal').off('shown.bs.modal').on('shown.bs.modal', function () {
        abriendoModalConceptoVenta = false;
        $(this).find('[autofocus]').trigger('focus');
    });

    $('#consultaconceptoventaModal').off('hidden.bs.modal').on('hidden.bs.modal', function () {
        abriendoModalConceptoVenta = false;
    });

    $('#aceptaconsultaconceptoventaModal').off('click').on('click', function () {
        $('#consultaconceptoventaModal').modal('hide');
    });

    $(document).off('keyup.consultaconceptoventa').on('keyup.consultaconceptoventa', '#consultaconceptoventa', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            var $primera = $('#datosconceptoventa tr').first();
            if ($primera.length && $primera.find('.eligeconsultaconceptoventa').length) {
                $primera.find('.eligeconsultaconceptoventa').trigger('click');
            }
            return;
        }
        buscar_datos_concepto_venta($(this).val());
    });

    $(document).off('click.eligeconsultaconceptoventa').on('click.eligeconsultaconceptoventa', '.eligeconsultaconceptoventa', function () {
        var data = dataConceptoDesdeFila($(this).closest('tr'));
        if (ptrFilaConceptoVenta.length) {
            aplicarConceptoVentaEnFilaFactura(ptrFilaConceptoVenta, data);
        } else if (ptrConceptoVentaId.length) {
            aplicarConceptoVentaEnCampo(ptrConceptoVentaId.closest('.tm-concepto-venta-campo'), data);
        }
        $('#consultaconceptoventaModal').modal('hide');
    });

    $(document).off('keydown.f1conceptoventa').on('keydown.f1conceptoventa', '.codigoconceptoventa, .descripcionarticulo', function (e) {
        if (!esTeclaF1ConceptoVenta(e)) {
            return;
        }
        e.preventDefault();
        abrirModalConceptoVenta($(this));
    });

    $(document).off('input.codigoconceptoventa').on('input.codigoconceptoventa', '.codigoconceptoventa', function () {
        conceptoVentaInvalidoMarcado = false;
    });

    $(document).off('blur.codigoconceptoventa').on('blur.codigoconceptoventa', '.codigoconceptoventa', function () {
        if (modalConsultaConceptoVentaAbierto()) {
            return;
        }
        var codigo = $.trim($(this).val());
        var $ctx = $(this).closest('.tm-concepto-venta-campo');
        resolverPorCodigoConceptoVenta(codigo, $ctx.length ? $ctx : null, null, false);
    });

    $(document).off('keydown.enterconceptoventa').on('keydown.enterconceptoventa', '.codigoconceptoventa', function (e) {
        if (e.key !== 'Enter') {
            return;
        }
        e.preventDefault();
        if (modalConsultaConceptoVentaAbierto()) {
            return;
        }
        var codigo = $.trim($(this).val());
        var $ctx = $(this).closest('.tm-concepto-venta-campo');
        resolverPorCodigoConceptoVenta(codigo, $ctx.length ? $ctx : null, null, true);
    });

    $(document).off('change.facturaPrecioConcepto').on('change.facturaPrecioConcepto', '#itemspedido-table .item-concepto-venta .precio', function () {
        if (typeof calculaFactura === 'function') {
            calculaFactura();
        }
    });

    $(document).off('change.facturaIvaConcepto').on('change.facturaIvaConcepto', '#itemspedido-table .factura-iva-linea', function () {
        if (typeof calculaFactura === 'function') {
            calculaFactura();
        }
    });

    $(document).off('change.articuloconceptoventa').on('change.articuloconceptoventa', '.item-factura .articulo_id, .item-pedido .articulo_id', function () {
        var $tr = $(this).closest('tr');
        if ($.trim($(this).val()) !== '') {
            $tr.find('.concepto_venta_id').val('');
            facturaLineaModoConcepto($tr, false);
            $tr.find('.factura-iva-linea').val('');
            facturaActualizarColumnasGrilla();
            $tr.find('.caja, .pieza').prop('readonly', false);
            $tr.find('.descripcionarticulo').prop('readonly', !!window.FL_FACTURA_LAYOUT_PEDIDO);
            $tr.find('.precio').prop('readonly', true);
        }
    });

    $(document).off('click.facturaLeyendaLinea').on('click.facturaLeyendaLinea', '.factura-abrir-leyenda-linea', function () {
        var $tr = $(this).closest('tr');
        if ($tr.hasClass('item-concepto-venta')) {
            return;
        }
        window.ptrFacturaLeyendaLinea = $tr;
        $('#factura_leyenda_linea_editor').val($tr.find('.factura-ta-leyenda-linea').val() || '');
        $('#modalFacturaLeyendaLinea').modal('show');
    });

    $('#modalFacturaLeyendaLinea').off('shown.bs.modal').on('shown.bs.modal', function () {
        $('#factura_leyenda_linea_editor').trigger('focus');
    });

    $(document).off('click.facturaLeyendaGuardar').on('click.facturaLeyendaGuardar', '#factura_leyenda_linea_guardar', function () {
        var $tr = window.ptrFacturaLeyendaLinea;
        if (!$tr || !$tr.length) {
            return;
        }
        var texto = $('#factura_leyenda_linea_editor').val() || '';
        $tr.find('.factura-ta-leyenda-linea').val(texto);
        facturaRefreshLeyendaBadge($tr);
        $('#modalFacturaLeyendaLinea').modal('hide');
    });

    $(document).off('blur.codigoarticuloconcepto').on('blur.codigoarticuloconcepto', '.item-factura .codigoarticulo, .item-pedido .codigoarticulo', function () {
        var $tr = $(this).closest('tr');
        var codigo = $.trim($(this).val());
        if (!codigo || modalConsultaConceptoVentaAbierto()) {
            return;
        }
        setTimeout(function () {
            if ($.trim($tr.find('.articulo_id').val() || '') !== '') {
                return;
            }
            if ($.trim($tr.find('.concepto_venta_id').val() || '') !== '') {
                return;
            }
            resolverPorCodigoConceptoVenta(codigo, null, $tr, false);
        }, 350);
    });
}

$(function () {
    activa_eventos_consultaconceptoventa();
    if (typeof actualizarAvisoConceptoVentaFactura === 'function') {
        actualizarAvisoConceptoVentaFactura();
    }
    $('#tbody-tabla tr.item-concepto-venta').each(function () {
        var $tr = $(this);
        facturaLineaModoConcepto($tr, true);
    });
    $('#tbody-tabla tr.item-factura, #tbody-tabla tr.item-pedido').each(function () {
        facturaRefreshLeyendaBadge($(this));
    });
    facturaActualizarColumnasGrilla();
});
