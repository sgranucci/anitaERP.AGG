/**
 * Autocompletado / resolución de registro por código-nombre en auditoría datos.
 */
(function ($) {
    'use strict';

    var timer = null;

    function urlBuscar() {
        return $('#registro_busqueda').data('url-buscar') || '';
    }

    function ensureDropdown() {
        var $input = $('#registro_busqueda');
        if (!$input.length) {
            return $();
        }
        var $wrap = $input.closest('.form-group');
        var $dd = $wrap.find('.auditoria-reg-suggest');
        if (!$dd.length) {
            $dd = $('<div>', { class: 'auditoria-reg-suggest list-group shadow-sm' });
            $wrap.css('position', 'relative').append($dd);
        }
        return $dd;
    }

    function hideSuggest() {
        ensureDropdown().empty().hide();
    }

    function showSuggest(items) {
        var $dd = ensureDropdown();
        $dd.empty();
        if (!items || !items.length) {
            $dd.hide();
            return;
        }
        items.forEach(function (item) {
            var $a = $('<button>', {
                type: 'button',
                class: 'list-group-item list-group-item-action py-1 px-2',
            });
            $a.append(
                $('<strong>', { text: item.etiqueta || '' }),
                ' ',
                $('<span>', { class: 'text-muted small', text: item.extra || '' })
            );
            $a.on('click', function () {
                $('#auditable_id').val(item.id);
                $('#registro_busqueda').val(item.codigo || item.etiqueta || item.id);
                hideSuggest();
            });
            $dd.append($a);
        });
        $dd.show();
    }

    function buscar() {
        var type = $('#auditable_type').val();
        var q = $.trim($('#registro_busqueda').val() || '');
        if (!type || q.length < 1) {
            hideSuggest();
            return;
        }
        $.getJSON(urlBuscar(), { auditable_type: type, q: q })
            .done(function (resp) {
                if (resp && resp.ok) {
                    showSuggest(resp.resultados || []);
                }
            });
    }

    $(function () {
        if (!$('#registro_busqueda').length) {
            return;
        }

        $('<style>').text(
            '.auditoria-reg-suggest{position:absolute;z-index:20;left:0;right:0;top:100%;max-height:220px;overflow:auto;display:none;background:#fff;}'
        ).appendTo('head');

        $('#registro_busqueda').on('input', function () {
            $('#auditable_id').val('');
            clearTimeout(timer);
            timer = setTimeout(buscar, 280);
        });

        $('#auditable_type').on('change', function () {
            $('#auditable_id').val('');
            hideSuggest();
        });

        $(document).on('click', function (e) {
            if (!$(e.target).closest('#registro_busqueda, .auditoria-reg-suggest').length) {
                hideSuggest();
            }
        });
    });
})(jQuery);
