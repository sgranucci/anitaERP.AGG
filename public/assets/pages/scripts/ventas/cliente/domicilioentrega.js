// Localidad de lugares de entrega (ABM cliente)

    function completarLocalidadesEntrega(provincia, localidadIdSeleccionar) {
        var $prov = $(provincia);
        var $tr = $prov.closest('tr');
        var $sel = $tr.find('.localidades');
        var restaurar = localidadIdSeleccionar !== undefined
            ? localidadIdSeleccionar
            : $tr.find('.localidad_id_previas').val();
        var key = 'entrega-' + $tr.index();
        LocalidadCascada.completar(
            $sel,
            $prov.val(),
            restaurar,
            $tr.find('.desc_localidades').val(),
            key
        );
    }

    function completarCPEntrega(localidad_id, codigopostal) {
        LocalidadCascada.completarCP(localidad_id, $(codigopostal));
    }

    function activaEventoEntrega() {
        $(".provincias").off('change.localidadCascada');
        $(".localidades").off('change.localidadCascada');

        $(".provincias").on('change.localidadCascada', function () {
            completarLocalidadesEntrega(this, '');
        });

        $(".localidades").on('change.localidadCascada', function () {
            var $sel = $(this);
            var $tr = $sel.closest('tr');
            var localidad_id = $sel.val();
            $tr.find('.localidad_id_previas').val(localidad_id || '');
            $tr.find('.desc_localidades').val($sel.children('option:selected').text());
            if (!$sel.data('aplicandoLocalidadCascada')) {
                completarCPEntrega(localidad_id, $tr.find('.codigospostales'));
            }
        });

        if (typeof activa_eventos_consultazonavta === 'function') {
            activa_eventos_consultazonavta();
        }
    }
