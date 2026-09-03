$(function () {
	var asientoIdActivo = null;

	function csrfToken() {
		var t = $('input[name=_token]').first().val();
		if (t) {
			return t;
		}
		return $('meta[name="csrf-token"]').attr('content') || '';
	}

	function asientoIdDesdeContexto($btn) {
		var id = $btn && $btn.data('asiento-id');
		if (id) {
			return id;
		}
		return $('#id').val() || '';
	}

	function postCopiarRevertir(revierte) {
		var id = asientoIdActivo || $('#id').val();
		if (!id) {
			alert('No se identificó el asiento.');
			return;
		}

		var fecha = revierte
			? ($('#fecha_revertir_asiento').val() || '')
			: ($('#fecha_copiar_asiento').val() || '');

		var url = (window.carpetaBase || '') + '/contable/copiar_asiento';
		var payload = {
			_token: csrfToken(),
			id: id,
			fechacopia: fecha,
			fecha: fecha
		};
		if (revierte) {
			payload.revierte = 1;
		}

		$.post(url, payload)
			.done(function (data) {
				if (data && data.errores) {
					alert(data.errores);
					return;
				}
				if (!data || !data.asiento_id) {
					alert(revierte ? 'No se pudo revertir el asiento.' : 'No se pudo copiar el asiento.');
					return;
				}
				var accion = revierte ? 'REVERTIDO' : 'COPIADO';
				alert('ASIENTO ' + accion + ' CORRECTAMENTE. GENERÓ EL ASIENTO CON ID: ' + data.asiento_id + ' NÚMERO: ' + data.numeroasiento);
				if ($('#tabla-paginada').length) {
					window.location.reload();
				}
			})
			.fail(function (xhr) {
				var msg = (xhr.responseJSON && (xhr.responseJSON.errores || xhr.responseJSON.mensaje))
					? (xhr.responseJSON.errores || xhr.responseJSON.mensaje)
					: 'No se pudo completar la operación.';
				alert(msg);
			});
	}

	$(document).on('click', '#botonform3, .btn-copiar-asiento', function () {
		asientoIdActivo = asientoIdDesdeContexto($(this));
		$('#copiarasientoModal').modal('show');
	});

	$(document).on('click', '#botonform4, .btn-revertir-asiento', function () {
		asientoIdActivo = asientoIdDesdeContexto($(this));
		$('#revertirasientoModal').modal('show');
	});

	$('#aceptacopiarasientoModal').on('click', function () {
		$('#copiarasientoModal').modal('hide');
		postCopiarRevertir(false);
	});

	$('#cierracopiarasientoModal').on('click', function () {
		$('#copiarasientoModal').modal('hide');
	});

	$('#aceptarevertirasientoModal').on('click', function () {
		$('#revertirasientoModal').modal('hide');
		postCopiarRevertir(true);
	});

	$('#cierrarevertirasientoModal').on('click', function () {
		$('#revertirasientoModal').modal('hide');
	});
});
