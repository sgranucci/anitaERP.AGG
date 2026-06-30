$(function () {
	if (typeof carpetaBase === 'undefined' || carpetaBase === '') {
		window.carpetaBase = window.location.pathname.split('/public')[0] + '/public';
	}

	var $modal = $('#modalRequisicionFirmanteRetomeArbol');
	var $lista = $('#requisicionFirmanteRetomeArbolLista');
	var $error = $('#requisicionFirmanteRetomeArbolError');
	var $texto = $('#requisicionFirmanteRetomeArbolTexto');
	var envioPendiente = null;
	var enviandoAlArbol = false;

	function limpiarErrorModal() {
		$error.addClass('d-none').text('');
	}

	function mostrarErrorModal(msg) {
		$error.removeClass('d-none').text(msg || 'No se pudo completar el envío al árbol.');
	}

	function detalleFirmante(f) {
		var partes = [];
		if (f && f.usuario) {
			partes.push(f.usuario);
		}
		if (f && f.email) {
			partes.push(f.email);
		}
		return partes.join(' \u00b7 ');
	}

	function nombreFirmante(f) {
		if (!f) {
			return '';
		}
		return f.nombre || f.usuario || ('Usuario ' + f.id);
	}

	function firmantePorId(firmantes, destinatarioId) {
		if (!firmantes || !firmantes.length) {
			return null;
		}
		if (destinatarioId) {
			for (var i = 0; i < firmantes.length; i++) {
				if (parseInt(firmantes[i].id, 10) === parseInt(destinatarioId, 10)) {
					return firmantes[i];
				}
			}
		}
		return firmantes[0];
	}

	function asegurarBannerEnviandoArbol() {
		if ($('#requisicion-banner-enviando-arbol').length) {
			return;
		}
		var html = '<div id="requisicion-banner-enviando-arbol" class="requisicion-enviando-arbol-overlay" role="status" aria-live="polite" aria-busy="true">';
		html += '<div class="alert alert-success shadow requisicion-enviando-arbol-banner mb-0 px-4 py-3">';
		html += '<div class="requisicion-enviando-arbol-spinner-wrap" aria-hidden="true">';
		html += '<div class="spinner-border text-dark" role="status"><span class="sr-only">Cargando&hellip;</span></div>';
		html += '</div>';
		html += '<strong id="requisicion-banner-enviando-arbol-titulo" class="d-block mb-2 text-dark">Enviando al &aacute;rbol de aprobaci&oacute;n&hellip;</strong>';
		html += '<span id="requisicion-banner-enviando-arbol-destinatario" class="d-block text-dark"></span>';
		html += '<span id="requisicion-banner-enviando-arbol-nivel" class="small d-block text-muted mt-1"></span>';
		html += '<span class="small d-block text-dark mt-2">Por favor espere.</span>';
		html += '</div></div>';
		$('body').append(html);
	}

	function mostrarBannerEnviandoArbol(firmante, nivel) {
		asegurarBannerEnviandoArbol();
		var nombre = nombreFirmante(firmante);
		var detalle = detalleFirmante(firmante);
		var destHtml = '<strong>Destinatario:</strong> ' + $('<div>').text(nombre).html();
		if (detalle) {
			destHtml += '<br><small class="text-muted">' + $('<div>').text(detalle).html() + '</small>';
		}
		$('#requisicion-banner-enviando-arbol-destinatario').html(destHtml);
		if (nivel) {
			$('#requisicion-banner-enviando-arbol-nivel').text('Nivel ' + nivel + ' del \u00e1rbol de aprobaci\u00f3n');
		} else {
			$('#requisicion-banner-enviando-arbol-nivel').text('');
		}
		$('#requisicion-banner-enviando-arbol').addClass('is-visible');
	}

	function ocultarBannerEnviandoArbol() {
		$('#requisicion-banner-enviando-arbol').removeClass('is-visible');
		enviandoAlArbol = false;
	}

	function renderFirmantes(firmantes) {
		var html = '<div class="list-group">';
		firmantes.forEach(function (f, idx) {
			var detalle = detalleFirmante(f);
			html += '<label class="list-group-item list-group-item-action mb-0">';
			html += '<input type="radio" name="firmante_retome_arbol" class="mr-2" value="' + f.id + '"' + (idx === 0 ? ' checked' : '') + '>';
			html += '<strong>' + $('<div>').text(nombreFirmante(f)).html() + '</strong>';
			if (detalle) {
				html += '<br><small class="text-muted">' + $('<div>').text(detalle).html() + '</small>';
			}
			html += '</label>';
		});
		html += '</div>';
		$lista.html(html);
	}

	function mensajeConfirmacionUnFirmante(firmante, nivel) {
		var nombre = nombreFirmante(firmante);
		var detalle = detalleFirmante(firmante);
		var msg = '\u00bfEnviar esta requisici\u00f3n al \u00e1rbol de aprobaci\u00f3n?\n\n';
		msg += 'Destinatario: ' + nombre;
		if (detalle) {
			msg += ' (' + detalle + ')';
		}
		if (nivel) {
			msg += '\nNivel ' + nivel + ' del \u00e1rbol.';
		}
		return msg;
	}

	function enviarAlArbol(requisicionId, postUrl, destinatarioId, redirectUrl, firmantes, nivel) {
		if (enviandoAlArbol) {
			return;
		}
		enviandoAlArbol = true;

		var firmante = firmantePorId(firmantes, destinatarioId);
		if ($modal.hasClass('show')) {
			$modal.modal('hide');
		}
		mostrarBannerEnviandoArbol(firmante, nivel);

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
			ocultarBannerEnviandoArbol();
			mostrarErrorModal((data && data.errores) ? data.errores : 'No se pudo enviar al \u00e1rbol de aprobaci\u00f3n.');
			if (!$modal.hasClass('show') && firmantes && firmantes.length > 1) {
				$modal.modal('show');
			}
		}).fail(function (xhr) {
			ocultarBannerEnviandoArbol();
			var msg = 'No se pudo enviar al \u00e1rbol de aprobaci\u00f3n.';
			if (xhr.responseJSON && xhr.responseJSON.errores) {
				msg = xhr.responseJSON.errores;
			}
			if (firmantes && firmantes.length > 1) {
				mostrarErrorModal(msg);
				$modal.modal('show');
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
			alert('No se pudo iniciar el env\u00edo al \u00e1rbol de aprobaci\u00f3n.');
			return;
		}

		if (enviandoAlArbol) {
			return;
		}

		$btn.prop('disabled', true);

		$.get(previewUrl)
			.done(function (data) {
				if (!data || data.mensaje !== 'ok') {
					alert((data && data.errores) ? data.errores : 'No se pudieron consultar los firmantes del \u00e1rbol.');
					return;
				}

				var firmantes = data.firmantes || [];
				var nivel = data.nivel || null;

				if (!data.requiere_seleccion) {
					var firmanteUnico = firmantes[0] || null;
					if (!window.confirm(mensajeConfirmacionUnFirmante(firmanteUnico, nivel))) {
						return;
					}
					enviarAlArbol(requisicionId, postUrl, null, redirectUrl, firmantes, nivel);
					return;
				}

				envioPendiente = {
					requisicionId: requisicionId,
					postUrl: postUrl,
					redirectUrl: redirectUrl,
					nivel: nivel,
					firmantes: firmantes
				};
				$texto.text('Hay m\u00e1s de un firmante en el nivel ' + (nivel || '') + ' del \u00e1rbol. Elija a qui\u00e9n enviar la requisici\u00f3n.');
				renderFirmantes(firmantes);
				limpiarErrorModal();
				$modal.modal('show');
			})
			.fail(function (xhr) {
				var msg = 'No se pudieron consultar los firmantes del \u00e1rbol.';
				if (xhr.responseJSON && xhr.responseJSON.errores) {
					msg = xhr.responseJSON.errores;
				}
				alert(msg);
			})
			.always(function () {
				if (!enviandoAlArbol) {
					$btn.prop('disabled', false);
				}
			});
	}

	$(document).on('click', '.js-enviar-arbol-requisicion', function (e) {
		e.preventDefault();
		iniciarEnvio($(this));
	});

	$('#requisicionFirmanteRetomeArbolConfirmar').on('click', function () {
		if (!envioPendiente || enviandoAlArbol) {
			return;
		}
		var seleccionado = parseInt($lista.find('input[name="firmante_retome_arbol"]:checked').val(), 10) || 0;
		if (seleccionado <= 0) {
			mostrarErrorModal('Seleccione un firmante para continuar.');
			return;
		}
		limpiarErrorModal();
		var $btnConfirmar = $(this);
		$btnConfirmar.prop('disabled', true);
		enviarAlArbol(
			envioPendiente.requisicionId,
			envioPendiente.postUrl,
			seleccionado,
			envioPendiente.redirectUrl,
			envioPendiente.firmantes,
			envioPendiente.nivel
		);
	});

	$modal.on('hidden.bs.modal', function () {
		if (!enviandoAlArbol) {
			envioPendiente = null;
		}
		$lista.empty();
		limpiarErrorModal();
		$('#requisicionFirmanteRetomeArbolConfirmar').prop('disabled', false);
	});
});
