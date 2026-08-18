(function ($) {
    'use strict';

    function normalizar(valor) {
        return String(valor || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim();
    }

    function aplicarFiltros() {
        var texto = normalizar($('#traza-busqueda').val());
        var estado = $('#traza-filtro-estado').val();
        var tipo = $('#traza-filtro-tipo').val();
        var visibles = 0;

        $('.traza-paso').each(function () {
            var $paso = $(this);
            var coincideTexto = texto === '' || normalizar($paso.data('texto')).indexOf(texto) !== -1;
            var coincideTipo = tipo === '' || $paso.data('tipo') === tipo;
            var coincideEstado = estado === ''
                || $paso.data('estado') === estado
                || $paso.data('importe') === estado;
            var mostrar = coincideTexto && coincideTipo && coincideEstado;

            $paso.toggle(mostrar);
            if (mostrar) {
                visibles++;
            }
        });

        $('#traza-visibles').text(visibles);
        $('#traza-sin-resultados').toggleClass('d-none', visibles > 0);
    }

    $(function () {
        $('#traza-busqueda').on('input', aplicarFiltros);
        $('#traza-filtro-estado, #traza-filtro-tipo').on('change', aplicarFiltros);

        $('#traza-expandir').on('click', function () {
            $('.traza-paso:visible').children('.collapse').collapse('show');
        });

        $('#traza-contraer').on('click', function () {
            $('.traza-paso:visible').children('.collapse').collapse('hide');
        });

        $('.traza-paso').children('.collapse')
            .on('show.bs.collapse', function () {
                $(this).siblings('.traza-paso-header').find('.traza-chevron')
                    .removeClass('fa-chevron-down')
                    .addClass('fa-chevron-up');
            })
            .on('hide.bs.collapse', function () {
                $(this).siblings('.traza-paso-header').find('.traza-chevron')
                    .removeClass('fa-chevron-up')
                    .addClass('fa-chevron-down');
            });

        $('#btn-cerrar-traza').on('click', function (event) {
            var destino = this.href;
            event.preventDefault();
            window.close();
            window.setTimeout(function () {
                window.location.href = destino;
            }, 150);
        });
    });
})(jQuery);
