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

    $(function () {
        if (!$('#tabla-variantes').length) {
            return;
        }

        $('#btn-agregar-variante').on('click', function () {
            agregarFila();
        });

        $('#tbody-variantes').on('click', '.btn-quitar-variante', function () {
            $(this).closest('tr').remove();
        });

        if ($('#tbody-variantes .variante-row').length === 0) {
            agregarFila();
        }
    });
})(jQuery);
