(function () {
	if (!window.FACTURA_NC_SIN_FACTURA || !window.FACTURA_NC_SIN_FACTURA.activo) {
		return;
	}

	var cfg = window.FACTURA_NC_SIN_FACTURA;
	var moduloAbiertoId = parseInt(cfg.moduloAbiertoId, 10) || 30;
	var tallesCargados = [];
	var preciosPorTalle = {};
	var metaPrecio = { listaprecio_id: '', incluyeimpuesto: '', moneda_id: '' };

	function ventaOrigenId() {
		return parseInt($('#venta_id').val() || '0', 10) || 0;
	}

	function metaTipo() {
		var $el = $('#tipotransaccion_id');
		if (typeof window.metaTipotransaccionVenta === 'function') {
			return window.metaTipotransaccionVenta($el);
		}
		if ($el.is('select')) {
			return $el.find('option:selected');
		}
		return $el;
	}

	function esProcesoNc() {
		var form = document.getElementById('formgeneral');
		if (form && form.getAttribute('data-factura-proceso') === 'nc') {
			return true;
		}
		if (typeof window.facturaTipoEsNotaCreditoMostrador === 'function') {
			return !!window.facturaTipoEsNotaCreditoMostrador();
		}
		var op = (metaTipo().attr('data-operacion') || '').toString().toUpperCase();
		return op === 'C';
	}

	function esNcSinFacturaActiva() {
		return esProcesoNc() && ventaOrigenId() <= 0;
	}

	function actualizarVisibilidadBotones() {
		var mostrar = esNcSinFacturaActiva();
		$('.factura-abrir-nc-sin-factura').toggleClass('d-none', !mostrar);
	}

	function $filaDestino() {
		var tr = $('#modalFacturaNcSinFactura').data('tr');
		return tr ? $(tr) : $();
	}

	function renumerar() {
		$('#tbody-tabla tr').each(function (i) {
			$(this).find('.item').val(i + 1);
		});
	}

	function recalcular() {
		var $cantidad = $('#tbody-tabla .cantidad').first();
		if ($cantidad.length) {
			$cantidad.trigger('change');
		}
	}

	function badgeTexto(combCodigo, talleNombre) {
		return 'Comb ' + (combCodigo || '') + ' · talle ' + (talleNombre || '');
	}

	function sumarParesModal() {
		var tot = 0;
		$('#factura_nc_sf_row_cantidades .nc-sf-cant-talle').each(function () {
			var n = parseFloat($(this).val() || '0');
			if (!isNaN(n) && n > 0) {
				tot += n;
			}
		});
		$('#factura_nc_sf_tot_pares').val(String(Math.round(tot)));
	}

	function limpiarModal() {
		tallesCargados = [];
		preciosPorTalle = {};
		metaPrecio = { listaprecio_id: '', incluyeimpuesto: '', moneda_id: '' };
		window.otCombinacionesLista = [];
		$('#factura_nc_sf_combinacion_id').val('');
		$('#factura_nc_sf_combinacion_codigo').val('');
		$('#factura_nc_sf_combinacion_nombre').val('');
		$('#factura_nc_sf_lote').val('');
		$('#factura_nc_sf_thead_talles').html('<th class="text-muted">Talles…</th>');
		$('#factura_nc_sf_row_cantidades').html('<td class="text-muted">Cargando…</td>');
		$('#factura_nc_sf_tot_pares').val('0');
		$('#factura_nc_sf_aviso_precio').text('');
	}

	function cargarCombinaciones(articuloId) {
		return $.get(carpetaBase + '/stock/leercombinacionesactivas/' + articuloId).then(function (data) {
			var lista = [];
			$.each(data || [], function (_i, value) {
				lista.push({
					id: value.id,
					codigo: value.codigo || '',
					nombre: value.nombre || ''
				});
			});
			window.otCombinacionesLista = lista;
			return lista;
		});
	}

	function cargarTalles() {
		return $.get(carpetaBase + '/stock/leertalles/' + moduloAbiertoId).then(function (data) {
			var talles = [];
			$.each(data || [], function (_i, modulo) {
				$.each(modulo.talles || [], function (_j, talle) {
					talles.push({
						id: parseInt(talle.id, 10) || 0,
						nombre: String(talle.nombre || talle.codigo || ''),
						codigo: String(talle.codigo || '')
					});
				});
			});
			tallesCargados = talles;
			pintarTalles(talles);
			return talles;
		});
	}

	function pintarTalles(talles) {
		var $head = $('#factura_nc_sf_thead_talles').empty();
		var $row = $('#factura_nc_sf_row_cantidades').empty();
		if (!talles.length) {
			$head.append('<th class="text-danger">Sin talles en m&oacute;dulo abierto</th>');
			$row.append('<td></td>');
			return;
		}
		talles.forEach(function (t, idx) {
			$head.append($('<th class="text-center p-1"></th>').text(t.nombre || t.codigo || t.id));
			var $inp = $('<input type="text" inputmode="numeric" class="form-control form-control-sm text-center nc-sf-cant-talle" style="width:2.4rem;padding:2px;">')
				.attr('data-talle-id', t.id)
				.attr('data-talle-nombre', t.nombre || t.codigo || '')
				.val('');
			if (idx === 0) {
				$inp.attr('autofocus', 'autofocus');
			}
			$row.append($('<td class="p-1 text-center"></td>').append($inp));
		});
		sumarParesModal();
	}

	function asignarPrecios(articuloId, talles) {
		preciosPorTalle = {};
		metaPrecio = { listaprecio_id: '', incluyeimpuesto: '', moneda_id: '' };
		var ids = talles.map(function (t) { return t.id; }).filter(function (id) { return id > 0; });
		if (!ids.length) {
			return $.Deferred().resolve().promise();
		}
		// asignaprecio no devuelve talle_id: el whereIn del backend suele salir por id ascendente.
		var idsOrdenados = ids.slice().sort(function (a, b) { return a - b; });
		$('#factura_nc_sf_aviso_precio').text('Consultando precios…');
		return $.get(carpetaBase + '/stock/asignaprecio/' + articuloId + '/' + idsOrdenados.join(',')).then(function (data) {
			var filas = $.map(data || [], function (value) { return [value]; });
			idsOrdenados.forEach(function (talleId, idx) {
				var row = filas[idx] || {};
				preciosPorTalle[talleId] = parseFloat(row.precio || 0) || 0;
				if (!metaPrecio.listaprecio_id && row.listaprecio_id) {
					metaPrecio.listaprecio_id = row.listaprecio_id;
					metaPrecio.incluyeimpuesto = row.incluyeimpuesto != null ? row.incluyeimpuesto : '';
					metaPrecio.moneda_id = row.moneda_id || '';
				}
			});
			$('#factura_nc_sf_aviso_precio').text('');
		}).fail(function () {
			$('#factura_nc_sf_aviso_precio').text('No se pudo leer el precio; cargalo a mano si hace falta.');
		});
	}

	function abrirModal($tr) {
		if ($tr.hasClass('item-concepto-venta')) {
			alert('La variante se carga en un renglón de mercadería.');
			return;
		}
		var articuloId = parseInt($tr.find('.articulo_id').val() || '0', 10) || 0;
		if (!(articuloId > 0)) {
			alert('Elegí primero el artículo del renglón.');
			$tr.find('.codigoarticulo').trigger('focus');
			return;
		}
		var sku = $tr.find('.codigoarticulo').val() || '';
		var desc = $tr.find('.descripcionarticulo').val() || '';
		limpiarModal();
		$('#factura_nc_sin_factura_articulo_txt').text((sku + ' — ' + desc).replace(/^ — | — $/g, '') || ('Artículo #' + articuloId));
		$('#modalFacturaNcSinFactura').data('tr', $tr.get(0));
		$('#modalFacturaNcSinFactura').data('articulo-id', articuloId);
		$('#modalFacturaNcSinFactura').data('sku', sku);
		$('#modalFacturaNcSinFactura').data('descripcion', desc);
		$('#modalFacturaNcSinFactura').modal('show');

		$.when(cargarCombinaciones(articuloId), cargarTalles())
			.done(function () {
				asignarPrecios(articuloId, tallesCargados);
				setTimeout(function () {
					$('#factura_nc_sf_combinacion_codigo').trigger('focus');
				}, 200);
			})
			.fail(function () {
				alert('No se pudieron cargar combinaciones o talles.');
				$('#modalFacturaNcSinFactura').modal('hide');
			});
	}

	function aplicarFila($tr, datos) {
		$tr.attr('data-nc-sf-llenando', '1');
		$tr.find('.ordentrabajo_id, .pedido_combinacion_id, .ot_grupo_indice').val('');
		$tr.find('.picking_pedido_combinacion_id, .picking_grupo_indice').val('');
		$tr.find('.articulo_id').val(datos.articulo_id || '');
		$tr.find('.articulo_id_previo, .articulo_id_previa').val(datos.articulo_id || '');
		$tr.find('.concepto_venta_id').val('');
		$tr.find('.codigoarticulo').val(datos.sku || '');
		$tr.find('.codigo_previo_articulo').val(datos.sku || '');
		$tr.find('.descripcionarticulo').val(datos.descripcion || '');
		$tr.find('.combinacion_id').val(datos.combinacion_id || '');
		$tr.find('.talle_id').val(datos.talle_id || '');
		$tr.find('.modulo_id').val(datos.modulo_id || moduloAbiertoId);
		$tr.find('.lote_stock').val(datos.lote || '');
		$tr.find('.cantidad').val(datos.cantidad != null ? datos.cantidad : '');
		$tr.find('.precio').val(datos.precio != null ? Number(datos.precio).toFixed(2) : '');
		if (datos.listaprecio_id) {
			$tr.find('.listaprecio_id').val(datos.listaprecio_id);
		}
		if (datos.incluyeimpuesto != null && datos.incluyeimpuesto !== '') {
			$tr.find('.incluyeimpuesto').val(datos.incluyeimpuesto);
		}
		if (datos.moneda_id) {
			$tr.find('.moneda_id').val(datos.moneda_id);
		}
		$tr.find('.cantidad, .precio').prop('readonly', true);
		$tr.find('.factura-ot-badge').text(badgeTexto(datos.combinacion_codigo, datos.talle_nombre));
		$tr.find('.factura-abrir-nc-sin-factura').addClass('tiene-nc-sf');
		$tr.removeClass('item-concepto-venta');
		$tr.removeAttr('data-nc-sf-llenando');
	}

	function confirmar() {
		var $tr = $filaDestino();
		if (!$tr.length) {
			return;
		}
		var articuloId = parseInt($('#modalFacturaNcSinFactura').data('articulo-id') || '0', 10) || 0;
		var combId = parseInt($('#factura_nc_sf_combinacion_id').val() || '0', 10) || 0;
		var combCodigo = String($('#factura_nc_sf_combinacion_codigo').val() || '').trim();
		var combNombre = String($('#factura_nc_sf_combinacion_nombre').val() || '').trim();
		if (!(combId > 0)) {
			alert('Elegí la combinación (código + F1 / lupa).');
			$('#factura_nc_sf_combinacion_codigo').trigger('focus');
			return;
		}
		var lote = String($('#factura_nc_sf_lote').val() || '').trim();
		if (lote === '0') {
			lote = '';
		}
		var filas = [];
		$('#factura_nc_sf_row_cantidades .nc-sf-cant-talle').each(function () {
			var cant = parseFloat($(this).val() || '0');
			if (isNaN(cant) || cant <= 0) {
				return;
			}
			var talleId = parseInt($(this).attr('data-talle-id') || '0', 10) || 0;
			var talleNombre = $(this).attr('data-talle-nombre') || '';
			if (!(talleId > 0)) {
				return;
			}
			var precio = preciosPorTalle[talleId];
			if (precio == null) {
				precio = 0;
			}
			filas.push({
				articulo_id: articuloId,
				sku: $('#modalFacturaNcSinFactura').data('sku') || '',
				descripcion: $('#modalFacturaNcSinFactura').data('descripcion') || '',
				combinacion_id: combId,
				combinacion_codigo: combCodigo || combNombre,
				talle_id: talleId,
				talle_nombre: talleNombre,
				modulo_id: moduloAbiertoId,
				lote: lote,
				cantidad: cant,
				precio: precio,
				listaprecio_id: metaPrecio.listaprecio_id,
				incluyeimpuesto: metaPrecio.incluyeimpuesto,
				moneda_id: metaPrecio.moneda_id
			});
		});
		if (!filas.length) {
			alert('Indicá al menos un talle con cantidad mayor a cero.');
			$('#factura_nc_sf_row_cantidades .nc-sf-cant-talle').first().trigger('focus');
			return;
		}

		aplicarFila($tr, filas[0]);
		for (var i = 1; i < filas.length; i++) {
			$('#agrega_renglon').trigger('click');
			aplicarFila($('#tbody-tabla tr').last(), filas[i]);
		}
		renumerar();
		actualizarVisibilidadBotones();
		$('#modalFacturaNcSinFactura').modal('hide');
		recalcular();
	}

	function validarSubmitNcSinFactura() {
		if (!esNcSinFacturaActiva()) {
			return true;
		}
		var ok = true;
		var $focus = null;
		$('#tbody-tabla tr').each(function () {
			var $tr = $(this);
			if ($tr.hasClass('nc-linea-excluida') || $tr.hasClass('item-concepto-venta')) {
				return;
			}
			var articuloId = parseInt($tr.find('.articulo_id').val() || '0', 10) || 0;
			if (!(articuloId > 0)) {
				return;
			}
			var combId = parseInt($tr.find('.combinacion_id').val() || '0', 10) || 0;
			var talleId = parseInt($tr.find('.talle_id').val() || '0', 10) || 0;
			if (!(combId > 0) || !(talleId > 0)) {
				ok = false;
				if (!$focus) {
					$focus = $tr.find('.factura-abrir-nc-sin-factura').length
						? $tr.find('.factura-abrir-nc-sin-factura')
						: $tr.find('.codigoarticulo');
				}
			}
		});
		if (!ok) {
			alert('Nota de crédito sin factura: cada renglón de mercadería debe tener combinación y talle. Usá el botón de cubos en la línea.');
			if ($focus && $focus.length) {
				$focus.trigger('focus');
			}
			return false;
		}
		return true;
	}

	window.ncSinFacturaValidarSubmit = validarSubmitNcSinFactura;
	window.facturaNcSinFacturaActualizarBotones = actualizarVisibilidadBotones;

	$(document).on('click', '.factura-abrir-nc-sin-factura', function () {
		if (!esNcSinFacturaActiva()) {
			return;
		}
		abrirModal($(this).closest('tr'));
	});

	$(document).on('input', '#factura_nc_sf_row_cantidades .nc-sf-cant-talle', sumarParesModal);

	$(document).on('click', '#factura_nc_sf_confirmar', confirmar);

	$(document).on('keydown', '#factura_nc_sf_combinacion_codigo', function (e) {
		if (e.key === 'F1' || e.which === 112) {
			e.preventDefault();
			$(this).closest('.tm-combinacion-campo').find('.consultacombinacion').trigger('click');
		}
	});

	$(document).on('combinacion:seleccionada', '#modalFacturaNcSinFactura .tm-combinacion-campo', function (_e, data) {
		if (!data || data.todas) {
			return;
		}
		setTimeout(function () {
			$('#factura_nc_sf_row_cantidades .nc-sf-cant-talle').first().trigger('focus');
		}, 50);
	});

	$(document).on('input', '#tbody-tabla .codigoarticulo', function () {
		var $tr = $(this).closest('tr');
		if ($tr.attr('data-nc-sf-llenando') === '1') {
			return;
		}
		if (String($tr.find('.combinacion_id').val() || '') === '' && String($tr.find('.talle_id').val() || '') === '') {
			return;
		}
		$tr.find('.combinacion_id, .talle_id, .modulo_id, .lote_stock').val('');
		$tr.find('.cantidad, .precio').prop('readonly', false);
		$tr.find('.factura-ot-badge').text('');
		$tr.find('.factura-abrir-nc-sin-factura').removeClass('tiene-nc-sf');
	});

	$(document).on('change', '#tipotransaccion_id, #venta_id', actualizarVisibilidadBotones);
	$(document).on('click', '#agrega_renglon', function () {
		setTimeout(actualizarVisibilidadBotones, 0);
	});

	var _actualizarFce = window.actualizarFceNcMostrador;
	if (typeof _actualizarFce === 'function') {
		window.actualizarFceNcMostrador = function () {
			_actualizarFce.apply(this, arguments);
			actualizarVisibilidadBotones();
		};
	}

	$('#modalFacturaNcSinFactura').on('hidden.bs.modal', function () {
		if (typeof window.liberarPantallaModalesBloqueados === 'function') {
			window.liberarPantallaModalesBloqueados();
		}
	});

	$(function () {
		if (typeof window.activa_eventos_consultacombinacion === 'function') {
			window.activa_eventos_consultacombinacion();
		}
		actualizarVisibilidadBotones();
		setTimeout(actualizarVisibilidadBotones, 400);
	});
})();
