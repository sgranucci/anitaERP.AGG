$(function () {
    const $empresa = $('#empresa_id');
    const $pvCae = $('#puntoventa_cae_id');
    const $pvCaea = $('#puntoventa_caea_id');

    if (!$empresa.length) {
        return;
    }

    const carpetaBase = typeof window.carpetaBase !== 'undefined' ? window.carpetaBase : '';
    const apiBase = carpetaBase + '/caja/estacionamiento/configuracion-puntoventa/api/selects-por-empresa';

    function opcionVacia(texto) {
        return $('<option>').attr('value', '').text(texto);
    }

    function limpiarSelectsPorEmpresa() {
        $pvCae.empty().append(opcionVacia('Seleccione…'));
        $pvCaea.empty().append(opcionVacia('Seleccione…'));
    }

    function rellenarPuntoventa($select, items) {
        $select.empty().append(opcionVacia('Seleccione…'));
        (items || []).forEach(function (item) {
            $select.append($('<option>').attr('value', item.id).text(item.label));
        });
    }

    function cargarSelectsPorEmpresa(empresaId) {
        if (!empresaId) {
            limpiarSelectsPorEmpresa();
            return;
        }

        $.getJSON(apiBase + '/' + empresaId)
            .done(function (data) {
                rellenarPuntoventa($pvCae, data.puntoventa_cae);
                rellenarPuntoventa($pvCaea, data.puntoventa_caea);
            })
            .fail(function () {
                limpiarSelectsPorEmpresa();
            });
    }

    $empresa.on('change', function () {
        limpiarSelectsPorEmpresa();
        cargarSelectsPorEmpresa(String($(this).val() || ''));
    });
});
