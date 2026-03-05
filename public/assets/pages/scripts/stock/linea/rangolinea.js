$(document).ready(function(){
	$("#desde_linea_id").change(function(){
		if ($('#hasta_linea_id').val() == '')
			$('#hasta_linea_id').val($(this).val());
	});
});
