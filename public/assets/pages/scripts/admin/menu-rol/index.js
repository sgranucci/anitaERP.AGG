(function () {
    var $page = $('#menu-rol-page');
    var permisosUrl = $page.data('permisos-url');
    var guardarMenuRolUrl = $page.data('guardarMenuRolUrl');
    var guardarPermisoRolUrl = $page.data('guardarPermisoRolUrl');
    var centrocostoIdFiltro = $page.data('centrocostoId');
    if (centrocostoIdFiltro === undefined || centrocostoIdFiltro === null) {
        centrocostoIdFiltro = '';
    } else {
        centrocostoIdFiltro = String(centrocostoIdFiltro);
    }

    var $wrap = $('#menu-rol-tabla-wrap');
    var $tabla = $('#tabla-menu-rol-data');
    var $colFilterStyle = $('#menu-rol-col-filter-style');
    var totalRolesCargados = $tabla.find('thead th.menu-rol-col-rol').length;

    function escapeHtml(text) {
        var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text).replace(/[&<>"']/g, function (m) { return map[m]; });
    }

    function escapeCssIdent(value) {
        return String(value).replace(/[^a-zA-Z0-9_-]/g, '\\$&');
    }

    function ajaxMenuRol(url, data) {
        $.ajax({
            url: url,
            type: 'POST',
            data: data,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            success: function (respuesta) {
                Biblioteca.notificaciones(respuesta.respuesta, 'Biblioteca', 'success');
            }
        });
    }

    function actualizarSombrasScroll() {
        if (!$wrap.length) {
            return;
        }
        var el = $wrap[0];
        $wrap.toggleClass('menu-rol-scrolled-x', el.scrollLeft > 2);
        $wrap.toggleClass('menu-rol-scrolled-y', el.scrollTop > 2);
    }

    function actualizarContadores(filasVisibles, colsVisibles) {
        var $filas = $('#menu-rol-filas-visibles');
        var $cols = $('#menu-rol-cols-visibles');
        if ($filas.length) {
            $filas.html('Filas de menú visibles: <strong>' + filasVisibles + '</strong>');
        }
        if ($cols.length) {
            $cols.html(
                'Columnas de roles visibles: <strong>' + colsVisibles + '</strong>' +
                (colsVisibles !== totalRolesCargados ? ' de ' + totalRolesCargados : '')
            );
        }
    }

    function aplicarFiltroFilas() {
        var qMenu = String($('#filtro-nombre-menu').val() || '').toLowerCase().trim();
        var moduloId = String($('#filtro-modulo-menu').val() || '').trim();
        var $filas = $tabla.find('tbody tr');
        var idsVisibles = {};
        var filasVisibles = 0;

        $filas.each(function () {
            var $tr = $(this);
            var nombre = String($tr.data('menuNombre') || '').toLowerCase();
            var menuId = String($tr.data('menuId') || '');
            var parentId = String($tr.data('parentId') || '0');
            var filaModuloId = String($tr.data('moduloId') || '');
            var matchModulo = moduloId === '' || filaModuloId === moduloId;
            var matchTexto = qMenu === '' || nombre.indexOf(qMenu) !== -1;
            var match = matchModulo && matchTexto;

            if (match) {
                idsVisibles[menuId] = true;
                // Mostrar ancestros para no romper el árbol al buscar opciones
                var pid = parentId;
                var guard = 0;
                while (pid && pid !== '0' && guard < 20) {
                    idsVisibles[pid] = true;
                    var $padre = $filas.filter('[data-menu-id="' + pid + '"]').first();
                    if (!$padre.length) {
                        break;
                    }
                    pid = String($padre.data('parentId') || '0');
                    guard += 1;
                }
            }
        });

        $filas.each(function () {
            var $tr = $(this);
            var menuId = String($tr.data('menuId') || '');
            var visible = !!idsVisibles[menuId];
            // Sin filtros: mostrar todo
            if (qMenu === '' && moduloId === '') {
                visible = true;
            }
            $tr.toggle(visible);
            if (visible) {
                filasVisibles += 1;
            }
        });

        return filasVisibles;
    }

    function aplicarFiltroColumnas() {
        var qRol = String($('#filtro-nombre-rol').val() || '').toLowerCase().trim();
        var ocultas = [];
        var visibles = 0;

        $tabla.find('thead th.menu-rol-col-rol').each(function () {
            var $th = $(this);
            var rolId = String($th.data('rolId') || '');
            var nombre = String($th.data('rolNombre') || '').toLowerCase();
            var match = qRol === '' || nombre.indexOf(qRol) !== -1;
            if (match) {
                visibles += 1;
            } else if (rolId !== '') {
                ocultas.push('.tabla-menu-rol .col-rol-' + escapeCssIdent(rolId));
            }
        });

        if (ocultas.length) {
            $colFilterStyle.text(ocultas.join(', ') + ' { display: none !important; }');
        } else {
            $colFilterStyle.text('');
        }

        return visibles;
    }

    function aplicarFiltrosCliente() {
        var filas = aplicarFiltroFilas();
        var cols = aplicarFiltroColumnas();
        actualizarContadores(filas, cols);
        actualizarSombrasScroll();
    }

    $('.menu_rol').on('change', function () {
        var data = {
            menu_id: $(this).data('menuid'),
            rol_id: $(this).val(),
            _token: $('input[name=_token]').val()
        };
        if ($(this).is(':checked')) {
            data.estado = 1;
        } else {
            data.estado = 0;
        }
        ajaxMenuRol(guardarMenuRolUrl, data);
    });

    $(document).on('change', '.permiso_rol_modal', function () {
        var data = {
            permiso_id: $(this).data('permisoid'),
            rol_id: $(this).val(),
            _token: $('input[name=_token]').val()
        };
        if ($(this).is(':checked')) {
            data.estado = 1;
        } else {
            data.estado = 0;
        }
        $.ajax({
            url: guardarPermisoRolUrl,
            type: 'POST',
            data: data,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            success: function (respuesta) {
                Biblioteca.notificaciones(respuesta.respuesta, 'Biblioteca', 'success');
            }
        });
    });

    function filtroNombreRolActual() {
        return String($('#filtro-nombre-rol').val() || '').toLowerCase().trim();
    }

    /**
     * Mismos roles visibles que en la grilla (filtro "Buscar rol").
     * El backend ya aplica centro de costo; el texto de rol es solo cliente.
     */
    function filtrarRolesVisibles(roles) {
        var qRol = filtroNombreRolActual();
        var filtrados = {};
        Object.keys(roles || {}).forEach(function (rolId) {
            var nombre = String(roles[rolId] || '').toLowerCase();
            if (qRol === '' || nombre.indexOf(qRol) !== -1) {
                filtrados[rolId] = roles[rolId];
            }
        });
        return filtrados;
    }

    function buildPermisosTable(data) {
        var roles = filtrarRolesVisibles(data.roles);
        var permisos = data.permisos;
        var roleKeys = Object.keys(roles);
        if (roleKeys.length === 0) {
            return null;
        }
        var html = '<table class="table table-striped table-bordered table-hover mb-0 tabla-menu-rol-modal"><thead><tr>';
        html += '<th class="menu-rol-col-menu">Permiso</th>';
        roleKeys.forEach(function (rolId) {
            html += '<th class="text-center menu-rol-col-rol" title="' + escapeHtml(roles[rolId]) + '">';
            html += '<span class="menu-rol-th-rol">' + escapeHtml(roles[rolId]) + '</span></th>';
        });
        html += '</tr></thead><tbody>';
        permisos.forEach(function (p) {
            html += '<tr><td class="font-weight-bold menu-rol-col-menu">' + escapeHtml(p.nombre) + '</td>';
            roleKeys.forEach(function (rolId) {
                var rid = parseInt(rolId, 10);
                var checked = p.roles_ids.indexOf(rid) !== -1 ? ' checked' : '';
                html += '<td class="text-center menu-rol-col-rol"><input type="checkbox" class="permiso_rol_modal" name="permiso_rol_modal[]" data-permisoid="' + p.id + '" value="' + rolId + '"' + checked + '></td>';
            });
            html += '</tr>';
        });
        html += '</tbody></table>';
        return html;
    }

    $('#filtro-nombre-menu, #filtro-nombre-rol').on('input', aplicarFiltrosCliente);
    $('#filtro-modulo-menu').on('change', aplicarFiltrosCliente);

    // Aplicar C.Costo al cambiar el select (sin obligar a pulsar el botón)
    $('#centrocosto_id').on('change', function () {
        $('#form-filtro-menu-rol').trigger('submit');
    });

    $wrap.on('scroll', actualizarSombrasScroll);
    $(window).on('resize', actualizarSombrasScroll);

    $('.btn-permisos-menu').on('click', function () {
        var menuId = $(this).data('menu-id');
        var menuNombre = $(this).data('menu-nombre');
        var $modal = $('#modalPermisosMenu');
        var $cargando = $('#modalPermisosMenuCargando');
        var $error = $('#modalPermisosMenuError');
        var $contenedor = $('#modalPermisosMenuContenedor');
        var $sinRoles = $('#modalPermisosMenuSinRoles');
        var $vacio = $('#modalPermisosMenuVacio');

        $error.hide().text('');
        $contenedor.empty().hide();
        $sinRoles.hide();
        $vacio.hide();
        $cargando.show();
        $('#modalPermisosMenuLabel').text('Permisos: ' + menuNombre);

        $modal.modal('show');

        var ajaxData = { menu_id: menuId };
        if (centrocostoIdFiltro !== '') {
            ajaxData.centrocosto_id = centrocostoIdFiltro;
        }

        $.ajax({
            url: permisosUrl,
            type: 'GET',
            headers: { Accept: 'application/json' },
            data: ajaxData,
            success: function (data) {
                $cargando.hide();
                var rolesVisibles = filtrarRolesVisibles(data.roles || {});
                if (Object.keys(rolesVisibles).length === 0) {
                    $sinRoles.show();
                    return;
                }
                if (!data.permisos || data.permisos.length === 0) {
                    $vacio.show();
                    return;
                }
                var tabla = buildPermisosTable(data);
                if (!tabla) {
                    $sinRoles.show();
                    return;
                }
                $contenedor.html(tabla).show();
            },
            error: function (xhr) {
                $cargando.hide();
                var msg = 'No se pudieron cargar los permisos.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                $error.text(msg).show();
            }
        });
    });

    aplicarFiltrosCliente();
    actualizarSombrasScroll();
})();
