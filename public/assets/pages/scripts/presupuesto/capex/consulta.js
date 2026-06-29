/* Modal CAPEX en líneas de requisición (filtra por empresa, último presupuesto y CC destino). */
var ptrCapexId;
var ptrCodigoCapex;
var ptrDetalleCapex;
var ptrFilaCapex;

function carpetaBaseCapex() {
	return window.location.pathname.split('/public')[0] + '/public';
}

function centrocostoFiltroDesdeFilaCapex($row) {
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

function asegurarOpcionesCentrocostoModalCapex($modalSelect, $row, valor) {
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

function centrocostoFiltroModalCapex() {
	return $('#consultacapex_centrocosto_id').val() || '';
}

function buscar_datos_capex(consulta) {
	var empresaId = $('#empresa_id').val();
	$.ajax({
		url: carpetaBaseCapex() + '/presupuesto/consulta_capex',
		type: 'POST',
		dataType: 'json',
		headers: {
			'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
		},
		data: {
			consulta: consulta || '',
			empresa_id: empresaId,
			centrocostodestino_id: centrocostoFiltroModalCapex()
		},
	})
		.done(function (resp) {
			$('#datoscapex').html(resp && resp.data ? resp.data : '');
		})
		.fail(function () {
			$('#datoscapex').html('<tr><td colspan="5">Error al consultar.</td></tr>');
		});
}

function activa_eventos_consultacapex() {
	$(document).off('click.consultaCapexReq', '.consultacapex').on('click.consultaCapexReq', '.consultacapex', function (event) {
		event.preventDefault();
		var $row = $(this).closest('tr.item-requisicion-articulo, tr.item-ordencompra-articulo');
		ptrCapexId = $row.find('.capex_id');
		ptrCodigoCapex = $row.find('.codigocapex');
		ptrDetalleCapex = $row.find('.descripcioncapex');
		ptrFilaCapex = $row;
		var ccInicial = centrocostoFiltroDesdeFilaCapex($row);
		asegurarOpcionesCentrocostoModalCapex($('#consultacapex_centrocosto_id'), $row, ccInicial);
		$('#consultacapexModal').modal('show');
	});

	$('#consultacapexModal').off('shown.bs.modal.consultaCapexReq').on('shown.bs.modal.consultaCapexReq', function () {
		var empresaId = $('#empresa_id').val();
		$('#consultacapex').val('');
		if (!empresaId) {
			$('#datoscapex').html('<tr><td colspan="5">Seleccione una empresa en el encabezado.</td></tr>');
		} else {
			buscar_datos_capex('');
		}
		$(this).find('#consultacapex').focus();
	});

	$('#aceptaconsultacapexModal').off('click.consultaCapexReq').on('click.consultaCapexReq', function () {
		$('#consultacapexModal').modal('hide');
	});

	$(document).off('keyup.consultaCapexReq', '#consultacapex').on('keyup.consultaCapexReq', '#consultacapex', function () {
		buscar_datos_capex($(this).val() || '');
	});

	$(document).off('change.consultaCapexReq', '#consultacapex_centrocosto_id').on('change.consultaCapexReq', '#consultacapex_centrocosto_id', function () {
		buscar_datos_capex($('#consultacapex').val() || '');
	});

	$(document).off('click.consultaCapexReq', '.eligeconsultacapex').on('click.consultaCapexReq', '.eligeconsultacapex', function (event) {
		event.preventDefault();
		var $tr = $(this).closest('tr');
		var id = $tr.find('td.id').text().trim();
		var codigo = $tr.find('td.codigo').text().trim();
		var concepto = $tr.find('td.concepto').text().trim();

		$(ptrCapexId).val(id);
		$(ptrCodigoCapex).val(codigo);
		$(ptrDetalleCapex).val(concepto);

		$('#consultacapexModal').modal('hide');
	});

	$(document).off('keydown.ocCapexCodigo', '.codigocapex').on('keydown.ocCapexCodigo', '.codigocapex', function (e) {
		if (e.which === 13) {
			e.preventDefault();
			$(this).blur();
		}
	});

	$(document).off('blur.ocCapexCodigo', '.codigocapex').on('blur.ocCapexCodigo', '.codigocapex', function () {
		var $inp = $(this);
		if ($inp.prop('readonly')) {
			return;
		}
		var $row = $inp.closest('tr.item-requisicion-articulo, tr.item-ordencompra-articulo');
		var codigo = ($inp.val() || '').trim();
		var empresaId = $('#empresa_id').val();
		var ccDest = centrocostoFiltroDesdeFilaCapex($row);

		if (!codigo) {
			$row.find('.capex_id').val('');
			$row.find('.descripcioncapex').val('');
			$inp.trigger('req:codigo-validado', [true]);
			return;
		}
		if (!empresaId) {
			alert('Seleccione una empresa en el encabezado.');
			return;
		}

		$.ajax({
			url: carpetaBaseCapex() + '/presupuesto/resolver-capex-codigo',
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
					$row.find('.capex_id').val(res.id);
					$inp.val(res.codigo);
					$row.find('.descripcioncapex').val(res.descripcion || '');
					$inp.trigger('req:codigo-validado', [true]);
				} else {
					$row.find('.capex_id').val('');
					$row.find('.descripcioncapex').val('');
					alert((res && res.mensaje) ? res.mensaje : 'CAPEX no encontrado.');
					$inp.trigger('req:codigo-validado', [false]);
				}
			})
			.fail(function (xhr) {
				$row.find('.capex_id').val('');
				$row.find('.descripcioncapex').val('');
				var msg = 'CAPEX no encontrado o no disponible para el centro de costo / presupuesto actual.';
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
