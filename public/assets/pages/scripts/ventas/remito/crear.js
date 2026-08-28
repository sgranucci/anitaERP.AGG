// Scripts para carga de pedidos

	var talles_txt;
	var medidas_txt;
	var precios_txt;
	var tallesid_txt;
	var cantidadmodal_txt;
	var nombre_modulo;
	var moduloElegido_id;
	var articuloxsku;
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
	var remito_articulo_ids=[];
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
	var kiloanulacion;
	var itemPedido_id;
	var flSaltarFocusDescuentoPedido = false;
	var flAgregarRenglonTrasDescuentoPedido = false;

	function mensajeErrorFacturaPedido(data) {
		if (data == null || data === '') {
			return 'Sin respuesta del servidor.';
		}
		if (typeof data === 'string') {
			return data;
		}
		if (data.error) {
			return String(data.error);
		}
		if (Array.isArray(data)) {
			for (var i = 0; i < data.length; i++) {
				if (data[i] && data[i].error) {
					return String(data[i].error);
				}
			}
		}
		return null;
	}

	function normalizarTextoFactura(valor) {
		if (valor == null || valor === '') {
			return null;
		}
		var texto = String(valor).trim();
		if (texto === '' || texto === 'undefined' || texto === 'null') {
			return null;
		}
		return texto;
	}

	function extraerFacturaDeItem(item) {
		if (!item || typeof item !== 'object') {
			return null;
		}
		if (item.error) {
			return null;
		}
		return normalizarTextoFactura(item.factura)
			|| normalizarTextoFactura(item.codigo)
			|| normalizarTextoFactura(item.referencia)
			|| normalizarTextoFactura(item.numero);
	}

	function esCodigoSucursalVillafranca(codigo) {
		return /(?:^|[\s-])00015-/.test(String(codigo || ''));
	}

	function debeOcultarFacturaEnMensajeOk(item, codigo, cantidadItems) {
		if (item && (item.ocultar_mensaje === true || item.ocultar_mensaje === 1 || item.ocultar_mensaje === '1')) {
			return true;
		}
		return cantidadItems > 1 && esCodigoSucursalVillafranca(codigo);
	}

	function extraerUrlImpresionSesion(data) {
		var items = Array.isArray(data) ? data : [data];
		for (var i = 0; i < items.length; i++) {
			var item = items[i];
			if (item && typeof item === 'object' && item.impresion_url) {
				var url = String(item.impresion_url).trim();
				if (url !== '') {
					return url;
				}
			}
		}
		return null;
	}

	function irASesionImpresionORecargar(data) {
		var url = extraerUrlImpresionSesion(data);
		if (url) {
			window.location = url;
			return;
		}
		window.history.go(0);
	}

	function facturasGeneradasPedido(data) {
		var items = Array.isArray(data) ? data : [data];
		var facturas = [];
		for (var j = 0; j < items.length; j++) {
			var facturaItem = extraerFacturaDeItem(items[j]);
			if (facturaItem && !debeOcultarFacturaEnMensajeOk(items[j], facturaItem, items.length)) {
				facturas.push(facturaItem);
			}
		}
		return facturas;
	}

	function mensajeErrorAjaxFacturaPedido(xhr) {
		if (xhr && xhr.responseJSON) {
			var msgJson = mensajeErrorFacturaPedido(xhr.responseJSON);
			if (msgJson) {
				return msgJson;
			}
			if (xhr.responseJSON.message) {
				return String(xhr.responseJSON.message);
			}
		}
		if (xhr && xhr.responseText && xhr.responseText.indexOf('<') < 0) {
			return xhr.responseText.substring(0, 500);
		}
		return 'Error al facturar el pedido' + (xhr && xhr.status ? ' (HTTP ' + xhr.status + ').' : '.');
	}

	function analizarResultadoFacturaPedido(data) {
		var items = Array.isArray(data) ? data : [data];
		var comprobantes = [];
		var errores = [];
		var huboExitoOculto = false;

		for (var i = 0; i < items.length; i++) {
			var item = items[i];
			if (item == null || typeof item !== 'object') {
				errores.push('Respuesta inválida del servidor.');
				continue;
			}

			var errorItem = item.error ? String(item.error).trim() : '';
			var factura = extraerFacturaDeItem(item);

			if (errorItem) {
				errores.push(errorItem);
				if (factura && !debeOcultarFacturaEnMensajeOk(item, factura, items.length)) {
					comprobantes.push({ codigo: factura, ok: false, detalle: errorItem });
				}
				continue;
			}

			if (factura) {
				if (debeOcultarFacturaEnMensajeOk(item, factura, items.length)) {
					huboExitoOculto = true;
					continue;
				}
				comprobantes.push({ codigo: factura, ok: true, detalle: null });
			} else if (item.ocultar_mensaje) {
				huboExitoOculto = true;
			}
		}

		var facturasOk = comprobantes.filter(function (c) { return c.ok; });
		var estado = 'error';
		var titulo = 'No se pudo facturar el pedido';
		var subtitulo = '';
		var exito = false;

		if (errores.length && facturasOk.length) {
			estado = 'parcial';
			titulo = 'Facturación parcial';
			subtitulo = 'Se generaron comprobantes, pero el proceso terminó con errores.';
			exito = true;
		} else if (errores.length) {
			estado = 'error';
			titulo = 'Error al facturar el pedido';
			subtitulo = errores.length === 1 ? errores[0] : 'Revise los detalles a continuación.';
		} else if (facturasOk.length) {
			estado = 'ok';
			titulo = facturasOk.length > 1 ? 'Facturación exitosa' : 'Factura generada';
			subtitulo = facturasOk.length > 1
				? 'Se emitieron ' + facturasOk.length + ' comprobantes.'
				: 'El comprobante quedó registrado correctamente.';
			exito = true;
		} else if (huboExitoOculto) {
			estado = 'ok';
			titulo = 'Factura generada';
			subtitulo = 'El comprobante quedó registrado correctamente.';
			exito = true;
		} else {
			errores.push('No se recibió el número de factura generada.');
			subtitulo = errores[0];
		}

		return {
			estado: estado,
			titulo: titulo,
			subtitulo: subtitulo,
			facturas: comprobantes,
			errores: errores,
			exito: exito,
			codigosOk: facturasOk.map(function (c) { return c.codigo; }),
		};
	}

	function mostrarResultadoFacturaPedidoEnOverlay(resumen, onContinuar) {
		if (!window.PedidoProcesoOverlay || typeof PedidoProcesoOverlay.mostrarResultado !== 'function') {
			var fallback = resumen.exito
				? ('Facturación exitosa: ' + (resumen.codigosOk.join(', ') || ''))
				: (resumen.errores[0] || 'Error al facturar el pedido.');
			if (window.toastr) {
				toastr[resumen.exito ? 'success' : 'error'](fallback, '', { timeOut: 9000, closeButton: true });
			} else {
				alert(fallback);
			}
			if (typeof onContinuar === 'function') {
				onContinuar(!!resumen.exito);
			}
			return;
		}

		PedidoProcesoOverlay.mostrarResultado({
			tipo: resumen.estado === 'ok' ? 'ok' : (resumen.estado === 'parcial' ? 'parcial' : 'error'),
			titulo: resumen.titulo,
			subtitulo: resumen.subtitulo,
			facturas: resumen.facturas,
			errores: resumen.estado === 'ok' ? [] : resumen.errores,
			boton: resumen.exito ? 'Continuar' : 'Cerrar',
			onCerrar: function () {
				if (typeof onContinuar === 'function') {
					onContinuar(!!resumen.exito);
				}
			},
		});
	}

	function mostrarResultadoFacturaPedido(data, onContinuar) {
		var resumen = analizarResultadoFacturaPedido(data);
		mostrarResultadoFacturaPedidoEnOverlay(resumen, onContinuar);
		return !!resumen.exito;
	}

	var mensajesProcesoFacturaPedido = [
		'Calculando importes de la factura…',
		'Numerando comprobante…',
		'Registrando venta en el sistema…',
		'Grabando en Anita…',
	];

	var emisionComprobantePedidoEnCurso = false;

	function botonAceptaFacturarPedido() {
		return $('#aceptaFacturarOrdenTrabajoModal');
	}

	function tomarEmisionComprobantePedido() {
		if (emisionComprobantePedidoEnCurso) {
			return false;
		}
		emisionComprobantePedidoEnCurso = true;
		botonAceptaFacturarPedido().prop('disabled', true);
		return true;
	}

	function liberarEmisionComprobantePedido() {
		emisionComprobantePedidoEnCurso = false;
		botonAceptaFacturarPedido().prop('disabled', false);
	}

	function iniciarProcesoFacturaPedido() {
		if (window.PedidoProcesoOverlay) {
			PedidoProcesoOverlay.iniciar(mensajesProcesoFacturaPedido, 'Facturando pedido…');
		}
	}

	function detenerProcesoFacturaPedido() {
		if (window.PedidoProcesoOverlay) {
			PedidoProcesoOverlay.detener();
		}
	}

	function esCampoPedidoEnfocable(el) {
		if (!el || el.tagName === 'TEXTAREA') {
			return false;
		}
		if (el.matches('input[type="hidden"], [readonly], [disabled]')) {
			return false;
		}
		if (!el.matches('input, select')) {
			return false;
		}
		return el.offsetParent !== null;
	}

	function obtenerCamposPedidoEnfocables() {
		var nodos = document.querySelectorAll(
			'#formgeneral input:not([type="hidden"]):not([readonly]):not([disabled]), ' +
			'#formgeneral select:not([disabled])'
		);
		return Array.prototype.filter.call(nodos, esCampoPedidoEnfocable);
	}

	function focuseCampoPedido(el) {
		if (!el || !esCampoPedidoEnfocable(el)) {
			return;
		}

		el.focus();

		if (el.tagName === 'INPUT' && (el.type === 'text' || el.type === '')) {
			el.select();
		}
	}

	function codigoArticuloSiguienteRenglon($tr) {
		var $siguiente = $tr.nextAll('tr.item-remito').first();

		if (!$siguiente.length) {
			return null;
		}

		var codigo = $siguiente.find('.codigoarticulo')[0];

		return codigo && esCampoPedidoEnfocable(codigo) ? codigo : null;
	}

	function obtenerSiguienteCampoPedido(actual) {
		var $actual = $(actual);
		var $tr = $actual.closest('#itemsremito-table tr.item-remito');

		if ($tr.length) {
			if (actual.classList.contains('codigoarticulo') || actual.classList.contains('unidadmedida_id')) {
				return 'defer';
			}

			if (actual.classList.contains('caja') || actual.classList.contains('pieza')) {
				var $kilo = $tr.find('.kilo');

				if ($kilo.length && esCampoPedidoEnfocable($kilo[0])) {
					return 'redondea:kilo';
				}

				return 'redondea:descuento';
			}

			if (actual.classList.contains('kilo')) {
				return 'redondea:descuento';
			}

			if (actual.classList.contains('pesada')) {
				var $descuento = $tr.find('.descuentoventa_id');

				if ($descuento.length && esCampoPedidoEnfocable($descuento[0])) {
					return $descuento[0];
				}

				return codigoArticuloSiguienteRenglon($tr);
			}

			if (actual.classList.contains('descuentoventa_id')) {
				return codigoArticuloSiguienteRenglon($tr);
			}
		}

		var campos = obtenerCamposPedidoEnfocables();
		var indice = campos.indexOf(actual);

		if (indice >= 0 && indice < campos.length - 1) {
			return campos[indice + 1];
		}

		return null;
	}

	function avanzarCampoPedidoConEnter(event) {
		if (event.key !== 'Enter' && event.which !== 13) {
			return;
		}
		if (!esCampoPedidoEnfocable(event.target)) {
			return;
		}
		if (confirmarDescuentoPedidoConEnter(event)) {
			return;
		}

		var $target = $(event.target);
		var $tr = $target.closest('#itemsremito-table tr.item-remito');

		if ($tr.length && event.target.classList.contains('codigoarticulo')
			&& !(event.target.value || '').trim()
			&& !hayModalPedidoAbierto()) {
			event.preventDefault();
			event.stopPropagation();
			eliminarRenglonPedidoSkuVacio($tr);
			return;
		}

		event.preventDefault();

		var accion = obtenerSiguienteCampoPedido(event.target);

		if (accion === 'defer') {
			$target.trigger('change');
			return;
		}

		if (typeof accion === 'string' && accion.indexOf('redondea:') === 0) {
			flSaltarFocusDescuentoPedido = accion === 'redondea:kilo';
			$target.trigger('change');

			if (accion === 'redondea:descuento' && !$tr.length) {
				flSaltarFocusDescuentoPedido = false;
			}

			return;
		}

		if (accion) {
			focuseCampoPedido(accion);
		}
	}

	function esSelectDescuentoPedido(el) {
		return !!(el && el.tagName === 'SELECT' && el.classList.contains('descuentoventa_id') && !el.disabled);
	}

	function opcionesNavegablesDescuentoPedido(select) {
		return Array.prototype.filter.call(select.options, function (opt) {
			return !opt.disabled;
		});
	}

	function navegarDescuentoPedidoConFlechas(event) {
		if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') {
			return;
		}
		if (!esSelectDescuentoPedido(event.target)) {
			return;
		}

		event.preventDefault();
		event.stopPropagation();

		var select = event.target;
		var opts = opcionesNavegablesDescuentoPedido(select);

		if (!opts.length) {
			return;
		}

		var idx = -1;

		for (var i = 0; i < opts.length; i++) {
			if (opts[i].selected || String(opts[i].value) === String(select.value)) {
				idx = i;
				break;
			}
		}

		if (event.key === 'ArrowDown') {
			idx = idx < 0 ? 0 : Math.min(idx + 1, opts.length - 1);
		} else {
			idx = idx < 0 ? opts.length - 1 : Math.max(idx - 1, 0);
		}

		select.selectedIndex = Array.prototype.indexOf.call(select.options, opts[idx]);
	}

	function agregarRenglonPedidoTrasDescuento($tr) {
		if ($tr && $tr.length) {
			var $siguiente = $tr.nextAll('tr.item-remito').first();
			var skuSiguiente = $siguiente.length
				? ($siguiente.find('.codigoarticulo').val() || '').trim()
				: '';

			if ($siguiente.length && !skuSiguiente) {
				var codigo = $siguiente.find('.codigoarticulo')[0];
				if (codigo && esCampoPedidoEnfocable(codigo)) {
					focuseCampoPedido(codigo);
					return;
				}
			}
		}

		if (!puedeAgregarRenglonPedido()) {
			alert('No puede generar pedidos con mas de 42 ítems');
			return;
		}

		agregaRenglon();
	}

	function confirmarDescuentoPedidoConEnter(event) {
		var el = event.target;
		if (!el || el.tagName !== 'SELECT' || !el.classList.contains('descuentoventa_id')) {
			return false;
		}

		event.preventDefault();
		event.stopPropagation();

		var $tr = $(el).closest('#itemsremito-table tr.item-remito');

		if (el.disabled || !el.value) {
			agregarRenglonPedidoTrasDescuento($tr);
			return true;
		}

		flAgregarRenglonTrasDescuentoPedido = true;
		$(el).trigger('change');
		return true;
	}

	function esAtajoAgregarRenglonPedido(event) {
		return event.key === '+' || event.code === 'NumpadAdd' || (event.key === '=' && event.shiftKey);
	}

	function estaEnTablaArticulosPedido(el) {
		return !!(el && el.closest && el.closest('#itemsremito-table'));
	}

	function hayModalPedidoAbierto() {
		return document.querySelector('.modal.show, .modal.in') !== null;
	}

	function eliminarRenglonPedidoSkuVacio($tr) {
		if (!$tr || !$tr.length) {
			return;
		}

		var $filas = $('#tbody-tabla tr.item-remito');
		if ($filas.length <= 1) {
			var codigoUnico = $tr.find('.codigoarticulo')[0];
			if (codigoUnico && esCampoPedidoEnfocable(codigoUnico)) {
				focuseCampoPedido(codigoUnico);
			}
			return;
		}

		var $siguiente = $tr.nextAll('tr.item-remito').first();
		var $previo = $tr.prevAll('tr.item-remito').first();

		$tr.remove();

		actualizaRenglones();
		TotalPedido();

		if ($siguiente.length) {
			var codigoSig = $siguiente.find('.codigoarticulo')[0];
			if (codigoSig && esCampoPedidoEnfocable(codigoSig)) {
				focuseCampoPedido(codigoSig);
			}
			return;
		}

		var descuentoPrev = $previo.find('.descuentoventa_id')[0];
		if (descuentoPrev && descuentoPrev.offsetParent !== null) {
			descuentoPrev.focus();
			return;
		}

		var codigoPrev = $previo.find('.codigoarticulo')[0];
		if (codigoPrev && esCampoPedidoEnfocable(codigoPrev)) {
			focuseCampoPedido(codigoPrev);
		}
	}

	function puedeAgregarRenglonPedido() {
		return $('#tbody-tabla .item-remito').length < 42;
	}

	function agregarRenglonPedidoConTeclado(event) {
		if (!esAtajoAgregarRenglonPedido(event)) {
			return;
		}
		if (!estaEnTablaArticulosPedido(event.target)) {
			return;
		}
		if (hayModalPedidoAbierto()) {
			return;
		}

		event.preventDefault();
		event.stopPropagation();

		if (!puedeAgregarRenglonPedido()) {
			alert('No puede generar pedidos con mas de 42 ítems');
			return;
		}

		agregaRenglon(event);
	}

	function enfocarCodigoClientePedidoAlCargar() {
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

	window.enfocarCodigoClientePedidoAlCargar = enfocarCodigoClientePedidoAlCargar;

	function initPedidoEnterNavigation() {
		var form = document.getElementById('formgeneral');

		if (!form || form.dataset.pedidoEnterNav) {
			return;
		}

		form.dataset.pedidoEnterNav = '1';
		form.addEventListener('keydown', agregarRenglonPedidoConTeclado, true);
		form.addEventListener('keydown', navegarDescuentoPedidoConFlechas, true);
		form.addEventListener('keydown', avanzarCampoPedidoConEnter, true);
	}
	
	function etiquetaLugarEntrega(entrega) {
		if (!entrega) {
			return '';
		}
		if (entrega.etiqueta) {
			return String(entrega.etiqueta);
		}
		var nombre = String(entrega.nombre || '').trim();
		if (nombre) {
			return nombre;
		}
		var domicilio = String(entrega.domicilio || '').trim();
		if (domicilio) {
			return domicilio;
		}
		return String(entrega.localidad || '').trim();
	}

	function entregasNombradasCliente(entregas) {
		return (entregas || []).filter(function (entrega) {
			return String(entrega.nombre || '').trim() !== '' || entrega.nombre_usable === true;
		});
	}

	function actualizarEstadoRequeridoLugarEntrega() {
		var obligatorio = $('#fl_cliente_tiene_entrega').val() === '1';
		var seleccionado = !!$('#cliente_entrega_id').val() || String($('#lugarentrega').val() || '').trim() !== '';

		$('#label-lugarentrega').toggleClass('requerido', obligatorio);
		$('#aviso-lugarentrega-obligatorio').toggle(obligatorio && !seleccionado);
		$('#lugarentrega').toggleClass('is-invalid', obligatorio && !seleccionado);
	}

	function completarCliente_Entrega(cliente_id){
		window._entregasClienteActual = [];

		$.get(carpetaBase+'/ventas/leercliente_entrega/'+cliente_id, function(data){
			var entr = $.map(data, function(value){
				return [value];
			});

			window._entregasClienteActual = entr;
			var nombradas = entregasNombradasCliente(entr);
			fl_tiene_entrega = nombradas.length > 0;
			$('#fl_cliente_tiene_entrega').val(fl_tiene_entrega ? '1' : '0');

			if (!fl_tiene_entrega) {
				$('#div-cambiar-lugarentrega').hide();
				$('#lugarentrega').prop('readonly', false).attr('placeholder', 'Puede cargarlo aquí si el cliente no tiene lugares en el ABM');

				if (entr.length === 1) {
					aplicarLugarEntregaCliente(entr[0]);
					actualizarEstadoRequeridoLugarEntrega();
					return;
				}

				if (!$('#lugarentrega').val()) {
					$.get(carpetaBase+'/ventas/leercliente/'+cliente_id, function(clienteData){
						if (!$('#lugarentrega').val()) {
							$('#lugarentrega').val(clienteData.lugarentrega || '');
						}
					});
				}
				actualizarEstadoRequeridoLugarEntrega();
				return;
			}

			$('#lugarentrega').prop('readonly', true).attr('placeholder', 'Seleccione un lugar de entrega del cliente');

			if (entr.length === 1) {
				aplicarLugarEntregaCliente(entr[0]);
				$('#div-cambiar-lugarentrega').hide();
				actualizarEstadoRequeridoLugarEntrega();
				return;
			}

			$('#div-cambiar-lugarentrega').show();

			var entregaPreviaId = $('#cliente_entrega_id_previa').val() || $('#cliente_entrega_id').val();
			if (entregaPreviaId) {
				var entregaPrevia = null;
				$.each(entr, function(index, value){
					if (String(value.id) === String(entregaPreviaId)) {
						entregaPrevia = value;
					}
				});

				if (entregaPrevia) {
					aplicarLugarEntregaCliente(entregaPrevia);
					actualizarEstadoRequeridoLugarEntrega();
					return;
				}
			}

			limpiarLugarEntregaCliente();
			actualizarEstadoRequeridoLugarEntrega();
			mostrarModalSeleccionEntrega(entr);
		});
	}

	function limpiarLugarEntregaCliente() {
		$('#cliente_entrega_id').val('');
		$('#entrega_nombre').val('');
		$('#lugarentrega').val('');
		actualizarEstadoRequeridoLugarEntrega();
	}

	function aplicarLugarEntregaCliente(entrega) {
		if (!entrega) {
			return;
		}

		var etiqueta = etiquetaLugarEntrega(entrega);
		$('#cliente_entrega_id').val(entrega.id);
		$('#cliente_entrega_id_previa').val(entrega.id);
		$('#entrega_nombre').val(etiqueta);
		$('#lugarentrega').val(etiqueta).prop('readonly', $('#fl_cliente_tiene_entrega').val() === '1');
		actualizarEstadoRequeridoLugarEntrega();
	}

	function renderFilasModalEntrega(entregas) {
		var html = '';

		$.each(entregas, function(index, value){
			html += '<tr>';
			html += '<td class="nombre">'+ (etiquetaLugarEntrega(value) || '') +'</td>';
			html += '<td class="domicilio">'+ (value.domicilio || '') +'</td>';
			html += '<td class="localidad">'+ (value.localidad || '') +'</td>';
			html += '<td class="provincia">'+ (value.provincia || '') +'</td>';
			html += '<td class="text-nowrap"><button type="button" class="btn btn-warning btn-sm eligelugarentrega" data-id="'+ value.id +'">Elegir</button></td>';
			html += '</tr>';
		});

		$('#datosclienteentrega').html(html);
	}

	function mostrarModalSeleccionEntrega(entregas) {
		if (!entregas || !entregas.length) {
			return;
		}

		renderFilasModalEntrega(entregas);
		$('#seleccionclienteentregaModal').modal('show');
	}

	function validarLugarEntregaAntesGuardar() {
		var tieneTexto = String($('#lugarentrega').val() || '').trim() !== '';
		if ($('#fl_cliente_tiene_entrega').val() === '1' && !$('#cliente_entrega_id').val() && !tieneTexto) {
			actualizarEstadoRequeridoLugarEntrega();
			if (window.toastr) {
				toastr.error('Debe seleccionar un lugar de entrega del cliente.', '', { timeOut: 8000, closeButton: true });
			} else {
				alert('Debe seleccionar un lugar de entrega del cliente.');
			}
			mostrarModalSeleccionEntrega(window._entregasClienteActual || []);
			$('#lugarentrega').focus();
			return false;
		}

		return true;
	}

	$(document).on('click', '#btn-cambiar-lugarentrega', function(){
		mostrarModalSeleccionEntrega(window._entregasClienteActual || []);
	});

	$(document).on('click', '.eligelugarentrega', function(){
		var entregaId = $(this).data('id');
		var entrega = null;

		$.each(window._entregasClienteActual || [], function(index, value){
			if (String(value.id) === String(entregaId)) {
				entrega = value;
			}
		});

		if (entrega) {
			aplicarLugarEntregaCliente(entrega);
			$('#seleccionclienteentregaModal').modal('hide');
		}
	});

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
		
		medidas_txt = medidas_txt + "<th><input name='"+nombre+"[]' "+cantidadmodal_txt+" class='"+nombre+"' style='width:30px;' type='text' value='"+Pcant+"'></input></th>";

    	precios_txt = precios_txt + "<th><input name='preciosportalles[]' class='preciosportalles' type='hidden' value='"+Pprec+"'></input></th>";
    	tallesid_txt = tallesid_txt + "<th><input name='tallesid[]' class='tallesid' type='hidden' value='"+Ptalle_id+"'></input></th>";
		cantidadmodal_txt = "";
	}

	function asignaPrecio(ptr, Particulo_id, Ptalle_id)
	{
		let codigocliente = $('#codigocliente').val();
		let articulo_id = $(ptr).parents("tr").find(".articulo_id").val();
		var precio, listaprecio_id, incluyeimpuesto, moneda_id;

		$.get(carpetaBase+'/stock/asignapreciocliente/'+articulo_id+'/'+codigocliente, function(data){
           	var prec = $.map(data, function(value, index){
               	return [value];
           	});
			dpr=[];
			dlp=[];
			dii=[];
			dmo=[];
           	$.each(prec, function(index,value){
				precio = parseFloat(value.precio);
				listaprecio_id = value.listaprecio_id;
				incluyeimpuesto = value.incluyeimpuesto;
				moneda_id = value.moneda_id;
			});

			if (!window.listaprecioIdEsValidoLineaVentas(listaprecio_id)) {
				var $trPrecio = $(ptr).parents('tr');
				var skuPrecio = ($trPrecio.find('.codigoarticulo, .codigoarticulolocal').first().val() || '').trim();
				window.limpiarLineaArticuloSinListaprecio($trPrecio, skuPrecio);

				return;
			}

			if (typeof precio != 'string')
			{
				let precioRedondeado = redondearDecimales(precio, 2);

				$(ptr).parents("tr").find(".precio").val(precioRedondeado);
			}
			else
				$(ptr).parents("tr").find(".precio").val(precio);

			$(ptr).parents("tr").find(".listaprecio_id").val(listaprecio_id);
			$(ptr).parents("tr").find(".incluyeimpuesto").val(incluyeimpuesto);
			$(ptr).parents("tr").find(".moneda_id").val(moneda_id);
		});
        setTimeout(() => {
			return(precio);
        }, 300);
	}

    $(function () {
		initPedidoEnterNavigation();

		var articulo_id;
		var combinacion_id;
		var modulo_id;
		let estadoPedido = $('#estadoremito').val();

		// Completa combinaciones y modulos al abrir pedido
		$("#tbody-tabla .articulo").each(function(index) {
			var articulo = $(this);
			var combinacion = $(this).parents("tr").find(".combinacion").val();
			var combinacion_id = $(this).parents("tr").find(".combinacion_id_previa").val();
			var modulo_id = $(this).parents("tr").find(".modulo_id_previa").val();

        	completarCombinaciones(articulo, combinacion_id, true);
        	completarModulos(articulo, modulo_id);
		});

		marcaDescuento();

		muestraBotonSuspension(estadoPedido);

		activa_eventos(true);
		TotalPedido();
	});

	function activa_eventos(flInicio)
	{
		// Si esta agregando items desactiva los eventos
		if (!flInicio)
		{
			$('.articulo').off('click');
			$('.codigocliente').off('change');
			$('.articulo').off('change');
        	$(".caja").off('change');
			$(".unidadmedida_id").off('change');
			$(".botonsincargo").off('click');
			$(".pieza").off('change');
			$(".kilo").off('change');
			$(".pesada").off('change');
			$(".precio").off('change');
			$(".descuentoventa_id").off('change');
			$(".checkImpresion").off('change');
        	$(".kilo").off('click keydown');
			$('#aceptaarticuloxskuModal').off('click');
			$('#medidasModal').off('show.bs.modal');
			$('#cierraModal').off('click');
			$('#aceptaModal').off('click');
			$('#medidasModal').off('hidden.bs.modal');
        	$('#aceptaOrdenTrabajoModal').off('click');
			$('#lote_id').off('change');
			$(document).off('change', '.desc_combinacion');
			$(document).off('change', '.desc_modulo');
			$(document).off('change', '.cantidadesportalles');
		}

		activa_eventos_consultacliente();
		activa_eventos_consultaarticulo();
		activa_eventos_consultatransporte();
		activa_eventos_consultazonavta();

		$('.codigocliente').on('change', function (event) {
			event.preventDefault();
			var cliente_id = $(this);

			$(".precio").each(function() {
				let ptr = $(this);

				asignaPrecio(ptr, '', '');
			});
        });

		$(document).off('change.ocPedidoCodigoLocal', '.codigoarticulolocal').on('change.ocPedidoCodigoLocal', '.codigoarticulolocal', function (event) {
			event.preventDefault();
			var articulo = $(this);
			var articulo_ant = $(this).parents("tr").find(".articulo_id_previo").val();
			var articulo_nuevo = articulo.val();

			if (articulo_nuevo != articulo_ant)
			{
				// Blanquea cantidades
				var $tr = $(this).parents("tr");
				$tr.find(".caja").val('');
				$tr.find(".pieza").val('');
				$tr.find(".kilo").val('');
				$tr.find(".pesada").val('');
				reseteaDescuentoFila($tr);
			}
        });

		$('.checkImpresion').on('change', function (event) {
			event.preventDefault();
			
			if (flFactura && $(this).prop("checked"))
			{
				let ordentrabajo = $(this).parents("tr").find(".otcodigo").val();
				let tilde = this;
				let cliente_id = $("#cliente_id").val();
				let estadocliente = $("#estadocliente").val();
				let tiposuspensioncliente_id = $("#tiposuspensioncliente_id").val();
				let nombretiposuspensioncliente = $("#nombretiposuspensioncliente").val();
				let pedido_combinacion_id = $(this).parents("tr").find(".ids").val();
			
				// No deja factura cliente stock
				if (cliente_id == CLIENTE_STOCK_ID)
				{
					alert("No puede facturar cliente STOCK");
					$(tilde).prop("checked",false);
					return;
				}

				// Debe chequear estado del cliente
				if (!window.clienteEstaHabilitadoParaFacturacion(estadocliente) &&
					(tiposuspensioncliente_id == PROFORMA ||
					tiposuspensioncliente_id == MOROSO ||
					tiposuspensioncliente_id == NO_FACTURAR
					))
				{
					alert("No puede facturar cliente en estado "+nombretiposuspensioncliente);
					$(tilde).prop("checked",false);
					return;
				}
			
				// chequea si puede facturar
				if (ordentrabajo <= 0)
				{
					alert('No puede facturar porque no tiene OT generada');
					$(tilde).prop("checked",false);
					return;
				}

				// Busca si tiene factura asociada
				var listarUri = carpetaBase+"/ventas/estadoot/"+ordentrabajo+"/"+pedido_combinacion_id;
            
				$.get(listarUri, function(data){
					
					if (data.numerofactura == -3)
					{
						alert("OT no está terminada");

						$(tilde).prop("checked",false);
						return;						
					}
					if (data.numerofactura != -1 && data.numerofactura != -2 && data.numerofactura != -3)
					{
						alert("OT ya facturada "+data.numerofactura);
						$(tilde).prop("checked",false);
						return;
					}
				});
			}
        });

        $(".unidadmedida_id").on('change', function() {
			// Blanquea cantidades y habilita descuento para recalcular
			var $tr = $(this).parents("tr");
			$tr.find(".caja").val('');
			$tr.find(".pieza").val('');
			$tr.find(".kilo").val('');
			$tr.find(".pesada").val('');
			reseteaDescuentoFila($tr);

			var unidadmedida = $(this).find('option:selected').text();

			$(this).parents("tr").find(".unidadmedida").val(unidadmedida);

			if (unidadmedida.toUpperCase() == 'CAJ') {
				$(this).parents("tr").find(".caja").focus();
			} else if (unidadmedida.toUpperCase() == 'UN' || unidadmedida.toUpperCase() == 'KG') {
				$(this).parents("tr").find(".pieza").focus();
			} else {
				$(this).parents("tr").find(".kilo").focus();
			}
		});

        $(".caja").on('change', function() {
			// Redondea caja
			redondeaCaja(this, 1);
		});

        $(".pieza").on('change', function() {
			// Redondea caja
			redondeaCaja(this, 2);
        });

        $(".kilo").on('change', function() {
			// Redondea caja
			redondeaCaja(this, 3);
        });

        $(".pesada").on('change', function() {
			TotalPedido();
		});

        $(".descuentoventa_id").on('change', function() {
			let categoria_secos_id = $("#categoria_secos_id").val();
			let subcategoria_maquina_id = $("#subcategoria_maquina_id").val();
			let subcategoria_tira_id = $("#subcategoria_tira_id").val();
			
			let categoria_id = $(this).parents("tr").find(".categoria_id").val();
			let subcategoria_id = $(this).parents("tr").find(".subcategoria_id").val();
			let pieza = $(this).parents("tr").find(".pieza").val();
			let selectDescuento = $(this).parents("tr").find(".descuentoventa_id option:selected").text();
			let cantidadDescuento = selectDescuento.substring(0, 2); // Saca la cantidad del descuento del texto del select

			// Si es categoria secos y subcategoria maquina no permite descuento con piezas menores a cantidad del descuento
			if (categoria_id == categoria_secos_id && subcategoria_id == subcategoria_maquina_id && 
				parseFloat(pieza) < parseFloat(cantidadDescuento))
			{
				flAgregarRenglonTrasDescuentoPedido = false;
				alert('No puede usar descuento mayor a las piezas pedidas. Descuento Piezas '+cantidadDescuento+' Piezas Pedidas '+pieza);

				reseteaDescuentoFila($(this).parents("tr"));
			}
			else
			{
				let descuentoventa_id = $(this).parents("tr").find('.descuentoventa_id').val();
				
				$(this).parents("tr").find(".descuentoventaanterior_id").val(descuentoventa_id);
				$(this).parents("tr").find(".descuentoventa_id").attr('disabled', 'disabled');

				// Redondea caja calculando por pieza el descuento
				redondeaCaja(this, 2);
			}
        });

		$(".botonsincargo").on('click', function() {
			let kilo = $(this).parents("tr").find(".kilo").val();
			let articulo_id = $(this).parents("tr").find(".articulo_id").val();

			if (kilo > 0 && articulo_id > 0)
			{
				$(this).parents("tr").find(".sincargo").val('S');

				if (controlDescuento(this))
				{
					$(this).parents("tr").find(".precio").val(0);
					$(this).parents("tr").find(".codigoarticulo").attr('readonly', true);
					$(this).parents("tr").find(".caja").attr('readonly', true);
					$(this).parents("tr").find(".pieza").attr('readonly', true);
					$(this).parents("tr").find(".kilo").attr('readonly', true);
					$(this).parents("tr").find(".unidadmedida_id").attr('readonly', true);
					$(this).parents("tr").find(".descuentoventa_id").attr('readonly', true);
				}
				else
					$(this).parents("tr").find(".sincargo").val('N');
			}
        });

        // Acepta modal OT
        $('#aceptaOrdenTrabajoModal').on('click', function () {
            var leyenda = $("#leyendaot").val();
            var checkotstock = $("input:checkbox[class=checkboxotstock]:checked").val();
			var ordentrabajo_stock_codigo = $("#ordentrabajo_stock_codigo").val();
			var articulo_id = $(pedido_combinacion).parents('tr').find('.articulo').val();
			var combinacion_id = $(pedido_combinacion).parents('tr').find('.combinacion').val();
			var cantidad = $(pedido_combinacion).parents('tr').find('.cantidad').val();
			
			if (ordentrabajo_stock_codigo == '')
				ordentrabajo_stock_codigo = 0;

			// Elimina caracteres especiales de la leyenda
			var pattern = /[\^*@!"#$%&/,()=?¡!¿'\\]/gi;
			leyenda = leyenda.replace(pattern, ' ');

			if (ordentrabajo_stock_codigo > 0)
			{
				var listarUri = carpetaBase+"/ventas/controlaordentrabajostock/"+ordentrabajo_stock_codigo+"/"+articulo_id+"/"+combinacion_id;

				$.get(listarUri, function(data){
					if (data.estado != -1)
					{
						alert("Saldo lote "+ordentrabajo_stock_codigo+" Saldo "+data.saldo+" Cantidad "+cantidad+" Deposito "+data.deposito_id);

						if (data.saldo < cantidad)
						{
							alert('No puede hacer la orden de trabajo porque no tiene saldo suficiente');
							return;
						}
						if (data.deposito_id < 1)
						{
							alert('No puede hacer la orden de trabajo porque no tiene deposito asignado el lote');
							return;							
						}
						$('#crearOrdenTrabajoModal').modal('hide');

						if (checkotstock == 'on')
							var listarUri = carpetaBase+"/ventas/guardaordenestrabajo/pedido/"+
											$(pedido_combinacion).val()+"/on/"+ordentrabajo_stock_codigo+'/'+data.deposito_id+'/'+leyenda;
						else
							var listarUri = carpetaBase+"/ventas/guardaordenestrabajo/pedido/"+
											$(pedido_combinacion).val()+"/off/"+ordentrabajo_stock_codigo+'/'+data.deposito_id+'/'+leyenda;
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
					var listarUri = carpetaBase+"/ventas/guardaordenestrabajo/pedido/"
									+$(pedido_combinacion).val()+"/on/"+ordentrabajo_stock_codigo+'/1/'+leyenda;
				else
					var listarUri = carpetaBase+"/ventas/guardaordenestrabajo/pedido/"
									+$(pedido_combinacion).val()+"/off/"+ordentrabajo_stock_codigo+'/1/'+leyenda;
	
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

		// Control de pesada
	}

	function sumaKilos(modalactivo, clasetalle)
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

	function TotalPedido()
	{
		let totCaja = 0;
		let totPieza = 0;
		let totKilo = 0;
		let totPesada = 0;

		$(".caja").each(function() {
			if (parseFloat($(this).val()) >= 1 && parseFloat($(this).val()) <= 999999)
				totCaja += parseFloat($(this).val());
		});
		$("#totalcajasremito").val(totCaja.toFixed(2));		
		$(".pieza").each(function() {
			if (parseFloat($(this).val()) >= 1 && parseFloat($(this).val()) <= 999999)
				totPieza += parseFloat($(this).val());
		});
		$("#totalpiezasremito").val(totPieza.toFixed(2));		
		$(".kilo").each(function() {
			if (parseFloat($(this).val()) >= 1 && parseFloat($(this).val()) <= 999999)
				totKilo += parseFloat($(this).val());
		});
		$("#totalkilosremito").val(totKilo.toFixed(2));
		$(".pesada").each(function() {
			if (parseFloat($(this).val()) >= 0.01 && parseFloat($(this).val()) <= 999999)
				totPesada += parseFloat($(this).val());
		});
		$("#totalkilospesados").val(totPesada.toFixed(2));
		calcularValorAsegurado();
	}

	function calcularValorAsegurado()
	{
		let totNeto = 0;
		$("#tbody-tabla tr").each(function() {
			let estado = String($(this).find(".estados").val() || "");
			if (estado === "A") {
				return;
			}
			let kilo = parseFloat($(this).find(".kilo").val());
			let precio = parseFloat($(this).find(".precio").val());
			if (isNaN(kilo) || isNaN(precio)) {
				return;
			}
			totNeto += kilo * precio;
		});
		let pct = parseFloat($("#porcentaje_valor_asegurado").val());
		if (isNaN(pct) || pct < 0) {
			pct = 0;
		}
		if (pct > 100) {
			pct = 100;
		}
		let valorAsegurado = totNeto * (1 - pct / 100);
		$("#valoraseguradoremito").val(valorAsegurado.toFixed(2));
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

	// Manejo de grilla 

    $(function () {
        $('#agrega_renglon').on('click', agregaRenglon);
        $(document).on('click', '.eliminar', borraRenglon);
        $(document).on('click', '.anulaitem', anulaItem);
		$(document).on('click', '.historiaitem', historiaItem);

		// Si no tiene items agrega el primero (sin robar foco del cliente)
		if(!$('.item-remito').length)
			agregaRenglon(event, { enfocarArticulo: false });

		let cliente_id = $("#cliente_id").val();
		if (cliente_id == CLIENTE_STOCK_ID)
			$("#divlote").show();
		else
			$("#divlote").hide();

		enfocarCodigoClientePedidoAlCargar();
    });

    function agregaRenglon(event, opciones){
		opciones = opciones || {};

		if ($('#formgeneral').hasClass('pedido-bloqueado-padron')) {
			if (typeof window.notificarBloqueoPadronCliente === 'function') {
				window.notificarBloqueoPadronCliente('Problemas en ARCA: no puede agregar ítems con este cliente.');
			} else {
				alert('Problemas en ARCA: no puede agregar ítems con este cliente.');
			}
			return;
		}

		if (event != undefined)
        	event.preventDefault();
        var renglon = $('#template-renglon').html();

        $("#tbody-tabla").append(renglon);
        actualizaRenglones();

		activa_eventos(false);

		if (opciones.enfocarArticulo !== false) {
			$('#itemsremito-table').find('tr').last().find('.codigoarticulo').focus();
		}
	}

	// Anula item 
    function anulaItem() {
		let estadoPedido = $('#estadoremito').val();

		if (estadoPedido != 'Pendiente')
		{
			alert("No puede anular un item que no está en estado pendiente");
		}
		else
		{
			motivoAnulacionOt = $(this).parents('tr').find('.motivosanulacion').val();
			itemAnulacionOt = $(this);

			itemAnulacion = $(this).parents('tr').find('.item');
			itemAnulacionId = $(this).parents('tr').find('.ids').val();
			botonAnulacion = $(this).parents('tr').find('.ianulaItem');
			descripcion_articulo = $(this).parents('tr').find('.descripcionarticulo').val();
			kiloanulacion = $(this).parents('tr').find('.kilo').val();

			flAnulacionItem = true;

			$("#anulacionModal").modal('show');
		}
	}

	// Controla apertura modal de anulacion
	$('#anulacionModal').on('show.bs.modal', function (event) {
  		var modal = $(this);
		modalActivo = "anulacionModal";

		if (botonAnulacion.hasClass('text-danger'))
	  	{
			var tituloModal = "Anulación item ";
  			modal.find('#aceptaanulacionModal').attr("title", "Anula item");
			$("#motivocierrepedido").hide();
	  	}
		else
	  	{
			var tituloModal = "Recupera item ";
  			modal.find('#aceptaanulacionModal').attr("title", "Recupera item");
			$("#motivocierrepedido").show();
			$("#nombremotivoanulacion").empty();
			$("#nombremotivoanulacion").append(motivoAnulacionOt);
		}
  		modal.find('.modal-title').text(tituloModal+descripcion_articulo);
  		modal.find('#anulacionModal').empty();
  		modal.find('#anulacionModal').append('');
		$('#totanulacionPares').val(kiloanulacion);
	});

	$('#cierraanulacionModal').on('click', function () {
	  	flAnulacionItem = false;
	});

	// Acepta modal de anulacion de item
	$('#aceptaanulacionModal').on('click', function () {
	  	let nuevoClienteId = 0;
	  	let motivoAnulacionId = $('#motivoanulacion_id').val();

		if (motivoAnulacionId == '')
		{
			alert("Debe ingresar motivo");
			return;
		}
	  	flAnulacionItem = false;
		codigoAnulacionOt = 'xxx';
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
					$(itemAnulacion).parents('tr').find('.anulaitem').attr("title", "Recupera item");
					alert("Item anulado con exito");
					$(itemAnulacionOt).parents('tr').find('.motivosanulacion').val($("select[id=motivoanulacion_id] option:selected").text());
				}
				else
				{
					$(itemAnulacion).css("background-color","");
					$(itemAnulacion).css("font-weight","normal");
					$(itemAnulacion).parents('tr').find('.anulaitem').attr("title", "Anula item");
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
		flAnulacionItem = true;

		setTimeout(() => {
			descripcion_articulo = $(this).parents('tr').find('.descripcionarticulo').val();
			kiloanulacion = $(this).parents('tr').find('.kilo').val();
			itemPedido_id = $(this).parents('tr').find('.ids').val();

			$("#historiaModal").modal('show');
		}, 300);
	}

	// Controla apertura modal de historia
	$('#historiaModal').on('show.bs.modal', function (event) {
		var modal = $(this);
		let tituloModal = "Historia Anulación Item ";

		modal.find('.modal-title').text(tituloModal+descripcion_articulo);
		modal.find('#historiaModal').empty();
		modal.find('#historiaModal').append('');

		var wrapper = $("#tbody-historia");

		let url = carpetaBase+'/ventas/leerhistoriaitempedido/'+itemPedido_id;

		$.get(url, function(historia){

			$(wrapper).empty();

			var hist = $.map(historia, function(value, index){
				return [value];
			});
			$.each(hist, function(index,value){
				let fechaCierre = Date.parse(value.created_at);

				if (value.cliente_id != null)
					var cliente = value.clientes.nombre;
				else
					var cliente = '';

				switch(value.estado)
				{
					case 'A':
						var estado = 'Anulado';
						break;
					case 'P':
						var estado = 'Pendiente';
						break;
				}

				$(wrapper).append('<tr>'+
                            '<td>'+
                                '<input type="text" name="historiafechas[]" class="form-control historiafecha" value="'+new Date(fechaCierre).toLocaleString("es-AR")+'" readonly>'+
                            '</td>'+
                            '<td>'+
                                '<input type="text" name="historiamotivos[]" class="form-control historiamotivo" value="'+value.motivoscierrepedido.nombre+'" readonly>'+
                            '</td>'+
                            '<td>'+
                                '<input type="text" name="historiaclientes[]" class="form-control historiacliente" value="'+cliente+'" readonly>'+
                            '</td>'+
                            '<td>'+
                                '<input type="text" name="historiaobservaciones[]" class="form-control historiaobservacione" value="'+value.observacion+'" readonly>'+
                            '</td>'+
							'<td>'+
                                '<input type="text" name="historiaestados[]" class="form-control historiaestado" value="'+estado+'" readonly>'+
                            '</td>'+
                        '</tr>');
			});
		});

		$('#tothistoriaPares').val(kiloanulacion);
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
		let pedido_combinacion_id = $(this).parents("tr").find(".ids").val();
		
		// Busca si tiene factura asociada
		var listarUri = carpetaBase+"/ventas/estadoot/"+ordentrabajo+"/"+pedido_combinacion_id;
		var flError = false;

		$.get(listarUri, function(data){
							
			if (data.numerofactura != -1 && data.numerofactura != -2 && data.numerofactura != -3)
			{
				alert("Item ya facturado "+data.numerofactura);
				flError = true;
			}
		});

		setTimeout(() => {
			if (!flError)
			{
				if (confirm("¿Desea borrar renglon?"))
				{
					$(this).parents('tr').remove();
					actualizaRenglones();
				}
				TotalPedido();
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

	function preparaFactura()
	{
        $("#tbody-tabla .checkImpresion").each(function() {
			$(this).show();
		});
		flFactura = true;
		$("#generaFactura").show();
	}

	function imprimePreFactura()
	{
		let checksId=[];
		let itemId;
	  	let pedidoId = $("#pedidoid").val();
		let descuentoLinea;

		$("input[type=checkbox]:checked").each(function(){
			
	  		itemId = $(this).parents('tr').find('.ids').val();
    		checksId.push(itemId);

		});
		descuentoLinea = prompt("Ingrese descuento de linea: ");

		let listarUri = carpetaBase+"/ventas/listarprefactura"+"/"+pedidoId+'/'+checksId+"/"+descuentoLinea;
		document.location.href= listarUri;
	}

	function generaFactura()
	{
		if (typeof window.clienteEsDespacho === 'function' && window.clienteEsDespacho($('#cliente_id').val())) {
			alert(window.mensajeClienteDespachoNoFacturable());
			return;
		}
		if (typeof validarLugarEntregaAntesGuardar === 'function' && !validarLugarEntregaAntesGuardar()) {
			return;
		}

		var clienteIdFactura = $("#cliente_id").val();
		if (typeof window.ejecutarSiPadronOperacionOk === 'function') {
			window.ejecutarSiPadronOperacionOk(clienteIdFactura, generaFacturaAbrirModal);
			return;
		}

		generaFacturaAbrirModal();
	}
	window.generaFactura = generaFactura;

	function generaFacturaAbrirModal()
	{
		let itemId, otId;
		
		preciosfactura_txt = [];
		titulofactura_txt = [];
		remito_articulo_ids = [];
		offFactura = 0;

		cliente_id = $("#cliente_id").val();
		
		$("#tbody-tabla .articulo_id").each(function(){

			itemId = $(this).parents('tr').find('.ids').val();
			
			if (!itemFacturado(itemId))
			{
				remito_articulo_ids.push(itemId);

				descripcion_articulo = $(this).parents("tr").find(".descripcionarticulo").val();
				kilo = $(this).parents("tr").find(".kilo").val();

				articulo_id = $(this).parents("tr").find(".articulo_id").val();

				cantidades=[];
				precios=[];
			
			}
		});
		nombrecliente = $("#nombrecliente").val();
		descuentoCliente = $('#descuento').val();

		setTimeout(() => {
			$("#facturarRemitoModal").modal('show');

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
				});
			});

		}, 300);
	}

	// Carga modal de facturacion
	$(document).on('shown.bs.modal', '#facturarRemitoModal', function() {
		var modal = $(this);

		modal.find('#tbody-tabla-factura').empty();
		modal.find('#tbody-tabla-total-factura').empty();
		modal.find('#alert-preview-factura-pedido').addClass('d-none').text('');

		modalActivo = "facturarRemitoModal";

		var numeroPedido = $('#codigoremito').val();
		let sel_puntoventa = JSON.parse(document.querySelector('#datosfactura').dataset.puntoventa);
		let sel_puntoventaremito = JSON.parse(document.querySelector('#datosfactura').dataset.puntoventa);
		let selectPuntoVenta = modal.find('#puntoventa_id');
		let selectPuntoVentaRemito = modal.find('#puntoventaremito_id');
		let puntoVentaDefault = $('#puntoventadefault_id').val();
		let puntoVentaRemitoDefault = $('#puntoventaremitodefault_id').val();
		let sel_tipotransaccion = JSON.parse(document.querySelector('#datosfactura').dataset.tipotransaccion);
		let selectTipoTransaccion = modal.find('#tipotransaccion_id');
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
	
		const tiempoTranscurrido = Date.now();
		const hoy = new Date(tiempoTranscurrido);

		modal.find('#fechafactura').val(hoy.toISOString().substring(0,10));
		modal.find('#nombrecliente').val(nombrecliente);
		modal.find('.modal-title').text('Factura REMITO '+numeroPedido);
		modal.find('#facturarMedidasModal').empty();
		modal.find('#descuentopie').val(descuentoCliente);

    	// Lee punto de venta si es de exportacion
    	leePuntoVenta(puntoVentaDefault);

		$("#tbody-tabla .articulo_id").each(function(){
			let estadoItem = $(this).parents('tr').find('.estados').val();

			if (estadoItem == 'P')
			{
				agregaRenglonFactura();

				// Asigna variables
				let articulo_id = $(this).val();
				let codigoarticulo = $(this).parents("tr").find(".codigoarticulo").val();
				let descripcionarticulo = $(this).parents("tr").find(".descripcionarticulo").val();
				let remito_articulo_id = $(this).parents("tr").find(".ids").val();
				let unidadmedida = $(this).parents("tr").find(".unidadmedida_id").find(':selected').text();
				let unidadmedida_id = $(this).parents("tr").find(".unidadmedida_id").val();
				let caja = $(this).parents("tr").find(".caja").val();
				let pieza = $(this).parents("tr").find(".pieza").val();
				let kilo = $(this).parents("tr").find(".kilo").val();
				let descuentoventa_id = $(this).parents("tr").find(".descuentoventa_id").val();
				let precio = $(this).parents("tr").find(".precio").val();

				$('#factura-remito-table').find('tr').last().find('.id_fac').val(remito_articulo_id);
				$('#factura-remito-table').find('tr').last().find('.articulo_id_fac').val(articulo_id);
				$('#factura-remito-table').find('tr').last().find('.codigoarticulo_fac').val(codigoarticulo);
				$('#factura-remito-table').find('tr').last().find('.descripcionarticulo_fac').val(descripcionarticulo);
				$('#factura-remito-table').find('tr').last().find('.unidadmedida_fac').val(unidadmedida);
				$('#factura-remito-table').find('tr').last().find('.unidadmedida_id_fac').val(unidadmedida_id);
				$('#factura-remito-table').find('tr').last().find('.caja_fac').val(caja);
				$('#factura-remito-table').find('tr').last().find('.pieza_fac').val(pieza);
				$('#factura-remito-table').find('tr').last().find('.pesada_fac').val(kilo);
				$('#factura-remito-table').find('tr').last().find('.descuentoventa_id_fac').val(descuentoventa_id);
				$('#factura-remito-table').find('tr').last().find('.precio_fac').val(precio);
			}
		});

		$("#tbody-tabla-factura .descuentoventa_id_fac").each(function(){
			let ptr = this;
			let descuentoventa_id = $(this).val();

			// Lee descuento
			if (descuentoventa_id > 0)
			{
				var uri = carpetaBase+"/ventas/leeundescuentoventa/"+descuentoventa_id;

				$.get(uri, function(data){
					
					let pesada = $(ptr).parents('tr').find('.pesada_fac').val();

					let bonificado = parseFloat(pesada) * parseFloat(data.porcentajedescuento) / 100;

					let pesadaSinDescuento = parseFloat(pesada) - parseFloat(bonificado.toFixed(1));

					$(ptr).parents('tr').find('.pesada_fac').val(pesadaSinDescuento.toFixed(2));
					$(ptr).parents('tr').find('.porcentajedescuento_fac').val(bonificado.toFixed(1));

				});

				setTimeout(() => {
				}, 300);
			}

		});

		// Agrega totales
		agregaRenglonTotalItemFactura();
		$('#factura-remito-table tr.item-factura').last().addClass('renglon-total-item-factura');

		let totalcajasremito = $("#totalcajasremito").val();
		let totalpiezasremito = $("#totalpiezasremito").val();
		let totalkilosremito = $("#totalkilosremito").val();

		$('#factura-remito-table').find('tr').last().find('.descripcionarticulo_fac').val("Totales");
		$('#factura-remito-table').find('tr').last().find('.descripcionarticulo_fac').css('fontWeight', 'bold');
		$('#factura-remito-table').find('tr').last().find('.caja_fac').val(totalcajasremito);
		$('#factura-remito-table').find('tr').last().find('.pieza_fac').val(totalpiezasremito);
		$('#factura-remito-table').find('tr').last().find('.pesada_fac').val(totalkilosremito);
		$('#factura-remito-table').find('tr').last().find('.caja_fac').css('fontWeight', 'bold');
		$('#factura-remito-table').find('tr').last().find('.pieza_fac').css('fontWeight', 'bold');
		$('#factura-remito-table').find('tr').last().find('.pesada_fac').css('fontWeight', 'bold');

		if (typeof asignarCantidadBultoDesdePedido === 'function') {
			asignarCantidadBultoDesdePedido(totalcajasremito);
		} else {
			$('#cantidadbulto').val(parseInt(totalcajasremito, 10) || 0);
		}

		// Arma select de tipos de transacciones
		selectTipoTransaccion.empty();
		selectTipoTransaccion.append('<option value="">-- Seleccionar tipo de transacción --</option>');
		$.each(sel_tipotransaccion, function(obj, item) {
			op = (window.PreferenciasFacturacionUsuario
				? window.PreferenciasFacturacionUsuario.opcionSelected(tipoTransaccionDefault, item.id)
				: (tipoTransaccionDefault == item.id ? ' selected="selected"' : ''));
			selectTipoTransaccion.append('<option value="' + item.id + '" data-abreviatura="' + (item.abreviatura || '') + '"'+op+'>' + item.abreviatura + '-' + item.nombre + '</option>');
		});

		// Arma select de puntos de venta
		selectPuntoVenta.empty();
		selectPuntoVenta.append('<option value="">-- Seleccionar punto de venta --</option>');
		$.each(sel_puntoventa, function(obj, item) {
			op = (window.PreferenciasFacturacionUsuario
				? window.PreferenciasFacturacionUsuario.opcionSelected(puntoVentaDefault, item.id)
				: (puntoVentaDefault == item.id ? ' selected="selected"' : ''));
			selectPuntoVenta.append('<option value="' + item.id + '"'+op+'>' + item.codigo + '-' + item.nombre + '</option>');
		});

		// Arma select de puntos de venta del remito
		selectPuntoVentaRemito.empty();
		selectPuntoVentaRemito.append('<option value="">-- Seleccionar punto de venta --</option>');
		$.each(sel_puntoventaremito, function(obj, item) {
			op = (window.PreferenciasFacturacionUsuario
				? window.PreferenciasFacturacionUsuario.opcionSelected(puntoVentaRemitoDefault, item.id)
				: (puntoVentaRemitoDefault == item.id ? ' selected="selected"' : ''));
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

		var token = $('#csrf_token').val();
		var puntoventa_id = $('#puntoventa_id').val();
		var tipotransaccion_id = $('#tipotransaccion_id').val();
		var descuentopie = $('#descuentopie').val();
		var descuentoimportepie = $('#descuentoimportepie').val();
		var descuentolinea = $('#descuentolinea').val();
		var fechafactura = $('#fechafactura').val();
		var leyendafactura = $('#leyendafactura').val();
		var cantidadbulto = $('#cantidadbulto').val();
		var puntoventaremito_id = $('#puntoventaremito_id').val();
		var formapago_id = $('#formapago_id').val();
		var incoterm_id = $('#incoterm_id').val();
		var mercaderia = $('#mercaderia').val();
		var leyendaexportacion = $('#leyendaexportacion').val();
		var cliente_id = $('#cliente_id').val();
		let remito_id = $('#remito_id').val();

		// Completa actividad
		var puntoventa_id = $("#puntoventa_id").val();

		var listarUri = carpetaBase+"/ventas/leeunpuntoventa/"+puntoventa_id;

		$.get(listarUri, function(data){
			$("#actividad_arca_id").val(data.actividad_arca_id);

			if (data.actividad_arca_id > 0)
				$('#actividad_arca_id').attr('readonly', true);
			else
				$('#actividad_arca_id').attr('readonly', false);
		});

		// Calcula factura (preview; padrón ya validado al elegir cliente)
		$.ajax({
			type: 'POST',
			url: carpetaBase+"/ventas/calculafacturaporremito",
			data: {
				remito_id: remito_id,
				remito_articulo_ids: remito_articulo_ids,
				cliente_id: cliente_id,
				fechafactura: fechafactura,
				descuentopie: descuentopie,
				descuentoimportepie: descuentoimportepie,
				descuentolinea: descuentolinea,
				formapago_id: formapago_id,
				incoterm_id: incoterm_id,
				mercaderia: mercaderia,
				totalcajasremito: totalcajasremito,
				_token: token
			},
			success: function(data){
				modal.find('#alert-preview-factura-pedido').addClass('d-none').text('');

				var errorCalculo = mensajeErrorFacturaPedido(data);
				if (errorCalculo) {
					modal.find('#alert-preview-factura-pedido')
						.removeClass('d-none alert-info alert-success')
						.addClass('alert alert-warning')
						.text(errorCalculo);
					if (window.toastr) {
						toastr.warning(errorCalculo, 'Preview de factura', { timeOut: 9000, closeButton: true, progressBar: true });
					} else {
						alert(errorCalculo);
					}
					return;
				}

				var lineasFactura = modal.find('#tbody-tabla-factura tr').not('.renglon-total-item-factura').length;
				if (lineasFactura === 0) {
					var sinItems = 'No hay ítems pendientes en el remito para mostrar en la factura.';
					modal.find('#alert-preview-factura-pedido')
						.removeClass('d-none alert-info alert-success')
						.addClass('alert alert-warning')
						.text(sinItems);
					if (window.toastr) {
						toastr.warning(sinItems, 'Preview de factura', { timeOut: 9000, closeButton: true });
					}
				}

				modal.find('#tbody-tabla-total-factura').empty();
				$.each(data.conceptostotales || [], function(index, item) {
					var esTotal = item.concepto === 'Total';
					if (item.importe != 0 || esTotal)
					{
						agregaRenglonTotalFactura();

						modal.find('#total-factura-remito-table').find('tr').last().find('.conceptototal').val(item.concepto);
						modal.find('#total-factura-remito-table').find('tr').last().find('.tasatotal').val(parseFloat(item.tasa).toFixed(2));
						modal.find('#total-factura-remito-table').find('tr').last().find('.importetotal').val(parseFloat(item.importe).toFixed(2));

						if (esTotal)
						{
							modal.find('#total-factura-remito-table').find('tr').last().find('.conceptototal').css('fontWeight', 'bold');
							modal.find('#total-factura-remito-table').find('tr').last().find('.importetotal').css('fontWeight', 'bold');
						}
					}
				});
				modal.find('.tasatotal').css('text-align', 'right');
				modal.find('.importetotal').css('text-align', 'right');

				if (typeof window.aplicarTipoComprobanteSugerido === 'function') {
					window.aplicarTipoComprobanteSugerido(data, modal);
				}
			},
			error: function(xhr) {
				var msg = 'No se pudo calcular el preview de la factura.';
				if (xhr && xhr.responseJSON) {
					var errCalc = mensajeErrorFacturaPedido(xhr.responseJSON);
					if (errCalc) {
						msg = errCalc;
					}
				}
				modal.find('#alert-preview-factura-pedido')
					.removeClass('d-none alert-info alert-success')
					.addClass('alert alert-warning')
					.text(msg);
				if (window.toastr) {
					toastr.warning(msg, 'Preview de factura', { timeOut: 9000, closeButton: true, progressBar: true });
				}
			}
		});

	});

	// Agrega renglon factura
    function agregaRenglonFactura(){
        var renglon = $('#template-renglon-factura').html();

		$("#tbody-tabla-factura").append(renglon);
    }

	function agregaRenglonTotalItemFactura()
	{
		var renglon = $('#template-renglon-total-item-factura').html();

		$("#tbody-tabla-factura").append(renglon);
	}

	// Agrega renglon totales de factura
    function agregaRenglonTotalFactura(){
        var renglon = $('#template-renglon-total-factura').html();

		$("#tbody-tabla-total-factura").append(renglon);
    }

	function suspendePedido()
	{
		let estadoActualPedido = $('#estadoremito').val();

		if (estadoActualPedido != 'Suspendido' && estadoActualPedido != 'Pendiente')
		{
			alert("No se puede suspender el pedido")
			return;
		}
		switch(estadoActualPedido)
		{
			case 'Suspendido':
				$('#estadoremito').val('Pendiente');	
				break;
			case 'Pendiente':
				$('#estadoremito').val('Suspendido');
				break;
		}

		// Actualiza estado del pedido
		let estadoPedido = $('#estadoremito').val();
		let remito_id = $('#remito_id').val();

		let listarUri = carpetaBase+"/ventas/actualizasolopedido/"+estadoPedido+"/"+remito_id;

		$.get(listarUri)
			.done(function(data){
				alert('Pedido actualizado con éxito');

				muestraBotonSuspension(estadoPedido);
			})
			.fail(function(jqXHR, textStatus, errorThrown) {
				alert("Error en la petición: "+textStatus+errorThrown);
				alert("Estado de la respuesta: "+jqXHR.status); // Ej: 404, 500
			});
	}

	function muestraBotonSuspension(estadoPedido)
	{
		switch(estadoPedido)
		{
			case 'Suspendido':
				$('#suspendepedido').html('<i class="fas fa-check"></i>Activar el Pedido');
				$( "#suspendepedido" ).css( "background-color", "green" ); 

				break;
			case 'Pendiente':
				$('#suspendepedido').html('<i class="fas fa-cross"></i>Suspender el Pedido');
				$( "#suspendepedido" ).css( "background-color", "yellow" ); 
				break;
		}
	}

	// Cierra modal medidas
	$('#cierraFacturarOrdenTrabajoModal').on('click', function () {
		tallesfactura_txt = [];
		medidasfactura_txt = [];
		preciosfactura_txt = [];
		tallesidfactura_txt = [];
		titulofactura_txt = [];
		offFactura = 0;
		$('#facturarRemitoModal').modal('hide');
	});

	// Acepta modal
	$('#aceptaFacturarOrdenTrabajoModal').on('click', function () {
		// Factura el item
		var token = $('#csrf_token').val();
		var puntoventa_id = $('#puntoventa_id').val();
		var tipotransaccion_id = $('#tipotransaccion_id').val();
		var descuentopie = $('#descuentopie').val();
		var descuentoimportepie = $('#descuentoimportepie').val();
		var descuentolinea = $('#descuentolinea').val();
		var fechafactura = $('#fechafactura').val();
		var leyendafactura = $('#leyendafactura').val();
		var cantidadbulto = $('#cantidadbulto').val();
		var puntoventaremito_id = $('#puntoventaremito_id').val();
		var formapago_id = $('#formapago_id').val();
		var incoterm_id = $('#incoterm_id').val();
		var mercaderia = $('#mercaderia').val();
		var leyendaexportacion = $('#leyendaexportacion').val();
		let cliente_id = $('#cliente_id').val();
		let actividad_arca_id = $('#actividad_arca_id').val();
		let remito_id = $('#remito_id').val();
		let estadoRemito = $('#estadoremito').val();

		if (estadoRemito === 'Facturado' || estadoRemito === 'Suspendido' || estadoRemito === 'Anulado')
		{
			alert("No puede facturar un remito en estado " + estadoRemito);
			$('#facturarRemitoModal').modal('hide');
			return;
		}

		if (puntoventaremito_id < 1)
		{
			alert("No puede facturar sin punto de venta del remito");
			$('#facturarRemitoModal').modal('hide');
			return;
		}

		if (actividad_arca_id == '')
		{
			alert('No puede facturar sin asignar actividad ARCA');
			$('#facturarRemitoModal').modal('hide');
			return;
		}

		cantidadbulto = typeof normalizarCantidadBulto === 'function'
			? normalizarCantidadBulto(cantidadbulto)
			: (parseInt(cantidadbulto, 10) || 0);
		$('#cantidadbulto').val(cantidadbulto === 0 ? '' : cantidadbulto);
		if (cantidadbulto > 999999)
		{
			alert("La cantidad de bultos no puede superar 999999");
			return false;
		}

		if (!tomarEmisionComprobantePedido()) {
			return;
		}

		if (typeof window.ejecutarSiPadronOperacionOk === 'function') {
			window.ejecutarSiPadronOperacionOk(cliente_id, emitirFacturaPedidoDesdeModal, {
				onBloqueado: liberarEmisionComprobantePedido
			});
			return;
		}

		emitirFacturaPedidoDesdeModal();
	});

	function emitirFacturaPedidoDesdeModal()
	{
		var token = $('#csrf_token').val();
		var puntoventa_id = $('#puntoventa_id').val();
		var tipotransaccion_id = $('#tipotransaccion_id').val();
		var descuentopie = $('#descuentopie').val();
		var descuentoimportepie = $('#descuentoimportepie').val();
		var descuentolinea = $('#descuentolinea').val();
		var fechafactura = $('#fechafactura').val();
		var leyendafactura = $('#leyendafactura').val();
		var cantidadbulto = typeof normalizarCantidadBulto === 'function'
			? normalizarCantidadBulto($('#cantidadbulto').val())
			: (parseInt($('#cantidadbulto').val(), 10) || 0);
		var puntoventaremito_id = $('#puntoventaremito_id').val();
		var formapago_id = $('#formapago_id').val();
		var incoterm_id = $('#incoterm_id').val();
		var mercaderia = $('#mercaderia').val();
		var leyendaexportacion = $('#leyendaexportacion').val();
		let cliente_id = $('#cliente_id').val();
		let actividad_arca_id = $('#actividad_arca_id').val();
		let remito_id = $('#remito_id').val();
		
		$('#facturarRemitoModal').modal('hide');

		iniciarProcesoFacturaPedido();

		$.ajax({
			url: carpetaBase + '/ventas/facturarporremito',
			method: 'POST',
			dataType: 'json',
			data: {
				remito_articulo_ids: remito_articulo_ids,
				cliente_id: cliente_id,
				remito_id: remito_id,
				ordentrabajo_id: ordentrabajo_ids,
				tipotransaccion_id: tipotransaccion_id,
				puntoventa_id: puntoventa_id,
				fechafactura: fechafactura,
				descuentopie: descuentopie,
				descuentoimportepie: descuentoimportepie,
				descuentolinea: descuentolinea,
				leyendafactura: leyendafactura,
				cantidadbulto: cantidadbulto,
				puntoventaremito_id: puntoventaremito_id,
				formapago_id: formapago_id,
				incoterm_id: incoterm_id,
				mercaderia: mercaderia,
				leyendaexportacion: leyendaexportacion,
				actividad_arca_id: actividad_arca_id,
				_token: token
			},
		})
			.done(function (data) {
				mostrarResultadoFacturaPedido(data, function (exito) {
					if (!exito) {
						liberarEmisionComprobantePedido();
						return;
					}

					$('#facturarRemitoModal').modal('hide');
					$('#estadoremito').val('Facturado');
					TotalPedido();
					irASesionImpresionORecargar(data);
				});
			})
			.fail(function (xhr) {
				var msg = mensajeErrorAjaxFacturaPedido(xhr);
				mostrarResultadoFacturaPedidoEnOverlay({
					estado: 'error',
					titulo: 'Error al facturar el pedido',
					subtitulo: msg,
					facturas: [],
					errores: [msg],
					exito: false,
					codigosOk: [],
				}, function () {
					liberarEmisionComprobantePedido();
				});
			});
	}

	$('#facturarRemitoModal').on('hidden.bs.modal', function () {

		// Inicializa variables modal
		talles_txt = "";
		medidas_txt = "";
		precios_txt = "";
		tallesid_txt = "";
		if (typeof window.resetearTipoComprobanteSugerido === 'function') {
			window.resetearTipoComprobanteSugerido();
		}
		$(this).find('#aviso-tipo-fce').addClass('d-none').text('');
	});

	$('#puntoventa_id').on('change', function () {
		let puntoventa_id = $('#puntoventa_id').val();

		// Lee punto de venta si es de exportacion
		leePuntoVenta(puntoventa_id);
	});

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

	function completaDatosCliente()
	{
		var cliente_id = $("#cliente_id").val();

		if (!cliente_id || !$.isNumeric(cliente_id) || parseInt(cliente_id, 10) <= 0) {
			return;
		}

		completarCliente_Entrega(cliente_id);
		asignaDatosCliente(cliente_id, true);
		setTimeout(() => {
			muestraTipoSuspension();			
		}, 1500);
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

	function aplicarVendedorPedidoDesdeCliente(data) {
		var $sel = $('#vendedor_id');
		if (!$sel.length) {
			return;
		}
		var vendedorId = resolverVendedorIdCliente(data);
		if (!vendedorId) {
			return;
		}
		if (!$sel.find('option[value="' + vendedorId + '"]').length) {
			var nombre = (data.vendedores && data.vendedores.nombre)
				? data.vendedores.nombre
				: ('Vendedor ' + vendedorId);
			$sel.append($('<option></option>').attr('value', vendedorId).text(nombre));
		}
		$sel.val(vendedorId);
	}

	window.aplicarVendedorPedidoDesdeCliente = aplicarVendedorPedidoDesdeCliente;
	window.aplicarVendedorDesdeCliente = aplicarVendedorPedidoDesdeCliente;

   	function asignaDatosCliente(cliente_id, flCambioCliente){
		if (!cliente_id || !$.isNumeric(cliente_id) || parseInt(cliente_id, 10) <= 0) {
			return;
		}

        $.get(carpetaBase+'/ventas/leercliente/'+cliente_id, function(data){
            const transporte_id = data.transporte_id == null ? 0 : data.transporte_id;
            const condicionventa_id = data.condicionventa_id;
            const descuento = data.descuento;
			const tiposuspension_id = data.tiposuspension_id;
			const lugarentrega = data.lugarentrega;
			const zonavta_id = data.zonavta_id;
			const transporteCliente = data.transportes || null;
			const codigotransporte = transporteCliente && transporteCliente.codigo ? transporteCliente.codigo : '';
			const nombretransporte = transporteCliente && transporteCliente.nombre ? transporteCliente.nombre : '';

			if (flCambioCliente)
			{
				aplicarVendedorPedidoDesdeCliente(data);
				$('#transporte_id').val(transporte_id);
				$('#codigotransporte').val(codigotransporte);
				$('#nombretransporte').val(nombretransporte);
				$('#condicionventa_id').val(condicionventa_id);
				$('#descuento').val(descuento);
				if ($('#fl_cliente_tiene_entrega').val() !== '1') {
					$('#lugarentrega').val(lugarentrega);
				}
				$('#zonavta_id').val(zonavta_id);
			}
			$('#tiposuspension_id').val(tiposuspension_id);
			// Lee zona de venta
			leeZonaVta();			
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

	function itemFacturado(remito_articulo_id)
	{
		var facturado = false;
		$("#tbody-tabla .ids").each(function () {
			if (String($(this).val()) === String(remito_articulo_id)) {
				var est = $(this).parents('tr').find('.estados').val();
				if (est && est !== 'P') {
					facturado = true;
				}
			}
		});
		return facturado;
	}

	function redondeaCaja(ptr, opcion)
	{
		let caja = $(ptr).parents("tr").find(".caja").val();
		let pieza = $(ptr).parents("tr").find(".pieza").val();
		let kilo = $(ptr).parents("tr").find(".kilo").val();
		let articulo_id = $(ptr).parents("tr").find(".articulo_id").val();
		let descuentoventa_id = $(ptr).parents("tr").find(".descuentoventa_id").val();
		var unidadmedida = $(ptr).parents("tr").find(".unidadmedida").val();

		$(ptr).parents("tr").find(".unidadmedida").val(unidadmedida);

		if (caja == '')
			caja = 0;
		if (pieza == '')
			pieza = 0;
		if (kilo == '')
			kilo = 0;
		if (descuentoventa_id == '')
			descuentoventa_id = 0;

		if (opcion > 0)
		{
			let url = carpetaBase+'/stock/redondeacaja/'+articulo_id+'/'+unidadmedida+'/'+caja+'/'+pieza+'/'+kilo+'/'+descuentoventa_id+'/'+opcion;

			$.get(url, function(data){
				if (typeof data.caja != 'string')
					var caja = redondearDecimales(data.caja, 2);
				else
					var caja = data.caja;

				if (typeof data.pieza != 'string')
					var pieza = redondearDecimales(data.pieza, 2);
				else	
					var pieza = data.pieza;

				if (typeof data.kilo != 'string')
					var kilo = redondearDecimales(data.kilo, 2);
				else
					var kilo = data.kilo;

				$(ptr).parents("tr").find(".caja").val(caja);
				$(ptr).parents("tr").find(".pieza").val(pieza);
				$(ptr).parents("tr").find(".kilo").val(kilo);

				TotalPedido();

				if (flAgregarRenglonTrasDescuentoPedido) {
					flAgregarRenglonTrasDescuentoPedido = false;
					agregarRenglonPedidoTrasDescuento($(ptr).parents('tr'));
				} else if (flSaltarFocusDescuentoPedido) {
					var $kilo = $(ptr).parents("tr").find(".kilo");

					if ($kilo.length && !$kilo.prop('readonly')) {
						$kilo.focus().select();
					}

					flSaltarFocusDescuentoPedido = false;
				} else {
					$(ptr).parents("tr").find(".descuentoventa_id").focus();
				}
			});
			setTimeout(() => {
			}, 300);
		} else if (flAgregarRenglonTrasDescuentoPedido) {
			flAgregarRenglonTrasDescuentoPedido = false;
			agregarRenglonPedidoTrasDescuento($(ptr).parents('tr'));
		}
	}

	function pesada()
	{
		$("#pesadaModal").modal('show');
	}

	$('#pesadaModal').on('show.bs.modal', function (event) {
		setTimeout(() => {
			$(this).find('#lecturaqrpesada').focus();

			$('#lecturaqrpesada').off('change');

			// Activa evento de borrar renglon
			$(document).on('click', '.eliminarpesada', borraRenglonPesada);
			
			$('#lecturaqrpesada').on('change', function () {
				// Asigna valores de pesada
				let codigoQR = $(this).val();
				let camposQR = codigoQR.split(";"); // caja id / sku / piezas / kilos / lote / vencimiento
				let flError = false;

				if (codigoQR != null)
				{
					// Busca si la caja ya fue leida
					$("#tbody-tabla-pesada .numerocajapesada").each(function(index) {
						let numerocajapesada = $(this).val();

						if (numerocajapesada == camposQR[0])
						{
							alert("La caja Nro."+camposQR[0]+" ya fue leida");
							flError = true;
							$('#lecturaqrpesada').val('');
							$('#lecturaqrpesada').focus();
						}
					});

					// Busca el articulo
					if (!flError)
					{
						let flAsigno = false;
						let totalKilo = 0;
						let totalPesada = 0;
						let descripcion;

						$("#tbody-tabla .articulo_id").each(function(index) {
							let articulo_id = $(this).val();
							let codigoarticulo = $(this).parents("tr").find(".codigoarticulo").val();
							let descripcionarticulo = $(this).parents("tr").find(".descripcionarticulo").val();
							let remito_articulo_id = $(this).parents("tr").find(".ids").val();
							let unidadmedida = $(this).parents("tr").find(".unidadmedida_id").find(':selected').text();
							let kilo = $(this).parents("tr").find(".kilo").val();
							let pesada = $(this).parents("tr").find(".pesada").val();

							if (!flAsigno)
							{
								if (codigoarticulo == camposQR[1] && parseFloat(kilo) > parseFloat(pesada)) // Si encuentra el articulo
								{
									let fecha = camposQR[5];
									let texto = fecha.replaceAll("-", "/");
									let fechas = texto.split("/");

									if (fechas[2].length == 1)
										fechas[2] = "0"+fechas[2];
									
									if (fechas[1].length == 1)
										fechas[1] = "0"+fechas[1];
									
									if (fechas[0].length == 1)
										fechas[0] = "0"+fechas[0];
									
									if (fechas[2].length >= 4)
										var fechaFormateada = fechas[2]+"-"+fechas[1]+"-"+fechas[0];
									else
										var fechaFormateada = "20"+fechas[2]+"-"+fechas[1]+"-"+fechas[0];

									agregaRenglonPesada(event);

									$('#pesadapedido-table').find('tr').last().find('.numerocajapesada').val(camposQR[0]);
									$('#pesadapedido-table').find('tr').last().find('.remito_articulo_id').val(remito_articulo_id);
									$('#pesadapedido-table').find('tr').last().find('.articulopesada_id').val(articulo_id);
									$('#pesadapedido-table').find('tr').last().find('.codigoarticulopesada').val(codigoarticulo);
									$('#pesadapedido-table').find('tr').last().find('.descripcionarticulopesada').val(descripcionarticulo);
									$('#pesadapedido-table').find('tr').last().find('.unidadmedidapesada').val(unidadmedida);
									$('#pesadapedido-table').find('tr').last().find('.piezapesada').val(camposQR[2]);
									$('#pesadapedido-table').find('tr').last().find('.kilopesada').val(camposQR[3]);
									$('#pesadapedido-table').find('tr').last().find('.lotepesada').val(camposQR[4]);
									$('#pesadapedido-table').find('tr').last().find('.fechavencimientopesada').val(fechaFormateada);

									// Asigna pesada
									let pesadaActual = $(this).parents("tr").find(".pesada").val();
									let pesadaTotal = parseFloat(pesadaActual) + parseFloat(camposQR[3]);

									$(this).parents("tr").find(".pesada").val(pesadaTotal);

									$('#lecturaqrpesada').val('');
									$('#lecturaqrpesada').focus();

									flAsigno = true;
								}
							}
							// Si existe el articulo acumula el kilaje
							if (codigoarticulo == camposQR[1])
							{
								totalKilo += parseFloat(kilo);
								totalPesada += parseFloat($(this).parents("tr").find(".pesada").val());
								descripcion = descripcionarticulo;
							}
						});
	//alert(flAsigno+" "+totalKilo+" "+totalPesada);
						if (!flAsigno)
						{
							if (parseFloat(totalKilo) < parseFloat(totalPesada) && totalKilo != 0)
								alert("Superó los kilos pedidos del artículo "+camposQR[1]+" "+descripcion+
										" - Kilos pedidos: "+totalKilo+" Kilos pesados: "+totalPesada);
							else
								alert('No existe el articulo');
						}
						$('#lecturaqrpesada').val('');
						$('#lecturaqrpesada').focus();
					}
				}
			});

		}, 300);
	});

	$('#aceptaPesadaModal').on('click', function () {
		$('#pesadaModal').modal('hide');
	});

	$('#pesadaModal').on('hidden.bs.modal', function () {
	});

	// Agrega renglon pesada
    function agregaRenglonPesada(event){
		if (event != undefined)
        	event.preventDefault();
        var renglon = $('#template-renglon-pesada').html();

        $("#tbody-tabla-pesada").append(renglon);
    }

    function borraRenglonPesada() {
        event.preventDefault();
		setTimeout(() => {
			if (confirm("¿Desea borrar renglon?"))
			{
				$(this).parents('tr').remove();
			}
		}, 300);
	}

	function controlDescuento(ptr)
	{
		let topedescuento = $("#topedescuento").val();
		var articuloDescuento_id = $(ptr).parents("tr").find(".articulo_id").val();
		var kiloActual = $(ptr).parents("tr").find(".kilo").val();
		var totalKiloConCargo = 0;
		var totalKiloSinCargo = parseFloat(kiloActual);
		var itemActual = $(ptr).parents("tr").find(".item").val();

		// Busca el articulo si existe
		$("#tbody-tabla .articulo_id").each(function(index) {
			let articulo_id = $(this).val();
			let sinCargo = $(this).parents("tr").find(".sincargo").val();
			var item = $(this).parents("tr").find(".item").val();

			// Suma si el articulo kilos sin descuento
			if (articulo_id == articuloDescuento_id && item != itemActual)
			{
				let kilo = $(this).parents("tr").find(".kilo").val();

				if (parseFloat(kilo) >= 0 && parseFloat(kilo) <= 99999999)
				{
					if (sinCargo == 'N')
						totalKiloConCargo += parseFloat(kilo);
					else
						totalKiloSinCargo += parseFloat(kilo);
				}
			}
		});

		if (totalKiloConCargo > 0)
		{
			let diferencia = parseFloat(totalKiloSinCargo / totalKiloConCargo * 100);

			if (diferencia > parseFloat(topedescuento))
			{
				var dif = redondearDecimales(diferencia, 2);

				alert("No puede tener artículos sin cargo por mas del "+topedescuento+"%. Kilos "+totalKiloConCargo+" Sin Cargo "+
						totalKiloSinCargo+" Diferencia "+dif+"%")

				return false;
			}
		}

		return true;
	}

	function armaSelectDescuentoVenta(ptr)
	{
		let categoria_secos_id = $("#categoria_secos_id").val();
		let subcategoria_tira_id = $("#subcategoria_tira_id").val();
			
		let categoria_id = $(ptr).parents("tr").find(".categoria_id").val();
		let subcategoria_id = $(ptr).parents("tr").find(".subcategoria_id").val();

		if (categoria_id == categoria_secos_id && subcategoria_id == subcategoria_tira_id)
		{
			// Elimina las opciones que no van para grupo 1 / tiras
			$(ptr).parents("tr").find('.descuentoventa_id option[value="3"]').remove();
			$(ptr).parents("tr").find('.descuentoventa_id option[value="4"]').remove();
		}

	}

	function reseteaDescuentoFila($tr)
	{
		$tr.find('.descuentoventa_id').val('').prop('disabled', false);
		$tr.find('.descuentoventaanterior_id').val('');
	}

	function marcaDescuento()
	{
		$("#tbody-tabla .descuentoventa_id").each(function(index) {
			let descuentoventa_id = $(this).val();

			if (descuentoventa_id > 0)
				$(this).attr('disabled', 'disabled');
		});
	}


	// F5 Anita: asignar kilos por reparto / porcentaje (solo remitos Bierzo)
	function enfocarRepartoAsignaKilosRemito()
	{
		var el = document.getElementById('asigna_kilos_codigotransporte');
		if (!el) {
			return;
		}
		el.focus();
		if (typeof el.select === 'function') {
			el.select();
		}
	}

	function abrirAsignarKilosRemito()
	{
		var tid = $('#transporte_id').val();
		$('#asigna_kilos_transporte_id').val(tid || '');
		$('#asigna_kilos_codigotransporte').val($('#codigotransporte').val() || '');
		$('#asigna_kilos_nombretransporte').val($('#nombretransporte').val() || '');
		$('#asigna_kilos_porcentaje').val('0');
		$('#asigna_kilos_aviso').addClass('d-none').text('');
		$('#asignarKilosRemitoModal').modal('show');
	}
	window.abrirAsignarKilosRemito = abrirAsignarKilosRemito;

	$(document)
		.off('shown.bs.modal.asignaKilos', '#asignarKilosRemitoModal')
		.on('shown.bs.modal.asignaKilos', '#asignarKilosRemitoModal', function () {
			if (typeof activa_eventos_consultatransporte === 'function') {
				activa_eventos_consultatransporte();
			}
			// Bootstrap reenfoca el modal; deferimos al campo Reparto
			setTimeout(enfocarRepartoAsignaKilosRemito, 50);
			setTimeout(enfocarRepartoAsignaKilosRemito, 200);
		});

	function esF5SinModificadoresRemito(e) {
		var ev = (e && e.originalEvent) ? e.originalEvent : e;
		if (!ev) {
			return false;
		}
		var esF5 = ev.key === 'F5' || ev.code === 'F5' || ev.keyCode === 116 || ev.which === 116;
		if (!esF5) {
			return false;
		}
		// Cualquier modificador → dejar al navegador (Ctrl+F5 / Shift+F5 / etc.)
		if (ev.ctrlKey || ev.metaKey || ev.altKey || ev.shiftKey) {
			return false;
		}
		if (typeof ev.getModifierState === 'function') {
			if (ev.getModifierState('Control') || ev.getModifierState('Meta')
				|| ev.getModifierState('Alt') || ev.getModifierState('Shift')
				|| ev.getModifierState('AltGraph')) {
				return false;
			}
		}
		return true;
	}

	// Native capture: evita que un jQuery viejo en caché siga robando Ctrl+F5
	if (!window._remitoAsignaKilosF5Bound) {
		window._remitoAsignaKilosF5Bound = true;
		document.addEventListener('keydown', function (e) {
			if (!esF5SinModificadoresRemito(e)) {
				return;
			}
			if (!$('#formgeneral').length || window.location.pathname.indexOf('/ventas/remito') === -1) {
				return;
			}
			var enModalAsigna = $('#asignarKilosRemitoModal').hasClass('show') || $('#asignarKilosRemitoModal').hasClass('in');
			var enConsultaTransporte = $('#consultatransporteModal').hasClass('show') || $('#consultatransporteModal').hasClass('in');
			if (enModalAsigna || enConsultaTransporte) {
				return;
			}
			e.preventDefault();
			e.stopPropagation();
			abrirAsignarKilosRemito();
		}, true);
	}

	$('#aceptaAsignarKilosRemito').on('click', function () {
		var transporteId = $('#asigna_kilos_transporte_id').val() || $('#transporte_id').val();
		var porcentaje = parseFloat($('#asigna_kilos_porcentaje').val() || '0');
		var token = $('#csrf_token').val();

		if (!transporteId || parseInt(transporteId, 10) < 1) {
			alert('Debe indicar un reparto');
			return;
		}

		$.post(carpetaBase + '/ventas/remito/asignarkilos', {
			transporte_id: transporteId,
			porcentaje: porcentaje,
			cliente_id: $('#cliente_id').val() || 0,
			_token: token
		}, function (data) {
			if (data.error) {
				$('#asigna_kilos_aviso').removeClass('d-none').text(data.error);
				return;
			}

			$('#asignarKilosRemitoModal').modal('hide');

			if ($('#transporte_id').val() === '' || $('#transporte_id').val() === '0') {
				$('#transporte_id').val(data.transporte_id);
			}

			if (data.fecha) {
				$('#fecha').val(data.fecha);
			}

			$('#origen_remito').val('asignakilos');

			$("#tbody-tabla").empty();
			(data.items || []).forEach(function (item) {
				if (typeof agregaRenglon === 'function') {
					agregaRenglon(null, { enfocarArticulo: false });
				}
				var $tr = $('#tbody-tabla tr').last();
				$tr.find('.articulo_id').val(item.articulo_id);
				$tr.find('.codigoarticulo').val(item.sku);
				$tr.find('.descripcionarticulo').val(item.descripcion);
				$tr.find('.kilo').val(item.kilo);
				$tr.find('.pieza').val(item.pieza);
				$tr.find('.caja').val(item.caja);
				$tr.find('.precio').val(item.precio || 0);
				if (item.incluyeimpuesto) {
					$tr.find('.incluyeimpuesto').val(item.incluyeimpuesto);
				}
				if (item.moneda_id) {
					$tr.find('.moneda_id').val(item.moneda_id);
				}
				if (item.listaprecio_id) {
					$tr.find('.listaprecio_id, .listaprecios_id').val(item.listaprecio_id);
				}
				if (item.unidadmedida_id) {
					$tr.find('.unidadmedida_id').val(item.unidadmedida_id);
				}
				$tr.find('.estados').val('P');
			});

			if (typeof TotalRemito === 'function') {
				TotalRemito();
			} else if (typeof TotalPedido === 'function') {
				TotalPedido();
			}

			var nItems = (data.items || []).length;
			var nComp = data.comprobantes || 0;
			var omitidos = data.omitidos || [];
			var msg = 'Kilos de Villafranca (' + nItems + ' ítems, ' + nComp + ' comprobantes). Revise y guarde el remito.';
			if (omitidos.length) {
				msg += ' Sin artículo ERP: ' + omitidos.join(', ');
			}
			if (window.toastr) {
				toastr.success(msg, 'F5');
			} else {
				alert(msg);
			}
		}).fail(function (xhr) {
			var msg = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'Error asignando kilos';
			$('#asigna_kilos_aviso').removeClass('d-none').text(msg);
		});
	});
