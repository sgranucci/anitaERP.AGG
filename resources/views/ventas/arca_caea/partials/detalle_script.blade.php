@once
<script>
$(function () {
    var $modal = $('#modalArcaCaeaDetalle');
    var $titulo = $('#modalArcaCaeaDetalleTitulo');
    var $cuerpo = $('#modalArcaCaeaDetalleCuerpo');
    var baseUrl = @json(url('ventas/arca-caea'));

    $(document).on('click', '.js-arca-caea-ver', function () {
        var id = $(this).data('id');
        var quincena = $(this).data('quincena') || '';
        var empresa = $(this).data('empresa') || '';
        $titulo.text(empresa ? (empresa + ' — ' + quincena) : ('CAEA ' + quincena));
        $cuerpo.html('<p class="text-muted mb-0">Cargando…</p>');
        $modal.modal('show');

        var params = {};
        var $filtroForm = $('form[action*="arca-caea"]').filter(function () {
            return $(this).attr('method') && $(this).attr('method').toLowerCase() === 'get';
        }).first();
        if ($filtroForm.length) {
            $filtroForm.serializeArray().forEach(function (field) {
                if (field.name && field.value !== '') {
                    params[field.name] = field.value;
                }
            });
        }

        $.ajax({
            url: baseUrl + '/' + id,
            data: params,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            dataType: 'html',
        })
            .done(function (html) {
                $cuerpo.html(html);
            })
            .fail(function (xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.message)
                    ? xhr.responseJSON.message
                    : 'No se pudo cargar el detalle.';
                $cuerpo.html('<p class="text-danger mb-0">' + $('<div>').text(msg).html() + '</p>');
            });
    });
});
</script>
@endonce
