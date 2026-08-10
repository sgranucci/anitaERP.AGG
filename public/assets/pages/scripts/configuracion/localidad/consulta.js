function buscar_datos_localidad(consulta) {
    let provincia_id = $("#provincia_id").val();

    $.ajax({
        url: carpetaBase+'/configuracion/localidad/consultalocalidad',
        type: 'POST',
        dataType: 'HTML',
	    headers: {
        	'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    	},
        data: {
            consulta: consulta,
            provincia_id: provincia_id
        },
    })
    .done (function(respuesta) {
		const resp = respuesta.replace(/\\/g, '');
        $("#datoslocalidad").html("");
        $("#datoslocalidad").html(resp);
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

$(document).on('keyup', '#consultalocalidad', function () {
    var valor = $(this).val();
    if (valor != "") {
        buscar_datos_localidad(valor);
    } else {
        buscar_datos_localidad();
    }
});

function activa_eventos_consultalocalidad()
{
    // Consulta de servicios
    $('.consultalocalidad').on('click', function (event) {
        // Abre modal de consulta
        $("#consultalocalidadModal").modal('show');
    });

    $('#consultalocalidadModal').on('shown.bs.modal', function () {
        $(this).find('[autofocus]').focus();
    })

    $('#aceptaconsultalocalidadModal').on('click', function () {
        $('#consultalocalidadModal').modal('hide');
    });

    $(document).on('click', '.eligeconsultalocalidad', function () {
        let seleccion = $(this).parents("tr").children().html();
        let nombre = $(this).parents("tr").find(".nombre").html();
        let codigo = $(this).parents("tr").find(".codigo").html();
        let codigopostal = $(this).parents("tr").find(".codigopostal").html();
        let provincia_id = $(this).parents("tr").find(".provincia_id").html();
        let nombreprovincia = $(this).parents("tr").find(".nombreprovincia").html();

        $("#localidad_id").val(seleccion);
        $("#nombrelocalidad").val(nombre);
        $("#codigolocalidad").val(codigo);
        $("#codigopostal").val(codigopostal);

        if (typeof window.sincronizarProvinciaDesdeLocalidad === 'function') {
            window.sincronizarProvinciaDesdeLocalidad({
                provincia_id: provincia_id,
                nombreprovincia: nombreprovincia,
                provincias: { id: provincia_id, nombre: nombreprovincia }
            });
        } else if (provincia_id && $('#provincia_id').length) {
            $('#provincia_id').val(String(provincia_id));
            if (nombreprovincia) {
                $('#desc_provincia').val($.trim(nombreprovincia));
            }
        }

        $('#consultalocalidadModal').modal('hide');
    });

    $(document).on('click', '.consultaunalocalidad', function () {
        let id = $(this).parents("tr").children().html();

        if (id > 0)
        {
            let urlConsultaLocalidad = route('editar_localidad', ':id');
            let url = urlConsultaLocalidad;
            url = url.replace(':id', id);
            document.location.href=url;
        }
    });

    $('#codigolocalidad').on('change', function (event) {
        event.preventDefault();

        // Lee servicio terrestre por codigo
        let codigolocalidad = $("#codigolocalidad").val();
        let url_res = carpetaBase+'/configuracion/leerlocalidad/'+codigolocalidad;

        $.get(url_res, function(data){
            if (data)
            {
                $("#localidad_id").val(data.id);
                $("#nombrelocalidad").val(data.nombre);
                $("#localidad").val(data.nombre);
                $("#codigolocalidad").val(data.codigo);
                $("#codigopostal").val(data.codigopostal);
                if (typeof window.sincronizarProvinciaDesdeLocalidad === 'function') {
                    window.sincronizarProvinciaDesdeLocalidad(data);
                } else if (data.provincia_id && $('#provincia_id').length) {
                    $('#provincia_id').val(String(data.provincia_id));
                    var nombreProv = (data.provincias && data.provincias.nombre) || '';
                    if (nombreProv) {
                        $('#desc_provincia').val(nombreProv);
                    }
                }
            }
        });
    });

}




