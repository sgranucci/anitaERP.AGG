(function () {
    'use strict';

    var CFG = window.FL_POS || {};
    var cart = [];
    var pendingArticulo = null;
    var pendingVariantes = null;
    var buscaTimer = null;
    var cuentacajaxcodigo = null;
    var clientePos = null;
    var netoActual = 0;

    function $(id) { return document.getElementById(id); }

    function money(n) {
        return '$ ' + (Number(n) || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function esTeclaF1(e) {
        return e.key === 'F1' || e.code === 'F1' || e.keyCode === 112;
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
        el.className = 'fl-msg mt-2 ' + (ok ? 'ok' : 'err');
        el.textContent = text || '';
    }

    function parseJsonResponse(r) {
        var ct = r.headers.get('content-type') || '';
        if (ct.indexOf('json') === -1) {
            return r.text().then(function () {
                throw new Error(r.status === 403 || r.status === 401
                    ? 'Sin permiso o sesión vencida'
                    : 'Respuesta inválida del servidor (' + r.status + ')');
            });
        }
        return r.json().then(function (j) {
            return { status: r.status, body: j };
        });
    }

    function post(url, body) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': CFG.csrf,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(Object.assign({ _token: CFG.csrf }, body || {}))
        }).then(parseJsonResponse);
    }

    function get(url) {
        return fetch(url, {
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': CFG.csrf
            }
        }).then(parseJsonResponse);
    }

    function escapeHtml(s) {
        return String(s || '').replace(/[&<>"']/g, function (c) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
        });
    }

    function htmlIconoMedio(cuenta) {
        var icono = (cuenta && cuenta.icono) || 'fa fa-cash-register';
        var color = (cuenta && cuenta.icono_color) || 'text-secondary';
        if (icono === 'gastro-icon-mercadopago') {
            return '<span class="gastro-icon-mercadopago" aria-hidden="true"></span>';
        }
        return '<i class="' + escapeHtml(icono) + ' ' + escapeHtml(color) + '" aria-hidden="true"></i>';
    }

    function etiquetaCortaMedio(cuenta) {
        if (cuenta && cuenta.etiqueta_boton) return cuenta.etiqueta_boton;
        var codigo = String((cuenta && cuenta.codigo) || '').trim();
        if (codigo) return codigo;
        return String((cuenta && cuenta.nombre) || 'Medio');
    }

    /* ——— Carrito ——— */
    function renderCart() {
        var tb = $('fl-cart-body');
        if (!tb) return;
        tb.innerHTML = '';
        cart.forEach(function (line, idx) {
            var tr = document.createElement('tr');
            var varLabel = line.modo === 'color_talle'
                ? ((line.color_nombre || ('C' + (line.color_id || ''))) + ' / ' + (line.talle_nombre || line.talle_id))
                : ((line.combinacion_nombre || ('Comb ' + line.combinacion_id)) + ' / ' + (line.talle_nombre || line.talle_id));
            tr.innerHTML =
                '<td><strong>' + escapeHtml(line.sku) + '</strong><br><span style="color:#5d6d7e;font-size:12px;">' + escapeHtml(line.descripcion) + '</span></td>' +
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

    function preview() {
        post(CFG.urls.preview, { lineas: cart }).then(function (res) {
            var b = res.body || {};
            netoActual = Number(b.neto || 0);
            var el = $('fl-totales');
            if (el) {
                el.childNodes[0].textContent = money(netoActual);
                var det = $('fl-totales-detalle');
                if (det) {
                    det.textContent = 'FAC ' + money(b.neto_fac || 0) + ' · NC ' + money(b.neto_nc || 0);
                }
            }
        }).catch(function () {});
    }

    /* ——— Cobranza ——— */
    function filaCobranzaDesdeTemplate() {
        var tpl = $('fl-template-renglon-cuenta');
        if (!tpl || !tpl.content) return null;
        return tpl.content.firstElementChild.cloneNode(true);
    }

    function agregarRenglonCobranza(monto, enfocar) {
        var tbody = $('tbody-fl-cuenta-table');
        if (!tbody) return null;
        var tr = filaCobranzaDesdeTemplate();
        if (!tr) return null;
        tbody.appendChild(tr);
        if (monto != null && monto !== '') {
            var m = tr.querySelector('.monto');
            if (m) m.value = Number(monto).toFixed(2);
        }
        if (enfocar) {
            var cod = tr.querySelector('.codigocuentacaja');
            if (cod) cod.focus();
        }
        return tr;
    }

    function asignarCuentaEnFila(tr, cuenta) {
        if (!tr || !cuenta) return;
        var idInp = tr.querySelector('.cuentacaja_id');
        var cod = tr.querySelector('.codigocuentacaja');
        var nom = tr.querySelector('.nombre');
        if (idInp) idInp.value = cuenta.id || '';
        if (cod) cod.value = cuenta.codigo || '';
        if (nom) nom.value = cuenta.nombre || '';
        var monto = tr.querySelector('.monto');
        if (monto && (!monto.value || Number(monto.value) === 0) && netoActual > 0) {
            var ya = totalCobrado();
            var resto = Math.max(0, netoActual - (ya - (parseFloat(monto.value) || 0)));
            monto.value = resto.toFixed(2);
        }
    }

    function totalCobrado() {
        var t = 0;
        document.querySelectorAll('#tbody-fl-cuenta-table .monto').forEach(function (inp) {
            t += parseFloat(inp.value) || 0;
        });
        return t;
    }

    function renderMediosRapidos() {
        var wrap = $('fl-medios-rapidos');
        if (!wrap) return;
        wrap.innerHTML = '';
        var lista = CFG.cuentas || [];
        if (!lista.length || !CFG.turnoId) {
            wrap.classList.add('d-none');
            return;
        }
        wrap.classList.remove('d-none');
        lista.forEach(function (cuenta) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-sm btn-outline-secondary fl-medio-rapido';
            btn.title = (cuenta.codigo ? cuenta.codigo + ' — ' : '') + (cuenta.nombre || '');
            btn.dataset.cuentacajaId = String(cuenta.id);
            btn.innerHTML = htmlIconoMedio(cuenta)
                + '<span class="fl-medio-cod">' + escapeHtml(cuenta.codigo || '') + '</span>'
                + '<span>' + escapeHtml(etiquetaCortaMedio(cuenta)) + '</span>';
            btn.addEventListener('click', function () {
                seleccionarMedioRapido(cuenta);
            });
            wrap.appendChild(btn);
        });
    }

    function seleccionarMedioRapido(cuenta) {
        if (!CFG.turnoId) {
            msg('Abrí la caja para cobrar', false);
            return;
        }
        var tbody = $('tbody-fl-cuenta-table');
        if (!tbody) return;
        var tr = Array.prototype.find.call(tbody.querySelectorAll('tr'), function (row) {
            return !(row.querySelector('.cuentacaja_id').value || '').trim();
        });
        if (!tr) {
            tr = agregarRenglonCobranza(0, false);
        }
        if (!tr) return;
        asignarCuentaEnFila(tr, cuenta);
        var monto = tr.querySelector('.monto');
        if (monto) monto.focus();
    }

    function mediosPago() {
        var out = [];
        document.querySelectorAll('#tbody-fl-cuenta-table tr').forEach(function (row) {
            var id = +(row.querySelector('.cuentacaja_id').value || 0);
            var monto = parseFloat(row.querySelector('.monto').value) || 0;
            if (id > 0 && monto !== 0) {
                out.push({ cuentacaja_id: id, moneda_id: 1, monto: monto });
            }
        });
        return out;
    }

    function initCobranza() {
        renderMediosRapidos();

        var addBtn = $('fl-add-medio');
        if (addBtn) {
            addBtn.addEventListener('click', function () {
                agregarRenglonCobranza(0, true);
            });
        }

        document.addEventListener('click', function (e) {
            var rm = e.target.closest('.fl-eliminar-cuenta');
            if (rm) {
                var tr = rm.closest('tr');
                if (tr) tr.remove();
                return;
            }
            var lupa = e.target.closest('#tbody-fl-cuenta-table .consultacuentacaja');
            if (lupa) {
                e.preventDefault();
                cuentacajaxcodigo = lupa.closest('tr').querySelector('.cuentacaja_id');
                if (window.jQuery) {
                    window.jQuery('#consultacuentacaja').val('');
                    window.jQuery('#consultacuentacajaModal').modal('show');
                }
            }
        });

        document.addEventListener('keydown', function (e) {
            var t = e.target;
            if (!t || !t.classList.contains('codigocuentacaja')) return;
            if (!t.closest('#tbody-fl-cuenta-table')) return;
            if (esTeclaF1(e)) {
                e.preventDefault();
                e.stopPropagation();
                var btn = t.closest('tr').querySelector('.consultacuentacaja');
                if (btn) btn.click();
                return;
            }
            if (e.key === 'Enter') {
                e.preventDefault();
                resolverCodigoCuenta(t.closest('tr'), true);
            }
        }, true);

        document.addEventListener('blur', function (e) {
            var t = e.target;
            if (!t || !t.classList.contains('codigocuentacaja')) return;
            if (!t.closest('#tbody-fl-cuenta-table')) return;
            if (window.jQuery && window.jQuery('#consultacuentacajaModal').hasClass('show')) return;
            resolverCodigoCuenta(t.closest('tr'), false);
        }, true);

        if (window.jQuery) {
            window.jQuery(document)
                .off('click.flCuentaElige', '.eligeconsultacuentacaja')
                .on('click.flCuentaElige', '.eligeconsultacuentacaja', function () {
                    if (!cuentacajaxcodigo) return;
                    var trModal = window.jQuery(this).parents('tr');
                    var cuenta = {
                        id: parseInt(trModal.find('.cuentacaja_id').html(), 10),
                        nombre: trModal.find('.nombre').html(),
                        codigo: trModal.find('.codigo').html()
                    };
                    var tr = cuentacajaxcodigo.closest('tr');
                    asignarCuentaEnFila(tr, cuenta);
                    window.jQuery('#consultacuentacajaModal').modal('hide');
                    cuentacajaxcodigo = null;
                });
        }
    }

    function resolverCodigoCuenta(tr, avisar) {
        if (!tr) return;
        var codInp = tr.querySelector('.codigocuentacaja');
        var codigo = String((codInp && codInp.value) || '').trim();
        if (!codigo) {
            tr.querySelector('.cuentacaja_id').value = '';
            tr.querySelector('.nombre').value = '';
            return;
        }
        var cuenta = (CFG.cuentas || []).find(function (c) {
            return String(c.codigo).toLowerCase() === codigo.toLowerCase()
                || String(c.id) === codigo;
        });
        if (cuenta) {
            asignarCuentaEnFila(tr, cuenta);
            return;
        }
        tr.querySelector('.cuentacaja_id').value = '';
        tr.querySelector('.nombre').value = '';
        if (avisar) {
            setTimeout(function () {
                alert('Cuenta no habilitada en este local: ' + codigo);
                if (codInp) codInp.focus();
            }, 0);
        }
    }

    /* ——— Artículos / F1 ——— */
    function buscar() {
        var q = ($('fl-q') && $('fl-q').value || '').trim();
        var box = $('fl-results');
        if (!box) return;
        if (q.length < 2) {
            box.innerHTML = '<div class="fl-empty">Escribí al menos 2 caracteres o usá F1</div>';
            return;
        }
        box.innerHTML = '<div class="fl-empty">Buscando…</div>';
        get(CFG.urls.buscar + '?q=' + encodeURIComponent(q) + '&local_id=' + (CFG.localId || ''))
            .then(function (res) {
                var j = res.body || {};
                box.innerHTML = '';
                if (res.status >= 400) {
                    box.innerHTML = '<div class="fl-err">' + escapeHtml(j.error || 'No se pudo buscar') + '</div>';
                    return;
                }
                (j.data || []).forEach(function (a) {
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.textContent = a.sku + ' — ' + a.descripcion;
                    btn.addEventListener('click', function () { elegirArticulo(a); });
                    box.appendChild(btn);
                });
                if (!(j.data || []).length) {
                    box.innerHTML = '<div class="fl-empty">Sin resultados en canal Local</div>';
                } else if ((j.data || []).length === 1 && q.length >= 2) {
                    // no auto-elegir: el operador confirma
                }
            })
            .catch(function (e) {
                box.innerHTML = '<div class="fl-err">' + escapeHtml(e.message || 'Error de red') + '</div>';
            });
    }

    function abrirConsultaArticulo() {
        var q = $('fl-q');
        if (q) q.focus();
        var val = (q && q.value || '').trim();
        if (val.length >= 2) {
            buscar();
            return;
        }
        var box = $('fl-results');
        if (box) {
            box.innerHTML = '<div class="fl-empty">Escribí SKU o descripción y Enter · o F1 con texto para consultar</div>';
        }
        if (q) {
            q.select();
        }
    }

    function limpiarCampoVariante(prefix) {
        var id = $('fl-var-' + prefix + '-id');
        var cod = $('fl-var-' + prefix + '-codigo');
        var nom = $('fl-var-' + prefix + '-nombre');
        if (id) id.value = '';
        if (cod) cod.value = '';
        if (nom) nom.value = '';
    }

    function elegirArticulo(a) {
        pendingArticulo = a;
        get(CFG.urls.variantes + '/' + a.id).then(function (res) {
            var v = res.body || {};
            if (res.status >= 400) {
                msg(v.error || 'No se pudieron cargar variantes', false);
                return;
            }
            pendingVariantes = v;
            $('fl-modal-var-title').textContent = a.sku + ' — ' + a.descripcion;
            limpiarCampoVariante('talle');
            limpiarCampoVariante('color');
            limpiarCampoVariante('comb');
            pendingArticulo.modo = v.modo;
            var aviso = $('fl-var-aviso');
            if (aviso) {
                aviso.classList.add('d-none');
                aviso.textContent = '';
            }
            if (v.modo === 'color_talle') {
                $('fl-var-color-wrap').style.display = '';
                $('fl-var-comb-wrap').style.display = 'none';
            } else {
                $('fl-var-color-wrap').style.display = 'none';
                $('fl-var-comb-wrap').style.display = '';
                var combs = v.combinaciones || [];
                if (!combs.length && aviso) {
                    aviso.textContent = 'El artículo no tiene combinaciones activas. No se puede vender hasta activar una en el maestro.';
                    aviso.classList.remove('d-none');
                }
            }
            $('fl-var-cant').value = '1';
            window.jQuery('#fl-modal-var').modal('show');
        }).catch(function (e) {
            msg(e.message || 'Error al cargar variantes', false);
        });
    }

    function focoTalleVariante() {
        var t = $('fl-var-talle-codigo');
        if (!t) return;
        t.focus();
        if (typeof t.select === 'function') t.select();
    }

    function focoSiguienteTrasTalle() {
        if (!pendingArticulo) return;
        if (pendingArticulo.modo === 'color_talle') {
            var c = $('fl-var-color-codigo');
            if (c) { c.focus(); if (c.select) c.select(); }
            return;
        }
        var comb = $('fl-var-comb-codigo');
        if (comb) { comb.focus(); if (comb.select) comb.select(); }
    }

    function focoCantidadVariante() {
        var cant = $('fl-var-cant');
        if (!cant) return;
        cant.focus();
        if (typeof cant.select === 'function') cant.select();
    }

    function modalVarianteVisible() {
        var m = $('fl-modal-var');
        return !!(m && m.classList.contains('show'));
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

    function aplicarTalleLocal(row) {
        if (!row) return false;
        var codigo = String(row.codigo || '').trim() || String(row.nombre || '');
        if ($('fl-var-talle-id')) $('fl-var-talle-id').value = row.id;
        if ($('fl-var-talle-codigo')) $('fl-var-talle-codigo').value = codigo;
        if ($('fl-var-talle-nombre')) $('fl-var-talle-nombre').value = row.nombre || '';
        return true;
    }

    function aplicarColorLocal(row) {
        if (!row) return false;
        var codigo = String(row.codigo || '').trim() || String(row.nombre || '');
        if ($('fl-var-color-id')) $('fl-var-color-id').value = row.id;
        if ($('fl-var-color-codigo')) $('fl-var-color-codigo').value = codigo;
        if ($('fl-var-color-nombre')) $('fl-var-color-nombre').value = row.nombre || '';
        return true;
    }

    function aplicarCombLocal(row) {
        if (!row) return false;
        if ($('fl-var-comb-id')) $('fl-var-comb-id').value = row.id;
        if ($('fl-var-comb-codigo')) $('fl-var-comb-codigo').value = row.codigo || '';
        if ($('fl-var-comb-nombre')) $('fl-var-comb-nombre').value = row.nombre || '';
        return true;
    }

    function enterEnCampoVariante(e) {
        if (e.key !== 'Enter' && e.keyCode !== 13) return;
        if (!modalVarianteVisible()) return;
        // Si hay un modal de consulta hijo abierto, no interferir
        if (document.querySelector('#consultatalleModal.show, #consultacolorModal.show, #consultacombinacionModal.show')) {
            return;
        }
        var t = e.target;
        if (!t || !t.closest || !t.closest('#fl-modal-var')) return;

        e.preventDefault();
        e.stopPropagation();
        if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();

        if (t.id === 'fl-var-talle-codigo' || t.classList.contains('codigotalle')) {
            var talle = buscarEnLista((pendingVariantes && pendingVariantes.talles) || [], t.value);
            if (!talle) {
                if ($('fl-var-talle-id')) $('fl-var-talle-id').value = '';
                if ($('fl-var-talle-nombre')) $('fl-var-talle-nombre').value = '';
                msg('Talle no encontrado. Usá F1 o el número (ej. 31).', false);
                t.focus();
                return;
            }
            aplicarTalleLocal(talle);
            focoSiguienteTrasTalle();
            return;
        }

        if (t.id === 'fl-var-color-codigo' || t.classList.contains('codigocolor')) {
            var color = buscarEnLista((pendingVariantes && pendingVariantes.colores) || [], t.value);
            if (!color) {
                if ($('fl-var-color-id')) $('fl-var-color-id').value = '';
                if ($('fl-var-color-nombre')) $('fl-var-color-nombre').value = '';
                msg('Color no encontrado. Usá F1.', false);
                t.focus();
                return;
            }
            aplicarColorLocal(color);
            focoCantidadVariante();
            return;
        }

        if (t.id === 'fl-var-comb-codigo' || t.classList.contains('codigocombinacion')) {
            var comb = buscarEnLista((pendingVariantes && pendingVariantes.combinaciones) || [], t.value);
            if (!comb) {
                if ($('fl-var-comb-id')) $('fl-var-comb-id').value = '';
                if ($('fl-var-comb-nombre')) $('fl-var-comb-nombre').value = '';
                msg('Combinación no encontrada. Usá F1.', false);
                t.focus();
                return;
            }
            aplicarCombLocal(comb);
            focoCantidadVariante();
            return;
        }

        if (t.id === 'fl-var-cant') {
            confirmarVariante();
            return;
        }

        if (t.id === 'fl-var-ok') {
            confirmarVariante();
        }
    }

    function initVarianteTeclado() {
        var modal = $('fl-modal-var');
        if (!modal) return;

        // Capture: gana al $('input').keydown de cuentacaja/consulta.js que hace return false
        modal.removeEventListener('keydown', enterEnCampoVariante, true);
        modal.addEventListener('keydown', enterEnCampoVariante, true);

        if (window.jQuery) {
            var $jq = window.jQuery;
            $jq('#fl-modal-var')
                .off('shown.bs.modal.flVarFoco')
                .on('shown.bs.modal.flVarFoco', function () {
                    setTimeout(focoTalleVariante, 30);
                });

            // Al elegir desde modal F1, avanzar al siguiente
            $jq('#fl-var-talle-wrap')
                .off('talle:seleccionado.flVarNav')
                .on('talle:seleccionado.flVarNav', function () {
                    if (!modalVarianteVisible()) return;
                    setTimeout(function () {
                        if ($jq('#consultatalleModal').hasClass('show')) return;
                        focoSiguienteTrasTalle();
                    }, 120);
                });

            $jq('#fl-var-color-wrap')
                .off('color:seleccionado.flVarNav')
                .on('color:seleccionado.flVarNav', function () {
                    if (!modalVarianteVisible()) return;
                    setTimeout(function () {
                        if ($jq('#consultacolorModal').hasClass('show')) return;
                        focoCantidadVariante();
                    }, 120);
                });
        }
    }

    window.payloadExtraConsultaTalle = function () {
        if (!pendingVariantes || !Array.isArray(pendingVariantes.talles)) return { ids: [] };
        return { ids: pendingVariantes.talles.map(function (t) { return +t.id; }).filter(Boolean) };
    };
    window.payloadExtraConsultaColor = function () {
        if (!pendingVariantes || !Array.isArray(pendingVariantes.colores)) return { ids: [] };
        return { ids: pendingVariantes.colores.map(function (c) { return +c.id; }).filter(Boolean) };
    };

    function listarCombinacionesFiltradas(consulta) {
        var lista = (pendingVariantes && pendingVariantes.combinaciones) || [];
        var q = String(consulta || '').trim().toLowerCase();
        if (!q) return lista;
        return lista.filter(function (c) {
            return String(c.codigo || '').toLowerCase().indexOf(q) >= 0
                || String(c.nombre || '').toLowerCase().indexOf(q) >= 0
                || String(c.id) === q;
        });
    }

    function renderModalCombinacion(consulta) {
        var tb = $('datoscombinacion');
        if (!tb) return;
        var filas = listarCombinacionesFiltradas(consulta);
        if (!filas.length) {
            tb.innerHTML = '<tr><td colspan="4" class="text-muted">Sin resultados</td></tr>';
            return;
        }
        tb.innerHTML = filas.map(function (c) {
            return '<tr data-id="' + c.id + '" data-codigo="' + escapeHtml(c.codigo || '') + '" data-nombre="' + escapeHtml(c.nombre || '') + '">'
                + '<td>' + c.id + '</td>'
                + '<td>' + escapeHtml(c.codigo || '') + '</td>'
                + '<td>' + escapeHtml(c.nombre || '') + '</td>'
                + '<td><button type="button" class="btn btn-sm btn-primary elige-combinacion">Elegir</button></td>'
                + '</tr>';
        }).join('');
    }

    function initCombinacionModal() {
        if (!window.jQuery) return;
        var $ = window.jQuery;
        $(document).on('click', '.consultacombinacion', function (e) {
            e.preventDefault();
            $('#consultacombinacion').val('');
            renderModalCombinacion('');
            $('#consultacombinacionModal').modal('show');
        });
        $('#consultacombinacionModal').on('shown.bs.modal', function () {
            $('#consultacombinacion').trigger('focus');
        });
        $(document).on('keyup', '#consultacombinacion', function (e) {
            if (e.which === 13) return;
            renderModalCombinacion(String($(this).val() || '').trim());
        });
        $(document).on('keydown', '#consultacombinacion', function (e) {
            if (e.which !== 13) return;
            e.preventDefault();
            var $btn = $('#datoscombinacion .elige-combinacion').first();
            if ($btn.length) $btn.trigger('click');
        });
        $(document).on('click', '.elige-combinacion', function () {
            var $tr = $(this).closest('tr');
            $('#fl-var-comb-id').val($tr.data('id'));
            $('#fl-var-comb-codigo').val($tr.data('codigo'));
            $('#fl-var-comb-nombre').val($tr.data('nombre'));
            $('#consultacombinacionModal').modal('hide');
            setTimeout(focoCantidadVariante, 50);
        });
        $(document).on('click', '#aceptaconsultacombinacionModal', function () {
            var $btn = $('#datoscombinacion .elige-combinacion').first();
            if ($btn.length) $btn.trigger('click');
            else $('#consultacombinacionModal').modal('hide');
        });
        $(document).on('keydown', '#fl-var-comb-codigo', function (e) {
            if (esTeclaF1(e)) {
                e.preventDefault();
                e.stopPropagation();
                $(this).closest('.tm-combinacion-campo').find('.consultacombinacion').trigger('click');
            }
            // Enter lo maneja initVarianteTeclado (navegación)
        });
    }

    function confirmarVariante() {
        if (!pendingArticulo) return;
        var talleId = +(($('fl-var-talle-id') && $('fl-var-talle-id').value) || 0);
        var talleNombre = ($('fl-var-talle-nombre') && $('fl-var-talle-nombre').value) || '';
        var talleCodigo = ($('fl-var-talle-codigo') && $('fl-var-talle-codigo').value) || '';
        if (!talleId) {
            msg('Elegí el talle (F1 / código + Enter)', false);
            if ($('fl-var-talle-codigo')) $('fl-var-talle-codigo').focus();
            return;
        }
        var colorId = null;
        var colorNombre = '';
        var combinacionId = null;
        var combinacionNombre = '';
        if (pendingArticulo.modo === 'color_talle') {
            colorId = +(($('fl-var-color-id') && $('fl-var-color-id').value) || 0) || null;
            colorNombre = ($('fl-var-color-nombre') && $('fl-var-color-nombre').value) || '';
            if (!colorId) {
                msg('Elegí el color (F1 / código + Enter)', false);
                if ($('fl-var-color-codigo')) $('fl-var-color-codigo').focus();
                return;
            }
        } else {
            combinacionId = +(($('fl-var-comb-id') && $('fl-var-comb-id').value) || 0) || null;
            combinacionNombre = ($('fl-var-comb-nombre') && $('fl-var-comb-nombre').value)
                || (($('fl-var-comb-codigo') && $('fl-var-comb-codigo').value) || '');
            if (!combinacionId) {
                msg('Elegí la combinación (F1 / código + Enter)', false);
                if ($('fl-var-comb-codigo')) $('fl-var-comb-codigo').focus();
                return;
            }
        }
        var cant = parseFloat($('fl-var-cant').value) || 0;
        if (!cant) {
            msg('Cantidad inválida', false);
            return;
        }
        overlay(true, 'Precio…');
        get(CFG.urls.precio + '?articulo_id=' + pendingArticulo.id +
            '&combinacion_id=' + (combinacionId || 0) +
            '&talle_id=' + talleId +
            '&local_id=' + CFG.localId
        ).then(function (res) {
            var p = res.body || {};
            cart.push({
                articulo_id: pendingArticulo.id,
                sku: pendingArticulo.sku,
                descripcion: pendingArticulo.descripcion,
                modo: pendingArticulo.modo,
                talle_id: talleId,
                talle_nombre: talleNombre || talleCodigo,
                color_id: colorId,
                color_nombre: colorNombre,
                combinacion_id: combinacionId,
                combinacion_nombre: combinacionNombre,
                cantidad: cant,
                precio: +(p.precio || 0),
                descuento: 0
            });
            pendingArticulo = null;
            pendingVariantes = null;
            window.jQuery('#fl-modal-var').modal('hide');
            $('fl-results').innerHTML = '';
            $('fl-q').value = '';
            renderCart();
            overlay(false);
            $('fl-q').focus();
        }).catch(function (e) {
            overlay(false);
            msg(e.message || 'No se pudo obtener precio', false);
        });
    }

    /* ——— Cliente / Factura A ——— */
    function actualizarLetraBadge(cli) {
        var badge = $('fl-letra-badge');
        var extra = $('fl-cliente-extra');
        if (!badge) return;
        if (!cli || !cli.id) {
            badge.className = 'badge badge-secondary';
            badge.textContent = 'Letra B/C · CF';
            if (extra) extra.textContent = '';
            clientePos = null;
            return;
        }
        var letra = String(cli.letra || 'B').toUpperCase();
        badge.className = 'badge ' + (letra === 'A' ? 'badge-danger' : 'badge-info');
        badge.textContent = 'Factura ' + letra;
        if (extra) {
            var bits = [];
            if (cli.condicioniva) bits.push(cli.condicioniva);
            if (cli.numerodocumento) bits.push('Doc ' + cli.numerodocumento);
            extra.textContent = bits.join(' · ');
        }
        clientePos = cli;
    }

    function cargarClientePos(opts) {
        opts = opts || {};
        var id = opts.id || +(($('cliente_id') && $('cliente_id').value) || 0);
        var codigo = opts.codigo || (($('codigocliente') && $('codigocliente').value) || '');
        var qs = id > 0 ? ('id=' + id) : ('codigo=' + encodeURIComponent(codigo));
        if (!id && !String(codigo).trim()) {
            actualizarLetraBadge(null);
            return;
        }
        get(CFG.urls.cliente + '?' + qs).then(function (res) {
            var cli = (res.body && res.body.cliente) || null;
            if (!cli) {
                if (opts.avisar) msg('Cliente no encontrado', false);
                actualizarLetraBadge(null);
                return;
            }
            if ($('cliente_id')) $('cliente_id').value = cli.id;
            if ($('codigocliente')) $('codigocliente').value = cli.codigo || '';
            if ($('nombrecliente')) $('nombrecliente').value = cli.nombre || '';
            actualizarLetraBadge(cli);
            if (cli.letra === 'A' && !cli.numerodocumento) {
                msg('Cliente RI sin documento: revisá el padrón antes de emitir Factura A', false);
            }
        });
    }

    window.completaDatosCliente = function () {
        cargarClientePos({ id: +(($('cliente_id') && $('cliente_id').value) || 0) });
    };

    function receptorDesdeCliente() {
        if (!clientePos) return {};
        return {
            nombre: clientePos.nombre || '',
            nrodoc: clientePos.numerodocumento || '',
            tipodoc: clientePos.tipodocumento || clientePos.tipodocumento_id || '',
            domicilio: clientePos.domicilio || ''
        };
    }

    function emitir(esRegalo) {
        if (!CFG.turnoId) {
            msg('Abrí la caja de este local (turno) para cobrar', false);
            var gate = $('fl-turno-local-id');
            if (gate) gate.focus();
            return;
        }
        if (!cart.length) {
            msg('Carrito vacío', false);
            return;
        }
        var clienteId = +(($('cliente_id') && $('cliente_id').value) || 0) || null;
        if (clientePos && clientePos.letra === 'A' && !clienteId) {
            msg('Para Factura A elegí un cliente con CUIT', false);
            return;
        }
        overlay(true, esRegalo ? 'Ticket regalo…' : 'Emitiendo CAE…');
        post(CFG.urls.emitir, {
            local_id: CFG.localId,
            lineas: cart,
            medios_pago: esRegalo ? [] : mediosPago(),
            cliente_id: clienteId,
            receptor: receptorDesdeCliente(),
            excedente_accion: $('fl-excedente').value || null,
            es_ticket_regalo: !!esRegalo
        }).then(function (res) {
            overlay(false);
            if (res.status >= 400 || !(res.body && res.body.ok)) {
                var err = (res.body && (res.body.error || (res.body.errores && res.body.errores[0]))) || 'Error al emitir';
                msg(err, false);
                return;
            }
            var letra = (res.body.letra || (clientePos && clientePos.letra) || '');
            msg('OK ' + (res.body.codigo || '') + (letra ? ' (' + letra + ')' : '') + (res.body.cae ? ' CAE ' + res.body.cae : ''), true);
            cart = [];
            renderCart();
            var tbody = $('tbody-fl-cuenta-table');
            if (tbody) tbody.innerHTML = '';
            refrescarContextoPos();
        }).catch(function (e) {
            overlay(false);
            msg(e.message || 'Error de red', false);
        });
    }

    function abrirTurno() {
        var turnoLocalEl = $('fl-turno-local-id');
        var turnoLocalId = turnoLocalEl ? String(turnoLocalEl.value || '') : '';
        if (!turnoLocalId) {
            msg('Elegí Mañana, Tarde o Noche', false);
            if (turnoLocalEl) turnoLocalEl.focus();
            return;
        }
        if (!CFG.localId) {
            msg('Elegí un local', false);
            return;
        }
        var fondo = $('fl-fondo-inicial') ? ($('fl-fondo-inicial').value || '0') : '0';
        overlay(true, 'Abriendo caja…');
        var fd = new FormData();
        fd.append('_token', CFG.csrf);
        fd.append('local_id', CFG.localId);
        fd.append('fondo_inicial', fondo);
        fd.append('turno_local_id', turnoLocalId);
        fetch(CFG.urls.abrirTurno, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': CFG.csrf
            },
            body: fd
        }).then(parseJsonResponse).then(function (res) {
            overlay(false);
            var j = res.body || {};
            if (res.status >= 400 || !j.ok) {
                msg(j.error || 'No se pudo abrir el turno', false);
                return;
            }
            window.location.href = j.redirect || (CFG.urls.pos + '?local_id=' + CFG.localId);
        }).catch(function (e) {
            overlay(false);
            msg(e.message || 'Error al abrir turno', false);
        });
    }

    function pintarContextoPos(ctx) {
        ctx = ctx || {};
        var pv = $('fl-ctx-pv');
        var dep = $('fl-ctx-dep');
        var prox = $('fl-ctx-prox-val');
        var lista = $('fl-ctx-lista');
        if (pv) {
            pv.textContent = ctx.puntoventa_codigo
                ? (ctx.puntoventa_codigo + ' — ' + (ctx.puntoventa_nombre || ''))
                : '—';
        }
        if (dep) {
            dep.textContent = ctx.deposito_codigo
                ? (ctx.deposito_codigo + ' — ' + (ctx.deposito_nombre || ''))
                : '—';
        }
        if (prox) {
            prox.textContent = ctx.proxima_etiqueta || '—';
            if (ctx.aviso) {
                prox.title = ctx.aviso;
            } else if (ctx.usa_webservice) {
                prox.title = 'Próximo según ARCA (FECompUltimoAutorizado)';
            } else {
                prox.title = '';
            }
        }
        if (lista && ctx.listaprecio_codigo) {
            lista.textContent = ctx.listaprecio_codigo + ' — ' + (ctx.listaprecio_nombre || '');
        }
        var bar = $('fl-ctx-bar');
        if (bar && ctx.aviso) {
            bar.title = ctx.aviso;
        }
    }

    function refrescarContextoPos() {
        if (!CFG.localId || !CFG.urls.contextoPos) return;
        get(CFG.urls.contextoPos + '?local_id=' + CFG.localId).then(function (res) {
            if (res.body && res.body.ok && res.body.contexto) {
                CFG.contexto = res.body.contexto;
                pintarContextoPos(res.body.contexto);
            }
        }).catch(function () {});
    }

    function enfocarSkuStock() {
        var stockQ = $('fl-stock-q');
        if (!stockQ) return;
        stockQ.focus();
        if (typeof stockQ.select === 'function') {
            stockQ.select();
        }
    }

    function abrirConsultaStockPrecios() {
        if (!CFG.localId) {
            msg('Elegí un local', false);
            return;
        }
        var q = ($('fl-q') && $('fl-q').value) || '';
        var stockQ = $('fl-stock-q');
        if (stockQ && q && !stockQ.value) {
            stockQ.value = q.trim();
        }
        if (window.jQuery) {
            window.jQuery('#fl-modal-stock').modal('show');
        }
    }

    function fmtNumPos(n, dec) {
        return (Number(n) || 0).toLocaleString('es-AR', {
            minimumFractionDigits: dec,
            maximumFractionDigits: dec
        });
    }

    function limpiarResultadoStock() {
        var matches = $('fl-stock-matches');
        var resumen = $('fl-stock-resumen');
        var combos = $('fl-stock-combos');
        var body = $('fl-stock-body');
        if (matches) {
            matches.classList.add('d-none');
            matches.innerHTML = '';
        }
        if (resumen) {
            resumen.classList.add('d-none');
            resumen.innerHTML = '';
        }
        if (combos) {
            combos.classList.add('d-none');
            combos.innerHTML = '';
        }
        if (body) {
            body.innerHTML = '<tr><td colspan="12" class="text-muted text-center">Consultando…</td></tr>';
        }
    }

    function renderMatchesStock(rows) {
        var wrap = $('fl-stock-matches');
        if (!wrap) return;
        if (!rows || !rows.length) {
            wrap.classList.add('d-none');
            wrap.innerHTML = '';
            return;
        }
        wrap.classList.remove('d-none');
        wrap.innerHTML = '<div class="small text-muted mb-1">Elegí el artículo:</div>'
            + rows.map(function (a) {
                return '<button type="button" class="btn btn-sm btn-outline-secondary mr-1 mb-1 fl-stock-match"'
                    + ' data-id="' + a.id + '" data-sku="' + escapeHtml(a.sku || '') + '">'
                    + escapeHtml(a.sku || '') + ' — ' + escapeHtml(a.descripcion || '')
                    + '</button>';
            }).join('');
        wrap.querySelectorAll('.fl-stock-match').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var sku = btn.getAttribute('data-sku') || '';
                var id = btn.getAttribute('data-id') || '';
                if ($('fl-stock-q')) $('fl-stock-q').value = sku;
                fetchStockPreciosPorArticulo(id || sku);
                enfocarSkuStock();
            });
        });
    }

    function etiquetaMedidaPos(m) {
        if (String(m) === '48' || Number(m) === 48) {
            return 'UN';
        }
        return String(m);
    }

    function claseCantidadPos(c) {
        var n = Number(c) || 0;
        if (n > 0) return 'fl-qty-pos';
        if (n < 0) return 'fl-qty-neg';
        return 'fl-qty-zero';
    }

    function filasPlanasAMatriz(filas) {
        var medidasMap = {};
        var grupos = {};
        (filas || []).forEach(function (f) {
            var med = f.medida != null ? String(f.medida) : '';
            if (med !== '') {
                medidasMap[med] = true;
            }
            var key = String(f.deposito != null ? f.deposito : '')
                + '|' + String(f.color != null ? f.color : '')
                + '|' + String(f.color_desc || '');
            if (!grupos[key]) {
                grupos[key] = {
                    deposito: f.deposito,
                    color: f.color,
                    color_desc: f.color_desc || '',
                    cantidades: {},
                    total: 0
                };
            }
            var c = Number(f.cantidad || 0);
            if (med !== '') {
                grupos[key].cantidades[med] = (Number(grupos[key].cantidades[med]) || 0) + c;
            }
            grupos[key].total += c;
        });
        var medidas = Object.keys(medidasMap).sort(function (a, b) {
            var na = Number(a);
            var nb = Number(b);
            if (!isNaN(na) && !isNaN(nb)) {
                return na - nb;
            }
            return String(a).localeCompare(String(b));
        });
        return {
            medidas: medidas,
            filas: Object.keys(grupos).map(function (k) { return grupos[k]; })
        };
    }

    function pintarStockPrecios(b) {
        var msgEl = $('fl-stock-msg');
        var thead = $('fl-stock-thead');
        var body = $('fl-stock-body');
        var resumen = $('fl-stock-resumen');
        var combos = $('fl-stock-combos');
        var art = b.articulo || {};
        var precio = b.precio || {};
        if (msgEl) {
            msgEl.textContent = (art.sku || '') + ' — ' + (art.descripcion || '')
                + (b.origen_stock ? ' · origen ' + b.origen_stock : '');
        }
        if (resumen) {
            resumen.classList.remove('d-none');
            resumen.innerHTML =
                '<div><strong>Precio lista local</strong>: ' + money(precio.valor || 0)
                + (precio.lista ? ' <span class="text-muted">(' + escapeHtml(precio.lista) + ')</span>' : '')
                + '</div>'
                + '<div><strong>Saldo total</strong>: ' + fmtNumPos(b.saldo_total || 0, 0) + '</div>'
                + '<div><strong>Variante</strong>: '
                + escapeHtml(b.modo_variante === 'color_talle' ? 'color + talle' : 'combinación + talle')
                + '</div>';
        }
        var listaCombos = b.combinaciones || [];
        if (combos) {
            if (listaCombos.length) {
                combos.classList.remove('d-none');
                combos.innerHTML = '<div class="small font-weight-bold mb-1">Combinaciones activas (POS)</div>'
                    + '<div class="fl-stock-combo-chips">'
                    + listaCombos.map(function (c) {
                        return '<span class="badge badge-info mr-1 mb-1">'
                            + escapeHtml(String(c.codigo || '')) + ' '
                            + escapeHtml(c.nombre || '')
                            + '</span>';
                    }).join('')
                    + '</div>';
            } else if (b.modo_variante === 'combinacion') {
                combos.classList.remove('d-none');
                combos.innerHTML = '<div class="small text-warning">Sin combinaciones activas (estado A) para vender en POS.</div>';
            } else {
                combos.classList.add('d-none');
                combos.innerHTML = '';
            }
        }

        var matriz;
        if (b.medidas && b.medidas.length && b.filas && b.filas[0] && b.filas[0].cantidades) {
            matriz = { medidas: b.medidas, filas: b.filas };
        } else {
            matriz = filasPlanasAMatriz(b.filas || []);
        }
        var medidas = matriz.medidas || [];
        var filas = matriz.filas || [];
        var colCount = medidas.length + 3;

        if (thead) {
            var headHtml = '<tr><th>Dp</th><th>Combinación</th>';
            medidas.forEach(function (m) {
                headHtml += '<th class="text-right fl-stock-med">' + escapeHtml(etiquetaMedidaPos(m)) + '</th>';
            });
            headHtml += '<th class="text-right">Tot</th></tr>';
            thead.innerHTML = headHtml;
        }

        if (!filas.length) {
            if (body) {
                body.innerHTML = '<tr><td colspan="' + colCount + '" class="text-muted text-center">Sin stock en el depósito del local.</td></tr>';
            }
            return;
        }

        if (body) {
            body.innerHTML = filas.map(function (f) {
                var combTxt = '';
                if (f.color != null && String(f.color) !== '') {
                    combTxt = String(f.color);
                }
                if (f.color_desc) {
                    combTxt = (combTxt ? combTxt + ' — ' : '') + f.color_desc;
                }
                var row = '<tr>'
                    + '<td>' + escapeHtml(String(f.deposito != null ? f.deposito : '')) + '</td>'
                    + '<td class="fl-stock-comb-cell">' + escapeHtml(combTxt) + '</td>';
                medidas.forEach(function (m) {
                    var c = Number((f.cantidades && f.cantidades[String(m)]) || 0);
                    row += '<td class="text-right ' + claseCantidadPos(c) + '">'
                        + (c === 0 ? '' : fmtNumPos(c, 0))
                        + '</td>';
                });
                row += '<td class="text-right font-weight-bold">' + fmtNumPos(f.total, 0) + '</td></tr>';
                return row;
            }).join('');
        }
    }

    function fetchStockPreciosPorArticulo(clave) {
        var msgEl = $('fl-stock-msg');
        var body = $('fl-stock-body');
        if (!clave) {
            if (msgEl) msgEl.textContent = 'Ingresá un SKU o nombre.';
            return;
        }
        if (msgEl) msgEl.textContent = 'Consultando…';
        var origenErp = $('fl-stock-origen-erp') && $('fl-stock-origen-erp').checked;
        // ID interno ERP (~hasta 6 dígitos) vs SKU Ferli (8+).
        var param = /^\d{1,6}$/.test(String(clave))
            ? 'articulo_id=' + encodeURIComponent(clave)
            : 'codigo=' + encodeURIComponent(clave);
        var url = CFG.urls.consultaStockPrecios
            + '?local_id=' + CFG.localId
            + '&' + param
            + '&origen=' + (origenErp ? 'erp' : 'anita');
        get(url).then(function (res) {
            var b = res.body || {};
            if (res.status >= 400 || !b.ok) {
                if (msgEl) msgEl.textContent = b.error || 'Sin resultados';
                if (body) {
                    body.innerHTML = '<tr><td colspan="20" class="text-muted text-center">'
                        + escapeHtml(b.error || 'Sin resultados') + '</td></tr>';
                }
                return;
            }
            pintarStockPrecios(b);
        }).catch(function (e) {
            if (msgEl) msgEl.textContent = e.message || 'Error de red';
            if (body) {
                body.innerHTML = '<tr><td colspan="20" class="text-danger text-center">Error de red</td></tr>';
            }
        });
    }

    function consultarStockPrecios() {
        var q = ($('fl-stock-q') && $('fl-stock-q').value || '').trim();
        var msgEl = $('fl-stock-msg');
        if (!q) {
            if (msgEl) msgEl.textContent = 'Ingresá un SKU o nombre.';
            return;
        }
        limpiarResultadoStock();
        if (msgEl) msgEl.textContent = 'Buscando…';

        // Si parece SKU numérico exacto, consultar directo; si no, listar candidatos.
        if (/^\d{6,}$/.test(q)) {
            fetchStockPreciosPorArticulo(q);
            return;
        }

        get(CFG.urls.buscar + '?q=' + encodeURIComponent(q) + '&local_id=' + (CFG.localId || ''))
            .then(function (res) {
                var rows = (res.body && res.body.data) || [];
                if (!rows.length) {
                    // Igual intentar resolución backend (descripción / canal).
                    fetchStockPreciosPorArticulo(q);
                    return;
                }
                if (rows.length === 1) {
                    if ($('fl-stock-q')) $('fl-stock-q').value = rows[0].sku || q;
                    fetchStockPreciosPorArticulo(String(rows[0].id));
                    return;
                }
                if (msgEl) msgEl.textContent = rows.length + ' artículos. Elegí uno.';
                var body = $('fl-stock-body');
                if (body) {
                    body.innerHTML = '<tr><td colspan="20" class="text-muted text-center">Elegí un artículo de la lista.</td></tr>';
                }
                renderMatchesStock(rows);
            })
            .catch(function () {
                fetchStockPreciosPorArticulo(q);
            });
    }

    function bind() {
        if ($('fl-buscar')) $('fl-buscar').addEventListener('click', buscar);
        if ($('fl-q-lupa')) $('fl-q-lupa').addEventListener('click', abrirConsultaArticulo);
        if ($('fl-q')) {
            $('fl-q').addEventListener('keydown', function (e) {
                if (e.key === 'Enter') { e.preventDefault(); buscar(); }
                if (e.key === 'Escape') { $('fl-q').value = ''; $('fl-results').innerHTML = ''; }
            });
            $('fl-q').addEventListener('input', function () {
                clearTimeout(buscaTimer);
                var q = $('fl-q').value.trim();
                if (q.length < 2) {
                    $('fl-results').innerHTML = '';
                    return;
                }
                buscaTimer = setTimeout(buscar, 280);
            });
            setTimeout(function () { $('fl-q').focus(); }, 50);
        }
        if ($('fl-emitir')) $('fl-emitir').addEventListener('click', function () { emitir(false); });
        if ($('fl-regalo')) $('fl-regalo').addEventListener('click', function () { emitir(true); });
        if ($('fl-var-ok')) $('fl-var-ok').addEventListener('click', confirmarVariante);
        if ($('fl-abrir-turno')) $('fl-abrir-turno').addEventListener('click', abrirTurno);

        if ($('cliente_id')) {
            $('cliente_id').addEventListener('change', function () {
                cargarClientePos({ id: +$('cliente_id').value || 0 });
            });
        }
        if (window.jQuery) {
            window.jQuery(document).on('change', '#cliente_id', function () {
                cargarClientePos({ id: +window.jQuery(this).val() || 0 });
            });
            if (typeof window.activa_eventos_consultacliente === 'function') {
                window.activa_eventos_consultacliente();
            }
            if (typeof window.activa_eventos_consultatalle === 'function') {
                window.activa_eventos_consultatalle();
            }
            if (typeof window.activa_eventos_consultacolor === 'function') {
                window.activa_eventos_consultacolor();
            }
        }

        if ($('fl-cerrar-turno') && CFG.urls.cerrarTurno) {
            $('fl-cerrar-turno').addEventListener('click', function () {
                if (!confirm('¿Cerrar turno de este local?')) return;
                overlay(true, 'Cerrando…');
                var fd = new FormData();
                fd.append('_token', CFG.csrf);
                fetch(CFG.urls.cerrarTurno, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': CFG.csrf
                    },
                    body: fd
                }).then(parseJsonResponse).then(function (res) {
                    overlay(false);
                    var j = res.body || {};
                    if (res.status >= 400 || !j.ok) {
                        msg(j.error || 'No se pudo cerrar', false);
                        return;
                    }
                    if (j.pdf_url) window.open(j.pdf_url, '_blank');
                    window.location.href = CFG.urls.pos + '?local_id=' + CFG.localId;
                }).catch(function (e) {
                    overlay(false);
                    msg(e.message || 'Error', false);
                });
            });
        }

        document.addEventListener('keydown', function (e) {
            if (esTeclaF1(e)) {
                var t = e.target;
                if (t && (t.id === 'fl-q' || t.classList.contains('fl-sku-input'))) {
                    e.preventDefault();
                    e.stopPropagation();
                    abrirConsultaArticulo();
                    return;
                }
            }
            if (e.key === 'F2') { e.preventDefault(); emitir(false); }
            if (e.key === 'F3') {
                e.preventDefault();
                abrirConsultaStockPrecios();
            }
            if (e.key === 'F8') { e.preventDefault(); emitir(true); }
        }, true);

        if ($('fl-tool-stock')) {
            $('fl-tool-stock').addEventListener('click', abrirConsultaStockPrecios);
        }
        if ($('fl-stock-buscar')) {
            $('fl-stock-buscar').addEventListener('click', consultarStockPrecios);
        }
        if ($('fl-stock-q')) {
            $('fl-stock-q').addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    consultarStockPrecios();
                }
            });
        }
        if (window.jQuery) {
            window.jQuery('#fl-modal-stock').on('shown.bs.modal', function () {
                enfocarSkuStock();
                var stockQ = $('fl-stock-q');
                if (stockQ && stockQ.value.trim()) {
                    consultarStockPrecios();
                    // Tras consultar, dejar el foco en el SKU para seguir tipeando.
                    setTimeout(enfocarSkuStock, 0);
                }
            });
        }

        initCobranza();
        initCombinacionModal();
        initVarianteTeclado();
        actualizarLetraBadge(null);
        pintarContextoPos(CFG.contexto || {});
        refrescarContextoPos();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bind);
    } else {
        bind();
    }
})();
