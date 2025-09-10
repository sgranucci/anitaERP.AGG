var ptrprovincia_uif_id;
var ptrnombreprovincia_uif;

function buscar_datos_provincia_uif(consulta) {
    $.ajax({
        url: '/anitaERP/public/uif/consultaprovincia_uif',
        type: 'POST',
        dataType: 'HTML',
	    headers: {
        	'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    	},
        data: {
            consulta: consulta
        },
    })
    .done (function(respuesta) {
		const resp = respuesta.replace(/\\/g, '');
        $("#datosprovincia_uif").html("");
        $("#datosprovincia_uif").html(resp);
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

$(document).on('keyup', '#consultaprovincia_uif', function () {
    var valor = $(this).val();
    if (valor != "") {
        buscar_datos_provincia_uif(valor);
    } else {
        buscar_datos_provincia_uif();
    }
});

function activa_eventos_consultaprovincia_uif()
{
    // Consulta de servicios
    $('.consultaprovincia_uif').on('click', function (event) {
        let provincia_uif_id = $("#provincia_uif_id").val();

        ptrprovincia_uif_id = $(this).parents("tr").find(".provincia_uif_id");
		ptrnombreprovincia_uif = $(this).parents("tr").find(".nombreprovincia_uif");

        // Abre modal de consulta
        $("#consultaprovincia_uifModal").modal('show');

        // Si tiene algo en el filtro de categorias abre directo la consulta
        ($.isNumeric(provincia_uif_id))
            buscar_datos_provincia_uif();
    });

    $('#consultaprovincia_uifModal').on('shown.bs.modal', function () {
        $(this).find('[autofocus]').focus();
    })

    $('#aceptaconsultaprovincia_uifModal').on('click', function () {
        $('#consultaprovincia_uifModal').modal('hide');
    });

    $(document).on('click', '.eligeconsultaprovincia_uif', function () {
        let seleccion = $(this).parents("tr").children().html();
        let nombre = $(this).parents("tr").find(".nombre").html();

        $(ptrprovincia_uif_id).val(seleccion);
        $(ptrnombreprovincia_uif).val(nombre);

        $("#provincia_uif_id").val(seleccion);
        $("#nombreprovincia_uif").val(nombre);

        $('#consultaprovincia_uifModal').modal('hide');
    });

    $('#provincia_uif_id').on('change', function (event) {
        event.preventDefault();
        let provincia_uif_id = $("#provincia_uif_id").val();

        if ($.isNumeric(provincia_uif_id))
        {
            let url_res = '/anitaERP/public/uif/leerprovincia_uif/'+provincia_uif_id;

            $("#provincia_uif_id").val("");
            $("#nombreprovincia_uif").val("");
            
            $.get(url_res, function(data){
                if (data)
                {
                    $("#provincia_uif_id").val(data.id);
                    $("#nombreprovincia_uif").val(data.nombre);
                }
            });

            setTimeout(() => {
            }, 1000);
        } 
        else
            $("#nombreprovincia_uif").val("");
    });

    $('.provincia_uif_id').on('change', function (event) {
        event.preventDefault();
        var ptrrenglon = this;

        // Lee concepto gasto
        let provincia_uif_id = $(this).val();
        let url_res = '/anitaERP/public/uif/leerprovincia_uif/'+provincia_uif_id;

        $.get(url_res, function(data){
            if (data)
            {
                $(ptrrenglon).parents("tr").find(".provincia_uif_id").val(data.id);
			    $(ptrrenglon).parents("tr").find(".nombreprovincia_uif").val(data.nombre);

                $("#provincia_uif_id").val(data.id);
                $("#nombreprovincia_uif").val(data.nombre);
            }
        });

        setTimeout(() => {
        }, 1000);

    });

}




