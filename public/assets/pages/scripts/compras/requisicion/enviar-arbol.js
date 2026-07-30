$(function () {
	if (typeof carpetaBase === 'undefined' || carpetaBase === '') {
		window.carpetaBase = window.location.pathname.split('/public')[0] + '/public';
	}

	var $modalFirmante = $('#modalRequisicionFirmanteRetomeArbol');
	var $listaFirmante = $('#requisicionFirmanteRetomeArbolLista');
	var $errorFirmante = $('#requisicionFirmanteRetomeArbolError');
	var $textoFirmante = $('#requisicionFirmanteRetomeArbolTexto');
	var envioPendiente = null;
	var enviandoAlArbol = false;

	function limpiarErrorModal($error) {
		$error.addClass('d-none').text('');
	}

	function mostrarErrorModal($error, msg) {
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

	function etiquetaCentrocosto(cc) {
		if (window.RequisicionCentrocostoArbolModal && window.RequisicionCentrocostoArbolModal.etiquetaCentrocosto) {
			return window.RequisicionCentrocostoArbolModal.etiquetaCentrocosto(cc);
		}
		if (!cc) {
			return '';
		}
		return cc.etiqueta || ((cc.codigo || '') + ' ' + (cc.nombre || '')).trim() || ('Centro de costo ' + cc.id);
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

	function mostrarBannerEnviandoArbol(firmante, nivel, centrocosto) {
		asegurarBannerEnviandoArbol();
		var nombre = nombreFirmante(firmante);
		var detalle = detalleFirmante(firmante);
		var destHtml = '<strong>Destinatario:</strong> ' + $('<div>').text(nombre).html();
		if (detalle) {
			destHtml += '<br><small class="text-muted">' + $('<div>').text(detalle).html() + '</small>';
		}
		if (centrocosto) {
			destHtml += '<br><small class="text-muted"><strong>Centro de costo:</strong> ' + $('<div>').text(etiquetaCentrocosto(centrocosto)).html() + '</small>';
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
		$listaFirmante.html(html);
	}

	function centrocostoPorId(centrosCosto, centrocostoId) {
		if (!centrosCosto || !centrosCosto.length || !centrocostoId) {
			return null;
		}
		for (var i = 0; i < centrosCosto.length; i++) {
			if (parseInt(centrosCosto[i].id, 10) === parseInt(centrocostoId, 10)) {
				return centrosCosto[i];
			}
		}
		return null;
	}

	function mensajeConfirmacionUnFirmante(firmante, nivel, centrocosto) {
		var nombre = nombreFirmante(firmante);
		var detalle = detalleFirmante(firmante);
		var msg = '\u00bfEnviar esta requisici\u00f3n al \u00e1rbol de aprobaci\u00f3n?\n\n';
		if (centrocosto) {
			msg += 'Centro de costo de destino: ' + etiquetaCentrocosto(centrocosto) + '\n';
		}
		msg += 'Destinatario: ' + nombre;
		if (detalle) {
			msg += ' (' + detalle + ')';
		}
		if (nivel) {
			msg += '\nNivel ' + nivel + ' del \u00e1rbol.';
		}
		return msg;
	}

	function consultarPreview(previewUrl, centrocostoId) {
		var params = {};
		if (centrocostoId) {
			params.centrocostodestino_arbol_id = centrocostoId;
		}
		return $.get(previewUrl, params);
	}

	function enviarAlArbol(requisicionId, postUrl, destinatarioId, redirectUrl, firmantes, nivel, centrocostoId, centrosCosto) {
		if (enviandoAlArbol) {
			return;
		}
		enviandoAlArbol = true;

		var firmante = firmantePorId(firmantes, destinatarioId);
		var centrocosto = centrocostoPorId(centrosCosto, centrocostoId);
		if ($modalFirmante.hasClass('show')) {
			$modalFirmante.modal('hide');
		}
		var $modalCentrocosto = $('#modalRequisicionCentrocostoRetomeArbol');
		if ($modalCentrocosto.length && $modalCentrocosto.hasClass('show')) {
			$modalCentrocosto.modal('hide');
		}
		mostrarBannerEnviandoArbol(firmante, nivel, centrocosto);

		var payload = {
			_token: $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').first().val(),
			redirect_url: redirectUrl || ''
		};
		if (destinatarioId) {
			payload.destinatario_usuario_id = destinatarioId;
		}
		if (centrocostoId) {
			payload.centrocostodestino_arbol_id = centrocostoId;
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
			if (data && data.mensaje === 'seleccionar_centrocosto') {
				mostrarModalCentrocosto(data.centros_costo || [], envioPendiente);
				return;
			}
			mostrarErrorModal($errorFirmante, (data && data.errores) ? data.errores : 'No se pudo enviar al \u00e1rbol de aprobaci\u00f3n.');
			if (!$modalFirmante.hasClass('show') && firmantes && firmantes.length > 1) {
				$modalFirmante.modal('show');
			}
		}).fail(function (xhr) {
			ocultarBannerEnviandoArbol();
			var msg = 'No se pudo enviar al \u00e1rbol de aprobaci\u00f3n.';
			var data = xhr.responseJSON || null;
			if (data && data.errores) {
				msg = data.errores;
			}
			if (data && data.mensaje === 'seleccionar_centrocosto') {
				mostrarModalCentrocosto(data.centros_costo || [], envioPendiente);
				return;
			}
			if (firmantes && firmantes.length > 1) {
				mostrarErrorModal($errorFirmante, msg);
				$modalFirmante.modal('show');
			} else {
				alert(msg);
			}
		});
	}

	function mostrarModalCentrocosto(centrosCosto, context) {
		if (!context) {
			return;
		}
		envioPendiente = context;
		envioPendiente.centrosCosto = centrosCosto || [];
		if (!window.RequisicionCentrocostoArbolModal) {
			alert('No se pudo abrir la selección de centro de costo.');
			return;
		}
		window.RequisicionCentrocostoArbolModal.abrir({
			centrosCosto: envioPendiente.centrosCosto,
			texto: 'La requisición tiene renglones con distintos centros de costo de destino. Elija con cuál continuar el árbol de aprobación.',
			onConfirm: function (seleccionado) {
				envioPendiente.centrocostoId = seleccionado;
				var $btn = envioPendiente.$btn;
				if ($btn && $btn.length) {
					$btn.prop('disabled', true);
				}
				consultarPreview(envioPendiente.previewUrl, seleccionado)
					.done(function (data) {
						procesarRespuestaPreview(data, envioPendiente);
					})
					.fail(function (xhr) {
						var msg = 'No se pudieron consultar los firmantes del árbol.';
						if (xhr.responseJSON && xhr.responseJSON.errores) {
							msg = xhr.responseJSON.errores;
						}
						alert(msg);
					})
					.always(function () {
						if ($btn && $btn.length && !enviandoAlArbol) {
							$btn.prop('disabled', false);
						}
					});
			},
			onCancel: function () {
				if (envioPendiente && envioPendiente.$btn) {
					envioPendiente.$btn.prop('disabled', false);
				}
			}
		});
	}

	function continuarFlujoFirmantes(data, context) {
		var firmantes = data.firmantes || [];
		var nivel = data.nivel || null;
		var centrocostoId = data.centrocosto_arbol_id || context.centrocostoId || null;
		context.centrocostoId = centrocostoId;
		context.firmantes = firmantes;
		context.nivel = nivel;

		if (!data.requiere_seleccion) {
			var firmanteUnico = firmantes[0] || null;
			var centrocosto = centrocostoPorId(context.centrosCosto, centrocostoId);
			if (!window.confirm(mensajeConfirmacionUnFirmante(firmanteUnico, nivel, centrocosto))) {
				return;
			}
			enviarAlArbol(
				context.requisicionId,
				context.postUrl,
				null,
				context.redirectUrl,
				firmantes,
				nivel,
				centrocostoId,
				context.centrosCosto
			);
			return;
		}

		envioPendiente = context;
		$textoFirmante.text('Hay m\u00e1s de un firmante en el nivel ' + (nivel || '') + ' del \u00e1rbol. Elija a qui\u00e9n enviar la requisici\u00f3n.');
		renderFirmantes(firmantes);
		limpiarErrorModal($errorFirmante);
		$modalFirmante.modal('show');
	}

	function procesarRespuestaPreview(data, context) {
		if (!data || data.mensaje !== 'ok') {
			alert((data && data.errores) ? data.errores : 'No se pudieron consultar los firmantes del \u00e1rbol.');
			return;
		}

		if (data.requiere_seleccion_centrocosto) {
			mostrarModalCentrocosto(data.centros_costo || [], context);
			return;
		}

		continuarFlujoFirmantes(data, context);
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

		var context = {
			requisicionId: requisicionId,
			postUrl: postUrl,
			previewUrl: previewUrl,
			redirectUrl: redirectUrl,
			centrocostoId: null,
			centrosCosto: [],
			firmantes: [],
			nivel: null,
			$btn: $btn
		};

		consultarPreview(previewUrl, null)
			.done(function (data) {
				procesarRespuestaPreview(data, context);
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
		var seleccionado = parseInt($listaFirmante.find('input[name="firmante_retome_arbol"]:checked').val(), 10) || 0;
		if (seleccionado <= 0) {
			mostrarErrorModal($errorFirmante, 'Seleccione un firmante para continuar.');
			return;
		}
		limpiarErrorModal($errorFirmante);
		var $btnConfirmar = $(this);
		$btnConfirmar.prop('disabled', true);
		enviarAlArbol(
			envioPendiente.requisicionId,
			envioPendiente.postUrl,
			seleccionado,
			envioPendiente.redirectUrl,
			envioPendiente.firmantes,
			envioPendiente.nivel,
			envioPendiente.centrocostoId,
			envioPendiente.centrosCosto
		);
	});

	$modalFirmante.on('hidden.bs.modal', function () {
		if (!enviandoAlArbol) {
			envioPendiente = null;
		}
		$listaFirmante.empty();
		limpiarErrorModal($errorFirmante);
		$('#requisicionFirmanteRetomeArbolConfirmar').prop('disabled', false);
	});
});
