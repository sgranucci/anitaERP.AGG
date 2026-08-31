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
    var tags = [];
    try {
        tags = JSON.parse($row.attr('data-concepto-tags') || '[]') || [];
    } catch (e) {
        tags = [];
    }
    if (!Array.isArray(tags)) {
        tags = [];
    }
    return {
        id: $.trim($row.find('.concepto_venta_id').text()),
        codigo: $.trim($row.find('.codigoconceptoventa').text()),
        nombre: $.trim($row.find('.nombreconceptoventa').text()),
        descripcion: $.trim($row.find('.descripcionconceptoventa').text()),
        plantilla: $.trim($row.attr('data-concepto-plantilla') || $row.find('.descripcionconceptoventa').text()),
        codigo_gtin: $.trim($row.find('.gtinconceptoventa').text()),
        impuesto_id: $.trim($row.attr('data-impuesto-id') || ''),
        tags: tags
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
            var $wrapDesc = $('<div class="d-flex align-items-start factura-concepto-detalle-wrap"></div>');
            $wrapDesc.append($ta);
            var $btnTags = $('<button type="button" class="btn btn-outline-secondary btn-sm ml-1 factura-abrir-tags-concepto d-none" title="Completar tags del concepto"><i class="fa fa-tags"></i></button>');
            $wrapDesc.append($btnTags);
            $actual.replaceWith($wrapDesc);
        } else {
            $actual.addClass('factura-detalle-concepto').prop('readonly', false)
                .attr('placeholder', 'Detalle (ej. AUTO FIAT UNO dominio XXX)');
            if (!$tr.find('.factura-abrir-tags-concepto').length) {
                $actual.after('<button type="button" class="btn btn-outline-secondary btn-sm ml-1 factura-abrir-tags-concepto d-none" title="Completar tags del concepto"><i class="fa fa-tags"></i></button>');
            }
        }
        return;
    }

    $tr.removeClass('item-concepto-venta item-concepto-comentario item-concepto-completo');
    $tr.find('.caja, .pieza').prop('readonly', false);
    $tr.find('.kilo').removeAttr('title').attr('placeholder', '');
    $tr.find('.factura-abrir-tags-concepto').remove();
    var $wrap = $tr.find('.factura-concepto-detalle-wrap');
    if ($wrap.length) {
        $actual = $wrap.find('.descripcionarticulo').first();
        valor = $actual.val() || '';
        name = $actual.attr('name') || name;
    }
    if ($actual.is('textarea') || $wrap.length) {
        var $inp = $('<input type="text">');
        $inp.addClass('descripcionarticulo form-control');
        $inp.attr('name', name);
        if (window.FL_FACTURA_LAYOUT_PEDIDO) {
            $inp.css({ width: '220px', height: '38px' }).prop('readonly', true);
        } else {
            $inp.css({ width: '700px', height: '38px' });
        }
        $inp.val(valor);
        if ($wrap.length) {
            $wrap.replaceWith($inp);
        } else {
            $actual.replaceWith($inp);
        }
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
    var plantilla = data.plantilla || data.descripcion || data.nombre || '';
    var tags = Array.isArray(data.tags) ? data.tags : [];
    guardarMetaTagsConceptoEnFila($tr, plantilla, tags);
    if (!data.contrato_venta_id) {
        $tr.find('.contrato_venta_id').val('');
        $tr.find('.concepto_tag_json').val('');
        $tr.find('.concepto_periodo_desde').val('');
        $tr.find('.concepto_periodo_hasta').val('');
    }
    $tr.find('.descripcionarticulo').val(plantilla).prop('readonly', false);
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
    if (tags.length && (!data.precio || parseFloat(data.precio) <= 0) && data.codigo) {
        completarPrecioConceptoDesdeServidor($tr, data.codigo);
    }
    if (typeof calculaFactura === 'function') {
        calculaFactura();
    }
    setTimeout(function () {
        if (tags.length) {
            abrirModalTagsConceptoFactura($tr, plantilla, tags);
            return;
        }
        if (data.codigo && data._skipTagFetch !== true) {
            var qs = [];
            var fecha = fechaFacturaParaConceptoVenta();
            var tipo = tipotransaccionIdParaConceptoVenta();
            if (fecha) {
                qs.push('fecha=' + encodeURIComponent(fecha));
            }
            if (tipo > 0) {
                qs.push('tipotransaccion_id=' + tipo);
            }
            $.get(carpetaBase + '/ventas/concepto-venta/por-codigo/' + encodeURIComponent(data.codigo) + (qs.length ? '?' + qs.join('&') : ''), function (resp) {
                if (resp && resp.ok) {
                    aplicarPrecioConceptoSiVacio($tr, resp.precio);
                    if (resp.tags && resp.tags.length) {
                        var plantillaSrv = resp.plantilla || resp.descripcion || plantilla;
                        guardarMetaTagsConceptoEnFila($tr, plantillaSrv, resp.tags);
                        if (!$.trim($tr.find('.descripcionarticulo').val() || '') || $tr.find('.descripcionarticulo').val() === plantilla) {
                            $tr.find('.descripcionarticulo').val(plantillaSrv);
                        }
                        abrirModalTagsConceptoFactura($tr, plantillaSrv, resp.tags);
                        if (typeof calculaFactura === 'function') {
                            calculaFactura();
                        }
                        return;
                    }
                    if (typeof calculaFactura === 'function') {
                        calculaFactura();
                    }
                }
                enfocarTrasConceptoSinTags($tr);
            }).fail(function () {
                enfocarTrasConceptoSinTags($tr);
            });
            return;
        }
        if ((!data.precio || parseFloat(data.precio) <= 0) && data.codigo) {
            completarPrecioConceptoDesdeServidor($tr, data.codigo);
        }
        enfocarTrasConceptoSinTags($tr);
    }, 0);
}

function enfocarTrasConceptoSinTags($tr) {
    var $desc = $tr.find('.descripcionarticulo');
    var $iva = $tr.find('.factura-iva-linea');
    $desc.attr('placeholder', 'Detalle (ej. AUTO FIAT UNO dominio XXX)');
    if ($iva.length && !$.trim($iva.val() || '')) {
        $iva.trigger('focus');
        return;
    }
    $desc.trigger('focus').trigger('select');
}

function guardarMetaTagsConceptoEnFila($tr, plantilla, tags) {
    if (!$tr || !$tr.length) {
        return;
    }
    $tr.attr('data-concepto-plantilla', plantilla || '');
    try {
        $tr.attr('data-concepto-tags', JSON.stringify(tags || []));
    } catch (e) {
        $tr.attr('data-concepto-tags', '[]');
    }
    var $btn = $tr.find('.factura-abrir-tags-concepto');
    if ($btn.length) {
        $btn.toggleClass('d-none', !(tags && tags.length));
    }
}

function sustituirTagsConceptoPlantilla(plantilla, valores) {
    return String(plantilla || '').replace(/@([a-z][a-z0-9_]{0,39})@/g, function (match, clave) {
        if (Object.prototype.hasOwnProperty.call(valores, clave)) {
            return String(valores[clave] == null ? '' : valores[clave]).trim();
        }
        return match;
    });
}

var ptrFacturaConceptoTagsFila = $();

function abrirModalTagsConceptoFactura($tr, plantilla, tags, valoresPrefill) {
    if (!$tr || !$tr.length || !tags || !tags.length) {
        return;
    }
    valoresPrefill = valoresPrefill || {};
    var $consulta = $('#consultaconceptoventaModal');
    if ($consulta.length && ($consulta.hasClass('show') || abriendoModalConceptoVenta)) {
        $consulta.one('hidden.bs.modal', function () {
            setTimeout(function () {
                abrirModalTagsConceptoFactura($tr, plantilla, tags, valoresPrefill);
            }, 0);
        });
        $consulta.modal('hide');
        return;
    }
    if (typeof window.liberarPantallaModalesBloqueados === 'function') {
        window.liberarPantallaModalesBloqueados();
    }
    ptrFacturaConceptoTagsFila = $tr;
    plantilla = plantilla || $tr.attr('data-concepto-plantilla') || $tr.find('.descripcionarticulo').val() || '';
    var $wrap = $('#factura_concepto_tags_campos');
    $wrap.empty();
    tags.forEach(function (tag, idx) {
        var clave = String(tag.clave || '');
        var etiqueta = String(tag.etiqueta || clave);
        var max = tag.largo_max ? parseInt(tag.largo_max, 10) : 0;
        var req = tag.obligatorio !== false;
        var tipo = String(tag.tipo || 'texto');
        var id = 'factura_concepto_tag_' + clave + '_' + idx;
        var $grp = $('<div class="form-group"></div>');
        var $lbl = $('<label></label>').attr('for', id).addClass(req ? 'requerido' : '');
        $lbl.text(etiqueta + ' (@' + clave + '@)');
        var prefill = (valoresPrefill && valoresPrefill[clave] != null) ? String(valoresPrefill[clave]) : '';
        var $inp;
        if (tipo === 'fecha') {
            $inp = $('<input type="date" class="form-control factura-concepto-tag-input">');
            if (/^\d{4}-\d{2}-\d{2}/.test(prefill)) {
                prefill = prefill.substring(0, 10);
            } else if (/^\d{2}\/\d{2}\/\d{4}$/.test(prefill)) {
                var p = prefill.split('/');
                prefill = p[2] + '-' + p[1] + '-' + p[0];
            }
        } else if (tipo === 'periodo') {
            $inp = $('<input type="text" class="form-control factura-concepto-tag-input">')
                .attr('placeholder', 'AAAA-MM o AAAA-MM-DD|AAAA-MM-DD');
        } else if (tipo === 'lista') {
            $inp = $('<select class="form-control factura-concepto-tag-input"></select>');
            $inp.append($('<option value=""></option>').text('-- Seleccionar --'));
            String(tag.opciones || '').split('|').forEach(function (opt) {
                opt = $.trim(opt);
                if (opt) {
                    $inp.append($('<option></option>').val(opt).text(opt));
                }
            });
        } else {
            $inp = $('<input type="text" class="form-control factura-concepto-tag-input">');
        }
        $inp.attr({
            id: id,
            'data-clave': clave,
            'data-tipo': tipo,
            'data-obligatorio': req ? '1' : '0',
            maxlength: max > 0 ? max : 255
        });
        if (prefill) {
            $inp.val(prefill);
        }
        $grp.append($lbl).append($inp);
        $wrap.append($grp);
    });
    $('#factura_concepto_tags_preview').val(plantilla);
    $('#factura_concepto_tags_aviso_largo').text('');
    $('#modalFacturaConceptoTags').data('plantilla', plantilla);
    $('#modalFacturaConceptoTags').data('metas', tags);
    $('#modalFacturaConceptoTags').modal('show');
}

function valoresModalTagsConceptoFactura() {
    var valores = {};
    $('#factura_concepto_tags_campos .factura-concepto-tag-input').each(function () {
        var clave = $(this).attr('data-clave');
        var tipo = $(this).attr('data-tipo') || 'texto';
        if (!clave) {
            return;
        }
        var val = $.trim($(this).val() || '');
        if (tipo === 'periodo' && val && val.indexOf('|') === -1 && /^\d{4}-\d{2}$/.test(val)) {
            // deja que el backend formatee AAAA-MM
        }
        valores[clave] = val;
    });
    return valores;
}

function actualizarPreviewTagsConceptoFactura() {
    var plantilla = $('#modalFacturaConceptoTags').data('plantilla') || '';
    var texto = sustituirTagsConceptoPlantilla(plantilla, valoresModalTagsConceptoFactura());
    $('#factura_concepto_tags_preview').val(texto);
    var $aviso = $('#factura_concepto_tags_aviso_largo');
    if (texto.length > 250) {
        $aviso.text('Atención: el texto supera 250 caracteres (límite ARCA MTXCA). Acórtelo antes de aplicar.')
            .addClass('text-danger');
    } else {
        $aviso.text(texto.length + ' / 250 caracteres').removeClass('text-danger');
    }
}

function aplicarModalTagsConceptoFactura() {
    var $tr = ptrFacturaConceptoTagsFila;
    if (!$tr || !$tr.length) {
        return;
    }
    var faltan = [];
    $('#factura_concepto_tags_campos .factura-concepto-tag-input').each(function () {
        if ($(this).attr('data-obligatorio') === '1' && !$.trim($(this).val() || '')) {
            faltan.push($(this).closest('.form-group').find('label').text() || $(this).attr('data-clave'));
        }
    });
    if (faltan.length) {
        alert('Complete los campos obligatorios:\n- ' + faltan.join('\n- '));
        return;
    }
    var plantilla = $('#modalFacturaConceptoTags').data('plantilla') || '';
    var texto = sustituirTagsConceptoPlantilla(plantilla, valoresModalTagsConceptoFactura());
    if (/@[a-z][a-z0-9_]{0,39}@/.test(texto)) {
        alert('Quedan tags sin completar en el detalle.');
        return;
    }
    if (texto.length > 250) {
        if (!confirm('El detalle supera 250 caracteres (ARCA). ¿Aplicarlo igual?')) {
            return;
        }
    }
    var valores = valoresModalTagsConceptoFactura();
    $tr.find('.descripcionarticulo').val(texto);
    try {
        $tr.find('.concepto_tag_json').val(JSON.stringify(valores));
    } catch (e) {
        $tr.find('.concepto_tag_json').val('');
    }
    if (valores.periodo && String(valores.periodo).indexOf('|') !== -1) {
        var partes = String(valores.periodo).split('|');
        $tr.find('.concepto_periodo_desde').val($.trim(partes[0] || ''));
        $tr.find('.concepto_periodo_hasta').val($.trim(partes[1] || ''));
    }
    $('#modalFacturaConceptoTags').modal('hide');
    setTimeout(function () {
        var $iva = $tr.find('.factura-iva-linea');
        if ($iva.length && !$.trim($iva.val() || '')) {
            $iva.trigger('focus');
            return;
        }
        $tr.find('.precio').trigger('focus');
    }, 0);
}

function aplicarContratoVentaPrefillEnFila($tr, prefill) {
    if (!$tr || !$tr.length || !prefill) {
        return;
    }
    var data = {
        id: prefill.concepto_venta_id,
        codigo: prefill.codigo,
        descripcion: prefill.texto_preview || prefill.plantilla || prefill.descripcion,
        plantilla: prefill.plantilla || prefill.descripcion,
        tags: prefill.tags || [],
        precio: prefill.precio,
        impuesto_id: prefill.impuesto_id,
        _skipTagFetch: true
    };
    aplicarConceptoVentaEnFilaFactura($tr, data);
    $tr.find('.contrato_venta_id').val(prefill.contrato_venta_id || '');
    $tr.find('.concepto_periodo_desde').val(prefill.periodo_desde || '');
    $tr.find('.concepto_periodo_hasta').val(prefill.periodo_hasta || '');
    try {
        $tr.find('.concepto_tag_json').val(JSON.stringify(prefill.valores || {}));
    } catch (e) {
        $tr.find('.concepto_tag_json').val('');
    }
    if (prefill.texto_preview) {
        $tr.find('.descripcionarticulo').val(prefill.texto_preview);
    }
    var tagsPendientes = (prefill.tags || []).filter(function (t) {
        var v = (prefill.valores || {})[t.clave];
        return t.obligatorio !== false && (!v || String(v).trim() === '');
    });
    if (tagsPendientes.length) {
        setTimeout(function () {
            abrirModalTagsConceptoFactura($tr, prefill.plantilla || '', prefill.tags, prefill.valores || {});
        }, 50);
    }
}

window.aplicarContratoVentaPrefillEnFila = aplicarContratoVentaPrefillEnFila;
window.abrirModalTagsConceptoFactura = abrirModalTagsConceptoFactura;

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

    $(document).off('input.facturaConceptoTags').on('input.facturaConceptoTags', '#factura_concepto_tags_campos .factura-concepto-tag-input', actualizarPreviewTagsConceptoFactura);
    $(document).off('click.facturaConceptoTagsAplicar').on('click.facturaConceptoTagsAplicar', '#factura_concepto_tags_aplicar', aplicarModalTagsConceptoFactura);
    $('#modalFacturaConceptoTags').off('shown.bs.modal').on('shown.bs.modal', function () {
        actualizarPreviewTagsConceptoFactura();
        $(this).find('.factura-concepto-tag-input').first().trigger('focus');
    });
    $(document).off('click.facturaAbrirTagsConcepto').on('click.facturaAbrirTagsConcepto', '.factura-abrir-tags-concepto', function () {
        var $tr = $(this).closest('tr');
        var plantilla = $tr.attr('data-concepto-plantilla') || '';
        var tags = [];
        try {
            tags = JSON.parse($tr.attr('data-concepto-tags') || '[]') || [];
        } catch (e) {
            tags = [];
        }
        if (!tags.length && $tr.find('.codigoarticulo').val()) {
            aplicarConceptoVentaEnFilaFactura($tr, {
                id: $tr.find('.concepto_venta_id').val(),
                codigo: $tr.find('.codigoarticulo').val(),
                descripcion: plantilla || $tr.find('.descripcionarticulo').val(),
                plantilla: plantilla || $tr.find('.descripcionarticulo').val(),
                tags: [],
                precio: $tr.find('.precio').val(),
                impuesto_id: $tr.find('.factura-iva-linea').val()
            });
            return;
        }
        if (!tags.length) {
            alert('Este concepto no tiene tags configurados.');
            return;
        }
        abrirModalTagsConceptoFactura($tr, plantilla || $tr.find('.descripcionarticulo').val() || '', tags);
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

    try {
        var rawBatch = sessionStorage.getItem('anita_contrato_venta_prefill_batch');
        if (rawBatch && $('#tbody-tabla').length) {
            sessionStorage.removeItem('anita_contrato_venta_prefill_batch');
            var batch = JSON.parse(rawBatch);
            var lineas = (batch && batch.lineas) || (batch && batch.prefills) || [];
            if (!Array.isArray(lineas) && batch && batch.linea) {
                lineas = [batch.linea];
            }
            lineas.forEach(function (prefill, idx) {
                var $tr = $('#tbody-tabla tr.item-factura, #tbody-tabla tr.item-pedido').eq(idx);
                if (!$tr.length && typeof window.agrega_renglon === 'function') {
                    window.agrega_renglon();
                    $tr = $('#tbody-tabla tr.item-factura, #tbody-tabla tr.item-pedido').last();
                }
                if ($tr.length && typeof window.aplicarContratoVentaPrefillEnFila === 'function') {
                    window.aplicarContratoVentaPrefillEnFila($tr, prefill);
                }
            });
        }
    } catch (e) {}
});
