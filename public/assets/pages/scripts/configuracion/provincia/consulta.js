var ptrprovincia_id;

function buscar_datos_provincia(consulta) {

    $.ajax({
        url: '/anitaERP/public/configuracion/provincia/consultaprovincia',
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
    // Consulta de servicios
    $('.consultaprovincia').on('click', function (event) {

        ptrprovincia_id = $(this).parents("tr").find(".provincia_id");

        // Abre modal de consulta
        $("#consultaprovinciaModal").modal('show');
    });

    $('#consultaprovinciaModal').on('shown.bs.modal', function () {
        $(this).find('[autofocus]').focus();
    });

    $('#aceptaconsultaprovinciaModal').on('click', function () {
        $('#consultaprovinciaModal').modal('hide');
    });

    $(document).on('click', '.eligeconsultaprovincia', function () {
        let seleccion = $(this).parents("tr").children().html();
        let nombre = $(this).parents("tr").find(".nombre").html();
        let codigo = $(this).parents("tr").find(".codigo").html();

        $("#provincia_id").val(seleccion);
        $("#nombreprovincia").val(nombre);
        $("#codigoprovincia").val(codigo);

        $(ptrprovincia_id).val(seleccion);
        $(ptrprovincia_id).parents("tr").find(".codigoprovincia").val(codigo);
        $(ptrprovincia_id).parents("tr").find(".nombreprovincia").val(nombre);

        $('#consultaprovinciaModal').modal('hide');
    });

    $('#codigoprovincia').on('change', function (event) {
        event.preventDefault();

        // Lee servicio terrestre por codigo
        let codigoprovincia = $("#codigoprovincia").val();
        let url_res = '/anitaERP/public/configuracion/leerunaprovincia/'+codigoprovincia;

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

    $('.codigoprovincia').on('change', function (event) {
        event.preventDefault();
        var ptrrenglon = this;

        let codigoprovincia = $(this).parents("tr").find(".codigoprovincia").val();
        let url_res = '/anitaERP/public/configuracion/leerunaprovincia/'+codigoprovincia;

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




