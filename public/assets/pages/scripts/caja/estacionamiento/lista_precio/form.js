$(function () {
	if (typeof carpetaBase === 'undefined' || carpetaBase === '') {
		window.carpetaBase = window.location.pathname.split('/public')[0] + '/public';
	}

	var $meta = $('#lista-precio-meta');
	var listaId = parseInt($meta.data('lista-id'), 10) || 0;
	var empresaGuardadaId = parseInt($meta.data('empresa-guardada-id'), 10) || 0;

	var $empresa = $('#empresa_id');
	if ($empresa.is('select')) {
		$empresa.on('change', function () {
			var nuevaEmpresa = $(this).val();
			if (!nuevaEmpresa) {
				return;
			}

			if (listaId > 0 && empresaGuardadaId > 0 && parseInt(nuevaEmpresa, 10) !== empresaGuardadaId) {
				if (!window.confirm('Al cambiar la empresa se mostrarán los ítems de la nueva empresa. Los precios guardados para ítems que no pertenezcan a esa empresa se eliminarán al guardar. ¿Continuar?')) {
					$(this).val(String(empresaGuardadaId));
					return;
				}
			}

			var basePath = window.location.pathname.split('?')[0];
			window.location.href = basePath + '?empresa_id=' + encodeURIComponent(nuevaEmpresa);
		});
	}

	$('#categoria_automovil_id').on('change', function () {
		validarCabeceraRemota(function (ok, msg) {
			if (!ok) {
				marcarErrorCabecera(msg);
				return;
			}
			limpiarErrorCabecera();
		});
	});

	$(document).on('change', '.fecha-vigente', function () {
		var itemId = $(this).data('item-id');
		validarFechaVigenteItem(itemId, $(this));
	});

	$(document).on('change', '.nueva-vigencia-fecha', function () {
		validarFechaNuevaVigencia($(this).closest('tr.fila-nueva-vigencia-wrap'));
	});

	$(document).on('click', '.btn-nueva-vigencia-item', function (event) {
		event.preventDefault();
		var itemId = $(this).closest('tr.fila-item-precio').data('item-id');
		var $wrap = $('tr.fila-nueva-vigencia-wrap[data-item-id="' + itemId + '"]');
		var seAbre = $wrap.hasClass('d-none');
		$wrap.toggleClass('d-none');
		if (seAbre) {
			limpiarErrorFecha($wrap.find('.nueva-vigencia-fecha'));
			var $precio = $wrap.find('.nueva-vigencia-precio');
			window.setTimeout(function () {
				$precio.trigger('focus').trigger('select');
			}, 0);
		}
	});

	$(document).on('click', '.btn-cancelar-nueva-vigencia', function (event) {
		event.preventDefault();
		var $wrap = $(this).closest('tr.fila-nueva-vigencia-wrap');
		$wrap.addClass('d-none');
		$wrap.find('.nueva-vigencia-precio').val('');
		$wrap.find('.nueva-vigencia-fecha').val(fechaHoyIso());
		limpiarErrorFecha($wrap.find('.nueva-vigencia-fecha'));
	});

	$(document).on('click', '.btn-confirmar-nueva-vigencia', function (event) {
		event.preventDefault();
		confirmarNuevaVigencia($(this).closest('tr.fila-nueva-vigencia-wrap'), true);
	});

	$(document).on('click', '.btn-historia-item', function (event) {
		event.preventDefault();
		var listaIdBtn = $(this).data('lista-id');
		var itemId = $(this).data('item-id');
		var nombre = $(this).closest('tr.fila-item-precio').data('item-nombre') || '';
		abrirHistoriaItem(listaIdBtn, itemId, nombre);
	});

	$('#form-general').on('submit', function (event) {
		var $form = $(this);

		prepararPanelesNuevaVigencia();
		reconstruirPayloadPrecios();

		var duplicados = detectarDuplicadosGlobales();
		if (duplicados.length > 0) {
			event.preventDefault();
			alert('Hay fechas de vigencia duplicadas para el mismo ítem:\n\n' + duplicados.join('\n'));
			return false;
		}

		if ($form.data('omitir-cabecera')) {
			$form.removeData('omitir-cabecera');
			return true;
		}

		event.preventDefault();
		validarCabeceraRemota(function (ok, msg) {
			if (!ok) {
				alert(msg || 'Ya existe una lista de precios para esta empresa y categoría.');
				marcarErrorCabecera(msg);
				return;
			}
			limpiarErrorCabecera();
			prepararPanelesNuevaVigencia();
			reconstruirPayloadPrecios();
			$form.data('omitir-cabecera', true);
			$form[0].submit();
		});

		return false;
	});
});

function fechaHoyIso() {
	return new Date().toISOString().slice(0, 10);
}

function prepararPanelesNuevaVigencia() {
	$('tr.fila-nueva-vigencia-wrap:not(.d-none)').each(function () {
		var $wrap = $(this);
		var itemId = $wrap.data('item-id');
		var $fila = $('tr.fila-item-precio[data-item-id="' + itemId + '"]');
		var precioNuevo = $.trim($wrap.find('.nueva-vigencia-precio').val());
		var precioVigente = $.trim($fila.find('.precio-vigente').val());

		if (precioNuevo !== '' && precioVigente === '') {
			$fila.find('.precio-vigente').val(precioNuevo);
			var fechaNueva = $wrap.find('.nueva-vigencia-fecha').val() || fechaHoyIso();
			$fila.find('.fecha-vigente').val(fechaNueva);
			$wrap.addClass('d-none');
			$wrap.find('.nueva-vigencia-precio').val('');
			$wrap.find('.nueva-vigencia-fecha').val(fechaHoyIso());
			return;
		}

		confirmarNuevaVigencia($wrap, false);
	});
}

/**
 * Arma precio_renglones con índices explícitos desde la grilla visible.
 * @returns {Array<{linea_id: string, item_id: string|number, precio: string, fecha_vigencia: string}>}
 */
function recolectarRenglonesPrecio() {
	var renglones = [];
	var claves = {};

	function agregar(lineaId, itemId, precio, fecha) {
		var precioStr = $.trim(String(precio));
		var fechaStr = $.trim(String(fecha));
		var itemIdStr = String(itemId);

		if (!itemIdStr || itemIdStr === '0' || precioStr === '') {
			return;
		}
		if (!fechaStr) {
			fechaStr = fechaHoyIso();
		}

		var clave = itemIdStr + '|' + fechaStr;
		if (claves[clave]) {
			return;
		}
		claves[clave] = true;

		renglones.push({
			linea_id: lineaId ? String(lineaId) : '',
			item_id: itemIdStr,
			precio: precioStr,
			fecha_vigencia: fechaStr
		});
	}

	$('tr.fila-item-precio').each(function () {
		var $fila = $(this);
		var precio = $.trim($fila.find('.precio-vigente').val());
		if (precio === '') {
			return;
		}

		agregar(
			$fila.data('linea-id') || '',
			$fila.data('item-id'),
			precio,
			$fila.find('.fecha-vigente').val() || fechaHoyIso()
		);
	});

	$('#payload-historial-precios .payload-historial-row').each(function () {
		agregar(
			$(this).data('linea-id') || '',
			$(this).data('item-id'),
			$(this).data('precio'),
			$(this).data('fecha')
		);
	});

	$('#payload-nuevas-vigencias .payload-nueva-vigencia').each(function () {
		agregar(
			$(this).data('linea-id') || '',
			$(this).data('item-id'),
			$(this).data('precio'),
			$(this).data('fecha')
		);
	});

	return renglones;
}

function reconstruirPayloadPrecios() {
	var renglones = recolectarRenglonesPrecio();
	var $container = $('#payload-renglones-envio');
	$container.empty();

	renglones.forEach(function (renglon, idx) {
		var html = '';
		html += '<input type="hidden" name="precio_renglones[' + idx + '][linea_id]" value="' + escAttr(renglon.linea_id) + '">';
		html += '<input type="hidden" name="precio_renglones[' + idx + '][item_id]" value="' + escAttr(renglon.item_id) + '">';
		html += '<input type="hidden" name="precio_renglones[' + idx + '][precio]" value="' + escAttr(renglon.precio) + '">';
		html += '<input type="hidden" name="precio_renglones[' + idx + '][fecha_vigencia]" value="' + escAttr(renglon.fecha_vigencia) + '">';
		$container.append(html);
	});
}

function escAttr(valor) {
	return String(valor)
		.replace(/&/g, '&amp;')
		.replace(/"/g, '&quot;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;');
}

/**
 * @param {JQuery} $wrap
 * @param {boolean} mostrarAlertas
 * @returns {boolean}
 */
function confirmarNuevaVigencia($wrap, mostrarAlertas) {
	var itemId = $wrap.data('item-id');
	var $fila = $('tr.fila-item-precio[data-item-id="' + itemId + '"]');
	var $inputFecha = $wrap.find('.nueva-vigencia-fecha');
	var precioRaw = $.trim($wrap.find('.nueva-vigencia-precio').val());
	var fecha = $inputFecha.val();

	if (precioRaw === '') {
		return false;
	}

	var precio = parseFloat(precioRaw);
	if (isNaN(precio) || precio < 0) {
		if (mostrarAlertas) {
			alert('Ingrese un precio válido.');
		}
		return false;
	}
	if (!fecha) {
		fecha = fechaHoyIso();
		$inputFecha.val(fecha);
	}

	if (fechaVigenciaDuplicadaParaItem(itemId, fecha)) {
		if (mostrarAlertas) {
			marcarErrorFecha($inputFecha, 'Ya existe un precio con esa fecha de vigencia para este ítem.');
			$inputFecha.trigger('focus');
		}
		return false;
	}

	limpiarErrorFecha($inputFecha);

	var uid = 'nv-' + itemId + '-' + Date.now();
	var html = '<div class="payload-nueva-vigencia" data-uid="' + uid + '"';
	html += ' data-item-id="' + itemId + '"';
	html += ' data-linea-id=""';
	html += ' data-precio="' + escAttr(precio) + '"';
	html += ' data-fecha="' + escAttr(fecha) + '"></div>';
	$('#payload-nuevas-vigencias').append(html);

	actualizarPendientesCelda($fila);
	$wrap.addClass('d-none');
	$wrap.find('.nueva-vigencia-precio').val('');
	$wrap.find('.nueva-vigencia-fecha').val(fechaHoyIso());

	return true;
}

function validarCabeceraRemota(callback) {
	var $meta = $('#lista-precio-meta');
	var empresaId = $('#empresa_id').val();
	var categoriaId = $('#categoria_automovil_id').val();

	if (!empresaId || !categoriaId) {
		callback(false, 'Seleccione empresa y categoría.');
		return;
	}

	$.getJSON($meta.data('url-validar-cabecera'), {
		empresa_id: empresaId,
		categoria_automovil_id: categoriaId,
		excluir_id: parseInt($meta.data('lista-id'), 10) || 0
	}).done(function (resp) {
		callback(!!resp.disponible, resp.mensaje || '');
	}).fail(function () {
		callback(false, 'No se pudo validar empresa y categoría.');
	});
}

function marcarErrorCabecera(mensaje) {
	var $select = $('#categoria_automovil_id');
	$select.addClass('is-invalid');
	var $fb = $select.siblings('.invalid-feedback-cabecera');
	if ($fb.length) {
		$fb.text(mensaje || 'Ya existe una lista de precios para esta empresa y categoría.');
	}
	var $aviso = $('#cabecera-duplicada-aviso');
	if ($aviso.length) {
		$aviso.removeClass('d-none').text(mensaje || 'Ya existe una lista de precios para esta empresa y categoría.');
	}
}

function limpiarErrorCabecera() {
	var $select = $('#categoria_automovil_id');
	$select.removeClass('is-invalid');
	$select.siblings('.invalid-feedback-cabecera').text('');
	$('#cabecera-duplicada-aviso').addClass('d-none').text('');
}

/**
 * @param {number|string} itemId
 * @param {string} fecha
 * @param {{excluirUid?: string}} [opts]
 */
function fechaVigenciaDuplicadaParaItem(itemId, fecha, opts) {
	opts = opts || {};
	var fechas = fechasVigenciaPorItem(itemId, opts);

	return fechas.indexOf(fecha) !== -1;
}

/**
 * @param {number|string} itemId
 * @param {{excluirUid?: string, excluirVigente?: boolean}} [opts]
 * @returns {string[]}
 */
function fechasVigenciaPorItem(itemId, opts) {
	opts = opts || {};
	var fechas = [];
	var itemIdNum = parseInt(itemId, 10);

	var $fila = $('tr.fila-item-precio[data-item-id="' + itemId + '"]');
	if ($fila.length && !opts.excluirVigente) {
		var precioVigente = $.trim($fila.find('.precio-vigente').val());
		var fechaVigente = $fila.find('.fecha-vigente').val();
		if (precioVigente !== '' && fechaVigente) {
			fechas.push(fechaVigente);
		}
	}

	$('#payload-historial-precios .payload-historial-row').each(function () {
		if (parseInt($(this).data('item-id'), 10) === itemIdNum) {
			var fecha = $(this).data('fecha');
			if (fecha) {
				fechas.push(String(fecha));
			}
		}
	});

	$('#payload-nuevas-vigencias .payload-nueva-vigencia').each(function () {
		if (opts.excluirUid && $(this).data('uid') === opts.excluirUid) {
			return;
		}
		if (parseInt($(this).data('item-id'), 10) === itemIdNum) {
			var fechaPend = $(this).data('fecha');
			if (fechaPend) {
				fechas.push(String(fechaPend));
			}
		}
	});

	return fechas;
}

function validarFechaVigenteItem(itemId, $input) {
	var fecha = $input.val();
	var precio = $.trim($('tr.fila-item-precio[data-item-id="' + itemId + '"]').find('.precio-vigente').val());

	if (!fecha || precio === '') {
		limpiarErrorFecha($input);
		return true;
	}

	if (fechaVigenciaDuplicadaParaItem(itemId, fecha)) {
		marcarErrorFecha($input, 'Ya existe un precio con esa fecha de vigencia para este ítem.');
		return false;
	}

	limpiarErrorFecha($input);
	return true;
}

function validarFechaNuevaVigencia($wrap) {
	var $input = $wrap.find('.nueva-vigencia-fecha');
	var fecha = $input.val();
	var itemId = $wrap.data('item-id');

	if (!fecha) {
		limpiarErrorFecha($input);
		return true;
	}

	if (fechaVigenciaDuplicadaParaItem(itemId, fecha)) {
		marcarErrorFecha($input, 'Ya existe un precio con esa fecha de vigencia para este ítem.');
		return false;
	}

	limpiarErrorFecha($input);
	return true;
}

function detectarDuplicadosGlobales() {
	var vistos = {};
	var errores = [];

	function registrar(itemId, fecha, etiqueta) {
		if (!itemId || !fecha) {
			return;
		}
		var clave = itemId + '|' + fecha;
		if (vistos[clave]) {
			if (errores.indexOf(etiqueta + ' — ' + formatearFecha(fecha)) === -1) {
				errores.push(etiqueta + ' — ' + formatearFecha(fecha));
			}
		} else {
			vistos[clave] = true;
		}
	}

	recolectarRenglonesPrecio().forEach(function (renglon) {
		var nombre = $('tr.fila-item-precio[data-item-id="' + renglon.item_id + '"]').data('item-nombre') || ('Ítem #' + renglon.item_id);
		registrar(String(renglon.item_id), renglon.fecha_vigencia, nombre);
	});

	return errores;
}

function marcarErrorFecha($input, mensaje) {
	$input.addClass('is-invalid');
	var $fb = $input.siblings('.invalid-feedback-fecha-vigencia');
	if ($fb.length === 0) {
		$fb = $('<div class="invalid-feedback d-block invalid-feedback-fecha-vigencia"></div>');
		$input.after($fb);
	}
	$fb.text(mensaje);
}

function limpiarErrorFecha($input) {
	$input.removeClass('is-invalid');
	$input.siblings('.invalid-feedback-fecha-vigencia').remove();
}

function actualizarPendientesCelda($fila) {
	var itemId = $fila.data('item-id');
	var pendientes = [];
	$('#payload-nuevas-vigencias .payload-nueva-vigencia[data-item-id="' + itemId + '"]').each(function () {
		var precio = $(this).data('precio');
		var fecha = $(this).data('fecha');
		if (precio && fecha) {
			pendientes.push(formatearMoneda(precio) + ' desde ' + formatearFecha(fecha));
		}
	});

	var $celda = $fila.find('.celda-pendientes-vigencia');
	if (pendientes.length === 0) {
		$celda.text('—');
	} else {
		$celda.html(pendientes.map(function (t) {
			return '<span class="badge badge-warning d-block mb-1">' + $('<div>').text(t).html() + '</span>';
		}).join(''));
	}
}

function abrirHistoriaItem(listaId, itemId, nombre) {
	var $modal = $('#modalHistoriaPrecioItem');
	if ($modal.length === 0) {
		return;
	}

	$('#modalHistoriaPrecioItemTitulo').text('Historial de precios');
	$('#modalHistoriaPrecioItemSubtitulo').text(nombre);
	$('#modalHistoriaPrecioItemBody').empty();
	$('#modalHistoriaPrecioItemError').addClass('d-none').text('');
	$('#modalHistoriaPrecioItemCargando').removeClass('d-none');
	$modal.modal('show');

	$.getJSON(carpetaBase + '/caja/estacionamiento/lista-precio/' + listaId + '/historia-item/' + itemId)
		.done(function (resp) {
			$('#modalHistoriaPrecioItemCargando').addClass('d-none');
			var lineas = resp.lineas || [];
			if (lineas.length === 0) {
				$('#modalHistoriaPrecioItemBody').html('<tr><td colspan="4" class="text-muted text-center">Sin registros</td></tr>');
				return;
			}
			var html = '';
			lineas.forEach(function (row) {
				html += '<tr>';
				html += '<td>' + formatearFecha(row.fecha_vigencia) + '</td>';
				html += '<td class="text-right">' + formatearMoneda(row.precio) + '</td>';
				html += '<td>' + $('<div>').text(row.usuario || '—').html() + '</td>';
				html += '<td>' + (row.es_vigente ? '<span class="badge badge-success">Vigente</span>' : '') + '</td>';
				html += '</tr>';
			});
			$('#modalHistoriaPrecioItemBody').html(html);
		})
		.fail(function (xhr) {
			$('#modalHistoriaPrecioItemCargando').addClass('d-none');
			var msg = 'No se pudo cargar el historial.';
			if (xhr.responseJSON && xhr.responseJSON.mensaje) {
				msg = xhr.responseJSON.mensaje;
			}
			$('#modalHistoriaPrecioItemError').removeClass('d-none').text(msg);
		});
}

function formatearFecha(iso) {
	if (!iso || iso.length < 10) {
		return '—';
	}
	var p = iso.substr(0, 10).split('-');
	return p[2] + '/' + p[1] + '/' + p[0];
}

function formatearMoneda(valor) {
	var n = parseFloat(valor);
	if (isNaN(n)) {
		return '—';
	}
	return n.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
