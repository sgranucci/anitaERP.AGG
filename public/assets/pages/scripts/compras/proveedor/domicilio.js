// Domicilio del ABM proveedor: localidad por consulta (F1 / lupa), no por combo.
// Capital Federal tiene miles de calles en el maestro; el select nativo no se puede usar.

function limpiarCampoLocalidadProveedor() {
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
        }
        limpiarCampoLocalidadProveedor();
    });
});
