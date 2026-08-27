// Combo provincia → localidad (configuración). Restaura el valor al volver el AJAX.
(function (window, $) {
    function baseApp() {
        var base = (window.carpetaBase != null) ? String(window.carpetaBase) : '';
        return base.replace(/\/$/, '');
    }

    function urlLeerLocalidades(provinciaId) {
        return baseApp() + '/configuracion/leerlocalidades/' + encodeURIComponent(provinciaId);
    }

    function urlLeerCodigoPostal(localidadId) {
        return baseApp() + '/configuracion/leercodigopostal/' + encodeURIComponent(localidadId);
    }

    function refrescarSelect($sel) {
        if ($sel.hasClass('select2-hidden-accessible')) {
            $sel.trigger('change.select2');
        }
    }

    function aplicarOpciones($sel, localidades, idSeleccionar, nombreFallback) {
        var seleccionado = idSeleccionar ? String(idSeleccionar) : '';
        $sel.data('aplicandoLocalidadCascada', true);
        $sel.empty();
        $sel.append($('<option>', { value: '', text: '' }));
        $.each(localidades || [], function (_, value) {
            $sel.append($('<option>', { value: String(value.id), text: value.nombre }));
        });
        if (seleccionado && $sel.find('option[value="' + seleccionado + '"]').length === 0) {
            $sel.append($('<option>', { value: seleccionado, text: nombreFallback || seleccionado }));
        }
        $sel.val(seleccionado || '');
        refrescarSelect($sel);
        $sel.trigger('change');
        $sel.data('aplicandoLocalidadCascada', false);
    }

    var pedidos = {};

    function completar($sel, provinciaId, restaurar, nombreFallback, key) {
        if (!$sel || !$sel.length) {
            return;
        }
        key = key || $sel.attr('id') || 'localidad';
        pedidos[key] = (pedidos[key] || 0) + 1;
        var pedido = pedidos[key];

        if (!provinciaId) {
            aplicarOpciones($sel, [], restaurar, nombreFallback);
            return;
        }

        $.get(urlLeerLocalidades(provinciaId), function (data) {
            if (pedido !== pedidos[key]) {
                return;
            }
            var loc = $.map(data || [], function (value) {
                return [value];
            });
            aplicarOpciones($sel, loc, restaurar, nombreFallback);
        });
    }

    function completarCP(localidadId, $input) {
        if (!localidadId || !$input || !$input.length) {
            return;
        }
        $.get(urlLeerCodigoPostal(localidadId), function (data) {
            if (data != 0) {
                $input.val(data);
            }
        });
    }

    window.LocalidadCascada = {
        completar: completar,
        completarCP: completarCP,
        aplicarOpciones: aplicarOpciones
    };
})(window, jQuery);
