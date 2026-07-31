/**
 * Copiloto IA: NL + herramientas agrupadas + Excel en cabecera.
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
		ultimaExport: null,
		ultimoPedido: null,
		intents: [],
		grupos: {},
		grupoActivo: null,
		toolsOpen: false
	};

	function escapeHtml(s) {
		return String(s == null ? '' : s)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function csrf() {
		return $('meta[name="csrf-token"]').attr('content') || '';
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
				var align = (key === 'debe' || key === 'haber' || key === 'entrada' || key === 'salida'
					|| key === 'total' || key === 'monto' || key === 'dias' || key === 'lineas'
					|| key === 'valor' || key === 'comprobantes' || key === 'ranking')
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
		var html = '<div class="anita-ai-consulta__plan"><div class="small text-muted mb-1">Pasos del plan:</div>';
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

	function renderPedidoConsumoAcciones(data) {
		var datos = data && data.datos ? data.datos : null;
		if (!datos || (!datos.borrador_compra && !datos.borrador_sala)) {
			return '';
		}
		state.ultimoPedido = {
			decisionId: data.ai_decision_id || null,
			borrador_compra: datos.borrador_compra || null,
			borrador_sala: datos.borrador_sala || null,
			puede_compra: !!datos.puede_confirmar_compra,
			puede_sala: !!datos.puede_confirmar_sala
		};
		if (!CFG.urlConfirmarPedido) {
			return '<div class="small text-muted mt-1">Borradores listos (use Alta RQ si no aparece el botón de confirmar).</div>';
		}
		var html = '<div class="anita-ai-consulta__pedido-acciones">';
		if (state.ultimoPedido.borrador_compra && state.ultimoPedido.puede_compra) {
			html += '<button type="button" class="btn btn-sm btn-success mr-1 anita-ai-consulta__btn-confirmar-pedido" data-tipo="compra">'
				+ '<i class="fa fa-check"></i> Crear RQ compra</button>';
		}
		if (state.ultimoPedido.borrador_sala && state.ultimoPedido.puede_sala) {
			html += '<button type="button" class="btn btn-sm btn-info anita-ai-consulta__btn-confirmar-pedido" data-tipo="sala">'
				+ '<i class="fa fa-exchange"></i> Crear RQ sala</button>';
		}
		html += '</div>';
		return html;
	}

	function confirmarPedidoConsumo(tipo) {
		if (!state.ultimoPedido || !CFG.urlConfirmarPedido) {
			return;
		}
		var borrador = tipo === 'sala' ? state.ultimoPedido.borrador_sala : state.ultimoPedido.borrador_compra;
		if (!borrador) {
			showError('No hay borrador «' + tipo + '» en la última proyección.');
			return;
		}
		if (!window.confirm('¿Confirmar creación de requisición de ' + tipo + ' con las líneas sugeridas?')) {
			return;
		}
		$.ajax({
			url: CFG.urlConfirmarPedido,
			method: 'POST',
			data: {
				tipo: tipo,
				ai_decision_id: state.ultimoPedido.decisionId || '',
				borrador: JSON.stringify(borrador)
			},
			headers: {
				'X-CSRF-TOKEN': csrf(),
				'Accept': 'application/json'
			}
		}).done(function (resp) {
			if (resp && resp.ok) {
				appendMsg('bot', '<span class="text-success">' + escapeHtml(resp.message || 'Documento creado.') + '</span>');
			} else {
				appendMsg('bot', '<span class="text-warning">' + escapeHtml((resp && resp.message) || 'No se pudo confirmar.') + '</span>');
			}
		}).fail(function (xhr) {
			var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Error al confirmar el pedido.';
			appendMsg('bot', '<span class="text-danger">' + escapeHtml(msg) + '</span>');
		});
	}

	function setOpen(open) {
		var $root = $('#anita-ai-consulta');
		$root.toggleClass('is-open', !!open);
		$root.attr('aria-hidden', open ? 'false' : 'true');
		if (open) {
			$('#anita-ai-consulta-pregunta').trigger('focus');
		} else {
			setToolsOpen(false);
		}
	}

	function setExpanded(expanded) {
		var $root = $('#anita-ai-consulta');
		$root.toggleClass('is-expanded', !!expanded);
		$('#anita-ai-consulta-expandir')
			.attr('aria-pressed', expanded ? 'true' : 'false')
			.find('i')
			.attr('class', expanded ? 'fa fa-compress' : 'fa fa-expand');
	}

	function setToolsOpen(open) {
		state.toolsOpen = !!open;
		var $tools = $('#anita-ai-consulta-tools');
		if (open) {
			$tools.prop('hidden', false);
			$('#anita-ai-consulta-tools-toggle').addClass('is-open');
			renderToolsTabs();
			renderToolsGrid(state.grupoActivo);
		} else {
			$tools.prop('hidden', true);
			$('#anita-ai-consulta-tools-toggle').removeClass('is-open');
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

	function setExcelReady(ready) {
		var $btn = $('#anita-ai-consulta-excel');
		if (ready && CFG.urlExportar) {
			$btn.removeClass('d-none').addClass('is-ready');
			setTimeout(function () { $btn.removeClass('is-ready'); }, 1300);
		} else {
			$btn.addClass('d-none').removeClass('is-ready');
		}
	}

	function guardarExport(data, preguntaUsuario) {
		if (!data || !data.exportable || !data.intent) {
			state.ultimaExport = null;
			setExcelReady(false);
			return;
		}
		state.ultimaExport = {
			intent: data.intent,
			params: data.params || {},
			pregunta: data.pregunta || preguntaUsuario || '',
			interpretacion: data.interpretacion || '',
			fuente: data.fuente || ''
		};
		setExcelReady(true);
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
		appendMsg('bot', '<span class="text-muted small">Descarga de Excel iniciada.</span>');
	}

	function renderRespuestaBot(data) {
		var html = '';
		if (data.interpretacion) {
			html += '<div class="anita-ai-consulta__interp text-muted mb-1"><strong>Entendí:</strong> '
				+ escapeHtml(data.interpretacion);
			if (data.fuente) {
				html += ' <span class="anita-ai-consulta__fuente">(' + escapeHtml(etiquetaFuente(data.fuente)) + ')</span>';
			}
			html += '</div>';
		} else if (data.fuente) {
			html += '<div class="anita-ai-consulta__interp text-muted mb-1">'
				+ escapeHtml(etiquetaFuente(data.fuente)) + '</div>';
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
		html += renderPedidoConsumoAcciones(data);
		var links = Array.isArray(data.links) ? data.links : [];
		if (links.length) {
			html += '<div class="anita-ai-consulta__links">';
			links.forEach(function (l) {
				if (!l || !l.url) {
					return;
				}
				html += '<a class="text-primary" href="' + escapeHtml(l.url) + '" target="_blank" rel="noopener">'
					+ escapeHtml(l.etiqueta || 'Abrir') + '</a>';
			});
			html += '</div>';
		}
		if (data.exportable && CFG.urlExportar) {
			html += '<div class="anita-ai-consulta__result-actions">'
				+ '<button type="button" class="anita-ai-consulta__btn-excel">'
				+ '<i class="fa fa-file-excel-o"></i> Exportar Excel</button></div>';
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
		ejemplos.slice(0, 4).forEach(function (ej) {
			var $a = $('<a href="#" class="anita-ai-consulta__ejemplo"></a>').text(ej);
			$box.append($a);
		});
	}

	function gruposOrdenados() {
		var order = ['compras', 'contable', 'stock', 'ventas', 'ayuda', 'otros'];
		var present = {};
		state.intents.forEach(function (item) {
			present[item.grupo || 'otros'] = true;
		});
		return order.filter(function (g) { return present[g]; });
	}

	function renderToolsTabs() {
		var $tabs = $('#anita-ai-consulta-tools-tabs').empty();
		var grupos = gruposOrdenados();
		if (!state.grupoActivo || grupos.indexOf(state.grupoActivo) === -1) {
			state.grupoActivo = grupos[0] || 'otros';
		}
		grupos.forEach(function (g) {
			var label = state.grupos[g] || g;
			var $btn = $('<button type="button" class="anita-ai-consulta__tools-tab" role="tab"></button>')
				.text(label)
				.attr('data-grupo', g)
				.toggleClass('is-active', g === state.grupoActivo);
			$tabs.append($btn);
		});
	}

	function renderToolsGrid(grupo) {
		var $grid = $('#anita-ai-consulta-tools-grid').empty();
		var items = state.intents.filter(function (it) {
			return (it.grupo || 'otros') === grupo;
		});
		if (!items.length) {
			$grid.append('<div class="small text-muted">No hay herramientas en este grupo.</div>');
			return;
		}
		items.forEach(function (item) {
			var instant = !!item.auto_pregunta;
			var $btn = $('<button type="button" class="anita-ai-consulta__tool"></button>')
				.toggleClass('is-instant', instant)
				.attr('data-intent', item.intent)
				.attr('data-placeholder', item.placeholder || '')
				.attr('data-auto', item.auto_pregunta || '')
				.append($('<span class="anita-ai-consulta__tool-label"></span>').text(item.etiqueta || item.intent))
				.append($('<span class="anita-ai-consulta__tool-meta"></span>')
					.text(instant ? 'Ejecutar ahora' : (item.placeholder || 'Completar dato')));
			$grid.append($btn);
		});
	}

	function showIntentPill(etiqueta) {
		if (!etiqueta) {
			$('#anita-ai-consulta-intent-pill').addClass('d-none');
			$('#anita-ai-consulta-intent-label').text('');
			return;
		}
		$('#anita-ai-consulta-intent-label').text(etiqueta);
		$('#anita-ai-consulta-intent-pill').removeClass('d-none');
	}

	function clearIntent() {
		state.intent = null;
		state.placeholder = '';
		showIntentPill('');
		$('#anita-ai-consulta-pregunta').attr('placeholder', 'Escriba su consulta…');
	}

	function selectTool(item, autoRun) {
		state.intent = item.intent || null;
		state.placeholder = item.placeholder || '';
		showIntentPill(item.etiqueta || item.intent || '');
		var $ta = $('#anita-ai-consulta-pregunta');
		if (state.placeholder) {
			$ta.attr('placeholder', state.placeholder);
		}
		setToolsOpen(false);
		showError('');
		if (autoRun && item.auto_pregunta) {
			$ta.val(item.auto_pregunta);
			consultar();
			return;
		}
		$ta.trigger('focus');
	}

	function textoPregunta() {
		return $.trim($('#anita-ai-consulta-pregunta').val() || '');
	}

	function autoResizeInput() {
		var el = document.getElementById('anita-ai-consulta-pregunta');
		if (!el) {
			return;
		}
		el.style.height = 'auto';
		el.style.height = Math.min(110, Math.max(42, el.scrollHeight)) + 'px';
	}

	function consultar() {
		if (state.busy) {
			return;
		}
		var pregunta = textoPregunta();
		if (!pregunta) {
			showError('Escriba una consulta o elija una herramienta.');
			return;
		}

		state.busy = true;
		$('#anita-ai-consulta-enviar').prop('disabled', true);
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
				setExcelReady(false);
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
			autoResizeInput();
			clearIntent();
		}).fail(function (xhr) {
			state.ultimaExport = null;
			setExcelReady(false);
			var json = xhr && xhr.responseJSON ? xhr.responseJSON : null;
			var msg = (json && (json.clarification || json.error)) || 'No se pudo completar la consulta.';
			var pref = (json && json.needs_clarification) ? '<strong>Necesito aclarar:</strong> ' : '';
			appendMsg('bot', '<span class="text-warning">' + pref + escapeHtml(msg) + '</span>');
			if (json && Array.isArray(json.sugerencias)) {
				renderEjemplos(json.sugerencias);
			}
		}).always(function () {
			state.busy = false;
			$('#anita-ai-consulta-enviar').prop('disabled', false);
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
		$('#anita-ai-consulta-expandir').on('click', function () {
			setExpanded(!$('#anita-ai-consulta').hasClass('is-expanded'));
		});
		$('#anita-ai-consulta-tools-toggle').on('click', function () {
			setToolsOpen(!state.toolsOpen);
		});
		$('#anita-ai-consulta-tools-cerrar').on('click', function () {
			setToolsOpen(false);
		});
		$('#anita-ai-consulta-tools-tabs').on('click', '.anita-ai-consulta__tools-tab', function () {
			state.grupoActivo = $(this).attr('data-grupo') || 'otros';
			renderToolsTabs();
			renderToolsGrid(state.grupoActivo);
		});
		$('#anita-ai-consulta-tools-grid').on('click', '.anita-ai-consulta__tool', function () {
			var $btn = $(this);
			selectTool({
				intent: $btn.attr('data-intent'),
				placeholder: $btn.attr('data-placeholder') || '',
				etiqueta: $btn.find('.anita-ai-consulta__tool-label').text(),
				auto_pregunta: $btn.attr('data-auto') || ''
			}, !!$btn.attr('data-auto'));
		});
		$('#anita-ai-consulta-intent-clear').on('click', clearIntent);
		$('#anita-ai-consulta-enviar').on('click', consultar);
		$('#anita-ai-consulta-excel').on('click', function (e) {
			e.preventDefault();
			exportarExcel();
		});
		$('#anita-ai-consulta-chat').on('click', '.anita-ai-consulta__btn-excel', function (e) {
			e.preventDefault();
			exportarExcel();
		});
		$('#anita-ai-consulta-chat').on('click', '.anita-ai-consulta__btn-confirmar-pedido', function (e) {
			e.preventDefault();
			confirmarPedidoConsumo($(this).attr('data-tipo') || 'compra');
		});
		$('#anita-ai-consulta-chat').on('click', '.anita-ai-consulta__plan-paso', function (e) {
			e.preventDefault();
			var frase = $(this).attr('data-frase') || '';
			if (!frase) {
				return;
			}
			$('#anita-ai-consulta-pregunta').val(frase);
			autoResizeInput();
			consultar();
		});
		$('#anita-ai-consulta-pregunta').on('keydown', function (e) {
			if (e.key === 'Enter' && !e.shiftKey) {
				e.preventDefault();
				consultar();
			}
		}).on('input', autoResizeInput);
		$('#anita-ai-consulta-ejemplos').on('click', '.anita-ai-consulta__ejemplo', function (e) {
			e.preventDefault();
			$('#anita-ai-consulta-pregunta').val($(this).text());
			autoResizeInput();
			consultar();
		});

		$.ajax({
			url: CFG.urlIntents,
			method: 'GET',
			headers: { 'Accept': 'application/json' }
		}).done(function (resp) {
			if (!resp || !resp.ok) {
				return;
			}
			state.intents = Array.isArray(resp.intents) ? resp.intents : [];
			state.grupos = resp.grupos && typeof resp.grupos === 'object' ? resp.grupos : {
				compras: 'Compras',
				contable: 'Contable',
				stock: 'Stock',
				ventas: 'Ventas',
				ayuda: 'Ayuda',
				otros: 'Otros'
			};
			if (Array.isArray(resp.ejemplos)) {
				renderEjemplos(resp.ejemplos);
			}
		});
	}

	$(boot);
})(window, window.jQuery);
