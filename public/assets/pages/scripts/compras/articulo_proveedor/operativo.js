/**
 * Catálogo articulo_proveedor en grillas RQ / OC / recepción.
 *
 * Solo se activa si el artículo tiene filas en articulo_proveedor.
 * Si no hay catálogo → no abre modal, no cambia descripción/precio/UM (queda el maestro).
 * Depende de carpetaBase y del modal #modalElegirArticuloProveedor.
 */
(function (window, $) {
	'use strict';

	function apBase() {
		return (typeof carpetaBase !== 'undefined' && carpetaBase) ? String(carpetaBase).replace(/\/$/, '') : '';
	}

	function apEsc(s) {
		return String(s == null ? '' : s)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function apFmtNum(n, dec) {
		var x = parseFloat(n);
		if (!isFinite(x)) {
			return '';
		}
		var d = dec == null ? 4 : dec;
		var t = parseFloat(x.toFixed(d));
		return String(t);
	}

	/**
	 * @param {object} opts
	 * @param {number|string} opts.articuloId
	 * @param {number|string} [opts.proveedorId]
	 * @param {boolean} [opts.restrictivo]
	 * @returns {JQuery.jqXHR}
	 */
	function apFetchProveedoresCompra(opts) {
		opts = opts || {};
		var articuloId = parseInt(opts.articuloId, 10) || 0;
		var qs = [];
		var pid = parseInt(opts.proveedorId, 10) || 0;
		if (pid > 0) {
			qs.push('proveedor_id=' + encodeURIComponent(String(pid)));
		}
		if (opts.restrictivo) {
			qs.push('restrictivo=1');
		}
		var url = apBase() + '/stock/articulo/' + articuloId + '/proveedores-compra';
		if (qs.length) {
			url += '?' + qs.join('&');
		}
		return $.getJSON(url);
	}

	/**
	 * Aplica datos del catálogo a la fila.
	 * Espera hiddens/clases: .linea-proveedor-id, .linea-articulo-proveedor-id,
	 * .linea-codigo-articulo-proveedor, .linea-coef-conversion, .linea-um-compra-abrev,
	 * .descripcionarticulo, .precio-linea, moneda select, hint conversión.
	 */
	function apAplicarAFila($tr, item, dataArticulo) {
		if (!$tr || !$tr.length) {
			return;
		}
		item = item || null;

		if (!item) {
			$tr.find('.linea-proveedor-id').val('');
			$tr.find('.linea-articulo-proveedor-id').val('');
			$tr.find('.linea-codigo-articulo-proveedor').val('');
			$tr.find('.linea-coef-conversion').val('');
			$tr.find('.linea-um-compra-abrev').val('');
			$tr.find('.linea-proveedor-etiqueta').text('').attr('title', '');
			apActualizarHintConversion($tr);
			return;
		}

		$tr.find('.linea-proveedor-id').val(item.proveedor_id || '');
		$tr.find('.linea-articulo-proveedor-id').val(item.articulo_proveedor_id || '');
		$tr.find('.linea-codigo-articulo-proveedor').val(item.codigo_articulo_proveedor || '');
		$tr.find('.linea-coef-conversion').val(item.coeficiente_conversion != null ? item.coeficiente_conversion : 1);
		$tr.find('.linea-um-compra-abrev').val(item.um_compra_abreviatura || '');

		var etiq = (item.proveedor_codigo || '') + (item.proveedor_nombre ? ' ' + item.proveedor_nombre : '');
		etiq = $.trim(etiq);
		$tr.find('.linea-proveedor-etiqueta')
			.text(etiq || ('#' + (item.proveedor_id || '')))
			.attr('title', etiq + (item.codigo_articulo_proveedor ? ' · Cód: ' + item.codigo_articulo_proveedor : ''));

		var nombreProv = $.trim(item.nombre_articulo_proveedor || '');
		if (nombreProv) {
			$tr.find('.descripcionarticulo').val(nombreProv);
		} else if (dataArticulo && dataArticulo.descripcion) {
			$tr.find('.descripcionarticulo').val(dataArticulo.descripcion);
		}

		if (item.tiene_precio && item.precio != null && isFinite(parseFloat(item.precio))) {
			var $precio = $tr.find('.precio-linea');
			if ($precio.length && !$precio.prop('readonly')) {
				$precio.val(apFmtNum(item.precio, 4));
			}
			if (item.moneda_id) {
				var $mon = $tr.find('select[name="moneda_linea_ids[]"], .oc-moneda-linea');
				if ($mon.length && !$mon.prop('disabled')) {
					$mon.val(String(item.moneda_id));
				}
			}
		}

		apActualizarHintConversion($tr);
	}

	function apActualizarHintConversion($tr) {
		var coef = parseFloat($tr.find('.linea-coef-conversion').val()) || 0;
		var umCompra = $.trim($tr.find('.linea-um-compra-abrev').val() || '');
		var cant = parseFloat($tr.find('.cantidad-linea').val()) || 0;
		var $hint = $tr.find('.linea-conversion-hint');
		if (!$hint.length) {
			return;
		}
		if (coef <= 0 || !umCompra) {
			$hint.addClass('d-none').html('');
			return;
		}
		var stock = cant * coef;
		var stockTxt = apFmtNum(stock, 4);
		$hint.removeClass('d-none').html(
			'<small class="text-muted" title="Al recibir: stock = cantidad compra × coef">' +
			apEsc(umCompra) + ' ×' + apEsc(apFmtNum(coef, 6)) + ' → stock ' + apEsc(stockTxt) +
			'</small>'
		);
	}

	/**
	 * Abre modal y resuelve Promise con item elegido o null (cancelar).
	 * @param {Array} items
	 * @param {object} [meta] { titulo, subtitulo }
	 * @returns {JQuery.Promise}
	 */
	function apAbrirModalElegir(items, meta) {
		var dfd = $.Deferred();
		var $modal = $('#modalElegirArticuloProveedor');
		if (!$modal.length) {
			dfd.resolve(null);
			return dfd.promise();
		}

		meta = meta || {};
		if (meta.titulo) {
			$('#modalElegirArticuloProveedorTitulo').text(meta.titulo);
		} else {
			$('#modalElegirArticuloProveedorTitulo').text('Elegir proveedor de compra');
		}
		if (meta.subtitulo) {
			$('#modalElegirArticuloProveedorSubtitulo').text(meta.subtitulo);
		} else {
			$('#modalElegirArticuloProveedorSubtitulo').text(
				'Seleccione el proveedor (y código) con el que se comprará este artículo.'
			);
		}

		var $tb = $('#tbody-elegir-articulo-proveedor').empty();
		(items || []).forEach(function (it, idx) {
			var pref = it.preferido
				? ' <span class="badge badge-success">Preferido</span>'
				: '';
			var precioTxt = it.tiene_precio && it.precio != null
				? (apEsc(it.moneda_abreviatura || '') + ' ' + apEsc(apFmtNum(it.precio, 4))).trim()
				: '—';
			var $tr = $(
				'<tr class="ap-elegir-fila" style="cursor:pointer;" data-idx="' + idx + '">' +
				'<td class="text-center"><button type="button" class="btn btn-warning btn-sm ap-btn-elegir">Elegir</button></td>' +
				'<td>' + apEsc((it.proveedor_codigo || '') + ' ' + (it.proveedor_nombre || '')) + pref + '</td>' +
				'<td>' + apEsc(it.codigo_articulo_proveedor || '—') + '</td>' +
				'<td>' + apEsc(it.nombre_articulo_proveedor || '—') + '</td>' +
				'<td>' + apEsc(it.um_compra_abreviatura || '—') + '</td>' +
				'<td class="text-right">' + apEsc(apFmtNum(it.coeficiente_conversion, 6) || '1') + '</td>' +
				'<td class="text-right">' + precioTxt + '</td>' +
				'</tr>'
			);
			$tr.data('ap-item', it);
			$tb.append($tr);
		});

		function cleanup() {
			$modal.off('hidden.bs.modal.apElegir');
			$modal.off('click.apElegir');
			$('#btn-elegir-ap-cancelar').off('click.apElegir');
		}

		function resolver(item) {
			cleanup();
			$modal.modal('hide');
			dfd.resolve(item);
		}

		$modal.on('click.apElegir', '.ap-btn-elegir, tr.ap-elegir-fila', function (e) {
			e.preventDefault();
			e.stopPropagation();
			var $fila = $(this).closest('tr.ap-elegir-fila');
			resolver($fila.data('ap-item') || null);
		});

		$('#btn-elegir-ap-cancelar').on('click.apElegir', function () {
			resolver(null);
		});

		$modal.one('hidden.bs.modal.apElegir', function () {
			if (dfd.state() === 'pending') {
				cleanup();
				dfd.resolve(null);
			}
		});

		$modal.modal('show');
		return dfd.promise();
	}

	/**
	 * Flujo completo post-selección de artículo.
	 * @param {object} opts
	 * @param {jQuery} opts.$tr
	 * @param {object} opts.dataArticulo
	 * @param {number} [opts.proveedorCabeceraId]
	 * @param {boolean} [opts.restrictivo]  OC: true
	 * @param {function} [opts.onRequiereCabecera]
	 * @param {function} [opts.onSinMatchCabecera]
	 * @param {function} [opts.afterApply]  (item|null)
	 * @returns {JQuery.Promise}
	 */
	function apResolverTrasArticulo(opts) {
		opts = opts || {};
		var $tr = opts.$tr;
		var dataArticulo = opts.dataArticulo || {};
		var articuloId = parseInt(dataArticulo.id || ($tr && $tr.find('.articulo_id').val()) || 0, 10) || 0;
		var dfd = $.Deferred();

		if (!articuloId || !$tr || !$tr.length) {
			dfd.resolve(null);
			return dfd.promise();
		}

		var proveedorCab = parseInt(opts.proveedorCabeceraId, 10) || 0;
		var restrictivo = !!opts.restrictivo;

		if (restrictivo && proveedorCab <= 0) {
			if (typeof opts.onRequiereCabecera === 'function') {
				opts.onRequiereCabecera();
			}
			apAplicarAFila($tr, null, dataArticulo);
			dfd.resolve(null);
			return dfd.promise();
		}

		apFetchProveedoresCompra({
			articuloId: articuloId,
			proveedorId: proveedorCab > 0 ? proveedorCab : 0,
			restrictivo: restrictivo
		}).done(function (resp) {
			var opcion = (resp && resp.opcion) || 'ninguno';
			var items = (resp && resp.proveedores) || [];
			var elegido = (resp && resp.elegido) || null;

			function fin(item) {
				apAplicarAFila($tr, item, dataArticulo);
				if (typeof opts.afterApply === 'function') {
					opts.afterApply(item);
				}
				dfd.resolve(item || null);
			}

			if (opcion === 'requiere_cabecera') {
				if (typeof opts.onRequiereCabecera === 'function') {
					opts.onRequiereCabecera();
				}
				fin(null);
				return;
			}

			if (opcion === 'sin_match_cabecera') {
				if (typeof opts.onSinMatchCabecera === 'function') {
					opts.onSinMatchCabecera();
				}
				fin(null);
				return;
			}

			if (opcion === 'auto' && elegido) {
				fin(elegido);
				return;
			}

			if (opcion === 'modal' && items.length) {
				var titulo = proveedorCab > 0
					? 'Elegir código del proveedor'
					: 'Elegir proveedor de compra';
				var sub = proveedorCab > 0
					? 'Hay varios códigos en articulo_proveedor para el proveedor de la cabecera.'
					: 'El artículo tiene datos en articulo_proveedor. Elija a cuál proveedor se le comprará.';
				apAbrirModalElegir(items, { titulo: titulo, subtitulo: sub }).done(fin);
				return;
			}

			fin(null);
		}).fail(function () {
			apAplicarAFila($tr, null, dataArticulo);
			dfd.resolve(null);
		});

		return dfd.promise();
	}

	window.ArticuloProveedorOperativo = {
		fetch: apFetchProveedoresCompra,
		aplicarAFila: apAplicarAFila,
		actualizarHintConversion: apActualizarHintConversion,
		abrirModal: apAbrirModalElegir,
		resolverTrasArticulo: apResolverTrasArticulo
	};
})(window, jQuery);
