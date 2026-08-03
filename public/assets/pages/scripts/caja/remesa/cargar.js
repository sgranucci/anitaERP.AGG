(function () {
    'use strict';

    var app = document.getElementById('remesa-app');
    if (!app) {
        return;
    }

    var carpetaBase = (typeof window.carpetaBase !== 'undefined' && window.carpetaBase) ? window.carpetaBase : '';
    var TOLERANCIA = 0.02;
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

    function getEmpresaId() {
        var el = document.getElementById('empresa_id');
        return el ? parseInt(el.value, 10) || 0 : parseInt(app.dataset.empresaId || '0', 10);
    }

    function getTipo() {
        var el = document.getElementById('tipo_remesa');
        return el ? el.value : '';
    }

    function esExterna() {
        return getTipo() === (app.dataset.tipoExterna || 'M');
    }

    function sumaMontos(selector) {
        var total = 0;
        app.querySelectorAll(selector).forEach(function (input) {
            total += parseNum(input.value);
        });
        return Math.round(total * 100) / 100;
    }

    function totalesPorMoneda() {
        var dest = {};
        var orig = {};
        var labels = {};

        app.querySelectorAll('#tabla-destino tbody tr').forEach(function (tr) {
            var input = tr.querySelector('.js-linea-destino');
            if (!input) {
                return;
            }
            var m = parseNum(input.value);
            if (m <= 0) {
                return;
            }
            var mid = parseInt(input.dataset.monedaId || tr.dataset.monedaId || '1', 10) || 1;
            dest[mid] = Math.round(((dest[mid] || 0) + m) * 100) / 100;
            labels[mid] = tr.dataset.monedaAbrev || labels[mid] || ('#' + mid);
        });

        app.querySelectorAll('#tabla-origen tbody tr').forEach(function (tr) {
            var input = tr.querySelector('.js-linea-origen');
            if (!input) {
                return;
            }
            var m = parseNum(input.value);
            if (m <= 0) {
                return;
            }
            var mid = parseInt(input.dataset.monedaId || tr.dataset.monedaId || '1', 10) || 1;
            orig[mid] = Math.round(((orig[mid] || 0) + m) * 100) / 100;
            labels[mid] = tr.dataset.monedaAbrev || labels[mid] || ('#' + mid);
        });

        return { dest: dest, orig: orig, labels: labels };
    }

    function actualizarTotales() {
        var dest = sumaMontos('.js-linea-destino');
        var orig = sumaMontos('.js-linea-origen');
        var dif = Math.round((dest - orig) * 100) / 100;

        var elDest = document.getElementById('tot-destino');
        var elOrig = document.getElementById('tot-origen');
        var elDif = document.getElementById('tot-diferencia');
        var wrapDif = document.getElementById('tot-diferencia-wrap');

        if (elDest) {
            elDest.textContent = fmtMoney(dest);
        }
        if (elOrig) {
            elOrig.textContent = fmtMoney(orig);
        }
        if (elDif) {
            elDif.textContent = fmtMoney(dif);
        }

        var descuadreMoneda = false;
        if (esExterna()) {
            var porMon = totalesPorMoneda();
            Object.keys(Object.assign({}, porMon.dest, porMon.orig)).forEach(function (k) {
                var d = porMon.dest[k] || 0;
                var o = porMon.orig[k] || 0;
                if (Math.abs(d - o) > TOLERANCIA) {
                    descuadreMoneda = true;
                }
            });
        }

        if (wrapDif) {
            wrapDif.classList.toggle('is-alerta', esExterna() && (Math.abs(dif) > TOLERANCIA || descuadreMoneda));
        }

        actualizarPreview();
    }

    function actualizarPreview() {
        var box = document.getElementById('remesa-preview-asiento');
        if (!box) {
            return;
        }

        if (!esExterna()) {
            box.innerHTML = '<p class="text-muted mb-0">Remesa interna: no genera asiento contable (origen TES / movimiento RMI).</p>';
            return;
        }

        var grupos = {};
        function acumular(tr, lado) {
            var sel = lado === 'destino' ? '.js-linea-destino' : '.js-linea-origen';
            var input = tr.querySelector(sel);
            if (!input) {
                return;
            }
            var m = parseNum(input.value);
            if (m <= 0) {
                return;
            }
            var mid = parseInt(input.dataset.monedaId || tr.dataset.monedaId || '1', 10) || 1;
            var abrev = tr.dataset.monedaAbrev || ('#' + mid);
            var nombre = tr.cells[1] ? tr.cells[1].textContent.trim() : '';
            if (!grupos[mid]) {
                grupos[mid] = { abrev: abrev, filas: [], dest: 0, orig: 0 };
            }
            grupos[mid].filas.push({
                nombre: nombre,
                debe: lado === 'destino' ? m : 0,
                haber: lado === 'origen' ? m : 0
            });
            if (lado === 'destino') {
                grupos[mid].dest = Math.round((grupos[mid].dest + m) * 100) / 100;
            } else {
                grupos[mid].orig = Math.round((grupos[mid].orig + m) * 100) / 100;
            }
        }

        app.querySelectorAll('#tabla-destino tbody tr').forEach(function (tr) { acumular(tr, 'destino'); });
        app.querySelectorAll('#tabla-origen tbody tr').forEach(function (tr) { acumular(tr, 'origen'); });

        var keys = Object.keys(grupos);
        if (keys.length === 0) {
            box.innerHTML = '<p class="text-muted mb-0">Complete montos para ver el preview (un asiento por moneda).</p>';
            return;
        }

        keys.sort(function (a, b) { return parseInt(a, 10) - parseInt(b, 10); });
        var html = '';
        keys.forEach(function (k) {
            var g = grupos[k];
            var ok = Math.abs(g.dest - g.orig) <= TOLERANCIA;
            html += '<div class="mb-2"><strong>Asiento ' + escapeHtml(g.abrev) + '</strong>';
            html += ' <span class="small ' + (ok ? 'text-success' : 'text-danger') + '">';
            html += ok ? 'cuadrado' : ('dif. ' + fmtMoney(g.dest - g.orig));
            html += '</span>';
            html += '<table class="table table-sm table-bordered mb-0 mt-1"><thead><tr><th>Cuenta</th><th class="text-right">Debe</th><th class="text-right">Haber</th></tr></thead><tbody>';
            g.filas.forEach(function (f) {
                html += '<tr><td>' + escapeHtml(f.nombre) + '</td>';
                html += '<td class="text-right">' + (f.debe > 0 ? fmtMoney(f.debe) : '') + '</td>';
                html += '<td class="text-right">' + (f.haber > 0 ? fmtMoney(f.haber) : '') + '</td></tr>';
            });
            html += '</tbody></table></div>';
        });

        box.innerHTML = html;
    }

    function escapeHtml(text) {
        return String(text || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function initFormatoMontos(root) {
        var scope = root || app;
        scope.querySelectorAll('.js-monto-ar').forEach(formatearInputMonto);
    }

    function renderTablaLineas(tablaId, lineas, lado) {
        var tbody = document.querySelector('#' + tablaId + ' tbody');
        if (!tbody) {
            return;
        }
        tbody.innerHTML = '';
        if (!lineas || lineas.length === 0) {
            tbody.innerHTML = '<tr><td colspan="3" class="text-muted text-center py-3">Sin cuentas para este tipo/empresa.</td></tr>';
            return;
        }

        lineas.forEach(function (linea) {
            var tr = document.createElement('tr');
            var monedaId = parseInt(linea.moneda_id || 1, 10) || 1;
            var monedaAbrev = linea.moneda_abrev || '';
            tr.dataset.monedaId = String(monedaId);
            tr.dataset.monedaAbrev = monedaAbrev;
            var montoFmt = fmtMoney(linea.monto || 0);
            tr.innerHTML =
                '<td class="col-codigo">' + escapeHtml(linea.codigo) + '</td>' +
                '<td title="' + escapeHtml(linea.descripcion_operaciones || '') + '">' + escapeHtml(linea.nombre) + '</td>' +
                '<td class="col-monto">' +
                    '<input type="hidden" name="' + lado + '_cuentacaja_ids[]" value="' + linea.cuentacaja_id + '">' +
                    '<input type="text" name="' + lado + '_montos[]" class="form-control form-control-sm text-right js-monto-ar js-linea-' + lado +
                    '" data-lado="' + lado + '" data-moneda-id="' + monedaId + '" value="' + montoFmt + '">' +
                '</td>';
            tbody.appendChild(tr);
        });

        initFormatoMontos(tbody);
        bindMontos(tbody);
        actualizarTotales();
    }

    function recargarLineas() {
        var empresaId = getEmpresaId();
        if (empresaId <= 0 || recargandoLineas) {
            return;
        }
        if (app.dataset.modoEdicion === '1') {
            // En edición no se cambia empresa; sí puede cambiar el tipo solo en alta.
            // Si está soloLectura no aplica; en editar confirmada permitimos recargar origen al cambiar tipo? Tipo disabled in soloLectura.
        }
        recargandoLineas = true;

        var tokenEl = document.querySelector('#form-remesa input[name="_token"]');
        var body = new URLSearchParams();
        body.set('empresa_id', String(empresaId));
        body.set('remesa_id', app.dataset.remesaId || '0');
        body.set('tipo', getTipo() || 'M');
        if (tokenEl) {
            body.set('_token', tokenEl.value);
        }

        fetch(carpetaBase + '/caja/remesa/api/lineas-empresa', {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: body
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) {
                    return;
                }
                var label = document.getElementById('label-uso-origen');
                if (label && data.uso_origen) {
                    label.textContent = data.uso_origen;
                }
                renderTablaLineas('tabla-destino', data.destino || [], 'destino');
                renderTablaLineas('tabla-origen', data.origen || [], 'origen');
            })
            .catch(function () { /* silencioso */ })
            .finally(function () {
                recargandoLineas = false;
            });
    }

    function bindMontos(root) {
        var scope = root || app;
        scope.querySelectorAll('.js-monto-ar').forEach(function (input) {
            if (input.dataset.bound === '1') {
                return;
            }
            input.dataset.bound = '1';

            input.addEventListener('focus', function () {
                desformatearInputMonto(input);
                input.select();
            });
            input.addEventListener('blur', function () {
                formatearInputMonto(input);
                actualizarTotales();
            });
            input.addEventListener('input', actualizarTotales);
            input.addEventListener('keydown', function (ev) {
                if (ev.key === 'Enter') {
                    ev.preventDefault();
                    formatearInputMonto(input);
                    avanzarEnter(input);
                }
            });
        });
    }

    function avanzarEnter(input) {
        var inputs = Array.prototype.slice.call(app.querySelectorAll('.js-monto-ar:not([readonly])'));
        var idx = inputs.indexOf(input);
        if (idx >= 0 && idx < inputs.length - 1) {
            inputs[idx + 1].focus();
        } else if (idx === inputs.length - 1) {
            var btn = document.getElementById('btn-guardar-remesa');
            if (btn) {
                btn.focus();
            }
        }
    }

    function validarAntesSubmit(ev) {
        app.querySelectorAll('.js-monto-ar').forEach(desformatearInputMonto);

        if (!esExterna()) {
            return true;
        }

        var porMon = totalesPorMoneda();
        var keys = Object.keys(Object.assign({}, porMon.dest, porMon.orig));
        if (keys.length === 0) {
            ev.preventDefault();
            alert('Debe cargar al menos un monto.');
            initFormatoMontos();
            return false;
        }

        for (var i = 0; i < keys.length; i++) {
            var k = keys[i];
            var d = porMon.dest[k] || 0;
            var o = porMon.orig[k] || 0;
            if (Math.abs(d - o) > TOLERANCIA) {
                ev.preventDefault();
                alert(
                    'En remesa externa los totales deben cuadrar por moneda (' +
                    (porMon.labels[k] || ('#' + k)) +
                    '): destino ' + fmtMoney(d) + ' / origen ' + fmtMoney(o) + '.'
                );
                initFormatoMontos();
                return false;
            }
        }

        return true;
    }

    document.addEventListener('DOMContentLoaded', function () {
        initFormatoMontos();
        bindMontos();
        actualizarTotales();

        var empresaEl = document.getElementById('empresa_id');
        if (empresaEl && app.dataset.modoEdicion !== '1') {
            empresaEl.addEventListener('change', recargarLineas);
        }

        var tipoEl = document.getElementById('tipo_remesa');
        if (tipoEl && !tipoEl.disabled) {
            tipoEl.addEventListener('change', function () {
                if (app.dataset.modoEdicion === '1') {
                    actualizarTotales();
                    return;
                }
                recargarLineas();
            });
        }

        var form = document.getElementById('form-remesa');
        if (form) {
            form.addEventListener('submit', validarAntesSubmit);
        }
    });
}());
