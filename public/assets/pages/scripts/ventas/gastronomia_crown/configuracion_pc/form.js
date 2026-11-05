$(function () {
    const $empresa = $('#empresa_id');
    const $pvCae = $('#puntoventa_cae_id');
    const $pvCaea = $('#puntoventa_caea_id');

    if (!$empresa.length || $empresa.is('[readonly]') || $empresa.is(':disabled')) {
        return;
    }

    const carpetaBase = typeof window.carpetaBase !== 'undefined' ? window.carpetaBase : '';
    const apiBase = carpetaBase + '/ventas/configuracion-puntoventa-gastronomia/api/selects-por-empresa';

    function opcionVacia(texto) {
        return $('<option>').attr('value', '').text(texto);
    }

    function rellenarPuntoventa($select, items, vacio) {
        const actual = String($select.val() || '');
        $select.empty().append(opcionVacia(vacio));
        (items || []).forEach(function (item) {
            $select.append($('<option>').attr('value', item.id).text(item.label));
        });
        if (actual && $select.find('option[value="' + actual + '"]').length) {
            $select.val(actual);
        }
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

    function cargar(empresaId) {
        if (!empresaId) {
            rellenarPuntoventa($pvCae, [], 'Seleccione…');
            rellenarPuntoventa($pvCaea, [], 'Seleccione…');
            rellenarPlanos([]);
            return;
        }
        $.getJSON(apiBase + '/' + empresaId)
            .done(function (data) {
                rellenarPuntoventa($pvCae, data.puntoventa_cae, 'Seleccione…');
                rellenarPuntoventa($pvCaea, data.puntoventa_caea, 'Seleccione…');
                rellenarPlanos(data.planos);
            });
    }

    $empresa.on('change', function () {
        cargar(String($(this).val() || ''));
    });
});
