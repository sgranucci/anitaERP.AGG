(function () {
    'use strict';

    const CFG = window.MV_RENDICION || {};
    const TOLERANCIA = 0.02;
    let cuentasCaja = [];
    let cuentasPorId = {};
    let indiceArticulo = 0;
    let indiceMedio = 0;

    function $(sel, root) {
        return (root || document).querySelector(sel);
    }

    function $$(sel, root) {
        return Array.from((root || document).querySelectorAll(sel));
    }

    function carpetaBase() {
        if (typeof resolverCarpetaBaseApp === 'function') {
            return String(resolverCarpetaBaseApp() || '').replace(/\/$/, '');
        }
        return String(typeof carpetaBase !== 'undefined' ? carpetaBase : '').replace(/\/$/, '');
    }

    function fmtMoney(n) {
        const v = Number(n) || 0;
        return '$' + v.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function parseNum(val) {
        if (val === null || val === undefined || val === '') return 0;
        const n = parseFloat(String(val).replace(',', '.'));
        return Number.isFinite(n) ? n : 0;
    }

    function empresaIdActual() {
        const el = document.getElementById('empresa_id');
        return el ? parseInt(el.value, 10) || 0 : (CFG.empresaId || 0);
    }

    function maquinaIdActual() {
        if (CFG.modo === 'edit' && CFG.maquinaId) {
            return parseInt(CFG.maquinaId, 10) || 0;
        }
        const el = document.getElementById('maquinavending_id');
        return el ? parseInt(el.value, 10) || 0 : 0;
    }

    async function fetchJson(url, options) {
        const resp = await fetch(url, options || {});
        const data = await resp.json().catch(() => ({}));
        if (!resp.ok) {
            throw new Error(data.error || data.message || 'Error de comunicación');
        }
        return data;
    }

    function resolverIconoCuentacaja(cuenta) {
        if (!cuenta) {
            return { icono: 'fa fa-search', color: 'text-primary' };
        }
        return {
            icono: cuenta.icono || 'fa fa-search',
            color: cuenta.icono_color || 'text-primary',
        };
    }

    function htmlIconoMedio(info) {
        const icono = info && info.icono ? info.icono : 'fa fa-search';
        const color = info && info.color ? info.color : 'text-primary';
        if (icono.indexOf('gastro-icon-') === 0) {
            return '<span class="' + icono + '" aria-hidden="true"></span>';
        }
        return '<i class="' + icono + ' ' + color + '" aria-hidden="true"></i>';
    }

    function etiquetaCortaMedio(cuenta) {
        if (!cuenta) return 'Medio';
        if (cuenta.etiqueta_boton) return String(cuenta.etiqueta_boton);
        if (cuenta.codigo) return String(cuenta.codigo);
        const nombre = String(cuenta.nombre || '').trim();
        if (!nombre) return 'Medio';
        const palabras = nombre.split(/\s+/).filter(Boolean);
        return palabras.length <= 2 ? nombre : palabras.slice(0, 2).join(' ');
    }

    function actualizarIconoConsultaFila(tr, cuenta) {
        const btn = tr.querySelector('.consultacuentacaja');
        if (!btn) return;
        btn.querySelectorAll('i, .gastro-icon-mercadopago').forEach((el) => el.remove());
        btn.insertAdjacentHTML('afterbegin', htmlIconoMedio(resolverIconoCuentacaja(cuenta)));
    }

    function renderMediosRapidos() {
        const wrap = document.getElementById('mv-medios-rapidos');
        if (!wrap) return;
        wrap.innerHTML = '';
        if (!cuentasCaja.length) {
            wrap.classList.add('d-none');
            return;
        }
        cuentasCaja.forEach((cuenta) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-sm btn-outline-secondary mv-medio-rapido';
            btn.title = (cuenta.codigo ? cuenta.codigo + ' — ' : '') + (cuenta.nombre || '');
            btn.dataset.cuentacajaId = String(cuenta.id);
            btn.innerHTML = htmlIconoMedio(resolverIconoCuentacaja(cuenta)) +
                '<span>' + etiquetaCortaMedio(cuenta) + '</span>';
            btn.addEventListener('click', () => seleccionarMedioRapido(cuenta));
            wrap.appendChild(btn);
        });
        wrap.classList.remove('d-none');
    }

    function filaCobranzaTemplate() {
        const tpl = document.getElementById('mv-template-renglon-cuenta');
        if (!tpl || !tpl.content) return null;
        return tpl.content.firstElementChild.cloneNode(true);
    }

    function asignarCuentaEnFila(tr, cuenta) {
        if (!tr || !cuenta) return;
        tr.querySelector('.cuentacaja_id').value = cuenta.id;
        tr.querySelector('.codigo').value = cuenta.codigo || '';
        tr.querySelector('.nombre').value = cuenta.nombre || '';
        const monCell = tr.querySelector('.mv-cc-moneda');
        if (monCell) {
            monCell.textContent = cuenta.moneda_abreviatura || '—';
        }
        actualizarIconoConsultaFila(tr, cuenta);
        actualizarMontoSugerido(tr);
        sincronizarNamesMedios();
        recalcularTotales();
    }

    function agregarRenglonCobranza(enfocar) {
        const tr = filaCobranzaTemplate();
        if (!tr) return;
        const tbody = document.getElementById('tbody-mv-cuenta-table');
        tbody.appendChild(tr);
        wireFilaCobranza(tr);
        sincronizarNamesMedios();
        if (enfocar !== false) {
            const cod = tr.querySelector('.codigo');
            if (cod) cod.focus();
        }
    }

    function wireFilaCobranza(tr) {
        tr.querySelector('.mv-quitar-renglon-cuenta')?.addEventListener('click', () => {
            tr.remove();
            if (!document.querySelector('#tbody-mv-cuenta-table tr')) {
                agregarRenglonCobranza(false);
            }
            sincronizarNamesMedios();
            recalcularTotales();
        });
        tr.querySelector('.monto')?.addEventListener('input', function () {
            this.dataset.montoEditadoManual = '1';
            recalcularTotales();
        });
        tr.querySelector('.codigo')?.addEventListener('change', () => resolverCodigoCuenta(tr));
        tr.querySelector('.codigo')?.addEventListener('keydown', (ev) => {
            if (ev.key === 'Enter') {
                ev.preventDefault();
                resolverCodigoCuenta(tr).then(() => {
                    tr.querySelector('.monto')?.focus();
                });
            }
        });
    }

    async function resolverCodigoCuenta(tr) {
        const codigo = (tr.querySelector('.codigo')?.value || '').trim();
        if (!codigo) return;
        const found = cuentasCaja.find((c) => String(c.codigo).toLowerCase() === codigo.toLowerCase());
        if (found) {
            asignarCuentaEnFila(tr, found);
            return;
        }
    }

    function seleccionarMedioRapido(cuenta) {
        const tbody = document.getElementById('tbody-mv-cuenta-table');
        let tr = $$('#tbody-mv-cuenta-table tr').find((row) => !(row.querySelector('.cuentacaja_id')?.value || '').trim());
        if (!tr) {
            agregarRenglonCobranza(false);
            tr = tbody.querySelector('tr:last-child');
        }
        asignarCuentaEnFila(tr, cuenta);
        const montoInp = tr.querySelector('.monto');
        if (montoInp) {
            montoInp.focus();
            montoInp.select();
        }
    }

    function sincronizarNamesMedios() {
        indiceMedio = 0;
        $$('#tbody-mv-cuenta-table tr').forEach((tr) => {
            const ccId = tr.querySelector('.cuentacaja_id');
            const monto = tr.querySelector('.monto');
            const cot = tr.querySelector('.cotizacion');
            if (!ccId || !monto) return;
            const i = indiceMedio++;
            ccId.name = 'medios_pago[' + i + '][cuentacaja_id]';
            monto.name = 'medios_pago[' + i + '][monto]';
            if (cot) cot.name = 'medios_pago[' + i + '][cotizacion]';
        });
    }

    function totalCobradoExcluyendo(trExcluir) {
        let sum = 0;
        $$('#tbody-mv-cuenta-table tr').forEach((tr) => {
            if (tr === trExcluir) return;
            const monto = parseNum(tr.querySelector('.monto')?.value);
            const cot = parseNum(tr.querySelector('.cotizacion')?.value) || 1;
            sum += monto * cot;
        });
        return Math.round(sum * 100) / 100;
    }

    function totalCobrado() {
        let sum = 0;
        $$('#tbody-mv-cuenta-table tr').forEach((tr) => {
            const monto = parseNum(tr.querySelector('.monto')?.value);
            const cot = parseNum(tr.querySelector('.cotizacion')?.value) || 1;
            sum += monto * cot;
        });
        return Math.round(sum * 100) / 100;
    }

    function saldoPendiente(tr) {
        const tv = totalVentas();
        const cobradoOtros = totalCobradoExcluyendo(tr);
        return Math.max(0, Math.round((tv - cobradoOtros) * 100) / 100);
    }

    function montoVacioOEsSugerido(montoInp) {
        if (!montoInp || montoInp.dataset.montoEditadoManual === '1') {
            return false;
        }
        const val = (montoInp.value || '').trim();
        if (val === '') return true;
        const cur = parseNum(val);
        const prev = parseNum(montoInp.dataset.saldoValidacion || '');
        if (Math.abs(cur - prev) < TOLERANCIA) return true;
        return false;
    }

    function actualizarMontoSugerido(tr) {
        const montoInp = tr.querySelector('.monto');
        if (!montoInp) return;
        const cuentaId = (tr.querySelector('.cuentacaja_id')?.value || '').trim();
        const saldo = saldoPendiente(tr);
        if (totalVentas() > 0 && cuentaId && saldo > 0 && montoVacioOEsSugerido(montoInp)) {
            montoInp.value = saldo.toFixed(2);
            delete montoInp.dataset.montoEditadoManual;
        }
        if (totalVentas() > 0 && cuentaId) {
            montoInp.dataset.saldoValidacion = saldo.toFixed(2);
            montoInp.title = 'Saldo pendiente: ' + fmtMoney(saldo);
        } else {
            delete montoInp.dataset.saldoValidacion;
            montoInp.removeAttribute('title');
        }
    }

    function actualizarMontosSugeridosTodasFilas() {
        $$('#tbody-mv-cuenta-table tr').forEach(actualizarMontoSugerido);
    }

    function totalVentas() {
        let sum = 0;
        $$('#tbody-articulos-rendicion tr[data-articulo-id]').forEach((tr) => {
            sum += parseNum(tr.querySelector('.importe-linea')?.value);
        });
        return Math.round(sum * 100) / 100;
    }

    function recalcularTotales() {
        $$('#tbody-articulos-rendicion tr[data-articulo-id]').forEach((tr) => {
            const cant = parseNum(tr.querySelector('.cantidad-vendida')?.value);
            const precio = parseNum(tr.querySelector('.precio-lista-hidden')?.value);
            const imp = Math.round(cant * precio * 100) / 100;
            const impEl = tr.querySelector('.importe-linea');
            const impTxt = tr.querySelector('.importe-linea-txt');
            if (impEl) impEl.value = imp.toFixed(2);
            if (impTxt) impTxt.textContent = fmtMoney(imp);
        });

        actualizarMontosSugeridosTodasFilas();

        const tv = totalVentas();
        const tc = totalCobrado();
        const diff = Math.round((tc - tv) * 100) / 100;

        ['mv-total-ventas', 'mv-total-ventas-foot'].forEach((id) => {
            const el = document.getElementById(id);
            if (el) el.textContent = fmtMoney(tv);
        });

        const resumen = document.getElementById('mv-totales-cobranza');
        if (resumen) {
            let html = '<span>Cobrado: <strong>' + fmtMoney(tc) + '</strong></span>';
            if (Math.abs(diff) > TOLERANCIA) {
                html += ' <span class="mv-total-diff">(dif. ' + fmtMoney(diff) + ')</span>';
            } else if (tv > 0) {
                html += ' <span class="text-success"><i class="fa fa-check"></i> Cuadrado</span>';
            }
            resumen.innerHTML = html;
        }

        const alertDiff = document.getElementById('mv-alert-diferencias');
        if (alertDiff) {
            if (tv > 0 && Math.abs(diff) > TOLERANCIA) {
                alertDiff.textContent = 'El total cobrado (' + fmtMoney(tc) + ') no coincide con el total a rendir (' + fmtMoney(tv) + ').';
                alertDiff.classList.remove('d-none');
            } else {
                alertDiff.classList.add('d-none');
            }
        }

        const btn = document.getElementById('btn-guardar-rendicion');
        if (btn) {
            btn.disabled = !(tv > 0 && Math.abs(diff) <= TOLERANCIA);
        }
        const btnEditar = document.querySelector('#form-rendicion-vending button[type="submit"]');
        if (btnEditar && btnEditar.id !== 'btn-guardar-rendicion') {
            btnEditar.disabled = !(tv > 0 && Math.abs(diff) <= TOLERANCIA);
        }
    }

    function renderArticulos(articulos) {
        const tbody = document.getElementById('tbody-articulos-rendicion');
        tbody.innerHTML = '';
        indiceArticulo = 0;

        if (!articulos || !articulos.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-muted text-center p-3">La m&aacute;quina no tiene rulos configurados.</td></tr>';
            recalcularTotales();
            return;
        }

        articulos.forEach((art) => {
            const tr = document.createElement('tr');
            tr.dataset.articuloId = String(art.articulo_id);
            tr.dataset.numeroRulo = String(art.numero_rulo);
            const idx = indiceArticulo++;
            tr.innerHTML =
                '<td class="text-center">' + art.numero_rulo + '</td>' +
                '<td>' + (art.sku || '') + '</td>' +
                '<td>' + (art.descripcion || '') + '</td>' +
                '<td class="text-right">' + fmtMoney(art.precio_lista || 0) + '</td>' +
                '<td><input type="number" min="0" step="0.001" class="form-control form-control-sm cantidad-vendida" ' +
                'name="articulos[' + idx + '][cantidad]" value="0"></td>' +
                '<td class="text-right">' +
                '<span class="importe-linea-txt">$0,00</span>' +
                '<input type="hidden" class="importe-linea" value="0">' +
                '<input type="hidden" name="articulos[' + idx + '][numero_rulo]" value="' + art.numero_rulo + '">' +
                '<input type="hidden" name="articulos[' + idx + '][articulo_id]" value="' + art.articulo_id + '">' +
                '<input type="hidden" name="articulos[' + idx + '][precio_lista]" class="precio-lista-hidden" value="' + (art.precio_lista || 0) + '">' +
                '</td>';
            tbody.appendChild(tr);
            tr.querySelector('.cantidad-vendida')?.addEventListener('input', recalcularTotales);
        });
        recalcularTotales();
        if (CFG.modo !== 'edit') {
            enfocarPrimerRulo();
        }
    }

    function aplicarCantidadesGuardadas(guardadas) {
        if (!guardadas || !guardadas.length) {
            return;
        }
        const map = {};
        guardadas.forEach((a) => {
            map[String(a.numero_rulo)] = a;
            map['id_' + a.articulo_id] = a;
        });
        $$('#tbody-articulos-rendicion tr[data-articulo-id]').forEach((tr) => {
            const saved = map[tr.dataset.numeroRulo] || map['id_' + tr.dataset.articuloId];
            if (!saved) {
                return;
            }
            const inp = tr.querySelector('.cantidad-vendida');
            if (inp) {
                inp.value = saved.cantidad;
            }
            const precioHidden = tr.querySelector('.precio-lista-hidden');
            if (precioHidden && saved.precio_lista !== undefined && saved.precio_lista !== null) {
                precioHidden.value = saved.precio_lista;
            }
        });
        recalcularTotales();
    }

    function renderMediosGuardados(medios) {
        const tbody = document.getElementById('tbody-mv-cuenta-table');
        if (!tbody) {
            return;
        }
        tbody.innerHTML = '';
        if (!medios || !medios.length) {
            agregarRenglonCobranza(false);
            return;
        }
        medios.forEach((m) => {
            agregarRenglonCobranza(false);
            const tr = tbody.querySelector('tr:last-child');
            if (!tr) {
                return;
            }
            const cuenta = cuentasPorId[String(m.cuentacaja_id)];
            if (cuenta) {
                asignarCuentaEnFila(tr, cuenta);
            } else {
                tr.querySelector('.cuentacaja_id').value = m.cuentacaja_id || '';
                tr.querySelector('.codigo').value = m.codigo || '';
                tr.querySelector('.nombre').value = m.nombre || '';
            }
            const montoInp = tr.querySelector('.monto');
            if (montoInp) {
                montoInp.value = Number(m.monto || 0).toFixed(2);
                montoInp.dataset.montoEditadoManual = '1';
            }
            const cot = tr.querySelector('.cotizacion');
            if (cot) {
                cot.value = m.cotizacion || 1;
            }
        });
        sincronizarNamesMedios();
        recalcularTotales();
    }

    function enfocarPrimerRulo() {
        const inp = document.querySelector('#tbody-articulos-rendicion tr[data-articulo-id] .cantidad-vendida');
        if (!inp) return;
        requestAnimationFrame(() => {
            inp.focus();
            if (typeof inp.select === 'function') {
                inp.select();
            }
        });
    }

    async function cargarMaquinas(empresaId) {
        const sel = document.getElementById('maquinavending_id');
        if (!sel) return;
        const prev = sel.value;
        sel.innerHTML = '<option value="">— Seleccionar —</option>';
        if (empresaId <= 0) return;

        const url = String(CFG.urlMaquinas || '').replace('__EMP__', String(empresaId));
        try {
            const data = await fetchJson(url);
            (data.maquinas || []).forEach((m) => {
                const opt = document.createElement('option');
                opt.value = m.id;
                opt.textContent = m.etiqueta || m.nombre;
                sel.appendChild(opt);
            });
            if (prev && sel.querySelector('option[value="' + prev + '"]')) {
                sel.value = prev;
            }
        } catch (e) {
            console.warn(e);
        }
    }

    async function cargarArticulos() {
        const empresaId = empresaIdActual();
        const maquinaId = maquinaIdActual();
        const panel = document.getElementById('panel-rendicion-contenido');
        const aviso = document.getElementById('aviso-seleccion-maquina');

        if (empresaId <= 0 || maquinaId <= 0) {
            panel?.classList.add('d-none');
            aviso?.classList.remove('d-none');
            return;
        }

        panel?.classList.remove('d-none');
        aviso?.classList.add('d-none');

        document.getElementById('mv-empresa-id').value = String(empresaId);
        const mirror = document.getElementById('empresa_id_mirror');
        if (mirror) mirror.value = String(empresaId);
        window.GASTRONOMIA = window.GASTRONOMIA || {};
        window.GASTRONOMIA.empresaId = empresaId;
        window.GASTRONOMIA.usocuentacajaGastronomiaId = CFG.usocuentacajaGastronomiaId || 0;

        const base = carpetaBase();
        const fechaJornadaEl = document.getElementById('fecha_jornada');
        const fechaJornada = fechaJornadaEl && fechaJornadaEl.value
            ? '&fecha_jornada=' + encodeURIComponent(fechaJornadaEl.value)
            : '';
        const url = base + '/ventas/gastronomia/maquinas-vending/rendiciones/api/maquina/' +
            maquinaId + '/articulos?empresa_id=' + encodeURIComponent(empresaId) + fechaJornada;

        try {
            const data = await fetchJson(url);
            renderArticulos(data.articulos || []);
            if (CFG.modo === 'edit' && CFG.datosIniciales && CFG.datosIniciales.articulos) {
                aplicarCantidadesGuardadas(CFG.datosIniciales.articulos);
            }
        } catch (e) {
            renderArticulos([]);
            alert(e.message || 'No se pudieron cargar los artículos.');
        }

        await cargarCuentasCaja(empresaId);
    }

    async function cargarCuentasCaja(empresaId) {
        const base = carpetaBase();
        const url = base + '/ventas/gastronomia/maquinas-vending/rendiciones/api/cuentas-caja?empresa_id=' +
            encodeURIComponent(empresaId);
        try {
            const data = await fetchJson(url);
            cuentasCaja = data.cuentas_caja || [];
            cuentasPorId = {};
            cuentasCaja.forEach((c) => { cuentasPorId[String(c.id)] = c; });
            renderMediosRapidos();

            const tbody = document.getElementById('tbody-mv-cuenta-table');
            tbody.innerHTML = '';
            if (CFG.modo === 'edit' && CFG.datosIniciales && CFG.datosIniciales.medios_pago) {
                renderMediosGuardados(CFG.datosIniciales.medios_pago);
            } else {
                agregarRenglonCobranza(false);
            }
        } catch (e) {
            document.getElementById('tbody-mv-cuenta-table').innerHTML =
                '<tr><td colspan="4" class="text-danger text-center p-2">' + (e.message || 'Error') + '</td></tr>';
        }
    }

    function initEventos() {
        if (CFG.modo !== 'edit') {
            document.getElementById('empresa_id')?.addEventListener('change', async function () {
                await cargarMaquinas(empresaIdActual());
                document.getElementById('maquinavending_id').value = '';
                document.getElementById('panel-rendicion-contenido')?.classList.add('d-none');
                document.getElementById('aviso-seleccion-maquina')?.classList.remove('d-none');
            });

            document.getElementById('maquinavending_id')?.addEventListener('change', () => {
                void cargarArticulos();
            });
        }

        document.getElementById('fecha_jornada')?.addEventListener('change', () => {
            if (maquinaIdActual() > 0) {
                void cargarArticulos();
            }
        });

        document.getElementById('mv-agrega-renglon-cuenta')?.addEventListener('click', () => {
            agregarRenglonCobranza(true);
        });

        document.getElementById('form-rendicion-vending')?.addEventListener('submit', (ev) => {
            sincronizarNamesMedios();
            const tv = totalVentas();
            const tc = totalCobrado();
            if (tv <= 0) {
                ev.preventDefault();
                alert('Indique cantidades vendidas.');
                return;
            }
            if (Math.abs(tv - tc) > TOLERANCIA) {
                ev.preventDefault();
                alert('Los medios de pago deben cuadrar con el total a rendir.');
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initEventos();

        if (typeof activa_eventos_consultacuentacaja === 'function') {
            activa_eventos_consultacuentacaja();
        }

        document.body.addEventListener('click', function (ev) {
            const btn = ev.target.closest('.eligecuentacaja, .eligeconsultacuentacaja');
            if (!btn) return;
            const tr = btn.closest('tr.item-cuenta-mv');
            if (!tr) return;
            const cuentaId = btn.dataset.id || btn.getAttribute('data-id') || tr.querySelector('.cuentacaja_id')?.value;
            const cuenta = cuentasPorId[String(cuentaId)];
            if (cuenta) {
                asignarCuentaEnFila(tr, cuenta);
            }
        });

        const empresaId = empresaIdActual();
        if (CFG.modo === 'edit' && empresaId > 0 && maquinaIdActual() > 0) {
            document.getElementById('aviso-seleccion-maquina')?.classList.add('d-none');
            void cargarArticulos();
        } else if (empresaId > 0 && maquinaIdActual() > 0) {
            void cargarArticulos();
        } else if (empresaId > 0) {
            void cargarMaquinas(empresaId).then(() => {
                if (maquinaIdActual() > 0) void cargarArticulos();
            });
        }
    });
})();
