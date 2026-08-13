(function () {
    'use strict';

    var cfg = window.arfConfig || {};
    var overlay = document.getElementById('arf-overlay');
    var tituloEl = document.getElementById('arf-overlay-titulo');
    var subtituloEl = document.getElementById('arf-overlay-subtitulo');

    var estado = {
        remitos: [],
        facturas: [],
        pagRemito: 1,
        pagFactura: 1,
        lastRemito: 1,
        lastFactura: 1,
        totalRemitos: 0,
        totalFacturas: 0,
        remitoSel: null,
        facturaSel: null,
        pares: [],
        sugerencias: [],
        debounceRemito: null,
        debounceFactura: null,
    };

    function tokenCsrf() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function mostrarOverlay(titulo, subtitulo) {
        if (!overlay) {
            return;
        }
        if (titulo && tituloEl) {
            tituloEl.textContent = titulo;
        }
        if (subtitulo && subtituloEl) {
            subtituloEl.textContent = subtitulo;
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

    window.addEventListener('pageshow', ocultarOverlay);

    function escapeHtml(valor) {
        return String(valor == null ? '' : valor)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function fmtNum(n) {
        var x = Number(n || 0);
        return x.toLocaleString('es-AR', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
    }

    function idsUsados() {
        var remitos = {};
        var facturas = {};
        estado.pares.forEach(function (p) {
            remitos[p.remito.id] = true;
            facturas[p.factura.id] = true;
        });
        return { remitos: remitos, facturas: facturas };
    }

    function puntaje(remito, factura) {
        var mismoCliente = remito.cliente_id && remito.cliente_id === factura.cliente_id;
        var mismaFecha = remito.fecha && remito.fecha === factura.fecha;
        var kilosR = Number(remito.kilos || 0);
        var kilosF = Number(factura.kilos || 0);
        var kilosCercanos = kilosR > 0 && kilosF > 0 && Math.abs(kilosR - kilosF) / Math.max(kilosR, kilosF) <= 0.15;
        var nivel;
        var etiqueta;
        if (mismoCliente && (mismaFecha || kilosCercanos)) {
            nivel = 'excelente';
            etiqueta = 'Mismo cliente' + (mismaFecha ? ' y fecha' : ' y kilos similares');
        } else if (mismoCliente) {
            nivel = 'bueno';
            etiqueta = 'Mismo cliente';
        } else if (mismaFecha) {
            nivel = 'regular';
            etiqueta = 'Misma fecha, distinto cliente: el remito tomará los datos de la factura';
        } else {
            nivel = 'distinto';
            etiqueta = 'Sin coincidencia de cliente ni fecha: el remito tomará los datos de la factura';
        }
        return { nivel: nivel, etiqueta: etiqueta, mismo_cliente: mismoCliente, misma_fecha: mismaFecha };
    }

    function articulosHtml(items) {
        if (!items || !items.length) {
            return '<span class="text-muted">Sin artículos</span>';
        }
        return items.slice(0, 6).map(function (a) {
            return escapeHtml(a.sku) + ' · ' + fmtNum(a.kilo) + ' kg';
        }).join('<br>') + (items.length > 6 ? '<br>…' : '');
    }

    function accionesDoc(urlEditar, urlPdf, puedeEditar, puedePdf, tituloEditar) {
        var html = '';
        if (puedeEditar && urlEditar) {
            html += '<a class="btn-accion-tabla tooltipsC" href="' + escapeHtml(urlEditar) +
                '" target="_blank" rel="noopener" title="' + escapeHtml(tituloEditar) +
                '" onclick="event.stopPropagation()"><i class="fa fa-edit"></i></a> ';
        }
        if (puedePdf && urlPdf) {
            html += '<a class="btn-accion-tabla tooltipsC" href="' + escapeHtml(urlPdf) +
                '" target="_blank" rel="noopener" title="Imprimir" onclick="event.stopPropagation()">' +
                '<i class="fa fa-print"></i></a>';
        }
        return html;
    }

    function renderFacturas() {
        var tbody = document.getElementById('arf-tbody-facturas');
        var usados = idsUsados();
        if (!estado.facturas.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="arf-empty">Sin facturas para los filtros.</td></tr>';
            return;
        }
        tbody.innerHTML = estado.facturas.map(function (f) {
            var cls = 'arf-row js-arf-factura';
            if (estado.facturaSel && estado.facturaSel.id === f.id) {
                cls += ' is-selected';
            }
            if (estado.remitoSel && estado.remitoSel.cliente_id === f.cliente_id) {
                cls += ' is-match';
            }
            if (usados.facturas[f.id] || !f.huerfano) {
                cls += ' is-used';
            }
            var avisoRemito = f.numeroremito > 0
                ? '<div class="small text-warning">Nº remito Anita: ' + f.numeroremito + '</div>'
                : '';
            return '<tr class="' + cls + '" data-id="' + f.id + '">' +
                '<td><input type="radio" name="arf_factura" ' + (estado.facturaSel && estado.facturaSel.id === f.id ? 'checked' : '') + '></td>' +
                '<td><strong>' + escapeHtml(f.comprobante) + '</strong>' + avisoRemito +
                (f.empresa ? '<div class="small text-muted">' + escapeHtml(f.empresa) + (f.puntoventa ? ' · PV ' + escapeHtml(f.puntoventa) : '') + '</div>' : '') +
                '<div class="arf-articulos">' + articulosHtml(f.articulos) + '</div></td>' +
                '<td>' + escapeHtml(f.fecha_txt) + '</td>' +
                '<td><b>' + escapeHtml(f.cliente) + '</b></td>' +
                '<td class="text-right">' + fmtNum(f.kilos) + '</td>' +
                '<td class="text-nowrap">' + accionesDoc(f.url_editar, f.url_pdf, cfg.puedeEditarFactura, cfg.puedeListarFactura, 'Consultar factura') + '</td>' +
                '</tr>';
        }).join('');
    }

    function renderRemitos() {
        var tbody = document.getElementById('arf-tbody-remitos');
        var usados = idsUsados();
        if (!estado.remitos.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="arf-empty">Sin remitos para los filtros.</td></tr>';
            return;
        }
        tbody.innerHTML = estado.remitos.map(function (r) {
            var cls = 'arf-row js-arf-remito';
            if (estado.remitoSel && estado.remitoSel.id === r.id) {
                cls += ' is-selected';
            }
            if (estado.facturaSel && estado.facturaSel.cliente_id === r.cliente_id) {
                cls += ' is-match';
            }
            if (usados.remitos[r.id] || !r.huerfano) {
                cls += ' is-used';
            }
            return '<tr class="' + cls + '" data-id="' + r.id + '">' +
                '<td><input type="radio" name="arf_remito" ' + (estado.remitoSel && estado.remitoSel.id === r.id ? 'checked' : '') + '></td>' +
                '<td><strong>' + escapeHtml(r.codigo) + '</strong>' +
                (r.empresa ? '<div class="small text-muted">' + escapeHtml(r.empresa) + (r.puntoventa ? ' · PV ' + escapeHtml(r.puntoventa) : '') + '</div>' : '') +
                '<div class="arf-articulos">' + articulosHtml(r.articulos) + '</div></td>' +
                '<td>' + escapeHtml(r.fecha_txt) + '</td>' +
                '<td><b>' + escapeHtml(r.cliente) + '</b>' +
                (r.transporte_codigo ? '<div class="small text-muted">Rpto ' + escapeHtml(r.transporte_codigo) + '</div>' : '') +
                '</td>' +
                '<td><span class="badge badge-secondary arf-badge-origen">' + escapeHtml(r.origen_txt) + '</span></td>' +
                '<td class="text-right">' + fmtNum(r.kilos) + '</td>' +
                '<td class="text-nowrap">' + accionesDoc(r.url_editar, r.url_pdf, cfg.puedeEditarRemito, cfg.puedeListarRemito, 'Consultar remito') + '</td>' +
                '</tr>';
        }).join('');
    }

    function renderPares() {
        var cont = document.getElementById('arf-pares');
        var vacio = document.getElementById('arf-pares-vacio');
        var count = document.getElementById('arf-count-pares');
        count.textContent = String(estado.pares.length);
        var btnTodas = document.getElementById('btn-arf-confirmar-todas');
        var btnUnoEstado = document.getElementById('btn-arf-confirmar-uno');
        if (btnTodas) {
            btnTodas.disabled = estado.pares.length === 0;
        }
        if (btnUnoEstado) {
            btnUnoEstado.disabled = estado.pares.length === 0 && !(estado.remitoSel && estado.facturaSel);
        }
        if (!estado.pares.length) {
            vacio.style.display = '';
            cont.innerHTML = '';
            return;
        }
        vacio.style.display = 'none';
        cont.innerHTML = estado.pares.map(function (p, idx) {
            var s = p.score || puntaje(p.remito, p.factura);
            return '<div class="arf-par is-' + s.nivel + '" data-idx="' + idx + '">' +
                '<div class="arf-par-flujo">' +
                '<div class="arf-par-lado">' +
                '<span class="lbl">Factura</span>' +
                '<span class="val">' + escapeHtml(p.factura.comprobante) + '</span>' +
                '<div class="meta">' + escapeHtml(p.factura.cliente) + ' · ' + escapeHtml(p.factura.fecha_txt) + ' · ' + fmtNum(p.factura.kilos) + ' kg</div>' +
                '</div>' +
                '<div class="arf-flecha text-center"><i class="fa fa-exchange-alt"></i></div>' +
                '<div class="arf-par-lado">' +
                '<span class="lbl">Remito (se conserva el nº)</span>' +
                '<span class="val">' + escapeHtml(p.remito.codigo) + '</span>' +
                '<div class="meta">' + escapeHtml(p.remito.cliente) + ' · ' + escapeHtml(p.remito.fecha_txt) + ' · ' + fmtNum(p.remito.kilos) + ' kg</div>' +
                '</div>' +
                '</div>' +
                '<div class="d-flex justify-content-between align-items-center mt-2">' +
                '<small>' + escapeHtml(s.etiqueta) + '</small>' +
                '<button type="button" class="btn btn-sm btn-outline-danger js-arf-quitar" data-idx="' + idx + '">' +
                '<i class="fa fa-times"></i> Quitar</button>' +
                '</div></div>';
        }).join('');
    }

    function renderPreview() {
        var box = document.getElementById('arf-preview');
        var btnVincular = document.getElementById('btn-arf-vincular');
        if (!estado.remitoSel || !estado.facturaSel) {
            box.classList.remove('is-visible');
            btnVincular.disabled = true;
            return;
        }
        var s = puntaje(estado.remitoSel, estado.facturaSel);
        box.classList.add('is-visible');
        btnVincular.disabled = false;
        var nivel = document.getElementById('arf-preview-nivel');
        nivel.textContent = s.nivel;
        nivel.className = 'badge badge-' + (s.nivel === 'excelente' || s.nivel === 'bueno' ? 'success' : (s.nivel === 'regular' ? 'warning' : 'danger'));
        document.getElementById('arf-preview-ayuda').textContent = s.etiqueta +
            '. Al confirmar, el remito ' + estado.remitoSel.codigo +
            ' pasa a cliente/fecha/artículos de la factura ' + estado.facturaSel.comprobante + '.';
        document.getElementById('arf-preview-factura').innerHTML =
            '<strong>' + escapeHtml(estado.facturaSel.comprobante) + '</strong><div>' +
            escapeHtml(estado.facturaSel.cliente) + ' · ' + escapeHtml(estado.facturaSel.fecha_txt) +
            '</div><div class="arf-articulos mt-1">' + articulosHtml(estado.facturaSel.articulos) + '</div>';
        document.getElementById('arf-preview-remito').innerHTML =
            '<strong>' + escapeHtml(estado.remitoSel.codigo) + '</strong><div>' +
            escapeHtml(estado.remitoSel.cliente) + ' · ' + escapeHtml(estado.remitoSel.fecha_txt) +
            ' · ' + escapeHtml(estado.remitoSel.origen_txt) +
            '</div><div class="arf-articulos mt-1">' + articulosHtml(estado.remitoSel.articulos) + '</div>';
    }

    function renderPaginacion() {
        document.getElementById('arf-count-facturas').textContent = estado.totalFacturas ? '(' + estado.totalFacturas + ')' : '';
        document.getElementById('arf-count-remitos').textContent = estado.totalRemitos ? '(' + estado.totalRemitos + ')' : '';
        document.getElementById('arf-pag-facturas-info').textContent = estado.totalFacturas
            ? 'Pág. ' + estado.pagFactura + ' / ' + estado.lastFactura
            : '';
        document.getElementById('arf-pag-remitos-info').textContent = estado.totalRemitos
            ? 'Pág. ' + estado.pagRemito + ' / ' + estado.lastRemito
            : '';
        document.getElementById('arf-pag-facturas-prev').disabled = estado.pagFactura <= 1;
        document.getElementById('arf-pag-facturas-next').disabled = estado.pagFactura >= estado.lastFactura;
        document.getElementById('arf-pag-remitos-prev').disabled = estado.pagRemito <= 1;
        document.getElementById('arf-pag-remitos-next').disabled = estado.pagRemito >= estado.lastRemito;
        var btnSug = document.getElementById('btn-arf-sugerir');
        if (btnSug) {
            btnSug.disabled = !estado.sugerencias.length;
        }
    }

    function pintar() {
        renderFacturas();
        renderRemitos();
        renderPares();
        renderPreview();
        renderPaginacion();
    }

    function empresaIdSeleccionada() {
        var el = document.getElementById('arf_empresa_id');
        return el ? String(el.value || '').trim() : '';
    }

    function paramsConsulta() {
        var p = new URLSearchParams();
        p.set('empresa_id', empresaIdSeleccionada());
        p.set('fecha_desde', document.getElementById('arf_fecha_desde').value);
        var hasta = document.getElementById('arf_fecha_hasta').value;
        if (hasta) {
            p.set('fecha_hasta', hasta);
        }
        var reparto = document.getElementById('arf_filtro_reparto').value.trim();
        if (reparto) {
            p.set('filtro_reparto', reparto);
        }
        p.set('vista', document.getElementById('arf_vista').value);
        p.set('busqueda_remito', document.getElementById('arf_busqueda_remito').value.trim());
        p.set('busqueda_factura', document.getElementById('arf_busqueda_factura').value.trim());
        p.set('pagina_remito', String(estado.pagRemito));
        p.set('pagina_factura', String(estado.pagFactura));
        return p;
    }

    function consultar() {
        if (!empresaIdSeleccionada()) {
            alert('Seleccioná una empresa. Cada empresa (El Bierzo vs división Villafranca / PV 15) se consulta por separado.');
            return;
        }
        mostrarOverlay('Consultando remitos y facturas…', 'Puede demorar según el período. No cierre la página.');
        fetch(cfg.urlConsultar + '?' + paramsConsulta().toString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        }).then(function (res) {
            return res.json().then(function (json) {
                if (!res.ok) {
                    throw new Error(json.mensaje || json.message || 'Error al consultar');
                }
                return json;
            });
        }).then(function (json) {
            estado.remitos = (json.remitos && json.remitos.data) || [];
            estado.facturas = (json.facturas && json.facturas.data) || [];
            estado.pagRemito = (json.remitos && json.remitos.current_page) || 1;
            estado.pagFactura = (json.facturas && json.facturas.current_page) || 1;
            estado.lastRemito = (json.remitos && json.remitos.last_page) || 1;
            estado.lastFactura = (json.facturas && json.facturas.last_page) || 1;
            estado.totalRemitos = (json.resumen && json.resumen.remitos) || 0;
            estado.totalFacturas = (json.resumen && json.resumen.facturas) || 0;
            estado.sugerencias = json.sugerencias || [];
            estado.remitoSel = null;
            estado.facturaSel = null;
            pintar();
        }).catch(function (err) {
            alert(err.message || 'No se pudo consultar');
        }).finally(ocultarOverlay);
    }

    function buscarDoc(lista, id) {
        id = Number(id);
        for (var i = 0; i < lista.length; i++) {
            if (Number(lista[i].id) === id) {
                return lista[i];
            }
        }
        return null;
    }

    function vincular(remito, factura) {
        if (!remito || !factura) {
            return;
        }
        if (!remito.huerfano) {
            alert('Ese remito ya tiene factura');
            return;
        }
        if (!factura.huerfano) {
            alert('Esa factura ya tiene remito');
            return;
        }
        var usados = idsUsados();
        if (usados.remitos[remito.id] || usados.facturas[factura.id]) {
            alert('Ese remito o factura ya está emparejado');
            return;
        }
        estado.pares.push({
            remito: remito,
            factura: factura,
            score: puntaje(remito, factura),
        });
        estado.remitoSel = null;
        estado.facturaSel = null;
        pintar();
    }

    function confirmar(pares) {
        if (!cfg.puedeEjecutar) {
            return;
        }
        if (!pares.length) {
            return;
        }
        if (!confirm('Al confirmar, cada remito se convierte en el remito de la factura (cliente, fecha y artículos). ¿Continuar?')) {
            return;
        }
        mostrarOverlay('Asignando remitos a facturas…', 'No cierre la página.');
        fetch(cfg.urlConfirmar, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': tokenCsrf(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                empresa_id: empresaIdSeleccionada(),
                pares: pares.map(function (p) {
                    return { remito_id: p.remito.id, venta_id: p.factura.id };
                }),
            }),
        }).then(function (res) {
            return res.json().then(function (json) {
                return { res: res, json: json };
            });
        }).then(function (pack) {
            var json = pack.json;
            var asignados = json.asignados || [];
            var errores = json.errores || [];
            if (asignados.length) {
                var idsR = {};
                var idsF = {};
                asignados.forEach(function (a) {
                    idsR[a.remito_id] = true;
                    idsF[a.venta_id] = true;
                });
                estado.pares = estado.pares.filter(function (p) {
                    return !idsR[p.remito.id] && !idsF[p.factura.id];
                });
            }
            var msg = json.mensaje || (pack.res.ok ? 'Listo' : 'No se pudo asignar');
            if (errores.length) {
                msg += '\n' + errores.map(function (e) { return e.error; }).join('\n');
            }
            alert(msg);
            consultar();
        }).catch(function (err) {
            alert(err.message || 'Error al confirmar');
            ocultarOverlay();
        });
    }

    function setVista(vista) {
        document.getElementById('arf_vista').value = vista;
        document.querySelectorAll('.js-arf-vista').forEach(function (btn) {
            var activa = btn.getAttribute('data-vista') === vista;
            btn.classList.toggle('btn-info', activa);
            btn.classList.toggle('btn-outline-secondary', !activa);
        });
    }

    document.getElementById('btn-arf-consultar').addEventListener('click', function () {
        estado.pagRemito = 1;
        estado.pagFactura = 1;
        consultar();
    });

    document.querySelectorAll('.js-arf-vista').forEach(function (btn) {
        btn.addEventListener('click', function () {
            setVista(btn.getAttribute('data-vista'));
            estado.pagRemito = 1;
            estado.pagFactura = 1;
            consultar();
        });
    });

    document.getElementById('arf-tbody-facturas').addEventListener('click', function (ev) {
        var tr = ev.target.closest('tr.js-arf-factura');
        if (!tr || tr.classList.contains('is-used')) {
            return;
        }
        estado.facturaSel = buscarDoc(estado.facturas, tr.getAttribute('data-id'));
        pintar();
    });

    document.getElementById('arf-tbody-remitos').addEventListener('click', function (ev) {
        var tr = ev.target.closest('tr.js-arf-remito');
        if (!tr || tr.classList.contains('is-used')) {
            return;
        }
        estado.remitoSel = buscarDoc(estado.remitos, tr.getAttribute('data-id'));
        pintar();
    });

    document.getElementById('btn-arf-vincular').addEventListener('click', function () {
        vincular(estado.remitoSel, estado.facturaSel);
    });

    document.getElementById('arf-pares').addEventListener('click', function (ev) {
        var btn = ev.target.closest('.js-arf-quitar');
        if (!btn) {
            return;
        }
        estado.pares.splice(Number(btn.getAttribute('data-idx')), 1);
        pintar();
    });

    document.getElementById('btn-arf-sugerir').addEventListener('click', function () {
        (estado.sugerencias || []).forEach(function (s) {
            var remito = buscarDoc(estado.remitos, s.remito_id);
            var factura = buscarDoc(estado.facturas, s.venta_id);
            if (remito && factura) {
                vincular(remito, factura);
            }
        });
    });

    var btnUno = document.getElementById('btn-arf-confirmar-uno');
    if (btnUno) {
        btnUno.addEventListener('click', function () {
            if (estado.remitoSel && estado.facturaSel) {
                confirmar([{
                    remito: estado.remitoSel,
                    factura: estado.facturaSel,
                    score: puntaje(estado.remitoSel, estado.facturaSel),
                }]);
                return;
            }
            if (!estado.pares.length) {
                return;
            }
            confirmar([estado.pares[estado.pares.length - 1]]);
        });
    }
    var btnTodas = document.getElementById('btn-arf-confirmar-todas');
    if (btnTodas) {
        btnTodas.addEventListener('click', function () {
            confirmar(estado.pares.slice());
        });
    }

    document.getElementById('arf-pag-facturas-prev').addEventListener('click', function () {
        if (estado.pagFactura > 1) {
            estado.pagFactura -= 1;
            consultar();
        }
    });
    document.getElementById('arf-pag-facturas-next').addEventListener('click', function () {
        if (estado.pagFactura < estado.lastFactura) {
            estado.pagFactura += 1;
            consultar();
        }
    });
    document.getElementById('arf-pag-remitos-prev').addEventListener('click', function () {
        if (estado.pagRemito > 1) {
            estado.pagRemito -= 1;
            consultar();
        }
    });
    document.getElementById('arf-pag-remitos-next').addEventListener('click', function () {
        if (estado.pagRemito < estado.lastRemito) {
            estado.pagRemito += 1;
            consultar();
        }
    });

    function debounceConsulta(lado) {
        var key = lado === 'remito' ? 'debounceRemito' : 'debounceFactura';
        clearTimeout(estado[key]);
        estado[key] = setTimeout(function () {
            if (lado === 'remito') {
                estado.pagRemito = 1;
            } else {
                estado.pagFactura = 1;
            }
            consultar();
        }, 400);
    }
    document.getElementById('arf_busqueda_factura').addEventListener('input', function () {
        debounceConsulta('factura');
    });
    document.getElementById('arf_busqueda_remito').addEventListener('input', function () {
        debounceConsulta('remito');
    });

    setVista(document.getElementById('arf_vista').value || 'huerfanos');
    var elEmpresa = document.getElementById('arf_empresa_id');
    if (elEmpresa) {
        elEmpresa.addEventListener('change', function () {
            estado.pares = [];
            estado.pagRemito = 1;
            estado.pagFactura = 1;
            if (empresaIdSeleccionada()) {
                consultar();
            }
        });
    }
    if (empresaIdSeleccionada()) {
        consultar();
    }
})();
