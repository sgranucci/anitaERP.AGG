var ptrMozo_id;
var ptrCodigoMozo_id;
var ptrNombreMozo;

var GASTRONOMIA_MODAL_Z_BASE = 1050;
var GASTRONOMIA_MODAL_Z_STEP = 20;

function modalPadreAbiertoParaConsultaMozo() {
    var $padre = $('#modal-abrir-cuenta, #modal-cm-login-mozo');
    return $padre.length > 0 && $padre.filter('.show').length > 0;
}

function apilarConsultaMozoSobreModalPadre() {
    if (!modalPadreAbiertoParaConsultaMozo()) {
        return;
    }
    var $hijo = $('#consultamozoModal');
    var zHijo = GASTRONOMIA_MODAL_Z_BASE + GASTRONOMIA_MODAL_Z_STEP;
    var zBackdrop = zHijo - 10;
    $hijo.data('gastroApilado', true);
    $hijo.css('z-index', zHijo);
    $('.modal-backdrop').last().css('z-index', zBackdrop);
}

function desapilarConsultaMozoModal() {
    var $hijo = $('#consultamozoModal');
    if (!$hijo.data('gastroApilado')) {
        return;
    }
    $hijo.removeData('gastroApilado');
    $hijo.css('z-index', '');
    if ($('.modal-backdrop').length) {
        $('.modal-backdrop').last().css('z-index', '');
    }
    if ($('#modal-abrir-cuenta, #modal-cm-login-mozo').filter('.show').length) {
        $('body').addClass('modal-open');
    }
}

function enfocarCampoConsultaMozo() {
    var $inp = $('#consultamozo');
    window.setTimeout(function () {
        if (!$('#consultamozoModal').hasClass('show')) {
            return;
        }
        $inp.trigger('focus');
        var el = $inp.get(0);
        if (el && typeof el.select === 'function') {
            el.select();
        }
    }, 0);
}

function abrirConsultaMozoModal() {
    $('#consultamozoModal').modal('show');
}

function empresaIdFacturacionGastronomia() {
    if (typeof window.GASTRONOMIA !== 'undefined') {
        var id = parseInt(window.GASTRONOMIA.empresaId, 10);
        if (id > 0) {
            return id;
        }
    }
    return null;
}

function csrfTokenConsultaMozo() {
    var meta = $('meta[name="csrf-token"]').attr('content');
    if (meta) {
        return meta;
    }
    if (typeof window.GASTRONOMIA !== 'undefined' && window.GASTRONOMIA.csrf) {
        return window.GASTRONOMIA.csrf;
    }
    var inp = $('input[name="_token"]').first().val();
    return inp || '';
}

function avisoErrorConsultaMozo(msg) {
    if (window.toastr) {
        window.toastr.error(msg, '', { timeOut: 10000, closeButton: true });
    } else if (typeof alert === 'function') {
        alert(msg);
    }
}

function mensajeErrorAjaxConsultaMozo(xhr) {
    if (xhr.status === 401 || xhr.status === 419) {
        return 'Sesión expirada o token inválido. Cierre sesión, vuelva a entrar y recargue la página.';
    }
    if (xhr.status === 403) {
        return 'Sin permiso para consultar mozos. Verifique el rol activo (Cambiar rol en el menú de usuario).';
    }
    if (xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) {
        return xhr.responseJSON.message || xhr.responseJSON.error;
    }
    return 'No se pudo cargar la lista de mozos (HTTP ' + xhr.status + ').';
}

function urlConsultaMozoCanjeMarketing() {
    if (typeof window.CANJE_MARKETING !== 'undefined'
        && window.CANJE_MARKETING.tieneCfgPv
        && window.CANJE_MARKETING.rutas
        && window.CANJE_MARKETING.rutas.apiBase) {
        return window.CANJE_MARKETING.rutas.apiBase.replace(/\/$/, '') + '/consulta-mozo';
    }

    return '';
}

function urlLeerMozoPorCodigoCanjeMarketing(codigo) {
    var cod = String(codigo || '').trim();
    if (!cod) {
        return '';
    }
    if (typeof window.CANJE_MARKETING !== 'undefined'
        && window.CANJE_MARKETING.tieneCfgPv
        && window.CANJE_MARKETING.rutas
        && window.CANJE_MARKETING.rutas.apiBase) {
        return window.CANJE_MARKETING.rutas.apiBase.replace(/\/$/, '')
            + '/mozo/leer-codigo/'
            + encodeURIComponent(cod);
    }

    return '';
}

function esLoginMozoCanjeMarketing(ptrrenglon) {
    return typeof window.CANJE_MARKETING !== 'undefined'
        && window.CANJE_MARKETING.tieneCfgPv
        && ptrrenglon
        && $(ptrrenglon).closest('#modal-cm-login-mozo-mozo-wrap').length > 0;
}

function buscar_datos_mozo(consulta) {
    var data = { consulta: consulta || '' };
    var urlCanje = urlConsultaMozoCanjeMarketing();
    var empresaId = null;
    if (!urlCanje && typeof window.GASTRONOMIA !== 'undefined') {
        empresaId = empresaIdFacturacionGastronomia();
        if (empresaId) {
            data.empresa_id = empresaId;
        } else if (window.GASTRONOMIA.tieneCfgPv === false) {
            $('#datosmozo').html('<tr><td colspan="4">Sin configuración PV para esta terminal.</td></tr>');
            avisoErrorConsultaMozo(
                'Sin configuración de punto de venta gastronomía para esta PC. Recargue la página; si persiste, revise Config. PV gastronomía.',
            );
            return;
        }
    }
    var token = csrfTokenConsultaMozo();
    if (token) {
        data._token = token;
    }

    $.ajax({
        url: urlCanje || (carpetaBase + '/ventas/mozo-gastronomia/consultamozo'),
        type: 'POST',
        dataType: 'HTML',
        headers: {
            'X-CSRF-TOKEN': token,
            'X-Requested-With': 'XMLHttpRequest',
        },
        data: data,
    })
        .done(function (respuesta) {
            var html = '';
            if (typeof respuesta === 'string') {
                try {
                    html = JSON.parse(respuesta).data || '';
                } catch (e) {
                    html = respuesta.replace(/\\/g, '');
                }
            } else if (respuesta && typeof respuesta.data === 'string') {
                html = respuesta.data;
            }
            if (!html && typeof respuesta === 'string' && /login|seguridad/i.test(respuesta)) {
                avisoErrorConsultaMozo('Sesión expirada. Vuelva a iniciar sesión.');
                html = '<tr><td colspan="4">Sesión expirada</td></tr>';
            }
            $('#datosmozo').html(html || '<tr><td colspan="4">Sin resultados</td></tr>');
        })
        .fail(function (xhr) {
            console.warn('error consulta mozo', xhr.status, xhr.responseText);
            avisoErrorConsultaMozo(mensajeErrorAjaxConsultaMozo(xhr));
            $('#datosmozo').html('<tr><td colspan="4">Error al consultar mozos</td></tr>');
        });
}

$(document).on('keydown', 'input', function (e) {
    if (e.which !== 13) {
        return;
    }
    if ($(this).closest('#modal-abrir-cuenta, #modal-cm-login-mozo, #consultamozoModal, #consultaclientevipModal, #modal-cm-wigos-vip, #modal-cm-f8-descuento, #cm-panel-descuento-vip').length) {
        return;
    }
    e.preventDefault();
    return false;
});

$(document).on('keyup', '#consultamozo', function () {
    var valor = $(this).val();
    buscar_datos_mozo(valor);
});

function activa_eventos_consultamozo() {
    $('.consultamozo')
        .off('click.consultaMozo')
        .on('click.consultaMozo', function () {
            var $btn = $(this);
            if ($btn.closest('#modal-abrir-cuenta').length) {
                ptrMozo_id = $('#abrir-mozo_gastronomia_id');
                ptrCodigoMozo_id = $('#abrir-codigomozo');
                ptrNombreMozo = $('#abrir-nombremozo');
            } else if ($btn.closest('#modal-cm-login-mozo').length) {
                ptrMozo_id = $('#cm-login-mozo_gastronomia_id');
                ptrCodigoMozo_id = $('#cm-login-codigomozo');
                ptrNombreMozo = $('#cm-login-nombremozo');
            } else {
                ptrMozo_id = $btn.parents('tr').find('.mozo_gastronomia_id');
                if (!ptrMozo_id || !ptrMozo_id.length) {
                    ptrMozo_id = $('#mozo_gastronomia_id');
                }
                ptrCodigoMozo_id = $btn.parents('tr').find('.codigomozo');
                if (!ptrCodigoMozo_id || !ptrCodigoMozo_id.length) {
                    ptrCodigoMozo_id = $('#codigomozo');
                }
                ptrNombreMozo = $btn.parents('tr').find('.nombremozo');
                if (!ptrNombreMozo || !ptrNombreMozo.length) {
                    ptrNombreMozo = $('#nombremozo');
                }
            }

            abrirConsultaMozoModal();
        });

    $('#consultamozoModal')
        .off('shown.bs.modal.consultaMozo')
        .on('shown.bs.modal.consultaMozo', function () {
            buscar_datos_mozo($('#consultamozo').val());
        });

    $('#aceptaconsultamozoModal')
        .off('click.consultaMozo')
        .on('click.consultaMozo', function () {
            $('#consultamozoModal').modal('hide');
        });

    $(document).on('click', '.eligeconsultamozo', function () {
        let seleccion = $(this).parents('tr').find('.id').html();
        let nombre = $(this).parents('tr').find('.nombre').html();
        let codigo = $(this).parents('tr').find('.codigo').html();

        if (ptrMozo_id && ptrMozo_id.length) {
            ptrMozo_id.val(seleccion);
        }
        if (ptrCodigoMozo_id && ptrCodigoMozo_id.length) {
            ptrCodigoMozo_id.val(codigo);
        }
        if (ptrNombreMozo && ptrNombreMozo.length) {
            ptrNombreMozo.val(nombre);
        }

        $('#consultamozoModal').modal('hide');
    });

    $(document)
        .off('change.gastroMozoCod', '.codigomozo')
        .on('change.gastroMozoCod', '.codigomozo', function (event) {
            event.preventDefault();
            // Login canjes marketing: Enter/blur lo resuelve proceso_facturacion.js (API canjes).
            if (esLoginMozoCanjeMarketing(this)) {
                return;
            }
            leerMozoPorCodigo($(this).val(), this);
        });
}

function leerMozoPorCodigo(codigo, ptrrenglon, onDone) {
    var empresaId = empresaIdFacturacionGastronomia();
    var codigomozo = (codigo || '').trim();
    if (!codigomozo) {
        if (typeof onDone === 'function') {
            onDone(null);
        }
        return;
    }

    var urlCanje = urlLeerMozoPorCodigoCanjeMarketing(codigomozo);
    var url_res = urlCanje || (carpetaBase + '/ventas/mozo-gastronomia/leer/' + encodeURIComponent(codigomozo));
    if (!urlCanje && empresaId) {
        url_res += '?empresa_id=' + empresaId;
    }

    var $ctx = null;
    if (ptrrenglon) {
        $ctx = $(ptrrenglon).closest('#modal-abrir-cuenta-mozo-wrap, #modal-cm-login-mozo-mozo-wrap, tr').first();
        if (!$ctx.length && $(ptrrenglon).attr('id') === 'abrir-codigomozo') {
            $ctx = $('#modal-abrir-cuenta-mozo-wrap');
        }
        if (!$ctx.length && $(ptrrenglon).attr('id') === 'cm-login-codigomozo') {
            $ctx = $('#modal-cm-login-mozo-mozo-wrap');
        }
        $ctx.find('.mozo_gastronomia_id').val('');
        $ctx.find('.codigomozo').val('');
        $ctx.find('.nombremozo').val('');
    } else {
        $('#mozo_gastronomia_id').val('');
        $('#nombremozo').val('');
        $('#codigomozo').val('');
    }

    $.get(url_res)
        .done(function (data) {
            if (!data || !data.id) {
                if (typeof onDone === 'function') {
                    onDone(null);
                }
                return;
            }
            if ($ctx && $ctx.length) {
                $ctx.find('.mozo_gastronomia_id').val(data.id);
                $ctx.find('.codigomozo').val(data.codigo);
                $ctx.find('.nombremozo').val(data.nombre);
            } else if (ptrrenglon) {
                $(ptrrenglon).parents('tr').find('.mozo_gastronomia_id').val(data.id);
                $(ptrrenglon).parents('tr').find('.codigomozo').val(data.codigo);
                $(ptrrenglon).parents('tr').find('.nombremozo').val(data.nombre);
            } else {
                $('#mozo_gastronomia_id').val(data.id);
                $('#nombremozo').val(data.nombre);
                $('#codigomozo').val(data.codigo);
            }
            if (typeof onDone === 'function') {
                onDone(data);
            }
        })
        .fail(function () {
            if (typeof onDone === 'function') {
                onDone(null);
            }
        });
}

if (typeof $ !== 'undefined') {
    $('#consultamozoModal')
        .off(
            'show.bs.modal.consultaMozoStack hidden.bs.modal.consultaMozoStack shown.bs.modal.consultaMozoStack',
        )
        .on('show.bs.modal.consultaMozoStack', apilarConsultaMozoSobreModalPadre)
        .on('shown.bs.modal.consultaMozoStack', enfocarCampoConsultaMozo)
        .on('hidden.bs.modal.consultaMozoStack', desapilarConsultaMozoModal);
}
