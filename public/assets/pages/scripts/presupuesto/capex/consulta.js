/* Modal CAPEX en líneas de requisición (filtra por empresa, último presupuesto y CC destino de la línea). */
var ptrCapexId;
var ptrCodigoCapex;
var ptrDetalleCapex;
var ptrCentrocostoDestinoIdCapex;

function carpetaBaseCapex() {
	return window.location.pathname.split('/public')[0] + '/public';
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
			centrocostodestino_id: ptrCentrocostoDestinoIdCapex || ''
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
		var $row = $(this).closest('tr.item-requisicion-articulo');
		ptrCapexId = $row.find('.capex_id');
		ptrCodigoCapex = $row.find('.codigocapex');
		ptrDetalleCapex = $row.find('.descripcioncapex');
		ptrCentrocostoDestinoIdCapex = $row.find('select[name="centrocostodestino_ids[]"]').val() || '';
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
}
