$(function () {
	if (typeof carpetaBase === 'undefined' || carpetaBase === '') {
		window.carpetaBase = window.location.pathname.split('/public')[0] + '/public';
	}

	var urlConsulta = carpetaBase + '/stock/precio/consulta-por-articulo';
	var articuloIdActivo = 0;
	var articuloSkuActivo = '';
	var articuloDescActivo = '';

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

	function hoyIso() {
		var d = new Date();
		var m = String(d.getMonth() + 1).padStart(2, '0');
		var day = String(d.getDate()).padStart(2, '0');
		return d.getFullYear() + '-' + m + '-' + day;
	}

	function detectarOrigenRetorno() {
		var path = window.location.pathname || '';
		if (/\/articulo\/\d+\/editar/.test(path)) {
			return 'editar';
		}
		return 'index';
	}

	function urlPrecioConRetornoArticulo(baseUrl, articuloId, fechaRef, listaprecioId) {
		if (!baseUrl || !articuloId) {
			return baseUrl;
		}
		var sep = baseUrl.indexOf('?') >= 0 ? '&' : '?';
		var url =
			baseUrl +
			sep +
			'articulo_id=' +
			encodeURIComponent(articuloId) +
			'&retorno_articulo_id=' +
			encodeURIComponent(articuloId) +
			'&retorno_origen=' +
			encodeURIComponent(detectarOrigenRetorno()) +
			'&retorno_fecha_referencia=' +
			encodeURIComponent(fechaRef || hoyIso());
		if (listaprecioId) {
			url += '&listaprecio_id=' + encodeURIComponent(listaprecioId);
		}
		return url;
	}

	function urlEditarPrecioConRetorno(baseUrl, articuloId, fechaRef) {
		return urlPrecioConRetornoArticulo(baseUrl, articuloId, fechaRef, '');
	}

	function actualizarBotonNuevoPrecio(data, fechaRef, listaprecioId) {
		var $btn = $('#consultaprecioarticuloNuevo');
		if (!$btn.length) {
			return;
		}
		var puedeCrear = data && data.puede_crear && data.crear_url;
		if (!puedeCrear || !articuloIdActivo) {
			$btn.addClass('d-none');
			return;
		}
		$btn
			.attr(
				'href',
				urlPrecioConRetornoArticulo(data.crear_url, articuloIdActivo, fechaRef, listaprecioId)
			)
			.removeClass('d-none');
	}

	function abrirModalConsulta(articuloId, sku, desc, fechaRef, listaprecioId) {
		articuloIdActivo = articuloId;
		articuloSkuActivo = sku || '';
		articuloDescActivo = desc || '';

		$('#consultaprecioarticuloTitulo').text(
			'Precios — ' + articuloSkuActivo + (articuloDescActivo ? ' — ' + articuloDescActivo : '')
		);
		$('#consultaprecioarticuloFechaRef').val(fechaRef || hoyIso());
		$('#consultaprecioarticuloListaId').val(listaprecioId ? String(listaprecioId) : '');
		$('#consultaprecioarticuloBody').empty();
		$('#consultaprecioarticuloError').addClass('d-none').text('');
		$('#consultaprecioarticuloNuevo').addClass('d-none');
		$('#consultaprecioarticuloModal').modal('show');
		cargarConsulta();
	}

	function cargarConsulta() {
		if (!articuloIdActivo) {
			return;
		}

		var $body = $('#consultaprecioarticuloBody');
		var $sub = $('#consultaprecioarticuloSubtitulo');
		var $err = $('#consultaprecioarticuloError');
		var $load = $('#consultaprecioarticuloCargando');
		var fechaRef = $('#consultaprecioarticuloFechaRef').val() || hoyIso();
		var listaprecioId = $('#consultaprecioarticuloListaId').val() || '';

		$err.addClass('d-none').text('');
		$body.empty();
		$load.removeClass('d-none');

		var params = {
			articulo_id: articuloIdActivo,
			fecha_referencia: fechaRef,
		};
		if (listaprecioId !== '') {
			params.listaprecio_id = listaprecioId;
		}

		$.getJSON(urlConsulta, params)
			.done(function (data) {
				$load.addClass('d-none');
				actualizarBotonNuevoPrecio(data, fechaRef, listaprecioId);

				var art = data.articulo || {};
				var sku = art.sku || articuloSkuActivo;
				var desc = art.descripcion || articuloDescActivo;
				$('#consultaprecioarticuloTitulo').text(
					'Precios — ' + sku + (desc ? ' — ' + desc : '')
				);

				var ref = data.fecha_referencia || fechaRef;
				$sub.text(
					'Referencia de vigencia: ' +
						ref +
						'. Historial por lista de precios (vigente hacia atrás). La fila resaltada es el precio vigente a esa fecha en cada lista.'
				);

				var filas = data.filas || [];
				if (!filas.length) {
					var msgVacio = listaprecioId !== ''
						? 'Este artículo no tiene precios cargados en la lista seleccionada.'
						: 'Este artículo no tiene precios cargados en ninguna lista.';
					$body.append(
						'<tr><td colspan="8" class="text-center text-muted">' + esc(msgVacio) + '</td></tr>'
					);
					return;
				}

				filas.forEach(function (r) {
					var $tr = $('<tr></tr>');
					if (r.es_vigente) {
						$tr.addClass('table-info');
					}

					var listaLabel = esc(r.listaprecio_nombre || '');
					if (r.es_vigente) {
						listaLabel += ' <span class="badge badge-primary ml-1">Vigente</span>';
					}

					$tr.append($('<td></td>').html(listaLabel));
					$tr.append($('<td></td>').text(esc(r.listaprecio_codigo || '')));
					$tr.append($('<td></td>').text(esc(r.fechavigencia_fmt || r.fechavigencia || '')));
					$tr.append(
						$('<td class="text-right"></td>').html('<strong>' + fmtNum(r.precio) + '</strong>')
					);
					$tr.append($('<td class="text-right"></td>').text(fmtNum(r.precioanterior)));
					$tr.append($('<td></td>').text(esc(r.moneda_nombre || '')));
					$tr.append($('<td></td>').text(esc(r.usuario_nombre || '')));

					var $acc = $('<td class="text-nowrap"></td>');
					if (r.puede_editar && r.editar_url) {
						var hrefEditar = urlEditarPrecioConRetorno(
							r.editar_url,
							articuloIdActivo,
							fechaRef
						);
						$acc.append(
							$('<a></a>')
								.attr('href', hrefEditar)
								.addClass('btn-accion-tabla tooltipsC')
								.attr('title', 'Editar precio')
								.html('<i class="fa fa-edit"></i>')
						);
					}
					$tr.append($acc);
					$body.append($tr);
				});
			})
			.fail(function (xhr) {
				$load.addClass('d-none');
				$('#consultaprecioarticuloNuevo').addClass('d-none');
				var msg =
					(xhr.responseJSON && xhr.responseJSON.message) ||
					xhr.statusText ||
					'No se pudo cargar la consulta.';
				if (xhr.status === 403) {
					msg = 'No tiene permisos para esta consulta.';
				}
				$err.removeClass('d-none').text(msg);
			});
	}

	function procesarRetornoConsultaPrecios() {
		var params = new URLSearchParams(window.location.search);
		if (params.get('abrir_consulta_precios') !== '1') {
			return;
		}

		var articuloId = parseInt(params.get('articulo_id'), 10);
		if (!articuloId) {
			return;
		}

		var fechaRef = params.get('fecha_referencia') || hoyIso();

		if (window.history && window.history.replaceState) {
			params.delete('abrir_consulta_precios');
			params.delete('articulo_id');
			params.delete('fecha_referencia');
			var qs = params.toString();
			var nuevaUrl =
				window.location.pathname + (qs ? '?' + qs : '') + window.location.hash;
			window.history.replaceState({}, document.title, nuevaUrl);
		}

		setTimeout(function () {
			abrirModalConsulta(articuloId, '', '', fechaRef);
		}, 300);
	}

	$(document).on('click', '.consultapreciosarticulo', function (event) {
		event.preventDefault();
		var articuloId = parseInt($(this).data('articulo-id'), 10) || 0;
		if (!articuloId) {
			articuloId = parseInt($('#articulo_id').val(), 10) || 0;
		}
		if (!articuloId) {
			return;
		}

		var sku = $(this).data('articulo-sku') || '';
		var desc = $(this).data('articulo-descripcion') || '';
		abrirModalConsulta(articuloId, sku, desc, hoyIso());
	});

	$('#consultaprecioarticuloRecargar').on('click', function () {
		cargarConsulta();
	});

	$(document).on('change', '#consultaprecioarticuloListaId', function () {
		cargarConsulta();
	});

	$('#consultaprecioarticuloModal').on('hidden.bs.modal', function () {
		articuloIdActivo = 0;
		articuloSkuActivo = '';
		articuloDescActivo = '';
		$('#consultaprecioarticuloListaId').val('');
	});

	procesarRetornoConsultaPrecios();
});
