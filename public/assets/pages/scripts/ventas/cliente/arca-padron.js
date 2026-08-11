/**
 * Consulta padrón ARCA (constancia de inscripción) para el alta/edición de clientes.
 * Requiere en la página: #cliente-arca-config[data-arca-constancia-url], meta csrf o input _token.
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

	function normalizeTipoDocLabel(s) {
		return (s || '')
			.toString()
			.replace(/\./g, '')
			.replace(/\s+/g, ' ')
			.trim()
			.toUpperCase();
	}

	/** Selecciona la opción CUIT en #tipodocumento_id (abreviatura en el texto de la opción). */
	function selectTipoDocumentoCuit() {
		const sel = byId('tipodocumento_id');
		if (!sel) return;
		const opts = sel.querySelectorAll('option');
		for (let i = 0; i < opts.length; i++) {
			const o = opts[i];
			if (!o.value) continue;
			const lab = normalizeTipoDocLabel(o.textContent);
			if (lab === 'CUIT' || lab.indexOf('CUIT') !== -1) {
				sel.value = o.value;
				triggerChange('tipodocumento_id');
				return;
			}
		}
	}

	function aplicarCuitNumerodocumento(cuit, options) {
		options = options || {};
		if (cuit == null || cuit === '') return;
		const nro = byId('numerodocumento');
		if (!nro) return;
		nro.value = String(cuit).replace(/\D+/g, '');
		if (typeof window.formatarCUIT === 'function') window.formatarCUIT(nro);
		triggerChange('numerodocumento');
		if (options.verificarDuplicado !== false) {
			verificarDocumentoDuplicado({ debounce: false });
		}
	}

	function getCsrfToken() {
		const meta = qs('meta[name="csrf-token"]');
		if (meta && meta.getAttribute('content')) return meta.getAttribute('content');
		const input = qs('input[name="_token"]');
		return input ? input.value : '';
	}

	function getArcaEndpointUrl() {
		const cfg = byId('cliente-arca-config');
		let u = cfg && cfg.getAttribute('data-arca-constancia-url');
		if (!u) {
			const any = document.querySelector('[data-arca-constancia-url]');
			u = any && any.getAttribute('data-arca-constancia-url');
		}
		return u ? String(u).trim() : '';
	}

	function soloDigitos(v) {
		return (v || '').toString().replace(/\D+/g, '');
	}

	function escapeHtml(str) {
		const d = document.createElement('div');
		d.textContent = str == null ? '' : String(str);
		return d.innerHTML;
	}

	let lastArcaData = null;
	let lastArcaPayloadTree = null;
	/** @type {{ request: string, response: string }|null} */
	let lastArcaSoap = null;

	function pickSoapFromJson(json) {
		if (!json) return null;
		if (json.soap && (json.soap.request || json.soap.response)) return json.soap;
		if (json.data && json.data.soap && (json.data.soap.request || json.data.soap.response)) {
			return json.data.soap;
		}
		return null;
	}

	function storeArcaSoap(soap) {
		lastArcaSoap = soap && (soap.request || soap.response) ? soap : null;
	}

	function dataForTreeView(data) {
		if (!data || typeof data !== 'object') return data;
		const copy = Object.assign({}, data);
		delete copy.soap;
		return copy;
	}

	function renderArcaSoapPanel() {
		const section = byId('arca-soap-section');
		const reqPre = byId('arca-soap-request');
		const resPre = byId('arca-soap-response');
		const reqEmpty = byId('arca-soap-request-empty');
		const resEmpty = byId('arca-soap-response-empty');
		if (!section) return;

		const req = lastArcaSoap && lastArcaSoap.request ? String(lastArcaSoap.request) : '';
		const res = lastArcaSoap && lastArcaSoap.response ? String(lastArcaSoap.response) : '';
		const hasSoap = !!(req || res);

		section.style.display = hasSoap ? 'block' : 'none';
		if (!hasSoap) return;

		if (reqPre) {
			reqPre.textContent = req;
			reqPre.style.display = req ? 'block' : 'none';
		}
		if (reqEmpty) reqEmpty.style.display = req ? 'none' : 'block';

		if (resPre) {
			resPre.textContent = res;
			resPre.style.display = res ? 'block' : 'none';
		}
		if (resEmpty) resEmpty.style.display = res ? 'none' : 'block';
	}

	async function copyTextToClipboard(text, okMsg) {
		const t = text == null ? '' : String(text);
		if (!t) {
			alert('No hay contenido para copiar.');
			return;
		}
		try {
			if (navigator.clipboard && navigator.clipboard.writeText) {
				await navigator.clipboard.writeText(t);
			} else {
				const ta = document.createElement('textarea');
				ta.value = t;
				ta.setAttribute('readonly', '');
				ta.style.position = 'fixed';
				ta.style.left = '-9999px';
				document.body.appendChild(ta);
				ta.select();
				document.execCommand('copy');
				document.body.removeChild(ta);
			}
			if (okMsg) alert(okMsg);
		} catch (e) {
			alert('No se pudo copiar al portapapeles.');
		}
	}

	function soapBothText() {
		if (!lastArcaSoap) return '';
		const parts = [];
		if (lastArcaSoap.request) {
			parts.push('=== SOAP REQUEST ===\n' + lastArcaSoap.request);
		}
		if (lastArcaSoap.response) {
			parts.push('=== SOAP RESPONSE ===\n' + lastArcaSoap.response);
		}
		return parts.join('\n\n');
	}

	function promptOpenSoapAfterError(message) {
		if (!lastArcaSoap) {
			alert(message || 'Error consultando padrón ARCA.');
			return;
		}
		const abrir = confirm(
			(message || 'Error consultando padrón ARCA.') +
				'\n\n¿Abrir el XML SOAP (request/response) para enviar a mesa de ayuda ARCA?'
		);
		if (abrir) {
			openArcaFullPayloadView();
		}
	}

	function setArcaLoadingCliente(isLoading) {
		const btn = byId('btn-consulta-arca-cliente');
		const badge = byId('arca-loading-cliente');
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
		storeArcaSoap(data && data.soap ? data.soap : null);
		lastArcaPayloadTree = dataForTreeView(data);

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
				title.textContent = cuit
					? 'Datos normalizados, raw del WS y XML SOAP de la última llamada (CUIT ' + cuit + ').'
					: '';
			}
			root.appendChild(buildTreeDom(lastArcaPayloadTree, 'respuesta', 0));
		}

		renderArcaSoapPanel();

		const overlay = byId('arca-full-overlay');
		if (overlay) overlay.style.display = 'flex';
	}

	async function aplicarDatosArcaEnFormulario({ cuit, data }) {
		selectTipoDocumentoCuit();
		aplicarCuitNumerodocumento(cuit, { verificarDuplicado: true });

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

		if (df.localidad_id) {
			setVal('localidad_id', df.localidad_id);
		}
		if (df.localidad) {
			setVal('desc_localidad', df.localidad);
			const nodes = document.querySelectorAll('#nombrelocalidad');
			if (nodes && nodes.length) {
				nodes.forEach(function (n) {
					if (n && 'value' in n) n.value = df.localidad;
				});
			}
		}
	}

	function arcaValidacionImpuestosHabilitada() {
		const cfg = byId('cliente-arca-config');
		return !!(cfg && cfg.getAttribute('data-arca-validar-impuestos') === '1');
	}

	function getCondicionivaIdForm() {
		const el = byId('condicioniva_id');
		if (!el || !el.value) return null;
		const n = parseInt(el.value, 10);
		return Number.isFinite(n) && n > 0 ? n : null;
	}

	function condicionivaRequiereValidacionArca(condicionivaId) {
		const cfg = byId('cliente-arca-config');
		if (!cfg || !condicionivaId) return false;
		const ri = parseInt(cfg.getAttribute('data-condicioniva-ri-id') || '1', 10);
		const mono = parseInt(cfg.getAttribute('data-condicioniva-monotributo-id') || '4', 10);
		const baja = parseInt(cfg.getAttribute('data-condicioniva-baja-id') || '7', 10);
		return condicionivaId === ri || condicionivaId === mono || condicionivaId === baja;
	}

	function mostrarAlertaArcaImpuestos(validacion) {
		const box = byId('arca-impuestos-alerta');
		const msg = byId('arca-impuestos-alerta-mensaje');
		const det = byId('arca-impuestos-alerta-detalles');
		if (!box || !msg) return;

		if (!validacion || validacion.ok || !validacion.aplica) {
			box.style.display = 'none';
			msg.textContent = '';
			if (det) {
				det.innerHTML = '';
				det.style.display = 'none';
			}
			if (typeof window.actualizarUiRegularizarCliente === 'function') {
				window.actualizarUiRegularizarCliente();
			}
			return;
		}

		box.style.display = 'block';
		msg.textContent = validacion.mensaje || 'Problemas en ARCA: el cliente no tiene impuestos activos.';
		if (det) {
			det.innerHTML = '';
			const detalles = validacion.detalles || [];
			if (detalles.length) {
				det.style.display = 'block';
				detalles.forEach(function (texto) {
					const li = document.createElement('li');
					li.textContent = texto;
					det.appendChild(li);
				});
			} else {
				det.style.display = 'none';
			}
		}
		if (typeof window.actualizarUiRegularizarCliente === 'function') {
			window.actualizarUiRegularizarCliente();
		}
	}

	function procesarValidacionImpuestosArca(validacion, json) {
		if (!validacion || !validacion.aplica) {
			mostrarAlertaArcaImpuestos({ ok: true, aplica: false });
			return;
		}
		mostrarAlertaArcaImpuestos(validacion);
	}

	function getArcaValidarClienteUrl() {
		const form = byId('form-general');
		const u = form && form.getAttribute('data-arca-validar-url');
		return u ? String(u).trim() : '';
	}

	function getVerificarDocumentoUrl() {
		const cfg = byId('cliente-arca-config');
		const u = cfg && cfg.getAttribute('data-verificar-documento-url');
		return u ? String(u).trim() : '';
	}

	function permiteCuitDuplicadoConfig() {
		const cfg = byId('cliente-arca-config');
		return !!(cfg && cfg.getAttribute('data-permitir-cuit-duplicado') === '1');
	}

	function getExcluirClienteIdDocumento() {
		const form = byId('form-general');
		if (!form) return 0;
		const id = parseInt(form.getAttribute('data-cliente-id') || '0', 10);
		return Number.isFinite(id) && id > 0 ? id : 0;
	}

	let lastVerificacionDocumentoToken = 0;
	let verificacionDocumentoTimer = null;
	let clienteDocumentoDuplicadoActivo = false;

	function ocultarAlertaDocumentoDuplicado() {
		const box = byId('cliente-cuit-duplicado-alerta');
		const msg = byId('cliente-cuit-duplicado-alerta-mensaje');
		const titulo = byId('cliente-cuit-duplicado-alerta-titulo');
		if (msg) msg.innerHTML = '';
		if (titulo) {
			titulo.innerHTML = '<i class="fa fa-exclamation-triangle"></i> CUIT ya registrado';
		}
		if (box) {
			box.classList.remove('alert-info');
			box.classList.add('alert-warning');
			box.style.display = 'none';
		}
		clienteDocumentoDuplicadoActivo = false;
	}

	function mostrarAlertaDocumentoDuplicado(cliente, options) {
		options = options || {};
		const box = byId('cliente-cuit-duplicado-alerta');
		const msg = byId('cliente-cuit-duplicado-alerta-mensaje');
		const titulo = byId('cliente-cuit-duplicado-alerta-titulo');
		if (!box || !msg || !cliente) return;

		const debeBloquear = typeof options.bloquear === 'boolean'
			? options.bloquear
			: !permiteCuitDuplicadoConfig();

		let html = escapeHtml(cliente.mensaje || 'El CUIT/documento ya está registrado en otro cliente.');
		if (cliente.url_consulta) {
			html += ' <a href="' + escapeHtml(cliente.url_consulta) + '" target="_blank" rel="noopener">Ver cliente existente</a>';
		}
		if (!debeBloquear) {
			html += ' <span class="d-block mt-1 text-muted">La configuración permite guardar CUIT duplicados.</span>';
		}
		msg.innerHTML = html;
		if (titulo) {
			titulo.innerHTML = debeBloquear
				? '<i class="fa fa-exclamation-triangle"></i> CUIT ya registrado'
				: '<i class="fa fa-info-circle"></i> CUIT ya registrado (permitido)';
		}
		box.classList.toggle('alert-warning', debeBloquear);
		box.classList.toggle('alert-info', !debeBloquear);
		box.style.display = 'block';
		clienteDocumentoDuplicadoActivo = debeBloquear;
		box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

		const tabLink = byId('tab-datos-facturacion-link');
		if (tabLink && typeof window.jQuery === 'function') {
			window.jQuery(tabLink).tab('show');
		}
	}

	async function verificarDocumentoDuplicado(options) {
		options = options || {};
		const url = getVerificarDocumentoUrl();
		if (!url) return;

		const digitos = soloDigitos(getVal('numerodocumento'));
		if (digitos.length !== 11) {
			ocultarAlertaDocumentoDuplicado();
			return;
		}

		const run = async function () {
			const token = ++lastVerificacionDocumentoToken;
			try {
				const resp = await fetch(url, {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'Content-Type': 'application/json',
						Accept: 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
						'X-CSRF-TOKEN': getCsrfToken(),
					},
					body: JSON.stringify({
						numerodocumento: digitos,
						excluir_cliente_id: getExcluirClienteIdDocumento(),
					}),
				});

				if (token !== lastVerificacionDocumentoToken) return;

				const contentType = (resp.headers.get('content-type') || '').toLowerCase();
				if (!contentType.includes('application/json')) {
					console.error('Verificación CUIT cliente: respuesta no JSON', resp.status);
					return;
				}

				const json = await resp.json();
				if (token !== lastVerificacionDocumentoToken) return;

				if (json.duplicado && json.cliente) {
					const bloquear = typeof json.bloquear === 'boolean'
						? json.bloquear
						: !permiteCuitDuplicadoConfig();
					mostrarAlertaDocumentoDuplicado(json.cliente, { bloquear: bloquear });
				} else {
					ocultarAlertaDocumentoDuplicado();
				}
			} catch (err) {
				console.error('Verificación CUIT cliente:', err);
			}
		};

		if (options.debounce === false) {
			if (verificacionDocumentoTimer) {
				clearTimeout(verificacionDocumentoTimer);
				verificacionDocumentoTimer = null;
			}
			await run();
			return;
		}

		if (verificacionDocumentoTimer) clearTimeout(verificacionDocumentoTimer);
		verificacionDocumentoTimer = setTimeout(run, 400);
	}

	window.verificarClienteDocumentoDuplicado = verificarDocumentoDuplicado;

	function bindVerificacionDocumentoCliente() {
		const nroDoc = byId('numerodocumento');
		if (!nroDoc || nroDoc.getAttribute('data-verificar-documento-bound') === '1') {
			return;
		}
		nroDoc.setAttribute('data-verificar-documento-bound', '1');

		function onInputDocumento() {
			const len = soloDigitos(nroDoc.value).length;
			if (len !== 11) {
				ocultarAlertaDocumentoDuplicado();
				return;
			}
			verificarDocumentoDuplicado();
		}

		nroDoc.addEventListener('change', function () {
			verificarDocumentoDuplicado({ debounce: false });
		});
		nroDoc.addEventListener('blur', function () {
			verificarDocumentoDuplicado({ debounce: false });
		});
		nroDoc.addEventListener('input', onInputDocumento);
	}

	async function consultarArcaConValidacionImpuestos(options) {
		options = options || {};
		if (!arcaValidacionImpuestosHabilitada()) return null;

		const condicionivaId = getCondicionivaIdForm();
		if (!condicionivaRequiereValidacionArca(condicionivaId)) {
			mostrarAlertaArcaImpuestos({ ok: true, aplica: false });
			return null;
		}

		const cuit = soloDigitos(getVal('numerodocumento'));
		if (cuit.length !== 11) return null;

		const validarUrl = getArcaValidarClienteUrl();
		const endpoint = validarUrl || getArcaEndpointUrl();
		if (!endpoint) return null;

		const setLoading = options.silent ? function () {} : setArcaLoadingCliente;

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
				body: JSON.stringify({ cuit: cuit, condicioniva_id: condicionivaId }),
			});

			const contentType = (resp.headers.get('content-type') || '').toLowerCase();
			if (!contentType.includes('application/json')) {
				console.error('Validación ARCA: respuesta no JSON', resp.status);
				return null;
			}

			const json = await resp.json();
			storeArcaSoap(pickSoapFromJson(json));
			if (json.validacion) {
				procesarValidacionImpuestosArca(json.validacion, json);
			} else if (!json.ok && json.message) {
				mostrarAlertaArcaImpuestos({
					aplica: true,
					ok: false,
					mensaje: json.message,
					detalles: [],
				});
			}

			return json;
		} catch (err) {
			if (!options.silent) alert('Error de red consultando padrón ARCA.');
			return null;
		} finally {
			setLoading(false);
		}
	}

	/**
	 * @param {'tab'|'modal'} loadingUi — modal: overlay de CUIT (alta cliente); tab: badge junto al ícono en datos facturación
	 * @returns {Promise<boolean|'aborted'>}
	 */
	async function ejecutarConsultaArcaCliente(loadingUi, options) {
		options = options || {};
		const endpoint = getArcaEndpointUrl();
		if (!endpoint) {
			alert('No está configurada la URL de consulta ARCA en el formulario.');
			return 'aborted';
		}

		const cuit = soloDigitos(options.cuit != null ? options.cuit : getVal('numerodocumento'));
		if (cuit.length !== 11) {
			alert('Ingresá una CUIT válida (11 dígitos).');
			return 'aborted';
		}

		const setLoading = loadingUi === 'modal' ? setArcaCuitModalLoading : setArcaLoadingCliente;
		const condicionivaId = getCondicionivaIdForm();
		const payload = { cuit: cuit };
		if (arcaValidacionImpuestosHabilitada() && condicionivaId) {
			payload.condicioniva_id = condicionivaId;
		}

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
				body: JSON.stringify(payload),
			});

			const contentType = (resp.headers.get('content-type') || '').toLowerCase();
			if (!contentType.includes('application/json')) {
				const text = await resp.text();
				alert('Error consultando padrón ARCA. El servidor respondió un contenido inesperado (posible sesión vencida/CSRF).');
				console.error('Respuesta no-JSON ARCA:', resp.status, text);
				return false;
			}

			const json = await resp.json();
			storeArcaSoap(pickSoapFromJson(json));

			if (json.validacion) {
				procesarValidacionImpuestosArca(json.validacion, json);
			}

			if (!resp.ok || !json.ok) {
				promptOpenSoapAfterError(json.message || 'Error consultando padrón ARCA.');
				return false;
			}

			const data = json.data || {};
			if (!data.soap && json.soap) data.soap = json.soap;
			if (!options.skipPreview) {
				openArcaPreview({ cuit: cuit, data: data });
			}
			return true;
		} catch (err) {
			alert('Error de red consultando padrón ARCA.');
			return false;
		} finally {
			setLoading(false);
		}
	}

	async function handlerConsultaArcaCliente(e) {
		if (e && typeof e.preventDefault === 'function') e.preventDefault();
		await ejecutarConsultaArcaCliente('tab');
	}

	window.consultaArcaCliente = handlerConsultaArcaCliente;

	function openArcaCuitEntryOverlay() {
		setArcaCuitModalLoading(false);
		const inp = byId('arca-cuit-entry-input');
		const ov = byId('arca-cuit-entry-overlay');
		if (!ov) return;
		if (inp) {
			inp.value = getVal('numerodocumento');
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
		const btn = byId('btn-consulta-arca-cliente');
		if (btn) btn.addEventListener('click', handlerConsultaArcaCliente);

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
			const cuit = soloDigitos(inp ? inp.value : '');
			const resultado = await ejecutarConsultaArcaCliente('modal', { cuit: cuit });
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

		const copyReq = byId('arca-soap-copy-request');
		if (copyReq) {
			copyReq.addEventListener('click', function () {
				copyTextToClipboard(lastArcaSoap && lastArcaSoap.request, 'Request SOAP copiado.');
			});
		}
		const copyRes = byId('arca-soap-copy-response');
		if (copyRes) {
			copyRes.addEventListener('click', function () {
				copyTextToClipboard(lastArcaSoap && lastArcaSoap.response, 'Response SOAP copiado.');
			});
		}
		const copyBoth = byId('arca-soap-copy-both');
		if (copyBoth) {
			copyBoth.addEventListener('click', function () {
				copyTextToClipboard(soapBothText(), 'Request y response SOAP copiados.');
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

		if (arcaValidacionImpuestosHabilitada() && !byId('cliente-arca-validacion-config')) {
			consultarArcaConValidacionImpuestos({ silent: true });
			const condIva = byId('condicioniva_id');
			if (condIva) {
				condIva.addEventListener('change', function () {
					consultarArcaConValidacionImpuestos({ silent: true });
				});
			}
			const nroDoc = byId('numerodocumento');
			if (nroDoc) {
				nroDoc.addEventListener('change', function () {
					consultarArcaConValidacionImpuestos({ silent: true });
				});
			}
		}

		bindVerificacionDocumentoCliente();
		const nroDocInicial = byId('numerodocumento');
		if (nroDocInicial && soloDigitos(nroDocInicial.value).length === 11) {
			verificarDocumentoDuplicado({ debounce: false });
		}

		const formGeneral = byId('form-general');
		if (formGeneral) {
			formGeneral.addEventListener('submit', function (ev) {
				if (!clienteDocumentoDuplicadoActivo) {
					return;
				}
				ev.preventDefault();
				ev.stopPropagation();
				const box = byId('cliente-cuit-duplicado-alerta');
				if (box) {
					box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
				}
				alert('El CUIT/documento ya está registrado en otro cliente. Corrija el número antes de guardar.');
			}, true);
		}
	});
})();
