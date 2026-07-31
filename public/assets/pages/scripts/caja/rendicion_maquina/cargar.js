(function () {
    'use strict';

    var app = document.getElementById('rendicion-maquina-app');
    if (!app) {
        return;
    }

    var carpetaBase = (typeof window.carpetaBase !== 'undefined' && window.carpetaBase) ? window.carpetaBase : '';
    var debounceTimer = null;
    var wigosOriginales = {};
    var ajustesPendientes = [];
    var recargandoLineas = false;

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
            lineas.push({
                cuentacaja_id: parseInt(tr.dataset.cuentacajaId, 10),
                monto: parseNum(tr.querySelector('.js-valor-monto')?.value)
            });
        });
        return lineas;
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
        return {
            comprobante: parseNum(document.getElementById('calc_comprobante')?.value),
            vale_rep_fondo: parseNum(document.getElementById('calc_vale_rep_fondo')?.value)
        };
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
        var wrapDif = document.getElementById('wrap-dif-caja');
        if (wrapDif) {
            if (getTurno() === 'C') {
                wrapDif.classList.remove('d-none');
            } else {
                wrapDif.classList.add('d-none');
            }
        }
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
        tbody.innerHTML = lineas.map(function (linea) {
            var id = parseInt(linea.cuentacaja_id, 10) || 0;
            var codigo = escapeHtml(linea.codigo || '');
            var nombre = escapeHtml(linea.nombre || '');
            var monto = fmtMoney(linea.monto || 0);
            return '<tr data-cuentacaja-id="' + id + '">'
                + '<td class="text-muted col-codigo">' + codigo + '</td>'
                + '<td class="col-desc" title="' + nombre + '">' + nombre + '</td>'
                + '<td class="col-monto"><input type="text" inputmode="decimal" class="form-control form-control-sm text-right js-valor-monto js-monto-ar" autocomplete="off" value="' + monto + '"></td>'
                + '</tr>';
        }).join('');
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
        tbody.innerHTML = lineas.map(function (linea) {
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

    function recargarLineasPorEmpresa() {
        var empresaId = getEmpresaId();
        app.dataset.empresaId = String(empresaId);

        if (app.dataset.modoEdicion === '1') {
            calcularDebounced();
            return;
        }

        if (empresaId <= 0) {
            renderValores([]);
            renderGastos([]);
            return;
        }

        recargandoLineas = true;
        postJson(app.dataset.apiLineasEmpresa, { empresa_id: empresaId })
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
                calcularDebounced();
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
        ajustesPendientes = [];
        calcularDebounced();
    }

    function traerWigos() {
        var empresaId = getEmpresaId();
        if (empresaId <= 0) {
            alert('Seleccione empresa.');
            return;
        }
        postJson(app.dataset.apiTraerWigos, {
            empresa_id: empresaId,
            fecha: getFecha(),
            turno: getTurno()
        }).then(function (data) {
            if (data.ok) {
                aplicarWigos(data);
                if (data.meta && data.meta.stub) {
                    alert(data.meta.mensaje || data.mensaje || 'WIGOS aún no está conectado: se cargaron ceros.');
                }
            }
        }).catch(function (err) {
            alert(err.message);
        });
    }

    function guardar() {
        var empresaId = getEmpresaId();
        if (empresaId <= 0) {
            alert('Seleccione empresa.');
            return;
        }

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
                alert(data.mensaje || 'Guardado.');
                if (data.url_comprobante_pdf) {
                    window.open(data.url_comprobante_pdf, '_blank');
                }
                if (data.url_editar) {
                    window.location.href = data.url_editar;
                } else {
                    window.location.href = app.dataset.urlIndex;
                }
            }
        }).catch(function (err) {
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
    document.getElementById('fecha_rendicion')?.addEventListener('change', calcularDebounced);
    document.getElementById('turno_rendicion')?.addEventListener('change', function () {
        app.dataset.turno = getTurno();
        calcularDebounced();
    });

    document.getElementById('btn-traer-wigos')?.addEventListener('click', traerWigos);
    document.getElementById('btn-guardar-rendicion')?.addEventListener('click', guardar);
    document.getElementById('btn-ver-log-ajustes')?.addEventListener('click', verLogAjustes);

    if (getTurno() === 'C') {
        document.getElementById('wrap-dif-caja')?.classList.remove('d-none');
    }

    initFormatoMontos(app);
    calcularDebounced();
})();
