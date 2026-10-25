(function ($) {
    'use strict';

    function formulario() {
        var id = $('.lw-workbench').data('form');
        return id ? $(id) : $();
    }

    function syncColumnasHidden() {
        var keys = [];
        $('#lw-grilla-config-tbody tr.lw-grilla-fila').each(function () {
            var $tr = $(this);
            if ($tr.find('input[type=checkbox][name*="[visible]"]').is(':checked')) {
                keys.push($tr.data('key'));
            }
        });
        $('#lw_columnas_csv').val(keys.join(','));
    }

    function reindexGrillaRows() {
        $('#lw-grilla-config-tbody tr.lw-grilla-fila').each(function (i) {
            var $tr = $(this);
            $tr.find('.lw-grilla-orden-num').text(i + 1);
            $tr.find('.lw-grilla-orden').val(i);
            $tr.find('input, select').each(function () {
                var name = $(this).attr('name');
                if (!name) {
                    return;
                }
                $(this).attr('name', name.replace(/grilla\[\d+]/, 'grilla[' + i + ']'));
            });
        });
    }

    $(function () {
        var $form = formulario();
        if (!$form.length || !$('.lw-workbench').length) {
            return;
        }

        $('#lw-search-rapida').on('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                $('#filtro_busqueda_rapida').val('1');
                $('#filtro_valor').val($(this).val());
                syncColumnasHidden();
                $form.trigger('submit');
            }
        });
        $('#btn-lw-buscar-rapida').on('click', function () {
            $('#filtro_busqueda_rapida').val('1');
            $('#filtro_valor').val($('#lw-search-rapida').val());
            syncColumnasHidden();
            $form.trigger('submit');
        });
        $('#lw-vista-select').on('change', function () {
            var id = $(this).val();
            var base = $(this).data('base-url') || window.location.pathname;
            var sala = $form.find('input[name="sala_id"]').val();
            var extra = sala ? '&sala_id=' + encodeURIComponent(sala) : '';
            if (!id) {
                window.location = base + '?vista_estandar=1' + extra;
                return;
            }
            window.location = base + '?vista_id=' + encodeURIComponent(id) + extra;
        });
        $form.on('submit', function () {
            syncColumnasHidden();
            if (typeof window.lwQbeBeforeSubmit === 'function' && window.lwQbeBeforeSubmit()) {
                $('#filtro_busqueda_rapida').val('');
            }
        });
        $(document).on('click', '.lw-grilla-up', function () {
            var $tr = $(this).closest('tr');
            var $prev = $tr.prev('tr');
            if ($prev.length) {
                $tr.insertBefore($prev);
                reindexGrillaRows();
            }
        });
        $(document).on('click', '.lw-grilla-down', function () {
            var $tr = $(this).closest('tr');
            var $next = $tr.next('tr');
            if ($next.length) {
                $tr.insertAfter($next);
                reindexGrillaRows();
            }
        });
        $('#form-lw-grilla-aplicar').on('submit', function () {
            reindexGrillaRows();
        });
        $('#btn-lw-eliminar-vista-ref').on('click', function () {
            if (confirm('¿Eliminar esta vista?')) {
                $('#form-lw-eliminar-vista').trigger('submit');
            }
        });
        if (typeof window.initListadoQbeGrupos === 'function') {
            window.initListadoQbeGrupos();
        }
        if (typeof window.initListadoOrden === 'function') {
            window.initListadoOrden();
        }
        if (typeof window.initListadoAgrupacion === 'function') {
            window.initListadoAgrupacion();
        }
        if (typeof window.initListadoDisenadorPreview === 'function') {
            window.initListadoDisenadorPreview();
        }
    });
})(jQuery);
