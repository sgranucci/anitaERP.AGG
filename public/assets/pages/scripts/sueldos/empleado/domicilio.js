// Domicilio del empleado: país / provincia / localidad por consulta (F1 / lupa / Enter).
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

    $(document).on('change', '#provincia_id', function () {
        var $campo = $(this).closest('.tm-provincia-campo');
        var nombre = String($campo.find('.nombreprovincia').val() || $('#nombreprovincia').val() || '').trim();
        if ($(this).val()) {
            $('#desc_provincia').val(nombre);
            $campo.find('.desc_provincia').val(nombre);
        } else {
            $('#desc_provincia').val('');
            $campo.find('.desc_provincia').val('');
        }
        limpiarCampoLocalidadEmpleado();
    });
});
