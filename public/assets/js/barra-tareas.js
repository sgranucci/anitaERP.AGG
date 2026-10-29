(function ($) {
    'use strict';

    function csrfToken() {
        return $('meta[name="csrf-token"]').attr('content')
            || $('input[name="_token"]').first().val()
            || '';
    }

    function escHtml(text) {
        return String(text || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function iconClass(pin) {
        if (pin && pin.icono_clases) {
            return pin.icono_clases + ' fa-fw';
        }

        var icon = String((pin && pin.icono) ? pin.icono : 'fa-circle').trim();
        if (/^(fa|fas|far|fab|fal|fad)\s+/.test(icon)) {
            return icon + ' fa-fw';
        }
        if (icon.indexOf('fa-') === -1) {
            icon = 'fa-' + icon;
        }
        return 'fa ' + icon + ' fa-fw';
    }

    function menuUrl(path) {
        var base = (window.Laravel && window.Laravel.baseUrl) ? window.Laravel.baseUrl.replace(/\/$/, '') : '';
        var segment = String(path || '').replace(/^\//, '');
        return base + '/' + segment;
    }

    function buildPinElement(pin) {
        var $pin = $('<a>', {
            href: menuUrl(pin.url),
            class: 'anita-taskbar-pin' + (pin.activo ? ' is-active' : ''),
            'data-menu-id': pin.menu_id,
        });

        $pin.append(
            $('<span>', { class: 'anita-taskbar-pin-icon' }).append(
                $('<i>', { class: iconClass(pin) })
            ),
            $('<span>', {
                class: 'anita-taskbar-pin-label',
                text: pin.nombre || '',
            })
        );

        return $pin;
    }

    function renderPins($container, anclados) {
        $container.empty();

        (anclados || []).forEach(function (pin) {
            $container.append(buildPinElement(pin));
        });

        actualizarContadorAccesos((anclados || []).length);
        if ($('#anita-accesos-panel').length && !$('#anita-accesos-panel').hasClass('d-none')) {
            poblarAccesos();
        }
    }

    function actualizarContadorAccesos(cantidad) {
        var $count = $('.anita-accesos-count');
        if (!$count.length) {
            return;
        }
        $count.text(String(cantidad));
        $count.toggleClass('d-none', cantidad <= 0);
    }

    function poblarAccesos() {
        var $lista = $('#anita-accesos-lista');
        if (!$lista.length) {
            return;
        }

        $lista.empty();
        var $pins = $('#anita-taskbar-pins .anita-taskbar-pin');
        if (!$pins.length) {
            $lista.append('<div class="anita-accesos-vacio">Todavía no hay accesos anclados. Use Agregar para elegir programas.</div>');
            return;
        }

        $pins.each(function () {
            var $pin = $(this);
            var $item = $('<a>', {
                href: $pin.attr('href'),
                class: 'anita-accesos-item' + ($pin.hasClass('is-active') ? ' is-active' : ''),
                'data-menu-id': $pin.data('menu-id'),
                title: $pin.attr('title') || $pin.find('.anita-taskbar-pin-label').text(),
            });
            $item.append($pin.find('.anita-taskbar-pin-icon').clone());
            $item.append($('<span>', {
                class: 'anita-accesos-item-label',
                text: $pin.find('.anita-taskbar-pin-label').text(),
            }));
            $lista.append($item);
        });
    }

    function posicionarAccesos() {
        var btn = document.getElementById('anita-accesos-anclados-btn');
        var panel = document.getElementById('anita-accesos-panel');
        var sidebar = document.querySelector('.main-sidebar');
        if (!btn || !panel || !sidebar) {
            return;
        }

        var btnRect = btn.getBoundingClientRect();
        var sideRect = sidebar.getBoundingClientRect();
        var margen = 8;
        var top = Math.max(margen, btnRect.top);
        var left = sideRect.right + margen;
        var maxAlto = Math.max(180, window.innerHeight - top - margen);
        var ancho = panel.offsetWidth || 280;

        if (left + ancho > window.innerWidth - margen) {
            left = Math.max(margen, window.innerWidth - ancho - margen);
        }

        panel.style.top = top + 'px';
        panel.style.left = left + 'px';
        panel.style.maxHeight = maxAlto + 'px';
        var lista = panel.querySelector('.anita-accesos-lista');
        if (lista) {
            var ocupado = panel.querySelector('.anita-accesos-panel-header').offsetHeight
                + panel.querySelector('.anita-accesos-panel-footer').offsetHeight;
            lista.style.maxHeight = Math.max(80, maxAlto - ocupado) + 'px';
        }
    }

    function cerrarAccesos() {
        var $panel = $('#anita-accesos-panel');
        var $btn = $('#anita-accesos-anclados-btn');
        if (!$panel.length || $panel.hasClass('d-none')) {
            return;
        }
        $panel.addClass('d-none').attr('aria-hidden', 'true');
        $btn.removeClass('is-open').attr('aria-expanded', 'false');
    }

    function abrirAccesos() {
        var $panel = $('#anita-accesos-panel');
        var $btn = $('#anita-accesos-anclados-btn');
        if (!$panel.length) {
            return;
        }
        poblarAccesos();
        $panel.css('visibility', 'hidden').removeClass('d-none').attr('aria-hidden', 'false');
        posicionarAccesos();
        $panel.css('visibility', '');
        $btn.addClass('is-open').attr('aria-expanded', 'true');
    }

    function tituloPinMenu(anclado) {
        return anclado
            ? 'Quitar de la barra de tareas (clic para confirmar)'
            : 'Anclar en la barra de tareas (clic para confirmar). También: clic derecho sobre el programa.';
    }

    function syncSidebarPin(menuId, anclado) {
        var $btn = $('.anita-menu-pin-btn[data-menu-id="' + menuId + '"]');
        $btn.toggleClass('is-pinned', !!anclado);
        $btn.attr('title', tituloPinMenu(!!anclado));
        $btn.attr('aria-label', anclado ? 'Desanclar de la barra de tareas' : 'Anclar en la barra de tareas');
    }

    function confirmarAccionPin(nombre, anclar) {
        var deferred = $.Deferred();
        var titulo = anclar
            ? '¿Anclar en la barra de tareas?'
            : '¿Quitar de la barra de tareas?';
        var texto = anclar
            ? '«' + nombre + '» quedará como acceso directo en la barra inferior.'
            : '«' + nombre + '» dejará de mostrarse en la barra inferior.';
        var etiquetaConfirmar = anclar ? 'Anclar' : 'Quitar';

        if (typeof swal !== 'function') {
            if (window.confirm(titulo + '\n\n' + texto)) {
                deferred.resolve();
            } else {
                deferred.reject();
            }
            return deferred.promise();
        }

        swal({
            title: titulo,
            text: texto,
            icon: 'warning',
            buttons: {
                cancel: 'Cancelar',
                confirm: etiquetaConfirmar,
            },
        }).then(function (value) {
            if (value) {
                deferred.resolve();
            } else {
                deferred.reject();
            }
        });

        return deferred.promise();
    }

    function solicitarTogglePin(menuId, anclar, nombre) {
        var etiqueta = String(nombre || '').trim() || 'este programa';

        return confirmarAccionPin(etiqueta, anclar).then(function () {
            return togglePin(menuId, anclar);
        });
    }

    function syncSidebarPins(anclados) {
        var ids = (anclados || []).map(function (p) { return Number(p.menu_id); });
        $('.anita-menu-pin-btn').each(function () {
            var menuId = Number($(this).data('menu-id'));
            syncSidebarPin(menuId, ids.indexOf(menuId) !== -1);
        });
    }

    function postJson(url, data) {
        return $.ajax({
            url: url,
            type: 'POST',
            data: $.extend({ _token: csrfToken() }, data || {}),
            dataType: 'json',
        });
    }

    function togglePin(menuId, anclar) {
        var $bar = $('#anita-taskbar');
        if (!$bar.length) {
            return $.Deferred().reject().promise();
        }

        var url = anclar ? $bar.data('url-anclar') : $bar.data('url-desanclar');

        return postJson(url, { menu_id: menuId }).then(function (resp) {
            if (!resp || !resp.ok) {
                var msg = (resp && resp.mensaje) ? resp.mensaje : 'No se pudo actualizar la barra de tareas.';
                if (window.toastr) {
                    toastr.warning(msg);
                }
                return;
            }

            renderPins($('#anita-taskbar-pins'), resp.anclados || []);
            syncSidebarPins(resp.anclados || []);
        }).fail(function (xhr) {
            var msg = 'No se pudo actualizar la barra de tareas.';
            if (xhr.responseJSON && xhr.responseJSON.mensaje) {
                msg = xhr.responseJSON.mensaje;
            }
            if (window.toastr) {
                toastr.error(msg);
            }
        });
    }

    function renderPickerList(menus, filtro) {
        var $lista = $('#barra-tareas-lista');
        var term = String(filtro || '').toLowerCase().trim();
        var filtrados = (menus || []).filter(function (m) {
            if (!term) {
                return true;
            }
            return String(m.nombre || '').toLowerCase().indexOf(term) !== -1
                || String(m.nombre_ruta || '').toLowerCase().indexOf(term) !== -1;
        });

        $lista.empty();

        if (!filtrados.length) {
            $lista.append('<div class="anita-taskbar-picker-empty">No hay programas que coincidan con la búsqueda.</div>');
            return;
        }

        filtrados.forEach(function (menu) {
            var $item = $('<div>', {
                class: 'anita-taskbar-picker-item' + (menu.anclado ? ' is-pinned' : ''),
                'data-menu-id': menu.id,
            });

            $item.append(
                '<span class="anita-taskbar-picker-icon"><i class="' + escHtml(iconClass(menu)) + '"></i></span>'
                + '<span class="anita-taskbar-picker-text">'
                + '<strong>' + escHtml(menu.nombre) + '</strong>'
                + '<small>' + escHtml(menu.nombre_ruta) + '</small>'
                + '</span>'
                + '<span class="anita-taskbar-picker-action">' + (menu.anclado ? 'Quitar' : 'Anclar') + '</span>'
            );

            $lista.append($item);
        });
    }

    function cargarMenusPicker() {
        var $bar = $('#anita-taskbar');
        var url = $bar.data('url-menus');
        var $lista = $('#barra-tareas-lista');

        $lista.html('<div class="anita-taskbar-picker-empty"><i class="fas fa-spinner fa-spin"></i> Cargando…</div>');

        return $.getJSON(url).then(function (resp) {
            var menus = (resp && resp.menus) ? resp.menus : [];
            $('#modal-barra-tareas').data('menus-cache', menus);
            renderPickerList(menus, $('#barra-tareas-buscar').val());
        }).fail(function () {
            $lista.html('<div class="anita-taskbar-picker-empty text-danger">No se pudo cargar el listado de programas.</div>');
        });
    }

    $(function () {
        var $bar = $('#anita-taskbar');
        if (!$bar.length) {
            return;
        }

        $(document).on('click', '#anita-taskbar-add', function (e) {
            e.preventDefault();
            e.stopPropagation();
            $('#modal-barra-tareas').modal('show');
            cargarMenusPicker();
        });

        $(document).on('click', '.anita-menu-pin-btn', function (e) {
            e.preventDefault();
            e.stopPropagation();

            var $btn = $(this);
            solicitarTogglePin(
                Number($btn.data('menu-id')),
                !$btn.hasClass('is-pinned'),
                $btn.data('menu-nombre')
            );
        });

        $(document).on('contextmenu', '.anita-menu-leaf > .nav-link', function (e) {
            var $btn = $(this).closest('.anita-menu-leaf').find('.anita-menu-pin-btn');
            if (!$btn.length) {
                return;
            }

            e.preventDefault();
            e.stopPropagation();

            solicitarTogglePin(
                Number($btn.data('menu-id')),
                !$btn.hasClass('is-pinned'),
                $btn.data('menu-nombre')
            );
        });

        $(document).on('contextmenu', '.anita-taskbar-pin, .anita-accesos-item', function (e) {
            e.preventDefault();

            var $pin = $(this);
            var menuId = Number($pin.data('menu-id'));
            if (menuId <= 0) {
                return;
            }

            var nombre = String(
                $pin.find('.anita-taskbar-pin-label, .anita-accesos-item-label').first().text() || ''
            ).trim();
            solicitarTogglePin(menuId, false, nombre);
        });

        $(document).on('click', '#anita-accesos-anclados-btn', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if ($('#anita-accesos-panel').hasClass('d-none')) {
                abrirAccesos();
            } else {
                cerrarAccesos();
            }
        });

        $(document).on('click', '#anita-accesos-cerrar', function (e) {
            e.preventDefault();
            cerrarAccesos();
        });

        $(document).on('click', '#anita-accesos-agregar', function (e) {
            e.preventDefault();
            cerrarAccesos();
            $('#anita-taskbar-add').trigger('click');
        });

        $(document).on('click', function (e) {
            if ($(e.target).closest('#anita-accesos-panel, #anita-accesos-anclados-btn').length) {
                return;
            }
            cerrarAccesos();
        });

        $(document).on('keydown', function (e) {
            if (e.key === 'Escape') {
                cerrarAccesos();
            }
        });

        function reposicionarSiAbierto() {
            if (!$('#anita-accesos-panel').hasClass('d-none')) {
                posicionarAccesos();
            }
        }

        $(window).on('resize', reposicionarSiAbierto);
        $('.main-sidebar .sidebar').on('scroll', reposicionarSiAbierto);
        $('.main-sidebar').on('mouseenter mouseleave', function () {
            window.setTimeout(reposicionarSiAbierto, 320);
        });

        $('#barra-tareas-buscar').on('input', function () {
            var menus = $('#modal-barra-tareas').data('menus-cache') || [];
            renderPickerList(menus, $(this).val());
        });

        $(document).on('click', '.anita-taskbar-picker-item', function () {
            var menuId = Number($(this).data('menu-id'));
            var menus = $('#modal-barra-tareas').data('menus-cache') || [];
            var menu = menus.find(function (m) { return Number(m.id) === menuId; });
            if (!menu) {
                return;
            }

            togglePin(menuId, !menu.anclado).then(function () {
                menu.anclado = !menu.anclado;
                $('#modal-barra-tareas').data('menus-cache', menus);
                renderPickerList(menus, $('#barra-tareas-buscar').val());
            });
        });

        $('#modal-barra-tareas').on('hidden.bs.modal', function () {
            $('#barra-tareas-buscar').val('');
        });
    });
}(jQuery));
