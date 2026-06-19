$(function () {
    $('.reporte-empresas-dual').each(function () {
        initReporteEmpresasDual($(this));
    });
});

function initReporteEmpresasDual($contenedor) {
    if (!$contenedor.length) {
        return;
    }

    var prefix = String($contenedor.data('id-prefix') || 'reporte');
    var empresaUnica = $contenedor.data('empresa-unica') === 1 || $contenedor.data('empresa-unica') === '1';

    var cfg = {
        disponible: '#' + prefix + '_empresas_disponibles',
        asignado: '#' + prefix + '_empresas_asignadas_list',
        hidden: '#' + prefix + '_empresas_asignadas_hidden',
        inputName: 'empresa_ids[]',
        validacion: '#' + prefix + '_empresa_ids_validacion',
    };

    function opcionesOrdenadas($select) {
        return $select.find('option').sort(function (a, b) {
            return String($(a).text()).localeCompare(String($(b).text()), 'es', { sensitivity: 'base' });
        });
    }

    function reordenarSelect($select) {
        var $ordenadas = opcionesOrdenadas($select);
        $select.empty().append($ordenadas);
    }

    function sincronizarHidden() {
        var $hidden = $(cfg.hidden);
        var $asignado = $(cfg.asignado);
        $hidden.empty();
        $asignado.find('option').each(function () {
            $hidden.append(
                $('<input>', {
                    type: 'hidden',
                    name: cfg.inputName,
                    value: $(this).val(),
                })
            );
        });
        if (cfg.validacion) {
            $(cfg.validacion).val($asignado.find('option').length ? '1' : '');
        }
        $contenedor.trigger('reporte-empresas-cambiadas');
    }

    function moverSeleccion(haciaAsignados) {
        var $origen = $(haciaAsignados ? cfg.disponible : cfg.asignado);
        var $destino = $(haciaAsignados ? cfg.asignado : cfg.disponible);
        if (!$origen.length || !$destino.length) {
            return;
        }
        var $seleccionados = $origen.find('option:selected');
        if (!$seleccionados.length) {
            return;
        }
        $seleccionados.each(function () {
            $(this).prop('selected', false);
            $destino.append(this);
        });
        reordenarSelect($destino);
        sincronizarHidden();
    }

    if (!empresaUnica) {
        $contenedor.on('click', '.btn-reporte-dual-asignar', function () {
            moverSeleccion(true);
        });

        $contenedor.on('click', '.btn-reporte-dual-quitar', function () {
            moverSeleccion(false);
        });

        $contenedor.on('dblclick', cfg.disponible + ' option', function () {
            $(this).prop('selected', true);
            moverSeleccion(true);
        });

        $contenedor.on('dblclick', cfg.asignado + ' option', function () {
            $(this).prop('selected', true);
            moverSeleccion(false);
        });

        sincronizarHidden();
    }

    $contenedor.on('click', '.btn-toggle-consolidar-empresas', function () {
        var $btn = $(this);
        var $input = $($btn.data('input'));
        if (!$input.length) {
            return;
        }
        var activo = String($input.val()) !== '1';
        $input.val(activo ? '1' : '0');
        $btn.toggleClass('btn-success', activo);
        $btn.toggleClass('btn-outline-secondary', !activo);
    });
}
