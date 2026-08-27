// Carga de domicilio provincia/localidad/codigo postal (cliente UIF)

    function refrescarSelectDependienteUif($sel) {
        if ($sel.hasClass('select2-hidden-accessible')) {
            $sel.trigger('change.select2');
        }
    }

    function aplicarOpcionesLocalidadUif($sel, localidades, idSeleccionar, nombreFallback) {
        var seleccionado = idSeleccionar ? String(idSeleccionar) : '';
        $sel.data('uifAplicandoLocalidad', true);
        $sel.empty();
        $sel.append($('<option>', { value: '', text: '' }));
        $.each(localidades || [], function (_, value) {
            $sel.append($('<option>', { value: String(value.id), text: value.nombre }));
        });
        if (seleccionado && $sel.find('option[value="' + seleccionado + '"]').length === 0) {
            $sel.append($('<option>', { value: seleccionado, text: nombreFallback || seleccionado }));
        }
        if (seleccionado) {
            $sel.val(seleccionado);
        } else {
            $sel.val('');
        }
        refrescarSelectDependienteUif($sel);
        $sel.trigger('change');
        $sel.data('uifAplicandoLocalidad', false);
    }

    var pedidoLocalidadResidenciaUif = 0;

    function completarLocalidades(provincia_id, localidadIdSeleccionar) {
        var $sel = $("#localidad_uif_id");
        var restaurar = localidadIdSeleccionar !== undefined
            ? localidadIdSeleccionar
            : $("#localidad_uif_id_previa").val();
        var nombreFallback = $("#desc_localidad_uif").val();
        var pedido = ++pedidoLocalidadResidenciaUif;

        if (!provincia_id) {
            aplicarOpcionesLocalidadUif($sel, [], restaurar, nombreFallback);
            return;
        }

        $.get(carpetaBase+'/uif/leerlocalidadesuif/'+provincia_id, function(data){
            if (pedido !== pedidoLocalidadResidenciaUif) {
                return;
            }
            aplicarOpcionesLocalidadUif($sel, data, restaurar, nombreFallback);
        });
    }

    function completarCP(localidad_id){
        if (!localidad_id) {
            return;
        }
        $.get(carpetaBase+'/uif/leercodigopostaluif/'+localidad_id, function(data){
            if(data!=0){
                $("#codigopostal").val(data);
            }
        });
    }

    $(function () {
        var localidadResidenciaInicial = $("#localidad_uif_id_previa").val();

        completarLocalidades($("#provincia_uif_id").val(), localidadResidenciaInicial);

        $("#provincia_uif_id").on('change', function(){
            completarLocalidades($(this).val(), '');
        });

        $("#localidad_uif_id").on('change', function(){
            var $sel = $(this);
            var localidad_id = $sel.val();
            $("#localidad_uif_id_previa").val(localidad_id || '');
            $("#desc_localidad_uif").val($sel.children("option:selected").text());
            if (! $sel.data('uifAplicandoLocalidad')) {
                completarCP(localidad_id);
            }
        });
    });
