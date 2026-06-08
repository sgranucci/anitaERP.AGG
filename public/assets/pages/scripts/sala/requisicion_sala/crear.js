(function () {
    'use strict';

    var urlNpu = window.requisicionSalaUrlNpu || '';

    function bindLinea($tr) {
        $tr.find('.eliminar_linea_sala').on('click', function () {
            if ($('#tabla-articulos-requisicion-sala tbody tr').length > 1) {
                $tr.remove();
            }
        });
        $tr.find('.codigoarticulo').on('change blur', function () {
            var sku = $(this).val();
            var $row = $(this).closest('tr');
            if (!sku || !urlNpu) {
                return;
            }
            $.getJSON(urlNpu, { sku: sku }).done(function (resp) {
                if (resp.encontrado) {
                    $row.find('.numeroparte-linea').val(resp.numeroparte).prop('readonly', true);
                } else {
                    $row.find('.numeroparte-linea').prop('readonly', false);
                }
            });
        });
    }

    $(function () {
        urlNpu = $('#form-general').data('url-npu') || urlNpu;
        $('#tabla-articulos-requisicion-sala tbody tr').each(function () {
            bindLinea($(this));
        });

        $('#agrega_renglon_sala').on('click', function () {
            var tpl = $('#template-linea-requisicion-sala').html();
            if (!tpl) {
                return;
            }
            var $row = $(tpl);
            $('#tabla-articulos-requisicion-sala tbody').append($row);
            bindLinea($row);
        });

        $(document).on('click', '.eliminar-archivo-requisicion-sala', function () {
            $(this).closest('.col-md-6').remove();
        });

        $('#botonform4').on('click', function () {
            $('.form1').hide();
            $('.form4').show();
        });
        $('#botonform1').on('click', function () {
            $('.form4').hide();
            $('.form1').show();
        });
    });
})();
