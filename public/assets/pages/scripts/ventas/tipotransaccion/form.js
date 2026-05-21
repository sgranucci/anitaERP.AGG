/**
 * Tipos de transacción de ventas: catálogo AFIP desde ARCA (WSMTXCA / WSFE).
 */
(function () {
	'use strict';

	function panel() {
		return document.getElementById('tipotransaccion-arca-panel');
	}

	function urlTipos() {
		const p = panel();
		return p ? p.getAttribute('data-url-tipos') : '';
	}

	function empresaId() {
		const sel = document.getElementById('empresa_arca_id');
		return sel ? parseInt(sel.value, 10) : 0;
	}

	function codigoActual() {
		const sel = document.getElementById('codigo');
		return sel ? sel.value : '';
	}

	function normalizarCodigo(val) {
		const digits = String(val || '').replace(/\D+/g, '');
		if (!digits) {
			return String(val || '');
		}
		return digits.padStart(3, '0');
	}

	function elementosUi() {
		return {
			btn: document.getElementById('btn-actualizar-tipos-arca'),
			icono: document.getElementById('btn-actualizar-tipos-arca-icono'),
			spinner: document.getElementById('btn-actualizar-tipos-arca-spinner'),
			btnTexto: document.getElementById('btn-actualizar-tipos-arca-texto'),
			estado: document.getElementById('tipotransaccion-arca-estado'),
			hint: document.getElementById('tipotransaccion-webservice-arca'),
		};
	}

	function mostrarProgreso(mensaje, tipo) {
		const ui = elementosUi();
		if (ui.estado) {
			ui.estado.className = 'alert py-2 px-3 mt-2 mb-0 alert-' + (tipo || 'info');
			ui.estado.innerHTML =
				'<i class="fa fa-spinner fa-spin" aria-hidden="true"></i> ' + mensaje;
			ui.estado.classList.remove('d-none');
		}
		if (ui.btn) {
			ui.btn.disabled = true;
		}
		if (ui.icono) {
			ui.icono.classList.add('d-none');
		}
		if (ui.spinner) {
			ui.spinner.classList.remove('d-none');
		}
		if (window.toastr && tipo === 'info') {
			toastr.info(mensaje, '', { timeOut: 8000, progressBar: true });
		}
	}

	function ocultarProgreso() {
		const ui = elementosUi();
		if (ui.btn) {
			ui.btn.disabled = false;
		}
		if (ui.icono) {
			ui.icono.classList.remove('d-none');
		}
		if (ui.spinner) {
			ui.spinner.classList.add('d-none');
		}
	}

	function mostrarEstadoFinal(mensaje, tipo) {
		const ui = elementosUi();
		if (ui.estado) {
			const icono =
				tipo === 'success'
					? 'fa-check-circle'
					: tipo === 'warning'
						? 'fa-exclamation-triangle'
						: 'fa-times-circle';
			ui.estado.className = 'alert py-2 px-3 mt-2 mb-0 alert-' + (tipo || 'info');
			ui.estado.innerHTML = '<i class="fa ' + icono + '" aria-hidden="true"></i> ' + mensaje;
			ui.estado.classList.remove('d-none');
		}
	}

	function poblarSelect(tipos, codigoPreservar) {
		const sel = document.getElementById('codigo');
		if (!sel) {
			return;
		}
		const preservar = normalizarCodigo(codigoPreservar);
		const codigos = tipos.map(function (t) {
			return t.codigo;
		});

		sel.innerHTML = '';
		const opt0 = document.createElement('option');
		opt0.value = '';
		opt0.textContent = '-- Elija tipo AFIP (ARCA) --';
		sel.appendChild(opt0);

		if (preservar && codigos.indexOf(preservar) === -1) {
			const optLegacy = document.createElement('option');
			optLegacy.value = preservar;
			optLegacy.textContent = preservar + ' — valor actual (no figura en ARCA)';
			optLegacy.selected = true;
			sel.appendChild(optLegacy);
		}

		tipos.forEach(function (t) {
			const opt = document.createElement('option');
			opt.value = t.codigo;
			opt.textContent = t.codigo + ' — ' + t.descripcion;
			opt.setAttribute('data-descripcion', t.descripcion);
			if (t.codigo === preservar) {
				opt.selected = true;
			}
			sel.appendChild(opt);
		});

		sel.disabled = tipos.length === 0 && !preservar;

		if (window.jQuery && jQuery.fn.select2) {
			jQuery(sel).trigger('change.select2');
		}
	}

	function mostrarError(msg) {
		mostrarEstadoFinal(msg, 'danger');
		if (window.toastr) {
			toastr.error(msg);
			return;
		}
		window.alert(msg);
	}

	function mensajeExito(body) {
		const n = (body.tipos || []).length;
		let texto = 'Se cargaron ' + n + ' tipos de comprobante';
		if (body.webservice_etiqueta) {
			texto += ' (' + body.webservice_etiqueta + ')';
		}
		if (body.origen === 'arca') {
			texto += ' desde ARCA';
		} else if (body.origen === 'bd') {
			texto += ' desde la base de datos';
		}
		if (body.persistido && body.registros_guardados) {
			texto +=
				'. Guardados ' +
				body.registros_guardados +
				' registros en <code>arca_tipo_comprobante</code>';
		} else if (body.origen === 'arca' && !body.persistido) {
			texto += '. <strong>No se guardó en base de datos</strong> (revise migración o logs)';
		}
		if (body.sincronizado_at) {
			texto += ' — ' + body.sincronizado_at;
		}
		return texto;
	}

	function cargarTipos(refresh, silencioso) {
		const url = urlTipos();
		const empId = empresaId();
		if (!url || !empId) {
			return;
		}

		if (!silencioso) {
			mostrarProgreso(
				refresh
					? 'Consultando tipos de comprobante en ARCA/AFIP…'
					: 'Cargando tipos desde base de datos…',
				'info'
			);
		}

		const params = new URLSearchParams({
			empresa_id: String(empId),
			refresh: refresh ? '1' : '0',
		});

		fetch(url + '?' + params.toString(), {
			headers: {
				Accept: 'application/json',
				'X-Requested-With': 'XMLHttpRequest',
			},
			credentials: 'same-origin',
		})
			.then(function (res) {
				return res.json().then(function (body) {
					return { ok: res.ok, body: body };
				});
			})
			.then(function (result) {
				if (!result.ok || !result.body.ok) {
					throw new Error(
						(result.body && result.body.message) ||
							'No se pudo obtener el catálogo AFIP desde ARCA.'
					);
				}

				if (result.body.origen === 'arca' && result.body.persistido) {
					mostrarProgreso('Guardando catálogo en base de datos…', 'info');
				}

				poblarSelect(result.body.tipos || [], codigoActual());

				const textoOk = mensajeExito(result.body);
				mostrarEstadoFinal(textoOk, result.body.persistido ? 'success' : 'warning');

				if (window.toastr) {
					toastr.success(textoOk.replace(/<[^>]+>/g, ''));
				}

				const ui = elementosUi();
				if (ui.hint && result.body.webservice_etiqueta) {
					ui.hint.textContent = textoOk.replace(/<[^>]+>/g, '');
					ui.hint.classList.remove('d-none');
				}
			})
			.catch(function (err) {
				if (!silencioso) {
					mostrarError(err.message || String(err));
				}
			})
			.finally(function () {
				ocultarProgreso();
			});
	}

	function init() {
		const btn = document.getElementById('btn-actualizar-tipos-arca');
		const empSel = document.getElementById('empresa_arca_id');

		if (btn) {
			btn.addEventListener('click', function () {
				cargarTipos(true, false);
			});
		}

		if (empSel) {
			empSel.addEventListener('change', function () {
				cargarTipos(false, false);
			});
		}

		// Si el servidor no precargó opciones (p. ej. caché de vista), completar desde BD vía API
		const sel = document.getElementById('codigo');
		if (sel && empresaId() > 0 && sel.options.length <= 2) {
			cargarTipos(false, true);
		} else if (sel && window.jQuery && jQuery.fn.select2) {
			jQuery(sel).trigger('change.select2');
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
