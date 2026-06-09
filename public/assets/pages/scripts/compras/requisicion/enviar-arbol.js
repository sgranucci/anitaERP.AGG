$(function () {
	if (typeof carpetaBase === 'undefined' || carpetaBase === '') {
		window.carpetaBase = window.location.pathname.split('/public')[0] + '/public';
	}

	var $modal = $('#modalRequisicionFirmanteRetomeArbol');
	var $lista = $('#requisicionFirmanteRetomeArbolLista');
	var $error = $('#requisicionFirmanteRetomeArbolError');
	var $texto = $('#requisicionFirmanteRetomeArbolTexto');
	var envioPendiente = null;

	function limpiarErrorModal() {
		$error.addClass('d-none').text('');
	}

	function mostrarErrorModal(msg) {
		$error.removeClass('d-none').text(msg || 'No se pudo completar el envío al árbol.');
	}

	function renderFirmantes(firmantes) {
		var html = '<div class="list-group">';
		firmantes.forEach(function (f, idx) {
			var detalle = [];
			if (f.usuario) {
				detalle.push(f.usuario);
			}
			if (f.email) {
				detalle.push(f.email);
			}
			html += '<label class="list-group-item list-group-item-action mb-0">';
			html += '<input type="radio" name="firmante_retome_arbol" class="mr-2" value="' + f.id + '"' + (idx === 0 ? ' checked' : '') + '>';
			html += '<strong>' + $('<div>').text(f.nombre || ('Usuario ' + f.id)).html() + '</strong>';
			if (detalle.length) {
				html += '<br><small class="text-muted">' + $('<div>').text(detalle.join(' · ')).html() + '</small>';
			}
			html += '</label>';
		});
		html += '</div>';
		$lista.html(html);
	}

	function enviarAlArbol(requisicionId, postUrl, destinatarioId, redirectUrl) {
		var payload = {
			_token: $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').first().val(),
			redirect_url: redirectUrl || ''
		};
		if (destinatarioId) {
			payload.destinatario_usuario_id = destinatarioId;
		}

		$.ajax({
			url: postUrl,
			method: 'POST',
			data: payload,
			dataType: 'json'
		}).done(function (data) {
			if (data && data.mensaje === 'ok') {
				window.location.href = data.redirect || redirectUrl || window.location.href;
				return;
			}
			mostrarErrorModal((data && data.errores) ? data.errores : 'No se pudo enviar al árbol de aprobación.');
		}).fail(function (xhr) {
			var msg = 'No se pudo enviar al árbol de aprobación.';
			if (xhr.responseJSON && xhr.responseJSON.errores) {
				msg = xhr.responseJSON.errores;
			}
			if ($modal.hasClass('show')) {
				mostrarErrorModal(msg);
			} else {
				alert(msg);
			}
		});
	}

	function iniciarEnvio($btn) {
		var requisicionId = parseInt($btn.data('requisicion-id'), 10) || 0;
		var postUrl = $btn.data('post-url') || '';
		var previewUrl = $btn.data('preview-url') || '';
		var redirectUrl = $btn.data('redirect-url') || '';

		if (requisicionId <= 0 || !postUrl || !previewUrl) {
			alert('No se pudo iniciar el envío al árbol de aprobación.');
			return;
		}

		$btn.prop('disabled', true);

		$.get(previewUrl)
			.done(function (data) {
				if (!data || data.mensaje !== 'ok') {
					alert((data && data.errores) ? data.errores : 'No se pudieron consultar los firmantes del árbol.');
					return;
				}

				if (!data.requiere_seleccion) {
					if (!window.confirm('¿Enviar esta requisición al árbol de aprobación para continuar el circuito?')) {
						return;
					}
					enviarAlArbol(requisicionId, postUrl, null, redirectUrl);
					return;
				}

				envioPendiente = {
					requisicionId: requisicionId,
					postUrl: postUrl,
					redirectUrl: redirectUrl,
					nivel: data.nivel
				};
				$texto.text('Hay más de un firmante en el nivel ' + (data.nivel || '') + ' del árbol. Elija a quién enviar la requisición.');
				renderFirmantes(data.firmantes || []);
				limpiarErrorModal();
				$modal.modal('show');
			})
			.fail(function (xhr) {
				var msg = 'No se pudieron consultar los firmantes del árbol.';
				if (xhr.responseJSON && xhr.responseJSON.errores) {
					msg = xhr.responseJSON.errores;
				}
				alert(msg);
			})
			.always(function () {
				$btn.prop('disabled', false);
			});
	}

	$(document).on('click', '.js-enviar-arbol-requisicion', function (e) {
		e.preventDefault();
		iniciarEnvio($(this));
	});

	$('#requisicionFirmanteRetomeArbolConfirmar').on('click', function () {
		if (!envioPendiente) {
			return;
		}
		var seleccionado = parseInt($lista.find('input[name="firmante_retome_arbol"]:checked').val(), 10) || 0;
		if (seleccionado <= 0) {
			mostrarErrorModal('Seleccione un firmante para continuar.');
			return;
		}
		limpiarErrorModal();
		$(this).prop('disabled', true);
		enviarAlArbol(
			envioPendiente.requisicionId,
			envioPendiente.postUrl,
			seleccionado,
			envioPendiente.redirectUrl
		);
		$(this).prop('disabled', false);
	});

	$modal.on('hidden.bs.modal', function () {
		envioPendiente = null;
		$lista.empty();
		limpiarErrorModal();
	});
});
