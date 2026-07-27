/**
 * Modal de plan/cuotas vinculadas desde el index de solicitudes de pago.
 */
(function ($) {
    'use strict';

    $(document).on('click', '.btn-sp-ver-plan', function (e) {
        e.preventDefault();
        var url = $(this).data('url');
        var codigo = $(this).data('codigo') || '';
        var $modal = $('#modal-sp-familia-vinculos');
        var $body = $('#modal-sp-familia-body');
        if (!url || !$modal.length) {
            return;
        }

        $('#modal-sp-familia-titulo').html(
            '<i class="fa fa-sitemap"></i> Plan / cuotas' + (codigo ? ' — SP #' + codigo : '')
        );
        $body.html('<div class="text-center text-muted py-4"><i class="fa fa-spinner fa-spin"></i> Cargando…</div>');
        $modal.modal('show');

        $.get(url)
            .done(function (html) {
                $body.html(html);
            })
            .fail(function () {
                $body.html('<div class="alert alert-danger mb-0">No se pudo cargar el plan de cuotas.</div>');
            });
    });
})(jQuery);
