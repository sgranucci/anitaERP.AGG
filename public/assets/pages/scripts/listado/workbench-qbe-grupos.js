/**
 * QBE multi-grupo (AND/OR/NOT) compartido por workbench cliente/proveedor.
 * Requiere #lw-qbe-panel con data-campos / data-ops-*.
 */
(function (window, $) {
    'use strict';

    function initListadoQbeGrupos() {
        var $panel = $('#lw-qbe-panel');
        if (!$panel.length) {
            return;
        }

        var qbeCampos = [];
        var opsMap = { texto: {}, entero: {}, booleano: {}, fecha: {}, decimal: {} };
        try {
            qbeCampos = JSON.parse($panel.attr('data-campos') || '[]');
            opsMap.texto = JSON.parse($panel.attr('data-ops-texto') || '{}');
            opsMap.entero = JSON.parse($panel.attr('data-ops-entero') || '{}');
            opsMap.bool = JSON.parse($panel.attr('data-ops-bool') || '{}');
            opsMap.booleano = opsMap.bool;
            opsMap.fecha = JSON.parse($panel.attr('data-ops-fecha') || '{}');
            opsMap.decimal = JSON.parse($panel.attr('data-ops-decimal') || '{}');
        } catch (e) {}

        var maxGrupos = parseInt($('#lw-qbe-grupos').attr('data-max-grupos') || '8', 10) || 8;

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

        function toggleFormula($row) {
            var campo = $row.find('.lw-qbe-campo').val();
            var $wrap = $row.find('.lw-qbe-formula-wrap');
            var esFormula = campo === '__formula__';
            if (esFormula) {
                $wrap.removeClass('d-none');
            } else {
                $wrap.addClass('d-none');
                $row.find('.lw-qbe-formula').val('');
            }
        }

        function opDefault(tipo) {
            if (tipo === 'fecha' || tipo === 'decimal') {
                return 'entre';
            }
            if (tipo === 'entero' || tipo === 'booleano') {
                return 'igual';
            }
            return 'contiene';
        }

        function syncInputTipo($row) {
            var esFecha = tipoParaFila($row) === 'fecha';
            $row.find('.lw-qbe-valor, .lw-qbe-valor-hasta').attr('type', esFecha ? 'date' : 'text');
        }

        function tipoParaFila($row) {
            var campo = $row.find('.lw-qbe-campo').val();
            if (campo === '__formula__') {
                var f = ($row.find('.lw-qbe-formula').val() || '').trim();
                if (/^LENGTH\s*\(/i.test(f) || /^\d/.test(f)) {
                    return 'entero';
                }
                return 'texto';
            }
            return $row.find('.lw-qbe-campo option:selected').data('type') || 'texto';
        }

        function toggleValor($row) {
            var op = $row.find('.lw-qbe-op').val();
            var tipo = tipoParaFila($row);
            var esRango = tipo === 'fecha' || tipo === 'decimal';
            var $val = $row.find('.lw-qbe-valor');
            var $hastaWrap = $row.find('.lw-qbe-hasta-wrap');
            var $label = $row.find('.lw-qbe-valor-label');
            if (op === 'vacio') {
                $val.val('').prop('disabled', true);
                $hastaWrap.addClass('d-none');
                $label.text('Valor');
            } else {
                $val.prop('disabled', false);
                if (op === 'entre') {
                    $hastaWrap.removeClass('d-none');
                    $label.text(esRango ? 'Desde' : 'Valor');
                } else {
                    $hastaWrap.addClass('d-none');
                    $row.find('.lw-qbe-valor-hasta').val('');
                    if (!esRango) {
                        $label.text('Valor');
                    } else if (op === 'menor' || op === 'menor_igual') {
                        $label.text('Hasta');
                    } else if (op === 'mayor' || op === 'mayor_igual') {
                        $label.text('Desde');
                    } else {
                        $label.text(tipo === 'fecha' ? 'Día' : 'Importe');
                    }
                }
            }
        }

        function syncEntreGruposHidden() {
            var logic = 'and';
            var $btns = $('#lw-qbe-grupos .lw-qbe-entre-toggle').first().find('.active');
            if ($btns.length && $btns.data('logic') === 'or') {
                logic = 'or';
            }
            // Prefer last toggled / consensus: if any entre says or and active, use first toggle's active
            $('#lw-qbe-grupos .lw-qbe-entre-toggle').each(function () {
                var $a = $(this).find('.active');
                if ($a.length) {
                    logic = $a.data('logic') === 'or' ? 'or' : 'and';
                    return false;
                }
            });
            $('#lw-qbe-entre-grupos').val(logic);
            $('#lw-qbe-grupos .lw-qbe-entre-toggle').each(function () {
                $(this).find('[data-logic="and"]').toggleClass('active', logic === 'and');
                $(this).find('[data-logic="or"]').toggleClass('active', logic === 'or');
            });
        }

        function reindexQbe() {
            var g = 0;
            $('#lw-qbe-grupos > .lw-qbe-entre, #lw-qbe-grupos > .lw-qbe-grupo').each(function () {
                // skip: we iterate grupos only below
            });
            $('#lw-qbe-grupos .lw-qbe-grupo').each(function (gi) {
                var $grupo = $(this);
                $grupo.attr('data-grupo-idx', gi);
                $grupo.find('.lw-qbe-grupo-titulo').text('Grupo ' + (gi + 1));
                $grupo.find('.lw-qbe-grupo-logic').attr('name', 'qbe[grupos][' + gi + '][logic]');
                $grupo.find('input[type=hidden][name*="[not]"]').attr('name', 'qbe[grupos][' + gi + '][not]');
                var $not = $grupo.find('.lw-qbe-grupo-not');
                $not.attr('name', 'qbe[grupos][' + gi + '][not]');
                $not.attr('id', 'lw-qbe-not-' + gi);
                $grupo.find('label[for^="lw-qbe-not-"]').attr('for', 'lw-qbe-not-' + gi);

                $grupo.find('.lw-qbe-criterios-grupo .lw-qbe-row').each(function (ci) {
                    $(this).find('select, input').each(function () {
                        var name = $(this).attr('name');
                        if (!name) {
                            return;
                        }
                        name = name.replace(/qbe\[grupos\]\[\d+\]/, 'qbe[grupos][' + gi + ']');
                        name = name.replace(/\[criterios\]\[\d+\]/, '[criterios][' + ci + ']');
                        $(this).attr('name', name);
                    });
                });
                g = gi + 1;
            });
            // Show/hide entre separators: one before each group after first
            var $nodes = $('#lw-qbe-grupos').children();
            // Rebuild entre visibility: remove orphan entres, ensure entre before groups 2+
            $('#lw-qbe-grupos > .lw-qbe-entre').remove();
            $('#lw-qbe-grupos .lw-qbe-grupo').each(function (gi) {
                if (gi === 0) {
                    return;
                }
                var $entre = $(
                    '<div class="lw-qbe-entre text-center my-2">' +
                    '<div class="btn-group btn-group-sm lw-qbe-entre-toggle" role="group">' +
                    '<button type="button" class="btn btn-outline-secondary lw-entre-and" data-logic="and">Y</button>' +
                    '<button type="button" class="btn btn-outline-secondary lw-entre-or" data-logic="or">O</button>' +
                    '</div></div>'
                );
                $(this).before($entre);
            });
            syncEntreGruposHidden();
            void g;
        }

        function buildRowHtml(gi, ci) {
            var tpl = document.getElementById('lw-qbe-row-template');
            if (!tpl) {
                return $();
            }
            var html = tpl.innerHTML.replace(/__g__/g, String(gi)).replace(/__i__/g, String(ci));
            var $row = $(html);
            var $campo = $row.find('.lw-qbe-campo');
            qbeCampos.forEach(function (c) {
                $campo.append($('<option/>').val(c.key).attr('data-type', c.type).text(c.label));
            });
            $campo.append($('<option/>').val('__formula__').attr('data-type', 'formula').text('Fórmula…'));
            fillOps($row.find('.lw-qbe-op'), 'texto', 'contiene');
            return $row;
        }

        $panel.on('change', '.lw-qbe-campo', function () {
            var $row = $(this).closest('.lw-qbe-row');
            toggleFormula($row);
            var tipo = tipoParaFila($row);
            fillOps($row.find('.lw-qbe-op'), tipo, opDefault(tipo));
            syncInputTipo($row);
            toggleValor($row);
        });

        $panel.on('input change', '.lw-qbe-formula', function () {
            var $row = $(this).closest('.lw-qbe-row');
            if ($row.find('.lw-qbe-campo').val() !== '__formula__') {
                return;
            }
            var tipo = tipoParaFila($row);
            var cur = $row.find('.lw-qbe-op').val();
            fillOps($row.find('.lw-qbe-op'), tipo, cur);
            toggleValor($row);
        });

        $panel.on('change', '.lw-qbe-op', function () {
            toggleValor($(this).closest('.lw-qbe-row'));
        });

        $panel.on('click', '.lw-qbe-remove', function () {
            var $box = $(this).closest('.lw-qbe-criterios-grupo');
            var $rows = $box.find('.lw-qbe-row');
            if ($rows.length <= 1) {
                $rows.find('.lw-qbe-valor').val('');
                $rows.find('.lw-qbe-valor-hasta').val('');
                return;
            }
            $(this).closest('.lw-qbe-row').remove();
            reindexQbe();
        });

        $panel.on('click', '.lw-qbe-add-criterio-grupo', function () {
            var $grupo = $(this).closest('.lw-qbe-grupo');
            var gi = parseInt($grupo.attr('data-grupo-idx') || '0', 10);
            var ci = $grupo.find('.lw-qbe-row').length;
            $grupo.find('.lw-qbe-criterios-grupo').append(buildRowHtml(gi, ci));
            reindexQbe();
        });

        $panel.on('click', '.lw-qbe-remove-grupo', function () {
            var $grupos = $('#lw-qbe-grupos .lw-qbe-grupo');
            if ($grupos.length <= 1) {
                $grupos.find('.lw-qbe-valor').val('');
                $grupos.find('.lw-qbe-valor-hasta').val('');
                $grupos.find('.lw-qbe-grupo-not').prop('checked', false);
                return;
            }
            $(this).closest('.lw-qbe-grupo').remove();
            reindexQbe();
        });

        $panel.on('click', '.lw-qbe-entre-toggle .btn', function () {
            var logic = $(this).data('logic') === 'or' ? 'or' : 'and';
            $('#lw-qbe-entre-grupos').val(logic);
            $('#lw-qbe-grupos .lw-qbe-entre-toggle .btn').removeClass('active');
            $('#lw-qbe-grupos .lw-qbe-entre-toggle [data-logic="' + logic + '"]').addClass('active');
        });

        $('#btn-lw-add-grupo').on('click', function () {
            var n = $('#lw-qbe-grupos .lw-qbe-grupo').length;
            if (n >= maxGrupos) {
                alert('Máximo ' + maxGrupos + ' grupos.');
                return;
            }
            var tpl = document.getElementById('lw-qbe-grupo-template');
            if (!tpl) {
                return;
            }
            var html = tpl.innerHTML.replace(/__g__/g, String(n));
            var $frag = $(html);
            // template includes entre + grupo; append both
            $('#lw-qbe-grupos').append($frag);
            var $nuevo = $('#lw-qbe-grupos .lw-qbe-grupo').last();
            $nuevo.find('.lw-qbe-criterios-grupo').append(buildRowHtml(n, 0));
            reindexQbe();
        });

        $('#btn-lw-add-criterio').on('click', function () {
            var $grupo = $('#lw-qbe-grupos .lw-qbe-grupo').first();
            if (!$grupo.length) {
                $('#btn-lw-add-grupo').trigger('click');
                return;
            }
            $grupo.find('.lw-qbe-add-criterio-grupo').trigger('click');
        });

        window.lwQbeReindex = reindexQbe;
        window.lwQbeBeforeSubmit = function () {
            reindexQbe();
            $('#lw-qbe-criterios-grupo .lw-qbe-valor:disabled, .lw-qbe-valor:disabled').prop('disabled', false);
            var hay = false;
            $('#lw-qbe-grupos .lw-qbe-row').each(function () {
                var op = $(this).find('.lw-qbe-op').val();
                var v = $.trim($(this).find('.lw-qbe-valor').val() || '');
                var h = $.trim($(this).find('.lw-qbe-valor-hasta').val() || '');
                if (op === 'vacio' || op === 'entre' || v !== '' || h !== '') {
                    hay = true;
                    return false;
                }
            });
            return hay;
        };

        // init toggle valor on existing rows
        $panel.find('.lw-qbe-row').each(function () {
            syncInputTipo($(this));
            toggleValor($(this));
        });
        syncEntreGruposHidden();
    }

    window.initListadoQbeGrupos = initListadoQbeGrupos;
})(window, jQuery);
