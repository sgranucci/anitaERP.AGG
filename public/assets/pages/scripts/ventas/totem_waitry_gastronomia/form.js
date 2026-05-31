$(function () {
    const $empresa = $('#empresa_id');
    const $ubicacion = $('#ubicacion_id');

    if (!$empresa.length || !$ubicacion.length) {
        return;
    }

    const carpetaBase = typeof window.carpetaBase !== 'undefined' ? window.carpetaBase : '';
    const apiBase = carpetaBase + '/ventas/totem-waitry-gastronomia/api/ubicaciones-por-empresa';
    const ubicacionSeleccionada = String($ubicacion.val() || '');

    function opcionVacia(texto) {
        return $('<option>').attr('value', '').text(texto);
    }

    function rellenarUbicaciones(items, seleccionId) {
        $ubicacion.empty().append(opcionVacia('Seleccione…'));
        (items || []).forEach(function (item) {
            const opt = $('<option>').attr('value', item.id).text(item.nombre);
            if (String(seleccionId) === String(item.id)) {
                opt.prop('selected', true);
            }
            $ubicacion.append(opt);
        });
    }

    function cargarUbicacionesPorEmpresa(empresaId, seleccionId) {
        if (!empresaId) {
            rellenarUbicaciones([], '');
            return;
        }

        $.getJSON(apiBase + '/' + empresaId)
            .done(function (data) {
                rellenarUbicaciones(data.ubicaciones, seleccionId || '');
            })
            .fail(function () {
                rellenarUbicaciones([], '');
            });
    }

    $empresa.on('change', function () {
        cargarUbicacionesPorEmpresa(String($(this).val() || ''), '');
    });

    if ($empresa.is('select')) {
        const empresaInicial = String($empresa.val() || '');
        if (empresaInicial && $ubicacion.find('option').length <= 1) {
            cargarUbicacionesPorEmpresa(empresaInicial, ubicacionSeleccionada);
        }
    }
});
