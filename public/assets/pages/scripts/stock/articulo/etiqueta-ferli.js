$(function () {
    if (typeof window.carpetaBase === 'undefined') {
        var __locCb = window.location.pathname || '';
        var __mCb = __locCb.match(/^(.*\/public)(?:\/|$)/);
        window.carpetaBase = __mCb ? __mCb[1] : '';
    }

    var articuloIdActivo = 0;
    var maxCantidad = 250;

    function mostrarError(msg) {
        $('#modalEtiquetaFerliError').removeClass('d-none').text(msg || 'Error.');
    }

    function limpiarError() {
        $('#modalEtiquetaFerliError').addClass('d-none').text('');
    }

    function fillSelect($el, items, valueKey, labelFn, emptyLabel) {
        $el.empty();
        if (emptyLabel) {
            $el.append($('<option>').val('').text(emptyLabel));
        }
        (items || []).forEach(function (item) {
            $el.append($('<option>').val(item[valueKey]).text(labelFn(item)));
        });
    }

    function cargarDatos(articuloId) {
        limpiarError();
        $('#modalEtiquetaFerliLoading').removeClass('d-none');
        $('#modalEtiquetaFerliImprimir').prop('disabled', true);

        return $.getJSON(carpetaBase + '/stock/articulo/' + articuloId + '/api/datos-etiqueta-ferli')
            .done(function (data) {
                fillSelect(
                    $('#modalEtiquetaFerliModelo'),
                    data.modelos || [],
                    'id',
                    function (m) { return m.nombre; },
                    null
                );
                if (data.modelo_default_id) {
                    $('#modalEtiquetaFerliModelo').val(String(data.modelo_default_id));
                }

                fillSelect(
                    $('#modalEtiquetaFerliCombinacion'),
                    data.combinaciones || [],
                    'id',
                    function (c) { return (c.codigo || '') + ' — ' + (c.nombre || ''); },
                    'Sin combinación'
                );

                fillSelect(
                    $('#modalEtiquetaFerliTalle'),
                    data.talles || [],
                    'id',
                    function (t) { return t.nombre || t.codigo || ('#' + t.id); },
                    'Sin talle'
                );

                maxCantidad = parseInt(data.max_cantidad, 10) || 250;
                $('#modalEtiquetaFerliCantidad').attr('max', maxCantidad).val('1');
                $('#modalEtiquetaFerliImprimir').prop('disabled', false);
            })
            .fail(function (xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.error) || 'No se pudieron cargar los datos de etiqueta.';
                mostrarError(msg);
            })
            .always(function () {
                $('#modalEtiquetaFerliLoading').addClass('d-none');
            });
    }

    $(document).on('click', '.btn-imprimir-etiqueta-ferli', function () {
        articuloIdActivo = parseInt($(this).data('articulo-id'), 10) || 0;
        var sku = $(this).data('articulo-sku') || '';
        var desc = $(this).data('articulo-descripcion') || '';
        $('#modalEtiquetaFerliSubtitulo').text(sku + (desc ? ' — ' + desc : ''));
        limpiarError();
        $('#modalEtiquetaFerli').modal('show');
        cargarDatos(articuloIdActivo);
        setTimeout(function () {
            $('#modalEtiquetaFerliCantidad').trigger('focus').select();
        }, 300);
    });

    $('#modalEtiquetaFerliImprimir').on('click', function () {
        if (!articuloIdActivo) {
            mostrarError('Artículo no válido.');
            return;
        }
        var cantidad = parseInt($.trim($('#modalEtiquetaFerliCantidad').val()), 10);
        if (!cantidad || cantidad < 1) {
            mostrarError('Indique una cantidad válida.');
            return;
        }
        if (cantidad > maxCantidad) {
            mostrarError('La cantidad máxima es ' + maxCantidad + '.');
            return;
        }

        var qs = {
            cantidad: cantidad,
            combinacion_id: $('#modalEtiquetaFerliCombinacion').val() || '',
            talle_id: $('#modalEtiquetaFerliTalle').val() || '',
            modeloetiqueta_id: $('#modalEtiquetaFerliModelo').val() || ''
        };
        var url =
            carpetaBase +
            '/stock/listar_etiqueta_articulo/' +
            encodeURIComponent(articuloIdActivo) +
            '?' +
            $.param(qs);

        $('#modalEtiquetaFerli').modal('hide');
        if (typeof window.imprimirEtiquetaArticulo === 'function') {
            window.imprimirEtiquetaArticulo(url);
        } else {
            window.location.href = url;
        }
    });

    $('#modalEtiquetaFerli').on('hidden.bs.modal', function () {
        articuloIdActivo = 0;
        limpiarError();
    });
});
