/* Modal partida de gasto en líneas de requisición (filtra por empresa, último presupuesto y CC destino). */
var ptrPartidagastoId;
var ptrCodigoPartidagasto;
var ptrDetallePartidagasto;
var ptrFilaPartidagasto;

function carpetaBasePartidagasto() {
	if (typeof window.carpetaBase === 'string') {
		return window.carpetaBase;
	}
	var loc = window.location.pathname || '';
	var m = loc.match(/^(.*\/public)(?:\/|$)/);
	return m ? m[1] : '';
}

function centrocostoFiltroDesdeFila($row) {
	var cc = '';
	if ($row && $row.length) {
		cc = $row.find('select[name="centrocostodestino_ids[]"]').val() || '';
	}
	if (!cc) {
		cc = $('#centrocosto_id').val() || '';
	}
	if (!cc) {
		cc = $('#wz_centrocosto_id').val() || '';
	}
	return cc;
}

function asegurarOpcionesCentrocostoModal($modalSelect, $row, valor) {
	if (!$modalSelect || !$modalSelect.length) {
		return;
	}
	if ($modalSelect.find('option').length > 1) {
		if (valor && $modalSelect.find('option[value="' + valor + '"]').length) {
			$modalSelect.val(valor);
		}
		return;
	}

	var $src = ($row && $row.length) ? $row.find('select[name="centrocostodestino_ids[]"]') : $();
	if (!$src.length) {
		$src = $('select[name="centrocostodestino_ids[]"]').first();
	}
	if ($src.length) {
		$src.find('option').each(function () {
			var v = $(this).attr('value');
			if (v === undefined || v === null || v === '') {
				return;
			}
			$modalSelect.append($(this).clone());
		});
	}

	if (valor && $modalSelect.find('option[value="' + valor + '"]').length) {
		$modalSelect.val(valor);
	} else {
		$modalSelect.val('');
	}
}

function centrocostoFiltroModalPartidagasto() {
	return $('#consultapartidagasto_centrocosto_id').val() || '';
}

function buscar_datos_partidagasto(consulta) {
	var empresaId = $('#empresa_id').val();
	$.ajax({
		url: carpetaBasePartidagasto() + '/presupuesto/consulta_partidagasto',
		type: 'POST',
		dataType: 'json',
		headers: {
			'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
		},
		data: {
			consulta: consulta || '',
			empresa_id: empresaId,
			centrocostodestino_id: centrocostoFiltroModalPartidagasto()
		},
	})
		.done(function (resp) {
			$('#datospartidagasto').html(resp && resp.data ? resp.data : '');
		})
		.fail(function () {
			$('#datospartidagasto').html('<tr><td colspan="5">Error al consultar.</td></tr>');
		});
}

function activa_eventos_consultapartidagasto() {
	$(document).off('click.consultaPdgReq', '.consultapartidagasto').on('click.consultaPdgReq', '.consultapartidagasto', function (event) {
		event.preventDefault();
		var $row = $(this).closest('tr.item-requisicion-articulo, tr.item-ordencompra-articulo');
		ptrPartidagastoId = $row.find('.partidagasto_id');
		ptrCodigoPartidagasto = $row.find('.codigopartidagasto');
		ptrDetallePartidagasto = $row.find('.descripcionpartidagasto');
		ptrFilaPartidagasto = $row;
		var ccInicial = centrocostoFiltroDesdeFila($row);
		asegurarOpcionesCentrocostoModal($('#consultapartidagasto_centrocosto_id'), $row, ccInicial);
		$('#consultapartidagastoModal').modal('show');
	});

	$('#consultapartidagastoModal').off('shown.bs.modal.consultaPdgReq').on('shown.bs.modal.consultaPdgReq', function () {
		var empresaId = $('#empresa_id').val();
		$('#consultapartidagasto').val('');
		if (!empresaId) {
			$('#datospartidagasto').html('<tr><td colspan="5">Seleccione una empresa en el encabezado.</td></tr>');
		} else {
			buscar_datos_partidagasto('');
		}
		$(this).find('#consultapartidagasto').focus();
	});

	$('#aceptaconsultapartidagastoModal').off('click.consultaPdgReq').on('click.consultaPdgReq', function () {
		$('#consultapartidagastoModal').modal('hide');
	});

	$(document).off('keyup.consultaPdgReq', '#consultapartidagasto').on('keyup.consultaPdgReq', '#consultapartidagasto', function () {
		buscar_datos_partidagasto($(this).val() || '');
	});

	$(document).off('change.consultaPdgReq', '#consultapartidagasto_centrocosto_id').on('change.consultaPdgReq', '#consultapartidagasto_centrocosto_id', function () {
		buscar_datos_partidagasto($('#consultapartidagasto').val() || '');
	});

	$(document).off('click.consultaPdgReq', '.eligeconsultapartidagasto').on('click.consultaPdgReq', '.eligeconsultapartidagasto', function (event) {
		event.preventDefault();
		var $tr = $(this).closest('tr');
		var id = $tr.find('td.id').text().trim();
		var codigo = $tr.find('td.codigo').text().trim();
		var concepto = $tr.find('td.concepto').text().trim();
		if (!concepto) {
			concepto = '(Sin descripción en artículo — partida asignada)';
		}

		$(ptrPartidagastoId).val(id);
		$(ptrCodigoPartidagasto).val(codigo);
		$(ptrDetallePartidagasto).val(concepto);

		$('#consultapartidagastoModal').modal('hide');
	});

	$(document).off('keydown.ocPdgCodigo', '.codigopartidagasto').on('keydown.ocPdgCodigo', '.codigopartidagasto', function (e) {
		if (e.which === 13) {
			e.preventDefault();
			$(this).blur();
		}
	});

	$(document).off('blur.ocPdgCodigo', '.codigopartidagasto').on('blur.ocPdgCodigo', '.codigopartidagasto', function () {
		var $inp = $(this);
		if ($inp.prop('readonly')) {
			return;
		}
		var $row = $inp.closest('tr.item-requisicion-articulo, tr.item-ordencompra-articulo');
		var codigo = ($inp.val() || '').trim();
		var empresaId = $('#empresa_id').val();
		var ccDest = centrocostoFiltroDesdeFila($row);

		if (!codigo) {
			$row.find('.partidagasto_id').val('');
			$row.find('.descripcionpartidagasto').val('');
			$inp.trigger('req:codigo-validado', [true]);
			return;
		}
		if (!empresaId) {
			alert('Seleccione una empresa en el encabezado.');
			return;
		}

		$.ajax({
			url: carpetaBasePartidagasto() + '/presupuesto/resolver-partidagasto-codigo',
			type: 'POST',
			dataType: 'json',
			headers: {
				'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
			},
			data: {
				codigo: codigo,
				empresa_id: empresaId,
				centrocostodestino_id: ccDest
			}
		})
			.done(function (res) {
				if (res && res.ok) {
					$row.find('.partidagasto_id').val(res.id);
					$inp.val(res.codigo);
					$row.find('.descripcionpartidagasto').val(
						res.descripcion && String(res.descripcion).trim() !== ''
							? res.descripcion
							: '(Sin descripción en artículo — partida asignada)'
					);
					$inp.trigger('req:codigo-validado', [true]);
				} else {
					$row.find('.partidagasto_id').val('');
					$row.find('.descripcionpartidagasto').val('');
					alert((res && res.mensaje) ? res.mensaje : 'Partida no encontrada.');
					$inp.trigger('req:codigo-validado', [false]);
				}
			})
			.fail(function (xhr) {
				$row.find('.partidagasto_id').val('');
				$row.find('.descripcionpartidagasto').val('');
				var msg = 'Partida no encontrada o no disponible para el centro de costo / presupuesto actual.';
				if (xhr.responseJSON && xhr.responseJSON.mensaje) {
					msg = xhr.responseJSON.mensaje;
				} else if (xhr.responseText) {
					try {
						var j = JSON.parse(xhr.responseText);
						if (j && j.mensaje) {
							msg = j.mensaje;
						}
					} catch (eIgn) {}
				}
				alert(msg);
				$inp.trigger('req:codigo-validado', [false]);
			});
	});
}
