$(function () {
	if (typeof carpetaBase === 'undefined' || carpetaBase === '') {
		window.carpetaBase = window.location.pathname.split('/public')[0] + '/public';
	}

	if (typeof activa_eventos_consultaarticulo === 'function') {
		activa_eventos_consultaarticulo();
	}

	$('#agrega_renglon_categoriafidelidad_articulo').on('click', function () {
		var $tbody = $('#tabla-articulos-categoriafidelidad tbody');
		var $first = $tbody.find('tr.item-categoriafidelidad-articulo').first();
		var $clone = $first.clone();
		$clone.find('input').not('.descripcionarticulo').val('');
		$clone.find('.linea_id').val('');
		$clone.find('.descripcionarticulo').val('');
		$tbody.append($clone);
	});

	$(document).on('click', '.eliminar_categoriafidelidad_articulo', function (event) {
		event.preventDefault();
		var $tbody = $('#tabla-articulos-categoriafidelidad tbody');
		var $rows = $tbody.find('tr.item-categoriafidelidad-articulo');
		if ($rows.length > 1) {
			$(this).closest('tr.item-categoriafidelidad-articulo').remove();
		} else {
			$(this).closest('tr.item-categoriafidelidad-articulo').find('input').each(function () {
				if ($(this).hasClass('linea_id') || $(this).hasClass('articulo_id')) {
					$(this).val('');
				} else if ($(this).hasClass('descripcionarticulo') || $(this).hasClass('codigoarticulo')) {
					$(this).val('');
				}
			});
		}
	});
});
