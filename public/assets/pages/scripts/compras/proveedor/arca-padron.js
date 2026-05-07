/**
 * Consulta padrón ARCA (constancia de inscripción) para el alta/edición de proveedores.
 * Requiere en la página: #tab2[data-arca-constancia-url], meta csrf o input _token.
 */
(function () {
	function qs(sel) {
		return document.querySelector(sel);
	}

	function byId(id) {
		return document.getElementById(id);
	}

	function getVal(id) {
		const el = byId(id);
		return el ? el.value : '';
	}

	function setVal(id, value) {
		const el = byId(id);
		if (!el) return;
		el.value = value ?? '';
	}

	function triggerChange(id) {
		const el = byId(id);
		if (!el) return;
		el.dispatchEvent(new Event('change', { bubbles: true }));
	}

	function getCsrfToken() {
		const meta = qs('meta[name="csrf-token"]');
		if (meta && meta.getAttribute('content')) return meta.getAttribute('content');
		const input = qs('input[name="_token"]');
		return input ? input.value : '';
	}

	function getArcaEndpointUrl() {
		const tab = byId('tab2');
		let u = tab && tab.getAttribute('data-arca-constancia-url');
		if (!u) {
			const any = document.querySelector('[data-arca-constancia-url]');
			u = any && any.getAttribute('data-arca-constancia-url');
		}
		return u ? String(u).trim() : '';
	}

	function soloDigitos(v) {
		return (v || '').toString().replace(/\D+/g, '');
	}

	function cssEscape(v) {
		if (window.CSS && typeof window.CSS.escape === 'function') return window.CSS.escape(String(v));
		return String(v).replace(/["\\]/g, '\\$&');
	}

	function escapeHtml(str) {
		const d = document.createElement('div');
		d.textContent = str == null ? '' : String(str);
		return d.innerHTML;
	}

	let lastArcaData = null;
	let lastArcaPayloadTree = null;

	function setArcaLoadingProveedor(isLoading) {
		const btn = byId('btn-consulta-arca-proveedor');
		const badge = byId('arca-loading-proveedor');
		if (badge) badge.style.display = isLoading ? 'inline-block' : 'none';
		if (btn) {
			btn.disabled = !!isLoading;
			btn.style.pointerEvents = isLoading ? 'none' : '';
			btn.style.opacity = isLoading ? '0.6' : '';
		}
	}

	function setArcaCuitModalLoading(isLoading) {
		const spin = byId('arca-cuit-entry-loading');
		const consultBtn = byId('arca-cuit-entry-consultar');
		const cancelBtn = byId('arca-cuit-entry-cancel');
		const closeBtn = byId('arca-cuit-entry-close');
		const inp = byId('arca-cuit-entry-input');
		const ov = byId('arca-cuit-entry-overlay');
		if (spin) {
			spin.classList.toggle('is-visible', !!isLoading);
			spin.setAttribute('aria-busy', isLoading ? 'true' : 'false');
		}
		[consultBtn, cancelBtn, closeBtn].forEach(function (b) {
			if (b) b.disabled = !!isLoading;
		});
		if (inp) inp.disabled = !!isLoading;
		if (ov) ov.style.pointerEvents = isLoading ? 'none' : '';
	}

	function openArcaPreview({ cuit, data }) {
		lastArcaData = { cuit, data };
		lastArcaPayloadTree = data || null;

		const df = data && data.domicilioFiscal ? data.domicilioFiscal : {};

		byId('arca-prev-cuit').textContent = cuit || '';
		byId('arca-prev-nombre').textContent = data && data.nombre ? data.nombre : '';
		byId('arca-prev-domicilio').textContent = df.texto || '';
		byId('arca-prev-cp').textContent = df.codPostal || '';
		byId('arca-prev-prov').textContent = df.provincia || '';
		byId('arca-prev-loc').textContent = df.localidad || '';

		const warns = [];
		if (df.provincia && !df.provincia_id) warns.push('No se pudo vincular la provincia con el maestro interno.');
		if (df.localidad && !df.localidad_id) warns.push('No se pudo vincular la localidad con el maestro interno.');

		const warnEl = byId('arca-prev-warn');
		if (warnEl) {
			if (warns.length) {
				warnEl.style.display = 'block';
				warnEl.textContent = warns.join(' ');
			} else {
				warnEl.style.display = 'none';
				warnEl.textContent = '';
			}
		}

		const overlay = byId('arca-preview-overlay');
		if (overlay) overlay.style.display = 'flex';
	}

	function closeArcaPreview() {
		const overlay = byId('arca-preview-overlay');
		if (overlay) overlay.style.display = 'none';
	}

	function closeArcaFullView() {
		const overlay = byId('arca-full-overlay');
		if (overlay) overlay.style.display = 'none';
	}

	function buildTreeDom(value, keyLabel, depth) {
		const maxAutoOpenDepth = 3;
		const wrap = document.createElement('div');
		wrap.className = 'arca-tree-node';

		if (value === null || value === undefined) {
			const line = document.createElement('div');
			line.className = 'arca-tree-line';
			line.style.paddingLeft = depth * 14 + 'px';
			line.innerHTML = '<span class="arca-tree-k">' + escapeHtml(keyLabel) + '</span>' +
				'<span class="arca-tree-v arca-tree-null">' + escapeHtml(String(value)) + '</span>';
			wrap.appendChild(line);
			return wrap;
		}

		const t = typeof value;
		if (t !== 'object') {
			const line = document.createElement('div');
			line.className = 'arca-tree-line';
			line.style.paddingLeft = depth * 14 + 'px';
			line.innerHTML = '<span class="arca-tree-k">' + escapeHtml(keyLabel) + '</span>' +
				'<span class="arca-tree-v">' + escapeHtml(String(value)) + '</span>';
			wrap.appendChild(line);
			return wrap;
		}

		if (Array.isArray(value)) {
			if (value.length === 0) {
				const line = document.createElement('div');
				line.className = 'arca-tree-line';
				line.style.paddingLeft = depth * 14 + 'px';
				line.innerHTML = '<span class="arca-tree-k">' + escapeHtml(keyLabel) + '</span>' +
					'<span class="arca-tree-v">[]</span>';
				wrap.appendChild(line);
				return wrap;
			}
			const det = document.createElement('details');
			det.open = depth < maxAutoOpenDepth;
			const sum = document.createElement('summary');
			sum.className = 'arca-tree-summary';
			sum.style.paddingLeft = depth * 14 + 'px';
			sum.textContent = keyLabel + '  [' + value.length + ']';
			det.appendChild(sum);
			value.forEach(function (item, idx) {
				const child = buildTreeDom(item, '[' + idx + ']', depth + 1);
				det.appendChild(child);
			});
			wrap.appendChild(det);
			return wrap;
		}

		const keys = Object.keys(value);
		if (keys.length === 0) {
			const line = document.createElement('div');
			line.className = 'arca-tree-line';
			line.style.paddingLeft = depth * 14 + 'px';
			line.innerHTML = '<span class="arca-tree-k">' + escapeHtml(keyLabel) + '</span>' +
				'<span class="arca-tree-v">{}</span>';
			wrap.appendChild(line);
			return wrap;
		}

		const det = document.createElement('details');
		det.open = depth < maxAutoOpenDepth;
		const sum = document.createElement('summary');
		sum.className = 'arca-tree-summary';
		sum.style.paddingLeft = depth * 14 + 'px';
		sum.textContent = keyLabel + '  {' + keys.length + '}';
		det.appendChild(sum);

		keys.sort();
		keys.forEach(function (k) {
			det.appendChild(buildTreeDom(value[k], k, depth + 1));
		});
		wrap.appendChild(det);
		return wrap;
	}

	function openArcaFullPayloadView() {
		const root = byId('arca-full-tree');
		const title = byId('arca-full-subtitle');
		if (!root) return;

		root.innerHTML = '';
		if (!lastArcaPayloadTree) {
			root.textContent = 'No hay datos cargados. Consultá el padrón primero.';
			if (title) title.textContent = '';
		} else {
			if (title) {
				const cuit = lastArcaData && lastArcaData.cuit ? lastArcaData.cuit : '';
				title.textContent = cuit ? 'Respuesta normalizada e incluye el objeto raw del web service (CUIT ' + cuit + ').' : '';
			}
			root.appendChild(buildTreeDom(lastArcaPayloadTree, 'respuesta', 0));
		}

		const overlay = byId('arca-full-overlay');
		if (overlay) overlay.style.display = 'flex';
	}

	function ensureSelectHasOption(selectId, value, label) {
		const sel = byId(selectId);
		if (!sel || value == null || value === '') return;
		const exists = sel.querySelector('option[value="' + cssEscape(value) + '"]');
		if (exists) return;
		const opt = document.createElement('option');
		opt.value = String(value);
		opt.textContent = label || String(value);
		sel.appendChild(opt);
	}

	async function aplicarDatosArcaEnFormulario({ cuit, data }) {
		const df = data.domicilioFiscal || {};

		if (data.nombre) setVal('nombre', data.nombre);
		if (df.texto) setVal('domicilio', df.texto);
		if (df.codPostal) setVal('codigopostal', df.codPostal);

		if (df.provincia_id) {
			setVal('provincia_id', df.provincia_id);
			triggerChange('provincia_id');
			const provSel = byId('provincia_id');
			const provText = provSel && provSel.selectedOptions && provSel.selectedOptions[0] ? provSel.selectedOptions[0].text : '';
			setVal('desc_provincia', df.provincia || provText);
		} else if (df.provincia) {
			setVal('desc_provincia', df.provincia);
		}

		if (df.localidad) {
			setVal('desc_localidad', df.localidad);
		}

		function sleep(ms) {
			return new Promise(function (resolve) {
				setTimeout(resolve, ms);
			});
		}

		async function esperarOptionLocalidad(timeoutMs, desiredValue) {
			const start = Date.now();
			const desired = desiredValue == null ? '' : String(desiredValue);
			while (Date.now() - start < timeoutMs) {
				const loc = byId('localidad_id');
				if (loc) {
					const opt = loc.querySelector('option[value="' + cssEscape(desired) + '"]');
					if (opt) return true;
				}
				await sleep(100);
			}
			return false;
		}

		if (df.localidad_id) {
			await esperarOptionLocalidad(7000, df.localidad_id);
			ensureSelectHasOption('localidad_id', df.localidad_id, df.localidad || df.localidad_id);
			setVal('localidad_id', df.localidad_id);
			triggerChange('localidad_id');
			if (!getVal('desc_localidad') && df.localidad) {
				setVal('desc_localidad', df.localidad);
			}
		}
	}

	/**
	 * @param {'tab'|'modal'} loadingUi — modal: aviso en overlay de CUIT (crear proveedor); tab: badge junto al ícono en datos impuestos
	 * @returns {Promise<boolean|'aborted'>} true si se abrió la previsualización; false si hubo error de respuesta/red; 'aborted' si falló validación previa (no hubo llamada al servidor)
	 */
	async function ejecutarConsultaArcaProveedor(loadingUi) {
		const endpoint = getArcaEndpointUrl();
		if (!endpoint) {
			alert('No está configurada la URL de consulta ARCA en el formulario.');
			return 'aborted';
		}

		const cuit = soloDigitos(getVal('nroinscripcion'));
		if (cuit.length !== 11) {
			alert('Ingresá una CUIT válida (11 dígitos).');
			return 'aborted';
		}

		const setLoading = loadingUi === 'modal' ? setArcaCuitModalLoading : setArcaLoadingProveedor;

		try {
			setLoading(true);
			const resp = await fetch(endpoint, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					Accept: 'application/json',
					'X-Requested-With': 'XMLHttpRequest',
					'X-CSRF-TOKEN': getCsrfToken(),
				},
				body: JSON.stringify({ cuit: cuit }),
			});

			const contentType = (resp.headers.get('content-type') || '').toLowerCase();
			if (!contentType.includes('application/json')) {
				const text = await resp.text();
				alert('Error consultando padrón ARCA. El servidor respondió un contenido inesperado (posible sesión vencida/CSRF).');
				console.error('Respuesta no-JSON ARCA:', resp.status, text);
				return false;
			}

			const json = await resp.json();
			if (!resp.ok || !json.ok) {
				alert(json.message || 'Error consultando padrón ARCA.');
				return false;
			}

			const data = json.data || {};
			openArcaPreview({ cuit: cuit, data: data });
			return true;
		} catch (err) {
			alert('Error de red consultando padrón ARCA.');
			return false;
		} finally {
			setLoading(false);
		}
	}

	async function handlerConsultaArcaProveedor(e) {
		if (e && typeof e.preventDefault === 'function') e.preventDefault();
		await ejecutarConsultaArcaProveedor('tab');
	}

	window.consultaArcaProveedor = handlerConsultaArcaProveedor;

	function openArcaCuitEntryOverlay() {
		setArcaCuitModalLoading(false);
		const inp = byId('arca-cuit-entry-input');
		const ov = byId('arca-cuit-entry-overlay');
		if (!ov) return;
		if (inp) {
			inp.value = getVal('nroinscripcion');
			if (typeof window.formatarCUIT === 'function') window.formatarCUIT(inp);
		}
		ov.style.display = 'flex';
		if (inp) setTimeout(function () { inp.focus(); inp.select && inp.select(); }, 50);
	}

	function closeArcaCuitEntryOverlay() {
		const ov = byId('arca-cuit-entry-overlay');
		if (ov) ov.style.display = 'none';
	}

	document.addEventListener('DOMContentLoaded', function () {
		const btn = byId('btn-consulta-arca-proveedor');
		if (btn) btn.addEventListener('click', handlerConsultaArcaProveedor);

		const crearBtn = byId('btn-consulta-arca-padron-crear');
		if (crearBtn) {
			crearBtn.addEventListener('click', function (e) {
				e.preventDefault();
				openArcaCuitEntryOverlay();
			});
		}

		function bindCuitEntryClose(el) {
			if (el) el.addEventListener('click', function () { closeArcaCuitEntryOverlay(); });
		}
		bindCuitEntryClose(byId('arca-cuit-entry-close'));
		bindCuitEntryClose(byId('arca-cuit-entry-cancel'));
		const cuitOv = byId('arca-cuit-entry-overlay');
		if (cuitOv) {
			cuitOv.addEventListener('click', function (ev) {
				if (ev.target && ev.target.id === 'arca-cuit-entry-overlay') closeArcaCuitEntryOverlay();
			});
		}
		const cuitGo = byId('arca-cuit-entry-consultar');
		async function runConsultaDesdeModalCuit() {
			const inp = byId('arca-cuit-entry-input');
			const raw = inp ? inp.value : '';
			const nro = byId('nroinscripcion');
			if (nro) {
				nro.value = raw;
				if (typeof window.formatarCUIT === 'function') window.formatarCUIT(nro);
			}
			const resultado = await ejecutarConsultaArcaProveedor('modal');
			if (resultado !== 'aborted') {
				closeArcaCuitEntryOverlay();
			}
		}
		if (cuitGo) {
			cuitGo.addEventListener('click', runConsultaDesdeModalCuit);
		}
		const cuitEntryInp = byId('arca-cuit-entry-input');
		if (cuitEntryInp) {
			cuitEntryInp.addEventListener('keydown', function (ev) {
				if (ev.key === 'Enter') {
					ev.preventDefault();
					runConsultaDesdeModalCuit();
				}
			});
		}

		byId('arca-preview-close') && byId('arca-preview-close').addEventListener('click', function () {
			closeArcaPreview();
		});
		byId('arca-preview-cancel') && byId('arca-preview-cancel').addEventListener('click', function () {
			closeArcaPreview();
		});
		byId('arca-preview-overlay') && byId('arca-preview-overlay').addEventListener('click', function (ev) {
			if (ev.target && ev.target.id === 'arca-preview-overlay') closeArcaPreview();
		});
		byId('arca-preview-apply') && byId('arca-preview-apply').addEventListener('click', async function () {
			if (!lastArcaData) return;
			try {
				await aplicarDatosArcaEnFormulario(lastArcaData);
			} finally {
				closeArcaPreview();
			}
		});

		const expandBtn = byId('arca-preview-expand-full');
		if (expandBtn) {
			expandBtn.addEventListener('click', function () {
				openArcaFullPayloadView();
			});
		}

		function bindFullClose(el) {
			if (el) el.addEventListener('click', function () { closeArcaFullView(); });
		}
		bindFullClose(byId('arca-full-close'));
		bindFullClose(byId('arca-full-close-foot'));
		byId('arca-full-overlay') && byId('arca-full-overlay').addEventListener('click', function (ev) {
			if (ev.target && ev.target.id === 'arca-full-overlay') closeArcaFullView();
		});
	});
})();
