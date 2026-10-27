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

function ncDevolucionNumero(valor) {
    var s = String(valor == null ? '' : valor).trim().replace(/\s/g, '');
    var coma = s.lastIndexOf(',');
    var punto = s.lastIndexOf('.');
    if (coma >= 0 && punto >= 0) {
        if (coma > punto) {
            s = s.replace(/\./g, '').replace(',', '.');
        } else {
            s = s.replace(/,/g, '');
        }
    } else if (coma >= 0) {
        s = s.replace(',', '.');
    }
    var n = parseFloat(s);
    return isNaN(n) ? 0 : n;
}

function ncDevolucionRedondear(n) {
    return Math.round((Number(n) + Number.EPSILON) * 100) / 100;
}

function ncDevolucionLineaActiva($tr) {
    var $chk = $tr.find('.nc-devolver');
    return $chk.length > 0 && $chk.is(':checked') && !$chk.prop('disabled');
}

function ncDevolucionPintarConceptos(filas) {
    var $body = $('#tbody-tabla-total-factura');
    var tpl = $('#template-renglon-total-factura').html();
    if (!$body.length || !tpl) {
        return;
    }
    $body.empty();
    filas.forEach(function (fila) {
        if (fila.concepto !== 'Total' && Math.abs(fila.importe) < 0.005) {
            return;
        }
        $body.append(tpl);
        var $tr = $body.find('tr').last();
        $tr.find('.conceptototal').val(fila.concepto);
        $tr.find('.tasatotal').val(fila.tasa > 0 ? Number(fila.tasa).toFixed(2) : '').css('text-align', 'right');
        $tr.find('.importetotal').val(Number(fila.importe).toFixed(2)).css('text-align', 'right');
        if (fila.concepto === 'Total') {
            $tr.find('.conceptototal, .importetotal').css('fontWeight', 'bold');
            $('#montototalfactura').val(Number(fila.importe).toFixed(2));
        }
    });
}

function ncDevolucionFactorPercepcion(netoNc, netoOrigen) {
    if (netoOrigen <= 0.00001) {
        return netoNc > 0.00001 ? 1 : 0;
    }
    var factor = netoNc / netoOrigen;
    if (factor < 0) {
        return 0;
    }
    if (factor > 1) {
        return 1;
    }
    return factor;
}

function ncDevolucionPeriodo(fecha) {
    if (!fecha) {
        return null;
    }
    var s = String(fecha).trim();
    var iso = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (iso) {
        return Number(iso[1]) * 100 + Number(iso[2]);
    }
    var dmy = s.match(/^(\d{2})\/(\d{2})\/(\d{4})/);
    if (dmy) {
        return Number(dmy[3]) * 100 + Number(dmy[2]);
    }
    return null;
}

function ncDevolucionCabaDentroDePlazo(fechaOrigen, fechaNota) {
    var origen = ncDevolucionPeriodo(fechaOrigen);
    var nota = ncDevolucionPeriodo(fechaNota);
    if (origen === null || nota === null) {
        return true;
    }
    var anio = Math.floor(origen / 100);
    var mes = (origen % 100) + 2;
    while (mes > 12) {
        mes -= 12;
        anio += 1;
    }
    return nota <= (anio * 100 + mes);
}

function ncDevolucionPercepcionesProrrateadas(netoNc) {
    var origen = window.ncPercepcionesOrigen || {};
    var filasOrigen = origen.filas || [];
    if (!filasOrigen.length || netoNc < 0.00001) {
        return [];
    }
    var factor = ncDevolucionFactorPercepcion(netoNc, Number(origen.neto) || 0);
    if (factor < 0.00001) {
        return [];
    }
    var anul = $.trim($('#fce_anulacion').val() || '').toUpperCase();
    var completa = anul === 'S' || factor >= 0.999;
    var jurBa = Number(origen.jurisdiccion_buenos_aires) || 902;
    var jurCaba = Number(origen.jurisdiccion_caba) || 901;
    var fechaNota = $.trim($('#fechafactura').val() || '');
    var cabaOk = completa && ncDevolucionCabaDentroDePlazo(origen.fecha_origen, fechaNota);
    var esTotal = factor >= 0.999;
    var out = [];
    filasOrigen.forEach(function (fila) {
        var jur = Number(fila.jurisdiccion) || 0;
        if (!completa && jur === jurBa) {
            return;
        }
        if (!cabaOk && jur === jurCaba) {
            return;
        }
        var importeOrigen = ncDevolucionRedondear(fila.importe);
        var importe = esTotal ? importeOrigen : ncDevolucionRedondear(importeOrigen * factor);
        if (Math.abs(importe) < 0.01) {
            return;
        }
        out.push({
            concepto: fila.concepto || 'Perc. IIBB',
            tasa: Number(fila.tasa) || 0,
            importe: importe
        });
    });
    return out;
}

function ncDevolucionCantidadLinea($tr) {
    var $campo = $tr.find('.kilo').length ? $tr.find('.kilo') : $tr.find('.cantidad');
    return ncDevolucionNumero($campo.val());
}

function ncDevolucionEsDevolucionTotal() {
    var filas = ncDevolucionFilasOrigen();
    if (!filas.length) {
        return false;
    }
    var alguna = false;
    var total = true;
    filas.each(function () {
        var $tr = $(this);
        var original = ncDevolucionNumero($tr.attr('data-cantidad-original'));
        if (!(original > 0.0001)) {
            original = ncDevolucionNumero($tr.attr('data-cantidad-pendiente'));
        }
        if (!(original > 0.0001)) {
            return;
        }
        alguna = true;
        var pendiente = ncDevolucionNumero($tr.attr('data-cantidad-pendiente'));
        if (Math.abs(pendiente - original) > 0.009) {
            total = false;
            return false;
        }
        if (!ncDevolucionLineaActiva($tr)) {
            total = false;
            return false;
        }
        if (Math.abs(ncDevolucionCantidadLinea($tr) - original) > 0.009) {
            total = false;
            return false;
        }
    });
    return alguna && total;
}

function ncDevolucionPieAnulacionTotal() {
    var origen = window.ncPercepcionesOrigen || {};
    var pie = origen.pie || [];
    if (!pie.length) {
        return null;
    }
    var jurCaba = Number(origen.jurisdiccion_caba) || 901;
    var fechaNota = $.trim($('#fechafactura').val() || '');
    var cabaOk = ncDevolucionCabaDentroDePlazo(origen.fecha_origen, fechaNota);
    var filas = [];
    var omitido = 0;
    var totalOrigen = null;
    pie.forEach(function (fila) {
        var concepto = String(fila.concepto || '');
        var importe = ncDevolucionRedondear(fila.importe);
        if (concepto === 'Total') {
            totalOrigen = importe;
            return;
        }
        var jur = Number(fila.jurisdiccion) || 0;
        if (fila.es_iibb && !cabaOk && jur === jurCaba) {
            omitido += importe;
            return;
        }
        filas.push({
            concepto: concepto,
            tasa: Number(fila.tasa) || 0,
            importe: importe
        });
    });
    var total = totalOrigen === null ? 0 : ncDevolucionRedondear(totalOrigen - omitido);
    filas.push({ concepto: 'Total', tasa: 0, importe: total });
    return filas;
}

function ncDevolucionPreviewTotales() {
    if ($('#formgeneral').attr('data-factura-proceso') !== 'nc') {
        return;
    }
    if (ncDevolucionEsDevolucionTotal()) {
        var pieTotal = ncDevolucionPieAnulacionTotal();
        if (pieTotal) {
            ncDevolucionPintarConceptos(pieTotal);
            return;
        }
    }
    var neto = 0;
    var ivas = {};
    var hay = false;
    ncDevolucionFilasOrigen().each(function () {
        var $tr = $(this);
        if (!ncDevolucionLineaActiva($tr)) {
            return;
        }
        hay = true;
        var cant = ncDevolucionNumero($tr.find('.kilo').length ? $tr.find('.kilo').val() : $tr.find('.cantidad').val());
        var precio = ncDevolucionNumero($tr.find('.precio').val());
        var dto = ncDevolucionNumero($tr.find('.descuento').val());
        var bruto = cant * precio;
        if (dto) {
            bruto = bruto * (1 - dto / 100);
        }
        var incluye = String($tr.find('.incluyeimpuesto').val() || '').toUpperCase();
        var conIvaEnPrecio = incluye !== '' && incluye !== 'N' && incluye !== '2';
        var tasa = ncDevolucionNumero($tr.find('.factura-iva-linea option:selected').attr('data-valor'));
        var base = bruto;
        var ivaLinea = 0;
        if (conIvaEnPrecio && tasa > 0) {
            base = bruto / (1 + tasa / 100);
            ivaLinea = bruto - base;
        } else if (tasa > 0) {
            ivaLinea = bruto * tasa / 100;
        }
        neto += ncDevolucionRedondear(base);
        var key = String(tasa);
        ivas[key] = (ivas[key] || 0) + ivaLinea;
    });
    if (!hay) {
        ncDevolucionPintarConceptos([
            { concepto: 'Subtotal', tasa: 0, importe: 0 },
            { concepto: 'Total', tasa: 0, importe: 0 }
        ]);
        return;
    }
    var netoRed = ncDevolucionRedondear(neto);
    var filas = [{ concepto: 'Subtotal', tasa: 0, importe: netoRed }];
    var totalIva = 0;
    Object.keys(ivas).sort(function (a, b) { return Number(a) - Number(b); }).forEach(function (key) {
        var tasa = Number(key);
        var importe = ncDevolucionRedondear(ivas[key]);
        if (Math.abs(importe) < 0.005) {
            return;
        }
        filas.push({ concepto: 'Iva ' + tasa + '%', tasa: tasa, importe: importe });
        totalIva += importe;
    });
    var totalPerc = 0;
    ncDevolucionPercepcionesProrrateadas(netoRed).forEach(function (fila) {
        filas.push(fila);
        totalPerc += fila.importe;
    });
    filas.push({
        concepto: 'Total',
        tasa: 0,
        importe: ncDevolucionRedondear(netoRed + totalIva + totalPerc)
    });
    ncDevolucionPintarConceptos(filas);
}

function ncDevolucionRecalcular() {
    ncDevolucionPreviewTotales();
    if (typeof window.calculaFactura === 'function') {
        window.calculaFactura();
    } else if (typeof calculaFactura === 'function') {
        calculaFactura();
    }
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
    ncDevolucionRecalcular();
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
        ncDevolucionRecalcular();
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
                ncDevolucionRecalcular();
            }
        });

    $(document).off('input.ncDevTot', '#tbody-tabla .nc-linea-origen .cantidad, #tbody-tabla .nc-linea-origen .kilo, #tbody-tabla .nc-linea-origen .precio, #tbody-tabla .nc-linea-origen .descuento')
        .on('input.ncDevTot', '#tbody-tabla .nc-linea-origen .cantidad, #tbody-tabla .nc-linea-origen .kilo, #tbody-tabla .nc-linea-origen .precio, #tbody-tabla .nc-linea-origen .descuento', function () {
            ncDevolucionPreviewTotales();
        });

    $(document).off('change.ncDevTot', '#tbody-tabla .nc-linea-origen .cantidad, #tbody-tabla .nc-linea-origen .kilo, #tbody-tabla .nc-linea-origen .precio, #tbody-tabla .nc-linea-origen .descuento')
        .on('change.ncDevTot', '#tbody-tabla .nc-linea-origen .cantidad, #tbody-tabla .nc-linea-origen .kilo, #tbody-tabla .nc-linea-origen .precio, #tbody-tabla .nc-linea-origen .descuento', function () {
            ncDevolucionRecalcular();
        });

    $(document).off('change.ncDevFecha', '#fechafactura, #fce_anulacion')
        .on('change.ncDevFecha', '#fechafactura, #fce_anulacion', function () {
            if ($('#formgeneral').attr('data-factura-proceso') === 'nc') {
                ncDevolucionRecalcular();
            }
        });
}

$(function () {
    if ($('#tm_articulo_nc_devolucion').length || $('.nc-devolver').length) {
        activa_eventos_nc_devolucion();
        aplicarEstadoTodasLineasNc();
        if ($('#formgeneral').attr('data-factura-proceso') === 'nc') {
            ncDevolucionRecalcular();
        }
    }
});
