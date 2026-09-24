(function ($) {
    'use strict';

    var estadoModal = {
        pedidoId: 0,
        pedidoCodigo: '',
        facturas: []
    };

    /**
     * Prefijo real de la app (/anitaERP/public). No usar url()/route() de Laravel:
     * APP_URL suele ir sin carpeta y genera 404 en Apache.
     */
    function carpetaBaseApp() {
        if (typeof window.resolverCarpetaBaseApp === 'function') {
            var resuelto = String(window.resolverCarpetaBaseApp() || '').replace(/\/$/, '');
            if (resuelto !== '') {
                return resuelto;
            }
        }
        if (typeof window.carpetaBase !== 'undefined' && window.carpetaBase !== null && String(window.carpetaBase) !== '') {
            return String(window.carpetaBase).replace(/\/$/, '');
        }
        if (typeof carpetaBase !== 'undefined' && carpetaBase !== null && String(carpetaBase) !== '') {
            return String(carpetaBase).replace(/\/$/, '');
        }
        var loc = String(window.location.pathname || '');
        var mPublic = loc.match(/^(.*\/public)(?:\/|$)/);
        if (mPublic && mPublic[1]) {
            return mPublic[1];
        }
        return '';
    }

    /** Bases generadas en Blade con urlAppCarpeta (fuente de verdad). */
    function basesImpresionModal() {
        var $m = $('#modalImprimirFacturasPedido');
        return {
            factura: String($m.attr('data-base-factura') || '').replace(/\/$/, ''),
            lote: String($m.attr('data-base-lote') || '').replace(/\/$/, ''),
            retorno: String($m.attr('data-retorno-index') || '').replace(/\/$/, '')
        };
    }

    function pathRetornoIndex() {
        var bases = basesImpresionModal();
        if (bases.retorno) {
            return bases.retorno;
        }
        try {
            var path = String(window.location.pathname || '');
            if (/\/index\.php$/i.test(path)) {
                path = path.replace(/\/index\.php$/i, '') || '/';
            }
            if (path.indexOf('/ventas/') === -1 && carpetaBaseApp()) {
                return carpetaBaseApp() + '/ventas/pedido';
            }
            return path + String(window.location.search || '');
        } catch (e) {
            return carpetaBaseApp() + '/ventas/pedido';
        }
    }

    function urlSesionFactura(ventaId) {
        var bases = basesImpresionModal();
        var base = bases.factura
            ? (bases.factura + '/' + encodeURIComponent(ventaId))
            : (carpetaBaseApp() + '/ventas/impresion-sesion/factura/' + encodeURIComponent(ventaId));
        return base + '?auto=1&enviar_impresora=1&retorno=' + encodeURIComponent(pathRetornoIndex());
    }

    function urlSesionLote(pedidoId, ventaIds) {
        var bases = basesImpresionModal();
        var base = bases.lote
            ? (bases.lote + '/' + encodeURIComponent(pedidoId))
            : (carpetaBaseApp() + '/ventas/impresion-sesion/pedido-facturas/' + encodeURIComponent(pedidoId));
        var qs = 'auto=1&enviar_impresora=1&pack_completo=1&retorno=' + encodeURIComponent(pathRetornoIndex());
        if (ventaIds && ventaIds.length) {
            qs += '&venta_ids=' + encodeURIComponent(ventaIds.join(','));
        }
        return base + '?' + qs;
    }

    function formatearTotal(n) {
        var v = parseFloat(n) || 0;
        return v.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function abrirImpresionUna(factura) {
        window.location = urlSesionFactura(factura.id);
    }

    function abrirImpresionLote(ventaIds) {
        if (!estadoModal.pedidoId) {
            return;
        }
        window.location = urlSesionLote(estadoModal.pedidoId, ventaIds || []);
    }

    function renderModalFacturas(facturas) {
        var $tb = $('#modal-imprimir-facturas-tbody');
        $tb.empty();
        facturas.forEach(function (f) {
            var cae = f.cae ? String(f.cae) : '—';
            $tb.append(
                '<tr>' +
                    '<td class="text-center">' +
                        '<input type="checkbox" class="modal-imprimir-factura-check" value="' + f.id + '" checked>' +
                    '</td>' +
                    '<td>' + $('<div>').text(f.codigo || '').html() + '</td>' +
                    '<td>' + $('<div>').text(f.fecha || '').html() + '</td>' +
                    '<td class="text-right">' + formatearTotal(f.total) + '</td>' +
                    '<td><small>' + $('<div>').text(cae).html() + '</small></td>' +
                '</tr>'
            );
        });
        $('#modal-imprimir-facturas-check-todas').prop('checked', true);
    }

    function idsSeleccionados() {
        var ids = [];
        $('#modal-imprimir-facturas-tbody .modal-imprimir-factura-check:checked').each(function () {
            var id = parseInt($(this).val(), 10);
            if (id > 0) {
                ids.push(id);
            }
        });
        return ids;
    }

    $(document).on('click', '.btn-imprimir-facturas-pedido-index', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var pedidoId = parseInt($btn.data('pedido-id'), 10) || 0;
        var codigo = String($btn.data('pedido-codigo') || '');
        var facturas = $btn.data('facturas');
        if (typeof facturas === 'string') {
            try {
                facturas = JSON.parse(facturas);
            } catch (err) {
                facturas = [];
            }
        }
        if (!Array.isArray(facturas) || facturas.length === 0 || pedidoId <= 0) {
            if (window.toastr) {
                toastr.warning('No hay facturas para imprimir en este pedido.');
            } else {
                alert('No hay facturas para imprimir en este pedido.');
            }
            return;
        }

        if (facturas.length === 1) {
            abrirImpresionUna(facturas[0]);
            return;
        }

        estadoModal.pedidoId = pedidoId;
        estadoModal.pedidoCodigo = codigo;
        estadoModal.facturas = facturas;
        $('#modal-imprimir-facturas-pedido-codigo').text('Pedido ' + codigo + ' — ' + facturas.length + ' comprobantes');
        renderModalFacturas(facturas);
        $('#modalImprimirFacturasPedido').modal('show');
    });

    $(document).on('change', '#modal-imprimir-facturas-check-todas', function () {
        var on = $(this).is(':checked');
        $('#modal-imprimir-facturas-tbody .modal-imprimir-factura-check').prop('checked', on);
    });

    $(document).on('change', '#modal-imprimir-facturas-tbody .modal-imprimir-factura-check', function () {
        var total = $('#modal-imprimir-facturas-tbody .modal-imprimir-factura-check').length;
        var marcados = $('#modal-imprimir-facturas-tbody .modal-imprimir-factura-check:checked').length;
        $('#modal-imprimir-facturas-check-todas').prop('checked', total > 0 && marcados === total);
    });

    $(document).on('click', '#btn-imprimir-facturas-seleccionadas', function () {
        var ids = idsSeleccionados();
        if (ids.length === 0) {
            alert('Seleccioná al menos una factura.');
            return;
        }
        if (ids.length === 1) {
            window.location = urlSesionFactura(ids[0]);
            return;
        }
        abrirImpresionLote(ids);
    });

    $(document).on('click', '#btn-imprimir-facturas-todas', function () {
        var ids = (estadoModal.facturas || []).map(function (f) { return parseInt(f.id, 10); }).filter(function (id) { return id > 0; });
        if (ids.length === 0) {
            return;
        }
        if (ids.length === 1) {
            window.location = urlSesionFactura(ids[0]);
            return;
        }
        abrirImpresionLote(ids);
    });
})(jQuery);
