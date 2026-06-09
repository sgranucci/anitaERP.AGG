(function () {
    var $page = $('#menu-rol-page');
    var permisosUrl = $page.data('permisos-url');
    var guardarMenuRolUrl = $page.data('guardarMenuRolUrl');
    var guardarPermisoRolUrl = $page.data('guardarPermisoRolUrl');
    var centrocostoFiltro = $page.data('centrocosto') || '';

    function escapeHtml(text) {
        var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text).replace(/[&<>"']/g, function (m) { return map[m]; });
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

    function buildPermisosTable(data) {
        var roles = data.roles;
        var permisos = data.permisos;
        var roleKeys = Object.keys(roles);
        if (roleKeys.length === 0) {
            return null;
        }
        var html = '<table class="table table-striped table-bordered table-hover mb-0"><thead><tr><th>Permiso</th>';
        roleKeys.forEach(function (rolId) {
            html += '<th class="text-center">' + escapeHtml(roles[rolId]) + '</th>';
        });
        html += '</tr></thead><tbody>';
        permisos.forEach(function (p) {
            html += '<tr><td class="font-weight-bold">' + escapeHtml(p.nombre) + '</td>';
            roleKeys.forEach(function (rolId) {
                var rid = parseInt(rolId, 10);
                var checked = p.roles_ids.indexOf(rid) !== -1 ? ' checked' : '';
                html += '<td class="text-center"><input type="checkbox" class="permiso_rol_modal" name="permiso_rol_modal[]" data-permisoid="' + p.id + '" value="' + rolId + '"' + checked + '></td>';
            });
            html += '</tr>';
        });
        html += '</tbody></table>';
        return html;
    }

    $('#filtro-nombre-menu').on('input', function () {
        var q = String($(this).val() || '').toLowerCase().trim();
        $('#tabla-menu-rol-data tbody tr').each(function () {
            var texto = $(this).find('td:first').text().toLowerCase();
            $(this).toggle(q === '' || texto.indexOf(q) !== -1);
        });
    });

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

        $.ajax({
            url: permisosUrl,
            type: 'GET',
            headers: { Accept: 'application/json' },
            data: {
                menu_id: menuId,
                centrocosto: centrocostoFiltro
            },
            success: function (data) {
                $cargando.hide();
                var roleKeys = Object.keys(data.roles || {});
                if (roleKeys.length === 0) {
                    $sinRoles.show();
                    return;
                }
                if (!data.permisos || data.permisos.length === 0) {
                    $vacio.show();
                    return;
                }
                $contenedor.html(buildPermisosTable(data)).show();
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
})();
