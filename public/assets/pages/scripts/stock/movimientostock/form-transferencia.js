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

    function actualizarCamposTransferenciaEnSubmit(esT) {
        var $salida = $('#deposito_salida_id');
        var $entrada = $('#deposito_entrada_id');
        var $bienOrigen = $('#bien_uso_origen_id');
        var $bienDestino = $('#bien_uso_destino_id');

        if (esT) {
            $salida.attr('name', 'deposito_salida_id');
            $entrada.attr('name', 'deposito_entrada_id');
            $bienOrigen.attr('name', 'bien_uso_origen_id');
            $bienDestino.attr('name', 'bien_uso_destino_id');
        } else {
            $salida.prop('required', false).removeAttr('name');
            $entrada.prop('required', false).removeAttr('name');
            $bienOrigen.prop('required', false).removeAttr('name');
            $bienDestino.prop('required', false).removeAttr('name');
        }
    }

    function actualizarRequiredTransferencia() {
        var esT = esTransferencia();
        var origenBien = tipoOrigenBienUso();
        var destinoBien = tipoDestinoBienUso();

        $('#deposito_salida_id').prop('required', esT && !origenBien);
        $('#deposito_entrada_id').prop('required', esT && !destinoBien);
        $('#bien_uso_origen_id').prop('required', esT && origenBien);
        $('#bien_uso_destino_id').prop('required', esT && destinoBien);
        actualizarCamposTransferenciaEnSubmit(esT);
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
                window.copiarDepositoCampo('deposito_id', 'deposito_salida_id');
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
