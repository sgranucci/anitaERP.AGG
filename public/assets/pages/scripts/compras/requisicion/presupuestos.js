(function ($) {
	var lineasReqCache = [];
	var idsConservarArchivos = [];

	function escapeHtml(s) {
		if (s == null) return '';
		return String(s).replace(/[&<>"']/g, function (c) {
			return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
		});
	}

	// Lee el estado actual (no necesariamente persistido) de la grilla de ítems
	// del tab "Datos principales". Permite que la pantalla de presupuestos
	// refleje cambios de precio/cantidad/artículo/moneda hechos por el usuario
	// antes de haber grabado la requisición.
	function obtenerLineasItemsDOM() {
		var $tabla = $('#tabla-articulos-requisicion');
		if (!$tabla.length) {
			return { lineas: [], nuevasSinGrabar: 0 };
		}
		var lineas = [];
		var nuevasSinGrabar = 0;
		$tabla.find('tbody tr.item-requisicion-articulo').each(function () {
			var $tr = $(this);
			var articuloId = parseInt($tr.find('.articulo_id').val(), 10);
			if (!articuloId || isNaN(articuloId)) {
				return;
			}
			var ridRaw = $tr.find('.requisicion_articulo_id').val();
			var rid = parseInt(ridRaw, 10);
			if (!rid || isNaN(rid)) {
				nuevasSinGrabar++;
				return;
			}
			var $monedaSel = $tr.find('select[name="moneda_linea_ids[]"]');
			var monedaAbrev = '';
			if ($monedaSel.length) {
				monedaAbrev = $.trim($monedaSel.find('option:selected').text());
			}
			var cantidad = parseFloat($tr.find('.cantidad-linea').val());
			var precio = parseFloat($tr.find('.precio-linea').val());
			lineas.push({
				requisicion_articulo_id: rid,
				articulo_codigo: $.trim($tr.find('.codigoarticulo').val() || ''),
				articulo_descripcion: $.trim($tr.find('.descripcionarticulo').val() || ''),
				cantidad: isNaN(cantidad) ? 0 : cantidad,
				precio_requisicion: isNaN(precio) ? 0 : precio,
				moneda_abreviatura: monedaAbrev
			});
		});
		return { lineas: lineas, nuevasSinGrabar: nuevasSinGrabar };
	}

	// Reemplaza la caché de líneas con la información viva del DOM cuando hay
	// líneas válidas en el formulario de ítems. Devuelve cuántas líneas nuevas
	// sin grabar quedaron fuera para que el caller pueda avisar al usuario.
	function sincronizarCacheLineasConItemsDOM() {
		var info = obtenerLineasItemsDOM();
		if (info.lineas.length) {
			lineasReqCache = info.lineas;
		}
		return info;
	}

	function box() {
		return $('#solapa-presupuestos-requisicion');
	}

	function cfg(name) {
		var $b = box();
		if (!$b.length) {
			return undefined;
		}
		// Usar .attr: jQuery .data() transforma claves con guiones y suele fallar con data-url-index, etc.
		return $b.attr('data-' + name);
	}

	function readonly() {
		return String(cfg('readonly')) === '1';
	}

	function renderPreviewArchivoNuevo(file) {
		var $col = $('<div class="col-md-4 mb-3 presupuesto-preview-card"></div>');
		var name = file.name || '';
		var lower = name.toLowerCase();
		var url = URL.createObjectURL(file);
		var inner = '';
		if (/\.(png|jpe?g|gif|webp)$/i.test(lower)) {
			inner = '<img src="' + url + '" class="img-fluid img-thumbnail" alt="">';
		} else if (/\.pdf$/i.test(lower)) {
			inner = '<iframe src="' + url + '" class="w-100" style="min-height:220px;border:1px solid #dee2e6;" title="PDF"></iframe>';
		} else {
			inner = '<div class="p-3 border rounded text-center text-muted"><i class="fa fa-file-o fa-3x mb-2"></i><br><small>' + escapeHtml(name) + '</small></div>';
		}
		$col.append('<div class="small font-weight-bold mb-1 text-truncate" title="' + escapeHtml(name) + '">' + escapeHtml(name) + '</div>');
		$col.append(inner);
		return $col;
	}

	function limpiarPreviewNuevos() {
		$('#presupuesto-preview-nuevos-archivos').empty();
		$('#presupuesto_archivos_input').val('');
	}

	function onArchivosInputChange() {
		var files = $('#presupuesto_archivos_input')[0].files;
		var $wrap = $('#presupuesto-preview-nuevos-archivos').empty();
		if (!files || !files.length) return;
		for (var i = 0; i < files.length; i++) {
			$wrap.append(renderPreviewArchivoNuevo(files[i]));
		}
	}

	function armarTablaLineasDesdeCache(preciosPorLinea) {
		var $tb = $('#tabla-lineas-presupuesto tbody').empty();
		lineasReqCache.forEach(function (ln, idx) {
			var rid = Number(ln.requisicion_articulo_id);
			var precioReq = ln.precio_requisicion != null ? ln.precio_requisicion : '';
			var precioCot = preciosPorLinea && preciosPorLinea[rid] !== undefined && preciosPorLinea[rid] !== null && preciosPorLinea[rid] !== ''
				? preciosPorLinea[rid]
				: precioReq;
			var obs = preciosPorLinea && preciosPorLinea['_obs_' + rid] !== undefined ? preciosPorLinea['_obs_' + rid] : '';
			var $tr = $('<tr></tr>');
			$tr.attr('data-requisicion-articulo-id', rid);
			$tr.append('<td>' + escapeHtml(ln.articulo_codigo || '') + '</td>');
			$tr.append('<td>' + escapeHtml(ln.articulo_descripcion || '') + '</td>');
			$tr.append('<td>' + escapeHtml(String(ln.cantidad != null ? ln.cantidad : '')) + '</td>');
			$tr.append('<td>' + escapeHtml(String(precioReq)) + ' ' + escapeHtml(ln.moneda_abreviatura || '') + '</td>');
			var inputPrecio = $('<input type="number" step="0.0001" class="form-control form-control-sm presupuesto-in-precio">').val(precioCot);
			var inputObs = $('<input type="text" class="form-control form-control-sm presupuesto-in-obs">').val(obs);
			if (readonly()) {
				inputPrecio.prop('readonly', true);
				inputObs.prop('readonly', true);
			}
			$tr.append($('<td></td>').append(inputPrecio));
			$tr.append($('<td></td>').append(inputObs));
			$tb.append($tr);
		});
	}

	function enlacesPdfModalParaId(id) {
		var base = cfg('url-presupuesto-base') || '';
		if (!base || !id) {
			return;
		}
		$('#presupuesto_abrir_pdf').removeClass('d-none').attr('href', base + '/' + id + '/pdf');
		$('#presupuesto_abrir_impresion').removeClass('d-none').attr('href', base + '/' + id + '/imprimir');
	}

	function ocultarEnlacesPdfModal() {
		$('#presupuesto_abrir_pdf, #presupuesto_abrir_impresion').addClass('d-none').attr('href', '#');
	}

	function abrirModalNuevo() {
		var infoDom = sincronizarCacheLineasConItemsDOM();
		if (!lineasReqCache.length) {
			alert('La requisición no tiene líneas de artículo válidas; no se puede pedir presupuesto.');
			return;
		}
		if (infoDom.nuevasSinGrabar > 0) {
			alert(
				'Hay ' + infoDom.nuevasSinGrabar + ' línea(s) nueva(s) en los ítems que aún no fueron grabadas en la requisición. ' +
				'Para incluirlas en el presupuesto, primero actualice la requisición.'
			);
		}
		$('#presupuesto_edit_id').val('');
		ocultarEnlacesPdfModal();
		$('#presupuesto_fecha').val(new Date().toISOString().slice(0, 10));
		$('#presupuesto_proveedor_id').val('');
		$('#presupuesto_condicionentrega_id').val('');
		$('#presupuesto_condicioncompra_id').val('');
		$('#presupuesto_condicionpago_id').val('');
		$('#presupuesto_estado').val('ACTIVO');
		idsConservarArchivos = [];
		$('#presupuesto-archivos-existentes').empty();
		limpiarPreviewNuevos();
		armarTablaLineasDesdeCache(null);
		$('#modalPresupuestoRequisicionTitulo').text('Nuevo presupuesto');
		$('#modalPresupuestoRequisicion').modal('show');
	}

	function archivoPreviewHtml(urlVer, nombre) {
		var lower = (nombre || '').toLowerCase();
		if (/\.(png|jpe?g|gif|webp)$/i.test(lower)) {
			return '<div class="mb-2"><img src="' + escapeHtml(urlVer) + '" class="img-fluid img-thumbnail" alt=""></div>';
		}
		if (/\.pdf$/i.test(lower)) {
			return '<iframe src="' + escapeHtml(urlVer) + '" class="w-100 mb-2" style="min-height:200px;border:1px solid #dee2e6;" title="PDF"></iframe>';
		}
		return '';
	}

	function cargarDetalle(id) {
		var url = cfg('url-show') + '/' + id;
		$.get(url).done(function (det) {
			if ((!lineasReqCache || !lineasReqCache.length) && det.lineas_requisicion && det.lineas_requisicion.length) {
				lineasReqCache = det.lineas_requisicion;
			}
			if ((!lineasReqCache || !lineasReqCache.length) && det.articulos && det.articulos.length) {
				lineasReqCache = det.articulos.map(function (a) {
					return {
						requisicion_articulo_id: a.requisicion_articulo_id,
						articulo_codigo: a.articulo_codigo,
						articulo_descripcion: a.articulo_descripcion,
						cantidad: a.cantidad_requisicion,
						precio_requisicion: a.precio_requisicion,
						moneda_abreviatura: a.moneda_abreviatura
					};
				});
			}
			// Si el usuario modificó los ítems en el tab "Datos principales", reflejamos
			// el estado vivo del formulario. Para no ocultar cotizaciones de un presupuesto
			// existente que apunten a una línea que el usuario haya quitado del DOM sin
			// grabar, hacemos un merge por requisicion_articulo_id en vez de reemplazar.
			var infoDom = obtenerLineasItemsDOM();
			if (infoDom.lineas.length) {
				var domPorId = {};
				infoDom.lineas.forEach(function (ln) {
					domPorId[Number(ln.requisicion_articulo_id)] = ln;
				});
				lineasReqCache = (lineasReqCache || []).map(function (ln) {
					var d = domPorId[Number(ln.requisicion_articulo_id)];
					return d ? d : ln;
				});
			}
			$('#presupuesto_edit_id').val(det.id);
			$('#presupuesto_fecha').val(det.fecha || '');
			$('#presupuesto_proveedor_id').val(det.proveedor_id || '');
			$('#presupuesto_condicionentrega_id').val(det.condicionentrega_id || '');
			$('#presupuesto_condicioncompra_id').val(det.condicioncompra_id || '');
			$('#presupuesto_condicionpago_id').val(det.condicionpago_id || '');
			$('#presupuesto_estado').val(det.estado || 'ACTIVO');
			var precMap = {};
			(det.articulos || []).forEach(function (a) {
				var arid = Number(a.requisicion_articulo_id);
				if (isNaN(arid)) {
					return;
				}
				precMap[arid] = a.precio_unitario;
				precMap['_obs_' + arid] = a.observacion || '';
			});
			idsConservarArchivos = (det.archivos || []).map(function (x) { return x.id; });
			armarTablaLineasDesdeCache(precMap);
			var $ex = $('#presupuesto-archivos-existentes').empty();
			(det.archivos || []).forEach(function (ar) {
				var chkId = 'arc_keep_' + ar.id;
				var $blk = $('<div class="border rounded p-2 mb-2"></div>');
				var pdfPrev = archivoPreviewHtml(ar.url_ver, ar.nombrearchivo);
				if (!readonly()) {
					$blk.append(
						'<div class="custom-control custom-checkbox mb-1">' +
						'<input type="checkbox" class="custom-control-input archivo-conservar-cb" id="' + chkId + '" checked data-id="' + ar.id + '">' +
						'<label class="custom-control-label" for="' + chkId + '">Conservar en disco</label></div>'
					);
				}
				if (pdfPrev) {
					$blk.append(pdfPrev);
				}
				$blk.append(
					'<a href="' + escapeHtml(ar.url_ver) + '" target="_blank" rel="noopener noreferrer">' +
					'<i class="fa fa-external-link"></i> ' + escapeHtml(ar.nombrearchivo) + '</a>'
				);
				$ex.append($blk);
			});
			if (!readonly()) {
				$(document).off('change.presArcKeep').on('change.presArcKeep', '.archivo-conservar-cb', function () {
					var idAr = parseInt($(this).data('id'), 10);
					var checked = $(this).is(':checked');
					if (checked) {
						if (idsConservarArchivos.indexOf(idAr) < 0) idsConservarArchivos.push(idAr);
					} else {
						idsConservarArchivos = idsConservarArchivos.filter(function (x) { return x !== idAr; });
					}
				});
			}
			limpiarPreviewNuevos();
			$('#modalPresupuestoRequisicionTitulo').text('Editar presupuesto #' + det.id);
			enlacesPdfModalParaId(det.id);
			$('#modalPresupuestoRequisicion').modal('show');
		}).fail(function () {
			alert('No se pudo cargar el presupuesto.');
		});
	}

	function recolectarLineasParaEnvio() {
		var ids = [];
		var precios = [];
		var obs = [];
		$('#tabla-lineas-presupuesto tbody tr').each(function () {
			var rid = Number($(this).data('requisicion-articulo-id'));
			var p = $(this).find('.presupuesto-in-precio').val();
			var o = $(this).find('.presupuesto-in-obs').val();
			ids.push(rid);
			precios.push(p);
			obs.push(o || '');
		});
		return { ids: ids, precios: precios, obs: obs };
	}

	function guardarPresupuesto() {
		var fechaVal = $('#presupuesto_fecha').val();
		if (!fechaVal || !String(fechaVal).trim()) {
			alert('Indique la fecha del presupuesto.');
			return;
		}
		var provVal = $('#presupuesto_proveedor_id').val();
		if (!provVal) {
			alert('Seleccione un proveedor cotizado.');
			return;
		}
		var editId = $('#presupuesto_edit_id').val();
		var fd = new FormData();
		fd.append('fecha', fechaVal);
		fd.append('proveedor_id', $('#presupuesto_proveedor_id').val());
		fd.append('condicionentrega_id', $('#presupuesto_condicionentrega_id').val() || '');
		fd.append('condicioncompra_id', $('#presupuesto_condicioncompra_id').val() || '');
		fd.append('condicionpago_id', $('#presupuesto_condicionpago_id').val() || '');
		fd.append('estado', $('#presupuesto_estado').val());
		var L = recolectarLineasParaEnvio();
		L.ids.forEach(function (id) { fd.append('requisicion_articulo_ids[]', id); });
		L.precios.forEach(function (p) { fd.append('precios_unitarios[]', p); });
		L.obs.forEach(function (o) { fd.append('observaciones_linea[]', o); });
		var files = $('#presupuesto_archivos_input')[0].files;
		if (files && files.length) {
			for (var i = 0; i < files.length; i++) {
				fd.append('archivos_presupuesto[]', files[i]);
			}
		}
		if (editId) {
			idsConservarArchivos.forEach(function (id) {
				fd.append('archivo_ids_conservar[]', id);
			});
		}
		var token = cfg('csrf');
		var url = cfg('url-store');
		var method = 'POST';
		if (editId) {
			url = cfg('url-update') + '/' + editId;
			fd.append('_method', 'PUT');
		}
		$.ajax({
			url: url,
			type: 'POST',
			data: fd,
			processData: false,
			contentType: false,
			headers: { 'X-CSRF-TOKEN': token }
		}).done(function (r) {
			if (r && r.mensaje === 'ok') {
				$('#modalPresupuestoRequisicion').modal('hide');
				window.cargaSolapaPresupuestos();
			} else {
				alert((r && r.mensaje) ? r.mensaje : 'No se pudo guardar.');
			}
		}).fail(function (xhr) {
			var msg = 'No se pudo guardar.';
			if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
			if (xhr.responseJSON && xhr.responseJSON.errors) {
				msg = Object.values(xhr.responseJSON.errors).flat().join('\n');
			}
			alert(msg);
		});
	}

	function eliminarPresupuesto(id) {
		if (!confirm('¿Eliminar este presupuesto y sus archivos?')) return;
		var token = cfg('csrf');
		$.ajax({
			url: cfg('url-destroy') + '/' + id,
			type: 'POST',
			data: { _method: 'DELETE', _token: token },
			headers: { 'X-CSRF-TOKEN': token }
		}).done(function (r) {
			if (r && r.mensaje === 'ok') {
				window.cargaSolapaPresupuestos();
			} else {
				alert((r && r.mensaje) ? r.mensaje : 'No se pudo eliminar.');
			}
		}).fail(function () {
			alert('No se pudo eliminar.');
		});
	}

	function renderTablaLista(rows) {
		var $tb = $('#tabla-lista-presupuestos tbody').empty();
		var ro = readonly();
		if (!rows || !rows.length) {
			$tb.append('<tr><td colspan="6" class="text-center text-muted">Sin presupuestos cargados.</td></tr>');
			return;
		}
		rows.forEach(function (p) {
			var archTxt = (p.archivos && p.archivos.length) ? (p.archivos.length + ' archivo(s)') : '—';
			var $tr = $('<tr></tr>');
			$tr.append('<td>' + escapeHtml(p.fecha || '') + '</td>');
			$tr.append('<td>' + escapeHtml(p.proveedor_codigo || '') + ' — ' + escapeHtml(p.proveedor_nombre || '') + '</td>');
			$tr.append('<td>' + escapeHtml(p.estado || '') + '</td>');
			var numLin = typeof p.num_lineas_cotizadas === 'number' ? p.num_lineas_cotizadas : parseInt(p.num_lineas_cotizadas, 10) || 0;
			$tr.append('<td class="text-center">' + numLin + '</td>');
			$tr.append('<td>' + escapeHtml(archTxt) + '</td>');
			var basePres = box().attr('data-url-presupuesto-base') || '';
			var urlPdf = basePres + '/' + p.id + '/pdf';
			var urlImp = basePres + '/' + p.id + '/imprimir';
			var $td = $('<td class="text-nowrap"></td>');
			$td.append(
				$('<a class="btn btn-sm btn-outline-danger mr-1" title="Descargar PDF"></a>')
					.attr('href', urlPdf)
					.attr('target', '_blank')
					.attr('rel', 'noopener noreferrer')
					.html('<i class="fas fa-file-pdf"></i>')
			);
			$td.append(
				$('<a class="btn btn-sm btn-outline-secondary mr-1" title="Formulario para imprimir"></a>')
					.attr('href', urlImp)
					.attr('target', '_blank')
					.attr('rel', 'noopener noreferrer')
					.html('<i class="fa fa-print"></i>')
			);
			if (ro) {
				$td.append(
					$('<button type="button" class="btn btn-sm btn-outline-secondary" title="Ver detalle"></button>')
						.html('<i class="fa fa-eye"></i>')
						.on('click', function () { cargarDetalle(p.id); })
				);
			} else {
				$td.append(
					$('<button type="button" class="btn btn-sm btn-info mr-1"></button>')
						.html('<i class="fa fa-edit"></i>')
						.on('click', function () { cargarDetalle(p.id); })
				);
				$td.append(
					$('<button type="button" class="btn btn-sm btn-danger"></button>')
						.html('<i class="fa fa-trash"></i>')
						.on('click', function () { eliminarPresupuesto(p.id); })
				);
			}
			$tr.append($td);
			$tb.append($tr);
		});
	}

	window.cargaSolapaPresupuestos = function () {
		var $box = box();
		if (!$box.length) return;
		var url = cfg('url-index');
		$.get(url).done(function (data) {
			lineasReqCache = data.lineas_requisicion || [];
			renderTablaLista(data.presupuestos || []);
		}).fail(function () {
			$('#tabla-lista-presupuestos tbody').html(
				'<tr><td colspan="6" class="text-center text-danger">No se pudieron cargar los presupuestos.</td></tr>'
			);
		});
	};

	function clickNuevoPresupuestoDesdeFooter() {
		if (!lineasReqCache.length) {
			window.cargaSolapaPresupuestos();
			setTimeout(function () {
				if (!lineasReqCache.length) {
					alert('La requisición no tiene líneas de artículo; no se puede pedir presupuesto.');
					return;
				}
				abrirModalNuevo();
			}, 400);
			return;
		}
		abrirModalNuevo();
	}

	$(function () {
		$(document).on('click', '#btn-footer-nuevo-presupuesto-requisicion', function (e) {
			e.preventDefault();
			clickNuevoPresupuestoDesdeFooter();
		});
		if (!box().length) {
			return;
		}
		$('#presupuesto_btn_guardar').on('click', guardarPresupuesto);
		$(document).on('change', '#presupuesto_archivos_input', onArchivosInputChange);
	});
})(jQuery);
