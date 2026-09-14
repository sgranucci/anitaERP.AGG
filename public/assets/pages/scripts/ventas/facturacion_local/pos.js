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
            syncMedioDefault(netoActual);
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

    function syncMedioDefault(neto) {
        var tbody = $('tbody-fl-cuenta-table');
        if (!tbody || !CFG.turnoId) return;
        if (tbody.children.length) return;
        var tr = agregarRenglonCobranza(Math.max(0, neto), false);
        var efectivo = (CFG.cuentas || []).find(function (c) { return +c.id === +CFG.efectivoId; })
            || (CFG.cuentas || [])[0];
        if (tr && efectivo) asignarCuentaEnFila(tr, efectivo);
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
        if (CFG.turnoId) syncMedioDefault(0);

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
            if (v.modo === 'color_talle') {
                $('fl-var-color-wrap').style.display = '';
                $('fl-var-comb-wrap').style.display = 'none';
            } else {
                $('fl-var-color-wrap').style.display = 'none';
                $('fl-var-comb-wrap').style.display = '';
            }
            $('fl-var-cant').value = '1';
            window.jQuery('#fl-modal-var').modal('show');
            setTimeout(function () {
                var t = $('fl-var-talle-codigo');
                if (t) t.focus();
            }, 200);
        }).catch(function (e) {
            msg(e.message || 'Error al cargar variantes', false);
        });
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
        });
        $(document).on('click', '#aceptaconsultacombinacionModal', function () {
            var $btn = $('#datoscombinacion .elige-combinacion').first();
            if ($btn.length) $btn.trigger('click');
            else $('#consultacombinacionModal').modal('hide');
        });
        $(document).on('keydown', '.codigocombinacion', function (e) {
            if (esTeclaF1(e)) {
                e.preventDefault();
                e.stopPropagation();
                $(this).closest('.tm-combinacion-campo').find('.consultacombinacion').trigger('click');
                return;
            }
            if (e.key !== 'Enter') return;
            e.preventDefault();
            var codigo = String($(this).val() || '').trim();
            var hit = listarCombinacionesFiltradas(codigo).find(function (c) {
                return String(c.codigo).toLowerCase() === codigo.toLowerCase()
                    || String(c.id) === codigo
                    || String(c.nombre || '').toLowerCase() === codigo.toLowerCase();
            }) || (listarCombinacionesFiltradas(codigo)[0] || null);
            if (hit) {
                $('#fl-var-comb-id').val(hit.id);
                $('#fl-var-comb-codigo').val(hit.codigo || '');
                $('#fl-var-comb-nombre').val(hit.nombre || '');
            } else {
                $('#fl-var-comb-id').val('');
                $('#fl-var-comb-nombre').val('');
                setTimeout(function () { alert('Combinación no encontrada'); }, 0);
            }
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
            syncMedioDefault(0);
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
            if (e.key === 'F8') { e.preventDefault(); emitir(true); }
        }, true);

        initCobranza();
        initCombinacionModal();
        actualizarLetraBadge(null);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bind);
    } else {
        bind();
    }
})();
