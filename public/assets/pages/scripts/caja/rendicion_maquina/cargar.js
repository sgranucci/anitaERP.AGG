(function () {
    'use strict';

    var app = document.getElementById('rendicion-maquina-app');
    if (!app) {
        return;
    }

    var carpetaBase = (typeof window.carpetaBase !== 'undefined' && window.carpetaBase) ? window.carpetaBase : '';
    var debounceTimer = null;
    var wigosDebounceTimer = null;
    var wigosOriginales = {};
    /** Semillas Completo (fondo_cierre/resultado/transfer) que no tienen input en pantalla. */
    var orquestadorCompletoExtra = {};
    var ajustesPendientes = [];
    var recargandoLineas = false;
    var wigosLeidoOk = false;
    var wigosEnCurso = false;
    /** Descarta respuestas viejas si el usuario cambia empresa/fecha/turno a mitad de lectura. */
    var wigosRequestSeq = 0;
    var esAlta = app.dataset.modoEdicion !== '1';

    function fmtMoney(n) {
        return Number(n || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function parseNum(val) {
        if (val == null || val === '') {
            return 0;
        }
        if (typeof val === 'number') {
            return isNaN(val) ? 0 : Math.round(val * 100) / 100;
        }
        var t = String(val).trim().replace(/\s/g, '');
        if (t.indexOf(',') >= 0) {
            t = t.replace(/\./g, '').replace(',', '.');
        } else if (/^\d{1,3}(\.\d{3})+$/.test(t)) {
            t = t.replace(/\./g, '');
        }
        var n = parseFloat(t);
        return isNaN(n) ? 0 : Math.round(n * 100) / 100;
    }

    function formatearInputMonto(el) {
        if (!el) {
            return;
        }
        if (el.value === '' || el.value == null) {
            el.value = '';
            return;
        }
        el.value = fmtMoney(parseNum(el.value));
    }

    function desformatearInputMonto(el) {
        if (!el || el.value === '') {
            return;
        }
        el.value = String(parseNum(el.value));
    }

    function esInputMonto(el) {
        if (!el || !el.classList) {
            return false;
        }
        return el.classList.contains('js-input-wigos')
            || el.classList.contains('js-input-manual')
            || el.classList.contains('js-valor-monto')
            || el.classList.contains('js-gasto-monto')
            || el.classList.contains('js-calc-orq')
            || el.classList.contains('js-monto-ar');
    }

    function initFormatoMontos(root) {
        var scope = root || app;
        scope.querySelectorAll('.js-monto-ar, .js-input-wigos, .js-input-manual, .js-valor-monto, .js-gasto-monto, .js-calc-orq')
            .forEach(formatearInputMonto);
    }

    function escapeHtml(text) {
        return String(text || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function getEmpresaId() {
        var el = document.getElementById('empresa_id');
        return el ? parseInt(el.value, 10) || 0 : parseInt(app.dataset.empresaId || '0', 10);
    }

    function getFecha() {
        var el = document.getElementById('fecha_rendicion');
        return el ? el.value : (app.dataset.fecha || '');
    }

    function getTurno() {
        var el = document.getElementById('turno_rendicion');
        return el ? el.value : (app.dataset.turno || 'M');
    }

    function claseBadgeTurno(turno) {
        if (turno === 'C') {
            return 'badge-warning';
        }
        if (turno === 'N') {
            return 'badge-dark';
        }
        if (turno === 'T') {
            return 'badge-info';
        }
        return 'badge-primary';
    }

    function actualizarBadgeTurno() {
        var badge = document.getElementById('rendmaq-badge-turno');
        if (!badge) {
            return;
        }
        var turno = getTurno();
        badge.textContent = 'Turno ' + turno;
        badge.classList.remove('badge-primary', 'badge-info', 'badge-dark', 'badge-warning');
        badge.classList.add(claseBadgeTurno(turno));
        var avisoQr = document.getElementById('aviso-precarga-qr-maquinas');
        if (avisoQr) {
            avisoQr.style.display = turno === 'M' ? '' : 'none';
        }
    }

    function recolectarInputs() {
        var inputs = {};
        app.querySelectorAll('.js-input-wigos, .js-input-manual').forEach(function (inp) {
            var clave = inp.dataset.clave || inp.dataset.campo || '';
            if (clave.indexOf('inputs.') === 0) {
                clave = clave.substring(7);
            }
            if (clave) {
                inputs[clave] = parseNum(inp.value);
            }
        });
        return inputs;
    }

    function recolectarValores() {
        var lineas = [];
        app.querySelectorAll('#tabla-valores-rendicion tbody tr[data-cuentacaja-id]').forEach(function (tr) {
            var cot = parseFloat(tr.dataset.cotizacion || '0');
            if (!isFinite(cot) || cot <= 0) {
                cot = 1;
            }
            lineas.push({
                cuentacaja_id: parseInt(tr.dataset.cuentacajaId, 10),
                monto: parseNum(tr.querySelector('.js-valor-monto')?.value),
                cotizacion: cot,
                moneda_id: parseInt(tr.dataset.monedaId || '1', 10) || 1
            });
        });
        return lineas;
    }

    /** Actualiza data-cotizacion de filas existentes según cotizacion_tesoreria de la fecha. */
    function refrescarCotizacionesValores() {
        var empresaId = getEmpresaId();
        var fecha = getFecha();
        if (empresaId <= 0 || !fecha || !app.dataset.apiLineasEmpresa) {
            return;
        }
        var montos = {};
        recolectarValores().forEach(function (l) {
            montos[l.cuentacaja_id] = l.monto;
        });
        postJson(app.dataset.apiLineasEmpresa, { empresa_id: empresaId, fecha: fecha })
            .then(function (data) {
                if (!data.ok) {
                    return;
                }
                var lineas = (data.cuentas_valor || []).map(function (linea) {
                    var id = parseInt(linea.cuentacaja_id, 10) || 0;
                    if (Object.prototype.hasOwnProperty.call(montos, id)) {
                        linea.monto = montos[id];
                    }
                    return linea;
                });
                renderValores(lineas);
                calcularDebounced();
            })
            .catch(function () {
                // Silencioso: el guardado backend también resuelve cotización
            });
    }

    function recolectarGastos() {
        var lineas = [];
        app.querySelectorAll('#tabla-gastos-rendicion tbody tr[data-apertura-gasto-id]').forEach(function (tr) {
            lineas.push({
                apertura_gasto_id: parseInt(tr.dataset.aperturaGastoId, 10),
                monto: parseNum(tr.querySelector('.js-gasto-monto')?.value)
            });
        });
        return lineas;
    }

    function recolectarCalcOrquestador() {
        var orq = {
            comprobante: parseNum(document.getElementById('calc_comprobante')?.value),
            vale_rep_fondo: parseNum(document.getElementById('calc_vale_rep_fondo')?.value)
        };
        if (getTurno() === 'C' && orquestadorCompletoExtra) {
            if (orquestadorCompletoExtra.fondo_cierre !== undefined) {
                orq.fondo_cierre = orquestadorCompletoExtra.fondo_cierre;
            }
            if (orquestadorCompletoExtra.resultado_turno !== undefined) {
                orq.resultado_turno = orquestadorCompletoExtra.resultado_turno;
            }
            if (orquestadorCompletoExtra.transferencia !== undefined) {
                orq.transferencia = orquestadorCompletoExtra.transferencia;
            }
        }
        return orq;
    }

    function pintarTotales(totales) {
        if (!totales) {
            return;
        }
        app.querySelectorAll('[data-total]').forEach(function (el) {
            var key = el.dataset.total;
            if (totales[key] !== undefined) {
                el.textContent = '$' + fmtMoney(totales[key]);
            }
        });
        var depCalc = document.getElementById('calc_deposito');
        if (depCalc && totales.deposito !== undefined) {
            depCalc.value = fmtMoney(totales.deposito);
        }
        var ffCalc = document.getElementById('calc_fondo_fijo');
        if (ffCalc && totales.fondo_fijo !== undefined) {
            ffCalc.value = fmtMoney(totales.fondo_fijo);
        }
        var wrapDif = document.getElementById('wrap-dif-caja');
        if (wrapDif) {
            if (getTurno() === 'C') {
                wrapDif.classList.remove('d-none');
            } else {
                wrapDif.classList.add('d-none');
            }
        }
    }

    /** Pie sticky a cero (alta / cambio cabecera / Ctrl+R con bfcache). */
    function blanquearTotales() {
        pintarTotales({
            fondo_inicial: 0,
            comprobante: 0,
            fondo_fijo: 0,
            venta_ficha: 0,
            venta_ruleta: 0,
            total_ventas: 0,
            win: 0,
            drop_billete_bruto: 0,
            impuesto_drop: 0,
            drop_bill_rodillo: 0,
            drop_bill_ruleta: 0,
            dropqr_rodillo: 0,
            total_ingreso: 0,
            total_salida: 0,
            resultado_turno: 0,
            fondo_cierre: 0,
            transferencia: 0,
            dif_caja: 0,
            deposito: 0
        });
    }

    function blanquearFondoInput() {
        var inpFondo = document.getElementById('input_fondo_inicial');
        if (inpFondo) {
            inpFondo.value = fmtMoney(0);
        }
        var inpVale = document.getElementById('calc_vale_rep_fondo');
        if (inpVale) {
            inpVale.value = fmtMoney(0);
        }
        var inpComp = document.getElementById('calc_comprobante');
        if (inpComp) {
            inpComp.value = fmtMoney(0);
        }
    }

    function ordenarPorCodigoNumerico(lineas) {
        return (lineas || []).slice().sort(function (a, b) {
            var ca = String(a.codigo || '').trim();
            var cb = String(b.codigo || '').trim();
            var na = /^\d+$/.test(ca) ? parseInt(ca, 10) : null;
            var nb = /^\d+$/.test(cb) ? parseInt(cb, 10) : null;
            if (na !== null && nb !== null && na !== nb) {
                return na - nb;
            }
            if (na !== null && nb === null) {
                return -1;
            }
            if (na === null && nb !== null) {
                return 1;
            }
            return ca.localeCompare(cb, 'es', { numeric: true, sensitivity: 'base' });
        });
    }

    function renderValores(lineas) {
        var tbody = document.querySelector('#tabla-valores-rendicion tbody');
        if (!tbody) {
            return;
        }
        if (!lineas || !lineas.length) {
            tbody.innerHTML = '<tr class="js-fila-vacia"><td colspan="3" class="text-muted text-center py-3">Sin cuentas con uso «Rendición de máquinas»</td></tr>';
            return;
        }
        tbody.innerHTML = ordenarPorCodigoNumerico(lineas).map(function (linea) {
            var id = parseInt(linea.cuentacaja_id, 10) || 0;
            var codigo = escapeHtml(linea.codigo || '');
            var nombreRaw = linea.nombre || '';
            var nombre = escapeHtml(nombreRaw);
            var monto = fmtMoney(linea.monto || 0);
            var cot = parseFloat(linea.cotizacion);
            if (!isFinite(cot) || cot <= 0) {
                cot = 1;
            }
            var monedaId = parseInt(linea.moneda_id, 10) || 1;
            var title = nombreRaw;
            if (monedaId > 1) {
                title += ' — moneda extranjera × cotización ' + cot;
            }
            return '<tr data-cuentacaja-id="' + id + '" data-cotizacion="' + cot + '" data-moneda-id="' + monedaId + '">'
                + '<td class="text-muted col-codigo">' + codigo + '</td>'
                + '<td class="col-desc" title="' + escapeHtml(title) + '">' + nombre + '</td>'
                + '<td class="col-monto"><input type="text" inputmode="decimal" class="form-control form-control-sm text-right js-valor-monto js-monto-ar" autocomplete="off" value="' + monto + '"'
                + (monedaId > 1 ? ' title="' + escapeHtml(title) + '"' : '')
                + '></td>'
                + '</tr>';
        }).join('');
        initFormatoMontos(tbody);
    }

    /**
     * Turno mañana: pisa solo TotalCoin QR Máquinas (drop QR rodillo + impuesto QR).
     * No regenera la grilla para no borrar el resto de valores ya cargados.
     */
    function aplicarPrecargaValores(lineas) {
        (lineas || []).forEach(function (linea) {
            var id = parseInt(linea.cuentacaja_id, 10) || 0;
            if (id <= 0) {
                return;
            }
            var tr = app.querySelector('#tabla-valores-rendicion tbody tr[data-cuentacaja-id="' + id + '"]');
            if (!tr) {
                return;
            }
            var inp = tr.querySelector('.js-valor-monto');
            if (!inp) {
                return;
            }
            inp.value = fmtMoney(linea.monto || 0);
            inp.title = 'Precargado: drop QR rodillo + impuesto QR (WIGOS). Editable.';
        });
    }

    function recolectarGastos() {
        var lineas = [];
        app.querySelectorAll('#tabla-gastos-rendicion tbody tr[data-apertura-gasto-id]').forEach(function (tr) {
            lineas.push({
                apertura_gasto_id: parseInt(tr.dataset.aperturaGastoId, 10),
                monto: parseNum(tr.querySelector('.js-gasto-monto')?.value)
            });
        });
        return lineas;
    }

    function renderGastos(lineas) {
        var tbody = document.querySelector('#tabla-gastos-rendicion tbody');
        if (!tbody) {
            return;
        }
        if (!lineas || !lineas.length) {
            tbody.innerHTML = '<tr class="js-fila-vacia"><td colspan="3" class="text-muted text-center py-3">Sin aperturas de gasto activas para la empresa</td></tr>';
            return;
        }
        tbody.innerHTML = ordenarPorCodigoNumerico(lineas).map(function (linea) {
            var id = parseInt(linea.apertura_gasto_id, 10) || 0;
            var codigo = escapeHtml(linea.codigo || '');
            var nombre = escapeHtml(linea.nombre || '');
            var monto = fmtMoney(linea.monto || 0);
            return '<tr data-apertura-gasto-id="' + id + '">'
                + '<td class="text-muted col-codigo">' + codigo + '</td>'
                + '<td class="col-desc" title="' + nombre + '">' + nombre + '</td>'
                + '<td class="col-monto"><input type="text" inputmode="decimal" class="form-control form-control-sm text-right js-gasto-monto js-monto-ar" autocomplete="off" value="' + monto + '"></td>'
                + '</tr>';
        }).join('');
    }

    /**
     * route() puede devolver URL absoluta sin app_carpeta (/anitaERP/public).
     * Normaliza a path usable con carpetaBase.
     */
    function resolverUrlApi(url) {
        var raw = String(url || '').trim();
        if (!raw) {
            return carpetaBase || '';
        }

        var path = raw;
        if (/^https?:\/\//i.test(raw)) {
            try {
                path = new URL(raw).pathname + (new URL(raw).search || '');
            } catch (e) {
                return raw;
            }
        }

        if (carpetaBase && path.indexOf(carpetaBase) === 0) {
            return path;
        }

        if (path.charAt(0) !== '/') {
            path = '/' + path;
        }

        return (carpetaBase || '') + path;
    }

    function postJson(url, payload) {
        return fetch(resolverUrlApi(url), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': app.dataset.csrf,
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload)
        }).then(function (r) {
            return r.text().then(function (text) {
                var data = null;
                try {
                    data = text ? JSON.parse(text) : {};
                } catch (e) {
                    throw new Error('Respuesta inválida del servidor (¿sesión vencida o ruta incorrecta?).');
                }
                if (!r.ok) {
                    throw new Error((data && (data.error || data.message)) || 'Error en la solicitud');
                }
                return data;
            });
        });
    }

    function calcular() {
        var empresaId = getEmpresaId();
        if (empresaId <= 0 || recargandoLineas) {
            return;
        }

        postJson(app.dataset.apiCalcular, {
            empresa_id: empresaId,
            fecha: getFecha(),
            turno: getTurno(),
            inputs: recolectarInputs(),
            valores: recolectarValores(),
            gastos: recolectarGastos(),
            calc_orquestador: recolectarCalcOrquestador()
        }).then(function (data) {
            if (data.ok && data.totales) {
                pintarTotales(data.totales);
            }
        }).catch(function (err) {
            console.warn('Calcular rendición:', err.message);
        });
    }

    function calcularDebounced() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(calcular, 400);
    }

    function puedeDispararWigos() {
        return getEmpresaId() > 0 && !!getFecha() && !!getTurno();
    }

    function actualizarAvisoWigos() {
        var aviso = document.getElementById('aviso-wigos-pendiente');
        var progreso = document.getElementById('aviso-wigos-progreso');
        if (progreso) {
            progreso.style.display = wigosEnCurso ? 'block' : 'none';
        }
        if (!aviso) {
            return;
        }
        // Pendiente solo en alta, si aún no hay lectura OK y no hay lectura en curso
        aviso.style.display = (esAlta && !wigosLeidoOk && !wigosEnCurso) ? 'block' : 'none';
    }

    function marcarWigosLeido(ok) {
        wigosLeidoOk = !!ok;
        actualizarAvisoWigos();
    }

    function setWigosEnCurso(enCurso) {
        wigosEnCurso = !!enCurso;
        var btn = document.getElementById('btn-traer-wigos');
        if (btn) {
            // No bloquear el resto del formulario: solo feedback en el botón
            btn.disabled = wigosEnCurso;
            btn.innerHTML = wigosEnCurso
                ? '<i class="fa fa-spinner fa-spin"></i> Leyendo WIGOS…'
                : '<i class="fa fa-cloud-download"></i> Traer WIGOS';
        }
        actualizarAvisoWigos();
    }

    function traerWigosDebounced(motivo) {
        clearTimeout(wigosDebounceTimer);
        // Mientras llega WIGOS, no dejar totales/fondo del turno/fecha anterior
        if (esAlta) {
            blanquearTotales();
            blanquearFondoInput();
        }
        wigosDebounceTimer = setTimeout(function () {
            traerWigos({ silencioso: true, motivo: motivo || 'cambio' });
        }, 450);
    }

    function recargarLineasPorEmpresa() {
        var empresaId = getEmpresaId();
        app.dataset.empresaId = String(empresaId);
        marcarWigosLeido(false);
        if (esAlta) {
            blanquearTotales();
            blanquearFondoInput();
        }

        if (app.dataset.modoEdicion === '1') {
            if (puedeDispararWigos()) {
                traerWigosDebounced('empresa');
            } else {
                calcularDebounced();
            }
            return;
        }

        if (empresaId <= 0) {
            renderValores([]);
            renderGastos([]);
            blanquearTotales();
            actualizarAvisoWigos();
            return;
        }

        recargandoLineas = true;
        postJson(app.dataset.apiLineasEmpresa, { empresa_id: empresaId, fecha: getFecha() })
            .then(function (data) {
                if (data.ok) {
                    renderValores(data.cuentas_valor || []);
                    renderGastos(data.gastos || []);
                }
            })
            .catch(function (err) {
                alert(err.message || 'No se pudieron cargar las cuentas de la empresa.');
            })
            .finally(function () {
                recargandoLineas = false;
                if (puedeDispararWigos()) {
                    traerWigosDebounced('empresa');
                } else {
                    calcularDebounced();
                }
            });
    }

    function marcarAjuste(inp) {
        if (app.dataset.puedeAjustar !== '1') {
            return;
        }
        var campo = inp.dataset.campo;
        if (!campo) {
            return;
        }
        var wigos = wigosOriginales[campo];
        if (wigos === undefined) {
            return;
        }
        var ajustado = parseNum(inp.value);
        if (Math.abs(wigos - ajustado) < 0.005) {
            inp.classList.remove('input-wigos-ajustable');
            ajustesPendientes = ajustesPendientes.filter(function (a) { return a.campo !== campo; });
            return;
        }
        inp.classList.add('input-wigos-ajustable');
        var existente = ajustesPendientes.findIndex(function (a) { return a.campo === campo; });
        var reg = { campo: campo, valor_wigos: wigos, valor_ajustado: ajustado, motivo: null };
        if (existente >= 0) {
            ajustesPendientes[existente] = reg;
        } else {
            ajustesPendientes.push(reg);
        }
    }

    function aplicarWigos(data) {
        var inputs = data.inputs || {};
        wigosOriginales = {};
        app.querySelectorAll('.js-input-wigos').forEach(function (inp) {
            var clave = inp.dataset.clave;
            var campo = inp.dataset.campo;
            var val = inputs[clave] !== undefined ? inputs[clave] : 0;
            inp.value = fmtMoney(val);
            if (campo) {
                wigosOriginales[campo] = parseNum(val);
            }
            inp.classList.remove('input-wigos-ajustable');
        });
        // Fondo / vale / comprobante: previas de la fecha pedida (no conservar día anterior)
        if (inputs.fondo_inicial !== undefined) {
            var inpFondo = document.getElementById('input_fondo_inicial');
            if (inpFondo) {
                inpFondo.value = fmtMoney(inputs.fondo_inicial);
            }
        }
        var orq = data.calc_orquestador || {};
        if (orq.comprobante !== undefined) {
            var inpComp = document.getElementById('calc_comprobante');
            if (inpComp) {
                inpComp.value = fmtMoney(orq.comprobante);
            }
        }
        if (orq.vale_rep_fondo !== undefined) {
            var inpVale = document.getElementById('calc_vale_rep_fondo');
            if (inpVale) {
                inpVale.value = fmtMoney(orq.vale_rep_fondo);
            }
        }
        // Completo: semillas Noche / suma transferencias (sin input en pantalla)
        if (getTurno() === 'C') {
            orquestadorCompletoExtra = {
                fondo_cierre: parseNum(orq.fondo_cierre),
                resultado_turno: parseNum(orq.resultado_turno),
                transferencia: parseNum(orq.transferencia)
            };
        } else {
            orquestadorCompletoExtra = {};
        }
        // Completo: valores/gastos consolidados de M/T/N (lee_rendiciones_del_dia)
        if (Array.isArray(data.valores)) {
            renderValores(data.valores);
        }
        if (Array.isArray(data.precarga_valores)) {
            aplicarPrecargaValores(data.precarga_valores);
        }
        if (Array.isArray(data.gastos)) {
            renderGastos(data.gastos);
        }
        // Manuales / previas que el consolidado Completo también trae
        app.querySelectorAll('.js-input-manual[data-clave]').forEach(function (inp) {
            var clave = inp.dataset.clave;
            if (clave && inputs[clave] !== undefined) {
                inp.value = fmtMoney(inputs[clave]);
            }
        });
        ajustesPendientes = [];
        calcularDebounced();
    }

    /**
     * Lectura WIGOS en segundo plano: no bloquea valores/gastos/manuales/usuarios.
     * Si el usuario cambia empresa/fecha/turno, una nueva lectura invalida la anterior.
     *
     * @param {{ silencioso?: boolean, motivo?: string }|undefined} opts
     */
    function traerWigos(opts) {
        opts = opts || {};
        if (!puedeDispararWigos()) {
            if (!opts.silencioso) {
                alert('Para leer WIGOS complete empresa, fecha y turno.');
            }
            return;
        }

        var seq = ++wigosRequestSeq;
        var empresaIdReq = getEmpresaId();
        var fechaReq = getFecha();
        var turnoReq = getTurno();

        setWigosEnCurso(true);
        postJson(app.dataset.apiTraerWigos, {
            empresa_id: empresaIdReq,
            fecha: fechaReq,
            turno: turnoReq,
            rendicion_id: parseInt(app.dataset.rendicionId || '0', 10) || null,
            inputs: recolectarInputs()
        }).then(function (data) {
            // Respuesta obsoleta (cambió cabecera o hay otra lectura más nueva)
            if (seq !== wigosRequestSeq) {
                return;
            }
            if (
                empresaIdReq !== getEmpresaId()
                || fechaReq !== getFecha()
                || turnoReq !== getTurno()
            ) {
                return;
            }
            if (data.ok || (data.inputs && Object.keys(data.inputs).length)) {
                aplicarWigos(data);
            }
            var stub = !!(data.meta && data.meta.stub);
            marcarWigosLeido(!stub);
            if (stub && !opts.silencioso) {
                alert(data.meta.mensaje || data.mensaje || 'No se pudo leer WIGOS.');
            }
        }).catch(function (err) {
            if (seq !== wigosRequestSeq) {
                return;
            }
            marcarWigosLeido(false);
            if (!opts.silencioso) {
                alert(err.message);
            }
        }).finally(function () {
            // Solo apagar el spinner si esta era la lectura vigente
            if (seq === wigosRequestSeq) {
                setWigosEnCurso(false);
            }
        });
    }

    function mostrarOverlayGuardando() {
        var overlay = document.getElementById('rendmaq-guardando-overlay');
        if (!overlay) {
            return;
        }
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }

    function ocultarOverlayGuardando() {
        var overlay = document.getElementById('rendmaq-guardando-overlay');
        if (!overlay) {
            return;
        }
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }

    function guardar() {
        var empresaId = getEmpresaId();
        if (empresaId <= 0) {
            alert('Seleccione empresa.');
            return;
        }

        if (esAlta && !wigosLeidoOk) {
            var seguir = window.confirm(
                'Todavía no se importaron (o falló) los datos WIGOS.\n\n'
                + 'Si guarda así, los drops/tito/ventas pueden quedar en cero.\n\n'
                + '¿Desea guardar de todos modos?'
            );
            if (!seguir) {
                return;
            }
        }

        var btnGuardar = document.getElementById('btn-guardar-rendicion');
        if (btnGuardar) {
            btnGuardar.disabled = true;
        }
        mostrarOverlayGuardando();

        var payload = {
            id: parseInt(app.dataset.rendicionId || '0', 10) || null,
            empresa_id: empresaId,
            fecha: getFecha(),
            turno: getTurno(),
            inputs: recolectarInputs(),
            valores: recolectarValores(),
            gastos: recolectarGastos(),
            calc_orquestador: recolectarCalcOrquestador(),
            wigos_json: wigosOriginales,
            supervisor_usuario_id: document.getElementById('supervisor_usuario_id')?.value || null,
            auxiliar_usuario_id: document.getElementById('auxiliar_usuario_id')?.value || null,
            cajero_usuario_id: document.getElementById('cajero_usuario_id')?.value || null,
            observacion: document.getElementById('observacion_rendicion')?.value || '',
            ajustes: ajustesPendientes
        };

        postJson(app.dataset.apiGuardar, payload).then(function (data) {
            if (data.ok) {
                if (data.url_comprobante_pdf) {
                    window.open(data.url_comprobante_pdf, '_blank');
                }
                window.location.href = data.url_index || app.dataset.urlIndex;
                return;
            }
            throw new Error((data && (data.error || data.message)) || 'No se pudo guardar.');
        }).catch(function (err) {
            ocultarOverlayGuardando();
            if (btnGuardar) {
                btnGuardar.disabled = false;
            }
            alert(err.message);
        });
    }

    function verLogAjustes() {
        var params = new URLSearchParams({
            empresa_id: String(getEmpresaId()),
            fecha: getFecha(),
            turno: getTurno(),
            rendicion_maquina_id: app.dataset.rendicionId || '0'
        });
        fetch(resolverUrlApi(app.dataset.apiAjustes) + '?' + params.toString(), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (r) {
            return r.text().then(function (text) {
                try {
                    return text ? JSON.parse(text) : {};
                } catch (e) {
                    throw new Error('Respuesta inválida del servidor al leer ajustes.');
                }
            });
        }).then(function (data) {
            var tbody = document.getElementById('tbody-log-ajustes-wigos');
            if (!tbody) {
                return;
            }
            tbody.innerHTML = '';
            (data.ajustes || []).forEach(function (row) {
                var tr = document.createElement('tr');
                tr.innerHTML = '<td>' + (row.created_at || '') + '</td>'
                    + '<td>' + (row.etiqueta || row.campo || '') + '</td>'
                    + '<td class="text-right">' + fmtMoney(row.valor_wigos) + '</td>'
                    + '<td class="text-right">' + fmtMoney(row.valor_ajustado) + '</td>'
                    + '<td class="text-right">' + fmtMoney(row.delta) + '</td>'
                    + '<td>' + (row.usuario || '') + '</td>';
                tbody.appendChild(tr);
            });
            $('#modal-log-ajustes-wigos').modal('show');
        }).catch(function (err) {
            alert(err.message);
        });
    }

    // Delegación: sobrevive al regenerar filas de valores/gastos
    app.addEventListener('focusin', function (event) {
        var el = event.target;
        if (esInputMonto(el)) {
            desformatearInputMonto(el);
            if (typeof el.select === 'function') {
                el.select();
            }
        }
    });

    app.addEventListener('focusout', function (event) {
        var el = event.target;
        if (esInputMonto(el)) {
            formatearInputMonto(el);
        }
    });

    app.addEventListener('input', function (event) {
        var el = event.target;
        if (!el || !el.classList) {
            return;
        }
        if (el.classList.contains('js-input-wigos')) {
            marcarAjuste(el);
            calcularDebounced();
            return;
        }
        if (
            el.classList.contains('js-input-manual')
            || el.classList.contains('js-valor-monto')
            || el.classList.contains('js-gasto-monto')
            || el.classList.contains('js-calc-orq')
        ) {
            calcularDebounced();
        }
    });

    document.getElementById('empresa_id')?.addEventListener('change', recargarLineasPorEmpresa);
    document.getElementById('fecha_rendicion')?.addEventListener('change', function () {
        app.dataset.fecha = getFecha();
        marcarWigosLeido(false);
        if (esAlta) {
            blanquearTotales();
            blanquearFondoInput();
            // Recarga cotizaciones de tesorería para la nueva fecha (conserva montos)
            refrescarCotizacionesValores();
        }
        if (puedeDispararWigos()) {
            traerWigosDebounced('fecha');
        } else {
            calcularDebounced();
        }
    });
    document.getElementById('turno_rendicion')?.addEventListener('change', function () {
        app.dataset.turno = getTurno();
        actualizarBadgeTurno();
        marcarWigosLeido(false);
        if (esAlta) {
            blanquearTotales();
            blanquearFondoInput();
        }
        if (getTurno() === 'C') {
            document.getElementById('wrap-dif-caja')?.classList.remove('d-none');
        } else {
            document.getElementById('wrap-dif-caja')?.classList.add('d-none');
        }
        if (puedeDispararWigos()) {
            traerWigosDebounced('turno');
        } else {
            calcularDebounced();
        }
    });

    document.getElementById('btn-traer-wigos')?.addEventListener('click', function () {
        traerWigos({ silencioso: false, motivo: 'boton' });
    });
    document.getElementById('btn-guardar-rendicion')?.addEventListener('click', guardar);
    document.getElementById('btn-ver-log-ajustes')?.addEventListener('click', verLogAjustes);

    function esTeclaF1(e) {
        return e && (e.key === 'F1' || e.code === 'F1' || e.keyCode === 112);
    }

    function esTeclaEnter(e) {
        return e && (e.key === 'Enter' || e.keyCode === 13 || e.which === 13);
    }

    function modalAbierto(selector) {
        var m = document.querySelector(selector);
        return !!(m && m.classList.contains('show'));
    }

    function hayModalAbierto() {
        return modalAbierto('#consultausuarioModal')
            || modalAbierto('#modal-log-ajustes-wigos')
            || !!document.querySelector('.modal.show');
    }

    function esCampoNav(el) {
        if (!el || el.disabled || el.readOnly) {
            return false;
        }
        if (el.tagName === 'TEXTAREA') {
            return false;
        }
        if (el.tagName === 'SELECT') {
            return el.id === 'empresa_id' || el.id === 'turno_rendicion';
        }
        if (el.tagName !== 'INPUT') {
            return false;
        }
        if (el.type === 'hidden' || el.type === 'button' || el.type === 'submit') {
            return false;
        }
        if (el.classList.contains('usuario_codigo_arbol')) {
            return true;
        }
        if (el.classList.contains('nombreusuario')) {
            return false;
        }
        return el.id === 'fecha_rendicion'
            || el.classList.contains('js-input-wigos')
            || el.classList.contains('js-input-manual')
            || el.classList.contains('js-valor-monto')
            || el.classList.contains('js-gasto-monto')
            || el.classList.contains('js-calc-orq');
    }

    function listarCamposNav() {
        var nodos = app.querySelectorAll(
            '#empresa_id, #fecha_rendicion, #turno_rendicion, '
            + '.usuario_codigo_arbol, '
            + '.js-input-wigos, .js-input-manual, '
            + '.js-valor-monto, .js-gasto-monto, .js-calc-orq'
        );
        var out = [];
        nodos.forEach(function (el) {
            if (esCampoNav(el) && el.offsetParent !== null) {
                out.push(el);
            }
        });
        return out;
    }

    function enfocarCampo(el) {
        if (!el) {
            return;
        }
        setTimeout(function () {
            el.focus();
            if (typeof el.select === 'function' && el.tagName === 'INPUT' && el.type !== 'date') {
                el.select();
            }
        }, 0);
    }

    function siguienteCampoNav(actual) {
        var campos = listarCamposNav();
        var idx = campos.indexOf(actual);
        if (idx >= 0 && idx < campos.length - 1) {
            return campos[idx + 1];
        }
        return null;
    }

    function validarUsuarioYAvanzar(input) {
        var $inp = window.jQuery ? window.jQuery(input) : null;
        if ($inp && $inp.length) {
            // Dispara el blur de consulta.js (resolver por código/ID)
            $inp.trigger('blur');
        } else {
            input.blur();
        }
        setTimeout(function () {
            var next = siguienteCampoNav(input);
            if (next) {
                enfocarCampo(next);
            }
        }, 180);
    }

    function abrirConsultaUsuarioDesdeInput(input) {
        var ctx = input.closest('.tm-usuario-campo') || input.closest('.form-group');
        if (!ctx) {
            return;
        }
        var btn = ctx.querySelector('.consultausuario');
        if (btn) {
            btn.click();
        }
    }

    // Capture: gana al bloqueo global de Enter en consulta.js
    app.addEventListener('keydown', function (e) {
        var target = e.target;
        if (!target || !app.contains(target)) {
            return;
        }

        if (esTeclaF1(e)) {
            if (
                target.classList
                && target.classList.contains('usuario_codigo_arbol')
                && !modalAbierto('#consultausuarioModal')
            ) {
                e.preventDefault();
                e.stopPropagation();
                abrirConsultaUsuarioDesdeInput(target);
            }
            return;
        }

        if (!esTeclaEnter(e)) {
            return;
        }
        if (hayModalAbierto()) {
            return;
        }
        if (target.tagName === 'TEXTAREA' || target.tagName === 'BUTTON') {
            return;
        }
        if (!esCampoNav(target) && !(target.classList && target.classList.contains('usuario_codigo_arbol'))) {
            return;
        }

        e.preventDefault();
        e.stopPropagation();

        if (target.classList && target.classList.contains('usuario_codigo_arbol')) {
            validarUsuarioYAvanzar(target);
            return;
        }

        // Al pasar el foco, focusout formatea montos; change de fecha/select ya corrió al editar
        var next = siguienteCampoNav(target);
        if (next) {
            enfocarCampo(next);
        }
    }, true);

    if (typeof window.jQuery !== 'undefined' && typeof window.activa_eventos_consultausuario === 'function') {
        window.activa_eventos_consultausuario();
    }

    if (getTurno() === 'C') {
        document.getElementById('wrap-dif-caja')?.classList.remove('d-none');
    }

    initFormatoMontos(app);
    actualizarAvisoWigos();

    // Alta: pie en cero hasta Traer WIGOS / editar montos (Ctrl+R no debe dejar totales viejos).
    // Edición: recalcular con lo grabado.
    if (esAlta) {
        blanquearTotales();
    } else {
        calcularDebounced();
    }

    // bfcache (atrás / a veces Ctrl+R): forzar pie en cero en alta
    window.addEventListener('pageshow', function (ev) {
        if (ev.persisted && esAlta) {
            blanquearTotales();
            marcarWigosLeido(false);
        }
    });
})();
