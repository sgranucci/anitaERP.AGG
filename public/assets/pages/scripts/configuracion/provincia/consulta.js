var ptrprovincia_id;

function buscar_datos_provincia(consulta) {

    $.ajax({
        url: carpetaBase+'/configuracion/provincia/consultaprovincia',
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
        $("#datosprovincia").html("");
        $("#datosprovincia").html(resp);
    })
    .fail (function() {
        console.log("error");
    });
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

$(document).on('keyup', '#consultaprovincia', function () {
    var valor = $(this).val();
    if (valor != "") {
        buscar_datos_provincia(valor);
    } else {
        buscar_datos_provincia();
    }
});

function activa_eventos_consultaprovincia()
{
    function esTeclaF1Provincia(e) {
        return e && (e.key === 'F1' || e.code === 'F1' || e.keyCode === 112);
    }

    function abrirModalConsultaProvincia($origen) {
        var $tr = $origen && $origen.length ? $origen.closest('tr') : $();
        ptrprovincia_id = $tr.length ? $tr.find('.provincia_id') : $('#provincia_id');
        $('#consultaprovinciaModal').modal('show');
    }

    // Consulta de provincias
    $(document)
        .off('click.consultaProvincia', '.consultaprovincia')
        .on('click.consultaProvincia', '.consultaprovincia', function (event) {
            event.preventDefault();
            abrirModalConsultaProvincia($(this));
        });

    document.removeEventListener('keydown', window.__provinciaF1Capture, true);
    window.__provinciaF1Capture = function (e) {
        if (!esTeclaF1Provincia(e)) {
            return;
        }
        var target = e.target;
        if (!target || target.disabled) {
            return;
        }
        var $target = $(target);
        var esCampoProvincia = $target.hasClass('codigoprovincia')
            || $target.is('#codigoprovincia')
            || $target.hasClass('nombreprovincia')
            || $target.is('#nombreprovincia')
            || $target.hasClass('consultaprovincia')
            || $target.closest('.consultaprovincia').length > 0;
        if (!esCampoProvincia) {
            return;
        }
        if ($('#consultaprovinciaModal').hasClass('show') || $('#consultaprovinciaModal').is(':visible')) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        abrirModalConsultaProvincia($target);
    };
    document.addEventListener('keydown', window.__provinciaF1Capture, true);

    $('#consultaprovinciaModal').off('shown.bs.modal.consultaProvincia').on('shown.bs.modal.consultaProvincia', function () {
        $(this).find('[autofocus]').focus();
    });

    $('#aceptaconsultaprovinciaModal').off('click.consultaProvincia').on('click.consultaProvincia', function () {
        $('#consultaprovinciaModal').modal('hide');
    });

    $(document).off('click.eligeconsultaprovincia').on('click.eligeconsultaprovincia', '.eligeconsultaprovincia', function () {
        let seleccion = $(this).parents("tr").children().html();
        let nombre = $(this).parents("tr").find(".nombre").html();
        let codigo = $(this).parents("tr").find(".codigo").html();

        $("#provincia_id").val(seleccion);
        $("#nombreprovincia").val(nombre);
        $("#provincia").val(nombre);
        $("#codigoprovincia").val(codigo);

        $(ptrprovincia_id).val(seleccion);
        $(ptrprovincia_id).parents("tr").find(".codigoprovincia").val(codigo);
        $(ptrprovincia_id).parents("tr").find(".nombreprovincia").val(nombre);

        $('#consultaprovinciaModal').modal('hide');
    });

    $('#codigoprovincia').off('change.consultaProvincia').on('change.consultaProvincia', function (event) {
        event.preventDefault();

        // Lee servicio terrestre por codigo
        let codigoprovincia = $("#codigoprovincia").val();
        let url_res = carpetaBase+'/configuracion/leerunaprovincia/'+codigoprovincia;

        $.get(url_res, function(data){
            if (data)
            {
                $("#provincia_id").val(data.id);
                $("#nombreprovincia").val(data.nombre);
                $("#provincia").val(data.nombre);
                $("#codigoprovincia").val(data.codigo);
            }
        });
    });

    $('.codigoprovincia').off('change.consultaProvinciaFila').on('change.consultaProvinciaFila', function (event) {
        event.preventDefault();
        var ptrrenglon = this;

        let codigoprovincia = $(this).parents("tr").find(".codigoprovincia").val();
        let url_res = carpetaBase+'/configuracion/leerunaprovincia/'+codigoprovincia;

        $(ptrrenglon).parents("tr").find(".provincia_id").val("");
        $(ptrrenglon).parents("tr").find(".codigoprovincia").val("");
		$(ptrrenglon).parents("tr").find(".nombreprovincia").val("");

        $("#provincia_id").val("");
        $("#nombreprovincia").val("");

        $.get(url_res, function(data){
            if (data)
            {
                $(ptrrenglon).parents("tr").find(".provincia_id").val(data.id);
                $(ptrrenglon).parents("tr").find(".codigoprovincia").val(data.codigo);
                $(ptrrenglon).parents("tr").find(".nombreprovincia").val(data.nombre);

                $("#provincia_id").val(data.id);
                $("#nombreprovincia").val(data.nombre);
            }
        });

        setTimeout(() => {
        }, 1000);

    });


}




