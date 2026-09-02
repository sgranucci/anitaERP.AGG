$(document).ready(function () {
    $('#nestable').nestable().on('change', function () {
        const data = {
            menu: window.JSON.stringify($('#nestable').nestable('serialize')),
            _token: $('input[name=_token]').first().val()
        };
        let gord = carpetaBase+'/admin/menu/guardar-orden';
        $.ajax({
            url: gord,
            type: 'POST',
            dataType: 'JSON',
            data: data,
            success: function (respuesta) {
            }
        });
    });

    $('.eliminar-menu').on('click', function(event){
        event.preventDefault();
        const url = $(this).attr('href');
        swal({
            title: '¿ Está seguro que desea eliminar el registro ?',
            text: "Esta acción no se puede deshacer!",
            icon: 'warning',
            buttons: {
                cancel: "Cancelar",
                confirm: "Aceptar"
            },
        }).then((value) => {
            if (value) {
                window.location.href = url;
            }
        });
    })

    $('#nestable').nestable('expandAll');

    function actualizarContador() {
        var n = $('#nestable .menu-sel:checked').length;
        $('#menu-sel-contador').text(n);
    }

    function marcarDescendientes($li, checked) {
        $li.find('.menu-sel').prop('checked', checked);
    }

    function idsSeleccionados() {
        var ids = [];
        $('#nestable .menu-sel:checked').each(function () {
            var v = parseInt(this.value, 10);
            if (v > 0) {
                ids.push(v);
            }
        });
        return ids;
    }

    function nombresSeleccionados(limite) {
        var nombres = [];
        $('#nestable .menu-sel:checked').each(function () {
            if (nombres.length >= limite) {
                return false;
            }
            nombres.push($(this).attr('data-nombre') || this.value);
        });
        return nombres;
    }

    function enviarBorradoLote(ids) {
        var form = document.getElementById('form-eliminar-varios-menu');
        if (!form) {
            window.alert('No se encontró el formulario de baja.');
            return;
        }
        $(form).find('input[name="ids[]"]').remove();
        ids.forEach(function (id) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'ids[]';
            input.value = String(id);
            form.appendChild(input);
        });
        if (window.AnitaGrabacion && typeof window.AnitaGrabacion.enviar === 'function') {
            window.AnitaGrabacion.enviar(form);
            return;
        }
        form.submit();
    }

    $('#nestable').on('click', '.menu-sel-label, .menu-sel', function (e) {
        e.stopPropagation();
    });

    $('#nestable').on('mousedown', '.menu-sel-label, .menu-sel', function (e) {
        e.stopPropagation();
    });

    $('#nestable').on('change', '.menu-sel', function () {
        var $li = $(this).closest('li.dd-item');
        if (this.checked) {
            marcarDescendientes($li, true);
        }
        actualizarContador();
    });

    function textoItem($li) {
        return ($li.children('.dd3-content').text() || '').toLowerCase();
    }

    function aplicarFiltro() {
        var q = ($('#menu-filtro').val() || '').trim().toLowerCase();
        var $items = $('#nestable li.dd-item');
        if (!q) {
            $items.removeClass('menu-filtro-oculto');
            return;
        }
        $items.each(function () {
            var $li = $(this);
            var propio = textoItem($li).indexOf(q) !== -1;
            var hijo = $li.find('li.dd-item').filter(function () {
                return textoItem($(this)).indexOf(q) !== -1;
            }).length > 0;
            $li.toggleClass('menu-filtro-oculto', !(propio || hijo));
        });
    }

    $('#menu-filtro').on('input', aplicarFiltro);

    $('#menu-sel-visibles').on('click', function () {
        var q = ($('#menu-filtro').val() || '').trim().toLowerCase();
        $('#nestable li.dd-item').not('.menu-filtro-oculto').each(function () {
            var $li = $(this);
            if (q && textoItem($li).indexOf(q) === -1) {
                return;
            }
            marcarDescendientes($li, true);
        });
        actualizarContador();
    });

    $('#menu-sel-ninguno').on('click', function () {
        $('#nestable .menu-sel').prop('checked', false);
        actualizarContador();
    });

    $('#menu-eliminar-seleccionados').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var ids = idsSeleccionados();
        if (ids.length === 0) {
            if (typeof swal === 'function') {
                swal('Nada seleccionado', 'Marque al menos un ítem de menú.', 'info');
            } else {
                window.alert('Marque al menos un ítem de menú.');
            }
            return;
        }
        var nombres = nombresSeleccionados(12);
        var extra = ids.length > 12 ? '\n… y ' + (ids.length - 12) + ' más' : '';
        var texto = 'Se borrarán ' + ids.length + ' ítems (incluye submenús de cada padre).\n\n'
            + nombres.join('\n') + extra
            + '\n\nEsta acción no se puede deshacer.';

        function confirmarYEnviar(ok) {
            if (!ok) {
                return;
            }
            enviarBorradoLote(ids);
        }

        if (typeof swal !== 'function') {
            confirmarYEnviar(window.confirm('¿Eliminar ' + ids.length + ' ítems de menú?\n\n' + texto));
            return;
        }

        swal({
            title: '¿Eliminar ' + ids.length + ' ítems de menú?',
            text: texto,
            icon: 'warning',
            buttons: {
                cancel: 'Cancelar',
                confirm: 'Eliminar'
            },
            dangerMode: true,
        }).then(confirmarYEnviar);
    });

    actualizarContador();
});
