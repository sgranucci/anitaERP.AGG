(function () {
    'use strict';

    if (window.__anitaKardexMovimientosArticuloInit) {
        return;
    }
    window.__anitaKardexMovimientosArticuloInit = true;

    var pendingKardexArticuloId = 0;
    var pendingKardexVolverUrl = '';
    var pendingSaldosArticuloId = 0;
    var pendingSaldosVolverUrl = '';
    var kardexModalConfirmarConEnter = false;

    function carpetaBase() {
        return typeof window.carpetaBase !== 'undefined' ? window.carpetaBase : '';
    }

    function enFormularioRecuento() {
        return !!document.getElementById('form-recuento');
    }

    function enFormularioArticulo() {
        return !!document.getElementById('form-general') && !!document.getElementById('articulo_id');
    }

    function enPaginaMovimientosArticulo() {
        return !!document.getElementById('filtro-deposito-movimientos-articulo');
    }

    function modalKardexDisponible() {
        return !!document.getElementById('modalKardexDeposito');
    }

    function urlIndexMovimientosArticulo() {
        var el = document.getElementById('recuento-movimientos-articulo-url');
        return el ? el.value : (carpetaBase() + '/stock/recuento/movimientos-articulo');
    }

    function urlApiSaldosArticulo() {
        var el = document.getElementById('articulo-saldos-deposito-url');
        return el ? el.value : (carpetaBase() + '/stock/articulo/api/saldos-deposito');
    }

    function empresaIdParaSaldosKardex() {
        if (typeof window.obtenerEmpresaIdFiltroSaldosKardex === 'function') {
            return parseInt(window.obtenerEmpresaIdFiltroSaldosKardex(), 10) || 0;
        }

        var elPagina = document.getElementById('movimientos-articulo-empresa-id');
        if (elPagina) {
            return parseInt(elPagina.value, 10) || 0;
        }

        return 0;
    }

    function empresaIdParaUrlKardex() {
        return empresaIdParaSaldosKardex();
    }

    function textoInfoArticulo(sku, descripcion, articuloId, unidadMedida) {
        var info = (sku || '').trim();
        if ((descripcion || '').trim()) {
            info += (info ? ' — ' : '') + descripcion.trim();
        }
        var um = (unidadMedida || '').trim();
        if (um) {
            info += (info ? ' · ' : '') + 'UM: ' + um;
        }
        return info || ('Artículo #' + (parseInt(articuloId, 10) || 0));
    }

    function actualizarEtiquetasUnidadMedidaSaldos($panel, data) {
        if (!$panel || !$panel.length) {
            return;
        }

        var um = data && data.articulo ? String(data.articulo.unidad_medida || '').trim() : '';
        var $hint = $panel.find('.saldos-articulo-um-hint');
        var $thSaldo = $panel.find('.saldos-th-saldo');

        if (um) {
            $hint.text('(' + um + ')').removeClass('d-none');
            $thSaldo.text('Saldo (' + um + ')');
        } else {
            $hint.text('').addClass('d-none');
            $thSaldo.text('Saldo');
        }

        if (!data || !data.articulo) {
            return;
        }

        var art = data.articulo;
        var info = textoInfoArticulo(art.sku, art.descripcion, art.id, um);

        if ($panel.closest('#modalSaldosArticulo').length) {
            $('#modal-saldos-articulo-info').text(info);
        }
        if ($panel.closest('#modalKardexDeposito').length) {
            $('#modal-kardex-articulo-info').text(info);
        }
    }

    function limpiarModalesStockConsultaAlCargar() {
        if (!window.jQuery) {
            return;
        }
        window.__anitaPermitirShowModalSaldos = false;
        window.__anitaPermitirShowModalKardex = false;
        window.__anitaPermitirShowConsultadepositoModal = false;
        var modales = ['#modalKardexDeposito', '#modalSaldosArticulo'];
        if (esListadoArticulosIndexPagina()) {
            modales.unshift('#consultadepositoModal');
        }
        modales.forEach(function (selector) {
            var $m = $(selector);
            if (!$m.length) {
                return;
            }
            if ($m.hasClass('show')) {
                $m.modal('hide');
            }
            $m.removeClass('show').attr('aria-hidden', 'true').attr('inert', 'inert').css('display', 'none');
        });
        $('body').removeClass('modal-open').css('padding-right', '');
        $('.modal-backdrop').remove();
    }

    function esListadoArticulosIndexPagina() {
        return !!document.getElementById('form-filtros-articulo')
            && !document.getElementById('filtro-deposito-movimientos-articulo');
    }

    function registrarGuardModalStock($modal, flagName) {
        if (!$modal || !$modal.length || $modal.data('anitaGuardStockRegistrado')) {
            return;
        }
        $modal.data('anitaGuardStockRegistrado', true);
        $modal.on('show.bs.modal.guardStockConsulta', function (e) {
            if (window[flagName]) {
                window[flagName] = false;
                return;
            }
            e.preventDefault();
            e.stopImmediatePropagation();
            return false;
        });
    }

    function enfocarBusquedaListadoArticulos() {
        if (!document.getElementById('form-filtros-articulo')) {
            return;
        }
        var $input = $('#filtro_valor:visible').first();
        if ($input.length && !$('.modal.show').length) {
            setTimeout(function () {
                $input.trigger('focus');
            }, 0);
        }
    }

    function focoCodigoDepositoKardexModal() {
        var $cod = $('#kardex_pick_deposito_id_codigo');
        if (!$cod.length) {
            return;
        }
        setTimeout(function () {
            $cod.trigger('focus').select();
        }, 80);
    }

    function ejecutarAbrirKardexModal() {
        var depId = depositoDesdePickerKardexModal();
        if (depId <= 0) {
            notificar('Kardex', 'Seleccione un depósito o use «Todos los depósitos».');
            focoCodigoDepositoKardexModal();
            return;
        }
        $('#modalKardexDeposito').modal('hide');
        abrirUrlKardex(pendingKardexArticuloId, depId, pendingKardexVolverUrl);
    }

    function renderSaldosEnPanel($panel, data) {
        if (!$panel || !$panel.length) {
            return;
        }

        var $loading = $panel.find('.saldos-articulo-loading');
        var $error = $panel.find('.saldos-articulo-error');
        var $wrap = $panel.find('.saldos-articulo-tabla-wrap');
        var $vacio = $panel.find('.saldos-articulo-vacio');
        var $tbody = $panel.find('.saldos-articulo-tbody');
        var $total = $panel.find('.saldos-articulo-total');
        var mostrarEmpresa = !!(data && data.mostrar_empresa);

        $panel.find('.saldos-col-empresa, .saldos-footer-empresa').toggleClass('d-none', !mostrarEmpresa);

        $loading.addClass('d-none');
        $error.addClass('d-none').text('');

        var filas = data && Array.isArray(data.filas) ? data.filas : [];
        $tbody.empty();

        if (filas.length === 0) {
            $wrap.addClass('d-none');
            $vacio.removeClass('d-none');
            $total.text((data && data.total_fmt) ? data.total_fmt : '0');
            actualizarEtiquetasUnidadMedidaSaldos($panel, data);
            return;
        }

        $vacio.addClass('d-none');
        $wrap.removeClass('d-none');
        $total.text((data && data.total_fmt) ? data.total_fmt : '0');

        filas.forEach(function (fila) {
            var depId = parseInt(fila.deposito_id, 10) || 0;
            var $tr = $('<tr></tr>');
            $tr.append($('<td class="text-monospace small"></td>').text(fila.codigo || ''));
            $tr.append($('<td class="small"></td>').text(fila.nombre || ''));
            if (mostrarEmpresa) {
                $tr.append($('<td class="small saldos-col-empresa"></td>').text(fila.empresa_nombre || ''));
            }
            $tr.append($('<td class="text-right text-monospace small"></td>').text(fila.saldo_fmt || ''));
            var $btn = $('<button type="button" class="btn btn-outline-info btn-xs btn-sm py-0 px-1 btn-saldo-fila-kardex" title="Abrir kardex en este dep\u00f3sito"></button>');
            $btn.attr('data-deposito-id', String(depId));
            $btn.html('<i class="fa fa-list-alt"></i>');
            $tr.append($('<td class="text-center"></td>').append($btn));
            $tbody.append($tr);
        });

        actualizarEtiquetasUnidadMedidaSaldos($panel, data);
    }

    function cargarSaldosArticulo(articuloId, panelSelector) {
        articuloId = parseInt(articuloId, 10) || 0;
        var $panel = $(panelSelector);
        if (articuloId <= 0 || !$panel.length) {
            return;
        }

        var $loading = $panel.find('.saldos-articulo-loading');
        var $error = $panel.find('.saldos-articulo-error');
        var $wrap = $panel.find('.saldos-articulo-tabla-wrap');
        var $vacio = $panel.find('.saldos-articulo-vacio');

        $loading.removeClass('d-none');
        $error.addClass('d-none').text('');
        $wrap.addClass('d-none');
        $vacio.addClass('d-none');
        $panel.find('.saldos-articulo-tbody').empty();

        var payloadSaldos = { articulo_id: articuloId };
        var empresaIdSaldos = empresaIdParaSaldosKardex();
        if (empresaIdSaldos > 0) {
            payloadSaldos.empresa_id = empresaIdSaldos;
        }

        $.ajax({
            url: urlApiSaldosArticulo(),
            type: 'GET',
            dataType: 'json',
            data: payloadSaldos,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            },
        })
            .done(function (data) {
                renderSaldosEnPanel($panel, data);
            })
            .fail(function (xhr) {
                $loading.addClass('d-none');
                $wrap.addClass('d-none');
                $vacio.addClass('d-none');
                var msg = (xhr.responseJSON && xhr.responseJSON.error)
                    ? xhr.responseJSON.error
                    : 'No se pudieron consultar los saldos.';
                $error.removeClass('d-none').text(msg);
            });
    }

    function abrirKardexDesdePanelSaldos(articuloId, depositoId, volverUrl) {
        articuloId = parseInt(articuloId, 10) || 0;
        depositoId = parseInt(depositoId, 10) || 0;
        if (articuloId <= 0) {
            return;
        }
        $('#modalSaldosArticulo, #modalKardexDeposito').modal('hide');
        abrirUrlKardex(articuloId, depositoId, volverUrl || window.location.pathname + window.location.search);
    }

    function mostrarModalSaldosArticulo(config) {
        config = config || {};
        pendingSaldosArticuloId = parseInt(config.articuloId, 10) || 0;
        pendingSaldosVolverUrl = config.volverUrl || (window.location.pathname + window.location.search);

        $('#modal-saldos-articulo-info').text(
            textoInfoArticulo(config.sku, config.descripcion, pendingSaldosArticuloId)
        );

        cargarSaldosArticulo(pendingSaldosArticuloId, '#saldos-articulo-panel-modal');
        window.__anitaPermitirShowModalSaldos = true;
        $('#modalSaldosArticulo').modal({ focus: false });
        $('#modalSaldosArticulo').removeAttr('inert').modal('show');
    }

    function datosDesdeBotonArticulo(btn) {
        if (!btn) {
            return null;
        }
        return {
            articuloId: parseInt(btn.getAttribute('data-articulo-id'), 10) || 0,
            sku: btn.getAttribute('data-articulo-sku') || '',
            descripcion: btn.getAttribute('data-articulo-descripcion') || '',
            depositoId: parseInt(btn.getAttribute('data-deposito-id'), 10) || 0,
            volverUrl: window.location.pathname + window.location.search,
        };
    }

    function notificar(titulo, mensaje, tipo) {
        if (typeof Biblioteca !== 'undefined' && Biblioteca.notificaciones) {
            Biblioteca.notificaciones(mensaje, titulo, tipo || 'warning');
        } else {
            alert(mensaje);
        }
    }

    function abrirUrlKardex(articuloId, depositoId, volverUrl) {
        articuloId = parseInt(articuloId, 10) || 0;
        if (articuloId <= 0) {
            notificar('Kardex', 'Seleccione un artículo válido.');
            return;
        }

        depositoId = parseInt(depositoId, 10) || 0;
        var params = new URLSearchParams({
            articulo_id: String(articuloId),
            deposito_id: depositoId > 0 ? String(depositoId) : '0',
            vista: 'consulta',
            volver: volverUrl || (window.location.pathname + window.location.search),
        });

        var empresaIdKardex = empresaIdParaUrlKardex();
        if (empresaIdKardex > 0) {
            params.set('empresa_id', String(empresaIdKardex));
        }

        window.open(urlIndexMovimientosArticulo() + '?' + params.toString(), '_blank', 'noopener');
    }

    function limpiarPickerKardexModal() {
        var $ctx = $('#tm_deposito_kardex_pick');
        if ($ctx.length && typeof limpiarCamposDepositoEnFormulario === 'function') {
            limpiarCamposDepositoEnFormulario($ctx);
        }
    }

    function aplicarDepositoPickerKardexModal(deposito) {
        if (!deposito || !deposito.id) {
            return;
        }
        var $ctx = $('#tm_deposito_kardex_pick');
        var descripcion = deposito.descripcion || deposito.nombre || '';
        if (deposito.empresa_nombre) {
            descripcion = descripcion + ' (' + deposito.empresa_nombre + ')';
        }
        $ctx.find('.deposito_id').val(deposito.id);
        $ctx.find('.codigodeposito').val(deposito.codigo || '');
        $ctx.find('.descripciondeposito').val(descripcion);
        if (deposito.tipodeposito !== undefined) {
            $ctx.attr('data-tipodeposito', deposito.tipodeposito);
        }
    }

    function depositoDesdePickerKardexModal() {
        return parseInt($('#kardex_pick_deposito_id').val(), 10) || 0;
    }

    function mostrarModalKardexDeposito(config) {
        config = config || {};
        pendingKardexArticuloId = parseInt(config.articuloId, 10) || 0;
        pendingKardexVolverUrl = config.volverUrl || (window.location.pathname + window.location.search);
        kardexModalConfirmarConEnter = false;

        if (pendingKardexArticuloId <= 0) {
            notificar('Kardex', 'Seleccione un artículo válido.');
            return;
        }

        var sku = (config.sku || '').trim();
        var descripcion = (config.descripcion || '').trim();
        $('#modal-kardex-articulo-info').text(textoInfoArticulo(sku, descripcion, pendingKardexArticuloId));

        var depositoDefaultId = parseInt(config.depositoDefaultId, 10) || 0;

        limpiarPickerKardexModal();
        $('#modal-kardex-picker-label').text('Depósito');

        if (depositoDefaultId > 0 && (config.depositoDefaultCodigo || config.depositoDefaultNombre)) {
            aplicarDepositoPickerKardexModal({
                id: depositoDefaultId,
                codigo: config.depositoDefaultCodigo || '',
                descripcion: config.depositoDefaultNombre || '',
            });
        } else if (depositoDefaultId > 0) {
            $('#kardex_pick_deposito_id').val(depositoDefaultId);
        }

        cargarSaldosArticulo(pendingKardexArticuloId, '#saldos-articulo-panel-kardex');
        window.__anitaPermitirShowModalKardex = true;
        $('#modalKardexDeposito').modal({ focus: false });
        $('#modalKardexDeposito').removeAttr('inert').modal('show');
    }

    /**
     * @param {number} articuloId
     * @param {string|number} depositoIdAttr
     * @param {Array<{id:number,label:string}>|null} opcionesDeposito
     * @param {{sku?:string,descripcion?:string,volverUrl?:string}} extra
     */
    function abrirKardexArticulo(articuloId, depositoIdAttr, opcionesDeposito, extra) {
        extra = extra || {};
        articuloId = parseInt(articuloId, 10) || 0;
        if (articuloId <= 0) {
            var hiddenArt = document.getElementById('articulo_id');
            articuloId = hiddenArt ? parseInt(hiddenArt.value, 10) || 0 : 0;
        }

        if (articuloId <= 0) {
            notificar('Kardex', 'Seleccione un artículo válido.');
            return;
        }

        var opciones = Array.isArray(opcionesDeposito) ? opcionesDeposito.filter(function (o) {
            return parseInt(o.id, 10) > 0;
        }) : [];

        if (opciones.length === 1) {
            abrirUrlKardex(articuloId, opciones[0].id, extra.volverUrl);
            return;
        }

        var depositoId = resolverDepositoDefaultArticulo(depositoIdAttr);
        if (depositoId > 0 && opciones.length === 0) {
            abrirUrlKardex(articuloId, depositoId, extra.volverUrl);
            return;
        }

        if (modalKardexDisponible()) {
            mostrarModalKardexDeposito({
                articuloId: articuloId,
                sku: extra.sku || '',
                descripcion: extra.descripcion || '',
                depositoDefaultId: depositoId > 0 ? depositoId : 0,
                volverUrl: extra.volverUrl,
            });
            return;
        }

        if (depositoId > 0) {
            abrirUrlKardex(articuloId, depositoId, extra.volverUrl);
            return;
        }

        abrirUrlKardex(articuloId, 0, extra.volverUrl);
    }

    function abrirConsultaMovimientosRecuento(articuloId) {
        if (!enFormularioRecuento()) {
            return;
        }

        articuloId = parseInt(articuloId, 10) || 0;
        var el = document.getElementById('recuento_deposito_id')
            || document.querySelector('#form-recuento .deposito_id');
        var depId = el ? parseInt(el.value, 10) || 0 : 0;

        if (!depId) {
            notificar('Recuento', 'Valide el depósito del recuento antes de consultar movimientos.');
            return;
        }
        if (articuloId <= 0) {
            notificar('Recuento', 'Seleccione un artículo válido.');
            return;
        }

        abrirUrlKardex(articuloId, depId, window.location.pathname + window.location.search);
    }

    function resolverDepositoDefaultArticulo(depositoIdAttr) {
        var depId = parseInt(depositoIdAttr, 10) || 0;

        if (enFormularioArticulo()) {
            var $sel = $('#depositoentrega_id');
            if ($sel.length) {
                var desdeForm = parseInt($sel.val(), 10) || 0;
                if (desdeForm > 0) {
                    return desdeForm;
                }
            }
        }

        return depId;
    }

    function depositoDesdeFormArticulo() {
        var $sel = $('#depositoentrega_id');
        if (!$sel.length) {
            return null;
        }
        var id = parseInt($sel.val(), 10) || 0;
        if (id <= 0) {
            return null;
        }
        var texto = ($sel.find('option:selected').text() || '').trim();
        return {
            id: id,
            codigo: '',
            descripcion: texto,
        };
    }

    function abrirKardexDesdeAbmArticulo(btn) {
        var articuloId = btn.getAttribute('data-articulo-id');
        var extra = {
            sku: btn.getAttribute('data-articulo-sku') || '',
            descripcion: btn.getAttribute('data-articulo-descripcion') || '',
            volverUrl: window.location.pathname + window.location.search,
        };
        var depId = resolverDepositoDefaultArticulo(btn.getAttribute('data-deposito-id') || '');

        if (modalKardexDisponible()) {
            var depForm = depositoDesdeFormArticulo();
            mostrarModalKardexDeposito({
                articuloId: articuloId,
                sku: extra.sku,
                descripcion: extra.descripcion,
                depositoDefaultId: depForm ? depForm.id : depId,
                depositoDefaultNombre: depForm ? depForm.descripcion : '',
                volverUrl: extra.volverUrl,
            });
            return;
        }

        abrirKardexArticulo(articuloId, depId, null, extra);
    }

    function recargarPaginaMovimientos(depositoId) {
        var params = new URLSearchParams(window.location.search);
        var articuloId = document.getElementById('movimientos-articulo-id');
        if (articuloId && articuloId.value) {
            params.set('articulo_id', articuloId.value);
        }

        depositoId = parseInt(depositoId, 10) || 0;
        params.set('deposito_id', depositoId > 0 ? String(depositoId) : '0');

        var empresaIdKardex = empresaIdParaUrlKardex();
        if (empresaIdKardex > 0) {
            params.set('empresa_id', String(empresaIdKardex));
        } else {
            params.delete('empresa_id');
        }

        window.location.href = urlIndexMovimientosArticulo() + '?' + params.toString();
    }

    function datosDesdeFilaRecuento(tr) {
        if (!tr) {
            return null;
        }
        return {
            articuloId: parseInt((tr.querySelector('.articulo_id') || {}).value, 10) || 0,
            sku: ((tr.querySelector('.codigoarticulo') || {}).value || '').trim(),
            descripcion: ((tr.querySelector('.descripcionarticulo') || {}).value || '').trim(),
        };
    }

    function datosDesdeFilaMovimientoStock(tr) {
        if (!tr) {
            return null;
        }
        var articuloId = 0;
        if (typeof window.msFilaArticuloId === 'function' && window.jQuery) {
            articuloId = parseInt(window.msFilaArticuloId(window.jQuery(tr)), 10) || 0;
        }
        if (articuloId <= 0) {
            articuloId = parseInt((tr.querySelector('.articulo_id') || {}).value, 10) || 0;
        }
        return {
            articuloId: articuloId,
            sku: ((tr.querySelector('.codigoarticulo') || {}).value || '').trim(),
            descripcion: ((tr.querySelector('.descripcionarticulo') || {}).value || '').trim(),
        };
    }

    function actualizarBotonesMovimientosFila(tr) {
        if (!tr || !enFormularioRecuento()) {
            return;
        }
        var btn = tr.querySelector('.btn-movimientos-articulo-deposito');
        if (!btn) {
            return;
        }
        var articuloId = parseInt((tr.querySelector('.articulo_id') || {}).value, 10) || 0;
        btn.disabled = articuloId <= 0;
        btn.classList.toggle('d-none', articuloId <= 0);
    }

    function actualizarBotonesSaldosFilaMovStock(tr) {
        if (!tr || !document.getElementById('tabla-items-movimientostock')) {
            return;
        }
        var btn = tr.querySelector('.btn-saldos-articulo-linea');
        if (!btn) {
            return;
        }
        var datos = datosDesdeFilaMovimientoStock(tr);
        var visible = datos && datos.articuloId > 0;
        btn.disabled = !visible;
        btn.classList.toggle('d-none', !visible);
    }

    function toggleBotonesModalConsulta() {
        var enRecuento = enFormularioRecuento();
        if (enRecuento) {
            $('#consultaarticuloModal .btn-movimientos-articulo-deposito').removeClass('d-none');
            $('#consultaarticuloModal .btn-kardex-consulta-articulo').addClass('d-none');
        } else {
            $('#consultaarticuloModal .btn-movimientos-articulo-deposito').addClass('d-none');
            $('#consultaarticuloModal .btn-kardex-consulta-articulo').removeClass('d-none');
        }
    }

    window.abrirKardexArticulo = abrirKardexArticulo;
    window.abrirUrlKardex = abrirUrlKardex;
    window.abrirMovimientosArticuloDepositoRecuento = abrirConsultaMovimientosRecuento;
    window.actualizarBotonMovimientosRecuentoFila = actualizarBotonesMovimientosFila;
    window.actualizarBotonKardexMovimientoStockFila = actualizarBotonesSaldosFilaMovStock;
    window.actualizarBotonSaldosMovimientoStockFila = actualizarBotonesSaldosFilaMovStock;

    var onDepositoAplicadoAnterior = window.onDepositoAplicadoEnFormulario;
    window.onDepositoAplicadoEnFormulario = function (data, $ctx) {
        if ($ctx && $ctx.length) {
            if ($ctx.closest('#filtro-deposito-movimientos-articulo').length) {
                recargarPaginaMovimientos(data.id);
                return;
            }
            if ($ctx.closest('#modal-kardex-picker-wrap').length) {
                if (kardexModalConfirmarConEnter && data && data.id) {
                    kardexModalConfirmarConEnter = false;
                    $('#modalKardexDeposito').modal('hide');
                    abrirUrlKardex(pendingKardexArticuloId, data.id, pendingKardexVolverUrl);
                }
                return;
            }
        }

        if (typeof onDepositoAplicadoAnterior === 'function') {
            onDepositoAplicadoAnterior(data, $ctx);
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        limpiarModalesStockConsultaAlCargar();
        registrarGuardModalStock($('#modalSaldosArticulo'), '__anitaPermitirShowModalSaldos');
        registrarGuardModalStock($('#modalKardexDeposito'), '__anitaPermitirShowModalKardex');
        enfocarBusquedaListadoArticulos();

        window.addEventListener('pageshow', function () {
            if (esListadoArticulosIndexPagina()) {
                limpiarModalesStockConsultaAlCargar();
                enfocarBusquedaListadoArticulos();
            }
        });

        window.addEventListener('load', function () {
            if (esListadoArticulosIndexPagina()) {
                limpiarModalesStockConsultaAlCargar();
                enfocarBusquedaListadoArticulos();
            }
        });

        if (enPaginaMovimientosArticulo() && typeof activa_eventos_consultadeposito === 'function') {
            activa_eventos_consultadeposito();
        }

        $(document).on('click.saldoFilaKardex', '.btn-saldo-fila-kardex', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var depId = parseInt($(this).attr('data-deposito-id'), 10) || 0;
            var $modalSaldos = $(this).closest('#modalSaldosArticulo');
            var $modalKardex = $(this).closest('#modalKardexDeposito');
            var articuloId = pendingSaldosArticuloId;
            var volverUrl = pendingSaldosVolverUrl;
            if ($modalKardex.length) {
                articuloId = pendingKardexArticuloId;
                volverUrl = pendingKardexVolverUrl;
            } else if (!$modalSaldos.length) {
                articuloId = 0;
            }
            abrirKardexDesdePanelSaldos(articuloId, depId, volverUrl);
        });

        if (modalKardexDisponible()) {
            $('#modal-kardex-picker-wrap .codigodeposito, #modal-kardex-picker-wrap .consultadeposito')
                .prop('tabindex', -1);

            $('#modalKardexDeposito')
                .on('hidden.bs.modal.kardexTab', function () {
                    $('#modal-kardex-picker-wrap .codigodeposito, #modal-kardex-picker-wrap .consultadeposito')
                        .prop('tabindex', -1);
                    $(this).attr('inert', 'inert');
                });

            $('#modalKardexDeposito').on('shown.bs.modal.kardexFoco', function () {
                $(this).removeAttr('inert');
                $('#modal-kardex-picker-wrap .codigodeposito, #modal-kardex-picker-wrap .consultadeposito')
                    .prop('tabindex', 0);
                if (typeof activa_eventos_consultadeposito === 'function') {
                    activa_eventos_consultadeposito();
                }
                focoCodigoDepositoKardexModal();
            });

            $('#btn-kardex-abrir').on('click', function () {
                ejecutarAbrirKardexModal();
            });

            $('#btn-kardex-todos-depositos').on('click', function () {
                $('#modalKardexDeposito').modal('hide');
                abrirUrlKardex(pendingKardexArticuloId, 0, pendingKardexVolverUrl);
            });

            $(document).on('keydown.kardexModalAbrir', '#modalKardexDeposito', function (e) {
                if (e.key !== 'Enter' && e.which !== 13) {
                    return;
                }
                if ($(e.target).closest('#consultadepositoModal.show, #consultadepositoModal[style*="display: block"]').length) {
                    return;
                }
                if (e.target.classList.contains('codigodeposito')) {
                    var depId = depositoDesdePickerKardexModal();
                    if (depId > 0) {
                        e.preventDefault();
                        e.stopImmediatePropagation();
                        ejecutarAbrirKardexModal();
                    } else {
                        kardexModalConfirmarConEnter = true;
                    }
                    return;
                }
                if ($(e.target).is('textarea')) {
                    return;
                }
                if ($(e.target).is('button, a')) {
                    return;
                }
                e.preventDefault();
                ejecutarAbrirKardexModal();
            });

            $(document).on('keydown.kardexF1Deposito', function (e) {
                if (e.key !== 'F1' && e.code !== 'F1' && e.keyCode !== 112) {
                    return;
                }
                if (!$('#modalKardexDeposito').hasClass('show')) {
                    return;
                }
                var target = e.target;
                if (!target || !target.classList.contains('codigodeposito')) {
                    return;
                }
                if (!$(target).closest('#modal-kardex-picker-wrap').length) {
                    return;
                }
                if ($('#consultadepositoModal').hasClass('show')) {
                    return;
                }
                e.preventDefault();
                e.stopPropagation();
                $(target).closest('.tm-deposito-campo').find('.consultadeposito').first().trigger('click');
            });

            $(document).on('keydown.kardexTabValidarDep', '#modal-kardex-picker-wrap .codigodeposito', function (e) {
                if (e.key !== 'Tab' || e.shiftKey) {
                    return;
                }
                var cod = ($(this).val() || '').trim();
                if (!cod || depositoDesdePickerKardexModal() > 0) {
                    return;
                }
                if (typeof leerDepositoPorCodigo !== 'function') {
                    return;
                }
                e.preventDefault();
                var $cod = $(this);
                leerDepositoPorCodigo(cod, this, function () {
                    var $picker = $('#modal-kardex-picker-wrap');
                    var $next = $picker.find('.consultadeposito, #btn-kardex-abrir, #btn-kardex-todos-depositos')
                        .filter(':visible')
                        .first();
                    if ($next.length) {
                        $next.trigger('focus');
                    }
                });
            });
        }

        if (document.getElementById('modalSaldosArticulo')) {
            $('#modalSaldosArticulo')
                .on('shown.bs.modal.saldosInert', function () {
                    $(this).removeAttr('inert');
                })
                .on('hidden.bs.modal.saldosInert', function () {
                    $(this).attr('inert', 'inert');
                });

            $('#btn-saldos-kardex-todos').on('click', function () {
                abrirKardexDesdePanelSaldos(pendingSaldosArticuloId, 0, pendingSaldosVolverUrl);
            });
        }

        if (enPaginaMovimientosArticulo()) {
            var btnTodos = document.getElementById('btn-movimientos-todos-depositos');
            if (btnTodos) {
                btnTodos.addEventListener('click', function (e) {
                    e.preventDefault();
                    recargarPaginaMovimientos(0);
                });
            }

            return;
        }

        document.addEventListener('click', function (e) {
            var btnSaldosLinea = e.target.closest('.btn-saldos-articulo-linea');
            if (btnSaldosLinea) {
                e.preventDefault();
                e.stopPropagation();
                var trSaldos = btnSaldosLinea.closest('tr');
                var datosLinea = datosDesdeFilaMovimientoStock(trSaldos);
                if (!datosLinea || datosLinea.articuloId <= 0) {
                    notificar('Saldos', 'Cargue un artículo en la línea.');
                    return;
                }
                if (!document.getElementById('modalSaldosArticulo')) {
                    notificar('Saldos', 'No está disponible la consulta de saldos en esta pantalla.');
                    return;
                }
                mostrarModalSaldosArticulo({
                    articuloId: datosLinea.articuloId,
                    sku: datosLinea.sku,
                    descripcion: datosLinea.descripcion,
                    volverUrl: window.location.pathname + window.location.search,
                });
                return;
            }

            var btnArticulo = e.target.closest('.btn-movimientos-stock-articulo');
            if (btnArticulo) {
                e.preventDefault();
                e.stopPropagation();
                var datosBtn = datosDesdeBotonArticulo(btnArticulo);
                var extra = {
                    sku: datosBtn.sku,
                    descripcion: datosBtn.descripcion,
                    volverUrl: datosBtn.volverUrl,
                };
                if (btnArticulo.closest('#consultaarticuloModal')
                    && typeof window.depositosKardexMovimientoStock === 'function') {
                    var opcionesConsulta = window.depositosKardexMovimientoStock();
                    if (opcionesConsulta.length > 0) {
                        abrirKardexArticulo(
                            datosBtn.articuloId,
                            datosBtn.depositoId || '',
                            opcionesConsulta,
                            extra
                        );
                        return;
                    }
                }
                abrirKardexDesdeAbmArticulo(btnArticulo);
                return;
            }

            var btnSaldos = e.target.closest('.btn-saldos-articulo');
            if (btnSaldos) {
                e.preventDefault();
                e.stopPropagation();
                var datosSaldo = datosDesdeBotonArticulo(btnSaldos);
                mostrarModalSaldosArticulo(datosSaldo);
                return;
            }

            var btnRecuento = e.target.closest('.btn-movimientos-articulo-deposito');
            if (!btnRecuento || !enFormularioRecuento()) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();

            var articuloId = parseInt(btnRecuento.getAttribute('data-articulo-id'), 10) || 0;
            if (!articuloId) {
                var trRec = btnRecuento.closest('tr.recuento-item-row');
                var datosRec = datosDesdeFilaRecuento(trRec);
                if (datosRec) {
                    articuloId = datosRec.articuloId;
                }
            }

            abrirConsultaMovimientosRecuento(articuloId);
        });

        if (enFormularioRecuento()) {
            $('#consultaarticuloModal')
                .on('show.bs.modal.movArtDep', toggleBotonesModalConsulta)
                .on('shown.bs.modal.movArtDep', toggleBotonesModalConsulta);

            document.querySelectorAll('#tbody-recuento-items tr.recuento-item-row').forEach(actualizarBotonesMovimientosFila);
        } else if ($('#consultaarticuloModal').length) {
            $('#consultaarticuloModal')
                .on('show.bs.modal.movArtDep', toggleBotonesModalConsulta)
                .on('shown.bs.modal.movArtDep', toggleBotonesModalConsulta);
            toggleBotonesModalConsulta();
        }

        if (document.getElementById('tabla-items-movimientostock')) {
            document.querySelectorAll('#tabla-items-movimientostock tbody tr.item-pedido').forEach(actualizarBotonesSaldosFilaMovStock);
        }
    });

    if (window.jQuery) {
        jQuery(function () {
            limpiarModalesStockConsultaAlCargar();
            registrarGuardModalStock(jQuery('#modalSaldosArticulo'), '__anitaPermitirShowModalSaldos');
            registrarGuardModalStock(jQuery('#modalKardexDeposito'), '__anitaPermitirShowModalKardex');
        });
    }
})();
