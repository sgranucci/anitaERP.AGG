(function () {
    'use strict';

    var CFG = window.FL_POS || {};
    var cart = [];
    var pendingArticulo = null;

    function $(id) { return document.getElementById(id); }

    function money(n) {
        return '$ ' + (Number(n) || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function overlay(show, txt) {
        var el = $('fl-overlay');
        if (!el) return;
        if (txt) $('fl-overlay-txt').textContent = txt;
        el.classList.toggle('show', !!show);
    }

    function msg(text, ok) {
        var el = $('fl-msg');
        if (!el) return;
        el.style.color = ok ? '#2ecc71' : '#e74c3c';
        el.textContent = text || '';
    }

    function post(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': CFG.csrf,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(Object.assign({ _token: CFG.csrf }, body || {}))
        }).then(function (r) {
            return r.json().then(function (j) {
                return { status: r.status, body: j };
            });
        });
    }

    function get(url) {
        return fetch(url, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) { return r.json(); });
    }

    function renderCart() {
        var tb = $('fl-cart-body');
        if (!tb) return;
        tb.innerHTML = '';
        cart.forEach(function (line, idx) {
            var tr = document.createElement('tr');
            var varLabel = line.modo === 'color_talle'
                ? ('C' + (line.color_id || '') + ' T' + (line.talle_nombre || line.talle_id))
                : ('Comb ' + (line.combinacion_nombre || line.combinacion_id) + ' T' + (line.talle_nombre || line.talle_id));
            tr.innerHTML =
                '<td><strong>' + escapeHtml(line.sku) + '</strong><br><span style="color:#8b9bb4;font-size:12px;">' + escapeHtml(line.descripcion) + '</span></td>' +
                '<td style="font-size:12px;">' + escapeHtml(varLabel) + '</td>' +
                '<td><input type="number" step="1" data-i="' + idx + '" class="fl-cant" value="' + line.cantidad + '"></td>' +
                '<td><input type="number" step="0.01" data-i="' + idx + '" class="fl-precio" value="' + line.precio + '"></td>' +
                '<td><input type="number" step="0.01" data-i="' + idx + '" class="fl-dto" value="' + (line.descuento || 0) + '"></td>' +
                '<td><button type="button" class="fl-btn fl-btn-ghost fl-del" data-i="' + idx + '">×</button></td>';
            tb.appendChild(tr);
        });
        tb.querySelectorAll('.fl-cant').forEach(function (inp) {
            inp.addEventListener('change', function () {
                cart[+inp.dataset.i].cantidad = parseFloat(inp.value) || 0;
                preview();
            });
        });
        tb.querySelectorAll('.fl-precio').forEach(function (inp) {
            inp.addEventListener('change', function () {
                cart[+inp.dataset.i].precio = parseFloat(inp.value) || 0;
                preview();
            });
        });
        tb.querySelectorAll('.fl-dto').forEach(function (inp) {
            inp.addEventListener('change', function () {
                cart[+inp.dataset.i].descuento = parseFloat(inp.value) || 0;
                preview();
            });
        });
        tb.querySelectorAll('.fl-del').forEach(function (btn) {
            btn.addEventListener('click', function () {
                cart.splice(+btn.dataset.i, 1);
                renderCart();
                preview();
            });
        });
        preview();
    }

    function escapeHtml(s) {
        return String(s || '').replace(/[&<>"']/g, function (c) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
        });
    }

    function preview() {
        post(CFG.urls.preview, { lineas: cart }).then(function (res) {
            var b = res.body || {};
            var el = $('fl-totales');
            if (el) {
                el.childNodes[0].textContent = money(b.neto || 0);
                var det = $('fl-totales-detalle');
                if (det) {
                    det.textContent = 'FAC ' + money(b.neto_fac || 0) + ' · NC ' + money(b.neto_nc || 0);
                }
            }
            syncMedioDefault(b.neto || 0);
        }).catch(function () {});
    }

    function syncMedioDefault(neto) {
        var wrap = $('fl-medios');
        if (!wrap || wrap.children.length) return;
        addMedio(Math.max(0, neto));
    }

    function addMedio(monto) {
        var wrap = $('fl-medios');
        if (!wrap) return;
        var cuentas = CFG.cuentas || [];
        var sel = cuentas.map(function (c) {
            var selAttr = (CFG.efectivoId && +c.id === +CFG.efectivoId) ? ' selected' : '';
            return '<option value="' + c.id + '"' + selAttr + '>' + escapeHtml(c.codigo + ' — ' + c.nombre) + '</option>';
        }).join('');
        var div = document.createElement('div');
        div.className = 'row-medio';
        div.innerHTML =
            '<select class="fl-cc" style="flex:2;">' + sel + '</select>' +
            '<input type="number" step="0.01" class="fl-monto" style="flex:1;" value="' + (monto || 0).toFixed(2) + '">' +
            '<button type="button" class="fl-btn fl-btn-ghost fl-rm-medio">×</button>';
        wrap.appendChild(div);
        div.querySelector('.fl-rm-medio').addEventListener('click', function () {
            div.remove();
        });
    }

    function buscar() {
        var q = ($('fl-q') && $('fl-q').value || '').trim();
        if (q.length < 2) return;
        get(CFG.urls.buscar + '?q=' + encodeURIComponent(q)).then(function (j) {
            var box = $('fl-results');
            box.innerHTML = '';
            (j.data || []).forEach(function (a) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = a.sku + ' — ' + a.descripcion;
                btn.addEventListener('click', function () { elegirArticulo(a); });
                box.appendChild(btn);
            });
            if (!(j.data || []).length) {
                box.innerHTML = '<div style="padding:10px;color:#8b9bb4;">Sin resultados (¿canal Local?)</div>';
            }
        });
    }

    function elegirArticulo(a) {
        pendingArticulo = a;
        get(CFG.urls.variantes + '/' + a.id).then(function (v) {
            $('fl-modal-var-title').textContent = a.sku + ' — ' + a.descripcion;
            fillSelect($('fl-var-talle'), v.talles || [], 'nombre');
            if (v.modo === 'color_talle') {
                $('fl-var-color-wrap').style.display = '';
                $('fl-var-comb-wrap').style.display = 'none';
                fillSelect($('fl-var-color'), v.colores || [], 'nombre');
            } else {
                $('fl-var-color-wrap').style.display = 'none';
                $('fl-var-comb-wrap').style.display = '';
                fillSelect($('fl-var-comb'), v.combinaciones || [], function (c) {
                    return (c.codigo || '') + ' — ' + (c.nombre || '');
                });
            }
            pendingArticulo.modo = v.modo;
            $('fl-var-cant').value = '1';
            window.jQuery('#fl-modal-var').modal('show');
        });
    }

    function fillSelect(sel, rows, labelFn) {
        sel.innerHTML = '';
        rows.forEach(function (r) {
            var opt = document.createElement('option');
            opt.value = r.id;
            opt.textContent = typeof labelFn === 'function' ? labelFn(r) : (r[labelFn] || r.nombre || r.id);
            sel.appendChild(opt);
        });
    }

    function confirmarVariante() {
        if (!pendingArticulo) return;
        var talleSel = $('fl-var-talle');
        var talleId = +talleSel.value;
        var talleNombre = talleSel.options[talleSel.selectedIndex]
            ? talleSel.options[talleSel.selectedIndex].textContent
            : '';
        var colorId = null;
        var combinacionId = null;
        var combinacionNombre = '';
        if (pendingArticulo.modo === 'color_talle') {
            colorId = +$('fl-var-color').value || null;
        } else {
            var combSel = $('fl-var-comb');
            combinacionId = +combSel.value || null;
            combinacionNombre = combSel.options[combSel.selectedIndex]
                ? combSel.options[combSel.selectedIndex].textContent
                : '';
        }
        var cant = parseFloat($('fl-var-cant').value) || 0;
        if (!cant) {
            msg('Cantidad inválida');
            return;
        }
        overlay(true, 'Precio…');
        get(CFG.urls.precio + '?articulo_id=' + pendingArticulo.id +
            '&combinacion_id=' + (combinacionId || 0) +
            '&talle_id=' + talleId +
            '&local_id=' + CFG.localId
        ).then(function (p) {
            cart.push({
                articulo_id: pendingArticulo.id,
                sku: pendingArticulo.sku,
                descripcion: pendingArticulo.descripcion,
                modo: pendingArticulo.modo,
                talle_id: talleId,
                talle_nombre: talleNombre,
                color_id: colorId,
                combinacion_id: combinacionId,
                combinacion_nombre: combinacionNombre,
                cantidad: cant,
                precio: +(p.precio || 0),
                descuento: 0
            });
            pendingArticulo = null;
            window.jQuery('#fl-modal-var').modal('hide');
            $('fl-results').innerHTML = '';
            $('fl-q').value = '';
            renderCart();
            overlay(false);
            $('fl-q').focus();
        }).catch(function () {
            overlay(false);
            msg('No se pudo obtener precio');
        });
    }

    function mediosPago() {
        var out = [];
        document.querySelectorAll('#fl-medios .row-medio').forEach(function (row) {
            out.push({
                cuentacaja_id: +row.querySelector('.fl-cc').value,
                moneda_id: 1,
                monto: parseFloat(row.querySelector('.fl-monto').value) || 0
            });
        });
        return out;
    }

    function emitir(esRegalo) {
        if (!CFG.turnoId) {
            msg('Abra un turno primero');
            return;
        }
        if (!cart.length) {
            msg('Carrito vacío');
            return;
        }
        overlay(true, esRegalo ? 'Ticket regalo…' : 'Emitiendo CAE…');
        post(CFG.urls.emitir, {
            local_id: CFG.localId,
            lineas: cart,
            medios_pago: esRegalo ? [] : mediosPago(),
            cliente_id: +($('fl-cliente-id').value || 0) || null,
            receptor: {
                nombre: ($('fl-cliente-nombre').textContent || '').replace('Consumidor final', '').trim()
            },
            excedente_accion: $('fl-excedente').value || null,
            es_ticket_regalo: !!esRegalo
        }).then(function (res) {
            overlay(false);
            if (res.status >= 400 || !(res.body && res.body.ok)) {
                var err = (res.body && (res.body.error || (res.body.errores && res.body.errores[0]))) || 'Error al emitir';
                msg(err, false);
                return;
            }
            msg('OK ' + (res.body.codigo || '') + (res.body.cae ? ' CAE ' + res.body.cae : ''), true);
            cart = [];
            renderCart();
            $('fl-medios').innerHTML = '';
        }).catch(function (e) {
            overlay(false);
            msg(e.message || 'Error de red', false);
        });
    }

    function bind() {
        document.body.classList.add('fl-pos-active');
        if ($('fl-buscar')) $('fl-buscar').addEventListener('click', buscar);
        if ($('fl-q')) {
            $('fl-q').addEventListener('keydown', function (e) {
                if (e.key === 'Enter') { e.preventDefault(); buscar(); }
                if (e.key === 'Escape') { $('fl-q').value = ''; $('fl-results').innerHTML = ''; }
            });
        }
        if ($('fl-add-medio')) $('fl-add-medio').addEventListener('click', function () { addMedio(0); });
        if ($('fl-emitir')) $('fl-emitir').addEventListener('click', function () { emitir(false); });
        if ($('fl-regalo')) $('fl-regalo').addEventListener('click', function () { emitir(true); });
        if ($('fl-var-ok')) $('fl-var-ok').addEventListener('click', confirmarVariante);
        if ($('fl-cliente-codigo')) {
            $('fl-cliente-codigo').addEventListener('keydown', function (e) {
                if (e.key !== 'Enter') return;
                e.preventDefault();
                get(CFG.urls.cliente + '?codigo=' + encodeURIComponent($('fl-cliente-codigo').value)).then(function (j) {
                    if (j.cliente) {
                        $('fl-cliente-id').value = j.cliente.id;
                        $('fl-cliente-nombre').textContent = j.cliente.nombre || j.cliente.codigo;
                    } else {
                        $('fl-cliente-id').value = '';
                        $('fl-cliente-nombre').textContent = 'No encontrado — CF';
                    }
                });
            });
        }
        if ($('fl-abrir-turno')) {
            $('fl-abrir-turno').addEventListener('click', function () {
                var turnoLocalEl = $('fl-turno-local-id');
                var turnoLocalId = turnoLocalEl ? String(turnoLocalEl.value || '') : '';
                if (!turnoLocalId) {
                    msg('Elija el turno (Mañana/Tarde/Noche)', false);
                    if (turnoLocalEl) turnoLocalEl.focus();
                    return;
                }
                var fondo = prompt('Fondo inicial', '0') || '0';
                overlay(true, 'Abriendo turno…');
                var fd = new FormData();
                fd.append('_token', CFG.csrf);
                fd.append('local_id', CFG.localId);
                fd.append('fondo_inicial', fondo);
                fd.append('turno_local_id', turnoLocalId);
                fetch(CFG.urls.abrirTurno, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: fd
                }).then(function (r) { return r.json(); }).then(function (j) {
                    overlay(false);
                    if (j.ok) location.reload();
                    else msg(j.error || 'No se pudo abrir', false);
                }).catch(function () { overlay(false); msg('Error', false); });
            });
        }
        if ($('fl-cerrar-turno') && CFG.urls.cerrarTurno) {
            $('fl-cerrar-turno').addEventListener('click', function () {
                if (!confirm('¿Cerrar turno?')) return;
                overlay(true, 'Cerrando…');
                var fd = new FormData();
                fd.append('_token', CFG.csrf);
                fetch(CFG.urls.cerrarTurno, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: fd
                }).then(function (r) { return r.json(); }).then(function (j) {
                    overlay(false);
                    if (j.ok) {
                        if (j.pdf_url) window.open(j.pdf_url, '_blank');
                        location.reload();
                    } else msg(j.error || 'No se pudo cerrar', false);
                }).catch(function () { overlay(false); msg('Error', false); });
            });
        }
        document.addEventListener('keydown', function (e) {
            if (e.key === 'F2') { e.preventDefault(); emitir(false); }
            if (e.key === 'F8') { e.preventDefault(); emitir(true); }
            if (e.key === 'F1' && document.activeElement === $('fl-q')) {
                e.preventDefault();
                $('fl-q').value = '';
                $('fl-results').innerHTML = '';
            }
        });
        if (CFG.turnoId) addMedio(0);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bind);
    } else {
        bind();
    }
})();
