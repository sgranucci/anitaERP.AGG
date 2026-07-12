(function ($) {
    'use strict';

    var ptrNpuLinea = null;

    function urlConsultaNpu() {
        return window.movimientoStockConsultaNpuUrl
            || ($('#ms-consulta-npu-url').val() || '').trim();
    }

    function empresaIdParaConsultaNpu() {
        var $emp = $('#empresa_id').first();
        if (!$emp.length) {
            return '';
        }
        return String($emp.val() || '').trim();
    }

    function buscarDatosNpu(consulta) {
        var url = urlConsultaNpu();
        if (!url) {
            return;
        }

        $.ajax({
            url: url,
            type: 'POST',
            dataType: 'HTML',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            },
            data: {
                consulta: consulta || '',
                empresa_id: empresaIdParaConsultaNpu(),
            },
        })
            .done(function (respuesta) {
                var html = '';
                if (typeof respuesta === 'string') {
                    try {
                        html = JSON.parse(respuesta).data || '';
                    } catch (e) {
                        html = respuesta.replace(/\\/g, '');
                    }
                } else if (respuesta && typeof respuesta.data === 'string') {
                    html = respuesta.data;
                }
                $('#datosnpubaja').html(html);
            })
            .fail(function () {
                $('#datosnpubaja').html('<tr><td colspan="4">Error al consultar NPUs</td></tr>');
            });
    }

    function aplicarDatosNpuEnFila($tr, datos) {
        if (!$tr || !$tr.length || !datos) {
            return;
        }

        $tr.find('.numeroparte-baja-linea').val(String(datos.npu || '').trim());
        $tr.find('input.articulo_id[name="articulos_id[]"]').val(datos.articulo_id || '');
        $tr.find('.codigoarticulo').val(datos.sku || '');
        $tr.find('.descripcionarticulo').val(datos.descripcion || '');
        $tr.find('.articulo_id_previo').val(datos.articulo_id || '');
        $tr.find('.cantidad-stock').val('1');

        if (typeof actualizarLinkEditarArticulo === 'function') {
            actualizarLinkEditarArticulo($tr, parseInt(datos.articulo_id, 10) || 0);
        }

        if (datos.precio != null && isFinite(parseFloat(datos.precio))) {
            $tr.find('.precio').val(parseFloat(datos.precio).toFixed(2));
            if (datos.listaprecio_id) {
                $tr.find('.listaprecio_id').val(datos.listaprecio_id);
            }
            if (datos.moneda_id) {
                $tr.find('.moneda_id').val(datos.moneda_id);
            }
            if (datos.incluyeimpuesto != null && datos.incluyeimpuesto !== '') {
                $tr.find('.incluyeimpuesto').val(datos.incluyeimpuesto);
            }
            if (typeof window.movStockProgramarPreviewAsiento === 'function') {
                window.movStockProgramarPreviewAsiento();
            }
        } else if (typeof window.msResolverPrecioLinea === 'function') {
            window.msResolverPrecioLinea($tr, parseInt(datos.articulo_id, 10) || 0);
        }
    }

    function datosDesdeFilaModal($row) {
        return {
            npu: ($row.find('.numeroparte').text() || '').trim(),
            sku: ($row.find('.sku').text() || '').trim(),
            descripcion: ($row.find('.descripcion').text() || '').trim(),
            articulo_id: ($row.find('.articulo-id').text() || '').trim(),
        };
    }

    function aplicarNpuElegido($row) {
        if (!ptrNpuLinea || !ptrNpuLinea.length || !$row || !$row.length) {
            return;
        }

        var datos = datosDesdeFilaModal($row);
        if (datos.npu === '') {
            return;
        }

        var $tr = ptrNpuLinea.closest('tr.item-pedido');

        window._omitirBlurResolverNpu = true;
        aplicarDatosNpuEnFila($tr, datos);
        $('#consultanpubajaModal').modal('hide');

        if (typeof window.msResolverNpuEnFila === 'function') {
            window.msResolverNpuEnFila($tr, { silencioso: true, yaAplicado: true });
        }

        setTimeout(function () {
            window._omitirBlurResolverNpu = false;
        }, 400);
    }

    $(document).on('click', '.consultanpubaja', function () {
        if (typeof window.msEsModoBajaNpu === 'function' && !window.msEsModoBajaNpu()) {
            return;
        }
        ptrNpuLinea = $(this).closest('td').find('.numeroparte-baja-linea');
        $('#consultanpubaja').val('');
        $('#datosnpubaja').html('');
        $('#consultanpubajaModal').modal('show');
        buscarDatosNpu('');
        setTimeout(function () {
            $('#consultanpubaja').trigger('focus');
        }, 300);
    });

    $(document).on('keyup', '#consultanpubaja', function () {
        buscarDatosNpu($(this).val());
    });

    $(document).on('click', '#aceptaconsultanpubajaModal', function () {
        var $sel = $('#datosnpubaja tr').has('.eligeconsultanpubaja:focus').first();
        if (!$sel.length) {
            $sel = $('#datosnpubaja tr').first();
        }
        aplicarNpuElegido($sel);
    });

    $(document).on('click', '.eligeconsultanpubaja', function (e) {
        e.preventDefault();
        aplicarNpuElegido($(this).closest('tr'));
    });

    window.activaEventosConsultaNpuBaja = function () {
        // Reservado por convención de modales de consulta del ERP.
    };
}(jQuery));
