/**
 * Puntos de venta: catálogo ARCA (códigos) + domicilio fiscal del padrón.
 */
(function () {
	'use strict';

	var STORAGE_KEY = 'arca_ptos_venta_v1';

	function root() {
		return document.getElementById('puntoventa-form-root');
	}

	function panel() {
		return document.getElementById('puntoventa-arca-panel') || root();
	}

	function urlPuntos() {
		var el = root() || panel();
		return el ? el.getAttribute('data-url-puntos') : '';
	}

	function urlDomicilio() {
		var el = root();
		return el ? el.getAttribute('data-url-domicilio') : '';
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

	function byId(id) {
		return document.getElementById(id);
	}

	function setVal(id, value) {
		var el = byId(id);
		if (!el) {
			return;
		}
		el.value = value == null ? '' : String(value);
	}

	function elementosUi() {
		return {
			btn: byId('btn-actualizar-ptos-arca'),
			icono: byId('btn-actualizar-ptos-arca-icono'),
			spinner: byId('btn-actualizar-ptos-arca-spinner'),
			estado: byId('puntoventa-arca-estado'),
			hint: byId('puntoventa-webservice-arca'),
		};
	}

	function elementosDomicilioUi() {
		return {
			btn: byId('btn-traer-domicilio-arca'),
			icono: byId('btn-traer-domicilio-arca-icono'),
			spinner: byId('btn-traer-domicilio-arca-spinner'),
			estado: byId('puntoventa-domicilio-arca-estado'),
		};
	}

	function mostrarProgreso(mensaje, tipo) {
		var ui = elementosUi();
		if (ui.estado) {
			ui.estado.className = 'alert py-2 px-3 mt-3 mb-0 alert-' + (tipo || 'info');
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
			ui.estado.className = 'alert py-2 px-3 mt-3 mb-0 alert-' + (tipo || 'info');
			ui.estado.innerHTML = '<i class="fa ' + icono + '" aria-hidden="true"></i> ' + mensaje;
			ui.estado.classList.remove('d-none');
		}
	}

	function mostrarDomicilioProgreso(mensaje) {
		var ui = elementosDomicilioUi();
		if (ui.estado) {
			ui.estado.className = 'alert alert-info py-2 px-3 mb-0';
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

	function ocultarDomicilioProgreso() {
		var ui = elementosDomicilioUi();
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

	function mostrarDomicilioEstado(mensaje, tipo) {
		var ui = elementosDomicilioUi();
		if (!ui.estado) {
			return;
		}
		var icono =
			tipo === 'success'
				? 'fa-check-circle'
				: tipo === 'warning'
					? 'fa-exclamation-triangle'
					: 'fa-times-circle';
		ui.estado.className = 'alert py-2 px-3 mb-0 alert-' + (tipo || 'info');
		ui.estado.innerHTML = '<i class="fa ' + icono + '" aria-hidden="true"></i> ' + mensaje;
		ui.estado.classList.remove('d-none');
	}

	function poblarSelect(puntos, codigoPreservar) {
		var sel = byId('codigo');
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

	function aplicarDomicilioFiscal(body) {
		var df = body.domicilioFiscal || {};
		var avisos = [];

		if (body.pais_id) {
			setVal('pais_id', body.pais_id);
			if (window.jQuery && jQuery.fn.select2) {
				jQuery('#pais_id').trigger('change.select2');
			}
		}

		if (df.direccion) {
			setVal('domicilio', df.direccion);
		} else if (df.texto) {
			setVal('domicilio', df.texto);
			avisos.push('Se usó el texto completo del padrón (sin calle separada).');
		}

		if (df.codPostal) {
			setVal('codigopostal', df.codPostal);
		}

		if (df.provincia) {
			setVal('desc_provincia', df.provincia);
		}
		if (df.localidad) {
			setVal('desc_localidad', df.localidad);
		}

		if (df.provincia_id) {
			setVal('provincia_id', df.provincia_id);
			if (window.jQuery && jQuery.fn.select2) {
				jQuery('#provincia_id').trigger('change.select2');
			}

			var locId = df.localidad_id || '';
			setVal('localidad_id_previa', locId);

			if (window.LocalidadCascada && window.jQuery) {
				window.LocalidadCascada.completar(
					jQuery('#localidad_id'),
					df.provincia_id,
					locId,
					df.localidad || '',
					'localidad_id'
				);
			} else if (locId) {
				setVal('localidad_id', locId);
			}
		} else if (df.provincia) {
			avisos.push('No se pudo vincular la provincia «' + df.provincia + '» con el maestro.');
		}

		if (df.localidad && !df.localidad_id) {
			avisos.push('No se pudo vincular la localidad «' + df.localidad + '» con el maestro.');
		}

		return avisos;
	}

	function cargarDomicilioFiscal() {
		var url = urlDomicilio();
		var empId = empresaId();
		if (!url) {
			mostrarDomicilioEstado('No hay endpoint de domicilio ARCA configurado.', 'danger');
			return;
		}
		if (!empId) {
			mostrarDomicilioEstado('Seleccioná una empresa para consultar el padrón ARCA.', 'warning');
			return;
		}

		mostrarDomicilioProgreso('Consultando domicilio fiscal en padrón ARCA…');

		var params = new URLSearchParams({ empresa_id: String(empId) });

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
							'No se pudo obtener el domicilio fiscal desde ARCA.'
					);
				}

				var body = result.body;
				var avisos = aplicarDomicilioFiscal(body);
				var texto =
					'Domicilio fiscal precargado' +
					(body.razon_social ? ' (' + body.razon_social + ')' : '') +
					(body.cuit ? ' — CUIT ' + body.cuit : '') +
					'.';
				if (avisos.length) {
					texto += ' ' + avisos.join(' ');
					mostrarDomicilioEstado(texto, 'warning');
				} else {
					mostrarDomicilioEstado(texto, 'success');
				}
				if (window.toastr) {
					toastr.success('Domicilio fiscal traído desde ARCA');
				}
			})
			.catch(function (err) {
				var msg = err.message || String(err);
				mostrarDomicilioEstado(msg, 'danger');
				if (window.toastr) {
					toastr.error(msg);
				}
			})
			.finally(function () {
				ocultarDomicilioProgreso();
			});
	}

	function init() {
		var btn = byId('btn-actualizar-ptos-arca');
		var btnDom = byId('btn-traer-domicilio-arca');
		var empSel = byId('empresa_id');
		var modoSel = byId('modofacturacion');
		var wsSel = byId('webservice');

		if (btn) {
			btn.addEventListener('click', function () {
				cargarPuntos(true, false);
			});
		}

		if (btnDom) {
			btnDom.addEventListener('click', function () {
				cargarDomicilioFiscal();
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

		var sel = byId('codigo');
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
