/**
 * Filtra selects de depósito según #empresa_id (opciones con data-empresa-id).
 */
(function () {
    'use strict';

    function empresaIdActivo() {
        var $emp = $('#empresa_id');
        if (!$emp.length) {
            return '';
        }
        return String($emp.val() || '').trim();
    }

    function filtrarSelect($select, empresaId) {
        if (!$select || !$select.length) {
            return;
        }

        var valorPrevio = String($select.val() || '');
        var visibleSeleccionado = false;

        $select.find('option').each(function () {
            var $opt = $(this);
            var val = String($opt.val() || '');
            if (val === '') {
                $opt.prop('hidden', false).prop('disabled', false);
                return;
            }

            var empDep = String($opt.data('empresa-id') || '');
            var visible = !empresaId || empDep === empresaId;
            $opt.prop('hidden', !visible).prop('disabled', !visible);
            if (visible && val === valorPrevio) {
                visibleSeleccionado = true;
            }
        });

        if (!visibleSeleccionado) {
            $select.val('');
        }

        $select.trigger('change');
    }

    function aplicarFiltroDeposito() {
        var empresaId = empresaIdActivo();
        filtrarSelect($('#deposito_id'), empresaId);
        filtrarSelect($('#deposito_origen_id'), empresaId);
        filtrarSelect($('#deposito_destino_id'), empresaId);
    }

    $(document).on('change', '#empresa_id', aplicarFiltroDeposito);

    $(function () {
        aplicarFiltroDeposito();
    });
})();
