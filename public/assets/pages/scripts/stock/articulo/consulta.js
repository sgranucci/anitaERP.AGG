var ptrarticulo_id;
var ptrcodigoarticulo;
var ptrnombrearticulo;

function buscar_datos_articulo(consulta) {
    $.ajax({
        url: '/anitaERP/public/stock/product/consultaarticulo',
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
        $("#datos").html(resp);
    })
    .fail (function() {
        console.log("error");
    });
}

// Si pulsamos tecla enter en un Input no envia formulario
$("input").keydown(function (e){
    // Capturamos qué telca ha sido
    var keyCode= e.which;
    // Si la tecla es el Intro/Enter
    if (keyCode == 13){
      // Evitamos que se ejecute eventos
      event.preventDefault();
      // Devolvemos falso
      return false;
    }
  });

$(document).on('keyup', '#consulta', function () {
    var valor = $(this).val();
    if (valor != "") {
        buscar_datos_articulo(valor);
    } else {
        buscar_datos_articulo();
    }
});

function activa_eventos_consultaarticulo()
{
    // Consulta de articulo
    $('.consultaarticulo').on('click', function (event) {
        ptrarticulo_id = $(this).parents("tr").find(".articulo_id");
        ptrcodigoarticulo = $(this).parents("tr").find(".codigoarticulo");
		ptrnombrearticulo = $(this).parents("tr").find(".descripcionarticulo");

        // Abre modal de consulta
        $("#consultaarticuloModal").modal('show');
    });

    $('#consultaarticuloModal').on('shown.bs.modal', function () {
        $(this).find('[autofocus]').focus();
    })

    $('#aceptaconsultaarticuloModal').on('click', function () {
        $('#consultaarticuloModal').modal('hide');
    });

    $(document).on('click', '.eligeconsultaarticulo', function () {
        let seleccion = $(this).parents("tr").children().html();
        let codigo = $(this).parents("tr").find(".sku").html();
        let nombre = $(this).parents("tr").find(".descripcion").html();

        $(ptrarticulo_id).val(seleccion);
        $(ptrcodigoarticulo).val(codigo);
        $(ptrnombrearticulo).val(nombre);

        $("#articulo_id").val(seleccion);
        $("#nombrearticulo").val(nombre);

        $('#consultaarticuloModal').modal('hide');
    });

    $('#articulo_id').on('change', function (event) {
        event.preventDefault();

        // Lee servicio terrestre por codigo
        let articulo_id = $("#articulo_id").val();
        let url_res = '/anitaERP/public/caja/leerarticulo/'+articulo_id;

        $.get(url_res, function(data){
            if (data)
            {
                $(ptrarticulo_id).val(data.id);
                $(ptrnombrearticulo).val(data.nombre);

                $("#articulo_id").val(data.id);
                $("#nombrearticulo").val(data.nombre);
            }
        });

        setTimeout(() => {
        }, 1000);

    });

    $('.articulo_id').on('change', function (event) {
        event.preventDefault();
        var ptrrenlong = this;

        // Lee concepto gasto
        let articulo_id = $(this).val();
        let url_res = '/anitaERP/public/caja/leerarticulo/'+articulo_id;

        $.get(url_res, function(data){
            if (data)
            {
                $(ptrarticulo_id).val(data.id);
                $(ptrnombrearticulo).val(data.nombre);

                $(ptrrenlong).parents("tr").find(".articulo_id").val(data.id);
			    $(ptrrenlong).parents("tr").find(".nombrearticulo").val(data.nombre);

                $("#articulo_id").val(data.id);
                $("#nombrearticulo").val(data.nombre);
            }
        });

        setTimeout(() => {
        }, 1000);

    });

}


