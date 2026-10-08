// Domicilio del empleado: localidad por consulta (F1 / lupa / Enter), no por combo.
// Al cambiar la provincia se limpia la localidad; el modal filtra por #provincia_id.

function limpiarCampoLocalidadEmpleado() {
    if (window.__sincronizandoProvinciaDesdeLocalidad) {
        return;
    }
    var $campo = $('#localidad_id').closest('.tm-localidad-campo');
    if (typeof aplicarLocalidadEnCampo === 'function' && $campo.length) {
        aplicarLocalidadEnCampo($campo, null);
        return;
    }
    $('#localidad_id').val('');
    $('#localidad_id_previa').val('');
    $('#desc_localidad').val('');
    $('#codigolocalidad').val('');
    $('#nombrelocalidad').val('');
}

$(function () {
    if (!$('#provincia_id').length) {
        return;
    }

    $('#provincia_id').on('change', function () {
        var texto = $(this).children('option:selected').text();
        if ($(this).val()) {
            $('#desc_provincia').val(texto);
        } else {
            $('#desc_provincia').val('');
        }
        limpiarCampoLocalidadEmpleado();
    });
});
