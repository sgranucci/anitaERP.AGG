$(function () {
    if (!$('#form-general[data-admin-usuario-depositos="1"]').length) {
        return;
    }

    $('#agrega_renglon_usuario_tipotransaccion_stock').on('click', agregaRenglonTipotransaccionStock);
    $(document).on('click', '.eliminar_usuario_tipotransaccion_stock', borraRenglonTipotransaccionStock);

    $('#botonform3').on('click', function () {
        $('.form1').hide();
        $('.form2').hide();
        $('.form3').show();
        $(this).removeClass('btn-info').addClass('btn-primary');
        $('#botonform1').removeClass('btn-primary').addClass('btn-info');
        $('#botonform2').removeClass('btn-primary').addClass('btn-info');
    });

    if (typeof activa_eventos_consultatipotransaccionstock === 'function') {
        activa_eventos_consultatipotransaccionstock();
    }

    $(document).on('change', '#tbody-usuario-tipotransaccion-stock-table .abreviaturatipotransaccionstock', function () {
        var $tr = $(this).closest('tr');
        var abreviatura = String($(this).val() || '').trim();
        if (!abreviatura) {
            $tr.find('.tipotransaccion_stock_id').val('');
            $tr.find('.nombretipotransaccionstock').val('');
            $tr.find('.operacion-tipotransaccion-stock').val('');
            return;
        }
        if (tipotransaccionStockDuplicadaEnTabla($tr, abreviatura)) {
            alert('Tipo de transacci\u00f3n ya cargado');
            $tr.find('.tipotransaccion_stock_id').val('');
            $(this).val('');
            $tr.find('.nombretipotransaccionstock').val('');
            $tr.find('.operacion-tipotransaccion-stock').val('');
        }
    });
});

function agregaRenglonTipotransaccionStock(event) {
    event.preventDefault();
    var renglon = $('#template-renglon-usuario-tipotransaccion-stock').html();
    $('#tbody-usuario-tipotransaccion-stock-table').append(renglon);
    if (typeof activa_eventos_consultatipotransaccionstock === 'function') {
        activa_eventos_consultatipotransaccionstock();
    }
    $('#usuario-tipotransaccion-stock-table').find('tr').last().find('.abreviaturatipotransaccionstock').focus();
}

function borraRenglonTipotransaccionStock(event) {
    event.preventDefault();
    $(this).closest('tr').remove();
}

function tipotransaccionStockDuplicadaEnTabla($trActual, abreviatura) {
    var duplicado = false;
    $('#tbody-usuario-tipotransaccion-stock-table .abreviaturatipotransaccionstock').each(function () {
        if ($(this).closest('tr').is($trActual)) {
            return;
        }
        if (String($(this).val() || '').trim().toUpperCase() === abreviatura.toUpperCase()) {
            duplicado = true;
        }
    });
    return duplicado;
}

window.esConsultaTipotransaccionStockAdminUsuario = function () {
    return $('#form-general[data-admin-usuario-depositos="1"]').length > 0;
};

window.payloadExtraConsultaTipotransaccionStock = function () {
    if (!window.esConsultaTipotransaccionStockAdminUsuario()) {
        return {};
    }
    return {
        omitir_filtro_usuario: 1,
    };
};
