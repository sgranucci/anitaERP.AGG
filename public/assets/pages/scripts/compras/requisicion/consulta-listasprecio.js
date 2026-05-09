$(function () {
	if (typeof carpetaBase === 'undefined' || carpetaBase === '') {
		window.carpetaBase = window.location.pathname.split('/public')[0] + '/public';
	}

	var urlConsulta = carpetaBase + '/compras/requisicion/consulta-listas-precio-articulo';

	function fmtNum(n) {
		if (n === null || n === undefined || n === '') {
			return '';
		}
		var x = parseFloat(n);
		if (isNaN(x)) {
			return String(n);
		}
		return x.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 6 });
	}

	function esc(s) {
		if (s === null || s === undefined) {
			return '';
		}
		return String(s)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/"/g, '&quot;');
	}

	function trunc(s, max) {
		var t = esc(s);
		if (t.length <= max) {
			return t;
		}
		return t.substring(0, max) + '…';
	}

	function fmtFechaAlta(raw) {
		if (!raw) {
			return '';
		}
		var s = String(raw);
		return s.length >= 10 ? s.substring(0, 10) : s;
	}

	$(document).on('click', '.consultalistasprecio', function (event) {
		event.preventDefault();
		var $row = $(this).closest('tr.item-requisicion-articulo');
		var articuloId = parseInt($row.find('.articulo_id').val(), 10);
		if (!articuloId || articuloId <= 0) {
			alert('Seleccione un artículo antes de consultar listas de precios.');
			return;
		}

		var proveedorVal = $('#proveedor_id').val();
		var proveedorId = proveedorVal ? parseInt(proveedorVal, 10) : null;
		var fechaRef = $('#fecha').val() || '';

		var $modal = $('#consultalistasprecioModal');
		var $body = $('#consultalistasprecioBody');
		var $sub = $('#consultalistasprecioSubtitulo');
		var $err = $('#consultalistasprecioError');
		var $load = $('#consultalistasprecioCargando');

		$err.addClass('d-none').text('');
		$body.empty();
		$sub.text('');
		$load.removeClass('d-none');
		$modal.modal('show');

		var params = { articulo_id: articuloId };
		if (fechaRef) {
			params.fecha_referencia = fechaRef;
		}
		if (proveedorId && proveedorId > 0) {
			params.proveedor_id = proveedorId;
		}

		$.getJSON(urlConsulta, params)
			.done(function (data) {
				$load.addClass('d-none');

				var art = data.articulo || {};
				var sku = art.sku || '';
				var desc = art.descripcion || '';
				$('#consultalistasprecioTitulo').text(
					'Listas de precios — ' + sku + (desc ? ' — ' + desc : '')
				);

				var ref = data.fecha_referencia || '';
				var subt =
					'Referencia de vigencia: ' +
					ref +
					'. ' +
					(data.filtrado_por_proveedor
						? 'Solo listas ACTIVAS del proveedor cargado en la requisición y precio vigente a esa fecha.'
						: 'Sin proveedor en la requisición: todas las listas ACTIVAS que incluyen el ítem; orden por precio para comparar.');
				$sub.text(subt);

				var filas = data.filas || [];
				if (!filas.length) {
					$body.append(
						'<tr><td colspan="16" class="text-center text-muted">No hay precios en listas activas para este artículo a la fecha indicada.</td></tr>'
					);
					return;
				}

				var minPrecio = null;
				if (!data.filtrado_por_proveedor) {
					filas.forEach(function (r) {
						var p = parseFloat(r.precio);
						if (!isNaN(p) && (minPrecio === null || p < minPrecio)) {
							minPrecio = p;
						}
					});
				}

				filas.forEach(function (r) {
					var provLabel = esc(r.proveedor_codigo || '') + ' — ' + esc(r.proveedor_nombre || '');
					var listaLabel = '#' + esc(String(r.lista_id || '')) + ' — ' + esc(r.lista_nombre || '');
					var p = parseFloat(r.precio);
					var esMin =
						!data.filtrado_por_proveedor &&
						minPrecio !== null &&
						!isNaN(p) &&
						p === minPrecio;

					var $tr = $('<tr></tr>');
					if (esMin) {
						$tr.addClass('table-success');
					}

					function td(html) {
						return $('<td></td>').html(html);
					}

					$tr.append(td(provLabel));
					$tr.append(td(listaLabel));
					$tr.append(td(esc(r.lista_fecha || '')));
					$tr.append(td(esc(r.lista_estado || '')));
					$tr.append(td(esc(r.moneda_abreviatura || r.moneda_nombre || '')));
					$tr.append(td('<strong>' + fmtNum(r.precio) + '</strong>'));
					$tr.append(td(fmtNum(r.descuento)));
					$tr.append(td(esc(r.articulo_proveedor || '')));
					$tr.append(td(esc(r.linea_fechavigencia || '')));
					$tr.append(td(esc(r.condicion_pago || '—')));
					$tr.append(td(esc(r.condicion_entrega || '—')));
					$tr.append(td(esc(r.condicion_compra || '—')));
					var obs = r.lista_observaciones || '';
					$tr.append(
						$('<td></td>')
							.attr('title', esc(obs))
							.text(trunc(obs, 80))
					);
					$tr.append(td(fmtFechaAlta(r.lista_created_at)));
					$tr.append(td(esc(r.lista_creador || '')));
					$tr.append(td(esc(r.linea_ultimo_usuario || '')));

					$body.append($tr);
				});
			})
			.fail(function (xhr) {
				$load.addClass('d-none');
				var msg =
					(xhr.responseJSON && xhr.responseJSON.message) ||
					xhr.statusText ||
					'No se pudo cargar la consulta.';
				if (xhr.status === 403) {
					msg = 'No tiene permisos para esta consulta.';
				}
				$err.removeClass('d-none').text(msg);
			});
	});
});
