(function ($) {
    'use strict';

    const G = window.CANJE_MARKETING || {};
    const apiBase = (G.rutas && G.rutas.apiBase) ? G.rutas.apiBase.replace(/\/$/, '') : '';

    let cuenta = null;
    let mozoActual = null;
    let emitiendo = false;
    let pendingArticulo = null;
    let pendingOpcionalesResolver = null;
    let cmPendingAltaPostOpcionales = null;
    let cmModalCantidadResolver = null;
    let cmWigosInputTimer = null;
    let cmWigosProcesando = false;
    let cmWigosUltimoTrackdata = '';
    let cmWigosVipPendiente = null;
    let cmLoginModoNuevaCuenta = false;
    let cmFacturacionLoadingTimer = null;
    let cmFacturacionLoadingGen = 0;
    let cmDocumentEnterModalF8Activo = false;

    function detenerRotacionMensajesCm() {
        cmFacturacionLoadingGen += 1;
        if (cmFacturacionLoadingTimer) {
            clearInterval(cmFacturacionLoadingTimer);
            cmFacturacionLoadingTimer = null;
        }
    }

    function setFacturacionLoadingCm(isLoading, mensaje, opciones) {
        const opts = opciones || {};
        const overlay = document.getElementById('cm-facturacion-procesando-overlay');
        const tituloOverlay = document.getElementById('cm-facturacion-procesando-titulo');
        const subtituloOverlay = document.getElementById('cm-facturacion-procesando-subtitulo');
        const btnF8 = document.getElementById('modal-cm-f8-confirmar');
        if (!isLoading) {
            detenerRotacionMensajesCm();
        }
        if (!opts.soloTexto) {
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
            if (btnF8) {
                btnF8.disabled = !!isLoading;
            }
        }
        const texto = mensaje || (isLoading ? 'Procesando…' : '');
        if (tituloOverlay && texto) {
            tituloOverlay.textContent = texto;
        }
        if (subtituloOverlay) {
            if (opts.subtitulo !== undefined) {
                subtituloOverlay.textContent = opts.subtitulo;
            } else if (!isLoading) {
                subtituloOverlay.textContent = 'Por favor espere. No cierre ni recargue la página.';
            }
        }
    }

    function mensajesProcesoEmisionCm() {
        const mensajes = [
            'Generando comprobante fiscal…',
            'Registrando venta en el sistema…',
            'Solicitando autorización ARCA (CAE)…',
        ];
        if (G.imprimirTicket === true) {
            mensajes.push('Imprimiendo ticket térmico…');
        }
        return mensajes;
    }

    function iniciarRotacionMensajesCm(mensajes) {
        detenerRotacionMensajesCm();
        if (!mensajes || !mensajes.length) {
            return;
        }
        const gen = cmFacturacionLoadingGen;
        let idx = 0;
        const subtituloFinal = 'Aguarde: puede demorar unos segundos (ARCA e impresión). No cierre la pantalla.';
        setFacturacionLoadingCm(true, mensajes[0], {
            subtitulo: mensajes.length > 1
                ? 'Por favor espere. No cierre ni recargue la página.'
                : subtituloFinal,
        });
        if (mensajes.length <= 1) {
            return;
        }
        cmFacturacionLoadingTimer = setInterval(function () {
            if (gen !== cmFacturacionLoadingGen) {
                return;
            }
            if (idx >= mensajes.length - 1) {
                return;
            }
            idx += 1;
            const esUltimo = idx >= mensajes.length - 1;
            setFacturacionLoadingCm(true, mensajes[idx], {
                soloTexto: true,
                subtitulo: esUltimo ? subtituloFinal : 'Por favor espere. No cierre ni recargue la página.',
            });
        }, 3200);
    }

    function mostrarResultadoEmisionCanje(data) {
        const factura = (data && data.factura) || '';
        const txt = (data && String(data.mensaje || '').trim()) ||
            (factura ? 'Factura ' + factura + ' emitida correctamente.' : 'Factura emitida correctamente.');
        const warn = data && String(data.warn || '').trim();
        $('#modal-cm-f8-descuento').modal('hide');
        restaurarPanelDescuentoVipEnTarjeta();
        cuenta = null;
        renderCuenta();
        void cargarCuentasActivas();
        if (warn) {
            toast(warn, 'warning');
        }
        toast(txt, 'success');
        mostrarLoginMozo();
    }

    function csrf() {
        return G.csrfToken || $('meta[name="csrf-token"]').attr('content') || '';
    }

    function toast(msg, tipo) {
        if (typeof toastr !== 'undefined') {
            const t = tipo || 'info';
            const titulos = { error: 'Error', success: 'OK', warning: 'Aviso', info: 'Info' };
            toastr.options = {
                closeButton: true,
                progressBar: true,
                newestOnTop: true,
                positionClass: 'toast-top-right',
                preventDuplicates: true,
                timeOut: t === 'error' ? 8000 : 5000,
            };
            toastr[t](msg, titulos[t] || 'Info');
        } else {
            alert(msg);
        }
    }

    async function api(method, path, body) {
        const opts = {
            method: method,
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf(),
                'X-Requested-With': 'XMLHttpRequest',
            },
        };
        if (body !== undefined) {
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(body);
        }
        const res = await fetch(apiBase + path, opts);
        const data = await res.json().catch(function () { return {}; });
        if (!res.ok) {
            throw new Error(data.error || data.mensaje || ('Error HTTP ' + res.status));
        }
        return data;
    }

    const CM_MODAL_Z_BASE = 1050;
    const CM_MODAL_Z_STEP = 20;

    function contarModalesVisiblesCm() {
        return document.querySelectorAll('.modal.show').length;
    }

    function modalCmF8Abierto() {
        const el = document.getElementById('modal-cm-f8-descuento');
        return !!(el && el.classList.contains('show'));
    }

    function limpiarBackdropHuerfanoCm() {
        document.body.classList.remove('modal-open');
        document.querySelectorAll('.modal-backdrop').forEach(function (el) {
            el.remove();
        });
    }

    function sincronizarEstadoModalesCm() {
        const visibles = contarModalesVisiblesCm();
        if (visibles <= 0) {
            limpiarBackdropHuerfanoCm();
            return;
        }
        $('body').addClass('modal-open');
        let backdrops = $('.modal-backdrop');
        while (backdrops.length > visibles) {
            backdrops.last().remove();
            backdrops = $('.modal-backdrop');
        }
        if (backdrops.length === 0) {
            $('<div class="modal-backdrop fade show"></div>').appendTo(document.body);
        }
    }

    function apilarModalSobreCmF8($modal) {
        if (!$modal || !$modal.length || !modalCmF8Abierto()) {
            return;
        }
        const zHijo = CM_MODAL_Z_BASE + CM_MODAL_Z_STEP;
        const zBackdrop = zHijo - 10;
        $modal.data('cmApiladoSobreF8', true);
        $modal.css('z-index', zHijo);
        window.setTimeout(function () {
            if ($modal.data('cmApiladoSobreF8')) {
                $('.modal-backdrop').last().css('z-index', zBackdrop);
            }
        }, 0);
    }

    function desapilarModalSobreCmF8($modal) {
        if ($modal && $modal.length && $modal.data('cmApiladoSobreF8')) {
            $modal.removeData('cmApiladoSobreF8');
            $modal.css('z-index', '');
            if ($('.modal-backdrop').length) {
                $('.modal-backdrop').last().css('z-index', '');
            }
        }
        window.setTimeout(sincronizarEstadoModalesCm, 0);
    }

    function wireApiladoConsultasSobreCmF8() {
        ['#consultaclientevipModal', '#modal-cm-wigos-vip'].forEach(function (sel) {
            const $m = $(sel);
            if (!$m.length) {
                return;
            }
            $m.off('shown.bs.modal.cmF8Stack hidden.bs.modal.cmF8Stack');
            $m.on('shown.bs.modal.cmF8Stack', function () {
                apilarModalSobreCmF8($m);
            });
            $m.on('hidden.bs.modal.cmF8Stack', function () {
                desapilarModalSobreCmF8($m);
            });
        });
    }

    function fmtMoney(n) {
        return (Number(n) || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function resolverSkuCompleto(raw) {
        const val = String(raw || '').trim();
        if (!val) { return ''; }
        const digitos = Number(G.skuCatalogoDigitosSufijo || 0);
        const prefijo = String(G.skuCatalogoPrefijo || 'V');
        if (digitos > 0 && /^\d+$/.test(val)) {
            return prefijo + val.padStart(digitos, '0');
        }
        if (digitos > 0 && val.toUpperCase().startsWith(prefijo.toUpperCase())) {
            return val.toUpperCase();
        }
        return val;
    }

    function skuPermitidoCm(sku) {
        const s = String(sku || '').trim().toUpperCase();
        if (!s) {
            return false;
        }
        const digitos = Number(G.skuCatalogoDigitosSufijo || 0);
        const prefijo = String(G.skuCatalogoPrefijo || 'V').toUpperCase();
        if (digitos > 0) {
            const esc = prefijo.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            return new RegExp('^' + esc + '\\d{' + digitos + '}$').test(s);
        }
        return s.startsWith(prefijo);
    }

    function mensajeSkuCatalogoCm() {
        const digitos = Number(G.skuCatalogoDigitosSufijo || 0);
        const prefijo = String(G.skuCatalogoPrefijo || 'V');
        if (digitos > 0) {
            return prefijo + ' seguido de ' + digitos + ' dígitos';
        }
        return 'SKU debe comenzar con ' + prefijo;
    }

    function desbloquearPos() {
        $('#cm-pos-shell').removeClass('cm-bloqueado');
    }

    function bloquearPos() {
        $('#cm-pos-shell').addClass('cm-bloqueado');
    }

    function limpiarFormLoginMozo() {
        $('#cm-login-mozo_gastronomia_id').val('');
        $('#cm-login-codigomozo').val('');
        $('#cm-login-nombremozo').val('');
        const clave = document.getElementById('cm-login-clave-mozo');
        if (clave) {
            clave.value = '';
        }
    }

    function focusSkuInput() {
        const el = document.getElementById('cm-sku-input');
        if (!el || typeof el.focus !== 'function') {
            return;
        }
        el.focus();
        if (typeof el.select === 'function') {
            el.select();
        }
    }

    function aplicarArticuloEnCampoCargaCm(dataArticulo) {
        if (!dataArticulo) {
            return;
        }
        const sku = String(dataArticulo.sku || dataArticulo.codigo || '').trim();
        const desc = String(dataArticulo.descripcion || dataArticulo.nombre || '').trim();
        $('#cm-articulo_id').val(dataArticulo.id || dataArticulo.articulo_id || '');
        $('#cm-articulo-descripcion').val(desc);
        const digitos = Number(G.skuCatalogoDigitosSufijo || 0);
        const prefijo = String(G.skuCatalogoPrefijo || 'V');
        if (digitos > 0 && sku) {
            const esc = prefijo.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            const m = sku.match(new RegExp('^' + esc + '(\\d+)$', 'i'));
            if (m) {
                $('#cm-sku-input').val(m[1].replace(/^0+/, '') || m[1]);
            } else {
                $('#cm-sku-input').val(sku);
            }
        } else {
            $('#cm-sku-input').val(sku);
        }
        focusSkuInput();
    }

    function esAtajoCantidadEnSkuCm(e) {
        return e.key === '+' || e.code === 'NumpadAdd' || (e.key === '=' && e.shiftKey);
    }

    function limpiarCampoCargaArticuloCm() {
        $('#cm-articulo_id').val('');
        $('#cm-articulo-descripcion').val('');
        $('#cm-sku-input').val('');
    }

    function actualizarBotonCerrarCuenta() {
        const btn = document.getElementById('cm-btn-cerrar-cuenta');
        if (!btn) {
            return;
        }
        if (cuenta && cuenta.id) {
            btn.classList.remove('d-none');
        } else {
            btn.classList.add('d-none');
        }
    }

    function limpiarClienteVipDescuento() {
        $('#cliente_vip_id').val('');
        $('#cliente_vip_numeroid').val('');
        $('#cliente_vip_documento').val('');
        $('#cliente_vip_nombre').val('');
    }

    function ocultarAvisoVip() {
        $('#cm-vip-aviso').addClass('d-none').text('');
    }

    function mostrarAvisoVip(mensaje) {
        $('#cm-vip-aviso').removeClass('d-none').text(mensaje || 'Cliente VIP no encontrado.');
    }

    function pintarClienteVipEnPantalla(vip) {
        if (!vip) {
            limpiarClienteVipDescuento();
            return;
        }
        $('#cliente_vip_id').val(vip.id || '');
        $('#cliente_vip_numeroid').val(vip.numeroid != null ? String(vip.numeroid) : '');
        $('#cliente_vip_documento').val(vip.nrodocumento || '');
        $('#cliente_vip_nombre').val(
            vip.nombre_completo || ((vip.apellido || '') + ' ' + (vip.nombre || '')).trim()
        );
    }

    function lineasDesdeCuenta(c) {
        const raw = c && c.lineas;
        if (Array.isArray(raw)) {
            return raw;
        }
        if (raw && typeof raw === 'object') {
            return Object.values(raw);
        }
        return [];
    }

    function renderDescuentoVipDesdeCuenta(c) {
        if (!c) {
            $('#descuento_id').val('');
            $('#cm-codigodescuento').val('');
            $('#cm-nombredescuento').val('');
            limpiarClienteVipDescuento();
            return;
        }
        if (c.descuento_gastronomia) {
            $('#descuento_id').val(c.descuento_gastronomia.id || c.descuento_gastronomia_id || '');
            $('#cm-codigodescuento').val(c.descuento_gastronomia.codigo || '');
            $('#cm-nombredescuento').val(c.descuento_gastronomia.nombre || '');
        }
        pintarClienteVipEnPantalla(c.cliente_vip || null);
    }

    async function guardarClienteVipDescuentoCuenta(silencioso) {
        if (!cuenta || !cuenta.id) {
            return;
        }
        const vipId = parseInt($('#cliente_vip_id').val(), 10) || null;
        try {
            const data = await api('PATCH', '/cuenta/' + cuenta.id, {
                cliente_vip_gastronomia_id: vipId,
            });
            cuenta = data.cuenta;
            renderCuenta();
        } catch (e) {
            if (!silencioso) {
                toast(e.message, 'error');
            }
        }
    }

    function focusCodigoLoginMozo() {
        const el = document.getElementById('cm-login-codigomozo');
        if (!el || typeof el.focus !== 'function') {
            return;
        }
        el.focus();
        if (typeof el.select === 'function') {
            el.select();
        }
    }

    function focusIdLoginMozo() {
        const el = document.getElementById('cm-login-mozo_gastronomia_id');
        if (!el || typeof el.focus !== 'function') {
            return;
        }
        el.focus();
        if (typeof el.select === 'function') {
            el.select();
        }
    }

    function focusClaveLoginMozo() {
        const el = document.getElementById('cm-login-clave-mozo');
        if (el && typeof el.focus === 'function') {
            el.focus();
            if (typeof el.select === 'function') {
                el.select();
            }
        }
    }

    function limpiarCamposMozoLogin() {
        $('#cm-login-mozo_gastronomia_id').val('');
        $('#cm-login-codigomozo').val('');
        $('#cm-login-nombremozo').val('');
    }

    function aplicarMozoLogin(data) {
        if (!data || !data.id) {
            return false;
        }
        $('#cm-login-mozo_gastronomia_id').val(data.id);
        $('#cm-login-codigomozo').val(data.codigo != null ? String(data.codigo) : '');
        $('#cm-login-nombremozo').val(data.nombre || '');
        return true;
    }

    async function cargarMozoLoginPorCodigo(codigo) {
        const cod = String(codigo || '').trim();
        if (!cod) {
            return null;
        }
        limpiarCamposMozoLogin();
        $('#cm-login-codigomozo').val(cod);
        try {
            const data = await api('GET', '/mozo/leer-codigo/' + encodeURIComponent(cod));
            if (aplicarMozoLogin(data)) {
                return data;
            }
        } catch (e) { /* no encontrado */ }
        limpiarCamposMozoLogin();
        return null;
    }

    async function cargarMozoLoginPorId(id) {
        const mid = parseInt(String(id || '').trim(), 10);
        if (!(mid > 0)) {
            return null;
        }
        limpiarCamposMozoLogin();
        $('#cm-login-mozo_gastronomia_id').val(String(mid));
        try {
            const data = await api('GET', '/mozo/leer-id/' + mid);
            if (aplicarMozoLogin(data)) {
                return data;
            }
        } catch (e) { /* no encontrado */ }
        limpiarCamposMozoLogin();
        return null;
    }

    async function validarCodigoMozoLogin(opciones) {
        const opts = opciones || {};
        const cod = ($('#cm-login-codigomozo').val() || '').trim();
        const idActual = ($('#cm-login-mozo_gastronomia_id').val() || '').trim();

        $('#cm-login-error').addClass('d-none').text('');

        if (!cod) {
            if (opts.requerirCodigo) {
                $('#cm-login-error').removeClass('d-none').text('Indique el código del mozo.');
                focusCodigoLoginMozo();
            }
            return false;
        }

        if (idActual && ($('#cm-login-nombremozo').val() || '').trim() !== '') {
            focusClaveLoginMozo();
            return true;
        }

        const data = await cargarMozoLoginPorCodigo(cod);
        if (data) {
            focusClaveLoginMozo();
            return true;
        }
        $('#cm-login-error').removeClass('d-none').text('Mozo no encontrado para esta empresa.');
        focusCodigoLoginMozo();
        return false;
    }

    async function validarIdMozoLogin(opciones) {
        const opts = opciones || {};
        const idVal = ($('#cm-login-mozo_gastronomia_id').val() || '').trim();

        $('#cm-login-error').addClass('d-none').text('');

        if (!idVal) {
            if (opts.requerirId) {
                $('#cm-login-error').removeClass('d-none').text('Indique el ID del mozo.');
                focusIdLoginMozo();
            }
            return false;
        }

        const data = await cargarMozoLoginPorId(idVal);
        if (data) {
            focusClaveLoginMozo();
            return true;
        }
        $('#cm-login-error').removeClass('d-none').text('Mozo no encontrado para esta empresa.');
        focusIdLoginMozo();
        return false;
    }

    function onKeydownCodigoMozoLogin(e) {
        const esEnter = e.key === 'Enter' || e.keyCode === 13;
        const esTab = e.key === 'Tab' && !e.shiftKey;
        if (!esEnter && !esTab) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        void validarCodigoMozoLogin({ requerirCodigo: esEnter });
    }

    function onKeydownIdMozoLogin(e) {
        const esEnter = e.key === 'Enter' || e.keyCode === 13;
        const esTab = e.key === 'Tab' && !e.shiftKey;
        if (!esEnter && !esTab) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        void validarIdMozoLogin({ requerirId: esEnter });
    }

    function mostrarLoginMozo() {
        bloquearPos();
        mozoActual = null;
        cuenta = null;
        renderMozoBadge();
        renderCuenta();
        limpiarFormLoginMozo();
        $('#cm-login-error').addClass('d-none').text('');
        $('#modal-cm-login-mozo').modal('show');
    }

    function renderMozoBadge() {
        if (!mozoActual) {
            $('#cm-mozo-badge').text('Sin mozo').removeClass('badge-success').addClass('badge-secondary');
            return;
        }
        $('#cm-mozo-badge').text((mozoActual.codigo || '') + ' — ' + (mozoActual.nombre || '')).removeClass('badge-secondary').addClass('badge-success');
    }

    function renderCuenta() {
        if (!cuenta) {
            $('#cm-cuenta-label').text('Cuenta —');
            $('#cm-tbody-lineas').html('<tr><td colspan="5" class="text-muted text-center">Sin consumos</td></tr>');
            $('#cm-total-estimado').text('0,00');
            limpiarClienteVipDescuento();
            actualizarBotonCerrarCuenta();
            return;
        }
        $('#cm-cuenta-label').text('Cuenta #' + cuenta.id);
        const lineas = lineasDesdeCuenta(cuenta);
        if (!lineas.length) {
            $('#cm-tbody-lineas').html('<tr><td colspan="5" class="text-muted text-center">Sin consumos</td></tr>');
        } else {
            let html = '';
            lineas.forEach(function (l) {
                const art = l.articulo || {};
                const imp = (Number(l.cantidad) || 0) * (Number(l.precio_unitario) || 0);
                const cant = Number(l.cantidad) || 0;
                html += '<tr>';
                html += '<td>' + (art.sku || '') + '</td>';
                html += '<td>' + (art.descripcion || l.descripcion || '') + '</td>';
                html += '<td class="text-right text-nowrap align-middle cm-cantidad-linea">';
                html += '<button type="button" class="btn btn-xs btn-outline-secondary btn-cm-qty" data-dir="-1" data-id="' + l.id + '" data-cant="' + cant + '" title="Menos"><i class="fa fa-minus"></i></button>';
                html += '<span class="mx-1">' + cant + '</span>';
                html += '<button type="button" class="btn btn-xs btn-outline-secondary btn-cm-qty" data-dir="1" data-id="' + l.id + '" data-cant="' + cant + '" title="Más"><i class="fa fa-plus"></i></button>';
                html += '</td>';
                html += '<td class="text-right">' + fmtMoney(imp) + '</td>';
                html += '<td><button type="button" class="btn btn-xs btn-outline-danger cm-del-linea" data-id="' + l.id + '" title="Quitar"><i class="fa fa-trash"></i></button></td>';
                html += '</tr>';
            });
            $('#cm-tbody-lineas').html(html);
            $('#cm-tbody-lineas .btn-cm-qty').on('click', function () {
                const lineaId = $(this).data('id');
                const cur = parseFloat($(this).data('cant'));
                const dir = parseInt($(this).data('dir'), 10);
                const next = cur + dir;
                void patchCantidadLineaCm(lineaId, next);
            });
        }
        $('#cm-total-estimado').text(fmtMoney(cuenta.total_facturar_ars || cuenta.subtotal_estimado || 0));
        renderDescuentoVipDesdeCuenta(cuenta);
        actualizarBotonCerrarCuenta();
    }

    async function cargarCuentasActivas() {
        const panel = document.getElementById('cm-panel-cuentas');
        if (!panel) {
            return;
        }
        try {
            const data = await api('GET', '/cuentas-activas');
            const cuentas = data.cuentas || [];
            panel.innerHTML = '';
            if (!cuentas.length) {
                panel.innerHTML = '<div class="col-12"><span class="text-muted small">Sin cuentas abiertas</span></div>';
                return;
            }
            cuentas.forEach(function (c) {
                const esActiva = cuenta && cuenta.id === c.id;
                const col = document.createElement('div');
                col.className = 'col-auto';
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-sm m-1' + (esActiva ? ' btn-primary btn-gastro-cuenta-activa' : ' btn-outline-primary');
                let label = 'Cuenta #' + c.id;
                if (c.mozo && c.mozo.nombre) {
                    label += ' · ' + c.mozo.nombre;
                }
                if (esActiva) {
                    label += ' ★';
                }
                btn.textContent = label;
                btn.addEventListener('click', function () {
                    void cargarCuenta(c.id);
                });
                col.appendChild(btn);
                panel.appendChild(col);
            });
        } catch (e) {
            panel.innerHTML = '<div class="col-12"><span class="text-danger small">' + e.message + '</span></div>';
        }
    }

    async function continuarTrasCerrarCuentas(cerradas) {
        cuenta = null;
        mozoActual = null;
        renderMozoBadge();
        renderCuenta();
        await cargarCuentasActivas();
        const panel = document.getElementById('cm-panel-cuentas');
        const quedan = panel && panel.querySelector('button');
        if (quedan) {
            desbloquearPos();
            toast((cerradas > 1 ? cerradas + ' cuentas cerradas.' : 'Cuenta cerrada.') + ' Seleccione otra cuenta o abra una nueva.', 'success');
            return;
        }
        toast(cerradas > 1 ? 'Se cerraron ' + cerradas + ' cuentas.' : 'Cuenta cerrada.', 'success');
        mostrarLoginMozo();
    }

    async function cerrarCuenta() {
        if (!cuenta || !cuenta.id) {
            return;
        }
        if (!confirm('¿Cerrar cuenta #' + cuenta.id + ' sin facturar?')) {
            return;
        }
        try {
            await api('POST', '/cuenta/' + cuenta.id + '/cerrar');
            await continuarTrasCerrarCuentas(1);
        } catch (e) {
            toast(e.message, 'error');
        }
    }

    async function cerrarTodasCuentas() {
        if (!confirm('¿Cerrar TODAS las cuentas abiertas de esta terminal sin facturar?')) {
            return;
        }
        try {
            const data = await api('POST', '/cuentas-activas/cerrar-todas');
            await continuarTrasCerrarCuentas(Number(data.cerradas) || 0);
        } catch (e) {
            toast(e.message, 'error');
        }
    }

    async function cargarCuenta(id) {
        const data = await api('GET', '/cuenta/' + id);
        cuenta = data.cuenta;
        if (cuenta && cuenta.mozo) {
            mozoActual = cuenta.mozo;
            renderMozoBadge();
        }
        renderCuenta();
        await cargarCuentasActivas();
        setTimeout(focusSkuInput, 80);
    }

    async function loginMozo() {
        const codigo = ($('#cm-login-codigomozo').val() || '').trim();
        const clave = ($('#cm-login-clave-mozo').val() || '').trim();
        if (!codigo || !clave) {
            $('#cm-login-error').removeClass('d-none').text('Indique mozo (código o lupa) y clave.');
            return;
        }
        try {
            const data = await api('POST', '/autenticar-mozo', {
                codigo: codigo,
                clave: clave,
                forzar_nueva_cuenta: cmLoginModoNuevaCuenta,
            });
            cmLoginModoNuevaCuenta = false;
            mozoActual = data.mozo;
            cuenta = data.cuenta;
            renderMozoBadge();
            renderCuenta();
            $('#modal-cm-login-mozo').modal('hide');
            desbloquearPos();
            await cargarCuentasActivas();
            await cargarDescuentoPrefijado();
            setTimeout(focusSkuInput, 200);
        } catch (e) {
            cmLoginModoNuevaCuenta = false;
            $('#cm-login-error').removeClass('d-none').text(e.message);
        }
    }

    async function cargarDescuentoPrefijado() {
        const data = await api('GET', '/descuento-prefijado');
        if (!data.descuento || !data.descuento.id) {
            throw new Error('No se pudo cargar el descuento prefijado de canje marketing.');
        }
        $('#descuento_id').val(data.descuento.id);
        $('#cm-codigodescuento').val(data.descuento.codigo);
        $('#cm-nombredescuento').val(data.descuento.nombre);
        return data.descuento;
    }

    async function patchCantidadLineaCm(lineaId, nuevaCantidad) {
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
            cuenta = data.cuenta;
            renderCuenta();
            focusSkuInput();
        } catch (e) {
            toast(e.message, 'error');
        }
    }

    function mostrarModalCantidadCm() {
        return new Promise(function (resolve, reject) {
            cmModalCantidadResolver = { resolve: resolve, reject: reject };
            $('#cm-modal-cantidad-valor').val('1');
            $('#modal-cm-cantidad').modal('show');
        });
    }

    function confirmarModalCantidadCm() {
        const q = parseFloat($('#cm-modal-cantidad-valor').val()) || 0;
        if (!(q > 0)) {
            toast('Cantidad inválida.', 'warning');
            return;
        }
        $('#modal-cm-cantidad').modal('hide');
        if (cmModalCantidadResolver) {
            const fn = cmModalCantidadResolver.resolve;
            cmModalCantidadResolver = null;
            fn(q);
        }
    }

    function cancelarModalCantidadCm() {
        if (cmModalCantidadResolver) {
            const fn = cmModalCantidadResolver.reject;
            cmModalCantidadResolver = null;
            fn(new Error('Operación cancelada.'));
        }
    }

    async function resolverSkuPorTabYEnfocarAgregarCm() {
        const art = await resolverArticuloDesdeSkuInput();
        if (!art) {
            return;
        }
        const btn = document.getElementById('cm-btn-agregar-sku');
        if (btn && typeof btn.focus === 'function') {
            btn.focus();
        }
    }

    async function resolverArticuloDesdeSkuInput() {
        const sku = resolverSkuCompleto($('#cm-sku-input').val());
        if (!sku) {
            toast('Ingrese el código del artículo.', 'warning');
            return null;
        }
        if (!skuPermitidoCm(sku)) {
            toast('Código inválido: use ' + mensajeSkuCatalogoCm() + '.', 'warning');
            focusSkuInput();
            return null;
        }
        try {
            const artResp = await api('GET', '/articulo-catalogo-por-sku?sku=' + encodeURIComponent(sku));
            if (!artResp.articulo || !artResp.articulo.id) {
                toast('Artículo no encontrado.', 'warning');
                limpiarCampoCargaArticuloCm();
                focusSkuInput();
                return null;
            }
            $('#cm-articulo_id').val(artResp.articulo.id);
            $('#cm-articulo-descripcion').val(artResp.articulo.descripcion || '');
            return artResp.articulo;
        } catch (e) {
            toast(e.message, 'error');
            limpiarCampoCargaArticuloCm();
            focusSkuInput();
            return null;
        }
    }

    async function iniciarAltaArticuloCm(articulo, opciones) {
        if (!articulo || !articulo.id) {
            return;
        }
        const pedirCantidad = !!(opciones && opciones.pedirCantidad);
        let opcionales = {};
        try {
            const opResp = await api('GET', '/opcionales-articulo/' + articulo.id);
            if (opResp.grupos && opResp.grupos.length) {
                opcionales = await new Promise(function (resolve) {
                    cmPendingAltaPostOpcionales = {
                        resolve: resolve,
                        pedirCantidad: pedirCantidad,
                        articulo: articulo,
                    };
                    pedirOpcionales(opResp.grupos);
                });
                cmPendingAltaPostOpcionales = null;
            }
        } catch (e) { /* sin opcionales */ }

        if (pedirCantidad) {
            try {
                const q = await mostrarModalCantidadCm();
                await agregarLinea(articulo.id, q, opcionales);
            } catch (e) {
                if (e && e.message !== 'Operación cancelada.') {
                    toast(e.message, 'error');
                }
            }
            return;
        }
        await agregarLinea(articulo.id, 1, opcionales);
    }

    function pedirOpcionales(grupos) {
        let html = '';
        (grupos || []).forEach(function (g, gi) {
            html += '<div class="mb-2"><strong>' + (g.nombre || g.grupo || ('Grupo ' + (gi + 1))) + '</strong>';
            (g.opciones || g.items || []).forEach(function (op) {
                const id = op.id || op.articulo_id;
                html += '<div class="custom-control custom-radio"><input class="custom-control-input cm-op-radio" type="radio" name="cm_op_' + gi + '" id="cm_op_' + gi + '_' + id + '" value="' + id + '" data-grupo="' + (g.grupo_id || gi) + '">';
                html += '<label class="custom-control-label" for="cm_op_' + gi + '_' + id + '">' + (op.nombre || op.descripcion || id) + '</label></div>';
            });
            html += '</div>';
        });
        $('#cm-opcionales-body').html(html);
        $('#modal-cm-opcionales').modal('show');
    }

    async function agregarLinea(articuloId, cantidad, opcionales) {
        if (!cuenta || !cuenta.id) {
            toast('Abra una cuenta con el login de mozo.', 'warning');
            return;
        }
        const payload = { articulo_id: articuloId, cantidad: cantidad, opcionales: opcionales || {} };
        try {
            const data = await api('POST', '/cuenta/' + cuenta.id + '/linea', payload);
            cuenta = data.cuenta || cuenta;
            if (data.cuenta) {
                renderCuenta();
            } else if (data.linea) {
                if (!Array.isArray(cuenta.lineas)) {
                    cuenta.lineas = lineasDesdeCuenta(cuenta);
                }
                cuenta.lineas.push(data.linea);
                renderCuenta();
            } else {
                await cargarCuenta(cuenta.id);
            }
            limpiarCampoCargaArticuloCm();
            focusSkuInput();
        } catch (e) {
            toast(e.message, 'error');
        }
    }

    async function procesarSku(opciones) {
        const pedirCantidad = !!(opciones && opciones.pedirCantidad);
        const art = await resolverArticuloDesdeSkuInput();
        if (!art) {
            return;
        }
        await iniciarAltaArticuloCm(art, { pedirCantidad: pedirCantidad });
    }

    async function guardarCabeceraFacturacion() {
        await cargarDescuentoPrefijado();
        const descId = parseInt($('#descuento_id').val(), 10) || null;
        const vipId = parseInt($('#cliente_vip_id').val(), 10) || null;
        if (!descId) { throw new Error('Falta descuento prefijado.'); }
        if (!vipId) { throw new Error('Debe indicar el cliente del descuento (VIP).'); }
        const data = await api('PATCH', '/cuenta/' + cuenta.id, {
            descuento_gastronomia_id: descId,
            cliente_vip_gastronomia_id: vipId,
        });
        cuenta = data.cuenta;
    }

    async function emitirFactura() {
        if (emitiendo || !cuenta) { return; }
        emitiendo = true;
        try {
            setFacturacionLoadingCm(true, 'Validando cuenta…');
            await guardarCabeceraFacturacion();
            const val = await api('POST', '/validar-emision', {
                cuenta_id: cuenta.id,
                moneda_id: G.monedaFacturaId || 1,
            });
            if (!val.ok) {
                throw new Error((val.errores || [val.error]).join(' '));
            }
            iniciarRotacionMensajesCm(mensajesProcesoEmisionCm());
            const res = await api('POST', '/emitir-factura', {
                cuenta_id: cuenta.id,
                moneda_id: G.monedaFacturaId || 1,
            });
            detenerRotacionMensajesCm();
            mostrarResultadoEmisionCanje(res);
        } catch (e) {
            toast(e.message, 'error');
        } finally {
            setFacturacionLoadingCm(false);
            emitiendo = false;
        }
    }

    function panelDescuentoVipEnModalF8() {
        const bloque = document.getElementById('cm-descuento-vip-movable');
        const slotModal = document.getElementById('cm-f8-slot-descuento-vip');
        return !!(bloque && slotModal && slotModal.contains(bloque));
    }

    function actualizarAvisoDescuentoVipEnTarjeta() {
        const aviso = document.getElementById('cm-descuento-vip-aviso-f8');
        if (aviso) {
            aviso.classList.toggle('d-none', !panelDescuentoVipEnModalF8());
        }
    }

    function moverPanelDescuentoVipAlModalF8() {
        const bloque = document.getElementById('cm-descuento-vip-movable');
        const slotModal = document.getElementById('cm-f8-slot-descuento-vip');
        if (bloque && slotModal) {
            slotModal.appendChild(bloque);
        }
        actualizarAvisoDescuentoVipEnTarjeta();
    }

    function restaurarPanelDescuentoVipEnTarjeta() {
        const bloque = document.getElementById('cm-descuento-vip-movable');
        const slotOriginal = document.getElementById('cm-descuento-vip-slot-original');
        if (bloque && slotOriginal) {
            slotOriginal.appendChild(bloque);
        }
        actualizarAvisoDescuentoVipEnTarjeta();
    }

    function focusCampoVipDescuento() {
        const el = document.getElementById('cliente_vip_numeroid');
        if (el && typeof el.focus === 'function') {
            el.focus();
            if (typeof el.select === 'function') {
                el.select();
            }
        }
    }

    function focusCampoVipDescuentoConReintento() {
        [0, 50, 120, 250].forEach(function (ms) {
            window.setTimeout(focusCampoVipDescuento, ms);
        });
    }

    function otroModalConsultaCmAbierto(target) {
        if (target && target.closest) {
            const otro = target.closest('.modal.show');
            if (otro && otro.id && otro.id !== 'modal-cm-f8-descuento') {
                return true;
            }
        }
        return document.querySelectorAll('.modal.show').length > 1;
    }

    function activarEnterDocumentoModalF8() {
        if (cmDocumentEnterModalF8Activo) {
            return;
        }
        cmDocumentEnterModalF8Activo = true;
        document.addEventListener('keydown', onDocumentEnterModalF8, true);
    }

    function desactivarEnterDocumentoModalF8() {
        if (!cmDocumentEnterModalF8Activo) {
            return;
        }
        cmDocumentEnterModalF8Activo = false;
        document.removeEventListener('keydown', onDocumentEnterModalF8, true);
    }

    function onDocumentEnterModalF8(e) {
        if (!modalCmF8Abierto() || !esTeclaEnter(e) || e.ctrlKey || e.metaKey || e.altKey || e.shiftKey) {
            return;
        }
        if (otroModalConsultaCmAbierto(e.target)) {
            return;
        }
        const t = e.target;
        if (!t || t.tagName === 'TEXTAREA') {
            return;
        }
        if (t.getAttribute && t.getAttribute('data-dismiss') === 'modal') {
            return;
        }
        if (t.classList && t.classList.contains('close')) {
            return;
        }

        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();

        if (t.id === 'cliente_vip_numeroid' || t.id === 'cliente_vip_documento') {
            void onEnterCampoVipDescuento(t);
            return;
        }

        void confirmarModalF8DesdeEnterGlobal(t);
    }

    async function confirmarModalF8DesdeEnterGlobal(target) {
        const vipNum = document.getElementById('cliente_vip_numeroid');
        const vipDoc = document.getElementById('cliente_vip_documento');
        const vipId = parseInt($('#cliente_vip_id').val(), 10) || 0;
        const vipNombre = ($('#cliente_vip_nombre').val() || '').trim();
        if (vipId > 0 && vipNombre !== '') {
            await confirmarModalF8();
            return;
        }
        const docVal = vipDoc ? String(vipDoc.value || '').trim() : '';
        const numVal = vipNum ? String(vipNum.value || '').trim() : '';
        if (docVal || numVal) {
            await onEnterCampoVipDescuento(docVal ? vipDoc : vipNum);
            return;
        }
        if (target && target.id === 'cm-btn-f8' && typeof target.blur === 'function') {
            target.blur();
        }
        focusCampoVipDescuento();
        toast('Indique el cliente del descuento (VIP).', 'warning');
    }

    async function confirmarModalF8() {
        try {
            await cargarDescuentoPrefijado();
        } catch (e) {
            toast(e.message, 'error');
            return;
        }
        const vipId = parseInt($('#cliente_vip_id').val(), 10) || 0;
        if (!vipId) {
            toast('Indique el cliente del descuento (VIP).', 'warning');
            focusCampoVipDescuento();
            return;
        }
        await emitirFactura();
    }

    async function abrirModalF8() {
        if (!cuenta || !lineasDesdeCuenta(cuenta).length) {
            toast('Cargue al menos un artículo.', 'warning');
            return;
        }
        try {
            await cargarDescuentoPrefijado();
        } catch (e) {
            toast(e.message, 'error');
            return;
        }
        moverPanelDescuentoVipAlModalF8();
        $('#modal-cm-f8-descuento').modal('show');
    }

    function esTeclaEnter(e) {
        return e && (e.key === 'Enter' || e.keyCode === 13);
    }

    function origenCampoVip(el) {
        if (!el || !el.id) {
            return 'auto';
        }
        if (el.id === 'cliente_vip_documento') {
            return 'documento';
        }
        if (el.id === 'cliente_vip_numeroid') {
            return 'numeroid';
        }
        return 'auto';
    }

    async function onEnterCampoVipDescuento(el) {
        const origen = origenCampoVip(el);
        const vipId = parseInt($('#cliente_vip_id').val(), 10) || 0;
        if (vipId > 0 && ($('#cliente_vip_nombre').val() || '').trim() !== '') {
            if (panelDescuentoVipEnModalF8()) {
                await confirmarModalF8();
            }
            return;
        }
        const ok = await cargarVipPorCampo(origen);
        if (!ok) {
            return;
        }
        await guardarClienteVipDescuentoCuenta(true);
        if (panelDescuentoVipEnModalF8()) {
            await confirmarModalF8();
        }
    }

    function bindEnterCampoVipDescuento(el) {
        if (!el || el.dataset.cmEnterVipBound === '1') {
            return;
        }
        el.dataset.cmEnterVipBound = '1';
        el.addEventListener('keydown', function (e) {
            if (!esTeclaEnter(e)) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            void onEnterCampoVipDescuento(el);
        }, true);
    }

    function codigoVipSegunOrigen(origen) {
        if (origen === 'documento') {
            return ($('#cliente_vip_documento').val() || '').trim();
        }
        if (origen === 'numeroid') {
            return ($('#cliente_vip_numeroid').val() || '').trim();
        }
        const doc = ($('#cliente_vip_documento').val() || '').trim();
        const num = ($('#cliente_vip_numeroid').val() || '').trim();
        return doc || num;
    }

    function limpiarVipTrasEdicionCampo(origen) {
        $('#cliente_vip_id').val('');
        $('#cliente_vip_nombre').val('');
        if (origen === 'documento') {
            $('#cliente_vip_numeroid').val('');
        } else if (origen === 'numeroid') {
            $('#cliente_vip_documento').val('');
        } else {
            $('#cliente_vip_numeroid').val('');
            $('#cliente_vip_documento').val('');
        }
        ocultarAvisoVip();
    }

    async function cargarVipPorCampo(origen) {
        origen = origen || 'auto';
        const cod = codigoVipSegunOrigen(origen);
        if (!cod) {
            ocultarAvisoVip();
            return false;
        }
        try {
            const data = await api('GET', '/cliente-vip/leer/' + encodeURIComponent(cod));
            if (!data.cliente_vip) {
                throw new Error('Cliente VIP no encontrado.');
            }
            ocultarAvisoVip();
            if (typeof window.cmAplicarClienteVip === 'function') {
                window.cmAplicarClienteVip(data.cliente_vip);
            } else {
                pintarClienteVipEnPantalla(data.cliente_vip);
            }
            if (cuenta) {
                cuenta.cliente_vip = data.cliente_vip;
                cuenta.cliente_vip_gastronomia_id = data.cliente_vip.id;
            }
            return true;
        } catch (e) {
            const msg = 'No existe cliente VIP con el DNI/código ingresado («' + cod + '»).';
            mostrarAvisoVip(msg);
            toast(msg, 'warning');
            $('#cliente_vip_id').val('');
            $('#cliente_vip_nombre').val('');
            if (origen === 'documento') {
                $('#cliente_vip_numeroid').val('');
                $('#cliente_vip_documento').val(cod);
            } else if (origen === 'numeroid') {
                $('#cliente_vip_documento').val('');
                $('#cliente_vip_numeroid').val(cod);
            } else {
                limpiarClienteVipDescuento();
            }
            if (cuenta && cuenta.id) {
                cuenta.cliente_vip = null;
                cuenta.cliente_vip_gastronomia_id = null;
                await guardarClienteVipDescuentoCuenta(true);
                if (origen === 'documento') {
                    $('#cliente_vip_documento').val(cod);
                } else if (origen === 'numeroid') {
                    $('#cliente_vip_numeroid').val(cod);
                }
            }
            return false;
        }
    }

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

    function resetModalWigos() {
        if (cmWigosInputTimer) {
            clearTimeout(cmWigosInputTimer);
            cmWigosInputTimer = null;
        }
        cmWigosProcesando = false;
        cmWigosUltimoTrackdata = '';
        cmWigosVipPendiente = null;
        $('#cm-wigos-trackdata').val('');
        $('#cm-wigos-error').addClass('d-none').text('');
        $('#cm-wigos-preview').addClass('d-none');
        $('#cm-wigos-prev-nombre').text('—');
        $('#cm-wigos-prev-documento').text('—');
        $('#cm-wigos-prev-codigo').text('—');
        $('#cm-wigos-aplicar').prop('disabled', true).text('Aplicar al cliente VIP');
    }

    function actualizarBotonConfirmarWigos() {
        const btn = document.getElementById('cm-wigos-aplicar');
        if (!btn) {
            return;
        }
        btn.disabled = !cmWigosVipPendiente;
    }

    function programarLecturaWigosTrasInput() {
        const inp = document.getElementById('cm-wigos-trackdata');
        if (!inp || cmWigosProcesando) {
            return;
        }
        if (cmWigosInputTimer) {
            clearTimeout(cmWigosInputTimer);
        }
        const val = normalizarTrackdataLector(inp.value || '');
        if (val.length < 4) {
            return;
        }
        cmWigosInputTimer = setTimeout(function () {
            cmWigosInputTimer = null;
            void validarWigosTarjeta({ silencioso: true });
        }, 150);
    }

    function mostrarPreviewWigos(vip) {
        if (!vip) {
            $('#cm-wigos-preview').addClass('d-none');
            return;
        }
        const nombre = vip.nombre_completo || [vip.apellido, vip.nombre].filter(Boolean).join(' ').trim() || '—';
        $('#cm-wigos-prev-nombre').text(nombre);
        $('#cm-wigos-prev-documento').text(vip.nrodocumento || '—');
        $('#cm-wigos-prev-codigo').text(vip.numeroid != null ? String(vip.numeroid) : '—');
        $('#cm-wigos-preview').removeClass('d-none');
    }

    async function validarWigosTarjeta(opciones) {
        const opts = opciones || {};
        if (cmWigosProcesando) {
            return false;
        }
        const inp = document.getElementById('cm-wigos-trackdata');
        const track = normalizarTrackdataLector(inp ? inp.value : ($('#cm-wigos-trackdata').val() || ''));
        if (inp && inp.value !== track) {
            inp.value = track;
        }
        if (!track) {
            const msg = 'Pase la tarjeta o ingrese el trackdata.';
            cmWigosVipPendiente = null;
            actualizarBotonConfirmarWigos();
            $('#cm-wigos-error').removeClass('d-none').text(msg);
            if (!opts.silencioso) {
                toast(msg, 'warning');
            }
            return false;
        }
        if (track === cmWigosUltimoTrackdata && cmWigosVipPendiente) {
            return true;
        }
        cmWigosProcesando = true;
        cmWigosVipPendiente = null;
        actualizarBotonConfirmarWigos();
        $('#cm-wigos-error').addClass('d-none').text('');
        $('#cm-wigos-aplicar').prop('disabled', true).text('Leyendo…');
        try {
            const data = await api('POST', '/cliente-vip/wigos', { trackdata: track });
            cmWigosUltimoTrackdata = track;
            cmWigosVipPendiente = data.cliente_vip || null;
            mostrarPreviewWigos(cmWigosVipPendiente);
            actualizarBotonConfirmarWigos();
            if (!opts.silencioso) {
                toast('Tarjeta validada. Confirme con Enter o «Aplicar al cliente VIP».', 'info');
            }
            return true;
        } catch (e) {
            const msg = e.message || 'No se pudo leer la tarjeta Wigos.';
            $('#cm-wigos-error').removeClass('d-none').text(msg);
            if (!opts.silencioso) {
                toast(msg, 'error');
            }
            return false;
        } finally {
            cmWigosProcesando = false;
            $('#cm-wigos-aplicar').text('Aplicar al cliente VIP');
            actualizarBotonConfirmarWigos();
        }
    }

    async function confirmarWigosVip() {
        if (!cmWigosVipPendiente) {
            const ok = await validarWigosTarjeta();
            if (!ok || !cmWigosVipPendiente) {
                return;
            }
        }
        const vip = cmWigosVipPendiente;
        ocultarAvisoVip();
        if (typeof window.cmAplicarClienteVip === 'function') {
            window.cmAplicarClienteVip(vip);
        } else {
            pintarClienteVipEnPantalla(vip);
        }
        if (cuenta && cuenta.id) {
            await guardarClienteVipDescuentoCuenta(true);
        }
        toast('Cliente VIP del descuento cargado desde tarjeta Wigos.', 'success');
        $('#modal-cm-wigos-vip').modal('hide');
        focusSkuInput();
    }

    function abrirModalWigos() {
        if (!G.wigosHabilitado) {
            toast('Consulta de tarjeta Wigos no habilitada en este terminal.', 'warning');
            return;
        }
        resetModalWigos();
        $('#modal-cm-wigos-vip').modal('show');
    }

    function wireEvents() {
        $('#cm-login-confirmar').on('click', function () { void loginMozo(); });
        const codMozoLogin = document.getElementById('cm-login-codigomozo');
        if (codMozoLogin) {
            codMozoLogin.addEventListener('keydown', onKeydownCodigoMozoLogin, true);
            codMozoLogin.addEventListener('blur', function () {
                const cod = String(codMozoLogin.value || '').trim();
                if (!cod) {
                    return;
                }
                if (($('#cm-login-nombremozo').val() || '').trim() !== '') {
                    return;
                }
                void validarCodigoMozoLogin({ requerirCodigo: false });
            });
        }
        const idMozoLogin = document.getElementById('cm-login-mozo_gastronomia_id');
        if (idMozoLogin) {
            idMozoLogin.addEventListener('keydown', onKeydownIdMozoLogin, true);
            idMozoLogin.addEventListener('blur', function () {
                const idVal = String(idMozoLogin.value || '').trim();
                if (!idVal) {
                    return;
                }
                if (($('#cm-login-nombremozo').val() || '').trim() !== '') {
                    return;
                }
                void validarIdMozoLogin({ requerirId: false });
            });
        }
        const claveMozoLogin = document.getElementById('cm-login-clave-mozo');
        if (claveMozoLogin) {
            claveMozoLogin.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.keyCode === 13) {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    void loginMozo();
                }
            }, true);
        }
        $('#cm-login-cerrar').on('click', function () {
            $('#modal-cm-login-mozo').modal('hide');
        });
        $('#cm-btn-cambiar-mozo').on('click', function () {
            cmLoginModoNuevaCuenta = false;
            mostrarLoginMozo();
        });

        $('#cm-btn-agregar-sku, #cm-sku-input').on('keydown', function (e) {
            if (e.target.id !== 'cm-sku-input') {
                return;
            }
            if (esAtajoCantidadEnSkuCm(e)) {
                e.preventDefault();
                void procesarSku({ pedirCantidad: true });
                return;
            }
            if (e.key === 'Enter' || e.keyCode === 13) {
                e.preventDefault();
                void procesarSku({ pedirCantidad: false });
                return;
            }
            if (e.key === 'Tab' && !e.shiftKey) {
                e.preventDefault();
                void resolverSkuPorTabYEnfocarAgregarCm();
            }
        });
        $('#cm-btn-agregar-sku').on('click', function () { void procesarSku({ pedirCantidad: true }); });
        $('#cm-btn-agregar-cantidad').on('click', function () { void procesarSku({ pedirCantidad: true }); });
        $('#cm-modal-cantidad-ok').on('click', function () { confirmarModalCantidadCm(); });
        $('#modal-cm-cantidad').on('hidden.bs.modal.cmCant', function () {
            if (cmModalCantidadResolver) {
                cancelarModalCantidadCm();
            }
        });
        $('#modal-cm-cantidad').on('shown.bs.modal.cmCant', function () {
            const el = document.getElementById('cm-modal-cantidad-valor');
            if (el) {
                el.focus();
                if (typeof el.select === 'function') {
                    el.select();
                }
            }
        });
        $('#modal-cm-cantidad').on('keydown.cmCant', function (e) {
            if (e.key === 'Enter' || e.keyCode === 13) {
                e.preventDefault();
                confirmarModalCantidadCm();
            }
        });

        $(document).on('click', '.cm-del-linea', function () {
            const lid = $(this).data('id');
            void api('DELETE', '/cuenta/' + cuenta.id + '/linea/' + lid).then(function (d) {
                cuenta = d.cuenta;
                renderCuenta();
                focusSkuInput();
            }).catch(function (e) { toast(e.message, 'error'); });
        });

        $('#cm-btn-nueva-cuenta').on('click', function () {
            cmLoginModoNuevaCuenta = true;
            mostrarLoginMozo();
        });
        $('#cm-btn-cerrar-cuenta').on('click', function () { void cerrarCuenta(); });
        $('#cm-btn-cerrar-todas-cuentas').on('click', function () { void cerrarTodasCuentas(); });
        $('#cm-btn-f8').on('click', function () {
            if (typeof this.blur === 'function') {
                this.blur();
            }
            void abrirModalF8();
        });
        $('#modal-cm-f8-confirmar').on('click', function () { void confirmarModalF8(); });

        $('#cliente_vip_documento').on('input', function () {
            limpiarVipTrasEdicionCampo('documento');
        });
        $('#cliente_vip_numeroid').on('input', function () {
            limpiarVipTrasEdicionCampo('numeroid');
        });
        bindEnterCampoVipDescuento(document.getElementById('cliente_vip_numeroid'));
        bindEnterCampoVipDescuento(document.getElementById('cliente_vip_documento'));
        $('#cliente_vip_documento').on('blur', function () {
            const doc = ($(this).val() || '').trim();
            if (doc.length >= 6) {
                void cargarVipPorCampo('documento');
            }
        });
        $('#cliente_vip_numeroid').on('blur', function () {
            const num = ($(this).val() || '').trim();
            if (num.length >= 1) {
                void cargarVipPorCampo('numeroid');
            }
        });
        $('#consultaclientevipModal').on('hidden.bs.modal.cmVipDescuento', function () {
            setTimeout(function () { void guardarClienteVipDescuentoCuenta(true); }, 80);
        });

        $(document).on('click', '#cm-btn-abrir-wigos', function () { abrirModalWigos(); });
        $(document).on('click', '#cm-wigos-aplicar', function () { void confirmarWigosVip(); });
        $(document).on('input', '#cm-wigos-trackdata', function () {
            cmWigosVipPendiente = null;
            cmWigosUltimoTrackdata = '';
            actualizarBotonConfirmarWigos();
            programarLecturaWigosTrasInput();
        });
        $(document).on('keydown', '#cm-wigos-trackdata', function (e) {
            if (e.key === 'Enter' || e.keyCode === 13) {
                e.preventDefault();
                e.stopPropagation();
                if (cmWigosInputTimer) {
                    clearTimeout(cmWigosInputTimer);
                    cmWigosInputTimer = null;
                }
                if (cmWigosVipPendiente) {
                    void confirmarWigosVip();
                } else {
                    void validarWigosTarjeta();
                }
            }
        });
        $(document).on('keydown.cmWigosConfirm', '#modal-cm-wigos-vip', function (e) {
            if (e.key !== 'Enter' || e.target.id === 'cm-wigos-trackdata') {
                return;
            }
            if (cmWigosVipPendiente) {
                e.preventDefault();
                void confirmarWigosVip();
            }
        });
        $(document).on('shown.bs.modal', '#modal-cm-wigos-vip', function () {
            const inp = document.getElementById('cm-wigos-trackdata');
            if (inp) {
                inp.focus();
            }
        });
        $(document).on('hidden.bs.modal', '#modal-cm-wigos-vip', function () {
            if (!cmWigosProcesando) {
                resetModalWigos();
            }
        });

        $('#cm-opcionales-ok').on('click', function () {
            const sel = {};
            $('#cm-opcionales-body .cm-op-radio:checked').each(function () {
                sel[$(this).data('grupo')] = parseInt($(this).val(), 10);
            });
            if (cmPendingAltaPostOpcionales && typeof cmPendingAltaPostOpcionales.resolve === 'function') {
                const ctx = cmPendingAltaPostOpcionales;
                cmPendingAltaPostOpcionales = null;
                $('#modal-cm-opcionales').modal('hide');
                ctx.resolve(sel);
                return;
            }
            if (!pendingOpcionalesResolver) { return; }
            $('#modal-cm-opcionales').modal('hide');
            pendingOpcionalesResolver.resolve(sel);
            pendingOpcionalesResolver = null;
        });

        $(document).on('keydown.cmF8', function (e) {
            if (e.key === 'F8' || e.keyCode === 119) {
                if ($('.modal.show').length) { return; }
                e.preventDefault();
                void abrirModalF8();
            }
        });
    }

    $(function () {
        if (!G.tieneCfgPv) { return; }
        if (typeof activa_eventos_consultamozo === 'function') {
            activa_eventos_consultamozo();
        }
        if (typeof activa_eventos_consultaarticulo === 'function') {
            activa_eventos_consultaarticulo();
        }
        window.onArticuloSeleccionado = function (dataArticulo) {
            if (!G.tieneCfgPv) {
                return;
            }
            aplicarArticuloEnCampoCargaCm(dataArticulo);
        };
        window.cmOnClienteVipElegido = function () {
            void guardarClienteVipDescuentoCuenta(true);
        };
        wireEvents();
        document.addEventListener('input', function (e) {
            const t = e.target;
            if (!t || !t.classList || !t.classList.contains('gastro-sku-sufijo')) {
                return;
            }
            const d = String(t.value || '').replace(/\D/g, '');
            if (t.value !== d) {
                t.value = d;
            }
        });
        $(document)
            .off('hidden.bs.modal.cmSync')
            .on('hidden.bs.modal.cmSync', '.modal', function () {
                window.setTimeout(sincronizarEstadoModalesCm, 0);
            });
        wireApiladoConsultasSobreCmF8();
        if ($('#consultaclientevipModal').length && !$('#consultaclientevipModal').parent().is('body')) {
            $('#consultaclientevipModal').appendTo('body');
        }
        if ($('#modal-cm-f8-descuento').length && !$('#modal-cm-f8-descuento').parent().is('body')) {
            $('#modal-cm-f8-descuento').appendTo('body');
        }
        $('#modal-cm-f8-descuento')
            .off('shown.bs.modal.cmF8 hidden.bs.modal.cmF8')
            .on('shown.bs.modal.cmF8', function () {
                activarEnterDocumentoModalF8();
                focusCampoVipDescuentoConReintento();
            })
            .on('hidden.bs.modal.cmF8', function () {
                desactivarEnterDocumentoModalF8();
                if (!emitiendo) {
                    restaurarPanelDescuentoVipEnTarjeta();
                }
                window.setTimeout(sincronizarEstadoModalesCm, 0);
            });
        if ($('#modal-cm-login-mozo').length && !$('#modal-cm-login-mozo').parent().is('body')) {
            $('#modal-cm-login-mozo').appendTo('body');
        }
        if ($('#modal-cm-wigos-vip').length && !$('#modal-cm-wigos-vip').parent().is('body')) {
            $('#modal-cm-wigos-vip').appendTo('body');
        }
        $('#modal-cm-login-mozo')
            .off('shown.bs.modal.cmLogin')
            .on('shown.bs.modal.cmLogin', function () {
                focusCodigoLoginMozo();
            });
        $('#consultamozoModal')
            .off('hidden.bs.modal.cmLoginMozo')
            .on('hidden.bs.modal.cmLoginMozo', function () {
                if ($('#modal-cm-login-mozo').hasClass('show')) {
                    setTimeout(focusClaveLoginMozo, 50);
                }
            });
        $('#modal-cm-login-mozo').on('hidden.bs.modal', function () {
            if (!mozoActual) {
                desbloquearPos();
            }
        });
        mostrarLoginMozo();
    });
}(jQuery));
