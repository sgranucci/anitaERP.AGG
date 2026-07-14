var ptrTransporteContext;

function buscar_datos_transporte(consulta) {
    $.ajax({
        url: carpetaBase+'/ventas/transporte/consultatransporte',
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
        $("#datostransporte").html("");
        $("#datostransporte").html(resp);
    })
    .fail (function() {
        console.log("error");
    });
}

function aplicarTransporteEnContexto($ctx, data) {
    if ($ctx && $ctx.length) {
        $ctx.find('.transporte_id').first().val(data.id);
        $ctx.find('.codigotransporte').first().val(data.codigo);
        $ctx.find('.nombretransporte').first().val(data.nombre);
    }

    $('#transporte_id').val(data.id);
    $('#codigotransporte').val(data.codigo);
    $('#nombretransporte').val(data.nombre);
}

function limpiarTransporteEnContexto($ctx) {
    if ($ctx && $ctx.length) {
        $ctx.find('.transporte_id').first().val('');
        $ctx.find('.codigotransporte').first().val('');
        $ctx.find('.nombretransporte').first().val('');
    }

    $('#transporte_id').val('');
    $('#codigotransporte').val('');
    $('#nombretransporte').val('');
}

function resolverPorCodigoTransporte(codigo, $ctx) {
    var cod = $.trim(codigo);
    if (cod === '') {
        limpiarTransporteEnContexto($ctx);
        return;
    }

    var urlRes = carpetaBase + '/ventas/leertransporte/' + encodeURIComponent(cod);
    $.get(urlRes, function(data) {
        if (data && data.id) {
            aplicarTransporteEnContexto($ctx, data);
            if ($ctx && $ctx.length && $ctx.closest('#asignarKilosRemitoModal').length) {
                $('#asigna_kilos_porcentaje').trigger('focus');
            }
            // No enfocar #fechaentrega: en Firefox rueda/teclas pueden cambiar la fecha sin que se note.
        } else {
            limpiarTransporteEnContexto($ctx);
        }
    }).fail(function() {
        limpiarTransporteEnContexto($ctx);
    });
}

function abrirModalConsultaTransporteDesdeInput($input) {
    ptrTransporteContext = $input.closest('.tm-transporte-campo');
    if (!ptrTransporteContext.length) {
        ptrTransporteContext = null;
    }
    $('#consultatransporteModal').modal('show');
    buscar_datos_transporte('');
}

function esTeclaF1Transporte(e) {
    return e && (e.key === 'F1' || e.code === 'F1' || e.keyCode === 112);
}

function modalConsultaTransporteAbierto() {
    var m = document.getElementById('consultatransporteModal');
    return !!(m && (m.classList.contains('show') || m.classList.contains('in')));
}

/** Eleva z-index / backdrop cuando la consulta se abre sobre otro modal (ej. Asignar kilos). */
function apilarModalConsultaTransporteSiAnidado() {
    var visibles = document.querySelectorAll('.modal.show, .modal.in').length;
    if (visibles < 2) {
        return;
    }
    var $m = $('#consultatransporteModal');
    var zHijo = 1040 + (10 * visibles);
    $m.data('transporteModalApilado', true);
    $m.css('z-index', zHijo);
    setTimeout(function () {
        $('.modal-backdrop').last().css('z-index', zHijo - 1);
    }, 0);
}

function desapilarModalConsultaTransporteSiAnidado() {
    var $m = $('#consultatransporteModal');
    if (!$m.data('transporteModalApilado')) {
        return;
    }
    $m.removeData('transporteModalApilado');
    $m.css('z-index', '');
    if (document.querySelectorAll('.modal.show, .modal.in').length > 0) {
        $('body').addClass('modal-open');
    }
}

function contextoTransporteEnAsignaKilos() {
    return !!(ptrTransporteContext && ptrTransporteContext.length
        && ptrTransporteContext.closest('#asignarKilosRemitoModal').length);
}

// Si pulsamos tecla enter en un Input no envia formulario
$("input").keydown(function (e){
    // Capturamos qué tecla ha sido
    var keyCode= e.which;
    // Si la tecla es el Intro/Enter
    if (keyCode == 13){
      // Evitamos que se ejecute eventos
      e.preventDefault();
      // Devolvemos falso
      return false;
    }
  });

$(document).on('keyup', '#consultatransporte', function () {
    var valor = $(this).val();
    if (valor != "") {
        buscar_datos_transporte(valor);
    } else {
        buscar_datos_transporte();
    }
});

function activa_eventos_consultatransporte()
{
    // Consulta de transportes
    $('.consultatransporte').off('click.transporte').on('click.transporte', function (event) {
        ptrTransporteContext = $(this).closest('.tm-transporte-campo');
        if (!ptrTransporteContext.length) {
            ptrTransporteContext = null;
        }
        $("#consultatransporteModal").modal('show');
        buscar_datos_transporte('');
    });

    $('#consultatransporteModal').off('shown.bs.modal.transporte').on('shown.bs.modal.transporte', function () {
        apilarModalConsultaTransporteSiAnidado();
        $(this).find('[autofocus]').focus();
    });

    $('#consultatransporteModal').off('hidden.bs.modal.transporteStack').on('hidden.bs.modal.transporteStack', function () {
        desapilarModalConsultaTransporteSiAnidado();
        if (contextoTransporteEnAsignaKilos()) {
            setTimeout(function () {
                $('#asigna_kilos_porcentaje').trigger('focus');
            }, 50);
        }
    });

    $('#aceptaconsultatransporteModal').off('click.transporte').on('click.transporte', function () {
        $('#consultatransporteModal').modal('hide');
    });

    $(document).off('click.eligeconsultatransporte').on('click.eligeconsultatransporte', '.eligeconsultatransporte', function () {
        let $tr = $(this).parents("tr");
        let data = {
            id: $.trim($tr.find(".id").html()),
            nombre: $.trim($tr.find(".nombre").html()),
            codigo: $.trim($tr.find(".codigo").html()),
        };

        if (ptrTransporteContext && ptrTransporteContext.length) {
            aplicarTransporteEnContexto(ptrTransporteContext, data);
        } else {
            $("#transporte_id").val(data.id);
            $("#nombretransporte").val(data.nombre);
            $("#codigotransporte").val(data.codigo);
        }

        var enAsignaKilos = contextoTransporteEnAsignaKilos();
        $('#consultatransporteModal').modal('hide');
        if (enAsignaKilos) {
            setTimeout(function () {
                $('#asigna_kilos_porcentaje').trigger('focus');
            }, 50);
        }
        // No enfocar #fechaentrega tras elegir reparto (evita cambio accidental de fecha).
    });

    // F1 en campo código de reparto/transporte (formulario o modal Asignar kilos)
    $(document).off('keydown.transporteF1').on('keydown.transporteF1', '.codigotransporte', function (e) {
        if (!esTeclaF1Transporte(e)) {
            return;
        }
        if (modalConsultaTransporteAbierto()) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        abrirModalConsultaTransporteDesdeInput($(this));
    });

    $('.codigotransporte').off('change.transporte blur.transporte').on('change.transporte blur.transporte', function (event) {
        if (event.type === 'blur' && !$(this).closest('.tm-transporte-campo').length && this.id !== 'codigotransporte') {
            return;
        }

        event.preventDefault();
        var $ctx = $(this).closest('.tm-transporte-campo');
        resolverPorCodigoTransporte($(this).val(), $ctx.length ? $ctx : null);
    });

    // Consulta de transportes
    $('.consultadesdetransporte').off('click.transporteDesde').on('click.transporteDesde', function (event) {
        ptrTransporteContext = null;
        $("#consultatransporteModal").modal('show');
    });

    $('#codigodesdetransporte').off('change.transporteDesde').on('change.transporteDesde', function (event) {
        event.preventDefault();

        let codigotransporte = $("#codigodesdetransporte").val();
        let url_res = carpetaBase+'/ventas/leertransporte/'+codigotransporte;

        $.get(url_res, function(data){
            if (data)
            {
                $("#desdetransporte_id").val(data.id);
                $("#nombredesdetransporte").val(data.nombre);
                $("#codigodesdetransporte").val(data.codigo);
            }
        });
    });    

    // Consulta de transportes
    $('.consultahastatransporte').off('click.transporteHasta').on('click.transporteHasta', function (event) {
        ptrTransporteContext = null;
        $("#consultatransporteModal").modal('show');
    });

    $('#codigohastatransporte').off('change.transporteHasta').on('change.transporteHasta', function (event) {
        event.preventDefault();

        let codigotransporte = $("#codigohastatransporte").val();
        let url_res = carpetaBase+'/ventas/leertransporte/'+codigotransporte;

        $.get(url_res, function(data){
            if (data)
            {
                $("#hastatransporte_id").val(data.id);
                $("#nombrehastatransporte").val(data.nombre);
                $("#codigohastatransporte").val(data.codigo);
            }
        });
    });        
}



