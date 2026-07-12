(function ($) {
    'use strict';

    function esModoBajaNpu() {
        var meta = typeof msTipoTransaccionMeta === 'function' ? msTipoTransaccionMeta() : {};

        return !!meta.bajaNpu;
    }

    function urlResolverNpu() {
        return window.movimientoStockResolverNpuUrl
            || ($('#ms-resolver-npu-url').val() || '').trim();
    }

    function aplicarPrecioUltimaCompraEnFila($tr, data) {
        if (!data || data.precio == null) {
            if (typeof window.msResolverPrecioLinea === 'function') {
                window.msResolverPrecioLinea($tr, parseInt(data.articulo_id, 10) || 0);
            }
            return;
        }
        var precio = parseFloat(data.precio);
        if (!isFinite(precio)) {
            return;
        }
        $tr.find('.precio').val(precio.toFixed(2));
        if (data.listaprecio_id) {
            $tr.find('.listaprecio_id').val(data.listaprecio_id);
        } else {
            $tr.find('.listaprecio_id').val('');
        }
        if (data.moneda_id) {
            $tr.find('.moneda_id').val(data.moneda_id);
        }
        if (data.incluyeimpuesto != null && data.incluyeimpuesto !== '') {
            $tr.find('.incluyeimpuesto').val(data.incluyeimpuesto);
        }
        if (typeof window.movStockProgramarPreviewAsiento === 'function') {
            window.movStockProgramarPreviewAsiento();
        }
    }

    function aplicarModoBajaNpuEnTabla() {
        var activo = esModoBajaNpu();
        var $tabla = $('#tabla-items-movimientostock');
        if (!$tabla.length) {
            return;
        }

        $tabla.find('th.ms-col-npu-baja, td.ms-col-npu-baja').toggle(activo);
        $tabla.toggleClass('ms-tabla-baja-npu', activo);
        $('#ms-ayuda-baja-npu').toggle(activo);

        if (activo) {
            $tabla.find('tr.item-pedido').each(function () {
                var $tr = $(this);
                $tr.find('.cantidad-stock').val('1').prop('readonly', true);
                $tr.find('.cant-unidad').val('').prop('readonly', true);
                $tr.find('.precio').prop('readonly', true);
            });
            if (typeof window.msRefrescarPreciosTodasLasFilas === 'function') {
                window.msRefrescarPreciosTodasLasFilas();
            }
        } else {
            $tabla.find('.cantidad-stock, .cant-unidad, .precio').prop('readonly', false);
        }
    }

    function resolverNpuEnFila($tr, opciones) {
        opciones = opciones || {};
        var url = urlResolverNpu();
        var npu = ($tr.find('.numeroparte-baja-linea').val() || '').trim();
        if (!url || npu === '') {
            return $.Deferred().reject().promise();
        }

        var tipoId = parseInt($('#tipotransaccion_stock_id').val(), 10) || 0;

        return $.get(url, {
            npu: npu,
            tipotransaccion_stock_id: tipoId,
        }).done(function (data) {
            if (!data || !data.ok) {
                if (!opciones.silencioso) {
                    alert((data && data.mensaje) ? data.mensaje : 'NPU no válido.');
                }
                return;
            }

            if (!opciones.yaAplicado) {
                $tr.find('input.articulo_id[name="articulos_id[]"]').val(data.articulo_id || '');
                $tr.find('.codigoarticulo').val(data.sku || '');
                $tr.find('.descripcionarticulo').val(data.descripcion || '');
                $tr.find('.articulo_id_previo').val(data.articulo_id || '');
                $tr.find('.cantidad-stock').val('1');

                if (typeof actualizarLinkEditarArticulo === 'function') {
                    actualizarLinkEditarArticulo($tr, parseInt(data.articulo_id, 10) || 0);
                }
            }

            aplicarPrecioUltimaCompraEnFila($tr, data);
        }).fail(function (xhr) {
            if (opciones.silencioso || opciones.yaAplicado) {
                if (typeof window.msResolverPrecioLinea === 'function') {
                    var articuloId = parseInt($tr.find('input.articulo_id[name="articulos_id[]"]').val(), 10) || 0;
                    if (articuloId > 0) {
                        window.msResolverPrecioLinea($tr, articuloId);
                    }
                }
                return;
            }
            var msg = xhr.responseJSON && xhr.responseJSON.mensaje ? xhr.responseJSON.mensaje : 'No se pudo resolver el NPU.';
            alert(msg);
        });
    }

    $(document).on('change', '#tipotransaccion_stock_id', function () {
        aplicarModoBajaNpuEnTabla();
    });

    $(document).on('keydown', '.numeroparte-baja-linea', function (e) {
        if (e.which !== 13) {
            return;
        }
        e.preventDefault();
        resolverNpuEnFila($(this).closest('tr.item-pedido'));
    });

    $(document).on('blur', '.numeroparte-baja-linea', function () {
        if (window._omitirBlurResolverNpu) {
            return;
        }
        var $tr = $(this).closest('tr.item-pedido');
        if (!esModoBajaNpu()) {
            return;
        }
        var npu = ($(this).val() || '').trim();
        if (npu !== '' && !($tr.find('.codigoarticulo').val() || '').trim()) {
            resolverNpuEnFila($tr);
        }
    });

    $(document).on('click', '#agrega_renglon', function () {
        setTimeout(aplicarModoBajaNpuEnTabla, 50);
    });

    $(function () {
        if (!$('#tabla-items-movimientostock').length) {
            return;
        }
        setTimeout(aplicarModoBajaNpuEnTabla, 200);
    });

    window.msAplicarModoBajaNpuEnTabla = aplicarModoBajaNpuEnTabla;
    window.msEsModoBajaNpu = esModoBajaNpu;
    window.msResolverNpuEnFila = resolverNpuEnFila;
}(jQuery));
