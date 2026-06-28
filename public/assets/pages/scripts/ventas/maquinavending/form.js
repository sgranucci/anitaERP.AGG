$(function () {
    const $empresa = $('#empresa_id');
    const $ubicacion = $('#ubicacion_id');
    const $puntoventa = $('#puntoventa_id');

    if (!$empresa.length) {
        return;
    }

    const carpetaBase = typeof window.carpetaBase !== 'undefined' ? window.carpetaBase : '';
    const apiBase = carpetaBase + '/ventas/gastronomia/maquinas-vending/api/selects-por-empresa';
    const ubicacionSeleccionada = String($ubicacion.val() || '');
    const puntoventaSeleccionado = String($puntoventa.val() || '');

    function opcionVacia(texto) {
        return $('<option>').attr('value', '').text(texto);
    }

    function rellenarSelect($select, items, seleccionId, labelKey) {
        $select.empty().append(opcionVacia('Seleccione…'));
        (items || []).forEach(function (item) {
            const texto = labelKey ? item[labelKey] : item.nombre;
            const opt = $('<option>').attr('value', item.id).text(texto);
            if (String(seleccionId) === String(item.id)) {
                opt.prop('selected', true);
            }
            $select.append(opt);
        });
    }

    function cargarSelectsPorEmpresa(empresaId, seleccionUbicacion, seleccionPuntoventa) {
        if (!empresaId) {
            rellenarSelect($ubicacion, [], '', 'nombre');
            rellenarSelect($puntoventa, [], '', 'label');
            return;
        }

        $.getJSON(apiBase + '/' + empresaId)
            .done(function (data) {
                rellenarSelect($ubicacion, data.ubicaciones || [], seleccionUbicacion || '', 'nombre');
                const pvs = (data.puntoventas || []).map(function (pv) {
                    return { id: pv.id, label: pv.label };
                });
                rellenarSelect($puntoventa, pvs, seleccionPuntoventa || '', 'label');
            })
            .fail(function () {
                rellenarSelect($ubicacion, [], '', 'nombre');
                rellenarSelect($puntoventa, [], '', 'label');
            });
    }

    $empresa.on('change', function () {
        cargarSelectsPorEmpresa(String($(this).val() || ''), '', '');
        $('.tm-deposito-campo').each(function () {
            $(this).find('.deposito_id').val('');
            $(this).find('.codigodeposito').val('');
            $(this).find('.descripciondeposito').val('');
        });
    });

    if ($empresa.is('select')) {
        const empresaInicial = String($empresa.val() || '');
        if (empresaInicial && ($ubicacion.find('option').length <= 1 || $puntoventa.find('option').length <= 1)) {
            cargarSelectsPorEmpresa(empresaInicial, ubicacionSeleccionada, puntoventaSeleccionado);
        }
    }
});
