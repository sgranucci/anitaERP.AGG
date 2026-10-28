(function () {
    var CFG = window.FL_NC_VIEJA || {};
    var cart = [];
    var pendingArticulo = null;
    var pendingVariantes = null;

    function $(id) { return document.getElementById(id); }

    function escapeHtml(s) {
        return String(s || '').replace(/[&<>"']/g, function (c) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
        });
    }

    function msg(texto, ok) {
        var el = $('fl-msg');
        if (!el) return;
        el.textContent = texto || '';
        el.style.color = ok ? '#196f3d' : '#922b21';
    }

    function overlay(on, texto) {
        var el = $('fl-overlay');
        if (!el) return;
        el.style.display = on ? 'flex' : 'none';
        var t = $('fl-overlay-txt');
        if (t && texto) t.textContent = texto;
    }

    function get(url) {
        return fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (res) {
                return res.json().then(function (body) { return { status: res.status, body: body }; });
            });
    }

    function post(url, data) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CFG.csrf || '',
            },
            body: JSON.stringify(data),
        }).then(function (res) {
            return res.json().then(function (body) { return { status: res.status, body: body }; });
        });
    }

    function buscar() {
        var q = ($('fl-q') && $('fl-q').value || '').trim();
        var box = $('fl-results');
        if (!box) return;
        if (q.length < 2) {
            box.innerHTML = '<div class="fl-empty">Escribí al menos 2 caracteres.</div>';
            return;
        }
        box.innerHTML = '<div class="fl-empty">Buscando…</div>';
        get(CFG.urls.buscar + '?q=' + encodeURIComponent(q)).then(function (res) {
            var rows = (res.body && res.body.data) || [];
            box.innerHTML = '';
            if (!rows.length) {
                box.innerHTML = '<div class="fl-empty">Sin resultados en canal Local</div>';
                return;
            }
            rows.forEach(function (a) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = a.sku + ' — ' + a.descripcion;
                btn.addEventListener('click', function () { elegirArticulo(a); });
                box.appendChild(btn);
            });
        }).catch(function () {
            box.innerHTML = '<div class="fl-err">No se pudo buscar.</div>';
        });
    }

    function limpiarCampo(prefix) {
        ['id', 'codigo', 'nombre'].forEach(function (suf) {
            var el = $('fl-var-' + prefix + '-' + suf);
            if (el) el.value = '';
        });
    }

    function elegirArticulo(a) {
        pendingArticulo = a;
        get(CFG.urls.variantes + '/' + a.id).then(function (res) {
            var v = res.body || {};
            if (res.status >= 400) {
                msg(v.error || 'No se pudieron cargar las variantes', false);
                return;
            }
            pendingVariantes = v;
            pendingArticulo.modo = v.modo;
            $('fl-modal-var-title').textContent = a.sku + ' — ' + a.descripcion;
            limpiarCampo('talle');
            limpiarCampo('color');
            limpiarCampo('comb');
            var aviso = $('fl-var-aviso');
            if (aviso) {
                aviso.classList.add('d-none');
                aviso.textContent = '';
            }
            $('fl-var-talle-wrap').style.display = v.modo === 'sin_variante' ? 'none' : '';
            $('fl-var-color-wrap').style.display = v.modo === 'color_talle' ? '' : 'none';
            $('fl-var-comb-wrap').style.display = (v.modo !== 'sin_variante' && v.modo !== 'color_talle') ? '' : 'none';
            if (v.modo !== 'sin_variante' && v.modo !== 'color_talle' && !(v.combinaciones || []).length && aviso) {
                aviso.textContent = 'El artículo no tiene combinaciones activas.';
                aviso.classList.remove('d-none');
            }
            $('fl-var-cant').value = '1';
            window.jQuery('#fl-modal-var').modal('show');
        }).catch(function () {
            msg('No se pudieron cargar las variantes', false);
        });
    }

    function buscarEnLista(lista, codigo) {
        var q = String(codigo || '').trim().toLowerCase();
        if (!q || !Array.isArray(lista)) return null;
        var exacto = lista.find(function (r) {
            return String(r.codigo || '').toLowerCase() === q
                || String(r.nombre || '').toLowerCase() === q
                || String(r.id) === q;
        });
        if (exacto) return exacto;
        var parcial = lista.filter(function (r) {
            return String(r.codigo || '').toLowerCase().indexOf(q) >= 0
                || String(r.nombre || '').toLowerCase().indexOf(q) >= 0;
        });
        return parcial.length === 1 ? parcial[0] : null;
    }

    function aplicarCampo(prefix, row) {
        if (!row) return;
        var codigo = String(row.codigo || '').trim() || String(row.nombre || '');
        if ($('fl-var-' + prefix + '-id')) $('fl-var-' + prefix + '-id').value = row.id;
        if ($('fl-var-' + prefix + '-codigo')) $('fl-var-' + prefix + '-codigo').value = codigo;
        if ($('fl-var-' + prefix + '-nombre')) $('fl-var-' + prefix + '-nombre').value = row.nombre || '';
    }

    function foco(id) {
        var el = $(id);
        if (!el) return;
        el.focus();
        if (el.select) el.select();
    }

    function confirmarVariante() {
        if (!pendingArticulo) return;
        var modo = pendingArticulo.modo;
        var sin = modo === 'sin_variante';
        var talleId = sin ? 0 : +(($('fl-var-talle-id') && $('fl-var-talle-id').value) || 0);
        if (!sin && !talleId) {
            msg('Elegí el talle.', false);
            foco('fl-var-talle-codigo');
            return;
        }
        var colorId = 0;
        var combId = 0;
        var colorNombre = '';
        var combNombre = '';
        var talleNombre = sin ? '' : (($('fl-var-talle-nombre') && $('fl-var-talle-nombre').value) || $('fl-var-talle-codigo').value);
        if (modo === 'color_talle') {
            colorId = +(($('fl-var-color-id') && $('fl-var-color-id').value) || 0);
            colorNombre = ($('fl-var-color-nombre') && $('fl-var-color-nombre').value) || '';
            if (!colorId) {
                msg('Elegí el color.', false);
                foco('fl-var-color-codigo');
                return;
            }
        } else if (!sin) {
            combId = +(($('fl-var-comb-id') && $('fl-var-comb-id').value) || 0);
            combNombre = ($('fl-var-comb-nombre') && $('fl-var-comb-nombre').value) || '';
            if (!combId) {
                msg('Elegí la combinación.', false);
                foco('fl-var-comb-codigo');
                return;
            }
        }
        var cant = parseFloat($('fl-var-cant').value) || 0;
        if (cant <= 0) {
            msg('La cantidad tiene que ser mayor a cero.', false);
            return;
        }
        var articulo = pendingArticulo;
        overlay(true, 'Precio…');
        get(CFG.urls.precio + '?articulo_id=' + articulo.id
            + '&combinacion_id=' + (combId || 0)
            + '&talle_id=' + talleId
            + '&local_id=' + CFG.localId
        ).then(function (res) {
            var precio = +((res.body && res.body.precio) || 0);
            cart.push({
                articulo_id: articulo.id,
                sku: articulo.sku,
                descripcion: articulo.descripcion,
                modo: modo,
                talle_id: talleId,
                talle_nombre: talleNombre,
                color_id: colorId,
                color_nombre: colorNombre,
                combinacion_id: combId,
                combinacion_nombre: combNombre,
                cantidad: cant,
                precio: precio,
            });
            pendingArticulo = null;
            pendingVariantes = null;
            window.jQuery('#fl-modal-var').modal('hide');
            $('fl-results').innerHTML = '';
            $('fl-q').value = '';
            renderCart();
            overlay(false);
            $('fl-q').focus();
        }).catch(function () {
            overlay(false);
            msg('No se pudo leer el precio.', false);
        });
    }

    function etiquetaVariante(line) {
        if (line.modo === 'sin_variante') return '—';
        if (line.modo === 'color_talle') {
            return (line.color_nombre || 'Color') + ' / ' + (line.talle_nombre || line.talle_id);
        }
        return (line.combinacion_nombre || 'Comb.') + ' / ' + (line.talle_nombre || line.talle_id);
    }

    function totalCart() {
        return cart.reduce(function (s, line) {
            return s + (Number(line.cantidad) || 0) * (Number(line.precio) || 0);
        }, 0);
    }

    function renderCart() {
        var tb = $('fl-cart-body');
        if (!tb) return;
        tb.innerHTML = '';
        cart.forEach(function (line, idx) {
            var tr = document.createElement('tr');
            tr.innerHTML =
                '<td><strong>' + escapeHtml(line.sku) + '</strong><br><span style="color:#5d6d7e;font-size:12px;">' + escapeHtml(line.descripcion) + '</span></td>' +
                '<td style="font-size:12px;">' + escapeHtml(etiquetaVariante(line)) + '</td>' +
                '<td><input type="number" step="1" min="1" data-i="' + idx + '" class="fl-cant" value="' + line.cantidad + '"></td>' +
                '<td><input type="number" step="0.01" min="0" data-i="' + idx + '" class="fl-precio" value="' + line.precio + '"></td>' +
                '<td><button type="button" class="fl-btn fl-btn-ghost fl-del" data-i="' + idx + '">×</button></td>';
            tb.appendChild(tr);
        });
        tb.querySelectorAll('.fl-cant').forEach(function (inp) {
            inp.addEventListener('change', function () {
                cart[+inp.dataset.i].cantidad = Math.abs(parseFloat(inp.value) || 0);
                pintarTotal();
            });
        });
        tb.querySelectorAll('.fl-precio').forEach(function (inp) {
            inp.addEventListener('change', function () {
                cart[+inp.dataset.i].precio = parseFloat(inp.value) || 0;
                pintarTotal();
            });
        });
        tb.querySelectorAll('.fl-del').forEach(function (btn) {
            btn.addEventListener('click', function () {
                cart.splice(+btn.dataset.i, 1);
                renderCart();
            });
        });
        pintarTotal();
    }

    function pintarTotal() {
        var el = $('fl-totales');
        if (!el) return;
        el.textContent = '$ ' + totalCart().toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function imprimirPdf(url) {
        var iframe = $('fl-iframe-impresion');
        if (!iframe || !url) return Promise.resolve(false);
        return fetch(url, { credentials: 'same-origin' }).then(function (res) {
            if (!res.ok) throw new Error('pdf');
            return res.blob();
        }).then(function (blob) {
            var blobUrl = URL.createObjectURL(blob);
            return new Promise(function (resolve, reject) {
                iframe.addEventListener('load', function onLoad() {
                    iframe.removeEventListener('load', onLoad);
                    resolve(blobUrl);
                });
                iframe.addEventListener('error', function onError() {
                    iframe.removeEventListener('error', onError);
                    reject(new Error('pdf'));
                });
                iframe.src = blobUrl;
            });
        }).then(function () {
            var win = iframe.contentWindow;
            if (win) {
                win.focus();
                win.print();
            }
            return true;
        }).catch(function () {
            return false;
        });
    }

    function emitir() {
        if (!CFG.turnoId) {
            msg('Abrí el turno del local antes de emitir.', false);
            return;
        }
        if (!cart.length) {
            msg('Agregá al menos un artículo.', false);
            return;
        }
        var fecha = ($('fl-nc-fecha') && $('fl-nc-fecha').value) || '';
        overlay(true, 'Emitiendo nota de crédito…');
        msg('', true);
        post(CFG.urls.emitir, {
            local_id: CFG.localId,
            fecha: fecha,
            lineas: cart.map(function (line) {
                return {
                    articulo_id: line.articulo_id,
                    cantidad: line.cantidad,
                    precio: line.precio,
                    talle_id: line.talle_id || 0,
                    color_id: line.color_id || 0,
                    combinacion_id: line.combinacion_id || 0,
                };
            }),
        }).then(function (res) {
            overlay(false);
            var body = res.body || {};
            if (!body.ok) {
                msg(body.error || 'No se pudo emitir.', false);
                return;
            }
            cart = [];
            renderCart();
            msg(body.mensaje || 'Nota de crédito generada.', true);
            if (body.pdf_url) {
                imprimirPdf(body.pdf_url).then(function (ok) {
                    if (!ok) {
                        window.open(body.pdf_url, '_blank', 'noopener');
                    }
                });
            }
        }).catch(function () {
            overlay(false);
            msg('No se pudo emitir. Reintentá.', false);
        });
    }

    function enterEnVariante(e) {
        if (e.key !== 'Enter') return;
        var modal = $('fl-modal-var');
        if (!modal || !modal.classList.contains('show')) return;
        if (document.querySelector('#consultatalleModal.show, #consultacolorModal.show, #consultacombinacionModal.show')) return;
        var t = e.target;
        if (!t || !t.closest || !t.closest('#fl-modal-var')) return;
        e.preventDefault();
        if (t.id === 'fl-var-talle-codigo') {
            var talle = buscarEnLista((pendingVariantes && pendingVariantes.talles) || [], t.value);
            if (!talle) { msg('Talle no encontrado. Usá F1.', false); return; }
            aplicarCampo('talle', talle);
            if (pendingArticulo && pendingArticulo.modo === 'color_talle') foco('fl-var-color-codigo');
            else foco('fl-var-comb-codigo');
            return;
        }
        if (t.id === 'fl-var-color-codigo') {
            var color = buscarEnLista((pendingVariantes && pendingVariantes.colores) || [], t.value);
            if (!color) { msg('Color no encontrado. Usá F1.', false); return; }
            aplicarCampo('color', color);
            foco('fl-var-cant');
            return;
        }
        if (t.id === 'fl-var-comb-codigo') {
            var comb = buscarEnLista((pendingVariantes && pendingVariantes.combinaciones) || [], t.value);
            if (!comb) { msg('Combinación no encontrada. Usá F1.', false); return; }
            aplicarCampo('comb', comb);
            foco('fl-var-cant');
            return;
        }
        if (t.id === 'fl-var-cant') confirmarVariante();
    }

    function renderCombinaciones(consulta) {
        var tb = $('datoscombinacion');
        if (!tb) return;
        var lista = (pendingVariantes && pendingVariantes.combinaciones) || [];
        var q = String(consulta || '').trim().toLowerCase();
        if (q) {
            lista = lista.filter(function (c) {
                return String(c.codigo || '').toLowerCase().indexOf(q) >= 0
                    || String(c.nombre || '').toLowerCase().indexOf(q) >= 0;
            });
        }
        if (!lista.length) {
            tb.innerHTML = '<tr><td colspan="4" class="text-muted">Sin resultados</td></tr>';
            return;
        }
        tb.innerHTML = lista.map(function (c) {
            return '<tr data-id="' + c.id + '" data-codigo="' + escapeHtml(c.codigo || '') + '" data-nombre="' + escapeHtml(c.nombre || '') + '">'
                + '<td>' + c.id + '</td><td>' + escapeHtml(c.codigo || '') + '</td><td>' + escapeHtml(c.nombre || '') + '</td>'
                + '<td><button type="button" class="btn btn-sm btn-warning elige-combinacion">Elegir</button></td></tr>';
        }).join('');
    }

    window.payloadExtraConsultaTalle = function () {
        var talles = (pendingVariantes && pendingVariantes.talles) || [];
        return { ids: talles.map(function (t) { return +t.id; }).filter(Boolean) };
    };
    window.payloadExtraConsultaColor = function () {
        var colores = (pendingVariantes && pendingVariantes.colores) || [];
        return { ids: colores.map(function (c) { return +c.id; }).filter(Boolean) };
    };

    document.addEventListener('DOMContentLoaded', function () {
        var q = $('fl-q');
        if (q) {
            q.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    buscar();
                }
            });
        }
        var buscarBtn = $('fl-buscar');
        if (buscarBtn) buscarBtn.addEventListener('click', buscar);
        var emitirBtn = $('fl-emitir');
        if (emitirBtn) emitirBtn.addEventListener('click', emitir);
        var ok = $('fl-var-ok');
        if (ok) ok.addEventListener('click', confirmarVariante);
        var modal = $('fl-modal-var');
        if (modal) modal.addEventListener('keydown', enterEnVariante, true);

        if (window.jQuery) {
            var jq = window.jQuery;
            jq('#fl-modal-var').on('shown.bs.modal', function () {
                setTimeout(function () {
                    if (pendingArticulo && pendingArticulo.modo === 'sin_variante') foco('fl-var-cant');
                    else foco('fl-var-talle-codigo');
                }, 30);
            });
            jq('#fl-var-talle-wrap').on('talle:seleccionado', function () {
                setTimeout(function () {
                    if (pendingArticulo && pendingArticulo.modo === 'color_talle') foco('fl-var-color-codigo');
                    else foco('fl-var-comb-codigo');
                }, 120);
            });
            jq('#fl-var-color-wrap').on('color:seleccionado', function () {
                setTimeout(function () { foco('fl-var-cant'); }, 120);
            });
            jq(document).on('click', '.consultacombinacion', function (e) {
                e.preventDefault();
                jq('#consultacombinacion').val('');
                renderCombinaciones('');
                jq('#consultacombinacionModal').modal('show');
            });
            jq(document).on('keyup', '#consultacombinacion', function () {
                renderCombinaciones(String(jq(this).val() || ''));
            });
            jq(document).on('click', '.elige-combinacion', function () {
                var tr = jq(this).closest('tr');
                aplicarCampo('comb', {
                    id: tr.data('id'),
                    codigo: tr.data('codigo'),
                    nombre: tr.data('nombre'),
                });
                jq('#consultacombinacionModal').modal('hide');
                foco('fl-var-cant');
            });
        }
    });
})();
