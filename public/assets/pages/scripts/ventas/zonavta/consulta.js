var ptrZonavtaContext;

function aplicarZonavtaEnContexto($ctx, data) {
    if (!$ctx || !$ctx.length) {
        return;
    }

    $ctx.find('.zonavta_id, #zonavta_id').first().val(data.id);
    $ctx.find('.codigozonavta').first().val(data.codigo);
    $ctx.find('.nombrezonavta').first().val(data.nombre);

    if ($ctx.find('#zonavta_id').length) {
        $('#zonavta_id_previa').val(data.id);
        $('#desc_zonavta').val(data.nombre);
    }
}

function limpiarZonavtaEnContexto($ctx) {
    if (!$ctx || !$ctx.length) {
        return;
    }

    $ctx.find('.zonavta_id, #zonavta_id').first().val('');
    $ctx.find('.codigozonavta').first().val('');
    $ctx.find('.nombrezonavta').first().val('');

    if ($ctx.find('#zonavta_id').length) {
        $('#zonavta_id_previa').val('');
        $('#desc_zonavta').val('');
    }
}

function resolverContextoZonavta($trigger) {
    var $ctx = $trigger.closest('.tm-zonavta-campo');
    if ($ctx.length) {
        return $ctx;
    }

    var $legacy = $trigger.closest('.form-group.row').filter(function () {
        return $(this).find('#zonavta_id, .zonavta_id').length > 0;
    }).first();

    return $legacy.length ? $legacy : null;
}

function buscar_datos_zonavta(consulta) {
    $.ajax({
        url: carpetaBase+'/ventas/zonavta/consultazonavta',
        type: 'POST',
        dataType: 'HTML',
	    headers: {
        	'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    	},
        data: {
            consulta: consulta,
        },
    })
    .done (function(respuesta) {
		const resp = respuesta.replace(/\\/g, '');
        $("#datoszonavta").html("");
        $("#datoszonavta").html(resp);
    })
    .fail (function() {
        console.log("error");
    });
}

function resolverPorCodigoZonavta(codigo, $ctx) {
    var codigoZona = $.trim(codigo);
    if (codigoZona === '') {
        limpiarZonavtaEnContexto($ctx);
        return;
    }

    var urlRes = carpetaBase + '/ventas/leerzonavta/' + encodeURIComponent(codigoZona);
    $.get(urlRes, function(data) {
        if (data && data.id) {
            aplicarZonavtaEnContexto($ctx, data);
        } else {
            limpiarZonavtaEnContexto($ctx);
        }
    }).fail(function() {
        limpiarZonavtaEnContexto($ctx);
    });
}

function abrirModalConsultaZonavtaDesdeInput($input) {
    ptrZonavtaContext = resolverContextoZonavta($input);
    $('#consultazonavtaModal').modal('show');
    buscar_datos_zonavta('');
}

function esTeclaF1Zonavta(e) {
    return e && (e.key === 'F1' || e.code === 'F1' || e.keyCode === 112);
}

function modalConsultaZonavtaAbierto() {
    var m = document.getElementById('consultazonavtaModal');
    return !!(m && (m.classList.contains('show') || m.classList.contains('in')));
}

function manejarF1CodigoZonavtaCapture(e) {
    if (!esTeclaF1Zonavta(e)) {
        return;
    }
    var target = e.target;
    if (!target || !target.classList || !target.classList.contains('codigozonavta')) {
        return;
    }
    if (target.readOnly || target.disabled) {
        return;
    }
    if (modalConsultaZonavtaAbierto()) {
        return;
    }
    e.preventDefault();
    e.stopImmediatePropagation();
    abrirModalConsultaZonavtaDesdeInput($(target));
}

function manejarEnterCodigoZonavtaCapture(e) {
    if (!(e && (e.key === 'Enter' || e.which === 13 || e.keyCode === 13))) {
        return;
    }
    var target = e.target;
    if (!target || !target.classList || !target.classList.contains('codigozonavta')) {
        return;
    }
    if (target.readOnly || target.disabled) {
        return;
    }
    e.preventDefault();
    e.stopImmediatePropagation();
    var $input = $(target);
    var $ctx = resolverContextoZonavta($input);
    resolverPorCodigoZonavta($input.val(), $ctx);
}

if (!window.__zonavtaF1CaptureActivo) {
    document.addEventListener('keydown', manejarF1CodigoZonavtaCapture, true);
    document.addEventListener('keydown', manejarEnterCodigoZonavtaCapture, true);
    window.__zonavtaF1CaptureActivo = true;
}

// Enter en inputs no envía el form; código zona/reparto validan por su handler.
if (!window.__zonavtaEnterGuardActivo) {
    $(document).on('keydown.zonavtaEnterGuard', 'input', function (e) {
        if (e.which !== 13 && e.key !== 'Enter') {
            return;
        }
        if ($(this).is('.codigozonavta, .codigotransporte')) {
            return;
        }
        e.preventDefault();
        return false;
    });
    window.__zonavtaEnterGuardActivo = true;
}

$(document).on('keyup', '#consultazonavta', function () {
    var valor = $(this).val();
    if (valor != "") {
        buscar_datos_zonavta(valor);
    } else {
        buscar_datos_zonavta();
    }
});

function activa_eventos_consultazonavta()
{
    $('.consultazonavta').off('click.zonavta').on('click.zonavta', function (event) {
        event.preventDefault();
        ptrZonavtaContext = resolverContextoZonavta($(this));

        $("#consultazonavtaModal").modal('show');
        buscar_datos_zonavta('');
    });

    $('#consultazonavtaModal').off('shown.bs.modal.zonavta').on('shown.bs.modal.zonavta', function () {
        $(this).find('[autofocus]').focus();
    });

    $('#aceptaconsultazonavtaModal').off('click.zonavta').on('click.zonavta', function () {
        $('#consultazonavtaModal').modal('hide');
    });

    $(document).off('click.eligeconsultazonavta').on('click.eligeconsultazonavta', '.eligeconsultazonavta', function () {
        var $row = $(this).closest('tr');
        var data = {
            id: $.trim($row.find('.id').first().text()),
            nombre: $.trim($row.find('.nombre').first().text()),
            codigo: $.trim($row.find('.codigo').first().text()),
        };

        if (ptrZonavtaContext && ptrZonavtaContext.length) {
            aplicarZonavtaEnContexto(ptrZonavtaContext, data);
        }

        $('#consultazonavtaModal').modal('hide');
    });

    $(document).off('click.consultaunazonavta').on('click.consultaunazonavta', '.consultaunazonavta', function () {
        var id = $.trim($(this).closest('tr').find('.id').first().text());

        if (id > 0) {
            var url = carpetaBase + '/ventas/zonavta/' + id + '/editar?origen=modal_consulta&vista=consulta';
            window.open(url, '_blank', 'noopener');
        }
    });

    $(document).off('keydown.zonavtaF1Enter', '.codigozonavta').on('keydown.zonavtaF1Enter', '.codigozonavta', function (e) {
        if (esTeclaF1Zonavta(e)) {
            if (modalConsultaZonavtaAbierto()) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            abrirModalConsultaZonavtaDesdeInput($(this));
            return;
        }
        if (e.which !== 13 && e.key !== 'Enter') {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        var $ctx = resolverContextoZonavta($(this));
        resolverPorCodigoZonavta($(this).val(), $ctx);
    });

    $('.tm-zonavta-campo .codigozonavta').off('blur.zonavta').on('blur.zonavta', function () {
        var $ctx = $(this).closest('.tm-zonavta-campo');
        resolverPorCodigoZonavta($(this).val(), $ctx);
    });

    $('#codigozonavta').off('change.zonavtaLegacy').on('change.zonavtaLegacy', function (event) {
        if ($(this).closest('.tm-zonavta-campo').length) {
            return;
        }

        event.preventDefault();
        resolverPorCodigoZonavta($(this).val(), resolverContextoZonavta($(this)));
    });
}

function leeZonaVta()
{
    var zonavta_id = $("#zonavta_id").val();
    var url_res = carpetaBase+'/ventas/leerzonavtaporid/'+zonavta_id;

    $.get(url_res, function(data){
        if (data) {
            aplicarZonavtaEnContexto($('#tab-datos-facturacion .tm-zonavta-campo').first(), data);
        }
    });
}
