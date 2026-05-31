/**
 * Consulta padrón ARCA (constancia de inscripción) para el alta/edición de clientes UIF.
 * Requiere: [data-arca-constancia-url], meta csrf o input _token, campos #cuit / #numerodocumento.
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

	function normalizarKeyArca(v) {
		v = String(v || '').toUpperCase().trim();
		v = (typeof v.normalize === 'function' ? v.normalize('NFD').replace(/[\u0300-\u036f]/g, '') : v);
		v = v.replace(/[^A-Z0-9 ]+/g, ' ');
		return v.replace(/\s+/g, ' ').trim();
	}

	function formatearCuitDesdeDigitos(cuit11) {
		const c = soloDigitos(cuit11);
		if (c.length !== 11) return c;
		return c.substring(0, 2) + '-' + c.substring(2, 10) + '-' + c.substring(10);
	}

	/** DNI de 8 dígitos: segmento central del CUIT (entre guiones XX-XXXXXXXX-X). */
	function numeroDocumentoDesdeCuit(cuit) {
		const digitos = soloDigitos(cuit);
		if (digitos.length !== 11) return '';
		return digitos.substring(2, 10);
	}

	function aplicarCuitEnCamposUif(cuit) {
		const digitos = soloDigitos(cuit);
		if (digitos.length !== 11) return;
		setVal('cuit', formatearCuitDesdeDigitos(digitos));
		selectTipoDocumentoDni();
		setVal('numerodocumento', numeroDocumentoDesdeCuit(digitos));
		triggerChange('numerodocumento');
	}

	function cuitConsultaDesdeFormularioUif() {
		const desdeCuit = soloDigitos(getVal('cuit'));
		if (desdeCuit.length === 11) return desdeCuit;
		return soloDigitos(getVal('numerodocumento'));
	}

	function ensureSelectHasOption(selectId, value, label) {
		const sel = byId(selectId);
		if (!sel || value == null || value === '') return;
		const exists = sel.querySelector('option[value="' + String(value).replace(/"/g, '\\"') + '"]');
		if (exists) return;
		const opt = document.createElement('option');
		opt.value = String(value);
		opt.textContent = label || String(value);
		sel.appendChild(opt);
	}

	/** Distancia de edición (para parecido de nombres). */
	function distanciaLevenshtein(a, b) {
		const s = String(a);
		const t = String(b);
		const n = s.length;
		const m = t.length;
		if (n === 0) return m;
		if (m === 0) return n;
		const d = [];
		for (let i = 0; i <= n; i++) d[i] = [i];
		for (let j = 0; j <= m; j++) d[0][j] = j;
		for (let i = 1; i <= n; i++) {
			for (let j = 1; j <= m; j++) {
				const costo = s.charAt(i - 1) === t.charAt(j - 1) ? 0 : 1;
				d[i][j] = Math.min(
					d[i - 1][j] + 1,
					d[i][j - 1] + 1,
					d[i - 1][j - 1] + costo
				);
			}
		}
		return d[n][m];
	}

	/**
	 * Puntuación 0–1 entre dos etiquetas (exacto, contiene, tokens, Levenshtein).
	 */
	function puntuacionParecidoTexto(busqueda, candidato) {
		const a = normalizarKeyArca(busqueda);
		const b = normalizarKeyArca(candidato);
		if (!a || !b) return 0;
		if (a === b) return 1;
		if (b.indexOf(a) !== -1 || a.indexOf(b) !== -1) return 0.92;

		const ta = a.split(' ').filter(function (t) { return t.length > 1; });
		const tb = b.split(' ').filter(function (t) { return t.length > 1; });
		let inter = 0;
		ta.forEach(function (t) {
			if (tb.indexOf(t) !== -1) inter++;
		});
		const union = new Set(ta.concat(tb)).size;
		const jaccard = union ? inter / union : 0;

		const maxLen = Math.max(a.length, b.length);
		const lev = maxLen ? 1 - distanciaLevenshtein(a, b) / maxLen : 0;

		return Math.max(jaccard, lev * 0.88);
	}

	/**
	 * Elige la mejor opción de un &lt;select&gt; por nombre (exacto o por parecido).
	 * @returns {{ id: string, label: string, score: number }|null}
	 */
	function seleccionarEnSelectPorParecido(selectId, nombreBuscado, minScore) {
		const sel = byId(selectId);
		if (!sel || !nombreBuscado) return null;
		const umbral = typeof minScore === 'number' ? minScore : 0.55;
		const clave = normalizarKeyArca(nombreBuscado);
		let mejor = null;
		let mejorScore = 0;

		for (let i = 0; i < sel.options.length; i++) {
			const o = sel.options[i];
			if (!o.value) continue;
			const label = (o.textContent || '').trim();
			if (normalizarKeyArca(label) === clave) {
				sel.value = o.value;
				triggerChange(selectId);
				return { id: o.value, label: label, score: 1 };
			}
			const score = puntuacionParecidoTexto(nombreBuscado, label);
			if (score > mejorScore) {
				mejorScore = score;
				mejor = { id: o.value, label: label, score: score };
			}
		}

		if (mejor && mejorScore >= umbral) {
			sel.value = mejor.id;
			triggerChange(selectId);
			return mejor;
		}
		return null;
	}

	function expandirAliasProvinciaArca(nombreProvincia) {
		let key = normalizarKeyArca(nombreProvincia);
		const aliases = {
			CABA: 'CIUDAD AUTONOMA DE BUENOS AIRES',
			'CAPITAL FEDERAL': 'CIUDAD AUTONOMA DE BUENOS AIRES',
			'CIUDAD DE BUENOS AIRES': 'CIUDAD AUTONOMA DE BUENOS AIRES',
			'BS AS': 'BUENOS AIRES',
			'BUENOS AIRES': 'BUENOS AIRES',
		};
		if (aliases[key]) key = normalizarKeyArca(aliases[key]);
		return key;
	}

	function seleccionarPaisResidenciaUifDesdeArca(df) {
		const candidatos = [
			df && df.pais,
			'ARGENTINA',
			'REPUBLICA ARGENTINA',
		].filter(Boolean);
		for (let i = 0; i < candidatos.length; i++) {
			const r = seleccionarEnSelectPorParecido('pais_uif_id', candidatos[i], 0.45);
			if (r) return r;
		}
		return null;
	}

	function seleccionarProvinciaResidenciaUifPorNombre(nombreProvincia) {
		if (!nombreProvincia) return null;
		const alias = expandirAliasProvinciaArca(nombreProvincia);
		const sel = byId('provincia_uif_id');
		if (!sel) return null;

		for (let i = 0; i < sel.options.length; i++) {
			const o = sel.options[i];
			if (!o.value) continue;
			if (normalizarKeyArca(o.textContent) === alias) {
				sel.value = o.value;
				triggerChange('provincia_uif_id');
				setVal('desc_provincia_uif', o.textContent.trim());
				return { id: o.value, label: o.textContent.trim(), score: 1 };
			}
		}

		const r = seleccionarEnSelectPorParecido('provincia_uif_id', nombreProvincia, 0.52);
		if (r) {
			setVal('desc_provincia_uif', r.label);
			return r;
		}
		setVal('desc_provincia_uif', nombreProvincia);
		return null;
	}

	function sleep(ms) {
		return new Promise(function (resolve) {
			setTimeout(resolve, ms);
		});
	}

	/** Espera a que el select de localidad tenga opciones tras cambiar provincia. */
	async function esperarOpcionesLocalidadUifCargadas(timeoutMs) {
		const limite = timeoutMs || 8000;
		const inicio = Date.now();
		while (Date.now() - inicio < limite) {
			const loc = byId('localidad_uif_id');
			if (loc && loc.options.length > 1) return true;
			await sleep(150);
		}
		return false;
	}

	function limpiarNombreLocalidadArca(nombre) {
		return String(nombre || '')
			.replace(/\s*\([^)]*\)\s*/g, ' ')
			.replace(/\s+/g, ' ')
			.trim();
	}

	async function seleccionarLocalidadResidenciaUifPorNombre(nombreLocalidad) {
		if (!nombreLocalidad) return null;
		const limpio = limpiarNombreLocalidadArca(nombreLocalidad);
		const variantes = [limpio, nombreLocalidad];
		if (limpio.indexOf(' ') !== -1) {
			variantes.push(limpio.split(' ')[0]);
		}

		for (let v = 0; v < variantes.length; v++) {
			const r = seleccionarEnSelectPorParecido('localidad_uif_id', variantes[v], 0.48);
			if (r) {
				setVal('desc_localidad_uif', r.label);
				setVal('localidad_uif_id_previa', r.id);
				return r;
			}
		}
		setVal('desc_localidad_uif', limpio);
		return null;
	}

	function normalizarTextoDireccionArca(direccion) {
		return String(direccion || '')
			.replace(/[°º]/gi, '')
			.replace(/\s+/g, ' ')
			.trim();
	}

	function limpiarRestoDireccionArca(texto) {
		return String(texto || '')
			.replace(/\s*,\s*/g, ' ')
			.replace(/\s*;\s*/g, ' ')
			.replace(/\s+/g, ' ')
			.trim();
	}

	function quitarCoincidenciaDireccionArca(texto, coincidencia) {
		if (!coincidencia || !coincidencia[0]) return texto;
		return limpiarRestoDireccionArca(texto.replace(coincidencia[0], ' '));
	}

	/**
	 * Solo calle/número: prioriza domicilioFiscal.direccion; si viene texto compuesto, toma el tramo antes del primer " - ".
	 */
	function direccionCalleArcaDesdeDf(df) {
		if (!df) return '';
		if (df.direccion) return String(df.direccion).trim();
		const texto = df.texto ? String(df.texto).trim() : '';
		if (!texto) return '';
		const sep = texto.indexOf(' - ');
		return sep !== -1 ? texto.substring(0, sep).trim() : texto;
	}

	/**
	 * Separa calle/número, piso y departamento desde la dirección ARCA.
	 */
	function parsearDomicilioArca(direccion) {
		let calle = normalizarTextoDireccionArca(direccion);
		let piso = '';
		let departamento = '';

		if (!calle) {
			return { domicilio: '', piso: '', departamento: '' };
		}

		const rePiso = /\b(?:PISO|PLANTA|PI\.?|P\.)\s*:?\s*([0-9]{1,3}[A-Z]?|PB|S\/N)\b/i;
		const mPiso = rePiso.exec(calle);
		if (mPiso) {
			piso = String(mPiso[1]).toUpperCase();
			calle = quitarCoincidenciaDireccionArca(calle, mPiso);
		}

		if (!piso) {
			const mPb = /\b(?:PB|P\.?\s*B\.?|PLANTA\s+BAJA)\b/i.exec(calle);
			if (mPb) {
				piso = 'PB';
				calle = quitarCoincidenciaDireccionArca(calle, mPb);
			}
		}

		const reDepto = /\b(?:DEPTO?\.?|DEPARTAMENTO|DEP\.?|DTO\.?|DPTO?\.?|DPT\.?|UNIDAD|UN\.?)\s*:?\s*([0-9A-Z]{1,6})\b/i;
		const mDepto = reDepto.exec(calle);
		if (mDepto) {
			departamento = String(mDepto[1]).toUpperCase();
			calle = quitarCoincidenciaDireccionArca(calle, mDepto);
		}

		// Ej.: "ALBERDI 2367 P 14 D A"
		if (!piso || !departamento) {
			const mPd = calle.match(/^(.+?)\s+P\.?\s*([0-9]{1,3}|PB)\s+D\.?\s*([0-9A-Z]{1,6})\s*$/i);
			if (mPd && mPd[1].match(/\d/)) {
				if (!piso) piso = String(mPd[2]).toUpperCase();
				if (!departamento) departamento = String(mPd[3]).toUpperCase();
				calle = mPd[1].trim();
			}
		}

		// Ej.: "ALBERDI 2367, 14, A" o "ALBERDI 2367 14 A"
		if (!piso || !departamento) {
			const mTrail = calle.match(/^(.+?)(?:,|\s)+(\d{1,3})\s*,?\s*([A-Z0-9]{1,6})\s*$/i);
			if (mTrail && mTrail[1].match(/\d/)) {
				if (!piso) piso = mTrail[2];
				if (!departamento) departamento = String(mTrail[3]).toUpperCase();
				calle = mTrail[1].trim();
			}
		}

		calle = limpiarRestoDireccionArca(calle);

		return { domicilio: calle, piso: piso, departamento: departamento };
	}

	function aplicarDomicilioArcaEnCamposUif(direccion) {
		const partes = parsearDomicilioArca(direccion);
		setVal('domicilio', partes.domicilio || '');
		setVal('piso', partes.piso || '');
		setVal('departamento', partes.departamento || '');
		return partes;
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
		const dirPreview = direccionCalleArcaDesdeDf(df);
		if (dirPreview) {
			const p = parsearDomicilioArca(dirPreview);
			if (p.piso || p.departamento) {
				warns.push('La dirección ARCA incluye piso/depto: al aplicar se completarán domicilio, piso y departamento por separado.');
			}
		}
		if (df.provincia || df.localidad) {
			warns.push('País, provincia y localidad de residencia se buscarán por parecido de nombre en el maestro UIF.');
		}

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

	/**
	 * Nombres propios frecuentes (AR). Solo se usan si no hay CUIT/CUIL válido de 11 dígitos
	 * o si el prefijo no es de persona física (20/23/27/24).
	 */
	const NOMBRES_FEMENINOS_AR = new Set([
		'ABIGAIL', 'ADELA', 'ADELAIDA', 'ADELINA', 'ADRIANA', 'AGUSTINA', 'AIDA', 'ALBA', 'ALICIA',
		'ALMA', 'AMALIA', 'AMANDA', 'ANA', 'ANABEL', 'ANDREA', 'ANGELA', 'ANGELICA', 'ANTONIA',
		'ARACELI', 'ASHLEY', 'AURORA', 'BARBARA', 'BEATRIZ', 'BELEN', 'BIANCA', 'BRENDA', 'BRISA',
		'CAMILA', 'CANDELA', 'CARINA', 'CARLA', 'CARMEN', 'CAROLINA', 'CATALINA', 'CECILIA',
		'CELIA', 'CELINA', 'CLARA', 'CLARISA', 'CLAUDIA', 'CONCEPCION', 'CONSUELO', 'CRISTINA',
		'DAIANA', 'DANIELA', 'DEBORA', 'DELIA', 'DIANA', 'DINA', 'DOLORES', 'DORA', 'DORIS',
		'EDITH', 'ELENA', 'ELISA', 'ELIZABETH', 'ELVIRA', 'EMILIA', 'EMMA', 'ERICA', 'ESMERALDA',
		'ESPERANZA', 'ESTEFANIA', 'ESTELA', 'ESTHER', 'EUGENIA', 'EVA', 'FABIANA', 'FATIMA',
		'FLORENCIA', 'FRANCISCA', 'GABRIELA', 'GLADYS', 'GLORIA', 'GRACIELA', 'GRISELDA', 'GUADALUPE',
		'HELENA', 'HERMINDA', 'HILDA', 'INES', 'IRENE', 'IRMA', 'ISABEL', 'JANET', 'JAZMIN',
		'JIMENA', 'JOANA', 'JORGELINA', 'JOSEFINA', 'JUANA', 'JULIA', 'JULIANA',
		'JULIETA', 'JUSTINA', 'KAREN', 'KARINA', 'KARLA', 'LAURA', 'LEONOR', 'LETICIA', 'LIDIA',
		'LILIANA', 'LILIAN', 'LORENA', 'LORETA', 'LORETO', 'LOURDES', 'LUCIA', 'LUCIANA', 'LUCILA',
		'LUDMILA', 'LUISA', 'LUZ', 'MABEL', 'MAGDALENA', 'MALENA', 'MANUELA', 'MARCELA', 'MARGARITA',
		'MARIA', 'MARIANA', 'MARIEL', 'MARIELA', 'MARINA', 'MARTA', 'MATILDE', 'MAYRA', 'MELINA',
		'MELISA', 'MERCEDES', 'MICAELA', 'MICHELLE', 'MIRIAM', 'MIRTA', 'MONICA', 'NADIA', 'NATALIA',
		'NATALI', 'NELIDA', 'NIDIA', 'NINA', 'NOELIA', 'NORA', 'NORMA', 'OLGA', 'OLIVIA',
		'ORNELLA', 'PALOMA', 'PAMELA', 'PAOLA', 'PATRICIA', 'PAULA', 'PIA', 'PILAR', 'PRISCILA',
		'RAQUEL', 'REBECA', 'REGINA', 'RENATA', 'RITA', 'ROCIO', 'ROMINA', 'ROSA', 'ROSANA',
		'ROSARIO', 'ROXANA', 'RUTH', 'SABRINA', 'SANDRA', 'SARA', 'SILVIA', 'SILVINA',
		'SIMONA', 'SOFIA', 'SOLEDAD', 'SONIA', 'SORAYA', 'STELLA', 'STEPHANIE', 'SUSANA', 'TAMARA',
		'TANIA', 'TATIANA', 'TERESA', 'VALENTINA', 'VALERIA', 'VANESA', 'VANESSA', 'VERONICA',
		'VICTORIA', 'VIOLETA', 'VIRGINIA', 'VIVIANA', 'WANDA', 'XIMENA', 'YANINA', 'YASMIN', 'YESICA',
		'YOLANDA', 'ZOE', 'ZULEMA', 'ZULMA',
	]);

	const NOMBRES_MASCULINOS_AR = new Set([
		'ABEL', 'ABRAHAM', 'ADAN', 'ADOLFO', 'ADRIAN', 'AGUSTIN', 'ALAN', 'ALBERTO', 'ALDO', 'ALEJANDRO',
		'ALEX', 'ALEXIS', 'ALFONSO', 'ALFREDO', 'ALONSO', 'AMADEO', 'ANDRES', 'ANGEL', 'ANTONIO', 'ARIEL',
		'ARMANDO', 'ARNOLDO', 'ARTURO', 'AXEL', 'BENJAMIN', 'BERNARDO', 'BRAIAN', 'BRIAN', 'BRUNO',
		'CARLOS', 'CESAR', 'CHRISTIAN', 'CIRO', 'CLAUDIO', 'CRISTIAN', 'CRISTOBAL', 'DAMIAN', 'DANIEL',
		'DARIO', 'DAVID', 'DIEGO', 'DIONISIO', 'DOMINGO', 'EDGARDO', 'EDUARDO', 'ELIAS', 'EMANUEL',
		'EMILIANO', 'EMILIO', 'ENRIQUE', 'ERNESTO', 'ESTEBAN', 'EUGENIO', 'EZEQUIEL', 'FABIAN', 'FACUNDO',
		'FEDERICO', 'FELIPE', 'FELIX', 'FERNANDO', 'FLAVIO', 'FRANCISCO', 'FRANCO', 'GABRIEL', 'GASTON',
		'GAEL', 'GERARDO', 'GERMAN', 'GONZALO', 'GREGORIO', 'GUILLERMO', 'GUSTAVO', 'HECTOR', 'HERNAN',
		'HORACIO', 'HUGO', 'HUMBERTO', 'IGNACIO', 'ISMAEL', 'IVAN', 'JAVIER', 'JEREMIAS', 'JOAQUIN',
		'JOEL', 'JONATHAN', 'JORDAN', 'JORGELITO', 'JORGE', 'JOSE', 'JOSUE', 'JUAN', 'JULIAN', 'JULIO',
		'JUSTO', 'LAUTARO', 'LEANDRO', 'LEONARDO', 'LORENZO', 'LUCAS', 'LUCIANO', 'LUIS', 'MANUEL',
		'MARCELO', 'MARCO', 'MARCOS', 'MARIO', 'MARTIN', 'MATIAS', 'MAURICIO', 'MAXIMILIANO', 'MIGUEL',
		'MILTON', 'MOISES', 'NAHUEL', 'NELSON', 'NESTOR', 'NICOLAS', 'NOE', 'OCTAVIO', 'OMAR', 'ORLANDO',
		'OSCAR', 'PABLO', 'PANCHO', 'PASCUAL', 'PATRICIO', 'PEDRO', 'RAFAEL', 'RAMIRO', 'RAMON', 'RAUL',
		'RENATO', 'RENZO', 'RICARDO', 'ROBERTO', 'RODOLFO', 'RODRIGO', 'ROGELIO', 'ROLANDO', 'ROMAN',
		'RUBEN', 'SALVADOR', 'SAMUEL', 'SANTIAGO', 'SAUL', 'SEBASTIAN', 'SERGIO', 'SILVESTRE', 'SIMON',
		'TADEO', 'THIAGO', 'TOMAS', 'TRISTAN', 'ULISES', 'VALENTIN', 'VICENTE', 'VICTOR', 'WALTER',
		'WILFREDO', 'XAVIER', 'YAMIL', 'YERKO', 'YONATHAN',
	]);

	/** Nombres masculinos que terminan en A (excepción a la regla -A → femenino). */
	const NOMBRES_MASCULINOS_TERMINAN_A = new Set([
		'AHMED', 'BERNABA', 'IMA', 'JOSHUA', 'JUDA', 'LUCA', 'MICA', 'MUSTAFA', 'NICOLA', 'NOA',
		'SIMEA', 'YEHUDA',
	]);

	/** Prefijos CUIT/CUIL de persona jurídica u otro (no inferir sexo por nombre si hay CUIT válido). */
	const PREFIJOS_CUIT_SIN_SEXO = new Set(['30', '33', '34']);

	function esPersonaJuridicaArca(data) {
		if (!data) return false;
		const tipo = String(data.tipoPersona || '').toUpperCase();
		if (tipo.indexOf('JURIDICA') !== -1) return true;
		return !!(data.razonSocial && !data.nombrePersona);
	}

	function digitosCuitCuil(opts) {
		const directo = soloDigitos(opts && opts.cuit);
		if (directo.length === 11) return directo;
		const alterno = soloDigitos(opts && opts.cuitAlterno);
		if (alterno.length === 11) return alterno;
		return '';
	}

	/** Prefijo CUIT/CUIL AFIP persona física: 20/23 masculino, 27/24 femenino. */
	function sexoDesdePrefijoCuit(cuit) {
		const digitos = soloDigitos(cuit);
		if (digitos.length !== 11) return '';
		const pref = digitos.substring(0, 2);
		if (pref === '27' || pref === '24') return 'FEMENINO';
		if (pref === '20' || pref === '23') return 'MASCULINO';
		return '';
	}

	function tokensNombrePropio(texto) {
		return normalizarKeyArca(texto).split(' ').filter(function (t) {
			return t.length > 1;
		});
	}

	/** Tokens candidatos a nombre propio (ARCA: apellido + nombre; también "NOMBRE APELLIDO"). */
	function tokensCandidatosNombrePropio(data, nombreCompleto) {
		const candidatos = [];
		if (data && data.nombrePersona) {
			tokensNombrePropio(data.nombrePersona).forEach(function (t) {
				candidatos.push(t);
			});
		}
		const full = String(nombreCompleto || (data && data.nombre) || '').trim();
		if (!full) return candidatos;
		const tokens = tokensNombrePropio(full);
		if (tokens.length === 0) return candidatos;
		if (tokens.length === 1) {
			candidatos.push(tokens[0]);
			return candidatos;
		}
		candidatos.push(tokens[tokens.length - 1]);
		candidatos.push(tokens[0]);
		for (let i = 1; i < tokens.length - 1; i++) {
			candidatos.push(tokens[i]);
		}
		return candidatos;
	}

	function sexoDesdeUnNombrePropio(nombre) {
		const n = String(nombre || '').trim();
		if (!n) return '';
		if (NOMBRES_FEMENINOS_AR.has(n)) return 'FEMENINO';
		if (NOMBRES_MASCULINOS_AR.has(n)) return 'MASCULINO';
		if (n.length > 3 && n.endsWith('A') && !NOMBRES_MASCULINOS_TERMINAN_A.has(n)) {
			return 'FEMENINO';
		}
		if (n.length > 3 && (n.endsWith('O') || n.endsWith('OS') || n.endsWith('OR'))) {
			return 'MASCULINO';
		}
		if (n.length > 4 && (n.endsWith('IA') || n.endsWith('INA') || n.endsWith('ANA') || n.endsWith('ELA'))) {
			return 'FEMENINO';
		}
		return '';
	}

	function mapaAprendizajeSexoUif() {
		const m = window.UIF_SEXO_APRENDIZAJE;
		return m && typeof m === 'object' ? m : {};
	}

	/** Sexo aprendido de clientes UIF ya cargados (token → MASCULINO/FEMENINO). */
	function sexoDesdeAprendizaje(data, nombreCompleto) {
		const mapa = mapaAprendizajeSexoUif();
		const keys = Object.keys(mapa);
		if (!keys.length) return '';

		const vistos = new Set();
		const tokens = tokensCandidatosNombrePropio(data, nombreCompleto);
		for (let i = 0; i < tokens.length; i++) {
			const t = tokens[i];
			if (vistos.has(t)) continue;
			vistos.add(t);
			if (mapa[t] === 'MASCULINO' || mapa[t] === 'FEMENINO') {
				return mapa[t];
			}
		}
		return '';
	}

	function sexoDesdeNombrePropio(data, nombreCompleto) {
		const vistos = new Set();
		const tokens = tokensCandidatosNombrePropio(data, nombreCompleto);
		for (let i = 0; i < tokens.length; i++) {
			const t = tokens[i];
			if (vistos.has(t)) continue;
			vistos.add(t);
			const sexo = sexoDesdeUnNombrePropio(t);
			if (sexo) return sexo;
		}
		return '';
	}

	/**
	 * 1) CUIT/CUIL 11 dígitos → prefijo 20/23/27/24.
	 * 2) Sin CUIT → aprendizaje (clientes UIF) → listas y reglas de nombre.
	 */
	function inferirSexoUif(nombreCompleto, opts) {
		opts = opts || {};
		if (esPersonaJuridicaArca(opts)) return '';

		const digitos = digitosCuitCuil(opts);
		if (digitos.length === 11) {
			const pref = digitos.substring(0, 2);
			if (PREFIJOS_CUIT_SIN_SEXO.has(pref)) return '';
			const desdePrefijo = sexoDesdePrefijoCuit(digitos);
			if (desdePrefijo) return desdePrefijo;
			return '';
		}

		const desdeAprendizaje = sexoDesdeAprendizaje(opts, nombreCompleto);
		if (desdeAprendizaje) return desdeAprendizaje;

		return sexoDesdeNombrePropio(opts, nombreCompleto);
	}

	function aplicarSexoEnFormularioUif(sexo) {
		if (!sexo) return;
		const sel = byId('sexo');
		if (!sel) return;
		for (let i = 0; i < sel.options.length; i++) {
			if (sel.options[i].value === sexo) {
				sel.value = sexo;
				triggerChange('sexo');
				return;
			}
		}
	}

	function aplicarSexoInferidoUif(cuit, data, nombreCompleto) {
		const sexo = inferirSexoUif(nombreCompleto, Object.assign({}, data || {}, { cuit: cuit }));
		aplicarSexoEnFormularioUif(sexo);
	}

	/** Expuesto para crear.js (blur de nombre / CUIT si el sexo sigue vacío). */
	window.inferirSexoUifDesdeNombre = inferirSexoUif;

	function selectTipoDocumentoDni() {
		const sel = byId('tipodocumento_id');
		if (!sel) return;
		for (let i = 0; i < sel.options.length; i++) {
			const o = sel.options[i];
			if (!o.value) continue;
			const lab = (o.textContent || '').replace(/\./g, '').replace(/\s+/g, ' ').trim().toUpperCase();
			if (lab === 'DNI' || lab.indexOf('DNI') !== -1) {
				sel.value = o.value;
				triggerChange('tipodocumento_id');
				return;
			}
		}
	}

	async function aplicarDatosArcaEnFormulario({ cuit, data }) {
		aplicarCuitEnCamposUif(cuit);

		const df = data.domicilioFiscal || {};

		if (data.nombre) setVal('nombre', data.nombre);
		aplicarSexoInferidoUif(cuit, data, data.nombre);

		const dirArca = direccionCalleArcaDesdeDf(df);
		if (dirArca) aplicarDomicilioArcaEnCamposUif(dirArca);
		if (df.codPostal) setVal('codigopostal', df.codPostal);

		seleccionarPaisResidenciaUifDesdeArca(df);

		const provMatch = seleccionarProvinciaResidenciaUifPorNombre(df.provincia);
		if (provMatch && df.localidad) {
			await esperarOpcionesLocalidadUifCargadas(8000);
			await seleccionarLocalidadResidenciaUifPorNombre(df.localidad);
		} else if (df.localidad) {
			setVal('desc_localidad_uif', limpiarNombreLocalidadArca(df.localidad));
		}
	}

	/**
	 * @param {'tab'|'modal'} loadingUi — modal: overlay de CUIT (alta cliente); tab: badge junto al ícono en datos facturación
	 * @returns {Promise<boolean|'aborted'>}
	 */
	async function ejecutarConsultaArcaClienteUif(loadingUi) {
		const endpoint = getArcaEndpointUrl();
		if (!endpoint) {
			alert('No está configurada la URL de consulta ARCA en el formulario.');
			return 'aborted';
		}

		const cuit = cuitConsultaDesdeFormularioUif();
		if (cuit.length !== 11) {
			alert('Ingresá una CUIT válida (11 dígitos) en el campo CUIT o en el número de documento.');
			return 'aborted';
		}

		const setLoading = loadingUi === 'modal' ? setArcaCuitModalLoading : setArcaLoadingCliente;

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
			storeArcaSoap(pickSoapFromJson(json));

			if (!resp.ok || !json.ok) {
				promptOpenSoapAfterError(json.message || 'Error consultando padrón ARCA.');
				return false;
			}

			const data = json.data || {};
			if (!data.soap && json.soap) data.soap = json.soap;
			openArcaPreview({ cuit: cuit, data: data });
			return true;
		} catch (err) {
			alert('Error de red consultando padrón ARCA.');
			return false;
		} finally {
			setLoading(false);
		}
	}

	async function handlerConsultaArcaClienteUif(e) {
		if (e && typeof e.preventDefault === 'function') e.preventDefault();
		await ejecutarConsultaArcaClienteUif('tab');
	}

	window.consultaArcaClienteUif = handlerConsultaArcaClienteUif;

	function openArcaCuitEntryOverlay() {
		setArcaCuitModalLoading(false);
		const inp = byId('arca-cuit-entry-input');
		const ov = byId('arca-cuit-entry-overlay');
		if (!ov) return;
		if (inp) {
			const cuitFmt = getVal('cuit') || formatearCuitDesdeDigitos(cuitConsultaDesdeFormularioUif());
			inp.value = cuitFmt;
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
		const btn = byId('btn-consulta-arca-cliente-uif');
		if (btn) btn.addEventListener('click', handlerConsultaArcaClienteUif);

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
			aplicarCuitEnCamposUif(raw);
			const resultado = await ejecutarConsultaArcaClienteUif('modal');
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
	});
})();
