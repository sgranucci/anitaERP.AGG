(function () {
    'use strict';

    var cfg = window.flConsultaCfg || {};
    if (!cfg.formId || !cfg.urlConsulta) {
        return;
    }

    var form = document.getElementById(cfg.formId);
    if (!form) {
        return;
    }

    var selectLocal = document.getElementById(cfg.formId + '-local');
    var inputId = document.getElementById(cfg.formId + '-articulo_id');
    var inputCodigo = document.getElementById(cfg.formId + '-codigo');
    var inputDesc = document.getElementById(cfg.formId + '-descripcion');
    var btn = document.getElementById(cfg.formId + '-btn');
    var lupa = document.getElementById(cfg.formId + '-lupa');
    var listaChip = document.getElementById(cfg.formId + '-lista-chip');
    var checkAnita = document.getElementById(cfg.formId + '-origen-anita');
    var resultado = document.getElementById(cfg.resultadoId);
    var vacio = document.getElementById(cfg.vacioId);
    var vacioTxt = document.getElementById(cfg.vacioTxtId);
    var overlay = document.getElementById(cfg.overlayId);
    var consultando = false;
    var ultimaConsultaKey = '';

    function token() {
        var el = document.querySelector('input[name="_token"]');
        return el ? el.value : '';
    }

    function esTeclaF1(e) {
        return e && (e.key === 'F1' || e.code === 'F1' || e.keyCode === 112);
    }

    function modalAbierto(selector) {
        var m = document.querySelector(selector);
        return !!(m && m.classList.contains('show'));
    }

    function mostrarOverlay(titulo) {
        if (!overlay) {
            return;
        }
        var t = document.getElementById(cfg.overlayId.replace(/-overlay$/, '-overlay-titulo'));
        if (titulo && t) {
            t.textContent = titulo;
        }
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }

    function ocultarOverlay() {
        if (!overlay) {
            return;
        }
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }

    function fmtNum(n, dec) {
        var v = Number(n || 0);
        return v.toLocaleString('es-AR', {
            minimumFractionDigits: dec,
            maximumFractionDigits: dec
        });
    }

    function etiquetaMedida(m) {
        if (String(m) === '48' || Number(m) === 48) {
            return 'UN';
        }
        return String(m);
    }

    function mostrarVacio(mensaje, tipo) {
        if (resultado) {
            resultado.style.display = 'none';
        }
        if (!vacio) {
            return;
        }
        vacio.style.display = 'block';
        vacio.className = 'fl-empty-state' + (tipo === 'warn' ? ' is-warn' : (tipo === 'error' ? ' is-error' : ''));
        if (vacioTxt) {
            vacioTxt.textContent = mensaje || 'Sin resultados.';
        } else {
            vacio.textContent = mensaje || 'Sin resultados.';
        }
    }

    function ocultarVacio() {
        if (vacio) {
            vacio.style.display = 'none';
        }
    }

    function listaDelLocal() {
        if (!selectLocal || !selectLocal.selectedOptions || !selectLocal.selectedOptions[0]) {
            return { id: 0, nombre: '' };
        }
        var opt = selectLocal.selectedOptions[0];
        return {
            id: parseInt(opt.getAttribute('data-listaprecio-id') || '0', 10) || 0,
            nombre: (opt.getAttribute('data-lista-nombre') || '').trim()
        };
    }

    function usaAnita() {
        return !!(checkAnita && checkAnita.checked);
    }

    function etiquetaOrigen(json) {
        var origen = (json && json.origen) ? String(json.origen) : (usaAnita() ? 'anita' : 'erp');
        var detalle = (json && json.origen_stock) ? String(json.origen_stock) : '';
        if (origen === 'erp') {
            return 'Origen: ERP' + (detalle ? ' (' + detalle + ')' : ' · articulo_movimiento');
        }
        return 'Origen: Anita Local' + (detalle ? ' (' + detalle + ')' : '');
    }

    function textoSaldoHint(json) {
        var origen = (json && json.origen) ? String(json.origen) : (usaAnita() ? 'anita' : 'erp');
        return origen === 'erp' ? 'Unidades en ERP (depósito del local)' : 'Unidades en Anita Local';
    }

    function actualizarChipLista() {
        if (!listaChip) {
            return;
        }
        var lista = listaDelLocal();
        var txt = listaChip.querySelector('.fl-consulta-lista-chip-txt');
        if (lista.id > 0 && lista.nombre) {
            listaChip.classList.remove('is-empty');
            if (txt) {
                txt.textContent = 'Lista: ' + lista.nombre;
            }
        } else {
            listaChip.classList.add('is-empty');
            if (txt) {
                txt.textContent = 'Sin lista de precios en el local';
            }
        }
        if (lupa) {
            if (lista.id > 0) {
                lupa.setAttribute('data-listaprecio-id', String(lista.id));
                lupa.setAttribute('data-listaprecio-nombre', lista.nombre);
            } else {
                lupa.removeAttribute('data-listaprecio-id');
                lupa.removeAttribute('data-listaprecio-nombre');
            }
        }
    }

    function articuloQuery() {
        var id = inputId ? String(inputId.value || '').trim() : '';
        var sku = inputCodigo ? String(inputCodigo.value || '').trim() : '';
        if (id && parseInt(id, 10) > 0) {
            return { articulo_id: id, q: sku || id };
        }
        return { articulo_id: '', q: sku };
    }

    function claseSaldo(n) {
        var v = Number(n || 0);
        if (v < 0) {
            return 'is-saldo-neg';
        }
        if (v === 0) {
            return 'is-saldo-cero';
        }
        return 'is-saldo-pos';
    }

    function claseCantidad(c) {
        if (c === 0) {
            return 'cant-cero';
        }
        if (c < 0) {
            return 'cant-neg';
        }
        if (c >= 10) {
            return 'cant-hot';
        }
        return 'cant-pos';
    }

    function consultar(opts) {
        opts = opts || {};
        var art = articuloQuery();
        if (!art.q && !art.articulo_id) {
            mostrarVacio('Ingresá un artículo (SKU + Enter, F1 o lupa).', 'warn');
            if (inputCodigo) {
                inputCodigo.focus();
            }
            return;
        }
        if (!selectLocal || !selectLocal.value) {
            mostrarVacio('Seleccioná un local.', 'warn');
            return;
        }

        var key = String(selectLocal.value) + '|' + (art.articulo_id || art.q) + '|' + (usaAnita() ? 'anita' : 'erp');
        if (consultando && key === ultimaConsultaKey && !opts.force) {
            return;
        }
        consultando = true;
        ultimaConsultaKey = key;
        ocultarVacio();
        mostrarOverlay(
            usaAnita()
                ? (cfg.modo === 'precios' ? 'Consultando Anita…' : 'Consultando stock Anita…')
                : (cfg.modo === 'precios' ? 'Consultando ERP…' : 'Consultando stock ERP…')
        );

        var params = new URLSearchParams();
        params.set('local_id', selectLocal.value);
        params.set('origen_anita', usaAnita() ? '1' : '0');
        if (art.articulo_id) {
            params.set('articulo_id', art.articulo_id);
        }
        if (art.q) {
            params.set('q', art.q);
        }

        fetch(cfg.urlConsulta + '?' + params.toString(), {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': token()
            },
            credentials: 'same-origin'
        })
            .then(function (r) {
                return r.json().then(function (json) {
                    return { okHttp: r.ok, json: json };
                });
            })
            .then(function (pack) {
                consultando = false;
                ocultarOverlay();
                var json = pack.json || {};
                if (!json.ok) {
                    mostrarVacio(json.error || 'Sin resultados.', 'warn');
                    return;
                }
                ocultarVacio();
                if (cfg.modo === 'precios') {
                    renderPrecios(json);
                } else {
                    renderStock(json);
                }
                if (resultado) {
                    resultado.style.display = 'block';
                }
                if (inputCodigo) {
                    inputCodigo.focus();
                    inputCodigo.select();
                }
            })
            .catch(function (err) {
                consultando = false;
                ocultarOverlay();
                mostrarVacio('Error de consulta: ' + (err && err.message ? err.message : 'red'), 'error');
            });
    }

    function renderStock(json) {
        var art = json.articulo || {};
        var precio = json.precio || {};
        var saldo = Number(json.saldo_total || 0);

        document.getElementById('fl-stock-sku').textContent = art.sku || '—';
        document.getElementById('fl-stock-desc').textContent = art.descripcion || '';
        document.getElementById('fl-stock-precio').textContent = '$ ' + fmtNum(precio.valor, 2);
        document.getElementById('fl-stock-lista').textContent = precio.lista
            ? ('Lista del local: ' + precio.lista + (precio.origen ? ' · ' + precio.origen : ''))
            : (precio.origen === 'sin_precio' ? 'Sin precio configurado' : '');
        var elSaldo = document.getElementById('fl-stock-saldo');
        elSaldo.textContent = fmtNum(saldo, 0);
        elSaldo.className = 'fl-kpi-value ' + claseSaldo(saldo);
        var hint = document.getElementById('fl-stock-saldo-hint');
        if (hint) {
            hint.textContent = textoSaldoHint(json);
        }
        document.getElementById('fl-stock-origen').textContent = etiquetaOrigen(json);

        var medidas = json.medidas || [];
        var thead = document.getElementById('fl-stock-thead');
        var tbody = document.getElementById('fl-stock-tbody');
        var headHtml = '<tr><th>Dp</th><th>Color</th>';
        medidas.forEach(function (m) {
            headHtml += '<th class="text-right">' + etiquetaMedida(m) + '</th>';
        });
        headHtml += '<th class="text-right">Tot</th></tr>';
        thead.innerHTML = headHtml;

        var filas = json.filas || [];
        var countBadge = document.getElementById('fl-stock-filas-count');
        if (countBadge) {
            countBadge.textContent = filas.length + (filas.length === 1 ? ' fila' : ' filas');
        }

        if (!filas.length) {
            var vacioTxt = (json.origen === 'erp') ? 'Sin stock en ERP' : 'Sin stock en Anita Local';
            tbody.innerHTML = '<tr><td colspan="' + (medidas.length + 3) + '" class="text-center text-muted py-4">' + vacioTxt + '</td></tr>';
            return;
        }

        var body = '';
        filas.forEach(function (f) {
            var colorTxt = (f.color || '') + (f.color_desc ? ' ' + f.color_desc : '');
            body += '<tr><td>' + (f.deposito || '') + '</td><td>' + colorTxt + '</td>';
            medidas.forEach(function (m) {
                var c = Number((f.cantidades && f.cantidades[String(m)]) || 0);
                var cls = claseCantidad(c);
                body += '<td class="text-right ' + cls + '">' + (c === 0 ? '' : fmtNum(c, 0)) + '</td>';
            });
            body += '<td class="text-right fl-row-total">' + fmtNum(f.total, 0) + '</td></tr>';
        });
        tbody.innerHTML = body;
    }

    function renderPrecios(json) {
        var art = json.articulo || {};
        var precio = json.precio || {};
        var saldo = Number(json.saldo_total || 0);
        var listaLocalId = Number(precio.listaprecio_id || 0) || listaDelLocal().id;

        document.getElementById('fl-precios-sku').textContent = art.sku || '—';
        document.getElementById('fl-precios-desc').textContent = art.descripcion || '';
        document.getElementById('fl-precios-precio').textContent = '$ ' + fmtNum(precio.valor, 2);
        document.getElementById('fl-precios-lista').textContent = precio.lista
            ? ('Lista del local: ' + precio.lista)
            : 'Sin lista / sin precio';
        var elSaldo = document.getElementById('fl-precios-saldo');
        elSaldo.textContent = fmtNum(saldo, 0);
        elSaldo.className = 'fl-kpi-value ' + claseSaldo(saldo);
        var hintPrecios = document.getElementById('fl-precios-saldo-hint');
        if (hintPrecios) {
            hintPrecios.textContent = textoSaldoHint(json);
        }
        document.getElementById('fl-precios-origen').textContent = etiquetaOrigen(json);

        var tbodyStock = document.getElementById('fl-precios-tbody-stock');
        var filas = json.filas || [];
        var stockCount = document.getElementById('fl-precios-stock-count');
        if (stockCount) {
            stockCount.textContent = filas.length + (filas.length === 1 ? ' línea' : ' líneas');
        }
        if (!filas.length) {
            tbodyStock.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">Sin stock</td></tr>';
        } else {
            var body = '';
            filas.forEach(function (f) {
                var colorTxt = (f.color || '') + (f.color_desc ? ' ' + f.color_desc : '');
                var c = Number(f.cantidad || 0);
                body += '<tr>'
                    + '<td>' + (f.deposito || '') + '</td>'
                    + '<td>' + colorTxt + '</td>'
                    + '<td>' + etiquetaMedida(f.medida) + '</td>'
                    + '<td class="text-right ' + claseCantidad(c) + '">' + fmtNum(c, 0) + '</td>'
                    + '</tr>';
            });
            tbodyStock.innerHTML = body;
        }

        var tbodyListas = document.getElementById('fl-precios-tbody-listas');
        var listas = json.precios_listas || [];
        var badge = document.getElementById('fl-precios-lista-activa-badge');
        var hayActiva = false;
        if (!listas.length) {
            tbodyListas.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-4">Sin precios ERP</td></tr>';
        } else {
            var lb = '';
            listas.forEach(function (p) {
                var activa = listaLocalId > 0 && Number(p.listaprecio_id || 0) === listaLocalId;
                if (activa) {
                    hayActiva = true;
                }
                lb += '<tr class="' + (activa ? 'lista-activa' : '') + '">'
                    + '<td>' + (p.lista || '') + '</td>'
                    + '<td class="text-right">$ ' + fmtNum(p.precio, 2) + '</td>'
                    + '<td>' + (p.fechavigencia || '') + '</td>'
                    + '</tr>';
            });
            tbodyListas.innerHTML = lb;
        }
        if (badge) {
            badge.style.display = hayActiva ? 'inline-block' : 'none';
        }
    }

    function abrirModalArticulo() {
        if (modalAbierto('#consultaarticuloModal')) {
            return;
        }
        if (lupa) {
            lupa.click();
        }
    }

    if (typeof window.jQuery !== 'undefined') {
        window.jQuery(function ($) {
            if (typeof window.activa_eventos_consultaarticulo === 'function') {
                window.activa_eventos_consultaarticulo();
            }

            window.onArticuloSeleccionado = function (data) {
                if (!data || !data.id) {
                    return;
                }
                if (inputId) {
                    inputId.value = data.id;
                }
                if (inputCodigo && data.sku) {
                    inputCodigo.value = data.sku;
                }
                if (inputDesc && data.descripcion) {
                    inputDesc.value = data.descripcion;
                }
                setTimeout(function () {
                    consultar({ force: true });
                }, 0);
            };
        });
    }

    if (btn) {
        btn.addEventListener('click', function () {
            consultar({ force: true });
        });
    }

    if (selectLocal) {
        selectLocal.addEventListener('change', function () {
            actualizarChipLista();
            var art = articuloQuery();
            if (art.q || art.articulo_id) {
                consultar({ force: true });
            }
        });
    }

    if (inputCodigo) {
        inputCodigo.addEventListener('keydown', function (ev) {
            if (esTeclaF1(ev)) {
                ev.preventDefault();
                ev.stopPropagation();
                abrirModalArticulo();
                return;
            }
            if (ev.key === 'Enter') {
                ev.preventDefault();
                var sku = (inputCodigo.value || '').trim();
                if (!sku) {
                    mostrarVacio('Ingresá un SKU.', 'warn');
                    return;
                }
                var prevId = inputId ? inputId.value : '';
                if (window.jQuery) {
                    window.jQuery(inputCodigo).trigger('change');
                }
                setTimeout(function () {
                    var newId = inputId ? inputId.value : '';
                    if (newId && newId === prevId) {
                        consultar({ force: true });
                    } else if (!newId) {
                        consultar({ force: true });
                    }
                    // Si newId cambió, onArticuloSeleccionado ya disparó la consulta.
                }, 400);
            }
        });
    }

    document.addEventListener('keydown', function (ev) {
        if (!esTeclaF1(ev)) {
            return;
        }
        var target = ev.target;
        if (!form.contains(target)) {
            return;
        }
        if (target.classList && target.classList.contains('codigoarticulo')) {
            if (modalAbierto('#consultaarticuloModal')) {
                return;
            }
            ev.preventDefault();
            ev.stopPropagation();
            abrirModalArticulo();
        }
    }, true);

    window.addEventListener('pageshow', ocultarOverlay);
    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape') {
            ocultarOverlay();
        }
    });

    actualizarChipLista();
    mostrarVacio('Elegí un local y un artículo (F1 / Enter) para consultar.', '');
})();
