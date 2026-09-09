(function ($) {
    'use strict';

    function actualizarEtiqueta($input) {
        var activo = $input.is(':checked');
        var $row = $input.closest('tr');
        var $label = $row.find('.ticket-cfg-toggle-label');

        $label.text(activo ? 'Activo' : 'Como ahora');
        $row.toggleClass('ticket-cfg-row-on', activo);
        $row.attr('data-activo', activo ? '1' : '0');
    }

    function contarActivos() {
        var total = $('#tabla-ticket-configuracion .ticket-cfg-toggle:checked').length;
        $('#ticket-cfg-stat-activos').text(total);
    }

    function contarClaim() {
        var total = 0;
        $('#tabla-ticket-configuracion-area .ticket-cfg-modo').each(function () {
            if ($(this).val() === 'claim') {
                total += 1;
            }
        });
        $('#ticket-cfg-stat-claim').text(total);
    }

    function aplicarFiltros() {
        var texto = $.trim($('#ticket-cfg-filtro').val() || '').toLowerCase();
        var modo = $('.ticket-cfg-filter .btn.active').data('filtro') || 'todos';
        var visibles = 0;

        $('#tabla-ticket-configuracion tbody tr[data-nombre]').each(function () {
            var $row = $(this);
            var nombre = String($row.data('nombre') || '');
            var activo = String($row.attr('data-activo') || '0') === '1';
            var okTexto = !texto || nombre.indexOf(texto) !== -1;
            var okModo = modo === 'todos'
                || (modo === 'activos' && activo)
                || (modo === 'inactivos' && !activo);
            var mostrar = okTexto && okModo;

            $row.toggleClass('ticket-cfg-row-hidden', !mostrar);
            if (mostrar) {
                visibles += 1;
            }
        });

        $('#ticket-cfg-sin-resultados').toggle(visibles === 0);
    }

    $(function () {
        $('#tabla-ticket-configuracion').on('change', '.ticket-cfg-toggle', function () {
            actualizarEtiqueta($(this));
            contarActivos();
            aplicarFiltros();
        });

        $('#tabla-ticket-configuracion-area').on('change', '.ticket-cfg-modo', function () {
            var $row = $(this).closest('tr');
            var esClaim = $(this).val() === 'claim';
            $row.toggleClass('ticket-cfg-row-claim', esClaim);
            $row.attr('data-modo', $(this).val());
            contarClaim();
        });

        $('#ticket-cfg-filtro').on('input', aplicarFiltros);

        $('.ticket-cfg-filter').on('click', '.btn', function () {
            $('.ticket-cfg-filter .btn').removeClass('active');
            $(this).addClass('active');
            aplicarFiltros();
        });
    });
})(jQuery);
