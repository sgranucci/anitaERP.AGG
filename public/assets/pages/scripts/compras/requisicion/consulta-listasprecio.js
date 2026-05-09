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

	function cmpPrecio(r) {
		if (r.precio_neto_descuento !== undefined && r.precio_neto_descuento !== null && r.precio_neto_descuento !== '') {
			var x = parseFloat(r.precio_neto_descuento);
			if (!isNaN(x)) {
				return x;
			}
		}
		var p = parseFloat(r.precio);
		return isNaN(p) ? null : p;
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
						? 'Proveedor cargado en la requisición: solo listas ACTIVAS de ese proveedor y precio vigente del ítem a esa fecha.'
						: 'Sin proveedor en la requisición: todas las listas ACTIVAS que incluyen el ítem; orden por precio neto (tras % descuento) para comparar. La fila con menor precio neto se resalta.');
				$sub.text(subt);

				var filas = data.filas || [];
				if (!filas.length) {
					$body.append(
						'<tr><td colspan="19" class="text-center text-muted">No hay precios en listas activas para este artículo a la fecha indicada.</td></tr>'
					);
					return;
				}

				var minPrecio = null;
				if (!data.filtrado_por_proveedor) {
					filas.forEach(function (r) {
						var p = cmpPrecio(r);
						if (p !== null && (minPrecio === null || p < minPrecio)) {
							minPrecio = p;
						}
					});
				}

				filas.forEach(function (r) {
					var provLabel = esc(r.proveedor_codigo || '') + ' — ' + esc(r.proveedor_nombre || '');
					var listaLabel = '#' + esc(String(r.lista_id || '')) + ' — ' + esc(r.lista_nombre || '');
					var pcmp = cmpPrecio(r);
					var esMin =
						!data.filtrado_por_proveedor &&
						minPrecio !== null &&
						pcmp !== null &&
						pcmp === minPrecio;

					var $tr = $('<tr></tr>');
					if (esMin) {
						$tr.addClass('table-success');
					}

					function td(html) {
						return $('<td></td>').html(html);
					}

					var monHtml = '<strong>' + esc(r.moneda_abreviatura || '') + '</strong>';
					if (r.moneda_codigo) {
						monHtml += ' <small class="text-muted">(' + esc(r.moneda_codigo) + ')</small>';
					}
					if (r.moneda_nombre) {
						monHtml += '<br><small class="text-muted">' + esc(r.moneda_nombre) + '</small>';
					}

					$tr.append(td(provLabel));
					$tr.append(td(esc(r.proveedor_fantasia || '—')));
					$tr.append(td(listaLabel));
					$tr.append(td(esc(r.lista_fecha || '')));
					$tr.append(td(esc(r.lista_estado || '')));
					$tr.append(td(monHtml));
					$tr.append(td('<strong>' + fmtNum(r.precio) + '</strong>'));
					$tr.append(td(fmtNum(r.descuento)));
					$tr.append(td('<strong>' + fmtNum(r.precio_neto_descuento) + '</strong>'));
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
					$tr.append(td(esc(r.lista_updated_at || '')));
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
