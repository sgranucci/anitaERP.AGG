$(function () {
    const $empresa = $('#empresa_id');
    const $pvCae = $('#puntoventa_cae_id');
    const $pvCaea = $('#puntoventa_caea_id');
    const $ubicacion = $('#ubicacion_id');
    const $depositoVenta = $('#deposito_venta_id');
    const $depositoInsumos = $('#deposito_insumos_id');
    const $mesaWaitry = $('#waitry_mesa_gastronomia_id');

    if (!$empresa.length) {
        return;
    }

    const carpetaBase = typeof window.carpetaBase !== 'undefined' ? window.carpetaBase : '';
    const apiBase = carpetaBase + '/ventas/configuracion-puntoventa-gastronomia/api/selects-por-empresa';

    function opcionVacia(texto) {
        return $('<option>').attr('value', '').text(texto);
    }

    function limpiarSelectsPorEmpresa() {
        $pvCae.empty().append(opcionVacia('Seleccione…'));
        $pvCaea.empty().append(opcionVacia('Seleccione…'));
        $ubicacion.empty().append(opcionVacia('Todas las ubicaciones'));
        $depositoVenta.empty().append(opcionVacia('Seleccione…'));
        $depositoInsumos.empty().append(opcionVacia('Seleccione…'));
        $mesaWaitry.empty().append(opcionVacia('Mesa por defecto de la sala'));
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

    function rellenarDepositos(items) {
        $depositoVenta.empty().append(opcionVacia('Seleccione…'));
        $depositoInsumos.empty().append(opcionVacia('Seleccione…'));
        (items || []).forEach(function (item) {
            const $opt = $('<option>').attr('value', item.id).text(item.label);
            $depositoVenta.append($opt.clone());
            $depositoInsumos.append($opt.clone());
        });
    }

    function rellenarMesasWaitry(items) {
        $mesaWaitry.empty().append(opcionVacia('Mesa por defecto de la sala'));
        (items || []).forEach(function (item) {
            $mesaWaitry.append($('<option>').attr('value', item.id).text(item.label));
        });
    }

    function rellenarPlanos(items) {
        const $tbody = $('#tabla-planos-pc tbody');
        if (!$tbody.length) {
            return;
        }
        $tbody.empty();
        if (!items || !items.length) {
            $tbody.append(
                $('<tr>').append(
                    $('<td colspan="4" class="text-muted text-center py-2">')
                        .text('No hay planos cargados para esta empresa.')
                )
            );
            return;
        }
        items.forEach(function (item) {
            const idCheck = 'plano_id_' + item.id;
            const $check = $('<div class="custom-control custom-checkbox">')
                .append($('<input type="checkbox" class="custom-control-input" name="plano_ids[]">')
                    .attr('id', idCheck).attr('value', item.id))
                .append($('<label class="custom-control-label">').attr('for', idCheck).html('&nbsp;'));
            const $orden = $('<input type="number" class="form-control form-control-sm" min="0" max="999">')
                .attr('name', 'plano_orden[' + item.id + ']')
                .val(item.orden || 0);

            $tbody.append(
                $('<tr>')
                    .append($('<td class="text-center">').append($check))
                    .append($('<td>').text(item.label))
                    .append($('<td>').text(item.sala || ''))
                    .append($('<td>').append($orden))
            );
        });
    }

    function cargarSelectsPorEmpresa(empresaId) {
        if (!empresaId) {
            limpiarSelectsPorEmpresa();
            rellenarPlanos([]);
            return;
        }

        $.getJSON(apiBase + '/' + empresaId)
            .done(function (data) {
                rellenarPuntoventa($pvCae, data.puntoventa_cae);
                rellenarPuntoventa($pvCaea, data.puntoventa_caea);
                rellenarUbicaciones(data.ubicaciones);
                rellenarDepositos(data.depositos);
                rellenarMesasWaitry(data.mesas_waitry);
                rellenarPlanos(data.planos);
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
