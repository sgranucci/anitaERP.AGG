var ptrguia_id;
var ptrnombreguia;
var ptrcodigoguia;

function buscar_datos_guia(consulta) {
    $.ajax({
        url: '/anitaERP/public/receptivo/guia/consultaguia',
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
        $("#datosguia").html("");
        $("#datosguia").html(resp);
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

$(document).on('keyup', '#consultaguia', function () {
    var valor = $(this).val();
    if (valor != "") {
        buscar_datos_guia(valor);
    } else {
        buscar_datos_guia();
    }
});

function activa_eventos_consultaguia()
{
    // Consulta de servicios
    $('.consultaguia').on('click', function (event) {
        ptrguia_id = $(this).parents("tr").find(".guia_id");
		ptrnombreguia = $(this).parents("tr").find(".nombreguia");
        ptrcodigoguia = $(this).parents("tr").find(".codigoguia");

        // Abre modal de consulta
        $("#consultaguiaModal").modal('show');
    });

    $('#consultaguiaModal').on('shown.bs.modal', function () {
        $(this).find('[autofocus]').focus();
    })

    $('#aceptaconsultaguiaModal').on('click', function () {
        $('#consultaguiaModal').modal('hide');
    });

    $(document).on('click', '.eligeconsultaguia', function () {
        let seleccion = $(this).parents("tr").children().html();
        let nombre = $(this).parents("tr").find(".nombre").html();
        let codigo = $(this).parents("tr").find(".codigo").html();

        $(ptrguia_id).val(seleccion);
        $(ptrcodigoguia).val(codigo);
        $(ptrnombreguia).val(nombre);

        $("#guia_id").val(seleccion);
        $("#nombreguia").val(nombre);
        $("#codigoguia").val(codigo);

        $('#consultaguiaModal').modal('hide');
    });

    $('#codigoguia').on('change', function (event) {
        event.preventDefault();

        // Lee guia por codigo
        let codigoguia = $("#codigoguia").val();
        let url_res = '/anitaERP/public/receptivo/leerguia/'+codigoguia;

        $.get(url_res, function(data){
            if (data)
            {
                $("#guia_id").val(data.id);
                $("#nombreguia").val(data.nombre);
                $("#guia").val(data.nombre);
                $("#codigoguia").val(data.codigo);
            }
        });

        setTimeout(() => {
        }, 1000);

    });

    $('.codigoguia').on('change', function (event) {
        event.preventDefault();
        var ptrrenglon = this;

        // Lee guia por codigo
        let codigoguia = $(this).val();
        let url_res = '/anitaERP/public/receptivo/leerguia/'+codigoguia;

        $.get(url_res, function(data){
            if (data)
            {
                $(ptrrenglon).parents("tr").find(".guia_id").val(data.id);
                $(ptrrenglon).parents("tr").find(".codigoguia").val(data.codigo);
			    $(ptrrenglon).parents("tr").find(".nombreguia").val(data.nombre);
            }
        });

        setTimeout(() => {
        }, 1000);

    });
}




