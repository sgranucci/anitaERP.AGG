$(function () {
    const $empresa = $('#empresa_id');
    const $pvCae = $('#puntoventa_cae_id');
    const $pvCaea = $('#puntoventa_caea_id');
    const $ubicacion = $('#ubicacion_id');

    if (!$empresa.length) {
        return;
    }

    const carpetaBase = typeof window.carpetaBase !== 'undefined' ? window.carpetaBase : '';
    const apiBase = carpetaBase + '/ventas/configuracion-puntoventa-gastronomia/api/selects-por-empresa';

    function opcionVacia(texto) {
        return $('<option>').attr('value', '').text(texto);
    }

    function limpiarPvYUbicacion() {
        $pvCae.empty().append(opcionVacia('Seleccione…'));
        $pvCaea.empty().append(opcionVacia('Seleccione…'));
        $ubicacion.empty().append(opcionVacia('Todas las ubicaciones'));
    }

    function rellenarPuntoventa($select, items) {
        $select.empty().append(opcionVacia('Seleccione…'));
        (items || []).forEach(function (item) {
            $select.append($('<option>').attr('value', item.id).text(item.label));
        });
    }

    function rellenarUbicaciones(items) {
        $ubicacion.empty().append(opcionVacia('Todas las ubicaciones'));
        (items || []).forEach(function (item) {
            $ubicacion.append($('<option>').attr('value', item.id).text(item.nombre));
        });
    }

    function cargarSelectsPorEmpresa(empresaId) {
        if (!empresaId) {
            limpiarPvYUbicacion();
            return;
        }

        $.getJSON(apiBase + '/' + empresaId)
            .done(function (data) {
                rellenarPuntoventa($pvCae, data.puntoventa_cae);
                rellenarPuntoventa($pvCaea, data.puntoventa_caea);
                rellenarUbicaciones(data.ubicaciones);
            })
            .fail(function () {
                limpiarPvYUbicacion();
            });
    }

    $empresa.on('change', function () {
        limpiarPvYUbicacion();
        cargarSelectsPorEmpresa(String($(this).val() || ''));
    });
});
