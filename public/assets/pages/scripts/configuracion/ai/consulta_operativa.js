/**
 * Diálogo operativo IA (Fase C): NL + atajos + export Excel.
 */
(function (window, $) {
	'use strict';

	if (!$) {
		return;
	}

	var CFG = window.AnitaAiConsulta || {};
	var state = {
		intent: null,
		placeholder: '',
		busy: false,
		ultimaExport: null
	};

	function escapeHtml(s) {
		return String(s == null ? '' : s)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function renderTabla(tabla) {
		if (!tabla || !Array.isArray(tabla.columnas) || !tabla.columnas.length) {
			return '';
		}
		var filas = Array.isArray(tabla.filas) ? tabla.filas : [];
		if (!filas.length) {
			return '';
		}
		var html = '<div class="anita-ai-consulta__tabla-wrap"><table class="anita-ai-consulta__tabla">';
		html += '<thead><tr>';
		tabla.columnas.forEach(function (col) {
			html += '<th>' + escapeHtml(col.label || col.key || '') + '</th>';
		});
		html += '</tr></thead><tbody>';
		filas.forEach(function (fila) {
			html += '<tr>';
			tabla.columnas.forEach(function (col) {
				var key = col.key || '';
				var val = fila && fila[key] != null ? fila[key] : '';
				var align = (key === 'debe' || key === 'haber' || key === 'entrada' || key === 'salida' || key === 'total')
					? ' class="text-right"'
					: '';
				html += '<td' + align + '>' + escapeHtml(val) + '</td>';
			});
			html += '</tr>';
		});
		html += '</tbody></table></div>';
		return html;
	}

	function renderPlanPasos(datos) {
		if (!datos || !Array.isArray(datos.pasos) || !datos.pasos.length) {
			return '';
		}
		var html = '<div class="anita-ai-consulta__plan mt-1"><div class="small text-muted mb-1">Pasos del plan (click para consultar):</div>';
		datos.pasos.forEach(function (paso, idx) {
			var frase = paso && paso.frase ? String(paso.frase) : '';
			if (!frase || frase.indexOf('[') !== -1) {
				html += '<div class="small mb-1">' + (idx + 1) + '. ' + escapeHtml(paso.etiqueta || frase) + '</div>';
				return;
			}
			html += '<a href="#" class="anita-ai-consulta__plan-paso d-block small mb-1" data-frase="'
				+ escapeHtml(frase) + '">' + (idx + 1) + '. ' + escapeHtml(paso.etiqueta || frase) + '</a>';
		});
		html += '</div>';
		return html;
	}

	function csrf() {
		return $('meta[name="csrf-token"]').attr('content') || '';
	}

	function setOpen(open) {
		var $root = $('#anita-ai-consulta');
		$root.toggleClass('is-open', !!open);
		$root.attr('aria-hidden', open ? 'false' : 'true');
		if (open) {
			$('#anita-ai-consulta-pregunta').trigger('focus');
		}
	}

	function showError(msg) {
		var $err = $('#anita-ai-consulta-error');
		if (!msg) {
			$err.addClass('d-none').empty();
			return;
		}
		$err.removeClass('d-none').text(msg);
	}

	function scrollChat() {
		var el = document.getElementById('anita-ai-consulta-chat');
		if (el) {
			el.scrollTop = el.scrollHeight;
		}
	}

	function appendMsg(tipo, html) {
		var $chat = $('#anita-ai-consulta-chat');
		var cls = tipo === 'user' ? 'anita-ai-consulta__msg--user' : 'anita-ai-consulta__msg--bot';
		$chat.append('<div class="anita-ai-consulta__msg ' + cls + '">' + html + '</div>');
		scrollChat();
	}

	function etiquetaFuente(fuente) {
		if (fuente === 'llm') {
			return 'interpretado por LLM';
		}
		if (fuente === 'reglas') {
			return 'interpretado por reglas';
		}
		if (fuente === 'tipado') {
			return 'atajo tipado';
		}
		return fuente ? String(fuente) : '';
	}

	function guardarExport(data, preguntaUsuario) {
		if (!data || !data.exportable || !data.intent) {
			state.ultimaExport = null;
			return;
		}
		state.ultimaExport = {
			intent: data.intent,
			params: data.params || {},
			pregunta: data.pregunta || preguntaUsuario || '',
			interpretacion: data.interpretacion || '',
			fuente: data.fuente || ''
		};
	}

	function exportarExcel() {
		if (!CFG.urlExportar || !state.ultimaExport) {
			showError('Primero ejecute una consulta para poder exportar.');
			return;
		}
		var u = state.ultimaExport;
		var $form = $('<form>', {
			method: 'POST',
			action: CFG.urlExportar,
			target: '_blank'
		});
		$form.append($('<input>', { type: 'hidden', name: '_token', value: csrf() }));
		$form.append($('<input>', { type: 'hidden', name: 'intent', value: u.intent }));
		$form.append($('<input>', { type: 'hidden', name: 'params', value: JSON.stringify(u.params || {}) }));
		if (u.pregunta) {
			$form.append($('<input>', { type: 'hidden', name: 'pregunta', value: u.pregunta }));
		}
		var empresaId = $('#empresa_id').val();
		if (empresaId) {
			$form.append($('<input>', { type: 'hidden', name: 'empresa_id', value: empresaId }));
		}
		$form.appendTo('body').trigger('submit');
		setTimeout(function () { $form.remove(); }, 1000);
		appendMsg('bot', '<span class="text-muted small">Descarga de Excel iniciada (hasta 200 líneas en mayor/CT).</span>');
	}

	function renderRespuestaBot(data) {
		var html = '';
		if (data.interpretacion) {
			html += '<div class="anita-ai-consulta__interp small text-muted mb-1"><strong>Entendí:</strong> '
				+ escapeHtml(data.interpretacion);
			if (data.fuente) {
				html += ' <span class="anita-ai-consulta__fuente">(' + escapeHtml(etiquetaFuente(data.fuente)) + ')</span>';
			}
			html += '</div>';
		} else if (data.fuente) {
			html += '<div class="anita-ai-consulta__interp small text-muted mb-1">'
				+ escapeHtml(etiquetaFuente(data.fuente)) + '</div>';
		}
		if (data.score != null && isFinite(Number(data.score))) {
			html += '<div class="anita-ai-consulta__score text-muted mb-1">score '
				+ Number(data.score).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
				+ '</div>';
		}
		var parrafos = Array.isArray(data.parrafos) ? data.parrafos : [];
		if (parrafos.length) {
			html += '<ul class="mb-1 pl-3">';
			parrafos.forEach(function (p) {
				html += '<li>' + escapeHtml(p) + '</li>';
			});
			html += '</ul>';
		}
		html += renderTabla(data.tabla);
		html += renderPlanPasos(data.datos);
		var links = Array.isArray(data.links) ? data.links : [];
		if (links.length) {
			html += '<div class="anita-ai-consulta__links">';
			links.forEach(function (l) {
				if (!l || !l.url) {
					return;
				}
				html += '<a class="text-primary" href="' + escapeHtml(l.url) + '" target="_blank" rel="noopener">'
					+ escapeHtml(l.etiqueta || 'Abrir') + '</a> ';
			});
			html += '</div>';
		}
		if (data.exportable && CFG.urlExportar) {
			html += '<div class="mt-2">'
				+ '<button type="button" class="btn btn-sm btn-outline-success anita-ai-consulta__btn-excel">'
				+ '<i class="fa fa-file-excel-o"></i> Excel</button></div>';
		}
		if (!html) {
			html = 'Sin detalle para mostrar.';
		}
		appendMsg('bot', html);
	}

	function renderEjemplos(ejemplos) {
		var $box = $('#anita-ai-consulta-ejemplos').empty();
		if (!ejemplos || !ejemplos.length) {
			return;
		}
		$box.append('<div class="mb-1">Ejemplos:</div>');
		ejemplos.forEach(function (ej) {
			var $a = $('<a href="#" class="anita-ai-consulta__ejemplo d-inline-block mr-2"></a>').text(ej);
			$box.append($a);
		});
	}

	function renderChips(intents) {
		var $chips = $('#anita-ai-consulta-chips').empty();
		(intents || []).forEach(function (item) {
			var $btn = $('<button type="button" class="anita-ai-consulta__chip"></button>')
				.text(item.etiqueta || item.intent)
				.attr('data-intent', item.intent)
				.attr('data-placeholder', item.placeholder || '');
			$chips.append($btn);
		});
	}

	function selectIntent($btn) {
		$('.anita-ai-consulta__chip').removeClass('is-active');
		$btn.addClass('is-active');
		state.intent = $btn.data('intent');
		state.placeholder = $btn.data('placeholder') || '';
		var $ta = $('#anita-ai-consulta-pregunta');
		if (state.placeholder) {
			$ta.attr('placeholder', state.placeholder + ' (o escriba la consulta completa)');
		}
		showError('');
		$ta.trigger('focus');
	}

	function textoPregunta() {
		return $.trim($('#anita-ai-consulta-pregunta').val() || '');
	}

	function consultar() {
		if (state.busy) {
			return;
		}
		var pregunta = textoPregunta();
		if (!pregunta) {
			showError('Escriba una consulta o use un atajo e indique el código.');
			return;
		}

		state.busy = true;
		$('#anita-ai-consulta-enviar').prop('disabled', true).text('Pensando…');
		showError('');
		appendMsg('user', escapeHtml(pregunta));

		var payload = {
			pregunta: pregunta
		};
		if (state.intent && pregunta.indexOf(' ') === -1) {
			payload.intent = state.intent;
			payload.valor = pregunta;
		}
		var empresaId = $('#empresa_id').val();
		if (empresaId) {
			payload.empresa_id = empresaId;
		}

		$.ajax({
			url: CFG.urlConsultar,
			method: 'POST',
			data: payload,
			headers: {
				'X-CSRF-TOKEN': csrf(),
				'Accept': 'application/json'
			}
		}).done(function (resp) {
			if (!resp || !resp.ok) {
				state.ultimaExport = null;
				var err = (resp && (resp.clarification || resp.error)) || 'Sin resultado.';
				var pref = (resp && resp.needs_clarification) ? '<strong>Necesito aclarar:</strong> ' : '';
				appendMsg('bot', '<span class="text-warning">' + pref + escapeHtml(err) + '</span>');
				if (resp && Array.isArray(resp.sugerencias) && resp.sugerencias.length) {
					renderEjemplos(resp.sugerencias);
				}
				return;
			}
			guardarExport(resp, pregunta);
			renderRespuestaBot(resp);
			$('#anita-ai-consulta-pregunta').val('');
		}).fail(function (xhr) {
			state.ultimaExport = null;
			var json = xhr && xhr.responseJSON ? xhr.responseJSON : null;
			var msg = (json && (json.clarification || json.error)) || 'No se pudo completar la consulta.';
			var pref = (json && json.needs_clarification) ? '<strong>Necesito aclarar:</strong> ' : '';
			appendMsg('bot', '<span class="text-warning">' + pref + escapeHtml(msg) + '</span>');
			if (json && Array.isArray(json.sugerencias)) {
				renderEjemplos(json.sugerencias);
			}
		}).always(function () {
			state.busy = false;
			$('#anita-ai-consulta-enviar').prop('disabled', false).text('Enviar');
		});
	}

	function boot() {
		if (!CFG.urlConsultar || !CFG.urlIntents) {
			return;
		}
		if (!$('#anita-ai-consulta').length) {
			return;
		}

		$('#anita-ai-consulta-fab').on('click', function () {
			setOpen(!$('#anita-ai-consulta').hasClass('is-open'));
		});
		$('#anita-ai-consulta-cerrar').on('click', function () {
			setOpen(false);
		});
		$('#anita-ai-consulta-chips').on('click', '.anita-ai-consulta__chip', function () {
			selectIntent($(this));
		});
		$('#anita-ai-consulta-enviar').on('click', consultar);
		$('#anita-ai-consulta-chat').on('click', '.anita-ai-consulta__btn-excel', function (e) {
			e.preventDefault();
			exportarExcel();
		});
		$('#anita-ai-consulta-chat').on('click', '.anita-ai-consulta__plan-paso', function (e) {
			e.preventDefault();
			var frase = $(this).attr('data-frase') || '';
			if (!frase) {
				return;
			}
			$('#anita-ai-consulta-pregunta').val(frase);
			consultar();
		});
		$('#anita-ai-consulta-pregunta').on('keydown', function (e) {
			if (e.key === 'Enter' && !e.shiftKey) {
				e.preventDefault();
				consultar();
			}
		});
		$('#anita-ai-consulta-ejemplos').on('click', '.anita-ai-consulta__ejemplo', function (e) {
			e.preventDefault();
			$('#anita-ai-consulta-pregunta').val($(this).text());
			consultar();
		});

		$.ajax({
			url: CFG.urlIntents,
			method: 'GET',
			headers: { 'Accept': 'application/json' }
		}).done(function (resp) {
			if (resp && resp.ok) {
				if (Array.isArray(resp.intents)) {
					renderChips(resp.intents);
				}
				if (Array.isArray(resp.ejemplos)) {
					renderEjemplos(resp.ejemplos);
				}
			}
		});
	}

	$(boot);
})(window, window.jQuery);
