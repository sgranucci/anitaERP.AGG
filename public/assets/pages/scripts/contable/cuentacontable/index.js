$(function () {
    var $arbol = $('#pc-arbol');
    var cfg = window.pcWorkbench || null;

    if ($arbol.length) {
        $arbol.on('click', '.pc-nodo__toggle', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var $btn = $(this);
            if ($btn.hasClass('pc-nodo__toggle--empty')) {
                return;
            }
            toggleNodo($btn.closest('.pc-nodo'), $btn.attr('aria-expanded') !== 'true');
        });

        $('#pc-expandir-todo').on('click', function () {
            $arbol.find('.pc-nodo__hijos').prop('hidden', false);
            $arbol.find('.pc-nodo__toggle').not('.pc-nodo__toggle--empty')
                .attr('aria-expanded', 'true')
                .find('i').removeClass('fa-caret-right').addClass('fa-caret-down');
        });

        $('#pc-contraer-todo').on('click', function () {
            $arbol.find('.pc-nodo__hijos').prop('hidden', true);
            $arbol.find('.pc-nodo__toggle').not('.pc-nodo__toggle--empty')
                .attr('aria-expanded', 'false')
                .find('i').removeClass('fa-caret-down').addClass('fa-caret-right');
        });

        if (cfg) {
            iniciarInspector($arbol, cfg);
        }
    }

    $(document).on('click', '.eliminar-cuentacontable', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var $link = $(this);
        var url = $link.attr('href');
        var $fila = $link.closest('tr');
        var $nodo = $link.closest('.pc-nodo');

        if (typeof swal !== 'function') {
            if (!window.confirm('¿Está seguro que desea eliminar el registro?')) {
                return;
            }
            eliminarCuenta(url, $fila, $nodo);
            return;
        }

        swal({
            title: '¿Está seguro que desea eliminar el registro?',
            text: 'Esta acción no se puede deshacer.',
            icon: 'warning',
            buttons: {
                cancel: 'Cancelar',
                confirm: 'Aceptar'
            }
        }).then(function (value) {
            if (value) {
                eliminarCuenta(url, $fila, $nodo);
            }
        });
    });

    function toggleNodo($nodo, abrir) {
        var $btn = $nodo.children('.pc-nodo__row').find('.pc-nodo__toggle').not('.pc-nodo__toggle--empty');
        var $hijos = $nodo.children('.pc-nodo__hijos');
        if (!$hijos.length) {
            return;
        }
        $btn.attr('aria-expanded', abrir ? 'true' : 'false');
        $btn.find('i').toggleClass('fa-caret-down', abrir).toggleClass('fa-caret-right', !abrir);
        $hijos.prop('hidden', !abrir);
    }

    function iniciarInspector($arbol, cfg) {
        var $form = $('#pc-insp-form');
        var $vacio = $('#pc-insp-vacio');
        var $titulo = $('#pc-insp-titulo');
        var $estado = $('#pc-insp-estado');
        var parentInicial = '';

        $arbol.on('click', '.pc-nodo__row', function (e) {
            if ($(e.target).closest('a, button, .pc-nodo__toggle').length) {
                return;
            }
            abrirInspector($(this).closest('.pc-nodo'));
        });

        $arbol.on('keydown', '.pc-nodo__row', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                abrirInspector($(this).closest('.pc-nodo'));
            }
        });

        $('#pc-insp-cerrar').on('click', function () {
            cerrarInspector();
        });

        $('#pc-insp-niveles').on('click', '.pc-nivel', function () {
            if (this.disabled) {
                return;
            }
            marcarNivel($(this).data('nivel'));
            pintarPreview();
        });

        $('#pc-insp-nombre, #pc-insp-padre, #pc-insp-tipo, #pc-insp-rubro').on('input change', function () {
            pintarPreview();
            if (this.id === 'pc-insp-nombre') {
                $titulo.text($('#pc-insp-nombre').val() || 'Cuenta');
            }
        });

        $form.on('submit', function (e) {
            e.preventDefault();
            guardarInspector();
        });

        if (cfg.cuentaInicial) {
            var $ini = $arbol.find('.pc-nodo[data-id="' + cfg.cuentaInicial + '"]').first();
            if ($ini.length) {
                expandirHasta($ini);
                abrirInspector($ini);
                if ($ini[0].scrollIntoView) {
                    $ini[0].scrollIntoView({ block: 'center' });
                }
            }
        }

        function tokenCsrf() {
            return $('meta[name="csrf-token"]').attr('content')
                || $form.find('input[name="_token"]').val()
                || '';
        }

        function abrirInspector($nodo) {
            var id = String($nodo.data('id') || '');
            if (!id) {
                return;
            }
            $arbol.find('.pc-nodo__row').removeClass('is-selected');
            $nodo.children('.pc-nodo__row').addClass('is-selected');
            $vacio.prop('hidden', true);
            $form.prop('hidden', false);
            $('#pc-insp-cerrar').prop('hidden', false);

            $('#pc-insp-id').val(id);
            $('#pc-insp-nombre').val(String($nodo.attr('data-nombre') || ''));
            $('#pc-insp-tipo').val(String($nodo.attr('data-tipo') || '1'));
            $('#pc-insp-rubro').val(String($nodo.attr('data-rubro-id') || ''));
            $titulo.text(String($nodo.attr('data-nombre') || 'Cuenta'));
            $estado.text($nodo.attr('data-codigo') || '');

            marcarNivel(parseInt($nodo.attr('data-nivel'), 10) || 1);
            llenarPadres($nodo);
            parentInicial = String($('#pc-insp-padre').val() || '');

            var ficha = String(cfg.urlFicha || '').replace('__ID__', id);
            $('#pc-insp-ficha').attr('href', ficha);
            pintarPreview();
        }

        function cerrarInspector() {
            $arbol.find('.pc-nodo__row').removeClass('is-selected');
            $form.prop('hidden', true);
            $vacio.prop('hidden', false);
            $('#pc-insp-cerrar').prop('hidden', true);
            $titulo.text('Elegí una cuenta');
            $estado.text('');
        }

        function marcarNivel(nivel) {
            nivel = parseInt(nivel, 10) || 1;
            $('#pc-insp-nivel').val(nivel);
            $('#pc-insp-niveles .pc-nivel').each(function () {
                var n = parseInt($(this).data('nivel'), 10);
                $(this).toggleClass('is-on', n <= nivel);
                $(this).toggleClass('is-current', n === nivel);
            });
        }

        function llenarPadres($nodo) {
            var excludeId = String($nodo.data('id') || '');
            var actuales = {};
            $nodo.find('.pc-nodo').each(function () {
                actuales[String($(this).data('id'))] = true;
            });
            var $sel = $('#pc-insp-padre');
            var elegido = String($nodo.attr('data-parent-id') || '');
            if ($nodo.attr('data-padre-origen') !== 'manual') {
                elegido = '';
            }
            $sel.empty().append($('<option>', { value: '', text: 'Automático por código' }));
            $arbol.find('.pc-nodo').each(function () {
                var $n = $(this);
                var id = String($n.data('id') || '');
                if (!id || id === excludeId || actuales[id]) {
                    return;
                }
                if (String($n.data('tipo')) === '3') {
                    return;
                }
                var nivel = parseInt($n.data('nivel'), 10) || 1;
                var pad = new Array(Math.max(0, nivel - 1) + 1).join('· ');
                $sel.append($('<option>', {
                    value: id,
                    text: pad + ($n.data('codigo') || '') + ' ' + ($n.data('nombre') || '')
                }));
            });
            $sel.val(elegido);
        }

        function lineaPreview(opts) {
            var pad = (opts.depth || 0) * 14;
            var $li = $('<li>', {
                class: 'pc-preview__linea' + (opts.cls ? ' ' + opts.cls : ''),
                css: { paddingLeft: pad + 'px' }
            });
            $li.append($('<span>', { class: 'pc-preview__n', text: 'N' + opts.nivel }));
            $li.append($('<span>', { class: 'pc-preview__cod', text: opts.codigo || '' }));
            $li.append($('<span>', { text: opts.nombre || '' }));
            return $li;
        }

        function pintarPreview() {
            var id = $('#pc-insp-id').val();
            var $nodo = $arbol.find('.pc-nodo[data-id="' + id + '"]').first();
            var $ul = $('#pc-preview-arbol').empty();
            if (!$nodo.length) {
                return;
            }
            var parentId = String($('#pc-insp-padre').val() || '');
            var nivel = parseInt($('#pc-insp-nivel').val(), 10) || 1;
            var crumbs = [];
            if (parentId) {
                var $p = $arbol.find('.pc-nodo[data-id="' + parentId + '"]').first();
                $p.parents('.pc-nodo').get().reverse().forEach(function (el) {
                    crumbs.push($(el));
                });
                if ($p.length) {
                    crumbs.push($p);
                }
            } else {
                $nodo.parents('.pc-nodo').get().reverse().forEach(function (el) {
                    crumbs.push($(el));
                });
            }
            crumbs.forEach(function ($c, i) {
                $ul.append(lineaPreview({
                    depth: i,
                    nivel: $c.data('nivel'),
                    codigo: $c.data('codigo'),
                    nombre: $c.data('nombre')
                }));
            });
            $ul.append(lineaPreview({
                depth: crumbs.length,
                nivel: nivel,
                codigo: $nodo.data('codigo'),
                nombre: $('#pc-insp-nombre').val() || $nodo.data('nombre'),
                cls: 'is-self'
            }));
            $nodo.children('.pc-nodo__hijos').children('.pc-nodo').each(function () {
                var $h = $(this);
                $ul.append(lineaPreview({
                    depth: crumbs.length + 1,
                    nivel: $h.data('nivel'),
                    codigo: $h.data('codigo'),
                    nombre: $h.data('nombre'),
                    cls: 'is-child'
                }));
            });
        }

        function expandirHasta($nodo) {
            $nodo.parents('.pc-nodo').each(function () {
                toggleNodo($(this), true);
            });
        }

        function guardarInspector() {
            if (!cfg.puedeEditar) {
                return;
            }
            var id = $('#pc-insp-id').val();
            var url = String(cfg.urlInspector || '').replace('__ID__', id);
            var parentId = $('#pc-insp-padre').val() || '';
            $estado.text('Guardando…');
            $('#pc-insp-guardar').prop('disabled', true);

            $.ajax({
                url: url,
                type: 'PUT',
                data: {
                    nombre: $('#pc-insp-nombre').val(),
                    nivel: $('#pc-insp-nivel').val(),
                    tipocuenta: $('#pc-insp-tipo').val(),
                    rubrocontable_id: $('#pc-insp-rubro').val(),
                    parent_id: parentId || null
                },
                headers: {
                    'X-CSRF-TOKEN': tokenCsrf(),
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).done(function (resp) {
                if (!resp || !resp.ok) {
                    $estado.text((resp && resp.error) || 'No se pudo guardar.');
                    return;
                }
                var $nodo = $arbol.find('.pc-nodo[data-id="' + id + '"]').first();
                aplicarNodo($nodo, resp.cuenta);
                $titulo.text(resp.cuenta.nombre);
                if (String(parentId) !== String(parentInicial)) {
                    var next = new URL(window.location.href);
                    next.searchParams.set('cuenta', id);
                    window.location.href = next.toString();
                    return;
                }
                $estado.text('Aplicado');
                pintarPreview();
            }).fail(function (xhr) {
                var msg = 'No se pudo guardar.';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    msg = xhr.responseJSON.error;
                } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                    var first = Object.values(xhr.responseJSON.errors)[0];
                    msg = Array.isArray(first) ? first[0] : String(first);
                }
                $estado.text(msg);
            }).always(function () {
                $('#pc-insp-guardar').prop('disabled', false);
            });
        }

        function aplicarNodo($nodo, cuenta) {
            if (!$nodo.length || !cuenta) {
                return;
            }
            $nodo.attr('data-nombre', cuenta.nombre);
            $nodo.attr('data-nivel', cuenta.nivel);
            $nodo.attr('data-tipo', cuenta.tipocuenta);
            $nodo.attr('data-rubro-id', cuenta.rubrocontable_id);
            var parent = cuenta.parent_id || '';
            $nodo.attr('data-parent-id', parent);
            $nodo.attr('data-padre-origen', parent ? 'manual' : 'codigo');

            var $row = $nodo.children('.pc-nodo__row');
            $row.find('.pc-nodo__nombre').text(cuenta.nombre);
            var rubro = $('#pc-insp-rubro option:selected').text();
            $row.find('.pc-nodo__meta').text('N' + cuenta.nivel + (rubro ? ' · ' + rubro : ''));
            var $badge = $row.find('.pc-nodo__tipo');
            $badge.text(cuenta.tipo_label || '');
            $badge.removeClass('badge-success badge-info badge-secondary');
            if (String(cuenta.tipocuenta) === '1') {
                $badge.addClass('badge-success');
            } else if (String(cuenta.tipocuenta) === '2') {
                $badge.addClass('badge-info');
            } else {
                $badge.addClass('badge-secondary');
            }
            $nodo.toggleClass('pc-nodo--total', String(cuenta.tipocuenta) === '3');
            $row.find('.pc-nodo__nombre').toggleClass('font-weight-bold', String(cuenta.tipocuenta) === '2');
        }
    }

    function eliminarCuenta(url, $fila, $nodo) {
        $.ajax({
            url: url,
            type: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            success: function (respuesta) {
                if (respuesta.mensaje === 'ok') {
                    if ($nodo.length) {
                        $nodo.remove();
                    } else {
                        $fila.remove();
                    }
                    if (window.Biblioteca && Biblioteca.notificaciones) {
                        Biblioteca.notificaciones('El registro fue eliminado correctamente', 'anitaERP', 'success');
                    }
                } else if (window.Biblioteca && Biblioteca.notificaciones) {
                    Biblioteca.notificaciones('No se pudo eliminar la cuenta.', 'anitaERP', 'error');
                }
            },
            error: function () {
                if (window.Biblioteca && Biblioteca.notificaciones) {
                    Biblioteca.notificaciones('No se pudo eliminar la cuenta.', 'anitaERP', 'error');
                }
            }
        });
    }
});
