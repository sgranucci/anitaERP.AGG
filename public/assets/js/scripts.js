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
        selector: '.tooltipsC:not(.consultaarticulo):not(.consultadeposito)',
        placement: 'top',
        html: true,
        container: 'body',
        boundary: 'window'
    });
    $('body').on('mousedown', '.btn-accion-tabla.tooltipsC', function () {
        $(this).tooltip('hide');
    });
    var menuParents = $('ul.nav-sidebar').find('a.active').parents('li.has-treeview');
    menuParents.addClass('menu-open');
    menuParents.children('a').addClass('menu-parent-open');
    $('ul.nav-sidebar li.menu-open > .nav-treeview').css('display', 'block');
    // Trabajo con Ventana de Roles.
    const modal = $('#modal-seleccionar-rol');
    if (modal.length && modal.data('rol-set') == 'NO') {
        modal.modal('show');
    }

    function urlAjaxSesion() {
        if (window.Laravel && window.Laravel.baseUrl) {
            return window.Laravel.baseUrl.replace(/\/$/, '') + '/ajax-sesion';
        }
        if (typeof carpetaBase !== 'undefined' && carpetaBase) {
            return String(carpetaBase).replace(/\/$/, '') + '/ajax-sesion';
        }

        return '/ajax-sesion';
    }

    $('.asignar-rol').on('click', function (event) {
        event.preventDefault();
        const data = {
            rol_id: $(this).data('rolid'),
            rol_nombre: $(this).data('rolnombre'),
            _token: $('meta[name="csrf-token"]').attr('content') || $('input[name=_token]').first().val()
        };
        ajaxRequest(data, urlAjaxSesion(), 'asignar-rol');
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
                    $('#modal-seleccionar-rol').modal('hide');
                    location.reload();
                }
            },
            error: function (xhr) {
                if (funcion !== 'asignar-rol') {
                    return;
                }
                const msg = (xhr.responseJSON && xhr.responseJSON.mensaje)
                    ? xhr.responseJSON.mensaje
                    : 'No se pudo asignar el rol. Cierre sesión e intente de nuevo.';
                if (typeof swal === 'function') {
                    swal('Error', msg, 'error');
                } else {
                    alert(msg);
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