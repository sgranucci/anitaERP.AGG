// Carga de provincia/localidad de nacimiento (cliente UIF)

    var pedidoLocalidadNacimientoUif = 0;

    function completarLocalidadesNacimientos(provincia_id, localidadIdSeleccionar) {
        var $sel = $("#localidadnacimiento_id");
        var restaurar = localidadIdSeleccionar !== undefined
            ? localidadIdSeleccionar
            : $("#localidadnacimiento_id_previa").val();
        var nombreFallback = $("#desc_localidadnacimiento").val();
        var pedido = ++pedidoLocalidadNacimientoUif;

        if (!provincia_id) {
            aplicarOpcionesLocalidadUif($sel, [], restaurar, nombreFallback);
            return;
        }

        $.get(carpetaBase+'/uif/leerlocalidadesuif/'+provincia_id, function(data){
            if (pedido !== pedidoLocalidadNacimientoUif) {
                return;
            }
            aplicarOpcionesLocalidadUif($sel, data, restaurar, nombreFallback);
        });
    }

    $(function () {
        var localidadNacimientoInicial = $("#localidadnacimiento_id_previa").val();

        completarLocalidadesNacimientos($("#provincianacimiento_id").val(), localidadNacimientoInicial);

        $("#provincianacimiento_id").on('change', function(){
            completarLocalidadesNacimientos($(this).val(), '');
        });

        $("#localidadnacimiento_id").on('change', function(){
            $("#localidadnacimiento_id_previa").val($(this).val() || '');
            $("#desc_localidadnacimiento").val($(this).children("option:selected").text());
        });
    });
