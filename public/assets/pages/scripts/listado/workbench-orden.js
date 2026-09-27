/**
 * Ordenamiento multi-criterio (chips gráficos) — compartido cliente/proveedor.
 * Monta el panel en el modal de grilla y lo devuelve al form antes de enviar.
 */
(function (window, $) {
    'use strict';

    function initListadoOrden() {
        var $panel = $('#lw-orden-panel');
        if (!$panel.length) {
            return;
        }

        var ordenCampos = [];
        var ordenMax = 5;
        try {
            ordenCampos = JSON.parse($panel.attr('data-campos') || '[]');
            ordenMax = parseInt($panel.attr('data-max') || '5', 10) || 5;
        } catch (e) {}

        function reindexOrden() {
            var $rows = $('#lw-orden-criterios .lw-orden-chip');
            $rows.each(function (i) {
                var $row = $(this);
                $row.attr('data-idx', i);
                $row.find('.lw-orden-prio').text(String(i + 1));
                $row.find('select, input').each(function () {
                    var name = $(this).attr('name');
                    if (name) {
                        $(this).attr('name', name.replace(/sort\[\d+]/, 'sort[' + i + ']'));
                    }
                });
                var campo = $row.find('.lw-orden-campo').val() || '';
                $row.toggleClass('lw-orden-chip--on', campo !== '');
                $row.toggleClass('lw-orden-chip--idle', campo === '');
            });
            var hay = $rows.filter(function () {
                return !!($(this).find('.lw-orden-campo').val() || '');
            }).length > 0;
            $('#lw-orden-empty').toggleClass('d-none', hay);
            $('#btn-lw-add-orden').prop('disabled', $rows.length >= ordenMax);
        }

        function ensureOrdenInForm() {
            if ($('#modal-lw-grilla').hasClass('show') && $panel.closest('#lw-orden-modal-mount').length) {
                return;
            }
            var $slot = $('#lw-orden-qbe-slot');
            if ($slot.length && $panel.length && !$slot.find('#lw-orden-panel').length) {
                $slot.append($panel);
            }
            $('#lw-orden-modal-placeholder').removeClass('d-none');
            $('#lw-orden-qbe-away').addClass('d-none');
        }

        function mountOrdenInModal() {
            var $mount = $('#lw-orden-modal-mount');
            if (!$mount.length || !$panel.length) {
                return;
            }
            $mount.append($panel);
            $('#lw-orden-modal-placeholder').addClass('d-none');
            $('#lw-orden-qbe-away').removeClass('d-none');
            reindexOrden();
        }

        function setDir($chip, dir) {
            dir = dir === 'desc' ? 'desc' : 'asc';
            $chip.find('.lw-orden-dir').val(dir);
            $chip.find('.lw-orden-dir-btn').removeClass('is-active');
            $chip.find('.lw-orden-dir-btn[data-dir="' + dir + '"]').addClass('is-active');
            var $pill = $chip.find('.lw-orden-dir-pill');
            $pill.removeClass('is-asc is-desc').addClass(dir === 'desc' ? 'is-desc' : 'is-asc');
            $pill.html(
                '<i class="fa ' + (dir === 'desc' ? 'fa-long-arrow-down' : 'fa-long-arrow-up') + '"></i> ' +
                (dir === 'desc' ? 'Desc' : 'Asc')
            );
        }

        $panel.on('click', '.lw-orden-dir-btn', function (e) {
            e.preventDefault();
            setDir($(this).closest('.lw-orden-chip'), $(this).data('dir'));
        });

        $panel.on('change', '.lw-orden-campo', function () {
            reindexOrden();
        });

        $panel.on('click', '.lw-orden-remove', function () {
            var $rows = $('#lw-orden-criterios .lw-orden-chip');
            if ($rows.length <= 1) {
                $rows.find('.lw-orden-campo').val('');
                setDir($rows.first(), 'asc');
                reindexOrden();
                return;
            }
            $(this).closest('.lw-orden-chip').remove();
            reindexOrden();
        });

        $panel.on('click', '.lw-orden-move-up', function () {
            var $chip = $(this).closest('.lw-orden-chip');
            var $prev = $chip.prev('.lw-orden-chip');
            if ($prev.length) {
                $chip.insertBefore($prev);
                reindexOrden();
            }
        });

        $panel.on('click', '.lw-orden-move-down', function () {
            var $chip = $(this).closest('.lw-orden-chip');
            var $next = $chip.next('.lw-orden-chip');
            if ($next.length) {
                $chip.insertAfter($next);
                reindexOrden();
            }
        });

        $(document).on('click', '#btn-lw-add-orden', function () {
            var n = $('#lw-orden-criterios .lw-orden-chip').length;
            if (n >= ordenMax) {
                alert('Máximo ' + ordenMax + ' criterios de ordenamiento.');
                return;
            }
            var tpl = document.getElementById('lw-orden-row-template');
            if (!tpl) {
                return;
            }
            var html = tpl.innerHTML.replace(/__i__/g, String(n));
            var $row = $(html);
            var $campo = $row.find('.lw-orden-campo');
            $campo.append($('<option/>').val('').text('Elegir campo…'));
            ordenCampos.forEach(function (c) {
                $campo.append(
                    $('<option/>').val(c.key).attr('data-type', c.type || 'texto').text(c.label)
                );
            });
            $('#lw-orden-criterios').append($row);
            reindexOrden();
        });

        $('a[href="#pane-grilla-ordenar"]').on('shown.bs.tab', function () {
            mountOrdenInModal();
        });

        $('#modal-lw-grilla').on('hidden.bs.modal', function () {
            ensureOrdenInForm();
        });

        $(document).on('click', '#btn-lw-aplicar-orden-modal', function () {
            ensureOrdenInForm();
            reindexOrden();
            var $form = $panel.closest('form');
            if (!$form.length) {
                $form = $('form[id^="form-filtros-"]').first();
            }
            $('#filtro_busqueda_rapida').val('');
            if ($('#filtro_modo').length && ($('#filtro_modo').val() === '' || $('#filtro_modo').val() === 'todos')) {
                // Mantener filtros actuales; el sort viaja igual
            }
            $('#modal-lw-grilla').modal('hide');
            setTimeout(function () {
                ensureOrdenInForm();
                if (typeof window.lwGroupEnsureInForm === 'function') {
                    window.lwGroupEnsureInForm();
                }
                if ($form.length) {
                    $form.trigger('submit');
                }
            }, 200);
        });

        // API para workbench.js
        window.lwOrdenReindex = reindexOrden;
        window.lwOrdenEnsureInForm = ensureOrdenInForm;

        reindexOrden();
    }

    window.initListadoOrden = initListadoOrden;
})(window, jQuery);
