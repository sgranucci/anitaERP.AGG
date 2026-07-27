(function ($) {
    'use strict';

    function proximoIndice() {
        var max = -1;
        $('#tbody-variantes .variante-row').each(function () {
            var name = $(this).find('select[name*="[color_id]"]').attr('name') || '';
            var m = name.match(/variantes\[(\d+)\]/);
            if (m) {
                var idx = parseInt(m[1], 10);
                if (idx > max) {
                    max = idx;
                }
            }
        });
        return max + 1;
    }

    function agregarFila() {
        var tpl = document.getElementById('tpl-variante-row');
        if (!tpl) {
            return;
        }
        var idx = proximoIndice();
        var html = tpl.innerHTML.replace(/__IDX__/g, idx);
        $('#tbody-variantes').append(html);
    }

    function limpiarArticuloFila($tr) {
        $tr.find('.articulo_id').val('');
        $tr.find('.descripcionarticulo').val('').attr('title', '');
        if (typeof actualizarLinkEditarArticulo === 'function') {
            actualizarLinkEditarArticulo($tr, '');
        }
    }

    $(function () {
        if (!$('#tabla-variantes').length) {
            return;
        }

        if (typeof activa_eventos_consultaarticulo === 'function') {
            activa_eventos_consultaarticulo();
        }

        $('#btn-agregar-variante').on('click', function () {
            agregarFila();
        });

        $('#tbody-variantes').on('click', '.btn-quitar-variante', function () {
            $(this).closest('tr').remove();
        });

        // Si borran el SKU a mano, limpiar id/descripcion
        $('#tbody-variantes').on('input', '.codigoarticulo', function () {
            var $tr = $(this).closest('tr');
            if (!($(this).val() || '').trim()) {
                limpiarArticuloFila($tr);
            }
        });

        // F1 sobre SKU abre el modal (igual que mov. stock)
        $('#tbody-variantes').on('keydown', '.codigoarticulo', function (e) {
            if (e.key === 'F1' || e.keyCode === 112) {
                e.preventDefault();
                var $btn = $(this).closest('tr').find('.consultaarticulo').first();
                if ($btn.length) {
                    $btn.trigger('click');
                }
            }
        });

        if ($('#tbody-variantes .variante-row').length === 0) {
            agregarFila();
        }
    });
})(jQuery);
