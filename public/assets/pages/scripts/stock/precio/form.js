$(function () {
    $('#consultaarticuloModal').data('articuloSoloFacturable', 1);

    if (typeof activa_eventos_consultaarticulo === 'function') {
        activa_eventos_consultaarticulo();
    }

    var $campoArticulo = $('.tm-articulo-campo');
    var articuloId = parseInt($campoArticulo.find('.articulo_id').val(), 10) || 0;
    if (articuloId > 0 && typeof actualizarLinkEditarArticulo === 'function') {
        actualizarLinkEditarArticulo($campoArticulo, articuloId);
    }

    window.onArticuloSeleccionado = function (data) {
        if (!data || !data.id || typeof actualizarLinkEditarArticulo !== 'function') {
            return;
        }
        actualizarLinkEditarArticulo($('.tm-articulo-campo'), data.id);
    };
});
