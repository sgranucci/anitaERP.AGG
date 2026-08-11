$(function () {
    if (typeof window.carpetaBase === 'undefined') {
        var __locCb = window.location.pathname || '';
        var __mCb = __locCb.match(/^(.*\/public)(?:\/|$)/);
        window.carpetaBase = __mCb ? __mCb[1] : '';
    }

    var articuloIdActivo = 0;
    var maxCantidad = 250;

    function mostrarError(msg) {
        $('#modalEtiquetaCantidadArticuloError').removeClass('d-none').text(msg || 'Error al imprimir.');
    }

    function limpiarError() {
        $('#modalEtiquetaCantidadArticuloError').addClass('d-none').text('');
    }

    function leerCantidad() {
        var raw = $.trim($('#modalEtiquetaCantidadArticuloCantidad').val());
        if (raw === '' || !/^\d+$/.test(raw)) {
            return null;
        }

        return parseInt(raw, 10);
    }

    function validarCantidad() {
        var cantidad = leerCantidad();
        if (cantidad === null || cantidad < 1) {
            mostrarError('Indique una cantidad válida (entero mayor o igual a 1).');
            return null;
        }
        if (cantidad > maxCantidad) {
            mostrarError('La cantidad máxima por impresión es ' + maxCantidad + ' etiquetas.');
            return null;
        }
        limpiarError();
        return cantidad;
    }

    function urlImprimir(articuloId, cantidad) {
        var url =
            carpetaBase +
            '/stock/listar_etiqueta_articulo/' +
            encodeURIComponent(articuloId) +
            '?cantidad=' +
            encodeURIComponent(cantidad);
        return url;
    }

  $(document).on('click', '.btn-imprimir-etiqueta-cantidad', function () {
        articuloIdActivo = parseInt($(this).data('articulo-id'), 10) || 0;
        var sku = $(this).data('articulo-sku') || '';
        var desc = $(this).data('articulo-descripcion') || '';
        var subtitulo = sku;
        if (desc) {
            subtitulo += ' — ' + desc;
        }
        maxCantidad =
            parseInt($(this).data('max-cantidad'), 10) ||
            parseInt($('#modalEtiquetaCantidadArticuloCantidad').attr('max'), 10) ||
            250;

        $('#modalEtiquetaCantidadArticuloSubtitulo').text(subtitulo);
        $('#modalEtiquetaCantidadArticuloCantidad').val('1').attr('max', maxCantidad);
        limpiarError();
        $('#modalEtiquetaCantidadArticulo').modal('show');
        setTimeout(function () {
            $('#modalEtiquetaCantidadArticuloCantidad').trigger('focus').select();
        }, 300);
    });

    $('#modalEtiquetaCantidadArticuloCantidad').on('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            $('#modalEtiquetaCantidadArticuloImprimir').trigger('click');
        }
    });

    $('#modalEtiquetaCantidadArticuloImprimir').on('click', function () {
        if (!articuloIdActivo) {
            mostrarError('Artículo no válido.');
            return;
        }
        var cantidad = validarCantidad();
        if (!cantidad) {
            return;
        }
        var url = urlImprimir(articuloIdActivo, cantidad);
        $('#modalEtiquetaCantidadArticulo').modal('hide');
        if (typeof window.imprimirEtiquetaArticulo === 'function') {
            window.imprimirEtiquetaArticulo(url);
        } else {
            window.location.href = url;
        }
    });

    $('#modalEtiquetaCantidadArticulo').on('hidden.bs.modal', function () {
        articuloIdActivo = 0;
        limpiarError();
        $('#modalEtiquetaCantidadArticuloCantidad').val('1');
    });
});
