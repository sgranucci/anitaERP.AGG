$(function () {
    var $arbol = $('#pc-arbol');
    if ($arbol.length) {
        $arbol.on('click', '.pc-nodo__toggle', function (e) {
            e.preventDefault();
            var $btn = $(this);
            if ($btn.hasClass('pc-nodo__toggle--empty')) {
                return;
            }
            var $hijos = $btn.closest('.pc-nodo').children('.pc-nodo__hijos');
            var abierto = $btn.attr('aria-expanded') === 'true';
            $btn.attr('aria-expanded', abierto ? 'false' : 'true');
            $btn.find('i').toggleClass('fa-caret-down', !abierto).toggleClass('fa-caret-right', abierto);
            $hijos.prop('hidden', abierto);
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
    }

    $(document).on('click', '.eliminar-cuentacontable', function (e) {
        e.preventDefault();
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
