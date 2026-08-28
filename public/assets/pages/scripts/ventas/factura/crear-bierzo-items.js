// Grilla de ítems estilo pedido (EL BIERZO) en factura/crear

(function () {
	'use strict';

	if (!window.FL_FACTURA_LAYOUT_PEDIDO) {
		return;
	}

	var flSaltarFocusDescuentoFactura = false;
	var flAgregarRenglonTrasDescuentoFactura = false;

	function sincronizarCantidadRenglon($tr) {
		var kilo = ($tr.find('.kilo').val() || '').toString().replace(',', '.');
		$tr.find('.cantidad').val(kilo);
	}

	function sincronizarCantidadesItemsFactura() {
		$('#tbody-tabla tr.item-pedido').each(function () {
			sincronizarCantidadRenglon($(this));
		});
	}

	window.sincronizarCantidadesItemsFactura = sincronizarCantidadesItemsFactura;

	window.asignaPrecio = function (ptr, Particulo_id, Ptalle_id) {
		if ($('#formgeneral').attr('data-factura-proceso') === 'nc') {
			return;
		}

		var cliente_id = $('#cliente_id').val();
		var codigoCliente = ($('#codigocliente').val() || '').toString().trim();
		var articulo_id = $(ptr).parents('tr').find('.articulo_id').val();

		if ((!codigoCliente && !cliente_id) || !articulo_id) {
			return;
		}

		var claveCliente = codigoCliente || cliente_id;

		$.get(carpetaBase + '/stock/asignapreciocliente/' + articulo_id + '/' + encodeURIComponent(claveCliente), function (data) {
			var prec = $.map(data, function (value) {
				return [value];
			});
			var precio, listaprecio_id, incluyeimpuesto, moneda_id;

			$.each(prec, function (index, value) {
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

			if (typeof precio !== 'string') {
				$(ptr).parents('tr').find('.precio').val(redondearDecimales(precio, 2));
			} else {
				$(ptr).parents('tr').find('.precio').val(precio);
			}

			$(ptr).parents('tr').find('.listaprecio_id').val(listaprecio_id);
			$(ptr).parents('tr').find('.incluyeimpuesto').val(incluyeimpuesto);
			$(ptr).parents('tr').find('.moneda_id').val(moneda_id);

			if (typeof calculaFactura === 'function') {
				calculaFactura();
			}
		});
	};

	window.armaSelectDescuentoVenta = function (ptr) {
		var categoria_secos_id = $('#categoria_secos_id').val();
		var subcategoria_tira_id = $('#subcategoria_tira_id').val();
		var categoria_id = $(ptr).parents('tr').find('.categoria_id').val();
		var subcategoria_id = $(ptr).parents('tr').find('.subcategoria_id').val();

		if (categoria_id == categoria_secos_id && subcategoria_id == subcategoria_tira_id) {
			$(ptr).parents('tr').find('.descuentoventa_id option[value="3"]').remove();
			$(ptr).parents('tr').find('.descuentoventa_id option[value="4"]').remove();
		}
	};

	function unidadMedidaCajaFactura(unidadmedida) {
		var um = (unidadmedida || '').toString().trim().toUpperCase();
		return um === 'CAJ' || um === 'CJ' || um === 'C';
	}

	function opcionCantidadSegunUnidadFactura(unidadmedida) {
		if (unidadMedidaCajaFactura(unidadmedida)) {
			return 1;
		}
		if (typeof unidadMedidaEsKilos === 'function' && unidadMedidaEsKilos(unidadmedida)) {
			return 3;
		}
		return 2;
	}

	function marcarCambioCantidadFactura($el) {
		$el.data('facturaCambioLock', Date.now());
		setTimeout(function () {
			$el.removeData('facturaCambioLock');
		}, 400);
	}

	function esCambioCantidadFacturaDuplicado($el) {
		var t = $el.data('facturaCambioLock');
		return !!t && (Date.now() - t) < 400;
	}

	function dispararCambioCampoFactura(el) {
		$(el).trigger('change');
	}

	function redondeaCajaFactura(ptr, opcion) {
		var caja = $(ptr).parents('tr').find('.caja').val();
		var pieza = $(ptr).parents('tr').find('.pieza').val();
		var kilo = $(ptr).parents('tr').find('.kilo').val();
		var articulo_id = $(ptr).parents('tr').find('.articulo_id').val();
		var descuentoventa_id = $(ptr).parents('tr').find('.descuentoventa_id').val();
		var unidadmedida = $(ptr).parents('tr').find('.unidadmedida').val();

		if (caja === '') {
			caja = 0;
		}
		if (pieza === '') {
			pieza = 0;
		}
		if (kilo === '') {
			kilo = 0;
		}
		if (descuentoventa_id === '') {
			descuentoventa_id = 0;
		}

		if (opcion > 0 && articulo_id > 0) {
			var url = carpetaBase + '/stock/redondeacaja/' + articulo_id + '/' + encodeURIComponent(unidadmedida || 'KG') + '/' + caja + '/' + pieza + '/' + kilo + '/' + descuentoventa_id + '/' + opcion + '?sin_redondeo_caja=1';

			$.get(url, function (data) {
				var cajaR = typeof data.caja !== 'string' ? redondearDecimales(data.caja, 2) : data.caja;
				var piezaR = typeof data.pieza !== 'string' ? redondearDecimales(data.pieza, 2) : data.pieza;
				var kiloR = typeof data.kilo !== 'string' ? redondearDecimales(data.kilo, 2) : data.kilo;

				$(ptr).parents('tr').find('.caja').val(cajaR);
				$(ptr).parents('tr').find('.pieza').val(piezaR);
				$(ptr).parents('tr').find('.kilo').val(kiloR);

				sincronizarCantidadRenglon($(ptr).parents('tr'));

				if (flAgregarRenglonTrasDescuentoFactura) {
					flAgregarRenglonTrasDescuentoFactura = false;
					agregarRenglonFacturaTrasDescuento($(ptr).parents('tr'));
				} else if (flSaltarFocusDescuentoFactura) {
					var $kilo = $(ptr).parents('tr').find('.kilo');

					if ($kilo.length && !$kilo.prop('readonly')) {
						$kilo.focus().select();
					}

					flSaltarFocusDescuentoFactura = false;
				} else {
					$(ptr).parents('tr').find('.descuentoventa_id').focus();
				}

				if (typeof calculaFactura === 'function') {
					calculaFactura();
				}
			});
		} else if (flAgregarRenglonTrasDescuentoFactura) {
			flAgregarRenglonTrasDescuentoFactura = false;
			agregarRenglonFacturaTrasDescuento($(ptr).parents('tr'));
		}
	}

	window.activa_eventosFacturaBierzo = function (flInicio) {
		$(document).off('change.ocPedidoCodigoLocal', '.codigoarticulolocal');
		$('.unidadmedida_id').off('change.facturaBierzo');
		$('.caja').off('change.facturaBierzo');
		$('.pieza').off('change.facturaBierzo');
		$('.kilo').off('change.facturaBierzo');
		$('.descuentoventa_id').off('change.facturaBierzo');
		$('.precio').off('change.facturaBierzo');
		$('#puntoventa_id').off('change.facturaBierzo');

		activa_eventos_consultacliente();
		activa_eventos_consultaarticulo();
		if ($('#codigotransporte').length && typeof activa_eventos_consultatransporte === 'function') {
			activa_eventos_consultatransporte();
		}

		$(document).off('change.ocPedidoCodigoLocal', '.codigoarticulolocal').on('change.ocPedidoCodigoLocal', '.codigoarticulolocal', function (event) {
			event.preventDefault();
			var articulo_ant = $(this).parents('tr').find('.articulo_id_previa').val();
			var articulo_nuevo = $(this).parents('tr').find('.articulo_id').val();

			if (articulo_nuevo != articulo_ant) {
				$(this).parents('tr').find('.caja').val('');
				$(this).parents('tr').find('.pieza').val('');
				$(this).parents('tr').find('.kilo').val('');
				$(this).parents('tr').find('.descuentoventa_id').val('');
				$(this).parents('tr').find('.articulo_id_previa').val(articulo_nuevo);
			}
		});

		$('.unidadmedida_id').on('change.facturaBierzo', function () {
			$(this).parents('tr').find('.caja').val('');
			$(this).parents('tr').find('.pieza').val('');
			$(this).parents('tr').find('.kilo').val('');
			$(this).parents('tr').find('.descuentoventa_id').val('');

			var unidadmedida = $(this).find('option:selected').text();
			$(this).parents('tr').find('.unidadmedida').val(unidadmedida);

			if (typeof enfocarCantidadLineaArticulo === 'function') {
				enfocarCantidadLineaArticulo($(this).parents('tr'), unidadmedida);
			} else if (typeof unidadMedidaEsKilos === 'function' && unidadMedidaEsKilos(unidadmedida)) {
				$(this).parents('tr').find('.kilo').focus();
			} else if (unidadmedida.toUpperCase() === 'CAJ') {
				$(this).parents('tr').find('.caja').focus();
			} else if (unidadmedida.toUpperCase() === 'UN') {
				$(this).parents('tr').find('.pieza').focus();
			} else {
				$(this).parents('tr').find('.kilo').focus();
			}
		});

		$('.caja').on('change.facturaBierzo', function () {
			if (esCambioCantidadFacturaDuplicado($(this))) {
				return;
			}
			marcarCambioCantidadFactura($(this));
			redondeaCajaFactura(this, 1);
		});

		$('.pieza').on('change.facturaBierzo', function () {
			if (esCambioCantidadFacturaDuplicado($(this))) {
				return;
			}
			marcarCambioCantidadFactura($(this));
			redondeaCajaFactura(this, 2);
		});

		$('.kilo').on('change.facturaBierzo', function () {
			if (esCambioCantidadFacturaDuplicado($(this))) {
				return;
			}
			marcarCambioCantidadFactura($(this));
			redondeaCajaFactura(this, 3);
		});

		$('.descuentoventa_id').on('change.facturaBierzo', function () {
			var categoria_secos_id = $('#categoria_secos_id').val();
			var subcategoria_maquina_id = $('#subcategoria_maquina_id').val();
			var subcategoria_tira_id = $('#subcategoria_tira_id').val();
			var categoria_id = $(this).parents('tr').find('.categoria_id').val();
			var subcategoria_id = $(this).parents('tr').find('.subcategoria_id').val();
			var pieza = $(this).parents('tr').find('.pieza').val();
			var selectDescuento = $(this).parents('tr').find('.descuentoventa_id option:selected').text();
			var cantidadDescuento = selectDescuento.substring(0, 2);

			if (categoria_id == categoria_secos_id && subcategoria_id == subcategoria_maquina_id &&
				parseFloat(pieza) < parseFloat(cantidadDescuento)) {
				flAgregarRenglonTrasDescuentoFactura = false;
				alert('No puede usar descuento mayor a las piezas pedidas. Descuento Piezas ' + cantidadDescuento + ' Piezas ' + pieza);
				$(this).parents('tr').find('.descuentoventa_id').val('');
				return;
			}

			$(this).parents('tr').find('.descuentoventaanterior_id').val($(this).val());
			redondeaCajaFactura(this, opcionCantidadSegunUnidadFactura($(this).parents('tr').find('.unidadmedida').val()));
		});

		$('.precio').on('change.facturaBierzo', function () {
			if (typeof calculaFactura === 'function') {
				calculaFactura();
			}
		});

		$('#puntoventa_id').on('change.facturaBierzo', function (event) {
			event.preventDefault();
			var puntoventa_id = $(this).val();

			if (!puntoventa_id) {
				return;
			}

			$.get(carpetaBase + '/ventas/leeunpuntoventa/' + puntoventa_id, function (data) {
				$('#actividad_arca_id').val(data.actividad_arca_id);
				$('#actividad_arca_id').attr('readonly', data.actividad_arca_id > 0);
			});

			if (typeof leePuntoVenta === 'function') {
				leePuntoVenta(puntoventa_id);
			}

			if (typeof guardarPreferenciasFactura === 'function') {
				guardarPreferenciasFactura();
			}
		});
	};

	window.enfocarPrimerCampoItemsFactura = function () {
		var $codigo = $('#itemspedido-table tr.item-pedido .codigoarticulo').first();

		if ($codigo.length) {
			$codigo.focus();
		}
	};

	// Enter como Tab (misma lógica que pedidos)
	function esCampoFacturaEnfocable(el) {
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

	function obtenerCamposFacturaEnfocables() {
		var nodos = document.querySelectorAll(
			'#formgeneral input:not([type="hidden"]):not([readonly]):not([disabled]), ' +
			'#formgeneral select:not([disabled])'
		);
		return Array.prototype.filter.call(nodos, esCampoFacturaEnfocable);
	}

	function focuseCampoFactura(el) {
		if (!el || !esCampoFacturaEnfocable(el)) {
			return;
		}
		el.focus();
		if (el.tagName === 'INPUT' && (el.type === 'text' || el.type === '')) {
			el.select();
		}
	}

	function codigoArticuloSiguienteRenglonFactura($tr) {
		var $siguiente = $tr.nextAll('tr.item-pedido').first();
		if (!$siguiente.length) {
			return null;
		}
		var codigo = $siguiente.find('.codigoarticulo')[0];
		return codigo && esCampoFacturaEnfocable(codigo) ? codigo : null;
	}

	function obtenerSiguienteCampoFactura(actual) {
		var $actual = $(actual);
		var $tr = $actual.closest('#itemspedido-table tr.item-pedido');

		if ($tr.length) {
			if (actual.classList.contains('codigoarticulo') || actual.classList.contains('unidadmedida_id')) {
				return 'defer';
			}
			if (actual.classList.contains('caja') || actual.classList.contains('pieza')) {
				var $kilo = $tr.find('.kilo');
				if ($kilo.length && esCampoFacturaEnfocable($kilo[0])) {
					return 'redondea:kilo';
				}
				return 'redondea:descuento';
			}
			if (actual.classList.contains('kilo')) {
				return 'redondea:descuento';
			}
			if (actual.classList.contains('descuentoventa_id')) {
				return codigoArticuloSiguienteRenglonFactura($tr);
			}
		}

		var campos = obtenerCamposFacturaEnfocables();
		var indice = campos.indexOf(actual);
		if (indice >= 0 && indice < campos.length - 1) {
			return campos[indice + 1];
		}
		return null;
	}

	function esSelectDescuentoFactura(el) {
		return !!(el && el.tagName === 'SELECT' && el.classList.contains('descuentoventa_id') && !el.disabled);
	}

	function opcionesNavegablesDescuentoFactura(select) {
		return Array.prototype.filter.call(select.options, function (opt) {
			return !opt.disabled;
		});
	}

	function navegarDescuentoFacturaConFlechas(event) {
		if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') {
			return;
		}
		if (!esSelectDescuentoFactura(event.target)) {
			return;
		}

		event.preventDefault();
		event.stopPropagation();

		var select = event.target;
		var opts = opcionesNavegablesDescuentoFactura(select);

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

	function agregarRenglonFacturaTrasDescuento($tr) {
		if ($tr && $tr.length) {
			var $siguiente = $tr.nextAll('tr.item-pedido').first();
			var skuSiguiente = $siguiente.length
				? ($siguiente.find('.codigoarticulo').val() || '').trim()
				: '';

			if ($siguiente.length && !skuSiguiente) {
				var codigo = $siguiente.find('.codigoarticulo')[0];
				if (codigo && esCampoFacturaEnfocable(codigo)) {
					focuseCampoFactura(codigo);
					return;
				}
			}
		}

		if (typeof agregaRenglon === 'function') {
			agregaRenglon();
		}
	}

	function confirmarDescuentoFacturaConEnter(event) {
		if (!esSelectDescuentoFactura(event.target)) {
			return false;
		}

		event.preventDefault();
		event.stopPropagation();

		var $tr = $(event.target).closest('#itemspedido-table tr.item-pedido');

		if (!event.target.value) {
			agregarRenglonFacturaTrasDescuento($tr);
			return true;
		}

		flAgregarRenglonTrasDescuentoFactura = true;
		$(event.target).trigger('change');
		return true;
	}

	function hayModalFacturaAbierto() {
		return document.querySelector('.modal.show, .modal.in') !== null;
	}

	function eliminarRenglonFacturaSkuVacio($tr) {
		if (!$tr || !$tr.length) {
			return;
		}

		var $filas = $('#tbody-tabla tr.item-pedido');
		if ($filas.length <= 1) {
			var codigoUnico = $tr.find('.codigoarticulo')[0];
			if (codigoUnico && esCampoFacturaEnfocable(codigoUnico)) {
				focuseCampoFactura(codigoUnico);
			}
			return;
		}

		var $siguiente = $tr.nextAll('tr.item-pedido').first();
		var $previo = $tr.prevAll('tr.item-pedido').first();

		$tr.remove();

		if (typeof actualizaRenglones === 'function') {
			actualizaRenglones();
		}
		if (typeof calculaFactura === 'function') {
			calculaFactura();
		}

		if ($siguiente.length) {
			var codigoSig = $siguiente.find('.codigoarticulo')[0];
			if (codigoSig && esCampoFacturaEnfocable(codigoSig)) {
				focuseCampoFactura(codigoSig);
			}
			return;
		}

		var descuentoPrev = $previo.find('.descuentoventa_id')[0];
		if (descuentoPrev && esCampoFacturaEnfocable(descuentoPrev)) {
			focuseCampoFactura(descuentoPrev);
			return;
		}

		var codigoPrev = $previo.find('.codigoarticulo')[0];
		if (codigoPrev && esCampoFacturaEnfocable(codigoPrev)) {
			focuseCampoFactura(codigoPrev);
		}
	}

	function avanzarCampoFacturaConEnter(event) {
		if (event.key !== 'Enter' && event.which !== 13) {
			return;
		}
		if (!esCampoFacturaEnfocable(event.target)) {
			return;
		}
		if (confirmarDescuentoFacturaConEnter(event)) {
			return;
		}

		var $target = $(event.target);
		var $tr = $target.closest('#itemspedido-table tr.item-pedido');

		if ($tr.length && event.target.classList.contains('codigoarticulo')
			&& !(event.target.value || '').trim()
			&& !hayModalFacturaAbierto()) {
			event.preventDefault();
			event.stopPropagation();
			eliminarRenglonFacturaSkuVacio($tr);
			return;
		}

		event.preventDefault();

		var accion = obtenerSiguienteCampoFactura(event.target);

		if (accion === 'defer') {
			event.target.blur();
			return;
		}

		if (typeof accion === 'string' && accion.indexOf('redondea:') === 0) {
			flSaltarFocusDescuentoFactura = accion === 'redondea:kilo';
			if (accion === 'redondea:descuento' && !$tr.length) {
				flSaltarFocusDescuentoFactura = false;
			}
			// Enter en kilos/caja no cambia el valor (lo llenó el redondeo): blur no dispara
			// change y el foco se queda. trigger + lock evita el segundo change del blur.
			dispararCambioCampoFactura(event.target);
			return;
		}

		if (accion) {
			focuseCampoFactura(accion);
		}
	}

	window.initFacturaEnterNavigation = function () {
		var form = document.getElementById('formgeneral');
		if (!form || form.dataset.facturaEnterNav) {
			return;
		}
		form.dataset.facturaEnterNav = '1';
		form.addEventListener('keydown', navegarDescuentoFacturaConFlechas, true);
		form.addEventListener('keydown', avanzarCampoFacturaConEnter, true);
	};

})();
