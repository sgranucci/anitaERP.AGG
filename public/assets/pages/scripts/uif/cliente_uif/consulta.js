/**
 * Modal + campo consulta cliente UIF.
 * Uso: activa_eventos_consultacliente_uif();
 * Callback opcional: window.onClienteUifElegido = function(prefix, data) {}
 */
var ptrClienteUifCampo = null;
var consultaclienteUifModalAbriendo = false;

function csrfTokenClienteUif() {
    return $('meta[name="csrf-token"]').attr('content')
        || $('input[name="_token"]').first().val()
        || '';
}

function buscar_datos_cliente_uif(consulta, anitaOrigen) {
    var payload = {
        consulta: consulta || '',
        _token: csrfTokenClienteUif()
    };
    if (anitaOrigen) {
        payload.anita_origen = anitaOrigen;
    }
    if (typeof window.payloadExtraConsultaClienteUif === 'function') {
        var extra = window.payloadExtraConsultaClienteUif() || {};
        Object.keys(extra).forEach(function (k) {
            payload[k] = extra[k];
        });
    }

    $.ajax({
        url: carpetaBase + '/uif/consultacliente_uif',
        type: 'POST',
        dataType: 'HTML',
        headers: {
            'X-CSRF-TOKEN': csrfTokenClienteUif()
        },
        data: payload
    })
    .done(function (respuesta) {
        var resp = (respuesta || '').replace(/\\/g, '');
        $('#datoscliente_uif').html(resp);
    })
    .fail(function () {
        $('#datoscliente_uif').html(
            '<tr><td colspan="7" class="text-center text-danger">Error al consultar</td></tr>'
        );
    });
}

function campoClienteUifPorPrefix(prefix) {
    var $wrap = $('#tm_cliente_uif_' + prefix);
    if (!$wrap.length) {
        $wrap = $('.tm-cliente-uif-campo[data-prefix="' + prefix + '"]').first();
    }
    return {
        wrap: $wrap,
        id: $wrap.find('.cliente_uif_id'),
        codigo: $wrap.find('.codigocliente_uif'),
        descripcion: $wrap.find('.descripcioncliente_uif'),
        link: $wrap.find('.btn-link-editar-cliente-uif'),
        prefix: prefix
    };
}

function aplicarClienteUifEnCampo(campo, data) {
    if (!campo || !data) {
        return;
    }
    campo.id.val(data.id);
    campo.codigo.val(data.id);
    campo.descripcion.val(data.nombre || '');
    if (campo.link && campo.link.length) {
        var url = carpetaBase + '/uif/cliente_uif/' + data.id + '/editar?origen=modal_consulta&vista=consulta';
        campo.link.attr('href', url).removeClass('d-none');
    }
    if (typeof window.onClienteUifElegido === 'function') {
        window.onClienteUifElegido(campo.prefix, data);
    }
}

function limpiarClienteUifCampo(campo) {
    if (!campo) {
        return;
    }
    campo.id.val('');
    campo.codigo.val('');
    campo.descripcion.val('');
    if (campo.link && campo.link.length) {
        campo.link.attr('href', '#').addClass('d-none');
    }
    if (typeof window.onClienteUifElegido === 'function') {
        window.onClienteUifElegido(campo.prefix, null);
    }
}

function resolverClienteUifPorId(campo, id, avisar) {
    if (!campo) {
        return;
    }
    id = String(id || '').trim();
    if (!id || !$.isNumeric(id)) {
        limpiarClienteUifCampo(campo);
        return;
    }

    $.get(carpetaBase + '/uif/leercliente_uif/' + id)
        .done(function (data) {
            if (data && data.id) {
                aplicarClienteUifEnCampo(campo, data);
            } else {
                limpiarClienteUifCampo(campo);
                if (avisar) {
                    setTimeout(function () {
                        alert('Cliente UIF no encontrado.');
                    }, 0);
                }
            }
        })
        .fail(function () {
            limpiarClienteUifCampo(campo);
            if (avisar) {
                setTimeout(function () {
                    alert('Cliente UIF no encontrado.');
                }, 0);
            }
        });
}

function activa_eventos_consultacliente_uif() {
    $(document).off('click.consultacliente_uif', '.consultacliente_uif');
    $(document).on('click.consultacliente_uif', '.consultacliente_uif', function (e) {
        e.preventDefault();
        var prefix = $(this).data('prefix')
            || $(this).closest('.tm-cliente-uif-campo').data('prefix')
            || 'cliente_uif';
        ptrClienteUifCampo = campoClienteUifPorPrefix(prefix);
        consultaclienteUifModalAbriendo = true;
        $('#consultacliente_uif').val('');
        $('#datoscliente_uif').html('');
        $('#consultacliente_uifModal').modal('show');
        buscar_datos_cliente_uif('');
        setTimeout(function () {
            consultaclienteUifModalAbriendo = false;
        }, 400);
    });

    $('#consultacliente_uifModal').off('shown.bs.modal.consultacliente_uif');
    $('#consultacliente_uifModal').on('shown.bs.modal.consultacliente_uif', function () {
        $(this).find('[autofocus]').focus();
    });

    $(document).off('keyup.consultacliente_uif', '#consultacliente_uif');
    $(document).on('keyup.consultacliente_uif', '#consultacliente_uif', function (e) {
        if (e.which === 13) {
            e.preventDefault();
            var $primera = $('#datoscliente_uif tr').first().find('.eligeconsultacliente_uif');
            if ($primera.length) {
                $primera.trigger('click');
            }
            return;
        }
        var valor = $(this).val();
        buscar_datos_cliente_uif(valor);
    });

    $(document).off('click.eligeconsultacliente_uif', '.eligeconsultacliente_uif');
    $(document).on('click.eligeconsultacliente_uif', '.eligeconsultacliente_uif', function () {
        var $tr = $(this).closest('tr');
        var id = $.trim($tr.find('.id').text());
        if (!id) {
            return;
        }
        $('#consultacliente_uifModal').modal('hide');
        if (ptrClienteUifCampo) {
            resolverClienteUifPorId(ptrClienteUifCampo, id, true);
        }
    });

    $(document).off('keydown.consultacliente_uif_f1', '.codigocliente_uif');
    $(document).on('keydown.consultacliente_uif_f1', '.codigocliente_uif', function (e) {
        if (e.which === 112) {
            e.preventDefault();
            $(this).closest('.tm-cliente-uif-campo').find('.consultacliente_uif').trigger('click');
            return;
        }
        if (e.which === 13) {
            e.preventDefault();
            var prefix = $(this).data('prefix')
                || $(this).closest('.tm-cliente-uif-campo').data('prefix');
            var campo = campoClienteUifPorPrefix(prefix);
            resolverClienteUifPorId(campo, $(this).val(), true);
        }
    });

    $(document).off('blur.consultacliente_uif', '.codigocliente_uif');
    $(document).on('blur.consultacliente_uif', '.codigocliente_uif', function () {
        if (consultaclienteUifModalAbriendo || $('#consultacliente_uifModal').hasClass('show')) {
            return;
        }
        var prefix = $(this).data('prefix')
            || $(this).closest('.tm-cliente-uif-campo').data('prefix');
        var campo = campoClienteUifPorPrefix(prefix);
        var valor = $.trim($(this).val());
        if (!valor) {
            limpiarClienteUifCampo(campo);
            return;
        }
        if (String(campo.id.val()) === valor && campo.descripcion.val()) {
            return;
        }
        resolverClienteUifPorId(campo, valor, false);
    });

    $(document).off('input.consultacliente_uif', '.codigocliente_uif');
    $(document).on('input.consultacliente_uif', '.codigocliente_uif', function () {
        var prefix = $(this).data('prefix')
            || $(this).closest('.tm-cliente-uif-campo').data('prefix');
        var campo = campoClienteUifPorPrefix(prefix);
        if (String(campo.id.val()) !== String($(this).val())) {
            campo.id.val('');
            campo.descripcion.val('');
            if (campo.link && campo.link.length) {
                campo.link.addClass('d-none');
            }
        }
    });
}
