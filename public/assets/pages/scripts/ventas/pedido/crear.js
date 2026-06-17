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
	var pedido_articulo_ids=[];
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

	function facturasGeneradasPedido(data) {
		var facturas = [];
		if (Array.isArray(data)) {
			for (var j = 0; j < data.length; j++) {
				var facturaItem = extraerFacturaDeItem(data[j]);
				if (facturaItem) {
					facturas.push(facturaItem);
				}
			}
			return facturas;
		}

		var facturaUnica = extraerFacturaDeItem(data);
		if (facturaUnica) {
			facturas.push(facturaUnica);
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
				if (factura) {
					comprobantes.push({ codigo: factura, ok: false, detalle: errorItem });
				}
				continue;
			}

			if (factura) {
				comprobantes.push({ codigo: factura, ok: true, detalle: null });
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
		var $siguiente = $tr.nextAll('tr.item-pedido').first();

		if (!$siguiente.length) {
			return null;
		}

		var codigo = $siguiente.find('.codigoarticulo')[0];

		return codigo && esCampoPedidoEnfocable(codigo) ? codigo : null;
	}

	function obtenerSiguienteCampoPedido(actual) {
		var $actual = $(actual);
		var $tr = $actual.closest('#itemspedido-table tr.item-pedido');

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

		event.preventDefault();

		var $target = $(event.target);
		var $tr = $target.closest('#itemspedido-table tr.item-pedido');
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

	function agregarRenglonPedidoTrasDescuento() {
		if (!puedeAgregarRenglonPedido()) {
			alert('No puede generar pedidos con mas de 42 ítems');
			return;
		}

		agregaRenglon();
	}

	function confirmarDescuentoPedidoConEnter(event) {
		if (!esSelectDescuentoPedido(event.target)) {
			return false;
		}

		event.preventDefault();
		event.stopPropagation();

		if (!event.target.value) {
			agregarRenglonPedidoTrasDescuento();
			return true;
		}

		flAgregarRenglonTrasDescuentoPedido = true;
		$(event.target).trigger('change');
		return true;
	}

	function esAtajoAgregarRenglonPedido(event) {
		return event.key === '+' || event.code === 'NumpadAdd' || (event.key === '=' && event.shiftKey);
	}

	function estaEnTablaArticulosPedido(el) {
		return !!(el && el.closest && el.closest('#itemspedido-table'));
	}

	function hayModalPedidoAbierto() {
		return document.querySelector('.modal.show, .modal.in') !== null;
	}

	function puedeAgregarRenglonPedido() {
		return $('#tbody-tabla .item-pedido').length < 42;
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
	
	function actualizarEstadoRequeridoLugarEntrega() {
		var obligatorio = $('#fl_cliente_tiene_entrega').val() === '1';
		var seleccionado = !!$('#cliente_entrega_id').val();

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
			fl_tiene_entrega = entr.length > 0;
			$('#fl_cliente_tiene_entrega').val(fl_tiene_entrega ? '1' : '0');

			if (!fl_tiene_entrega) {
				$('#cliente_entrega_id').val('');
				$('#entrega_nombre').val('');
				$('#div-cambiar-lugarentrega').hide();
				$('#lugarentrega').prop('readonly', false).attr('placeholder', '');

				$.get(carpetaBase+'/ventas/leercliente/'+cliente_id, function(clienteData){
					var datoscli = $.map(clienteData, function(value){
						return [value];
					});
					$('#lugarentrega').val(datoscli[6] || '');
				});
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

		$('#cliente_entrega_id').val(entrega.id);
		$('#cliente_entrega_id_previa').val(entrega.id);
		$('#entrega_nombre').val(entrega.nombre);
		$('#lugarentrega').val(entrega.nombre).prop('readonly', true);
		actualizarEstadoRequeridoLugarEntrega();
	}

	function renderFilasModalEntrega(entregas) {
		var html = '';

		$.each(entregas, function(index, value){
			html += '<tr>';
			html += '<td class="nombre">'+ (value.nombre || '') +'</td>';
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
		if ($('#fl_cliente_tiene_entrega').val() === '1' && !$('#cliente_entrega_id').val()) {
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
		let estadoPedido = $('#estadopedido').val();

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

		$("#codigocliente").focus();
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
				if (estadocliente > '0' && 
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
		$("#totalcajaspedido").val(totCaja.toFixed(2));		
		$(".pieza").each(function() {
			if (parseFloat($(this).val()) >= 1 && parseFloat($(this).val()) <= 999999)
				totPieza += parseFloat($(this).val());
		});
		$("#totalpiezaspedido").val(totPieza.toFixed(2));		
		$(".kilo").each(function() {
			if (parseFloat($(this).val()) >= 1 && parseFloat($(this).val()) <= 999999)
				totKilo += parseFloat($(this).val());
		});
		$("#totalkilospedido").val(totKilo.toFixed(2));
		$(".pesada").each(function() {
			if (parseFloat($(this).val()) >= 0.01 && parseFloat($(this).val()) <= 999999)
				totPesada += parseFloat($(this).val());
		});
		$("#totalkilospesados").val(totPesada.toFixed(2));
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

		// Si no tiene items agrega el primero
		if(!$('.item-pedido').length)
			agregaRenglon(event);

		let cliente_id = $("#cliente_id").val();
		if (cliente_id == CLIENTE_STOCK_ID)
			$("#divlote").show();
		else
			$("#divlote").hide();

		$("#codigocliente").focus();
    });

    function agregaRenglon(event){
		if (event != undefined)
        	event.preventDefault();
        var renglon = $('#template-renglon').html();

        $("#tbody-tabla").append(renglon);
        actualizaRenglones();

		activa_eventos(false);

		$('#itemspedido-table').find('tr').last().find('.codigoarticulo').focus();
	}

	// Anula item 
    function anulaItem() {
		let estadoPedido = $('#estadopedido').val();

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
		if (typeof validarLugarEntregaAntesGuardar === 'function' && !validarLugarEntregaAntesGuardar()) {
			return;
		}

		let itemId, otId;
		
		preciosfactura_txt = [];
		titulofactura_txt = [];
		pedido_articulo_ids = [];
		offFactura = 0;

		cliente_id = $("#cliente_id").val();
		
		$("#tbody-tabla .articulo_id").each(function(){

			itemId = $(this).parents('tr').find('.ids').val();
			
			if (!itemFacturado(itemId))
			{
				pedido_articulo_ids.push(itemId);

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
			$("#facturarPedidoModal").modal('show');

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
	$(document).on('shown.bs.modal', '#facturarPedidoModal', function() {
		var modal = $(this);

		modal.find('#tbody-tabla-factura').empty();
		modal.find('#tbody-tabla-total-factura').empty();
		modal.find('#alert-preview-factura-pedido').addClass('d-none').text('');

		modalActivo = "facturarPedidoModal";

		var numeroPedido = $('#codigopedido').val();
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
	
		const tiempoTranscurrido = Date.now();
		const hoy = new Date(tiempoTranscurrido);

		modal.find('#fechafactura').val(hoy.toISOString().substring(0,10));
		modal.find('#nombrecliente').val(nombrecliente);
		modal.find('.modal-title').text('Factura PEDIDO '+numeroPedido);
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
				let pedido_articulo_id = $(this).parents("tr").find(".ids").val();
				let unidadmedida = $(this).parents("tr").find(".unidadmedida_id").find(':selected').text();
				let unidadmedida_id = $(this).parents("tr").find(".unidadmedida_id").val();
				let caja = $(this).parents("tr").find(".caja").val();
				let pieza = $(this).parents("tr").find(".pieza").val();
				let pesada = $(this).parents("tr").find(".pesada").val();
				let descuentoventa_id = $(this).parents("tr").find(".descuentoventa_id").val();
				let precio = $(this).parents("tr").find(".precio").val();

				$('#factura-pedido-table').find('tr').last().find('.id_fac').val(pedido_articulo_id);
				$('#factura-pedido-table').find('tr').last().find('.articulo_id_fac').val(articulo_id);
				$('#factura-pedido-table').find('tr').last().find('.codigoarticulo_fac').val(codigoarticulo);
				$('#factura-pedido-table').find('tr').last().find('.descripcionarticulo_fac').val(descripcionarticulo);
				$('#factura-pedido-table').find('tr').last().find('.unidadmedida_fac').val(unidadmedida);
				$('#factura-pedido-table').find('tr').last().find('.unidadmedida_id_fac').val(unidadmedida_id);
				$('#factura-pedido-table').find('tr').last().find('.caja_fac').val(caja);
				$('#factura-pedido-table').find('tr').last().find('.pieza_fac').val(pieza);
				$('#factura-pedido-table').find('tr').last().find('.pesada_fac').val(pesada);
				$('#factura-pedido-table').find('tr').last().find('.descuentoventa_id_fac').val(descuentoventa_id);
				$('#factura-pedido-table').find('tr').last().find('.precio_fac').val(precio);
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
		$('#factura-pedido-table tr.item-factura').last().addClass('renglon-total-item-factura');

		let totalcajaspedido = $("#totalcajaspedido").val();
		let totalpiezaspedido = $("#totalpiezaspedido").val();
		let totalkilospesados = $("#totalkilospesados").val();

		$('#factura-pedido-table').find('tr').last().find('.descripcionarticulo_fac').val("Totales");
		$('#factura-pedido-table').find('tr').last().find('.descripcionarticulo_fac').css('fontWeight', 'bold');
		$('#factura-pedido-table').find('tr').last().find('.caja_fac').val(totalcajaspedido);
		$('#factura-pedido-table').find('tr').last().find('.pieza_fac').val(totalpiezaspedido);
		$('#factura-pedido-table').find('tr').last().find('.pesada_fac').val(totalkilospesados);
		$('#factura-pedido-table').find('tr').last().find('.caja_fac').css('fontWeight', 'bold');
		$('#factura-pedido-table').find('tr').last().find('.pieza_fac').css('fontWeight', 'bold');
		$('#factura-pedido-table').find('tr').last().find('.pesada_fac').css('fontWeight', 'bold');

		$('#cantidadbulto').val(totalcajaspedido);

		// Arma select de tipos de transacciones
		selectTipoTransaccion.empty();
		selectTipoTransaccion.append('<option value="">-- Seleccionar tipo de transacción --</option>');
		$.each(sel_tipotransaccion, function(obj, item) {
			if (tipoTransaccionDefault == item.id)
				op = 'selected="selected"';
			else
				op = '';
			selectTipoTransaccion.append('<option value="' + item.id + '"'+op+'>' + item.abreviatura + '-' + item.nombre + '</option>');
		});

		// Arma select de puntos de venta
		selectPuntoVenta.empty();
		selectPuntoVenta.append('<option value="">-- Seleccionar punto de venta --</option>');
		$.each(sel_puntoventa, function(obj, item) {
			if (puntoVentaDefault == item.id)
				op = 'selected="selected"';
			else
				op = '';
			selectPuntoVenta.append('<option value="' + item.id + '"'+op+'>' + item.codigo + '-' + item.nombre + '</option>');
		});

		// Arma select de puntos de venta del remito
		selectPuntoVentaRemito.empty();
		selectPuntoVentaRemito.append('<option value="">-- Seleccionar punto de venta --</option>');
		$.each(sel_puntoventaremito, function(obj, item) {
			if (puntoVentaRemitoDefault == item.id)
				op = 'selected="selected"';
			else
				op = '';
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
		let pedido_id = $('#pedido_id').val();

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

		// Calcula factura
		$.post(carpetaBase+"/ventas/calculafacturaporpedido",
		{
			pedido_id: pedido_id,
			pedido_articulo_ids: pedido_articulo_ids,
			cliente_id: cliente_id,
			fechafactura: fechafactura,
			descuentopie: descuentopie,
			descuentoimportepie: descuentoimportepie,
			descuentolinea: descuentolinea,
			formapago_id: formapago_id,
			incoterm_id: incoterm_id,
			mercaderia: mercaderia,
			totalcajaspedido: totalcajaspedido,
			_token: token
		},
		function(data){
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

			var lineasFactura = $('#tbody-tabla-factura tr').not('.renglon-total-item-factura').length;
			if (lineasFactura === 0) {
				var sinItems = 'No hay ítems pendientes en el pedido para mostrar en la factura.';
				modal.find('#alert-preview-factura-pedido')
					.removeClass('d-none alert-info alert-success')
					.addClass('alert alert-warning')
					.text(sinItems);
				if (window.toastr) {
					toastr.warning(sinItems, 'Preview de factura', { timeOut: 9000, closeButton: true });
				}
			}

			$.each(data.conceptostotales, function(index, item) {
					var esTotal = item.concepto === 'Total';
					if (item.importe != 0 || esTotal)
					{
						agregaRenglonTotalFactura();

						$('#total-factura-pedido-table').find('tr').last().find('.conceptototal').val(item.concepto);
						$('#total-factura-pedido-table').find('tr').last().find('.tasatotal').val(parseFloat(item.tasa).toFixed(2));
						$('#total-factura-pedido-table').find('tr').last().find('.importetotal').val(parseFloat(item.importe).toFixed(2));

						if (esTotal)
						{
							$('#total-factura-pedido-table').find('tr').last().find('.conceptototal').css('fontWeight', 'bold');
							$('#total-factura-pedido-table').find('tr').last().find('.importetotal').css('fontWeight', 'bold');
						}
					}
				});
				$('.tasatotal').css('text-align', 'right');
				$('.importetotal').css('text-align', 'right');
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
		let estadoActualPedido = $('#estadopedido').val();

		if (estadoActualPedido != 'Suspendido' && estadoActualPedido != 'Pendiente')
		{
			alert("No se puede suspender el pedido")
			return;
		}
		switch(estadoActualPedido)
		{
			case 'Suspendido':
				$('#estadopedido').val('Pendiente');	
				break;
			case 'Pendiente':
				$('#estadopedido').val('Suspendido');
				break;
		}

		// Actualiza estado del pedido
		let estadoPedido = $('#estadopedido').val();
		let pedido_id = $('#pedido_id').val();

		let listarUri = carpetaBase+"/ventas/actualizasolopedido/"+estadoPedido+"/"+pedido_id;

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
		$('#facturarPedidoModal').modal('hide');
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
		let pedido_id = $('#pedido_id').val();
		let estadoPedido = $('#estadopedido').val();

		if (estadoPedido != 'Pendiente')
		{
			alert("No puede facturar un pedido que no este pendiente");
			$('#facturarOrdenventaModal').modal('hide');
			return;
		}

		if (puntoventaremito_id < 1)
		{
			alert("No puede facturar sin punto de venta del remito");
			$('#facturarOrdenventaModal').modal('hide');
			return;
		}

		if (actividad_arca_id == '')
		{
			alert('No puede facturar sin asignar actividad ARCA');
			$('#facturarOrdenventaModal').modal('hide');
			return;
		}

		if (cantidadbulto < 1 || cantidadbulto > 999999)
		{
			alert("No permite facturar sin cargar bultos");
			return false;
		}
		
		$('#facturarPedidoModal').modal('hide');

		iniciarProcesoFacturaPedido();

		$.ajax({
			url: carpetaBase + '/ventas/facturarporpedido',
			method: 'POST',
			dataType: 'json',
			data: {
				pedido_articulo_ids: pedido_articulo_ids,
				cliente_id: cliente_id,
				pedido_id: pedido_id,
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
						return;
					}

					$('#facturarPedidoModal').modal('hide');
					$('#estadopedido').val('Facturado');
					TotalPedido();
					window.history.go(0);
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
				});
			});
	});

	$('#facturarPedidoModal').on('hidden.bs.modal', function () {

		// Inicializa variables modal
		talles_txt = "";
		medidas_txt = "";
		precios_txt = "";
		tallesid_txt = "";
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

		completarCliente_Entrega(cliente_id);
		asignaDatosCliente(cliente_id, true);
		setTimeout(() => {
			muestraTipoSuspension();			
		}, 1500);
	}

   	function asignaDatosCliente(cliente_id, flCambioCliente){
        $.get(carpetaBase+'/ventas/leercliente/'+cliente_id, function(data){
            var datoscli = $.map(data, function(value, index){
                return [value];
            });
            const vendedor_id = datoscli[1] == null ? 1 : datoscli[1];
            const transporte_id = datoscli[2] == null ? 0 : datoscli[2];
            const condicionventa_id = datoscli[3];
            const descuento = datoscli[4];
			const tiposuspension_id = datoscli[5];
			const lugarentrega = datoscli[6];
			const zonavta_id = datoscli[7];
			const codigotransporte = datoscli[11].codigo;
			const nombretransporte = datoscli[11].nombre;

			if (flCambioCliente)
			{
				if (vendedor_id == null)
					vendedor_id = 1;

				$('#vendedor_id').val(vendedor_id);
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

	function itemFacturado(pedido_articulo_id)
	{
		// Busca si tiene factura asociada
		var listarUri = carpetaBase+"/ventas/estadoitempedido/"+pedido_articulo_id;
            
		$.get(listarUri, function(data){
							
			if (data.numerofactura != -1 && data.numerofactura != -2 && data.numerofactura != -3)
				return true;
			else 
			{
				if ($data.numerofactura == -2)
				{
					alert('OT no esta terminada');
				}
				return false;
			}

		});

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
					agregarRenglonPedidoTrasDescuento();
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
							let pedido_articulo_id = $(this).parents("tr").find(".ids").val();
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
									$('#pesadapedido-table').find('tr').last().find('.pedido_articulo_id').val(pedido_articulo_id);
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
