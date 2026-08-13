$(function () {
    // Impuesto interno (listas incluyeimpuesto=2) admite artículos no facturables
    $('#consultaarticuloModal').removeData('articuloSoloFacturable');

    if (typeof activa_eventos_consultaarticulo === 'function') {
        activa_eventos_consultaarticulo();
    }

    if (typeof activa_eventos_consultalistaprecio === 'function') {
        activa_eventos_consultalistaprecio();
    }

    var $campoArticulo = $('.tm-articulo-campo');
    var articuloId = parseInt($campoArticulo.find('.articulo_id').val(), 10) || 0;
    if (articuloId > 0 && typeof actualizarLinkEditarArticulo === 'function') {
        actualizarLinkEditarArticulo($campoArticulo, articuloId);
    }

    var $campoLista = $('.tm-listaprecio-campo');
    var listaId = parseInt($campoLista.find('.listaprecio_id').val(), 10) || 0;
    if (listaId > 0 && typeof actualizarLinkEditarListaprecio === 'function') {
        actualizarLinkEditarListaprecio($campoLista, listaId);
    }

    window.onArticuloSeleccionado = function (data) {
        if (!data || !data.id || typeof actualizarLinkEditarArticulo !== 'function') {
            return;
        }
        actualizarLinkEditarArticulo($('.tm-articulo-campo'), data.id);
    };
});
