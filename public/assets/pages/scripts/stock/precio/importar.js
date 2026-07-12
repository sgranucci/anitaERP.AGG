(function ($) {
    'use strict';

    var previewTimer = null;
    var previewXhr = null;
    var ultimasHojasDetectadas = null;

    function mostrarPanelHoja(mostrar) {
        var $panel = $('#panel-hoja-excel');
        if (mostrar) {
            $panel.removeClass('d-none');
        } else {
            $panel.addClass('d-none');
        }
    }

    function marcarSelectHojasCargando() {
        $('#hoja_indice_select')
            .prop('disabled', true)
            .html('<option value="">Detectando hojas…</option>');
    }

    function actualizarPanelesFormato() {
        var formato = $('#formato').val();
        var esSimple = formato === 'simple';
        $('#panel-import-simple').toggle(esSimple);
        $('#panel-import-listas').toggle(!esSimple);
        $('#listaprecio_id').prop('required', esSimple);
        $('#col_sku, #col_precio').prop('required', esSimple);
    }

    function archivoSeleccionado() {
        var input = document.getElementById('file');
        return input && input.files && input.files.length > 0;
    }

    function escHtml(texto) {
        return $('<div/>').text(texto == null ? '' : String(texto)).html();
    }

    function badgeColumna(col) {
        if (!col) {
            return '<span class="badge badge-danger">No encontrada</span>';
        }
        if (col.encontrada) {
            return '<span class="badge badge-success">«' + escHtml(col.titulo) + '»</span>';
        }
        var req = col.requerida ? ' (requerida)' : ' (opcional)';
        return '<span class="badge badge-danger">No encontrada' + req + '</span>';
    }

    function renderPreviewSimple(data) {
        var html = '';
        if (data.hoja_nombre) {
            html += '<p class="small mb-2">Hoja analizada: <strong>' + escHtml(data.hoja_seleccionada) + ' — ' + escHtml(data.hoja_nombre) + '</strong></p>';
        }
        html += '<p class="small mb-2">Encabezado detectado en fila <strong>' + escHtml(data.fila_encabezado) + '</strong>';
        if (data.fila_encabezado_automatica) {
            html += ' (automático)';
        }
        html += '.</p>';

        html += '<div class="row small mb-2">';
        html += '<div class="col-md-4"><strong>SKU</strong> (' + escHtml(data.columnas.sku.configurado) + '): ' + badgeColumna(data.columnas.sku) + '</div>';
        html += '<div class="col-md-4"><strong>Descripción</strong> (' + escHtml(data.columnas.descripcion.configurado) + '): ' + badgeColumna(data.columnas.descripcion) + '</div>';
        html += '<div class="col-md-4"><strong>Precio</strong> (' + escHtml(data.columnas.precio.configurado) + '): ' + badgeColumna(data.columnas.precio) + '</div>';
        html += '</div>';

        if (data.lista_destino) {
            html += '<p class="small mb-2">Lista destino: <strong>' + escHtml(data.lista_destino.codigo + ' — ' + data.lista_destino.nombre) + '</strong></p>';
        }

        if (data.advertencias && data.advertencias.length) {
            html += '<div class="alert alert-warning py-2 small mb-2"><ul class="mb-0 pl-3">';
            data.advertencias.forEach(function (msg) {
                html += '<li>' + escHtml(msg) + '</li>';
            });
            html += '</ul></div>';
        }

        if (data.resumen) {
            html += '<p class="small mb-2">';
            html += 'Filas de datos: <strong>' + data.resumen.total_filas_datos + '</strong> · ';
            html += 'Importables: <strong class="text-success">' + data.resumen.importables + '</strong> · ';
            html += 'Omitidas: <strong class="text-muted">' + data.resumen.omitidas + '</strong>';
            html += '</p>';
        }

        if (data.filas && data.filas.length) {
            html += '<div class="table-responsive"><table class="table table-sm table-bordered mb-0">';
            html += '<thead style="background-color:#85C1E9;color:#17202A;"><tr>';
            html += '<th>Fila</th><th>SKU</th><th>Descripción Excel</th><th>Artículo sistema</th><th class="text-right">Precio</th><th>Resultado</th>';
            html += '</tr></thead><tbody>';
            data.filas.forEach(function (fila) {
                var cls = fila.estado === 'ok' ? 'table-success' : '';
                html += '<tr class="' + cls + '">';
                html += '<td>' + escHtml(fila.fila_excel) + '</td>';
                html += '<td>' + escHtml(fila.sku) + '</td>';
                html += '<td><small>' + escHtml(fila.descripcion) + '</small></td>';
                html += '<td><small>' + escHtml(fila.articulo_descripcion || '—') + '</small></td>';
                html += '<td class="text-right">' + escHtml(fila.precio_texto || '') + '</td>';
                html += '<td><small>' + escHtml(fila.mensaje) + '</small></td>';
                html += '</tr>';
            });
            html += '</tbody></table></div>';
            if (data.hay_mas_filas) {
                html += '<p class="text-muted small mt-2 mb-0">Mostrando las primeras ' + data.filas.length + ' filas de datos.</p>';
            }
        }

        return html;
    }

    function renderPreviewListas(data) {
        var html = '';
        if (data.hoja_nombre) {
            html += '<p class="small mb-2">Hoja analizada: <strong>' + escHtml(data.hoja_seleccionada) + ' — ' + escHtml(data.hoja_nombre) + '</strong></p>';
        }
        html += '<p class="small mb-2">Encabezado en fila <strong>' + escHtml(data.fila_encabezado) + '</strong>.</p>';
        html += '<p class="small mb-2">Columna SKU: ' + badgeColumna(data.columnas.sku) + '</p>';

        if (data.columnas.listas && data.columnas.listas.length) {
            html += '<p class="small mb-1"><strong>Listas detectadas:</strong></p><ul class="small">';
            data.columnas.listas.forEach(function (lista) {
                var badge = lista.encontrada
                    ? '<span class="text-success">' + escHtml(lista.titulo + ' → ' + (lista.listaprecio_nombre || '')) + '</span>'
                    : '<span class="text-danger">' + escHtml(lista.titulo + ' (lista no existe)') + '</span>';
                html += '<li>' + badge + '</li>';
            });
            html += '</ul>';
        }

        if (data.advertencias && data.advertencias.length) {
            html += '<div class="alert alert-warning py-2 small mb-2"><ul class="mb-0 pl-3">';
            data.advertencias.forEach(function (msg) {
                html += '<li>' + escHtml(msg) + '</li>';
            });
            html += '</ul></div>';
        }

        if (data.resumen) {
            html += '<p class="small mb-2">';
            html += 'Filas: <strong>' + data.resumen.total_filas_datos + '</strong> · ';
            html += 'Con precios: <strong class="text-success">' + data.resumen.importables + '</strong> · ';
            html += 'Precios totales: <strong>' + (data.resumen.precios_detectados || 0) + '</strong>';
            html += '</p>';
        }

        if (data.filas && data.filas.length) {
            html += '<div class="table-responsive"><table class="table table-sm table-bordered mb-0">';
            html += '<thead style="background-color:#85C1E9;color:#17202A;"><tr>';
            html += '<th>Fila</th><th>SKU</th><th>Artículo</th><th>Precios detectados</th><th>Resultado</th>';
            html += '</tr></thead><tbody>';
            data.filas.forEach(function (fila) {
                var cls = fila.estado === 'ok' ? 'table-success' : '';
                var preciosTxt = '';
                if (fila.precios && fila.precios.length) {
                    preciosTxt = fila.precios.map(function (p) {
                        return escHtml((p.columna || '') + ': ' + (p.precio_texto || ''));
                    }).join('<br>');
                }
                html += '<tr class="' + cls + '">';
                html += '<td>' + escHtml(fila.fila_excel) + '</td>';
                html += '<td>' + escHtml(fila.sku) + '</td>';
                html += '<td><small>' + escHtml(fila.descripcion || '—') + '</small></td>';
                html += '<td><small>' + (preciosTxt || '—') + '</small></td>';
                html += '<td><small>' + escHtml(fila.mensaje) + '</small></td>';
                html += '</tr>';
            });
            html += '</tbody></table></div>';
        }

        return html;
    }

    function actualizarSelectorHojas(data) {
        var $select = $('#hoja_indice_select');
        var $hidden = $('#hoja_indice');
        var $ayuda = $('#hoja_indice_ayuda');
        var hojas = data && data.hojas && data.hojas.length ? data.hojas : null;

        if (hojas) {
            ultimasHojasDetectadas = hojas;
        } else if (ultimasHojasDetectadas) {
            hojas = ultimasHojasDetectadas;
        } else {
            mostrarPanelHoja(false);
            $select.empty().prop('disabled', false);
            $hidden.val(1);
            return;
        }

        if (hojas.length <= 1) {
            mostrarPanelHoja(false);
            $select.empty().prop('disabled', false);
            $hidden.val(1);
            return;
        }

        var seleccionada = parseInt((data && data.hoja_seleccionada) || $hidden.val() || 1, 10);
        if (seleccionada < 1 || seleccionada > hojas.length) {
            seleccionada = 1;
        }

        $select.empty().prop('disabled', false);
        hojas.forEach(function (hoja) {
            var label = hoja.indice + ' — ' + hoja.nombre;
            $select.append(
                $('<option></option>').val(hoja.indice).text(label).prop('selected', parseInt(hoja.indice, 10) === seleccionada)
            );
        });

        $hidden.val(String(seleccionada));
        $ayuda.text('Este archivo tiene ' + hojas.length + ' hojas. Elija cuál contiene los precios a importar.');
        mostrarPanelHoja(true);
    }

    function renderPreview(data) {
        var $panel = $('#panel-preview-import-precio');
        var $contenido = $('#preview-import-precio-contenido');
        var $estado = $('#preview-import-precio-estado');

        $panel.show();
        actualizarSelectorHojas(data);

        if (!data || (data.mensaje && !data.resumen)) {
            $estado.removeClass().addClass('badge badge-danger').text('Error');
            $contenido.html('<p class="text-danger small mb-0">' + escHtml(data && data.mensaje ? data.mensaje : 'No se pudo analizar el archivo.') + '</p>');
            if (data && data.hojas) {
                actualizarSelectorHojas(data);
            }
            return;
        }

        if (data.ok) {
            $estado.removeClass().addClass('badge badge-success').text('Listo para importar');
        } else {
            $estado.removeClass().addClass('badge badge-warning').text('Revisar configuración');
        }

        if (data.formato === 'listas') {
            $contenido.html(renderPreviewListas(data));
        } else {
            $contenido.html(renderPreviewSimple(data));
        }
    }

    function solicitarPreview() {
        if (!window.precioImportPreviewUrl || !archivoSeleccionado()) {
            return;
        }

        if (previewXhr) {
            previewXhr.abort();
        }

        var formData = new FormData(document.getElementById('form-general'));
        var $contenido = $('#preview-import-precio-contenido');
        var $estado = $('#preview-import-precio-estado');

        $('#panel-preview-import-precio').show();
        $estado.removeClass().addClass('badge badge-secondary').text('Analizando…');
        $contenido.html('<p class="text-muted small mb-0"><i class="fa fa-spinner fa-spin"></i> Leyendo archivo…</p>');

        previewXhr = $.ajax({
            url: window.precioImportPreviewUrl,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function (data) {
            renderPreview(data);
        }).fail(function (xhr) {
            var msg = 'Error al analizar el archivo.';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                msg = xhr.responseJSON.message;
            } else if (xhr.responseJSON && xhr.responseJSON.mensaje) {
                msg = xhr.responseJSON.mensaje;
            }
            renderPreview({ mensaje: msg });
        }).always(function () {
            previewXhr = null;
        });
    }

    function programarPreview() {
        clearTimeout(previewTimer);
        previewTimer = setTimeout(solicitarPreview, 450);
    }

    $(function () {
        $('#formato').on('change', function () {
            actualizarPanelesFormato();
            if (archivoSeleccionado()) {
                programarPreview();
            }
        });
        actualizarPanelesFormato();

        $('#file').on('change', function () {
            var tieneArchivo = archivoSeleccionado();
            $('#btn-preview-import-precio').prop('disabled', !tieneArchivo);
            if (tieneArchivo) {
                $('#hoja_indice').val(1);
                ultimasHojasDetectadas = null;
                mostrarPanelHoja(true);
                marcarSelectHojasCargando();
                programarPreview();
            } else {
                $('#panel-preview-import-precio').hide();
                ultimasHojasDetectadas = null;
                mostrarPanelHoja(false);
            }
        });

        $('#hoja_indice_select').on('change', function () {
            $('#hoja_indice').val($(this).val());
            if (archivoSeleccionado()) {
                programarPreview();
            }
        });

        $('#btn-preview-import-precio').on('click', function () {
            solicitarPreview();
        });

        $('#form-general').on('change input', '#listaprecio_id, #col_sku, #col_descripcion, #col_precio, #fila_encabezado', function () {
            if (archivoSeleccionado()) {
                programarPreview();
            }
        });
    });
}(jQuery));
