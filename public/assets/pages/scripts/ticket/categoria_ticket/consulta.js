var ptrcategoria_ticket_id;
var ptrnombrecategoria_ticket;

function buscar_datos_categoria_ticket(consulta) {
    let areadestino_id = $("#areadestino_id").val();

    $.ajax({
        url: '/anitaERP/public/ticket/consultacategoria_ticket',
        type: 'POST',
        dataType: 'HTML',
	    headers: {
        	'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    	},
        data: {
            consulta: consulta,
            areadestino_id: areadestino_id
        },
    })
    .done (function(respuesta) {
		const resp = respuesta.replace(/\\/g, '');
        $("#datoscategoria_ticket").html("");
        $("#datoscategoria_ticket").html(resp);
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

$(document).on('keyup', '#consultacategoria_ticket', function () {
    var valor = $(this).val();
    if (valor != "") {
        buscar_datos_categoria_ticket(valor);
    } else {
        buscar_datos_categoria_ticket();
    }
});

function activa_eventos_consultacategoria_ticket()
{
    // Consulta de servicios
    $('.consultacategoria_ticket').on('click', function (event) {
        let categoria_ticket_id = $("#categoria_ticket_id").val();

        ptrcategoria_ticket_id = $(this).parents("tr").find(".categoria_ticket_id");
		ptrnombrecategoria_ticket = $(this).parents("tr").find(".nombrecategoria_ticket");

        // Abre modal de consulta
        $("#consultacategoria_ticketModal").modal('show');

        // Si tiene algo en el filtro de categorias abre directo la consulta
        ($.isNumeric(categoria_ticket_id))
            buscar_datos_categoria_ticket();
    });

    $('#consultacategoria_ticketModal').on('shown.bs.modal', function () {
        $(this).find('[autofocus]').focus();
    })

    $('#aceptaconsultacategoria_ticketModal').on('click', function () {
        $('#consultacategoria_ticketModal').modal('hide');
    });

    $(document).on('click', '.eligeconsultacategoria_ticket', function () {
        let seleccion = $(this).parents("tr").children().html();
        let nombre = $(this).parents("tr").find(".nombre").html();
        let areadestino_id = $(this).parents("tr").find(".idareadestino").html();

        $(ptrcategoria_ticket_id).val(seleccion);
        $(ptrnombrecategoria_ticket).val(nombre);

        $("#categoria_ticket_id").val(seleccion);
        $("#nombrecategoria_ticket").val(nombre);
        $("#areadestino_id").val(areadestino_id);

        $('#consultacategoria_ticketModal').modal('hide');
    });

    // Si cambia el filtro blanquea el modal
    $('#areadestino_id').on('change', function (event) {
        event.preventDefault();

        $("#datoscategoria_ticket").html("");
        $("#datossubcategoria_ticket").html("");
        $("#categoria_ticket_id").val("");
        $("#nombrecategoria_ticket").val("");
        $("#subcategoria_ticket_id").val("");
        $("#nombresubcategoria_ticket").val("");

    });

    $('#categoria_ticket_id').on('change', function (event) {
        event.preventDefault();
        let areadestino_id = $("#areadestino_id").val();

        let categoria_ticket_id = $("#categoria_ticket_id").val();

        if ($.isNumeric(categoria_ticket_id))
        {
            let url_res = '/anitaERP/public/ticket/leercategoria_ticket/'+categoria_ticket_id;

            $("#categoria_ticket_id").val("");
            $("#nombrecategoria_ticket").val("");
            
            $.get(url_res, function(data){
                if (data)
                {
                    if (data.areadestino_id != areadestino_id)
                        alert('No coincide area de destino');
                    else
                    {
                        $("#categoria_ticket_id").val(data.id);
                        $("#nombrecategoria_ticket").val(data.nombre);

                        $("#areadestino_id").val(data.areadestino_id);

                        $("#subcategoria_ticket_id").val("");
                        $("#nombresubcategoria_ticket").val("");
                    }
                }
            });

            setTimeout(() => {
            }, 1000);
        } 
        else
            $("#nombrecategoria_ticket").val("");
    });

    $('.categoria_ticket_id').on('change', function (event) {
        event.preventDefault();
        var ptrrenglon = this;

        // Lee concepto gasto
        let categoria_ticket_id = $(this).val();
        let url_res = '/anitaERP/public/ticket/leercategoria_ticket/'+categoria_ticket_id;

        $.get(url_res, function(data){
            if (data)
            {
                $(ptrrenglon).parents("tr").find(".categoria_ticket_id").val(data.id);
			    $(ptrrenglon).parents("tr").find(".nombrecategoria_ticket").val(data.nombre);

                $("#categoria_ticket_id").val(data.id);
                $("#nombrecategoria_ticket").val(data.nombre);
            }
        });

        setTimeout(() => {
        }, 1000);

    });

}




