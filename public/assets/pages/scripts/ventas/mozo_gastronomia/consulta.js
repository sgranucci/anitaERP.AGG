var ptrMozo_id;
var ptrCodigoMozo_id;
var ptrNombreMozo;

var GASTRONOMIA_MODAL_Z_BASE = 1050;
var GASTRONOMIA_MODAL_Z_STEP = 20;

function modalPadreAbiertoParaConsultaMozo() {
    var $padre = $('#modal-abrir-cuenta');
    return $padre.length > 0 && $padre.hasClass('show');
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
    if ($('#modal-abrir-cuenta').hasClass('show')) {
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
    if (typeof window.GASTRONOMIA !== 'undefined' && window.GASTRONOMIA.empresaId) {
        return window.GASTRONOMIA.empresaId;
    }
    return '';
}

function buscar_datos_mozo(consulta) {
    var data = { consulta: consulta || '' };
    if (typeof window.GASTRONOMIA !== 'undefined') {
        data.empresa_id = empresaIdFacturacionGastronomia();
    }

    $.ajax({
        url: carpetaBase + '/ventas/mozo-gastronomia/consultamozo',
        type: 'POST',
        dataType: 'HTML',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
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
            $('#datosmozo').html(html);
        })
        .fail(function () {
            console.log('error consulta mozo');
        });
}

$(document).on('keydown', 'input', function (e) {
    if (e.which !== 13) {
        return;
    }
    if ($(this).closest('#modal-abrir-cuenta, #consultamozoModal').length) {
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

    var url_res = carpetaBase + '/ventas/mozo-gastronomia/leer/' + encodeURIComponent(codigomozo);
    if (empresaId) {
        url_res += '?empresa_id=' + empresaId;
    }

    var $ctx = null;
    if (ptrrenglon) {
        $ctx = $(ptrrenglon).closest('#modal-abrir-cuenta-mozo-wrap, tr').first();
        if (!$ctx.length && $(ptrrenglon).attr('id') === 'abrir-codigomozo') {
            $ctx = $('#modal-abrir-cuenta-mozo-wrap');
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
