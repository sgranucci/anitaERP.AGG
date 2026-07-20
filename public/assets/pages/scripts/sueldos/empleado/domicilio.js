// Domicilio del empleado: carga localidades por provincia y código postal por localidad.
// Mismo patrón que compras/proveedor/domicilio.js (rutas configuracion/leerlocalidades y leercodigopostal),
// adaptado a los IDs del formulario de empleado (#codigo_postal).

function completarLocalidadesEmpleado(provincia_id, reselectPrevia) {
    if (!provincia_id) {
        $("#localidad_id").empty().append('<option value=""></option>');
        return;
    }
    $.get(carpetaBase + 'configuracion/leerlocalidades/' + provincia_id, function (data) {
        var loc = $.map(data, function (value) { return [value]; });
        $("#localidad_id").empty();
        $("#localidad_id").append('<option value=""></option>');
        $.each(loc, function (index, value) {
            $("#localidad_id").append('<option value="' + value.id + '">' + value.nombre + '</option>');
        });
        if (reselectPrevia) {
            var previa = $("#localidad_id_previa").val();
            if (previa) {
                $("#localidad_id").val(previa);
                $("#desc_localidad").val($("#localidad_id option:selected").text());
            }
        }
    });
}

function completarCPEmpleado(localidad_id) {
    if (!localidad_id) return;
    $.get(carpetaBase + 'configuracion/leercodigopostal/' + localidad_id, function (data) {
        if (data != 0) {
            $("#codigo_postal").val(data);
        }
    });
}

$(function () {
    if (!document.getElementById('provincia_id')) return;

    $("#provincia_id").on('change', function () {
        var provincia_id = $(this).val();
        $("#desc_provincia").val($(this).children("option:selected").text());
        completarLocalidadesEmpleado(provincia_id, false);
    });

    $(document).on('change', '#localidad_id', function () {
        var localidad_id = $(this).val();
        $("#desc_localidad").val($(this).children("option:selected").text());
        completarCPEmpleado(localidad_id);
    });

    // Carga inicial (edición): repuebla localidades y reselecciona la previa.
    var provincia_id_ini = $("#provincia_id").val();
    if (provincia_id_ini) {
        completarLocalidadesEmpleado(provincia_id_ini, true);
    }
});
