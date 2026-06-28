var cuentacontablexcodigo;
var nombrexcodigo;
var codigoxcodigo;
var ptrCuentacontableContext;

function empresaIdParaConsultaCuentaContable($ctx) {
    var empresaId = 0;

    if ($ctx && $ctx.length) {
        var $tr = $ctx.is('tr') ? $ctx : $ctx.closest('tr');
        if ($tr.length && $tr.find('.empresa').length) {
            empresaId = parseInt($tr.find('.empresa').val(), 10) || 0;
        }
    }

    if (!empresaId) {
        empresaId = parseInt($('#consultaempresa_id').val(), 10) || 0;
    }
    if (!empresaId) {
        empresaId = parseInt($('#empresa_id').val(), 10) || 0;
    }

    return empresaId;
}

function actualizarLinkEditarCuentaContable($ctx, cuentaId) {
    if (!$ctx || !$ctx.length) {
        return;
    }
    var $link = $ctx.find('.btn-link-editar-cuentacontable');
    if (!$link.length) {
        return;
    }
    var id = parseInt(cuentaId, 10) || 0;
    if (id > 0) {
        $link.attr('href', carpetaBase + '/contable/cuentacontable/' + id + '/editar?origen=modal_consulta&vista=consulta').removeClass('d-none');
    } else {
        $link.attr('href', '#').addClass('d-none');
    }
}

function aplicarCuentaContableEnContexto($ctx, data) {
    if ($ctx && $ctx.length) {
        $ctx.find('.cuentacontable_id').first().val(data.id);
        $ctx.find('.codigocuentacontable').first().val(data.codigo);
        $ctx.find('.nombrecuentacontable').first().val(data.nombre);
        $ctx.find('.cuentacontable_id_previa').val(data.id);
        $ctx.find('.codigo_previo').val(data.codigo);
        actualizarLinkEditarCuentaContable($ctx, data.id);
    }

    $('#cuentacontable_id').val(data.id);
    $('#codigocuentacontable').val(data.codigo);
    $('#nombrecuentacontable').val(data.nombre);
    actualizarLinkEditarCuentaContable($('.tm-cuentacontable-campo').first(), data.id);
}

function limpiarCuentaContableEnContexto($ctx) {
    if ($ctx && $ctx.length) {
        $ctx.find('.cuentacontable_id').first().val('');
        $ctx.find('.codigocuentacontable').first().val('');
        $ctx.find('.nombrecuentacontable').first().val('');
        $ctx.find('.cuentacontable_id_previa').val('');
        $ctx.find('.codigo_previo').val('');
        actualizarLinkEditarCuentaContable($ctx, 0);
    }

    $('#cuentacontable_id').val('');
    $('#codigocuentacontable').val('');
    $('#nombrecuentacontable').val('');
    actualizarLinkEditarCuentaContable($('.tm-cuentacontable-campo').first(), 0);
}

function buscar_datos(consulta) {
    var empresa_id = empresaIdParaConsultaCuentaContable(ptrCuentacontableContext);

    $.ajax({
        url: carpetaBase+'/contable/cuentacontable/consultacuentacontable',
        type: 'POST',
        dataType: 'json',
	    headers: {
        	'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    	},
        data: {
            consulta: consulta,
            empresa_id: empresa_id
        },
    })
    .done(function(respuesta) {
        var html = '';
        if (respuesta && typeof respuesta === 'object' && respuesta.data !== undefined) {
            html = respuesta.data;
        } else if (typeof respuesta === 'string') {
            try {
                var parsed = JSON.parse(respuesta);
                html = parsed.data || '';
            } catch (e) {
                html = respuesta;
            }
        }
        $('#datoscuentas').html(html);
    })
    .fail (function() {
        console.log("error");
    });
}

function resolverPorCodigoCuentaContable(codigo, $ctx) {
    var codigoNuevo = $.trim(codigo);
    var empresaId = empresaIdParaConsultaCuentaContable($ctx);

    if (!codigoNuevo) {
        limpiarCuentaContableEnContexto($ctx);
        return;
    }

    if (!empresaId) {
        alert('Debe ingresar empresa');
        return;
    }

    var urlCta = carpetaBase + '/contable/cuentacontable/leercuentacontableporcodigo/' + empresaId + '/' + encodeURIComponent(codigoNuevo);

    $.get(urlCta, function(data) {
        if (data && data.id > 0) {
            aplicarCuentaContableEnContexto($ctx, data);

            if ($ctx && $ctx.length && $ctx.is('tr') && $ctx.find('.cuentacontable_id_previa').length) {
                var codigoAnt = $ctx.find('.codigo_previo').val();
                if (codigoNuevo != codigoAnt && typeof leeCentroCosto === 'function') {
                    leeCentroCosto($ctx.find('.codigocuentacontable').get(0));
                }
            }
        } else {
            alert('No existe la cuenta');

            if ($ctx && $ctx.length && $ctx.is('tr') && $ctx.find('.cuentacontable_id_previa').length) {
                $ctx.remove();
            }

            limpiarCuentaContableEnContexto($ctx);
        }
    }).fail(function() {
        limpiarCuentaContableEnContexto($ctx);
    });
}

// Si pulsamos tecla enter en un Input no envia formulario
$("input").keydown(function (e){
    var keyCode= e.which;
    if (keyCode == 13){
      e.preventDefault();
      return false;
    }
});

$(document).on('keyup', '#consultacuentacontable', function () {
    var valor = $(this).val();
    if (valor != "") {
        buscar_datos(valor);
    } else {
        buscar_datos();
    }
});

function activa_eventos_consulta_cuentacontable()
{
    $('.codigocuentacontable').off('change.consultacta blur.consultacta').on('change.consultacta blur.consultacta', function (event) {
        var $input = $(this);
        var $ctx = $input.closest('.tm-cuentacontable-campo');
        if (!$ctx.length) {
            $ctx = $input.closest('tr');
        }

        if (event.type === 'blur') {
            if (!$ctx.length || !$ctx.hasClass('tm-cuentacontable-campo')) {
                return;
            }
        } else if ($ctx.hasClass('tm-cuentacontable-campo')) {
            return;
        }

        event.preventDefault();
        resolverPorCodigoCuentaContable($input.val(), $ctx.length ? $ctx : null);
    });

    $('.consultacuentacontable').off('click.consultacta').on('click.consultacta', function (event) {
        event.preventDefault();

        var $ctx = $(this).closest('.tm-cuentacontable-campo');
        if (!$ctx.length) {
            $ctx = $(this).closest('tr');
        }

        ptrCuentacontableContext = $ctx.length ? $ctx : null;
        cuentacontablexcodigo = $ctx.length ? $ctx.find('.cuentacontable_id').first() : $(this).parents('tr').find('.cuentacontable_id');
        nombrexcodigo = $ctx.length ? $ctx.find('.nombrecuentacontable').first() : $(this).parents('tr').find('.nombrecuentacontable');
        codigoxcodigo = $ctx.length ? $ctx.find('.codigocuentacontable').first() : $(this).parents('tr').find('.codigocuentacontable');

        var empresaId = empresaIdParaConsultaCuentaContable($ctx);

        if (empresaId > 0) {
            $('#consultaempresa_id').val(empresaId);
            $('#consultacuentaModal').modal('show');
            buscar_datos('');
        } else {
            alert('Debe ingresar empresa');
        }
    });

    $('#consultacuentaModal').off('shown.bs.modal.consultacta').on('shown.bs.modal.consultacta', function () {
        $(this).find('[autofocus]').focus();
    });

    $('#aceptaconsultacuentaModal').off('click.consultacta').on('click.consultacta', function () {
        $('#consultacuentaModal').modal('hide');
    });

    $(document).off('click.eligeconsultacuentacontable').on('click.eligeconsultacuentacontable', '.eligeconsultacuentacontable', function () {
        var $tr = $(this).closest('tr');
        var data = {
            id: $.trim($tr.find('.cuentacontable_id').first().text()),
            codigo: $.trim($tr.find('.codigocuentacontable').first().text()),
            nombre: $.trim($tr.find('.nombrecuentacontable').first().text()),
        };

        if (window.ptrIeCpFilaCuentaConcepto && window.ptrIeCpFilaCuentaConcepto.length
            && typeof window.ieComprobanteIvaAplicarCuenta === 'function') {
            window.ieComprobanteIvaAplicarCuenta(data.id, data.codigo, data.nombre);
            $('#consultacuentaModal').modal('hide');
            return;
        }

        var $ctx = ptrCuentacontableContext;
        if (!$ctx || !$ctx.length) {
            $ctx = null;
        }

        if (cuentacontablexcodigo && cuentacontablexcodigo.length) {
            cuentacontablexcodigo.val(data.id);
            nombrexcodigo.val(data.nombre);
            codigoxcodigo.val(data.codigo);
            cuentacontablexcodigo.parents('tr').find('.cuentacontable_id_previa').val(data.id);
            cuentacontablexcodigo.parents('tr').find('.codigo_previo').val(data.codigo);
        }

        if ($ctx && $ctx.length) {
            aplicarCuentaContableEnContexto($ctx, data);
        } else {
            $('#cuentacontable_id').val(data.id);
            $('#nombrecuentacontable').val(data.nombre);
            $('#codigocuentacontable').val(data.codigo);
        }

        $('#consultacuentaModal').modal('hide');
    });
}
