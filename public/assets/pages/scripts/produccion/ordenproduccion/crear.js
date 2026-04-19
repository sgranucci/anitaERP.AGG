
window.rellenaAtributosArticuloOrdenProduccion = function (data) {
	if (!data) {
		return;
	}
	var tp = (data.tipoproductos && data.tipoproductos.nombre) ? data.tipoproductos.nombre : '';
	var cap = (data.capacidades && data.capacidades.nombre) ? data.capacidades.nombre : '';
	var marca = (data.mventas && data.mventas.nombre) ? data.mventas.nombre : '';
	var color = (data.colores && data.colores.nombre) ? data.colores.nombre : '';
	var tlf = (data.tipoliquidofrenos && data.tipoliquidofrenos.nombre) ? data.tipoliquidofrenos.nombre : '';
	$('#tipoproducto').val(tp);
	$('#capacidad').val(cap);
	$('#marca').val(marca);
	$('#color').val(color);
	$('#tipoliquidofreno').val(tlf);
};

$(function () {

	activa_eventos(true);

});

function sub()
{
	$('#form-general').submit();
}

function activa_eventos(flInicio)
{
	// Si esta agregando items desactiva los eventos
	if (!flInicio)
	{
	}

	// Activa eventos de consulta
	activa_eventos_consultaarticulo();

}

