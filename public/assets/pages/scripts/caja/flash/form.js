(function ($) {
    'use strict';

    var OVERLAY_ID = 'flash-calculo-aviso';
    var TITULO_ID = 'flash-calculo-aviso-titulo';
    var SUBTITULO_ID = 'flash-calculo-aviso-subtitulo';
    var TITULO_DEFAULT = 'Calculando flash…';
    var SUBTITULO_DEFAULT = 'Consultando ERP, Wigos y Anita. Por favor espere. No cierre ni recargue la página.';

    var CAMPOS_CALCULADOS = [
        'ayb', 'estac', 'cant_vehic', 'bingo_cant_carton', 'bingo_total_venta', 'bingo_resultado',
        'slot_coin_in', 'slot_d', 'slot_r', 'soft_count', 'hard_count', 'cant_slots', 'win_ol_slot',
        'rul_coin_in', 'rul_d', 'rul_r', 'soft_rul', 'hard_rul', 'cant_rul', 'win_ol_rul'
    ];

    function aplicarDatos(datos) {
        if (!datos) { return; }
        CAMPOS_CALCULADOS.forEach(function (campo) {
            if (typeof datos[campo] !== 'undefined') {
                $('#' + campo).val(datos[campo]);
            }
        });
    }

    function mostrarAvisoCalculo(visible, titulo, subtitulo) {
        var $aviso = $('#' + OVERLAY_ID);
        if (!$aviso.length) { return; }

        if (visible) {
            $('#' + TITULO_ID).text(titulo || TITULO_DEFAULT);
            $('#' + SUBTITULO_ID).text(subtitulo || SUBTITULO_DEFAULT);
            $aviso.removeClass('d-none').css('display', 'flex').attr('aria-hidden', 'false');
            $('body').css('overflow', 'hidden');
            return;
        }

        $aviso.addClass('d-none').css('display', '').attr('aria-hidden', 'true');
        $('body').css('overflow', '');
    }

    $('#btn-flash-calcular').on('click', function () {
        var empresaId = $('#empresa_id').val();
        var fecha = $('#fecha').val();
        if (!empresaId || !fecha) {
            alert('Seleccione empresa y fecha.');
            return;
        }
        var $btn = $(this).prop('disabled', true);
        mostrarAvisoCalculo(true);
        $.ajax({
            url: window.flashCalcularUrl || '/caja/flash/api/calcular',
            method: 'POST',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content'),
                empresa_id: empresaId,
                fecha: fecha
            }
        }).done(function (resp) {
            if (resp && resp.ok && resp.datos) {
                aplicarDatos(resp.datos);
            } else {
                alert('No se recibieron datos de c\u00e1lculo.');
            }
        }).fail(function (xhr) {
            var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Error al calcular flash.';
            alert(msg);
        }).always(function () {
            mostrarAvisoCalculo(false);
            $btn.prop('disabled', false);
        });
    });
})(jQuery);
