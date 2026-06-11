(function ($) {
    'use strict';

    const G = window.ESTACIONAMIENTO || {};
    const apiBase = (G.rutas && G.rutas.apiBase) ? G.rutas.apiBase.replace(/\/$/, '') : '';

    let cuenta = null;
    let categorias = [];
    let itemsCatalogo = [];
    let pendingItemEstacionamiento = null;
    let cuentasCaja = [];
    let cuentasCajaPorId = {};
    let cuentacajaxcodigo = null;
    let monedaFacturaId = G.monedaFacturaId || 1;
    let emitiendo = false;
    let totalFacturadoArs = 0;
    let facturacionLoadingTimer = null;
    let estAvisoKeyHandler = null;
    const EST_MODAL_Z_BASE = 1050;
    const EST_MODAL_Z_STEP = 20;
    const TOLERANCIA_MONTO_COBRANZA = 0.02;

    function esTeclaF5(e) {
        return e.key === 'F5' || e.code === 'F5' || e.keyCode === 116;
    }

    function esTeclaF8(e) {
        return e.key === 'F8' || e.code === 'F8' || e.keyCode === 119;
    }

    function teclaConModificador(e) {
        return !!(e.ctrlKey || e.metaKey || e.altKey || e.shiftKey);
    }

    function modalF8DescuentoAbierto() {
        const el = document.getElementById('modal-f8-descuento');
        return !!(el && el.classList.contains('show'));
    }

    function debeIgnorarAtajoPos() {
        const ids = [
            'consultacuentacajaModal',
            'consultaclienteModal',
            'consultadescuentoModal',
            'modal-f8-descuento',
            'modal-est-aviso',
            'modal-est-cantidad',
        ];
        for (let i = 0; i < ids.length; i++) {
            const el = document.getElementById(ids[i]);
            if (el && el.classList.contains('show')) {
                return true;
            }
        }
        if (overlayVisible()) {
            return true;
        }
        return false;
    }

    function overlayVisible() {
        const overlay = document.getElementById('est-facturacion-procesando-overlay');
        return !!(overlay && !overlay.classList.contains('d-none'));
    }

    function apilarModalConsultaSobreF8($modal) {
        if (!$modal || !$modal.length || !modalF8DescuentoAbierto()) {
            return;
        }
        const zHijo = EST_MODAL_Z_BASE + EST_MODAL_Z_STEP;
        const zBackdrop = zHijo - 10;
        $modal.data('estApiladoSobreF8', true);
        $modal.addClass('est-modal-sobre-f8');
        $modal.css('z-index', zHijo);
        $('.modal-backdrop').last().css('z-index', zBackdrop);
    }

    function desapilarModalConsultaSobreF8($modal) {
        if (!$modal || !$modal.length || !$modal.data('estApiladoSobreF8')) {
            return;
        }
        $modal.removeData('estApiladoSobreF8');
        $modal.removeClass('est-modal-sobre-f8');
        $modal.css('z-index', '');
        if ($('.modal-backdrop').length) {
            $('.modal-backdrop').last().css('z-index', '');
        }
        if (modalF8DescuentoAbierto()) {
            $('body').addClass('modal-open');
        }
    }

    function wireApiladoConsultasSobreModalF8() {
        ['#consultaclienteModal', '#consultadescuentoModal'].forEach(function (sel) {
            const $m = $(sel);
            $m.off('shown.bs.modal.estF8Stack hidden.bs.modal.estF8Stack');
            $m.on('shown.bs.modal.estF8Stack', function () {
                apilarModalConsultaSobreF8($m);
            });
            $m.on('hidden.bs.modal.estF8Stack', function () {
                desapilarModalConsultaSobreF8($m);
                if (modalF8DescuentoAbierto()) {
                    setTimeout(enfocarPrimerCampoPendienteModalF8, 80);
                }
            });
        });
    }
    let modalF8DescuentoEnCurso = false;
    let modalF8DescuentoConfirmadoOk = false;
    let pendingModalF8DescuentoResolver = null;

    function avisoDescuentoEnModal(visible) {
        const aviso = document.getElementById('est-descuento-en-modal-aviso');
        if (aviso) {
            aviso.classList.toggle('d-none', !visible);
        }
    }

    function slotDescuentoOriginal() {
        return document.getElementById('est-descuento-slot-original');
    }

    function slotDescuentoModal() {
        return document.getElementById('est-descuento-slot-modal');
    }

    function bloqueDescuentoMovable() {
        return document.getElementById('est-descuento-movable');
    }

    function descuentoEnModalF8() {
        const bloque = bloqueDescuentoMovable();
        const slotModal = slotDescuentoModal();
        return !!(bloque && slotModal && slotModal.contains(bloque));
    }

    function moverBloqueDescuentoAlModal() {
        const bloque = bloqueDescuentoMovable();
        const slotModal = slotDescuentoModal();
        if (bloque && slotModal && !slotModal.contains(bloque)) {
            slotModal.appendChild(bloque);
        }
        avisoDescuentoEnModal(true);
        if (typeof mostrarPanelClienteInternoDescuento === 'function') {
            mostrarPanelClienteInternoDescuento(true);
        }
    }

    function restaurarBloqueDescuentoEnTarjeta() {
        const bloque = bloqueDescuentoMovable();
        const slotOriginal = slotDescuentoOriginal();
        if (bloque && slotOriginal && !slotOriginal.contains(bloque)) {
            slotOriginal.appendChild(bloque);
        }
        avisoDescuentoEnModal(false);
    }

    function prepararModalF8EnBody() {
        const $modal = $('#modal-f8-descuento');
        if ($modal.length && !$modal.parent().is('body')) {
            $modal.appendTo('body');
        }
    }

    function initPortalDescuentoF8() {
        prepararModalF8EnBody();
        restaurarBloqueDescuentoEnTarjeta();
        if (!tieneDescuentoEnPantalla() && typeof mostrarPanelClienteInternoDescuento === 'function') {
            mostrarPanelClienteInternoDescuento(false);
        }
    }

    function rechazarModalF8Descuento(mensaje) {
        if (pendingModalF8DescuentoResolver) {
            pendingModalF8DescuentoResolver.reject(new Error(mensaje || 'Operación cancelada.'));
            pendingModalF8DescuentoResolver = null;
        }
        modalF8DescuentoEnCurso = false;
    }

    function enfocarCampoDescuentoCodigo() {
        const el = document.getElementById('codigodescuento');
        if (el && typeof el.focus === 'function') {
            el.focus();
            if (typeof el.select === 'function') {
                el.select();
            }
        }
    }

    window.estEnfocarCampoDescuentoCodigo = enfocarCampoDescuentoCodigo;

    function enfocarClienteInternoDescuento() {
        const el = document.getElementById('codigocliente_descuento');
        if (el && typeof el.focus === 'function') {
            el.focus();
            if (typeof el.select === 'function') {
                el.select();
            }
        }
    }

    function validarClienteInternoDescuentoEnPantalla() {
        if (!tieneDescuentoEnPantalla()) {
            return null;
        }
        const cliId = ($('#cliente_descuento_id').val() || '').trim();
        if (!cliId) {
            return 'Indique el cliente interno del descuento (quien invita o centro de costos). No es el cliente de la factura.';
        }
        return null;
    }

    function enfocarPrimerCampoPendienteModalF8() {
        if (!tieneDescuentoEnPantalla()) {
            enfocarCampoDescuentoCodigo();
            return;
        }
        const errCli = validarClienteInternoDescuentoEnPantalla();
        if (errCli) {
            enfocarClienteInternoDescuento();
            return;
        }
        const btn = document.getElementById('modal-f8-descuento-confirmar');
        if (btn && typeof btn.focus === 'function') {
            btn.focus();
            return;
        }
        enfocarCampoDescuentoCodigo();
    }

    async function cargarClienteInternoPorCodigoApi(codigo) {
        const cod = String(codigo || '').trim();
        if (!cod) {
            throw new Error('Código de cliente interno vacío.');
        }
        const res = await fetch((typeof carpetaBase !== 'undefined' ? carpetaBase : '') + '/ventas/leerunclienteporcodigo/' + encodeURIComponent(cod), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const data = await res.json().catch(function () {
            return {};
        });
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

    async function cargarDescuentoPorCodigo(codigo, silencioso) {
        codigo = String(codigo || '').trim();
        if (!codigo) {
            if (typeof pintarDescuentoEnPantalla === 'function') {
                pintarDescuentoEnPantalla(null);
            }
            return null;
        }
        if (typeof leerDescuentoPorCodigo !== 'function') {
            throw new Error('Consulta de descuento no disponible.');
        }
        try {
            await leerDescuentoPorCodigo(codigo, null, !!silencioso);
        } catch (e) {
            if (!silencioso) {
                toast((e && e.message) || 'Descuento no encontrado', 'error');
            }
            return null;
        }
        const id = ($('#descuento_estacionamiento_id').val() || '').trim();
        if (!id) {
            if (!silencioso) {
                toast('Descuento no encontrado', 'error');
            }
            return null;
        }
        return {
            id: parseInt(id, 10),
            codigo: ($('#codigodescuento').val() || '').trim(),
            nombre: ($('#nombredescuento').val() || '').trim(),
        };
    }

    function tieneDescuentoEnPantalla() {
        return !!($('#descuento_estacionamiento_id').val() || '').trim();
    }

    async function recalcularTotalCuentaConDescuento() {
        if (!cuenta || !cuenta.id) {
            return null;
        }
        try {
            await guardarCabecera(true);
            return cuenta;
        } catch (e) {
            return null;
        }
    }

    async function asegurarDescuentoObligatorio(opciones) {
        const silencioso = !!(opciones && opciones.silencioso);
        const descId = ($('#descuento_estacionamiento_id').val() || '').trim();
        const cod = ($('#codigodescuento').val() || '').trim();

        if (!cod) {
            if (descuentoEnModalF8()) {
                throw new Error('Indique el código de descuento (Enter o lupa) y pulse Facturar.');
            }
            throw new Error('Indique el código de descuento.');
        }

        const data = await cargarDescuentoPorCodigo(cod, silencioso);
        if (!data) {
            throw new Error('Descuento no encontrado.');
        }

        const errCli = validarClienteInternoDescuentoEnPantalla();
        if (errCli) {
            throw new Error(errCli);
        }

        await recalcularTotalCuentaConDescuento();
        return data;
    }

    function abrirModalF8Descuento() {
        return new Promise(function (resolve, reject) {
            if (!exigirOperacion()) {
                reject(new Error('Operación no permitida.'));
                return;
            }
            if (!cuenta || !cuenta.id) {
                reject(new Error('No hay cuenta activa.'));
                return;
            }
            if (modalF8DescuentoEnCurso) {
                reject(new Error('Ya hay un modal de descuento abierto.'));
                return;
            }
            modalF8DescuentoEnCurso = true;
            modalF8DescuentoConfirmadoOk = false;
            pendingModalF8DescuentoResolver = {
                resolve: function () {
                    pendingModalF8DescuentoResolver = null;
                    modalF8DescuentoEnCurso = false;
                    resolve();
                },
                reject: function (err) {
                    pendingModalF8DescuentoResolver = null;
                    modalF8DescuentoEnCurso = false;
                    reject(err);
                },
            };

            if (typeof mostrarPanelClienteInternoDescuento === 'function') {
                mostrarPanelClienteInternoDescuento(true);
            }
            if (typeof predefinirClienteInternoDescuentoEstacionamiento === 'function') {
                void predefinirClienteInternoDescuentoEstacionamiento().always(function () {
                    setTimeout(enfocarPrimerCampoPendienteModalF8, 80);
                });
            }

            moverBloqueDescuentoAlModal();
            $('#modal-f8-descuento').modal('show');
        });
    }

    async function confirmarModalF8Descuento() {
        const btn = document.getElementById('modal-f8-descuento-confirmar');
        if (emitiendo) {
            return;
        }
        if (btn) {
            btn.disabled = true;
        }
        let continuaFacturacion = false;
        try {
            setFacturacionLoading(true, 'Validando descuento y cliente interno…');
            await asegurarDescuentoObligatorio({ silencioso: true });
            continuaFacturacion = true;
            modalF8DescuentoConfirmadoOk = true;
            if (pendingModalF8DescuentoResolver) {
                pendingModalF8DescuentoResolver.resolve();
            }
            setFacturacionLoading(true, 'Preparando facturación con descuento…');
            $('#modal-f8-descuento').modal('hide');
        } catch (e) {
            toast(e.message || String(e), 'error');
        } finally {
            if (btn) {
                btn.disabled = false;
            }
            if (!continuaFacturacion) {
                setFacturacionLoading(false);
            }
        }
    }

    window.estOnDescuentoCargado = function () {
        if (cuenta && cuenta.id) {
            void recalcularTotalCuentaConDescuento();
        }
        if (typeof predefinirClienteInternoDescuentoEstacionamiento === 'function') {
            void predefinirClienteInternoDescuentoEstacionamiento().always(function () {
                if (descuentoEnModalF8()) {
                    setTimeout(enfocarPrimerCampoPendienteModalF8, 80);
                }
            });
            return;
        }
        if (descuentoEnModalF8()) {
            const errCli = validarClienteInternoDescuentoEnPantalla();
            if (errCli) {
                enfocarClienteInternoDescuento();
            }
        }
    };

    function fmt(n) {
        return Number(n || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function escHtml(s) {
        const d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function fmtCantidadLinea(cant) {
        const n = Number(cant || 0);
        if (Math.abs(n - Math.round(n)) < 0.0001) {
            return String(Math.round(n));
        }
        return n.toLocaleString('es-AR', { minimumFractionDigits: 0, maximumFractionDigits: 4 });
    }

    function formatearTextoAviso(msg) {
        return String(msg || '').replace(/\\n/g, '\n').trim();
    }

    function detenerRotacionMensajesProceso() {
        if (facturacionLoadingTimer) {
            clearInterval(facturacionLoadingTimer);
            facturacionLoadingTimer = null;
        }
    }

    /**
     * Mensajes alineados con lo que ocurre en POST /emitir-factura (antes de responder al POS).
     * Anita e impresión de ticket van en defer post-respuesta: no rotan en el overlay.
     */
    function mensajesProcesoEmision() {
        return [
            'Consultando numeración fiscal en ARCA…',
            'Registrando venta en el sistema…',
            'Registrando cobranza…',
            'Solicitando autorización ARCA (CAE)…',
            'Cerrando cuenta de estacionamiento…',
        ];
    }

    function iniciarRotacionMensajesProceso(mensajes, intervaloMs) {
        detenerRotacionMensajesProceso();
        if (!mensajes || !mensajes.length) {
            return;
        }
        let idx = 0;
        setFacturacionLoading(true, mensajes[0]);
        if (mensajes.length === 1) {
            return;
        }
        facturacionLoadingTimer = setInterval(function () {
            if (idx >= mensajes.length - 1) {
                return;
            }
            idx += 1;
            setFacturacionLoading(true, mensajes[idx], { soloTexto: true });
        }, intervaloMs || 3500);
    }

    function setFacturacionLoading(isLoading, mensaje, opciones) {
        const opts = opciones || {};
        const btnFacturar = document.getElementById('tool-facturar');
        const btnDescuento = document.getElementById('tool-descuento');
        const badge = document.getElementById('est-facturacion-loading');
        const texto = badge ? badge.querySelector('.est-facturacion-loading-text') : null;
        const overlay = document.getElementById('est-facturacion-procesando-overlay');
        const tituloOverlay = document.getElementById('est-facturacion-procesando-titulo');
        const subtituloOverlay = document.getElementById('est-facturacion-procesando-subtitulo');
        const barCuenta = document.getElementById('est-bar-cuenta-activa');
        const msgBar = document.getElementById('est-cuenta-proceso-msg');
        const badgeEstado = document.getElementById('est-cuenta-activa-estado');

        if (!isLoading) {
            detenerRotacionMensajesProceso();
        }

        if (!opts.soloTexto) {
            if (badge) {
                badge.style.display = isLoading ? 'inline-block' : 'none';
            }
            [btnFacturar, btnDescuento].forEach(function (btn) {
                if (!btn) {
                    return;
                }
                btn.disabled = !!isLoading;
                btn.style.pointerEvents = isLoading ? 'none' : '';
                btn.style.opacity = isLoading ? '0.6' : '';
            });
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
            if (barCuenta) {
                barCuenta.classList.toggle('est-procesando', !!isLoading);
            }
            if (badgeEstado) {
                if (isLoading) {
                    badgeEstado.className = 'badge badge-warning';
                    badgeEstado.textContent = 'FACTURANDO';
                } else {
                    badgeEstado.className = 'badge badge-info';
                    badgeEstado.textContent = 'ABIERTA';
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
        if (msgBar) {
            if (isLoading && textoProceso) {
                msgBar.textContent = textoProceso;
                msgBar.classList.remove('d-none');
            } else {
                msgBar.textContent = '';
                msgBar.classList.add('d-none');
            }
        }
        if (subtituloOverlay && opts.subtitulo !== undefined) {
            subtituloOverlay.textContent = opts.subtitulo;
        } else if (subtituloOverlay && !isLoading) {
            subtituloOverlay.textContent = 'Por favor espere. No cierre ni recargue la página.';
        }
    }

    function cerrarAvisoPersistente() {
        if (estAvisoKeyHandler) {
            document.removeEventListener('keydown', estAvisoKeyHandler, true);
            estAvisoKeyHandler = null;
        }
        $('#modal-est-aviso').modal('hide');
    }

    function mostrarAvisoPersistente(mensaje, tipo, opciones) {
        const opts = opciones || {};
        const modal = document.getElementById('modal-est-aviso');
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
        const header = document.getElementById('modal-est-aviso-header');
        const tituloEl = document.getElementById('modal-est-aviso-titulo');
        const detalleEl = document.getElementById('modal-est-aviso-detalle');
        const mensajeEl = document.getElementById('modal-est-aviso-body');
        const btnAceptar = document.getElementById('modal-est-aviso-aceptar');
        if (header) {
            header.className = 'modal-header py-2 ' + (headerCls[t] || headerCls.warning);
        }
        if (tituloEl) {
            tituloEl.textContent = titulos[t] || titulos.warning;
        }
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
        if (mensajeEl) {
            mensajeEl.textContent = formatearTextoAviso(mensaje);
        }
        if (estAvisoKeyHandler) {
            document.removeEventListener('keydown', estAvisoKeyHandler, true);
        }
        estAvisoKeyHandler = function (ev) {
            if (!modal.classList.contains('show')) {
                return;
            }
            if (ev.key === 'Enter' || ev.key === 'Escape' || ev.key === ' ') {
                ev.preventDefault();
                cerrarAvisoPersistente();
            }
        };
        document.addEventListener('keydown', estAvisoKeyHandler, true);
        $('#modal-est-aviso').off('hidden.bs.modal.estAviso').on('hidden.bs.modal.estAviso', function () {
            if (estAvisoKeyHandler) {
                document.removeEventListener('keydown', estAvisoKeyHandler, true);
                estAvisoKeyHandler = null;
            }
        });
        $('#modal-est-aviso').modal('show');
        setTimeout(function () {
            if (btnAceptar) {
                btnAceptar.focus();
            }
        }, 350);
    }

    function mostrarResultadoEmisionFactura(data) {
        const factura = (data && data.factura) || '';
        let txt =
            (data && String(data.mensaje || '').trim()) ||
            (factura ? 'Factura ' + factura + ' emitida correctamente.' : 'Factura emitida correctamente.');
        const warn = data && String(data.warn || '').trim();
        if (warn) {
            mostrarAvisoPersistente(warn, 'warning', {
                titulo: 'Factura emitida — revisar avisos',
                detalle: factura ? 'Comprobante: ' + factura : '',
            });
        }
        toast(txt, 'success');
    }

    function toast(msg, tipo, opciones) {
        const t = tipo || 'warning';
        if (opciones && opciones.persistente) {
            mostrarAvisoPersistente(msg, t, opciones);
            return;
        }
        if (typeof toastr !== 'undefined') {
            const opts =
                t === 'warning' || t === 'error'
                    ? { timeOut: 8000, extendedTimeOut: 4000, closeButton: true, progressBar: true }
                    : {};
            toastr[t](msg, '', opts);
        } else {
            alert(msg);
        }
    }

    function avisoModal(titulo, cuerpo, tipo) {
        mostrarAvisoPersistente(cuerpo, tipo || 'info', { titulo: titulo || 'Aviso' });
    }

    function csrfHeaders() {
        return {
            'X-CSRF-TOKEN': G.csrf || $('meta[name="csrf-token"]').attr('content'),
            Accept: 'application/json',
        };
    }

    async function api(method, path, data) {
        const opts = {
            method: method,
            headers: csrfHeaders(),
        };
        if (data !== undefined) {
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(data);
        }
        const res = await fetch(apiBase + path, opts);
        const json = await res.json().catch(function () {
            return { error: 'Respuesta inválida del servidor' };
        });
        if (!res.ok) {
            throw new Error(json.error || json.mensaje || ('Error HTTP ' + res.status));
        }
        return json;
    }

    function exigirOperacion() {
        if (!G.tieneCfgPv) {
            toast('Configure el punto de venta estacionamiento para esta terminal.', 'error');
            return false;
        }
        if (G.jornadaObligatoria && G.jornada && !G.jornada.jornada_abierta) {
            toast('Debe abrir la jornada antes de operar.', 'error');
            return false;
        }
        if (G.requiereHabilitacionTurno && G.turnoOperativo && !G.turnoOperativo.turno_habilitado) {
            toast('Debe habilitar el turno en esta terminal.', 'error');
            return false;
        }
        return true;
    }

    function categoriaIdActual() {
        const v = $('#est-categoria-select').val();
        return v ? parseInt(v, 10) : 0;
    }

    function categoriaUnica() {
        return categorias.length === 1 ? categorias[0] : null;
    }

    function actualizarUiSelectorCategoria() {
        const unica = categoriaUnica();
        const sel = $('#est-categoria-select');
        const hint = $('#est-categoria-hint-cambio');
        if (unica) {
            sel.val(unica.id);
            sel.addClass('d-none');
            if (hint.length) {
                hint.addClass('d-none');
            }
        } else {
            sel.removeClass('d-none');
            if (hint.length) {
                hint.removeClass('d-none');
            }
        }
        actualizarBarraCategoria();
    }

    async function persistirCategoriaUnicaSiHaceFalta(silencioso) {
        const unica = categoriaUnica();
        if (!unica || !cuenta || !cuenta.id) {
            return;
        }
        const id = parseInt(unica.id, 10);
        if (categoriaIdActual() !== id) {
            $('#est-categoria-select').val(id);
            actualizarBarraCategoria();
        }
        if (parseInt(cuenta.categoria_automovil_estacionamiento_id, 10) === id) {
            return;
        }
        await guardarCabecera(!!silencioso);
    }

    function actualizarBarraCategoria() {
        const id = categoriaIdActual();
        const cat = categorias.find(function (c) { return parseInt(c.id, 10) === id; });
        const bar = $('#est-bar-categoria');
        if (id > 0 && cat) {
            bar.removeClass('sin-categoria');
            $('#est-categoria-nombre-visible').removeClass('d-none').text(cat.nombre);
        } else {
            bar.addClass('sin-categoria');
            $('#est-categoria-nombre-visible').addClass('d-none').text('');
        }
    }

    function enfocarIngresoItem() {
        const el = document.getElementById('est-item-id-input');
        if (!el || typeof el.focus !== 'function') {
            return;
        }
        window.requestAnimationFrame(function () {
            el.focus();
            if (typeof el.select === 'function') {
                el.select();
            }
        });
    }

    function pintarCategoriasSelect() {
        const sel = $('#est-categoria-select');
        const prev = sel.val();
        sel.find('option:not(:first)').remove();
        categorias.forEach(function (c) {
            sel.append($('<option></option>').val(c.id).text(c.nombre));
        });
        const unica = categoriaUnica();
        if (unica) {
            sel.val(unica.id);
        } else if (prev) {
            sel.val(prev);
        }
        actualizarUiSelectorCategoria();
    }

    function renderIconosItems() {
        const cont = $('#est-items-iconos');
        cont.empty();
        if (!itemsCatalogo.length) {
            $('#est-items-vacio').removeClass('d-none');
            return;
        }
        $('#est-items-vacio').addClass('d-none');
        itemsCatalogo.forEach(function (it) {
            const btn = $('<button type="button" class="est-item-icono"></button>');
            btn.attr('title', it.nombre);
            btn.append($('<span class="est-item-id"></span>').text('#' + it.id));
            btn.append($('<span></span>').text(it.nombre));
            btn.append($('<span class="est-item-precio"></span>').text('$ ' + fmt(it.precio)));
            btn.on('click', function () {
                seleccionarItemPreview(it);
                void procesarAltaItemDirecto(it, 1);
            });
            cont.append(btn);
        });
    }

    function seleccionarItemPreview(it) {
        $('#est-item-id-input').val(it.id);
        $('#est-item-nombre-preview').val(it.nombre + ' — $ ' + fmt(it.precio));
    }

    function limpiarCampoItem() {
        $('#est-item-id-input').val('');
        $('#est-item-nombre-preview').val('');
    }

    function esAtajoCantidadEnItemInput(e) {
        return e.key === '+' || e.code === 'NumpadAdd' || (e.key === '=' && e.shiftKey);
    }

    async function resolverItemEnCampo() {
        const id = parseInt($('#est-item-id-input').val(), 10);
        const catId = categoriaIdActual();
        if (catId <= 0) {
            toast('Seleccione la categoría del vehículo primero.', 'warning');
            return { ok: false, item: null };
        }
        if (!id) {
            toast('Indique el ID del ítem.', 'warning');
            return { ok: false, item: null };
        }
        if (!cuenta || !cuenta.id) {
            toast('No hay cuenta activa.', 'warning');
            return { ok: false, item: null };
        }
        try {
            const data = await api('GET', '/item/' + id + '?categoria_id=' + catId);
            const item = data.item;
            if (!item || !item.id) {
                toast('Ítem no encontrado.', 'warning');
                limpiarCampoItem();
                enfocarIngresoItem();
                return { ok: false, item: null };
            }
            seleccionarItemPreview(item);
            return { ok: true, item: item };
        } catch (e) {
            limpiarCampoItem();
            toast(e.message, 'error');
            enfocarIngresoItem();
            return { ok: false, item: null };
        }
    }

    async function agregarLineaItem(item, cantidad) {
        if (!exigirOperacion()) {
            return;
        }
        if (!item || !item.id || !cuenta || !cuenta.id) {
            return;
        }
        const cant = parseFloat(cantidad);
        if (!(cant > 0)) {
            toast('Cantidad inválida', 'warning');
            return;
        }
        if (categoriaIdActual() <= 0) {
            toast('Seleccione la categoría antes de cargar ítems.', 'warning');
            return;
        }
        try {
            await guardarCabecera(true);
            const data = await api('POST', '/cuenta/' + cuenta.id + '/linea', {
                item_estacionamiento_id: item.id,
                cantidad: cant,
            });
            refrescarUiCuenta(data.cuenta);
            limpiarCampoItem();
            pendingItemEstacionamiento = null;
            enfocarIngresoItem();
        } catch (e) {
            toast(e.message, 'error');
        }
    }

    async function procesarAltaItemDirecto(item, cantidad) {
        await agregarLineaItem(item, cantidad);
    }

    function mostrarModalCantidadItem() {
        const fld = document.getElementById('est-fld-cantidad-linea');
        if (fld) {
            fld.value = '1';
        }
        return new Promise(function (resolve) {
            $('#modal-est-cantidad').one('shown.bs.modal.estCantidad', function () {
                if (fld && typeof fld.focus === 'function') {
                    fld.focus();
                    if (typeof fld.select === 'function') {
                        fld.select();
                    }
                }
                resolve();
            });
            $('#modal-est-cantidad').modal('show');
        });
    }

    async function iniciarAltaLineaItem(item) {
        if (!exigirOperacion()) {
            return;
        }
        pendingItemEstacionamiento = item;
        await mostrarModalCantidadItem();
    }

    async function continuarDespuesCantidadItem() {
        const fld = document.getElementById('est-fld-cantidad-linea');
        const cant = parseFloat((fld && fld.value) || '0');
        if (!(cant > 0)) {
            toast('Cantidad inválida', 'warning');
            return;
        }
        const item = pendingItemEstacionamiento;
        pendingItemEstacionamiento = null;
        $('#modal-est-cantidad').modal('hide');
        if (!item) {
            return;
        }
        await agregarLineaItem(item, cant);
    }

    function manejarEnterModalCantidadItem(e) {
        if (!e || e.key !== 'Enter' || e.ctrlKey || e.metaKey || e.altKey || e.shiftKey) {
            return;
        }
        const modal = document.getElementById('modal-est-cantidad');
        if (!modal || !modal.classList.contains('show')) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        void continuarDespuesCantidadItem();
    }

    async function intentarAgregarItemDesdeTeclado() {
        if (!exigirOperacion()) {
            return;
        }
        const res = await resolverItemEnCampo();
        if (!res.ok || !res.item) {
            return;
        }
        await procesarAltaItemDirecto(res.item, 1);
    }

    async function intentarAgregarItemConCantidadDesdeTeclado() {
        if (!exigirOperacion()) {
            return;
        }
        const res = await resolverItemEnCampo();
        if (!res.ok || !res.item) {
            return;
        }
        await iniciarAltaLineaItem(res.item);
    }

    async function resolverItemPorTabYEnfocarAgregar() {
        const res = await resolverItemEnCampo();
        if (!res.ok) {
            return;
        }
        const btn = document.getElementById('btn-est-agregar-item');
        if (btn && typeof btn.focus === 'function') {
            btn.focus();
        }
    }

    async function cargarItemsCatalogo() {
        const catId = categoriaIdActual();
        if (catId <= 0) {
            itemsCatalogo = [];
            renderIconosItems();
            return;
        }
        try {
            const data = await api('GET', '/items-catalogo?categoria_id=' + catId);
            itemsCatalogo = data.items || [];
            renderIconosItems();
        } catch (e) {
            toast(e.message, 'error');
        }
    }

    async function cargarCategorias() {
        try {
            const data = await api('GET', '/categorias');
            categorias = data.categorias || [];
            pintarCategoriasSelect();
        } catch (e) {
            toast(e.message, 'error');
        }
    }

    function aplicarDescuentoDesdeCuenta(c) {
        if (!c) {
            return;
        }
        const desc = c.descuento_estacionamiento || c.descuentoEstacionamiento;
        const cliInterno = c.cliente_interno_descuento || c.clienteInternoDescuento;
        if (typeof pintarDescuentoEnPantalla === 'function') {
            pintarDescuentoEnPantalla(
                desc
                    ? {
                        id: desc.id,
                        codigo: desc.codigo,
                        nombre: desc.nombre,
                        tipovalor: desc.tipovalor,
                        valor: desc.valor,
                        cliente: desc.cliente || null,
                    }
                    : null
            );
        }
        if (cliInterno && typeof aplicarClienteInternoDescuentoEnPantalla === 'function') {
            aplicarClienteInternoDescuentoEnPantalla(cliInterno);
            if (typeof mostrarPanelClienteInternoDescuento === 'function') {
                mostrarPanelClienteInternoDescuento(true);
            }
        }
    }

    function datosCabeceraDesdeFormulario() {
        return {
            categoria_automovil_estacionamiento_id: categoriaIdActual() || null,
            patente: ($('#est-patente').val() || '').trim().toUpperCase() || null,
            cliente_id: ($('#cliente_id').val() || '').trim() || null,
            descuento_estacionamiento_id: ($('#descuento_estacionamiento_id').val() || '').trim() || null,
            cliente_interno_descuento_id: ($('#cliente_descuento_id').val() || '').trim() || null,
            factura_receptor_nombre: ($('#fld-factura-receptor-nombre').val() || '').trim() || null,
            factura_receptor_documento: ($('#fld-factura-receptor-documento').val() || '').trim() || null,
            factura_receptor_domicilio: ($('#fld-factura-receptor-domicilio').val() || '').trim() || null,
        };
    }

    function aplicarCuentaEnFormulario(c) {
        if (!c) {
            return;
        }
        const unica = categoriaUnica();
        if (unica) {
            $('#est-categoria-select').val(unica.id);
        } else if (c.categoria_automovil_estacionamiento_id) {
            $('#est-categoria-select').val(c.categoria_automovil_estacionamiento_id);
        }
        actualizarUiSelectorCategoria();
        $('#est-patente').val(c.patente || '');
        $('#cliente_id').val(c.cliente_id || '');
        $('#codigocliente').val(c.cliente && c.cliente.codigo != null ? c.cliente.codigo : '');
        $('#nombrecliente').val(c.cliente ? (c.cliente.nombre || '') : '');
        if (c.descuento_estacionamiento || c.descuentoEstacionamiento) {
            aplicarDescuentoDesdeCuenta(c);
        } else {
            if (typeof pintarDescuentoEnPantalla === 'function') {
                pintarDescuentoEnPantalla(null);
            }
        }
        $('#fld-factura-receptor-nombre').val(c.factura_receptor_nombre || '');
        $('#fld-factura-receptor-documento').val(c.factura_receptor_documento || '');
        $('#fld-factura-receptor-domicilio').val(c.factura_receptor_domicilio || '');
    }

    function etiquetaDescuentoAplicado(desc) {
        if (!desc) {
            return 'Descuento';
        }
        const val = Number(desc.valor || 0);
        if (desc.tipovalor === 'P') {
            return (desc.nombre || 'Descuento') + ' (' + val + '%)';
        }
        if (desc.tipovalor === 'I') {
            return (desc.nombre || 'Descuento') + ' ($ ' + val.toFixed(2) + ')';
        }
        return desc.nombre || 'Descuento';
    }

    function subtotalLineasSinDescuentoCabecera(c) {
        let sub = 0;
        ((c && c.lineas) ? c.lineas : []).forEach(function (ln) {
            const pu = Number(ln.precio_unitario);
            const cant = Number(ln.cantidad || 1);
            sub += cant * pu;
        });
        return Math.round(sub * 100) / 100;
    }

    function subtotalEstimadoDesdeCuenta(c) {
        if (c && c.total_facturar_ars != null && !Number.isNaN(Number(c.total_facturar_ars))) {
            return Number(c.total_facturar_ars);
        }
        if (c && c.subtotal_estimado != null && !Number.isNaN(Number(c.subtotal_estimado))) {
            return Number(c.subtotal_estimado);
        }
        return subtotalLineasSinDescuentoCabecera(c);
    }

    function htmlDetalleSubtotalConDescuento(c, subFinal) {
        const subBruto = subtotalLineasSinDescuentoCabecera(c);
        const desc = (c && (c.descuento_estacionamiento || c.descuentoEstacionamiento)) || null;
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

    function renderLineas(c) {
        const panel = document.getElementById('panel-detalle-lineas');
        if (!panel) {
            return;
        }
        const lineas = (c && c.lineas) ? c.lineas : [];
        if (!lineas.length) {
            panel.innerHTML = '<p class="text-muted small mb-0">Sin ítems cargados.</p>';
            setTotalFacturadoArs(0);
            return;
        }
        const sub = subtotalEstimadoDesdeCuenta(c);
        let html = '<table class="table table-sm table-striped mb-0">';
        html += '<thead><tr><th>#</th><th>Ítem</th><th>Cant.</th><th class="text-right">P.U.</th><th></th></tr></thead><tbody>';
        lineas.forEach(function (ln) {
            const pu = Number(ln.precio_unitario || 0);
            const cant = Number(ln.cantidad || 1);
            const item = ln.item_estacionamiento || ln.itemEstacionamiento;
            const codigo = item && item.codigo ? String(item.codigo) : ('#' + ln.item_estacionamiento_id);
            const nombre = (item && item.nombre) || ln.descripcion || codigo;
            const etiquetaItem = escHtml(codigo) + ' — ' + escHtml(nombre);
            html += '<tr>';
            html += '<td>' + escHtml(ln.numero_linea != null ? ln.numero_linea : '') + '</td>';
            html += '<td>' + etiquetaItem + '</td>';
            html += '<td class="text-nowrap align-middle">';
            html += '<button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 btn-est-qty" data-dir="-1" data-linea="' + ln.id + '" data-cant="' + cant + '" title="Menos">−</button>';
            html += '<span class="mx-1">' + escHtml(fmtCantidadLinea(cant)) + '</span>';
            html += '<button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 btn-est-qty" data-dir="1" data-linea="' + ln.id + '" data-cant="' + cant + '" title="Más">+</button>';
            html += '</td>';
            html += '<td class="text-right">' + pu.toFixed(2) + '</td>';
            html += '<td><button type="button" class="btn btn-sm btn-link text-danger btn-est-del-linea" data-linea="' + ln.id + '">quitar</button></td>';
            html += '</tr>';
        });
        html += '</tbody></table>';
        html += htmlDetalleSubtotalConDescuento(c, sub);
        panel.innerHTML = html;
        setTotalFacturadoArs(sub);
        actualizarBarraCuenta(c);

        panel.querySelectorAll('.btn-est-del-linea').forEach(function (btn) {
            btn.addEventListener('click', function () {
                quitarLinea(btn.getAttribute('data-linea'));
            });
        });
        panel.querySelectorAll('.btn-est-qty').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const lineaId = btn.getAttribute('data-linea');
                const cur = parseFloat(btn.getAttribute('data-cant'));
                const dir = parseInt(btn.getAttribute('data-dir'), 10);
                const next = cur + dir;
                if (!(next >= 0.0001)) {
                    toast('La cantidad no puede ser menor al mínimo permitido.', 'warning');
                    return;
                }
                void patchCantidadLinea(lineaId, next);
            });
        });
    }

    async function patchCantidadLinea(lineaId, nuevaCantidad) {
        if (!exigirOperacion()) {
            return;
        }
        if (!cuenta || !cuenta.id) {
            return;
        }
        if (!(nuevaCantidad >= 0.0001)) {
            toast('La cantidad no puede ser menor al mínimo permitido.', 'warning');
            return;
        }
        try {
            const data = await api('PATCH', '/cuenta/' + cuenta.id + '/linea/' + lineaId, {
                cantidad: nuevaCantidad,
            });
            refrescarUiCuenta(data.cuenta);
            enfocarIngresoItem();
        } catch (e) {
            toast(e.message, 'error');
        }
    }

    function actualizarBarraCuenta(c) {
        if (!c || !c.id) {
            $('#est-bar-cuenta-activa').addClass('d-none');
            return;
        }
        $('#est-bar-cuenta-activa').removeClass('d-none');
        const cat = c.categoria_automovil ? c.categoria_automovil.nombre : 'Sin categoría';
        const pat = c.patente ? ' · ' + c.patente : '';
        const tot = c.total_facturar_ars != null ? ' · Total $ ' + fmt(c.total_facturar_ars) : '';
        $('#est-cuenta-activa-linea').text('Cuenta #' + c.id + ' · ' + cat + pat + tot);
    }

    function monedaIdFila(tr) {
        const $tr = $(tr);
        return parseInt(String($tr.data('moneda-id') || $tr.attr('data-moneda-id') || monedaFacturaId), 10);
    }

    function montoCobranzaEnArs(tr) {
        const montoInp = tr.querySelector('.monto');
        const monId = monedaIdFila(tr);
        const val = parseFloat(String(montoInp && montoInp.value ? montoInp.value : '').replace(',', '.'));
        if (!monId || Number.isNaN(val) || val === 0) {
            return 0;
        }
        return val;
    }

    function totalCobranzaArsAntesDe(tr) {
        let total = 0;
        document.querySelectorAll('#tbody-est-cuenta-table tr.item-cuenta-est').forEach(function (row) {
            if (row === tr) {
                return;
            }
            total += montoCobranzaEnArs(row);
        });
        return total;
    }

    function saldoPendienteArs(tr) {
        return Math.max(0, totalFacturadoArs - totalCobranzaArsAntesDe(tr));
    }

    function saldoPendienteEnMonedaFila(tr) {
        return saldoPendienteArs(tr);
    }

    function montoCobranzaVacioOEsSugerido(montoInp) {
        if (montoInp.dataset.montoEditadoManual === '1') {
            return false;
        }
        const val = (montoInp.value || '').trim();
        if (val === '') {
            return true;
        }
        const cur = parseFloat(String(val).replace(',', '.'));
        const prev = parseFloat(montoInp.dataset.saldoValidacion || '');
        if (Number.isNaN(cur)) {
            return true;
        }
        if (!Number.isNaN(prev) && Math.abs(cur - prev) < TOLERANCIA_MONTO_COBRANZA) {
            return true;
        }
        return false;
    }

    function actualizarDatoSaldoMonto(tr) {
        const montoInp = tr.querySelector('.monto');
        if (!montoInp) {
            return;
        }
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
            const abr = (tr.querySelector('.moneda-abrev')?.value || '$').trim() || '$';
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
        document.querySelectorAll('#tbody-est-cuenta-table tr.item-cuenta-est').forEach(actualizarDatoSaldoMonto);
    }

    function sumarMontosCobranza() {
        actualizarDatosSaldoTodasFilas();
        const totales = {};
        let totalCobranzaArs = 0;
        document.querySelectorAll('#tbody-est-cuenta-table tr.item-cuenta-est').forEach(function (tr) {
            const montoInp = tr.querySelector('.monto');
            const monId = monedaIdFila(tr);
            const val = parseFloat(String(montoInp && montoInp.value ? montoInp.value : '').replace(',', '.'));
            if (!monId || Number.isNaN(val) || val === 0) {
                return;
            }
            totales[monId] = (totales[monId] || 0) + val;
            totalCobranzaArs += montoCobranzaEnArs(tr);
        });
        const wrap = document.getElementById('est-totales-cobranza');
        if (!wrap) {
            return;
        }
        const partes = Object.keys(totales).map(function (mid) {
            const abr = mid === String(monedaFacturaId) ? '$' : mid;
            return abr + ': ' + totales[mid].toFixed(2);
        });
        let html = '';
        if (partes.length) {
            html += '<div class="text-right mt-1 text-muted" style="font-size:0.875em;">' + partes.join(' · ') + '</div>';
        }
        html += '<div class="text-right mt-1"><strong>Total cobranza ($):</strong> ' + totalCobranzaArs.toFixed(2) + '</div>';
        if (totalFacturadoArs > 0) {
            const pendiente = Math.max(0, totalFacturadoArs - totalCobranzaArs);
            html += '<div class="text-right mt-1"><strong>Saldo pendiente ($):</strong> ' + pendiente.toFixed(2) + '</div>';
            const diff = Math.abs(totalCobranzaArs - totalFacturadoArs);
            const ok = diff < TOLERANCIA_MONTO_COBRANZA;
            let extra = '';
            if (!ok && totalCobranzaArs > 0) {
                extra = ' <span class="est-total-diff">(diferencia ' + diff.toFixed(2) + ')</span>';
            } else if (ok && totalCobranzaArs > 0) {
                extra = ' <span class="text-success">✓</span>';
            }
            html += '<div class="text-right mt-1"><strong>Total factura ($):</strong> ' + totalFacturadoArs.toFixed(2) + extra + '</div>';
        }
        if (cuenta && cuenta.sin_cobranza) {
            html += ' <span class="badge badge-warning">Sin cobranza</span>';
        }
        wrap.innerHTML = html;
        $('#factura-moneda-id').val(monedaFacturaId);
    }

    function setTotalFacturadoArs(monto) {
        totalFacturadoArs = Math.max(0, parseFloat(monto) || 0);
        sumarMontosCobranza();
    }

    function refrescarUiCuenta(c) {
        cuenta = c;
        aplicarCuentaEnFormulario(c);
        renderLineas(c);
        actualizarBarraCuenta(c);
    }

    async function guardarCabecera(silencioso) {
        if (!cuenta || !cuenta.id) {
            return;
        }
        try {
            const data = await api('PATCH', '/cuenta/' + cuenta.id, datosCabeceraDesdeFormulario());
            refrescarUiCuenta(data.cuenta);
            if (!silencioso) {
                toast('Datos guardados.', 'success');
            }
        } catch (e) {
            toast(e.message, 'error');
            throw e;
        }
    }

    async function initCuentaActiva(opciones) {
        const opts = opciones || {};
        try {
            const data = await api('GET', '/cuenta-activa');
            refrescarUiCuenta(data.cuenta);
            await persistirCategoriaUnicaSiHaceFalta(true);
            if (categoriaIdActual() > 0) {
                await cargarItemsCatalogo();
                if (opts.enfocarItem !== false) {
                    enfocarIngresoItem();
                }
            }
        } catch (e) {
            toast(e.message, 'error');
        }
    }

    async function buscarItemPorId() {
        await resolverItemEnCampo();
    }

    async function agregarItemActual() {
        if (!exigirOperacion()) {
            return;
        }
        const res = await resolverItemEnCampo();
        if (!res.ok || !res.item) {
            return;
        }
        await iniciarAltaLineaItem(res.item);
    }

    async function quitarLinea(lineaId) {
        if (!cuenta || !cuenta.id) {
            return;
        }
        try {
            const data = await api('DELETE', '/cuenta/' + cuenta.id + '/linea/' + lineaId);
            refrescarUiCuenta(data.cuenta);
        } catch (e) {
            toast(e.message, 'error');
        }
    }

    function sumaCobranzaGrilla() {
        let s = 0;
        $('#tbody-est-cuenta-table tr.item-cuenta-est').each(function () {
            const m = parseFloat(String($(this).find('.monto').val()).replace(',', '.')) || 0;
            s += m;
        });
        return s;
    }

    function mediosPagoDesdeGrilla() {
        const medios = [];
        $('#tbody-est-cuenta-table tr.item-cuenta-est').each(function () {
            const ccId = parseInt($(this).find('.cuentacaja_id').val(), 10);
            const monedaId = parseInt($(this).data('moneda-id'), 10) || monedaFacturaId;
            const monto = parseFloat(String($(this).find('.monto').val()).replace(',', '.')) || 0;
            if (ccId > 0 && monto > 0) {
                medios.push({ cuentacaja_id: ccId, moneda_id: monedaId, monto: monto });
            }
        });
        return medios;
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
        const cfg = cuentasCajaPorId[String(cuenta.id)] || cuentasCajaPorId[parseInt(cuenta.id, 10)];
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
        if (!tr) {
            return;
        }
        const btnConsulta = tr.querySelector('.consultacuentacaja');
        if (!btnConsulta) {
            return;
        }
        btnConsulta.querySelectorAll('i, .gastro-icon-mercadopago').forEach(function (el) {
            el.remove();
        });
        btnConsulta.insertAdjacentHTML('afterbegin', htmlIconoMedio(resolverIconoCuentacaja(cuenta)));
    }

    function etiquetaCortaMedioPago(cuenta) {
        if (!cuenta) {
            return '';
        }
        if (cuenta.etiqueta_boton) {
            return String(cuenta.etiqueta_boton);
        }
        const codigo = String(cuenta.codigo || '').trim();
        if (codigo) {
            return codigo;
        }
        const nombre = String(cuenta.nombre || '').trim();
        if (!nombre) {
            return 'Medio';
        }
        const palabras = nombre.split(/\s+/).filter(Boolean);
        if (palabras.length <= 2) {
            return nombre;
        }
        return palabras.slice(0, 2).join(' ');
    }

    function asignarCuentaCajaEnFila(tr, cuentaCaja) {
        if (!tr || !cuentaCaja || !cuentaCaja.id) {
            return;
        }
        const $tr = $(tr);
        $tr.find('.cuentacaja_id').val(cuentaCaja.id);
        $tr.find('.codigo').val(cuentaCaja.codigo || '');
        $tr.find('.nombre').val(cuentaCaja.nombre || '');
        $tr.find('.moneda-abrev').val(cuentaCaja.moneda_abreviatura || 'ARS');
        const monId = cuentaCaja.moneda_id || monedaFacturaId;
        $tr.data('moneda-id', monId);
        $tr.attr('data-moneda-id', monId);
        actualizarIconoConsultaFila(tr, cuentaCaja);
        actualizarDatoSaldoMonto(tr);
        const montoInp = tr.querySelector('.monto');
        if (montoInp && cuentaCaja.monto && montoCobranzaVacioOEsSugerido(montoInp)) {
            montoInp.value = cuentaCaja.monto;
        }
        if (montoInp) {
            montoInp.focus();
        }
        sumarMontosCobranza();
    }

    function agregarRenglonCobranza(prefill) {
        const tpl = document.getElementById('est-template-renglon-cuenta');
        if (!tpl) {
            return;
        }
        const row = $(tpl.content.cloneNode(true)).find('tr');
        $('#tbody-est-cuenta-table').append(row);
        activarEventosCobranzaFila(row);
        if (prefill && prefill.id) {
            asignarCuentaCajaEnFila(row[0], prefill);
        } else {
            sumarMontosCobranza();
        }
    }

    function activarEventosCobranzaFila($row) {
        const tr = $row[0];
        $row.find('.est-quitar-renglon-cobranza').on('click', function () {
            $(this).closest('tr').remove();
            sumarMontosCobranza();
        });
        $row.find('.monto').on('focus', function () {
            actualizarDatoSaldoMonto(tr);
        });
        $row.find('.monto').on('change', function () {
            sumarMontosCobranza();
        });
        $row.find('.monto').on('input', function () {
            tr.querySelector('.monto').dataset.montoEditadoManual = '1';
            sumarMontosCobranza();
        });
        $row.find('.codigo').on('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                resolverCuentacajaPorCodigo($(this).closest('tr'));
            }
        });
        $row.find('.consultacuentacaja').on('click', function () {
            abrirConsultaCuentacajaEst(tr);
        });
    }

    function abrirConsultaCuentacajaEst(tr) {
        const emp = document.getElementById('empresa_id') || document.getElementById('est-empresa-id');
        if (emp && G.empresaId) {
            emp.value = G.empresaId;
        }
        cuentacajaxcodigo = tr.querySelector('.cuentacaja_id');
        $('#consultacuentacajaModal').one('shown.bs.modal.estCuenta', function () {
            if (typeof buscar_datos_cuentacaja === 'function') {
                buscar_datos_cuentacaja('');
            }
            $(this).find('#consultacuentacaja').trigger('focus');
        });
        $('#consultacuentacajaModal').modal('show');
    }

    async function resolverCuentacajaPorCodigo($row) {
        const cod = ($row.find('.codigo').val() || '').trim();
        if (!cod) {
            return;
        }
        try {
            const data = await api('GET', '/cuentacaja-por-codigo/' + encodeURIComponent(cod));
            if (data.id) {
                asignarCuentaCajaEnFila($row[0], data);
            }
        } catch (e) {
            toast(e.message, 'error');
        }
    }

    function seleccionarMedioPagoRapido(cuenta) {
        if (!cuenta || !cuenta.id) {
            return;
        }
        const tbody = document.getElementById('tbody-est-cuenta-table');
        if (!tbody) {
            return;
        }
        let tr = Array.from(tbody.querySelectorAll('tr')).find(function (row) {
            const ccId = (row.querySelector('.cuentacaja_id')?.value || '').trim();
            return !ccId;
        });
        if (!tr) {
            agregarRenglonCobranza(null);
            tr = tbody.querySelector('tr:last-child');
        }
        if (!tr) {
            return;
        }
        asignarCuentaCajaEnFila(tr, cuenta);
    }

    function renderMediosRapidos() {
        const wrap = document.getElementById('est-medios-rapidos');
        if (!wrap) {
            return;
        }
        cuentasCajaPorId = {};
        cuentasCaja.forEach(function (c) {
            if (c && c.id) {
                cuentasCajaPorId[String(c.id)] = c;
            }
        });
        wrap.innerHTML = '';
        if (!cuentasCaja.length) {
            wrap.classList.add('d-none');
            return;
        }
        wrap.classList.remove('d-none');
        cuentasCaja.forEach(function (cuenta) {
            const info = resolverIconoCuentacaja(cuenta);
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-sm btn-outline-secondary est-medio-rapido';
            btn.title = (cuenta.codigo ? cuenta.codigo + ' — ' : '') + (cuenta.nombre || '');
            btn.dataset.cuentacajaId = String(cuenta.id);
            btn.innerHTML = htmlIconoMedio(info) + '<span>' + etiquetaCortaMedioPago(cuenta) + '</span>';
            btn.addEventListener('click', function () {
                seleccionarMedioPagoRapido(cuenta);
            });
            wrap.appendChild(btn);
        });
    }

    async function cargarCuentasCaja() {
        try {
            const data = await api('GET', '/cuentas-caja');
            cuentasCaja = data.cuentas_caja || [];
            renderMediosRapidos();
        } catch (e) {
            toast(e.message, 'error');
        }
    }

    function prepararCobranzaEfectivo() {
        const total = parseFloat(cuenta && cuenta.total_facturar_ars ? cuenta.total_facturar_ars : 0);
        if (total <= 0 || cuenta.sin_cobranza) {
            return;
        }
        if (mediosPagoDesdeGrilla().length > 0) {
            return;
        }
        const eff = G.cuentacajaEfectivo || null;
        if (!eff || !eff.id) {
            toast(G.cuentacajaEfectivoError || 'Configure cuenta de caja efectivo para F5.', 'warning');
            return;
        }
        $('#tbody-est-cuenta-table').empty();
        agregarRenglonCobranza({
            id: eff.id,
            codigo: eff.codigo,
            nombre: eff.nombre,
            moneda_id: eff.moneda_id || monedaFacturaId,
            moneda_abreviatura: eff.moneda_abreviatura || 'ARS',
            monto: total.toFixed(2),
        });
    }

    async function emitirFactura(opciones) {
        opciones = opciones || {};
        if (emitiendo || !exigirOperacion() || !cuenta || !cuenta.id) {
            return;
        }
        emitiendo = true;

        try {
            setFacturacionLoading(true, 'Validando cuenta y cobranza…');
            await guardarCabecera(true);
            if (!opciones.exigirDescuento) {
                prepararCobranzaEfectivo();
            }
            const medios = mediosPagoDesdeGrilla();
            const body = {
                cuenta_id: cuenta.id,
                moneda_id: monedaFacturaId,
                medios_pago: medios,
                facturacion_con_descuento: !!opciones.exigirDescuento,
            };

            setFacturacionLoading(true, 'Validando datos de emisión…');
            const val = await api('POST', '/validar-emision', body);
            if (!val.ok) {
                throw new Error((val.errores || [val.error]).join(' '));
            }

            iniciarRotacionMensajesProceso(mensajesProcesoEmision());
            const res = await api('POST', '/emitir-factura', body);
            detenerRotacionMensajesProceso();
            mostrarResultadoEmisionFactura(res);
            await initCuentaActiva();
            $('#tbody-est-cuenta-table').empty();
            if (!$('#tbody-est-cuenta-table tr').length) {
                agregarRenglonCobranza(null);
            }
        } catch (e) {
            detenerRotacionMensajesProceso();
            mostrarAvisoPersistente(e.message || String(e), 'error', {
                titulo: 'Error al facturar',
            });
        } finally {
            emitiendo = false;
            setFacturacionLoading(false);
        }
    }

    function efectivizar() {
        emitirFactura({ exigirDescuento: false });
    }

    async function facturarConDescuento() {
        if (!exigirOperacion()) {
            return;
        }
        if (!cuenta || !cuenta.id) {
            toast('No hay cuenta activa.', 'warning');
            return;
        }
        const lineas = (cuenta && cuenta.lineas) ? cuenta.lineas : [];
        if (!lineas.length) {
            toast('La cuenta no tiene ítems para facturar.', 'warning');
            return;
        }

        try {
            await abrirModalF8Descuento();
            setFacturacionLoading(true, 'Recalculando total con descuento…');
            const cActualizada = await recalcularTotalCuentaConDescuento();
            const cRef = cActualizada || cuenta;
            const sinCobranza = !!cRef.sin_cobranza;
            const medios = mediosPagoDesdeGrilla();
            if (!sinCobranza && !medios.length) {
                prepararCobranzaEfectivo();
                setFacturacionLoading(false);
                toast(
                    'Descuento aplicado. Revise el medio de cobro en la grilla y pulse F8 de nuevo (o Facturar).',
                    'info',
                );
                return;
            }
            await emitirFactura({ exigirDescuento: true });
        } catch (e) {
            setFacturacionLoading(false);
            if (e && e.message && e.message !== 'Operación cancelada.') {
                toast(e.message, 'error');
            }
        }
    }

    async function cerrarCuentaSinFacturar() {
        if (!cuenta || !cuenta.id) {
            return;
        }
        if (!confirm('¿Cerrar la cuenta sin facturar? Se perderán los ítems cargados.')) {
            return;
        }
        try {
            await api('POST', '/cerrar-cuenta/' + cuenta.id);
            await initCuentaActiva();
            toast('Cuenta cerrada.', 'info');
        } catch (e) {
            toast(e.message, 'error');
        }
    }

    async function cargarConfig() {
        try {
            const cfg = await api('GET', '/config');
            G.cuentacajaEfectivo = cfg.cuentacaja_efectivo || null;
            G.cuentacajaEfectivoError = cfg.cuentacaja_efectivo_error || null;
            monedaFacturaId = cfg.moneda_factura_id || monedaFacturaId;
            if (cfg.cliente_descuento_codigo) {
                G.clienteDescuentoCodigo = cfg.cliente_descuento_codigo;
            }
            if (cfg.cliente_descuento) {
                G.clienteDescuento = cfg.cliente_descuento;
            }
        } catch (e) {
            /* cfg opcional al inicio */
        }
    }

    function wireCamposDescuentoTeclado() {
        const codDesc = document.getElementById('codigodescuento');
        const codCli = document.getElementById('codigocliente_descuento');

        if (codDesc) {
            codDesc.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const cod = (codDesc.value || '').trim();
                    if (!cod) {
                        return;
                    }
                    void cargarDescuentoPorCodigo(cod).then(function (data) {
                        if (!data) {
                            return;
                        }
                        if (descuentoEnModalF8()) {
                            const errCli = validarClienteInternoDescuentoEnPantalla();
                            if (errCli) {
                                enfocarClienteInternoDescuento();
                            } else {
                                void confirmarModalF8Descuento();
                            }
                        } else {
                            enfocarClienteInternoDescuento();
                        }
                    });
                    return;
                }
                if (e.key === 'Tab' && !e.shiftKey) {
                    const panelCli = document.getElementById('panel-cliente-descuento');
                    if (panelCli && !panelCli.classList.contains('d-none')) {
                        e.preventDefault();
                        enfocarClienteInternoDescuento();
                    }
                }
            });
        }

        if (codCli) {
            codCli.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const cod = (codCli.value || '').trim();
                    if (!cod) {
                        return;
                    }
                    void cargarClienteInternoPorCodigoApi(cod).then(function () {
                        if (descuentoEnModalF8() && !validarClienteInternoDescuentoEnPantalla()) {
                            void confirmarModalF8Descuento();
                        }
                    }).catch(function (err) {
                        toast(err.message || String(err), 'error');
                    });
                }
            });
        }
    }

    function registrarEventos() {
        $('#est-categoria-select').on('change', async function () {
            if (categoriaUnica()) {
                return;
            }
            actualizarBarraCategoria();
            await guardarCabecera(true).catch(function () {});
            await cargarItemsCatalogo();
            if (categoriaIdActual() > 0) {
                enfocarIngresoItem();
            }
        });

        $('#btn-est-buscar-item').on('click', function () {
            void buscarItemPorId();
        });
        $('#btn-est-agregar-item').on('click', function () {
            void agregarItemActual();
        });

        document.getElementById('modal-est-cantidad-confirmar').addEventListener('click', function () {
            void continuarDespuesCantidadItem();
        });
        const fldCantidad = document.getElementById('est-fld-cantidad-linea');
        if (fldCantidad) {
            fldCantidad.addEventListener('keydown', manejarEnterModalCantidadItem);
        }
        const btnCantidadConfirmar = document.getElementById('modal-est-cantidad-confirmar');
        if (btnCantidadConfirmar) {
            btnCantidadConfirmar.addEventListener('keydown', manejarEnterModalCantidadItem);
        }
        if (typeof $ !== 'undefined') {
            $('#modal-est-cantidad').on('keydown.estCantidad', manejarEnterModalCantidadItem);
            $('#modal-est-cantidad').on('hidden.bs.modal.estCantidad', function () {
                if (pendingItemEstacionamiento) {
                    pendingItemEstacionamiento = null;
                }
            });
        }

        document.addEventListener(
            'keydown',
            function (e) {
                const t = e.target;
                if (!t || t.id !== 'est-item-id-input') {
                    return;
                }

                if (esAtajoCantidadEnItemInput(e)) {
                    e.preventDefault();
                    e.stopPropagation();
                    void intentarAgregarItemConCantidadDesdeTeclado();
                    return;
                }

                if (e.key !== 'Enter' && e.key !== 'Tab') {
                    return;
                }

                if (e.key === 'Enter') {
                    e.preventDefault();
                    e.stopPropagation();
                    void intentarAgregarItemDesdeTeclado();
                    return;
                }
                if (e.key === 'Tab' && !e.shiftKey) {
                    e.preventDefault();
                    e.stopPropagation();
                    void resolverItemPorTabYEnfocarAgregar();
                }
            },
            true,
        );

        $('#btn-est-guardar-cabecera').on('click', function () { guardarCabecera(false); });
        $('#btn-est-cerrar-cuenta').on('click', cerrarCuentaSinFacturar);
        $('#tool-facturar').on('click', efectivizar);
        $('#tool-descuento').on('click', function () { void facturarConDescuento(); });

        wireCamposDescuentoTeclado();
        wireApiladoConsultasSobreModalF8();

        $('#est-agrega-renglon-cuenta').on('click', function () {
            agregarRenglonCobranza(null);
        });

        $('#modal-f8-descuento-confirmar').on('click', function () { void confirmarModalF8Descuento(); });
        $('#modal-f8-descuento').on('show.bs.modal.estPortal', function () {
            moverBloqueDescuentoAlModal();
        });
        $('#modal-f8-descuento').on('shown.bs.modal', function () {
            if (!descuentoEnModalF8()) {
                moverBloqueDescuentoAlModal();
            }
            if (typeof predefinirClienteInternoDescuentoEstacionamiento === 'function') {
                void predefinirClienteInternoDescuentoEstacionamiento().always(function () {
                    setTimeout(enfocarPrimerCampoPendienteModalF8, 80);
                });
            } else {
                setTimeout(enfocarPrimerCampoPendienteModalF8, 80);
            }
        });
        $('#modal-f8-descuento').on('hidden.bs.modal', function () {
            restaurarBloqueDescuentoEnTarjeta();
            if (!modalF8DescuentoConfirmadoOk) {
                rechazarModalF8Descuento('Operación cancelada.');
                setFacturacionLoading(false);
            }
            modalF8DescuentoConfirmadoOk = false;
        });
        $('#modal-f8-descuento').on('keydown.estF8Enter', function (e) {
            if (e.key !== 'Enter' || teclaConModificador(e)) {
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

        document.addEventListener('keydown', function (e) {
            if (!esTeclaF5(e) && !esTeclaF8(e)) {
                return;
            }
            if (teclaConModificador(e)) {
                return;
            }
            if (debeIgnorarAtajoPos()) {
                return;
            }
            if (esTeclaF5(e)) {
                e.preventDefault();
                e.stopPropagation();
                void efectivizar();
                return;
            }
            if (esTeclaF8(e)) {
                e.preventDefault();
                e.stopPropagation();
                void facturarConDescuento();
            }
        }, true);

        if (typeof activa_eventos_consultacliente === 'function') {
            activa_eventos_consultacliente();
        }
        if (typeof activa_eventos_consultadescuento === 'function') {
            activa_eventos_consultadescuento();
        }

        $(document).off('click.estCliInterno', '.consultaclienteinternodescuento');
        $(document).on('click.estCliInterno', '.consultaclienteinternodescuento', function () {
            if (typeof ptrcliente_id !== 'undefined') {
                ptrcliente_id = $('#cliente_descuento_id');
            }
            if (typeof ptrnombrecliente !== 'undefined') {
                ptrnombrecliente = $('#nombrecliente_descuento');
            }
            $('#consultaclienteModal').data('gastroConsultaDestino', 'interno');
            $('#consultaclienteModal').modal('show');
        });

        $(document).off('click.estCuentaElige', '.eligeconsultacuentacaja');
        $(document).on('click.estCuentaElige', '.eligeconsultacuentacaja', function () {
            if (!cuentacajaxcodigo) {
                return;
            }
            const trModal = $(this).parents('tr');
            const id = trModal.find('.cuentacaja_id').html();
            const tr = cuentacajaxcodigo.closest('tr');
            asignarCuentaCajaEnFila(tr, {
                id: parseInt(id, 10),
                nombre: trModal.find('.nombre').html(),
                codigo: trModal.find('.codigo').html(),
                moneda_id: parseInt(trModal.find('.moneda_id').html(), 10),
                moneda_abreviatura: trModal.find('.nombremoneda').html() || 'ARS',
            });
            $('#consultacuentacajaModal').modal('hide');
            cuentacajaxcodigo = null;
        });
    }

    $(async function () {
        initPortalDescuentoF8();
        if (!G.tieneCfgPv) {
            return;
        }
        registrarEventos();
        await cargarConfig();
        await cargarCategorias();
        await cargarCuentasCaja();
        await initCuentaActiva();
        if (!$('#tbody-est-cuenta-table tr').length) {
            agregarRenglonCobranza(null);
        }
    });
}(jQuery));
