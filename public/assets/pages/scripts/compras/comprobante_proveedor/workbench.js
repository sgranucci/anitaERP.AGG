(function ($) {
    'use strict';

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

    function cloneGrillaIntoVistaForm() {
        var $box = $('#lw-grilla-vista-clone').empty();
        $('#lw-grilla-config-tbody tr.lw-grilla-fila').each(function (i) {
            var $tr = $(this);
            var key = $tr.data('key');
            var titulo = $tr.find('input[name*="[titulo]"]').val() || '';
            var visible = $tr.find('input[type=checkbox][name*="[visible]"]').is(':checked') ? '1' : '0';
            var ancho = $tr.find('input[name*="[ancho]"]').val() || '120';
            var alinea = $tr.find('select[name*="[alinea]"]').val() || 'izquierda';
            $box.append('<input type="hidden" name="grilla[' + i + '][key]" value="' + $('<div/>').text(key).html() + '">');
            $box.append('<input type="hidden" name="grilla[' + i + '][titulo]" value="' + $('<div/>').text(titulo).html() + '">');
            $box.append('<input type="hidden" name="grilla[' + i + '][visible]" value="' + visible + '">');
            $box.append('<input type="hidden" name="grilla[' + i + '][ancho]" value="' + ancho + '">');
            $box.append('<input type="hidden" name="grilla[' + i + '][alinea]" value="' + alinea + '">');
            $box.append('<input type="hidden" name="grilla[' + i + '][orden]" value="' + i + '">');
        });
        $('#form-lw-grilla-vista').find('input[name^="sort["]').remove();
        var si = 0;
        $('#lw-orden-criterios .lw-orden-chip').each(function () {
            var campo = $(this).find('.lw-orden-campo').val() || '';
            var dir = $(this).find('.lw-orden-dir').val() || 'asc';
            if (!campo) {
                return;
            }
            $box.append('<input type="hidden" name="sort[' + si + '][campo]" value="' + $('<div/>').text(campo).html() + '">');
            $box.append('<input type="hidden" name="sort[' + si + '][dir]" value="' + $('<div/>').text(dir).html() + '">');
            si++;
        });
        // QBE actual del panel (grupos), no los hiddens stale de filtrosHidden.
        $('#form-lw-grilla-vista').find('[name^="qbe["]').remove();
        if (typeof window.lwQbeReindex === 'function') {
            window.lwQbeReindex();
        }
        $('#lw-qbe-panel').find('input[name^="qbe["], select[name^="qbe["]').each(function () {
            var $el = $(this);
            var name = $el.attr('name');
            if (!name || name.indexOf('__g__') >= 0 || name.indexOf('__i__') >= 0) {
                return;
            }
            if ($el.closest('template').length) {
                return;
            }
            if ($el.is(':checkbox') && !$el.prop('checked')) {
                return;
            }
            var val = $el.val();
            if (val === null || typeof val === 'undefined') {
                val = '';
            }
            $box.append($('<input type="hidden">').attr('name', name).val(val));
        });
        if (typeof window.lwGroupCloneInto === 'function') {
            window.lwGroupCloneInto($box);
        }
    }

    $(function () {
        if (!$('#form-filtros-comprobante-proveedor').length || !$('.lw-workbench').length) {
            return;
        }

        $('#filtro_codigo').on('keydown', function (e) {
            if (e.key !== 'Enter') {
                return;
            }
            e.preventDefault();
            syncColumnasHidden();
            $('#form-filtros-comprobante-proveedor').trigger('submit');
        });

        $('#lw-search-rapida').on('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                $('#filtro_busqueda_rapida').val('1');
                $('#filtro_modo').val('todos');
                $('#filtro_valor').val($(this).val());
                syncColumnasHidden();
                $('#form-filtros-comprobante-proveedor').trigger('submit');
            }
        });

        $('#btn-lw-buscar-rapida').on('click', function () {
            $('#filtro_busqueda_rapida').val('1');
            $('#filtro_modo').val('todos');
            $('#filtro_valor').val($('#lw-search-rapida').val());
            syncColumnasHidden();
            $('#form-filtros-comprobante-proveedor').trigger('submit');
        });

        $('#btn-lw-aplicar-qbe').on('click', function () {
            $('#filtro_modo').val('qbe');
            $('#filtro_busqueda_rapida').val('');
            $('#lw-aplicar-qbe').val('1');
            syncColumnasHidden();
        });

        $('#lw-qbe-panel').on('keydown', 'input, select', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                $('#filtro_modo').val('qbe');
                $('#filtro_busqueda_rapida').val('');
                $('#lw-aplicar-qbe').val('1');
                syncColumnasHidden();
                $('#form-filtros-comprobante-proveedor').trigger('submit');
            }
        });

        $('#lw-vista-select').on('change', function () {
            var id = $(this).val();
            var base = $(this).data('base-url') || window.location.pathname;
            var params = new URLSearchParams();
            var $form = $('#form-filtros-comprobante-proveedor');
            ['empresa_id', 'empresa_todas', 'estado', 'estado_todas'].forEach(function (name) {
                var v = $form.find('[name="' + name + '"]').val();
                if (v) {
                    params.set(name, v);
                }
            });
            if (!id) {
                params.set('vista_estandar', '1');
            } else {
                params.set('vista_id', id);
            }
            window.location = base + '?' + params.toString();
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
            syncColumnasHidden();
            var n = $('#lw-grilla-config-tbody input[type=checkbox][name*="[visible]"]:checked').length;
            if (n < 1) {
                alert('Deje al menos una columna visible.');
                return false;
            }
        });

        $('#form-lw-grilla-vista').on('submit', function () {
            if (typeof window.lwOrdenEnsureInForm === 'function') {
                window.lwOrdenEnsureInForm();
            }
            reindexGrillaRows();
            cloneGrillaIntoVistaForm();
            var n = $('#lw-grilla-config-tbody input[type=checkbox][name*="[visible]"]:checked').length;
            if (n < 1) {
                alert('Deje al menos una columna visible antes de guardar la vista.');
                return false;
            }
        });

        $('#btn-lw-eliminar-vista-ref').on('click', function () {
            if (confirm('¿Eliminar esta vista?')) {
                $('#form-lw-eliminar-vista').trigger('submit');
            }
        });

        $('#form-filtros-comprobante-proveedor').on('submit', function () {
            syncColumnasHidden();
            if (typeof window.lwOrdenEnsureInForm === 'function') {
                window.lwOrdenEnsureInForm();
            }
            if (typeof window.lwOrdenReindex === 'function') {
                window.lwOrdenReindex();
            }
            if (typeof window.lwGroupEnsureInForm === 'function') {
                window.lwGroupEnsureInForm();
            }
            if (typeof window.lwGroupBeforeSubmit === 'function') {
                window.lwGroupBeforeSubmit();
            }
            var hay = typeof window.lwQbeBeforeSubmit === 'function'
                ? window.lwQbeBeforeSubmit()
                : false;
            if (hay && $('#filtro_busqueda_rapida').val() !== '1') {
                $('#filtro_modo').val('qbe');
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
