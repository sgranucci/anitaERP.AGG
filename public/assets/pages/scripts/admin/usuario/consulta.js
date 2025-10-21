var ptrusuario_id;
var ptrnombreusuario;
var consultaCentrocosto_id;

function buscar_datos_usuario(consulta) {
    let empresa_id = $("#empresa_id").val();

    $.ajax({
        url: '/anitaERP/public/configuracion/consultausuario',
        type: 'POST',
        dataType: 'HTML',
	    headers: {
        	'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    	},
        data: {
            consulta: consulta,
            empresa_id: empresa_id,
            centrocosto_id: consultaCentrocosto_id
        },
    })
    .done (function(respuesta) {
		const resp = respuesta.replace(/\\/g, '');
        $("#datosusuario").html("");
        $("#datosusuario").html(resp);
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

$(document).on('keyup', '#consultausuario', function () {
    var valor = $(this).val();
    if (valor != "") {
        buscar_datos_usuario(valor);
    } else {
        buscar_datos_usuario();
    }
});

function activa_eventos_consultausuario()
{
    $('.consultausuario').on('click', function (event) {
        let usuario_id = $("#usuario_id").val();

        ptrusuario_id = $(this).parents("tr").find(".usuario_id");
		ptrnombreusuario = $(this).parents("tr").find(".nombreusuario");
        consultaCentrocosto_id = $(this).parents("tr").find(".centrocosto").val();

        // Abre modal de consulta
        $("#consultausuarioModal").modal('show');

        ($.isNumeric(usuario_id))
            buscar_datos_usuario();
    });

    $('#consultausuarioModal').on('shown.bs.modal', function () {
        $(this).find('[autofocus]').focus();
    })

    $('#aceptaconsultausuarioModal').on('click', function () {
        $('#consultausuarioModal').modal('hide');
    });

    $(document).on('click', '.eligeconsultausuario', function () {
        let seleccion = $(this).parents("tr").children().html();
        let nombre = $(this).parents("tr").find(".nombre").html();

        // Chequea cambio de tecnico
        let usuario_id = $(ptrusuario_id).val();

        $(ptrusuario_id).val(seleccion);
        $(ptrnombreusuario).val(nombre);

        $("#usuario_id").val(seleccion);
        $("#nombreusuario").val(nombre);

        $('#consultausuarioModal').modal('hide');
    });

    // Si cambia el filtro blanquea el modal
    $('#empresa_id').on('change', function (event) {
        event.preventDefault();

        $("#datosusuario").html("");
    });

    $('#usuario_id').on('change', function (event) {
        event.preventDefault();

        let usuario_id = $("#usuario_id").val();

        if ($.isNumeric(usuario_id))
        {
            let url_res = '/anitaERP/public/configuracion/leerunusuario/'+usuario_id;

            $.get(url_res, function(data){
                if (data)
                {
                    $("#usuario_id").val(data.id);
                    $("#nombreusuario").val(data.nombre);
                }
            });

            setTimeout(() => {
            }, 1000);
        } 
        else
            $("#nombreusuario").val("");
    });

    $('.usuario_id').on('change', function (event) {
        event.preventDefault();
        var ptrrenglon = this;
        let empresa_id = $("#empresa_id").val();
        let usuario_id = $(ptrrenglon).parents("tr").find(".usuario_id").val();

        let url_res = '/anitaERP/public/configuracion/leerusuario/'+usuario_id;

        $(ptrrenglon).parents("tr").find(".usuario_id").val("");
		$(ptrrenglon).parents("tr").find(".nombreusuario").val("");

        $.get(url_res, function(data){
            if (data)
            {
                if (data.empresa_id != empresa_id)
                    alert('No coincide empresa');
                else
                {
                    $(ptrrenglon).parents("tr").find(".usuario_id").val(data.id);
                    $(ptrrenglon).parents("tr").find(".nombreusuario").val(data.nombre);

                    $("#usuario_id").val(data.id);
                    $("#nombreusuario").val(data.nombre);
                }
            }
        });

        setTimeout(() => {
        }, 1000);

    });

}




