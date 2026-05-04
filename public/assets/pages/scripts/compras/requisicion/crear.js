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

	window.onArticuloSeleccionado = function (dataArticulo, ctx) {
		if (!dataArticulo) return;
		var oficina = dataArticulo.oficinacompra_id || '';
		if (!oficina) return;

		var $of = $('#oficinacompra_id');
		var $ofShow = $('#oficinacompra_id_show');
		var actual = $of.val() || '';
		var oficinaNombre = (window.oficinacompraMap && window.oficinacompraMap[String(oficina)]) ? window.oficinacompraMap[String(oficina)] : oficina;

		if (!actual) {
			$of.val(oficina);
			$ofShow.val(oficinaNombre);
			return;
		}

		if (String(actual) !== String(oficina)) {
			alert('No se permiten artículos de diferentes oficinas de compra en una requisición.');
			if (ctx && ctx.row) {
				var $row = $(ctx.row);
				$row.find('.articulo_id').val('');
				$row.find('.codigoarticulo').val('');
				$row.find('.descripcionarticulo').val('');
			}
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
		$clone.find('input,select').val('');
		$clone.find('select').each(function () {
			$(this).prop('selectedIndex', 0);
		});
		var defCc = $table.attr('data-requisicion-cc-destino-default');
		if (defCc !== undefined && defCc !== null && defCc !== '') {
			var $ccDest = $clone.find('select[name="centrocostodestino_ids[]"]');
			if ($ccDest.find('option[value="' + defCc + '"]').length) {
				$ccDest.val(defCc);
			}
		}
		$tbody.append($clone);
	});

	$(document).on('click', '.eliminar_requisicion_articulo', function (event) {
		event.preventDefault();
		var $tbody = $('#tabla-articulos-requisicion tbody');
		var $rows = $tbody.find('tr.item-requisicion-articulo');
		if ($rows.length > 1) {
			$(this).closest('tr.item-requisicion-articulo').remove();
		} else {
			$(this).closest('tr.item-requisicion-articulo').find('input,select').each(function () {
				if ($(this).is('select')) {
					$(this).prop('selectedIndex', 0);
				} else {
					$(this).val('');
				}
			});
		}
	});

	$("#botonform1").click(function(){
		$(".form1").show();
		$(".form3").hide();
		$(".form4").hide();
		$(".form5").hide();
	});
	$("#botonform3").click(function(){
		$(".form1").hide();
		$(".form3").show();
		$(".form4").hide();
		$(".form5").hide();
		leeHistoria();
	});
	$("#botonform4").click(function(){
		$(".form1").hide();
		$(".form3").hide();
		$(".form4").show();
		$(".form5").hide();
	});
	$("#botonform5").click(function(){
		$(".form1").hide();
		$(".form3").hide();
		$(".form4").hide();
		$(".form5").show();
		leeArbol();
	});

	$( "#botonform0" ).click(function() {
		$("#form-general").submit();
	});
		
	function leeHistoria() {
		var id = $("#requisicion_id").val();
		if (!id) return;
		var url = carpetaBase + '/compras/leer_historia_requisicion/' + id;
		$.get(url, function (historia) {
			var $w = $(".container-historia").empty();
			$.each(historia, function (_, value) {
				var fecha = value.fecha ? value.fecha.substring(0, 10) : '';
				$w.append(
					'<tr><td><input type="date" class="form-control" value="' + fecha + '" readonly></td>' +
					'<td><input type="text" class="form-control" value="' + (value.estado || '') + '" readonly></td>' +
					'<td><input type="text" class="form-control" value="' + (value.usuarios && value.usuarios.nombre ? value.usuarios.nombre : '') + '" readonly></td>' +
					'<td><input type="text" class="form-control" value="' + (value.observacion || '') + '" readonly></td></tr>'
				);
			});
		});
	}

	function fechaArbolTexto(raw) {
		if (raw == null || raw === '') {
			return '';
		}
		return String(raw).substring(0, 19).replace('T', ' ');
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

	$(".form3,.form4,.form5").hide();
	$(".form1").show();

	$('#empresa_id').on('change', function () {
		actualizaAvisoArbolGrabacion();
	});

	actualizaAvisoArbolGrabacion();
});
