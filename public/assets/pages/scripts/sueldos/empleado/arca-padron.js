/**
 * Consulta padrón ARCA (constancia de inscripción) para el alta/edición de empleados.
 * Mismo flujo que proveedores/clientes: modal de ingreso de CUIT → previsualización → aplicar al formulario.
 * Reutiliza los modales compartidos: includes/compras/proveedor/arca-cuit-entry-modal y arca-padron-modals.
 * Requiere en la página: #tab2[data-arca-constancia-url] y meta csrf / input _token.
 */
(function () {
    function qs(sel) { return document.querySelector(sel); }
    function byId(id) { return document.getElementById(id); }
    function getVal(id) { const el = byId(id); return el ? el.value : ''; }
    function setVal(id, value) { const el = byId(id); if (el) el.value = value == null ? '' : value; }
    function soloDigitos(v) { return (v || '').toString().replace(/\D+/g, ''); }

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
            const any = qs('[data-arca-constancia-url]');
            u = any && any.getAttribute('data-arca-constancia-url');
        }
        return u ? String(u).trim() : '';
    }

    function escapeHtml(str) {
        const d = document.createElement('div');
        d.textContent = str == null ? '' : String(str);
        return d.innerHTML;
    }

    let lastArcaData = null;
    let lastArcaPayloadTree = null;
    let lastArcaSoap = null;

    function pickSoapFromJson(json) {
        if (!json) return null;
        if (json.soap && (json.soap.request || json.soap.response)) return json.soap;
        if (json.data && json.data.soap && (json.data.soap.request || json.data.soap.response)) return json.data.soap;
        return null;
    }

    function storeArcaSoap(soap) { lastArcaSoap = soap && (soap.request || soap.response) ? soap : null; }

    function dataForTreeView(data) {
        if (!data || typeof data !== 'object') return data;
        const copy = Object.assign({}, data);
        delete copy.soap;
        return copy;
    }

    // ---------- Loading UI ----------
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
        [consultBtn, cancelBtn, closeBtn].forEach(function (b) { if (b) b.disabled = !!isLoading; });
        if (inp) inp.disabled = !!isLoading;
        if (ov) ov.style.pointerEvents = isLoading ? 'none' : '';
    }

    // ---------- Preview ----------
    function openArcaPreview(payload) {
        const cuit = payload.cuit;
        const data = payload.data || {};
        lastArcaData = { cuit: cuit, data: data };
        storeArcaSoap(data && data.soap ? data.soap : null);
        lastArcaPayloadTree = dataForTreeView(data);

        const df = data && data.domicilioFiscal ? data.domicilioFiscal : {};
        if (byId('arca-prev-cuit')) byId('arca-prev-cuit').textContent = cuit || '';
        if (byId('arca-prev-nombre')) byId('arca-prev-nombre').textContent = data && data.nombre ? data.nombre : '';
        if (byId('arca-prev-domicilio')) byId('arca-prev-domicilio').textContent = df.texto || '';
        if (byId('arca-prev-cp')) byId('arca-prev-cp').textContent = df.codPostal || '';
        if (byId('arca-prev-prov')) byId('arca-prev-prov').textContent = df.provincia || '';
        if (byId('arca-prev-loc')) byId('arca-prev-loc').textContent = df.localidad || '';

        const warnEl = byId('arca-prev-warn');
        if (warnEl) { warnEl.style.display = 'none'; warnEl.textContent = ''; }

        const overlay = byId('arca-preview-overlay');
        if (overlay) overlay.style.display = 'flex';
    }

    function closeArcaPreview() {
        const overlay = byId('arca-preview-overlay');
        if (overlay) overlay.style.display = 'none';
    }

    function cssEscape(v) {
        if (window.CSS && typeof window.CSS.escape === 'function') return window.CSS.escape(String(v));
        return String(v).replace(/["\\]/g, '\\$&');
    }

    function triggerChange(id) {
        const el = byId(id);
        if (el) el.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function sleep(ms) { return new Promise(function (r) { setTimeout(r, ms); }); }

    async function esperarOptionLocalidad(timeoutMs, desiredValue) {
        const start = Date.now();
        const desired = desiredValue == null ? '' : String(desiredValue);
        while (Date.now() - start < timeoutMs) {
            const loc = byId('localidad_id');
            if (loc && loc.querySelector('option[value="' + cssEscape(desired) + '"]')) return true;
            await sleep(100);
        }
        return false;
    }

    function ensureSelectHasOption(selectId, value, label) {
        const sel = byId(selectId);
        if (!sel || value == null || value === '') return;
        if (sel.querySelector('option[value="' + cssEscape(value) + '"]')) return;
        const opt = document.createElement('option');
        opt.value = String(value);
        opt.textContent = label || String(value);
        sel.appendChild(opt);
    }

    async function aplicarDatosArcaEnFormulario(payload) {
        const data = payload.data || {};
        const df = data.domicilioFiscal || {};
        if (payload.cuit) {
            setVal('cuil', payload.cuit);
            if (typeof window.formatarCUIT === 'function') window.formatarCUIT(byId('cuil'));
        }
        if (data.nombre) setVal('nombre', data.nombre);
        if (df.texto) setVal('domicilio', df.texto);
        if (df.codPostal) setVal('codigo_postal', df.codPostal);

        // Provincia vinculada al maestro (dispara la carga de localidades vía domicilio.js).
        if (df.provincia_id && byId('provincia_id')) {
            setVal('provincia_id', df.provincia_id);
            triggerChange('provincia_id');
            const provSel = byId('provincia_id');
            const provText = provSel && provSel.selectedOptions && provSel.selectedOptions[0] ? provSel.selectedOptions[0].text : '';
            setVal('desc_provincia', df.provincia || provText);
        } else if (df.provincia) {
            setVal('desc_provincia', df.provincia);
        }

        // Localidad vinculada: esperar a que carguen las opciones de la provincia.
        if (df.localidad_id && byId('localidad_id')) {
            await esperarOptionLocalidad(7000, df.localidad_id);
            ensureSelectHasOption('localidad_id', df.localidad_id, df.localidad || df.localidad_id);
            setVal('localidad_id', df.localidad_id);
            triggerChange('localidad_id');
            if (!getVal('desc_localidad') && df.localidad) setVal('desc_localidad', df.localidad);
        } else if (df.localidad) {
            setVal('desc_localidad', df.localidad);
        }
    }

    // ---------- Full payload / SOAP (paridad con proveedores) ----------
    function buildTreeDom(value, keyLabel, depth) {
        const maxAutoOpenDepth = 3;
        const wrap = document.createElement('div');
        wrap.className = 'arca-tree-node';
        if (value === null || value === undefined || typeof value !== 'object') {
            const line = document.createElement('div');
            line.className = 'arca-tree-line';
            line.style.paddingLeft = depth * 14 + 'px';
            const cls = value === null || value === undefined ? ' arca-tree-null' : '';
            line.innerHTML = '<span class="arca-tree-k">' + escapeHtml(keyLabel) + '</span>' +
                '<span class="arca-tree-v' + cls + '">' + escapeHtml(String(value)) + '</span>';
            wrap.appendChild(line);
            return wrap;
        }
        const isArr = Array.isArray(value);
        const keys = isArr ? value.map(function (_, i) { return i; }) : Object.keys(value).sort();
        const det = document.createElement('details');
        det.open = depth < maxAutoOpenDepth;
        const sum = document.createElement('summary');
        sum.className = 'arca-tree-summary';
        sum.style.paddingLeft = depth * 14 + 'px';
        sum.textContent = keyLabel + (isArr ? '  [' + value.length + ']' : '  {' + keys.length + '}');
        det.appendChild(sum);
        keys.forEach(function (k) { det.appendChild(buildTreeDom(value[k], isArr ? '[' + k + ']' : k, depth + 1)); });
        wrap.appendChild(det);
        return wrap;
    }

    function renderArcaSoapPanel() {
        const section = byId('arca-soap-section');
        if (!section) return;
        const req = lastArcaSoap && lastArcaSoap.request ? String(lastArcaSoap.request) : '';
        const res = lastArcaSoap && lastArcaSoap.response ? String(lastArcaSoap.response) : '';
        const hasSoap = !!(req || res);
        section.style.display = hasSoap ? 'block' : 'none';
        const reqPre = byId('arca-soap-request');
        const resPre = byId('arca-soap-response');
        if (reqPre) { reqPre.textContent = req; reqPre.style.display = req ? 'block' : 'none'; }
        if (resPre) { resPre.textContent = res; resPre.style.display = res ? 'block' : 'none'; }
    }

    function openArcaFullPayloadView() {
        const root = byId('arca-full-tree');
        if (!root) return;
        root.innerHTML = '';
        if (!lastArcaPayloadTree) {
            root.textContent = 'No hay datos cargados. Consultá el padrón primero.';
        } else {
            root.appendChild(buildTreeDom(lastArcaPayloadTree, 'respuesta', 0));
        }
        renderArcaSoapPanel();
        const overlay = byId('arca-full-overlay');
        if (overlay) overlay.style.display = 'flex';
    }

    function closeArcaFullView() {
        const overlay = byId('arca-full-overlay');
        if (overlay) overlay.style.display = 'none';
    }

    // ---------- Consulta ----------
    async function ejecutarConsultaArca(cuitRaw) {
        const endpoint = getArcaEndpointUrl();
        if (!endpoint) {
            alert('No está configurada la URL de consulta ARCA en el formulario.');
            return 'aborted';
        }
        const cuit = soloDigitos(cuitRaw);
        if (cuit.length !== 11) {
            alert('Ingresá una CUIL/CUIT válida (11 dígitos).');
            return 'aborted';
        }
        try {
            setArcaCuitModalLoading(true);
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
                await resp.text();
                alert('Error consultando padrón ARCA (respuesta inesperada, posible sesión vencida).');
                return false;
            }
            const json = await resp.json();
            storeArcaSoap(pickSoapFromJson(json));
            if (!resp.ok || !json.ok) {
                alert((json && json.message) || 'Error consultando padrón ARCA.');
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
            setArcaCuitModalLoading(false);
        }
    }

    // ---------- Entry modal ----------
    function openArcaCuitEntryOverlay() {
        setArcaCuitModalLoading(false);
        const inp = byId('arca-cuit-entry-input');
        const ov = byId('arca-cuit-entry-overlay');
        if (!ov) {
            // Fallback: sin modal compartido, consultar directo con el CUIL del form.
            ejecutarConsultaArca(getVal('cuil'));
            return;
        }
        if (inp) {
            inp.value = getVal('cuil');
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
        const btn = byId('btn-consulta-arca-padron-crear');
        if (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                openArcaCuitEntryOverlay();
            });
        }

        function bindClose(id) { const el = byId(id); if (el) el.addEventListener('click', closeArcaCuitEntryOverlay); }
        bindClose('arca-cuit-entry-close');
        bindClose('arca-cuit-entry-cancel');
        const cuitOv = byId('arca-cuit-entry-overlay');
        if (cuitOv) {
            cuitOv.addEventListener('click', function (ev) {
                if (ev.target && ev.target.id === 'arca-cuit-entry-overlay') closeArcaCuitEntryOverlay();
            });
        }

        async function runConsultaDesdeModal() {
            const inp = byId('arca-cuit-entry-input');
            const raw = inp ? inp.value : getVal('cuil');
            const resultado = await ejecutarConsultaArca(raw);
            if (resultado !== 'aborted' && resultado !== false) closeArcaCuitEntryOverlay();
        }
        const cuitGo = byId('arca-cuit-entry-consultar');
        if (cuitGo) cuitGo.addEventListener('click', runConsultaDesdeModal);
        const cuitEntryInp = byId('arca-cuit-entry-input');
        if (cuitEntryInp) {
            cuitEntryInp.addEventListener('keydown', function (ev) {
                if (ev.key === 'Enter') { ev.preventDefault(); runConsultaDesdeModal(); }
            });
        }

        // Preview modal
        const pClose = byId('arca-preview-close'); if (pClose) pClose.addEventListener('click', closeArcaPreview);
        const pCancel = byId('arca-preview-cancel'); if (pCancel) pCancel.addEventListener('click', closeArcaPreview);
        const pOverlay = byId('arca-preview-overlay');
        if (pOverlay) {
            pOverlay.addEventListener('click', function (ev) {
                if (ev.target && ev.target.id === 'arca-preview-overlay') closeArcaPreview();
            });
        }
        const pApply = byId('arca-preview-apply');
        if (pApply) {
            pApply.addEventListener('click', async function () {
                if (!lastArcaData) return;
                try { await aplicarDatosArcaEnFormulario(lastArcaData); } finally { closeArcaPreview(); }
            });
        }
        const expandBtn = byId('arca-preview-expand-full');
        if (expandBtn) expandBtn.addEventListener('click', openArcaFullPayloadView);

        // Full payload modal
        const fClose = byId('arca-full-close'); if (fClose) fClose.addEventListener('click', closeArcaFullView);
        const fCloseFoot = byId('arca-full-close-foot'); if (fCloseFoot) fCloseFoot.addEventListener('click', closeArcaFullView);
        const fOverlay = byId('arca-full-overlay');
        if (fOverlay) {
            fOverlay.addEventListener('click', function (ev) {
                if (ev.target && ev.target.id === 'arca-full-overlay') closeArcaFullView();
            });
        }
    });
})();
