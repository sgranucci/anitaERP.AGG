/**
 * Puntos de venta: catálogo ARCA/AFIP para el campo codigo.
 */
(function () {
	'use strict';

	var STORAGE_KEY = 'arca_ptos_venta_v1';

	function panel() {
		return document.getElementById('puntoventa-arca-panel');
	}

	function urlPuntos() {
		var p = panel();
		return p ? p.getAttribute('data-url-puntos') : '';
	}

	function empresaId() {
		var sel = document.getElementById('empresa_id');
		return sel ? parseInt(sel.value, 10) : 0;
	}

	function webserviceSeleccionado() {
		var sel = document.getElementById('webservice');
		return sel ? String(sel.value || '') : '';
	}

	function modofacturacionSeleccionado() {
		var sel = document.getElementById('modofacturacion');
		return sel ? String(sel.value || '') : '';
	}

	function codigoActual() {
		var sel = document.getElementById('codigo');
		return sel ? sel.value : '';
	}

	function codigoNumerico(val) {
		var n = parseInt(String(val || '').replace(/\D+/g, ''), 10);
		return isNaN(n) ? 0 : n;
	}

	function codigosCoinciden(a, b) {
		var na = codigoNumerico(a);
		var nb = codigoNumerico(b);
		return na > 0 && na === nb;
	}

	function puntoCoincideConCodigo(punto, codigo) {
		if (!punto) {
			return false;
		}
		return codigosCoinciden(punto.codigo, codigo) || codigosCoinciden(punto.numero, codigo);
	}

	function cacheKey(empId, webservice, modofacturacion) {
		return String(empId) + ':' + (webservice || 'auto') + ':' + (modofacturacion || 'all');
	}

	function readCache() {
		try {
			var raw = sessionStorage.getItem(STORAGE_KEY);
			return raw ? JSON.parse(raw) : {};
		} catch (e) {
			return {};
		}
	}

	function writeCacheEntry(key, payload) {
		try {
			var store = readCache();
			store[key] = payload;
			sessionStorage.setItem(STORAGE_KEY, JSON.stringify(store));
		} catch (e) {
			/* ignorar */
		}
	}

	function getCached(empId, webservice, modofacturacion) {
		var store = readCache();
		var exact = store[cacheKey(empId, webservice, modofacturacion)];
		if (exact && exact.puntos) {
			return exact.puntos;
		}
		var fallback = store[cacheKey(empId, webservice, 'all')];
		if (fallback && fallback.puntos && !modofacturacion) {
			return fallback.puntos;
		}
		if (fallback && fallback.puntos && modofacturacion) {
			return filtrarPorModo(fallback.puntos, modofacturacion);
		}
		return null;
	}

	function filtrarPorModo(puntos, modo) {
		return (puntos || []).filter(function (pv) {
			var emision = String(pv.emision_tipo || '').toUpperCase();
			if (modo === 'C') {
				return emision === '' || (emision.indexOf('CAE') >= 0 && emision.indexOf('CAEA') < 0);
			}
			if (modo === 'A') {
				return emision === '' || emision.indexOf('CAEA') >= 0;
			}
			return true;
		});
	}

	function elementosUi() {
		return {
			btn: document.getElementById('btn-actualizar-ptos-arca'),
			icono: document.getElementById('btn-actualizar-ptos-arca-icono'),
			spinner: document.getElementById('btn-actualizar-ptos-arca-spinner'),
			estado: document.getElementById('puntoventa-arca-estado'),
			hint: document.getElementById('puntoventa-webservice-arca'),
		};
	}

	function mostrarProgreso(mensaje, tipo) {
		var ui = elementosUi();
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
	}

	function ocultarProgreso() {
		var ui = elementosUi();
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
		var ui = elementosUi();
		if (ui.estado) {
			var icono =
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

	function poblarSelect(puntos, codigoPreservar) {
		var sel = document.getElementById('codigo');
		if (!sel) {
			return;
		}
		var preservar = String(codigoPreservar || '');
		var coincideEnArca = (puntos || []).some(function (p) {
			return puntoCoincideConCodigo(p, preservar);
		});

		sel.innerHTML = '';
		var opt0 = document.createElement('option');
		opt0.value = '';
		opt0.textContent = '-- Elija punto de venta (ARCA) --';
		sel.appendChild(opt0);

		if (preservar && !coincideEnArca) {
			var optLegacy = document.createElement('option');
			optLegacy.value = preservar;
			optLegacy.textContent = preservar + ' — valor actual (no figura en ARCA)';
			optLegacy.selected = true;
			sel.appendChild(optLegacy);
		}

		(puntos || []).forEach(function (p) {
			var opt = document.createElement('option');
			opt.value = String(p.codigo);
			opt.textContent = p.descripcion || String(p.codigo);
			if (puntoCoincideConCodigo(p, preservar)) {
				opt.selected = true;
			}
			sel.appendChild(opt);
		});

		sel.disabled = (puntos || []).length === 0 && !preservar;

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
		var n = (body.puntos || []).length;
		var texto = 'Se cargaron ' + n + ' puntos de venta habilitados';
		if (body.webservice_etiqueta) {
			texto += ' (' + body.webservice_etiqueta + ')';
		}
		if (body.origen === 'arca') {
			texto += ' desde ARCA';
		} else if (body.origen === 'cache') {
			texto += ' desde caché del servidor';
		}
		return texto;
	}

	function cargarPuntos(refresh, silencioso) {
		var url = urlPuntos();
		var empId = empresaId();
		if (!url || !empId) {
			poblarSelect([], codigoActual());
			return;
		}

		var webservice = webserviceSeleccionado();
		var modofacturacion = modofacturacionSeleccionado();

		if (!refresh && !silencioso) {
			var cached = getCached(empId, webservice, modofacturacion);
			if (cached) {
				poblarSelect(cached, codigoActual());
				return;
			}
		}

		if (!silencioso) {
			mostrarProgreso(
				refresh
					? 'Consultando puntos de venta en ARCA/AFIP…'
					: 'Cargando puntos de venta habilitados…',
				'info'
			);
		}

		var params = new URLSearchParams({
			empresa_id: String(empId),
			refresh: refresh ? '1' : '0',
		});
		if (webservice) {
			params.set('webservice', webservice);
		}
		if (modofacturacion) {
			params.set('modofacturacion', modofacturacion);
		}

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
							'No se pudo obtener los puntos de venta desde ARCA.'
					);
				}

				var body = result.body;
				var key = cacheKey(body.empresa_id, body.webservice, modofacturacion || 'all');
				writeCacheEntry(key, {
					empresa_id: body.empresa_id,
					webservice: body.webservice,
					modofacturacion: modofacturacion || 'all',
					puntos: body.puntos || [],
					origen: body.origen,
					ts: Date.now(),
				});

				poblarSelect(body.puntos || [], codigoActual());

				if (!silencioso) {
					var textoOk = mensajeExito(body);
					mostrarEstadoFinal(textoOk, 'success');
					var ui = elementosUi();
					if (ui.hint && body.webservice_etiqueta) {
						ui.hint.textContent = textoOk;
						ui.hint.classList.remove('d-none');
					}
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
		var btn = document.getElementById('btn-actualizar-ptos-arca');
		var empSel = document.getElementById('empresa_id');
		var modoSel = document.getElementById('modofacturacion');
		var wsSel = document.getElementById('webservice');

		if (btn) {
			btn.addEventListener('click', function () {
				cargarPuntos(true, false);
			});
		}

		if (empSel) {
			empSel.addEventListener('change', function () {
				cargarPuntos(false, false);
			});
		}

		if (modoSel) {
			modoSel.addEventListener('change', function () {
				cargarPuntos(false, false);
			});
		}

		if (wsSel) {
			wsSel.addEventListener('change', function () {
				cargarPuntos(false, false);
			});
		}

		var sel = document.getElementById('codigo');
		if (sel && empresaId() > 0 && sel.options.length <= 2) {
			cargarPuntos(false, true);
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
