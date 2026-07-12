$(function () {
    const $empresa = $('#empresa_id');
    const $ubicacion = $('#ubicacion_id');
    const $depositoPlatos = $('#deposito_platos_id');
    const $depositoInsumos = $('#deposito_insumos_id');

    if (!$empresa.length || $empresa.is('[readonly]') || $empresa.prop('disabled')) {
        return;
    }

    const carpetaBase = typeof window.carpetaBase !== 'undefined' ? window.carpetaBase : '';
    const apiBase = carpetaBase + '/ventas/gastronomia/viandas/configuracion-terminal/api/depositos';

    function opcionVacia(texto) {
        return $('<option>').attr('value', '').text(texto);
    }

    function rellenarDepositos(items) {
        $depositoPlatos.empty().append(opcionVacia('Seleccione…'));
        $depositoInsumos.empty().append(opcionVacia('Seleccione…'));
        (items || []).forEach(function (item) {
            const $opt = $('<option>').attr('value', item.id).text(item.label);
            $depositoPlatos.append($opt.clone());
            $depositoInsumos.append($opt.clone());
        });
    }

    function rellenarUbicaciones(items) {
        $ubicacion.empty().append(opcionVacia('— Sin ubicación —'));
        (items || []).forEach(function (item) {
            $ubicacion.append($('<option>').attr('value', item.id).text(item.nombre));
        });
    }

    function cargarSelectsPorEmpresa(empresaId) {
        if (!empresaId) {
            rellenarDepositos([]);
            rellenarUbicaciones([]);
            return;
        }

        $.getJSON(apiBase + '/' + empresaId)
            .done(function (data) {
                rellenarDepositos(data.depositos);
                rellenarUbicaciones(data.ubicaciones);
            })
            .fail(function () {
                rellenarDepositos([]);
                rellenarUbicaciones([]);
            });
    }

    $empresa.on('change', function () {
        cargarSelectsPorEmpresa(String($(this).val() || ''));
    });
});
