// Carga de domicilio provincia/localidad/codigo postal (ABM proveedor)

    function completarLocalidades(provincia_id, localidadIdSeleccionar) {
        var restaurar = localidadIdSeleccionar !== undefined
            ? localidadIdSeleccionar
            : $("#localidad_id_previa").val();
        LocalidadCascada.completar(
            $("#localidad_id"),
            provincia_id,
            restaurar,
            $("#desc_localidad").val(),
            'localidad_id'
        );
    }

    function completarCP(localidad_id) {
        LocalidadCascada.completarCP(localidad_id, $("#codigopostal"));
    }

    $(function () {
        if (!$("#localidad_id").length) {
            return;
        }

        completarLocalidades($("#provincia_id").val(), $("#localidad_id_previa").val());

        $("#provincia_id").on('change', function () {
            $('#desc_provincia').val($(this).children("option:selected").text());
            completarLocalidades($(this).val(), '');
        });

        $("#localidad_id").on('change', function () {
            var $sel = $(this);
            var localidad_id = $sel.val();
            $("#localidad_id_previa").val(localidad_id || '');
            $("#desc_localidad").val($sel.children("option:selected").text());
            if (!$sel.data('aplicandoLocalidadCascada')) {
                completarCP(localidad_id);
            }
        });
    });
