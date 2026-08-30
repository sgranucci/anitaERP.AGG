// Scripts para carga de movimientos de stock

	var talles_txt;
	var medidas_txt;
	var precios_txt;
	var tallesid_txt;
	var cantidadmodal_txt;
	var nombre_modulo;
	var moduloElegido_id;
	var articuloxsku;
	var descripcionxsku;
	var skuxsku;
	var pedido_combinacion;
	var descripcion_articulo;
	var nombre_combinacion;
	var tbl_medidas;
	var medidas=[];
	var cantidades=[];
	var cantidades_por_modulo=[];
	var precios=[];
	var dpr=[];
	var dlp=[];
	var dii=[];
	var dmo=[];
	var totPares;
	var cantidad;
	var precio;
	var flAnulacionItem = false;
	var itemAnulacion;
	var itemAnulacionId;
	var botonAnulacion;
	var fl_tiene_entrega = false;
	var modulo_actual = 1;
	var codigoAnulacionOt;
	var idAnulacionOt;
	var motivoAnulacionOt
	var nombreClienteAnulacionOt;
	var itemAnulacionOt;
	var flFactura;
	var pedido_combinacion_ids=[];
	var cliente_id;
	var modulo_actual = 1;
	var ordentrabajo_ids=[];
	var nombrecliente;
	var tallesfactura_txt=[];
	var medidasfactura_txt=[]; 
	var preciosfactura_txt=[]; 
	var tallesidfactura_txt=[];
	var titulofactura_txt=[];
	var offFactura;
	var modalActivo;
	var descuentoCliente;
	var totalDebe = 0;
	var totalHaber = 0;
	var totalDebeAsiento = 0;
	var totalHaberAsiento = 0;
	var totalMoneda=[];
	var idMoneda=[];
	var descripcionMoneda=[];
	var flCrear;
	var flModificaAsiento;

	window.FL_FACTURA_LAYOUT_PEDIDO = window.FL_FACTURA_LAYOUT_PEDIDO || (
		document.querySelector('#datosfactura') &&
		document.querySelector('#datosfactura').dataset.layoutItemsPedido === '1'
	);

	(function () {
		var overlayTimer = null;
		var overlayActivo = false;
		var onResultadoCerrar = null;
		var mensajesFactura = [
			'Calculando importes…',
			'Numerando comprobante…',
			'Solicitando CAE en ARCA…',
			'Registrando el comprobante…',
		];
		var mensajesNc = [
			'Preparando nota de crédito…',
			'Solicitando CAE en ARCA…',
			'Registrando la nota de crédito…',
		];

		function overlayEl() {
			return document.getElementById('factura-procesando-overlay');
		}

		function mostrarOverlay(on) {
			var el = overlayEl();
			if (!el) {
				return;
			}
			if (on) {
				el.classList.remove('d-none');
				el.style.display = 'flex';
				el.setAttribute('aria-hidden', 'false');
			} else {
				el.classList.add('d-none');
				el.style.display = '';
				el.setAttribute('aria-hidden', 'true');
			}
		}

		function mostrarSpinner(titulo, subtitulo) {
			$('#factura-procesando-spinner').removeClass('d-none');
			$('#factura-procesando-resultado').addClass('d-none');
			var t = document.getElementById('factura-procesando-titulo');
			var s = document.getElementById('factura-procesando-subtitulo');
			if (t) {
				t.textContent = titulo || 'Generando comprobante…';
			}
			if (s) {
				s.textContent = subtitulo || 'Por favor espere. No cierre ni recargue la página.';
			}
		}

		function escHtml(texto) {
			return String(texto == null ? '' : texto)
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;');
		}

		function iconoResultado(tipo) {
			switch (tipo) {
				case 'ok':
					return 'fa-check-circle text-success';
				case 'parcial':
					return 'fa-exclamation-triangle text-warning';
				case 'error':
				default:
					return 'fa-times-circle text-danger';
			}
		}

		function mostrarPanelResultado(opciones) {
			var opts = opciones || {};
			var tipo = opts.tipo || 'error';

			$('#factura-procesando-spinner').addClass('d-none');
			$('#factura-procesando-resultado').removeClass('d-none');

			$('#factura-procesando-resultado-icono')
				.attr('class', 'fa fa-2x mb-2 ' + iconoResultado(tipo));
			$('#factura-procesando-resultado-titulo').text(opts.titulo || 'Resultado del proceso');
			$('#factura-procesando-resultado-subtitulo').text(opts.subtitulo || '');

			var $facturas = $('#factura-procesando-resultado-facturas').empty();
			(opts.facturas || []).forEach(function (item) {
				var codigo = item && item.codigo ? String(item.codigo) : '';
				if (!codigo) {
					return;
				}
				var ok = !(item && item.ok === false);
				var detalle = item && item.detalle ? String(item.detalle) : '';
				var clase = ok ? 'text-success' : 'text-danger';
				var icono = ok ? 'fa-check' : 'fa-times';
				var html =
					'<li class="' + clase + ' mb-1">' +
					'<i class="fa ' + icono + ' mr-1" aria-hidden="true"></i>' +
					'<strong>' + escHtml(codigo) + '</strong>';
				if (detalle) {
					html += '<div class="text-muted ml-3">' + escHtml(detalle) + '</div>';
				}
				html += '</li>';
				$facturas.append(html);
			});

			var $errores = $('#factura-procesando-resultado-errores').empty();
			(opts.errores || []).forEach(function (err) {
				var texto = String(err || '').trim();
				if (!texto) {
					return;
				}
				$errores.append(
					'<li class="mb-1"><i class="fa fa-exclamation-circle mr-1" aria-hidden="true"></i>' +
						escHtml(texto) +
						'</li>'
				);
			});

			var btnLabel = opts.boton || (tipo === 'ok' ? 'Continuar' : 'Cerrar');
			$('#factura-procesando-resultado-cerrar').text(btnLabel);
			onResultadoCerrar = typeof opts.onCerrar === 'function' ? opts.onCerrar : null;
		}

		window.FacturaProcesoOverlay = {
			iniciar: function (esNc) {
				this.detener();
				overlayActivo = true;
				var msgs = esNc ? mensajesNc : mensajesFactura;
				var idx = 0;
				$('.factura-carga-bloqueable').filter('button, .btn, input[type="submit"], input[type="button"]').prop('disabled', true);
				if (window.AnitaGrabacion && typeof window.AnitaGrabacion.liberar === 'function') {
					window.AnitaGrabacion.liberar();
				}
				mostrarOverlay(true);
				mostrarSpinner(esNc ? 'Generando nota de crédito…' : 'Generando comprobante…');
				overlayTimer = setInterval(function () {
					idx = (idx + 1) % msgs.length;
					var t = document.getElementById('factura-procesando-titulo');
					if (t) {
						t.textContent = msgs[idx];
					}
				}, 2200);
			},
			detener: function () {
				if (overlayTimer) {
					clearInterval(overlayTimer);
					overlayTimer = null;
				}
				overlayActivo = false;
				onResultadoCerrar = null;
				$('.factura-carga-bloqueable').filter('button, .btn, input[type="submit"], input[type="button"]').prop('disabled', false);
				mostrarOverlay(false);
			},
			mostrarResultado: function (opciones) {
				if (overlayTimer) {
					clearInterval(overlayTimer);
					overlayTimer = null;
				}
				overlayActivo = true;
				$('.factura-carga-bloqueable').filter('button, .btn, input[type="submit"], input[type="button"]').prop('disabled', false);
				mostrarOverlay(true);
				mostrarPanelResultado(opciones);
			},
		};

		$(document).on('click', '#factura-procesando-resultado-cerrar', function () {
			var cb = onResultadoCerrar;
			onResultadoCerrar = null;
			window.FacturaProcesoOverlay.detener();
			if (typeof cb === 'function') {
				cb();
			}
		});

		window.addEventListener('pageshow', function () {
			if (window.FacturaProcesoOverlay) {
				window.FacturaProcesoOverlay.detener();
			}
		});
	})();

	function esProcesoNotaDeCreditoFactura() {
		var form = document.getElementById('formgeneral');
		return !!(form && form.getAttribute('data-factura-proceso') === 'nc');
	}

	function iniciarOverlayProcesoFactura() {
		if (window.FacturaProcesoOverlay) {
			window.FacturaProcesoOverlay.iniciar(esProcesoNotaDeCreditoFactura());
		}
	}

	function extraerFacturaRespuestaMostrador(item) {
		if (!item || typeof item !== 'object') {
			return '';
		}
		if (item.factura) {
			return String(item.factura);
		}
		if (item.codigo) {
			return String(item.codigo);
		}
		return '';
	}

	function mensajeErrorAjaxFacturaMostrador(xhr) {
		if (xhr && xhr.responseJSON) {
			var data = xhr.responseJSON;
			if (Array.isArray(data) && data[0] && data[0].error) {
				return String(data[0].error);
			}
			if (data.error) {
				return String(data.error);
			}
			if (data.message) {
				return String(data.message);
			}
			if (data.mensaje) {
				return String(data.mensaje);
			}
		}
		if (xhr && xhr.responseText && xhr.responseText.indexOf('<') < 0) {
			return xhr.responseText.substring(0, 500);
		}
		return 'No se pudo generar el comprobante' + (xhr && xhr.status ? ' (HTTP ' + xhr.status + ').' : '.');
	}

	function analizarResultadoFacturaMostrador(data) {
		var items = Array.isArray(data) ? data : [data];
		var comprobantes = [];
		var errores = [];
		var redirect = '';
		var i;
		for (i = 0; i < items.length; i++) {
			var item = items[i];
			if (item == null || typeof item !== 'object') {
				errores.push('Respuesta inválida del servidor.');
				continue;
			}
			if (item.redirect) {
				redirect = String(item.redirect);
			}
			var errorItem = item.error ? String(item.error).trim() : '';
			var factura = extraerFacturaRespuestaMostrador(item);
			if (errorItem) {
				errores.push(errorItem);
				if (factura) {
					comprobantes.push({ codigo: factura, ok: false, detalle: errorItem });
				}
				continue;
			}
			if (factura) {
				comprobantes.push({ codigo: factura, ok: true, detalle: item.aviso_caea ? String(item.aviso_caea) : null });
			}
		}

		var facturasOk = comprobantes.filter(function (c) { return c.ok; });
		var exito = facturasOk.length > 0 && errores.length === 0;
		var esNc = esProcesoNotaDeCreditoFactura();
		var tituloOk = esNc ? 'Nota de crédito generada' : 'Comprobante generado';
		var tituloError = esNc ? 'Error al generar la nota de crédito' : 'Error al generar el comprobante';

		return {
			exito: exito,
			tipo: exito ? 'ok' : 'error',
			titulo: exito ? tituloOk : tituloError,
			subtitulo: exito
				? (esNc
					? 'La nota de crédito quedó registrada correctamente.'
					: 'El comprobante quedó registrado correctamente.')
				: (errores.length === 1 ? errores[0] : 'Revise los detalles a continuación.'),
			facturas: comprobantes,
			errores: exito ? [] : errores,
			redirect: redirect,
		};
	}

	function mostrarResultadoFacturaMostrador(resumen) {
		if (!window.FacturaProcesoOverlay || typeof window.FacturaProcesoOverlay.mostrarResultado !== 'function') {
			if (resumen.exito) {
				window.location.href = resumen.redirect || (document.getElementById('formgeneral') || {}).getAttribute('data-factura-redirect') || window.location.href;
				return;
			}
			alert(resumen.subtitulo || 'No se pudo generar el comprobante.');
			return;
		}

		window.FacturaProcesoOverlay.mostrarResultado({
			tipo: resumen.tipo,
			titulo: resumen.titulo,
			subtitulo: resumen.subtitulo,
			facturas: resumen.facturas,
			errores: resumen.errores,
			boton: resumen.exito ? 'Continuar' : 'Cerrar',
			onCerrar: function () {
				if (!resumen.exito) {
					return;
				}
				var destino = resumen.redirect
					|| (document.getElementById('formgeneral') || {}).getAttribute('data-factura-redirect')
					|| '';
				if (destino) {
					window.location.href = destino;
					return;
				}
				window.location.reload();
			},
		});
	}

	function enviarComprobanteFacturaMostradorAjax() {
		var form = document.getElementById('formgeneral');
		if (!form) {
			return;
		}

		if (typeof validarPadronOperacionAntesSubmitForm === 'function') {
			var evPadron = { preventDefault: function () {}, target: form, defaultPrevented: false };
			if (validarPadronOperacionAntesSubmitForm(evPadron) === false) {
				if (window.FacturaProcesoOverlay) {
					window.FacturaProcesoOverlay.detener();
				}
				return;
			}
		}

		iniciarOverlayProcesoFactura();

		var fd = new FormData(form);
		fd.append('ajax_overlay', '1');

		$.ajax({
			url: form.getAttribute('action'),
			method: (form.getAttribute('method') || 'POST').toUpperCase() === 'GET' ? 'GET' : 'POST',
			data: fd,
			processData: false,
			contentType: false,
			dataType: 'json',
			headers: {
				'Accept': 'application/json',
				'X-Requested-With': 'XMLHttpRequest',
			},
			timeout: 180000,
		})
			.done(function (data) {
				mostrarResultadoFacturaMostrador(analizarResultadoFacturaMostrador(data));
			})
			.fail(function (xhr) {
				var resumen;
				if (xhr && xhr.responseJSON) {
					resumen = analizarResultadoFacturaMostrador(xhr.responseJSON);
					if (!resumen.errores.length) {
						resumen.errores = [mensajeErrorAjaxFacturaMostrador(xhr)];
						resumen.subtitulo = resumen.errores[0];
					}
					resumen.exito = false;
					resumen.tipo = 'error';
				} else {
					var msg = mensajeErrorAjaxFacturaMostrador(xhr);
					resumen = {
						exito: false,
						tipo: 'error',
						titulo: esProcesoNotaDeCreditoFactura() ? 'Error al generar la nota de crédito' : 'Error al generar el comprobante',
						subtitulo: msg,
						facturas: [],
						errores: [msg],
						redirect: '',
					};
				}
				mostrarResultadoFacturaMostrador(resumen);
			});
	}

	window.validarSubmitFacturaConOverlay = function (event) {
		var ok = true;
		if (typeof validarPadronOperacionAntesSubmitForm === 'function') {
			ok = validarPadronOperacionAntesSubmitForm(event) !== false;
		}
		if (!ok) {
			if (window.FacturaProcesoOverlay) {
				window.FacturaProcesoOverlay.detener();
			}
			return false;
		}
		if (event && typeof event.preventDefault === 'function') {
			event.preventDefault();
		}
		enviarComprobanteFacturaMostradorAjax();
		return false;
	};

	window.enviarComprobanteFacturaMostradorAjax = enviarComprobanteFacturaMostradorAjax;

	function guardarPreferenciasFactura() {
		if (window.PreferenciasFacturacionUsuario) {
			window.PreferenciasFacturacionUsuario.guardar();
			return;
		}
		if (!window.FACTURA_URLS || !window.FACTURA_URLS.preferencias) {
			return;
		}
		$.post(window.FACTURA_URLS.preferencias, {
			_token: $('meta[name="csrf-token"]').attr('content'),
			tipotransaccion_id: $('#tipotransaccion_id').val() || '',
			puntoventa_id: $('#puntoventa_id').val() || '',
			puntoventaremito_id: $('#puntoventaremito_id').val() || ''
		});
	}

	// Realiza submit
    function subm()
	{
		if (typeof window.formularioVentasBloqueadoPorPadron === 'function' && window.formularioVentasBloqueadoPorPadron()) {
			if (typeof window.notificarBloqueoPadronCliente === 'function') {
				window.notificarBloqueoPadronCliente('Problemas en ARCA: no puede guardar la factura con este cliente.');
			} else {
				alert('Problemas en ARCA: no puede guardar la factura con este cliente.');
			}
			return;
		}

		if (typeof window.clienteEsDespacho === 'function' && window.clienteEsDespacho($('#cliente_id').val())) {
			alert(typeof window.mensajeClienteDespachoNoFacturable === 'function'
				? window.mensajeClienteDespachoNoFacturable()
				: 'El cliente DESPACHO no se factura. Use Transferir al despacho.');
			return;
		}

        var tipotransaccion_id = $("#tipotransaccion_id").val();
		var puntoventa_id = $("#puntoventa_id").val();

        if (tipotransaccion_id == '')
        {
            alert('Debe elegir un tipo de transacción');
            return;
        }

		if (puntoventa_id == '' || puntoventa_id == null)
		{
			alert('Debe elegir un punto de venta');
			return;
		}

		if (window.FL_FACTURA_LAYOUT_PEDIDO && typeof sincronizarCantidadesItemsFactura === 'function') {
			sincronizarCantidadesItemsFactura();
		}
        // Controla datos correctos
		var item = 0;
		var flError = false;
		var selectorRenglones = window.FL_FACTURA_LAYOUT_PEDIDO
			? '#tbody-tabla tr.item-pedido'
			: '#tbody-tabla tr.item-factura, #tbody-tabla tr.item-pedido';

		$(selectorRenglones).each(function(index) {
			item = item + 1;
			var $tr = $(this);
			var articulo_id = $tr.find('.articulo_id').val();
			var codigo = $tr.find('.codigoarticulo').val();
			var conceptoVentaId = $tr.find('.concepto_venta_id').val();
			var conceptoCabecera = $('#concepto_venta_comprobante_id').val();
			var exigeConcepto = typeof window.facturaConceptoObligatorioSinArticulo === 'function'
				&& window.facturaConceptoObligatorioSinArticulo();

			if (!articulo_id && exigeConcepto && !conceptoVentaId && !conceptoCabecera)
			{
				alert('El concepto es obligatorio en el ítem ' + item + ' porque no tiene artículo (WSMTXCA).');
				flError = true;
				return false;
			}

			if (!articulo_id && !codigo && !conceptoVentaId && !conceptoCabecera)
			{
				alert('Código de artículo nulo en ítem ' + item);
				flError = true;
				return false;
			}

			var cantidad = $tr.find('.cantidad').val();
			if (window.FL_FACTURA_LAYOUT_PEDIDO) {
				cantidad = $tr.find('.kilo').val();
			}

			if (cantidad == null || cantidad === '')
			{
				alert('Cantidad nula en ítem ' + item);
				flError = true;
				return false;
			}

			var listaprecioId = $tr.find('.listaprecio_id').val();
			if (articulo_id && typeof window.listaprecioIdEsValidoLineaVentas === 'function'
				&& !window.listaprecioIdEsValidoLineaVentas(listaprecioId)) {
				alert(window.mensajeErrorListaprecioArticuloVentas(codigo, item));
				flError = true;
				return false;
			}
		});

		if (!flError) {
			enviarComprobanteFacturaMostradorAjax();
		}
	}

	function completarCliente_Entrega(cliente_id){
        var loc_id;
		var lugarentrega = $("#lugarentrega").val();

        $.get(carpetaBase+'/ventas/leercliente_entrega/'+cliente_id, function(data){
            var entr = $.map(data, function(value, index){
                return [value];
            });
            $("#cliente_entrega_id").empty();
            $("#cliente_entrega_id").append('<option value=""></option>');
            fl_tiene_entrega = false;
            $.each(entr, function(index,value){
				if (value.nombre != lugarentrega)
				{
                	$("#cliente_entrega_id").append('<option value="'+value.id+'">'+value.nombre+'</option>');
				}
				else
				{
                	$("#cliente_entrega_id").append('<option value="'+value.id+'" selected>'+value.nombre+'</option>');
				}
                fl_tiene_entrega = true;
            });
            if (fl_tiene_entrega)
            {
              $("#divcodigoentrega").show();
              $("#divlugar").hide();
            }
            else
            {
              $("#divcodigoentrega").hide();
              $("#divlugar").show();
            }
        });
        setTimeout(() => {
        }, 3000);
    }

	function resolverVendedorIdCliente(data) {
		if (!data) {
			return '';
		}
		var vendedorId = data.vendedor_id;
		if ((vendedorId == null || vendedorId === '') && data.vendedores && data.vendedores.id != null) {
			vendedorId = data.vendedores.id;
		}
		if (vendedorId == null || vendedorId === '') {
			vendedorId = 1;
		}
		return String(vendedorId);
	}

	function aplicarVendedorDesdeCliente(data) {
		var $sel = $('#vendedor_id');
		if (!$sel.length) {
			return;
		}
		var vendedorId = resolverVendedorIdCliente(data);
		if (!vendedorId) {
			return;
		}
		var nombre = (data.vendedores && data.vendedores.nombre)
			? data.vendedores.nombre
			: ('Vendedor ' + vendedorId);
		var codigo = (data.vendedores && data.vendedores.codigo)
			? data.vendedores.codigo
			: '';
		if ($sel.is('select')) {
			if (!$sel.find('option[value="' + vendedorId + '"]').length) {
				$sel.append($('<option></option>').attr('value', vendedorId).text(nombre));
			}
			$sel.val(vendedorId);
			return;
		}
		$sel.val(vendedorId);
		$('#codigovendedor').val(codigo);
		$('#nombrevendedor').val(nombre);
		if (typeof actualizarLinkEditarVendedor === 'function') {
			actualizarLinkEditarVendedor($('.tm-vendedor-campo').first(), vendedorId);
		}
	}

	window.aplicarVendedorDesdeCliente = aplicarVendedorDesdeCliente;

	function actualizarLinkEditarClienteFactura(clienteId) {
		var $link = $('#link-editar-cliente-factura');
		if (!$link.length) {
			return;
		}
		var id = parseInt(clienteId, 10) || 0;
		if (id > 0) {
			$link.attr('href', carpetaBase + '/ventas/cliente/' + id + '/editar?origen=modal_consulta&vista=consulta').removeClass('d-none');
		} else {
			$link.attr('href', '#').addClass('d-none');
		}
	}

   	function asignaDatosCliente(cliente_id, flCambioCliente){
		if (!cliente_id || !$.isNumeric(cliente_id) || parseInt(cliente_id, 10) <= 0) {
			return;
		}
		actualizarLinkEditarClienteFactura(cliente_id);

        $.get(carpetaBase+'/ventas/leercliente/'+cliente_id, function(data){
            const transporte_id = data.transporte_id == null ? 0 : data.transporte_id;
            const condicionventa_id = data.condicionventa_id;
            const descuento = data.descuento;
			const tiposuspension_id = data.tiposuspension_id;
			const transporteCliente = data.transportes || null;
			const codigotransporte = transporteCliente && transporteCliente.codigo ? transporteCliente.codigo : '';
			const nombretransporte = transporteCliente && transporteCliente.nombre ? transporteCliente.nombre : '';

			if (flCambioCliente)
			{
				aplicarVendedorDesdeCliente(data);
				$('#transporte_id').val(transporte_id);
				if ($('#codigotransporte').length) {
					$('#codigotransporte').val(codigotransporte);
					$('#nombretransporte').val(nombretransporte);
				}
				$('#condicionventa_id').val(condicionventa_id);
				$('#descuentopie').val(descuento);
				descuentoCliente = $('#descuentopie').val();
				if (typeof window.actualizarAvisoDepositoFacturacion === 'function') {
					window.actualizarAvisoDepositoFacturacion(transporte_id, { sincronizarCampo: true });
				}
			}
			$('#tiposuspension_id').val(tiposuspension_id);
		});
		
        setTimeout(() => {
			
        }, 3000);
    }

    function muestraTipoSuspension()
    {
		var tiposuspensioncliente_query = $("#tiposuspensioncliente_query").val();
        var tiposuspension_id = $("#tiposuspension_id").val();
		
        if (tiposuspension_id > 0)
        {
            var tbl_tiposuspension = JSON.parse(tiposuspensioncliente_query);

            var nombre = "";
            $.each(tbl_tiposuspension, function(index,value){
                if (value.id == tiposuspension_id)
                    nombre = value.nombre;
            });

            $('#nombretiposuspension').text("SUSPENDIDO: "+nombre);
        }
        else
        {
            $('#nombretiposuspension').text('');
        }
    }

    function completarCombinaciones(articulo, combinacion_id, flsinfiltro){
        var comb_id;
		var articulo_id = $(articulo).val();
		var fl_todas_las_combinaciones = $(articulo).parents("tr").find('input:checkbox[class=checkCombinacion]:checked').val();
		var fl_todos_los_articulos = $(articulo).parents("tr").find('input:checkbox[class=checkSinFiltro]:checked').val();

		// Si marca boton de todas las combinaciones trae sin filtrar las activas o esta leyendo todos los articulos sin filtrar
		if (fl_todas_las_combinaciones == 'on' || fl_todos_los_articulos == 'on' || flsinfiltro)
			var url_comb = carpetaBase+'/stock/leercombinaciones/';
		else
			var url_comb = carpetaBase+'/stock/leercombinacionesactivas/';

        $.get(url_comb+articulo_id, function(data){
            var comb = $.map(data, function(value, index){
                return [value];
            });
            $(articulo).parents("tr").find('.combinacion').empty();
            $(articulo).parents("tr").find('.combinacion').append('<option value=""></option>');
            $.each(comb, function(index,value){
				if (value.id == combinacion_id)
                	$(articulo).parents("tr").find('.combinacion').append('<option value="'+value.id+'" selected>'+value.codigo+'-'+value.nombre+'</option>');
				else
                	$(articulo).parents("tr").find('.combinacion').append('<option value="'+value.id+'">'+value.codigo+'-'+value.nombre+'</option>');
            });
        });
        setTimeout(() => {
                var comb_id = $("#combinacion_id").val();
                if (comb_id != undefined) {
                    completarModulos(comb_id, 0);
                }
        }, 3000);
    }

    function completarModulos(articulo, modulo_id){
        var comb_id;
		var eligioModulo = false;
		var articulo_id = $(articulo).val();
		var flTieneModuloAbierto = false;
        $.get(carpetaBase+'/stock/leermodulos/'+articulo_id+'/'+modulo_id, function(data){
            var mod = $.map(data, function(value, index){
                return [value];
            });
            $(articulo).parents("tr").find('.modulo').empty();
            $(articulo).parents("tr").find('.modulo').append('<option value=""></option>');
			flTieneModuloAbierto = false;
            $.each(mod, function(index,value){
			  	if (value.id == 30)
				  	flTieneModuloAbierto = true;

				if (value.id == modulo_id)
				{
                	$(articulo).parents("tr").find('.modulo').append('<option value="'+value.id+'" selected>'+value.nombre+'</option>');
					eligioModulo = true;
				}
				else
                	$(articulo).parents("tr").find('.modulo').append('<option value="'+value.id+'">'+value.nombre+'</option>');
            });

			// Agrega modulo abierto
			if (!flTieneModuloAbierto)
            	$(articulo).parents("tr").find('.modulo').append('<option value="'+'30'+'">'+'Abierto'+'</option>');
        });
    }

	function completarTalles(modulo_id, ptrcheck, medidas, cantidades, precios)
	{
		talles_txt = "";
		medidas_txt = "";
		precios_txt = "";
		tallesid_txt = "";
		nombre_modulo = "";

		// Lee talles del modulo
        $.get(carpetaBase+'/stock/leertalles/'+modulo_id, function(data){
			var flEncontro, flHayMedidas;

           	var tall = $.map(data, function(value, index){
               	return [value];
           	});
			talles_txt = "<table class='table-bordered table-striped'><tr>";
			medidas_txt = "<tr>";
			precios_txt = "<tr>";
			tallesid_txt = "<tr>";

			// Arma variables modal
			cantidadmodal_txt = " autofocus ";
           	$.each(tall, function(index,value){
				nombre_modulo = value.nombre;
				for (var t in value.talles) {
					flEncontro = false;
					flHayMedidas = false;
					
					for (let s in medidas) 
					{
						flHayMedidas = true;
						
						if (parseFloat(value.talles[t].id) === parseFloat(medidas[s]))
						{
							var cant = parseFloat(cantidades[s]);
							var prec = parseFloat(precios[s]);
							
							// Calcula modulo actual
							if (value.talles[t].pivot.cantidad != 0) 
							{
								cantidades_por_modulo[s] = value.talles[t].pivot.cantidad;
							
								modulo_actual = cant / value.talles[t].pivot.cantidad;
							}

							agregaMedida(value.talles[t].nombre, cant, prec, value.talles[t].id);
							flEncontro = true;
							break;
						}
					}
					if (!flEncontro)
					{
						if (flHayMedidas)
							agregaMedida(value.talles[t].nombre, '', 0, value.talles[t].id);
						else
							agregaMedida(value.talles[t].nombre, (value.talles[t].pivot.cantidad == 0 ? '' : value.talles[t].pivot.cantidad), 0, value.talles[t].id);
					}
				}
			});
			talles_txt = talles_txt + "</tr>";
			medidas_txt = medidas_txt + "</tr>";
			precios_txt = precios_txt + "</tr>";
			tallesid_txt = tallesid_txt + "</tr>";

			if (flFactura)
			{
				tallesfactura_txt[offFactura] = talles_txt;
				medidasfactura_txt[offFactura] = medidas_txt;
				preciosfactura_txt[offFactura] = precios_txt;
				tallesidfactura_txt[offFactura] = tallesid_txt;

				let descripcion_art = $(ptrcheck).parents("tr").find(".articulo option:selected").text();
				let nombre_comb = $(ptrcheck).parents("tr").find(".desc_combinacion").val();
				titulofactura_txt[offFactura] = descripcion_art+" "+nombre_comb;

				offFactura = offFactura + 1;
			}
		});
	}

	function agregaMedida(Ptalle, Pcant, Pprec, Ptalle_id)
	{
		let nombre = "";

    	talles_txt = talles_txt + "<th><input name='medidasportalles[]' class='medidasportalles' style='width:30px; text-align:center; background-color   : #D2D8DC;' type='text' readonly value='"+Ptalle+"'></input></th>";

		if (!flAnulacionItem)
			nombre = "cantidadesportalles";
		else
			nombre = "cantidadesportallesa";
		
		medidas_txt = medidas_txt + "<th><input name='"+nombre+"[]' "+cantidadmodal_txt+" class='"+nombre+"' style='width:30px;' type='text' value='"+Math.abs(Pcant)+"'></input></th>";

    	precios_txt = precios_txt + "<th><input name='preciosportalles[]' class='preciosportalles' type='hidden' value='"+Pprec+"'></input></th>";
    	tallesid_txt = tallesid_txt + "<th><input name='tallesid[]' class='tallesid' type='hidden' value='"+Ptalle_id+"'></input></th>";
		cantidadmodal_txt = "";
	}

	function asignaPrecio(Particulo_id, Ptalle_id)
	{
		// Lee talles del modulo
        $.get(carpetaBase+'/stock/asignaprecio/'+Particulo_id+'/'+Ptalle_id, function(data){
           	var prec = $.map(data, function(value, index){
               	return [value];
           	});
			dpr=[];
			dlp=[];
			dii=[];
			dmo=[];
           	$.each(prec, function(index,value){
				dpr.push(value.precio);
				dlp.push(value.listaprecio_id);
				dii.push(value.incluyeimpuesto);
				dmo.push(value.moneda_id);
			});
		});
        setTimeout(() => {
			return(precio);
        }, 300);
	}

    $(function () {
		var articulo_id;
		var combinacion_id;
		var modulo_id;

		// Completa combinaciones y modulos al abrir pedido
		$("#tbody-tabla .articulo").each(function(index) {
			var articulo = $(this);
			var combinacion = $(this).parents("tr").find(".combinacion").val();
			var combinacion_id = $(this).parents("tr").find(".combinacion_id_previa").val();
			var modulo_id = $(this).parents("tr").find(".modulo_id_previa").val();

        	completarCombinaciones(articulo, combinacion_id, true);
        	completarModulos(articulo, modulo_id);
		});

		flCrear = document.getElementById("crear");
		flModificaAsiento = false;

		// Completa actividad
		let actividad_arca_id = $("#actividad_arcadefault_id").val();

		if (actividad_arca_id > 0)
		{
			$("#actividad_arca_id").val(actividad_arca_id);

			$('#actividad_arca_id').attr('readonly', true);
		}
		else
			$('#actividad_arca_id').attr('readonly', false);

		// Marca items como facturados, completa combinaciones y modulos al abrir pedido
		activa_eventos(true);
		TotalCantidadFactura();

		// activa variables para facturar
		let sel_puntoventa = JSON.parse(document.querySelector('#datosfactura').dataset.puntoventa);
		let sel_puntoventaremito = JSON.parse(document.querySelector('#datosfactura').dataset.puntoventa);
		let selectPuntoVenta = $('#puntoventa_id');
		let selectPuntoVentaRemito = $('#puntoventaremito_id');
		let puntoVentaDefault = $('#puntoventadefault_id').val();
		let puntoVentaRemitoDefault = $('#puntoventaremitodefault_id').val();
		let sel_tipotransaccion = JSON.parse(document.querySelector('#datosfactura').dataset.tipotransaccion);
		let selectTipoTransaccion = $('#tipotransaccion_id');
		let tipoTransaccionDefault = $('#tipotransacciondefault_id').val();

		if (document.querySelector('#datosfactura').dataset.incoterm !== '')
		{
			var sel_incoterm = JSON.parse(document.querySelector('#datosfactura').dataset.incoterm);
			var selectIncoterm = $('#incoterm_id');
		}
			
		if (document.querySelector('#datosfactura').dataset.formapago !== '')
		{
			var sel_formapago = JSON.parse(document.querySelector('#datosfactura').dataset.formapago);
			var selectFormapago = $('#formapago_id');
		}
	
		// Arma select de puntos de venta
		selectPuntoVenta.empty();
		selectPuntoVenta.append('<option value="">-- Seleccionar punto de venta --</option>');
		$.each(sel_puntoventa, function(obj, item) {
			op = window.PreferenciasFacturacionUsuario.opcionSelected(puntoVentaDefault, item.id);
			selectPuntoVenta.append('<option value="' + item.id + '"'+op+'>' + item.codigo + '-' + item.nombre + '</option>');
		});

		if (puntoVentaDefault) {
			selectPuntoVenta.val(puntoVentaDefault);
			leePuntoVenta(puntoVentaDefault);
			$.get(carpetaBase + '/ventas/leeunpuntoventa/' + puntoVentaDefault, function (data) {
				$('#actividad_arca_id').val(data.actividad_arca_id);
				$('#actividad_arca_id').attr('readonly', data.actividad_arca_id > 0);
				sincronizarEmpresaDesdePuntoVenta(data, true);
			});
		}

		// Arma select de puntos de venta del remito
		selectPuntoVentaRemito.empty();
		selectPuntoVentaRemito.append('<option value="">-- Seleccionar punto de venta --</option>');
		$.each(sel_puntoventaremito, function(obj, item) {
			op = window.PreferenciasFacturacionUsuario.opcionSelected(puntoVentaRemitoDefault, item.id);
			selectPuntoVentaRemito.append('<option value="' + item.id + '"'+op+'>' + item.codigo + '-' + item.nombre + '</option>');
		});

		// Arma select de incoterms
		if (document.querySelector('#datosfactura').dataset.incoterm !== '')
		{
			selectIncoterm.empty();
			selectIncoterm.append('<option value="">-- Seleccionar incoterm --</option>');
			$.each(sel_incoterm, function(obj, item) {
				selectIncoterm.append('<option value="' + item.id + '">' + item.nombre + '</option>');
			});
		}

		// Arma select de formas de pago
		if (document.querySelector('#datosfactura').dataset.formapago !== '')
		{
			selectFormapago.empty();
			selectFormapago.append('<option value="">-- Seleccionar forma de pago --</option>');
			$.each(sel_formapago, function(obj, item) {
				selectFormapago.append('<option value="' + item.id + '">' + item.nombre + '</option>');
			});
		}

		$('#puntoventa_id').on('change', function () {
			let puntoventa_id = $('#puntoventa_id').val();
	
			// Lee punto de venta si es de exportacion
			leePuntoVenta(puntoventa_id);
			guardarPreferenciasFactura();
		});

		$('#tipotransaccion_id').on('change', function () {
			guardarPreferenciasFactura();
			if (typeof window.sincronizarConceptoVentaDesdeTipo === 'function') {
				window.sincronizarConceptoVentaDesdeTipo();
			}
		});
		$('#puntoventa_id').on('change.conceptoVentaMtxca', function () {
			if (typeof window.actualizarAvisoConceptoVentaFactura === 'function') {
				window.actualizarAvisoConceptoVentaFactura();
			}
		});
		var esAltaFactura = !$.trim($('#codigofactura').val() || '');
		if (esAltaFactura && typeof window.sincronizarConceptoVentaDesdeTipo === 'function') {
			window.sincronizarConceptoVentaDesdeTipo();
		} else if (typeof window.actualizarAvisoConceptoVentaFactura === 'function') {
			window.actualizarAvisoConceptoVentaFactura();
		}

		$("#botonform1").click(function(e){
			if (e && typeof e.preventDefault === 'function') {
				e.preventDefault();
			}
			$(".form1").show();
			$(".formasientoexterno").hide();
			$("#botonform1").addClass("active");
			$("#botonform2").removeClass("active");
        });				
		$("#botonform2").click(function(e){
			if (e && typeof e.preventDefault === 'function') {
				e.preventDefault();
			}
			$(".form1").hide();
			$(".formasientoexterno").show();
			$("#botonform2").addClass("active");
			$("#botonform1").removeClass("active");

			$("#titulo").html("");
			$("#titulo").html("<span class='fa fa-cash-register'></span> Principal");
        });		
	});

	function esTeclaF1Factura(e) {
		return e && (e.key === 'F1' || e.code === 'F1' || e.keyCode === 112);
	}

	$(document)
		.off('keydown.facturaF1Consulta', '.codigocliente, #codigocliente, .codigovendedor, #codigovendedor')
		.on('keydown.facturaF1Consulta', '.codigocliente, #codigocliente, .codigovendedor, #codigovendedor', function (e) {
			if (!esTeclaF1Factura(e)) {
				return;
			}
			e.preventDefault();
			e.stopPropagation();
			var $campo = $(this);
			if ($campo.is('.codigocliente, #codigocliente')) {
				$campo.closest('.tm-cliente-campo, .form-group').find('.consultacliente').first().trigger('click');
				return;
			}
			$campo.closest('.tm-vendedor-campo, .form-group').find('.consultavendedor').first().trigger('click');
		});

	function sincronizarEmpresaDesdePuntoVenta(data, omitirLimpiarDeposito)
	{
		var $emp = $('#empresa_id');
		if (!$emp.length || !data) {
			return;
		}
		var nueva = String(data.empresa_id || '').trim();
		var actual = String($emp.val() || '').trim();
		if (nueva === actual) {
			return;
		}
		if (omitirLimpiarDeposito) {
			window._omitirLimpiarDepositoAlCambiarEmpresa = true;
		}
		$emp.val(nueva).trigger('change');
		window._omitirLimpiarDepositoAlCambiarEmpresa = false;
	}

	function leePuntoVenta(puntoventa_id)
	{
		var listarUri = carpetaBase+"/ventas/chequeapuntoventa/"+puntoventa_id;

		$.get(listarUri, function(data){
			
			if (data.modofacturacion == 'E')
			{
				$('#div_formapago').show();
				$('#div_mercaderia').show();
				$('#div_incoterm').show();
				$('#div_leyendaexportacion').show();
			}
			else
			{
				$('#div_formapago').hide();
				$('#div_mercaderia').hide();
				$('#div_incoterm').hide();
				$('#div_leyendaexportacion').hide();
			}
		});
	}

	function marcaItemFacturado()
	{
		// Completa combinaciones y modulos al abrir pedido
		$("#tbody-tabla .otcodigo").each(function(index) {
			let ordentrabajo = $(this).val();
			let ot = this;
			let tilde = $(this).parents("tr").find(".checkImpresion");
			
			// Busca si tiene factura asociada
			var listarUri = carpetaBase+"/ventas/estadoot/"+ordentrabajo;
		
			$.get(listarUri, function(data){
				
				if (data.numerofactura != -1 && data.numerofactura != -2)
				{
					$(ot).css("background-color","red");
						$(ot).css("font-weight","900");

					$(tilde).prop("checked",false);
				}
			});
		});
	}

	function activa_eventos(flInicio)
	{
		if (window.FL_FACTURA_LAYOUT_PEDIDO && typeof activa_eventosFacturaBierzo === 'function') {
			activa_eventosFacturaBierzo(flInicio);
			return;
		}

		// Si esta agregando items desactiva los eventos
		if (!flInicio)
		{
			$('.articulo').off('click');
			$('.articulo').off('change');
        	$(".modulo").off('change');
			$(".checkImpresion").off('change');
        	$(".cantidad").off('click keydown');
    		$('.consultaSKU').off('click');
			$('#aceptaarticuloxskuModal').off('click');
			$('#medidasModal').off('show.bs.modal');
			$('#cierraModal').off('click');
			$('#aceptaModal').off('click');
			$('#medidasModal').off('hidden.bs.modal');
        	$('#aceptaOrdenTrabajoModal').off('click');
			$('#lote_id').off('change');
			$('#puntoventa_id').off('change');
			$('.precio').off('change');
			$('.cantidad').off('change');
			$('.descuento').off('change');
			$(document).off('change', '.desc_combinacion');
			$(document).off('change', '.desc_modulo');
			$(document).off('change', '.cantidadesportalles');
		}

		// Activa eventos de consulta
		activa_eventos_consultacliente();
		activa_eventos_consultaarticulo();
		if (typeof activa_eventos_consultadeposito === 'function') {
			activa_eventos_consultadeposito();
		}
		if (typeof activa_eventos_consultavendedor === 'function') {
			activa_eventos_consultavendedor();
		}
		if (typeof activa_eventos_consultatransporte === 'function') {
			activa_eventos_consultatransporte();
		}
		$('#tm_deposito_factura .consultadeposito').addClass('factura-carga-bloqueable');

		$('.articulo').on('click', function (event) {

			armaSelectArticulo(this, this, 1);

		});

		$('.precio').on('change', function (event) {
			calculaFactura();
		});

		$('.cantidad').on('change', function (event) {
			calculaFactura();
		});

		$('.descuento').on('change', function (event) {
			calculaFactura();
		});

		$('#puntoventa_id').on('change', function (event) {
			event.preventDefault();
			var puntoventa_id = $(this).val();

			var listarUri = carpetaBase+"/ventas/leeunpuntoventa/"+puntoventa_id;

			$.get(listarUri, function(data){
				$("#actividad_arca_id").val(data.actividad_arca_id);

				if (data.actividad_arca_id > 0)
					$('#actividad_arca_id').attr('readonly', true);
				else
					$('#actividad_arca_id').attr('readonly', false);

				sincronizarEmpresaDesdePuntoVenta(data, false);
			});
        });
		
		$('.articulo').on('change', function (event) {
			event.preventDefault();
			var articulo = $(this);
			var articulo_ant = $(this).parents("tr").find(".articulo_id_previo").val();
			var articulo_nuevo = articulo.val();

			if (articulo_nuevo != articulo_ant)
			{
            	completarCombinaciones(articulo, 0, false);
            	completarModulos(articulo, 0);

				//* Asigna nuevo articulo
				$(this).parents("tr").find(".articulo_id_previo").val(articulo_nuevo);
			}
        });
		
    	$('.consultaSKU').on('click', function (event) {
        	articuloxsku = $(this).parents("tr").find(".articulo_id");
			descripcionxsku = $(this).parents("tr").find(".descripcion");
			skuxsku = $(this).parents("tr").find(".sku");

			$("#consulta").focus();

        	// Abre modal de consulta
        	$("#consultaarticuloModal").modal('show');
	
			var selectxsku = $("#articuloxsku_id");
        	armaSelectArticulo(selectxsku, articuloxsku, 2);
    	});

    	$('#aceptaconsultaarticuloModal').on('click', function () {
        	$('#consultaarticuloModal').modal('hide');
    	});

		$(document).on('click', '.eligeconsulta', function () {
			var seleccion = $(this).parents("tr").children().html();
			var descripcion = $(this).parents("tr").find(".descripcion").html();
			var sku = $(this).parents("tr").find(".sku").html();
		
			// Asigna a grilla los valores devueltos por consulta
			$(articuloxsku).val(seleccion);
			$(descripcionxsku).val(descripcion);
			$(skuxsku).val(sku);

			completarCombinaciones(articuloxsku, 0, false);
			completarModulos(articuloxsku, 0);
		
			//* Asigna nuevo articulo
			$(articuloxsku).parents("tr").find(".articulo_id_previo").val($(articuloxsku).val());
		
			$('#consultaarticuloModal').modal('hide');
		});

        $(".modulo").on('change', function() {
			modulo_id = $(this).parents("tr").find(".modulo").val();
		  	moduloElegido_id = modulo_id;
			
			// Blanquea medidas
			$(this).parents("tr").find(".medidas").val("");
		});

		// Con click sobre cantidad abre modal de medidas
        $(".cantidad").on('click keydown', function() {
			cantidad = $(this);

			articulo_id = $(this).parents("tr").find(".articulo").val();
			descripcion_articulo = $(this).parents("tr").find(".articulo option:selected").text();
			modulo_id = $(this).parents("tr").find(".modulo").val();
			combinacion_id = $(this).parents("tr").find(".combinacion").val();
			nombre_combinacion = $(this).parents("tr").find(".combinacion option:selected").text();

			// Lee tabla de medidas
			var val_medida = $(this).parents("tr").find(".medidas").val();

			medidas=[];
			cantidades=[];
			precios=[];

			if (val_medida != '')
			{
				var tbl_medidas = JSON.parse(val_medida);

           		$.each(tbl_medidas, function(index,value){
					medidas.push(value.talle_id);
					cantidades.push(value.cantidad);
					precios.push(value.precio);
				});
			}

			completarTalles(modulo_id, 0, medidas, cantidades, precios);

        	setTimeout(() => {
				$("#medidasModal").modal('show');
			}, 300);
        });

		// Controla apertura modal de medidas
		$('#medidasModal').on('show.bs.modal', function (event) {
  			var modal = $(this);
			modalActivo = "medidasModal";

  			modal.find('.modal-title').text('Medidas item '+descripcion_articulo+' Combinacion '+nombre_combinacion+' Modulo '+nombre_modulo);
  			modal.find('#medidasModal').empty();
  			modal.find('#medidasModal').append(talles_txt+medidas_txt+precios_txt+tallesid_txt);
			sumaPares(modalActivo, 'cantidadesportalles');
			muestraTotalPares();
		});

		// Autofocus en modal de medidas
		$(document).on('shown.bs.modal', '.modal', function() {
		  	// Si es modulo manual hace foco en cantidades 
		  	if (moduloElegido_id == 30)
  				$(this).find('[autofocus]').focus();
			else
  				$("#cantmodulo").focus();

			var _cant = 1;

			if (modulo_actual != 1)
        		$("#cantmodulo").val(modulo_actual);
			else
				$("#cantmodulo").val(_cant);

        	$("#cantmodulo").off('change');

			$("#cantmodulo").on('change', function () {

				// Multiplica por la cantidad de modulos a cada cantidad por talle
				$("#medidasModal .cantidadesportalles").each(function(index) {
					var cantidad = $(this).val();
					var cantmodulo = $("#cantmodulo").val();
					
					if (cantidad != '')
					{
						if (modulo_actual > 0 && modulo_actual != cantmodulo)
						{
							var cantidad_base = parseFloat(cantidad) / modulo_actual;
							var nueva_cantidad = cantidad_base * parseFloat(cantmodulo);
						}
						else	
							var nueva_cantidad = arseFloat(cantidad)*parseFloat(cantmodulo);

				  		$(this).val(nueva_cantidad);
						sumaPares(modalActivo, 'cantidadesportalles');
						muestraTotalPares();
					}
				});

			});

		});

	  	// Cierra modal medidas 
		$('#cierraModal').on('click', function () {
		});

		// Acepta modal de medidas
		$('#aceptaModal').on('click', function () {
		  	let jsonObject = new Array();

			med = [];
			$(".medidasportalles").each(function() {
            	med.push($(this).val());
			});
			talleid = [];
			$(".tallesid").each(function() {
            	talleid.push($(this).val());
			});
			cant = [];
			$(".cantidadesportalles").each(function() {
            	cant.push($(this).val());
			});
        	prec = []
        	$(".preciosportalles").each(function(){
            	prec.push($(this).val());
        	});

			let jsonTallesId = JSON.stringify(talleid); 

			asignaPrecio(articulo_id, jsonTallesId);

			off = 0;
		    var flError = false;
        	setTimeout(() => {
			for (let i in med) 
			{
				if (cant[i] == '')
					cant[i] = 0;
			  	jsonObject.push({
					medida: med[i],
				  	cantidad: cant[i],
				  	precio: dpr[i],
				  	listaprecio: dlp[i],
				  	incluyeimpuesto: dii[i],
				  	moneda: dmo[i],
				  	talle_id: talleid[i]
				});
			  	// Valida cantidades que tengan precio
			    if (cant[i] > 0 && dpr[i] == 0)
			  	{
					flError = true;	  	
					// Pedido por gaby 27/6 porque todos los articulos de la expo no tienen precio
				    //alert('Medida '+med[i]+' Cantidad '+cant[i]+' No tiene precio asignado');
			  	}
				if (dpr[i] > 0)
					off = i;		
			}

			let jsonString = JSON.stringify(jsonObject); 

			// Asigna medidas, cantidades y precios
			$(cantidad).parents('tr').find('.medidas').val(jsonString);

			// Asigna variables de precio
			var pre = fNumero(dpr[off], 2);
			var lis = fNumero(dlp[off], 0);
			var inc = fNumero(dii[off], 0);
			var mon = fNumero(dmo[off], 0);
			if (pre === 'NaN' || pre < 0 || pre > 9999999999)
			  	pre = 0;
	
			$(cantidad).parents('tr').find('.precio').val(pre);
			$(cantidad).parents('tr').find('.listaprecio_id').val(lis);
			$(cantidad).parents('tr').find('.incluyeimpuesto').val(inc);
			$(cantidad).parents('tr').find('.moneda_id').val(mon);
	
        	}, 300);

			$('#medidasModal').modal('hide');

			// Asigna total de pares a la cantidad del item en el formulario
			sumaPares(modalActivo, 'cantidadesportalles');
			muestraTotalPares();
			$(cantidad).val(totPares);
			TotalCantidadFactura();
		});

		$('#medidasModal').on('hidden.bs.modal', function () {

			// Inicializa variables modal
			talles_txt = "";
			medidas_txt = "";
			precios_txt = "";
			tallesid_txt = "";
		});

		// Llena variable desc_combinacion
		$(document).on('change', '.desc_combinacion', function(event) {
     		$(this).val($(".combinacion option:selected").text());
		});
		// Llena variable desc_modulo
		$(document).on('change', '.desc_modulo', function(event) {
     		$(this).val($(".modulo option:selected").text());
		});
		$(document).on('change', '.cantidadesportalles', function(event) {
			sumaPares(modalActivo, 'cantidadesportalles');
			muestraTotalPares();
		});

        // Acepta modal OT
        $('#aceptaOrdenTrabajoModal').on('click', function () {
            var leyenda = $("#leyendaot").val();
            var checkotstock = $("input:checkbox[class=checkboxotstock]:checked").val();
			var ordentrabajo_stock_codigo = $("#ordentrabajo_stock_codigo").val();
			var articulo_id = $(pedido_combinacion).parents('tr').find('.articulo').val();
			var combinacion_id = $(pedido_combinacion).parents('tr').find('.combinacion').val();
			
			if (ordentrabajo_stock_codigo == '')
				ordentrabajo_stock_codigo = 0;

			if (ordentrabajo_stock_codigo > 0)
			{
				var listarUri = carpetaBase+"/ventas/controlaordentrabajostock/"+ordentrabajo_stock_codigo+"/"+articulo_id+"/"+combinacion_id;

				$.get(listarUri, function(data){
					if (data.estado != -1)
					{
						alert("Saldo lote "+ordentrabajo_stock_codigo+" "+data.saldo);

						$('#crearOrdenTrabajoModal').modal('hide');

						if (checkotstock == 'on')
							var listarUri = carpetaBase+"/ventas/guardaordenestrabajo/pedido/"+$(pedido_combinacion).val()+"/on/"+ordentrabajo_stock_codigo+'/'+leyenda;
						else
							var listarUri = carpetaBase+"/ventas/guardaordenestrabajo/pedido/"+$(pedido_combinacion).val()+"/off/"+ordentrabajo_stock_codigo+'/'+leyenda;
			
						$.get(listarUri, function(data){
							// Asigna ot id y nro. de orden 
							if (data.id > 0)
							{
								$(pedido_combinacion).parents('tr').find('.ot').val(data.id);
								$(pedido_combinacion).parents('tr').find('.otcodigo').val(data.nro_orden);
			
								alert("OT "+data.nro_orden+" creada con exito");
			
								$("#ordentrabajo_stock_codigo").val('');
							}
						});
					}
					else	
					{
						alert("Lote inexistente");
						return;
					}
				});
			}
			else
			{
				$('#crearOrdenTrabajoModal').modal('hide');

				if (checkotstock == 'on')
					var listarUri = carpetaBase+"/ventas/guardaordenestrabajo/pedido/"+$(pedido_combinacion).val()+"/on/"+ordentrabajo_stock_codigo+'/'+leyenda;
				else
					var listarUri = carpetaBase+"/ventas/guardaordenestrabajo/pedido/"+$(pedido_combinacion).val()+"/off/"+ordentrabajo_stock_codigo+'/'+leyenda;
	
				$.get(listarUri, function(data){
					// Asigna ot id y nro. de orden 
					if (data.id > 0)
					{
						$(pedido_combinacion).parents('tr').find('.ot').val(data.id);
						$(pedido_combinacion).parents('tr').find('.otcodigo').val(data.nro_orden);
	
						alert("OT "+data.nro_orden+" creada con exito");
	
						$("#ordentrabajo_stock_codigo").val('');
					}
				});
			}
		});

		// Asigna los lotes a cada item
		$('#lote_id').on('change', function () {
			var lote_id = $(this).val();
			
			$(".loteids").each(function() {
				$(this).val(lote_id);
			});
		});
	}

	function armaSelectArticulo(ptrselect, ptrarticulo, opdata)
	{
		var select = $(ptrselect);
      	var options = select.children();
		var articulo_id = $(ptrarticulo).val();
		var mventa_id = $('#mventa_id').val();
		var mventa_nombre = $("#mventa_id option:selected").text();
		var fl_todos_los_articulos = $(ptrarticulo).parents("tr").find('input:checkbox[class=checkSinFiltro]:checked').val();

		// elige articulos x descripcion o por sku
		if (fl_todos_los_articulos == 'on')
			var sel_articulos = JSON.parse(document.querySelector('#marca').dataset.articuloall);
		else
			var sel_articulos = JSON.parse(document.querySelector('#marca').dataset.articulo);

		if (opdata == 2)
		{
			sel_articulos.sort(function(a, b) {
    				var textA = a.sku;
    				var textB = b.sku;
    				return (textA < textB) ? -1 : (textA > textB) ? 1 : 0;
				});
		}

		select.empty();

		if (mventa_nombre === "-- Seleccionar marca --")
			select.append('<option value="">-- Articulos sin filrar --</option>');
		else
			select.append('<option value="">-- Articulos ' + mventa_nombre + ' --</option>');

		$.each(sel_articulos, function(obj, item) {
			if (articulo_id == item.id)
				op = 'selected="selected"';
			else
				op = '';
			if (mventa_id == undefined || mventa_id == '')
				select.append('<option value="' + item.id + '"'+op+'>' + (opdata == 2 ? item.sku + '-' + item.descripcion : item_descripcion + '-' + item.sku) + '</option>');
			else
			{
				if (item.mventa_id == mventa_id)
					select.append('<option value="' + item.id + '"'+op+'>' + (opdata == 2 ? item.sku + '-' + item.descripcion : item.descripcion + '-' + item.sku) + '</option>');
			}
		});

		if (articulo_id > 0)
		{
			select.value = articulo_id;

			select.children().filter(function(){
   				return this.text == articulo_id;
			}).prop('selected', true);
		}
	}

	function sumaPares(modalactivo, clasetalle)
	{
		totPares = 0;

		$("#"+modalactivo+" ."+clasetalle).each(function() {
			if (parseFloat($(this).val()) >= 1 && parseFloat($(this).val()) <= 999999)
				totPares += parseFloat($(this).val());
		});
	}

	function muestraTotalPares()
	{
		$("#totPares").val(totPares.toFixed(0));

		if (flFactura)
			$("#facturartotpares").val(totPares.toFixed(0));
	}

	function TotalCantidadFactura()
	{
		totPares = 0;

		$(".cantidad").each(function() {
			if ((parseFloat($(this).val()) >= 1 && parseFloat($(this).val()) <= 999999) ||
				(parseFloat($(this).val()) <= -1 && parseFloat($(this).val()) >= -999999))
				totPares += parseFloat($(this).val());
		});
		
		$("#TotalCantidadFactura").val(totPares.toFixed(0));
	}

	function sumaanulacionPares()
	{
		totPares = 0;

		$(".cantidadesportallesa").each(function() {
			if (parseFloat($(this).val()) >= 1 && parseFloat($(this).val()) <= 999999)
				totPares += parseFloat($(this).val());
		});
	}

	function muestraanulacionTotalPares()
	{
		$("#totanulacionPares").val(totPares.toFixed(0));
	}

	function muestrahistoriaTotalPares()
	{
		$("#tothistoriaPares").val(totPares.toFixed(0));
	}

	// Arma medidas y cantidades para modal
	function armaMedidas(item)
	{
		articulo_id = $(item).parents("tr").find(".articulo").val();
		descripcion_articulo = $(item).parents("tr").find(".articulo option:selected").text();
		modulo_id = $(item).parents("tr").find(".modulo").val();
		combinacion_id = $(item).parents("tr").find(".combinacion").val();
		nombre_combinacion = $(item).parents("tr").find(".combinacion option:selected").text();

		// Lee tabla de medidas
		var val_medida = $(item).parents("tr").find(".medidas").val();

		medidas=[];
		cantidades=[];
		precios=[];

		if (val_medida != '')
		{
			var tbl_medidas = JSON.parse(val_medida);

       		$.each(tbl_medidas, function(index,value){
				medidas.push(value.talle_id);
				cantidades.push(value.cantidad);
				precios.push(value.precio);
			});
		}

		completarTalles(modulo_id, 0, medidas, cantidades, precios);
	}

	// Manejo de grilla 

    $(function () {
        $('#agrega_renglon').on('click', agregaRenglon);
        $(document).on('click', '.eliminar', borraRenglon);
        $(document).on('click', '.anulaitem', anulaItem);
		$(document).on('click', '.historiaitem', historiaItem);

		// Si no tiene items agrega el primero
		if (!$('.item-factura').length && !$('.item-pedido').length) {
			agregaRenglon(null, { enfocarArticulo: false });
		}

		if (window.FL_FACTURA_LAYOUT_PEDIDO && typeof initFacturaEnterNavigation === 'function') {
			initFacturaEnterNavigation();
		}

		enfocarCodigoClienteFacturaAlCargar();
    });

	function enfocarCodigoClienteFacturaAlCargar() {
		window.setTimeout(function () {
			var el = document.getElementById('codigocliente');
			if (!el || el.disabled || el.readOnly || el.offsetParent === null) {
				return;
			}
			try {
				el.focus({ preventScroll: true });
			} catch (e) {
				el.focus();
			}
			if (typeof el.select === 'function') {
				el.select();
			}
		}, 150);
	}

    function agregaRenglon(event, opciones){
		if (typeof window.formularioVentasBloqueadoPorPadron === 'function' && window.formularioVentasBloqueadoPorPadron()) {
			if (event && typeof event.preventDefault === 'function') {
				event.preventDefault();
			}
			return;
		}

		if (event != undefined && typeof event.preventDefault === 'function')
        	event.preventDefault();
        var renglon = $('#template-renglon').html();

        $("#tbody-tabla").append(renglon);
        actualizaRenglones();

		activa_eventos(false);
		if (typeof window.aplicarConceptoVentaCabeceraALineasVacias === 'function') {
			window.aplicarConceptoVentaCabeceraALineasVacias();
		}
		if (typeof window.facturaActualizarColumnasGrilla === 'function') {
			window.facturaActualizarColumnasGrilla();
		}

		opciones = opciones || {};
		if (window.FL_FACTURA_LAYOUT_PEDIDO && opciones.enfocarArticulo !== false) {
			$('#itemspedido-table').find('tr').last().find('.codigoarticulo').focus();
		}
    }

	// Anula item 
    function anulaItem() {
       	codigoAnulacionOt = $(this).parents('tr').find('.otcodigo').val();
		idAnulacionOt = $(this).parents('tr').find('.ot').val();
		motivoAnulacionOt = $(this).parents('tr').find('.motivosanulacion').val();
		nombreClienteAnulacionOt = $(this).parents('tr').find('.clientesanulacion').val();
	  	itemAnulacionOt = $(this);

	  	itemAnulacion = $(this).parents('tr').find('.item');
	  	itemAnulacionId = $(this).parents('tr').find('.ids').val();
	  	botonAnulacion = $(this).parents('tr').find('.ianulaItem');

	  	flAnulacionItem = true;
	}

	// Controla apertura modal de anulacion
	$('#anulacionModal').on('show.bs.modal', function (event) {
  		var modal = $(this);
		modalActivo = "anulacionModal";

		if (botonAnulacion.hasClass('text-danger'))
	  	{
			var tituloModal = "Anulación item ";
  			modal.find('#aceptaanulacionModal').text("Anula item");
			$("#clientereasignado").hide();
			$("#motivocierrepedido").hide();
	  	}
		else
	  	{
			var tituloModal = "Recupera item ";
  			modal.find('#aceptaanulacionModal').text("Recupera item");
			$("#clientereasignado").show();
			$("#motivocierrepedido").show();
			$("#nombreclientereasignado").empty();
			$("#nombreclientereasignado").append(nombreClienteAnulacionOt);
			$("#nombremotivoanulacion").empty();
			$("#nombremotivoanulacion").append(motivoAnulacionOt);
		}

		$("#ordentrabajoanulacion").val(codigoAnulacionOt);
  		modal.find('.modal-title').text(tituloModal+descripcion_articulo+' Combinacion '+nombre_combinacion+' Modulo '+nombre_modulo);
  		modal.find('#anulacionModal').empty();
  		modal.find('#anulacionModal').append(talles_txt+medidas_txt+precios_txt+tallesid_txt);
		sumaanulacionPares();
		muestraanulacionTotalPares();
	});

	$('#cierraanulacionModal').on('click', function () {
	  	flAnulacionItem = false;
	});

	// Acepta modal de anulacion de item
	$('#aceptaanulacionModal').on('click', function () {
	  	let nuevoClienteId = $('#nuevocliente_id').val();
	  	let motivoAnulacionId = $('#motivoanulacion_id').val();

		if (motivoAnulacionId == '')
		{
			alert("Debe ingresar motivo");
			return;
		}
	  	flAnulacionItem = false;

		$('#anulacionModal').modal('hide');

	  	// Anula el item 
        $.get(carpetaBase+'/ventas/anularitempedido/'+itemAnulacionId+'/'+codigoAnulacionOt+'/'+motivoAnulacionId+'/'+nuevoClienteId, function(data){
            var ret = $.map(data, function(value, index){
                return [value];
            });
            $.each(ret, function(index,value){
			  	if (value == 'anulado')
			  	{
				  	$(itemAnulacion).css("background-color","red");
				  	$(itemAnulacion).css("font-weight","900");
				  	alert("Item anulado con exito");
					$(itemAnulacionOt).parents('tr').find('.motivosanulacion').val($("select[id=motivoanulacion_id] option:selected").text());
					$(itemAnulacionOt).parents('tr').find('.clientesanulacion').val($("select[id=nuevocliente_id] option:selected").text());
			  	}
			  	else
			  	{
				  	$(itemAnulacion).css("background-color","");
				  	$(itemAnulacion).css("font-weight","normal");
				  	alert("Item recuperado con exito");
			  	}
				// Cambia atributo del boton
				botonAnulacion.attr('class', botonAnulacion.hasClass('fa fa-window-close text-success ianulaItem') ? 
			  							'fa fa-window-close text-danger ianulaItem' : 
			  							'fa fa-window-close text-success ianulaItem' );
			});
        });
        setTimeout(() => {
        }, 3000);
	});

	$('#anulacionModal').on('hidden.bs.modal', function () {
		// Inicializa variables modal
		talles_txt = "";
		medidas_txt = "";
		precios_txt = "";
		tallesid_txt = "";
	
	});

	// Muestra historia del item
    function historiaItem() {
		itemAnulacionOt = $(this);
		codigoAnulacionOt = $(this).parents('tr').find('.otcodigo').val();
		flAnulacionItem = true;

		armaMedidas(itemAnulacionOt);

		setTimeout(() => {
			$("#historiaModal").modal('show');
		}, 300);
	}

	// Controla apertura modal de historia
	$('#historiaModal').on('show.bs.modal', function (event) {
		var modal = $(this);
		let tituloModal = "Historia Anulación Item ";

		$("#ordentrabajohistoria").val(codigoAnulacionOt);
		modal.find('.modal-title').text(tituloModal+descripcion_articulo+' Combinacion '+nombre_combinacion+' Modulo '+nombre_modulo);
		modal.find('#historiaModal').empty();
		modal.find('#historiaModal').append(talles_txt+medidas_txt+precios_txt+tallesid_txt);
		modal.find('#tbody-historia').empty();
		
		let historia = $(itemAnulacionOt).parents("tr").find('.historiaanulacion').val();
		historia = JSON.parse(historia);

		historia_txt = "";
		for (var i=0; i < historia.length; i++)
		{
			var motivo = historia[i];

			historia_txt += "<tr>";
			
			let fechaCierre = Date.parse(motivo.created_at);

			historia_txt += "<td>"+new Date(fechaCierre).toLocaleString("es-AR")+"</td>";
			historia_txt += "<td>"+motivo.motivoscierrepedido.nombre+"</td>";
			if (motivo.clientes != null)
				historia_txt += "<td>"+motivo.clientes.nombre+"</td>";
			else
				historia_txt += "<td></td>";
			historia_txt += "<td>"+motivo.observacion+"</td>";
			historia_txt += "<td>"+motivo.estado+"</td>";
			historia_txt += "</tr>"
		}

		modal.find('#tbody-historia').append(historia_txt);

		sumaanulacionPares();
		muestrahistoriaTotalPares();
	});

	$('#aceptahistoriaModal').on('click', function () {
		$('#historiaModal').modal('hide');
		flAnulacionItem = false;
	});

	$('#historiaModal').on('hidden.bs.modal', function () {
		// Inicializa variables modal
		talles_txt = "";
		medidas_txt = "";
		precios_txt = "";
		tallesid_txt = "";
	});

    function borraRenglon() {
        event.preventDefault();
		ordentrabajo = $(this).parents('tr').find('.otcodigo').val();
		loteStock = $(this).parents('tr').find('.loteids').val();
		articulo_id = $(this).parents("tr").find(".articulo").val();
		combinacion_id = $(this).parents("tr").find(".combinacion").val();

		// Busca si tiene factura asociada
		var flError = false;
		if (ordentrabajo > 0 && ordentrabajo < 999999)
		{
			var listarUri = carpetaBase+"/ventas/estadoot/"+ordentrabajo;

			$.get(listarUri, function(data){
								
				if (data.numerofactura != -1 && data.numerofactura != -2)
				{
					alert("OT ya facturada "+data.numerofactura);
					flError = true;
				}
			});
		}

		// Busca si tiene una OT asociada al lote
		if (!flError && loteStock >= 1)
		{
			var listarUri = carpetaBase+"/ventas/controlaordentrabajostock/"+loteStock+"/"+articulo_id+"/"+combinacion_id;

			$.get(listarUri, function(data){
				if (data.estado != -1 && data.estado != 1)
				{
					alert("No puede borrar lote de stock "+loteStock+" porque tiene movimientos asociados");
					flError = true;
				}
			});
		}

		setTimeout(() => {
			if (!flError)
			{
				if (confirm("¿Desea borrar renglon?"))
				{
					$(this).parents('tr').remove();
					actualizaRenglones();
					if (typeof window.facturaActualizarColumnasGrilla === 'function') {
						window.facturaActualizarColumnasGrilla();
					}
				}
				TotalCantidadFactura();
				calculaFactura();
			}
		}, 300);
	}

    function actualizaRenglones() {
        var item = 1;

        $("#tbody-tabla .item").each(function() {
            $(this).val(item++);
        });
    }

	function preparaPreFactura()
	{
        $("#tbody-tabla .checkImpresion").each(function() {
			$(this).show();
		});
		flFactura = false;
		$("#imprimePreFactura").show();
	}

	function imprimePreFactura()
	{
		let checksId=[];
		let itemId;
	  	let pedidoId = $("#pedidoid").val();

		$("input[type=checkbox]:checked").each(function(){
			
	  		itemId = $(this).parents('tr').find('.ids').val();
    		checksId.push(itemId);

		});

		let listarUri = carpetaBase+"/ventas/listarprefactura"+"/"+pedidoId+'/'+checksId;
		document.location.href= listarUri;
	}
	
	function generaAsientoContable()
	{
		let token = $("meta[name='csrf-token']").attr("content");
		let datosCuentasContables=[];
		var wrapper = $(".container-asiento");
		let empresa_id = $('#empresa_id').val();

		if (!empresa_id)
		{
			alert("Debe asignar empresa");
			return;
		}

		// Recorre los items de la factura


		// Genera datos de las cuentas de caja contables actualmente cargadas
		if (!flModificaAsiento)
		{
			$("#cuenta-asiento-table .item-cuenta-asiento").each(function() {
				cuentacontable_ids = $(this).find(".cuentacontable_id").val();
				centrocostoasiento_ids = $(this).find(".centrocostoasiento").val();
				monedaasiento_ids = $(this).find(".monedaasiento").val();
				debeasientos = $(this).find(".debeasiento").val();
				haberasientos = $(this).find(".haberasiento").val();
				cotizacionasientos = $(this).find(".cotizacionasiento").val();
				observacionasientos = $(this).find(".observacionasiento").val();
				carga_cuentacontable_manuales = $(this).find(".carga_cuentacontable_manual").val();

				datosCuentasContables.push({
					cuentacontable_ids,
					centrocostoasiento_ids,
					monedaasiento_ids,
					debeasientos,
					haberasientos,
					cotizacionasientos,
					observacionasientos,
					carga_cuentacontable_manuales
				});
			});
		}
		datosCuentasContables = JSON.stringify(datosCuentasContables);
		
		$.ajaxSetup({
			headers: {
				'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
			}
		});
		
		let url = carpetaBase+"/caja/generaasientocontable_ingresoegreso";

		$.ajax({
			type: "POST",
			url: url,
			data: {
				tipotransaccion_caja_id: tipotransaccion_caja_id,
				conceptogasto_id: conceptogasto_id,
				empresa_id: empresa_id,
				datoscaja: datosCuentasCaja,
				datoscontables: datosCuentasContables
			},
			success: function (data) {
				if (data.mensaje == 'ok')
				{
					$(wrapper).empty();

					$.each(data.asiento, function(index,value){
						let nombreCuentaContable = value.nombre;
						let cuentaContableId = value.cuentacontable_id;
						let cuentaContableCodigo = value.codigo;
						let centroCosto = value.centrocosto_id;
						let monedaId = value.moneda_id;
						let cotizacion = value.cotizacion;
						let debe = value.debe;
						let haber = value.haber;
						let observacion = value.observacion;
						let cargaCuentacontableManual = value.carga_cuentacontable_manual;

						$(wrapper).append('<tr class="item-cuenta-asiento">'+
							'<td>'+
								'<div class="form-group row" id="cuentacontable">'+
								'<input type="hidden" name="cuenta[]" class="form-control iicuentacontable" readonly value="{{ $loop->index+1 }}" />'+
								'<input type="hidden" class="cuentacontable_id" name="cuentacontable_ids[]" value="'+cuentaContableId+'" >'+
								'<input type="hidden" class="cuentacontable_id_previa" name="cuentacontable_id_previa[]" value="'+cuentaContableId+'" >'+
								'<button type="button" title="Consulta cuentas" style="padding:1;" class="btn-accion-tabla consultacuenta tooltipsC">'+
									'<i class="fa fa-search text-primary"></i>'+
								'</button>'+
								'<input type="text" style="WIDTH: 100px;HEIGHT: 38px" class="codigoasiento form-control" name="codigoasientos[]" value="'+cuentaContableCodigo+'" >'+
								'<input type="hidden" class="codigo_previo_cuentacontable" name="codigo_previo_cuentacontables[]" value="" >'+
								'<input type="hidden" class="carga_cuentacontable_manual" name="carga_cuentacontable_manuales[]" value="'+cargaCuentacontableManual+'" >'+
								'</div>'+
							'</td>'+				
                        	'<td>'+
                            	'<input type="text" style="WIDTH: 250px; HEIGHT: 38px" class="nombrecuentacontable form-control" name="nombrecuentacontables[]" value="'+nombreCuentaContable+'" readonly>'+
                        	'</td>'+
                        	'<td>'+
                            	'<select name="centrocostoasiento_ids[]" data-placeholder="Centro de costo" class="centrocostoasiento form-control" data-fouc>'+
                            	'</select>'+
                            	'<input type="hidden" class="centrocostoasiento_id_previo" name="centrocostoasiento_id_previo[]" value="'+centroCosto+'" >'+
                        	'</td>'+
							'<td>'+
								'<select name="monedaasiento_ids[]" data-placeholder="Moneda" class="monedaasiento form-control required" required data-fouc>'+
								'</select>'+
								'<input type="hidden" class="monedaasiento_id_previo" name="monedaasiento_id_previo[]" value="'+monedaId+'" >'+
							'</td>'+
							'<td>'+
								'<input type="number" name="debeasientos[]" class="form-control debeasiento" value="'+debe+'">'+
							'</td>'+
							'<td>'+
								'<input type="number" name="haberasientos[]" class="form-control haberasiento" value="'+haber+'">'+
							'</td>'+
							'<td>'+
								'<input type="number" name="cotizacionasientos[]" class="form-control cotizacionasiento" value="'+cotizacion+'">'+
							'</td>'+
							'<td>'+
								'<input type="text" name="observacionasientos[]" class="form-control observacionasiento" value="'+observacion+'">'+
							'</td>'+
							'<td>'+
								'<button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_cuenta_asiento tooltipsC">'+
									'<i class="fa fa-times-circle text-danger"></i>'+
								'</button>'+
							'</td>'+
						'</tr>'
						);
					});

					// Rellena select de moneda
					$("#cuenta-asiento-table .item-cuenta-asiento").each(function() {
						armaSelectMoneda(this);

						codigocontablexcodigo = $(this).find(".codigoasiento");

						leeCentroCostoAsiento(codigocontablexcodigo);
					});

					// Suma totales del asiento
					sumaMontoAsiento();

					totalDebeAsiento = $("#totaldebeasiento").val();
					totalHaberAsiento = $("#totalhaberasiento").val();
				}
				else
					alert("Error en generación del asiento contable");
			},
			error: function (r) {
				alert("Error grave en generación del asiento contable");
			}
		});
	}

	function armaSelectMoneda(ptrrenglon)
	{
		var select = $(ptrrenglon).find('.monedaasiento');
		var moneda_id = $(ptrrenglon).find('.monedaasiento_id_previo').val();

		select.empty();
		select.append('<option value="">-- Seleccionar --</option>');

		// Lee monedas
		//$.get('/configuracion/leermoneda', function(data){
        //    var monedas = $.map(data, function(value, index){
        //        return [value];
        //    });
        //    $.each(monedas, function(index,value){
		//		if (value.id != moneda_id)
        //       	select.append('<option value="'+value.id+'">'+value.abreviatura+'</option>');
		//		else
        //       	select.append('<option value="'+value.id+'" selected>'+value.abreviatura+'</option>');
        //    });
		//});

		idMoneda.forEach(function(moneda, indice, array) {
			if (moneda != moneda_id)
				select.append('<option value="'+moneda+'">'+descripcionMoneda[moneda]+'</option>');
			else
				select.append('<option value="'+moneda+'" selected>'+descripcionMoneda[moneda]+'</option>');
		});

		if (moneda_id > 0)
		{
			select.value = moneda_id;

			select.children().filter(function(){
   				return this.text == moneda_id;
			}).prop('selected', true);
		}
	}

	var calculaFacturaSeq = 0;
	var calculaFacturaXhr = null;

	function calculaFactura()
	{
		var clienteId = parseInt($('#cliente_id').val() || '0', 10);
		if (!(clienteId > 0)) {
			return;
		}

		if (window.FL_FACTURA_LAYOUT_PEDIDO && typeof sincronizarCantidadesItemsFactura === 'function') {
			sincronizarCantidadesItemsFactura();
		}

		calculaFacturaAjax();
	}

	function calculaFacturaAjax()
	{
		var seq = ++calculaFacturaSeq;

		if (calculaFacturaXhr && calculaFacturaXhr.readyState !== 4) {
			calculaFacturaXhr.abort();
		}

		const tiempoTranscurrido = Date.now();
		const hoy = new Date(tiempoTranscurrido);

		var token = $('#csrf_token').val();
		var puntoventa_id = $('#puntoventa_id').val();
		var descuentopie = $('#descuentopie').val();
		var descuentoimportepie = $('#descuentoimportepie').val();
		var fechafactura = $('#fechafactura').val();
		var formapago_id = $('#formapago_id').val();
		var cliente_id = $('#cliente_id').val();

		// Arma los items
		var parametros=new FormData($('#formgeneral')[0])

		$.ajaxSetup({
			headers: {
				'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
			},
			processData: false,  
    		contentType: false, 
		});
		
		let url = carpetaBase+"/ventas/calcula_factura_general";

		calculaFacturaXhr = $.ajax({
			type: "POST",
			url: url,
			data: parametros,
			contentType: false, //importante enviar este parametro en false
			processData: false, //importante enviar este parametro en false
			success: function (data) {
				if (seq !== calculaFacturaSeq) {
					return;
				}

				if (data && data.error) {
					alert(data.error);
					return;
				}

				$('#tbody-tabla-total-factura').empty();

				$.each(data.conceptostotales, function(index, item) {
					// index es la posición del elemento en el array
					// item es el elemento en sí
					if (item.importe != 0)
					{
						agregaRenglonTotalFactura();

						$('#total-factura-table').find('tr').last().find('.conceptototal').val(item.concepto);
						if (item.tasa > 0)
							$('#total-factura-table').find('tr').last().find('.tasatotal').val(parseFloat(item.tasa).toFixed(2));

						$('#total-factura-table').find('tr').last().find('.importetotal').val(item.importe.toFixed(2));

						if (item.concepto == "Total")
						{
							$('#montototalfactura').val(item.importe.toFixed(2));
							$('#total-factura-table').find('tr').last().find('.conceptototal').css('fontWeight', 'bold');
							$('#total-factura-table').find('tr').last().find('.importetotal').css('fontWeight', 'bold');
						}
					}
				});
				$('.tasatotal').css('text-align', 'right');
				$('.importetotal').css('text-align', 'right');

				if (typeof window.aplicarTipoComprobanteSugerido === 'function') {
					window.aplicarTipoComprobanteSugerido(data);
				}
			},
			error :function( data ) {
				if (seq !== calculaFacturaSeq) {
					return;
				}
				if (data && data.statusText === 'abort') {
					return;
				}
				if (data.error)
					alert(data.error);
			}
		});
	}

	// Agrega renglon totales de factura
    function agregaRenglonTotalFactura(){
        var renglon = $('#template-renglon-total-factura').html();

		$("#tbody-tabla-total-factura").append(renglon);
    }


	
