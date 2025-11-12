var ptrarticulo_id;
var ptrcodigoarticulo;
var ptrnombrearticulo;
var ptrunidadmedida;
var ptrcategoria_id;
var ptrsubcategoria_id;

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
        ptrunidadmedida = $(this).parents("tr").find(".unidadmedida");
        ptrcategoria_id = $(this).parents("tr").find(".categoria_id");
        ptrsubcategoria_id = $(this).parents("tr").find(".subcategoria_id");

        // Abre modal de consulta
        $("#consultaarticuloModal").modal('show');
    });

    $('#consultaarticuloModal').on('shown.bs.modal', function () {
        $(this).find('#consulta').focus();
    })

    $('#aceptaconsultaarticuloModal').on('click', function () {
        $('#consultaarticuloModal').modal('hide');
    });

    $(document).on('click', '.eligeconsultaarticulo', function () {
        let seleccion = $(this).parents("tr").children().html();
        let codigo = $(this).parents("tr").find(".sku").html();
        let nombre = $(this).parents("tr").find(".descripcion").html();
        let unidadmedida = $(this).parents("tr").find(".unidadmedida").html();
        let unidadmedida_id = $(this).parents("tr").find(".idunidadmedida").val();
        let categoria_id = $(this).parents("tr").find(".categoria_id").val();
        let subcategoria_id = $(this).parents("tr").find(".subcategoria_id").val();

        $(ptrarticulo_id).parents("tr").find(".unidadmedida_id").val(unidadmedida_id);

        $(ptrarticulo_id).val(seleccion);
        $(ptrcodigoarticulo).val(codigo);
        $(ptrnombrearticulo).val(nombre);
        $(ptrunidadmedida).val(unidadmedida);
        $(ptrcategoria_id).val(categoria_id);
        $(ptrsubcategoria_id).val(subcategoria_id);

        $("#articulo_id").val(seleccion);
        $("#nombrearticulo").val(nombre);

        if (unidadmedida.toUpperCase() == 'CAJ')
            $(ptrarticulo_id).parents("tr").find(".caja").focus();

        if (unidadmedida.toUpperCase() == 'UN')
            $(ptrarticulo_id).parents("tr").find(".pieza").focus();        
        
        if (unidadmedida.toUpperCase() == 'KG' || unidadmedida.toUpperCase() == 'KIL')
            $(ptrarticulo_id).parents("tr").find(".kilo").focus();           

        $('#consultaarticuloModal').modal('hide');

        // Si es salamin tira saca opciones que no van del descuento
        armaSelectDescuentoVenta(ptrarticulo_id);
    });

    $('#articulo_id').on('change', function (event) {
        event.preventDefault();

        // Lee servicio terrestre por codigo
        let articulo_id = $("#articulo_id").val();
        let url_res = '/anitaERP/public/stock/leerunarticulo/'+articulo_id;

        $.get(url_res, function(data){
            if (data)
            {
                $("#articulo_id").val(data.id);
                $("#descripcionarticulo").val(data.descripcion);

                $.each(data.unidadesdemedidas, function(index,value){
                    if (index == 'abreviatura')
                        $("#unidadmedida").val(value);
                });

            }
        });

        setTimeout(() => {
        }, 1000);

    });

    $('.articulo_id').on('change', function (event) {
        event.preventDefault();
        var ptrrenglon = this;

        // Lee concepto gasto
        let articulo_id = $(this).val();
        let url_res = '/anitaERP/public/stock/leerunarticulo/'+articulo_id;

        $.get(url_res, function(data){
            if (data)
            {
                $(ptrrenglon).parents("tr").find(".articulo_id").val(data.id);
			    $(ptrrenglon).parents("tr").find(".descripcionarticulo").val(data.descripcion);

                $.each(data.unidadesdemedidas, function(index,value){
                    if (index == 'abreviatura')
                        $(ptrrenglon).parents("tr").find(".unidadmedida").val(value);
                });

                $("#articulo_id").val(data.id);
                $("#descripcionarticulo").val(data.descripcion);
                $("#unidadmedida").val(data.unidadmedida);
            }
        });

        setTimeout(() => {
        }, 1000);

    });

    $('.codigoarticulo').on('change', function (event) {
        event.preventDefault();
        var ptrrenglon = this;

        let sku = $(this).val();
        let url_res = '/anitaERP/public/stock/leerunarticuloporsku/'+sku;

        $.get(url_res, function(data){
            if (data)
            {
                $(ptrarticulo_id).val(data.id);
                $(ptrnombrearticulo).val(data.descripcion);

                $(ptrrenglon).parents("tr").find(".articulo_id").val(data.id);
			    $(ptrrenglon).parents("tr").find(".descripcionarticulo").val(data.descripcion);
                $(ptrrenglon).parents("tr").find(".unidadmedida_id").val(data.unidadmedida_id);
                $(ptrrenglon).parents("tr").find(".categoria_id").val(data.categoria_id);
                $(ptrrenglon).parents("tr").find(".subcategoria_id").val(data.subcategoria_id);

                $.each(data.unidadesdemedidas, function(index,value){
                    if (index == 'abreviatura')
                        $(ptrrenglon).parents("tr").find(".unidadmedida").val(value);
                });

                $("#articulo_id").val(data.id);
                $("#descripcionarticulo").val(data.nombre);
                $("#unidadmedida").val(data.unidadmedida);

                let unidadmedida = $(ptrrenglon).parents("tr").find(".unidadmedida").val();

                if (unidadmedida.toUpperCase() == 'CAJ')
                    $(ptrrenglon).parents("tr").find(".caja").focus();

                if (unidadmedida.toUpperCase() == 'UN')
                    $(ptrrenglon).parents("tr").find(".pieza").focus();

                if (unidadmedida.toUpperCase() == 'KG' || unidadmedida.toUpperCase() == 'KIL')                    
                    $(ptrrenglon).parents("tr").find(".kilo").focus();  
                
                asignaPrecio(ptrrenglon, data.id, '');

                if (!controlDescuento(ptrrenglon))
                {
                    alert("No puede cargar el artículo");
                    borraRenglon();
                }

                // Si es salamin tira saca opciones que no van del descuento
                armaSelectDescuentoVenta(ptrrenglon);
            }
        });

        setTimeout(() => {
        }, 1000);

    });    

}


