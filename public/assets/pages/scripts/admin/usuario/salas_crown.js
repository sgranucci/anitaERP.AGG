(function ($) {
    'use strict';

    function reordenar($select) {
        var $opts = $select.find('option').sort(function (a, b) {
            return String($(a).text()).localeCompare(String($(b).text()), 'es', { sensitivity: 'base' });
        });
        $select.empty().append($opts);
    }

    function syncHidden() {
        var $hidden = $('#salas_asignadas_hidden');
        $hidden.empty();
        $('#salas_asignadas_list option').each(function () {
            $hidden.append($('<input>', { type: 'hidden', name: 'sala_ids[]', value: $(this).val() }));
        });
    }

    function mover(haciaAsignados) {
        var $origen = $(haciaAsignados ? '#salas_disponibles' : '#salas_asignadas_list');
        var $destino = $(haciaAsignados ? '#salas_asignadas_list' : '#salas_disponibles');
        var $sel = $origen.find('option:selected');
        if (!$sel.length) {
            return;
        }
        $sel.each(function () {
            $(this).prop('selected', false);
            $destino.append(this);
        });
        reordenar($destino);
        syncHidden();
    }

    $(function () {
        if (!$('#usuario-asignacion-salas-crown').length) {
            return;
        }
        $(document).on('click', '.btn-dual-asignar-sala', function () { mover(true); });
        $(document).on('click', '.btn-dual-quitar-sala', function () { mover(false); });
        $(document).on('dblclick', '#salas_disponibles option', function () {
            $(this).prop('selected', true);
            mover(true);
        });
        $(document).on('dblclick', '#salas_asignadas_list option', function () {
            $(this).prop('selected', true);
            mover(false);
        });
    });
})(jQuery);
