$(function () {
    var $contenedor = $('#usuario-asignacion-dual');
    if (!$contenedor.length) {
        return;
    }

    var empresaUnica = $contenedor.data('empresa-unica') === 1 || $contenedor.data('empresa-unica') === '1';

    var grupos = {
        empresa: {
            disponible: '#empresas_disponibles',
            asignado: '#empresas_asignadas_list',
            hidden: '#empresas_asignadas_hidden',
            inputName: 'empresa_ids[]',
        },
        rol: {
            disponible: '#roles_disponibles',
            asignado: '#roles_asignados_list',
            hidden: '#roles_asignados_hidden',
            inputName: 'rol_id[]',
            validacion: '#rol_id_validacion',
        },
    };

    if (!empresaUnica) {
        grupos.empresa.validacion = '#empresa_ids_validacion';
    }

    function opcionesOrdenadas($select) {
        return $select.find('option').sort(function (a, b) {
            return String($(a).text()).localeCompare(String($(b).text()), 'es', { sensitivity: 'base' });
        });
    }

    function reordenarSelect($select) {
        var $ordenadas = opcionesOrdenadas($select);
        $select.empty().append($ordenadas);
    }

    function sincronizarHidden(grupo) {
        var cfg = grupos[grupo];
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
    }

    function cfgGrupo(grupo) {
        return grupos[grupo];
    }

    function moverSeleccion(grupo, haciaAsignados) {
        var cfg = cfgGrupo(grupo);
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
        sincronizarHidden(grupo);
    }

    $contenedor.on('click', '.btn-dual-asignar', function () {
        moverSeleccion($(this).data('grupo'), true);
    });

    $contenedor.on('click', '.btn-dual-quitar', function () {
        moverSeleccion($(this).data('grupo'), false);
    });

    $contenedor.on('dblclick', '#empresas_disponibles option', function () {
        if (empresaUnica) {
            return;
        }
        $(this).prop('selected', true);
        moverSeleccion('empresa', true);
    });

    $contenedor.on('dblclick', '#empresas_asignadas_list option', function () {
        if (empresaUnica) {
            return;
        }
        $(this).prop('selected', true);
        moverSeleccion('empresa', false);
    });

    $contenedor.on('dblclick', '#roles_disponibles option', function () {
        $(this).prop('selected', true);
        moverSeleccion('rol', true);
    });

    $contenedor.on('dblclick', '#roles_asignados_list option', function () {
        $(this).prop('selected', true);
        moverSeleccion('rol', false);
    });

    window.empresasUsuarioSeleccionadas = function () {
        if (empresaUnica) {
            var unica = $('#empresa_id').val();
            return unica ? [String(unica)] : [];
        }
        var ids = [];
        $('#empresas_asignadas_hidden input[name="empresa_ids[]"]').each(function () {
            ids.push(String($(this).val()));
        });
        return ids;
    };

    sincronizarHidden('rol');
    if (!empresaUnica) {
        sincronizarHidden('empresa');
    }
});
