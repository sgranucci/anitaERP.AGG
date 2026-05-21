var ptrDeposito_id;
var ptrCodigoDeposito_id;
var ptrDescripcionDeposito;

function buscar_datos_deposito(consulta) {
    $.ajax({
        url: carpetaBase + '/stock/depmae/consultadeposito',
        type: 'POST',
        dataType: 'HTML',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
        },
        data: {
            consulta: consulta || '',
        },
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
            $('#datosdeposito').html(html);
        })
        .fail(function () {
            $('#datosdeposito').html('<tr><td colspan="5">Error al consultar depósitos</td></tr>');
        });
}

$('input').keydown(function (e) {
    if (e.which === 13) {
        e.preventDefault();
        return false;
    }
});

$(document).on('keyup', '#consultadeposito', function () {
    buscar_datos_deposito($(this).val());
});

function activa_eventos_consultadeposito() {
    $('.consultadeposito')
        .off('click.consultaDeposito')
        .on('click.consultaDeposito', function () {
            var $btn = $(this);
            var $ctx = $btn.closest('.tm-deposito-campo, .depmae-campo-consulta, tr');

            ptrDeposito_id = $ctx.find('.deposito_id');
            if (!ptrDeposito_id.length) {
                ptrDeposito_id = $btn.parents('tr').find('.deposito_id');
            }

            ptrCodigoDeposito_id = $ctx.find('.codigodeposito');
            if (!ptrCodigoDeposito_id.length) {
                ptrCodigoDeposito_id = $btn.parents('tr').find('.codigodeposito');
            }

            ptrDescripcionDeposito = $ctx.find('.descripciondeposito');
            if (!ptrDescripcionDeposito.length) {
                ptrDescripcionDeposito = $btn.parents('tr').find('.descripciondeposito');
            }

            $('#consultadepositoModal').modal('show');
        });

    $('#consultadepositoModal')
        .off('shown.bs.modal.consultaDeposito')
        .on('shown.bs.modal.consultaDeposito', function () {
            $(this).find('[autofocus]').focus();
            buscar_datos_deposito($('#consultadeposito').val());
        });

    $('#aceptaconsultadepositoModal')
        .off('click.consultaDeposito')
        .on('click.consultaDeposito', function () {
            $('#consultadepositoModal').modal('hide');
        });

    $(document)
        .off('click.eligeconsultadeposito')
        .on('click', '.eligeconsultadeposito', function () {
            var $tr = $(this).parents('tr');
            var id = $tr.find('.id').html();
            var codigo = $tr.find('.codigo').html();
            var descripcion = $tr.find('.descripcion').html();
            var tipodeposito = $tr.find('.tipodeposito').html() || '';

            if ($('#form-general').length && $('#codigo[name="codigo"]').length
                && typeof window.aplicarDepositoEnFormularioAbm === 'function') {
                if (window.aplicarDepositoEnFormularioAbm({
                    id: id,
                    codigo: codigo,
                    descripcion: descripcion,
                    tipodeposito: tipodeposito,
                })) {
                    return;
                }
                $('#consultadepositoModal').modal('hide');
                return;
            }

            if (ptrDeposito_id && ptrDeposito_id.length) {
                ptrDeposito_id.val(id);
                ptrDeposito_id.trigger('change');
            }
            if (ptrCodigoDeposito_id && ptrCodigoDeposito_id.length) {
                ptrCodigoDeposito_id.val(codigo);
            }
            if (ptrDescripcionDeposito && ptrDescripcionDeposito.length) {
                ptrDescripcionDeposito.val(descripcion);
            }

            $('#consultadepositoModal').modal('hide');
        });

    $(document)
        .off('change.leerDepositoCod', '.codigodeposito')
        .on('change.leerDepositoCod', '.codigodeposito', function (e) {
            e.preventDefault();
            leerDepositoPorCodigo($(this).val(), this);
        });
}

function leerDepositoPorCodigo(codigo, ptrrenglon, onDone) {
    var cod = (codigo || '').trim();
    if (!cod) {
        if (typeof onDone === 'function') {
            onDone(null);
        }
        return;
    }

    var $ctx = $(ptrrenglon).closest('.tm-deposito-campo, .depmae-campo-consulta, tr');
    if ($ctx.length) {
        $ctx.find('.deposito_id').val('');
        $ctx.find('.codigodeposito').val('');
        $ctx.find('.descripciondeposito').val('');
    }

    $.get(carpetaBase + '/stock/depmae/leer/' + encodeURIComponent(cod))
        .done(function (data) {
            if (!data || !data.id) {
                if (typeof onDone === 'function') {
                    onDone(null);
                }
                return;
            }

            if ($('#form-general').length && $('#codigo[name="codigo"]').length
                && typeof window.aplicarDepositoEnFormularioAbm === 'function') {
                if (window.aplicarDepositoEnFormularioAbm(data)) {
                    return;
                }
                if (typeof onDone === 'function') {
                    onDone(data);
                }
                return;
            }

            if ($ctx.length) {
                $ctx.find('.deposito_id').val(data.id).trigger('change');
                $ctx.find('.codigodeposito').val(data.codigo);
                $ctx.find('.descripciondeposito').val(data.descripcion);
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
