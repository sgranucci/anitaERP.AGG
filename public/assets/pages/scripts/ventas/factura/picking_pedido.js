(function () {
	if (!window.FACTURA_PICKING_PEDIDO || !window.FACTURA_PICKING_PEDIDO.consulta) {
		return;
	}

	var cfg = window.FACTURA_PICKING_PEDIDO;
	var buscarTimer = null;
	var xhrBuscar = null;

	function $filaDestino() {
		var tr = $('#modalFacturaPickingPedido').data('tr');
		return tr ? $(tr) : $();
	}

	function renumerar() {
		$('#tbody-tabla tr').each(function (i) {
			$(this).find('.item').val(i + 1);
		});
	}

	function badgeTexto(grupo) {
		return 'Ped. ' + (grupo.pedido_codigo || '') + ' · Picking ' + (grupo.picking_codigo || '');
	}

	function aplicarGrupo($tr, grupo) {
		$tr.attr('data-picking-llenando', '1');
		$tr.find('.ordentrabajo_id, .pedido_combinacion_id, .ot_grupo_indice').val('');
		$tr.find('.factura-abrir-ot-pedido').removeClass('tiene-ot');
		$tr.find('.picking_pedido_combinacion_id').val(grupo.pedido_combinacion_id || '');
		$tr.find('.picking_grupo_indice').val(grupo.indice != null ? grupo.indice : 0);
		$tr.find('.articulo_id').val(grupo.articulo_id || '');
		$tr.find('.articulo_id_previo, .articulo_id_previa').val(grupo.articulo_id || '');
		$tr.find('.concepto_venta_id').val('');
		$tr.find('.codigoarticulo').val(grupo.sku || '');
		$tr.find('.codigo_previo_articulo').val(grupo.sku || '');
		$tr.find('.descripcionarticulo').val(grupo.descripcion || '');
		$tr.find('.cantidad').val(grupo.cantidad != null ? grupo.cantidad : '');
		$tr.find('.precio').val(grupo.precio != null ? Number(grupo.precio).toFixed(2) : '');
		if (grupo.descuento != null) {
			$tr.find('.descuento').val(Number(grupo.descuento).toFixed(2));
		}
		$tr.find('.listaprecio_id').val(grupo.listaprecio_id || '');
		$tr.find('.incluyeimpuesto').val(grupo.incluyeimpuesto != null ? grupo.incluyeimpuesto : '');
		$tr.find('.cantidad, .precio').prop('readonly', true);
		$tr.find('.factura-ot-badge').text(badgeTexto(grupo));
		$tr.find('.factura-abrir-picking-pedido').addClass('tiene-picking');
		$tr.removeClass('item-concepto-venta');
		$tr.removeAttr('data-picking-llenando');
	}

	function desvincularFila($tr) {
		var pc = String($tr.find('.picking_pedido_combinacion_id').val() || '');
		var $set = pc !== ''
			? $('#tbody-tabla tr').filter(function () {
				return String($(this).find('.picking_pedido_combinacion_id').val() || '') === pc;
			})
			: $tr;
		$set.each(function () {
			var $f = $(this);
			$f.find('.picking_pedido_combinacion_id, .picking_grupo_indice').val('');
			$f.find('.cantidad, .precio').prop('readonly', false);
			$f.find('.factura-ot-badge').text('');
			$f.find('.factura-abrir-picking-pedido').removeClass('tiene-picking');
		});
	}

	window.facturaLimpiarPickingsPedido = function () {
		$('#tbody-tabla tr').each(function () {
			var $tr = $(this);
			if (String($tr.find('.picking_pedido_combinacion_id').val() || '') !== '') {
				desvincularFila($tr);
			}
		});
	};

	function recalcular() {
		var $cantidad = $('#tbody-tabla .cantidad').first();
		if ($cantidad.length) {
			$cantidad.trigger('change');
		}
	}

	function clienteFacturaId() {
		return parseInt($('#cliente_id').val() || '0', 10) || 0;
	}

	function consultar(params) {
		return $.ajax({
			url: cfg.consulta,
			method: 'GET',
			data: params,
			dataType: 'json',
			processData: true,
			contentType: 'application/x-www-form-urlencoded; charset=UTF-8'
		});
	}

	function elegir(fila) {
		var clienteId = clienteFacturaId();
		var $tr = $filaDestino();
		if (!$tr.length) {
			return;
		}
		consultar({
			cliente_id: clienteId,
			pedido_combinacion_id: fila.pedido_combinacion_id,
			fecha: $('#fechafactura').val() || ''
		}).done(function (data) {
			if (data && data.error) {
				alert(data.error);
				return;
			}
			var grupos = (data && data.grupos) ? data.grupos : [];
			if (!grupos.length) {
				alert('El picking no tiene pares para facturar.');
				return;
			}
			var pc = String(fila.pedido_combinacion_id);
			$('#tbody-tabla tr').each(function () {
				var $o = $(this);
				if ($o[0] !== $tr[0] && String($o.find('.picking_pedido_combinacion_id').val() || '') === pc) {
					$o.remove();
				}
			});
			aplicarGrupo($tr, grupos[0]);
			for (var i = 1; i < grupos.length; i++) {
				$('#agrega_renglon').trigger('click');
				aplicarGrupo($('#tbody-tabla tr').last(), grupos[i]);
			}
			renumerar();
			$('#modalFacturaPickingPedido').modal('hide');
			recalcular();
		}).fail(function (xhr) {
			var msg = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'No se pudo leer el picking.';
			alert(msg);
		});
	}

	function pintar(filas) {
		var $tb = $('#factura-picking-pedido-tbody');
		$tb.empty();
		if (!filas || !filas.length) {
			$tb.append('<tr><td colspan="7" class="text-muted">No hay pickings pendientes para este cliente.</td></tr>');
			return;
		}
		filas.forEach(function (fila) {
			var $tr = $('<tr></tr>');
			$tr.append($('<td></td>').text(fila.picking_codigo || ''));
			$tr.append($('<td></td>').text(fila.pedido_codigo || ''));
			$tr.append($('<td></td>').text(((fila.sku || '') + ' ' + (fila.descripcion || '')).trim()));
			$tr.append($('<td></td>').text(fila.combinacion || ''));
			$tr.append($('<td></td>').text(fila.lote || ''));
			$tr.append($('<td class="text-right"></td>').text(fila.pares != null ? fila.pares : ''));
			var $acc = $('<td class="text-nowrap"></td>');
			var $elegir = $('<button type="button" class="btn btn-warning btn-sm">Elegir</button>');
			$elegir.on('click', function () {
				elegir(fila);
			});
			$acc.append($elegir);
			if (cfg.puedeConsultar && cfg.consultarPedido && fila.pedido_id) {
				var href = String(cfg.consultarPedido).replace('__ID__', String(fila.pedido_id));
				$acc.append(' ');
				$acc.append(
					$('<a class="btn btn-info btn-sm" target="_blank" rel="noopener">Consultar</a>').attr('href', href)
				);
			}
			$tr.append($acc);
			$tr.data('fila', fila);
			$tb.append($tr);
		});
	}

	function buscar() {
		var clienteId = clienteFacturaId();
		if (!(clienteId > 0)) {
			return;
		}
		if (xhrBuscar && xhrBuscar.readyState !== 4) {
			xhrBuscar.abort();
		}
		xhrBuscar = consultar({
			cliente_id: clienteId,
			q: $('#factura_picking_pedido_buscar').val() || ''
		}).done(function (data) {
			pintar((data && data.filas) ? data.filas : []);
		}).fail(function (xhr) {
			if (xhr && xhr.statusText === 'abort') {
				return;
			}
			var msg = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'No se pudo buscar.';
			$('#factura-picking-pedido-tbody').html('<tr><td colspan="7" class="text-danger"></td></tr>');
			$('#factura-picking-pedido-tbody td').text(msg);
		});
	}

	$(document).on('click', '.factura-abrir-picking-pedido', function () {
		var clienteId = clienteFacturaId();
		if (!(clienteId > 0)) {
			alert('Elegí el cliente de la factura.');
			return;
		}
		var $tr = $(this).closest('tr');
		if ($tr.hasClass('item-concepto-venta')) {
			alert('El picking se carga en un renglón de mercadería.');
			return;
		}
		$('#modalFacturaPickingPedido').data('tr', $tr.get(0));
		$('#factura_picking_pedido_buscar').val('');
		$('#modalFacturaPickingPedido').modal('show');
		buscar();
		setTimeout(function () {
			$('#factura_picking_pedido_buscar').trigger('focus');
		}, 200);
	});

	$(document).on('input', '#factura_picking_pedido_buscar', function () {
		clearTimeout(buscarTimer);
		buscarTimer = setTimeout(buscar, 250);
	});

	$(document).on('keydown', '#factura_picking_pedido_buscar', function (e) {
		if (e.key === 'Enter' || e.which === 13) {
			e.preventDefault();
			var $primera = $('#factura-picking-pedido-tbody tr').first();
			var fila = $primera.data('fila');
			if (fila) {
				elegir(fila);
			} else {
				buscar();
			}
		}
	});

	$(document).on('input', '#tbody-tabla .codigoarticulo', function () {
		var $tr = $(this).closest('tr');
		if ($tr.attr('data-picking-llenando') === '1' || $tr.attr('data-ot-llenando') === '1') {
			return;
		}
		if (String($tr.find('.picking_pedido_combinacion_id').val() || '') === '') {
			return;
		}
		desvincularFila($tr);
	});
})();
