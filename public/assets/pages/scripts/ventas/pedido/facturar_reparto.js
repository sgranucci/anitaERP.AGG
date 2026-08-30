(function ($) {
    'use strict';

    var transporteActivoId = 0;
    var actividadesArca = window.pedidoActividadesArca || {};
    var resultadoImpresion = {
        completa: '',
        elegir: '',
    };
    var debeRecargarAlCerrar = false;

    function baseUrl() {
        return (typeof carpetaBase !== 'undefined' ? carpetaBase : (window.carpetaBase || '')).replace(/\/$/, '');
    }

    function token() {
        return $('#csrf_token').val() || $('meta[name="csrf-token"]').attr('content') || '';
    }

    function avisar(msg, tipo) {
        tipo = tipo || 'error';
        if (window.toastr) {
            toastr[tipo === 'error' ? 'error' : 'warning'](msg, 'Facturar reparto', {
                timeOut: 9000,
                closeButton: true,
                progressBar: true,
            });
        } else {
            alert(msg);
        }
    }

    function queryFiltros() {
        var raw = window.pedidoRetornoIndexUrl || '';
        try {
            var parsed = new URL(raw, window.location.origin);
            var params = {};
            parsed.searchParams.forEach(function (valor, clave) {
                params[clave] = valor;
            });
            return params;
        } catch (e) {
            return {};
        }
    }

    function actualizarActividadDesdePv() {
        var pvId = $('#reparto_puntoventa_id').val();
        if (!pvId) {
            $('#reparto_actividad_arca_id').val('');
            $('#reparto_actividad_arca_nombre').val('');
            return;
        }
        $.get(baseUrl() + '/ventas/leeunpuntoventa/' + encodeURIComponent(pvId), function (data) {
            var actividadId = data && data.actividad_arca_id ? String(data.actividad_arca_id) : '';
            $('#reparto_actividad_arca_id').val(actividadId);
            $('#reparto_actividad_arca_nombre').val(actividadesArca[actividadId] || (actividadId ? ('Actividad ' + actividadId) : ''));
        });
    }

    function formatearTotal(valor) {
        var n = Number(valor);
        if (!isFinite(n)) {
            n = 0;
        }
        return n.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function hidratarModal(data) {
        var pedidos = data.pedidos || [];
        var desde = pedidos.length ? pedidos[0].codigo : '';
        var hasta = pedidos.length ? pedidos[pedidos.length - 1].codigo : '';
        var rango = pedidos.length === 1
            ? ('1 pedido: ' + desde)
            : (pedidos.length + ' pedidos, de ' + desde + ' a ' + hasta);
        $('#facturarRepartoPedidoLabel').text('Facturar reparto ' + (data.etiqueta || ''));
        $('#facturar-reparto-rango').text(rango + ' (filtro actual).');
        $('#alert-facturar-reparto').addClass('d-none').text('');
        var $tbody = $('#tbody-facturar-reparto').empty();
        var totalCaja = 0;
        var totalUnidad = 0;
        var totalKilo = 0;
        var totalPesada = 0;
        pedidos.forEach(function (pedido) {
            var caja = Number(pedido.caja) || 0;
            var unidad = Number(pedido.unidad) || 0;
            var kilo = Number(pedido.kilo) || 0;
            var pesada = Number(pedido.pesada) || 0;
            totalCaja += caja;
            totalUnidad += unidad;
            totalKilo += kilo;
            totalPesada += pesada;
            $tbody.append(
                '<tr><td>' + $('<div>').text(pedido.codigo || pedido.id).html()
                + '</td><td>' + $('<div>').text(pedido.cliente || '').html()
                + '</td><td class="text-right">' + formatearTotal(caja)
                + '</td><td class="text-right">' + formatearTotal(unidad)
                + '</td><td class="text-right">' + formatearTotal(kilo)
                + '</td><td class="text-right">' + formatearTotal(pesada) + '</td></tr>'
            );
        });
        if (data.totales) {
            totalCaja = Number(data.totales.caja);
            totalUnidad = Number(data.totales.unidad);
            totalKilo = Number(data.totales.kilo);
            totalPesada = Number(data.totales.pesada);
            if (!isFinite(totalCaja)) {
                totalCaja = 0;
            }
            if (!isFinite(totalUnidad)) {
                totalUnidad = 0;
            }
            if (!isFinite(totalKilo)) {
                totalKilo = 0;
            }
            if (!isFinite(totalPesada)) {
                totalPesada = 0;
            }
        }
        $('#total-facturar-reparto-caja').text(formatearTotal(totalCaja));
        $('#total-facturar-reparto-unidad').text(formatearTotal(totalUnidad));
        $('#total-facturar-reparto-kilo').text(formatearTotal(totalKilo));
        $('#total-facturar-reparto-pesada').text(formatearTotal(totalPesada));
        actualizarActividadDesdePv();
    }

    function abrirModal(transporteId) {
        transporteActivoId = transporteId;
        if (window.PedidoProcesoOverlay && typeof window.PedidoProcesoOverlay.iniciar === 'function') {
            window.PedidoProcesoOverlay.iniciar(['Buscando pedidos pesados del reparto…'], 'Facturar reparto');
        }
        $.getJSON(baseUrl() + '/ventas/pedido/reparto/' + encodeURIComponent(transporteId) + '/contexto-facturacion', queryFiltros())
            .done(function (data) {
                if (window.PedidoProcesoOverlay) {
                    window.PedidoProcesoOverlay.detener();
                }
                if (data && data.error) {
                    avisar(data.error);
                    return;
                }
                hidratarModal(data);
                $('#facturarRepartoPedidoModal').modal('show');
            })
            .fail(function (xhr) {
                if (window.PedidoProcesoOverlay) {
                    window.PedidoProcesoOverlay.detener();
                }
                var msg = (xhr.responseJSON && xhr.responseJSON.error) || 'No se pudo armar el rango a facturar.';
                avisar(msg);
            });
    }

    function textoSeguro(valor) {
        return $('<div>').text(valor || '').html();
    }

    function recargarIndex() {
        window.location = window.pedidoRetornoIndexUrl || window.location.href;
    }

    function mostrarResultado(data) {
        var facturas = data.facturas || [];
        var errores = data.errores || [];
        resultadoImpresion.completa = data.impresion_url_completa || '';
        resultadoImpresion.elegir = data.impresion_url_elegir || '';
        debeRecargarAlCerrar = true;

        var resumen = facturas.length === 1
            ? 'Se emitió 1 factura.'
            : ('Se emitieron ' + facturas.length + ' facturas.');
        $('#resultado-facturar-reparto-resumen').text(resumen);

        var $alert = $('#alert-resultado-facturar-reparto');
        if (errores.length) {
            $alert.removeClass('d-none').text(errores.join('\n'));
        } else {
            $alert.addClass('d-none').text('');
        }

        var $tbody = $('#tbody-resultado-facturar-reparto').empty();
        facturas.forEach(function (factura) {
            $tbody.append(
                '<tr><td>' + textoSeguro(factura.pedido)
                + '</td><td>' + textoSeguro(factura.cliente)
                + '</td><td>' + textoSeguro(factura.codigo) + '</td></tr>'
            );
        });

        var puedeImprimir = !!(resultadoImpresion.completa || resultadoImpresion.elegir);
        $('#opciones-impresion-reparto').toggle(puedeImprimir);
        $('#reparto_imp_completa').prop('disabled', !resultadoImpresion.completa);
        $('#reparto_imp_elegir').prop('disabled', !resultadoImpresion.elegir);
        if (puedeImprimir && resultadoImpresion.completa) {
            $('#reparto_imp_completa').prop('checked', true);
        } else {
            $('#reparto_imp_ninguna').prop('checked', true);
        }

        $('#resultadoFacturarRepartoModal').modal('show');
    }

    function aceptarResultado() {
        var modo = $('input[name="reparto_impresion_modo"]:checked').val() || 'ninguna';
        var url = '';
        if (modo === 'completa') {
            url = resultadoImpresion.completa;
        } else if (modo === 'elegir') {
            url = resultadoImpresion.elegir;
        }
        if (url) {
            debeRecargarAlCerrar = false;
            window.location = url;
            return;
        }
        $('#resultadoFacturarRepartoModal').modal('hide');
    }

    function emitir() {
        var tipo = $('#reparto_tipotransaccion_id').val();
        var pv = $('#reparto_puntoventa_id').val();
        var pvRemito = $('#reparto_puntoventaremito_id').val();
        var actividad = $('#reparto_actividad_arca_id').val();
        if (!tipo || !pv || !pvRemito) {
            avisar('Debe indicar tipo de transacción, punto de venta de factura y punto de venta del remito.');
            return;
        }
        if (!actividad) {
            avisar('Debe asignar actividad ARCA.');
            return;
        }

        $('#facturarRepartoPedidoModal').modal('hide');
        if (window.PedidoProcesoOverlay && typeof window.PedidoProcesoOverlay.iniciar === 'function') {
            window.PedidoProcesoOverlay.iniciar(
                ['Facturando pedidos del reparto…', 'Emitiendo comprobantes…', 'Puede demorar varios minutos…'],
                'Facturar reparto'
            );
        }

        var payload = queryFiltros();
        payload._token = token();
        payload.tipotransaccion_id = tipo;
        payload.puntoventa_id = pv;
        payload.puntoventaremito_id = pvRemito;
        payload.actividad_arca_id = actividad;
        payload.fechafactura = new Date().toISOString().substring(0, 10);
        payload.retorno_index = window.pedidoRetornoIndexPath || '';

        $.ajax({
            url: baseUrl() + '/ventas/pedido/reparto/' + encodeURIComponent(transporteActivoId) + '/facturar',
            method: 'POST',
            dataType: 'json',
            data: payload,
        })
            .done(function (data) {
                if (window.PedidoProcesoOverlay) {
                    window.PedidoProcesoOverlay.detener();
                }
                if (data && (data.facturas || []).length) {
                    mostrarResultado(data);
                    return;
                }
                avisar((data && data.error) || 'No se generaron facturas.');
            })
            .fail(function (xhr) {
                if (window.PedidoProcesoOverlay) {
                    window.PedidoProcesoOverlay.detener();
                }
                var msg = (xhr.responseJSON && xhr.responseJSON.error) || 'Error al facturar el reparto.';
                avisar(msg);
            });
    }

    $(function () {
        $(document).on('click', '.btn-facturar-reparto-index', function () {
            var transporteId = parseInt($(this).data('transporte-id'), 10) || 0;
            if (transporteId <= 0) {
                return;
            }
            abrirModal(transporteId);
        });
        $(document).on('change', '#reparto_puntoventa_id', actualizarActividadDesdePv);
        $('#aceptaFacturarRepartoPedido').on('click', emitir);
        $('#aceptaResultadoFacturarReparto').on('click', aceptarResultado);
        $('#resultadoFacturarRepartoModal').on('hidden.bs.modal', function () {
            if (debeRecargarAlCerrar) {
                recargarIndex();
            }
        });
    });
})(jQuery);
