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

    // Mantener scroll del aside / ítem activo visible tras navegación (MPA).
    (function persistirScrollAside() {
        var $body = $('body');
        if ($body.hasClass('modo-consulta') || $body.hasClass('modo-embed')) {
            return;
        }

        var $sidebar = $('.main-sidebar .sidebar');
        if (!$sidebar.length) {
            return;
        }

        var el = $sidebar[0];
        var STORAGE_KEY = 'anita_sidebar_scrollTop';
        var scrollTimer = null;

        function guardarScroll() {
            try {
                sessionStorage.setItem(STORAGE_KEY, String(el.scrollTop | 0));
            } catch (e) {
                // sessionStorage puede fallar en modo privado estricto
            }
        }

        function leerScrollGuardado() {
            try {
                var raw = sessionStorage.getItem(STORAGE_KEY);
                if (raw === null || raw === '') {
                    return null;
                }
                var top = parseInt(raw, 10);
                return isNaN(top) || top < 0 ? null : top;
            } catch (e) {
                return null;
            }
        }

        function enlaceActivo() {
            var $leaf = $sidebar.find('a.nav-link.active:not(.menu-parent-open)').first();
            if ($leaf.length) {
                return $leaf[0];
            }
            var $any = $sidebar.find('a.nav-link.active').first();
            return $any.length ? $any[0] : null;
        }

        function visibleEnAside(node) {
            if (!node) {
                return true;
            }
            var sRect = el.getBoundingClientRect();
            var nRect = node.getBoundingClientRect();
            return nRect.top >= sRect.top + 2 && nRect.bottom <= sRect.bottom - 2;
        }

        // scrollIntoView a veces scrollea la ventana; mover solo .sidebar.
        function traerAlAside(node, modo) {
            if (!node || el.scrollHeight <= el.clientHeight + 1) {
                return;
            }
            var sRect = el.getBoundingClientRect();
            var nRect = node.getBoundingClientRect();
            var relTop = nRect.top - sRect.top + el.scrollTop;
            var target;

            if (modo === 'center') {
                target = relTop - (el.clientHeight / 2) + (nRect.height / 2);
            } else if (nRect.top < sRect.top) {
                target = relTop - 8;
            } else if (nRect.bottom > sRect.bottom) {
                target = relTop + nRect.height - el.clientHeight + 8;
            } else {
                return;
            }

            el.scrollTop = Math.max(0, Math.min(target, el.scrollHeight - el.clientHeight));
        }

        function restaurarScrollAside() {
            if (el.scrollHeight <= el.clientHeight + 1) {
                return;
            }

            var guardado = leerScrollGuardado();
            var activo = enlaceActivo();

            if (guardado !== null) {
                el.scrollTop = guardado;
            }

            if (activo) {
                if (guardado === null) {
                    traerAlAside(activo, 'center');
                } else if (!visibleEnAside(activo)) {
                    traerAlAside(activo, 'nearest');
                }
            }

            guardarScroll();
        }

        $sidebar.on('scroll', function () {
            clearTimeout(scrollTimer);
            scrollTimer = setTimeout(guardarScroll, 80);
        });

        // Captura: guardar antes de que la navegación descargue la página.
        document.addEventListener('click', function (ev) {
            var link = ev.target && ev.target.closest
                ? ev.target.closest('.main-sidebar a.nav-link[href]')
                : null;
            if (!link) {
                return;
            }
            var href = link.getAttribute('href') || '';
            if (!href || href === '#' || href.indexOf('javascript:') === 0) {
                return;
            }
            guardarScroll();
        }, true);

        function programarRestaurar() {
            restaurarScrollAside();
            setTimeout(restaurarScrollAside, 50);
            setTimeout(restaurarScrollAside, 200);
        }

        if (typeof requestAnimationFrame === 'function') {
            requestAnimationFrame(programarRestaurar);
        } else {
            programarRestaurar();
        }

        $(window).on('load', function () {
            setTimeout(restaurarScrollAside, 0);
        });
    })();

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