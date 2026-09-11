$(function () {
	if (typeof window.carpetaBase === 'undefined') {
		var __locCb = window.location.pathname || '';
		var __mCb = __locCb.match(/^(.*\/public)(?:\/|$)/);
		window.carpetaBase = __mCb ? __mCb[1] : '';
	}

	if (typeof activa_eventos_consultaarticulo === 'function') {
		activa_eventos_consultaarticulo();
	}
	if (typeof activa_eventos_consultaproveedor === 'function') {
		activa_eventos_consultaproveedor();
	}

	function fechaHoyIso() {
		return new Date().toISOString().slice(0, 10);
	}

	window.lpFechaVigenciaExcel = function () {
		return $('#fechavigencia_excel').val() || $('#fecha').val() || fechaHoyIso();
	};

	function agregarRenglonArticulo() {
		var tpl = document.getElementById('template-renglon-listaprecio-articulo');
		var tbody = document.querySelector('#tabla-articulos-listaprecio tbody');
		if (!tpl || !tbody) {
			return;
		}
		var node;
		if (tpl.content) {
			node = document.importNode(tpl.content, true);
			var fecha = node.querySelector('input[name="fechavigencias[]"]');
			if (fecha) {
				fecha.value = fechaHoyIso();
			}
			tbody.appendChild(node);
			return;
		}
		var $first = $('#tabla-articulos-listaprecio tbody tr.item-listaprecio-articulo').first();
		if (!$first.length) {
			return;
		}
		var $clone = $first.clone();
		$clone.find('input,select').not('.descripcionarticulo').val('');
		$clone.find('.linea_id').val('');
		$clone.find('.descripcionarticulo').val('');
		$clone.find('input[name="fechavigencias[]"]').val(fechaHoyIso());
		$('#tabla-articulos-listaprecio tbody').append($clone);
	}

	window.lpAgregarRenglonArticulo = agregarRenglonArticulo;

	$('#agrega_renglon_listaprecio_articulo').on('click', function () {
		agregarRenglonArticulo();
	});

	$(document).on('click', '.eliminar_listaprecio_articulo', function (event) {
		event.preventDefault();
		var $tbody = $('#tabla-articulos-listaprecio tbody');
		var $rows = $tbody.find('tr.item-listaprecio-articulo');
		if ($rows.length > 1) {
			$(this).closest('tr.item-listaprecio-articulo').remove();
			return;
		}
		$(this).closest('tr.item-listaprecio-articulo').find('input,select').each(function () {
			if ($(this).hasClass('linea_id') || $(this).hasClass('articulo_id') || $(this).hasClass('descripcionarticulo')) {
				$(this).val('');
			} else if ($(this).attr('name') === 'fechavigencias[]') {
				$(this).val(fechaHoyIso());
			} else if ($(this).attr('name') === 'descuentos[]') {
				$(this).val('0');
			} else {
				$(this).val('');
			}
		});
	});

	$('#lp-agrega-renglon-archivo').on('click', function (event) {
		event.preventDefault();
		var tpl = document.getElementById('lp-template-renglon-archivo');
		var tbody = document.getElementById('lp-tbody-tabla-archivo');
		if (!tpl || !tbody) {
			return;
		}
		if (tpl.content) {
			tbody.appendChild(document.importNode(tpl.content, true));
		}
	});

	$(document).on('click', '#lp-tbody-tabla-archivo .lp-eliminararchivo', function (event) {
		event.preventDefault();
		var $tbody = $('#lp-tbody-tabla-archivo');
		var $rows = $tbody.find('tr.item-archivo-lp');
		if ($rows.length > 1) {
			$(this).closest('tr.item-archivo-lp').remove();
			return;
		}
		$(this).closest('tr.item-archivo-lp').find('input[type=file]').val('');
	});

	$(document).on('click', '.eliminar-archivo-listaprecio', function (event) {
		event.preventDefault();
		var $wrap = $(this).closest('.listaprecio-archivo-item');
		if ($wrap.length) {
			$wrap.remove();
			return;
		}
		$(this).closest('.col-md-6').remove();
	});

	$('a[data-toggle="tab"][href="#tab-historia"]').on('shown.bs.tab', function () {
		leeHistoria();
	});

	function leeHistoria() {
		var id = $('#listaprecio_proveedor_id').val();
		if (!id) {
			return;
		}
		var url = carpetaBase + '/compras/leer_historia_listaprecio_proveedor/' + id;
		$.get(url, function (historia) {
			var $w = $('.container-historia').empty();
			if (!historia || !historia.length) {
				$w.append('<tr><td colspan="4" class="text-muted text-center py-3">Sin movimientos de estado.</td></tr>');
				return;
			}
			$.each(historia, function (_, value) {
				var fecha = value.fecha ? String(value.fecha).substring(0, 16) : '';
				var usuario = value.usuarios && value.usuarios.nombre ? value.usuarios.nombre : '';
				var obs = (value.observacion || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
				$w.append(
					'<tr><td>' + fecha + '</td>' +
					'<td>' + (value.estado || '') + '</td>' +
					'<td>' + usuario + '</td>' +
					'<td>' + obs + '</td></tr>'
				);
			});
		});
	}

	if ($('#crear').length) {
		var $codigoProveedor = $('#codigoproveedor');
		if ($codigoProveedor.length && !$codigoProveedor.prop('readonly') && !$codigoProveedor.prop('disabled')) {
			setTimeout(function () {
				$codigoProveedor.trigger('focus');
			}, 0);
		}
	}
});
