(function () {
    const G = window.GASTRONOMIA || {};
    let empresaId = G.empresaId || null;
    const prefijoSku = (G.prefijoSku || 'V').toString();
    const skuDigitosSufijo = Math.max(0, parseInt(String(G.skuCatalogoDigitosSufijo || '0'), 10) || 0);
    const wsfeReceptorCfUmbralMonto = Math.max(0, parseFloat(String(G.wsfeReceptorCfUmbralMonto || '0')) || 0);

    let cuentaId = null;
    let cuentaActivaMesaId = null;
    let cuentaActivaSubtotalArs = null;
    let cuentaActivaConLineas = null;
    let lastDescuentoGastronomiaMeta = null;
    const IMPORTE_MINIMO_FACTURA = 0.01;
    /** @type {'mesa'|'cuenta'|'waitry'} */
    let modoSeleccion = 'mesa';
    let pendingArticulo = null;
    let pendingOpcionalesCtx = null;
    let pendingOpcionalesSeleccion = null;
    let pendingAbrirCuentaResolver = null;
    let pendingAbrirCuentaReject = null;
    let cobranzaWaitryTotemBloqueada = false;
    /** Cuenta cuya grilla de cobranza refleja el estado actual (evita arrastrar TOTEM/efectivo al cambiar de cuenta). */
    let cuentaIdCobranzaVinculada = null;

    function cubiertosDefaultApertura() {
        return Math.max(0, parseInt(String(G.cubiertosDefaultAlAbrir ?? '1'), 10) || 0);
    }

    function requiereDatosAperturaAlAbrir() {
        return !!(G.cubiertosObligatorioAlAbrir || G.mozoObligatorioAlAbrir);
    }

    function aplicarConfigAperturaDesdeApi(data) {
        if (!data) return;
        if (data.cubiertos_default_al_abrir != null) {
            G.cubiertosDefaultAlAbrir = data.cubiertos_default_al_abrir;
        }
        if (data.cubiertos_obligatorio_al_abrir != null) {
            G.cubiertosObligatorioAlAbrir = !!data.cubiertos_obligatorio_al_abrir;
        }
        if (data.mozo_obligatorio_al_abrir != null) {
            G.mozoObligatorioAlAbrir = !!data.mozo_obligatorio_al_abrir;
        }
        if (data.cuentas_libres_habilitadas != null) {
            G.cuentasLibresHabilitadas = !!data.cuentas_libres_habilitadas;
        }
        aplicarVisibilidadCuentasLibres();
    }

    function aplicarVisibilidadCuentasLibres() {
        const habilitadas = G.cuentasLibresHabilitadas !== false;
        const btnModoCuenta = document.getElementById('btn-modo-cuenta');
        if (btnModoCuenta) {
            btnModoCuenta.classList.toggle('d-none', !habilitadas);
        }
        if (!habilitadas && modoSeleccion === 'cuenta') {
            setModo('mesa', { silent: true });
        }
    }

    function waitryHabilitadoEnPos() {
        return G.waitryHabilitado === true;
    }

    function waitryMinutosAtrasFiltro() {
        const n = parseInt(String(G.waitryGetOrdersMinutosAtras ?? '20'), 10);
        return Number.isFinite(n) && n >= 0 ? n : 20;
    }

    function actualizarLeyendaFiltroWaitry(filtroApi) {
        const el = document.getElementById('panel-waitry-filtro-leyenda');
        if (!el) return;
        const min =
            filtroApi && filtroApi.minutos_atras != null
                ? parseInt(String(filtroApi.minutos_atras), 10)
                : waitryMinutosAtrasFiltro();
        if (min > 0) {
            el.textContent = 'Órdenes Waitry sin pago — últimos ' + min + ' min';
        } else {
            el.textContent = 'Órdenes Waitry sin pago (sin filtro horario)';
        }
    }

    function aplicarVisibilidadWaitry() {
        const visible = waitryHabilitadoEnPos();
        const btn = document.getElementById('btn-modo-waitry');
        if (btn) {
            btn.classList.toggle('d-none', !visible);
        }
        const btnPorId = document.getElementById('btn-waitry-importar-por-id');
        if (btnPorId) {
            btnPorId.classList.toggle('d-none', !visible);
        }
        if (!visible && modoSeleccion === 'waitry') {
            setModo('mesa', { silent: true });
        }
    }

    function esCampoTextoEditable(el) {
        if (!el || !el.tagName) return false;
        const tag = el.tagName.toLowerCase();
        if (tag === 'input' || tag === 'textarea' || tag === 'select') {
            if (el.disabled || el.readOnly) return false;
            const type = (el.type || '').toLowerCase();
            if (type === 'checkbox' || type === 'radio' || type === 'button' || type === 'submit') {
                return false;
            }
            return true;
        }
        return !!el.isContentEditable;
    }

    function esAtajoNuevaCuentaLibre(e) {
        return e.key === '+' || e.code === 'NumpadAdd' || (e.key === '=' && e.shiftKey);
    }

    function appPath(path) {
        if (/^https?:\/\//i.test(path)) {
            return path;
        }
        const raw = typeof carpetaBase !== 'undefined' && carpetaBase != null ? String(carpetaBase) : '';
        const base = raw.replace(/\/$/, '');
        const p = path.startsWith('/') ? path : '/' + path;
        return base + p;
    }

    function hdrJson() {
        return {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': G.csrf,
            'X-Requested-With': 'XMLHttpRequest',
        };
    }

    function normalizarRutaApi(path) {
        const p = String(path || '').split('?')[0];
        return p.startsWith('/') ? p : '/' + p;
    }

    function metodoHttpApi(opts) {
        return ((opts && opts.method) || 'GET').toUpperCase();
    }

    function jornadaAbiertaEnPos() {
        if (G.turnoOperativo && G.turnoOperativo.jornada_abierta === true) {
            return true;
        }
        return !!(G.jornada && G.jornada.jornada_abierta);
    }

    function turnoHabilitadoEnPos() {
        if (G.requiereHabilitacionTurno === false) {
            return true;
        }
        return !!(G.turnoOperativo && G.turnoOperativo.turno_habilitado);
    }

    function mensajeBloqueoJornadaTurno() {
        const partes = [];
        if (G.jornadaObligatoria !== false && !jornadaAbiertaEnPos()) {
            const urlJ = G.urlJornada || '';
            partes.push(
                'No hay jornada abierta para esta empresa.' +
                    (urlJ ? ' Abra la jornada en Ventas → Gastronomía → Jornada.' : ''),
            );
        }
        if (G.requiereHabilitacionTurno !== false && !turnoHabilitadoEnPos()) {
            const urlT = G.urlHabilitacionTurno || '';
            partes.push(
                'No hay turno habilitado en esta terminal.' +
                    (urlT ? ' Habilite el turno en Ventas → Gastronomía → Habilitación de turno.' : ''),
            );
        }
        return partes.join(' ');
    }

    function exigirJornadaTurnoParaOperar(opts) {
        const msg = mensajeBloqueoJornadaTurno();
        if (!msg) {
            return true;
        }
        if (!opts || !opts.silencioso) {
            toast(msg, 'warning');
        }
        return false;
    }

    function bloquearOperacionPosPorJornadaTurno() {
        return !exigirJornadaTurnoParaOperar();
    }

    async function asegurarJornadaTurnoAntesDeOperar() {
        if (typeof window.gastroRefrescarEstadoTurno === 'function') {
            try {
                await window.gastroRefrescarEstadoTurno();
            } catch (e) {
                /* estado turno opcional */
            }
        }
        return exigirJornadaTurnoParaOperar({ silencioso: true });
    }

    function apiRequiereJornadaTurno(path, opts) {
        const p = normalizarRutaApi(path);
        if (!p.includes('/ventas/gastronomia/api/')) {
            return false;
        }
        const method = metodoHttpApi(opts);
        return method !== 'GET' && method !== 'HEAD';
    }

    function aplicarEstadoJornadaTurnoDesdeApi(data) {
        if (!data) {
            return;
        }
        if (data.jornada != null) {
            G.jornada = data.jornada;
        }
        if (data.jornada_obligatoria != null) {
            G.jornadaObligatoria = !!data.jornada_obligatoria;
        }
        if (data.requiere_habilitacion_turno != null) {
            G.requiereHabilitacionTurno = !!data.requiere_habilitacion_turno;
        }
        if (data.turno_operativo != null) {
            G.turnoOperativo = data.turno_operativo;
            if (typeof window.gastroActualizarAlertaTurno === 'function') {
                window.gastroActualizarAlertaTurno(data.turno_operativo);
            }
        }
    }

    G.exigirJornadaTurnoParaOperar = exigirJornadaTurnoParaOperar;
    G.mensajeBloqueoJornadaTurno = mensajeBloqueoJornadaTurno;

    async function api(path, opts) {
        if (apiRequiereJornadaTurno(path, opts)) {
            const ok = await asegurarJornadaTurnoAntesDeOperar();
            if (!ok) {
                const err = new Error(mensajeBloqueoJornadaTurno());
                err.codigo = 'jornada_turno_requerido';
                throw err;
            }
        }
        const url = appPath(path);
        const sep = url.includes('?') ? '&' : '?';
        const res = await fetch(url + sep + '_=' + Date.now(), opts);
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            const detail =
                data.error ||
                data.mensaje ||
                data.message ||
                (data.errors ? JSON.stringify(data.errors) : '') ||
                '';
            const err = new Error(detail || 'HTTP ' + res.status);
            err.payload = data;
            err.httpStatus = res.status;
            throw err;
        }
        return data;
    }

    let iframeImpresionFactura = null;

    function obtenerIframeImpresionFactura() {
        if (iframeImpresionFactura && document.body.contains(iframeImpresionFactura)) {
            return iframeImpresionFactura;
        }
        iframeImpresionFactura = document.getElementById('gastro-iframe-impresion-factura');
        if (!iframeImpresionFactura) {
            iframeImpresionFactura = document.createElement('iframe');
            iframeImpresionFactura.id = 'gastro-iframe-impresion-factura';
            iframeImpresionFactura.setAttribute('aria-hidden', 'true');
            iframeImpresionFactura.title = 'Impresión factura';
            iframeImpresionFactura.style.cssText =
                'position:absolute;width:0;height:0;border:0;left:-9999px;top:-9999px;';
            document.body.appendChild(iframeImpresionFactura);
        }
        return iframeImpresionFactura;
    }

    function limpiarIframeImpresionFactura() {
        const iframe = iframeImpresionFactura;
        if (!iframe) {
            return;
        }
        if (iframe._gastroBlobUrl) {
            try {
                URL.revokeObjectURL(iframe._gastroBlobUrl);
            } catch (e) {
                /* ignore */
            }
            iframe._gastroBlobUrl = null;
        }
        iframe.removeAttribute('src');
    }

    async function imprimirFacturaPdf(ventaId) {
        const vid = parseInt(String(ventaId), 10);
        if (!vid) {
            limpiarIframeImpresionFactura();
            return false;
        }
        const url = G.rutas.listaPdfFacturaBase + '/' + vid;
        const iframe = obtenerIframeImpresionFactura();
        limpiarIframeImpresionFactura();

        try {
            const res = await fetch(url, { credentials: 'same-origin' });
            if (!res.ok) {
                throw new Error('No se pudo obtener el PDF de la factura.');
            }
            const blob = await res.blob();
            const blobUrl = URL.createObjectURL(blob);
            iframe._gastroBlobUrl = blobUrl;

            await new Promise((resolve, reject) => {
                const onLoad = () => {
                    iframe.removeEventListener('load', onLoad);
                    iframe.removeEventListener('error', onError);
                    resolve();
                };
                const onError = () => {
                    iframe.removeEventListener('load', onLoad);
                    iframe.removeEventListener('error', onError);
                    reject(new Error('No se pudo cargar el PDF para impresión.'));
                };
                iframe.addEventListener('load', onLoad);
                iframe.addEventListener('error', onError);
                iframe.src = blobUrl;
            });

            const win = iframe.contentWindow;
            if (win) {
                win.focus();
                win.print();
            }
            return true;
        } catch (e) {
            limpiarIframeImpresionFactura();
            toast(e.message || 'Error al imprimir la factura.', 'error');
            return false;
        }
    }

    const UMBRAL_AVISO_PERSISTENTE = 90;

    function debeUsarAvisoPersistente(msg, type) {
        const s = String(msg || '').trim();
        if (!s) return false;
        if (type === 'warning' && s.length >= UMBRAL_AVISO_PERSISTENTE) return true;
        if (type === 'error' && (s.length >= UMBRAL_AVISO_PERSISTENTE || /factura emitida/i.test(s))) return true;
        return false;
    }

    function formatearTextoAviso(msg) {
        return String(msg || '')
            .replace(/\s{2,}/g, ' ')
            .replace(/([.!?])\s+(?=[A-ZÁÉÍÓÚÑ])/g, '$1\n\n')
            .trim();
    }

    let gastroAvisoKeyHandler = null;

    function cerrarAvisoPersistente() {
        const modal = document.getElementById('modal-gastro-aviso');
        if (modal && window.jQuery) {
            window.jQuery(modal).modal('hide');
        }
        if (gastroAvisoKeyHandler) {
            document.removeEventListener('keydown', gastroAvisoKeyHandler, true);
            gastroAvisoKeyHandler = null;
        }
    }

    function mostrarAvisoPersistente(mensaje, tipo, opciones) {
        const opts = opciones || {};
        const modal = document.getElementById('modal-gastro-aviso');
        if (!modal) {
            alert(formatearTextoAviso(mensaje));
            return;
        }
        const t = tipo || 'warning';
        const titulos = {
            warning: opts.titulo || 'Aviso',
            error: opts.titulo || 'Error',
            success: opts.titulo || 'Operación completada',
            info: opts.titulo || 'Información',
        };
        const headerCls = {
            warning: 'bg-warning text-dark',
            error: 'bg-danger text-white',
            success: 'bg-success text-white',
            info: 'bg-info text-white',
        };
        const header = document.getElementById('modal-gastro-aviso-header');
        const tituloEl = document.getElementById('modal-gastro-aviso-titulo');
        const detalleEl = document.getElementById('modal-gastro-aviso-detalle');
        const mensajeEl = document.getElementById('modal-gastro-aviso-mensaje');
        const btnAceptar = document.getElementById('modal-gastro-aviso-aceptar');
        if (header) {
            header.className = 'modal-header py-2 ' + (headerCls[t] || headerCls.warning);
        }
        if (tituloEl) tituloEl.textContent = titulos[t] || titulos.warning;
        const detalle = opts.detalle ? String(opts.detalle).trim() : '';
        if (detalleEl) {
            if (detalle) {
                detalleEl.textContent = detalle;
                detalleEl.classList.remove('d-none');
            } else {
                detalleEl.textContent = '';
                detalleEl.classList.add('d-none');
            }
        }
        if (mensajeEl) mensajeEl.textContent = formatearTextoAviso(mensaje);
        if (gastroAvisoKeyHandler) {
            document.removeEventListener('keydown', gastroAvisoKeyHandler, true);
        }
        gastroAvisoKeyHandler = function (ev) {
            if (!modal.classList.contains('show')) return;
            if (ev.key === 'Enter' || ev.key === 'Escape' || ev.key === ' ') {
                ev.preventDefault();
                cerrarAvisoPersistente();
            }
        };
        document.addEventListener('keydown', gastroAvisoKeyHandler, true);
        if (window.jQuery) {
            const $m = window.jQuery(modal);
            $m.off('hidden.bs.modal.gastroAviso').on('hidden.bs.modal.gastroAviso', function () {
                if (gastroAvisoKeyHandler) {
                    document.removeEventListener('keydown', gastroAvisoKeyHandler, true);
                    gastroAvisoKeyHandler = null;
                }
            });
            $m.modal('show');
            window.setTimeout(function () {
                if (btnAceptar) btnAceptar.focus();
            }, 350);
        } else {
            alert((detalle ? detalle + '\n\n' : '') + formatearTextoAviso(mensaje));
        }
    }

    function toast(msg, type, opciones) {
        const t = type || 'info';
        if (opciones && opciones.persistente) {
            mostrarAvisoPersistente(msg, t, opciones);
            return;
        }
        if (debeUsarAvisoPersistente(msg, t)) {
            mostrarAvisoPersistente(msg, t, opciones);
            return;
        }
        if (window.toastr) {
            const opts =
                t === 'warning' || t === 'error'
                    ? { timeOut: 8000, extendedTimeOut: 4000, closeButton: true, progressBar: true }
                    : {};
            toastr[t](msg, '', opts);
        } else {
            alert(msg);
        }
    }

    let facturacionLoadingTimer = null;

    function detenerRotacionMensajesProceso() {
        if (facturacionLoadingTimer) {
            clearInterval(facturacionLoadingTimer);
            facturacionLoadingTimer = null;
        }
    }

    function mensajesProcesoEmision(cuenta) {
        const mensajes = [
            'Generando comprobante fiscal…',
            'Registrando venta en el sistema…',
        ];
        if (G.sincronizarAnitaAlFacturar !== false) {
            mensajes.push('Registrando en Anita…');
        }
        mensajes.push(
            'Registrando cobranza…',
            'Actualizando stock e insumos…',
            'Solicitando autorización ARCA (CAE)…',
            'Imprimiendo ticket térmico…',
        );
        const cuentaWaitry =
            cuenta && (cuenta.waitry_order_id || cuenta.waitry_cobro_totem);
        if (G.waitryHabilitado === true && cuentaWaitry) {
            if (G.waitryTrasRespuesta === false) {
                mensajes.push('Sincronizando pago y comanda en Waitry…');
            } else {
                mensajes.push('Finalizando registro en Waitry…');
            }
        }

        return mensajes;
    }

    function escHtml(s) {
        return String(s || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function htmlCargaProcesoInline(titulo, subtitulo, claseIcono) {
        const icono = claseIcono || 'text-info';
        return (
            '<div class="text-center py-3 px-2">' +
            '<i class="fa fa-spinner fa-spin fa-lg ' +
            icono +
            ' mb-2 d-block" aria-hidden="true"></i>' +
            '<div><strong>' +
            escHtml(titulo) +
            '</strong></div>' +
            '<div class="small text-muted mt-1">' +
            escHtml(subtitulo) +
            '</div></div>'
        );
    }

    /** Éxito de emisión — mismo criterio que notas de crédito (mensaje + warn aparte). */
    function mostrarResultadoEmisionFactura(data) {
        const factura = (data && data.factura) || '';
        let txt =
            (data && String(data.mensaje || '').trim()) ||
            (factura ? 'Factura ' + factura + ' emitida correctamente.' : 'Factura emitida correctamente.');
        const papelito = data && data.waitry_display_id ? String(data.waitry_display_id).trim() : '';
        if (papelito && txt.indexOf(papelito) < 0) {
            txt += ' Papelito monitor: ' + papelito + '.';
        }
        const warn = data && String(data.warn || '').trim();
        if (warn) {
            if (debeUsarAvisoPersistente(warn, 'warning')) {
                mostrarAvisoPersistente(warn, 'warning', {
                    titulo: 'Factura emitida — revisar avisos',
                    detalle: factura ? 'Comprobante: ' + factura : '',
                });
            } else {
                toast(warn, 'warning');
            }
        }
        toast(txt, 'success');
    }

    function mostrarResultadoImportacionWaitry(data, waitryOrderId) {
        const id = waitryOrderId || (data && data.waitry_order_id);
        const txt =
            (data && String(data.mensaje || '').trim()) ||
            (id ? 'Cuenta Waitry #' + id + ' importada correctamente.' : 'Cuenta Waitry importada correctamente.');
        const warn = data && String(data.warn || '').trim();
        if (warn) {
            toast(warn, 'warning');
        }
        toast(txt, 'success');
    }

    function mensajesProcesoImportWaitry(waitryOrderId) {
        const id = waitryOrderId ? ' #' + waitryOrderId : '';
        return [
            'Leyendo orden' + id + ' en Waitry…',
            'Obteniendo ítems y precios…',
            'Importando consumos a la cuenta…',
        ];
    }

    function iniciarRotacionMensajesProceso(mensajes, intervaloMs) {
        detenerRotacionMensajesProceso();
        if (!mensajes || !mensajes.length) {
            return;
        }
        let idx = 0;
        setFacturacionLoading(true, mensajes[0]);
        facturacionLoadingTimer = setInterval(function () {
            idx = (idx + 1) % mensajes.length;
            setFacturacionLoading(true, mensajes[idx], { soloTexto: true });
        }, intervaloMs || 2800);
    }

    function setFacturacionLoading(isLoading, mensaje, opciones) {
        const opts = opciones || {};
        const btn = document.getElementById('tool-facturar');
        const badge = document.getElementById('gastro-facturacion-loading');
        const texto = badge ? badge.querySelector('.gastro-facturacion-loading-text') : null;
        const overlay = document.getElementById('gastro-facturacion-procesando-overlay');
        const tituloOverlay = document.getElementById('gastro-facturacion-procesando-titulo');
        const subtituloOverlay = document.getElementById('gastro-facturacion-procesando-subtitulo');

        if (!isLoading) {
            detenerRotacionMensajesProceso();
        }

        if (!opts.soloTexto) {
            if (badge) {
                badge.style.display = isLoading ? 'inline-block' : 'none';
            }
            if (btn) {
                btn.disabled = !!isLoading;
                btn.style.pointerEvents = isLoading ? 'none' : '';
                btn.style.opacity = isLoading ? '0.6' : '';
            }
            if (overlay) {
                if (isLoading) {
                    overlay.classList.remove('d-none');
                    overlay.style.display = 'flex';
                    overlay.setAttribute('aria-hidden', 'false');
                } else {
                    overlay.classList.add('d-none');
                    overlay.style.display = '';
                    overlay.setAttribute('aria-hidden', 'true');
                }
            }
        }

        const textoProceso = mensaje || (isLoading ? 'Procesando…' : '');
        if (texto && textoProceso) {
            texto.textContent = textoProceso;
        }
        if (tituloOverlay && textoProceso) {
            tituloOverlay.textContent = textoProceso;
        }
        if (subtituloOverlay && opts.subtitulo !== undefined) {
            subtituloOverlay.textContent = opts.subtitulo;
        } else if (subtituloOverlay && !isLoading) {
            subtituloOverlay.textContent = 'Por favor espere. No cierre ni recargue la página.';
        }
    }

    function limpiarCobranza(opciones) {
        const dejarFilaVacia = !opciones || opciones.filaVacia !== false;
        const tbody = document.getElementById('tbody-gastro-cuenta-table');
        if (tbody) tbody.innerHTML = '';
        totalFacturadoArs = 0;
        cotizacionExtranjera = { monedaId: null, cotizacion: 1, abrev: '' };
        const hCot = document.getElementById('gastro-cotizacion-extranjera');
        const hMon = document.getElementById('gastro-moneda-extranjera-id');
        if (hCot) hCot.value = '';
        if (hMon) hMon.value = '';
        actualizarBarraCotizacion();
        const wrap = document.getElementById('gastro-totales-cobranza');
        if (wrap) wrap.innerHTML = '';
        if (dejarFilaVacia) {
            agregarRenglonCobranza(false);
        }
    }

    function recogerMediosPagoFromGrid() {
        const medios = [];
        document.querySelectorAll('#tbody-gastro-cuenta-table tr').forEach((tr) => {
            const cuentacajaId = parseInt(tr.querySelector('.cuentacaja_id')?.value || '0', 10);
            const monedaId = parseInt(tr.querySelector('.moneda_id')?.value || '0', 10);
            const monto = parseFloat(tr.querySelector('.monto')?.value || '');
            if (!cuentacajaId || !monedaId || Number.isNaN(monto) || monto <= 0) return;
            const cot =
                monedaId > MONEDA_PESOS_ID && cotizacionExtranjera.monedaId === monedaId
                    ? cotizacionExtranjera.cotizacion
                    : null;
            const medio = {
                cuentacaja_id: cuentacajaId,
                moneda_id: monedaId,
                monto: monto,
                cotizacion: cot,
            };
            const ticketId = parseInt(tr.querySelector('.ticket_id')?.value || '0', 10);
            const numeroTicket = parseInt(tr.querySelector('.numeroticket')?.value || '0', 10);
            if (ticketId > 0 && numeroTicket > 0) {
                medio.ticket_id = ticketId;
                medio.numeroticket = numeroTicket;
            }
            medios.push(medio);
        });
        return medios;
    }

    function recogerTicketsYaSeleccionados(excluirTr) {
        const tickets = [];
        document.querySelectorAll('#tbody-gastro-cuenta-table tr').forEach((tr) => {
            if (excluirTr && tr === excluirTr) return;
            const ticketId = parseInt(tr.querySelector('.ticket_id')?.value || '0', 10);
            const numeroTicket = parseInt(tr.querySelector('.numeroticket')?.value || '0', 10);
            if (ticketId > 0 && numeroTicket > 0) {
                tickets.push({ ticket_id: ticketId, numeroticket: numeroTicket });
            }
        });
        return tickets;
    }

    function totalCobranzaArsActual() {
        let total = 0;
        document.querySelectorAll('#tbody-gastro-cuenta-table tr').forEach((tr) => {
            total += montoCobranzaEnArs(tr);
        });
        return total;
    }

    function parseMontoArsTexto(raw) {
        const s = String(raw || '').trim();
        if (!s) return 0;
        if (/,\d{1,2}$/.test(s)) {
            return parseFloat(s.replace(/\./g, '').replace(',', '.'));
        }
        return parseFloat(s.replace(/,/g, ''));
    }

    function descuentoGastronomiaParaCalculo(cuenta) {
        if (cuenta && cuenta.descuento_gastronomia) {
            return cuenta.descuento_gastronomia;
        }
        return lastDescuentoGastronomiaMeta;
    }

    function subtotalBrutoLineasCuenta(cuenta) {
        let sub = 0;
        (cuenta && cuenta.lineas ? cuenta.lineas : []).forEach((ln) => {
            const pu = Number(ln.precio_unitario);
            const cant = Number(ln.cantidad);
            const pct = Number(ln.descuento_linea_pct || 0);
            sub += cant * pu * (1 - pct / 100);
        });
        return Math.round(sub * 100) / 100;
    }

    function evaluarFacturaCortesiaDesdeCuenta(cuenta) {
        const desc = descuentoGastronomiaParaCalculo(cuenta);
        const subBruto = subtotalBrutoLineasCuenta(cuenta || cuentaActivaConLineas);
        if (!desc) {
            return { cortesia: false, subtotalBruto: subBruto };
        }
        const val = Number(desc.valor || 0);
        if (desc.tipovalor === 'P' && val >= 100) {
            return { cortesia: true, subtotalBruto: subBruto };
        }
        if (desc.tipovalor === 'I' && subBruto > 0 && val >= subBruto - 0.001) {
            return { cortesia: true, subtotalBruto: subBruto };
        }
        return { cortesia: false, subtotalBruto: subBruto };
    }

    function esFacturaCortesia(montoArsOpt, cuentaOpt) {
        const cuenta = cuentaOpt || cuentaActivaConLineas;
        if (cuenta && cuenta.factura_cortesia) {
            return true;
        }
        if (cuenta && cuenta.sin_cobranza) {
            return true;
        }
        const monto =
            montoArsOpt != null && !Number.isNaN(Number(montoArsOpt))
                ? Number(montoArsOpt)
                : totalFacturarDesdeCuenta(cuenta);
        if (monto > 0 && monto <= 0.02) {
            return true;
        }
        return evaluarFacturaCortesiaDesdeCuenta(cuenta).cortesia;
    }

    /** Total fiscal del comprobante (con IVA e impuestos); usar para cobranza y validación. */
    function totalFacturarDesdeCuenta(cuenta) {
        const c = cuenta || cuentaActivaConLineas;
        if (c && c.total_facturar_ars != null && !Number.isNaN(Number(c.total_facturar_ars))) {
            return Number(c.total_facturar_ars);
        }
        return subtotalEstimadoDesdeCuenta(c);
    }

    function leerTotalAFacturarArs() {
        if (cuentaActivaSubtotalArs != null && cuentaActivaSubtotalArs > 0) {
            return cuentaActivaSubtotalArs;
        }
        const wrap = document.getElementById('panel-detalle-lineas');
        const elSub = wrap ? wrap.querySelector('[data-subtotal-estimado]') : null;
        if (elSub) {
            const v = parseFloat(elSub.getAttribute('data-subtotal-estimado') || '');
            if (!Number.isNaN(v) && v >= 0) {
                return v;
            }
        }
        const txt = wrap ? wrap.textContent : '';
        const m =
            txt.match(/Total a facturar \(\$\):\s*([\d.,]+)/) ||
            txt.match(/Subtotal estimado \(\$\):\s*([\d.,]+)/) ||
            txt.match(/Subtotal estimado:\s*([\d.,]+)/);
        if (m) {
            return parseMontoArsTexto(m[1]);
        }
        if (evaluarFacturaCortesiaDesdeCuenta(cuentaActivaConLineas).cortesia) {
            return IMPORTE_MINIMO_FACTURA;
        }
        return totalFacturadoArs > 0 ? totalFacturadoArs : 0;
    }

    function leerSubtotalEstimadoArs() {
        return leerTotalAFacturarArs();
    }

    function cuentaTieneLineasFacturables() {
        const wrap = document.getElementById('panel-detalle-lineas');
        return !!(wrap && wrap.querySelector('tbody tr'));
    }

    let pendingDescuentoResolver = null;
    let esperarDescuentoCleanup = null;
    let pendingModalF8DescuentoResolver = null;
    let modalF8DescuentoEnCurso = false;
    let modalF8DescuentoConfirmadoOk = false;

    function avisoDescuentoEnModal(visible) {
        const aviso = document.getElementById('gastro-descuento-en-modal-aviso');
        if (aviso) {
            aviso.classList.toggle('d-none', !visible);
        }
    }

    function slotDescuentoOriginal() {
        return document.getElementById('gastro-descuento-slot-original');
    }

    function slotDescuentoModal() {
        return document.getElementById('gastro-descuento-slot-modal');
    }

    function bloqueDescuentoMovable() {
        return document.getElementById('gastro-descuento-movable');
    }

    function descuentoEnModalF8() {
        const bloque = bloqueDescuentoMovable();
        const slotModal = slotDescuentoModal();
        return !!(bloque && slotModal && slotModal.contains(bloque));
    }

    function moverBloqueDescuentoAlModal() {
        const bloque = bloqueDescuentoMovable();
        const slotModal = slotDescuentoModal();
        if (bloque && slotModal) {
            slotModal.appendChild(bloque);
        }
        avisoDescuentoEnModal(true);
    }

    function restaurarBloqueDescuentoEnTarjeta() {
        const bloque = bloqueDescuentoMovable();
        const slotOriginal = slotDescuentoOriginal();
        if (bloque && slotOriginal) {
            slotOriginal.appendChild(bloque);
        }
        avisoDescuentoEnModal(false);
    }

    function rechazarModalF8Descuento(mensaje) {
        if (pendingModalF8DescuentoResolver) {
            pendingModalF8DescuentoResolver.reject(new Error(mensaje || 'Operación cancelada.'));
            pendingModalF8DescuentoResolver = null;
        }
        modalF8DescuentoEnCurso = false;
    }

    function cerrarModalF8Descuento() {
        if (typeof $ !== 'undefined') {
            $('#modal-f8-descuento').modal('hide');
            return;
        }
        const modal = document.getElementById('modal-f8-descuento');
        if (modal) {
            modal.classList.remove('show');
            modal.style.display = 'none';
            modal.setAttribute('aria-hidden', 'true');
        }
        document.body.classList.remove('modal-open');
        document.querySelectorAll('.modal-backdrop').forEach((el) => el.remove());
        restaurarBloqueDescuentoEnTarjeta();
        limpiarEsperaDescuento('Operación cancelada.');
        limpiarEsperaClienteInterno('Operación cancelada.');
        rechazarModalF8Descuento('Operación cancelada.');
    }

    function abrirModalF8Descuento() {
        return new Promise((resolve, reject) => {
            if (bloquearOperacionPosPorJornadaTurno()) {
                reject(new Error(mensajeBloqueoJornadaTurno()));
                return;
            }
            if (modalF8DescuentoEnCurso) {
                reject(new Error('Ya hay un modal de descuento abierto.'));
                return;
            }
            modalF8DescuentoEnCurso = true;
            modalF8DescuentoConfirmadoOk = false;
            pendingModalF8DescuentoResolver = {
                resolve: () => {
                    pendingModalF8DescuentoResolver = null;
                    modalF8DescuentoEnCurso = false;
                    resolve();
                },
                reject: (err) => {
                    pendingModalF8DescuentoResolver = null;
                    modalF8DescuentoEnCurso = false;
                    reject(err);
                },
            };

            if (tieneDescuentoEnPantalla() && typeof mostrarPanelClienteInternoDescuento === 'function') {
                mostrarPanelClienteInternoDescuento(true);
            }

            moverBloqueDescuentoAlModal();

            if (typeof $ !== 'undefined') {
                $('#modal-f8-descuento').modal('show');
            } else {
                const modal = document.getElementById('modal-f8-descuento');
                if (modal) {
                    modal.classList.add('show');
                    modal.style.display = 'block';
                    modal.removeAttribute('aria-hidden');
                }
                setTimeout(() => enfocarCampoDescuentoCodigo(), 80);
            }
        });
    }

    async function confirmarModalF8Descuento() {
        const btn = document.getElementById('modal-f8-descuento-confirmar');
        if (btn) {
            btn.disabled = true;
        }
        try {
            await asegurarDescuentoObligatorio({ silencioso: true });
            modalF8DescuentoConfirmadoOk = true;
            if (pendingModalF8DescuentoResolver) {
                pendingModalF8DescuentoResolver.resolve();
            }
            if (typeof $ !== 'undefined') {
                $('#modal-f8-descuento').modal('hide');
            } else {
                restaurarBloqueDescuentoEnTarjeta();
            }
        } catch (e) {
            toast(e.message || String(e), 'error');
        } finally {
            if (btn) {
                btn.disabled = false;
            }
        }
    }

    const GASTRONOMIA_MODAL_Z_BASE = 1050;
    const GASTRONOMIA_MODAL_Z_STEP = 20;

    function modalF8DescuentoAbierto() {
        const el = document.getElementById('modal-f8-descuento');
        return !!(el && el.classList.contains('show'));
    }

    /** Consulta cliente/descuento queda detrás del F8 si no se eleva z-index (orden DOM). */
    function apilarModalConsultaSobreF8($modal) {
        if (!$modal || !$modal.length || !modalF8DescuentoAbierto()) {
            return;
        }
        const zHijo = GASTRONOMIA_MODAL_Z_BASE + GASTRONOMIA_MODAL_Z_STEP;
        const zBackdrop = zHijo - 10;
        $modal.data('gastroApiladoSobreF8', true);
        $modal.addClass('gastro-modal-sobre-f8');
        $modal.css('z-index', zHijo);
        $('.modal-backdrop').last().css('z-index', zBackdrop);
    }

    function desapilarModalConsultaSobreF8($modal) {
        if (!$modal || !$modal.length || !$modal.data('gastroApiladoSobreF8')) {
            return;
        }
        $modal.removeData('gastroApiladoSobreF8');
        $modal.removeClass('gastro-modal-sobre-f8');
        $modal.css('z-index', '');
        if ($('.modal-backdrop').length) {
            $('.modal-backdrop').last().css('z-index', '');
        }
        if (modalF8DescuentoAbierto()) {
            $('body').addClass('modal-open');
        }
    }

    function wireApiladoConsultasSobreModalF8() {
        if (typeof $ === 'undefined') {
            return;
        }
        ['#consultaclienteModal', '#consultadescuentoModal'].forEach((sel) => {
            const $m = $(sel);
            $m.off('show.bs.modal.gastroF8Stack hidden.bs.modal.gastroF8Stack');
            $m.on('shown.bs.modal.gastroF8Stack', function () {
                apilarModalConsultaSobreF8($m);
            });
            $m.on('hidden.bs.modal.gastroF8Stack', function () {
                desapilarModalConsultaSobreF8($m);
            });
        });
    }

    function debeIgnorarAtajoPos() {
        const ids = [
            'modal-cantidad',
            'modal-waitry-importar-id',
            'modal-opcionales',
            'modal-abrir-cuenta',
            'consultacuentacajaModal',
            'consultamozoModal',
            'consultaclienteModal',
            'consultadescuentoModal',
            'consultaclienteModal',
            'modal-f8-descuento',
            'modal-gastro-canje-ticket-tarjeta',
            'modal-gastro-canje-premio',
            'modal-gastro-canje-fidelidad',
        ];
        for (let i = 0; i < ids.length; i++) {
            const el = document.getElementById(ids[i]);
            if (el && el.classList.contains('show')) {
                return true;
            }
        }
        const badge = document.getElementById('gastro-facturacion-loading');
        if (badge && badge.style.display !== 'none') {
            return true;
        }
        const overlayProc = document.getElementById('gastro-facturacion-procesando-overlay');
        if (overlayProc && overlayProc.getAttribute('aria-hidden') === 'false') {
            return true;
        }
        return false;
    }

    function limpiarEsperaDescuento(mensajeRechazo) {
        if (esperarDescuentoCleanup) {
            esperarDescuentoCleanup();
            esperarDescuentoCleanup = null;
        }
        if (mensajeRechazo && pendingDescuentoResolver) {
            pendingDescuentoResolver.reject(new Error(mensajeRechazo));
        }
        pendingDescuentoResolver = null;
    }

    function etiquetaDescuentoAplicado(desc) {
        if (!desc) return 'Descuento';
        const val = Number(desc.valor || 0);
        if (desc.tipovalor === 'P') {
            return `${desc.nombre || 'Descuento'} (${val}%)`;
        }
        if (desc.tipovalor === 'I') {
            return `${desc.nombre || 'Descuento'} ($ ${val.toFixed(2)})`;
        }
        return desc.nombre || 'Descuento';
    }

    function subtotalLineasSinDescuentoCabecera(cuenta) {
        let sub = 0;
        (cuenta.lineas || []).forEach((ln) => {
            const pu = Number(ln.precio_unitario);
            const cant = Number(ln.cantidad);
            const pct = Number(ln.descuento_linea_pct || 0);
            sub += cant * pu * (1 - pct / 100);
        });
        return Math.round(sub * 100) / 100;
    }

    function htmlDetalleSubtotalConDescuento(cuenta, subFinal) {
        const subBruto = subtotalLineasSinDescuentoCabecera(cuenta);
        const desc = cuenta.descuento_gastronomia || null;
        let html = '';
        if (desc && subBruto > subFinal + 0.001) {
            const ahorro = Math.round((subBruto - subFinal) * 100) / 100;
            html +=
                '<div class="text-right text-muted small">' +
                etiquetaDescuentoAplicado(desc) +
                ': -$ ' +
                ahorro.toFixed(2) +
                ' (antes $ ' +
                subBruto.toFixed(2) +
                ')</div>';
        }
        html +=
            '<div class="text-right mt-1" data-subtotal-estimado="' +
            subFinal.toFixed(2) +
            '"><strong>Total a facturar ($):</strong> ' +
            subFinal.toFixed(2) +
            '</div>';
        return html;
    }

    async function cargarDescuentoPorCodigoApi(codigo) {
        const cod = (codigo || '').trim();
        if (!cod) {
            throw new Error('Código de descuento vacío');
        }
        const res = await fetch(
            appPath('/ventas/descuento-gastronomia/leer/' + encodeURIComponent(cod)),
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        );
        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data.id) {
            throw new Error(data.error || 'Descuento no encontrado');
        }
        if (typeof pintarDescuentoEnPantalla === 'function') {
            pintarDescuentoEnPantalla(data);
        }
        enfocarClienteInternoTrasDescuento();
        return data;
    }

    function enfocarClienteInternoTrasDescuento() {
        const panelCli = document.getElementById('panel-cliente-descuento');
        if (!panelCli || panelCli.classList.contains('d-none')) {
            return;
        }
        setTimeout(() => enfocarCampoClienteInternoCodigo(), 0);
    }

    function enfocarPrimerCampoPendienteModalF8() {
        if (!tieneDescuentoEnPantalla()) {
            enfocarCampoDescuentoCodigo();
            return;
        }
        const errCli = validarClienteInternoDescuentoEnPantalla();
        if (errCli) {
            enfocarCampoClienteInternoCodigo();
            return;
        }
        const btn = document.getElementById('modal-f8-descuento-confirmar');
        if (btn && typeof btn.focus === 'function') {
            btn.focus();
            return;
        }
        enfocarCampoDescuentoCodigo();
    }

    function tieneDescuentoEnPantalla() {
        return !!(document.getElementById('descuento_gastronomia_id').value || '').trim();
    }

    function validarClienteInternoDescuentoEnPantalla() {
        if (!tieneDescuentoEnPantalla()) {
            return null;
        }
        const cliId = (document.getElementById('cliente_descuento_id').value || '').trim();
        if (!cliId) {
            return 'Indique el cliente interno del descuento (quien invita o centro de costos). No es el cliente de la factura.';
        }
        return null;
    }

    function validarDescuentoConCliente(data) {
        if (!data || !data.id) {
            throw new Error('Debe seleccionar un descuento gastronomía.');
        }
        if (typeof mostrarPanelClienteInternoDescuento === 'function') {
            mostrarPanelClienteInternoDescuento(true);
        }
        const errCli = validarClienteInternoDescuentoEnPantalla();
        if (errCli) {
            throw new Error(errCli);
        }
    }

    function validarDescuentoEnPantalla(exigirDescuento) {
        if (exigirDescuento && !tieneDescuentoEnPantalla()) {
            return 'Debe indicar un descuento gastronomía (F8 o campo Descuento).';
        }
        return validarClienteInternoDescuentoEnPantalla();
    }

    function cuentaTieneCanjePendienteRequiereF8(cuenta) {
        const c = cuenta || cuentaActivaConLineas;
        if (!c) {
            return false;
        }
        const premio = c.canje_premio_pendiente;
        if (premio && typeof premio === 'object' && String(premio.numerocupon || '').trim() !== '') {
            return true;
        }
        const fidelidad = c.canje_fidelidad_pendiente;
        if (fidelidad && typeof fidelidad === 'object' && String(fidelidad.trackdata || '').trim() !== '') {
            return true;
        }
        return false;
    }

    function mensajeBloqueoFacturacionEfectivoPorCanje(cuenta) {
        if (!cuentaTieneCanjePendienteRequiereF8(cuenta)) {
            return null;
        }
        return (
            'Esta cuenta tiene un canje de premio o fidelidad pendiente. ' +
            'Use F8 «Facturar con descuento» (descuento obligatorio). No puede usar F5 ni facturar sin descuento.'
        );
    }

    let pendingClienteInternoResolver = null;
    let esperarClienteInternoCleanup = null;

    function limpiarEsperaClienteInterno(mensajeRechazo) {
        if (esperarClienteInternoCleanup) {
            esperarClienteInternoCleanup();
            esperarClienteInternoCleanup = null;
        }
        if (mensajeRechazo && pendingClienteInternoResolver) {
            pendingClienteInternoResolver.reject(new Error(mensajeRechazo));
        }
        pendingClienteInternoResolver = null;
    }

    async function cargarClienteInternoPorCodigoApi(codigo) {
        const cod = (codigo || '').trim();
        if (!cod) {
            throw new Error('Código de cliente interno vacío.');
        }
        const res = await fetch(appPath('/ventas/leerunclienteporcodigo/' + encodeURIComponent(cod)), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data || !data.id) {
            throw new Error('Cliente interno no encontrado.');
        }
        if (String(data.estado) !== '0') {
            throw new Error('Cliente ' + (data.nombre || cod) + ' no está activo.');
        }
        if (typeof aplicarClienteInternoDescuentoEnPantalla === 'function') {
            aplicarClienteInternoDescuentoEnPantalla(data);
        }
        return data;
    }

    function enfocarCampoDescuentoCodigo() {
        const el = document.getElementById('codigodescuento');
        if (el && typeof el.focus === 'function') {
            el.focus();
            if (typeof el.select === 'function') el.select();
        }
    }

    function enfocarCampoClienteInternoCodigo() {
        const el = document.getElementById('codigocliente_descuento');
        if (el && typeof el.focus === 'function') {
            el.focus();
            if (typeof el.select === 'function') el.select();
        }
    }

    function esperarClienteInternoEnCampo(silencioso) {
        return new Promise((resolve, reject) => {
            limpiarEsperaClienteInterno();
            const codInp = document.getElementById('codigocliente_descuento');
            if (!codInp) {
                reject(new Error('Campo de cliente interno no disponible.'));
                return;
            }

            pendingClienteInternoResolver = {
                resolve: (cli) => {
                    limpiarEsperaClienteInterno();
                    resolve(cli);
                },
                reject: (err) => {
                    limpiarEsperaClienteInterno();
                    reject(err);
                },
            };

            const onKey = async (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    e.stopPropagation();
                    const cod = (codInp.value || '').trim();
                    if (!cod) {
                        toast('Ingrese el código del cliente interno.', 'warning');
                        return;
                    }
                    try {
                        const cli = await cargarClienteInternoPorCodigoApi(cod);
                        // aplicarClienteInternoDescuentoEnPantalla ya resolvió vía gastroOnClienteInternoDescuentoElegido
                        if (pendingClienteInternoResolver) {
                            pendingClienteInternoResolver.resolve(cli);
                        }
                    } catch (err) {
                        toast(err.message || String(err), 'error');
                    }
                    return;
                }
                if (e.key === 'Tab' && e.shiftKey) {
                    e.preventDefault();
                    enfocarCampoDescuentoCodigo();
                }
            };

            esperarClienteInternoCleanup = () => {
                codInp.removeEventListener('keydown', onKey, true);
            };

            if (!silencioso) {
                toast('Indique cliente interno del descuento (código + Enter) o use la lupa.', 'info');
            }
            codInp.addEventListener('keydown', onKey, true);
            enfocarCampoClienteInternoCodigo();
        });
    }

    async function asegurarClienteInternoDescuento(silencioso) {
        if (!tieneDescuentoEnPantalla()) {
            return;
        }
        if (typeof mostrarPanelClienteInternoDescuento === 'function') {
            mostrarPanelClienteInternoDescuento(true);
        }
        const err = validarClienteInternoDescuentoEnPantalla();
        if (!err) {
            return;
        }
        if (descuentoEnModalF8()) {
            enfocarCampoClienteInternoCodigo();
            throw new Error(
                'Indique el cliente interno del descuento (código + Enter o lupa) y pulse Facturar.',
            );
        }
        await esperarClienteInternoEnCampo(silencioso);
        const err2 = validarClienteInternoDescuentoEnPantalla();
        if (err2) {
            throw new Error(err2);
        }
    }

    let recalculandoTotalDescuento = false;

    async function recalcularTotalCuentaConDescuento() {
        if (!cuentaId || recalculandoTotalDescuento) return null;
        recalculandoTotalDescuento = true;
        try {
            return await guardarCabecera(true);
        } catch (e) {
            toast('No se pudo actualizar el total con el descuento: ' + e.message, 'warning');
            throw e;
        } finally {
            recalculandoTotalDescuento = false;
        }
    }

    function esperarDescuentoEnCampo(silencioso) {
        return new Promise((resolve, reject) => {
            limpiarEsperaDescuento();
            const codInp = document.getElementById('codigodescuento');
            if (!codInp) {
                reject(new Error('Campo de descuento no disponible.'));
                return;
            }

            pendingDescuentoResolver = {
                resolve: (data) => {
                    limpiarEsperaDescuento();
                    resolve(data);
                },
                reject: (err) => {
                    limpiarEsperaDescuento();
                    reject(err);
                },
            };

            const onKey = async (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    e.stopPropagation();
                    const cod = (codInp.value || '').trim();
                    if (!cod) {
                        toast('Ingrese el código de descuento.', 'warning');
                        return;
                    }
                    try {
                        await cargarDescuentoPorCodigoApi(cod);
                    } catch (err) {
                        toast(err.message || String(err), 'error');
                    }
                    return;
                }
                if (e.key === 'Tab' && !e.shiftKey) {
                    const panelCli = document.getElementById('panel-cliente-descuento');
                    if (panelCli && !panelCli.classList.contains('d-none')) {
                        e.preventDefault();
                        enfocarCampoClienteInternoCodigo();
                    }
                }
            };

            codInp.addEventListener('keydown', onKey, true);
            esperarDescuentoCleanup = () => {
                codInp.removeEventListener('keydown', onKey, true);
            };

            if (!silencioso) {
                toast('Indique código de descuento (Enter) o use la lupa. Tab → cliente interno.', 'info');
            }
            enfocarCampoDescuentoCodigo();
        });
    }

    async function asegurarDescuentoObligatorio(opciones) {
        const silencioso = !!(opciones && opciones.silencioso);
        const descId = (document.getElementById('descuento_gastronomia_id').value || '').trim();
        const cod = (document.getElementById('codigodescuento').value || '').trim();
        let data = null;
        if (descId && cod) {
            try {
                data = await cargarDescuentoPorCodigoApi(cod);
            } catch (_) {
                data = { id: parseInt(descId, 10) };
            }
        } else if (cod) {
            data = await cargarDescuentoPorCodigoApi(cod);
        } else if (descuentoEnModalF8()) {
            throw new Error('Indique el código de descuento (Enter o lupa) y pulse Facturar.');
        } else {
            data = await esperarDescuentoEnCampo(silencioso);
        }
        await asegurarClienteInternoDescuento(silencioso);
        await recalcularTotalCuentaConDescuento();
        return data;
    }

    function envolverPintarDescuentoParaRecalculo() {
        if (typeof pintarDescuentoEnPantalla !== 'function' || pintarDescuentoEnPantalla._gastroEnvuelto) {
            return;
        }
        const prev = pintarDescuentoEnPantalla;
        const envuelto = function (data) {
            if (data && data.id) {
                lastDescuentoGastronomiaMeta = {
                    tipovalor: data.tipovalor,
                    valor: data.valor,
                };
            } else {
                lastDescuentoGastronomiaMeta = null;
            }
            prev(data);
            if (pendingDescuentoResolver && data && data.id) {
                pendingDescuentoResolver.resolve(data);
            }
            if (data && data.id) {
                enfocarClienteInternoTrasDescuento();
                if (cuentaId) {
                    const cliId = (document.getElementById('cliente_descuento_id').value || '').trim();
                    if (cliId) {
                        void recalcularTotalCuentaConDescuento();
                    }
                }
            }
        };
        envuelto._gastroEnvuelto = true;
        window.pintarDescuentoEnPantalla = envuelto;
    }

    function enfocarGrillaCobranza() {
        const tbody = document.getElementById('tbody-gastro-cuenta-table');
        const tr = tbody ? tbody.querySelector('tr') : null;
        const inp = tr ? tr.querySelector('.codigocuentacaja, .monto') : null;
        if (inp && typeof inp.focus === 'function') {
            inp.focus();
            if (typeof inp.select === 'function') {
                inp.select();
            }
            return;
        }
        const panel = document.getElementById('panel-cobranza-compacta');
        if (panel && typeof panel.scrollIntoView === 'function') {
            panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    async function facturarConDescuento() {
        if (bloquearOperacionPosPorJornadaTurno()) {
            return;
        }
        if (!cuentaId) {
            return toast('Seleccione una mesa o cuenta con consumos.', 'warning');
        }
        if (!cuentaTieneLineasFacturables()) {
            return toast('La cuenta no tiene artículos para facturar.', 'warning');
        }

        try {
            await abrirModalF8Descuento();
            const cuenta = await guardarCabecera(true);
            const esCortesia = esFacturaCortesia(null, cuenta);
            if (!esCortesia && !recogerMediosPagoFromGrid().length) {
                const total = totalFacturarDesdeCuenta(cuenta);
                setTotalFacturadoArs(total > 0 ? total : 0);
                toast(
                    'Descuento aplicado. Indique el medio de cobro en la grilla inferior y pulse F8 de nuevo (o Facturar).',
                    'info',
                );
                enfocarGrillaCobranza();
                return;
            }
            await emitirFactura({
                exigirDescuento: true,
                prepararCobranzaSiFalta: esCortesia ? false : false,
                cuentaPrecargada: cuenta,
            });
        } catch (e) {
            if (e && e.message && e.message !== 'Operación cancelada.') {
                toast(e.message || String(e), 'error');
            }
        }
    }

    function cuentaEsWaitryCobroTotem(cuenta) {
        return !!(cuenta && cuenta.waitry_cobro_totem && cuenta.waitry_order_id);
    }

    function cuentaWaitryImpaga(cuenta) {
        return !!(cuenta && cuenta.waitry_order_id && !cuenta.waitry_cobro_totem);
    }

    function esCuentacajaTotem(cuentaCaja) {
        if (!cuentaCaja || !cuentaCaja.id) {
            return false;
        }
        const totem = G.cuentacajaTotem;
        if (totem && parseInt(totem.id, 10) === parseInt(cuentaCaja.id, 10)) {
            return true;
        }
        const cod = String(cuentaCaja.codigo || '')
            .trim()
            .toUpperCase();
        const totemCod = String((totem && totem.codigo) || G.cuentacajaTotemCodigo || 'TOTEM')
            .trim()
            .toUpperCase();
        return cod !== '' && cod === totemCod;
    }

    function normalizarTipoWaitry(tipo) {
        return String(tipo || '')
            .trim()
            .toLowerCase()
            .replace(/[\s_-]+/g, '');
    }

    function resolverCuentacajaWaitryTotem(cuenta) {
        return G.cuentacajaTotem || null;
    }

    function etiquetaMedioWaitry(cuenta) {
        const tipo = normalizarTipoWaitry(cuenta && cuenta.waitry_tipo_pago);
        const map = G.waitryTipoPagoCuentacaja || {};
        if (tipo && map[tipo] && map[tipo].etiqueta) {
            return map[tipo].etiqueta;
        }
        if (tipo === 'mercadopago') {
            return 'Mercado Pago';
        }
        if (tipo === 'totalcoin' || tipo === 'creditcard') {
            return 'QR (Totalcoin / tótem)';
        }
        return String((G.cuentacajaTotem && G.cuentacajaTotem.codigo) || G.cuentacajaTotemCodigo || 'TOTEM');
    }

    function esCuentacajaWaitryAutomatica(cuentaCaja) {
        if (!cuentaCaja || !cuentaCaja.id) {
            return false;
        }
        if (esCuentacajaTotem(cuentaCaja)) {
            return true;
        }
        const id = parseInt(cuentaCaja.id, 10);
        const map = G.waitryTipoPagoCuentacaja || {};
        return Object.values(map).some((cc) => cc && parseInt(cc.id, 10) === id);
    }

    function esCuentacajaCanjeTarjeta(cuentaCaja) {
        if (!cuentaCaja || !cuentaCaja.id) {
            return false;
        }
        const ctg = G.cuentacajaCanjeTarjeta;
        if (ctg && parseInt(ctg.id, 10) === parseInt(cuentaCaja.id, 10)) {
            return true;
        }
        const cod = String(cuentaCaja.codigo || '')
            .trim()
            .toUpperCase();
        const ctgCod = String((ctg && ctg.codigo) || G.cuentacajaCanjeTarjetaCodigo || 'CTG')
            .trim()
            .toUpperCase();
        return cod !== '' && cod === ctgCod;
    }

    function esCuentacajaSoloAutomatica(cuentaCaja) {
        if (!cuentaCaja || !cuentaCaja.id) {
            return false;
        }
        if (esCuentacajaCanjeTarjeta(cuentaCaja)) {
            return true;
        }
        return esCuentacajaTotem(cuentaCaja);
    }

    function rechazarCuentacajaSoloAutomaticaManual(cuentaCaja) {
        if (!esCuentacajaSoloAutomatica(cuentaCaja)) {
            return false;
        }
        let msg;
        if (esCuentacajaCanjeTarjeta(cuentaCaja)) {
            msg =
                'La cuenta CTG solo puede usarse mediante canje de ticket tarjeta gastronomía (no manualmente).';
        } else {
            msg =
                'La cuenta TOTEM se asigna automáticamente al importar una orden Waitry ya cobrada en el tótem.';
        }
        toast(msg, 'warning');
        return true;
    }

    function actualizarAvisoCobranzaWaitryTotem(visible, cuenta) {
        const el = document.getElementById('gastro-waitry-totem-aviso');
        const lbl = document.getElementById('gastro-waitry-medio-label');
        if (el) {
            el.classList.toggle('d-none', !visible);
        }
        if (lbl && visible) {
            lbl.textContent = etiquetaMedioWaitry(cuenta || cuentaActivaConLineas);
        }
        const btnAgregar = document.getElementById('gastro-agrega-renglon-cuenta');
        const mediosRapidos = document.getElementById('gastro-medios-rapidos');
        if (btnAgregar) {
            btnAgregar.disabled = !!visible;
        }
        if (mediosRapidos) {
            mediosRapidos.classList.toggle('d-none', !!visible);
            mediosRapidos.querySelectorAll('.gastro-medio-rapido').forEach((btn) => {
                btn.disabled = !!visible;
            });
        }
    }

    function bloquearGrillaCobranzaWaitryTotem(bloquear, cuenta) {
        cobranzaWaitryTotemBloqueada = !!bloquear;
        actualizarAvisoCobranzaWaitryTotem(cobranzaWaitryTotemBloqueada, cuenta);
        document.querySelectorAll('#tbody-gastro-cuenta-table tr').forEach((tr) => {
            const codInp = tr.querySelector('.codigo');
            const montoInp = tr.querySelector('.monto');
            const btnConsulta = tr.querySelector('.consultacuentacaja');
            const btnDel = tr.querySelector('.gastro-eliminar-cuenta');
            if (codInp) {
                codInp.readOnly = bloquear;
            }
            if (montoInp) {
                montoInp.readOnly = bloquear;
            }
            if (btnConsulta) {
                btnConsulta.disabled = bloquear;
            }
            if (btnDel) {
                btnDel.disabled = bloquear;
            }
        });
    }

    async function prepararCobranzaWaitryTotem(cuenta, montoArs) {
        let cc = resolverCuentacajaWaitryTotem(cuenta);
        if (!cc || !cc.id) {
            try {
                const cfg = await api('/ventas/gastronomia/api/config', { headers: hdrJson() });
                if (cfg.waitry_tipo_pago_cuentacaja) {
                    G.waitryTipoPagoCuentacaja = cfg.waitry_tipo_pago_cuentacaja;
                }
                if (cfg.cuentacaja_totem && cfg.cuentacaja_totem.id) {
                    G.cuentacajaTotem = cfg.cuentacaja_totem;
                } else if (cfg.cuentacaja_totem_error) {
                    G.cuentacajaTotemError = cfg.cuentacaja_totem_error;
                }
                cc = resolverCuentacajaWaitryTotem(cuenta);
            } catch (_) {
                /* usar mensaje genérico */
            }
        }
        if (!cc || !cc.id) {
            throw new Error(
                G.cuentacajaTotemError ||
                    'Configure la cuenta de caja TOTEM (puente Waitry) para la empresa de esta terminal.',
            );
        }
        if (G.cobranzaConfigError) {
            throw new Error(G.cobranzaConfigError);
        }
        limpiarCobranza({ filaVacia: false });
        setTotalFacturadoArs(montoArs);
        const tbody = document.getElementById('tbody-gastro-cuenta-table');
        const tr = filaCobranzaDesdeTemplate();
        if (!tr || !tbody) {
            throw new Error('No se pudo preparar la cobranza Waitry del tótem.');
        }
        tbody.appendChild(tr);
        wireEventosFilaCobranza(tr);
        asignarCuentaCajaEnFila(tr, cc, { asignacionAutomatica: true });
        const montoInp = tr.querySelector('.monto');
        if (montoInp) {
            montoInp.value = Number(montoArs).toFixed(2);
            montoInp.dataset.montoEditadoManual = '1';
        }
        sumarMontosCobranza();
        if (!recogerMediosPagoFromGrid().length) {
            throw new Error('No se pudo preparar el medio de cobro Waitry.');
        }
        bloquearGrillaCobranzaWaitryTotem(true, cuenta);
    }

    async function prepararCobranzaTotem(montoArs) {
        return prepararCobranzaWaitryTotem(cuentaActivaConLineas, montoArs);
    }

    async function aplicarCobranzaWaitryTotemSiCorresponde(cuenta) {
        const idCuenta = cuenta && cuenta.id != null ? Number(cuenta.id) : null;
        const cambioCuenta = idCuenta !== cuentaIdCobranzaVinculada;
        const montoArs = subtotalEstimadoDesdeCuenta(cuenta);

        if (cuentaEsWaitryCobroTotem(cuenta) && montoArs > 0) {
            try {
                await prepararCobranzaWaitryTotem(cuenta, montoArs);
            } catch (e) {
                toast(e.message || 'Error al preparar cobranza Waitry', 'error');
            }
            cuentaIdCobranzaVinculada = idCuenta;
            return;
        }

        if (cambioCuenta || cobranzaWaitryTotemBloqueada) {
            bloquearGrillaCobranzaWaitryTotem(false);
            limpiarCobranza();
            setTotalFacturadoArs(montoArs);
            cuentaIdCobranzaVinculada = idCuenta;
        }
    }

    async function prepararCobranzaEfectivo(montoArs) {
        let cc = G.cuentacajaEfectivo;
        if (!cc || !cc.id) {
            try {
                const cfg = await api('/ventas/gastronomia/api/config', { headers: hdrJson() });
                if (cfg.cuentacaja_efectivo && cfg.cuentacaja_efectivo.id) {
                    G.cuentacajaEfectivo = cfg.cuentacaja_efectivo;
                    cc = G.cuentacajaEfectivo;
                } else if (cfg.cuentacaja_efectivo_error) {
                    G.cuentacajaEfectivoError = cfg.cuentacaja_efectivo_error;
                }
            } catch (e) {
                /* usar mensaje genérico */
            }
        }
        if (!cc || !cc.id) {
            throw new Error(
                G.cuentacajaEfectivoError ||
                    'Configure GASTRONOMIA_CUENTACAJA_EFECTIVO_POR_EMPRESA para la empresa de esta terminal (ej. {"1":25,"2":24,"3":23}).',
            );
        }
        if (G.cobranzaConfigError) {
            throw new Error(G.cobranzaConfigError);
        }
        const monEfectivo = parseInt(String(cc.moneda_id || MONEDA_PESOS_ID), 10);
        if (monEfectivo <= MONEDA_PESOS_ID) {
            cotizacionExtranjera = { monedaId: null, cotizacion: 1, abrev: '' };
            const hCot = document.getElementById('gastro-cotizacion-extranjera');
            const hMon = document.getElementById('gastro-moneda-extranjera-id');
            if (hCot) hCot.value = '';
            if (hMon) hMon.value = '';
            actualizarBarraCotizacion();
        }
        limpiarCobranza({ filaVacia: false });
        setTotalFacturadoArs(montoArs);
        const tbody = document.getElementById('tbody-gastro-cuenta-table');
        const tr = filaCobranzaDesdeTemplate();
        if (!tr || !tbody) {
            throw new Error('No se pudo preparar la cobranza en efectivo.');
        }
        tbody.appendChild(tr);
        wireEventosFilaCobranza(tr);
        asignarCuentaCajaEnFila(tr, cc);
        const ccId = (tr.querySelector('.cuentacaja_id')?.value || '').trim();
        const monId = parseInt(tr.querySelector('.moneda_id')?.value || '0', 10);
        if (!ccId || !monId) {
            throw new Error(
                'No se pudo asignar la cuenta de caja de efectivo en la grilla. Revise GASTRONOMIA_CUENTACAJA_EFECTIVO_POR_EMPRESA.',
            );
        }
        await revisarMonedaExtranjeraEnGrilla();
        actualizarDatoSaldoMonto(tr);
        const montoInp = tr.querySelector('.monto');
        const montoFila = saldoPendienteEnMonedaFila(tr);
        if (montoInp) {
            montoInp.value = (montoFila > 0 ? montoFila : montoArs).toFixed(2);
            montoInp.dataset.montoEditadoManual = '1';
        }
        sumarMontosCobranza();
        if (!recogerMediosPagoFromGrid().length) {
            throw new Error(
                'No se pudo preparar el medio de cobro en efectivo (cuenta de caja, moneda o monto).',
            );
        }
    }

    async function efectivizar() {
        if (bloquearOperacionPosPorJornadaTurno()) {
            return;
        }
        if (!cuentaId) {
            return toast('Seleccione una mesa o cuenta con consumos.', 'warning');
        }
        if (!cuentaTieneLineasFacturables()) {
            return toast('La cuenta no tiene artículos para facturar.', 'warning');
        }
        const msgCanje = mensajeBloqueoFacturacionEfectivoPorCanje();
        if (msgCanje) {
            return toast(msgCanje, 'warning');
        }
        try {
            await emitirFactura({ prepararCobranzaSiFalta: true });
        } catch (e) {
            toast(e.message || String(e), 'error');
        }
    }

    function validarCobranzaConMedios(medios) {
        if (!medios || medios.length === 0) {
            return 'Indique al menos un medio de cobro (cuenta de caja y monto).';
        }
        let totalArs = 0;
        medios.forEach((m) => {
            const cot = m.cotizacion || cotizacionFilaCobranza(m.moneda_id);
            totalArs += m.monto * coeficienteAPesos(m.moneda_id, cot);
        });
        if (totalFacturadoArs > 0 && Math.abs(totalArs - totalFacturadoArs) > TOLERANCIA_MONTO_COBRANZA) {
            return (
                'El total de cobranza ($ ' +
                totalArs.toFixed(2) +
                ') debe coincidir con el total a facturar ($ ' +
                totalFacturadoArs.toFixed(2) +
                ').'
            );
        }
        return null;
    }

    function validarCobranzaAntesFacturar() {
        const medios = recogerMediosPagoFromGrid();
        const errMedios = validarCobranzaConMedios(medios);
        if (errMedios) {
            return errMedios;
        }
        for (const tr of document.querySelectorAll('#tbody-gastro-cuenta-table tr')) {
            if (!validarMontoCobranza(tr)) {
                return 'Revise los montos de cobranza antes de facturar.';
            }
        }
        return null;
    }

    function labelCodigoNombre(codigo, nombre) {
        const c = codigo != null && String(codigo).trim() !== '' ? String(codigo).trim() + ' — ' : '';
        return c + (nombre || '');
    }

    function etiquetaMesa(mesa) {
        if (!mesa) return '';
        if (mesa.numeromesa != null && String(mesa.numeromesa).trim() !== '') {
            return 'Mesa ' + mesa.numeromesa;
        }
        if (mesa.nombre && String(mesa.nombre).trim() !== '') {
            return 'Mesa ' + mesa.nombre;
        }
        if (mesa.codigo && String(mesa.codigo).trim() !== '') {
            return 'Mesa ' + mesa.codigo;
        }
        return 'Mesa';
    }

    function etiquetaCuentaActiva(cuenta) {
        if (!cuenta) {
            return { titulo: '', detalle: '', esMesa: false, mesaId: null };
        }
        const id = cuenta.id;
        const esMesa = cuenta.tipo === 'mesa' || !!cuenta.mesa_gastronomia_id;
        if (!cuenta.tipo && !cuenta.mesa_gastronomia_id && !cuenta.mesa) {
            return {
                titulo: 'Cuenta #' + id,
                detalle: 'Cargando datos…',
                esMesa: false,
                mesaId: null,
            };
        }
        if (esMesa) {
            const titulo = etiquetaMesa(cuenta.mesa) || 'Mesa (cuenta #' + id + ')';
            let detalle = 'Cuenta interna #' + id;
            if (cuenta.cubiertos > 0) {
                detalle += ' · ' + cuenta.cubiertos + ' cubiertos';
            }
            if (cuenta.mozo && cuenta.mozo.nombre) {
                detalle += ' · Mozo: ' + cuenta.mozo.nombre;
            }
            return {
                titulo,
                detalle,
                esMesa: true,
                mesaId: cuenta.mesa_gastronomia_id || (cuenta.mesa ? cuenta.mesa.id : null),
            };
        }
        return {
            titulo: 'Cuenta libre #' + id,
            detalle: 'Sin mesa asignada',
            esMesa: false,
            mesaId: null,
        };
    }

    function lineaIndicadorCuentaActiva(cuenta, subtotal) {
        const info = etiquetaCuentaActiva(cuenta);
        let linea = info.titulo;
        if (info.detalle) {
            linea += ' · ' + info.detalle;
        }
        if (subtotal != null && subtotal > 0) {
            linea += ' · Subtotal $' + Number(subtotal).toFixed(2);
        }
        return { linea, info };
    }

    function actualizarIndicadorCuentaActiva(cuenta, subtotal) {
        const bar = document.getElementById('gastro-bar-cuenta-activa');
        const lineaEl = document.getElementById('gastro-cuenta-activa-linea');
        const headerChip = document.getElementById('gastro-header-cuenta-chip');
        const badgeCuenta = document.getElementById('gastro-indicador-cuenta');

        if (!cuentaId || !cuenta) {
            cuentaActivaMesaId = null;
            if (bar) bar.classList.add('d-none');
            if (lineaEl) {
                lineaEl.textContent = '—';
                lineaEl.removeAttribute('title');
            }
            if (headerChip) {
                headerChip.classList.add('d-none');
                headerChip.textContent = '';
                headerChip.classList.remove('es-mesa', 'es-cuenta');
            }
            if (badgeCuenta) {
                badgeCuenta.classList.add('d-none');
                badgeCuenta.textContent = '';
                badgeCuenta.classList.remove('badge-warning', 'badge-info');
            }
            return;
        }

        const { linea, info } = lineaIndicadorCuentaActiva(cuenta, subtotal);
        cuentaActivaMesaId = info.mesaId || null;

        if (bar) bar.classList.remove('d-none');
        if (lineaEl) {
            lineaEl.textContent = linea;
            lineaEl.setAttribute('title', linea);
        }

        if (headerChip) {
            headerChip.textContent = info.titulo;
            headerChip.classList.remove('d-none', 'es-mesa', 'es-cuenta');
            headerChip.classList.add(info.esMesa ? 'es-mesa' : 'es-cuenta');
            headerChip.setAttribute('aria-hidden', 'false');
        }

        if (badgeCuenta) {
            badgeCuenta.textContent = info.titulo;
            badgeCuenta.classList.remove('d-none', 'badge-warning', 'badge-info', 'badge-dark');
            badgeCuenta.classList.add(info.esMesa ? 'badge-warning' : 'badge-info');
        }
    }

    function tieneClienteMaestroAsignado() {
        const cid = document.getElementById('cliente_id');
        return !!(cid && String(cid.value || '').trim() !== '');
    }

    function actualizarPanelReceptorManual(subtotal) {
        const panel = document.getElementById('panel-factura-receptor-manual');
        if (!panel) return;
        const requiere =
            wsfeReceptorCfUmbralMonto > 0 &&
            subtotal >= wsfeReceptorCfUmbralMonto &&
            !tieneClienteMaestroAsignado();
        panel.classList.toggle('d-none', !requiere);
    }

    function limpiarEstadoCuentaActiva() {
        cuentaId = null;
        cuentaActivaMesaId = null;
        cuentaActivaSubtotalArs = null;
        cuentaActivaConLineas = null;
        lastDescuentoGastronomiaMeta = null;
        actualizarIndicadorCuentaActiva(null);
        document.getElementById('btn-cerrar-cuenta').classList.add('d-none');
        document.getElementById('panel-detalle-lineas').innerHTML = '';
        document.getElementById('cliente_id').value = '';
        document.getElementById('nombrecliente').value = '';
        document.getElementById('codigocliente').value = '';
        const mozoId = document.getElementById('mozo_gastronomia_id');
        const mozoCod = document.getElementById('codigomozo');
        const mozoNom = document.getElementById('nombremozo');
        if (mozoId) mozoId.value = '';
        if (mozoCod) mozoCod.value = '';
        if (mozoNom) mozoNom.value = '';
        const fldCub = document.getElementById('fld-cubiertos');
        if (fldCub) fldCub.value = String(cubiertosDefaultApertura());
        if (typeof pintarDescuentoEnPantalla === 'function') {
            pintarDescuentoEnPantalla(null);
        } else {
            const descId = document.getElementById('descuento_gastronomia_id');
            const descCod = document.getElementById('codigodescuento');
            const descNom = document.getElementById('nombredescuento');
            if (descId) descId.value = '';
            if (descCod) descCod.value = '';
            if (descNom) descNom.value = '';
        }
        limpiarFormularioArticuloLinea();
        const fn = document.getElementById('fld-factura-receptor-nombre');
        const fd = document.getElementById('fld-factura-receptor-documento');
        const fdom = document.getElementById('fld-factura-receptor-domicilio');
        if (fn) fn.value = '';
        if (fd) fd.value = '';
        if (fdom) fdom.value = '';
        actualizarPanelReceptorManual(0);
        bloquearGrillaCobranzaWaitryTotem(false);
        cuentaIdCobranzaVinculada = null;
        limpiarCobranza();
        focusSkuConsumo();
    }

    const MONEDA_PESOS_ID = 1;
    const monedaAbrevPorId = {};
    let cuentacajaxcodigo = null;
    let totalFacturadoArs = 0;
    let cotizacionExtranjera = { monedaId: null, cotizacion: 1, abrev: '' };

    async function cargarMonedasFactura() {
        const data = await api('/ventas/gastronomia/api/monedas', { headers: hdrJson() });
        (data.monedas || []).forEach((m) => {
            monedaAbrevPorId[m.id] = m.abreviatura || m.nombre || String(m.id);
        });
        const hid = document.getElementById('factura-moneda-id');
        if (hid) hid.value = MONEDA_PESOS_ID;
    }

    function coeficienteAPesos(monedaId, cotizacion) {
        const mon = parseInt(String(monedaId), 10);
        const cot = parseFloat(cotizacion) || 1;
        if (typeof calculaCoeficienteMoneda === 'function') {
            return calculaCoeficienteMoneda(MONEDA_PESOS_ID, mon, cot);
        }
        if (mon === MONEDA_PESOS_ID) return 1;
        if (mon > MONEDA_PESOS_ID) return cot;
        return 1;
    }

    const TOLERANCIA_MONTO_COBRANZA = 0.02;

    function cotizacionFilaCobranza(monId) {
        return monId > MONEDA_PESOS_ID && cotizacionExtranjera.monedaId === monId
            ? cotizacionExtranjera.cotizacion
            : 1;
    }

    function montoCobranzaEnArs(tr) {
        const montoInp = tr.querySelector('.monto');
        const monId = parseInt(tr.querySelector('.moneda_id')?.value || '0', 10);
        const val = parseFloat(montoInp?.value || '');
        if (!monId || Number.isNaN(val) || val === 0) return 0;
        return val * coeficienteAPesos(monId, cotizacionFilaCobranza(monId));
    }

    function totalCobranzaArsAntesDe(tr) {
        let total = 0;
        document.querySelectorAll('#tbody-gastro-cuenta-table tr').forEach((row) => {
            if (row === tr) return;
            total += montoCobranzaEnArs(row);
        });
        return total;
    }

    function saldoPendienteArs(tr) {
        return Math.max(0, totalFacturadoArs - totalCobranzaArsAntesDe(tr));
    }

    function saldoPendienteEnMonedaFila(tr) {
        const monId = parseInt(tr.querySelector('.moneda_id')?.value || '0', 10);
        const saldoArs = saldoPendienteArs(tr);
        if (saldoArs <= 0) return 0;
        const coef = coeficienteAPesos(monId || MONEDA_PESOS_ID, cotizacionFilaCobranza(monId));
        if (coef <= 0) return saldoArs;
        return Math.round((saldoArs / coef) * 100) / 100;
    }

    function montoCobranzaVacioOEsSugerido(montoInp) {
        if (montoInp.dataset.montoEditadoManual === '1') {
            return false;
        }
        const val = (montoInp.value || '').trim();
        if (val === '') return true;
        const cur = parseFloat(val);
        const prev = parseFloat(montoInp.dataset.saldoValidacion || '');
        if (Number.isNaN(cur)) return true;
        if (!Number.isNaN(prev) && Math.abs(cur - prev) < TOLERANCIA_MONTO_COBRANZA) return true;
        return false;
    }

    function actualizarDatoSaldoMonto(tr) {
        const montoInp = tr.querySelector('.monto');
        if (!montoInp) return;
        const saldoMon = saldoPendienteEnMonedaFila(tr);
        const saldoArs = saldoPendienteArs(tr);
        const cuentaId = tr.querySelector('.cuentacaja_id')?.value;
        if (totalFacturadoArs > 0 && cuentaId && saldoMon > 0 && montoCobranzaVacioOEsSugerido(montoInp)) {
            montoInp.value = saldoMon.toFixed(2);
            delete montoInp.dataset.montoEditadoManual;
        }
        montoInp.dataset.saldoValidacion = saldoMon.toFixed(2);
        montoInp.dataset.saldoValidacionArs = saldoArs.toFixed(2);
        if (totalFacturadoArs > 0) {
            const abr =
                monedaAbrevPorId[parseInt(tr.querySelector('.moneda_id')?.value || '0', 10)] || '$';
            montoInp.placeholder = '';
            montoInp.title =
                'Saldo pendiente de la factura: $ ' +
                saldoArs.toFixed(2) +
                ' · máximo en ' +
                abr +
                ': ' +
                saldoMon.toFixed(2);
        } else {
            montoInp.placeholder = '';
            montoInp.removeAttribute('title');
            delete montoInp.dataset.saldoValidacion;
            delete montoInp.dataset.saldoValidacionArs;
            delete montoInp.dataset.montoEditadoManual;
        }
    }

    function actualizarDatosSaldoTodasFilas() {
        document.querySelectorAll('#tbody-gastro-cuenta-table tr').forEach(actualizarDatoSaldoMonto);
    }

    function validarMontoCobranza(tr, silencioso) {
        const montoInp = tr.querySelector('.monto');
        if (!montoInp || totalFacturadoArs <= 0) return true;
        const val = parseFloat(montoInp.value || '');
        if (Number.isNaN(val) || val === 0) return true;
        const maxMon = saldoPendienteEnMonedaFila(tr);
        if (val > maxMon + TOLERANCIA_MONTO_COBRANZA) {
            if (!silencioso) {
                toast(
                    'El monto supera el saldo pendiente ($ ' +
                        saldoPendienteArs(tr).toFixed(2) +
                        '). Máximo: ' +
                        maxMon.toFixed(2),
                    'warning',
                );
                montoInp.focus();
                montoInp.select();
            }
            return false;
        }
        return true;
    }

    async function cargarCotizacionMoneda(monedaId) {
        const mid = parseInt(String(monedaId), 10);
        if (!(mid > MONEDA_PESOS_ID)) {
            cotizacionExtranjera = { monedaId: null, cotizacion: 1, abrev: '' };
            actualizarBarraCotizacion();
            return;
        }
        try {
            const cot = await api(`/ventas/gastronomia/api/cotizacion?moneda_id=${mid}`, { headers: hdrJson() });
            cotizacionExtranjera = {
                monedaId: mid,
                cotizacion: parseFloat(cot.cotizacion) || 1,
                abrev: monedaAbrevPorId[mid] || String(mid),
            };
            const hCot = document.getElementById('gastro-cotizacion-extranjera');
            const hMon = document.getElementById('gastro-moneda-extranjera-id');
            if (hCot) hCot.value = cotizacionExtranjera.cotizacion;
            if (hMon) hMon.value = mid;
        } catch (e) {
            toast('No se pudo cargar cotización: ' + e.message, 'warning');
            cotizacionExtranjera = { monedaId: null, cotizacion: 1, abrev: '' };
        }
        actualizarBarraCotizacion();
    }

    function actualizarBarraCotizacion() {
        const bar = document.getElementById('gastro-cobranza-cotiz-bar');
        if (!bar) return;
        if (cotizacionExtranjera.monedaId > MONEDA_PESOS_ID) {
            bar.classList.remove('d-none');
            bar.textContent =
                'Cotización ' +
                cotizacionExtranjera.abrev +
                ': ' +
                cotizacionExtranjera.cotizacion.toFixed(4) +
                ' (aplica a todas las cuentas en moneda extranjera)';
        } else {
            bar.classList.add('d-none');
            bar.textContent = '';
        }
    }

    async function revisarMonedaExtranjeraEnGrilla() {
        let monedaExt = null;
        document.querySelectorAll('#tbody-gastro-cuenta-table tr').forEach((tr) => {
            const mid = parseInt(tr.querySelector('.moneda_id')?.value || '0', 10);
            if (mid > MONEDA_PESOS_ID) monedaExt = mid;
        });
        if (!monedaExt) {
            cotizacionExtranjera = { monedaId: null, cotizacion: 1, abrev: '' };
            const hCot = document.getElementById('gastro-cotizacion-extranjera');
            const hMon = document.getElementById('gastro-moneda-extranjera-id');
            if (hCot) hCot.value = '';
            if (hMon) hMon.value = '';
            actualizarBarraCotizacion();
            return;
        }
        if (cotizacionExtranjera.monedaId !== monedaExt) {
            await cargarCotizacionMoneda(monedaExt);
        }
    }

    function resolverIconoCuentacaja(cuenta) {
        if (!cuenta) {
            return { icono: 'fa fa-search', color: 'text-primary' };
        }
        if (cuenta.icono) {
            return {
                icono: cuenta.icono,
                color: cuenta.icono_color || cuenta.color || 'text-primary',
            };
        }
        const mapa = G.cuentasCajaGastronomiaPorId || {};
        const cfg = mapa[String(cuenta.id)] || mapa[parseInt(cuenta.id, 10)];
        if (cfg && cfg.icono) {
            return {
                icono: cfg.icono,
                color: cfg.icono_color || 'text-primary',
            };
        }
        return { icono: 'fa fa-search', color: 'text-primary' };
    }

    function htmlIconoMedio(info) {
        const icono = info && info.icono ? info.icono : 'fa fa-search';
        const color = info && info.color ? info.color : 'text-primary';
        if (icono.indexOf('gastro-icon-') === 0) {
            return '<span class="' + icono + '" aria-hidden="true"></span>';
        }
        return '<i class="' + icono + ' ' + color + '" aria-hidden="true"></i>';
    }

    function actualizarIconoConsultaFila(tr, cuenta) {
        if (!tr) return;
        const btnConsulta = tr.querySelector('.consultacuentacaja');
        if (!btnConsulta) return;
        btnConsulta.querySelectorAll('i, .gastro-icon-mercadopago').forEach((el) => el.remove());
        btnConsulta.insertAdjacentHTML('afterbegin', htmlIconoMedio(resolverIconoCuentacaja(cuenta)));
    }

    function etiquetaCortaMedioPago(cuenta) {
        if (!cuenta) return '';
        if (cuenta.etiqueta_boton) {
            return String(cuenta.etiqueta_boton);
        }
        const codigo = String(cuenta.codigo || '').trim();
        if (codigo) return codigo;
        const nombre = String(cuenta.nombre || '').trim();
        if (!nombre) return 'Medio';
        const palabras = nombre.split(/\s+/).filter(Boolean);
        if (palabras.length <= 2) return nombre;
        return palabras.slice(0, 2).join(' ');
    }

    function construirListaMediosConCanje(cuentas) {
        const lista = Array.isArray(cuentas) ? cuentas.slice() : [];
        const ctg = G.cuentacajaCanjeTarjeta;
        if (ctg && ctg.id && !lista.some((c) => Number(c.id) === Number(ctg.id))) {
            lista.push({
                ...ctg,
                accion: 'canje-ticket',
                etiqueta_boton: ctg.etiqueta_boton || 'Canje tarjeta',
                icono: ctg.icono || 'fa fa-barcode',
                icono_color: ctg.icono_color || 'text-primary',
            });
        }
        return lista;
    }

    function renderMediosPagoRapidos(cuentas) {
        const wrap = document.getElementById('gastro-medios-rapidos');
        if (!wrap) return;
        const lista = construirListaMediosConCanje(cuentas);
        G.cuentasCajaGastronomia = lista;
        G.cuentasCajaGastronomiaPorId = {};
        lista.forEach((c) => {
            if (c && c.id) {
                G.cuentasCajaGastronomiaPorId[String(c.id)] = c;
            }
        });
        wrap.innerHTML = '';
        if (!lista.length) {
            wrap.classList.add('d-none');
            return;
        }
        lista.forEach((cuenta) => {
            const info = resolverIconoCuentacaja(cuenta);
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-sm btn-outline-secondary gastro-medio-rapido';
            btn.title = (cuenta.codigo ? cuenta.codigo + ' — ' : '') + (cuenta.nombre || '');
            btn.dataset.cuentacajaId = String(cuenta.id);
            if (cuenta.accion === 'canje-ticket') {
                btn.id = 'gastro-btn-canje-ticket-tarjeta';
                btn.dataset.accion = 'canje-ticket';
            }
            btn.innerHTML = htmlIconoMedio(info) + '<span>' + etiquetaCortaMedioPago(cuenta) + '</span>';
            if (cuenta.accion === 'canje-ticket') {
                btn.addEventListener('click', () => abrirModalCanjeTicketTarjeta());
            } else {
                btn.addEventListener('click', () => {
                    void seleccionarMedioPagoRapido(cuenta);
                });
            }
            wrap.appendChild(btn);
        });
        wrap.classList.toggle('d-none', !!cobranzaWaitryTotemBloqueada);
    }

    async function cargarMediosPagoRapidos() {
        if (!empresaId) return;
        try {
            const data = await api('/ventas/gastronomia/api/cuentas-caja', { headers: hdrJson() });
            renderMediosPagoRapidos(data.cuentas_caja || []);
        } catch (e) {
            console.warn('Medios de pago gastronomía:', e.message || e);
        }
    }

    async function seleccionarMedioPagoRapido(cuenta) {
        if (bloquearOperacionPosPorJornadaTurno()) {
            return;
        }
        if (cobranzaWaitryTotemBloqueada) {
            toast('La cobranza TOTEM de esta cuenta Waitry no se puede modificar.', 'warning');
            return;
        }
        if (!cuenta || !cuenta.id) {
            return;
        }
        if (rechazarCuentacajaSoloAutomaticaManual(cuenta)) {
            return;
        }
        const tbody = document.getElementById('tbody-gastro-cuenta-table');
        if (!tbody) return;
        let tr = Array.from(tbody.querySelectorAll('tr')).find((row) => {
            const ccId = (row.querySelector('.cuentacaja_id')?.value || '').trim();
            return !ccId;
        });
        if (!tr) {
            agregarRenglonCobranza(false);
            tr = tbody.querySelector('tr:last-child');
        }
        if (!tr) return;
        asignarCuentaCajaEnFila(tr, cuenta);
    }

    function filaCobranzaDesdeTemplate() {
        const tpl = document.getElementById('gastro-template-renglon-cuenta');
        if (!tpl || !tpl.content) return null;
        return tpl.content.firstElementChild.cloneNode(true);
    }

    function agregarRenglonCobranza(enfocarCodigo) {
        if (bloquearOperacionPosPorJornadaTurno()) {
            return;
        }
        if (cobranzaWaitryTotemBloqueada) {
            toast('La cobranza TOTEM de esta cuenta Waitry no se puede modificar.', 'warning');
            return;
        }
        const tr = filaCobranzaDesdeTemplate();
        if (!tr) return;
        document.getElementById('tbody-gastro-cuenta-table').appendChild(tr);
        wireEventosFilaCobranza(tr);
        if (enfocarCodigo !== false) {
            const cod = tr.querySelector('.codigo');
            if (cod) cod.focus();
        }
        sumarMontosCobranza();
    }

    function asignarCuentaCajaEnFila(tr, cuenta, opts) {
        if (!tr || !cuenta || !cuenta.id) return;
        const manual = !opts || opts.asignacionAutomatica !== true;
        if (manual && rechazarCuentacajaSoloAutomaticaManual(cuenta)) {
            return;
        }
        const monIdNuevo = parseInt(String(cuenta.moneda_id || '0'), 10);
        if (
            monIdNuevo > MONEDA_PESOS_ID &&
            cotizacionExtranjera.monedaId > MONEDA_PESOS_ID &&
            cotizacionExtranjera.monedaId !== monIdNuevo
        ) {
            toast(
                'Ya hay cotización cargada para ' +
                    cotizacionExtranjera.abrev +
                    '. Use cuentas de la misma moneda extranjera o quite las otras líneas.',
                'warning',
            );
            return;
        }
        const idInp = tr.querySelector('.cuentacaja_id');
        const codInp = tr.querySelector('.codigo');
        const nomInp = tr.querySelector('.nombre');
        const monIdInp = tr.querySelector('.moneda_id');
        const monLbl = tr.querySelector('.moneda-label');
        if (idInp) idInp.value = cuenta.id;
        if (codInp) codInp.value = cuenta.codigo || '';
        if (nomInp) nomInp.value = cuenta.nombre || '';
        const monId = cuenta.moneda_id || '';
        if (monIdInp) monIdInp.value = monId;
        if (monLbl) {
            monLbl.textContent =
                cuenta.moneda_abreviatura || monedaAbrevPorId[monId] || (monId ? String(monId) : '—');
        }
        void revisarMonedaExtranjeraEnGrilla().then(() => {
            actualizarDatoSaldoMonto(tr);
            actualizarIconoConsultaFila(tr, cuenta);
            const monto = tr.querySelector('.monto');
            if (monto) monto.focus();
            sumarMontosCobranza();
        });
    }

    function limpiarCuentaEnFila(tr) {
        if (!tr) return;
        const idInp = tr.querySelector('.cuentacaja_id');
        const nomInp = tr.querySelector('.nombre');
        const monIdInp = tr.querySelector('.moneda_id');
        const monLbl = tr.querySelector('.moneda-label');
        if (idInp) idInp.value = '';
        if (nomInp) nomInp.value = '';
        if (monIdInp) monIdInp.value = '';
        if (monLbl) monLbl.textContent = '—';
        actualizarIconoConsultaFila(tr, null);
        const ticketIdInp = tr.querySelector('.ticket_id');
        const numeroTicketInp = tr.querySelector('.numeroticket');
        if (ticketIdInp) ticketIdInp.value = '';
        if (numeroTicketInp) numeroTicketInp.value = '';
        const codInp = tr.querySelector('.codigo');
        if (codInp) codInp.readOnly = false;
        const montoInp = tr.querySelector('.monto');
        if (montoInp) {
            montoInp.value = '';
            montoInp.readOnly = false;
            delete montoInp.dataset.montoEditadoManual;
            delete montoInp.dataset.saldoValidacion;
            delete montoInp.dataset.saldoValidacionArs;
        }
    }

    async function leerCuentaCajaPorCodigoGastro(codigo) {
        const enc = encodeURIComponent(String(codigo || '').trim());
        if (!enc) return { id: 0 };
        try {
            return await api(`/ventas/gastronomia/api/cuentacaja-por-codigo/${enc}`, { headers: hdrJson() });
        } catch (e) {
            return { id: 0, error: e.message || 'No se pudo validar la cuenta de caja.' };
        }
    }

    function abrirConsultaCuentacajaGastro(tr) {
        const emp = document.getElementById('empresa_id') || document.getElementById('gastro-empresa-id');
        if (emp && empresaId) emp.value = empresaId;
        cuentacajaxcodigo = tr.querySelector('.cuentacaja_id');
        nombrexcodigo = tr.querySelector('.nombre');
        codigoxcodigo = tr.querySelector('.codigo');
        if (typeof $ === 'undefined') return;
        $('#consultacuentacajaModal').one('shown.bs.modal.gastroCuenta', function () {
            if (typeof buscar_datos_cuentacaja === 'function') {
                buscar_datos_cuentacaja('');
            }
            $(this).find('#consultacuentacaja').trigger('focus');
        });
        $('#consultacuentacajaModal').modal('show');
    }

    function wireEventosFilaCobranza(tr) {
        const btnConsulta = tr.querySelector('.consultacuentacaja');
        if (btnConsulta) {
            btnConsulta.addEventListener('click', () => {
                if (cobranzaWaitryTotemBloqueada) {
                    return;
                }
                if (!empresaId) {
                    toast('Configure el punto de venta (empresa).', 'warning');
                    return;
                }
                abrirConsultaCuentacajaGastro(tr);
            });
        }

        const codInp = tr.querySelector('.codigo');
        if (codInp) {
            const buscarPorCodigo = async () => {
                if (cobranzaWaitryTotemBloqueada) {
                    return;
                }
                const codigo = codInp.value.trim();
                if (!codigo) {
                    limpiarCuentaEnFila(tr);
                    void revisarMonedaExtranjeraEnGrilla().then(sumarMontosCobranza);
                    return;
                }
                const data = await leerCuentaCajaPorCodigoGastro(codigo);
                if (data && data.id > 0) {
                    asignarCuentaCajaEnFila(tr, data);
                } else {
                    toast(
                        data?.error || 'No existe cuenta de caja con uso Gastronomía para ese código.',
                        'warning',
                    );
                    limpiarCuentaEnFila(tr);
                    codInp.focus();
                    codInp.select();
                    void revisarMonedaExtranjeraEnGrilla().then(sumarMontosCobranza);
                }
            };
            codInp.addEventListener('change', () => {
                void buscarPorCodigo();
            });
            codInp.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    e.stopPropagation();
                    void buscarPorCodigo();
                }
            });
        }

        const montoInp = tr.querySelector('.monto');
        if (montoInp) {
            montoInp.addEventListener('focus', () => actualizarDatoSaldoMonto(tr));
            montoInp.addEventListener('change', () => {
                if (!validarMontoCobranza(tr)) return;
                sumarMontosCobranza();
            });
            montoInp.addEventListener('input', () => {
                montoInp.dataset.montoEditadoManual = '1';
                sumarMontosCobranza();
            });
            montoInp.addEventListener('keydown', (e) => {
                if (e.key !== 'Enter') return;
                e.preventDefault();
                e.stopPropagation();
                if (!validarMontoCobranza(tr)) return;
                sumarMontosCobranza();
                const cuentaId = tr.querySelector('.cuentacaja_id')?.value;
                if (!cuentaId) {
                    toast('Ingrese la cuenta de caja antes del monto.', 'warning');
                    tr.querySelector('.codigo')?.focus();
                    return;
                }
                const tbody = document.getElementById('tbody-gastro-cuenta-table');
                const rows = tbody ? Array.from(tbody.querySelectorAll('tr')) : [];
                const idx = rows.indexOf(tr);
                if (idx >= 0 && idx < rows.length - 1) {
                    rows[idx + 1].querySelector('.codigo')?.focus();
                    return;
                }
                agregarRenglonCobranza(true);
            });
        }

        const btnDel = tr.querySelector('.gastro-eliminar-cuenta');
        if (btnDel) {
            btnDel.addEventListener('click', (e) => {
                e.preventDefault();
                if (cobranzaWaitryTotemBloqueada) {
                    return;
                }
                tr.remove();
                void revisarMonedaExtranjeraEnGrilla().then(sumarMontosCobranza);
            });
        }
    }

    function sumarMontosCobranza() {
        actualizarDatosSaldoTodasFilas();
        const totales = {};
        let totalCobranzaArs = 0;
        document.querySelectorAll('#tbody-gastro-cuenta-table tr').forEach((tr) => {
            const montoInp = tr.querySelector('.monto');
            const monId = parseInt(tr.querySelector('.moneda_id')?.value || '0', 10);
            const val = parseFloat(montoInp?.value || '');
            if (!monId || Number.isNaN(val) || val === 0) return;
            totales[monId] = (totales[monId] || 0) + val;
            totalCobranzaArs += montoCobranzaEnArs(tr);
        });
        const wrap = document.getElementById('gastro-totales-cobranza');
        if (!wrap) return;
        const partes = Object.keys(totales).map((mid) => {
            const abr = monedaAbrevPorId[mid] || mid;
            return `${abr}: ${totales[mid].toFixed(2)}`;
        });
        let html = '';
        if (partes.length) {
            html += `<div class="text-right mt-1 text-muted" style="font-size:0.875em;">${partes.join(' · ')}</div>`;
        }
        html += `<div class="text-right mt-1"><strong>Total cobranza ($):</strong> ${totalCobranzaArs.toFixed(2)}</div>`;
        if (totalFacturadoArs > 0) {
            const pendiente = Math.max(0, totalFacturadoArs - totalCobranzaArs);
            html += `<div class="text-right mt-1"><strong>Saldo pendiente ($):</strong> ${pendiente.toFixed(2)}</div>`;
            const diff = Math.abs(totalCobranzaArs - totalFacturadoArs);
            const ok = diff < TOLERANCIA_MONTO_COBRANZA;
            let extra = '';
            if (!ok && totalCobranzaArs > 0) {
                extra = ` <span class="gastro-total-diff">(diferencia ${diff.toFixed(2)})</span>`;
            } else if (ok && totalCobranzaArs > 0) {
                extra = ' <span class="text-success">✓</span>';
            }
            html += `<div class="text-right mt-1"><strong>Total factura ($):</strong> ${totalFacturadoArs.toFixed(2)}${extra}</div>`;
        }
        wrap.innerHTML = html;
    }

    function setTotalFacturadoArs(monto) {
        totalFacturadoArs = Math.max(0, parseFloat(monto) || 0);
        sumarMontosCobranza();
    }

    function initCobranzaGrid() {
        const tbody = document.getElementById('tbody-gastro-cuenta-table');
        if (!tbody) return;

        document.getElementById('gastro-agrega-renglon-cuenta')?.addEventListener('click', (e) => {
            e.preventDefault();
            agregarRenglonCobranza(true);
        });

        if (typeof $ !== 'undefined') {
            $(document).off('click.gastroCuentaElige', '.eligeconsultacuentacaja');
            $(document).on('click.gastroCuentaElige', '.eligeconsultacuentacaja', function () {
                if (!cuentacajaxcodigo) return;
                const trModal = $(this).parents('tr');
                const id = trModal.find('.cuentacaja_id').html();
                const nombre = trModal.find('.nombre').html();
                const codigo = trModal.find('.codigo').html();
                const moneda_id = trModal.find('.moneda_id').html();
                const tr = cuentacajaxcodigo.closest('tr');
                asignarCuentaCajaEnFila(tr, {
                    id: parseInt(id, 10),
                    nombre,
                    codigo,
                    moneda_id: parseInt(moneda_id, 10),
                    moneda_abreviatura: monedaAbrevPorId[moneda_id] || '',
                });
                void revisarMonedaExtranjeraEnGrilla();
                $('#consultacuentacajaModal').modal('hide');
                cuentacajaxcodigo = null;
            });
        }

        agregarRenglonCobranza(false);
        initCanjeTicketTarjeta();
        initCanjePremio();
        initCanjeFidelidad();
    }

    let canjePremioValidado = null;
    /** Tras confirmar cupón: apertura de cuenta libre + aplicar automático al quedar abierta. */
    let canjePremioEsperaApertura = false;
    let canjePremioValidando = false;
    let canjePremioInputTimer = null;
    let canjePremioUltimoCodigoValidado = '';

    function resetModalCanjePremio(limpiarValidacion) {
        if (limpiarValidacion !== false) {
            canjePremioValidado = null;
            canjePremioEsperaApertura = false;
            canjePremioUltimoCodigoValidado = '';
        }
        if (canjePremioInputTimer) {
            clearTimeout(canjePremioInputTimer);
            canjePremioInputTimer = null;
        }
        const inp = document.getElementById('gastro-canje-premio-codigo');
        const err = document.getElementById('gastro-canje-premio-error');
        const prev = document.getElementById('gastro-canje-premio-preview');
        const btn = document.getElementById('gastro-canje-premio-confirmar');
        const itemsWrap = document.getElementById('gastro-canje-premio-items-wrap');
        const itemsUl = document.getElementById('gastro-canje-premio-items');
        if (inp) inp.value = '';
        if (err) {
            err.textContent = '';
            err.classList.add('d-none');
        }
        if (prev) prev.classList.add('d-none');
        if (itemsWrap) itemsWrap.classList.add('d-none');
        if (itemsUl) itemsUl.innerHTML = '';
        if (btn) btn.disabled = true;
        actualizarBtnConfirmarCanjePremio();
    }

    function cuentaGastronomiaEsLibre(cuenta) {
        return cuenta && String(cuenta.tipo || '') === 'cuenta';
    }

    function cuentaLibreAptaParaCanjePremio(cuenta) {
        if (!cuentaGastronomiaEsLibre(cuenta) || String(cuenta.estado || '') !== 'abierta') {
            return false;
        }
        const premioPend = cuenta.canje_premio_pendiente;
        if (premioPend && typeof premioPend === 'object' && String(premioPend.numerocupon || '').trim() !== '') {
            return false;
        }
        const fidelidadPend = cuenta.canje_fidelidad_pendiente;
        if (fidelidadPend && typeof fidelidadPend === 'object' && String(fidelidadPend.trackdata || '').trim() !== '') {
            return false;
        }
        return true;
    }

    function puedeAplicarCanjePremioEnCuentaActual() {
        return cuentaId > 0 && cuentaLibreAptaParaCanjePremio(cuentaActivaConLineas);
    }

    function actualizarBtnConfirmarCanjePremio() {
        const btn = document.getElementById('gastro-canje-premio-confirmar');
        if (!btn) return;
        btn.textContent = puedeAplicarCanjePremioEnCuentaActual()
            ? 'Aplicar a cuenta libre'
            : 'Abrir cuenta libre y aplicar';
    }

    function programarValidacionCanjePremioTrasLectura() {
        const inp = document.getElementById('gastro-canje-premio-codigo');
        if (!inp) return;
        if (canjePremioInputTimer) {
            clearTimeout(canjePremioInputTimer);
        }
        const val = (inp.value || '').trim();
        if (val.length < 3) {
            return;
        }
        canjePremioInputTimer = setTimeout(() => {
            canjePremioInputTimer = null;
            void validarCodigoBarrasCanjePremio();
        }, 120);
    }

    function mostrarErrorModalCanjePremio(msg) {
        const err = document.getElementById('gastro-canje-premio-error');
        const prev = document.getElementById('gastro-canje-premio-preview');
        const btn = document.getElementById('gastro-canje-premio-confirmar');
        canjePremioValidado = null;
        canjePremioUltimoCodigoValidado = '';
        if (prev) prev.classList.add('d-none');
        if (btn) btn.disabled = true;
        if (err) {
            err.textContent = msg || 'No se pudo validar el cupón.';
            err.classList.remove('d-none');
        }
    }

    function pintarPreviewCanjePremio(data) {
        const res = data.resumen || {};
        const set = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.textContent = val != null && val !== '' ? val : '—';
        };
        set('gastro-canje-premio-prev-cupon', data.numerocupon);
        set('gastro-canje-premio-prev-premio', (res.premio_sku || '') + ' ' + (res.premio_descripcion || ''));
        set('gastro-canje-premio-prev-puntos', res.puntos_unidad);
        set('gastro-canje-premio-prev-cantidad', res.cantidad);
        set('gastro-canje-premio-prev-puntos-total', res.puntos_total);
        const fechaTxt = res.fecha_canje
            ? formatearFechaIsoADisplay(res.fecha_canje) + (res.fecha_canje_hora ? ' ' + res.fecha_canje_hora : '')
            : '—';
        set('gastro-canje-premio-prev-fecha', fechaTxt);
        set('gastro-canje-premio-prev-cliente-wigos', res.cliente_wigos);
        set('gastro-canje-premio-prev-apellido', res.apellido);
        set('gastro-canje-premio-prev-nombre', res.nombre);
        set('gastro-canje-premio-prev-documento', res.numerodocumento);

        const items = data.items || [];
        const itemsWrap = document.getElementById('gastro-canje-premio-items-wrap');
        const itemsUl = document.getElementById('gastro-canje-premio-items');
        if (itemsUl && items.length) {
            itemsUl.innerHTML = items
                .map(
                    (it) =>
                        '<li>' +
                        (it.sku || '') +
                        ' — ' +
                        (it.descripcion || '') +
                        ' × ' +
                        (parseFloat(it.cantidad) || 0) +
                        ' (' +
                        (it.puntos || 0) +
                        ' pts)</li>',
                )
                .join('');
            if (itemsWrap) itemsWrap.classList.remove('d-none');
        }

        const prev = document.getElementById('gastro-canje-premio-preview');
        if (prev) prev.classList.remove('d-none');
        actualizarBtnConfirmarCanjePremio();
    }

    async function validarCodigoBarrasCanjePremio() {
        if (canjePremioValidando) {
            return;
        }
        const inp = document.getElementById('gastro-canje-premio-codigo');
        const codigo = (inp?.value || '').trim();
        if (!codigo) {
            mostrarErrorModalCanjePremio('Ingrese o escanee el número de cupón.');
            return;
        }
        if (codigo === canjePremioUltimoCodigoValidado && canjePremioValidado) {
            return;
        }
        if (!G.wigosHabilitado) {
            mostrarErrorModalCanjePremio('Integración Wigos no habilitada en este terminal.');
            return;
        }

        const errBox = document.getElementById('gastro-canje-premio-error');
        if (errBox) errBox.classList.add('d-none');

        canjePremioValidando = true;
        try {
            const data = await api('/ventas/gastronomia/api/validar-ticket-canje-premio', {
                method: 'POST',
                headers: hdrJson(),
                body: JSON.stringify({ codigo_barras: codigo }),
            });
            if (!data.ok) {
                canjePremioUltimoCodigoValidado = '';
                mostrarErrorModalCanjePremio(data.error || data.mensaje || 'Cupón no válido.');
                return;
            }
            canjePremioUltimoCodigoValidado = codigo;
            canjePremioValidado = data;
            pintarPreviewCanjePremio(data);
            const btn = document.getElementById('gastro-canje-premio-confirmar');
            if (btn) {
                btn.disabled = false;
                setTimeout(() => btn.focus(), 0);
            }
            actualizarBtnConfirmarCanjePremio();
        } catch (e) {
            canjePremioUltimoCodigoValidado = '';
            const detalle =
                (e.payload && (e.payload.error || e.payload.mensaje)) || e.message || String(e);
            mostrarErrorModalCanjePremio(detalle);
        } finally {
            canjePremioValidando = false;
        }
    }

    function pintarCuentaTrasCanjePremio(cuenta) {
        if (!cuenta) return;
        pintarLineas(cuenta);
        if (cuenta.descuento_gastronomia && typeof pintarDescuentoEnPantalla === 'function') {
            pintarDescuentoEnPantalla(cuenta.descuento_gastronomia);
        }
        // Cliente de canje/descuento → solo panel invitación; factura sin cliente asignado = CF.
        if (cuenta.factura_consumidor_final || !cuenta.cliente_id) {
            document.getElementById('cliente_id').value = '';
            document.getElementById('codigocliente').value = '';
            document.getElementById('nombrecliente').value =
                cuenta.receptor_factura_nombre ||
                (typeof G !== 'undefined' && G.receptorCfNombre) ||
                'CONSUMIDOR FINAL';
        } else if (cuenta.cliente) {
            $('#cliente_id').val(cuenta.cliente.id || '');
            $('#codigocliente').val(cuenta.cliente.codigo != null ? String(cuenta.cliente.codigo) : '');
            $('#nombrecliente').val(cuenta.cliente.nombre || '');
        }
        if (cuenta.cliente_interno_descuento && typeof aplicarClienteInternoDescuentoEnPantalla === 'function') {
            aplicarClienteInternoDescuentoEnPantalla(cuenta.cliente_interno_descuento);
        }
    }

    function articuloStubDesdeItemCanjePremio(item) {
        return {
            id: parseInt(item.articulo_id, 10),
            sku: item.sku || '',
            descripcion: item.descripcion || '',
        };
    }

    function pedirOpcionalesCanjePremio(articulo, grupos) {
        return new Promise((resolve) => {
            pendingOpcionalesCtx = {
                articulo,
                cantidad: 1,
                modo: 'canje-premio',
                grupos,
                onResolve: resolve,
            };
            pendingOpcionalesSeleccion = null;
            renderGrillaOpcionales(grupos, articulo);
            $('#modal-opcionales').modal('show');
        });
    }

    /**
     * Tras validar el cupón: si algún ítem del premio tiene opcionales de fórmula, abre el modal
     * por cada artículo distinto antes de aplicar el canje.
     *
     * @returns {Promise<object|null>} mapa articulo_id → selección, o null si el usuario cancela
     */
    async function recolectarOpcionalesParaCanjePremio(items) {
        const lista = Array.isArray(items) ? items : [];
        const porArticulo = new Map();
        lista.forEach((it) => {
            const aid = parseInt(it.articulo_id, 10);
            if (aid > 0 && !porArticulo.has(aid)) {
                porArticulo.set(aid, it);
            }
        });

        const necesitan = [];
        for (const [aid, it] of porArticulo) {
            const grupos = await fetchGruposOpcionales(aid);
            if (grupos.length) {
                necesitan.push({ articulo: articuloStubDesdeItemCanjePremio(it), grupos });
            }
        }

        if (!necesitan.length) {
            return {};
        }

        const resultado = {};
        for (const { articulo, grupos } of necesitan) {
            const map = await pedirOpcionalesCanjePremio(articulo, grupos);
            if (map === null) {
                return null;
            }
            resultado[String(articulo.id)] = map;
        }
        return resultado;
    }

    async function aplicarCanjePremioACuenta(opciones) {
        const opts = opciones || {};
        if (!canjePremioValidado) {
            if (!opts.silencioso) {
                mostrarErrorModalCanjePremio('Valide el cupón antes de aplicarlo.');
            }
            return false;
        }
        if (!cuentaId) {
            if (!opts.silencioso) {
                mostrarErrorModalCanjePremio('Abra una cuenta libre para aplicar el canje.');
            }
            return false;
        }
        if (!cuentaLibreAptaParaCanjePremio(cuentaActivaConLineas)) {
            if (!opts.silencioso) {
                mostrarErrorModalCanjePremio('El canje de premio solo aplica a cuentas libres abiertas.');
            }
            return false;
        }
        const inp = document.getElementById('gastro-canje-premio-codigo');
        const codigo = (inp?.value || canjePremioValidado.numerocupon || '').trim();
        const btn = document.getElementById('gastro-canje-premio-confirmar');
        if (btn && !opts.silencioso) btn.disabled = true;

        const opcionalesPorArticulo = await recolectarOpcionalesParaCanjePremio(
            canjePremioValidado.items || [],
        );
        if (opcionalesPorArticulo === null) {
            if (btn && !opts.silencioso) btn.disabled = false;
            return false;
        }

        try {
            const payload = {
                cuenta_id: cuentaId,
                codigo_barras: codigo,
            };
            if (opcionalesPorArticulo && Object.keys(opcionalesPorArticulo).length) {
                payload.opcionales_por_articulo = opcionalesPorArticulo;
            }
            const data = await api('/ventas/gastronomia/api/aplicar-ticket-canje-premio', {
                method: 'POST',
                headers: hdrJson(),
                body: JSON.stringify(payload),
            });
            if (!data.ok) {
                const msg = data.error || data.mensaje || 'No se pudo aplicar el canje.';
                if (opts.silencioso) {
                    toast(msg, 'error');
                } else {
                    mostrarErrorModalCanjePremio(msg);
                }
                if (btn && !opts.silencioso) btn.disabled = false;
                return false;
            }

            pintarCuentaTrasCanjePremio(data.cuenta);

            const codDesc = codigoDescuentoDesdeValidacionCanje(
                data.validacion,
                G.canjePremioDescuentoCodigo,
            );

            if (!opts.silencioso) {
                resetModalCanjePremio(true);
                toast('Canje aplicado. Abriendo facturación con descuento 100%…', 'success');
                await cerrarModalCanjeYFacturarConF8('modal-gastro-canje-premio', codDesc);
            } else {
                resetModalCanjePremio(true);
                await continuarFacturacionTrasCanjeWigos(codDesc);
            }
            return true;
        } catch (e) {
            const msg = e.message || String(e);
            if (opts.silencioso) {
                toast(msg, 'error');
            } else {
                mostrarErrorModalCanjePremio(msg);
            }
            if (btn && !opts.silencioso) btn.disabled = false;
            return false;
        }
    }

    async function abrirCuentaLibreParaCanjePremio() {
        if (G.cuentasLibresHabilitadas === false) {
            toast('Las cuentas libres no están habilitadas.', 'warning');
            return;
        }
        canjePremioEsperaApertura = true;
        if (typeof $ !== 'undefined') {
            $('#modal-gastro-canje-premio').modal('hide');
        }
        try {
            const apertura = await resolverDatosAperturaNuevaCuenta(false, 'Cuenta libre — canje premio');
            const r = await api('/ventas/gastronomia/api/abrir-cuenta', {
                method: 'POST',
                headers: hdrJson(),
                body: JSON.stringify(Object.assign({ empresa_id: empresaId }, apertura)),
            });
            await seleccionarCuenta(r.cuenta_id);
            cargarCuentasActivas();
        } catch (e) {
            canjePremioEsperaApertura = false;
            if (e.message !== 'cancelado') {
                toast(e.message, 'error');
            }
        }
    }

    function puedeConfirmarCanjePremioDesdeTeclado() {
        const btn = document.getElementById('gastro-canje-premio-confirmar');
        return !!(canjePremioValidado && btn && !btn.disabled && !canjePremioValidando);
    }

    function manejarEnterModalCanjePremio(e) {
        if (!e || e.key !== 'Enter' || e.ctrlKey || e.metaKey || e.altKey) {
            return;
        }
        const t = e.target;
        if (puedeConfirmarCanjePremioDesdeTeclado()) {
            e.preventDefault();
            void confirmarCanjePremio();
            return;
        }
        if (t && t.id === 'gastro-canje-premio-confirmar' && !t.disabled) {
            e.preventDefault();
            void confirmarCanjePremio();
            return;
        }
        if (t && t.id === 'gastro-canje-premio-codigo') {
            e.preventDefault();
            if (canjePremioInputTimer) {
                clearTimeout(canjePremioInputTimer);
                canjePremioInputTimer = null;
            }
            void validarCodigoBarrasCanjePremio();
        }
    }

    async function confirmarCanjePremio() {
        if (!canjePremioValidado) {
            mostrarErrorModalCanjePremio('Valide el cupón antes de continuar.');
            return;
        }
        if (G.cuentasLibresHabilitadas === false) {
            toast('Las cuentas libres no están habilitadas.', 'warning');
            return;
        }
        if (puedeAplicarCanjePremioEnCuentaActual()) {
            await aplicarCanjePremioACuenta();
            return;
        }
        await abrirCuentaLibreParaCanjePremio();
    }

    async function intentarAplicarCanjePremioPendiente(cuenta) {
        if (!canjePremioEsperaApertura || !canjePremioValidado || !cuentaId) {
            return;
        }
        const c = cuenta || cuentaActivaConLineas;
        if (!cuentaLibreAptaParaCanjePremio(c)) {
            canjePremioEsperaApertura = false;
            resetModalCanjePremio(true);
            toast('No se pudo aplicar el canje en la cuenta libre.', 'warning');
            return;
        }
        const ok = await aplicarCanjePremioACuenta({ silencioso: true });
        if (ok) {
            canjePremioEsperaApertura = false;
        }
    }

    function initCanjePremio() {
        const btnAbrir = document.getElementById('gastro-btn-canje-premio');
        const inp = document.getElementById('gastro-canje-premio-codigo');
        const btnConfirmar = document.getElementById('gastro-canje-premio-confirmar');

        if (btnAbrir) {
            btnAbrir.addEventListener('click', () => {
                if (bloquearOperacionPosPorJornadaTurno()) {
                    return;
                }
                if (!G.wigosHabilitado) {
                    toast('Integración Wigos no habilitada.', 'warning');
                    return;
                }
                if (G.cuentasLibresHabilitadas === false) {
                    toast('Las cuentas libres no están habilitadas.', 'warning');
                    return;
                }
                resetModalCanjePremio(true);
                if (typeof $ !== 'undefined') {
                    $('#modal-gastro-canje-premio').one('shown.bs.modal', function () {
                        const c = document.getElementById('gastro-canje-premio-codigo');
                        if (c) c.focus();
                        actualizarBtnConfirmarCanjePremio();
                    });
                    $('#modal-gastro-canje-premio').modal('show');
                }
            });
        }

        if (inp) {
            inp.addEventListener('input', () => programarValidacionCanjePremioTrasLectura());
            inp.addEventListener('keydown', manejarEnterModalCanjePremio);
        }

        if (btnConfirmar) {
            btnConfirmar.addEventListener('click', () => void confirmarCanjePremio());
            btnConfirmar.addEventListener('keydown', manejarEnterModalCanjePremio);
        }

        if (typeof $ !== 'undefined') {
            $('#modal-gastro-canje-premio').on('keydown.gastroCanjePremio', manejarEnterModalCanjePremio);
            $('#modal-gastro-canje-premio').on('hidden.bs.modal', function () {
                if (!canjePremioEsperaApertura) {
                    resetModalCanjePremio(true);
                }
            });
        }
    }

    let canjeFidelidadValidado = null;
    let canjeFidelidadArticuloId = null;
    /** Tras confirmar tarjeta: apertura de cuenta libre + aplicar automático al quedar abierta. */
    let canjeFidelidadEsperaApertura = false;
    let canjeFidelidadValidando = false;
    let canjeFidelidadInputTimer = null;
    let canjeFidelidadUltimoTrackdataValidado = '';

    /** Quita sentinelas que agregan lectores magnéticos (; al inicio, ? al final, etc.). */
    function normalizarTrackdataLector(raw) {
        let t = String(raw || '').trim();
        t = t.replace(/[\x00-\x1F\x7F]/g, '');
        const prefijos = [';', '%', '*'];
        const sufijos = ['?', ';', '%'];
        while (t.length && prefijos.includes(t[0])) {
            t = t.slice(1);
        }
        while (t.length && sufijos.includes(t[t.length - 1])) {
            t = t.slice(0, -1);
        }
        return t.trim();
    }

    function resetModalCanjeFidelidad(limpiarValidacion) {
        if (limpiarValidacion !== false) {
            canjeFidelidadValidado = null;
            canjeFidelidadArticuloId = null;
            canjeFidelidadEsperaApertura = false;
            canjeFidelidadUltimoTrackdataValidado = '';
        }
        if (canjeFidelidadInputTimer) {
            clearTimeout(canjeFidelidadInputTimer);
            canjeFidelidadInputTimer = null;
        }
        const inp = document.getElementById('gastro-canje-fidelidad-trackdata');
        const err = document.getElementById('gastro-canje-fidelidad-error');
        const prev = document.getElementById('gastro-canje-fidelidad-preview');
        const btn = document.getElementById('gastro-canje-fidelidad-confirmar');
        const artWrap = document.getElementById('gastro-canje-fidelidad-articulos-wrap');
        const artBox = document.getElementById('gastro-canje-fidelidad-articulos');
        if (inp) inp.value = '';
        if (err) {
            err.textContent = '';
            err.classList.add('d-none');
        }
        if (prev) prev.classList.add('d-none');
        if (artWrap) artWrap.classList.add('d-none');
        if (artBox) artBox.innerHTML = '';
        if (btn) btn.disabled = true;
        actualizarBotonConfirmarCanjeFidelidad();
    }

    function puedeAplicarCanjeFidelidadEnCuentaActual() {
        return cuentaId > 0 && cuentaLibreAptaParaCanjePremio(cuentaActivaConLineas);
    }

    function mostrarErrorModalCanjeFidelidad(msg) {
        const err = document.getElementById('gastro-canje-fidelidad-error');
        const prev = document.getElementById('gastro-canje-fidelidad-preview');
        const btn = document.getElementById('gastro-canje-fidelidad-confirmar');
        canjeFidelidadValidado = null;
        canjeFidelidadArticuloId = null;
        canjeFidelidadUltimoTrackdataValidado = '';
        if (prev) prev.classList.add('d-none');
        if (btn) btn.disabled = true;
        if (err) {
            err.textContent = msg || 'No se pudo validar la tarjeta.';
            err.classList.remove('d-none');
        }
    }

    function actualizarBotonConfirmarCanjeFidelidad() {
        const btn = document.getElementById('gastro-canje-fidelidad-confirmar');
        if (!btn) return;
        btn.disabled = !canjeFidelidadValidado || !canjeFidelidadArticuloId;
        btn.textContent = puedeAplicarCanjeFidelidadEnCuentaActual()
            ? 'Aplicar y facturar con descuento'
            : 'Abrir cuenta libre y aplicar';
    }

    function programarValidacionCanjeFidelidadTrasLectura() {
        const inp = document.getElementById('gastro-canje-fidelidad-trackdata');
        if (!inp) return;
        if (canjeFidelidadInputTimer) {
            clearTimeout(canjeFidelidadInputTimer);
        }
        const val = (inp.value || '').trim();
        if (val.length < 4) {
            return;
        }
        canjeFidelidadInputTimer = setTimeout(() => {
            canjeFidelidadInputTimer = null;
            void validarTarjetaCanjeFidelidad();
        }, 120);
    }

    function pintarArticulosCanjeFidelidad(articulos, seleccionadoId) {
        const wrap = document.getElementById('gastro-canje-fidelidad-articulos-wrap');
        const box = document.getElementById('gastro-canje-fidelidad-articulos');
        if (!box || !wrap) return;

        const arts = articulos || [];
        if (!arts.length) {
            wrap.classList.add('d-none');
            box.innerHTML = '';
            canjeFidelidadArticuloId = null;
            actualizarBotonConfirmarCanjeFidelidad();
            return;
        }

        if (arts.length === 1) {
            canjeFidelidadArticuloId = parseInt(arts[0].articulo_id, 10);
            box.innerHTML =
                '<div class="pl-1">' +
                (arts[0].sku || '') +
                ' — ' +
                (arts[0].descripcion || '') +
                ' · $' +
                (parseFloat(arts[0].precio_unitario) || 0).toFixed(2) +
                '</div>';
        } else {
            box.innerHTML = arts
                .map((it) => {
                    const id = parseInt(it.articulo_id, 10);
                    const checked = seleccionadoId === id ? ' checked' : '';
                    return (
                        '<div class="custom-control custom-radio mb-1">' +
                        '<input type="radio" class="custom-control-input gastro-canje-fidelidad-art-radio" ' +
                        'name="gastro_canje_fidelidad_art" id="gastro-canje-fidelidad-art-' +
                        id +
                        '" value="' +
                        id +
                        '"' +
                        checked +
                        '>' +
                        '<label class="custom-control-label" for="gastro-canje-fidelidad-art-' +
                        id +
                        '">' +
                        (it.sku || '') +
                        ' — ' +
                        (it.descripcion || '') +
                        ' · $' +
                        (parseFloat(it.precio_unitario) || 0).toFixed(2) +
                        '</label></div>'
                    );
                })
                .join('');
            box.querySelectorAll('.gastro-canje-fidelidad-art-radio').forEach((radio) => {
                radio.addEventListener('change', () => {
                    if (radio.checked) {
                        canjeFidelidadArticuloId = parseInt(radio.value, 10);
                        actualizarBotonConfirmarCanjeFidelidad();
                    }
                });
            });
            const checkedRadio = box.querySelector('.gastro-canje-fidelidad-art-radio:checked');
            canjeFidelidadArticuloId = checkedRadio
                ? parseInt(checkedRadio.value, 10)
                : seleccionadoId || null;
        }

        wrap.classList.remove('d-none');
        actualizarBotonConfirmarCanjeFidelidad();
        if (arts.length === 1 && puedeConfirmarCanjeFidelidadDesdeTeclado()) {
            const btnConf = document.getElementById('gastro-canje-fidelidad-confirmar');
            if (btnConf && typeof btnConf.focus === 'function') {
                setTimeout(() => btnConf.focus(), 0);
            }
        }
    }

    function pintarPreviewCanjeFidelidad(data) {
        const tarjeta = data.tarjeta || {};
        const cat = data.categoria || {};
        const set = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.textContent = val != null && val !== '' ? val : '—';
        };
        const titular = [tarjeta.apellido, tarjeta.nombre].filter(Boolean).join(', ');
        set('gastro-canje-fidelidad-prev-titular', titular || '—');
        set('gastro-canje-fidelidad-prev-documento', tarjeta.documento);
        set('gastro-canje-fidelidad-prev-cuenta', tarjeta.account_number);
        set(
            'gastro-canje-fidelidad-prev-nivel',
            (tarjeta.level || '') + (tarjeta.level_code ? ' (cód. ' + tarjeta.level_code + ')' : ''),
        );
        set(
            'gastro-canje-fidelidad-prev-categoria',
            (cat.nombre || '') + (cat.codigo ? ' [' + cat.codigo + ']' : ''),
        );
        set('gastro-canje-fidelidad-prev-email', tarjeta.email);

        pintarArticulosCanjeFidelidad(
            data.articulos || [],
            data.articulo_seleccionado_id ? parseInt(data.articulo_seleccionado_id, 10) : null,
        );

        const prev = document.getElementById('gastro-canje-fidelidad-preview');
        if (prev) prev.classList.remove('d-none');
    }

    async function validarTarjetaCanjeFidelidad() {
        if (canjeFidelidadValidando) {
            return;
        }
        const inp = document.getElementById('gastro-canje-fidelidad-trackdata');
        const trackdata = normalizarTrackdataLector(inp?.value || '');
        if (inp && inp.value !== trackdata) {
            inp.value = trackdata;
        }
        if (!trackdata) {
            mostrarErrorModalCanjeFidelidad('Pase la tarjeta o ingrese el identificador.');
            return;
        }
        if (trackdata === canjeFidelidadUltimoTrackdataValidado && canjeFidelidadValidado) {
            return;
        }
        if (!G.wigosAccountInfoHabilitado) {
            mostrarErrorModalCanjeFidelidad('Consulta de tarjeta Wigos no habilitada en este terminal.');
            return;
        }

        const errBox = document.getElementById('gastro-canje-fidelidad-error');
        if (errBox) errBox.classList.add('d-none');

        const body = { trackdata };
        if (canjeFidelidadArticuloId) {
            body.articulo_id = canjeFidelidadArticuloId;
        }

        canjeFidelidadValidando = true;
        try {
            const data = await api('/ventas/gastronomia/api/validar-canje-fidelidad', {
                method: 'POST',
                headers: hdrJson(),
                body: JSON.stringify(body),
            });
            if (!data.ok) {
                canjeFidelidadUltimoTrackdataValidado = '';
                mostrarErrorModalCanjeFidelidad(data.error || data.mensaje || 'Tarjeta no válida.');
                return;
            }
            canjeFidelidadUltimoTrackdataValidado = trackdata;
            canjeFidelidadValidado = data;
            pintarPreviewCanjeFidelidad(data);
            actualizarBotonConfirmarCanjeFidelidad();
        } catch (e) {
            canjeFidelidadUltimoTrackdataValidado = '';
            const detalle =
                (e.payload && (e.payload.error || e.payload.mensaje)) || e.message || String(e);
            mostrarErrorModalCanjeFidelidad(detalle);
        } finally {
            canjeFidelidadValidando = false;
        }
    }

    async function prepararDescuentoCanjeWigosEnPantalla(codigoDescuento) {
        const cod = String(
            codigoDescuento || G.canjeFidelidadDescuentoCodigo || G.canjePremioDescuentoCodigo || '10',
        ).trim();
        if (!cod) {
            return;
        }
        if (!tieneDescuentoEnPantalla()) {
            await cargarDescuentoPorCodigoApi(cod);
        }
    }

    /** Tras aplicar canje Wigos: si descuento 100% y cliente interno ya están OK, confirma F8 sin otro clic. */
    function registrarAutoConfirmacionF8TrasCanjeWigos() {
        if (typeof $ === 'undefined') {
            return;
        }
        $('#modal-f8-descuento')
            .off('shown.bs.modal.gastroPostCanje')
            .one('shown.bs.modal.gastroPostCanje', function () {
                setTimeout(() => {
                    if (!tieneDescuentoEnPantalla()) {
                        return;
                    }
                    if (!validarClienteInternoDescuentoEnPantalla()) {
                        void confirmarModalF8Descuento();
                    }
                }, 150);
            });
    }

    async function continuarFacturacionTrasCanjeWigos(codigoDescuento) {
        try {
            await prepararDescuentoCanjeWigosEnPantalla(codigoDescuento);
            registrarAutoConfirmacionF8TrasCanjeWigos();
            await abrirModalF8Descuento();
            await emitirFactura({ exigirDescuento: true, prepararCobranzaSiFalta: true });
        } catch (eF8) {
            if (eF8 && eF8.message && eF8.message !== 'Operación cancelada.') {
                toast(eF8.message, 'warning');
            }
        }
    }

    function codigoDescuentoDesdeValidacionCanje(validacion, fallbackCodigo) {
        return (
            (validacion && validacion.descuento && validacion.descuento.codigo) ||
            fallbackCodigo ||
            G.canjePremioDescuentoCodigo ||
            '10'
        );
    }

    async function cerrarModalCanjeYFacturarConF8(modalId, codigoDescuento) {
        const ejecutar = () => continuarFacturacionTrasCanjeWigos(codigoDescuento);
        if (typeof $ === 'undefined') {
            await ejecutar();
            return;
        }
        const $modal = $('#' + modalId);
        if ($modal.length && $modal.hasClass('show')) {
            await new Promise((resolve, reject) => {
                $modal.one('hidden.bs.modal.gastroFacturarCanje', () => {
                    setTimeout(() => {
                        ejecutar().then(resolve).catch(reject);
                    }, 120);
                });
                $modal.modal('hide');
            });
            return;
        }
        await ejecutar();
    }

    async function aplicarCanjeFidelidadACuenta(opciones) {
        const opts = opciones || {};
        if (!canjeFidelidadValidado || !canjeFidelidadArticuloId) {
            if (!opts.silencioso) {
                mostrarErrorModalCanjeFidelidad('Valide la tarjeta y seleccione el artículo a canjear.');
            }
            return false;
        }
        if (!cuentaId) {
            if (!opts.silencioso) {
                mostrarErrorModalCanjeFidelidad('Abra una cuenta libre para aplicar el canje.');
            }
            return false;
        }
        if (!cuentaLibreAptaParaCanjePremio(cuentaActivaConLineas)) {
            if (!opts.silencioso) {
                mostrarErrorModalCanjeFidelidad('El canje fidelidad solo aplica a cuentas libres abiertas.');
            }
            return false;
        }
        const inp = document.getElementById('gastro-canje-fidelidad-trackdata');
        const trackdata = normalizarTrackdataLector(
            inp?.value || canjeFidelidadValidado.trackdata || '',
        );
        if (inp && inp.value !== trackdata) {
            inp.value = trackdata;
        }
        const btn = document.getElementById('gastro-canje-fidelidad-confirmar');
        if (btn && !opts.silencioso) btn.disabled = true;

        try {
            const data = await api('/ventas/gastronomia/api/aplicar-canje-fidelidad', {
                method: 'POST',
                headers: hdrJson(),
                body: JSON.stringify({
                    cuenta_id: cuentaId,
                    trackdata,
                    articulo_id: canjeFidelidadArticuloId,
                }),
            });
            if (!data.ok) {
                const msg = data.error || data.mensaje || 'No se pudo aplicar el canje.';
                if (opts.silencioso) {
                    toast(msg, 'error');
                } else {
                    mostrarErrorModalCanjeFidelidad(msg);
                }
                if (btn && !opts.silencioso) btn.disabled = false;
                actualizarBotonConfirmarCanjeFidelidad();
                return false;
            }

            pintarCuentaTrasCanjePremio(data.cuenta);

            const codDesc = codigoDescuentoDesdeValidacionCanje(
                data.validacion,
                G.canjeFidelidadDescuentoCodigo,
            );

            if (!opts.silencioso) {
                resetModalCanjeFidelidad(true);
                toast('Canje fidelidad aplicado. Abriendo facturación con descuento 100%…', 'success');
                await cerrarModalCanjeYFacturarConF8('modal-gastro-canje-fidelidad', codDesc);
            } else {
                resetModalCanjeFidelidad(true);
                await continuarFacturacionTrasCanjeWigos(codDesc);
            }
            return true;
        } catch (e) {
            const msg = e.message || String(e);
            if (opts.silencioso) {
                toast(msg, 'error');
            } else {
                mostrarErrorModalCanjeFidelidad(msg);
            }
            if (btn && !opts.silencioso) btn.disabled = false;
            actualizarBotonConfirmarCanjeFidelidad();
            return false;
        }
    }

    async function abrirCuentaLibreParaCanjeFidelidad() {
        if (G.cuentasLibresHabilitadas === false) {
            toast('Las cuentas libres no están habilitadas.', 'warning');
            return;
        }
        canjeFidelidadEsperaApertura = true;
        if (typeof $ !== 'undefined') {
            $('#modal-gastro-canje-fidelidad').modal('hide');
        }
        try {
            const apertura = await resolverDatosAperturaNuevaCuenta(false, 'Cuenta libre — canje fidelidad');
            const r = await api('/ventas/gastronomia/api/abrir-cuenta', {
                method: 'POST',
                headers: hdrJson(),
                body: JSON.stringify(Object.assign({ empresa_id: empresaId }, apertura)),
            });
            await seleccionarCuenta(r.cuenta_id);
            cargarCuentasActivas();
        } catch (e) {
            canjeFidelidadEsperaApertura = false;
            if (e.message !== 'cancelado') {
                toast(e.message, 'error');
            }
        }
    }

    function puedeConfirmarCanjeFidelidadDesdeTeclado() {
        const btn = document.getElementById('gastro-canje-fidelidad-confirmar');
        return !!(
            canjeFidelidadValidado &&
            canjeFidelidadArticuloId &&
            btn &&
            !btn.disabled &&
            !canjeFidelidadValidando
        );
    }

    function manejarEnterModalCanjeFidelidad(e) {
        if (!e || e.key !== 'Enter' || e.ctrlKey || e.metaKey || e.altKey) {
            return;
        }
        const t = e.target;
        if (puedeConfirmarCanjeFidelidadDesdeTeclado()) {
            e.preventDefault();
            void confirmarCanjeFidelidad();
            return;
        }
        if (t && t.id === 'gastro-canje-fidelidad-confirmar' && !t.disabled) {
            e.preventDefault();
            void confirmarCanjeFidelidad();
            return;
        }
        if (
            t &&
            (t.id === 'gastro-canje-fidelidad-trackdata' ||
                t.classList.contains('gastro-canje-fidelidad-art-radio'))
        ) {
            e.preventDefault();
            if (canjeFidelidadInputTimer) {
                clearTimeout(canjeFidelidadInputTimer);
                canjeFidelidadInputTimer = null;
            }
            if (t.classList.contains('gastro-canje-fidelidad-art-radio') && t.checked) {
                void confirmarCanjeFidelidad();
                return;
            }
            void validarTarjetaCanjeFidelidad();
        }
    }

    async function confirmarCanjeFidelidad() {
        if (!canjeFidelidadValidado || !canjeFidelidadArticuloId) {
            mostrarErrorModalCanjeFidelidad('Valide la tarjeta y seleccione el artículo antes de continuar.');
            return;
        }
        if (G.cuentasLibresHabilitadas === false) {
            toast('Las cuentas libres no están habilitadas.', 'warning');
            return;
        }
        if (puedeAplicarCanjeFidelidadEnCuentaActual()) {
            await aplicarCanjeFidelidadACuenta();
            return;
        }
        await abrirCuentaLibreParaCanjeFidelidad();
    }

    async function intentarAplicarCanjeFidelidadPendiente(cuenta) {
        if (!canjeFidelidadEsperaApertura || !canjeFidelidadValidado || !cuentaId) {
            return;
        }
        const c = cuenta || cuentaActivaConLineas;
        if (!cuentaLibreAptaParaCanjePremio(c)) {
            canjeFidelidadEsperaApertura = false;
            resetModalCanjeFidelidad(true);
            toast('No se pudo aplicar el canje fidelidad en la cuenta libre.', 'warning');
            return;
        }
        const ok = await aplicarCanjeFidelidadACuenta({ silencioso: true });
        if (ok) {
            canjeFidelidadEsperaApertura = false;
        }
    }

    function initCanjeFidelidad() {
        const btnAbrir = document.getElementById('gastro-btn-canje-fidelidad');
        const inp = document.getElementById('gastro-canje-fidelidad-trackdata');
        const btnConfirmar = document.getElementById('gastro-canje-fidelidad-confirmar');

        if (btnAbrir) {
            btnAbrir.addEventListener('click', () => {
                if (bloquearOperacionPosPorJornadaTurno()) {
                    return;
                }
                if (!G.wigosAccountInfoHabilitado) {
                    toast('Consulta de tarjeta Wigos no habilitada.', 'warning');
                    return;
                }
                if (G.cuentasLibresHabilitadas === false) {
                    toast('Las cuentas libres no están habilitadas.', 'warning');
                    return;
                }
                resetModalCanjeFidelidad(true);
                if (typeof $ !== 'undefined') {
                    $('#modal-gastro-canje-fidelidad').one('shown.bs.modal', function () {
                        const c = document.getElementById('gastro-canje-fidelidad-trackdata');
                        if (c) c.focus();
                        actualizarBotonConfirmarCanjeFidelidad();
                    });
                    $('#modal-gastro-canje-fidelidad').modal('show');
                }
            });
        }

        if (inp) {
            inp.addEventListener('input', () => programarValidacionCanjeFidelidadTrasLectura());
            inp.addEventListener('keydown', manejarEnterModalCanjeFidelidad);
        }

        if (btnConfirmar) {
            btnConfirmar.addEventListener('click', () => void confirmarCanjeFidelidad());
            btnConfirmar.addEventListener('keydown', manejarEnterModalCanjeFidelidad);
        }

        if (typeof $ !== 'undefined') {
            $('#modal-gastro-canje-fidelidad').on('keydown.gastroCanjeFid', manejarEnterModalCanjeFidelidad);
            $('#modal-gastro-canje-fidelidad').on('hidden.bs.modal', function () {
                if (!canjeFidelidadEsperaApertura) {
                    resetModalCanjeFidelidad(true);
                }
            });
        }
    }

    let canjeTicketValidado = null;

    function resetModalCanjeTicket() {
        canjeTicketValidado = null;
        const inp = document.getElementById('gastro-canje-codigo-barras');
        const err = document.getElementById('gastro-canje-ticket-error');
        const prev = document.getElementById('gastro-canje-ticket-preview');
        const btn = document.getElementById('gastro-canje-ticket-confirmar');
        if (inp) inp.value = '';
        if (err) {
            err.textContent = '';
            err.classList.add('d-none');
        }
        if (prev) prev.classList.add('d-none');
        if (btn) btn.disabled = true;
    }

    function mostrarErrorModalCanje(msg) {
        const err = document.getElementById('gastro-canje-ticket-error');
        const prev = document.getElementById('gastro-canje-ticket-preview');
        const btn = document.getElementById('gastro-canje-ticket-confirmar');
        canjeTicketValidado = null;
        if (prev) prev.classList.add('d-none');
        if (btn) btn.disabled = true;
        if (err) {
            err.textContent = msg || 'No se pudo validar el ticket.';
            err.classList.remove('d-none');
        }
    }

    function formatearFechaIsoADisplay(iso) {
        if (!iso) return '—';
        const p = String(iso).split('-');
        if (p.length !== 3) return iso;
        return `${p[2]}/${p[1]}/${p[0]}`;
    }

    async function validarCodigoBarrasCanjeTicket() {
        const inp = document.getElementById('gastro-canje-codigo-barras');
        const codigo = (inp?.value || '').trim();
        if (!codigo) {
            mostrarErrorModalCanje('Ingrese o escanee el código de barras.');
            return;
        }
        if (totalFacturadoArs <= 0) {
            mostrarErrorModalCanje('Calcule el total a facturar antes de canjear tickets.');
            return;
        }
        if (G.cuentacajaCanjeTarjetaError) {
            mostrarErrorModalCanje(G.cuentacajaCanjeTarjetaError);
            return;
        }

        const errBox = document.getElementById('gastro-canje-ticket-error');
        if (errBox) errBox.classList.add('d-none');

        try {
            const data = await api('/ventas/gastronomia/api/validar-ticket-tarjeta', {
                method: 'POST',
                headers: hdrJson(),
                body: JSON.stringify({
                    codigo_barras: codigo,
                    total_factura_ars: totalFacturadoArs,
                    monto_cobranza_ya_cargado_ars: totalCobranzaArsActual(),
                    tickets_ya_seleccionados: recogerTicketsYaSeleccionados(),
                }),
            });
            if (!data.ok) {
                mostrarErrorModalCanje(data.error || data.mensaje || 'Ticket no válido.');
                return;
            }
            canjeTicketValidado = data;
            const prev = document.getElementById('gastro-canje-ticket-preview');
            const set = (id, val) => {
                const el = document.getElementById(id);
                if (el) el.textContent = val;
            };
            set('gastro-canje-preview-importe', '$ ' + (parseFloat(data.importe) || 0).toFixed(2));
            set('gastro-canje-preview-fecha', formatearFechaIsoADisplay(data.fecha_emision));
            set('gastro-canje-preview-documento', data.numerodocumento || '—');
            set('gastro-canje-preview-monto', '$ ' + (parseFloat(data.monto_aplicar) || 0).toFixed(2));
            if (prev) prev.classList.remove('d-none');
            const btn = document.getElementById('gastro-canje-ticket-confirmar');
            if (btn) btn.disabled = false;
        } catch (e) {
            mostrarErrorModalCanje(e.message || String(e));
        }
    }

    function agregarTicketCanjeACobranza() {
        if (bloquearOperacionPosPorJornadaTurno()) {
            return;
        }
        if (!canjeTicketValidado || !canjeTicketValidado.cuentacaja) {
            mostrarErrorModalCanje('Valide el ticket antes de agregarlo.');
            return;
        }
        const tr = filaCobranzaDesdeTemplate();
        if (!tr) return;
        document.getElementById('tbody-gastro-cuenta-table').appendChild(tr);
        wireEventosFilaCobranza(tr);
        asignarCuentaCajaEnFila(tr, canjeTicketValidado.cuentacaja, { asignacionAutomatica: true });
        const ticketIdInp = tr.querySelector('.ticket_id');
        const numeroTicketInp = tr.querySelector('.numeroticket');
        const montoInp = tr.querySelector('.monto');
        if (ticketIdInp) ticketIdInp.value = canjeTicketValidado.ticket_id;
        if (numeroTicketInp) numeroTicketInp.value = canjeTicketValidado.numeroticket;
        if (montoInp) {
            montoInp.value = (parseFloat(canjeTicketValidado.monto_aplicar) || 0).toFixed(2);
            montoInp.dataset.montoEditadoManual = '1';
            montoInp.readOnly = true;
        }
        const codInp = tr.querySelector('.codigo');
        if (codInp) codInp.readOnly = true;
        sumarMontosCobranza();
        if (typeof $ !== 'undefined') {
            $('#modal-gastro-canje-ticket-tarjeta').modal('hide');
        }
        toast('Ticket agregado a la cobranza.', 'success');
    }

    function abrirModalCanjeTicketTarjeta() {
        if (bloquearOperacionPosPorJornadaTurno()) {
            return;
        }
        if (G.cuentacajaCanjeTarjetaError) {
            toast(G.cuentacajaCanjeTarjetaError, 'warning');
            return;
        }
        resetModalCanjeTicket();
        if (typeof $ !== 'undefined') {
            $('#modal-gastro-canje-ticket-tarjeta').one('shown.bs.modal', function () {
                const c = document.getElementById('gastro-canje-codigo-barras');
                if (c) c.focus();
            });
            $('#modal-gastro-canje-ticket-tarjeta').modal('show');
        }
    }

    function initCanjeTicketTarjeta() {
        const inp = document.getElementById('gastro-canje-codigo-barras');
        const btnConfirmar = document.getElementById('gastro-canje-ticket-confirmar');
        if (inp) {
            inp.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    void validarCodigoBarrasCanjeTicket();
                }
            });
            inp.addEventListener('change', () => {
                if ((inp.value || '').trim().length >= 7) {
                    void validarCodigoBarrasCanjeTicket();
                }
            });
        }

        if (btnConfirmar) {
            btnConfirmar.addEventListener('click', () => agregarTicketCanjeACobranza());
        }

        if (typeof $ !== 'undefined') {
            $('#modal-gastro-canje-ticket-tarjeta').on('hidden.bs.modal', resetModalCanjeTicket);
        }
    }

    async function cargarMesas() {
        const data = await api(`/ventas/gastronomia/api/mesas?empresa_id=${empresaId}`, { headers: hdrJson() });
        const panel = document.getElementById('panel-mesas');
        panel.innerHTML = '';
        (data.mesas || []).forEach((m) => {
            const esActiva = cuentaId && cuentaActivaMesaId === m.id;
            let cls = m.ocupada ? 'warning' : 'light';
            if (esActiva) cls = 'primary';
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = `btn btn-sm btn-${cls} m-1${esActiva ? ' btn-gastro-mesa-activa' : ''}`;
            btn.textContent = `${m.numeromesa} ${m.ocupada ? '(abierta)' : ''}${esActiva ? ' ★' : ''}`;
            btn.addEventListener('click', () => abrirMesa(m.id, !!m.ocupada));
            panel.appendChild(btn);
        });
    }

    async function cargarOrdenesWaitry(opciones) {
        opciones = opciones || {};
        const panel = document.getElementById('panel-waitry-lista');
        const vacio = document.getElementById('panel-waitry-vacio');
        if (!panel || !waitryHabilitadoEnPos()) {
            return;
        }
        panel.innerHTML =
            '<div class="col-12">' +
            htmlCargaProcesoInline(
                'Leyendo órdenes Waitry…',
                'Consultando comandas pendientes de cobro en el tótem.',
                'text-info',
            ) +
            '</div>';
        if (vacio) {
            vacio.classList.add('d-none');
        }
        try {
            const urlBase =
                (G.rutasWaitry && G.rutasWaitry.ordenesPendientes) ||
                '/ventas/gastronomia/api/waitry-ordenes-pendientes';
            const url = opciones.refresh ? urlBase + (urlBase.indexOf('?') >= 0 ? '&' : '?') + 'refresh=1' : urlBase;
            const data = await api(url, { headers: hdrJson() });
            actualizarLeyendaFiltroWaitry(data.filtro);
            panel.innerHTML = '';
            const ordenes = data.ordenes || [];
            if (ordenes.length === 0) {
                if (vacio) {
                    vacio.classList.remove('d-none');
                }
                return;
            }
            ordenes.forEach((o) => {
                const idNum = o.waitry_order_id;
                const displayId = o.display_id ? String(o.display_id).trim() : '';
                const sinSku = !!o.requiere_mapeo_sku;
                const unidades = o.cantidad_unidades != null ? o.cantidad_unidades : o.cantidad_items;
                const unidadesLabel =
                    unidades != null && Number(unidades) > 0
                        ? ' · ' +
                          (Number(unidades) === Math.floor(Number(unidades))
                              ? String(Math.floor(Number(unidades)))
                              : Number(unidades).toFixed(2)) +
                          (Number(unidades) === 1 ? ' ud.' : ' uds.')
                        : '';
                const label =
                    (displayId ? displayId + ' ' : '') +
                    '#' +
                    idNum +
                    unidadesLabel +
                    (o.total_estimado != null ? ' · $' + Number(o.total_estimado).toFixed(2) : '') +
                    (sinSku ? ' ⚠' : '');
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className =
                    'btn btn-sm m-1' +
                    (cuentaId &&
                    cuentaActivaConLineas &&
                    Number(cuentaActivaConLineas.waitry_order_id) === Number(idNum)
                        ? ' btn-primary'
                        : sinSku
                          ? ' btn-outline-warning'
                          : ' btn-outline-info');
                btn.title =
                    (sinSku
                        ? 'Ítems sin SKU en Waitry (externalId). Sincronice el menú o contacte soporte Waitry para habilitar getOrdersPOS.\n'
                        : '') +
                    (o.lineas_preview || [])
                        .map((ln) => (ln.cantidad || 1) + '× ' + (ln.sku || '—') + ' ' + (ln.titulo || ''))
                        .join('\n');
                btn.textContent = label;
                btn.addEventListener('click', () => void importarOrdenWaitry(o));
                panel.appendChild(btn);
            });
        } catch (e) {
            panel.innerHTML = '';
            toast(e.message || 'Error al cargar órdenes Waitry', 'error');
        }
    }

    function abrirModalWaitryImportarPorId() {
        if (bloquearOperacionPosPorJornadaTurno()) {
            return;
        }
        if (!waitryHabilitadoEnPos()) {
            toast('Integración Waitry no habilitada.', 'warning');
            return;
        }
        const input = document.getElementById('waitry-importar-id-input');
        if (input) {
            input.value = '';
        }
        $('#modal-waitry-importar-id').modal('show');
    }

    async function confirmarModalWaitryImportarPorId() {
        const input = document.getElementById('waitry-importar-id-input');
        const identificador = input ? String(input.value || '').trim() : '';
        if (!identificador || !/^[A-Za-z0-9_-]+$/.test(identificador)) {
            toast('Ingrese el ID alfanumérico del papelito Waitry.', 'warning');
            if (input) {
                input.focus();
            }
            return;
        }
        $('#modal-waitry-importar-id').modal('hide');
        await importarOrdenWaitry({ waitry_order_id: identificador, display_id: identificador }, { importarPorId: true });
    }

    async function importarOrdenWaitry(orden, opciones) {
        if (bloquearOperacionPosPorJornadaTurno()) {
            return;
        }
        opciones = opciones || {};
        if (!orden || (orden.waitry_order_id == null && !orden.display_id)) {
            return;
        }
        const idNumerico = orden.waitry_order_id != null ? String(orden.waitry_order_id) : '';
        const idDisplay = orden.display_id ? String(orden.display_id).trim() : '';
        const identificadorImport = idDisplay || idNumerico;
        if (!identificadorImport) {
            return;
        }
        if (orden.requiere_mapeo_sku && !opciones.incluirOrdenPagada && !opciones.importarPorId) {
            toast(
                'La orden Waitry no trae SKU (externalId) en los ítems. Sincronice el menú en Waitry o use «Por ID» si el tótem ya cobró.',
                'warning',
            );
            return;
        }
        let datosApertura = {};
        if (requiereDatosAperturaAlAbrir()) {
            try {
                datosApertura = await resolverDatosAperturaNuevaCuenta(
                    false,
                    'Importar cuenta Waitry ' +
                        (idDisplay ? idDisplay : '#' + (idNumerico || identificadorImport)),
                );
            } catch (_) {
                return;
            }
        }
        try {
            iniciarRotacionMensajesProceso(mensajesProcesoImportWaitry(identificadorImport), 2400);
            const payload = {
                waitry_order_id: identificadorImport,
                importar_por_id: opciones.importarPorId === true,
                incluir_orden_pagada: opciones.incluirOrdenPagada === true,
                cubiertos: datosApertura.cubiertos,
                mozo_gastronomia_id: datosApertura.mozo_gastronomia_id,
            };
            if (cuentaId && cuentaActivaConLineas && !(cuentaActivaConLineas.lineas || []).length) {
                payload.cuenta_id = cuentaId;
            }
            const data = await api(
                (G.rutasWaitry && G.rutasWaitry.importarOrden) || '/ventas/gastronomia/api/waitry-importar-orden',
                {
                    method: 'POST',
                    headers: hdrJson(),
                    body: JSON.stringify(payload),
                },
            );
            detenerRotacionMensajesProceso();
            if (data.errores && data.errores.length) {
                const detalleItems = data.errores.join(' · ');
                if (!data.warn) {
                    data.warn = detalleItems;
                } else if (data.warn.indexOf(detalleItems) < 0) {
                    data.warn = data.warn + '\n\n' + detalleItems;
                }
            }
            await seleccionarCuenta(data.cuenta_id);
            mostrarResultadoImportacionWaitry(data, identificadorImport);
            void cargarOrdenesWaitry({ refresh: true });
            cargarCuentasActivas();
        } catch (e) {
            const det = e.payload && e.payload.errores ? e.payload.errores.join(' · ') : '';
            toast((e.message || 'Error al importar') + (det ? ': ' + det : ''), 'error');
        } finally {
            setFacturacionLoading(false);
        }
    }

    async function cargarCuentasActivas() {
        const data = await api(`/ventas/gastronomia/api/cuentas-activas?empresa_id=${empresaId}`, { headers: hdrJson() });
        const panel = document.getElementById('panel-cuentas');
        panel.innerHTML = '';
        (data.cuentas || []).forEach((c) => {
            const esActiva = cuentaId === c.id;
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className =
                'btn btn-sm m-1' +
                (esActiva ? ' btn-primary btn-gastro-cuenta-activa' : ' btn-outline-primary');
            btn.textContent = 'Cuenta #' + c.id + (esActiva ? ' ★' : '');
            btn.addEventListener('click', () => seleccionarCuenta(c.id));
            panel.appendChild(btn);
        });
    }

    function resetCamposModalAbrirCuenta(titulo) {
        const tit = document.getElementById('modal-abrir-cuenta-titulo');
        if (tit) tit.textContent = titulo || 'Abrir cuenta';
        const cub = document.getElementById('abrir-cubiertos');
        if (cub) cub.value = String(cubiertosDefaultApertura());
        const mozoId = document.getElementById('abrir-mozo_gastronomia_id');
        const mozoCod = document.getElementById('abrir-codigomozo');
        const mozoNom = document.getElementById('abrir-nombremozo');
        if (mozoId) mozoId.value = '';
        if (mozoCod) mozoCod.value = '';
        if (mozoNom) mozoNom.value = '';
    }

    function leerDatosAperturaDesdeModal() {
        const cub = document.getElementById('abrir-cubiertos');
        const mozoIdEl = document.getElementById('abrir-mozo_gastronomia_id');
        const mozoId = mozoIdEl && mozoIdEl.value ? parseInt(mozoIdEl.value, 10) : 0;
        return {
            cubiertos: cub ? cub.value : cubiertosDefaultApertura(),
            mozo_gastronomia_id: mozoId > 0 ? mozoId : null,
        };
    }

    function focusCodigoMozoModalAbrir() {
        const el = document.getElementById('abrir-codigomozo');
        if (el && typeof el.focus === 'function') {
            el.focus();
            if (typeof el.select === 'function') {
                el.select();
            }
        }
    }

    function confirmarModalAbrirTrasMozo() {
        const datos = leerDatosAperturaDesdeModal();
        const errMsg = validarDatosAperturaCliente(datos);
        if (errMsg) {
            toast(errMsg, 'warning');
            focusCodigoMozoModalAbrir();
            return;
        }
        confirmarModalAbrirCuenta();
    }

    function aplicarMozoEnModalAbrir(data) {
        const wrap = document.getElementById('modal-abrir-cuenta-mozo-wrap');
        if (!wrap || !data || !data.id) {
            return false;
        }
        const idEl = wrap.querySelector('.mozo_gastronomia_id');
        const codEl = wrap.querySelector('.codigomozo');
        const nomEl = wrap.querySelector('.nombremozo');
        if (idEl) idEl.value = data.id;
        if (codEl) codEl.value = data.codigo != null ? String(data.codigo) : '';
        if (nomEl) nomEl.value = data.nombre || '';
        return true;
    }

    async function cargarMozoPorCodigoModalAbrir(codigo) {
        const cod = String(codigo || '').trim();
        if (!cod) {
            return null;
        }
        const q = empresaId ? `?empresa_id=${encodeURIComponent(empresaId)}` : '';
        try {
            const data = await api(
                `/ventas/mozo-gastronomia/leer/${encodeURIComponent(cod)}${q}`,
                { headers: hdrJson() },
            );
            if (data && data.id) {
                aplicarMozoEnModalAbrir(data);
                return data;
            }
        } catch (_) {
            /* mozo no encontrado */
        }
        return null;
    }

    async function resolverCodigoMozoModalAbrir(opciones) {
        const opts = opciones || {};
        const codEl = document.getElementById('abrir-codigomozo');
        const cod = codEl ? String(codEl.value || '').trim() : '';
        if (cod) {
            const data = await cargarMozoPorCodigoModalAbrir(cod);
            if (!data) {
                toast('Mozo no encontrado.', 'warning');
                focusCodigoMozoModalAbrir();
                return false;
            }
        } else if (opts.confirmar && G.mozoObligatorioAlAbrir) {
            toast('Indique el código del mozo.', 'warning');
            focusCodigoMozoModalAbrir();
            return false;
        }
        if (opts.confirmar) {
            confirmarModalAbrirTrasMozo();
            return true;
        }
        const btn = document.getElementById('modal-abrir-cuenta-confirmar');
        if (btn && typeof btn.focus === 'function') {
            btn.focus();
        }
        return true;
    }

    function onKeydownCodigoMozoModalAbrir(e) {
        const esEnter = e.key === 'Enter';
        const esTab = e.key === 'Tab' && !e.shiftKey;
        if (!esEnter && !esTab) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        if (esEnter) {
            void resolverCodigoMozoModalAbrir({ confirmar: true });
        } else {
            void resolverCodigoMozoModalAbrir({ confirmar: false });
        }
    }

    function validarDatosAperturaCliente(datos) {
        const cub = parseInt(String(datos.cubiertos ?? ''), 10);
        if (G.cubiertosObligatorioAlAbrir && (!(cub > 0) || Number.isNaN(cub))) {
            return 'Indique la cantidad de cubiertos.';
        }
        if (G.mozoObligatorioAlAbrir && !datos.mozo_gastronomia_id) {
            return 'Seleccione el mozo.';
        }
        return null;
    }

    function cerrarPromesaAperturaCuenta(err, datos) {
        const resolve = pendingAbrirCuentaResolver;
        const reject = pendingAbrirCuentaReject;
        pendingAbrirCuentaResolver = null;
        pendingAbrirCuentaReject = null;
        if (err && reject) {
            reject(err);
        } else if (resolve) {
            resolve(datos || {});
        }
    }

    function solicitarDatosAperturaCuenta(titulo) {
        return new Promise((resolve, reject) => {
            pendingAbrirCuentaResolver = resolve;
            pendingAbrirCuentaReject = reject;
            resetCamposModalAbrirCuenta(titulo);
            if (typeof $ !== 'undefined') {
                const $modal = $('#modal-abrir-cuenta');
                $modal.off('shown.bs.modal.gastroAbrir');
                $modal.on('shown.bs.modal.gastroAbrir', function () {
                    focusCodigoMozoModalAbrir();
                });
                $modal.modal('show');
            }
        });
    }

    function confirmarModalAbrirCuenta() {
        const datos = leerDatosAperturaDesdeModal();
        const errMsg = validarDatosAperturaCliente(datos);
        if (errMsg) {
            toast(errMsg, 'warning');
            focusCodigoMozoModalAbrir();
            return;
        }
        cerrarPromesaAperturaCuenta(null, datos);
        if (typeof $ !== 'undefined') {
            $('#modal-abrir-cuenta').modal('hide');
        }
    }

    function cancelarModalAbrirCuenta() {
        if (canjePremioEsperaApertura) {
            canjePremioEsperaApertura = false;
        }
        if (canjeFidelidadEsperaApertura) {
            canjeFidelidadEsperaApertura = false;
        }
        cerrarPromesaAperturaCuenta(new Error('cancelado'));
    }

    async function resolverDatosAperturaNuevaCuenta(mesaOcupada, tituloModal) {
        if (mesaOcupada) {
            return {};
        }
        if (!requiereDatosAperturaAlAbrir()) {
            return {
                cubiertos: cubiertosDefaultApertura(),
                mozo_gastronomia_id: null,
            };
        }
        return solicitarDatosAperturaCuenta(tituloModal);
    }

    async function abrirMesa(mesaId, mesaOcupada) {
        if (bloquearOperacionPosPorJornadaTurno()) {
            return;
        }
        try {
            let body = { mesa_id: mesaId, empresa_id: empresaId };
            if (!mesaOcupada) {
                const apertura = await resolverDatosAperturaNuevaCuenta(false, 'Abrir mesa');
                body = Object.assign(body, apertura);
            }
            const r = await api('/ventas/gastronomia/api/abrir-mesa', {
                method: 'POST',
                headers: hdrJson(),
                body: JSON.stringify(body),
            });
            seleccionarCuenta(r.cuenta_id);
            toast(r.reutilizada ? 'Mesa ya abierta — cargando cuenta.' : 'Mesa abierta.', 'success');
            cargarMesas();
        } catch (e) {
            if (e.message !== 'cancelado') {
                toast(e.message, 'error');
            }
        }
    }

    async function nuevaCuentaLibre() {
        if (bloquearOperacionPosPorJornadaTurno()) {
            return;
        }
        if (G.cuentasLibresHabilitadas === false) {
            return toast('Las cuentas libres no están habilitadas.', 'warning');
        }
        try {
            const apertura = await resolverDatosAperturaNuevaCuenta(false, 'Nueva cuenta libre');
            const r = await api('/ventas/gastronomia/api/abrir-cuenta', {
                method: 'POST',
                headers: hdrJson(),
                body: JSON.stringify(Object.assign({ empresa_id: empresaId }, apertura)),
            });
            seleccionarCuenta(r.cuenta_id);
            toast('Cuenta libre creada.', 'success');
            cargarCuentasActivas();
        } catch (e) {
            if (e.message !== 'cancelado') {
                toast(e.message, 'error');
            }
        }
    }

    function getTrLineaArticulo() {
        return document.getElementById('tr-gastro-linea-articulo');
    }

    function limpiarFormularioArticuloLinea() {
        const tr = getTrLineaArticulo();
        if (!tr) return;
        tr.querySelector('.articulo_id').value = '';
        const cod = tr.querySelector('.codigoarticulo');
        if (cod) cod.value = '';
        const suf = tr.querySelector('.gastro-sku-sufijo');
        if (suf) suf.value = '';
        tr.querySelector('.descripcionarticulo').value = '';
        tr.querySelector('.categoria_id').value = '';
        tr.querySelector('.subcategoria_id').value = '';
        tr.querySelector('.unidadmedida_id').value = '';
    }

    function focusSkuConsumo() {
        const tr = getTrLineaArticulo();
        if (!tr) return;
        if (!cuentaId) {
            const act = document.activeElement;
            if (act && tr.contains(act) && typeof act.blur === 'function') {
                act.blur();
            }
            return;
        }
        const el = tr.querySelector('.gastro-sku-sufijo') || tr.querySelector('.gastro-carga-sku');
        if (el && typeof el.focus === 'function') {
            el.focus();
            if (typeof el.select === 'function') el.select();
        }
    }

    function composeSkuDesdeSufijoDigitos(sufijoRaw) {
        const digits = String(sufijoRaw || '').replace(/\D/g, '');
        if (!digits) return '';
        if (skuDigitosSufijo <= 0) return '';
        const padded = digits.padStart(skuDigitosSufijo, '0').slice(-skuDigitosSufijo);
        return prefijoSku + padded;
    }

    function syncSufijoDesdeSkuCompleto(sku) {
        const tr = getTrLineaArticulo();
        if (!tr || skuDigitosSufijo <= 0) return;
        const el = tr.querySelector('.gastro-sku-sufijo');
        if (!el) return;
        if (!sku || !skuPermitidoGastronomia(sku)) {
            el.value = '';
            return;
        }
        const p = prefijoSku.toUpperCase();
        const s = String(sku).toUpperCase();
        if (!s.startsWith(p)) {
            el.value = '';
            return;
        }
        const tail = s.slice(p.length).replace(/\D/g, '') || '0';
        const padded = tail.padStart(skuDigitosSufijo, '0').slice(-skuDigitosSufijo);
        el.value = String(parseInt(padded, 10));
    }

    function skuIngresadoEnFila() {
        const tr = getTrLineaArticulo();
        if (!tr) return '';
        if (skuDigitosSufijo > 0) {
            const suf = tr.querySelector('.gastro-sku-sufijo');
            return composeSkuDesdeSufijoDigitos(suf ? suf.value : '');
        }
        const cod = tr.querySelector('.codigoarticulo');
        return (cod && cod.value ? cod.value : '').trim();
    }

    async function fetchArticuloCatalogoPorSku(fullSku) {
        const enc = encodeURIComponent(fullSku);
        return api(`/ventas/gastronomia/api/articulo-catalogo-por-sku?sku=${enc}`, { headers: hdrJson() });
    }

    async function patchCantidadLinea(lineaId, nuevaCantidad) {
        if (bloquearOperacionPosPorJornadaTurno()) {
            return;
        }
        if (!cuentaId) return;
        if (!(nuevaCantidad >= 0.0001)) {
            toast('La cantidad no puede ser menor al mínimo permitido.', 'warning');
            return;
        }
        try {
            const data = await api(`/ventas/gastronomia/api/cuenta/${cuentaId}/linea/${lineaId}`, {
                method: 'PATCH',
                headers: hdrJson(),
                body: JSON.stringify({ cantidad: nuevaCantidad }),
            });
            pintarLineas(data.cuenta);
            focusSkuConsumo();
        } catch (e) {
            toast(e.message, 'error');
        }
    }

    function articuloSeleccionadoEnFila() {
        const tr = getTrLineaArticulo();
        if (!tr) return null;
        const id = parseInt(tr.querySelector('.articulo_id').value || '0', 10);
        if (!id) return null;
        const codEl = tr.querySelector('.codigoarticulo');
        let sku = codEl ? (codEl.value || '').trim() : '';
        if (!sku) {
            sku = skuIngresadoEnFila();
        }
        const descripcion = (tr.querySelector('.descripcionarticulo').value || '').trim();
        return { id, sku, descripcion };
    }

    function skuPermitidoGastronomia(sku) {
        const s = (sku || '').toUpperCase();
        const p = prefijoSku.toUpperCase();
        if (skuDigitosSufijo > 0) {
            const esc = p.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            return new RegExp(`^${esc}\\d{${skuDigitosSufijo}}$`).test(s);
        }
        return s.startsWith(p);
    }

    function mensajeSkuCatalogoGastronomia() {
        if (skuDigitosSufijo > 0) {
            return (
                prefijoSku +
                ' seguido de ' +
                skuDigitosSufijo +
                ' dígitos (ej. ' +
                prefijoSku +
                '0'.repeat(Math.max(0, skuDigitosSufijo - 1)) +
                '1)'
            );
        }
        return 'SKU debe comenzar con ' + prefijoSku;
    }

    async function seleccionarCuenta(id) {
        cuentaId = id;
        document.getElementById('btn-cerrar-cuenta').classList.remove('d-none');
        actualizarIndicadorCuentaActiva({ id: id });
        try {
            const data = await api(`/ventas/gastronomia/api/cuenta/${id}`, { headers: hdrJson() });
            const c = data.cuenta;
            const cli = c.cliente || null;
            const idInternoCf = c.cliente_id && c.factura_consumidor_final ? '' : c.cliente_id || '';
            document.getElementById('cliente_id').value = idInternoCf;
            if (c.factura_consumidor_final && !idInternoCf) {
                document.getElementById('nombrecliente').value = c.receptor_factura_nombre || 'CONSUMIDOR FINAL';
                document.getElementById('codigocliente').value = '';
            } else {
                document.getElementById('nombrecliente').value = cli ? cli.nombre || '' : '';
                document.getElementById('codigocliente').value = cli && cli.codigo != null ? String(cli.codigo) : '';
            }
            document.getElementById('fld-cubiertos').value = c.cubiertos || 0;
            const mozo = c.mozo || null;
            document.getElementById('mozo_gastronomia_id').value = c.mozo_gastronomia_id || '';
            document.getElementById('codigomozo').value = mozo && mozo.codigo != null ? String(mozo.codigo) : '';
            document.getElementById('nombremozo').value = mozo ? mozo.nombre || '' : '';
            const desc = c.descuento_gastronomia || null;
            if (typeof pintarDescuentoEnPantalla === 'function') {
                pintarDescuentoEnPantalla(
                    desc
                        ? {
                              id: desc.id,
                              codigo: desc.codigo,
                              nombre: desc.nombre,
                              cliente: desc.cliente || null,
                          }
                        : null,
                );
            } else {
                document.getElementById('descuento_gastronomia_id').value = c.descuento_gastronomia_id || '';
            }
            const cliInterno = c.cliente_interno_descuento || null;
            if (cliInterno && typeof aplicarClienteInternoDescuentoEnPantalla === 'function') {
                aplicarClienteInternoDescuentoEnPantalla(cliInterno);
            } else if (desc && desc.cliente && typeof aplicarClienteInternoDescuentoEnPantalla === 'function') {
                aplicarClienteInternoDescuentoEnPantalla(desc.cliente);
            }
            const fn = document.getElementById('fld-factura-receptor-nombre');
            const fd = document.getElementById('fld-factura-receptor-documento');
            const fdom = document.getElementById('fld-factura-receptor-domicilio');
            if (fn) fn.value = c.factura_receptor_nombre || '';
            if (fd) fd.value = c.factura_receptor_documento || '';
            if (fdom) fdom.value = c.factura_receptor_domicilio || '';
            pintarLineas(c);
            limpiarFormularioArticuloLinea();
            cargarMesas();
            cargarCuentasActivas();
            setTimeout(() => focusSkuConsumo(), 50);
            await intentarAplicarCanjePremioPendiente(c);
            await intentarAplicarCanjeFidelidadPendiente(c);
            actualizarBtnConfirmarCanjePremio();
            actualizarBotonConfirmarCanjeFidelidad();
        } catch (e) {
            toast(e.message, 'error');
        }
    }

    function subtotalEstimadoDesdeCuenta(cuenta) {
        if (cuenta && cuenta.total_facturar_ars != null && !Number.isNaN(Number(cuenta.total_facturar_ars))) {
            return Number(cuenta.total_facturar_ars);
        }
        if (cuenta && cuenta.subtotal_estimado != null && !Number.isNaN(Number(cuenta.subtotal_estimado))) {
            return Number(cuenta.subtotal_estimado);
        }
        if (evaluarFacturaCortesiaDesdeCuenta(cuenta).cortesia) {
            return IMPORTE_MINIMO_FACTURA;
        }
        let sub = subtotalBrutoLineasCuenta(cuenta);
        const desc = cuenta.descuento_gastronomia || null;
        if (desc) {
            const val = Number(desc.valor || 0);
            if (desc.tipovalor === 'P') {
                sub *= 1 - val / 100;
            } else if (desc.tipovalor === 'I') {
                sub -= val;
            }
        }
        return Math.max(0, Math.round(sub * 100) / 100);
    }

    function actualizarEtiquetaReceptorFactura(cuenta) {
        const nomInp = document.getElementById('nombrecliente');
        if (!nomInp) return;
        if (cuenta && cuenta.factura_consumidor_final) {
            const nombreCf =
                cuenta.receptor_factura_nombre ||
                (typeof G !== 'undefined' && G.receptorCfNombre) ||
                'CONSUMIDOR FINAL';
            if (!tieneClienteMaestroAsignado()) {
                nomInp.value = nombreCf;
                nomInp.placeholder = nombreCf;
            }
        }
    }

    function pintarLineas(cuenta) {
        cuentaActivaConLineas = cuenta;
        if (cuenta && cuenta.descuento_gastronomia) {
            lastDescuentoGastronomiaMeta = {
                tipovalor: cuenta.descuento_gastronomia.tipovalor,
                valor: cuenta.descuento_gastronomia.valor,
            };
        } else if (!tieneDescuentoEnPantalla()) {
            lastDescuentoGastronomiaMeta = null;
        }
        const wrap = document.getElementById('panel-detalle-lineas');
        const sub = subtotalEstimadoDesdeCuenta(cuenta);
        let html = '<table class="table table-sm table-striped mb-0"><thead><tr><th>#</th><th>Artículo</th><th>Cant.</th><th>P.U.</th><th></th></tr></thead><tbody>';
        (cuenta.lineas || []).forEach((ln) => {
            const pu = Number(ln.precio_unitario);
            const cant = Number(ln.cantidad);
            const opcDetalleHtml = htmlOpcionalesDetalleLinea(ln);
            html += `<tr>
        <td>${ln.numero_linea}</td>
        <td>${ln.articulo ? escaparHtmlOpcional(ln.articulo.sku) + ' — ' + escaparHtmlOpcional(ln.articulo.descripcion) : ''}${opcDetalleHtml}</td>
        <td class="text-nowrap align-middle">
          <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 btn-gastro-qty" data-dir="-1" data-linea="${ln.id}" data-cant="${cant}" title="Menos">−</button>
          <span class="mx-1">${cant}</span>
          <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 btn-gastro-qty" data-dir="1" data-linea="${ln.id}" data-cant="${cant}" title="Más">+</button>
        </td>
        <td>${pu.toFixed(2)}</td>
        <td><button type="button" class="btn btn-sm btn-link text-danger btn-del-linea" data-linea="${ln.id}">quitar</button></td>
      </tr>`;
        });
        html += '</tbody></table>';
        html += htmlDetalleSubtotalConDescuento(cuenta, sub);
        wrap.innerHTML = html;
        cuentaActivaSubtotalArs = sub;
        setTotalFacturadoArs(sub);
        actualizarIndicadorCuentaActiva(cuenta, sub);
        actualizarPanelReceptorManual(sub);
        actualizarEtiquetaReceptorFactura(cuenta);
        wrap.querySelectorAll('.btn-del-linea').forEach((b) =>
            b.addEventListener('click', () => eliminarLinea(b.getAttribute('data-linea'))),
        );
        wrap.querySelectorAll('.btn-gastro-qty').forEach((b) => {
            b.addEventListener('click', () => {
                const lineaId = b.getAttribute('data-linea');
                const cur = parseFloat(b.getAttribute('data-cant'));
                const dir = parseInt(b.getAttribute('data-dir'), 10);
                const next = cur + dir;
                if (!(next >= 0.0001)) {
                    toast('La cantidad no puede ser menor al mínimo permitido.', 'warning');
                    return;
                }
                void patchCantidadLinea(lineaId, next);
            });
        });
        void aplicarCobranzaWaitryTotemSiCorresponde(cuenta);
    }

    function bodyCabeceraCuenta() {
        const cid = document.getElementById('cliente_id').value;
        const cliInterno = (document.getElementById('cliente_descuento_id').value || '').trim();
        const descId = (document.getElementById('descuento_gastronomia_id').value || '').trim();
        return {
            cliente_id: cid && String(cid).trim() !== '' ? cid : null,
            cubiertos: document.getElementById('fld-cubiertos').value,
            mozo_gastronomia_id: document.getElementById('mozo_gastronomia_id').value || null,
            descuento_gastronomia_id: descId || null,
            cliente_interno_descuento_id: descId && cliInterno ? cliInterno : null,
            factura_receptor_nombre: (document.getElementById('fld-factura-receptor-nombre') || {}).value || '',
            factura_receptor_documento: (document.getElementById('fld-factura-receptor-documento') || {}).value || '',
            factura_receptor_domicilio: (document.getElementById('fld-factura-receptor-domicilio') || {}).value || '',
        };
    }

    async function guardarCabecera(silencioso) {
        if (!silencioso && bloquearOperacionPosPorJornadaTurno()) {
            return null;
        }
        if (!cuentaId) {
            if (!silencioso) toast('Seleccione mesa/cuenta', 'warning');
            return null;
        }
        try {
            const data = await api(`/ventas/gastronomia/api/cuenta/${cuentaId}`, {
                method: 'PATCH',
                headers: hdrJson(),
                body: JSON.stringify(bodyCabeceraCuenta()),
            });
            if (!silencioso) toast('Cabecera guardada.', 'success');
            pintarLineas(data.cuenta);
            return data.cuenta;
        } catch (e) {
            if (!silencioso) toast(e.message, 'error');
            throw e;
        }
    }

    function escaparHtmlOpcional(valor) {
        return String(valor == null ? '' : valor)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function htmlOpcionalesDetalleLinea(linea) {
        const detalle = (linea && Array.isArray(linea.opcionales_detalle)) ? linea.opcionales_detalle : [];
        if (!detalle.length) return '';
        const partes = detalle.map((d) => {
            const desc = (d && d.descripcion) ? String(d.descripcion) : '';
            const sku = (d && d.sku) ? String(d.sku) : '';
            const texto = desc || sku || ('Artículo #' + (d && d.articulo_id ? d.articulo_id : '?'));
            return escaparHtmlOpcional(texto);
        });
        return '<br><small class="text-muted">+ ' + partes.join(' · ') + '</small>';
    }

    function totalGruposOpcionales() {
        return document.querySelectorAll('#modal-opcionales-body .gastro-opc-grupo').length;
    }

    function pasoActualOpcionales() {
        const body = document.getElementById('modal-opcionales-body');
        if (!body) return 0;
        const n = parseInt(String(body.dataset.pasoActual || '0'), 10);
        return Number.isFinite(n) && n >= 0 ? n : 0;
    }

    function setPasoActualOpcionales(idx) {
        const body = document.getElementById('modal-opcionales-body');
        if (!body) return;
        const total = totalGruposOpcionales();
        const safe = Math.max(0, Math.min(idx, Math.max(0, total - 1)));
        body.dataset.pasoActual = String(safe);
        sincronizarVistaPasoOpcional();
    }

    function sincronizarVistaPasoOpcional() {
        const body = document.getElementById('modal-opcionales-body');
        if (!body) return;
        const grupos = body.querySelectorAll('.gastro-opc-grupo');
        const total = grupos.length;
        const actual = pasoActualOpcionales();

        grupos.forEach((g, i) => {
            g.classList.toggle('activo', i === actual);
        });

        const pasos = body.querySelectorAll('.gastro-opc-progreso-pasos .gastro-opc-paso');
        pasos.forEach((p, i) => {
            const tieneSel = !!grupos[i] && !!grupos[i].querySelector('.gastro-opc-tarjeta.seleccionada');
            p.classList.toggle('completado', tieneSel && i !== actual);
            p.classList.toggle('actual', i === actual);
            if (i !== actual) p.classList.remove('faltante');
        });

        actualizarChipsResumenOpcionales();

        const subtit = body.querySelector('.gastro-opc-progreso-subtitulo');
        if (subtit) {
            const grupoActual = grupos[actual];
            const ordenActual = grupoActual ? grupoActual.dataset.orden : '';
            const cantOpts = grupoActual ? grupoActual.querySelectorAll('.gastro-opc-tarjeta').length : 0;
            subtit.textContent = ordenActual
                ? 'Paso ' + (actual + 1) + ' de ' + total + ' · Elegí 1 de ' + cantOpts
                : '';
        }

        const btnAtras = document.getElementById('modal-opcionales-atras');
        const btnNext = document.getElementById('modal-opcionales-confirmar');
        if (btnAtras) {
            btnAtras.disabled = actual <= 0;
        }
        if (btnNext) {
            const esUltimo = actual >= total - 1;
            btnNext.innerHTML = esUltimo
                ? '<i class="fa fa-check"></i> Aceptar'
                : 'Siguiente <i class="fa fa-arrow-right"></i>';
            btnNext.classList.toggle('btn-success', esUltimo);
            btnNext.classList.toggle('btn-primary', !esUltimo);
        }

        const grupoActual = grupos[actual];
        if (grupoActual) {
            const yaElegida = grupoActual.querySelector('.gastro-opc-tarjeta.seleccionada');
            const focoCandidato = yaElegida || grupoActual.querySelector('.gastro-opc-tarjeta');
            if (focoCandidato && typeof focoCandidato.focus === 'function') {
                try {
                    focoCandidato.focus({ preventScroll: false });
                } catch (e) {
                    focoCandidato.focus();
                }
            }
        }
    }

    function actualizarChipsResumenOpcionales() {
        const body = document.getElementById('modal-opcionales-body');
        if (!body) return;
        const cont = body.querySelector('.gastro-opc-resumen-chips');
        if (!cont) return;
        cont.innerHTML = '';
        const grupos = body.querySelectorAll('.gastro-opc-grupo');
        grupos.forEach((g, i) => {
            const sel = g.querySelector('.gastro-opc-tarjeta.seleccionada');
            const sku = sel ? (sel.querySelector('.gastro-opc-sku') || {}).textContent || '' : '';
            const desc = sel ? (sel.querySelector('.gastro-opc-descripcion') || {}).textContent || '' : '';
            const chip = document.createElement('span');
            chip.className = 'gastro-opc-chip' + (sel ? ' completado' : '');
            chip.dataset.paso = String(i);
            chip.title = sel ? (sku + (desc ? ' — ' + desc : '')) : 'Sin elegir';
            const num = document.createElement('span');
            num.className = 'gastro-opc-chip-num';
            num.textContent = String(i + 1);
            chip.appendChild(num);
            const txt = document.createElement('span');
            txt.textContent = sel ? (desc || sku || 'Elegido') : 'Sin elegir';
            chip.appendChild(txt);
            chip.style.cursor = 'pointer';
            chip.addEventListener('click', () => setPasoActualOpcionales(i));
            cont.appendChild(chip);
        });
    }

    function renderGrillaOpcionales(grupos, articulo) {
        const body = document.getElementById('modal-opcionales-body');
        if (!body) return;
        body.innerHTML = '';
        body.dataset.pasoActual = '0';

        const info = document.getElementById('modal-opcionales-articulo-info');
        if (info && articulo) {
            const sku = escaparHtmlOpcional(articulo.sku || '');
            const desc = escaparHtmlOpcional(articulo.descripcion || '');
            info.innerHTML = sku || desc ? `${sku}${sku && desc ? ' · ' : ''}${desc}` : '';
        } else if (info) {
            info.innerHTML = '';
        }

        const totalPasos = grupos.length;

        const cabecera = document.createElement('div');
        cabecera.className = 'gastro-opc-progreso';
        const cabeceraInfo = document.createElement('div');
        cabeceraInfo.className = 'gastro-opc-progreso-info';
        const cabTit = document.createElement('span');
        cabTit.className = 'gastro-opc-progreso-titulo';
        cabTit.textContent = 'Personalizá el pedido';
        const cabSub = document.createElement('span');
        cabSub.className = 'gastro-opc-progreso-subtitulo';
        cabeceraInfo.appendChild(cabTit);
        cabeceraInfo.appendChild(cabSub);
        cabecera.appendChild(cabeceraInfo);

        const pasosWrap = document.createElement('div');
        pasosWrap.className = 'gastro-opc-progreso-pasos';
        grupos.forEach((g, i) => {
            const paso = document.createElement('span');
            paso.className = 'gastro-opc-paso';
            paso.dataset.paso = String(i);
            paso.title = 'Ir al paso ' + (i + 1);
            paso.addEventListener('click', () => setPasoActualOpcionales(i));
            const num = document.createElement('span');
            num.className = 'gastro-opc-paso-num';
            num.textContent = String(i + 1);
            paso.appendChild(num);
            const lbl = document.createElement('span');
            lbl.textContent = 'Orden ' + String(g.orden);
            paso.appendChild(lbl);
            pasosWrap.appendChild(paso);
        });
        cabecera.appendChild(pasosWrap);
        body.appendChild(cabecera);

        if (totalPasos > 1) {
            const resumen = document.createElement('div');
            resumen.className = 'gastro-opc-resumen';
            const tit = document.createElement('div');
            tit.className = 'gastro-opc-resumen-titulo';
            tit.textContent = 'Tu selección';
            const chips = document.createElement('div');
            chips.className = 'gastro-opc-resumen-chips';
            resumen.appendChild(tit);
            resumen.appendChild(chips);
            body.appendChild(resumen);
        }

        const pasosCont = document.createElement('div');
        pasosCont.className = 'gastro-opc-pasos-wrap';

        grupos.forEach((g, i) => {
            const orden = String(g.orden);
            const grupo = document.createElement('div');
            grupo.className = 'gastro-opc-grupo';
            grupo.dataset.orden = orden;
            grupo.dataset.paso = String(i);

            const titulo = document.createElement('div');
            titulo.className = 'gastro-opc-grupo-titulo';
            const pill = document.createElement('span');
            pill.className = 'gastro-opc-pill';
            pill.textContent = 'Paso ' + (i + 1) + '/' + totalPasos;
            const tituloTxt = document.createElement('span');
            tituloTxt.textContent = 'Elegí una opción';
            const small = document.createElement('small');
            small.textContent = '· Orden ' + orden;
            titulo.appendChild(pill);
            titulo.appendChild(tituloTxt);
            titulo.appendChild(small);
            grupo.appendChild(titulo);

            const grilla = document.createElement('div');
            grilla.className = 'gastro-opc-grilla';

            (g.opciones || []).forEach((o, idx) => {
                const tarjeta = document.createElement('div');
                tarjeta.className = 'gastro-opc-tarjeta';
                tarjeta.setAttribute('role', 'button');
                tarjeta.setAttribute('tabindex', '0');
                tarjeta.dataset.articuloId = String(o.articulo_id);
                tarjeta.dataset.orden = orden;
                tarjeta.dataset.posicion = String(idx + 1);
                tarjeta.title = (o.sku || '') + (o.descripcion ? ' — ' + o.descripcion : '');

                if (idx < 9) {
                    const atajo = document.createElement('span');
                    atajo.className = 'gastro-opc-atajo';
                    atajo.textContent = String(idx + 1);
                    atajo.setAttribute('aria-hidden', 'true');
                    tarjeta.appendChild(atajo);
                }

                const sku = document.createElement('div');
                sku.className = 'gastro-opc-sku';
                sku.textContent = o.sku || '';
                const desc = document.createElement('div');
                desc.className = 'gastro-opc-descripcion';
                desc.textContent = o.descripcion || '';

                tarjeta.appendChild(sku);
                tarjeta.appendChild(desc);
                grilla.appendChild(tarjeta);
            });

            grupo.appendChild(grilla);
            pasosCont.appendChild(grupo);
        });
        body.appendChild(pasosCont);

        const leyenda = document.createElement('div');
        leyenda.className = 'gastro-opc-leyenda';
        leyenda.innerHTML =
            '<kbd>1</kbd>–<kbd>9</kbd> elegir · <kbd>Enter</kbd> siguiente / aceptar · <kbd>←</kbd> volver · <kbd>Esc</kbd> cancelar';
        body.appendChild(leyenda);

        sincronizarVistaPasoOpcional();
    }

    function onClickTarjetaOpcional(ev) {
        const t = ev.target.closest('.gastro-opc-tarjeta');
        if (!t) return;
        const grupo = t.closest('.gastro-opc-grupo');
        if (!grupo) return;
        grupo.classList.remove('gastro-opc-faltante');
        grupo.querySelectorAll('.gastro-opc-tarjeta.seleccionada').forEach((el) => {
            if (el !== t) el.classList.remove('seleccionada');
        });
        t.classList.add('seleccionada');
        actualizarChipsResumenOpcionales();

        const total = totalGruposOpcionales();
        const actual = pasoActualOpcionales();
        if (actual < total - 1) {
            window.setTimeout(() => setPasoActualOpcionales(actual + 1), 220);
        } else {
            sincronizarVistaPasoOpcional();
        }
    }

    function onKeyTarjetaOpcional(ev) {
        if (ev.key !== 'Enter' && ev.key !== ' ' && ev.key !== 'Spacebar') return;
        const t = ev.target.closest('.gastro-opc-tarjeta');
        if (!t) return;
        ev.preventDefault();
        ev.stopPropagation();
        onClickTarjetaOpcional({ target: t });

        if (ev.key !== 'Enter') return;
        const total = totalGruposOpcionales();
        const actual = pasoActualOpcionales();
        if (actual >= total - 1) {
            void avanzarOConfirmarOpcionales();
        }
    }

    /**
     * Asistente paso a paso:
     * - 1–9 elige opción del paso actual (auto-avanza al siguiente).
     * - Enter avanza/confirma según paso.
     * - Backspace o ← vuelve al paso anterior.
     * - Esc lo maneja Bootstrap.
     */
    function onKeyModalOpcionales(ev) {
        const modal = document.getElementById('modal-opcionales');
        if (!modal || !modal.classList.contains('show')) return;
        if (ev.ctrlKey || ev.altKey || ev.metaKey) return;
        if (esCampoTextoEditable(ev.target)) return;

        if (ev.key === 'Enter') {
            const enTarjeta = ev.target && ev.target.closest && ev.target.closest('.gastro-opc-tarjeta');
            if (enTarjeta) return;
            ev.preventDefault();
            ev.stopPropagation();
            void avanzarOConfirmarOpcionales();
            return;
        }

        if (ev.key === 'Backspace' || ev.key === 'ArrowLeft') {
            const actual = pasoActualOpcionales();
            if (actual > 0) {
                ev.preventDefault();
                ev.stopPropagation();
                setPasoActualOpcionales(actual - 1);
            }
            return;
        }

        if (ev.key === 'ArrowRight') {
            const actual = pasoActualOpcionales();
            const total = totalGruposOpcionales();
            if (actual < total - 1) {
                ev.preventDefault();
                ev.stopPropagation();
                setPasoActualOpcionales(actual + 1);
            }
            return;
        }

        if (ev.shiftKey) return;

        const num = parseInt(ev.key, 10);
        if (!(num >= 1 && num <= 9)) return;

        const grupos = modal.querySelectorAll('#modal-opcionales-body .gastro-opc-grupo');
        if (!grupos.length) return;

        const target = grupos[pasoActualOpcionales()] || grupos[0];
        if (!target) return;

        const tarjetas = target.querySelectorAll('.gastro-opc-tarjeta');
        if (num > tarjetas.length) return;

        ev.preventDefault();
        ev.stopPropagation();
        onClickTarjetaOpcional({ target: tarjetas[num - 1] });
    }

    async function avanzarOConfirmarOpcionales() {
        const body = document.getElementById('modal-opcionales-body');
        if (!body) return;
        const grupos = body.querySelectorAll('.gastro-opc-grupo');
        const total = grupos.length;
        const actual = pasoActualOpcionales();
        const grupoActual = grupos[actual];
        if (grupoActual && !grupoActual.querySelector('.gastro-opc-tarjeta.seleccionada')) {
            grupoActual.classList.add('gastro-opc-faltante');
            const paso = body.querySelector('.gastro-opc-progreso-pasos .gastro-opc-paso[data-paso="' + actual + '"]');
            if (paso) {
                paso.classList.remove('faltante');
                void paso.offsetWidth;
                paso.classList.add('faltante');
            }
            toast('Elegí una opción para continuar.', 'warning');
            return;
        }
        if (actual < total - 1) {
            setPasoActualOpcionales(actual + 1);
            return;
        }
        await confirmarOpcionales();
    }

    function leerSeleccionOpcionalesGrilla() {
        const map = {};
        const faltantes = [];
        const grupos = document.querySelectorAll('#modal-opcionales-body .gastro-opc-grupo');
        let primerFaltante = -1;
        grupos.forEach((g, i) => {
            const orden = g.dataset.orden;
            const sel = g.querySelector('.gastro-opc-tarjeta.seleccionada');
            if (sel && sel.dataset.articuloId) {
                map[orden] = parseInt(sel.dataset.articuloId, 10);
                g.classList.remove('gastro-opc-faltante');
            } else {
                map[orden] = null;
                faltantes.push(orden);
                g.classList.add('gastro-opc-faltante');
                if (primerFaltante < 0) primerFaltante = i;
            }
        });
        if (primerFaltante >= 0) {
            setPasoActualOpcionales(primerFaltante);
        }
        return { map, faltantes };
    }

    async function fetchGruposOpcionales(articuloId) {
        try {
            const opData = await api(`/ventas/gastronomia/api/opcionales-articulo/${articuloId}`, { headers: hdrJson() });
            return opData && Array.isArray(opData.grupos) ? opData.grupos : [];
        } catch (e) {
            return [];
        }
    }

    /**
     * Flujo no-directo (botón "Agregar consumo" / lupa / atajo + en SKU):
     * 1) Si el artículo tiene opcionales → modal-opcionales; luego alta con cantidad=1
     *    (agregar-directo) o modal-cantidad (cantidad-despues) según opciones.
     * 2) Si no tiene opcionales → modal-cantidad para que el usuario indique cuánto.
     *
     * @param {{ pedirCantidad?: boolean }} [opciones] pedirCantidad=true → tras opcionales abre cantidad (atajo +).
     */
    async function iniciarAltaLinea(articulo, opciones) {
        if (bloquearOperacionPosPorJornadaTurno()) {
            return;
        }
        const pedirCantidad = !!(opciones && opciones.pedirCantidad);
        pendingArticulo = null;
        pendingOpcionalesSeleccion = null;
        pendingOpcionalesCtx = null;

        const grupos = await fetchGruposOpcionales(articulo.id);
        if (grupos.length) {
            pendingOpcionalesCtx = {
                articulo,
                cantidad: 1,
                modo: pedirCantidad ? 'cantidad-despues' : 'agregar-directo',
                grupos,
            };
            renderGrillaOpcionales(grupos, articulo);
            $('#modal-opcionales').modal('show');
            return;
        }

        pendingArticulo = articulo;
        $('#modal-cantidad').modal('show');
    }

    /**
     * Flujo directo (SKU + Enter): cantidad fija = 1.
     * Si hay opcionales → grilla y luego alta inmediata con cantidad=1.
     */
    async function procesarAltaConsumo(articulo, cantidad) {
        if (bloquearOperacionPosPorJornadaTurno()) {
            return;
        }
        const grupos = await fetchGruposOpcionales(articulo.id);

        if (grupos.length) {
            pendingOpcionalesCtx = { articulo, cantidad, modo: 'agregar-directo', grupos };
            pendingOpcionalesSeleccion = null;
            renderGrillaOpcionales(grupos, articulo);
            $('#modal-opcionales').modal('show');
            return;
        }

        await agregarLineaApi(articulo, cantidad, {});
    }

    function manejarEnterModalCantidad(e) {
        if (!e || e.key !== 'Enter' || e.ctrlKey || e.metaKey || e.altKey || e.shiftKey) {
            return;
        }
        const modal = document.getElementById('modal-cantidad');
        if (!modal || !modal.classList.contains('show')) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        void continuarDespuesCantidad();
    }

    async function continuarDespuesCantidad() {
        const cant = parseFloat(document.getElementById('fld-cantidad-linea').value || '0');
        if (!(cant > 0)) return toast('Cantidad inválida', 'warning');

        const art = pendingArticulo;
        const opciones = pendingOpcionalesSeleccion;
        pendingArticulo = null;
        pendingOpcionalesSeleccion = null;
        pendingOpcionalesCtx = null;

        $('#modal-cantidad').modal('hide');

        if (!art) return;

        // En el flujo no-directo los opcionales (si los hay) ya se eligieron antes de
        // abrir el modal de cantidad. Si no hay selección, es porque no había opcionales.
        await agregarLineaApi(art, cant, opciones || {});
    }

    function aplicarArticuloResueltoEnFila(a) {
        const tr = getTrLineaArticulo();
        if (!tr || !a) return;
        tr.querySelector('.articulo_id').value = a.id;
        const cod = tr.querySelector('.codigoarticulo');
        if (cod) cod.value = a.sku || '';
        tr.querySelector('.descripcionarticulo').value = a.descripcion || '';
        syncSufijoDesdeSkuCompleto(a.sku || '');
    }

    /**
     * Misma búsqueda por SKU que Enter: valida cuenta, arma SKU, consulta catálogo y completa la fila.
     * @returns {{ ok: boolean, articulo: object|null }}
     */
    async function resolverSkuConsumoEnFila() {
        if (!cuentaId) {
            toast('Seleccione mesa o cuenta.', 'warning');
            return { ok: false, articulo: null };
        }
        const fullSku = skuIngresadoEnFila();
        if (!fullSku) {
            toast('Ingrese el código del artículo.', 'warning');
            return { ok: false, articulo: null };
        }
        if (!skuPermitidoGastronomia(fullSku)) {
            toast('Código inválido: use ' + mensajeSkuCatalogoGastronomia() + '.', 'warning');
            return { ok: false, articulo: null };
        }
        try {
            const data = await fetchArticuloCatalogoPorSku(fullSku);
            const a = data.articulo;
            if (!a || !a.id) {
                toast('Artículo no encontrado.', 'warning');
                limpiarFormularioArticuloLinea();
                focusSkuConsumo();
                return { ok: false, articulo: null };
            }
            aplicarArticuloResueltoEnFila(a);
            return { ok: true, articulo: a };
        } catch (e) {
            toast(e.message || 'No se encontró el artículo', 'warning');
            limpiarFormularioArticuloLinea();
            focusSkuConsumo();
            return { ok: false, articulo: null };
        }
    }

    async function intentarAgregarConsumoDesdeTeclado() {
        if (bloquearOperacionPosPorJornadaTurno()) {
            return;
        }
        const { ok, articulo } = await resolverSkuConsumoEnFila();
        if (!ok || !articulo) return;
        await procesarAltaConsumo(articulo, 1);
    }

    /** Atajo + en campo SKU: igual que botón Agregar (modal cantidad; opcionales → cantidad). */
    async function intentarAgregarConsumoConCantidadDesdeTeclado() {
        if (bloquearOperacionPosPorJornadaTurno()) {
            return;
        }
        const { ok, articulo } = await resolverSkuConsumoEnFila();
        if (!ok || !articulo) return;
        await iniciarAltaLinea(articulo, { pedirCantidad: true });
    }

    function esAtajoCantidadEnSkuConsumo(e) {
        return e.key === '+' || e.code === 'NumpadAdd' || (e.key === '=' && e.shiftKey);
    }

    async function resolverSkuPorTabYEnfocarAgregar() {
        const { ok } = await resolverSkuConsumoEnFila();
        if (!ok) return;
        const btn = document.getElementById('btn-agregar-consumo');
        if (btn && typeof btn.focus === 'function') btn.focus();
    }

    async function confirmarOpcionales() {
        const { map, faltantes } = leerSeleccionOpcionalesGrilla();
        if (faltantes.length) {
            toast('Seleccione un opcional para cada grupo: ' + faltantes.join(', '), 'warning');
            return;
        }

        const ctx = pendingOpcionalesCtx;
        if (!ctx) {
            $('#modal-opcionales').modal('hide');
            return;
        }

        if (ctx.modo === 'canje-premio') {
            pendingOpcionalesCtx = null;
            pendingOpcionalesSeleccion = null;
            $('#modal-opcionales').modal('hide');
            if (typeof ctx.onResolve === 'function') {
                ctx.onResolve(map);
            }
            return;
        }

        if (ctx.modo === 'cantidad-despues') {
            pendingOpcionalesSeleccion = map;
            pendingArticulo = ctx.articulo;
            pendingOpcionalesCtx = null;
            const $opc = $('#modal-opcionales');
            $opc.off('hidden.bs.modal.gastroAbrirCantidad');
            $opc.one('hidden.bs.modal.gastroAbrirCantidad', function () {
                $('#modal-cantidad').modal('show');
            });
            $opc.modal('hide');
            return;
        }

        pendingOpcionalesCtx = null;
        pendingOpcionalesSeleccion = null;
        $('#modal-opcionales').modal('hide');
        await agregarLineaApi(ctx.articulo, ctx.cantidad, map);
    }

    async function agregarLineaApi(articulo, cantidad, opcionales) {
        if (bloquearOperacionPosPorJornadaTurno()) {
            return;
        }
        if (!cuentaId) return toast('Seleccione cuenta', 'warning');
        try {
            const payload = {
                articulo_id: articulo.id,
                cantidad: cantidad,
                opcionales: opcionales,
            };
            if (articulo.precio_sugerido != null && articulo.precio_sugerido !== '') {
                payload.precio_unitario = articulo.precio_sugerido;
            }
            const data = await api(`/ventas/gastronomia/api/cuenta/${cuentaId}/linea`, {
                method: 'POST',
                headers: hdrJson(),
                body: JSON.stringify(payload),
            });
            toast('Línea agregada', 'success');
            pintarLineas(data.cuenta);
            limpiarFormularioArticuloLinea();
            cargarMesas();
            cargarCuentasActivas();
            focusSkuConsumo();
        } catch (e) {
            if (e.message && e.message.includes('fetch')) toast(String(e), 'error');
            else toast(e.message, 'error');
        }
    }

    async function eliminarLinea(lineaId) {
        if (bloquearOperacionPosPorJornadaTurno()) {
            return;
        }
        if (!cuentaId) return;
        try {
            const data = await api(`/ventas/gastronomia/api/cuenta/${cuentaId}/linea/${lineaId}`, {
                method: 'DELETE',
                headers: hdrJson(),
            });
            pintarLineas(data.cuenta);
            focusSkuConsumo();
        } catch (e) {
            toast(e.message, 'error');
        }
    }

    async function cerrarCuenta() {
        if (bloquearOperacionPosPorJornadaTurno()) {
            return;
        }
        if (!cuentaId) return;
        if (!confirm('¿Cerrar cuenta sin facturar?')) return;
        try {
            await api(`/ventas/gastronomia/api/cuenta/${cuentaId}/cerrar`, {
                method: 'POST',
                headers: hdrJson(),
                body: '{}',
            });
            toast('Cuenta cerrada', 'success');
            limpiarEstadoCuentaActiva();
            cargarMesas();
            cargarCuentasActivas();
        } catch (e) {
            toast(e.message, 'error');
        }
    }

    async function validarEmisionServidor(mediosPago, efectivizar, opciones) {
        const optsVal = opciones || {};
        const body = {
            cuenta_id: cuentaId,
            moneda_id: MONEDA_PESOS_ID,
            medios_pago: mediosPago || [],
            facturacion_con_descuento: !!optsVal.facturacionConDescuento,
        };
        if (efectivizar) {
            body.efectivizar = true;
        }
        const data = await api('/ventas/gastronomia/api/validar-emision', {
            method: 'POST',
            headers: hdrJson(),
            body: JSON.stringify(body),
        });
        if (data.errores && data.errores.length) {
            throw new Error(data.errores.join(' '));
        }
        return data;
    }

    async function emitirFactura(opciones) {
        if (bloquearOperacionPosPorJornadaTurno()) {
            return;
        }
        const opts = opciones || {};
        const facturacionConDescuento = !!opts.exigirDescuento;
        const msgCanje = mensajeBloqueoFacturacionEfectivoPorCanje();
        if (msgCanje && !facturacionConDescuento) {
            return toast(msgCanje, 'warning');
        }
        const errDesc = validarDescuentoEnPantalla(facturacionConDescuento);
        if (errDesc) {
            return toast(errDesc, 'warning');
        }
        if (!cuentaId) {
            return toast('Seleccione cuenta', 'warning');
        }
        try {
            setFacturacionLoading(true, 'Validando cuenta y cobranza…');
            const cuenta = opts.cuentaPrecargada || (await guardarCabecera(true));
            let montoArs = totalFacturarDesdeCuenta(cuenta) || leerTotalAFacturarArs();
            let esCortesia = esFacturaCortesia(montoArs, cuenta);
            setTotalFacturadoArs(montoArs > 0 ? montoArs : esCortesia ? IMPORTE_MINIMO_FACTURA : 0);

            let mediosPago = Array.isArray(opts.mediosPago) ? opts.mediosPago.slice() : [];

            if (!esCortesia) {
                if (montoArs <= 0) {
                    return toast('El total a facturar debe ser mayor a cero.', 'warning');
                }
                if (!mediosPago.length) {
                    mediosPago = recogerMediosPagoFromGrid();
                }
                if (!mediosPago.length && opts.prepararCobranzaSiFalta !== false) {
                    if (cuentaEsWaitryCobroTotem(cuenta)) {
                        await prepararCobranzaTotem(montoArs);
                    } else {
                        await prepararCobranzaEfectivo(montoArs);
                    }
                    mediosPago = recogerMediosPagoFromGrid();
                }
                const errCob = validarCobranzaConMedios(mediosPago);
                if (errCob) {
                    return toast(errCob, 'warning');
                }
                for (const tr of document.querySelectorAll('#tbody-gastro-cuenta-table tr')) {
                    if (!validarMontoCobranza(tr)) {
                        return toast('Revise los montos de cobranza antes de facturar.', 'warning');
                    }
                }
            } else {
                mediosPago = [];
            }

            const valData = await validarEmisionServidor(mediosPago, false, { facturacionConDescuento });
            if (valData && valData.total_facturar_ars != null && !Number.isNaN(Number(valData.total_facturar_ars))) {
                montoArs = Number(valData.total_facturar_ars);
                if (valData.factura_cortesia || valData.sin_cobranza) {
                    esCortesia = true;
                    mediosPago = [];
                }
                setTotalFacturadoArs(montoArs > 0 ? montoArs : IMPORTE_MINIMO_FACTURA);
            }
            iniciarRotacionMensajesProceso(mensajesProcesoEmision(cuenta));
            const data = await api('/ventas/gastronomia/api/emitir-factura', {
                method: 'POST',
                headers: hdrJson(),
                body: JSON.stringify({
                    cuenta_id: cuentaId,
                    moneda_id: MONEDA_PESOS_ID,
                    medios_pago: mediosPago,
                    facturacion_con_descuento: facturacionConDescuento,
                }),
            });
            detenerRotacionMensajesProceso();
            mostrarResultadoEmisionFactura(data);
            cargarMesas();
            cargarCuentasActivas();
            if (modoSeleccion === 'waitry') {
                void cargarOrdenesWaitry({ refresh: true });
            }
            limpiarEstadoCuentaActiva();
        } catch (e) {
            const detalleErr = (e.payload && e.payload.factura) ? 'Comprobante: ' + e.payload.factura : '';
            if (debeUsarAvisoPersistente(e.message, 'error')) {
                mostrarAvisoPersistente(e.message, 'error', {
                    titulo: 'Error al facturar',
                    detalle: detalleErr,
                });
            } else {
                toast(e.message, 'error');
            }
        } finally {
            setFacturacionLoading(false);
        }
    }

    function setModo(modo, opciones) {
        const opts = opciones || {};
        if (modo === 'cuenta' && G.cuentasLibresHabilitadas === false) {
            modo = 'mesa';
        }
        if (modo === 'waitry' && !waitryHabilitadoEnPos()) {
            modo = 'mesa';
        }
        modoSeleccion = modo;
        const esMesa = modo === 'mesa';
        const esCuenta = modo === 'cuenta';
        const esWaitry = modo === 'waitry';
        document.getElementById('panel-mesas').classList.toggle('d-none', !esMesa);
        document.getElementById('panel-cuentas').classList.toggle('d-none', !esCuenta);
        const panelWaitry = document.getElementById('panel-waitry');
        if (panelWaitry) {
            panelWaitry.classList.toggle('d-none', !esWaitry);
        }
        document.getElementById('btn-modo-mesa').classList.toggle('active', esMesa);
        const btnCuenta = document.getElementById('btn-modo-cuenta');
        if (btnCuenta) {
            btnCuenta.classList.toggle('active', esCuenta);
        }
        const btnWaitry = document.getElementById('btn-modo-waitry');
        if (btnWaitry) {
            btnWaitry.classList.toggle('active', esWaitry);
        }
        const btnNueva = document.getElementById('btn-nueva-cuenta-libre');
        if (btnNueva) {
            btnNueva.classList.toggle('d-none', !esCuenta || G.cuentasLibresHabilitadas === false);
        }
        if (esWaitry) {
            void cargarOrdenesWaitry();
        }
        if (!opts.silent) {
            void guardarPreferenciaModoSeleccion(modo);
        }
    }

    function aplicarPreferenciaModoSeleccion(modo) {
        const m = modo === 'cuenta' || modo === 'waitry' ? modo : 'mesa';
        setModo(m, { silent: true });
    }

    async function guardarPreferenciaModoSeleccion(modo) {
        if (modo === 'cuenta' && G.cuentasLibresHabilitadas === false) {
            return;
        }
        if (modo === 'waitry' && !waitryHabilitadoEnPos()) {
            return;
        }
        try {
            await api('/ventas/gastronomia/api/preferencia-modo-seleccion', {
                method: 'POST',
                headers: hdrJson(),
                body: JSON.stringify({ modo: modo }),
            });
        } catch (_) {
            /* preferencia no crítica */
        }
    }

    function wireConsultaClienteInternoDescuento() {
        if (typeof $ === 'undefined') {
            return;
        }
        $(document).off('click.gastroCliInterno', '.consultaclienteinternodescuento');
        $(document).on('click.gastroCliInterno', '.consultaclienteinternodescuento', function () {
            if (typeof ptrcliente_id !== 'undefined') {
                ptrcliente_id = $('#cliente_descuento_id');
            }
            if (typeof ptrnombrecliente !== 'undefined') {
                ptrnombrecliente = $('#nombrecliente_descuento');
            }
            $('#consultaclienteModal').data('gastroConsultaDestino', 'interno');
            $('#consultaclienteModal').modal('show');
        });
    }

    function wireConsultasSistema() {
        if (typeof activa_eventos_consultacliente === 'function') {
            activa_eventos_consultacliente();
        }
        wireConsultaClienteInternoDescuento();
        if (typeof activa_eventos_consultaarticulo === 'function') {
            activa_eventos_consultaarticulo();
        }
        if (typeof activa_eventos_consultamozo === 'function') {
            activa_eventos_consultamozo();
        }
        if (typeof activa_eventos_consultadescuento === 'function') {
            activa_eventos_consultadescuento();
        }
        window.onArticuloSeleccionado = function (dataArticulo) {
            if (!dataArticulo || !dataArticulo.id) return;
            if (!skuPermitidoGastronomia(dataArticulo.sku)) {
                toast(
                    'Este artículo no pertenece al catálogo gastronomía (' + mensajeSkuCatalogoGastronomia() + ').',
                    'warning',
                );
                limpiarFormularioArticuloLinea();
                return;
            }
            const tr = document.getElementById('tr-gastro-linea-articulo');
            if (tr) {
                tr.querySelector('.articulo_id').value = dataArticulo.id;
                const cod = tr.querySelector('.codigoarticulo');
                if (cod) cod.value = dataArticulo.sku || '';
                tr.querySelector('.descripcionarticulo').value = dataArticulo.descripcion || '';
                syncSufijoDesdeSkuCompleto(dataArticulo.sku || '');
            }
        };
    }

    function esTeclaF5(e) {
        return e.key === 'F5' || e.code === 'F5' || e.keyCode === 116;
    }

    function esTeclaF8(e) {
        return e.key === 'F8' || e.code === 'F8' || e.keyCode === 119;
    }

    function wireCamposDescuentoTeclado() {
        const codDesc = document.getElementById('codigodescuento');
        const codCli = document.getElementById('codigocliente_descuento');

        if (codDesc) {
            codDesc.addEventListener('keydown', async (e) => {
                if (pendingDescuentoResolver) return;
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const cod = (codDesc.value || '').trim();
                    if (!cod) return;
                    try {
                        await cargarDescuentoPorCodigoApi(cod);
                        if (descuentoEnModalF8()) {
                            const errCli = validarClienteInternoDescuentoEnPantalla();
                            if (errCli) {
                                enfocarCampoClienteInternoCodigo();
                            } else {
                                void confirmarModalF8Descuento();
                            }
                        }
                    } catch (err) {
                        toast(err.message || String(err), 'error');
                    }
                    return;
                }
                if (e.key === 'Tab' && !e.shiftKey) {
                    const panelCli = document.getElementById('panel-cliente-descuento');
                    if (panelCli && !panelCli.classList.contains('d-none')) {
                        e.preventDefault();
                        enfocarCampoClienteInternoCodigo();
                    }
                }
            });
        }

        if (codCli) {
            codCli.addEventListener('keydown', async (e) => {
                if (pendingClienteInternoResolver) return;
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const cod = (codCli.value || '').trim();
                    if (!cod) return;
                    try {
                        await cargarClienteInternoPorCodigoApi(cod);
                        if (descuentoEnModalF8() && !validarClienteInternoDescuentoEnPantalla()) {
                            void confirmarModalF8Descuento();
                        }
                    } catch (err) {
                        toast(err.message || String(err), 'error');
                    }
                }
            });
        }
    }

    function registrarEventosUi() {
        wireCamposDescuentoTeclado();

        document.getElementById('btn-modo-mesa').addEventListener('click', () => {
            setModo('mesa');
        });
        const btnModoCuenta = document.getElementById('btn-modo-cuenta');
        if (btnModoCuenta) {
            btnModoCuenta.addEventListener('click', () => {
                if (G.cuentasLibresHabilitadas === false) {
                    return toast('Las cuentas libres no están habilitadas.', 'warning');
                }
                setModo('cuenta');
            });
        }
        const btnModoWaitry = document.getElementById('btn-modo-waitry');
        if (btnModoWaitry) {
            btnModoWaitry.addEventListener('click', () => setModo('waitry'));
        }
        const btnWaitryRefrescar = document.getElementById('btn-waitry-refrescar');
        if (btnWaitryRefrescar) {
            btnWaitryRefrescar.addEventListener('click', () => void cargarOrdenesWaitry({ refresh: true }));
        }
        const btnWaitryImportarPorId = document.getElementById('btn-waitry-importar-por-id');
        if (btnWaitryImportarPorId) {
            btnWaitryImportarPorId.addEventListener('click', abrirModalWaitryImportarPorId);
        }
        const btnWaitryImportarIdConfirmar = document.getElementById('modal-waitry-importar-id-confirmar');
        if (btnWaitryImportarIdConfirmar) {
            btnWaitryImportarIdConfirmar.addEventListener('click', () => void confirmarModalWaitryImportarPorId());
        }
        const waitryImportarIdInput = document.getElementById('waitry-importar-id-input');
        if (waitryImportarIdInput) {
            waitryImportarIdInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    void confirmarModalWaitryImportarPorId();
                }
            });
        }
        $('#modal-waitry-importar-id').on('shown.bs.modal', function () {
            const el = document.getElementById('waitry-importar-id-input');
            if (el) {
                el.focus();
            }
        });
        document.getElementById('btn-nueva-cuenta-libre').addEventListener('click', () => {
            void nuevaCuentaLibre();
        });
        const btnConfirmarAbrir = document.getElementById('modal-abrir-cuenta-confirmar');
        if (btnConfirmarAbrir) {
            btnConfirmarAbrir.addEventListener('click', confirmarModalAbrirCuenta);
        }
        const abrirCub = document.getElementById('abrir-cubiertos');
        if (abrirCub) {
            abrirCub.addEventListener(
                'keydown',
                (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        e.stopPropagation();
                        focusCodigoMozoModalAbrir();
                    }
                },
                true,
            );
        }
        const abrirCodMozo = document.getElementById('abrir-codigomozo');
        if (abrirCodMozo) {
            abrirCodMozo.addEventListener('keydown', onKeydownCodigoMozoModalAbrir, true);
            abrirCodMozo.addEventListener('blur', function () {
                const cod = String(abrirCodMozo.value || '').trim();
                if (!cod) return;
                const idEl = document.getElementById('abrir-mozo_gastronomia_id');
                if (idEl && String(idEl.value || '').trim() !== '') return;
                void cargarMozoPorCodigoModalAbrir(cod);
            });
        }
        const abrirMozoId = document.getElementById('abrir-mozo_gastronomia_id');
        if (abrirMozoId) {
            abrirMozoId.addEventListener(
                'keydown',
                (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        e.stopPropagation();
                        confirmarModalAbrirTrasMozo();
                    }
                },
                true,
            );
        }
        if (typeof $ !== 'undefined') {
            wireApiladoConsultasSobreModalF8();
            $('#modal-abrir-cuenta').on('hidden.bs.modal', function () {
                if (pendingAbrirCuentaReject) {
                    cancelarModalAbrirCuenta();
                }
            });
            $('#consultamozoModal').on('hidden.bs.modal.gastroAbrir', function () {
                const modalAbrir = document.getElementById('modal-abrir-cuenta');
                if (modalAbrir && modalAbrir.classList.contains('show')) {
                    focusCodigoMozoModalAbrir();
                }
            });
            $('#modal-f8-descuento').on('shown.bs.modal', function () {
                setTimeout(() => enfocarPrimerCampoPendienteModalF8(), 80);
            });
            $('#modal-f8-descuento').on('hidden.bs.modal', function () {
                restaurarBloqueDescuentoEnTarjeta();
                if (!modalF8DescuentoConfirmadoOk) {
                    limpiarEsperaDescuento('Operación cancelada.');
                    limpiarEsperaClienteInterno('Operación cancelada.');
                    rechazarModalF8Descuento('Operación cancelada.');
                }
                modalF8DescuentoConfirmadoOk = false;
            });
            $('#consultadescuentoModal').on('hidden.bs.modal.gastroF8', function () {
                if (descuentoEnModalF8()) {
                    setTimeout(() => enfocarPrimerCampoPendienteModalF8(), 80);
                }
            });
            $('#consultaclienteModal').on('hidden.bs.modal.gastroF8', function () {
                if (descuentoEnModalF8()) {
                    setTimeout(() => enfocarPrimerCampoPendienteModalF8(), 80);
                }
            });
            $('#modal-f8-descuento').on('keydown.gastroF8', function (e) {
                if (e.key !== 'Enter' || e.ctrlKey || e.metaKey || e.altKey || e.shiftKey) {
                    return;
                }
                const t = e.target;
                if (!t || t.tagName === 'TEXTAREA') {
                    return;
                }
                if (t.classList && (t.classList.contains('codigodescuento') || t.classList.contains('codigoclienteinternodescuento'))) {
                    return;
                }
                if (t.id === 'modal-f8-descuento-confirmar') {
                    return;
                }
                e.preventDefault();
                void confirmarModalF8Descuento();
            });
        }
        const btnModalF8Confirmar = document.getElementById('modal-f8-descuento-confirmar');
        if (btnModalF8Confirmar) {
            btnModalF8Confirmar.addEventListener('click', () => {
                void confirmarModalF8Descuento();
            });
        }
        document.getElementById('btn-guardar-cabecera').addEventListener('click', () => {
            void guardarCabecera(false);
        });
        const clienteIdInput = document.getElementById('cliente_id');
        if (clienteIdInput) {
            clienteIdInput.addEventListener('change', () => {
                const wrap = document.getElementById('panel-detalle-lineas');
                const txt = wrap ? wrap.textContent : '';
                const m = txt.match(/Subtotal estimado:\s*([\d.,]+)/);
                const sub = m ? parseFloat(m[1].replace(/\./g, '').replace(',', '.')) : 0;
                actualizarPanelReceptorManual(sub);
            });
        }
        document.getElementById('btn-cerrar-cuenta').addEventListener('click', cerrarCuenta);
        document.getElementById('btn-agregar-consumo').addEventListener('click', async () => {
            let articuloParaModal = articuloSeleccionadoEnFila();
            if (!articuloParaModal || !articuloParaModal.id) {
                const { ok, articulo } = await resolverSkuConsumoEnFila();
                if (ok && articulo) articuloParaModal = articulo;
            }
            if (!articuloParaModal || !articuloParaModal.id) {
                return toast('Seleccione un artículo (lupa o SKU).', 'warning');
            }
            if (!skuPermitidoGastronomia(articuloParaModal.sku)) {
                return toast('Código inválido: use ' + mensajeSkuCatalogoGastronomia() + '.', 'warning');
            }
            await iniciarAltaLinea(articuloParaModal);
        });
        document.getElementById('modal-cantidad-confirmar').addEventListener('click', continuarDespuesCantidad);
        const fldCantidad = document.getElementById('fld-cantidad-linea');
        if (fldCantidad) {
            fldCantidad.addEventListener('keydown', manejarEnterModalCantidad);
        }
        const btnCantidadConfirmar = document.getElementById('modal-cantidad-confirmar');
        if (btnCantidadConfirmar) {
            btnCantidadConfirmar.addEventListener('keydown', manejarEnterModalCantidad);
        }
        document.getElementById('modal-opcionales-confirmar').addEventListener('click', () => {
            void avanzarOConfirmarOpcionales();
        });
        const btnOpcAtras = document.getElementById('modal-opcionales-atras');
        if (btnOpcAtras) {
            btnOpcAtras.addEventListener('click', () => {
                const actual = pasoActualOpcionales();
                if (actual > 0) setPasoActualOpcionales(actual - 1);
            });
        }
        document.getElementById('tool-facturar').addEventListener('click', () => {
            void emitirFactura();
        });
        document.addEventListener(
            'keydown',
            function (e) {
                if (
                    G.cuentasLibresHabilitadas !== false &&
                    modoSeleccion === 'cuenta' &&
                    esAtajoNuevaCuentaLibre(e) &&
                    !debeIgnorarAtajoPos() &&
                    !esCampoTextoEditable(e.target)
                ) {
                    if (!e.ctrlKey && !e.altKey && !e.metaKey) {
                        e.preventDefault();
                        e.stopPropagation();
                        void nuevaCuentaLibre();
                        return;
                    }
                }

                if (!esTeclaF5(e) && !esTeclaF8(e)) return;
                if (debeIgnorarAtajoPos()) return;

                if (esTeclaF5(e)) {
                    if (e.ctrlKey || e.metaKey || e.altKey || e.shiftKey) return;
                    e.preventDefault();
                    e.stopPropagation();
                    void efectivizar();
                    return;
                }

                if (esTeclaF8(e)) {
                    if (e.ctrlKey || e.metaKey || e.altKey || e.shiftKey) return;
                    e.preventDefault();
                    e.stopPropagation();
                    void facturarConDescuento();
                }
            },
            true,
        );
        document.getElementById('tool-asignar-cliente').addEventListener('click', () => {
            const el = document.getElementById('cliente_id');
            if (el) el.focus();
        });
        document.getElementById('tool-descuento').addEventListener('click', () => {
            const el = document.getElementById('codigodescuento') || document.getElementById('descuento_gastronomia_id');
            if (el) el.focus();
        });

        document.addEventListener(
            'keydown',
            function (e) {
                const t = e.target;
                if (!t || !t.classList || !t.classList.contains('gastro-carga-sku')) return;
                if (!t.closest('#tr-gastro-linea-articulo')) return;

                if (esAtajoCantidadEnSkuConsumo(e)) {
                    e.preventDefault();
                    e.stopPropagation();
                    void intentarAgregarConsumoConCantidadDesdeTeclado();
                    return;
                }

                if (e.key !== 'Enter' && e.key !== 'Tab') return;

                if (e.key === 'Enter') {
                    e.preventDefault();
                    e.stopPropagation();
                    void intentarAgregarConsumoDesdeTeclado();
                    return;
                }
                if (e.key === 'Tab' && !e.shiftKey) {
                    e.preventDefault();
                    e.stopPropagation();
                    void resolverSkuPorTabYEnfocarAgregar();
                }
            },
            true,
        );

        document.addEventListener('input', function (e) {
            const t = e.target;
            if (!t.classList || !t.classList.contains('gastro-sku-sufijo')) return;
            const d = String(t.value || '').replace(/\D/g, '');
            if (t.value !== d) t.value = d;
        });

        if (typeof $ !== 'undefined') {
            $('#modal-opcionales').on('hidden.bs.modal', function () {
                if (pendingOpcionalesCtx && pendingOpcionalesCtx.modo === 'canje-premio') {
                    const cb = pendingOpcionalesCtx.onResolve;
                    pendingOpcionalesCtx = null;
                    pendingOpcionalesSeleccion = null;
                    if (typeof cb === 'function') {
                        cb(null);
                    }
                    return;
                }
                if (pendingOpcionalesCtx) {
                    pendingOpcionalesCtx = null;
                    pendingOpcionalesSeleccion = null;
                    pendingArticulo = null;
                    setTimeout(() => focusSkuConsumo(), 80);
                }
            });
            $('#modal-opcionales').on('shown.bs.modal', function () {
                sincronizarVistaPasoOpcional();
            });
            $('#modal-cantidad').on('shown.bs.modal', function () {
                const el = document.getElementById('fld-cantidad-linea');
                if (el) {
                    el.value = el.value || '1';
                    if (typeof el.focus === 'function') el.focus();
                    if (typeof el.select === 'function') el.select();
                }
            });
            $('#modal-cantidad').on('keydown.gastroCantidad', manejarEnterModalCantidad);
            $('#modal-cantidad').on('hidden.bs.modal', function () {
                if (pendingArticulo || pendingOpcionalesSeleccion) {
                    pendingArticulo = null;
                    pendingOpcionalesSeleccion = null;
                    pendingOpcionalesCtx = null;
                    setTimeout(() => focusSkuConsumo(), 80);
                }
            });
        }

        const opcBody = document.getElementById('modal-opcionales-body');
        if (opcBody) {
            opcBody.addEventListener('click', onClickTarjetaOpcional);
            opcBody.addEventListener('keydown', onKeyTarjetaOpcional);
        }

        const opcModal = document.getElementById('modal-opcionales');
        if (opcModal) {
            opcModal.addEventListener('keydown', onKeyModalOpcionales);
        }
    }

    async function cargarConfigPv() {
        const data = await api('/ventas/gastronomia/api/config', { headers: hdrJson() });
        if (data.empresa_id) {
            empresaId = data.empresa_id;
            G.empresaId = empresaId;
            G.tieneCfgPv = true;
            G.empresaNombre = data.empresa_nombre || null;
            ['empresa_id', 'gastro-empresa-id'].forEach((id) => {
                const el = document.getElementById(id);
                if (el) el.value = empresaId;
            });
        }
        if (data.usocuentacaja_gastronomia_id) {
            G.usocuentacajaGastronomiaId = parseInt(data.usocuentacaja_gastronomia_id, 10);
        }
        if (data.cuentacaja_efectivo && data.cuentacaja_efectivo.id) {
            G.cuentacajaEfectivo = data.cuentacaja_efectivo;
            G.cuentacajaEfectivoError = null;
        } else {
            G.cuentacajaEfectivo = null;
            G.cuentacajaEfectivoError = data.cuentacaja_efectivo_error || null;
            if (G.cuentacajaEfectivoError) {
                console.warn('Gastronomía F5:', G.cuentacajaEfectivoError);
            }
        }
        if (data.cuentacaja_canje_tarjeta && data.cuentacaja_canje_tarjeta.id) {
            G.cuentacajaCanjeTarjeta = data.cuentacaja_canje_tarjeta;
            G.cuentacajaCanjeTarjetaError = null;
        } else {
            G.cuentacajaCanjeTarjeta = null;
            G.cuentacajaCanjeTarjetaError = data.cuentacaja_canje_tarjeta_error || null;
            if (G.cuentacajaCanjeTarjetaError) {
                console.warn('Gastronomía canje ticket:', G.cuentacajaCanjeTarjetaError);
            }
        }
        if (data.cuentacaja_totem && data.cuentacaja_totem.id) {
            G.cuentacajaTotem = data.cuentacaja_totem;
            G.cuentacajaTotemError = null;
        } else {
            G.cuentacajaTotem = null;
            G.cuentacajaTotemError = data.cuentacaja_totem_error || null;
            if (G.cuentacajaTotemError) {
                console.warn('Gastronomía Waitry TOTEM:', G.cuentacajaTotemError);
            }
        }
        if (data.cuentacaja_totem_codigo) {
            G.cuentacajaTotemCodigo = data.cuentacaja_totem_codigo;
        }
        if (data.waitry_tipo_pago_cuentacaja) {
            G.waitryTipoPagoCuentacaja = data.waitry_tipo_pago_cuentacaja;
        }
        G.cuentacajaEfectivoIdConfig = data.cuentacaja_efectivo_id || null;
        if (data.receptor_cf_nombre) {
            G.receptorCfNombre = data.receptor_cf_nombre;
        }
        if (data.cobranza_config_error) {
            G.cobranzaConfigError = data.cobranza_config_error;
            toast(data.cobranza_config_error, 'warning');
        } else {
            G.cobranzaConfigError = null;
        }
        if (data.waitry_habilitado != null) {
            G.waitryHabilitado = !!data.waitry_habilitado;
        }
        if (data.wigos_habilitado != null) {
            G.wigosHabilitado = !!data.wigos_habilitado;
        }
        if (data.wigos_account_info_habilitado != null) {
            G.wigosAccountInfoHabilitado = !!data.wigos_account_info_habilitado;
        }
        G.canjePremioDescuentoCodigo = data.canje_premio_descuento_codigo || '10';
        G.canjePremioClienteCodigo = data.canje_premio_cliente_codigo || '500';
        G.canjeFidelidadDescuentoCodigo = data.canje_fidelidad_descuento_codigo || '10';
        G.canjeFidelidadClienteCodigo = data.canje_fidelidad_cliente_codigo || '500';
        if (data.waitry_get_orders_minutos_atras != null) {
            G.waitryGetOrdersMinutosAtras = data.waitry_get_orders_minutos_atras;
        }
        if (data.listaprecio_id != null) {
            G.listaprecioId = parseInt(data.listaprecio_id, 10) || null;
        }
        if (data.listaprecio_nombre != null) {
            G.listaprecioNombre = data.listaprecio_nombre;
        }
        aplicarVisibilidadWaitry();
        actualizarLeyendaFiltroWaitry();
        if (data.modo_seleccion_preferido) {
            G.modoSeleccionPreferido = data.modo_seleccion_preferido;
            aplicarPreferenciaModoSeleccion(data.modo_seleccion_preferido);
        }
        aplicarConfigAperturaDesdeApi(data);
        aplicarEstadoJornadaTurnoDesdeApi(data);
        return data;
    }

    document.addEventListener('DOMContentLoaded', async () => {
        registrarEventosUi();
        aplicarVisibilidadCuentasLibres();
        aplicarVisibilidadWaitry();
        actualizarLeyendaFiltroWaitry();
        aplicarPreferenciaModoSeleccion(G.modoSeleccionPreferido || 'mesa');
        wireConsultasSistema();
        envolverPintarDescuentoParaRecalculo();
        window.gastroOnClienteInternoDescuentoElegido = function (cli) {
            if (pendingClienteInternoResolver && cli && cli.id) {
                pendingClienteInternoResolver.resolve(cli);
            }
            if (cuentaId && tieneDescuentoEnPantalla() && cli && cli.id) {
                void recalcularTotalCuentaConDescuento();
            }
            if (descuentoEnModalF8() && cli && cli.id && !validarClienteInternoDescuentoEnPantalla()) {
                setTimeout(() => {
                    const btn = document.getElementById('modal-f8-descuento-confirmar');
                    if (btn && typeof btn.focus === 'function') {
                        btn.focus();
                    }
                }, 50);
            }
        };
        window.gastroOnClienteFacturaElegido = function () {
            actualizarPanelReceptorManual(leerSubtotalEstimadoArs());
        };

        try {
            await cargarConfigPv();
        } catch (e) {
            toast(e.message, 'warning');
        }

        if (typeof window.gastroRefrescarEstadoTurno === 'function') {
            try {
                await window.gastroRefrescarEstadoTurno();
            } catch (e) {
                /* alerta turno opcional */
            }
        }

        try {
            await cargarMonedasFactura();
        } catch (e) {
            toast('No se pudieron cargar monedas: ' + e.message, 'error');
        }

        initCobranzaGrid();

        try {
            await cargarMediosPagoRapidos();
        } catch (e) {
            console.warn('Medios de pago:', e.message || e);
        }

        try {
            await cargarMesas();
        } catch (e) {
            toast('Mesas: ' + e.message, 'error');
        }

        try {
            await cargarCuentasActivas();
        } catch (e) {
            toast('Cuentas activas: ' + e.message, 'error');
        }

        setTimeout(() => focusSkuConsumo(), 200);
    });
})();
