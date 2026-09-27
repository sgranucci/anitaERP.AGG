/**
 * Diseñador de vista: preview A (wireframe) + B (muestra AJAX).
 * Lee layout del modal de grilla + sort/group del form.
 */
(function (window, $) {
    'use strict';

    var debounceTimer = null;
    var previewSeq = 0;

    function layoutDesdeModal() {
        var layout = [];
        $('#lw-grilla-config-tbody tr.lw-grilla-fila').each(function () {
            var $tr = $(this);
            layout.push({
                key: String($tr.data('key') || ''),
                titulo: $tr.find('input[name*="[titulo]"]').val() || '',
                visible: $tr.find('input[type=checkbox][name*="[visible]"]').is(':checked'),
                ancho: parseInt($tr.find('input[name*="[ancho]"]').val(), 10) || 120,
                alinea: $tr.find('select[name*="[alinea]"]').val() || 'izquierda'
            });
        });
        return layout;
    }

    function ordenDesdePanel() {
        var orden = [];
        $('#lw-orden-criterios .lw-orden-chip').each(function () {
            var campo = $(this).find('.lw-orden-campo').val() || '';
            if (!campo) {
                return;
            }
            orden.push({
                campo: campo,
                dir: $(this).find('.lw-orden-dir').val() || 'asc'
            });
        });
        return orden;
    }

    function agruparDesdePanel() {
        var g = [];
        $('#lw-group-criterios .lw-group-campo').each(function () {
            var v = $(this).val() || '';
            if (v) {
                g.push(v);
            }
        });
        return g;
    }

    function renderWireframe(layout, orden, agrupar) {
        var $wire = $('#lw-disenador-wire');
        var $chips = $('#lw-disenador-chips');
        if (!$wire.length) {
            return;
        }
        var visibles = layout.filter(function (c) { return c.visible && c.key; });
        $chips.empty();
        if (orden.length === 0 && agrupar.length === 0) {
            $chips.append('<span class="lw-disenador-chip lw-disenador-chip--muted">Sin orden ni agrupación</span>');
        }
        orden.forEach(function (o, i) {
            var dir = o.dir === 'desc' ? '↓' : '↑';
            var label = $('#lw-orden-criterios .lw-orden-campo').filter(function () {
                return $(this).val() === o.campo;
            }).first().find('option:selected').text() || o.campo;
            $chips.append(
                $('<span class="lw-disenador-chip lw-disenador-chip--orden"/>')
                    .text('Orden ' + (i + 1) + ': ' + label + ' ' + dir)
            );
        });
        agrupar.forEach(function (campo, i) {
            var label = $('#lw-group-criterios .lw-group-campo').filter(function () {
                return $(this).val() === campo;
            }).first().find('option:selected').text() || campo;
            $chips.append(
                $('<span class="lw-disenador-chip lw-disenador-chip--group"/>')
                    .text('Grupo ' + (i + 1) + ': ' + label)
            );
        });
        $chips.append(
            $('<span class="lw-disenador-chip"/>').text(visibles.length + ' columnas')
        );

        if (visibles.length === 0) {
            $wire.html('<div class="lw-disenador-wire-empty text-muted small">Ninguna columna visible.</div>');
            return;
        }
        var html = '<div class="lw-disenador-wire-cols">';
        visibles.forEach(function (c) {
            var w = Math.max(48, Math.min(160, Math.round((c.ancho || 120) * 0.55)));
            html += '<div class="lw-disenador-wire-col" style="width:' + w + 'px;min-width:' + w + 'px;" title="' +
                $('<div/>').text(c.titulo || c.key).html() + '">';
            html += '<div class="lw-disenador-wire-th">' + $('<div/>').text(c.titulo || c.key).html() + '</div>';
            html += '<div class="lw-disenador-wire-bar"></div>';
            html += '<div class="lw-disenador-wire-bar lw-disenador-wire-bar--short"></div>';
            html += '<div class="lw-disenador-wire-bar"></div>';
            html += '</div>';
        });
        html += '</div>';
        $wire.html(html);
    }

    function renderSample(payload) {
        var $thead = $('#lw-disenador-sample-thead');
        var $tbody = $('#lw-disenador-sample-tbody');
        var $meta = $('#lw-disenador-sample-meta');
        if (!$thead.length) {
            return;
        }
        if (!payload || typeof payload !== 'object') {
            $tbody.html('<tr><td class="text-danger small text-center py-3">No se pudo cargar la muestra.</td></tr>');
            return;
        }
        var cols = payload.columnas || [];
        var filas = payload.filas || [];
        if (cols.length === 0) {
            $thead.empty();
            $tbody.html('<tr><td class="text-muted small text-center py-3">Sin columnas visibles.</td></tr>');
            $meta.html('<span class="text-muted small">Sin muestra</span>');
            return;
        }
        var th = '<tr>';
        cols.forEach(function (c) {
            th += '<th style="white-space:nowrap;">' + $('<div/>').text(c.titulo || c.key).html() + '</th>';
        });
        th += '</tr>';
        $thead.html(th);

        if (filas.length === 0) {
            $tbody.html(
                '<tr><td colspan="' + cols.length + '" class="text-muted small text-center py-3">Sin filas para la muestra (ajustá filtros).</td></tr>'
            );
        } else {
            var tb = '';
            var filasDatos = 0;
            filas.forEach(function (f) {
                if (f.type === 'header') {
                    var nivel = parseInt(f.nivel, 10) || 0;
                    tb += '<tr class="lw-group-header lw-group-nivel-' + nivel + '">';
                    tb += '<td colspan="' + cols.length + '">';
                    tb += '<i class="fa fa-folder-open-o"></i> <strong>' + escHtml(f.label || '') + ':</strong> ';
                    tb += escHtml(f.valor || '');
                    tb += ' <span class="lw-group-count">' + escHtml(formatEntero(f.count)) + '</span>';
                    tb += '</td></tr>';
                    return;
                }
                filasDatos++;
                tb += '<tr>';
                (f.cells || []).forEach(function (cell) {
                    tb += '<td>' + escHtml(cell == null ? '' : String(cell)) + '</td>';
                });
                tb += '</tr>';
            });
            $tbody.html(tb);
            if (filasDatos > 0) {
                payload.muestra = filasDatos;
            }
        }
        var muestra = payload.muestra != null ? payload.muestra : filas.length;
        var total = payload.total != null ? payload.total : '?';
        var metaHtml =
            '<span class="small"><strong>Muestra</strong> ' + muestra +
            ' de <strong>' + total + '</strong> (universo del filtro)</span>';
        var cortes = payload.cortes || {};
        if (cortes.activo) {
            metaHtml +=
                ' · <span class="small text-info"><strong>Cortes</strong> ' +
                (cortes.grupos_nivel0 || 0) + ' grupos / ' +
                (cortes.total || 0) + ' regs.</span>';
        }
        $meta.html(metaHtml);

        var $chips = $('#lw-disenador-chips');
        if ($chips.length && Array.isArray(payload.chips)) {
            payload.chips.forEach(function (text) {
                var t = String(text || '');
                if (t.indexOf('Orden ') === 0 || t.indexOf('Grupo ') === 0) {
                    return;
                }
                $chips.append(
                    $('<span class="lw-disenador-chip lw-disenador-chip--group"/>').text(t)
                );
            });
        }
    }

    function escHtml(value) {
        return $('<div/>').text(value == null ? '' : String(value)).html();
    }

    function formatEntero(n) {
        var v = parseInt(n, 10);
        if (isNaN(v)) {
            return '0';
        }
        return v.toLocaleString('es-AR');
    }

    function collectPreviewData() {
        var data = {};
        var $form = $('form[id^="form-filtros-"]').first();
        if ($form.length) {
            $form.serializeArray().forEach(function (pair) {
                var name = pair.name;
                if (name.indexOf('grilla[') === 0) {
                    return;
                }
                if (Object.prototype.hasOwnProperty.call(data, name)) {
                    if (!Array.isArray(data[name])) {
                        data[name] = [data[name]];
                    }
                    data[name].push(pair.value);
                } else {
                    data[name] = pair.value;
                }
            });
        }
        Object.keys(data).forEach(function (k) {
            if (k.indexOf('sort[') === 0 || k.indexOf('group[') === 0 || k.indexOf('grilla[') === 0) {
                delete data[k];
            }
        });
        layoutDesdeModal().forEach(function (fila, i) {
            data['grilla[' + i + '][key]'] = fila.key;
            data['grilla[' + i + '][titulo]'] = fila.titulo;
            data['grilla[' + i + '][visible]'] = fila.visible ? '1' : '0';
            data['grilla[' + i + '][ancho]'] = String(fila.ancho);
            data['grilla[' + i + '][alinea]'] = fila.alinea;
            data['grilla[' + i + '][orden]'] = String(i);
        });
        ordenDesdePanel().forEach(function (o, i) {
            data['sort[' + i + '][campo]'] = o.campo;
            data['sort[' + i + '][dir]'] = o.dir;
        });
        agruparDesdePanel().forEach(function (campo, i) {
            data['group[' + i + ']'] = campo;
        });
        return data;
    }

    function refreshPreview(immediate) {
        var $panel = $('#lw-disenador-preview');
        if (!$panel.length) {
            return;
        }
        var layout = layoutDesdeModal();
        var orden = ordenDesdePanel();
        var agrupar = agruparDesdePanel();
        renderWireframe(layout, orden, agrupar);

        var run = function () {
            var url = $panel.attr('data-preview-url');
            if (!url) {
                return;
            }
            var seq = ++previewSeq;
            $panel.addClass('is-loading');
            $.ajax({
                url: url,
                method: 'POST',
                data: collectPreviewData(),
                headers: {
                    'X-CSRF-TOKEN': $panel.attr('data-csrf') || '',
                    'Accept': 'application/json'
                }
            }).done(function (payload) {
                if (seq !== previewSeq) {
                    return;
                }
                renderSample(payload || {});
            }).fail(function () {
                if (seq !== previewSeq) {
                    return;
                }
                $('#lw-disenador-sample-tbody').html(
                    '<tr><td class="text-danger small text-center py-3">No se pudo cargar la muestra.</td></tr>'
                );
            }).always(function () {
                if (seq === previewSeq) {
                    $panel.removeClass('is-loading');
                }
            });
        };

        if (immediate) {
            clearTimeout(debounceTimer);
            run();
            return;
        }
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(run, 450);
    }

    function initListadoDisenadorPreview() {
        if (!$('#lw-disenador-preview').length) {
            return;
        }

        $(document).on('change input', '#lw-grilla-config-tbody input, #lw-grilla-config-tbody select', function () {
            refreshPreview(false);
        });
        $(document).on('click', '.lw-grilla-up, .lw-grilla-down', function () {
            setTimeout(function () { refreshPreview(false); }, 50);
        });
        $(document).on('change', '#lw-orden-panel .lw-orden-campo, #lw-orden-panel .lw-orden-dir, #lw-group-panel .lw-group-campo', function () {
            refreshPreview(false);
        });
        $(document).on('click', '#lw-orden-panel .lw-orden-dir-btn, #lw-orden-panel .lw-orden-move-up, #lw-orden-panel .lw-orden-move-down, #lw-orden-panel .lw-orden-remove, #lw-group-panel .lw-group-move-up, #lw-group-panel .lw-group-move-down, #lw-group-panel .lw-group-remove, #btn-lw-add-orden, #btn-lw-add-group', function () {
            setTimeout(function () { refreshPreview(false); }, 80);
        });
        $(document).on('click', '#btn-lw-preview-refresh', function () {
            refreshPreview(true);
        });

        $('#modal-lw-grilla').on('shown.bs.modal', function () {
            refreshPreview(true);
        });

        window.lwDisenadorRefreshPreview = refreshPreview;
    }

    window.initListadoDisenadorPreview = initListadoDisenadorPreview;
})(window, jQuery);
