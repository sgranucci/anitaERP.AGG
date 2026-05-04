/* Modal partida de gasto en líneas de requisición (filtra por empresa, último presupuesto y CC destino de la línea). */
var ptrPartidagastoId;
var ptrCodigoPartidagasto;
var ptrDetallePartidagasto;
var ptrCentrocostoDestinoId;

function carpetaBasePartidagasto() {
	return window.location.pathname.split('/public')[0] + '/public';
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
			centrocostodestino_id: ptrCentrocostoDestinoId || ''
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
		var $row = $(this).closest('tr.item-requisicion-articulo');
		ptrPartidagastoId = $row.find('.partidagasto_id');
		ptrCodigoPartidagasto = $row.find('.codigopartidagasto');
		ptrDetallePartidagasto = $row.find('.descripcionpartidagasto');
		ptrCentrocostoDestinoId = $row.find('select[name="centrocostodestino_ids[]"]').val() || '';
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

	$(document).off('click.consultaPdgReq', '.eligeconsultapartidagasto').on('click.consultaPdgReq', '.eligeconsultapartidagasto', function (event) {
		event.preventDefault();
		var $tr = $(this).closest('tr');
		var id = $tr.find('td.id').text().trim();
		var codigo = $tr.find('td.codigo').text().trim();
		var concepto = $tr.find('td.concepto').text().trim();

		$(ptrPartidagastoId).val(id);
		$(ptrCodigoPartidagasto).val(codigo);
		$(ptrDetallePartidagasto).val(concepto);

		$('#consultapartidagastoModal').modal('hide');
	});
}
