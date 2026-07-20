$(function () {
	// No redefinir carpetaBase: el layout ya define la base correcta (app.app_carpeta).
	// Redefinirla rompía las peticiones AJAX en rutas como visualizar (sin /public en el path).
	if (typeof carpetaBase === 'undefined' || carpetaBase === '') {
		window.carpetaBase = window.location.pathname.split('/public')[0] + '/public';
	}

	function formateaAvisoGrabacion(msg) {
		if (!msg) {
			return '';
		}
		if (/grabar|guardar/i.test(msg)) {
			return msg;
		}
		return msg + ' La requisición no se podrá grabar en estado pendiente.';
	}

	function setAvisoArbolEstado(mode, mensaje) {
		var $box = $('#requisicion-aviso-arbol-grabacion');
		if (!$box.length) {
			return;
		}
		var $sp = $('#requisicion-aviso-arbol-spinner');
		if (mode === 'loading') {
			$box.removeClass('d-none alert-warning').addClass('alert-secondary');
			$box.find('.texto').text(mensaje || 'Verificando el árbol de aprobación…');
			if ($sp.length) {
				$sp.show();
			}
			return;
		}
		if ($sp.length) {
			$sp.hide();
		}
		if (mode === 'ok' || !mensaje) {
			$box.addClass('d-none').removeClass('alert-warning').addClass('alert-secondary');
			$box.find('.texto').text('');
			return;
		}
		$box.removeClass('d-none alert-secondary').addClass('alert-warning');
		$box.find('.texto').text(formateaAvisoGrabacion(mensaje));
	}

	function actualizaAvisoArbolGrabacion() {
		var eid = parseInt($('#empresa_id').val(), 10) || 0;
		var rid = parseInt($('#requisicion_id').val(), 10) || 0;
		if (rid <= 0 && eid <= 0) {
			setAvisoArbolEstado('ok');
			return;
		}
		setAvisoArbolEstado('loading');
		$.get(carpetaBase + '/compras/requisicion/aviso-arbol-grabacion', {
			empresa_id: eid,
			requisicion_id: rid
		}).done(function (data) {
			if (data && data.aviso) {
				setAvisoArbolEstado('warn', data.aviso);
			} else {
				setAvisoArbolEstado('ok');
			}
		}).fail(function () {
			setAvisoArbolEstado('ok');
		});
	}

	function reqFormatearCantAlt(num) {
		if (!isFinite(num)) {
			return '';
		}
		var t = parseFloat(num.toFixed(4));
		if (t === 0) {
			return '0';
		}
		return String(t);
	}

	function reqLimpiarCantidadAlternativaHint($tr) {
		$tr.find('.req-unidadesxenvase').val('');
		$tr.find('.req-um-alt-abrev').val('');
		$tr.find('.req-cantidadalternativa').val('');
		$tr.find('.req-cant-alt-valor').addClass('text-muted').text('—');
		$tr.find('.req-cant-alt-um').text('');
	}

	function reqEnriquecerUmAltDesdeArticulo($tr, dataArticulo) {
		if (!$tr || !$tr.length || !dataArticulo) {
			return;
		}
		var umdAlt = (dataArticulo.unidadesdemedidasalternativas && dataArticulo.unidadesdemedidasalternativas.abreviatura)
			|| dataArticulo.um_alternativa_abreviatura
			|| '';
		var uxenv = parseFloat(dataArticulo.unidadesxenvase) || 0;
		$tr.find('.req-unidadesxenvase').val(uxenv > 0 ? uxenv : '');
		$tr.find('.req-um-alt-abrev').val(umdAlt || '');
		reqActualizarCantidadAlternativaHint($tr);
	}

	function reqActualizarCantidadAlternativaHint($tr) {
		if (!$tr || !$tr.length) {
			return;
		}
		var uxenv = parseFloat($tr.find('.req-unidadesxenvase').val()) || 0;
		var abrev = ($tr.find('.req-um-alt-abrev').val() || '').trim();
		var cant = parseFloat($tr.find('.cantidad-linea').val()) || 0;
		var $valor = $tr.find('.req-cant-alt-valor');
		var $um = $tr.find('.req-cant-alt-um');
		var $hidden = $tr.find('.req-cantidadalternativa');

		if (uxenv <= 0) {
			$hidden.val('');
			$valor.addClass('text-muted').text('—');
			$um.text('');
			return;
		}

		var altTxt = reqFormatearCantAlt(cant * uxenv);
		$hidden.val(altTxt);
		$valor.removeClass('text-muted').text(altTxt);
		$um.text(abrev || '');
	}

	window.onArticuloSeleccionado = function (dataArticulo, ctx) {
		if (!dataArticulo) return;
		var $row = (ctx && ctx.row) ? $(ctx.row) : $();
		var enReq = $row.length && $row.closest('#tabla-articulos-requisicion').length;
		var oficina = dataArticulo.oficinacompra_id || '';

		if (oficina) {
			var $of = $('#oficinacompra_id');
			var $ofShow = $('#oficinacompra_id_show');
			var actual = $of.val() || '';
			var oficinaNombre = (window.oficinacompraMap && window.oficinacompraMap[String(oficina)]) ? window.oficinacompraMap[String(oficina)] : oficina;

			if (!actual) {
				$of.val(oficina);
				$ofShow.val(oficinaNombre);
			} else if (String(actual) !== String(oficina)) {
				alert('No se permiten artículos de diferentes oficinas de compra en una requisición.');
				if (enReq) {
					$row.find('.articulo_id').val('');
					$row.find('.codigoarticulo').val('');
					$row.find('.descripcionarticulo').val('');
					reqLimpiarCantidadAlternativaHint($row);
				}
				return;
			}
		}

		if (enReq) {
			if (typeof window.msAplicarExclusividadColorTalle === 'function') {
				if (!window.msAplicarExclusividadColorTalle(dataArticulo, $row)) {
					return;
				}
			}
			reqEnriquecerUmAltDesdeArticulo($row, dataArticulo);
		}
	};

	if (typeof activa_eventos_consultaarticulo === 'function') {
		activa_eventos_consultaarticulo();
	}
	if (typeof activa_eventos_consultapartidagasto === 'function') {
		activa_eventos_consultapartidagasto();
	}
	if (typeof activa_eventos_consultacapex === 'function') {
		activa_eventos_consultacapex();
	}
	if (typeof activa_eventos_consultaproveedor === 'function') {
		activa_eventos_consultaproveedor();
	}
	$('#agrega_renglon_requisicion_articulo').on('click', function () {
		var $tbody = $('#tabla-articulos-requisicion tbody');
		var $table = $('#tabla-articulos-requisicion');
		var $first = $tbody.find('tr.item-requisicion-articulo').first();
		var $clone = $first.clone();
		$clone.removeClass('req-requisicion-linea-cerrada').removeAttr('title');
		$clone.find('input,select').val('');
		$clone.find('select.ms-color-id, select.ms-talle-id').val('').attr('data-selected', '');
		$clone.attr('data-maneja-stock-color-talle', '0');
		reqLimpiarCantidadAlternativaHint($clone);
		$clone.removeAttr('data-req-cc-manual data-req-moneda-manual');
		$clone.find('select').each(function () {
			$(this).prop('selectedIndex', 0);
		});
		$tbody.append($clone);
		if (typeof window.reqLineasAplicarPatronFila === 'function') {
			window.reqLineasAplicarPatronFila($clone);
		} else {
			var defCc = $table.attr('data-requisicion-cc-destino-default');
			if (defCc !== undefined && defCc !== null && defCc !== '') {
				var $ccDest = $clone.find('select[name="centrocostodestino_ids[]"]');
				if ($ccDest.find('option[value="' + defCc + '"]').length) {
					$ccDest.val(defCc);
				}
			}
			var defMon = $table.attr('data-requisicion-moneda-default');
			if (defMon !== undefined && defMon !== null && defMon !== '') {
				var $mon = $clone.find('select[name="moneda_linea_ids[]"]');
				if ($mon.find('option[value="' + defMon + '"]').length) {
					$mon.val(defMon);
				}
			}
		}
		setTimeout(function () {
			$tbody.find('tr.item-requisicion-articulo').last().find('.codigoarticulo').trigger('focus');
		}, 0);
		if (typeof window.reqLineasScheduleTotales === 'function') {
			window.reqLineasScheduleTotales();
		}
	});

	$(document).on('click', '.eliminar_requisicion_articulo', function (event) {
		event.preventDefault();
		var $tbody = $('#tabla-articulos-requisicion tbody');
		var $rows = $tbody.find('tr.item-requisicion-articulo');
		if ($rows.length > 1) {
			$(this).closest('tr.item-requisicion-articulo').remove();
		} else {
			var $tr = $(this).closest('tr.item-requisicion-articulo');
			$tr.find('input,select').each(function () {
				if ($(this).is('select')) {
					$(this).prop('selectedIndex', 0);
				} else {
					$(this).val('');
				}
			});
			reqLimpiarCantidadAlternativaHint($tr);
		}
		if (typeof window.reqLineasScheduleTotales === 'function') {
			window.reqLineasScheduleTotales();
		}
	});

	$(document).on('input change', '#tabla-articulos-requisicion .cantidad-linea', function () {
		reqActualizarCantidadAlternativaHint($(this).closest('tr.item-requisicion-articulo'));
	});

	$(document).on('change', '#tabla-articulos-requisicion .codigoarticulo', function () {
		var sku = ($(this).val() || '').trim();
		if (!sku) {
			reqLimpiarCantidadAlternativaHint($(this).closest('tr.item-requisicion-articulo'));
		}
	});

	$('#tabla-articulos-requisicion tbody tr.item-requisicion-articulo').each(function () {
		reqActualizarCantidadAlternativaHint($(this));
	});

	function requisicionToggleFooterPresupuesto(mostrarSolapaPresupuesto) {
		var $actions = $('#requisicion-footer-presupuesto-actions');
		if (!$actions.length) {
			return;
		}
		if (mostrarSolapaPresupuesto) {
			$actions.removeClass('d-none');
		} else {
			$actions.addClass('d-none');
		}
	}

	$("#botonform1").click(function(){
		$(".form1").show();
		$(".form3").hide();
		$(".form4").hide();
		$(".form5").hide();
		$(".form6").hide();
		requisicionToggleFooterPresupuesto(false);
	});
	$("#botonform3").click(function(){
		$(".form1").hide();
		$(".form3").show();
		$(".form4").hide();
		$(".form5").hide();
		$(".form6").hide();
		requisicionToggleFooterPresupuesto(false);
		leeHistoria();
	});
	$("#botonform4").click(function(){
		$(".form1").hide();
		$(".form3").hide();
		$(".form4").show();
		$(".form5").hide();
		$(".form6").hide();
		requisicionToggleFooterPresupuesto(false);
		var sol = document.getElementById('requisicion-solapa-archivos-adjuntos');
		if (sol) {
			sol.scrollIntoView({ behavior: 'smooth', block: 'start' });
		}
	});
	$("#botonform5").click(function(){
		$(".form1").hide();
		$(".form3").hide();
		$(".form4").hide();
		$(".form5").show();
		$(".form6").hide();
		requisicionToggleFooterPresupuesto(false);
		leeArbol();
	});
	$(document).on('click', '#boton-solapa-presupuesto-requisicion', function (e) {
		e.preventDefault();
		$(".form1").hide();
		$(".form3").hide();
		$(".form4").hide();
		$(".form5").hide();
		$(".form6").show();
		requisicionToggleFooterPresupuesto(true);
		if (typeof window.cargaSolapaPresupuestos === 'function') {
			window.cargaSolapaPresupuestos();
		}
	});

	$(document).on('click', '#btn-footer-volver-datos-requisicion', function (e) {
		e.preventDefault();
		$('#botonform1').trigger('click');
	});

	$(document).on('click', '#botonform0', function (e) {
		e.preventDefault();
		var $f = $('#form-general');
		if ($f.length) {
			$f.trigger('submit');
		}
	});

	$(document).on('click', '.eliminar-archivo-requisicion', function (event) {
		event.preventDefault();
		var $wrap = $(this).closest('.requisicion-archivo-item');
		if ($wrap.length) {
			$wrap.remove();
		}
	});

	// Archivos nuevos: misma interacción que compras/proveedor (tabla + plantilla)
	$('#agrega_renglon_archivo').on('click', function (event) {
		event.preventDefault();
		var tpl = $('#template-renglon-archivo').html();
		if (!tpl) {
			return;
		}
		$('#tbody-tabla-archivo').append(tpl);
	});

	$(document).on('click', '#tbody-tabla-archivo .eliminararchivo', function (event) {
		event.preventDefault();
		$(this).closest('tr.item-archivo').remove();
	});

	$(document).on('change', '#tbody-tabla-archivo .nombrearchivos', function () {
		var fn = $(this).val();
		if (!fn) {
			return;
		}
		var filename = fn.match(/[^\\/]*$/)[0];
		$(this).closest('tr').find('.nombresanteriores').val(filename);
	});

	function leeHistoria() {
		var id = $("#requisicion_id").val();
		if (!id) return;
		var url = carpetaBase + '/compras/leer_historia_requisicion/' + id;
		$.get(url, function (historia) {
			var $w = $(".container-historia").empty();
			$.each(historia, function (_, value) {
				var fechaTxt = formatearFechaHoraEstado(value.fecha);
				var estadoEsc = $('<div>').text(value.estado || '').html();
				var usrEsc = $('<div>').text(value.usuarios && value.usuarios.nombre ? value.usuarios.nombre : '').html();
				var obsEsc = $('<div>').text(value.observacion || '').html();
				$w.append(
					'<tr><td><input type="text" class="form-control" value="' + $('<div>').text(fechaTxt).html() + '" readonly></td>' +
					'<td><input type="text" class="form-control" value="' + estadoEsc + '" readonly></td>' +
					'<td><input type="text" class="form-control" value="' + usrEsc + '" readonly></td>' +
					'<td><input type="text" class="form-control" value="' + obsEsc + '" readonly></td></tr>'
				);
			});
		});
	}

	function formatearFechaHoraEstado(raw) {
		if (raw == null || raw === '') {
			return '';
		}
		var s = String(raw).replace('T', ' ');
		if (s.length >= 19) {
			return s.substring(0, 19);
		}
		return s;
	}

	function fechaArbolTexto(raw) {
		if (raw == null || raw === '') {
			return '';
		}
		var s = String(raw).replace('T', ' ').trim();
		var m = s.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/);
		if (!m) {
			return s;
		}
		var out = m[3] + '-' + m[2] + '-' + m[1];
		if (m[4] !== undefined) {
			out += ' ' + m[4] + ':' + m[5];
			if (m[6] !== undefined) {
				out += ':' + m[6];
			}
		}
		return out;
	}

	function leeArbol() {
		var id = $("#requisicion_id").val();
		if (!id) return;
		var url = carpetaBase + '/arbolaprobacion/leer_movimiento_aprobacion/RE/' + id;
		var $w = $(".container-arbol");
		$w.empty();
		$w.append('<tr><td colspan="7" class="text-center text-muted">Cargando movimientos del árbol de aprobación…</td></tr>');
		setAvisoArbolEstado('loading', 'Mientras se cargan los datos del árbol de aprobación: si la empresa no tiene un árbol de requisiciones definido (o no aplica a esta requisición), no podrá grabar en estado pendiente.');
		$.ajax({
			url: url,
			method: 'GET',
			dataType: 'json',
			cache: false
		}).done(function (resp) {
			var rows = Array.isArray(resp) ? resp : (resp.movimientos || []);
			var aviso = (!Array.isArray(resp) && resp.aviso_grabacion_pendiente) ? resp.aviso_grabacion_pendiente : null;
			$w.empty();
			if (aviso) {
				setAvisoArbolEstado('warn', aviso);
			} else {
				setAvisoArbolEstado('ok');
			}
			if (!rows.length) {
				$w.append('<tr><td colspan="7" class="text-center text-muted">Sin movimientos registrados en el árbol.</td></tr>');
			}
			$.each(rows, function (_, value) {
				var obs = value.observacion || '';
				if (value.indicacion_estado_requisicion) {
					obs = obs + (obs ? ' — ' : '') + value.indicacion_estado_requisicion;
				}
				var $tr = $('<tr></tr>');
				$tr.append($('<td></td>').append($('<input type="text" class="form-control" readonly>').val(fechaArbolTexto(value.fechaenvio))));
				$tr.append($('<td></td>').append($('<input type="text" class="form-control" readonly>').val((value.enviousuarios && value.enviousuarios.nombre) || '')));
				$tr.append($('<td></td>').append($('<input type="text" class="form-control" readonly>').val(value.nivel !== undefined && value.nivel !== null ? value.nivel : '')));
				$tr.append($('<td></td>').append($('<input type="text" class="form-control" readonly>').val(value.estado || '')));
				$tr.append($('<td></td>').append($('<input type="text" class="form-control" readonly>').val(fechaArbolTexto(value.fechaproceso))));
				$tr.append($('<td></td>').append($('<input type="text" class="form-control" readonly>').val((value.destinatariousuarios && value.destinatariousuarios.nombre) || '')));
				$tr.append($('<td></td>').append($('<input type="text" class="form-control" readonly>').attr('title', obs).val(obs)));
				$w.append($tr);
			});
		}).fail(function () {
			$w.empty().append('<tr><td colspan="7" class="text-center text-danger">No se pudieron cargar los movimientos del árbol de aprobación.</td></tr>');
			setAvisoArbolEstado('ok');
		});
	}

	$(".form3,.form4,.form5,.form6").hide();
	$(".form1").show();
	requisicionToggleFooterPresupuesto(false);

	function claveUltimaEmpresaRequisicion() {
		var usuarioId = parseInt((window.requisicionEmpresaRecordar || {}).usuarioId, 10) || 0;
		if (usuarioId <= 0) {
			return '';
		}
		return 'anitaERP_requisicion_ultima_empresa_u' + usuarioId;
	}

	function leerUltimaEmpresaRequisicion() {
		var key = claveUltimaEmpresaRequisicion();
		if (!key) {
			return '';
		}
		try {
			return String(localStorage.getItem(key) || '').trim();
		} catch (eIgn) {
			return '';
		}
	}

	function guardarUltimaEmpresaRequisicion(empresaId) {
		var key = claveUltimaEmpresaRequisicion();
		var id = String(empresaId || '').trim();
		if (!key || !id) {
			return;
		}
		try {
			localStorage.setItem(key, id);
		} catch (eIgn) {}
	}

	function aplicarUltimaEmpresaRequisicionSiCorresponde() {
		if (($('#requisicion_id').val() || '').trim() !== '') {
			return false;
		}
		var $emp = $('#empresa_id');
		if (!$emp.length || ($emp.is(':hidden') && $emp.attr('type') === 'hidden')) {
			return false;
		}
		if ($emp.is('select') === false) {
			return false;
		}
		if (($emp.val() || '').trim() !== '') {
			return false;
		}
		var guardada = leerUltimaEmpresaRequisicion();
		if (!guardada || !$emp.find('option[value="' + guardada + '"]').length) {
			return false;
		}
		$emp.val(guardada).trigger('change');
		return true;
	}

	$('#empresa_id').on('change', function () {
		var empresaId = ($(this).val() || '').trim();
		if (empresaId) {
			guardarUltimaEmpresaRequisicion(empresaId);
		}
		actualizaAvisoArbolGrabacion();
	});

	if (window.requisicionModoProvisorio) {
		setAvisoArbolEstado('ok');
	} else if (!aplicarUltimaEmpresaRequisicionSiCorresponde()) {
		actualizaAvisoArbolGrabacion();
	}
});
