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
		ultimoDepositar: null,
		ultimoCaucionar: null,
		ultimoConfirmarRecepciones: null,
		ultimoEliminarRecepciones: null,
		ultimoConfirmarTm: null,
		ultimoDecisionId: null,
		historial: [],
		briefAutoPendiente: false,
		briefAutoHecho: false,
		intents: [],
		grupos: {},
		grupoActivo: null,
		toolsOpen: false,
		prefsOpen: false,
		contextoActivo: false,
		multiturn: true,
		preferencias: null
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
			var etiqueta = escapeHtml((paso && paso.etiqueta) || '');
			var frase = paso && paso.frase ? String(paso.frase) : '';
			var url = paso && paso.url ? String(paso.url) : '';
			var n = (idx + 1) + '. ';
			if (url) {
				html += '<div class="small mb-1">' + n
					+ '<a href="' + escapeHtml(url) + '" target="_blank" rel="noopener noreferrer" class="anita-ai-consulta__plan-link">'
					+ (etiqueta || 'Abrir pantalla') + '</a>';
				if (frase && frase.indexOf('[') === -1) {
					html += ' · <a href="#" class="anita-ai-consulta__plan-paso" data-frase="'
						+ escapeHtml(frase) + '">consultar en chat</a>';
				}
				html += '</div>';
				return;
			}
			if (!frase || frase.indexOf('[') !== -1) {
				html += '<div class="small mb-1">' + n + (etiqueta || escapeHtml(frase)) + '</div>';
				return;
			}
			html += '<a href="#" class="anita-ai-consulta__plan-paso d-block small mb-1" data-frase="'
				+ escapeHtml(frase) + '">' + n + (etiqueta || escapeHtml(frase)) + '</a>';
		});
		html += '</div>';
		return html;
	}

	function renderHitlEventoAcciones(data) {
		var datos = data && data.datos ? data.datos : null;
		if (!datos || !datos.puede_hitl || !Array.isArray(datos.hitl_eventos) || !datos.hitl_eventos.length) {
			return '';
		}
		if (!CFG.urlHitlEvento) {
			return '';
		}
		var html = '<div class="anita-ai-consulta__hitl mt-2">';
		html += '<div class="small text-muted mb-1">Acciones HITL (solo cierran el evento; no ejecutan el plan):</div>';
		datos.hitl_eventos.forEach(function (ev) {
			if (!ev || !ev.puede_resolver) {
				return;
			}
			html += '<div class="anita-ai-consulta__hitl-row mb-1">';
			html += '<span class="small mr-1">' + escapeHtml(ev.etiqueta || ('#' + ev.evento_id)) + '</span>';
			html += '<button type="button" class="btn btn-sm btn-outline-success mr-1 anita-ai-consulta__btn-hitl" data-accion="resolver" data-evento-id="'
				+ escapeHtml(ev.evento_id) + '">Resuelto</button>';
			html += '<button type="button" class="btn btn-sm btn-outline-secondary mr-1 anita-ai-consulta__btn-hitl" data-accion="visto" data-evento-id="'
				+ escapeHtml(ev.evento_id) + '">Visto</button>';
			html += '<button type="button" class="btn btn-sm btn-outline-danger anita-ai-consulta__btn-hitl" data-accion="descartar" data-evento-id="'
				+ escapeHtml(ev.evento_id) + '">Descartar</button>';
			if (ev.url) {
				html += ' <a class="small" href="' + escapeHtml(ev.url) + '" target="_blank" rel="noopener">Abrir</a>';
			}
			html += '</div>';
		});
		html += '</div>';
		return html;
	}

	function hitlEvento(accion, eventoId) {
		if (!CFG.urlHitlEvento || !eventoId) {
			return;
		}
		var label = accion === 'resolver' ? 'marcar RESUELTO' : (accion === 'descartar' ? 'DESCARTAR' : 'marcar VISTO');
		if (!window.confirm('¿Confirmar ' + label + ' el evento #' + eventoId + '?')) {
			return;
		}
		$.ajax({
			url: CFG.urlHitlEvento,
			method: 'POST',
			data: { accion: accion, evento_id: eventoId },
			headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' }
		}).done(function (resp) {
			appendMsg('bot', '<span class="' + ((resp && resp.ok) ? 'text-success' : 'text-warning') + '">'
				+ escapeHtml((resp && resp.message) || 'Sin respuesta') + '</span>');
			if (resp && resp.ok) {
				$('#anita-ai-consulta-pregunta').val('cola HITL');
				consultar();
			}
		}).fail(function (xhr) {
			var json = xhr && xhr.responseJSON ? xhr.responseJSON : null;
			appendMsg('bot', '<span class="text-warning">' + escapeHtml((json && json.message) || 'No se pudo actualizar el evento.') + '</span>');
		});
	}

	function renderDepositarChequesAcciones(data) {
		var datos = data && data.datos ? data.datos : null;
		var dep = datos && datos.depositar ? datos.depositar : null;
		var cau = datos && datos.caucionar ? datos.caucionar : null;
		var html = '';

		if (dep && dep.puede_depositar && Array.isArray(dep.preview) && dep.preview.length && CFG.urlHitlDepositar) {
			state.ultimoDepositar = {
				cheque_ids: dep.cheque_ids || [],
				preview: dep.preview,
				decisionId: data.ai_decision_id || null
			};
			html += '<div class="anita-ai-consulta__depositar mt-2">';
			html += '<div class="small text-muted mb-1">HITL depósito (' + dep.preview.length + ' elegibles):</div>';
			html += '<div class="form-inline flex-wrap mb-1">';
			html += '<label class="small mr-1 mb-0">Cuenta caja</label>';
			html += '<input type="text" class="form-control form-control-sm mr-1 anita-ai-consulta__cuentacaja" placeholder="código o ID" style="width:7rem">';
			html += '<button type="button" class="btn btn-sm btn-success anita-ai-consulta__btn-depositar"><i class="fa fa-university"></i> Depositar</button>';
			html += '</div></div>';
		} else {
			state.ultimoDepositar = null;
		}

		if (cau && cau.puede_caucionar && Array.isArray(cau.preview) && cau.preview.length && CFG.urlHitlCaucionar) {
			state.ultimoCaucionar = {
				cheque_ids: cau.cheque_ids || [],
				preview: cau.preview,
				decisionId: data.ai_decision_id || null
			};
			html += '<div class="anita-ai-consulta__caucionar mt-2">';
			html += '<div class="small text-muted mb-1">HITL caución (' + cau.preview.length + ' elegibles):</div>';
			html += '<div class="form-inline flex-wrap mb-1">';
			html += '<label class="small mr-1 mb-0">Nro caución</label>';
			html += '<input type="text" class="form-control form-control-sm mr-1 anita-ai-consulta__nro-caucion" placeholder="nro" style="width:7rem">';
			html += '<button type="button" class="btn btn-sm btn-outline-primary anita-ai-consulta__btn-caucionar"><i class="fa fa-lock"></i> Caucionar</button>';
			html += '</div>';
			html += '<div class="small text-muted">Requiere editar-cheque. Depósito y caución son excluyentes por cheque.</div>';
			html += '</div>';
		} else {
			state.ultimoCaucionar = null;
		}

		var borrador = datos && datos.crear_borrador_propuesta ? datos.crear_borrador_propuesta : null;
		if (borrador && borrador.puede && CFG.urlHitlCrearBorradorPropuesta) {
			state.ultimoCrearBorrador = {
				empresa_id: borrador.empresa_id || null,
				fecha_hasta: borrador.fecha_hasta || '',
				decisionId: data.ai_decision_id || null
			};
			html += '<div class="anita-ai-consulta__crear-borrador mt-2">';
			html += '<div class="small text-muted mb-1">HITL propuesta de pago:</div>';
			html += '<button type="button" class="btn btn-sm btn-outline-success anita-ai-consulta__btn-crear-borrador">';
			html += '<i class="fa fa-file-text-o"></i> Crear borrador (deuda vencida)</button>';
			html += '<div class="small text-muted mt-1">Queda en BORRADOR para revisar y enviar a aprobación. No paga ni firma sola.</div>';
			html += '</div>';
		} else if (borrador && borrador.mensaje) {
			state.ultimoCrearBorrador = null;
			html += '<div class="small text-muted mt-2">' + escapeHtml(borrador.mensaje) + '</div>';
		} else {
			state.ultimoCrearBorrador = null;
		}

		var conf = datos && datos.confirmar ? datos.confirmar : null;
		if (conf && conf.puede_confirmar && Array.isArray(conf.preview) && conf.preview.length && CFG.urlHitlConfirmarRecepciones) {
			state.ultimoConfirmarRecepciones = {
				recepcion_ids: conf.recepcion_ids || [],
				preview: conf.preview,
				decisionId: data.ai_decision_id || null
			};
			html += '<div class="anita-ai-consulta__confirmar-recepcion mt-2">';
			html += '<div class="small text-muted mb-1">HITL confirmar COM (' + conf.preview.length + ' borradores):</div>';
			html += '<button type="button" class="btn btn-sm btn-success anita-ai-consulta__btn-confirmar-recepcion">';
			html += '<i class="fa fa-check"></i> Confirmar recepción(es)</button>';
			html += '<div class="small text-muted mt-1">Requiere confirmar-recepcion-proveedor. Solo BORRADOR; stock + asiento + Anita.</div>';
			html += '</div>';
		} else {
			state.ultimoConfirmarRecepciones = null;
		}

		var elim = datos && datos.eliminar ? datos.eliminar : null;
		if (elim && elim.puede_eliminar && Array.isArray(elim.preview) && elim.preview.length && CFG.urlHitlEliminarRecepciones) {
			state.ultimoEliminarRecepciones = {
				recepcion_ids: elim.recepcion_ids || [],
				preview: elim.preview,
				decisionId: data.ai_decision_id || null
			};
			html += '<div class="anita-ai-consulta__eliminar-recepcion mt-2">';
			html += '<button type="button" class="btn btn-sm btn-outline-danger anita-ai-consulta__btn-eliminar-recepcion">';
			html += '<i class="fa fa-trash"></i> Eliminar borrador(es) COM</button>';
			html += '<div class="small text-muted mt-1">Solo BORRADOR. Las confirmadas no se anulan (usar devolución).</div>';
			html += '</div>';
		} else {
			state.ultimoEliminarRecepciones = null;
		}

		var tm = datos && datos.confirmar_tm ? datos.confirmar_tm : null;
		if (tm && tm.puede_confirmar && Array.isArray(tm.preview) && tm.preview.length && CFG.urlHitlConfirmarTransferencias) {
			state.ultimoConfirmarTm = {
				transferencia_ids: tm.transferencia_ids || [],
				preview: tm.preview,
				decisionId: data.ai_decision_id || null
			};
			html += '<div class="anita-ai-consulta__confirmar-tm mt-2">';
			html += '<div class="small text-muted mb-1">HITL confirmar TM (' + tm.preview.length + '):</div>';
			html += '<button type="button" class="btn btn-sm btn-success anita-ai-consulta__btn-confirmar-tm">';
			html += '<i class="fa fa-check"></i> Confirmar recepción TM</button>';
			html += '</div>';
		} else {
			state.ultimoConfirmarTm = null;
		}

		html += renderEnviarAprobacionPropuestas(datos, data.ai_decision_id || null);

		return html;
	}

	function renderEnviarAprobacionPropuestas(datos, decisionId) {
		var enviar = datos && datos.enviar_aprobacion_propuestas ? datos.enviar_aprobacion_propuestas : null;
		if (!enviar || !CFG.urlHitlEnviarAprobacionPropuesta) {
			return '';
		}
		var preview = Array.isArray(enviar.preview) ? enviar.preview : [];
		if (!enviar.puede || !preview.length) {
			if (enviar.mensaje) {
				return '<div class="small text-muted mt-2">' + escapeHtml(enviar.mensaje) + '</div>';
			}
			return '';
		}
		var html = '<div class="anita-ai-consulta__enviar-aprobacion mt-2">';
		html += '<div class="small text-muted mb-1">HITL enviar a aprobación (' + preview.length + '):</div>';
		preview.forEach(function (p) {
			if (!p || !p.id) {
				return;
			}
			var monto = typeof p.monto === 'number'
				? p.monto.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
				: (p.monto || '');
			html += '<div class="d-flex flex-wrap align-items-center mb-1">';
			html += '<span class="small mr-2">#' + escapeHtml(String(p.id)) + ' · ' + escapeHtml(p.estado || '')
				+ ' · $ ' + escapeHtml(String(monto)) + '</span>';
			if (p.url) {
				html += '<a class="text-primary small mr-2" href="' + escapeHtml(p.url)
					+ '" target="_blank" rel="noopener">Abrir</a>';
			}
			html += '<button type="button" class="btn btn-sm btn-primary anita-ai-consulta__btn-enviar-aprobacion"'
				+ ' data-propuesta-id="' + escapeHtml(String(p.id)) + '"'
				+ ' data-exige-arbol="' + (p.exige_arbol ? '1' : '0') + '"'
				+ ' data-decision-id="' + escapeHtml(String(decisionId || '')) + '">'
				+ '<i class="fa fa-paper-plane"></i> Enviar</button>';
			html += '</div>';
		});
		html += '<div class="small text-muted">Va al árbol de aprobación. En modo light autoriza sin árbol (no ejecuta OP).</div>';
		html += '</div>';
		return html;
	}

	function htmlEnviarAprobacionTrasBorrador(meta, decisionId) {
		if (!meta || !meta.puede || !meta.propuesta_pago_id || !CFG.urlHitlEnviarAprobacionPropuesta) {
			return '';
		}
		var html = '<div class="anita-ai-consulta__enviar-aprobacion mt-2">';
		html += '<button type="button" class="btn btn-sm btn-primary anita-ai-consulta__btn-enviar-aprobacion"'
			+ ' data-propuesta-id="' + escapeHtml(String(meta.propuesta_pago_id)) + '"'
			+ ' data-exige-arbol="' + (meta.exige_arbol ? '1' : '0') + '"'
			+ ' data-decision-id="' + escapeHtml(String(decisionId || '')) + '">'
			+ '<i class="fa fa-paper-plane"></i> Enviar #' + escapeHtml(String(meta.propuesta_pago_id))
			+ ' a aprobación</button>';
		html += '<div class="small text-muted mt-1">No ejecuta OP ni firma niveles del árbol.</div>';
		html += '</div>';
		return html;
	}

	function crearBorradorPropuestaDesdeChat($btn) {
		if (!state.ultimoCrearBorrador || !CFG.urlHitlCrearBorradorPropuesta) {
			showError('No hay contexto de AP para crear el borrador.');
			return;
		}
		var emp = state.ultimoCrearBorrador.empresa_id;
		if (!emp) {
			showError('Fijá empresa en preferencias del FAB antes de crear el borrador.');
			return;
		}
		if (!window.confirm('¿Crear propuesta de pago en BORRADOR con deuda vencida (empresa ' + emp + ')?')) {
			return;
		}
		$btn.prop('disabled', true);
		$.ajax({
			url: CFG.urlHitlCrearBorradorPropuesta,
			method: 'POST',
			data: {
				empresa_id: emp,
				fecha_hasta: state.ultimoCrearBorrador.fecha_hasta || '',
				ai_decision_id: state.ultimoCrearBorrador.decisionId || state.ultimoDecisionId || ''
			},
			headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' }
		}).done(function (resp) {
			var msg = (resp && resp.message) || 'Sin respuesta';
			var html = '<span class="' + ((resp && resp.ok) ? 'text-success' : 'text-warning') + '">'
				+ escapeHtml(msg) + '</span>';
			if (resp && resp.ok && resp.url) {
				html += ' <a class="text-primary" href="' + escapeHtml(resp.url)
					+ '" target="_blank" rel="noopener">Abrir propuesta</a>';
			}
			if (resp && resp.ok && resp.enviar_aprobacion) {
				html += htmlEnviarAprobacionTrasBorrador(
					resp.enviar_aprobacion,
					state.ultimoCrearBorrador.decisionId || state.ultimoDecisionId || ''
				);
			}
			appendMsg('bot', html);
		}).fail(function (xhr) {
			var json = xhr && xhr.responseJSON ? xhr.responseJSON : null;
			appendMsg('bot', '<span class="text-warning">' + escapeHtml((json && json.message) || 'No se pudo crear el borrador.') + '</span>');
		}).always(function () {
			$btn.prop('disabled', false);
		});
	}

	function enviarAprobacionPropuestaDesdeChat($btn) {
		if (!CFG.urlHitlEnviarAprobacionPropuesta) {
			showError('Envío a aprobación no configurado.');
			return;
		}
		var id = parseInt($btn.attr('data-propuesta-id') || '0', 10) || 0;
		if (id <= 0) {
			showError('Propuesta inválida.');
			return;
		}
		var exigeArbol = $btn.attr('data-exige-arbol') !== '0';
		var aviso = exigeArbol
			? '¿Enviar propuesta #' + id + ' al árbol de aprobación?\nNo firma niveles ni ejecuta OP.'
			: '¿Enviar propuesta #' + id + '?\nModo light: quedará AUTORIZADA sin árbol (no ejecuta OP).';
		if (!window.confirm(aviso)) {
			return;
		}
		$btn.prop('disabled', true);
		$.ajax({
			url: CFG.urlHitlEnviarAprobacionPropuesta,
			method: 'POST',
			data: {
				propuesta_pago_id: id,
				ai_decision_id: $btn.attr('data-decision-id') || state.ultimoDecisionId || ''
			},
			headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' }
		}).done(function (resp) {
			var msg = (resp && resp.message) || 'Sin respuesta';
			var html = '<span class="' + ((resp && resp.ok) ? 'text-success' : 'text-warning') + '">'
				+ escapeHtml(msg) + '</span>';
			if (resp && resp.estado) {
				html += ' <span class="badge badge-info">' + escapeHtml(resp.estado) + '</span>';
			}
			if (resp && resp.ok && resp.url) {
				html += ' <a class="text-primary" href="' + escapeHtml(resp.url)
					+ '" target="_blank" rel="noopener">Abrir propuesta</a>';
			}
			appendMsg('bot', html);
		}).fail(function (xhr) {
			var json = xhr && xhr.responseJSON ? xhr.responseJSON : null;
			appendMsg('bot', '<span class="text-warning">' + escapeHtml((json && json.message) || 'No se pudo enviar.') + '</span>');
		}).always(function () {
			$btn.prop('disabled', false);
		});
	}

	function confirmarRecepcionesDesdeChat() {
		if (!state.ultimoConfirmarRecepciones || !CFG.urlHitlConfirmarRecepciones) {
			showError('No hay recepciones elegibles en la última consulta.');
			return;
		}
		var n = state.ultimoConfirmarRecepciones.recepcion_ids.length;
		if (!window.confirm('¿Confirmar ' + n + ' recepción(es) COM en borrador? Se impactará stock y contabilidad.')) {
			return;
		}
		$.ajax({
			url: CFG.urlHitlConfirmarRecepciones,
			method: 'POST',
			data: {
				recepcion_ids: JSON.stringify(state.ultimoConfirmarRecepciones.recepcion_ids),
				ai_decision_id: state.ultimoConfirmarRecepciones.decisionId || state.ultimoDecisionId || ''
			},
			headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' }
		}).done(function (resp) {
			appendMsg('bot', '<span class="' + ((resp && resp.ok) ? 'text-success' : 'text-warning') + '">'
				+ escapeHtml((resp && resp.message) || 'Sin respuesta') + '</span>');
		}).fail(function (xhr) {
			var json = xhr && xhr.responseJSON ? xhr.responseJSON : null;
			appendMsg('bot', '<span class="text-warning">' + escapeHtml((json && json.message) || 'No se pudo confirmar.') + '</span>');
		});
	}

	function eliminarRecepcionesDesdeChat() {
		if (!state.ultimoEliminarRecepciones || !CFG.urlHitlEliminarRecepciones) {
			showError('No hay borradores COM elegibles.');
			return;
		}
		var n = state.ultimoEliminarRecepciones.recepcion_ids.length;
		if (!window.confirm('¿Eliminar ' + n + ' recepción(es) en BORRADOR? No se puede deshacer.')) {
			return;
		}
		$.ajax({
			url: CFG.urlHitlEliminarRecepciones,
			method: 'POST',
			data: {
				recepcion_ids: JSON.stringify(state.ultimoEliminarRecepciones.recepcion_ids),
				ai_decision_id: state.ultimoEliminarRecepciones.decisionId || state.ultimoDecisionId || ''
			},
			headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' }
		}).done(function (resp) {
			appendMsg('bot', '<span class="' + ((resp && resp.ok) ? 'text-success' : 'text-warning') + '">'
				+ escapeHtml((resp && resp.message) || 'Sin respuesta') + '</span>');
		}).fail(function (xhr) {
			var json = xhr && xhr.responseJSON ? xhr.responseJSON : null;
			appendMsg('bot', '<span class="text-warning">' + escapeHtml((json && json.message) || 'No se pudo eliminar.') + '</span>');
		});
	}

	function confirmarTmDesdeChat() {
		if (!state.ultimoConfirmarTm || !CFG.urlHitlConfirmarTransferencias) {
			showError('No hay TM pendientes elegibles.');
			return;
		}
		var n = state.ultimoConfirmarTm.transferencia_ids.length;
		if (!window.confirm('¿Confirmar recepción de ' + n + ' transferencia(s)?')) {
			return;
		}
		$.ajax({
			url: CFG.urlHitlConfirmarTransferencias,
			method: 'POST',
			data: {
				transferencia_ids: JSON.stringify(state.ultimoConfirmarTm.transferencia_ids),
				ai_decision_id: state.ultimoConfirmarTm.decisionId || state.ultimoDecisionId || ''
			},
			headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' }
		}).done(function (resp) {
			appendMsg('bot', '<span class="' + ((resp && resp.ok) ? 'text-success' : 'text-warning') + '">'
				+ escapeHtml((resp && resp.message) || 'Sin respuesta') + '</span>');
		}).fail(function (xhr) {
			var json = xhr && xhr.responseJSON ? xhr.responseJSON : null;
			appendMsg('bot', '<span class="text-warning">' + escapeHtml((json && json.message) || 'No se pudo confirmar TM.') + '</span>');
		});
	}

	function historialKey() {
		return 'anita_ai_consulta_historial_v1';
	}

	function cargarHistorial() {
		try {
			var raw = window.localStorage.getItem(historialKey());
			var arr = raw ? JSON.parse(raw) : [];
			state.historial = Array.isArray(arr) ? arr.slice(0, 8) : [];
		} catch (e) {
			state.historial = [];
		}
	}

	function guardarHistorialItem(pregunta) {
		pregunta = $.trim(pregunta || '');
		if (!pregunta) {
			return;
		}
		cargarHistorial();
		state.historial = state.historial.filter(function (p) { return p !== pregunta; });
		state.historial.unshift(pregunta);
		state.historial = state.historial.slice(0, 8);
		try {
			window.localStorage.setItem(historialKey(), JSON.stringify(state.historial));
		} catch (e) {}
		renderHistorial();
	}

	function renderHistorial() {
		var $box = $('#anita-ai-consulta-historial');
		if (!$box.length) {
			return;
		}
		$box.empty();
		if (!state.historial.length) {
			return;
		}
		$box.append($('<div class="small text-muted mb-1"></div>').text('Recientes:'));
		state.historial.forEach(function (p) {
			$box.append(
				$('<button type="button" class="btn btn-sm btn-outline-secondary mr-1 mb-1 anita-ai-consulta__hist-btn"></button>')
					.attr('title', 'Repetir')
					.text(p.length > 42 ? p.slice(0, 40) + '…' : p)
					.attr('data-frase', p)
			);
		});
	}

	function depositarChequesDesdeChat($btn) {
		if (!state.ultimoDepositar || !CFG.urlHitlDepositar) {
			showError('No hay cheques elegibles en la última consulta.');
			return;
		}
		var $wrap = $btn.closest('.anita-ai-consulta__depositar');
		var cuenta = $.trim($wrap.find('.anita-ai-consulta__cuentacaja').val() || '');
		if (!cuenta) {
			showError('Indique el código o ID de la cuenta de caja.');
			return;
		}
		var n = state.ultimoDepositar.cheque_ids.length;
		if (!window.confirm('¿Depositar ' + n + ' cheque(s) en la cuenta «' + cuenta + '»?')) {
			return;
		}
		$.ajax({
			url: CFG.urlHitlDepositar,
			method: 'POST',
			data: {
				cheque_ids: JSON.stringify(state.ultimoDepositar.cheque_ids),
				cuentacaja: cuenta,
				ai_decision_id: state.ultimoDepositar.decisionId || state.ultimoDecisionId || ''
			},
			headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' }
		}).done(function (resp) {
			appendMsg('bot', '<span class="' + ((resp && resp.ok) ? 'text-success' : 'text-warning') + '">'
				+ escapeHtml((resp && resp.message) || 'Sin respuesta') + '</span>');
		}).fail(function (xhr) {
			var json = xhr && xhr.responseJSON ? xhr.responseJSON : null;
			appendMsg('bot', '<span class="text-warning">' + escapeHtml((json && json.message) || 'No se pudo depositar.') + '</span>');
		});
	}

	function caucionarChequesDesdeChat($btn) {
		if (!state.ultimoCaucionar || !CFG.urlHitlCaucionar) {
			showError('No hay cheques elegibles para caución en la última consulta.');
			return;
		}
		var $wrap = $btn.closest('.anita-ai-consulta__caucionar');
		var nro = $.trim($wrap.find('.anita-ai-consulta__nro-caucion').val() || '');
		if (!nro || nro === '0') {
			showError('Indique el número de caución.');
			return;
		}
		var n = state.ultimoCaucionar.cheque_ids.length;
		if (!window.confirm('¿Caucionar ' + n + ' cheque(s) con nro «' + nro + '»?')) {
			return;
		}
		$.ajax({
			url: CFG.urlHitlCaucionar,
			method: 'POST',
			data: {
				cheque_ids: JSON.stringify(state.ultimoCaucionar.cheque_ids),
				nro_caucion: nro,
				ai_decision_id: state.ultimoCaucionar.decisionId || state.ultimoDecisionId || ''
			},
			headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' }
		}).done(function (resp) {
			appendMsg('bot', '<span class="' + ((resp && resp.ok) ? 'text-success' : 'text-warning') + '">'
				+ escapeHtml((resp && resp.message) || 'Sin respuesta') + '</span>');
		}).fail(function (xhr) {
			var json = xhr && xhr.responseJSON ? xhr.responseJSON : null;
			appendMsg('bot', '<span class="text-warning">' + escapeHtml((json && json.message) || 'No se pudo caucionar.') + '</span>');
		});
	}

	function setFabBadge(n) {
		var $b = $('#anita-ai-consulta-fab-badge');
		if (!$b.length) {
			return;
		}
		n = parseInt(n, 10) || 0;
		if (n > 0) {
			$b.text(n > 99 ? '99+' : String(n)).removeClass('d-none');
		} else {
			$b.addClass('d-none').text('');
		}
	}

	function renderAvisoInicio(aviso) {
		var $box = $('#anita-ai-consulta-aviso-inicio').empty();
		if (!aviso) {
			return;
		}
		var hitl = parseInt(aviso.hitl_pendientes, 10) || 0;
		var alerta = aviso.alerta_umbral && Array.isArray(aviso.alerta_umbral.lineas)
			? aviso.alerta_umbral.lineas
			: [];
		setFabBadge(hitl + (alerta.length ? 1 : 0));
		if (alerta.length) {
			$box.append(
				$('<div class="alert alert-warning py-1 px-2 mb-1 small"></div>')
					.text('Umbral: ' + alerta.slice(0, 2).join(' · '))
			);
		}
		if (hitl > 0) {
			$box.append(
				$('<button type="button" class="btn btn-sm btn-outline-warning mr-1 mb-1 anita-ai-consulta__aviso-btn"></button>')
					.attr('data-frase', 'cola HITL')
					.html('<i class="fa fa-bell"></i> ' + hitl + ' evento(s) HITL')
			);
		}
		if (aviso.brief_sugerido && !aviso.brief_auto_ejecutar) {
			$box.append(
				$('<button type="button" class="btn btn-sm btn-outline-primary mb-1 anita-ai-consulta__aviso-btn"></button>')
					.attr('data-frase', 'armar brief del día')
					.html('<i class="fa fa-sun-o"></i> Armar brief del día')
			);
		}
		if (aviso.brief_auto_ejecutar) {
			$box.append(
				$('<span class="small text-muted d-inline-block mb-1 mr-2"></span>')
					.html('<i class="fa fa-sun-o"></i> Armando brief del día…')
			);
		}
	}

	function marcarAutoBriefPendiente(aviso) {
		if (aviso && aviso.brief_auto_ejecutar && !state.briefAutoHecho) {
			state.briefAutoPendiente = true;
		}
	}

	/** Al abrir el FAB: arma el brief 1×/día si auto_brief está ON. */
	function maybeAutoBriefAlAbrir() {
		if (!state.briefAutoPendiente || state.briefAutoHecho || state.busy) {
			return;
		}
		state.briefAutoPendiente = false;
		state.briefAutoHecho = true;
		$('#anita-ai-consulta-pregunta').val('armar brief del día');
		autoResizeInput();
		setTimeout(function () {
			consultar();
		}, 250);
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
			maybeAutoBriefAlAbrir();
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
			setPrefsOpen(false);
			$tools.prop('hidden', false);
			$('#anita-ai-consulta-tools-toggle').addClass('is-open');
			renderToolsTabs();
			renderToolsGrid(state.grupoActivo);
		} else {
			$tools.prop('hidden', true);
			$('#anita-ai-consulta-tools-toggle').removeClass('is-open');
		}
	}

	function setPrefsOpen(open) {
		state.prefsOpen = !!open;
		var $prefs = $('#anita-ai-consulta-prefs');
		if (!$prefs.length) {
			return;
		}
		if (open) {
			setToolsOpen(false);
			hydratePrefsForm(state.preferencias || {});
			$prefs.prop('hidden', false);
			$('#anita-ai-consulta-prefs-toggle').attr('aria-pressed', 'true').addClass('is-open');
		} else {
			$prefs.prop('hidden', true);
			$('#anita-ai-consulta-prefs-toggle').attr('aria-pressed', 'false').removeClass('is-open');
		}
	}

	function hydratePrefsForm(prefs) {
		prefs = prefs || {};
		$('#anita-ai-pref-asistente-rol').val(prefs.asistente_rol || 'auto');
		$('#anita-ai-pref-empresa').val(prefs.empresa_id || '');
		$('#anita-ai-pref-cc').val(prefs.centrocosto_codigo || '');
		$('#anita-ai-pref-dep').val(prefs.deposito_codigo || '');
		$('#anita-ai-pref-auto-brief').prop('checked', prefs.auto_brief !== false);
		$('#anita-ai-pref-notify').prop('checked', prefs.notify_brief_campanita !== false);
		$('#anita-ai-pref-notify-mail').prop('checked', !!prefs.notify_brief_mail);
		$('#anita-ai-pref-alerta-umbral').prop('checked', !!prefs.notify_alerta_umbral);
		$('#anita-ai-pref-umbral-cheques').val(prefs.umbral_cheques_vencidos || '');
		$('#anita-ai-pref-umbral-deuda').val(prefs.umbral_deuda_ap || '');
		$('#anita-ai-pref-umbral-tickets').val(prefs.umbral_tickets_abiertos || '');
		$('#anita-ai-pref-umbral-stock').val(prefs.umbral_stock_critico || '');
		$('#anita-ai-consulta-prefs-msg').text('');
	}

	function recargarIntentsTrasRol() {
		if (!CFG.urlIntents) {
			return;
		}
		$.ajax({
			url: CFG.urlIntents,
			method: 'GET',
			headers: { 'Accept': 'application/json' }
		}).done(function (resp) {
			if (!resp || !resp.ok) {
				return;
			}
			state.intents = Array.isArray(resp.intents) ? resp.intents : [];
			if (resp.grupos && typeof resp.grupos === 'object') {
				state.grupos = resp.grupos;
			}
			if (Array.isArray(resp.ejemplos)) {
				renderEjemplos(resp.ejemplos);
			}
			if (state.toolsOpen) {
				renderToolsTabs();
				renderToolsGrid(state.grupoActivo);
			}
		});
	}

	function guardarPreferencias(e) {
		if (e) {
			e.preventDefault();
		}
		if (!CFG.urlPreferencias) {
			showError('Preferencias no configuradas.');
			return;
		}
		var payload = {
			asistente_rol: $('#anita-ai-pref-asistente-rol').val() || 'auto',
			empresa_id: $('#anita-ai-pref-empresa').val() || '',
			centrocosto_codigo: $.trim($('#anita-ai-pref-cc').val() || ''),
			deposito_codigo: $.trim($('#anita-ai-pref-dep').val() || ''),
			auto_brief: $('#anita-ai-pref-auto-brief').is(':checked') ? 1 : 0,
			notify_brief_campanita: $('#anita-ai-pref-notify').is(':checked') ? 1 : 0,
			notify_brief_mail: $('#anita-ai-pref-notify-mail').is(':checked') ? 1 : 0,
			notify_alerta_umbral: $('#anita-ai-pref-alerta-umbral').is(':checked') ? 1 : 0,
			umbral_cheques_vencidos: $('#anita-ai-pref-umbral-cheques').val() || '',
			umbral_deuda_ap: $('#anita-ai-pref-umbral-deuda').val() || '',
			umbral_tickets_abiertos: $('#anita-ai-pref-umbral-tickets').val() || '',
			umbral_stock_critico: $('#anita-ai-pref-umbral-stock').val() || ''
		};
		$.ajax({
			url: CFG.urlPreferencias,
			method: 'POST',
			data: payload,
			headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' }
		}).done(function (resp) {
			if (resp && resp.ok && resp.preferencias) {
				state.preferencias = resp.preferencias;
				if (Array.isArray(resp.ejemplos)) {
					renderEjemplos(resp.ejemplos);
				}
				$('#anita-ai-consulta-prefs-msg').text('Guardado.');
				recargarIntentsTrasRol();
			} else {
				$('#anita-ai-consulta-prefs-msg').text((resp && resp.message) || 'No se pudo guardar.');
			}
		}).fail(function () {
			$('#anita-ai-consulta-prefs-msg').text('Error al guardar.');
		});
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
			return 'interpretado por LLM (Ollama)';
		}
		if (fuente === 'llm_externo') {
			return 'interpretado por LLM externo (failover)';
		}
		if (fuente === 'reglas') {
			return 'interpretado por reglas';
		}
		if (fuente === 'seguimiento') {
			return 'continuación del chat';
		}
		if (fuente === 'tipado') {
			return 'atajo tipado';
		}
		return fuente ? String(fuente) : '';
	}

	function setContextoActivo(activo, resumen) {
		state.contextoActivo = !!activo;
		var $pill = $('#anita-ai-consulta-contexto-pill');
		if (!$pill.length) {
			return;
		}
		if (activo) {
			$pill.removeClass('d-none');
			if (resumen) {
				$pill.attr('title', String(resumen));
			}
		} else {
			$pill.addClass('d-none');
		}
	}

	function reiniciarContexto() {
		state.contextoActivo = false;
		setContextoActivo(false);
		appendMsg('bot', '<span class="text-muted small">Contexto del chat reiniciado. La próxima consulta empieza de cero.</span>');
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
			if (data.seguimiento) {
				html += ' <span class="badge badge-info">seguimiento</span>';
			}
			html += '</div>';
		} else if (data.fuente) {
			html += '<div class="anita-ai-consulta__interp text-muted mb-1">'
				+ escapeHtml(etiquetaFuente(data.fuente)) + '</div>';
		}
		if (data.resumen_nl) {
			html += '<div class="anita-ai-consulta__resumen mb-2">' + escapeHtml(data.resumen_nl);
			if (data.resumen_fuente === 'llm_externo') {
				html += ' <span class="anita-ai-consulta__fuente">(resumen vía LLM externo)</span>';
			}
			html += '</div>';
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
		html += renderHitlEventoAcciones(data);
		html += renderDepositarChequesAcciones(data);
		state.ultimoDecisionId = data.ai_decision_id || null;
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
		var order = ['compras', 'tesoreria', 'contable', 'stock', 'ventas', 'ayuda', 'otros'];
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
			guardarHistorialItem(pregunta);
			renderRespuestaBot(resp);
			setContextoActivo(!!resp.contexto_activo, resp.interpretacion || '');
			$('#anita-ai-consulta-pregunta').val('');
			autoResizeInput();
			clearIntent();
			if (state.multiturn) {
				$('#anita-ai-consulta-pregunta').attr(
					'placeholder',
					'Seguí con «y el mes pasado», «de biyemas»… o una consulta nueva'
				);
			}
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
		$('#anita-ai-consulta-prefs-toggle').on('click', function () {
			setPrefsOpen(!state.prefsOpen);
		});
		$('#anita-ai-consulta-prefs-cerrar').on('click', function () {
			setPrefsOpen(false);
		});
		$('#anita-ai-consulta-prefs-form').on('submit', guardarPreferencias);
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
		$('#anita-ai-consulta-contexto-clear').on('click', function () {
			if (state.busy) {
				return;
			}
			state.busy = true;
			$.ajax({
				url: CFG.urlConsultar,
				method: 'POST',
				data: { reiniciar_contexto: 1, pregunta: '' },
				headers: {
					'X-CSRF-TOKEN': csrf(),
					'Accept': 'application/json'
				}
			}).always(function () {
				state.busy = false;
				reiniciarContexto();
			});
		});
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
		$('#anita-ai-consulta-chat').on('click', '.anita-ai-consulta__btn-hitl', function (e) {
			e.preventDefault();
			hitlEvento($(this).attr('data-accion') || '', $(this).attr('data-evento-id') || '');
		});
		$('#anita-ai-consulta-chat').on('click', '.anita-ai-consulta__btn-depositar', function (e) {
			e.preventDefault();
			depositarChequesDesdeChat($(this));
		});
		$('#anita-ai-consulta-chat').on('click', '.anita-ai-consulta__btn-confirmar-recepcion', function (e) {
			e.preventDefault();
			confirmarRecepcionesDesdeChat();
		});
		$('#anita-ai-consulta-chat').on('click', '.anita-ai-consulta__btn-eliminar-recepcion', function (e) {
			e.preventDefault();
			eliminarRecepcionesDesdeChat();
		});
		$('#anita-ai-consulta-chat').on('click', '.anita-ai-consulta__btn-confirmar-tm', function (e) {
			e.preventDefault();
			confirmarTmDesdeChat();
		});
		$('#anita-ai-consulta-chat').on('click', '.anita-ai-consulta__btn-caucionar', function (e) {
			e.preventDefault();
			caucionarChequesDesdeChat($(this));
		});
		$('#anita-ai-consulta-chat').on('click', '.anita-ai-consulta__btn-crear-borrador', function (e) {
			e.preventDefault();
			crearBorradorPropuestaDesdeChat($(this));
		});
		$('#anita-ai-consulta-chat').on('click', '.anita-ai-consulta__btn-enviar-aprobacion', function (e) {
			e.preventDefault();
			enviarAprobacionPropuestaDesdeChat($(this));
		});
		$('#anita-ai-consulta-aviso-inicio').on('click', '.anita-ai-consulta__aviso-btn', function (e) {
			e.preventDefault();
			var frase = $(this).attr('data-frase') || '';
			if (!frase) {
				return;
			}
			setOpen(true);
			$('#anita-ai-consulta-pregunta').val(frase);
			autoResizeInput();
			consultar();
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
		$('#anita-ai-consulta-historial').on('click', '.anita-ai-consulta__hist-btn', function (e) {
			e.preventDefault();
			var frase = $(this).attr('data-frase') || '';
			if (!frase) {
				return;
			}
			$('#anita-ai-consulta-pregunta').val(frase);
			autoResizeInput();
			consultar();
		});
		cargarHistorial();
		renderHistorial();

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
				tesoreria: 'Tesorería',
				contable: 'Contable',
				stock: 'Stock',
				ventas: 'Ventas',
				ayuda: 'Ayuda',
				otros: 'Otros'
			};
			state.multiturn = resp.multiturn !== false;
			state.preferencias = resp.preferencias || null;
			setContextoActivo(!!resp.contexto_activo, resp.contexto_resumen || '');
			if (Array.isArray(resp.ejemplos)) {
				renderEjemplos(resp.ejemplos);
			}
			renderAvisoInicio(resp.aviso || null);
			marcarAutoBriefPendiente(resp.aviso || null);
		});
	}

	$(boot);

	window.AnitaAiConsultaAbrir = function (frase) {
		setOpen(true);
		var $ta = $('#anita-ai-consulta-pregunta');
		if (frase) {
			$ta.val(String(frase));
			autoResizeInput();
		}
		$ta.trigger('focus');
	};

	window.AnitaAiConsultaEnviar = function (frase) {
		window.AnitaAiConsultaAbrir(frase);
		consultar();
	};
})(window, window.jQuery);
