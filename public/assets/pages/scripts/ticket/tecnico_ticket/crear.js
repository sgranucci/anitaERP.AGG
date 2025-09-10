
$(function () {

	$('#crea_usuario').on('click', function (event) {
		let nombre = $('#nombre').val();
		let areadestino_id = $('#areadestino_id').val();

		// Graba el usuario
		$.ajaxSetup({
			headers: {
				'X-CSRF-TOKEN': $('#csrf_token').val()
			}
		});
		let url = "/anitaERP/public/admin/usuario/crearusuarioremoto";

		$.ajax({
			type: "POST",
			url: url,
			data: {
				nombre: nombre,
				areadestino_id: areadestino_id
			},
			success: function (data) {
				if (data.mensaje == 'ok')
					alert("Usuario grabado con éxito");

				// Arma nuevamente select de usuarios
				armaSelectUsuario();
			},
			error: function (r) {
				alert("Error en grabación del usuario");
			}
		});

	});


});

function armaSelectUsuario()
{
	var select = $("#usuario_id");

	select.empty();
	select.append('<option value="">-- Seleccionar --</option>');

	// Lee usuarios
	$.get('/anitaERP/public/admin/usuario/leerusuario', function(data){
		var usuarios = $.map(data, function(value, index){
			return [value];
		});
		$.each(usuarios, function(index,value){
			if (value.id != usuario_id)
				select.append('<option value="'+value.id+'">'+value.nombre+'</option>');
			else
				select.append('<option value="'+value.id+'" selected>'+value.nombre+'</option>');
		});
	});
}

