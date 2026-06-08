(function () {
    'use strict';

    var CFG = window.WAITRY_CIERRE_JORNADA_PROCESO || {};

    function el(id) {
        return document.getElementById(id);
    }

    function empresaYFechaDesdeFormulario() {
        var empresa = el('empresa_id');
        var fecha = el('fecha_jornada');
        return {
            empresa_id: empresa ? parseInt(empresa.value, 10) || 0 : 0,
            fecha_jornada: fecha ? (fecha.value || '') : '',
        };
    }

    function fmtMoney(n) {
        var x = parseFloat(n);
        if (isNaN(x)) {
            return '$ 0,00';
        }
        return '$ ' + x.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function mostrar(id, visible) {
        var node = el(id);
        if (!node) {
            return;
        }
        node.classList[(visible ? 'remove' : 'add')]('d-none');
    }

    function ocultarBannerRecalculo() {
        var overlay = el('proceso-recalculo-banner-overlay');
        var body = el('proceso-recalculo-banner-body');
        var ok = el('proceso-recalculo-ok');
        if (overlay) {
            overlay.classList.remove('is-visible');
            overlay.classList.add('d-none');
            overlay.setAttribute('aria-hidden', 'true');
        }
        document.body.classList.remove('waitry-recalculo-banner-abierto');
        if (body) {
            body.innerHTML = '';
        }
        if (ok) {
            ok.classList.add('d-none');
            ok.innerHTML = '';
        }
    }

    function mostrarBannerRecalculo(html) {
        mostrarOverlayRecalculando(false);
        var overlay = el('proceso-recalculo-banner-overlay');
        var body = el('proceso-recalculo-banner-body');
        var ok = el('proceso-recalculo-ok');
        if (!overlay || !body) {
            if (ok) {
                ok.innerHTML = html;
                ok.classList.remove('d-none');
            }
            return;
        }
        body.innerHTML = html;
        if (ok) {
            ok.innerHTML = html;
        }
        overlay.classList.remove('d-none');
        overlay.classList.add('is-visible');
        overlay.setAttribute('aria-hidden', 'false');
        document.body.classList.add('waitry-recalculo-banner-abierto');
    }

    function setRecalculoOk(html) {
        var err = el('proceso-error');
        if (err) {
            err.classList.add('d-none');
            err.textContent = '';
        }
        if (!html) {
            ocultarBannerRecalculo();
            return;
        }
        mostrarBannerRecalculo(html);
    }

    function setError(msg) {
        setRecalculoOk('');
        var box = el('proceso-error');
        if (!box) {
            return;
        }
        if (!msg) {
            box.classList.add('d-none');
            box.textContent = '';
            return;
        }
        box.textContent = msg;
        box.classList.remove('d-none');
    }

    function resumenCompensacionAnita(red) {
        red = red || {};
        var partes = [];
        var aQr = parseFloat(red.asignado_facturado_efectivo_a_qr, 10) || 0;
        var aMp = parseFloat(red.asignado_facturado_efectivo_a_mp, 10) || 0;
        if (aQr > 0.0001) {
            partes.push('QR ' + fmtMoney(aQr));
        }
        if (aMp > 0.0001) {
            partes.push('MP ' + fmtMoney(aMp));
        }
        var total = parseFloat(red.asignado_facturado_efectivo_compensacion, 10);
        if (isNaN(total)) {
            total = aQr + aMp;
        }
        return {
            total: total,
            etiqueta: partes.length ? partes.join(' · ') : fmtMoney(total),
        };
    }

    function mensajeRecalculoOk(data) {
        data = data || {};
        var red = data.redistribucion || {};
        var pct = parseFloat(data.porcentaje, 10) || 0;
        var objetivo = parseFloat(data.objetivo_importe, 10) || 0;
        var aEfectivo = parseFloat(red.asignado_sin_facturar_a_efectivo, 10) || 0;
        var comp = resumenCompensacionAnita(red);
        var cuadre = !!red.cuadre_qr_z_ok;
        var ajustes = red.ajustes || [];
        var nWaitry = ajustes.filter(function (a) {
            return a && String(a.tipo || '').indexOf('sin_facturar') === 0
                || String(a.tipo || '').indexOf('facturado_totem') === 0;
        }).length;
        var nAnita = ajustes.filter(function (a) {
            return a && String(a.tipo || '').indexOf('facturado_efectivo') === 0;
        }).length;

        var cuadreTxt = cuadre
            ? '<span class="text-success">Cuadre medios: OK</span> (Waitry→efectivo = Anita→mismo medio).'
            : '<span class="text-warning">Atención: compensación Anita (' + comp.etiqueta
                + ') distinta del traslado Waitry (' + fmtMoney(aEfectivo) + ').</span>';

        return 'Recálculo al <strong>' + pct + '%</strong> sobre facturado Anita '
            + '(objetivo ' + fmtMoney(objetivo) + ').<br><br>'
            + '<strong>Waitry sin facturar → efectivo:</strong> ' + fmtMoney(aEfectivo)
            + ' (' + nWaitry + ' ítems).<br>'
            + '<strong>Facturas Anita efectivo → medio original</strong> (planificado, sin grabar): '
            + comp.etiqueta + ' (' + nAnita + ' facturas).<br><br>'
            + cuadreTxt + '<br><br>'
            + 'Revise el cuadro y las tablas de redistribución antes de facturar.';
    }

    var textoProcesoLoadingDefault = '<i class="fa fa-spinner fa-spin"></i> Procesando movimientos Waitry…';

    function mostrarOverlayRecalculando(activo, pct) {
        var overlay = el('proceso-recalculando-overlay');
        var pctEl = el('proceso-recalculando-porcentaje');
        if (!overlay) {
            return;
        }
        if (activo) {
            if (pctEl && pct != null && !isNaN(parseFloat(pct, 10))) {
                pctEl.textContent = String(pct);
            } else if (pctEl) {
                pctEl.textContent = '—';
            }
            overlay.classList.remove('d-none');
            overlay.style.display = 'flex';
            overlay.setAttribute('aria-hidden', 'false');
            document.body.classList.add('waitry-recalculo-en-curso');
        } else {
            overlay.classList.add('d-none');
            overlay.style.display = '';
            overlay.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('waitry-recalculo-en-curso');
        }
    }

    var emisionFacturasLoadingTimer = null;

    function detenerRotacionMensajesEmisionFacturas() {
        if (emisionFacturasLoadingTimer) {
            clearInterval(emisionFacturasLoadingTimer);
            emisionFacturasLoadingTimer = null;
        }
    }

    function modofacturacionPuntoventaSeleccionado() {
        var selPv = el('emitir-proceso-puntoventa');
        if (!selPv || selPv.selectedIndex < 0) {
            return '';
        }
        var opt = selPv.options[selPv.selectedIndex];
        if (opt && opt.dataset && opt.dataset.modofacturacion) {
            return opt.dataset.modofacturacion;
        }
        var txt = opt ? String(opt.textContent || '') : '';
        var m = txt.match(/·\s*([A-Z])\s*$/);
        return m ? m[1] : '';
    }

    function mensajesProcesoEmisionCierre(opts) {
        opts = opts || {};
        var cantLotes = parseInt(opts.cantLotes, 10) || 0;
        var modFact = String(opts.modofacturacion || '').toUpperCase();
        var mensajes = [
            'Preparando comandas e ítems por lote…',
            'Generando comprobante fiscal en anitaERP…',
            'Registrando venta en anitaERP…',
            'Calculando IVA e impuestos…',
        ];
        if (cantLotes > 1) {
            mensajes.push('Emitiendo facturas CF por lotes (' + cantLotes + ' comprobantes)…');
        } else if (cantLotes === 1) {
            mensajes.push('Emitiendo factura CF del proceso…');
        }
        mensajes.push('Registrando cobranza…');
        mensajes.push('Actualizando stock e insumos (fórmulas)…');
        if (CFG.sincronizarAnitaAlFacturar !== false) {
            mensajes.push('Grabando comprobante en Anita (Informix)…');
        }
        if (modFact === 'A') {
            mensajes.push('Solicitando autorización ARCA (CAEA)…');
        } else if (modFact === 'C' || modFact === 'E') {
            mensajes.push('Solicitando autorización ARCA (CAE)…');
        } else {
            mensajes.push('Solicitando autorización ARCA…');
        }
        mensajes.push('Registrando ajuste de insumos (comandas efectivo)…');
        mensajes.push('Finalizando emisión del proceso…');

        return mensajes;
    }

    function setOverlayEmitiendoFacturasTexto(mensaje, opciones) {
        var opts = opciones || {};
        var titulo = el('proceso-emitiendo-facturas-titulo');
        var subtitulo = el('proceso-emitiendo-facturas-subtitulo');
        var texto = mensaje || 'Generando facturas del proceso…';
        if (titulo) {
            titulo.textContent = texto;
        }
        if (subtitulo && opts.subtitulo !== undefined) {
            subtitulo.textContent = opts.subtitulo;
        }
    }

    function iniciarRotacionMensajesEmisionFacturas(mensajes, intervaloMs) {
        detenerRotacionMensajesEmisionFacturas();
        if (!mensajes || !mensajes.length) {
            mostrarOverlayEmitiendoFacturas(true);
            return;
        }
        var idx = 0;
        mostrarOverlayEmitiendoFacturas(true, mensajes[0]);
        emisionFacturasLoadingTimer = setInterval(function () {
            idx = (idx + 1) % mensajes.length;
            setOverlayEmitiendoFacturasTexto(mensajes[idx], { soloTexto: true });
        }, intervaloMs || 2800);
    }

    function mostrarOverlayEmitiendoFacturas(activo, mensaje, opciones) {
        var overlay = el('proceso-emitiendo-facturas-overlay');
        if (!overlay) {
            return;
        }
        if (activo) {
            overlay.classList.remove('d-none');
            overlay.style.display = 'flex';
            overlay.setAttribute('aria-hidden', 'false');
            document.body.classList.add('waitry-proceso-en-curso');
            if (mensaje) {
                setOverlayEmitiendoFacturasTexto(mensaje, opciones);
            }
        } else {
            detenerRotacionMensajesEmisionFacturas();
            overlay.classList.add('d-none');
            overlay.style.display = '';
            overlay.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('waitry-proceso-en-curso');
            setOverlayEmitiendoFacturasTexto('Generando facturas del proceso…', {
                subtitulo: 'Emitiendo comprobantes CF por lotes según el cuadre.',
            });
        }
    }

    var grabacionAsientosLoadingTimer = null;

    function detenerRotacionMensajesGrabacionAsientos() {
        if (grabacionAsientosLoadingTimer) {
            clearInterval(grabacionAsientosLoadingTimer);
            grabacionAsientosLoadingTimer = null;
        }
    }

    function mensajesProcesoGrabacionAsientos() {
        return [
            'Validando preview de asientos del proceso…',
            'Resolviendo cuentas contables por medio de pago…',
            'Armando líneas Debe / Haber…',
            'Grabando asiento en anitaERP…',
            'Registrando movimientos contables (ctamov)…',
            'Sincronizando con Anita Informix…',
            'Cuadrando totales del proceso…',
            'Finalizando grabación de asientos…',
        ];
    }

    function setOverlayGrabandoAsientosTexto(mensaje, opciones) {
        var opts = opciones || {};
        var titulo = el('proceso-grabando-asientos-titulo');
        var subtitulo = el('proceso-grabando-asientos-subtitulo');
        var texto = mensaje || 'Grabando asientos contables…';
        if (titulo) {
            titulo.textContent = texto;
        }
        if (subtitulo && opts.subtitulo !== undefined) {
            subtitulo.textContent = opts.subtitulo;
        }
    }

    function iniciarRotacionMensajesGrabacionAsientos(mensajes, intervaloMs) {
        detenerRotacionMensajesGrabacionAsientos();
        if (!mensajes || !mensajes.length) {
            mostrarOverlayGrabandoAsientos(true);
            return;
        }
        var idx = 0;
        mostrarOverlayGrabandoAsientos(true, mensajes[0]);
        grabacionAsientosLoadingTimer = setInterval(function () {
            idx = (idx + 1) % mensajes.length;
            setOverlayGrabandoAsientosTexto(mensajes[idx], { soloTexto: true });
        }, intervaloMs || 2800);
    }

    function mostrarOverlayGrabandoAsientos(activo, mensaje, opciones) {
        var overlay = el('proceso-grabando-asientos-overlay');
        if (!overlay) {
            return;
        }
        if (activo) {
            overlay.classList.remove('d-none');
            overlay.style.display = 'flex';
            overlay.setAttribute('aria-hidden', 'false');
            document.body.classList.add('waitry-proceso-en-curso');
            if (mensaje) {
                setOverlayGrabandoAsientosTexto(mensaje, opciones);
            }
        } else {
            detenerRotacionMensajesGrabacionAsientos();
            overlay.classList.add('d-none');
            overlay.style.display = '';
            overlay.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('waitry-proceso-en-curso');
            setOverlayGrabandoAsientosTexto('Grabando asientos contables…', {
                subtitulo: 'Persistiendo el preview validado en ERP y Anita ctamov.',
            });
        }
    }

    function setProcesoLoading(visible, textoHtml) {
        var loading = el('proceso-loading');
        if (!loading) {
            return;
        }
        if (textoHtml) {
            loading.innerHTML = textoHtml;
        }
        mostrar('proceso-loading', visible);
        if (!visible && !textoHtml) {
            loading.innerHTML = textoProcesoLoadingDefault;
        }
    }

    function mensajeErrorApi(data, fallback) {
        if (!data || typeof data !== 'object') {
            return fallback;
        }
        if (data.error) {
            return String(data.error);
        }
        if (data.message) {
            return String(data.message);
        }
        if (data.errors && typeof data.errors === 'object') {
            var partes = [];
            Object.keys(data.errors).forEach(function (k) {
                var v = data.errors[k];
                if (Array.isArray(v)) {
                    partes.push(v.join(' '));
                } else if (v) {
                    partes.push(String(v));
                }
            });
            if (partes.length) {
                return partes.join(' ');
            }
        }

        return fallback;
    }

    function parsearRespuestaJson(r) {
        return r.text().then(function (texto) {
            if (!texto) {
                return {};
            }
            try {
                return JSON.parse(texto);
            } catch (err) {
                if (r.status === 401 || r.status === 403) {
                    throw new Error('Sesión expirada o sin permiso. Recargue la página e ingrese de nuevo.');
                }
                if (r.status >= 500) {
                    throw new Error('Error del servidor (HTTP ' + r.status + '). Consulte con sistemas.');
                }
                throw new Error('La respuesta no es JSON válido (HTTP ' + r.status + ').');
            }
        });
    }

    function apiGet(url) {
        return fetch(url, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        }).then(function (r) {
            return parsearRespuestaJson(r).then(function (data) {
                if (!r.ok) {
                    throw new Error(mensajeErrorApi(data, 'Error en la consulta (HTTP ' + r.status + ').'));
                }
                return data;
            });
        });
    }

    function apiPost(url, body) {
        var fd = new FormData();
        fd.append('_token', CFG.csrf || '');
        Object.keys(body || {}).forEach(function (k) {
            fd.append(k, body[k]);
        });
        return fetch(url, {
            method: 'POST',
            body: fd,
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        }).then(function (r) {
            return parsearRespuestaJson(r).then(function (data) {
                if (!r.ok) {
                    throw new Error(mensajeErrorApi(data, 'Error en la operación (HTTP ' + r.status + ').'));
                }
                return data;
            });
        });
    }

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s || '';
        return d.innerHTML;
    }

    function urlVerFacturaGastronomia(ventaId) {
        var base = CFG.urlFacturaVerBase;
        if (!base || !ventaId) {
            return '';
        }
        var url = String(base).replace(/\/$/, '') + '/' + ventaId + '/ver';
        if (typeof window.ModoConsulta !== 'undefined' && window.ModoConsulta) {
            url = window.ModoConsulta.url(url);
        }

        return url;
    }

    function urlVerAsientoContable(asientoId) {
        var base = CFG.urlAsientoVerBase;
        if (!base || !asientoId) {
            return '';
        }
        var url = String(base).replace(/\/$/, '') + '/' + asientoId + '/editar';
        if (typeof window.ModoConsulta !== 'undefined' && window.ModoConsulta) {
            url = window.ModoConsulta.url(url);
        }

        return url;
    }

    function urlVerMovimientoStock(movimientoId) {
        var base = CFG.urlMovimientoStockVerBase;
        if (!base || !movimientoId) {
            return '';
        }
        var url = String(base).replace(/\/$/, '') + '/' + movimientoId + '/editar';
        if (typeof window.ModoConsulta !== 'undefined' && window.ModoConsulta) {
            url = window.ModoConsulta.url(url);
        }

        return url;
    }

    function htmlBotonVerAsiento(asientoId) {
        var url = urlVerAsientoContable(asientoId);
        if (!url) {
            return '<span class="text-muted">—</span>';
        }

        return '<a href="' + escapeHtml(url) + '" class="btn-accion-tabla tooltipsC" target="_blank" rel="noopener" '
            + 'title="Abrir asiento en contabilidad">'
            + '<i class="fas fa-book text-primary"></i>'
            + '<span class="small"> Ver</span></a>';
    }

    function htmlBotonVerFactura(ventaId) {
        var url = urlVerFacturaGastronomia(ventaId);
        if (!url) {
            return '<span class="text-muted">—</span>';
        }

        return '<a href="' + escapeHtml(url) + '" class="btn-accion-tabla tooltipsC" target="_blank" rel="noopener" '
            + 'title="Ver factura en Anita (medio de pago, emisión y cobranzas)">'
            + '<i class="fas fa-file-invoice text-primary"></i>'
            + '<span class="small"> Ver</span></a>';
    }

    function etiquetaMedioAnitaOPlanificado(it) {
        var anita = (it.medio_anita_label || it.anita_cuentacaja_label || '').trim();
        if (anita !== '') {
            return anita;
        }
        if (it.medio_planificado_label) {
            return it.medio_planificado_label;
        }

        return etiquetaMedioPlanificado(it);
    }

    function jQueryDisponible() {
        return typeof window.jQuery !== 'undefined' && typeof window.jQuery.fn !== 'undefined'
            && typeof window.jQuery.fn.DataTable !== 'undefined';
    }

    function destruirDataTable(selector) {
        if (!jQueryDisponible()) {
            return;
        }
        var $t = window.jQuery(selector);
        if ($t.length) {
            $t.closest('.waitry-cierre-modal-tabla-wrap').removeClass('waitry-cierre-dt-activo');
            if (window.jQuery.fn.DataTable.isDataTable($t)) {
                $t.DataTable().clear().destroy();
            }
        }
    }

    function opcionesBotonesExportDataTable() {
        if (typeof configuracionBotonesExportDataTable === 'function') {
            return configuracionBotonesExportDataTable();
        }

        return ['copyHtml5', 'excelHtml5', 'csvHtml5', 'print', 'pageLength'];
    }

    function initDataTableDetalle(selector, tituloExport, columnaTotalIdx) {
        if (!jQueryDisponible()) {
            return null;
        }
        var $table = window.jQuery(selector);
        if (!$table.length) {
            return null;
        }
        destruirDataTable(selector);
        window.tituloExportListado = tituloExport || 'Detalle';

        var $wrap = $table.closest('.waitry-cierre-modal-tabla-wrap');
        if ($wrap.length) {
            $wrap.removeClass('waitry-cierre-dt-activo');
        }

        try {
            var dt = $table.DataTable({
                language: typeof idioma !== 'undefined' ? idioma : undefined,
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'Todo']],
                ordering: true,
                searching: true,
                info: true,
                paging: true,
                autoWidth: false,
                scrollX: true,
                dom: 'Blfrtip',
                buttons: opcionesBotonesExportDataTable(),
                columnDefs: [
                    { targets: columnaTotalIdx, className: 'text-right' },
                    { targets: -1, orderable: false, searchable: false },
                ],
            });
            if ($wrap.length) {
                $wrap.addClass('waitry-cierre-dt-activo');
            }
            dt.columns.adjust().draw(false);
            return dt;
        } catch (err) {
            console.error('waitry_cierre_jornada_proceso DataTable', err);
            return null;
        }
    }

    function fetchTodasLasPaginasItems(fetchPaginaFn) {
        var porPagina = 500;

        return fetchPaginaFn(1, porPagina).then(function (data) {
            var items = (data.items || []).slice();
            var totalPaginas = data.total_paginas || 1;
            if (totalPaginas <= 1) {
                return Object.assign({}, data, { items: items });
            }
            var promises = [];
            for (var p = 2; p <= totalPaginas; p++) {
                promises.push(fetchPaginaFn(p, porPagina));
            }
            return Promise.all(promises).then(function (pages) {
                pages.forEach(function (pageData) {
                    items = items.concat(pageData.items || []);
                });

                return Object.assign({}, data, {
                    items: items,
                    pagina: 1,
                    total_paginas: 1,
                    total_registros: items.length,
                });
            });
        });
    }

    function fetchPreviewFacturaTodasComandas(params, comandasAlcance) {
        var porPagina = 500;
        var alcance = comandasAlcance || 'factura_proceso';

        return apiGet(urlPreviewFactura(params, 1, porPagina, alcance)).then(function (data) {
            var comandas = data.comandas || {};
            var items = (comandas.items || []).slice();
            var totalPaginas = comandas.total_paginas || 1;
            if (totalPaginas <= 1) {
                data.comandas = Object.assign({}, comandas, { items: items });
                data.comandas_alcance = data.comandas_alcance || alcance;

                return data;
            }
            var promises = [];
            for (var p = 2; p <= totalPaginas; p++) {
                promises.push(apiGet(urlPreviewFactura(params, p, porPagina, alcance)));
            }
            return Promise.all(promises).then(function (pages) {
                pages.forEach(function (pd) {
                    items = items.concat((pd.comandas || {}).items || []);
                });
                data.comandas = Object.assign({}, comandas, {
                    items: items,
                    pagina: 1,
                    total_paginas: 1,
                    total: items.length,
                });
                data.comandas_alcance = data.comandas_alcance || alcance;

                return data;
            });
        });
    }

    function initModalesDataTableCleanup() {
        if (!jQueryDisponible()) {
            return;
        }
        var $ = window.jQuery;
        $('#modal-cuadro-detalle, #modal-comandas-factura').on('shown.bs.modal', function () {
            $(this).find('table.dataTable').each(function () {
                if ($.fn.DataTable.isDataTable(this)) {
                    $(this).DataTable().columns.adjust().draw(false);
                }
            });
        });
        $('#modal-cuadro-detalle').on('hidden.bs.modal', function () {
            destruirDataTable('#tabla-cuadro-detalle');
        });
        $('#modal-comandas-factura').on('hidden.bs.modal', function () {
            destruirDataTable('#tabla-comandas-factura');
        });
    }

    var TIPOS_AJUSTE_WAITRY_EFECTIVO = {
        sin_facturar_qr_a_efectivo: 'QR → efectivo (total)',
        sin_facturar_qr_mixto: 'QR → mixto (parcial)',
        sin_facturar_mp_a_efectivo: 'Mercado Pago → efectivo (total)',
        sin_facturar_mp_mixto: 'Mercado Pago → mixto (parcial)',
        facturado_totem_qr_a_efectivo: 'TOTEM QR → efectivo',
        facturado_totem_qr_mixto: 'TOTEM QR → mixto',
        facturado_totem_mp_a_efectivo: 'TOTEM MP → efectivo',
        facturado_totem_mp_mixto: 'TOTEM MP → mixto',
    };

    var TIPOS_AJUSTE_ANITA_COMPENSACION = {
        facturado_efectivo_a_qr: 'Efectivo → QR (total)',
        facturado_efectivo_a_mp: 'Efectivo → Mercado Pago (total)',
        facturado_efectivo_mixto: 'Efectivo → mixto',
    };

    function etiquetaTipoAjuste(tipo, mapa) {
        mapa = mapa || {};
        return mapa[tipo] || String(tipo || '').replace(/_/g, ' ');
    }

    function etiquetaMedioPlanificadoDesdeAjuste(aj) {
        if (aj.detalle_mixto && aj.detalle_mixto.length) {
            return aj.detalle_mixto.map(function (p) {
                return (p.clave || '') + ' ' + fmtMoney(p.monto);
            }).join(' · ');
        }
        if (aj.hacia === 'mixto') {
            return 'Mixto';
        }
        return aj.hacia || '—';
    }

    function renderFilasAjustes(tbody, ajustes, columnasFn) {
        if (!tbody) {
            return;
        }
        tbody.innerHTML = '';
        (ajustes || []).forEach(function (aj) {
            var tr = document.createElement('tr');
            tr.innerHTML = columnasFn(aj);
            tbody.appendChild(tr);
        });
    }

    function renderRedistribucion(data) {
        var panel = el('panel-proceso-redistribucion');
        var alertRes = el('alert-redistribucion-resumen');
        var tbodyWaitry = el('tbody-redistribucion-waitry');
        var tbodyAnita = el('tbody-redistribucion-anita');
        var sinAjustes = el('redistribucion-sin-ajustes');
        if (!panel) {
            return;
        }

        var red = data && data.redistribucion;
        var pct = parseFloat(data && data.porcentaje, 10) || 0;
        if (!red || pct <= 0.0001) {
            mostrar('panel-proceso-redistribucion', false);
            return;
        }

        var ajustesTodos = red.ajustes || [];
        var ajustesWaitry = ajustesTodos.filter(function (a) {
            return a && TIPOS_AJUSTE_WAITRY_EFECTIVO[a.tipo];
        });
        var ajustesAnita = ajustesTodos.filter(function (a) {
            return a && TIPOS_AJUSTE_ANITA_COMPENSACION[a.tipo];
        });
        var asignadoEf = parseFloat(red.asignado_sin_facturar_a_efectivo, 10) || 0;
        var comp = resumenCompensacionAnita(red);
        var objetivo = parseFloat(data.objetivo_importe, 10) || 0;
        var cuadreOk = !!red.cuadre_qr_z_ok;

        if (alertRes) {
            var cuadreHtml = cuadreOk
                ? ' <span class="text-success"><i class="fa fa-check"></i> Cuadre medios: Waitry→efectivo = Anita→mismo medio</span>'
                : ' <span class="text-danger"><i class="fa fa-exclamation-triangle"></i> Descuadre compensación ('
                    + fmtMoney(asignadoEf) + ' vs ' + comp.etiqueta + ')</span>';
            alertRes.innerHTML = '<strong>Objetivo (' + pct + '%):</strong> ' + fmtMoney(objetivo)
                + ' · <strong>Waitry → efectivo:</strong> ' + fmtMoney(asignadoEf)
                + ' (' + ajustesWaitry.length + ' ítems)'
                + ' · <strong>Anita efectivo → medio original (planif.):</strong> ' + comp.etiqueta
                + ' (' + ajustesAnita.length + ' facturas)' + cuadreHtml
                + '<br><span class="text-muted">Solo memoria; no se graba cobranza hasta validar.</span>';
        }

        renderFilasAjustes(tbodyWaitry, ajustesWaitry, function (aj) {
            return '<td>' + (aj.waitry_order_id || '—') + '</td>'
                + '<td>' + escapeHtml(aj.venta_codigo || '') + '</td>'
                + '<td class="text-right">' + fmtMoney(aj.monto) + '</td>'
                + '<td class="small">' + escapeHtml(etiquetaMedioPlanificadoDesdeAjuste(aj)) + '</td>'
                + '<td class="small">' + escapeHtml(etiquetaTipoAjuste(aj.tipo, TIPOS_AJUSTE_WAITRY_EFECTIVO)) + '</td>';
        });

        renderFilasAjustes(tbodyAnita, ajustesAnita, function (aj) {
            return '<td>' + escapeHtml(aj.venta_codigo || '—') + '</td>'
                + '<td>' + (aj.waitry_order_id || '—') + '</td>'
                + '<td class="text-right">' + fmtMoney(aj.monto) + '</td>'
                + '<td class="small">' + escapeHtml(etiquetaMedioPlanificadoDesdeAjuste(aj)) + '</td>'
                + '<td class="small">' + escapeHtml(etiquetaTipoAjuste(aj.tipo, TIPOS_AJUSTE_ANITA_COMPENSACION)) + '</td>';
        });

        if (sinAjustes) {
            var msgs = [];
            if (ajustesWaitry.length === 0 && objetivo > 0) {
                msgs.push('Sin comandas Waitry QR / Mercado Pago para el cupo a efectivo.');
            }
            if (ajustesAnita.length === 0 && asignadoEf > 0) {
                msgs.push('Sin facturas Anita en efectivo (cuenta real) para compensar al medio original.');
            }
            sinAjustes.textContent = msgs.join(' ');
        }

        mostrar('panel-proceso-redistribucion', true);
    }

    function etiquetaColumnaCuadro(col) {
        if (!col) {
            return '—';
        }
        if (col.etiqueta) {
            return String(col.etiqueta);
        }
        var codigo = String(col.codigo || '').trim();
        var nombre = String(col.nombre || '').trim();
        if (codigo && nombre) {
            return codigo + ' — ' + nombre;
        }
        return codigo || nombre || String(col.id || '—');
    }

    function tituloColumnaCuadro(col) {
        return etiquetaColumnaCuadro(col);
    }

    function columnasLegacyCuadro() {
        return [
            { id: 'qr', etiqueta: 'QR' },
            { id: 'mp', etiqueta: 'MP' },
            { id: 'efectivo', etiqueta: 'Efectivo' },
            { id: 'otros', etiqueta: 'Otros' },
        ];
    }

    function calcularLayoutColumnasCuadro(columnas, containerEl, mostrarDifCaja) {
        var todas = columnas || [];
        var ancho = containerEl && containerEl.clientWidth > 0
            ? containerEl.clientWidth
            : (window.innerWidth || 1200);
        var reservado = 210 + 95 + (mostrarDifCaja ? 95 : 0) + 32;
        var anchoCol = 88;
        var maxCols = Math.floor(Math.max(0, ancho - reservado) / anchoCol);
        if (maxCols >= todas.length || todas.length === 0) {
            return { visibles: todas.slice(), otrosIds: [], incluyeOtros: false };
        }
        if (maxCols <= 1) {
            return {
                visibles: [],
                otrosIds: todas.map(function (c) { return String(c.id); }),
                incluyeOtros: todas.length > 0,
            };
        }
        return {
            visibles: todas.slice(0, maxCols - 1),
            otrosIds: todas.slice(maxCols - 1).map(function (c) { return String(c.id); }),
            incluyeOtros: true,
        };
    }

    function montoFilaPorColumnaCuadro(fila, colDef, otrosIds) {
        var colId = String(colDef.id || '');
        if (colId === 'diferencia_caja') {
            return parseFloat(fila.diferencia_caja) || 0;
        }
        if (colId === 'otros_agregados') {
            var sumOtros = 0;
            (otrosIds || []).forEach(function (idCol) {
                sumOtros += montoFilaPorColumnaCuadro(fila, { id: idCol }, []);
            });
            return sumOtros;
        }
        var porCuenta = fila.por_cuenta || {};
        var ccId = parseInt(String(colId).replace(/^cc:/, ''), 10);
        if (colId.indexOf('cc:') === 0 && !isNaN(ccId)) {
            return parseFloat(porCuenta[ccId] != null ? porCuenta[ccId] : porCuenta[String(ccId)]) || 0;
        }
        return parseFloat(fila[colId]) || 0;
    }

    function columnasRenderCuadro(layout, mostrarDifCaja) {
        var cols = (layout.visibles || []).slice();
        if (layout.incluyeOtros) {
            cols.push({ id: 'otros_agregados', etiqueta: 'Otros' });
        }
        if (mostrarDifCaja) {
            cols.push({ id: 'diferencia_caja', etiqueta: 'Dif. caja' });
        }
        return cols;
    }

    function renderCuadro(data) {
        data = data || {};
        procesoEstado.lastCuadroData = data;
        var filas = data.cuadro_filas || [];
        var tbody = el('tbody-cuadro-cierre');
        var tabla = el('tabla-cuadro-cierre');
        if (!tbody || !tabla) {
            return;
        }
        var columnas = data.cuadro_columnas_medios || [];
        if (!columnas.length) {
            columnas = columnasLegacyCuadro();
        }
        var mostrarDifCaja = false;
        filas.forEach(function (f) {
            if (parseFloat(f.diferencia_caja) > 0.0001) {
                mostrarDifCaja = true;
            }
        });
        var wrapper = tabla.closest('.table-responsive');
        var layout = calcularLayoutColumnasCuadro(columnas, wrapper, mostrarDifCaja);
        var colsRender = columnasRenderCuadro(layout, mostrarDifCaja);
        var theadRow = el('thead-cuadro-cierre');
        if (theadRow) {
            theadRow.innerHTML = '<th>Concepto</th>'
                + colsRender.map(function (c) {
                    return '<th class="text-right cuadro-col-medio" title="' + escapeHtml(tituloColumnaCuadro(c)) + '">'
                        + escapeHtml(etiquetaColumnaCuadro(c)) + '</th>';
                }).join('')
                + '<th class="text-right">Total</th>';
        }
        tbody.innerHTML = '';
        var totales = { total: 0 };
        colsRender.forEach(function (c) {
            totales[c.id] = 0;
        });
        filas.forEach(function (fila) {
            var tr = document.createElement('tr');
            if (fila.tipo === 'waitry_impago') {
                tr.className = 'table-secondary';
            } else if (fila.tipo === 'waitry_cash') {
                tr.className = 'table-warning';
            } else if (fila.tipo === 'waitry_pago') {
                tr.className = 'table-info';
            } else if (fila.tipo === 'anita_totem') {
                tr.className = 'table-success';
            }
            var celdasMedios = colsRender.map(function (c) {
                var val = montoFilaPorColumnaCuadro(fila, c, layout.otrosIds);
                var cls = 'text-right cuadro-col-medio';
                var clickable = val > 0.0001 && fila.tipo && c.id !== 'otros_agregados';
                if (clickable) {
                    cls += ' cuadro-celda-detalle text-primary';
                }
                return '<td class="' + cls + '" data-fila="' + String(fila.tipo || '') + '" data-medio="' + escapeHtml(String(c.id)) + '"'
                    + (clickable ? ' role="button" tabindex="0" title="Ver detalle de comandas (clic)"' : '')
                    + '>' + fmtMoney(val) + '</td>';
            }).join('');
            tr.innerHTML = '<td>' + escapeHtml(fila.etiqueta || '') + '</td>' + celdasMedios
                + '<td class="text-right waitry-cierre-col-monto">' + fmtMoney(fila.total) + '</td>';
            tbody.appendChild(tr);
            if (fila.tipo !== 'waitry_impago') {
                colsRender.forEach(function (c) {
                    totales[c.id] += montoFilaPorColumnaCuadro(fila, c, layout.otrosIds);
                });
                totales.total += parseFloat(fila.total) || 0;
            }
        });
        var tfootRow = el('tfoot-cuadro-cierre');
        if (tfootRow) {
            tfootRow.innerHTML = '<td>Total cuadro (Anita + Waitry pend./impago)</td>'
                + colsRender.map(function (c) {
                    return '<td class="text-right cuadro-col-medio">' + fmtMoney(totales[c.id] || 0) + '</td>';
                }).join('')
                + '<td class="text-right" id="cuadro-total-general">' + fmtMoney(data.total_cuadro || totales.total) + '</td>';
        } else {
            var elTotal = el('cuadro-total-general');
            if (elTotal) {
                elTotal.textContent = fmtMoney(data.total_cuadro || totales.total);
            }
        }
        el('label-total-facturacion').textContent = fmtMoney(data.total_facturacion || 0);
        el('label-pendiente-facturar').textContent = fmtMoney(data.total_pendiente_facturar || 0);
        el('label-impago-waitry').textContent = fmtMoney(data.total_impago_waitry || 0);
        actualizarDesgloseAnitaCuadro(data);
        actualizarContextoPorcentaje(data);
    }

    function actualizarDesgloseAnitaCuadro(data) {
        var box = el('cuadro-desglose-anita');
        if (!box) {
            return;
        }
        data = data || {};
        var neto = parseFloat(data.total_facturacion) || 0;
        var filaJornada = parseFloat(data.total_anita_jornada_cuadro) || 0;
        var filaTotem = parseFloat(data.total_anita_totem_cuadro) || 0;
        var sinWaitry = parseFloat(data.total_anita_sin_waitry_cuadro != null
            ? data.total_anita_sin_waitry_cuadro
            : data.total_anita_sin_waitry) || 0;
        var nc = parseFloat(data.total_notas_credito) || 0;
        var partes = [];
        partes.push('Fila 1 (cobranzas ERP): ' + fmtMoney(filaJornada));
        if (Math.abs(filaTotem) > 0.0001) {
            partes.push('Fila TOTEM: ' + fmtMoney(filaTotem));
        }
        var sumaCuadro = Math.round((filaJornada + filaTotem) * 100) / 100;
        var delta = Math.round((neto - sumaCuadro) * 100) / 100;
        var notas = [];
        if (Math.abs(sinWaitry) > 0.0001) {
            notas.push('incl. sin Waitry: ' + fmtMoney(sinWaitry));
        }
        if (Math.abs(nc) > 0.0001) {
            notas.push('NC en neto: ' + fmtMoney(nc));
        }
        box.textContent = partes.join(' + ')
            + (notas.length ? (' (' + notas.join('; ') + ')') : '')
            + (Math.abs(delta) > 0.02
                ? (' · Diferencia vs neto arriba: ' + fmtMoney(delta))
                : ' · Cuadra con neto arriba');
        box.classList.remove('d-none');
    }

    var cuadroDetalleState = { fila: '', medio: '', pagina: 1 };
    var comandasFacturaState = { pagina: 1, ultimoPreview: null, alcance: 'factura_proceso' };

    var TITULOS_COMANDAS_ALCANCE = {
        factura_proceso: 'Comandas incluidas en la factura del proceso',
        efectivo_no_facturado: 'Comandas con efectivo no incluido en la factura del proceso',
    };
    var procesoEstado = {
        porcentaje: 0,
        snapshotCongelado: false,
        detalleCache: {},
        jornadaProceso: null,
        lastCuadroData: null,
        previewLotesEmision: null,
    };

    function porcentajeProcesoActual() {
        if (procesoEstado.porcentaje > 0) {
            return procesoEstado.porcentaje;
        }
        return porcentajeActual();
    }

    function claveCacheDetalle(fila, medio) {
        return String(fila) + '|' + String(medio) + '|' + porcentajeProcesoActual();
    }

    function porcentajeActual() {
        var pct = parseFloat((el('input-porcentaje') || {}).value, 10);
        if (isNaN(pct) || pct < 0) {
            return 0;
        }
        if (pct > 100) {
            return 100;
        }
        return pct;
    }

    function objetivoDesdePorcentaje(totalFacturacion, porcentaje) {
        var base = parseFloat(totalFacturacion, 10) || 0;
        var pct = parseFloat(porcentaje, 10) || 0;
        if (base <= 0 || pct <= 0) {
            return 0;
        }
        return Math.round(base * pct / 100);
    }

    function contextoPorcentajeDesdeData(data) {
        data = data || {};
        var ctx = data.contexto_porcentaje || {};
        return {
            totalFacturacionAnita: parseFloat(ctx.total_facturacion_anita != null
                ? ctx.total_facturacion_anita
                : data.total_facturacion) || 0,
            totalRecodificable: parseFloat(ctx.total_sin_facturar_recodificable) || 0,
            porcentajeMaximo: parseFloat(ctx.porcentaje_maximo_recodificacion) || 0,
            pendienteQrMpBase: parseFloat(ctx.total_pendiente_qr_mp) || 0,
        };
    }

    function validarPorcentajeRecodificacion(ctx, porcentaje) {
        ctx = ctx || {};
        var pct = parseFloat(porcentaje, 10) || 0;
        if (pct <= 0.0001) {
            return { ok: true, objetivo: 0, pendienteTras: ctx.pendienteQrMpBase || ctx.totalRecodificable || 0 };
        }
        var objetivo = objetivoDesdePorcentaje(ctx.totalFacturacionAnita, pct);
        var recodificable = parseFloat(ctx.totalRecodificable, 10) || 0;
        var pendienteBase = parseFloat(ctx.pendienteQrMpBase, 10);
        if (isNaN(pendienteBase)) {
            pendienteBase = recodificable;
        }
        var pendienteTras = Math.max(0, Math.round(pendienteBase - objetivo));
        if (objetivo > recodificable + 0.0001) {
            return {
                ok: false,
                objetivo: objetivo,
                pendienteTras: pendienteTras,
                mensaje: 'El ' + pct + '% implica recodificar ' + fmtMoney(objetivo)
                    + ', pero lo recodificable es ' + fmtMoney(recodificable)
                    + '. Máximo: ' + (ctx.porcentajeMaximo || 0) + '%.',
            };
        }

        return { ok: true, objetivo: objetivo, pendienteTras: pendienteTras };
    }

    function actualizarContextoPorcentaje(data) {
        var panel = el('panel-contexto-porcentaje');
        if (!panel) {
            return;
        }
        data = data || procesoEstado.lastCuadroData || {};
        var ctx = contextoPorcentajeDesdeData(data);
        var pct = porcentajeActual();
        var val = validarPorcentajeRecodificacion(ctx, pct);
        var elFact = el('ctx-facturacion-anita');
        var elRec = el('ctx-sin-facturar-recodificable');
        var elMax = el('ctx-porcentaje-maximo');
        var elObj = el('ctx-objetivo-importe');
        var elPend = el('ctx-pendiente-qr-mp-tras');
        var elExc = el('ctx-porcentaje-excedido');
        if (elFact) {
            elFact.textContent = fmtMoney(ctx.totalFacturacionAnita);
        }
        if (elRec) {
            elRec.textContent = fmtMoney(ctx.totalRecodificable);
        }
        if (elMax) {
            elMax.textContent = ctx.porcentajeMaximo > 0.0001
                ? (ctx.porcentajeMaximo + '%')
                : '—';
        }
        if (elObj) {
            elObj.textContent = pct > 0.0001 ? fmtMoney(val.objetivo) : '—';
        }
        if (elPend) {
            if (pct > 0.0001) {
                elPend.textContent = fmtMoney(val.pendienteTras);
                elPend.classList.toggle('text-danger', val.pendienteTras < 0);
            } else if (data.total_pendiente_qr_mp != null) {
                elPend.textContent = fmtMoney(data.total_pendiente_qr_mp);
                elPend.classList.remove('text-danger');
            } else {
                elPend.textContent = fmtMoney(ctx.pendienteQrMpBase || ctx.totalRecodificable);
                elPend.classList.remove('text-danger');
            }
        }
        if (elExc) {
            if (!val.ok) {
                elExc.textContent = val.mensaje || '';
                elExc.classList.remove('d-none');
            } else {
                elExc.textContent = '';
                elExc.classList.add('d-none');
            }
        }
    }

    function habilitarPreviewFactura(habilitar) {
        var btnPreview = el('btn-proceso-preview-asientos');
        var btnComandas = el('btn-proceso-comandas-factura');
        if (btnPreview) {
            btnPreview.disabled = !habilitar;
        }
        if (btnComandas) {
            btnComandas.disabled = !habilitar;
        }
    }

    function aplicarJornadaProcesoEstado(jp) {
        procesoEstado.jornadaProceso = jp || null;
        actualizarBotonEmitirFactura(jp);
        actualizarBotonGrabarAsientos(jp);
        actualizarBotonRevertirProceso(jp);
        renderProcesoResultadoGrabado(jp);
    }

    function formatearFechaIsoLegible(iso) {
        if (!iso) {
            return '';
        }
        var d = new Date(iso);
        if (isNaN(d.getTime())) {
            return String(iso);
        }
        return d.toLocaleString('es-AR');
    }

    function renderProcesoResultadoGrabado(jp) {
        var panel = el('panel-proceso-resultado');
        if (!panel) {
            return;
        }
        var rg = (jp && jp.resultado_grabado) ? jp.resultado_grabado : null;
        var facturas = (rg && rg.facturas) ? rg.facturas : [];
        var asientos = (rg && rg.asientos) ? rg.asientos : [];
        var tieneFacturas = facturas.length > 0;
        var tieneAsientos = asientos.length > 0;

        if (!tieneFacturas && !tieneAsientos) {
            panel.classList.add('d-none');
            return;
        }

        panel.classList.remove('d-none');

        var resumen = el('proceso-resultado-resumen');
        if (resumen) {
            var partes = [];
            if (rg && rg.porcentaje != null) {
                partes.push('Porcentaje aplicado: ' + Number(rg.porcentaje).toLocaleString('es-AR', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 4,
                }) + ' %');
            }
            if (rg && rg.emitido_en) {
                partes.push('Facturas emitidas: ' + formatearFechaIsoLegible(rg.emitido_en));
            }
            if (rg && rg.grabado_en) {
                partes.push('Asientos grabados: ' + formatearFechaIsoLegible(rg.grabado_en));
            }
            resumen.textContent = partes.join(' · ') || 'Registro del proceso de cierre Waitry.';
        }

        var badgeCompletado = el('badge-proceso-cierre-completado');
        if (badgeCompletado) {
            if (jp && jp.proceso_cierre_completado) {
                badgeCompletado.classList.remove('d-none');
            } else {
                badgeCompletado.classList.add('d-none');
            }
        }

        var tbodyFac = el('tbody-proceso-resultado-facturas');
        if (tbodyFac) {
            tbodyFac.innerHTML = '';
            facturas.forEach(function (fac) {
                var tr = document.createElement('tr');
                tr.innerHTML = '<td>' + (fac.lote || '—') + '</td>'
                    + '<td>' + escapeHtml(fac.factura || '—') + '</td>'
                    + '<td class="text-right waitry-cierre-col-monto">' + fmtMoney(fac.total) + '</td>'
                    + '<td class="text-right">' + (fac.cantidad_comandas || '—') + '</td>'
                    + '<td class="text-nowrap">' + htmlBotonVerFactura(fac.venta_id) + '</td>';
                tbodyFac.appendChild(tr);
            });
        }

        var tfootTotal = el('tfoot-proceso-resultado-facturas-total');
        var totalFacEl = el('proceso-resultado-total-factura');
        if (tfootTotal && totalFacEl && rg && rg.total_factura > 0) {
            tfootTotal.classList.remove('d-none');
            totalFacEl.textContent = fmtMoney(rg.total_factura);
        } else if (tfootTotal) {
            tfootTotal.classList.add('d-none');
        }

        var wrapAjuste = el('proceso-resultado-ajuste-wrap');
        var txtAjuste = el('proceso-resultado-ajuste');
        var ajuste = rg ? rg.ajuste_insumos : null;
        if (wrapAjuste && txtAjuste) {
            if (ajuste && (ajuste.movimientostock_id || ajuste.total)) {
                wrapAjuste.classList.remove('d-none');
                var movId = ajuste.movimientostock_id || 0;
                var urlMov = urlVerMovimientoStock(movId);
                var linkMov = urlMov
                    ? (' <a href="' + escapeHtml(urlMov) + '" target="_blank" rel="noopener">Movimiento #' + movId + '</a>')
                    : (movId ? (' Movimiento #' + movId) : '');
                txtAjuste.innerHTML = 'Total ajuste: ' + fmtMoney(ajuste.total || rg.total_ajuste || 0) + linkMov;
            } else if (rg && rg.total_ajuste > 0) {
                wrapAjuste.classList.remove('d-none');
                txtAjuste.textContent = 'Total ajuste: ' + fmtMoney(rg.total_ajuste);
            } else {
                wrapAjuste.classList.add('d-none');
                txtAjuste.textContent = '';
            }
        }

        var tbodyAsi = el('tbody-proceso-resultado-asientos');
        var pendientes = el('proceso-resultado-asientos-pendientes');
        if (tbodyAsi) {
            tbodyAsi.innerHTML = '';
            if (tieneAsientos) {
                asientos.forEach(function (asi) {
                    var tr = document.createElement('tr');
                    var titulo = (asi.codigo ? asi.codigo + ' — ' : '') + (asi.titulo || '');
                    tr.innerHTML = '<td>' + escapeHtml(titulo) + '</td>'
                        + '<td>' + escapeHtml(asi.numeroasiento || '—') + '</td>'
                        + '<td class="text-right waitry-cierre-col-monto">' + fmtMoney(asi.resumen_debe) + '</td>'
                        + '<td class="text-right waitry-cierre-col-monto">' + fmtMoney(asi.resumen_haber) + '</td>'
                        + '<td class="text-nowrap">' + htmlBotonVerAsiento(asi.asiento_id) + '</td>';
                    tbodyAsi.appendChild(tr);
                });
            } else {
                var trVac = document.createElement('tr');
                trVac.innerHTML = '<td colspan="5" class="text-muted small">Sin asientos grabados aún.</td>';
                tbodyAsi.appendChild(trVac);
            }
        }
        if (pendientes) {
            if (tieneFacturas && !tieneAsientos) {
                pendientes.classList.remove('d-none');
            } else {
                pendientes.classList.add('d-none');
            }
        }
    }

    function actualizarBotonGrabarAsientos(jp) {
        var btn = el('btn-proceso-grabar-asientos');
        if (!btn) {
            return;
        }
        if (!jp) {
            btn.disabled = true;
            btn.title = 'Analice el tramo antes de grabar asientos.';
            return;
        }
        if (jp.asientos_grabados && !jp.rendicion_anita_pendiente) {
            btn.disabled = true;
            btn.title = 'Los asientos del proceso ya fueron grabados para esta jornada.';
            return;
        }
        var puede = !!jp.puede_grabar_asientos_proceso;
        btn.disabled = !puede;
        btn.title = puede
            ? (jp.rendicion_anita_pendiente
                ? 'Completar la rendición Anita pendiente (los asientos ya están grabados).'
                : 'Grabar los asientos del preview en contabilidad (ERP + ctamov Anita).')
            : (jp.motivo_asientos_bloqueados || 'No puede grabar asientos en este momento.');
    }

    function actualizarBotonRevertirProceso(jp) {
        var btn = el('btn-proceso-revertir');
        if (!btn) {
            return;
        }
        var puede = !!(jp && jp.puede_revertir_proceso);
        if (puede) {
            btn.classList.remove('d-none');
            btn.disabled = false;
            btn.title = 'Elimina facturas CF, asientos contables y ajuste de insumos para rehacer el proceso.';
        } else {
            btn.classList.add('d-none');
            btn.disabled = true;
        }
    }

    function mostrarOverlayRevirtiendo(activo) {
        var overlay = el('proceso-revirtiendo-overlay');
        if (!overlay) {
            return;
        }
        if (activo) {
            overlay.classList.remove('d-none');
            overlay.style.display = 'flex';
            overlay.setAttribute('aria-hidden', 'false');
            document.body.classList.add('waitry-proceso-en-curso');
        } else {
            overlay.classList.add('d-none');
            overlay.style.display = '';
            overlay.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('waitry-proceso-en-curso');
        }
    }

    function poblarModalRevertirProceso(jp) {
        var rg = (jp && jp.resultado_grabado) ? jp.resultado_grabado : null;
        var facturas = (rg && rg.facturas) ? rg.facturas : [];
        var asientos = (rg && rg.asientos) ? rg.asientos : [];
        var ajuste = rg ? rg.ajuste_insumos : null;

        var wrapFac = el('revertir-proceso-resumen-facturas-wrap');
        var tbodyFac = el('tbody-revertir-proceso-facturas');
        if (wrapFac && tbodyFac) {
            if (facturas.length > 0) {
                wrapFac.classList.remove('d-none');
                tbodyFac.innerHTML = '';
                facturas.forEach(function (fac) {
                    var tr = document.createElement('tr');
                    tr.innerHTML = '<td>' + (fac.lote || '—') + '</td>'
                        + '<td>' + escapeHtml(fac.factura || '—') + '</td>'
                        + '<td class="text-right waitry-cierre-col-monto">' + fmtMoney(fac.total) + '</td>';
                    tbodyFac.appendChild(tr);
                });
            } else {
                wrapFac.classList.add('d-none');
                tbodyFac.innerHTML = '';
            }
        }

        var wrapAsi = el('revertir-proceso-resumen-asientos-wrap');
        var tbodyAsi = el('tbody-revertir-proceso-asientos');
        if (wrapAsi && tbodyAsi) {
            if (asientos.length > 0) {
                wrapAsi.classList.remove('d-none');
                tbodyAsi.innerHTML = '';
                asientos.forEach(function (asi) {
                    var tr = document.createElement('tr');
                    var titulo = (asi.codigo ? asi.codigo + ' — ' : '') + (asi.titulo || '');
                    tr.innerHTML = '<td>' + escapeHtml(titulo) + '</td>'
                        + '<td>' + escapeHtml(asi.numeroasiento || '—') + '</td>';
                    tbodyAsi.appendChild(tr);
                });
            } else {
                wrapAsi.classList.add('d-none');
                tbodyAsi.innerHTML = '';
            }
        }

        var wrapAjuste = el('revertir-proceso-ajuste-wrap');
        var txtAjuste = el('revertir-proceso-ajuste-texto');
        if (wrapAjuste && txtAjuste) {
            if (ajuste && (ajuste.movimientostock_id || ajuste.total)) {
                wrapAjuste.classList.remove('d-none');
                var movId = ajuste.movimientostock_id || 0;
                txtAjuste.textContent = 'Se eliminará el movimiento de stock #' + movId
                    + ' (total ' + fmtMoney(ajuste.total || rg.total_ajuste || 0) + ').';
            } else if (rg && rg.total_ajuste > 0) {
                wrapAjuste.classList.remove('d-none');
                txtAjuste.textContent = 'Se eliminará el ajuste de insumos (total ' + fmtMoney(rg.total_ajuste) + ').';
            } else {
                wrapAjuste.classList.add('d-none');
                txtAjuste.textContent = '';
            }
        }
    }

    function abrirModalRevertirProceso() {
        var jp = procesoEstado.jornadaProceso;
        if (!jp || !jp.puede_revertir_proceso) {
            alert('No hay proceso emitido o grabado para revertir en esta jornada.');
            return;
        }
        poblarModalRevertirProceso(jp);
        if (typeof window.jQuery !== 'undefined') {
            window.jQuery('#modal-revertir-proceso-cierre').modal('show');
        }
    }

    function confirmarRevertirProceso() {
        var params = empresaYFechaDesdeFormulario();
        if (params.empresa_id <= 0 || !params.fecha_jornada) {
            alert('Seleccione empresa y fecha.');
            return;
        }
        var btn = el('btn-confirmar-revertir-proceso');
        if (btn) {
            btn.disabled = true;
        }
        if (typeof window.jQuery !== 'undefined') {
            window.jQuery('#modal-revertir-proceso-cierre').modal('hide');
        }
        mostrarOverlayRevirtiendo(true);
        apiPost(CFG.urlRevertirProceso || '', {
            empresa_id: params.empresa_id,
            fecha_jornada: params.fecha_jornada,
        }).then(function (data) {
            if (data && data.ok) {
                alert(data.mensaje || 'Proceso revertido correctamente.');
                analizar();
            }
        }).catch(function (e) {
            alert(e.message);
        }).finally(function () {
            mostrarOverlayRevirtiendo(false);
            if (btn) {
                btn.disabled = false;
            }
            actualizarBotonRevertirProceso(procesoEstado.jornadaProceso);
        });
    }

    function actualizarBotonEmitirFactura(jp) {
        var btnEmitir = el('btn-proceso-emitir-factura');
        if (!btnEmitir) {
            return;
        }
        if (!jp) {
            btnEmitir.disabled = true;
            btnEmitir.title = 'Analice el tramo antes de emitir.';
            return;
        }
        if (jp.factura_proceso_emitida) {
            btnEmitir.disabled = true;
            btnEmitir.title = 'La factura del proceso ya fue emitida para esta jornada.';
            return;
        }
        var puede = !!jp.puede_facturar_proceso;
        btnEmitir.disabled = !puede;
        if (puede) {
            btnEmitir.title = 'Emitir facturas CF del proceso en lotes (jornada cerrada, tramo definitivo).';
        } else {
            btnEmitir.title = jp.motivo_factura_bloqueada || 'No puede emitir la factura del proceso en este momento.';
        }
    }

    function emitirFacturaProceso() {
        var jp = procesoEstado.jornadaProceso;
        if (jp && jp.factura_proceso_emitida) {
            alert('La factura del proceso ya fue emitida para esta jornada. Revise el panel de resultado.');
            return;
        }
        if (jp && jp.factura_bloqueada) {
            alert(jp.motivo_factura_bloqueada || 'No puede emitir la factura del proceso en este momento.');
            return;
        }
        var params = empresaYFechaDesdeFormulario();
        if (params.empresa_id <= 0 || !params.fecha_jornada) {
            alert('Seleccione empresa y fecha.');
            return;
        }
        abrirModalEmitirFacturaProceso(params);
    }

    function abrirModalEmitirFacturaProceso(params) {
        var modal = el('modal-emitir-factura-proceso');
        var selPv = el('emitir-proceso-puntoventa');
        var inpFecha = el('emitir-proceso-fecha-factura');
        var loading = el('emitir-proceso-loading');
        var errBox = el('emitir-proceso-error');
        var lotesTabla = el('emitir-proceso-lotes-tabla');
        var lotesBody = el('emitir-proceso-lotes-body');
        var lotesResumen = el('emitir-proceso-lotes-resumen');
        if (!modal || !selPv || !inpFecha) {
            alert('No se encontró el modal de emisión de factura.');
            return;
        }
        if (errBox) {
            errBox.classList.add('d-none');
            errBox.textContent = '';
        }
        if (lotesBody) {
            lotesBody.innerHTML = '';
        }
        if (lotesTabla) {
            lotesTabla.classList.add('d-none');
        }
        if (lotesResumen) {
            lotesResumen.classList.add('d-none');
            lotesResumen.textContent = '';
        }
        procesoEstado.previewLotesEmision = null;
        selPv.innerHTML = '';
        inpFecha.value = params.fecha_jornada;
        if (loading) {
            loading.classList.remove('d-none');
        }
        if (typeof window.jQuery !== 'undefined') {
            window.jQuery('#modal-emitir-factura-proceso').modal('show');
        }
        var urlOpciones = (CFG.urlOpcionesEmitir || '')
            + '?empresa_id=' + params.empresa_id
            + '&fecha_jornada=' + encodeURIComponent(params.fecha_jornada);
        var urlLotes = (CFG.urlPreviewLotesFactura || '')
            + '?empresa_id=' + params.empresa_id
            + '&fecha_jornada=' + encodeURIComponent(params.fecha_jornada)
            + '&porcentaje=' + encodeURIComponent(String(porcentajeActual()));
        Promise.all([apiGet(urlOpciones), apiGet(urlLotes)]).then(function (results) {
            var data = results[0];
            var lotesData = results[1];
            if (!data || !data.ok) {
                throw new Error((data && data.error) || 'No se pudieron cargar las opciones de emisión.');
            }
            var pvs = data.puntoventas || [];
            if (pvs.length === 0) {
                throw new Error('No hay puntos de venta electrónicos para esta empresa.');
            }
            var defaultId = (data.puntoventa_default && data.puntoventa_default.id) || 0;
            pvs.forEach(function (pv) {
                var opt = document.createElement('option');
                opt.value = pv.id;
                opt.dataset.modofacturacion = pv.modofacturacion || '';
                var modo = pv.modofacturacion ? (' · ' + pv.modofacturacion) : '';
                opt.textContent = (pv.codigo || '') + ' — ' + (pv.nombre || '') + modo;
                if (defaultId > 0 && pv.id === defaultId) {
                    opt.selected = true;
                }
                selPv.appendChild(opt);
            });
            if (defaultId <= 0 && selPv.options.length > 0) {
                selPv.selectedIndex = 0;
            }
            inpFecha.value = data.fecha_factura_default || params.fecha_jornada;

            if (lotesData && lotesData.ok) {
                renderPreviewLotesFactura(lotesData);
            } else if (lotesData && lotesData.error) {
                throw new Error(lotesData.error);
            }
        }).catch(function (e) {
            if (errBox) {
                errBox.textContent = e.message;
                errBox.classList.remove('d-none');
            } else {
                alert(e.message);
            }
        }).finally(function () {
            if (loading) {
                loading.classList.add('d-none');
            }
        });
    }

    function renderPreviewLotesFactura(data) {
        var lotesTabla = el('emitir-proceso-lotes-tabla');
        var lotesBody = el('emitir-proceso-lotes-body');
        var lotesResumen = el('emitir-proceso-lotes-resumen');
        if (!lotesBody || !lotesTabla || !lotesResumen) {
            return;
        }
        var lotes = data.lotes || [];
        var fmt = function (n) {
            return Number(n || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        };
        var cantLotes = data.cantidad_lotes || lotes.length;
        var cantFactura = data.cantidad_comandas_factura || 0;
        var cantAjuste = data.cantidad_comandas_ajuste || 0;
        lotesResumen.textContent = cantLotes + ' lote(s) · '
            + cantFactura + ' comanda(s) a facturar ($ ' + fmt(data.total_factura) + ') · '
            + cantAjuste + ' comanda(s) solo ajuste ($ ' + fmt(data.total_ajuste) + ') · '
            + 'tope CF $ ' + fmt(data.tope_cf);
        lotesResumen.classList.remove('d-none');

        lotes.forEach(function (lote) {
            var tr = document.createElement('tr');
            var ids = (lote.waitry_order_ids || []).join(', ');
            tr.innerHTML = '<td>' + (lote.numero || '') + '</td>'
                + '<td class="text-right">' + (lote.cantidad_comandas || 0) + '</td>'
                + '<td class="text-right waitry-cierre-col-monto">' + fmtMoney(lote.total) + '</td>'
                + '<td class="small waitry-cierre-col-ids" title="' + escapeHtml(ids) + '">' + escapeHtml(ids) + '</td>';
            lotesBody.appendChild(tr);
        });

        if (lotes.length > 0) {
            lotesTabla.classList.remove('d-none');
        }
        procesoEstado.previewLotesEmision = data;
    }

    function confirmarEmitirFacturaProceso() {
        var jp = procesoEstado.jornadaProceso;
        if (jp && jp.factura_proceso_emitida) {
            alert('La factura del proceso ya fue emitida para esta jornada.');
            return;
        }
        if (jp && jp.factura_bloqueada) {
            alert(jp.motivo_factura_bloqueada || 'No puede emitir la factura del proceso en este momento.');
            return;
        }
        var params = empresaYFechaDesdeFormulario();
        if (params.empresa_id <= 0 || !params.fecha_jornada) {
            alert('Seleccione empresa y fecha.');
            return;
        }
        var selPv = el('emitir-proceso-puntoventa');
        var inpFecha = el('emitir-proceso-fecha-factura');
        var puntoventaId = selPv ? parseInt(selPv.value, 10) : 0;
        var fechaFactura = inpFecha ? inpFecha.value : params.fecha_jornada;
        if (!puntoventaId || puntoventaId <= 0) {
            alert('Seleccione un punto de venta.');
            return;
        }
        if (!fechaFactura) {
            alert('Indique la fecha de la factura.');
            return;
        }
        var msgConfirm = '¿Confirma emitir las facturas CF del proceso según los lotes mostrados?';
        if (jp && jp.recuperacion_emision_archivada) {
            msgConfirm += '\n\nSe recalcularán comandas y lotes con el porcentaje actual y se usará '
                + 'la numeración vigente del punto de venta (no la del intento anterior revertido).';
        }
        if (!window.confirm(msgConfirm)) {
            return;
        }
        var btnConfirmar = el('btn-confirmar-emitir-factura-proceso');
        var btnEmitir = el('btn-proceso-emitir-factura');
        if (btnConfirmar) {
            btnConfirmar.disabled = true;
        }
        if (btnEmitir) {
            btnEmitir.disabled = true;
        }
        var previewLotes = procesoEstado.previewLotesEmision || {};
        iniciarRotacionMensajesEmisionFacturas(mensajesProcesoEmisionCierre({
            cantLotes: previewLotes.cantidad_lotes || (previewLotes.lotes || []).length,
            modofacturacion: modofacturacionPuntoventaSeleccionado(),
        }));
        apiPost(CFG.urlEmitirFactura || '', {
            empresa_id: params.empresa_id,
            fecha_jornada: params.fecha_jornada,
            porcentaje: porcentajeActual(),
            puntoventa_id: puntoventaId,
            fecha_factura: fechaFactura,
        }).then(function (data) {
            if (data && data.ok) {
                if (typeof window.jQuery !== 'undefined') {
                    window.jQuery('#modal-emitir-factura-proceso').modal('hide');
                }
                var msg = data.mensaje || 'Facturas del proceso emitidas correctamente.';
                if (data.ajuste_insumos && data.ajuste_insumos.movimientostock_id) {
                    msg += '\nAjuste de insumos: movimiento #' + data.ajuste_insumos.movimientostock_id + '.';
                }
                alert(msg);
                var pdfUrls = data.pdf_urls || (data.pdf_url ? [data.pdf_url] : []);
                pdfUrls.forEach(function (u) {
                    if (u) {
                        window.open(u, '_blank');
                    }
                });
                analizar();
            }
        }).catch(function (e) {
            alert(e.message);
        }).finally(function () {
            mostrarOverlayEmitiendoFacturas(false);
            if (btnConfirmar) {
                btnConfirmar.disabled = false;
            }
            actualizarBotonEmitirFactura(procesoEstado.jornadaProceso);
        });
    }

    function grabarAsientosProceso() {
        var jp = procesoEstado.jornadaProceso;
        if (jp && !jp.puede_grabar_asientos_proceso) {
            alert(jp.motivo_asientos_bloqueados || 'No puede grabar los asientos del proceso en este momento.');
            return;
        }
        var params = empresaYFechaDesdeFormulario();
        if (params.empresa_id <= 0 || !params.fecha_jornada) {
            alert('Seleccione empresa y fecha.');
            return;
        }
        if (!window.confirm(
            '¿Confirma grabar los asientos contables del proceso de cierre Waitry '
            + '(preview validado) en ERP y Anita ctamov?'
        )) {
            return;
        }
        var btn = el('btn-proceso-grabar-asientos');
        if (btn) {
            btn.disabled = true;
        }
        iniciarRotacionMensajesGrabacionAsientos(mensajesProcesoGrabacionAsientos());
        apiPost(CFG.urlGrabarAsientos || '', {
            empresa_id: params.empresa_id,
            fecha_jornada: params.fecha_jornada,
            porcentaje: porcentajeActual(),
            fecha_asiento: params.fecha_jornada,
        }).then(function (data) {
            if (data && data.ok) {
                alert(data.mensaje || 'Asientos grabados correctamente.');
                analizar();
            }
        }).catch(function (e) {
            alert(e.message);
        }).finally(function () {
            mostrarOverlayGrabandoAsientos(false);
            actualizarBotonGrabarAsientos(procesoEstado.jornadaProceso);
        });
    }

    function urlPreviewFactura(params, pagina, porPagina, comandasAlcance) {
        var url = (CFG.urlPreviewFactura || '')
            + '?empresa_id=' + params.empresa_id
            + '&fecha_jornada=' + encodeURIComponent(params.fecha_jornada)
            + '&porcentaje=' + porcentajeActual()
            + '&pagina=' + (pagina || 1)
            + '&por_pagina=' + (porPagina || 500);
        if (comandasAlcance && comandasAlcance !== 'factura_proceso') {
            url += '&comandas_alcance=' + encodeURIComponent(comandasAlcance);
        }
        return url;
    }

    function tituloModalComandas(alcance) {
        return TITULOS_COMANDAS_ALCANCE[alcance || 'factura_proceso']
            || TITULOS_COMANDAS_ALCANCE.factura_proceso;
    }

    function etiquetaMedioPlanificado(it) {
        var plan = it.medios_pago_planificados;
        if (Array.isArray(plan) && plan.length > 0) {
            return plan.map(function (p) {
                return (p.clave || '') + ' ' + fmtMoney(p.monto);
            }).join(' · ');
        }
        if (it.medio_pago_planificado) {
            return String(it.medio_pago_planificado);
        }
        return '—';
    }

    function badgeCuadre(ok, neutro) {
        if (neutro) {
            return '<span class="badge badge-secondary">Ref.</span>';
        }
        return ok
            ? '<span class="badge badge-success">OK</span>'
            : '<span class="badge badge-danger">Rev.</span>';
    }

    function renderCuadreAsientos(cuadre) {
        var wrap = el('preview-cuadre-asientos-wrap');
        var tbody = el('tbody-preview-cuadre-asientos');
        var tbodyVal = el('tbody-preview-cuadre-validaciones');
        var badge = el('preview-cuadre-badge');
        if (!wrap || !tbody || !tbodyVal) {
            return;
        }
        tbody.innerHTML = '';
        tbodyVal.innerHTML = '';
        var filas = cuadre.filas || [];
        if (filas.length === 0) {
            wrap.classList.add('d-none');
            return;
        }
        wrap.classList.remove('d-none');
        if (badge) {
            if (cuadre.cuadre_global_ok) {
                badge.className = 'badge badge-success ml-1';
                badge.textContent = 'Cuadre OK';
            } else {
                badge.className = 'badge badge-warning ml-1';
                badge.textContent = 'Revisar diferencias';
            }
        }
        filas.forEach(function (f) {
            var tr = document.createElement('tr');
            var dif = f.diferencia != null ? fmtMoney(f.diferencia) : '—';
            var refTotal = f.referencia_total != null ? fmtMoney(f.referencia_total) : '—';
            tr.innerHTML = '<td class="small">' + escapeHtml(String(f.asiento_numero || '') + ' — ' + (f.asiento_titulo || '')) + '</td>'
                + '<td class="text-right waitry-asiento-col-monto">' + fmtMoney(f.total_asiento) + '</td>'
                + '<td class="text-right waitry-asiento-col-monto">' + fmtMoney(f.debe) + '</td>'
                + '<td class="text-right waitry-asiento-col-monto">' + fmtMoney(f.haber) + '</td>'
                + '<td class="text-center">' + badgeCuadre(!!f.debe_haber_cuadra, false) + '</td>'
                + '<td class="small">' + escapeHtml(f.referencia_etiqueta || '—') + '</td>'
                + '<td class="text-right waitry-asiento-col-monto">' + refTotal + '</td>'
                + '<td class="text-right waitry-asiento-col-monto">' + dif + '</td>'
                + '<td class="text-center">' + (f.referencia_etiqueta ? badgeCuadre(!!f.referencia_cuadra, false) : '—') + '</td>';
            tbody.appendChild(tr);
        });
        (cuadre.validaciones || []).forEach(function (v) {
            var tr = document.createElement('tr');
            var neutro = v.cuadra === null;
            var nota = v.nota ? (' <span class="text-muted">(' + escapeHtml(v.nota) + ')</span>') : '';
            tr.innerHTML = '<td class="small">' + escapeHtml(v.etiqueta || '') + nota + '</td>'
                + '<td class="text-right waitry-asiento-col-monto">' + fmtMoney(v.monto_asientos) + '</td>'
                + '<td class="text-right waitry-asiento-col-monto">' + fmtMoney(v.monto_referencia) + '</td>'
                + '<td class="text-right waitry-asiento-col-monto">' + fmtMoney(v.diferencia) + '</td>'
                + '<td class="text-center">' + badgeCuadre(!!v.cuadra, neutro) + '</td>';
            tbodyVal.appendChild(tr);
        });
    }

    function renderPreviewFacturaModal(data) {
        comandasFacturaState.ultimoPreview = data;
        var fp = data.factura_proceso || {};
        var ap = data.asientos_proceso || {};
        var pv = data.puntoventa || {};
        var resumen = el('preview-factura-resumen');
        if (resumen) {
            var txt = 'Asiento 1 (QR no facturado): ' + (fp.cantidad_comandas || 0) + ' comanda(s) · '
                + fmtMoney(fp.total || 0);
            txt += ' · Porcentaje: ' + (data.porcentaje != null ? data.porcentaje : porcentajeActual()) + '%';
            if (pv.codigo) {
                txt += ' · PV: ' + pv.codigo + (pv.nombre ? ' (' + pv.nombre + ')' : '');
            }
            resumen.textContent = txt;
        }
        var advBox = el('preview-factura-advertencias');
        if (advBox) {
            advBox.innerHTML = '';
            var advertencias = (ap.advertencias || []).concat(fp.advertencias || []);
            advertencias.forEach(function (a) {
                var d = document.createElement('div');
                d.className = 'alert alert-warning py-1 mb-1 small';
                d.textContent = a;
                advBox.appendChild(d);
            });
            if ((ap.faltantes || []).length === 0 && advertencias.length === 0) {
                var ok = document.createElement('div');
                ok.className = 'alert alert-success py-1 mb-1 small';
                ok.textContent = 'Todas las cuentas requeridas están configuradas y resueltas.';
                advBox.appendChild(ok);
            }
        }

        var wrapCuentas = el('preview-cuentas-requeridas-wrap');
        var tbodyCuentas = el('tbody-preview-cuentas-requeridas');
        if (wrapCuentas && tbodyCuentas) {
            tbodyCuentas.innerHTML = '';
            var cuentasReq = ap.cuentas_requeridas || [];
            if (cuentasReq.length > 0) {
                wrapCuentas.classList.remove('d-none');
                cuentasReq.forEach(function (c) {
                    var tr = document.createElement('tr');
                    var estado = c.ok
                        ? '<span class="badge badge-success">OK</span>'
                        : '<span class="badge badge-danger">Falta</span>';
                    var codigo = c.cuenta_codigo != null && String(c.cuenta_codigo).trim() !== ''
                        ? String(c.cuenta_codigo).trim()
                        : (c.cuenta_id ? ('#' + c.cuenta_id) : '—');
                    var nombre = c.cuenta_nombre != null ? String(c.cuenta_nombre).trim() : '';
                    tr.innerHTML = '<td class="small">' + escapeHtml(c.tipo || '') + '</td>'
                        + '<td class="small">' + escapeHtml(c.concepto || '') + '</td>'
                        + '<td class="small">' + escapeHtml(codigo) + '</td>'
                        + '<td class="small">' + escapeHtml(nombre) + '</td>'
                        + '<td>' + estado + '</td>';
                    tbodyCuentas.appendChild(tr);
                });
            } else {
                wrapCuentas.classList.add('d-none');
            }
        }

        el('preview-factura-debe').textContent = fmtMoney(ap.resumen_debe != null ? ap.resumen_debe : fp.resumen_debe);
        el('preview-factura-haber').textContent = fmtMoney(ap.resumen_haber != null ? ap.resumen_haber : fp.resumen_haber);

        renderCuadreAsientos(ap.cuadre || {});

        var acordeon = el('preview-asientos-acordeon');
        if (!acordeon) {
            return;
        }
        acordeon.innerHTML = '';
        var asientos = ap.asientos || [];
        if (asientos.length === 0) {
            acordeon.innerHTML = '<p class="text-muted small mb-0">Sin asientos para el porcentaje y tramo actuales.</p>';
            return;
        }
        asientos.forEach(function (asiento, idx) {
            var cardId = 'preview-asiento-' + idx;
            var meta = [];
            if (asiento.cantidad_comandas) {
                if (asiento.comandas_alcance === 'efectivo_no_facturado') {
                    meta.push(asiento.cantidad_comandas + ' comanda(s) con efectivo no facturado');
                } else {
                    meta.push(asiento.cantidad_comandas + ' comanda(s)');
                }
            }
            if (asiento.cantidad_facturas) {
                meta.push(asiento.cantidad_facturas + ' factura(s)');
            }
            if (asiento.cantidad_invitaciones) {
                meta.push(asiento.cantidad_invitaciones + ' invitación(es) $0,01');
            }
            if (asiento.facturas_con_impuesto_interno) {
                meta.push(asiento.facturas_con_impuesto_interno + ' con imp. interno');
            }
            if (asiento.total) {
                meta.push('Total ' + fmtMoney(asiento.total));
            }
            var card = document.createElement('div');
            card.className = 'card mb-2';
            card.innerHTML = '<div class="card-header py-2" id="heading-' + cardId + '">'
                + '<button class="btn btn-link btn-sm p-0 text-left' + (idx === 0 ? '' : ' collapsed') + '" type="button" '
                + 'data-toggle="collapse" data-target="#' + cardId + '" aria-expanded="' + (idx === 0 ? 'true' : 'false') + '">'
                + '<strong>' + escapeHtml(asiento.titulo || ('Asiento ' + (asiento.numero || (idx + 1)))) + '</strong>'
                + (meta.length ? ' <span class="text-muted small">(' + meta.join(' · ') + ')</span>' : '')
                + ' — Debe ' + fmtMoney(asiento.resumen_debe) + ' / Haber ' + fmtMoney(asiento.resumen_haber)
                + '</button></div>'
                + '<div id="' + cardId + '" class="collapse' + (idx === 0 ? ' show' : '') + '" data-parent="#preview-asientos-acordeon">'
                + '<div class="card-body p-2"><div class="table-responsive">'
                + '<table class="table table-sm table-bordered mb-0"><thead class="thead-light"><tr>'
                + '<th>Código cuenta</th><th>Nombre cuenta</th><th>Concepto</th>'
                + '<th class="text-right waitry-asiento-col-monto">Debe</th>'
                + '<th class="text-right waitry-asiento-col-monto">Haber</th>'
                + '</tr></thead><tbody></tbody></table></div>'
                + (asiento.comandas_alcance && asiento.cantidad_comandas
                    ? ('<div class="mt-2">'
                        + '<button type="button" class="btn btn-outline-secondary btn-sm btn-preview-asiento-comandas" '
                        + 'data-comandas-alcance="' + escapeHtml(asiento.comandas_alcance) + '">'
                        + '<i class="fa fa-list"></i> Ver comandas'
                        + '</button></div>')
                    : '')
                + '</div></div>';
            var tbody = card.querySelector('tbody');
            (asiento.lineas || []).forEach(function (ln) {
                if (ln.tipo === 'info') {
                    return;
                }
                var codigoCuenta = ln.cuenta_codigo != null && String(ln.cuenta_codigo).trim() !== ''
                    ? String(ln.cuenta_codigo).trim()
                    : (ln.cuenta_label && String(ln.cuenta_label).trim() !== '' && ln.cuenta_label !== '—'
                        ? String(ln.cuenta_label).split(' — ')[0]
                        : (ln.cuenta_id ? ('#' + ln.cuenta_id) : '—'));
                var nombreCuenta = ln.cuenta_nombre != null && String(ln.cuenta_nombre).trim() !== ''
                    ? String(ln.cuenta_nombre).trim()
                    : (ln.cuenta_label && String(ln.cuenta_label).indexOf(' — ') !== -1
                        ? String(ln.cuenta_label).split(' — ').slice(1).join(' — ')
                        : '');
                var tr = document.createElement('tr');
                tr.innerHTML = '<td class="small">' + escapeHtml(codigoCuenta) + '</td>'
                    + '<td class="small">' + escapeHtml(nombreCuenta) + '</td>'
                    + '<td class="small">' + escapeHtml(ln.concepto || '') + '</td>'
                    + '<td class="text-right waitry-asiento-col-monto">' + fmtMoney(ln.debe) + '</td>'
                    + '<td class="text-right waitry-asiento-col-monto">' + fmtMoney(ln.haber) + '</td>';
                tbody.appendChild(tr);
            });
            if (!tbody.children.length) {
                var trEmpty = document.createElement('tr');
                trEmpty.innerHTML = '<td colspan="5" class="text-muted small">Sin líneas.</td>';
                tbody.appendChild(trEmpty);
            }
            acordeon.appendChild(card);
        });
        acordeon.querySelectorAll('.btn-preview-asiento-comandas').forEach(function (btn) {
            btn.addEventListener('click', function () {
                abrirComandasFactura(btn.getAttribute('data-comandas-alcance') || 'factura_proceso');
            });
        });
    }

    function renderComandasFacturaModal(data) {
        var alcance = data.comandas_alcance || (data.comandas || {}).alcance || comandasFacturaState.alcance || 'factura_proceso';
        comandasFacturaState.alcance = alcance;
        var tituloModal = el('modal-comandas-factura-titulo');
        if (tituloModal) {
            tituloModal.textContent = tituloModalComandas(alcance);
        }
        var comandas = data.comandas || {};
        var tbody = el('tbody-comandas-factura');
        if (!tbody) {
            return;
        }
        tbody.innerHTML = '';
        (comandas.items || []).forEach(function (it) {
            var tr = document.createElement('tr');
            tr.innerHTML = '<td>' + (it.waitry_order_id || '—') + '</td>'
                + '<td>' + escapeHtml(it.display_id || '') + '</td>'
                + '<td>' + escapeHtml(it.placed_at_fmt || '—') + '</td>'
                + '<td class="text-right waitry-cierre-col-monto">' + fmtMoney(it.total) + '</td>'
                + '<td>' + escapeHtml(it.waitry_medio_label || '—') + '</td>'
                + '<td class="small">' + escapeHtml(etiquetaMedioAnitaOPlanificado(it)) + '</td>'
                + '<td>' + escapeHtml(it.venta_codigo || '—') + '</td>'
                + '<td class="text-nowrap text-center">' + htmlBotonVerFactura(it.venta_id) + '</td>';
            tbody.appendChild(tr);
        });
        var resumen = el('modal-comandas-factura-resumen');
        if (resumen) {
            var importeLabel = alcance === 'efectivo_no_facturado'
                ? 'Importe efectivo no facturado'
                : 'Importe total';
            resumen.textContent = (comandas.total || 0) + ' comanda(s) · ' + importeLabel + ': '
                + fmtMoney(comandas.total_importe || 0)
                + ' · Porcentaje: ' + (data.porcentaje != null ? data.porcentaje : porcentajeActual()) + '%';
        }
        comandasFacturaState.pagina = 1;
        var tituloDt = tituloModalComandas(alcance) + ' — ' + (comandas.total || 0) + ' registro(s)';
        initDataTableDetalle('#tabla-comandas-factura', tituloDt, 3);
    }

    function cargarPreviewFactura(abrirModal, paginaComandas) {
        var params = empresaYFechaDesdeFormulario();
        if (params.empresa_id <= 0 || !params.fecha_jornada) {
            alert('Seleccione empresa y fecha.');
            return Promise.reject(new Error('Parámetros incompletos'));
        }
        mostrar('preview-factura-loading', true);
        if (abrirModal && typeof window.jQuery !== 'undefined') {
            window.jQuery('#modal-preview-asientos-factura').modal('show');
        }
        return apiGet(urlPreviewFactura(params, paginaComandas || 1)).then(function (data) {
            mostrar('preview-factura-loading', false);
            sincronizarJornadaProcesoDesdeRespuesta(data);
            renderPreviewFacturaModal(data);
            if (paginaComandas) {
                renderComandasFacturaModal(data);
            }
            return data;
        }).catch(function (e) {
            mostrar('preview-factura-loading', false);
            alert(e.message);
            throw e;
        });
    }

    function abrirComandasFactura(comandasAlcance) {
        var params = empresaYFechaDesdeFormulario();
        if (params.empresa_id <= 0 || !params.fecha_jornada) {
            alert('Seleccione empresa y fecha.');
            return;
        }
        var alcance = comandasAlcance || 'factura_proceso';
        comandasFacturaState.alcance = alcance;
        var tituloModal = el('modal-comandas-factura-titulo');
        if (tituloModal) {
            tituloModal.textContent = tituloModalComandas(alcance);
        }
        mostrar('modal-comandas-factura-loading', true);
        if (typeof window.jQuery !== 'undefined') {
            window.jQuery('#modal-comandas-factura').modal('show');
        }
        fetchPreviewFacturaTodasComandas(params, alcance).then(function (data) {
            mostrar('modal-comandas-factura-loading', false);
            renderComandasFacturaModal(data);
        }).catch(function (e) {
            mostrar('modal-comandas-factura-loading', false);
            alert(e.message);
        });
    }

    function urlCuadroDetalle(fila, medio, pagina, params, porPagina) {
        var tpl = String(CFG.urlCuadroDetalleBase || '');
        var url = tpl.replace('__FILA__', encodeURIComponent(fila)).replace('__MEDIO__', encodeURIComponent(medio));
        return url + '?empresa_id=' + params.empresa_id
            + '&fecha_jornada=' + encodeURIComponent(params.fecha_jornada)
            + '&pagina=' + pagina + '&por_pagina=' + (porPagina || 500)
            + '&porcentaje=' + porcentajeProcesoActual();
    }

    function htmlResumenDetalleCuadro(data) {
        var txt = (data.total_registros || 0) + ' registro(s) · Total celda: '
            + fmtMoney(data.total_importe || 0);
        var partes = data.totales_por_medio_waitry;
        if (partes && partes.length > 1) {
            txt += '<br><span class="text-dark"><strong>Desglose Waitry (QR / MP):</strong> '
                + partes.map(function (d) {
                    return escapeHtml(d.etiqueta || d.clave || '')
                        + ': ' + (d.registros || 0) + ' com. · ' + fmtMoney(d.importe);
                }).join(' · ')
                + '</span>';
        }
        txt += '<br><span class="text-muted">Use buscar, paginar o exportar en la tabla.</span>';

        return txt;
    }

    function renderDetalleCuadroModal(data) {
        var tbody = el('tbody-cuadro-detalle');
        if (!tbody) {
            return;
        }
        tbody.innerHTML = '';
        (data.items || []).forEach(function (it) {
            var tr = document.createElement('tr');
            tr.innerHTML = '<td>' + (it.waitry_order_id || '—') + '</td>'
                + '<td>' + escapeHtml(it.display_id || '') + '</td>'
                + '<td>' + escapeHtml(it.fecha_hora_fmt || it.placed_at_fmt || '—') + '</td>'
                + '<td class="text-right waitry-cierre-col-monto">' + fmtMoney(it.total) + '</td>'
                + '<td>' + escapeHtml(it.waitry_medio_label || it.waitry_tipo_pago || '—') + '</td>'
                + '<td class="small">' + escapeHtml(etiquetaMedioAnitaOPlanificado(it)) + '</td>'
                + '<td>' + escapeHtml(it.venta_codigo || '—') + '</td>'
                + '<td class="text-nowrap text-center">' + htmlBotonVerFactura(it.venta_id) + '</td>';
            tbody.appendChild(tr);
        });
        var resumen = el('modal-cuadro-detalle-resumen');
        if (resumen) {
            resumen.innerHTML = htmlResumenDetalleCuadro(data);
        }
        var tituloDt = (data.etiqueta_fila || cuadroDetalleState.fila) + ' — '
            + (data.etiqueta_medio || cuadroDetalleState.medio);
        initDataTableDetalle('#tabla-cuadro-detalle', tituloDt, 3);
    }

    function abrirDetalleCuadro(fila, medio) {
        var params = empresaYFechaDesdeFormulario();
        if (params.empresa_id <= 0 || !params.fecha_jornada) {
            alert('Seleccione empresa y fecha.');
            return;
        }
        cuadroDetalleState = { fila: fila, medio: medio, pagina: 1 };
        var titulo = el('modal-cuadro-detalle-titulo');
        if (titulo) {
            titulo.textContent = 'Detalle: ' + fila + ' · ' + medio.toUpperCase();
        }
        mostrar('modal-cuadro-detalle-loading', true);
        if (typeof window.jQuery !== 'undefined') {
            window.jQuery('#modal-cuadro-detalle').modal('show');
        } else {
            var modal = el('modal-cuadro-detalle');
            if (modal) {
                modal.classList.add('show');
                modal.style.display = 'block';
            }
        }
        var cacheKey = claveCacheDetalle(fila, medio);
        if (procesoEstado.detalleCache[cacheKey]) {
            mostrar('modal-cuadro-detalle-loading', false);
            renderDetalleCuadroModal(procesoEstado.detalleCache[cacheKey]);
            return;
        }

        fetchTodasLasPaginasItems(function (pagina, porPagina) {
            return apiGet(urlCuadroDetalle(fila, medio, pagina, params, porPagina));
        }).then(function (data) {
            mostrar('modal-cuadro-detalle-loading', false);
            if (titulo && data.etiqueta_fila) {
                titulo.textContent = data.etiqueta_fila + ' — ' + (data.etiqueta_medio || medio);
            }
            procesoEstado.detalleCache[cacheKey] = data;
            renderDetalleCuadroModal(data);
        }).catch(function (e) {
            mostrar('modal-cuadro-detalle-loading', false);
            alert(e.message);
        });
    }

    function initCuadroDetalleModal() {
        var tabla = el('tabla-cuadro-cierre');
        if (tabla && tabla.dataset.boundCuadro !== '1') {
            tabla.dataset.boundCuadro = '1';
            tabla.addEventListener('click', function (ev) {
                var td = ev.target.closest('td.cuadro-celda-detalle');
                if (!td) {
                    return;
                }
                abrirDetalleCuadro(td.getAttribute('data-fila'), td.getAttribute('data-medio'));
            });
            tabla.addEventListener('keydown', function (ev) {
                if (ev.key !== 'Enter' && ev.key !== ' ') {
                    return;
                }
                var td = ev.target.closest('td.cuadro-celda-detalle');
                if (!td) {
                    return;
                }
                ev.preventDefault();
                abrirDetalleCuadro(td.getAttribute('data-fila'), td.getAttribute('data-medio'));
            });
        }
    }

    function renderNotas(notas) {
        var ul = el('lista-notas');
        if (!ul) {
            return;
        }
        ul.innerHTML = '';
        (notas || []).forEach(function (n) {
            var li = document.createElement('li');
            li.textContent = n;
            ul.appendChild(li);
        });
        mostrar('panel-proceso-notas', (notas || []).length > 0);
    }

    function urlGrupoMovimientos(grupo) {
        var base = String(CFG.urlMovimientosBase || '').replace(/\/$/, '');
        return base + '/' + encodeURIComponent(grupo);
    }

    function cargarPaginaGrupo(container, grupo, params) {
        container.innerHTML = '<p class="text-muted small"><i class="fa fa-spinner fa-spin"></i> Cargando…</p>';
        var tablaId = 'tabla-grupo-' + String(grupo).replace(/[^a-z0-9_-]/gi, '_');

        fetchTodasLasPaginasItems(function (pagina, porPagina) {
            var url = urlGrupoMovimientos(grupo)
                + '?empresa_id=' + params.empresa_id
                + '&fecha_jornada=' + encodeURIComponent(params.fecha_jornada)
                + '&pagina=' + pagina + '&por_pagina=' + porPagina;

            return apiGet(url);
        }).then(function (data) {
            container.dataset.cargado = '1';
            var items = data.items || [];
            var esSinWaitry = grupo === 'anita_sin_waitry';
            if (items.length === 0) {
                container.innerHTML = '<p class="text-muted small mb-0">Sin registros en este grupo.</p>';
                return;
            }
            var html = '<div class="waitry-cierre-modal-tabla-wrap"><table class="table table-sm table-striped mb-0 w-100" id="'
                + tablaId + '"><thead><tr>';
            if (esSinWaitry) {
                html += '<th>Fecha/hora</th><th>Factura</th><th>Terminal</th><th>Total</th>'
                    + '<th>Medio cobranza Anita</th><th class="text-nowrap" data-orderable="false">Acciones</th>';
            } else {
                html += '<th>#W</th><th>Ref.</th><th>Fecha/hora</th><th>Total</th><th>Factura</th>'
                    + '<th>Medio Waitry</th><th>Medio Anita</th><th class="text-nowrap" data-orderable="false">Acciones</th>';
            }
            html += '</tr></thead><tbody>';
            items.forEach(function (it) {
                var facturaTxt = it.venta_codigo || '—';
                if (it.es_nota_credito) {
                    facturaTxt += ' (NC)';
                }
                var terminalTxt = it.terminal_descripcion || it.identificador_pc || '—';
                var medioAnita = it.anita_cuentacaja_label || etiquetaMedioAnitaOPlanificado(it) || '—';
                if (esSinWaitry) {
                    html += '<tr>'
                        + '<td>' + escapeHtml(it.fecha_hora_fmt || it.placed_at_fmt || '') + '</td>'
                        + '<td>' + escapeHtml(facturaTxt) + '</td>'
                        + '<td class="small">' + escapeHtml(terminalTxt) + '</td>'
                        + '<td class="text-right waitry-cierre-col-monto">' + fmtMoney(it.total) + '</td>'
                        + '<td class="small">' + escapeHtml(medioAnita) + '</td>'
                        + '<td class="text-nowrap text-center">' + htmlBotonVerFactura(it.venta_id) + '</td></tr>';
                } else {
                    html += '<tr><td>' + (it.waitry_order_id || '') + '</td>'
                        + '<td>' + escapeHtml(it.display_id || '') + '</td>'
                        + '<td>' + escapeHtml(it.fecha_hora_fmt || it.placed_at_fmt || '') + '</td>'
                        + '<td class="text-right waitry-cierre-col-monto">' + fmtMoney(it.total) + '</td>'
                        + '<td>' + escapeHtml(facturaTxt) + '</td>'
                        + '<td>' + escapeHtml(it.waitry_medio_label || '—') + '</td>'
                        + '<td class="small">' + escapeHtml(etiquetaMedioAnitaOPlanificado(it)) + '</td>'
                        + '<td class="text-nowrap text-center">' + htmlBotonVerFactura(it.venta_id) + '</td></tr>';
                }
            });
            html += '</tbody></table></div>';
            html += '<p class="small text-muted mb-0 mt-1">' + items.length + ' registro(s)</p>';
            container.innerHTML = html;
            initDataTableDetalle(
                '#' + tablaId,
                esSinWaitry ? 'Facturas POS sin Waitry — auditoría' : ('Grupo ' + grupo + ' — cierre Waitry'),
                esSinWaitry ? 0 : 3,
            );
        }).catch(function (e) {
            container.innerHTML = '<p class="text-danger small">' + escapeHtml(e.message) + '</p>';
        });
    }

    function renderAnitaSinWaitry(resumen) {
        var panel = el('panel-proceso-anita-sin-waitry');
        var titulo = el('anita-sin-waitry-titulo');
        var resumenEl = el('anita-sin-waitry-resumen');
        var detalleBody = el('anita-sin-waitry-detalle-body');
        var btnDetalle = el('btn-anita-sin-waitry-detalle');
        if (!panel) {
            return;
        }
        resumen = resumen || {};
        var cantidad = parseInt(resumen.cantidad, 10) || 0;
        var total = parseFloat(resumen.total) || 0;
        var cantFacturas = parseInt(resumen.cantidad_facturas, 10) || 0;
        var cantNc = parseInt(resumen.cantidad_notas_credito, 10) || 0;
        if (titulo && resumen.titulo) {
            titulo.textContent = resumen.titulo;
        }
        if (resumenEl) {
            var txt = cantidad + ' emisión(es) · ' + fmtMoney(total);
            if (cantNc > 0) {
                txt += ' (' + cantFacturas + ' fact. / ' + cantNc + ' NC)';
            }
            resumenEl.textContent = txt;
        }
        if (detalleBody) {
            detalleBody.dataset.cargado = '';
            detalleBody.dataset.grupo = resumen.clave || 'anita_sin_waitry';
            detalleBody.innerHTML = '<p class="text-muted small mb-0">Expandir para cargar el listado…</p>';
        }
        if (btnDetalle) {
            btnDetalle.classList.toggle('d-none', cantidad <= 0);
            btnDetalle.classList.add('collapsed');
            btnDetalle.setAttribute('aria-expanded', 'false');
        }
        var collapseDet = el('anita-sin-waitry-detalle');
        if (collapseDet && typeof window.jQuery !== 'undefined') {
            window.jQuery(collapseDet).collapse('hide');
        }
        mostrar('panel-proceso-anita-sin-waitry', true);
    }

    function initAnitaSinWaitryDetalle() {
        var collapseDet = el('anita-sin-waitry-detalle');
        if (!collapseDet || collapseDet.dataset.boundSinWaitry === '1') {
            return;
        }
        collapseDet.dataset.boundSinWaitry = '1';
        if (typeof window.jQuery !== 'undefined') {
            window.jQuery(collapseDet).on('shown.bs.collapse', function () {
                var grupo = el('anita-sin-waitry-detalle-body');
                if (!grupo || grupo.dataset.cargado === '1') {
                    return;
                }
                cargarPaginaGrupo(grupo, grupo.dataset.grupo || 'anita_sin_waitry', empresaYFechaDesdeFormulario());
            });
        }
    }

    function renderGrupos(resumen, params) {
        var cont = el('acordeon-grupos');
        if (!cont) {
            return;
        }
        cont.innerHTML = '';
        (resumen || []).filter(function (g) {
            return (g.cantidad || 0) > 0;
        }).forEach(function (g, idx) {
            var bodyId = 'grp-body-' + idx;
            var card = document.createElement('div');
            card.className = 'card mb-1';
            card.innerHTML =
                '<div class="card-header p-1">'
                + '<button class="btn btn-link btn-sm btn-block text-left collapsed" type="button" '
                + 'data-toggle="collapse" data-target="#' + bodyId + '" aria-expanded="false" '
                + 'aria-controls="' + bodyId + '">'
                + escapeHtml(g.titulo) + ' <span class="badge badge-secondary">' + g.cantidad + '</span>'
                + ' <span class="float-right">' + fmtMoney(g.total) + '</span></button></div>'
                + '<div id="' + bodyId + '" class="collapse" data-parent="#acordeon-grupos">'
                + '<div class="card-body p-2"><div class="grupo-detalle" data-grupo="' + escapeHtml(g.clave) + '">'
                + '<p class="text-muted small mb-0">Expandir…</p></div></div></div>';
            cont.appendChild(card);
        });
    }

    function initGruposAcordeon() {
        var cont = el('acordeon-grupos');
        if (!cont || cont.dataset.boundGrupos === '1') {
            return;
        }
        cont.dataset.boundGrupos = '1';
        if (typeof window.jQuery !== 'undefined') {
            window.jQuery(cont).on('shown.bs.collapse', '.collapse', function () {
                var grupo = this.querySelector('.grupo-detalle');
                if (!grupo || grupo.dataset.cargado === '1') {
                    return;
                }
                cargarPaginaGrupo(grupo, grupo.dataset.grupo, empresaYFechaDesdeFormulario());
            });
        }
    }

    function etiquetaUltimoTicketOrigen(origen) {
        if (origen === 'cierre_gastronomia') {
            return 'Fijado al cerrar jornada en Gastronomía (cierre tótem)';
        }
        if (origen === 'ultimo_leido') {
            return 'Último leído en este análisis (jornada abierta)';
        }
        if (origen === 'ultimo_leido_cierre_jornada') {
            return 'Último leído al cierre de jornada (sin registro tótem)';
        }
        return origen ? String(origen) : '—';
    }

    function sincronizarJornadaProcesoDesdeRespuesta(data) {
        if (data && data.jornada_proceso) {
            aplicarJornadaProcesoEstado(data.jornada_proceso);
        }
    }

    function renderAlertaJornadaProceso(jp) {
        var alertJp = el('alert-jornada-proceso');
        if (!alertJp || !jp) {
            if (alertJp) {
                alertJp.classList.add('d-none');
            }
            return;
        }
        alertJp.className = 'alert py-2 mb-2';
        if (jp.proceso_cierre_completado) {
            alertJp.classList.add('alert-success');
            alertJp.innerHTML = '<i class="fa fa-check-circle"></i> <strong>Proceso de cierre completado.</strong> '
                + 'Facturas emitidas y asientos contables grabados. Use <strong>Revertir proceso</strong> '
                + 'si necesita rehacer emisión o asientos; revise el panel de resultado más abajo.';
        } else if (jp.factura_proceso_emitida && !jp.asientos_grabados) {
            alertJp.classList.add('alert-info');
            alertJp.innerHTML = '<i class="fa fa-file-invoice"></i> <strong>Facturas emitidas.</strong> '
                + 'Puede grabar los asientos contables del preview o revisar las facturas en el panel de resultado.';
        } else if (jp.abierta) {
            alertJp.classList.add('alert-warning');
            alertJp.innerHTML = '<i class="fa fa-clock-o"></i> <strong>Jornada abierta.</strong> '
                + (jp.motivo_factura_bloqueada || 'Auditoría en curso.');
        } else if (jp.snapshot_provisional) {
            alertJp.classList.add('alert-warning');
            alertJp.innerHTML = '<i class="fa fa-exclamation-triangle"></i> <strong>Snapshot provisional.</strong> '
                + (jp.motivo_factura_bloqueada || 'Vuelva a analizar tras cerrar la jornada.');
        } else if (jp.cerrada && jp.snapshot_definitivo) {
            alertJp.classList.add('alert-success');
            alertJp.innerHTML = '<i class="fa fa-check"></i> Jornada cerrada y tramo Waitry definitivo. '
                + 'Listo para facturación del proceso (cuando esté habilitada).';
        } else {
            alertJp.classList.add('alert-info');
            alertJp.innerHTML = '<i class="fa fa-info-circle"></i> ' + (jp.motivo_factura_bloqueada || '');
        }
        alertJp.classList.remove('d-none');
    }

    function aplicarAnalisis(data, params) {
        setError('');
        var meta = data.meta || {};
        aplicarJornadaProcesoEstado(data.jornada_proceso || null);
        renderAlertaJornadaProceso(data.jornada_proceso || null);
        var origenEl = el('meta-ultimo-ticket-origen');
        if (origenEl) {
            origenEl.textContent = etiquetaUltimoTicketOrigen(
                data.tramo_ultimo_ticket_origen || meta.tramo_ultimo_ticket_origen
            );
        }
        el('meta-ventana').textContent = meta.ventana_operativa || '—';
        el('meta-rango').textContent = meta.rango_calendario_waitry || '—';
        var cantidad = meta.cantidad_movimientos;
        el('meta-cantidad').textContent = (cantidad !== undefined && cantidad !== null)
            ? String(cantidad)
            : '—';
        var canceladas = meta.waitry_canceladas || {};
        var wrapCancel = el('meta-canceladas-wrap');
        if (wrapCancel) {
            var cantCancel = parseInt(canceladas.cantidad, 10) || 0;
            if (cantCancel > 0) {
                wrapCancel.classList.remove('d-none');
                el('meta-canceladas').textContent = cantCancel + ' · ' + fmtMoney(canceladas.total || 0);
            } else {
                wrapCancel.classList.add('d-none');
            }
        }
        var anuladasDesc = meta.waitry_anuladas_descuento || {};
        var wrapAnulDesc = el('meta-anuladas-descuento-wrap');
        if (wrapAnulDesc) {
            var cantAnulDesc = parseInt(anuladasDesc.cantidad, 10) || 0;
            if (cantAnulDesc > 0) {
                wrapAnulDesc.classList.remove('d-none');
                el('meta-anuladas-descuento').textContent = cantAnulDesc + ' · ' + fmtMoney(anuladasDesc.total || 0);
            } else {
                wrapAnulDesc.classList.add('d-none');
            }
        }
        el('meta-ids').textContent = meta.rango_etiqueta || '—';
        procesoEstado.snapshotCongelado = !!(data.snapshot_congelado || meta.congelado);
        procesoEstado.detalleCache = {};
        if (data.porcentaje_guardado != null && !isNaN(parseFloat(data.porcentaje_guardado))) {
            procesoEstado.porcentaje = parseFloat(data.porcentaje_guardado);
            var inputPct = el('input-porcentaje');
            if (inputPct) {
                inputPct.value = String(procesoEstado.porcentaje);
            }
        } else {
            procesoEstado.porcentaje = porcentajeActual();
        }
        var alertCong = el('alert-snapshot-congelado');
        var txtCong = el('texto-snapshot-congelado');
        if (alertCong && txtCong) {
            if (procesoEstado.snapshotCongelado) {
                var cuando = data.snapshot_congelado_en || meta.congelado_en || '';
                var reutil = data.snapshot_reutilizado ? ' (reutilizado, sin llamar a Waitry)' : '';
                var prov = meta.snapshot_provisional || (data.jornada_proceso && data.jornada_proceso.snapshot_provisional);
                txtCong.textContent = 'Tramo Waitry congelado para esta jornada'
                    + (prov ? ' (provisional — jornada abierta o análisis previo al cierre)' : ' (definitivo)')
                    + (cuando ? ' el ' + cuando : '')
                    + reutil
                    + '. No se vuelve a Waitry hasta ?refrescar_waitry=1.';
                alertCong.classList.remove('d-none');
            } else {
                alertCong.classList.add('d-none');
            }
        }
        mostrar('panel-proceso-meta', true);
        renderNotas(data.notas);
        renderAnitaSinWaitry(data.anita_sin_waitry || {});
        initAnitaSinWaitryDetalle();
        renderCuadro(data);
        mostrar('panel-proceso-grilla', true);
        renderGrupos(data.grupos_resumen || [], params);
        mostrar('panel-proceso-grupos', true);
        habilitarPreviewFactura(true);
        if (procesoEstado.porcentaje > 0.0001) {
            el('label-objetivo-importe').textContent = 'Objetivo: ' + fmtMoney(data.objetivo_importe || 0)
                + ' (' + procesoEstado.porcentaje + '%)';
        } else {
            el('label-objetivo-importe').textContent = '';
        }
        renderRedistribucion(data);
    }

    function analizar() {
        var params = empresaYFechaDesdeFormulario();
        if (params.empresa_id <= 0 || !params.fecha_jornada) {
            setError('Seleccione empresa y fecha de jornada y pulse Consultar.');
            return;
        }
        setError('');
        setRecalculoOk('');
        setProcesoLoading(true);
        var url = (CFG.urlAnalizar || '') + '?empresa_id=' + params.empresa_id
            + '&fecha_jornada=' + encodeURIComponent(params.fecha_jornada);
        apiGet(url).then(function (data) {
            aplicarAnalisis(data, params);
        }).catch(function (e) {
            setError(e.message);
        }).finally(function () {
            setProcesoLoading(false);
        });
    }

    function recalcular() {
        var params = empresaYFechaDesdeFormulario();
        if (params.empresa_id <= 0 || !params.fecha_jornada) {
            setError('Seleccione empresa y fecha de jornada y pulse Consultar.');
            return;
        }
        var grillaVisible = el('panel-proceso-grilla') && !el('panel-proceso-grilla').classList.contains('d-none');
        if (!grillaVisible) {
            setError('Primero pulse «Analizar tramo de jornada (Waitry vs Anita)»; después use Recalcular medios.');
            return;
        }
        setError('');
        setRecalculoOk('');
        var pct = porcentajeActual();
        var ctxVal = validarPorcentajeRecodificacion(
            contextoPorcentajeDesdeData(procesoEstado.lastCuadroData || {}),
            pct,
        );
        if (!ctxVal.ok) {
            setError(ctxVal.mensaje || 'El porcentaje excede lo recodificable.');
            actualizarContextoPorcentaje(procesoEstado.lastCuadroData || {});
            return;
        }
        var btnRecalc = el('btn-proceso-recalcular');
        if (btnRecalc) {
            btnRecalc.disabled = true;
        }
        mostrarOverlayRecalculando(true, pct);
        apiPost(CFG.urlRecalcular || '', {
            empresa_id: params.empresa_id,
            fecha_jornada: params.fecha_jornada,
            porcentaje: pct,
        }).then(function (data) {
            sincronizarJornadaProcesoDesdeRespuesta(data);
            procesoEstado.porcentaje = parseFloat(data.porcentaje) || porcentajeActual();
            procesoEstado.detalleCache = {};
            el('label-objetivo-importe').textContent = 'Objetivo: ' + fmtMoney(data.objetivo_importe)
                + ' (' + data.porcentaje + '%)';
            renderCuadro(data);
            renderRedistribucion(data);
            mostrar('panel-proceso-grilla', true);
            setRecalculoOk(mensajeRecalculoOk(data));
        }).catch(function (e) {
            setError(e.message);
        }).finally(function () {
            mostrarOverlayRecalculando(false);
            if (btnRecalc) {
                btnRecalc.disabled = false;
            }
        });
    }

    function campoBaseDesdeId(campoId) {
        return (campoId || '').replace(/_id$/, '');
    }

    function empresaIdFormulario() {
        var empresa = el('empresa_id');
        return empresa ? parseInt(empresa.value, 10) || 0 : 0;
    }

    function limpiarCampoCuenta($campo) {
        $campo.find('.cuentacontable_id').val('');
        $campo.find('.codigocuentacontable').val('');
        $campo.find('.nombrecuentacontable').val('');
    }

    function cargarCampoCuenta($campo, cfg) {
        var campoId = $campo.data('campo-id');
        var base = campoBaseDesdeId(campoId);
        var codigo = cfg[base + '_codigo'] || '';
        $campo.find('.cuentacontable_id').val(cfg[campoId] || '');
        $campo.find('.codigocuentacontable').val(codigo);
        $campo.find('.codigo_previo').val(codigo);
        $campo.find('.nombrecuentacontable').val(cfg[base + '_nombre'] || '');
    }

    function syncEmpresaEnFilasConfig() {
        if (typeof window.jQuery === 'undefined') {
            return;
        }
        var emp = empresaIdFormulario();
        window.jQuery('.cfg-cuenta-campo .empresa').val(emp > 0 ? String(emp) : '');
    }

    function cargarConfigEnModal(cfg) {
        cfg = cfg || {};
        if (typeof window.jQuery === 'undefined') {
            return;
        }
        syncEmpresaEnFilasConfig();
        window.jQuery('.cfg-cuenta-campo').each(function () {
            cargarCampoCuenta(window.jQuery(this), cfg);
        });
    }

    function initConsultaCuentasConfig() {
        if (typeof window.jQuery === 'undefined' || typeof activa_eventos_consulta_cuentacontable !== 'function') {
            return;
        }
        var $ = window.jQuery;

        syncEmpresaEnFilasConfig();
        activa_eventos_consulta_cuentacontable();

        // Filas fijas de config: no borrar el <tr> ni invocar leeCentroCosto (patrón artículo es dinámico).
        $('.cfg-cuenta-campo .codigocuentacontable').off('change').on('change', function (event) {
            event.preventDefault();
            var $campo = $(this).closest('.cfg-cuenta-campo');
            var codigo = ($(this).val() || '').trim();
            var empresaId = empresaIdFormulario();
            $campo.find('.empresa').val(empresaId > 0 ? String(empresaId) : '');

            if (!codigo) {
                limpiarCampoCuenta($campo);
                return;
            }
            if (empresaId <= 0) {
                alert('Seleccione empresa.');
                limpiarCampoCuenta($campo);
                return;
            }
            $.get(
                carpetaBase + '/contable/cuentacontable/leercuentacontableporcodigo/' + empresaId + '/' + encodeURIComponent(codigo),
                function (data) {
                    if (data && data.id > 0) {
                        $campo.find('.cuentacontable_id').val(data.id);
                        $campo.find('.cuentacontable_id_previa').val(data.id);
                        $campo.find('.nombrecuentacontable').val(data.nombre || '');
                        $campo.find('.codigo_previo').val(codigo);
                    } else {
                        alert('No existe la cuenta para la empresa seleccionada.');
                        limpiarCampoCuenta($campo);
                    }
                },
            );
        });

        $('#modal-config-contable').on('shown.bs.modal', syncEmpresaEnFilasConfig);

        $('#empresa_id').on('change', function () {
            syncEmpresaEnFilasConfig();
            $('.cfg-cuenta-campo').each(function () {
                limpiarCampoCuenta($(this));
            });
        });
    }

    function leerConfigDesdeModal() {
        var payload = {};
        if (typeof window.jQuery === 'undefined') {
            return payload;
        }
        window.jQuery('.cfg-cuenta-campo').each(function () {
            var campoId = window.jQuery(this).data('campo-id');
            payload[campoId] = window.jQuery(this).find('.cuentacontable_id').val() || '';
        });
        return payload;
    }

    function initConfig() {
        initConsultaCuentasConfig();
        cargarConfigEnModal(CFG.configInicial);
        var btnCfg = el('btn-config-contable');
        if (btnCfg) {
            btnCfg.addEventListener('click', function () {
                var p = empresaYFechaDesdeFormulario();
                if (p.empresa_id > 0) {
                    apiGet((CFG.urlConfigBase || '').replace('__EMPRESA_ID__', p.empresa_id)).then(function (r) {
                        cargarConfigEnModal(r.config);
                        $('#modal-config-contable').modal('show');
                    }).catch(function (e) {
                        alert(e.message);
                    });
                } else {
                    alert('Seleccione empresa.');
                }
            });
        }
        var form = el('form-config-contable');
        if (form) {
            form.addEventListener('submit', function (ev) {
                ev.preventDefault();
                var p = empresaYFechaDesdeFormulario();
                if (p.empresa_id <= 0) {
                    alert('Seleccione empresa.');
                    return;
                }
                var payload = leerConfigDesdeModal();
                apiPost((CFG.urlConfigGuardarBase || '').replace('__EMPRESA_ID__', p.empresa_id), payload).then(function (r) {
                    if (r && r.config) {
                        CFG.configInicial = r.config;
                        cargarConfigEnModal(r.config);
                    }
                    $('#modal-config-contable').modal('hide');
                }).catch(function (e) {
                    alert(e.message);
                });
            });
        }
    }

    function init() {
        if (!CFG.puedeProceso) {
            return;
        }
        var btnAnalizar = el('btn-proceso-analizar');
        if (btnAnalizar) {
            btnAnalizar.addEventListener('click', analizar);
        }
        var btnRecalc = el('btn-proceso-recalcular');
        if (btnRecalc) {
            btnRecalc.addEventListener('click', recalcular);
        }
        var inputPct = el('input-porcentaje');
        if (inputPct) {
            inputPct.addEventListener('input', function () {
                actualizarContextoPorcentaje(procesoEstado.lastCuadroData || {});
            });
            inputPct.addEventListener('change', function () {
                actualizarContextoPorcentaje(procesoEstado.lastCuadroData || {});
            });
        }
        var btnPreview = el('btn-proceso-preview-asientos');
        if (btnPreview) {
            btnPreview.addEventListener('click', function () {
                cargarPreviewFactura(true);
            });
        }
        var btnComandas = el('btn-proceso-comandas-factura');
        if (btnComandas) {
            btnComandas.addEventListener('click', function () {
                abrirComandasFactura('factura_proceso');
            });
        }
        var btnEmitir = el('btn-proceso-emitir-factura');
        if (btnEmitir) {
            btnEmitir.addEventListener('click', emitirFacturaProceso);
        }
        var btnConfirmarEmitir = el('btn-confirmar-emitir-factura-proceso');
        if (btnConfirmarEmitir) {
            btnConfirmarEmitir.addEventListener('click', confirmarEmitirFacturaProceso);
        }
        var btnGrabarAsientos = el('btn-proceso-grabar-asientos');
        if (btnGrabarAsientos) {
            btnGrabarAsientos.addEventListener('click', grabarAsientosProceso);
        }
        var btnRevertir = el('btn-proceso-revertir');
        if (btnRevertir) {
            btnRevertir.addEventListener('click', abrirModalRevertirProceso);
        }
        var btnConfirmarRevertir = el('btn-confirmar-revertir-proceso');
        if (btnConfirmarRevertir) {
            btnConfirmarRevertir.addEventListener('click', confirmarRevertirProceso);
        }
        var btnPreviewComandas = el('btn-preview-abrir-comandas');
        if (btnPreviewComandas) {
            btnPreviewComandas.addEventListener('click', function () {
                if (typeof window.jQuery !== 'undefined') {
                    window.jQuery('#modal-preview-asientos-factura').modal('hide');
                }
                abrirComandasFactura('factura_proceso');
            });
        }
        initConfig();
        initCuadroDetalleModal();
        var cuadroResizeTimer;
        window.addEventListener('resize', function () {
            clearTimeout(cuadroResizeTimer);
            cuadroResizeTimer = setTimeout(function () {
                if (procesoEstado.lastCuadroData) {
                    renderCuadro(procesoEstado.lastCuadroData);
                }
            }, 150);
        });
        initGruposAcordeon();
        initModalesDataTableCleanup();
        initBannerRecalculo();
    }

    function initBannerRecalculo() {
        var overlay = el('proceso-recalculo-banner-overlay');
        if (!overlay) {
            return;
        }
        var cerrar = function () {
            ocultarBannerRecalculo();
        };
        var btnCerrar = el('btn-proceso-recalculo-banner-cerrar');
        var btnEntendido = el('btn-proceso-recalculo-banner-entendido');
        if (btnCerrar) {
            btnCerrar.addEventListener('click', cerrar);
        }
        if (btnEntendido) {
            btnEntendido.addEventListener('click', cerrar);
        }
        overlay.addEventListener('click', function (ev) {
            if (ev.target === overlay) {
                cerrar();
            }
        });
        document.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape' && overlay.classList.contains('is-visible')) {
                cerrar();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
