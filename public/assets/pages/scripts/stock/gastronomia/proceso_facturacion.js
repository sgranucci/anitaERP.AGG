(function () {
    const G = window.GASTRONOMIA || {};
    const empresaId = G.empresaId || 1;
    const prefijoSku = (G.prefijoSku || 'V').toString();
    const skuDigitosSufijo = Math.max(0, parseInt(String(G.skuCatalogoDigitosSufijo || '0'), 10) || 0);

    let cuentaId = null;
    let modoMesa = true;
    let pendingArticulo = null;
    let pendingOpcionalesCtx = null;

    function appPath(path) {
        const raw = typeof carpetaBase !== 'undefined' && carpetaBase != null ? String(carpetaBase) : '';
        const base = raw.replace(/\/$/, '');
        const p = path.startsWith('/') ? path : '/' + path;
        return base + p;
    }

    function hdrJson() {
        return {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': G.csrf,
            'X-Requested-With': 'XMLHttpRequest',
        };
    }

    async function api(path, opts) {
        const url = appPath(path);
        const sep = url.includes('?') ? '&' : '?';
        const res = await fetch(url + sep + '_=' + Date.now(), opts);
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            const detail =
                data.error ||
                data.message ||
                (data.errors ? JSON.stringify(data.errors) : '') ||
                '';
            throw new Error(detail || 'HTTP ' + res.status);
        }
        return data;
    }

    function toast(msg, type) {
        if (window.toastr) {
            toastr[type || 'info'](msg);
        } else {
            alert(msg);
        }
    }

    function labelCodigoNombre(codigo, nombre) {
        const c = codigo != null && String(codigo).trim() !== '' ? String(codigo).trim() + ' — ' : '';
        return c + (nombre || '');
    }

    async function cargarMozosDescuentosMonedasUsos() {
        const [mozos, desc, monedas, usos] = await Promise.all([
            api(`/stock/gastronomia/api/mozos?empresa_id=${empresaId}`, { headers: hdrJson() }),
            api('/stock/gastronomia/api/descuentos-gastronomia', { headers: hdrJson() }),
            api('/stock/gastronomia/api/monedas', { headers: hdrJson() }),
            api('/stock/gastronomia/api/usos-cuentacaja', { headers: hdrJson() }),
        ]);

        const selMozo = document.getElementById('fld-mozo');
        selMozo.innerHTML = '<option value="">—</option>';
        (mozos.mozos || []).forEach((m) => {
            const opt = document.createElement('option');
            opt.value = m.id;
            opt.textContent = labelCodigoNombre(m.codigo, m.nombre);
            selMozo.appendChild(opt);
        });

        const selD = document.getElementById('fld-descuento');
        selD.innerHTML = '<option value="">—</option>';
        (desc.descuentos || []).forEach((d) => {
            const opt = document.createElement('option');
            opt.value = d.id;
            opt.textContent = labelCodigoNombre(d.codigo, d.nombre);
            selD.appendChild(opt);
        });

        const selMon = document.getElementById('cb-moneda');
        selMon.innerHTML = '';
        (monedas.monedas || []).forEach((m) => {
            const opt = document.createElement('option');
            opt.value = m.id;
            opt.textContent = m.abreviatura || m.nombre;
            selMon.appendChild(opt);
        });

        const selUso = document.getElementById('cb-uso');
        selUso.innerHTML = '<option value="">Uso cuenta caja…</option>';
        (usos.usos || []).forEach((u) => {
            const opt = document.createElement('option');
            opt.value = u.id;
            opt.textContent = u.nombre;
            selUso.appendChild(opt);
        });

        selMon.addEventListener('change', refrescarCuentasCajaYCotiz);
        selUso.addEventListener('change', refrescarCuentasCajaYCotiz);
    }

    async function refrescarCuentasCajaYCotiz() {
        const monedaId = document.getElementById('cb-moneda').value;
        const usoId = document.getElementById('cb-uso').value;
        try {
            const cc = await api(
                `/stock/gastronomia/api/cuentas-caja?empresa_id=${empresaId}&moneda_id=${monedaId}&usocuentacaja_id=${usoId}`,
                { headers: hdrJson() },
            );
            const sel = document.getElementById('cb-cuentacaja');
            sel.innerHTML = '<option value="">Cuenta de caja…</option>';
            (cc.cuentas_caja || []).forEach((c) => {
                const opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = labelCodigoNombre(c.codigo, c.nombre);
                sel.appendChild(opt);
            });

            const cot = await api(`/stock/gastronomia/api/cotizacion?moneda_id=${monedaId}`, { headers: hdrJson() });
            document.getElementById('cb-cotiz').value = cot.cotizacion;
        } catch (e) {
            console.warn(e);
        }
    }

    async function cargarMesas() {
        const data = await api(`/stock/gastronomia/api/mesas?empresa_id=${empresaId}`, { headers: hdrJson() });
        const panel = document.getElementById('panel-mesas');
        panel.innerHTML = '';
        (data.mesas || []).forEach((m) => {
            const cls = m.ocupada ? 'warning' : 'light';
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = `btn btn-sm btn-${cls} m-1`;
            btn.textContent = `${m.numeromesa} ${m.ocupada ? '(abierta)' : ''}`;
            btn.addEventListener('click', () => abrirMesa(m.id));
            panel.appendChild(btn);
        });
    }

    async function cargarCuentasActivas() {
        const data = await api(`/stock/gastronomia/api/cuentas-activas?empresa_id=${empresaId}`, { headers: hdrJson() });
        const panel = document.getElementById('panel-cuentas');
        panel.innerHTML = '';
        (data.cuentas || []).forEach((c) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-sm btn-outline-primary m-1';
            btn.textContent = 'Cuenta #' + c.id;
            btn.addEventListener('click', () => seleccionarCuenta(c.id));
            panel.appendChild(btn);
        });
    }

    async function abrirMesa(mesaId) {
        try {
            const r = await api('/stock/gastronomia/api/abrir-mesa', {
                method: 'POST',
                headers: hdrJson(),
                body: JSON.stringify({ mesa_id: mesaId, empresa_id: empresaId }),
            });
            seleccionarCuenta(r.cuenta_id);
            toast(r.reutilizada ? 'Mesa ya abierta — cargando cuenta.' : 'Mesa abierta.', 'success');
            cargarMesas();
        } catch (e) {
            toast(e.message, 'error');
        }
    }

    async function nuevaCuentaLibre() {
        try {
            const r = await api('/stock/gastronomia/api/abrir-cuenta', {
                method: 'POST',
                headers: hdrJson(),
                body: JSON.stringify({ empresa_id: empresaId }),
            });
            seleccionarCuenta(r.cuenta_id);
            toast('Cuenta libre creada.', 'success');
            cargarCuentasActivas();
        } catch (e) {
            toast(e.message, 'error');
        }
    }

    function getTrLineaArticulo() {
        return document.getElementById('tr-gastro-linea-articulo');
    }

    function limpiarFormularioArticuloLinea() {
        const tr = getTrLineaArticulo();
        if (!tr) return;
        tr.querySelector('.articulo_id').value = '';
        const cod = tr.querySelector('.codigoarticulo');
        if (cod) cod.value = '';
        const suf = tr.querySelector('.gastro-sku-sufijo');
        if (suf) suf.value = '';
        tr.querySelector('.descripcionarticulo').value = '';
        tr.querySelector('.categoria_id').value = '';
        tr.querySelector('.subcategoria_id').value = '';
        tr.querySelector('.unidadmedida_id').value = '';
    }

    function focusSkuConsumo() {
        const tr = getTrLineaArticulo();
        if (!tr) return;
        const el = tr.querySelector('.gastro-sku-sufijo') || tr.querySelector('.gastro-carga-sku');
        if (el && typeof el.focus === 'function') {
            el.focus();
            if (typeof el.select === 'function') el.select();
        }
    }

    function composeSkuDesdeSufijoDigitos(sufijoRaw) {
        const digits = String(sufijoRaw || '').replace(/\D/g, '');
        if (!digits) return '';
        if (skuDigitosSufijo <= 0) return '';
        const padded = digits.padStart(skuDigitosSufijo, '0').slice(-skuDigitosSufijo);
        return prefijoSku + padded;
    }

    function syncSufijoDesdeSkuCompleto(sku) {
        const tr = getTrLineaArticulo();
        if (!tr || skuDigitosSufijo <= 0) return;
        const el = tr.querySelector('.gastro-sku-sufijo');
        if (!el) return;
        if (!sku || !skuPermitidoGastronomia(sku)) {
            el.value = '';
            return;
        }
        const p = prefijoSku.toUpperCase();
        const s = String(sku).toUpperCase();
        if (!s.startsWith(p)) {
            el.value = '';
            return;
        }
        const tail = s.slice(p.length).replace(/\D/g, '') || '0';
        const padded = tail.padStart(skuDigitosSufijo, '0').slice(-skuDigitosSufijo);
        el.value = String(parseInt(padded, 10));
    }

    function skuIngresadoEnFila() {
        const tr = getTrLineaArticulo();
        if (!tr) return '';
        if (skuDigitosSufijo > 0) {
            const suf = tr.querySelector('.gastro-sku-sufijo');
            return composeSkuDesdeSufijoDigitos(suf ? suf.value : '');
        }
        const cod = tr.querySelector('.codigoarticulo');
        return (cod && cod.value ? cod.value : '').trim();
    }

    async function fetchArticuloCatalogoPorSku(fullSku) {
        const enc = encodeURIComponent(fullSku);
        return api(`/stock/gastronomia/api/articulo-catalogo-por-sku?empresa_id=${empresaId}&sku=${enc}`, { headers: hdrJson() });
    }

    async function patchCantidadLinea(lineaId, nuevaCantidad) {
        if (!cuentaId) return;
        if (!(nuevaCantidad >= 0.0001)) {
            toast('La cantidad no puede ser menor al mínimo permitido.', 'warning');
            return;
        }
        try {
            const data = await api(`/stock/gastronomia/api/cuenta/${cuentaId}/linea/${lineaId}`, {
                method: 'PATCH',
                headers: hdrJson(),
                body: JSON.stringify({ cantidad: nuevaCantidad }),
            });
            pintarLineas(data.cuenta);
            focusSkuConsumo();
        } catch (e) {
            toast(e.message, 'error');
        }
    }

    function articuloSeleccionadoEnFila() {
        const tr = getTrLineaArticulo();
        if (!tr) return null;
        const id = parseInt(tr.querySelector('.articulo_id').value || '0', 10);
        if (!id) return null;
        const codEl = tr.querySelector('.codigoarticulo');
        let sku = codEl ? (codEl.value || '').trim() : '';
        if (!sku) {
            sku = skuIngresadoEnFila();
        }
        const descripcion = (tr.querySelector('.descripcionarticulo').value || '').trim();
        return { id, sku, descripcion };
    }

    function skuPermitidoGastronomia(sku) {
        const s = (sku || '').toUpperCase();
        const p = prefijoSku.toUpperCase();
        return s.startsWith(p);
    }

    async function seleccionarCuenta(id) {
        cuentaId = id;
        document.getElementById('badge-cuenta-id').textContent = '#' + id;
        document.getElementById('badge-cuenta-id').classList.remove('d-none');
        document.getElementById('btn-cerrar-cuenta').classList.remove('d-none');
        try {
            const data = await api(`/stock/gastronomia/api/cuenta/${id}`, { headers: hdrJson() });
            const c = data.cuenta;
            const cli = c.cliente || null;
            document.getElementById('cliente_id').value = c.cliente_id || '';
            document.getElementById('nombrecliente').value = cli ? cli.nombre || '' : '';
            document.getElementById('codigocliente').value = cli && cli.codigo != null ? String(cli.codigo) : '';
            document.getElementById('fld-cubiertos').value = c.cubiertos || 0;
            document.getElementById('fld-mozo').value = c.mozo_gastronomia_id || '';
            document.getElementById('fld-descuento').value = c.descuento_gastronomia_id || '';
            pintarLineas(c);
            limpiarFormularioArticuloLinea();
            setTimeout(() => focusSkuConsumo(), 50);
        } catch (e) {
            toast(e.message, 'error');
        }
    }

    function pintarLineas(cuenta) {
        const wrap = document.getElementById('panel-detalle-lineas');
        let sub = 0;
        let html = '<table class="table table-sm table-striped mb-0"><thead><tr><th>#</th><th>Artículo</th><th>Cant.</th><th>P.U.</th><th></th></tr></thead><tbody>';
        (cuenta.lineas || []).forEach((ln) => {
            const pu = Number(ln.precio_unitario);
            const cant = Number(ln.cantidad);
            sub += cant * pu;
            const op = ln.opcionales_json ? JSON.stringify(ln.opcionales_json) : '';
            html += `<tr>
        <td>${ln.numero_linea}</td>
        <td>${ln.articulo ? ln.articulo.sku + ' — ' + ln.articulo.descripcion : ''}<br><small class="text-muted">${op}</small></td>
        <td class="text-nowrap align-middle">
          <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 btn-gastro-qty" data-dir="-1" data-linea="${ln.id}" data-cant="${cant}" title="Menos">−</button>
          <span class="mx-1">${cant}</span>
          <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 btn-gastro-qty" data-dir="1" data-linea="${ln.id}" data-cant="${cant}" title="Más">+</button>
        </td>
        <td>${pu.toFixed(2)}</td>
        <td><button type="button" class="btn btn-sm btn-link text-danger btn-del-linea" data-linea="${ln.id}">quitar</button></td>
      </tr>`;
        });
        html += '</tbody></table>';
        html += `<div class="text-right mt-1"><strong>Subtotal estimado:</strong> ${sub.toFixed(2)}</div>`;
        wrap.innerHTML = html;
        wrap.querySelectorAll('.btn-del-linea').forEach((b) =>
            b.addEventListener('click', () => eliminarLinea(b.getAttribute('data-linea'))),
        );
        wrap.querySelectorAll('.btn-gastro-qty').forEach((b) => {
            b.addEventListener('click', () => {
                const lineaId = b.getAttribute('data-linea');
                const cur = parseFloat(b.getAttribute('data-cant'));
                const dir = parseInt(b.getAttribute('data-dir'), 10);
                const next = cur + dir;
                if (!(next >= 0.0001)) {
                    toast('La cantidad no puede ser menor al mínimo permitido.', 'warning');
                    return;
                }
                void patchCantidadLinea(lineaId, next);
            });
        });
    }

    async function guardarCabecera() {
        if (!cuentaId) return toast('Seleccione mesa/cuenta', 'warning');
        try {
            const cid = document.getElementById('cliente_id').value;
            const body = {
                cliente_id: cid && String(cid).trim() !== '' ? cid : null,
                cubiertos: document.getElementById('fld-cubiertos').value,
                mozo_gastronomia_id: document.getElementById('fld-mozo').value || null,
                descuento_gastronomia_id: document.getElementById('fld-descuento').value || null,
            };
            const data = await api(`/stock/gastronomia/api/cuenta/${cuentaId}`, {
                method: 'PATCH',
                headers: hdrJson(),
                body: JSON.stringify(body),
            });
            toast('Cabecera guardada.', 'success');
            pintarLineas(data.cuenta);
        } catch (e) {
            toast(e.message, 'error');
        }
    }

    function iniciarAltaLinea(articulo) {
        pendingArticulo = articulo;
        $('#modal-cantidad').modal('show');
    }

    async function procesarAltaConsumo(articulo, cantidad) {
        const opData = await api(`/stock/gastronomia/api/opcionales-articulo/${articulo.id}`, { headers: hdrJson() });

        if (opData.grupos && opData.grupos.length) {
            pendingOpcionalesCtx = { articulo, cantidad };
            const body = document.getElementById('modal-opcionales-body');
            body.innerHTML = '';
            opData.grupos.forEach((g) => {
                const orden = String(g.orden);
                const div = document.createElement('div');
                div.className = 'mb-2';
                div.innerHTML = `<label class="small mb-0">Opción orden ${orden}</label>`;
                const sel = document.createElement('select');
                sel.className = 'form-control form-control-sm opcional-grupo';
                sel.dataset.orden = orden;
                sel.innerHTML = '<option value="">—</option>';
                g.opciones.forEach((o) => {
                    sel.innerHTML += `<option value="${o.articulo_id}">${o.sku} — ${o.descripcion}</option>`;
                });
                div.appendChild(sel);
                body.appendChild(div);
            });
            $('#modal-opcionales').modal('show');
            return;
        }

        await agregarLineaApi(articulo, cantidad, {});
    }

    async function continuarDespuesCantidad() {
        const cant = parseFloat(document.getElementById('fld-cantidad-linea').value || '0');
        if (!(cant > 0)) return toast('Cantidad inválida', 'warning');
        $('#modal-cantidad').modal('hide');

        const art = pendingArticulo;
        pendingArticulo = null;
        if (!art) return;

        await procesarAltaConsumo(art, cant);
    }

    function aplicarArticuloResueltoEnFila(a) {
        const tr = getTrLineaArticulo();
        if (!tr || !a) return;
        tr.querySelector('.articulo_id').value = a.id;
        const cod = tr.querySelector('.codigoarticulo');
        if (cod) cod.value = a.sku || '';
        tr.querySelector('.descripcionarticulo').value = a.descripcion || '';
        syncSufijoDesdeSkuCompleto(a.sku || '');
    }

    /**
     * Misma búsqueda por SKU que Enter: valida cuenta, arma SKU, consulta catálogo y completa la fila.
     * @returns {{ ok: boolean, articulo: object|null }}
     */
    async function resolverSkuConsumoEnFila() {
        if (!cuentaId) {
            toast('Seleccione mesa o cuenta.', 'warning');
            return { ok: false, articulo: null };
        }
        const fullSku = skuIngresadoEnFila();
        if (!fullSku) {
            toast('Ingrese el código del artículo.', 'warning');
            return { ok: false, articulo: null };
        }
        if (!skuPermitidoGastronomia(fullSku)) {
            toast('SKU debe comenzar con ' + prefijoSku + ' (catálogo gastronomía).', 'warning');
            return { ok: false, articulo: null };
        }
        try {
            const data = await fetchArticuloCatalogoPorSku(fullSku);
            const a = data.articulo;
            if (!a || !a.id) {
                toast('Artículo no encontrado.', 'warning');
                return { ok: false, articulo: null };
            }
            aplicarArticuloResueltoEnFila(a);
            return { ok: true, articulo: a };
        } catch (e) {
            toast(e.message || 'No se encontró el artículo', 'warning');
            return { ok: false, articulo: null };
        }
    }

    async function intentarAgregarConsumoDesdeTeclado() {
        const { ok, articulo } = await resolverSkuConsumoEnFila();
        if (!ok || !articulo) return;
        await procesarAltaConsumo(articulo, 1);
    }

    async function resolverSkuPorTabYEnfocarAgregar() {
        const { ok } = await resolverSkuConsumoEnFila();
        if (!ok) return;
        const btn = document.getElementById('btn-agregar-consumo');
        if (btn && typeof btn.focus === 'function') btn.focus();
    }

    async function confirmarOpcionales() {
        const selects = document.querySelectorAll('#modal-opcionales-body select.opcional-grupo');
        const map = {};
        selects.forEach((s) => {
            map[s.dataset.orden] = s.value ? parseInt(s.value, 10) : null;
        });
        $('#modal-opcionales').modal('hide');
        const ctx = pendingOpcionalesCtx;
        pendingOpcionalesCtx = null;
        if (!ctx) return;
        await agregarLineaApi(ctx.articulo, ctx.cantidad, map);
    }

    async function agregarLineaApi(articulo, cantidad, opcionales) {
        if (!cuentaId) return toast('Seleccione cuenta', 'warning');
        try {
            const payload = {
                articulo_id: articulo.id,
                cantidad: cantidad,
                opcionales: opcionales,
            };
            if (articulo.precio_sugerido != null && articulo.precio_sugerido !== '') {
                payload.precio_unitario = articulo.precio_sugerido;
            }
            const data = await api(`/stock/gastronomia/api/cuenta/${cuentaId}/linea`, {
                method: 'POST',
                headers: hdrJson(),
                body: JSON.stringify(payload),
            });
            toast('Línea agregada', 'success');
            pintarLineas(data.cuenta);
            limpiarFormularioArticuloLinea();
            cargarMesas();
            cargarCuentasActivas();
            focusSkuConsumo();
        } catch (e) {
            if (e.message && e.message.includes('fetch')) toast(String(e), 'error');
            else toast(e.message, 'error');
        }
    }

    async function eliminarLinea(lineaId) {
        if (!cuentaId) return;
        try {
            const data = await api(`/stock/gastronomia/api/cuenta/${cuentaId}/linea/${lineaId}`, {
                method: 'DELETE',
                headers: hdrJson(),
            });
            pintarLineas(data.cuenta);
            focusSkuConsumo();
        } catch (e) {
            toast(e.message, 'error');
        }
    }

    async function cerrarCuenta() {
        if (!cuentaId) return;
        if (!confirm('¿Cerrar cuenta sin facturar?')) return;
        try {
            await api(`/stock/gastronomia/api/cuenta/${cuentaId}/cerrar`, {
                method: 'POST',
                headers: hdrJson(),
                body: '{}',
            });
            toast('Cuenta cerrada', 'success');
            cuentaId = null;
            document.getElementById('badge-cuenta-id').classList.add('d-none');
            document.getElementById('btn-cerrar-cuenta').classList.add('d-none');
            document.getElementById('panel-detalle-lineas').innerHTML = '';
            document.getElementById('cliente_id').value = '';
            document.getElementById('nombrecliente').value = '';
            document.getElementById('codigocliente').value = '';
            limpiarFormularioArticuloLinea();
            cargarMesas();
            cargarCuentasActivas();
        } catch (e) {
            toast(e.message, 'error');
        }
    }

    async function emitirFactura() {
        if (!cuentaId) return toast('Seleccione cuenta', 'warning');
        const monedaId = document.getElementById('cb-moneda').value;
        if (!monedaId) return toast('Seleccione moneda', 'warning');
        try {
            const data = await api('/stock/gastronomia/api/emitir-factura', {
                method: 'POST',
                headers: hdrJson(),
                body: JSON.stringify({ cuenta_id: cuentaId, moneda_id: monedaId }),
            });
            if (data.warn) toast(data.warn, 'warning');
            else toast('Factura emitida: ' + (data.factura || ''), 'success');

            const vid = data.venta_id;
            if (vid) {
                window.open(G.rutas.listaPdfFacturaBase + '/' + vid, '_blank');
                const cobUrl = G.rutas.crearCobranzaBase + '/' + vid;
                document.getElementById('btn-abrir-cobranza-completa').onclick = () => window.open(cobUrl, '_blank');
            }
            cargarMesas();
            cargarCuentasActivas();
            cuentaId = null;
            document.getElementById('badge-cuenta-id').classList.add('d-none');
            document.getElementById('btn-cerrar-cuenta').classList.add('d-none');
            document.getElementById('panel-detalle-lineas').innerHTML = '';
            document.getElementById('cliente_id').value = '';
            document.getElementById('nombrecliente').value = '';
            document.getElementById('codigocliente').value = '';
            limpiarFormularioArticuloLinea();
        } catch (e) {
            toast(e.message, 'error');
        }
    }

    function setModo(mesa) {
        modoMesa = mesa;
        document.getElementById('panel-mesas').classList.toggle('d-none', !mesa);
        document.getElementById('panel-cuentas').classList.toggle('d-none', mesa);
        document.getElementById('btn-modo-mesa').classList.toggle('active', mesa);
        document.getElementById('btn-modo-cuenta').classList.toggle('active', !mesa);
        document.getElementById('btn-nueva-cuenta-libre').classList.toggle('d-none', mesa);
    }

    function wireConsultasSistema() {
        if (typeof activa_eventos_consultacliente === 'function') {
            activa_eventos_consultacliente();
        }
        if (typeof activa_eventos_consultaarticulo === 'function') {
            activa_eventos_consultaarticulo();
        }
        window.onArticuloSeleccionado = function (dataArticulo) {
            if (!dataArticulo || !dataArticulo.id) return;
            if (!skuPermitidoGastronomia(dataArticulo.sku)) {
                toast(
                    'Este artículo no pertenece al catálogo gastronomía (SKU debe comenzar con ' + prefijoSku + ').',
                    'warning',
                );
                limpiarFormularioArticuloLinea();
                return;
            }
            const tr = document.getElementById('tr-gastro-linea-articulo');
            if (tr) {
                tr.querySelector('.articulo_id').value = dataArticulo.id;
                const cod = tr.querySelector('.codigoarticulo');
                if (cod) cod.value = dataArticulo.sku || '';
                tr.querySelector('.descripcionarticulo').value = dataArticulo.descripcion || '';
                syncSufijoDesdeSkuCompleto(dataArticulo.sku || '');
            }
        };
    }

    function registrarEventosUi() {
        document.getElementById('btn-modo-mesa').addEventListener('click', () => {
            setModo(true);
        });
        document.getElementById('btn-modo-cuenta').addEventListener('click', () => {
            setModo(false);
        });
        document.getElementById('btn-nueva-cuenta-libre').addEventListener('click', nuevaCuentaLibre);
        document.getElementById('btn-guardar-cabecera').addEventListener('click', guardarCabecera);
        document.getElementById('btn-cerrar-cuenta').addEventListener('click', cerrarCuenta);
        document.getElementById('btn-agregar-consumo').addEventListener('click', async () => {
            let articuloParaModal = articuloSeleccionadoEnFila();
            if (!articuloParaModal || !articuloParaModal.id) {
                const { ok, articulo } = await resolverSkuConsumoEnFila();
                if (ok && articulo) articuloParaModal = articulo;
            }
            if (!articuloParaModal || !articuloParaModal.id) {
                return toast('Seleccione un artículo (lupa o SKU).', 'warning');
            }
            if (!skuPermitidoGastronomia(articuloParaModal.sku)) {
                return toast('SKU debe comenzar con ' + prefijoSku + ' (catálogo gastronomía).', 'warning');
            }
            iniciarAltaLinea(articuloParaModal);
        });
        document.getElementById('modal-cantidad-confirmar').addEventListener('click', continuarDespuesCantidad);
        document.getElementById('modal-opcionales-confirmar').addEventListener('click', confirmarOpcionales);
        document.getElementById('tool-facturar').addEventListener('click', emitirFactura);
        document.getElementById('tool-asignar-cliente').addEventListener('click', () => {
            const el = document.getElementById('cliente_id');
            if (el) el.focus();
        });
        document.getElementById('tool-descuento').addEventListener('click', () => document.getElementById('fld-descuento').focus());

        document.getElementById('btn-abrir-cobranza-completa').addEventListener('click', () => {
            toast('Primero facture; luego use el botón que se habilita con el link a cobranza.', 'info');
        });

        document.addEventListener(
            'keydown',
            function (e) {
                if (e.key !== 'Enter' && e.key !== 'Tab') return;
                const t = e.target;
                if (!t || !t.classList || !t.classList.contains('gastro-carga-sku')) return;
                if (!t.closest('#tr-gastro-linea-articulo')) return;
                if (e.key === 'Enter') {
                    e.preventDefault();
                    e.stopPropagation();
                    void intentarAgregarConsumoDesdeTeclado();
                    return;
                }
                if (e.key === 'Tab' && !e.shiftKey) {
                    e.preventDefault();
                    e.stopPropagation();
                    void resolverSkuPorTabYEnfocarAgregar();
                }
            },
            true,
        );

        document.addEventListener('input', function (e) {
            const t = e.target;
            if (!t.classList || !t.classList.contains('gastro-sku-sufijo')) return;
            const d = String(t.value || '').replace(/\D/g, '');
            if (t.value !== d) t.value = d;
        });

        if (typeof $ !== 'undefined') {
            $('#modal-opcionales').on('hidden.bs.modal', function () {
                setTimeout(() => focusSkuConsumo(), 80);
            });
        }
    }

    document.addEventListener('DOMContentLoaded', async () => {
        registrarEventosUi();
        wireConsultasSistema();

        try {
            await api('/stock/gastronomia/api/config?empresa_id=' + empresaId, { headers: hdrJson() });
        } catch (e) {
            toast(e.message, 'warning');
        }

        try {
            await cargarMozosDescuentosMonedasUsos();
        } catch (e) {
            toast('No se pudieron cargar mozos/descuentos/monedas: ' + e.message, 'error');
        }

        try {
            await refrescarCuentasCajaYCotiz();
        } catch (e) {
            console.warn(e);
        }

        try {
            await cargarMesas();
        } catch (e) {
            toast('Mesas: ' + e.message, 'error');
        }

        try {
            await cargarCuentasActivas();
        } catch (e) {
            toast('Cuentas activas: ' + e.message, 'error');
        }

        setTimeout(() => focusSkuConsumo(), 200);
    });
})();
