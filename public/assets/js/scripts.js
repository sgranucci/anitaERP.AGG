/* Boton Borrar Campos De Formulario*/
$(document).ready(function () {
    //Cerrar Las Alertas Automaticamente
    $('.alert[data-auto-dismiss]').each(function (index, element) {
        const $element = $(element),
            timeout = $element.data('auto-dismiss') || 5000;
        setTimeout(function () {
            $element.alert('close');
        }, timeout);
    });
    //TOOLTIPS
    $('body').tooltip({
        trigger: 'hover',
        selector: '.tooltipsC',
        placement: 'top',
        html: true,
        container: 'body'
    });
    var menu = $('ul.nav-sidebar').find('a.active').parents('li.has-treeview');
    menu.addClass('menu-open');
    menu.children('a').addClass('active');
    // Trabajo con Ventana de Roles.
    const modal = $('#modal-seleccionar-rol');
    if (modal.length && modal.data('rol-set') == 'NO') {
        modal.modal('show');
    }

    $('.asignar-rol').on('click', function (event) {
        event.preventDefault();
        const data = {
            rol_id: $(this).data('rolid'),
            rol_nombre: $(this).data('rolnombre'),
            _token: $('input[name=_token]').val()
        }
        ajaxRequest(data, '/ajax-sesion', 'asignar-rol');
    });

    $('.cambiar-rol').on('click', function (event) {
        event.preventDefault();
        modal.modal('show');
    });

    function ajaxRequest(data, url, funcion) {
        $.ajax({
            url: url,
            type: 'POST',
            data: data,
            success: function (respuesta) {
                if (funcion == 'asignar-rol' && respuesta.mensaje == 'ok') {
                    $('#modal-seleccionar-rol').hide();
                    location.reload();
                }
            }
        });
    }
});

function zfill(number, width) {
    var length = number.toString().length; /* Largo del n£mero */

    return ((zero.repeat(width - length)) + number.toString());
}

function filtraCaracteresEspeciales(ptr)
{
    var c = ptr.selectionStart,
        r = /[^a-z0-9. ]/gi,
        v = $(ptr).val();

    if(r.test(v)) {
        $(ptr).val(v.replace(r, ''));
        c--;
    }

    ptr.setSelectionRange(c, c);
}