$(function () {
    var secuenciaAviso = $('#cliente-aviso-table tbody tr').length;

    function aplicarFilaAviso($fila) {
        var modo = ($fila.find('.aviso-modo').val() || 'aviso');
        var esAviso = modo !== 'accion';
        var $accion = $fila.find('.aviso-accion');
        $accion.toggleClass('bg-light', esAviso);
        $accion.css('pointer-events', esAviso ? 'none' : '');
        $accion.attr('tabindex', esAviso ? '-1' : '0');
        if (esAviso) {
            $accion.val('');
        }
        var sinVencimiento = $fila.find('.aviso-sin-vencimiento').val() === '1';
        var $check = $fila.find('.aviso-sin-vencimiento-check');
        var $hasta = $fila.find('.aviso-hasta');
        $check.prop('checked', sinVencimiento);
        $hasta.prop('readonly', sinVencimiento);
        if (sinVencimiento) {
            $hasta.val('');
        }
    }

    function renumerarChecksAviso() {
        $('#cliente-aviso-table tbody tr').each(function (indice) {
            var id = 'aviso-sin-vencimiento-' + indice;
            $(this).find('.aviso-sin-vencimiento-check').attr('id', id);
            $(this).find('label.custom-control-label').attr('for', id);
        });
    }

    $('#agrega_renglon_cliente_aviso').on('click', function (event) {
        event.preventDefault();
        var html = $('#template-renglon-cliente-aviso').html();
        if (!html) {
            return;
        }
        secuenciaAviso += 1;
        html = html.replace(/aviso-sin-vencimiento-nuevo/g, 'aviso-sin-vencimiento-n' + secuenciaAviso);
        $('#tbody-tabla-cliente-aviso').append(html);
        var $fila = $('#cliente-aviso-table tbody tr').last();
        aplicarFilaAviso($fila);
        $fila.find('.aviso-disparador').trigger('focus');
    });

    $(document).on('click', '.eliminar_cliente_aviso', function (event) {
        event.preventDefault();
        $(this).closest('tr').remove();
    });

    $(document).on('change', '#cliente-aviso-table .aviso-modo', function () {
        aplicarFilaAviso($(this).closest('tr'));
    });

    $(document).on('change', '#cliente-aviso-table .aviso-sin-vencimiento-check', function () {
        var $fila = $(this).closest('tr');
        $fila.find('.aviso-sin-vencimiento').val(this.checked ? '1' : '0');
        aplicarFilaAviso($fila);
    });

    $('#cliente-aviso-table tbody tr').each(function () {
        aplicarFilaAviso($(this));
    });
    renumerarChecksAviso();
});
