/**
 * Workbench estándar: reordenar columnas (drag en thead) y ocultar (× en el encabezado).
 * Agregar columnas sigue en «Diseñar vista».
 *
 * Config: window.lwColumnasDnd = { url, layout, tabla?: '#tabla-paginada', form?: '#form-lw-columnas-dnd' }
 * Compat: window.lwEntregaColumnasDnd (alias).
 */
(function ($) {
    'use strict';

    var cfg = window.lwColumnasDnd || window.lwEntregaColumnasDnd || null;
    var dragKey = null;
    var didDrag = false;
    var tablaSel = (cfg && cfg.tabla) ? cfg.tabla : '#tabla-paginada';
    var formSel = (cfg && cfg.form) ? cfg.form : '#form-lw-columnas-dnd';

    function $tabla() {
        return $(tablaSel);
    }

    function thsVisibles() {
        return $tabla().find('thead tr').first().children('th[data-col-key]');
    }

    function keysVisiblesOrden() {
        return thsVisibles().map(function () {
            return $(this).attr('data-col-key');
        }).get();
    }

    function celdasMovibles($row) {
        // Acciones (última) y fijas (checkbox, etc.) quedan fuera del reorder/ocultar.
        return $row.children().filter(function () {
            var $el = $(this);
            return !$el.hasClass('lw-col-acciones') && !$el.hasClass('lw-col-fija');
        });
    }

    function moverColumnaDom(fromIdx, toIdx) {
        if (fromIdx === toIdx || fromIdx < 0 || toIdx < 0) {
            return;
        }
        $tabla().find('tr').each(function () {
            var $row = $(this);
            if ($row.hasClass('lw-group-header')) {
                return;
            }
            var $movable = celdasMovibles($row);
            if ($movable.length <= fromIdx || $movable.length <= toIdx) {
                return;
            }
            var $from = $movable.eq(fromIdx);
            var $to = $movable.eq(toIdx);
            if (fromIdx < toIdx) {
                $from.insertAfter($to);
            } else {
                $from.insertBefore($to);
            }
        });
    }

    function quitarColumnaDom(idx) {
        if (idx < 0) {
            return;
        }
        $tabla().find('tr').each(function () {
            var $row = $(this);
            if ($row.hasClass('lw-group-header')) {
                var $cell = $row.children('td[colspan]').first();
                if ($cell.length) {
                    var span = parseInt($cell.attr('colspan'), 10) || 1;
                    if (span > 1) {
                        $cell.attr('colspan', span - 1);
                    }
                }
                return;
            }
            var $movable = celdasMovibles($row);
            if ($movable.length > idx) {
                $movable.eq(idx).remove();
            }
        });
    }

    function layoutDesdeKeysVisibles(keysOrden) {
        var porKey = {};
        (cfg.layout || []).forEach(function (fila) {
            porKey[fila.key] = $.extend({}, fila);
        });
        var out = [];
        var orden = 0;
        keysOrden.forEach(function (key) {
            if (!porKey[key]) {
                return;
            }
            var f = porKey[key];
            f.visible = true;
            f.orden = orden++;
            out.push(f);
            delete porKey[key];
        });
        Object.keys(porKey).forEach(function (key) {
            var f = porKey[key];
            f.visible = false;
            f.orden = orden++;
            out.push(f);
        });
        return out;
    }

    function persistir(layout) {
        var $form = $(formSel);
        if (!$form.length || !cfg || !cfg.url) {
            return;
        }
        $form.find('input.lw-dnd-grilla').remove();
        layout.forEach(function (fila, i) {
            var base = 'grilla[' + i + ']';
            $form.append($('<input>', { type: 'hidden', class: 'lw-dnd-grilla', name: base + '[key]', value: fila.key }));
            $form.append($('<input>', { type: 'hidden', class: 'lw-dnd-grilla', name: base + '[titulo]', value: fila.titulo || '' }));
            $form.append($('<input>', { type: 'hidden', class: 'lw-dnd-grilla', name: base + '[ancho]', value: fila.ancho != null ? fila.ancho : 100 }));
            $form.append($('<input>', { type: 'hidden', class: 'lw-dnd-grilla', name: base + '[alinea]', value: fila.alinea || 'left' }));
            $form.append($('<input>', { type: 'hidden', class: 'lw-dnd-grilla', name: base + '[orden]', value: i }));
            $form.append($('<input>', { type: 'hidden', class: 'lw-dnd-grilla', name: base + '[visible]', value: fila.visible ? 1 : 0 }));
        });
        $form.trigger('submit');
    }

    var keyPendienteOcultar = null;
    var modalOcultarSel = '#modal-lw-ocultar-columna';

    function tituloDeColumna(key) {
        var titulo = '';
        (cfg.layout || []).some(function (fila) {
            if (fila.key === key && fila.titulo) {
                titulo = String(fila.titulo);
                return true;
            }
            return false;
        });
        if (titulo) {
            return titulo;
        }
        var $th = thsVisibles().filter('[data-col-key="' + key + '"]').first();
        var texto = $th.find('.lw-th-titulo, a.lw-sort-link').first().text()
            || $th.attr('title')
            || key;
        return String(texto).replace(/\s+/g, ' ').trim();
    }

    function ocultarColumna(key) {
        var keys = keysVisiblesOrden();
        if (keys.length <= 1) {
            window.alert('Deje al menos una columna visible. Para agregar otras use «Diseñar vista».');
            return;
        }
        var idx = keys.indexOf(key);
        if (idx < 0) {
            return;
        }
        keys.splice(idx, 1);
        quitarColumnaDom(idx);
        persistir(layoutDesdeKeysVisibles(keys));
    }

    function pedirConfirmacionOcultar(key) {
        var keys = keysVisiblesOrden();
        if (keys.length <= 1) {
            window.alert('Deje al menos una columna visible. Para agregar otras use «Diseñar vista».');
            return;
        }
        if (keys.indexOf(key) < 0) {
            return;
        }
        var $modal = $(modalOcultarSel);
        if (!$modal.length || typeof $modal.modal !== 'function') {
            // Fallback si el partial no incluyó el modal (p. ej. confirm nativo).
            if (window.confirm('¿Ocultar la columna «' + tituloDeColumna(key) + '»?\nPodés volver a mostrarla desde «Diseñar vista».')) {
                ocultarColumna(key);
            }
            return;
        }
        keyPendienteOcultar = key;
        $modal.find('#modal-lw-ocultar-columna-nombre').text(tituloDeColumna(key));
        $modal.modal('show');
    }

    $(function () {
        if (!cfg || !$tabla().length) {
            return;
        }

        $tabla().find('thead tr').first().children('th').last().addClass('lw-col-acciones');
        $tabla().find('tbody tr').not('.lw-group-header').each(function () {
            $(this).children('td').last().addClass('lw-col-acciones');
        });

        $(document).on('dragstart', tablaSel + ' thead th[data-col-key]', function (e) {
            if ($(e.target).closest('.lw-th-hide').length) {
                e.preventDefault();
                return;
            }
            var key = $(this).attr('data-col-key');
            if (!key) {
                return;
            }
            dragKey = key;
            didDrag = false;
            $(this).addClass('lw-th-dragging');
            var dt = e.originalEvent.dataTransfer;
            if (dt) {
                dt.effectAllowed = 'move';
                try {
                    dt.setData('text/plain', key);
                } catch (err) { /* IE */ }
            }
        });

        $(document).on('dragend', tablaSel + ' thead th[data-col-key]', function () {
            $(this).removeClass('lw-th-dragging');
            thsVisibles().removeClass('lw-th-drop-target');
            dragKey = null;
        });

        $(document).on('dragover', tablaSel + ' thead th[data-col-key]', function (e) {
            e.preventDefault();
            var key = $(this).attr('data-col-key');
            if (!dragKey || key === dragKey) {
                return;
            }
            thsVisibles().removeClass('lw-th-drop-target');
            $(this).addClass('lw-th-drop-target');
            if (e.originalEvent.dataTransfer) {
                e.originalEvent.dataTransfer.dropEffect = 'move';
            }
        });

        $(document).on('dragleave', tablaSel + ' thead th[data-col-key]', function () {
            $(this).removeClass('lw-th-drop-target');
        });

        $(document).on('drop', tablaSel + ' thead th[data-col-key]', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var targetKey = $(this).attr('data-col-key');
            thsVisibles().removeClass('lw-th-drop-target');
            if (!dragKey || !targetKey || dragKey === targetKey) {
                return;
            }
            didDrag = true;
            var keys = keysVisiblesOrden();
            var fromIdx = keys.indexOf(dragKey);
            var toIdx = keys.indexOf(targetKey);
            if (fromIdx < 0 || toIdx < 0) {
                return;
            }
            keys.splice(toIdx, 0, keys.splice(fromIdx, 1)[0]);
            moverColumnaDom(fromIdx, toIdx);
            persistir(layoutDesdeKeysVisibles(keys));
        });

        $(document).on('click', tablaSel + ' thead th[data-col-key] a.lw-sort-link', function (e) {
            if (didDrag) {
                e.preventDefault();
                e.stopPropagation();
                didDrag = false;
            }
        });

        $(document).on('click', tablaSel + ' thead .lw-th-hide', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var key = $(this).closest('th[data-col-key]').attr('data-col-key');
            if (!key) {
                return;
            }
            pedirConfirmacionOcultar(key);
        });

        $(document).on('click', '#btn-lw-ocultar-columna-confirmar', function (e) {
            e.preventDefault();
            var key = keyPendienteOcultar;
            keyPendienteOcultar = null;
            var $modal = $(modalOcultarSel);
            if ($modal.length && typeof $modal.modal === 'function') {
                $modal.modal('hide');
            }
            if (key) {
                ocultarColumna(key);
            }
        });

        $(document).on('hidden.bs.modal', modalOcultarSel, function () {
            keyPendienteOcultar = null;
        });

        // Evitar que el × arranque un drag del th
        $(document).on('mousedown', tablaSel + ' thead .lw-th-hide', function (e) {
            e.stopPropagation();
        });
    });
})(jQuery);
