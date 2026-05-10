$(function () {
	if (typeof carpetaBase === 'undefined' || carpetaBase === '') {
		window.carpetaBase = window.location.pathname.split('/public')[0] + '/public';
	}

	if (typeof activa_eventos_consultaarticulo === 'function') {
		activa_eventos_consultaarticulo();
	}

	$('#agrega_renglon_listaprecio_articulo').on('click', function () {
		var $tbody = $('#tabla-articulos-listaprecio tbody');
		var $first = $tbody.find('tr.item-listaprecio-articulo').first();
		var $clone = $first.clone();
		$clone.find('input,select').not('.descripcionarticulo').val('');
		$clone.find('.linea_id').val('');
		$clone.find('.descripcionarticulo').val('');
		var hoy = new Date().toISOString().slice(0, 10);
		$clone.find('input[name="fechavigencias[]"]').val(hoy);
		$tbody.append($clone);
	});

	$(document).on('click', '.eliminar_listaprecio_articulo', function (event) {
		event.preventDefault();
		var $tbody = $('#tabla-articulos-listaprecio tbody');
		var $rows = $tbody.find('tr.item-listaprecio-articulo');
		if ($rows.length > 1) {
			$(this).closest('tr.item-listaprecio-articulo').remove();
		} else {
			$(this).closest('tr.item-listaprecio-articulo').find('input,select').each(function () {
				if ($(this).hasClass('linea_id')) {
					$(this).val('');
				} else if ($(this).hasClass('articulo_id')) {
					$(this).val('');
				} else if ($(this).hasClass('descripcionarticulo')) {
					$(this).val('');
				} else if ($(this).attr('name') === 'fechavigencias[]') {
					$(this).val(new Date().toISOString().slice(0, 10));
				} else {
					$(this).val('');
				}
			});
		}
	});

	$("#botonform1").click(function () {
		$(".form1").show();
		$(".form3").hide();
		$(".form4").hide();
		$("#importar-excel").hide();
	});
	$("#botonform3").click(function () {
		$(".form1").hide();
		$(".form3").show();
		$(".form4").hide();
		$("#importar-excel").hide();
		leeHistoria();
	});
	$("#botonform4").click(function () {
		$(".form1").hide();
		$(".form3").hide();
		$(".form4").show();
		$("#importar-excel").hide();
	});

	$('#agrega_renglon_archivo_listaprecio').on('click', function (event) {
		event.preventDefault();
		var tpl = document.getElementById('template-renglon-archivo-listaprecio');
		var tbody = document.getElementById('tbody-tabla-archivo-listaprecio');
		if (!tpl || !tbody) {
			return;
		}
		// jQuery .html() sobre <template> suele devolver vacío; usar el fragmento del template.
		if (tpl.content) {
			tbody.appendChild(document.importNode(tpl.content, true));
		} else {
			var html = $(tpl).html();
			if (html) {
				$('#tbody-tabla-archivo-listaprecio').append(html);
			}
		}
	});

	$(document).on('click', '#tbody-tabla-archivo-listaprecio .eliminararchivo', function (event) {
		event.preventDefault();
		$(this).parents('tr').remove();
	});

	$(document).on('click', '.eliminar-archivo-listaprecio', function (event) {
		event.preventDefault();
		var $wrap = $(this).closest('.listaprecio-archivo-item');
		if ($wrap.length) {
			$wrap.remove();
			return;
		}
		$(this).closest('.col-md-6').remove();
	});

	$("#botonform-importexcel").on("click", function () {
		var $target = $("#importar-excel");
		if (!$target.length) {
			return;
		}
		$(".form1").show();
		$(".form3").hide();
		$(".form4").hide();
		$target.show();
		$("html, body").animate(
			{ scrollTop: $target.offset().top - 72 },
			350
		);
	});

	function leeHistoria() {
		var id = $("#listaprecio_proveedor_id").val();
		if (!id) return;
		var url = carpetaBase + '/compras/leer_historia_listaprecio_proveedor/' + id;
		$.get(url, function (historia) {
			var $w = $(".container-historia").empty();
			$.each(historia, function (_, value) {
				var fecha = value.fecha ? String(value.fecha).substring(0, 16) : '';
				$w.append(
					'<tr><td><input type="text" class="form-control" value="' + fecha + '" readonly></td>' +
					'<td><input type="text" class="form-control" value="' + (value.estado || '') + '" readonly></td>' +
					'<td><input type="text" class="form-control" value="' + (value.usuarios && value.usuarios.nombre ? value.usuarios.nombre : '') + '" readonly></td>' +
					'<td><input type="text" class="form-control" value="' + (value.observacion || '').replace(/"/g, '&quot;') + '" readonly></td></tr>'
				);
			});
		});
	}

	$(".form3,.form4").hide();
	$(".form1").show();
	var $imp = $("#importar-excel");
	if ($imp.length && window.location.hash === "#importar-excel") {
		$imp.show();
		setTimeout(function () {
			$("html, body").animate({ scrollTop: $imp.offset().top - 72 }, 200);
		}, 100);
	} else {
		$imp.hide();
	}
});

function actualizaArchivo(elem) {
	var fn = $(elem).val();
	var filename = fn.match(/[^\\/]*$/)[0];
	$(elem).parents('tr').find('.nombresanteriores').val(filename);
}
