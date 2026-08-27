var ptrarticulo_id;
var ptrcodigoarticulo;
var ptrnombrearticulo;
var ptrunidadmedida;
var ptrcategoria_id;
var ptrsubcategoria_id;

/** Evita un POST por tecla y cancela respuestas obsoletas al seguir escribiendo */
var consultaArticuloAjax = null;
var consultaArticuloTimer = null;
var CONSULTA_ARTICULO_DEBOUNCE_MS = 280;
var CONSULTA_ARTICULO_MIN_LEN = 2;
var CONSULTA_ARTICULO_URL_EDITAR_QUERY = '?origen=modal_consulta&vista=consulta';

function empresaIdConsultaArticulo() {
    var $emp = $('#empresa_id');
    if (!$emp.length) {
        return '';
    }
    var v = parseInt(String($emp.val() || '0'), 10);
    return v > 0 ? String(v) : '';
}

function urlLeerArticuloPorSku(sku, queryExtra) {
    var url = carpetaBase + '/stock/leerunarticuloporsku/' + encodeURIComponent(sku || '');
    var parts = [];
    if (queryExtra) {
        parts.push(String(queryExtra).replace(/^\?/, ''));
    }
    var empresaId = empresaIdConsultaArticulo();
    if (empresaId !== '') {
        parts.push('empresa_id=' + encodeURIComponent(empresaId));
    }
    if (parts.length) {
        url += '?' + parts.join('&');
    }
    return url;
}

window.listaprecioIdEsValidoLineaVentas = function (listaprecioId) {
    if (listaprecioId == null || listaprecioId === '') {
        return false;
    }

    return parseInt(listaprecioId, 10) > 0;
};

window.mensajeErrorListaprecioArticuloVentas = function (sku, numeroItem) {
    var etiqueta = (sku || '').trim() || 'seleccionado';
    var sufijo = numeroItem ? ' (ítem ' + numeroItem + ')' : '';

    return 'El artículo ' + etiqueta + sufijo + ' no tiene lista de precios asignada.';
};

window.limpiarLineaArticuloSinListaprecio = function ($tr, sku, numeroItem) {
    alert(window.mensajeErrorListaprecioArticuloVentas(sku, numeroItem));
    $tr.find('.articulo_id').val('');
    $tr.find('.codigoarticulo, .codigoarticulolocal').val('');
    $tr.find('.codigo_previo_articulo').val('');
    $tr.find('.descripcionarticulo').val('');
    $tr.find('.listaprecio_id').val('');
    $tr.find('.precio').val('');
    $tr.find('.incluyeimpuesto').val('');
    $tr.find('.moneda_id').val('');
    actualizarLinkEditarArticulo($tr, '');
};

window.validarListaprecioLineasFormularioVentas = function (selectorRenglones) {
    var flError = false;

    $(selectorRenglones || '#tbody-tabla tr').each(function (index) {
        var $tr = $(this);
        var articuloId = ($tr.find('.articulo_id').val() || '').trim();
        if (!articuloId) {
            return;
        }

        var listaprecioId = $tr.find('.listaprecio_id').val();
        if (!window.listaprecioIdEsValidoLineaVentas(listaprecioId)) {
            var sku = ($tr.find('.codigoarticulo, .codigoarticulolocal').first().val() || '').trim();
            alert(window.mensajeErrorListaprecioArticuloVentas(sku, index + 1));
            flError = true;

            return false;
        }
    });

    return !flError;
};

function urlEditarArticuloConsulta(articuloId) {
    var id = parseInt(articuloId, 10) || 0;
    if (id <= 0) {
        return '#';
    }
    return carpetaBase + '/stock/articulo/' + id + '/editar' + CONSULTA_ARTICULO_URL_EDITAR_QUERY;
}

/**
 * Contenedor de la línea de artículo (no el <tr> de semana/tabla ancha).
 * En menús de vianda hay un solo <tr> con todos los días: closest('tr') pisaría todos los SKU.
 */
function consultaArticuloContextoLinea($el) {
    var $ctx = $($el).closest('.tm-articulo-campo, .item-vianda-articulo-dia, .cm-campo-articulo-carga, #cm-campo-articulo-carga');
    if (!$ctx.length) {
        $ctx = $($el).closest('tr');
    }
    return $ctx;
}

function actualizarLinkEditarArticulo($ctx, articuloId) {
    if (!$ctx || !$ctx.length) {
        return;
    }
    var $link = $ctx.find('.btn-link-articulo');
    if (!$link.length) {
        $link = $ctx.find('a[href*="editar_articulo"], a[href*="/stock/articulo/"][href*="/editar"]')
            .not('.btn-link-articulo-destino');
    }
    if (!$link.length) {
        return;
    }
    var id = parseInt(articuloId, 10) || 0;
    if (id > 0) {
        $link.attr('href', urlEditarArticuloConsulta(id)).removeClass('d-none');
    } else {
        $link.attr('href', '#').addClass('d-none');
    }
}

function unidadMedidaEsKilos(unidadmedida) {
    var um = (unidadmedida || '').toString().trim().toUpperCase();
    return um === 'KG' || um === 'KIL' || um === 'KILO' || um === 'KILOS' || um === 'KGS';
}

function resolverInputCantidadLineaArticulo($tr, unidadmedida) {
    if (!$tr || !$tr.length) {
        return $();
    }
    var um = (unidadmedida || '').toString().trim().toUpperCase();
    var $target = $();
    if (um === 'CAJ') {
        $target = $tr.find('.caja').filter(':visible:not([readonly])');
    } else if (unidadMedidaEsKilos(um)) {
        $target = $tr.find('.kilo').filter(':visible:not([readonly])');
    } else if (um === 'UN' || um === 'UND' || um === 'UNID') {
        $target = $tr.find('.pieza').filter(':visible:not([readonly])');
    }
    if (!$target.length) {
        $target = $tr.find('.cantidad-stock, .input-cantidad-contada, .cantidad-linea, input.cantidad')
            .filter(':visible:not([readonly])')
            .first();
    }
    return $target.first();
}

function enfocarCantidadLineaArticulo($tr, unidadmedida) {
    var $target = resolverInputCantidadLineaArticulo($tr, unidadmedida);
    if (!$target.length) {
        return false;
    }
    setTimeout(function () {
        $target.trigger('focus');
        if ($target[0] && typeof $target[0].select === 'function') {
            $target[0].select();
        }
    }, 0);
    return true;
}

function abreviaturaUnidadMedidaArticulo(data) {
    if (!data) {
        return '';
    }
    if (data.unidadesdemedidas && data.unidadesdemedidas.abreviatura) {
        return String(data.unidadesdemedidas.abreviatura);
    }
    if (data.unidadmedida && typeof data.unidadmedida === 'object' && data.unidadmedida.abreviatura) {
        return String(data.unidadmedida.abreviatura);
    }
    if (typeof data.unidadmedida === 'string') {
        return data.unidadmedida;
    }
    return '';
}

/**
 * Aplica la UM del artículo al renglón. Si el maestro no tiene UM (casi todo El Bierzo),
 * no deja el select vacío: usa la abreviatura o KG / primera opción.
 */
function aplicarUnidadMedidaLineaArticulo($tr, unidadmedidaId, unidadmedidaAbrev) {
    if (!$tr || !$tr.length) {
        return '';
    }
    var $sel = $tr.find('.unidadmedida_id').first();
    var id = parseInt(unidadmedidaId, 10) || 0;
    var abr = (unidadmedidaAbrev || '').toString().trim();

    function seleccionarPorValor(valor) {
        if (!$sel.length || valor === '' || valor == null) {
            return false;
        }
        var $opt = $sel.find('option').filter(function () {
            return String($(this).val()) === String(valor);
        });
        if (!$opt.length) {
            return false;
        }
        $sel.val(String($opt.first().val()));
        return true;
    }

    function seleccionarPorAbreviatura(texto) {
        var t = (texto || '').toString().trim().toUpperCase();
        if (!$sel.length || !t) {
            return false;
        }
        var $opt = $sel.find('option').filter(function () {
            return $(this).text().trim().toUpperCase() === t;
        }).first();
        if (!$opt.length) {
            return false;
        }
        $sel.val(String($opt.val()));
        return true;
    }

    var ok = false;
    if (id > 0) {
        ok = seleccionarPorValor(id);
    }
    if (!ok) {
        ok = seleccionarPorAbreviatura(abr);
    }
    if (!ok) {
        ok = seleccionarPorAbreviatura('KG');
    }
    if (!ok && $sel.length && !$sel.val()) {
        var $first = $sel.find('option').filter(function () {
            return String($(this).val() || '') !== '';
        }).first();
        if ($first.length) {
            $sel.val(String($first.val()));
        }
    }

    var texto = '';
    if ($sel.length) {
        texto = ($sel.find('option:selected').text() || '').trim();
    }
    if (!texto) {
        texto = abr;
    }
    $tr.find('.unidadmedida').val(texto);
    return texto;
}

function consultaArticuloResolverListaprecio() {
    var modal = $('#consultaarticuloModal');
    var idModal = parseInt(modal.data('articuloListaprecioId'), 10);
    if (idModal > 0) {
        return {
            id: idModal,
            nombre: (modal.data('articuloListaprecioNombre') || '').toString(),
        };
    }
    var def = window.consultaArticuloListaprecioDefault || {};
    return {
        id: parseInt(def.id, 10) || 1,
        nombre: (def.nombre || '').toString(),
    };
}

function consultaArticuloMostrarPrecioLista() {
    return consultaArticuloResolverListaprecio().id > 0;
}

function consultaArticuloColspanTabla() {
    return consultaArticuloMostrarPrecioLista() ? 7 : 6;
}

function actualizarEncabezadoPrecioListaConsulta(meta) {
    var $th = $('#consultaarticulo-th-precio');
    if (!$th.length) {
        return;
    }
    var mostrar = meta && meta.mostrar_precio_lista !== false && consultaArticuloMostrarPrecioLista();
    if (meta && meta.mostrar_precio_lista === false) {
        mostrar = false;
    }
    if (!mostrar) {
        $th.addClass('d-none');
        return;
    }
    $th.removeClass('d-none');
    var nombre = (meta && meta.listaprecio_nombre) ? String(meta.listaprecio_nombre).trim() : '';
    var id = meta && meta.listaprecio_id ? parseInt(meta.listaprecio_id, 10) : consultaArticuloResolverListaprecio().id;
    if (!nombre) {
        nombre = consultaArticuloResolverListaprecio().nombre || '';
    }
    if (nombre) {
        $th.text('Precio ' + nombre);
    } else if (id > 0) {
        $th.text('Precio lista ' + id);
    } else {
        $th.text('Precio');
    }
}

function htmlTablaConsultaArticuloMensaje(texto) {
    return '<tr><td colspan="' + consultaArticuloColspanTabla() + '" class="text-muted">' + texto + '</td></tr>';
}

function consultaArticuloMinLen(valor) {
    var digitos = $('#consultaarticuloModal').data('articuloSkuDigitosFiltro');
    if (digitos > 0 && /^\d+$/.test(String(valor || '').trim())) {
        return 1;
    }
    return CONSULTA_ARTICULO_MIN_LEN;
}

function consultaArticuloMensajeMinLen(minLen) {
    if (minLen === 1) {
        return 'Escriba al menos 1 dígito para buscar.';
    }
    return 'Escriba al menos ' + minLen + ' caracteres para buscar.';
}

function aplicarRespuestaConsultaArticulo(respuesta) {
    var html = '';
    var meta = respuesta;
    if (respuesta && typeof respuesta.data === 'string') {
        html = respuesta.data;
    } else if (typeof respuesta === 'string') {
        try {
            meta = JSON.parse(respuesta);
            html = meta.data || '';
        } catch (e) {
            html = respuesta.replace(/\\/g, '');
            meta = null;
        }
    }
    if (meta) {
        actualizarEncabezadoPrecioListaConsulta(meta);
    }
    $("#datos").html(html);
}

function consultaArticuloRequiereSoloFacturable($ctx) {
    if ($('#consultaarticuloModal').data('articuloSoloFacturable')) {
        return true;
    }
    if ($ctx && $ctx.length && $ctx.closest('[data-articulo-solo-facturable="1"]').length) {
        return true;
    }
    return false;
}

function buscar_datos_articulo(consulta) {
    if (consultaArticuloAjax && consultaArticuloAjax.readyState !== 4) {
        consultaArticuloAjax.abort();
    }
    var postData = { consulta: consulta };
    var prefFiltro = $('#consultaarticuloModal').data('articuloSkuPrefijoFiltro');
    if (typeof prefFiltro === 'string' && prefFiltro.length > 0) {
        postData.sku_prefijo = prefFiltro;
    }
    var digitosFiltro = $('#consultaarticuloModal').data('articuloSkuDigitosFiltro');
    if (digitosFiltro > 0) {
        postData.sku_digitos_sufijo = digitosFiltro;
    }
    var listaPrecio = consultaArticuloResolverListaprecio();
    if (listaPrecio.id > 0) {
        postData.listaprecio_id = listaPrecio.id;
    }
    if ($('#consultaarticuloModal').data('articuloSoloFacturable')) {
        postData.solo_facturable = 1;
    }
    if ($('#consultaarticuloModal').data('articuloSoloInsumoGastronomia')) {
        postData.solo_insumo_gastronomia = 1;
    }
    var empresaIdConsulta = empresaIdConsultaArticulo();
    if (empresaIdConsulta !== '') {
        postData.empresa_id = empresaIdConsulta;
    }
    consultaArticuloAjax = $.ajax({
        url: carpetaBase+'/stock/articulo/consultaarticulo',
        type: 'POST',
        dataType: 'json',
	    headers: {
        	'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    	},
        data: postData,
    })
    .done (function(respuesta) {
        aplicarRespuestaConsultaArticulo(respuesta);
    })
    .fail (function(xhr, status) {
        if (status !== 'abort') {
            console.log("error");
        }
    });
}

// Enter en input: no dispara submit accidental. En tablas con Enter=Tab (req/OC) o campos especiales se deja pasar al handler del módulo.
$(document).off('keydown.ocNoEnterSubmitArticulo', 'input').on('keydown.ocNoEnterSubmitArticulo', 'input', function (e) {
	var keyCode = e.which;
	if (keyCode !== 13) {
		return;
	}
	// Búsqueda rápida del index (lupa / Enter): no bloquear.
	if ($(this).is('#filtro_valor, #filtro_valor_panel') || $(this).attr('name') === 'filtro_valor') {
		return;
	}
	if ($(this).hasClass('gastro-carga-sku')) {
		return;
	}
	if ($(this).closest('#tabla-recuento-items').length && $(this).hasClass('input-cantidad-contada')) {
		return;
	}
    if ($(this).closest('#tabla-recuento-items').length && $(this).hasClass('codigoarticulo')) {
        return;
    }
    if ($(this).closest('#tabla-items-recepcion').length && $(this).hasClass('codigoarticulo')) {
        return;
    }
    if ($(this).closest('#tabla-items-movimientostock').length && $(this).hasClass('codigoarticulo')) {
        return;
    }
    if ($(this).closest('#tabla-articulos-requisicion').length) {
        return;
    }
    if ($(this).closest('#tabla-articulos-ordencompra').length) {
        return;
    }
    if ($(this).closest('#tabla-articulos-requisicion-sala').length) {
        return;
    }
    if ($(this).closest('#tabla-vianda-semana').length && $(this).hasClass('codigoarticulo')) {
        return;
    }
    if ($(this).closest('#cp-articulo-table').length && $(this).hasClass('codigoarticulo')) {
        return;
    }
	// Resto del form OC (fuera de la grilla de artículos): no bloquear Enter.
	if ($(this).closest('#form-ordencompra-general').length) {
		return;
	}
	if ($(this).closest('.tm-deposito-campo').length && $(this).hasClass('codigodeposito')) {
		return;
	}
	e.preventDefault();
	return false;
});

function activa_eventos_consultaarticulo()
{
    // Consulta de artículo (delegado para filas agregadas dinámicamente)
    $(document).off('click.consultaArtBtn', '.consultaarticulo').on('click.consultaArtBtn', '.consultaarticulo', function (event) {
        var prefijoSku = ($(this).attr('data-sku-prefijo-filtro') || '').trim();
        var digitosSku = parseInt($(this).attr('data-sku-digitos-filtro') || '0', 10) || 0;
        $('#consultaarticuloModal').data('articuloSkuPrefijoFiltro', prefijoSku);
        if (digitosSku > 0) {
            $('#consultaarticuloModal').data('articuloSkuDigitosFiltro', digitosSku);
        } else {
            $('#consultaarticuloModal').removeData('articuloSkuDigitosFiltro');
        }

        var listaIdBtn = parseInt($(this).attr('data-listaprecio-id') || '0', 10);
        if (listaIdBtn > 0) {
            $('#consultaarticuloModal').data('articuloListaprecioId', listaIdBtn);
            $('#consultaarticuloModal').data('articuloListaprecioNombre', ($(this).attr('data-listaprecio-nombre') || '').trim());
        } else if (window.GASTRONOMIA && parseInt(window.GASTRONOMIA.listaprecioId, 10) > 0) {
            $('#consultaarticuloModal').data('articuloListaprecioId', parseInt(window.GASTRONOMIA.listaprecioId, 10));
            $('#consultaarticuloModal').data('articuloListaprecioNombre', (window.GASTRONOMIA.listaprecioNombre || '').toString());
        } else {
            $('#consultaarticuloModal').removeData('articuloListaprecioId');
            $('#consultaarticuloModal').removeData('articuloListaprecioNombre');
        }

        if ($(this).attr('data-solo-facturable') === '1') {
            $('#consultaarticuloModal').data('articuloSoloFacturable', 1);
        } else {
            $('#consultaarticuloModal').removeData('articuloSoloFacturable');
        }

        var $ctxInsumo = $(this).closest('[data-articulo-solo-insumo-gastronomia="1"]');
        if ($ctxInsumo.length) {
            $('#consultaarticuloModal').data('articuloSoloInsumoGastronomia', 1);
        } else {
            $('#consultaarticuloModal').removeData('articuloSoloInsumoGastronomia');
        }

        var $ctxConsulta = consultaArticuloContextoLinea(this);
        ptrarticulo_id = $ctxConsulta.find('.articulo_id').first();
        ptrcodigoarticulo = $ctxConsulta.find('.codigoarticulo').first();
        ptrnombrearticulo = $ctxConsulta.find('.descripcionarticulo').first();
        ptrunidadmedida = $ctxConsulta.find('.unidadmedida').first();
        ptrcategoria_id = $ctxConsulta.find('.categoria_id').first();
        ptrsubcategoria_id = $ctxConsulta.find('.subcategoria_id').first();
        // Abre modal de consulta
        $("#consultaarticuloModal").modal('show');
    });

    $('#consultaarticuloModal').off('show.bs.modal.consultaArt').on('show.bs.modal.consultaArt', function () {
        actualizarEncabezadoPrecioListaConsulta({
            listaprecio_id: consultaArticuloResolverListaprecio().id,
            listaprecio_nombre: consultaArticuloResolverListaprecio().nombre,
            mostrar_precio_lista: consultaArticuloMostrarPrecioLista(),
        });
        var prefijo = $('#consultaarticuloModal').data('articuloSkuPrefijoFiltro');
        var valorInicial = '';
        if (prefijo) {
            var suf = $('#tr-gastro-linea-articulo .gastro-sku-sufijo').val();
            if (!suf) {
                suf = $('#cm-campo-articulo-carga .gastro-sku-sufijo').val();
            }
            if (suf) {
                valorInicial = String(suf).replace(/\D/g, '');
            }
        }
        $('#consulta').val(valorInicial);
        clearTimeout(consultaArticuloTimer);
        if (consultaArticuloAjax && consultaArticuloAjax.readyState !== 4) {
            consultaArticuloAjax.abort();
        }
        var minLen = consultaArticuloMinLen(valorInicial);
        if (valorInicial.length >= minLen) {
            buscar_datos_articulo(valorInicial);
        } else {
            $("#datos").html(htmlTablaConsultaArticuloMensaje(consultaArticuloMensajeMinLen(minLen)));
        }
    });

    $('#consultaarticuloModal').off('hidden.bs.modal.consultaArtPrefijo').on('hidden.bs.modal.consultaArtPrefijo', function () {
        $('#consultaarticuloModal').removeData('articuloSkuPrefijoFiltro');
        $('#consultaarticuloModal').removeData('articuloSkuDigitosFiltro');
        $('#consultaarticuloModal').removeData('articuloListaprecioId');
        $('#consultaarticuloModal').removeData('articuloListaprecioNombre');
        $('#consultaarticuloModal').removeData('articuloSoloFacturable');
        $('#consultaarticuloModal').removeData('articuloSoloInsumoGastronomia');
    });

    $('#consultaarticuloModal').off('shown.bs.modal.consultaArt').on('shown.bs.modal.consultaArt', function () {
        $(this).find('#consulta').focus();
    });

    $(document).off('input.consultaArtCampo', '#consulta').on('input.consultaArtCampo', '#consulta', function () {
        var valor = ($(this).val() || '').trim();
        var minLen = consultaArticuloMinLen(valor);
        clearTimeout(consultaArticuloTimer);
        if (valor.length < minLen) {
            if (consultaArticuloAjax && consultaArticuloAjax.readyState !== 4) {
                consultaArticuloAjax.abort();
            }
            if (valor.length === 0) {
                $("#datos").html(htmlTablaConsultaArticuloMensaje(consultaArticuloMensajeMinLen(minLen)));
            } else {
                $("#datos").html(htmlTablaConsultaArticuloMensaje('Ingrese al menos ' + minLen + (minLen === 1 ? ' dígito' : ' caracteres') + ' para buscar.'));
            }
            return;
        }
        consultaArticuloTimer = setTimeout(function () {
            buscar_datos_articulo(valor);
        }, CONSULTA_ARTICULO_DEBOUNCE_MS);
    });

    $('#aceptaconsultaarticuloModal').on('click', function () {
        $('#consultaarticuloModal').modal('hide');
    });

    $(document).on('click', '.eligeconsultaarticulo', function () {
        let seleccion = $(this).parents("tr").children().html();
        let codigo = $(this).parents("tr").find(".sku").html();
        let nombre = $(this).parents("tr").find(".descripcion").html();
        let unidadmedida = $(this).parents("tr").find(".unidadmedida").html();
        let unidadmedida_id = $(this).parents("tr").find(".idunidadmedida").val();
        let categoria_id = $(this).parents("tr").find(".categoria_id").val();
        let subcategoria_id = $(this).parents("tr").find(".subcategoria_id").val();

        var $ctxArt = (ptrarticulo_id && ptrarticulo_id.length)
            ? consultaArticuloContextoLinea(ptrarticulo_id)
            : $();
        var unidadmedidaAplicada = aplicarUnidadMedidaLineaArticulo($ctxArt, unidadmedida_id, unidadmedida);

        $(ptrarticulo_id).val(seleccion);
        $(ptrcodigoarticulo).val(codigo);
        $(ptrnombrearticulo).val(nombre);
        $(ptrunidadmedida).val(unidadmedidaAplicada || unidadmedida);
        $(ptrcategoria_id).val(categoria_id);
        $(ptrsubcategoria_id).val(subcategoria_id);

        actualizarLinkEditarArticulo($ctxArt, seleccion);

        $("#articulo_id").val(seleccion);
        $("#descripcionarticulo").val(nombre);
        $("#codigoarticulo").val(codigo);

        if (window.rellenaAtributosArticuloOrdenProduccion) {
            $.get(carpetaBase + '/stock/leerunarticulo/' + seleccion, function (dataArticulo) {
                if (dataArticulo) {
                    window.rellenaAtributosArticuloOrdenProduccion(dataArticulo);
                }
            });
        }

        if (window.onArticuloSeleccionado) {
            $.get(carpetaBase + '/stock/leerunarticulo/' + seleccion, function (dataArticulo) {
                if (dataArticulo) {
                    var $trModal = (ptrarticulo_id && ptrarticulo_id.length)
                        ? consultaArticuloContextoLinea(ptrarticulo_id)
                        : $();
                    window.onArticuloSeleccionado(dataArticulo, { row: $trModal });
                    if ($trModal.closest('#tabla-articulos-requisicion').length) {
                        $trModal.trigger('req:articulo-linea-cargado', [dataArticulo]);
                    }
                }
            });
        }

        var $trElegida = (ptrarticulo_id && ptrarticulo_id.length)
            ? consultaArticuloContextoLinea(ptrarticulo_id)
            : $();
        var esPosGastronomia = $trElegida.is('#tr-gastro-linea-articulo')
            || $trElegida.closest('#tr-gastro-linea-articulo').length > 0
            || $trElegida.closest('.cm-campo-articulo-carga, #cm-campo-articulo-carga').length > 0;
        $('#consultaarticuloModal').off('hidden.bs.modal.consultaArtFocusCant').one('hidden.bs.modal.consultaArtFocusCant', function () {
            if (esPosGastronomia) {
                return;
            }
            enfocarCantidadLineaArticulo($trElegida, unidadmedidaAplicada || unidadmedida);
        });
        $('#consultaarticuloModal').modal('hide');

        // Si es salamin tira saca opciones que no van del descuento
        if (window.armaSelectDescuentoVenta) {
            armaSelectDescuentoVenta(ptrarticulo_id);
        }

        if (window.asignaPrecio && ptrarticulo_id && ptrarticulo_id.length) {
            var articuloIdElige = $(this).parents('tr').find('td.articulo_id').first().text().trim();
            if (!articuloIdElige) {
                articuloIdElige = String(seleccion).trim();
            }
            asignaPrecio(ptrarticulo_id[0], articuloIdElige, '');
        }
    });

    $('#articulo_id').on('change', function (event) {
        event.preventDefault();

        // Lee servicio terrestre por codigo
        let articulo_id = $("#articulo_id").val();
        let url_res = carpetaBase+'/stock/leerunarticulo/'+articulo_id;

        $.get(url_res, function(data){
            if (data)
            {
                $("#articulo_id").val(data.id);
                $("#descripcionarticulo").val(data.descripcion);

                $.each(data.unidadesdemedidas, function(index,value){
                    if (index == 'abreviatura')
                        $("#unidadmedida").val(value);
                });

            }
        });

        setTimeout(() => {
        }, 1000);

    });

    $(document).off('change.ocArticuloIdLinea', '.articulo_id').on('change.ocArticuloIdLinea', '.articulo_id', function (event) {
        event.preventDefault();
        var ptrrenglon = this;
        var $ctx = consultaArticuloContextoLinea(ptrrenglon);

        // Lee concepto gasto
        let articulo_id = $(this).val();
        let url_res = carpetaBase+'/stock/leerunarticulo/'+articulo_id;

        $.get(url_res, function(data){
            if (data)
            {
                $ctx.find('.articulo_id').val(data.id);
                $ctx.find('.descripcionarticulo').val(data.descripcion);

                $.each(data.unidadesdemedidas, function(index,value){
                    if (index == 'abreviatura')
                        $ctx.find('.unidadmedida').val(value);
                });

                $("#articulo_id").val(data.id);
                $("#descripcionarticulo").val(data.descripcion);
                $("#unidadmedida").val(data.unidadmedida);
            }
        });

        setTimeout(() => {
        }, 1000);

    });

    $(document).off('change.ocCodigoArticuloLinea', '.codigoarticulo').on('change.ocCodigoArticuloLinea', '.codigoarticulo', function (event) {
        event.preventDefault();
        var ptrrenglon = this;
        var $tr = consultaArticuloContextoLinea(ptrrenglon);

        let sku = ($(this).val() || '').trim();
        if (!sku) {
            $tr.find('.articulo_id').val('');
            $tr.find('.descripcionarticulo').val('');
            return;
        }

        let url_res = urlLeerArticuloPorSku(sku, consultaArticuloRequiereSoloFacturable($tr) ? 'solo_facturable=1' : '');

        $.get(url_res, function (data) {
            if (!data || !data.id) {
                $tr.find('.articulo_id').val('');
                $tr.find('.descripcionarticulo').val('');
                alert(consultaArticuloRequiereSoloFacturable($tr)
                    ? 'No se encontró artículo facturable con ese SKU.'
                    : 'No se encontró artículo con ese SKU.');
                return;
            }

            $tr.find('.articulo_id').val(data.id);
            $tr.find('.descripcionarticulo').val(data.descripcion);
            $tr.find('.categoria_id').val(data.categoria_id);
            $tr.find('.subcategoria_id').val(data.subcategoria_id);
            actualizarLinkEditarArticulo($tr, data.id);

            let unidadmedida = aplicarUnidadMedidaLineaArticulo(
                $tr,
                data.unidadmedida_id,
                abreviaturaUnidadMedidaArticulo(data)
            );

            $("#articulo_id").val(data.id);
            $("#descripcionarticulo").val(data.descripcion);
            $("#nombrearticulo").val(data.descripcion);
            $("#unidadmedida").val(unidadmedida);
            $("#codigoarticulo").val(data.sku);

            if (window.asignaPrecio) {
                asignaPrecio(ptrrenglon, data.id, '');
            }

            if (window.controlDescuento) {
                if (!controlDescuento(ptrrenglon))
                {
                    alert("No puede cargar el artículo");
                    borraRenglon();
                }
            }

            if (window.armaSelectDescuentoVenta) {
                armaSelectDescuentoVenta(ptrrenglon);
            }

            if (window.rellenaAtributosArticuloOrdenProduccion) {
                window.rellenaAtributosArticuloOrdenProduccion(data);
            }

            if (window.onArticuloSeleccionado) {
                window.onArticuloSeleccionado(data, { row: $tr });
            }

            if ($tr.closest('#tabla-articulos-requisicion').length) {
                $tr.trigger('req:articulo-linea-cargado', [data]);
            }

            enfocarCantidadLineaArticulo($tr, unidadmedida);
        }).fail(function () {
            $tr.find('.articulo_id').val('');
            $tr.find('.descripcionarticulo').val('');
            alert('No se encontró artículo con ese SKU.');
        });

        setTimeout(() => {
        }, 1000);

    });    

    $('#codigoarticulo').on('change', function (event) {
        event.preventDefault();

        let sku = $(this).val();
        let $ctxCodigo = consultaArticuloContextoLinea(this);
        if (!$ctxCodigo.length) {
            $ctxCodigo = $(this).closest('form');
        }
        let url_res = urlLeerArticuloPorSku(sku);

        $.get(url_res, function(data){
            if (data)
            {
                $("#articulo_id").val(data.id);
                $("#descripcionarticulo").val(data.descripcion);
                $("#nombrearticulo").val(data.descripcion);
                $("#unidadmedida").val(aplicarUnidadMedidaLineaArticulo(
                    $ctxCodigo,
                    data.unidadmedida_id,
                    abreviaturaUnidadMedidaArticulo(data)
                ) || abreviaturaUnidadMedidaArticulo(data));
                $("#codigoarticulo").val(data.sku);

                if (window.rellenaAtributosArticuloOrdenProduccion) {
                    window.rellenaAtributosArticuloOrdenProduccion(data);
                }
            }
        });

        setTimeout(() => {
        }, 1000);

    });        

}

$(function () {
    $('tr, .tm-articulo-campo').each(function () {
        var $ctx = $(this);
        var articuloId = parseInt($ctx.find('.articulo_id').val(), 10) || 0;
        if (articuloId > 0 && (
            $ctx.find('.btn-link-articulo').length
            || $ctx.find('a[href*="editar_articulo"], a[href*="/stock/articulo/"][href*="/editar"]').not('.btn-link-articulo-destino').length
        )) {
            actualizarLinkEditarArticulo($ctx, articuloId);
        }
    });
});


