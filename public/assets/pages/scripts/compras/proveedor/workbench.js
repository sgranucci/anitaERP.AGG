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
    }

    $(function () {
        if (!$('#form-filtros-proveedor').length || !$('.lw-workbench').length) {
            return;
        }

        $('#lw-search-rapida').on('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                $('#filtro_busqueda_rapida').val('1');
                $('#filtro_modo').val('todos');
                $('#filtro_valor').val($(this).val());
                syncColumnasHidden();
                $('#form-filtros-proveedor').trigger('submit');
            }
        });

        $('#btn-lw-buscar-rapida').on('click', function () {
            $('#filtro_busqueda_rapida').val('1');
            $('#filtro_modo').val('todos');
            $('#filtro_valor').val($('#lw-search-rapida').val());
            syncColumnasHidden();
            $('#form-filtros-proveedor').trigger('submit');
        });

        $('#btn-lw-aplicar-qbe').on('click', function () {
            $('#filtro_modo').val('qbe');
            $('#filtro_busqueda_rapida').val('');
            syncColumnasHidden();
        });

        $('#lw-qbe-panel').on('keydown', 'input, select', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                $('#filtro_modo').val('qbe');
                $('#filtro_busqueda_rapida').val('');
                syncColumnasHidden();
                $('#form-filtros-proveedor').trigger('submit');
            }
        });

        $('#lw-vista-select').on('change', function () {
            var id = $(this).val();
            var base = $(this).data('base-url') || window.location.pathname;
            if (!id) {
                // Forzar Vista estándar (no re-aplicar la vista marcada como default).
                window.location = base + (base.indexOf('?') >= 0 ? '&' : '?') + 'vista_estandar=1';
                return;
            }
            window.location = base + (base.indexOf('?') >= 0 ? '&' : '?') + 'vista_id=' + encodeURIComponent(id);
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

        // —— QBE criterios (Campo + Operador + Valor) ——
        var $qbePanel = $('#lw-qbe-panel');
        var qbeCampos = [];
        var opsMap = { texto: {}, entero: {}, booleano: {} };
        try {
            qbeCampos = JSON.parse($qbePanel.attr('data-campos') || '[]');
            opsMap.texto = JSON.parse($qbePanel.attr('data-ops-texto') || '{}');
            opsMap.entero = JSON.parse($qbePanel.attr('data-ops-entero') || '{}');
            opsMap.bool = JSON.parse($qbePanel.attr('data-ops-bool') || '{}');
            opsMap.booleano = opsMap.bool;
        } catch (e) {}

        function opsParaTipo(tipo) {
            return opsMap[tipo] || opsMap.texto;
        }

        function fillOps($sel, tipo, selected) {
            var ops = opsParaTipo(tipo);
            $sel.empty();
            Object.keys(ops).forEach(function (k) {
                $sel.append($('<option/>').val(k).text(ops[k]));
            });
            if (selected && ops[selected]) {
                $sel.val(selected);
            }
        }

        function toggleValor($row) {
            var op = $row.find('.lw-qbe-op').val();
            var $val = $row.find('.lw-qbe-valor');
            if (op === 'vacio') {
                $val.val('').prop('disabled', true);
            } else {
                $val.prop('disabled', false);
            }
        }

        function reindexQbe() {
            $('#lw-qbe-criterios .lw-qbe-row').each(function (i) {
                $(this).find('select, input').each(function () {
                    var name = $(this).attr('name');
                    if (name) {
                        $(this).attr('name', name.replace(/qbe\[\d+]/, 'qbe[' + i + ']'));
                    }
                });
            });
        }

        $qbePanel.on('change', '.lw-qbe-campo', function () {
            var $row = $(this).closest('.lw-qbe-row');
            var tipo = $(this).find('option:selected').data('type') || 'texto';
            fillOps($row.find('.lw-qbe-op'), tipo, 'contiene');
            toggleValor($row);
        });

        $qbePanel.on('change', '.lw-qbe-op', function () {
            toggleValor($(this).closest('.lw-qbe-row'));
        });

        $qbePanel.on('click', '.lw-qbe-remove', function () {
            var $rows = $('#lw-qbe-criterios .lw-qbe-row');
            if ($rows.length <= 1) {
                $rows.find('.lw-qbe-valor').val('');
                return;
            }
            $(this).closest('.lw-qbe-row').remove();
            reindexQbe();
        });

        $('#btn-lw-add-criterio').on('click', function () {
            var tpl = document.getElementById('lw-qbe-row-template');
            if (!tpl) {
                return;
            }
            var i = $('#lw-qbe-criterios .lw-qbe-row').length;
            var html = tpl.innerHTML.replace(/__i__/g, String(i));
            var $row = $(html);
            var $campo = $row.find('.lw-qbe-campo');
            qbeCampos.forEach(function (c) {
                $campo.append($('<option/>').val(c.key).attr('data-type', c.type).text(c.label));
            });
            fillOps($row.find('.lw-qbe-op'), 'texto', 'contiene');
            $('#lw-qbe-criterios').append($row);
        });

        $('#form-filtros-proveedor').on('submit', function () {
            syncColumnasHidden();
            reindexQbe();
            $('#lw-qbe-criterios .lw-qbe-valor:disabled').prop('disabled', false);
            var hay = false;
            $('#lw-qbe-criterios .lw-qbe-row').each(function () {
                var op = $(this).find('.lw-qbe-op').val();
                var v = $.trim($(this).find('.lw-qbe-valor').val() || '');
                if (op === 'vacio' || v !== '') {
                    hay = true;
                    return false;
                }
            });
            if (hay && $('#filtro_busqueda_rapida').val() !== '1') {
                $('#filtro_modo').val('qbe');
            }
        });
    });
})(jQuery);
