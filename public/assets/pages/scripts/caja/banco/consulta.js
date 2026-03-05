var ptrbanco_id;
var ptrnombrebanco;
var ptrcodigobanco;

function buscar_datos_banco(consulta) {
    $.ajax({
        url: '/anitaERP/public/caja/banco/consultabanco',
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
        $("#datosbanco").html("");
        $("#datosbanco").html(resp);
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

$(document).on('keyup', '#consultabanco', function () {
    var valor = $(this).val();
    if (valor != "") {
        buscar_datos_banco(valor);
    } else {
        buscar_datos_banco();
    }
});

function activa_eventos_consultabanco()
{
    // Consulta de servicios
    $('.consultabanco').on('click', function (event) {
        ptrbanco_id = $(this).parents("tr").find(".banco_id");
        ptrcodigobanco = $(this).parents("tr").find(".codigobanco");
		ptrnombrebanco = $(this).parents("tr").find(".nombrebanco");

        // Abre modal de consulta
        $("#consultabancoModal").modal('show');
    });

    $('#consultabancoModal').on('shown.bs.modal', function () {
        $(this).find('[autofocus]').focus();
    })

    $('#aceptaconsultabancoModal').on('click', function () {
        $('#consultabancoModal').modal('hide');
    });

    $(document).on('click', '.eligeconsultabanco', function () {
        let seleccion = $(this).parents("tr").children().html();
        let nombre = $(this).parents("tr").find(".nombre").html();
        let codigo = $(this).parents("tr").find(".codigo").html();

        $(ptrbanco_id).val(seleccion);
        $(ptrnombrebanco).val(nombre);
        $(ptrcodigobanco).val(codigo);

        $("#banco_id").val(seleccion);
        $("#nombrebanco").val(nombre);

        $('#consultabancoModal').modal('hide');
    });

    $(document).on('click', '.consultaunbanco', function () {
        let id = $(this).parents("tr").children().html();

        if (id > 0)
        {
            let urlConsultaBanco = route('editar_banco', ':id');
            let url = urlConsultaBanco;
            url = url.replace(':id', id);
            document.location.href=url;
        }
    });

    $('#banco_id').on('change', function (event) {
        event.preventDefault();

        // Lee servicio terrestre por codigo
        let banco_id = $("#banco_id").val();
        let url_res = '/anitaERP/public/caja/leerbanco/'+banco_id;

        $.get(url_res, function(data){
            if (data)
            {
                $(ptrbanco_id).val(data.id);
                $(ptrnombrebanco).val(data.nombre);

                $("#banco_id").val(data.id);
                $("#nombrebanco").val(data.nombre);
            }
        });

        setTimeout(() => {
        }, 1000);

    });

    $('.banco_id').on('change', function (event) {
        event.preventDefault();
        var ptrrenlong = this;

        // Lee concepto gasto
        let banco_id = $(this).val();
        let url_res = '/anitaERP/public/caja/leerbanco/'+banco_id;

        $.get(url_res, function(data){
            if (data)
            {
                $(ptrbanco_id).val(data.id);
                $(ptrnombrebanco).val(data.nombre);

                $(ptrrenlong).parents("tr").find(".banco_id").val(data.id);
			    $(ptrrenlong).parents("tr").find(".nombrebanco").val(data.nombre);

                $("#banco_id").val(data.id);
                $("#nombrebanco").val(data.nombre);
            }
        });

        setTimeout(() => {
        }, 1000);

    });

    $('.codigobanco').on('change', function (event) {
        event.preventDefault();
        var ptrrenlong = this;

        // Lee concepto gasto
        let codigobanco = $(this).val();
        let url_res = '/anitaERP/public/caja/leerbancoporcodigo/'+codigobanco;

        $.get(url_res, function(data){
            if (data)
            {
                $(ptrbanco_id).val(data.id);
                $(ptrnombrebanco).val(data.nombre);

                $(ptrrenlong).parents("tr").find(".banco_id").val(data.id);
			    $(ptrrenlong).parents("tr").find(".nombrebanco").val(data.nombre);

                $("#banco_id").val(data.id);
                $("#nombrebanco").val(data.nombre);
            }
        });

        setTimeout(() => {
        }, 1000);

    });    
}




