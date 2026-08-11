$(function () {
	if (typeof window.carpetaBase === 'undefined') {
		var __locCb = window.location.pathname || '';
		var __mCb = __locCb.match(/^(.*\/public)(?:\/|$)/);
		window.carpetaBase = __mCb ? __mCb[1] : '';
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
