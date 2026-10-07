(function () {
	if (!window.FACTURA_OT_PEDIDO || !window.FACTURA_OT_PEDIDO.consulta) {
		return;
	}

	var cfg = window.FACTURA_OT_PEDIDO;
	var buscarTimer = null;
	var xhrBuscar = null;

	function $filaDestino() {
		var tr = $('#modalFacturaOtPedido').data('tr');
		return tr ? $(tr) : $();
	}

	function renumerar() {
		$('#tbody-tabla tr').each(function (i) {
			$(this).find('.item').val(i + 1);
		});
	}

	function badgeTexto(grupo) {
		var texto = 'Ped. ' + (grupo.pedido_codigo || '') + ' · OT ' + (grupo.ot_codigo || '');
		return texto;
	}

	function aplicarGrupo($tr, grupo) {
		$tr.attr('data-ot-llenando', '1');
		$tr.find('.ordentrabajo_id').val(grupo.ordentrabajo_id || '');
		$tr.find('.pedido_combinacion_id').val(grupo.pedido_combinacion_id || '');
		$tr.find('.ot_grupo_indice').val(grupo.indice != null ? grupo.indice : 0);
		$tr.find('.articulo_id').val(grupo.articulo_id || '');
		$tr.find('.articulo_id_previo, .articulo_id_previa').val(grupo.articulo_id || '');
		$tr.find('.concepto_venta_id').val('');
		$tr.find('.codigoarticulo').val(grupo.sku || '');
		$tr.find('.codigo_previo_articulo').val(grupo.sku || '');
		$tr.find('.descripcionarticulo').val(grupo.descripcion || '');
		$tr.find('.cantidad').val(grupo.cantidad != null ? grupo.cantidad : '');
		$tr.find('.precio').val(grupo.precio != null ? Number(grupo.precio).toFixed(2) : '');
		$tr.find('.listaprecio_id').val(grupo.listaprecio_id || '');
		$tr.find('.incluyeimpuesto').val(grupo.incluyeimpuesto != null ? grupo.incluyeimpuesto : '');
		$tr.find('.cantidad, .precio').prop('readonly', true);
		$tr.find('.factura-ot-badge').text(badgeTexto(grupo));
		$tr.find('.factura-abrir-ot-pedido').addClass('tiene-ot');
		$tr.removeClass('item-concepto-venta');
		$tr.removeAttr('data-ot-llenando');
	}

	function desvincularFila($tr) {
		var pc = String($tr.find('.pedido_combinacion_id').val() || '');
		var $set = pc !== ''
			? $('#tbody-tabla tr').filter(function () {
				return String($(this).find('.pedido_combinacion_id').val() || '') === pc;
			})
			: $tr;
		$set.each(function () {
			var $f = $(this);
			$f.find('.ordentrabajo_id, .pedido_combinacion_id, .ot_grupo_indice').val('');
			$f.find('.cantidad, .precio').prop('readonly', false);
			$f.find('.factura-ot-badge').text('');
			$f.find('.factura-abrir-ot-pedido').removeClass('tiene-ot');
		});
	}

	window.facturaLimpiarOtsPedido = function () {
		$('#tbody-tabla tr').each(function () {
			var $tr = $(this);
			if (String($tr.find('.ordentrabajo_id').val() || '') !== '') {
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

	function consultarOt(params) {
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
		var params = {
			cliente_id: clienteId,
			pedido_combinacion_id: fila.pedido_combinacion_id,
			ordentrabajo_id: fila.ordentrabajo_id,
			fecha: $('#fechafactura').val() || ''
		};
		consultarOt(params).done(function (data) {
			if (data && data.error) {
				alert(data.error);
				return;
			}
			var grupos = (data && data.grupos) ? data.grupos : [];
			if (!grupos.length) {
				alert('La OT no tiene pares para facturar.');
				return;
			}
			var pc = String(fila.pedido_combinacion_id);
			$('#tbody-tabla tr').each(function () {
				var $o = $(this);
				if ($o[0] !== $tr[0] && String($o.find('.pedido_combinacion_id').val() || '') === pc) {
					$o.remove();
				}
			});
			aplicarGrupo($tr, grupos[0]);
			for (var i = 1; i < grupos.length; i++) {
				$('#agrega_renglon').trigger('click');
				aplicarGrupo($('#tbody-tabla tr').last(), grupos[i]);
			}
			renumerar();
			$('#modalFacturaOtPedido').modal('hide');
			recalcular();
		}).fail(function (xhr) {
			var msg = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'No se pudo leer la OT.';
			alert(msg);
		});
	}

	function pintar(filas) {
		var $tb = $('#factura-ot-pedido-tbody');
		$tb.empty();
		if (!filas || !filas.length) {
			$tb.append('<tr><td colspan="6" class="text-muted">No hay OT pendientes para este cliente.</td></tr>');
			return;
		}
		filas.forEach(function (fila) {
			var $tr = $('<tr></tr>');
			$tr.append($('<td></td>').text(fila.pedido_codigo || ''));
			$tr.append($('<td></td>').text(fila.ot_codigo || ''));
			$tr.append($('<td></td>').text(((fila.sku || '') + ' ' + (fila.descripcion || '')).trim()));
			$tr.append($('<td></td>').text(fila.combinacion || ''));
			$tr.append($('<td class="text-right"></td>').text(fila.pares != null ? fila.pares : ''));
			var $acc = $('<td class="text-nowrap"></td>');
			var $elegir = $('<button type="button" class="btn btn-warning btn-sm">Elegir</button>');
			$elegir.on('click', function () {
				elegir(fila);
			});
			$acc.append($elegir);
			if (cfg.puedeConsultar && cfg.consultarOt) {
				var href = String(cfg.consultarOt).replace('__ID__', String(fila.ordentrabajo_id));
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
		xhrBuscar = consultarOt({
			cliente_id: clienteId,
			q: $('#factura_ot_pedido_buscar').val() || ''
		}).done(function (data) {
			pintar((data && data.filas) ? data.filas : []);
		}).fail(function (xhr) {
			if (xhr && xhr.statusText === 'abort') {
				return;
			}
			var msg = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'No se pudo buscar.';
			$('#factura-ot-pedido-tbody').html('<tr><td colspan="6" class="text-danger"></td></tr>');
			$('#factura-ot-pedido-tbody td').text(msg);
		});
	}

	$(document).on('click', '.factura-abrir-ot-pedido', function () {
		var clienteId = clienteFacturaId();
		if (!(clienteId > 0)) {
			alert('Elegí el cliente de la factura.');
			return;
		}
		var $tr = $(this).closest('tr');
		if ($tr.hasClass('item-concepto-venta')) {
			alert('La OT se carga en un renglón de mercadería.');
			return;
		}
		$('#modalFacturaOtPedido').data('tr', $tr.get(0));
		$('#factura_ot_pedido_buscar').val('');
		$('#modalFacturaOtPedido').modal('show');
		buscar();
		setTimeout(function () {
			$('#factura_ot_pedido_buscar').trigger('focus');
		}, 200);
	});

	$(document).on('input', '#factura_ot_pedido_buscar', function () {
		clearTimeout(buscarTimer);
		buscarTimer = setTimeout(buscar, 250);
	});

	$(document).on('keydown', '#factura_ot_pedido_buscar', function (e) {
		if (e.key === 'Enter' || e.which === 13) {
			e.preventDefault();
			var $primera = $('#factura-ot-pedido-tbody tr').first();
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
		if ($tr.attr('data-ot-llenando') === '1') {
			return;
		}
		if (String($tr.find('.ordentrabajo_id').val() || '') === '') {
			return;
		}
		desvincularFila($tr);
	});
})();
