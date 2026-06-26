(function ($) {
    'use strict';

    function meta() {
        return typeof window.msTipoTransaccionMeta === 'function'
            ? window.msTipoTransaccionMeta()
            : { operacion: '', manejaContabilidad: false, origenBienUso: false, destinoBienUso: false, nombre: '' };
    }

    function esTransferencia() {
        return meta().operacion === 'T';
    }

    function tipoDestinoBienUso() {
        return meta().destinoBienUso;
    }

    function tipoOrigenBienUso() {
        return meta().origenBienUso;
    }

    function actualizarRequiredDepositoSimple(activo) {
        $('#deposito_id').prop('required', activo);
    }

    function actualizarRequiredTransferencia() {
        var esT = esTransferencia();
        var origenBien = tipoOrigenBienUso();
        var destinoBien = tipoDestinoBienUso();

        $('#deposito_salida_id').prop('required', esT && !origenBien);
        $('#deposito_entrada_id').prop('required', esT && !destinoBien);
        $('#bien_uso_origen_id').prop('required', esT && origenBien);
        $('#bien_uso_destino_id').prop('required', esT && destinoBien);
    }

    function actualizarPanelesTransferencia() {
        if ($('#ms_transferencia_vinculada').length) {
            return;
        }

        var esT = esTransferencia();
        var origenBien = tipoOrigenBienUso();
        var destinoBien = tipoDestinoBienUso();

        $('#ms_deposito_simple').toggle(!esT);
        $('#ms_panel_transferencia').toggle(esT);

        if (esT) {
            $('#deposito_id').prop('required', false).removeAttr('name');
            var depSimple = String($('#deposito_id').val() || '').trim();
            if (depSimple && !String($('#deposito_salida_id').val() || '').trim() && !origenBien) {
                $('#deposito_salida_id').val(depSimple);
                $('#deposito_salida_codigo').val($('#deposito_id_codigo').val() || '');
                $('#deposito_salida_descripcion').val($('#deposito_id_descripcion').val() || '');
                var tipoSimple = $('#tm_deposito_movimientostock').attr('data-tipodeposito') || '';
                if (tipoSimple) {
                    $('#tm_deposito_salida').attr('data-tipodeposito', tipoSimple);
                    $('#deposito_salida_id').attr('data-tipodeposito', tipoSimple);
                }
            }
        } else {
            $('#deposito_id').attr('name', 'deposito_id');
        }

        $('#tm_deposito_salida').toggle(esT && !origenBien);
        $('#ms_panel_bien_origen').toggle(esT && origenBien);
        $('#tm_deposito_entrada').toggle(esT && !destinoBien);
        $('#ms_panel_bien_destino').toggle(esT && destinoBien);

        actualizarRequiredDepositoSimple(!esT);
        actualizarRequiredTransferencia();

        if (typeof window.msRefrescarConversionFormulaFilas === 'function') {
            window.msRefrescarConversionFormulaFilas();
        }
    }

    $(document).on('change', '#tipotransaccion_stock_id', actualizarPanelesTransferencia);

    $(function () {
        actualizarPanelesTransferencia();
    });
})(jQuery);
