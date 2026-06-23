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
            setTimeout(function () {
                $row.find('.codigoarticulo').trigger('focus');
            }, 0);
        });

        $(document).on('click', '#botonform0', function (e) {
            e.preventDefault();
            var $f = $('#form-general');
            if ($f.length) {
                $f.trigger('submit');
            }
        });

        $('#botonform1').on('click', function () {
            $('.form1').show();
            $('.form4').hide();
        });

        $('#botonform4').on('click', function () {
            $('.form1').hide();
            $('.form4').show();
            var sol = document.getElementById('requisicion-sala-solapa-archivos');
            if (sol) {
                sol.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });

        $(document).on('click', '.eliminar-archivo-requisicion-sala', function () {
            $(this).closest('.requisicion-sala-archivo-item').remove();
        });

        $('#agrega_renglon_archivo_sala').on('click', function (e) {
            e.preventDefault();
            var tpl = $('#template-renglon-archivo-sala').html();
            if (!tpl) {
                return;
            }
            $('#tbody-tabla-archivo-sala').append(tpl);
        });

        $(document).on('click', '#tbody-tabla-archivo-sala .eliminararchivo-sala', function (e) {
            e.preventDefault();
            $(this).closest('tr.item-archivo-sala').remove();
        });
    });
})();
