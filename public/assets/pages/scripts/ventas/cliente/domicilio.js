// Domicilio del ABM cliente: provincia/localidad por modal (no combo en cascada).

function limpiarCampoLocalidadDomicilio() {
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
    if (!$('#localidad_id').length && !$('#provincia_id').length) {
        return;
    }

    $(document).on('change.clienteDomicilio', '#tab-datos-principales #provincia_id', function () {
        limpiarCampoLocalidadDomicilio();
    });
});
