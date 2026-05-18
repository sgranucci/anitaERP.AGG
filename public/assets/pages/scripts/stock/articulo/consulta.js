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

function htmlTablaConsultaArticuloMensaje(texto) {
    return '<tr><td colspan="6" class="text-muted">' + texto + '</td></tr>';
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
    if (respuesta && typeof respuesta.data === 'string') {
        html = respuesta.data;
    } else if (typeof respuesta === 'string') {
        try {
            html = JSON.parse(respuesta).data || '';
        } catch (e) {
            html = respuesta.replace(/\\/g, '');
        }
    }
    $("#datos").html(html);
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

// Enter en input: no dispara submit accidental, salvo en formulario de orden de compra (allí se deja el comportamiento por defecto).
$(document).off('keydown.ocNoEnterSubmitArticulo', 'input').on('keydown.ocNoEnterSubmitArticulo', 'input', function (e) {
	var keyCode = e.which;
	if (keyCode !== 13) {
		return;
	}
	if ($(this).closest('#form-ordencompra-general').length) {
		return;
	}
	if ($(this).hasClass('gastro-carga-sku')) {
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

        //if ($(this).parents("tr").find(".articulo_id").length > 0) {
            ptrarticulo_id = $(this).closest("tr").find(".articulo_id");
            ptrcodigoarticulo = $(this).closest("tr").find(".codigoarticulo");
            ptrnombrearticulo = $(this).closest("tr").find(".descripcionarticulo");
            ptrunidadmedida = $(this).closest("tr").find(".unidadmedida");
            ptrcategoria_id = $(this).closest("tr").find(".categoria_id");
            ptrsubcategoria_id = $(this).closest("tr").find(".subcategoria_id");
        //}
        // Abre modal de consulta
        $("#consultaarticuloModal").modal('show');
    });

    $('#consultaarticuloModal').off('show.bs.modal.consultaArt').on('show.bs.modal.consultaArt', function () {
        var prefijo = $('#consultaarticuloModal').data('articuloSkuPrefijoFiltro');
        var valorInicial = '';
        if (prefijo) {
            var suf = $('#tr-gastro-linea-articulo .gastro-sku-sufijo').val();
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

        $(ptrarticulo_id).parents("tr").find(".unidadmedida_id").val(unidadmedida_id);

        $(ptrarticulo_id).val(seleccion);
        $(ptrcodigoarticulo).val(codigo);
        $(ptrnombrearticulo).val(nombre);
        $(ptrunidadmedida).val(unidadmedida);
        $(ptrcategoria_id).val(categoria_id);
        $(ptrsubcategoria_id).val(subcategoria_id);

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
                    window.onArticuloSeleccionado(dataArticulo, { row: $(ptrarticulo_id).closest('tr') });
                }
            });
        }

        if (unidadmedida != null)
        {
            if (unidadmedida.toUpperCase() == 'CAJ')
                $(ptrarticulo_id).parents("tr").find(".caja").focus();

            if (unidadmedida.toUpperCase() == 'UN')
                $(ptrarticulo_id).parents("tr").find(".pieza").focus();        
            
            if (unidadmedida.toUpperCase() == 'KG' || unidadmedida.toUpperCase() == 'KIL')
                $(ptrarticulo_id).parents("tr").find(".pieza").focus();           
        }
        $('#consultaarticuloModal').modal('hide');

        // Si es salamin tira saca opciones que no van del descuento
        if (window.armaSelectDescuentoVenta) {
            armaSelectDescuentoVenta(ptrarticulo_id);
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

        // Lee concepto gasto
        let articulo_id = $(this).val();
        let url_res = carpetaBase+'/stock/leerunarticulo/'+articulo_id;

        $.get(url_res, function(data){
            if (data)
            {
                $(ptrrenglon).parents("tr").find(".articulo_id").val(data.id);
			    $(ptrrenglon).parents("tr").find(".descripcionarticulo").val(data.descripcion);

                $.each(data.unidadesdemedidas, function(index,value){
                    if (index == 'abreviatura')
                        $(ptrrenglon).parents("tr").find(".unidadmedida").val(value);
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
        var $tr = $(ptrrenglon).closest('tr');

        let sku = ($(this).val() || '').trim();
        if (!sku) {
            $tr.find('.articulo_id').val('');
            $tr.find('.descripcionarticulo').val('');
            return;
        }

        let url_res = carpetaBase + '/stock/leerunarticuloporsku/' + encodeURIComponent(sku);

        $.get(url_res, function (data) {
            if (!data || !data.id) {
                $tr.find('.articulo_id').val('');
                $tr.find('.descripcionarticulo').val('');
                alert('No se encontró artículo con ese SKU.');
                return;
            }

            $tr.find('.articulo_id').val(data.id);
            $tr.find('.descripcionarticulo').val(data.descripcion);
            $tr.find('.unidadmedida_id').val(data.unidadmedida_id);
            $tr.find('.categoria_id').val(data.categoria_id);
            $tr.find('.subcategoria_id').val(data.subcategoria_id);

            $.each(data.unidadesdemedidas, function (index, value) {
                if (index == 'abreviatura') {
                    $tr.find('.unidadmedida').val(value);
                }
            });

            $("#articulo_id").val(data.id);
            $("#descripcionarticulo").val(data.descripcion);
            $("#nombrearticulo").val(data.descripcion);
            $("#unidadmedida").val(data.unidadmedida);
            $("#codigoarticulo").val(data.sku);

            let unidadmedida = $tr.find('.unidadmedida').val();

            if (unidadmedida != null)
            {
                if (unidadmedida.toUpperCase() == 'CAJ') {
                    $tr.find('.caja').focus();
                }

                if (unidadmedida.toUpperCase() == 'UN') {
                    $tr.find('.pieza').focus();
                }

                if (unidadmedida.toUpperCase() == 'KG' || unidadmedida.toUpperCase() == 'KIL') {
                    $tr.find('.pieza').focus();
                }
            }
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
        let url_res = carpetaBase+'/stock/leerunarticuloporsku/'+sku;

        $.get(url_res, function(data){
            if (data)
            {
                $("#articulo_id").val(data.id);
                $("#descripcionarticulo").val(data.descripcion);
                $("#nombrearticulo").val(data.descripcion);
                $("#unidadmedida").val(data.unidadmedida);
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


