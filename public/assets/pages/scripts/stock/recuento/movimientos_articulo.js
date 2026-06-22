(function () {
    'use strict';

    if (window.__anitaKardexMovimientosArticuloInit) {
        return;
    }
    window.__anitaKardexMovimientosArticuloInit = true;

    var pendingKardexArticuloId = 0;

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
        $ctx.find('.deposito_id').val(deposito.id);
        $ctx.find('.codigodeposito').val(deposito.codigo || '');
        $ctx.find('.descripciondeposito').val(deposito.descripcion || deposito.nombre || '');
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

        var sku = (config.sku || '').trim();
        var descripcion = (config.descripcion || '').trim();
        var info = sku;
        if (descripcion) {
            info += ' — ' + descripcion;
        }
        $('#modal-kardex-articulo-info').text(info || 'Artículo #' + pendingKardexArticuloId);

        var $wrapOpciones = $('#modal-kardex-opciones-wrap');
        var $lista = $('#modal-kardex-opciones').empty();
        var opciones = Array.isArray(config.opciones) ? config.opciones : [];
        var depositoDefaultId = parseInt(config.depositoDefaultId, 10) || 0;

        limpiarPickerKardexModal();

        if (opciones.length > 0) {
            $wrapOpciones.removeClass('d-none');
            opciones.forEach(function (opt) {
                var depId = parseInt(opt.id, 10) || 0;
                if (depId <= 0) {
                    return;
                }
                var $btn = $('<button type="button" class="list-group-item list-group-item-action"></button>');
                $btn.text(opt.label || ('Depósito ' + depId));
                $btn.on('click', function () {
                    $('#modalKardexDeposito').modal('hide');
                    abrirUrlKardex(pendingKardexArticuloId, depId, config.volverUrl);
                });
                $lista.append($btn);
            });
            $('#modal-kardex-picker-label').text('Otro depósito');
        } else {
            $wrapOpciones.addClass('d-none');
            $('#modal-kardex-picker-label').text('Depósito');
        }

        if (depositoDefaultId > 0 && (config.depositoDefaultCodigo || config.depositoDefaultNombre)) {
            aplicarDepositoPickerKardexModal({
                id: depositoDefaultId,
                codigo: config.depositoDefaultCodigo || '',
                descripcion: config.depositoDefaultNombre || '',
            });
        } else if (depositoDefaultId > 0) {
            $('#kardex_pick_deposito_id').val(depositoDefaultId);
        }

        $('#modalKardexDeposito').modal('show');
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

        if (opciones.length > 1) {
            if (modalKardexDisponible()) {
                mostrarModalKardexDeposito({
                    articuloId: articuloId,
                    sku: extra.sku || '',
                    descripcion: extra.descripcion || '',
                    opciones: opciones,
                    volverUrl: extra.volverUrl,
                });
            } else {
                abrirUrlKardex(articuloId, opciones[0].id, extra.volverUrl);
            }
            return;
        }

        var depositoId = resolverDepositoDefaultArticulo(depositoIdAttr);
        if (depositoId > 0) {
            abrirUrlKardex(articuloId, depositoId, extra.volverUrl);
            return;
        }

        if (modalKardexDisponible()) {
            mostrarModalKardexDeposito({
                articuloId: articuloId,
                sku: extra.sku || '',
                descripcion: extra.descripcion || '',
                depositoDefaultId: 0,
                volverUrl: extra.volverUrl,
            });
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

    function actualizarBotonesKardexFilaMovStock(tr) {
        if (!tr || !document.getElementById('tabla-items-movimientostock')) {
            return;
        }
        var btn = tr.querySelector('.btn-kardex-articulo-linea');
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
    window.actualizarBotonKardexMovimientoStockFila = actualizarBotonesKardexFilaMovStock;

    var onDepositoAplicadoAnterior = window.onDepositoAplicadoEnFormulario;
    window.onDepositoAplicadoEnFormulario = function (data, $ctx) {
        if ($ctx && $ctx.length) {
            if ($ctx.closest('#filtro-deposito-movimientos-articulo').length) {
                recargarPaginaMovimientos(data.id);
                return;
            }
        }

        if (typeof onDepositoAplicadoAnterior === 'function') {
            onDepositoAplicadoAnterior(data, $ctx);
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof activa_eventos_consultadeposito === 'function') {
            activa_eventos_consultadeposito();
        }

        if (modalKardexDisponible()) {
            $('#modalKardexDeposito').on('shown.bs.modal', function () {
                if (typeof activa_eventos_consultadeposito === 'function') {
                    activa_eventos_consultadeposito();
                }
            });

            $('#btn-kardex-abrir').on('click', function () {
                var depId = depositoDesdePickerKardexModal();
                if (depId <= 0) {
                    notificar('Kardex', 'Seleccione un depósito o use «Todos los depósitos».');
                    return;
                }
                $('#modalKardexDeposito').modal('hide');
                abrirUrlKardex(pendingKardexArticuloId, depId);
            });

            $('#btn-kardex-todos-depositos').on('click', function () {
                $('#modalKardexDeposito').modal('hide');
                abrirUrlKardex(pendingKardexArticuloId, 0);
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
            var btnKardexLinea = e.target.closest('.btn-kardex-articulo-linea');
            if (btnKardexLinea) {
                e.preventDefault();
                e.stopPropagation();
                var tr = btnKardexLinea.closest('tr');
                var datos = datosDesdeFilaMovimientoStock(tr);
                if (!datos || datos.articuloId <= 0) {
                    notificar('Kardex', 'Cargue un artículo en la línea.');
                    return;
                }
                var opciones = typeof window.depositosKardexMovimientoStock === 'function'
                    ? window.depositosKardexMovimientoStock()
                    : [];
                abrirKardexArticulo(datos.articuloId, 0, opciones, {
                    sku: datos.sku,
                    descripcion: datos.descripcion,
                    volverUrl: window.location.pathname + window.location.search,
                });
                return;
            }

            var btnArticulo = e.target.closest('.btn-movimientos-stock-articulo');
            if (btnArticulo) {
                e.preventDefault();
                e.stopPropagation();
                var extra = {
                    sku: btnArticulo.getAttribute('data-articulo-sku') || '',
                    descripcion: btnArticulo.getAttribute('data-articulo-descripcion') || '',
                    volverUrl: window.location.pathname + window.location.search,
                };
                if (btnArticulo.closest('#consultaarticuloModal')
                    && typeof window.depositosKardexMovimientoStock === 'function') {
                    var opcionesConsulta = window.depositosKardexMovimientoStock();
                    if (opcionesConsulta.length > 0) {
                        abrirKardexArticulo(
                            btnArticulo.getAttribute('data-articulo-id'),
                            btnArticulo.getAttribute('data-deposito-id') || '',
                            opcionesConsulta,
                            extra
                        );
                        return;
                    }
                }
                abrirKardexDesdeAbmArticulo(btnArticulo);
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
            document.querySelectorAll('#tabla-items-movimientostock tbody tr.item-pedido').forEach(actualizarBotonesKardexFilaMovStock);
        }
    });
})();
