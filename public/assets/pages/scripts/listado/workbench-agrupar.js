/**
 * Agrupación multi-nivel — chips + montaje en modal (como Ordenar).
 */
(function (window, $) {
    'use strict';

    function initListadoAgrupacion() {
        var $panel = $('#lw-group-panel');
        if (!$panel.length) {
            return;
        }

        var campos = [];
        var maxN = 2;
        try {
            campos = JSON.parse($panel.attr('data-campos') || '[]');
            maxN = parseInt($panel.attr('data-max') || '2', 10) || 2;
        } catch (e) {}

        function reindex() {
            var $rows = $('#lw-group-criterios .lw-group-chip');
            $rows.each(function (i) {
                var $row = $(this);
                $row.attr('data-idx', i);
                $row.find('.lw-orden-prio').text(String(i + 1));
                $row.find('.lw-group-nivel-tag, .lw-group-prio-label').text(i === 0 ? 'Grupo' : 'Luego');
                $row.find('select').attr('name', 'group[' + i + ']');
                var campo = $row.find('.lw-group-campo').val() || '';
                $row.toggleClass('lw-orden-chip--on', campo !== '');
                $row.toggleClass('lw-orden-chip--idle', campo === '');
            });
            var hay = $rows.filter(function () {
                return !!($(this).find('.lw-group-campo').val() || '');
            }).length > 0;
            $('#lw-group-empty').toggleClass('d-none', hay);
            $('#btn-lw-add-group').prop('disabled', $rows.length >= maxN);
        }

        function ensureGroupInForm() {
            if ($('#modal-lw-grilla').hasClass('show') && $panel.closest('#lw-group-modal-mount').length) {
                return;
            }
            var $slot = $('#lw-group-qbe-slot');
            if ($slot.length && $panel.length && !$slot.find('#lw-group-panel').length) {
                $slot.append($panel);
            }
            $('#lw-group-modal-placeholder').removeClass('d-none');
            $('#lw-group-qbe-away').addClass('d-none');
        }

        function mountGroupInModal() {
            var $mount = $('#lw-group-modal-mount');
            if (!$mount.length || !$panel.length) {
                return;
            }
            $mount.append($panel);
            $('#lw-group-modal-placeholder').addClass('d-none');
            $('#lw-group-qbe-away').removeClass('d-none');
            reindex();
        }

        $panel.on('change', '.lw-group-campo', function () {
            reindex();
        });

        $panel.on('click', '.lw-group-remove', function () {
            var $rows = $('#lw-group-criterios .lw-group-chip');
            if ($rows.length <= 1) {
                $rows.find('.lw-group-campo').val('');
                reindex();
                return;
            }
            $(this).closest('.lw-group-chip').remove();
            reindex();
        });

        $panel.on('click', '.lw-group-move-up', function () {
            var $chip = $(this).closest('.lw-group-chip');
            var $prev = $chip.prev('.lw-group-chip');
            if ($prev.length) {
                $chip.insertBefore($prev);
                reindex();
            }
        });

        $panel.on('click', '.lw-group-move-down', function () {
            var $chip = $(this).closest('.lw-group-chip');
            var $next = $chip.next('.lw-group-chip');
            if ($next.length) {
                $chip.insertAfter($next);
                reindex();
            }
        });

        $(document).on('click', '#btn-lw-add-group', function () {
            var n = $('#lw-group-criterios .lw-group-chip').length;
            if (n >= maxN) {
                alert('Máximo ' + maxN + ' niveles de agrupación.');
                return;
            }
            var tpl = document.getElementById('lw-group-row-template');
            if (!tpl) {
                return;
            }
            var html = tpl.innerHTML.replace(/__i__/g, String(n));
            var $row = $(html);
            var $campo = $row.find('.lw-group-campo');
            $campo.append($('<option/>').val('').text('Elegir campo…'));
            campos.forEach(function (c) {
                $campo.append($('<option/>').val(c.key).text(c.label));
            });
            $('#lw-group-criterios').append($row);
            reindex();
        });

        $('a[href="#pane-grilla-agrupar"]').on('shown.bs.tab', function () {
            mountGroupInModal();
        });

        $('#modal-lw-grilla').on('hidden.bs.modal', function () {
            ensureGroupInForm();
        });

        $(document).on('click', '#btn-lw-aplicar-group-modal', function () {
            ensureGroupInForm();
            reindex();
            var $form = $panel.closest('form');
            if (!$form.length) {
                $form = $('form[id^="form-filtros-"]').first();
            }
            $('#filtro_busqueda_rapida').val('');
            $('#modal-lw-grilla').modal('hide');
            setTimeout(function () {
                ensureGroupInForm();
                if (typeof window.lwOrdenEnsureInForm === 'function') {
                    window.lwOrdenEnsureInForm();
                }
                if ($form.length) {
                    $form.trigger('submit');
                }
            }, 200);
        });

        window.lwGroupReindex = reindex;
        window.lwGroupEnsureInForm = ensureGroupInForm;
        window.lwGroupBeforeSubmit = function () {
            ensureGroupInForm();
            reindex();
        };

        window.lwGroupCloneInto = function ($box) {
            $('#form-lw-grilla-vista').find('input[name^="group["], select[name^="group["]').remove();
            ensureGroupInForm();
            reindex();
            var gi = 0;
            $('#lw-group-criterios .lw-group-campo').each(function () {
                var v = $(this).val() || '';
                if (!v) {
                    return;
                }
                $box.append(
                    $('<input type="hidden">').attr('name', 'group[' + gi + ']').val(v)
                );
                gi++;
            });
        };

        reindex();
    }

    window.initListadoAgrupacion = initListadoAgrupacion;
})(window, jQuery);
