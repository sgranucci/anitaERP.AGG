/**
 * Ingreso masivo CHT: vista previa AJAX + mapeo de columnas.
 */
(function ($) {
    'use strict';

    var previewTimer = null;
    var previewXhr = null;
    var ultimasHojasDetectadas = null;
    var sincronizandoMapa = false;

    function archivoSeleccionado() {
        var input = document.getElementById('file');
        return input && input.files && input.files.length > 0;
    }

    function escHtml(texto) {
        return $('<div/>').text(texto == null ? '' : String(texto)).html();
    }

    function mostrarPanelHoja(mostrar) {
        $('#panel-hoja-excel').toggleClass('d-none', !mostrar);
    }

    function actualizarSelectorHojas(data) {
        var $select = $('#hoja_indice_select');
        var $hidden = $('#hoja_indice');
        var hojas = data && data.hojas && data.hojas.length ? data.hojas : ultimasHojasDetectadas;

        if (data && data.hojas && data.hojas.length) {
            ultimasHojasDetectadas = data.hojas;
            hojas = data.hojas;
        }

        if (!hojas || hojas.length <= 1) {
            mostrarPanelHoja(false);
            $select.empty();
            $hidden.val(1);
            return;
        }

        var seleccionada = parseInt((data && data.hoja_seleccionada) || $hidden.val() || 1, 10);
        $select.empty();
        hojas.forEach(function (hoja) {
            $select.append(
                $('<option></option>')
                    .val(hoja.indice)
                    .text(hoja.indice + ' — ' + hoja.nombre)
                    .prop('selected', parseInt(hoja.indice, 10) === seleccionada)
            );
        });
        $hidden.val(String(seleccionada));
        mostrarPanelHoja(true);
    }

    function poblarSelectsMapeo(headers, mapping) {
        sincronizandoMapa = true;
        $('.map-campo').each(function () {
            var $sel = $(this);
            var campo = $sel.data('campo');
            var actual = mapping && mapping[campo] != null ? String(mapping[campo]) : '';
            $sel.empty().append($('<option></option>').val('').text('—'));
            (headers || []).forEach(function (h) {
                $sel.append(
                    $('<option></option>')
                        .val(h.indice)
                        .text((h.indice + 1) + ': ' + h.titulo)
                        .prop('selected', String(h.indice) === actual)
                );
            });
            if (actual !== '' && $sel.val() !== actual) {
                $sel.val(actual);
            }
        });
        sincronizandoMapa = false;
    }

    function renderPreview(data) {
        var $panel = $('#panel-preview-import-cheque');
        var $contenido = $('#preview-import-cheque-contenido');
        var $estado = $('#preview-import-cheque-estado');
        var $btnConfirmar = $('#btn-confirmar-import-cheque');

        $panel.show();
        actualizarSelectorHojas(data);

        if (!data || (data.mensaje && !data.headers)) {
            $estado.removeClass().addClass('badge badge-danger').text('Error');
            $contenido.html('<p class="text-danger small mb-0">' + escHtml(data && data.mensaje ? data.mensaje : 'No se pudo analizar.') + '</p>');
            $btnConfirmar.prop('disabled', true);
            return;
        }

        if (data.headers) {
            poblarSelectsMapeo(data.headers, data.mapping || {});
        }

        if (data.ok) {
            $estado.removeClass().addClass('badge badge-success').text('Listo');
        } else {
            $estado.removeClass().addClass('badge badge-warning').text('Revisar');
        }

        var html = '';
        if (data.fila_encabezado) {
            html += '<p class="small mb-1">Encabezado fila <strong>' + escHtml(data.fila_encabezado) + '</strong>';
            if (data.hoja_nombre) {
                html += ' · hoja ' + escHtml(data.hoja_nombre);
            }
            html += '</p>';
        }

        if (data.totales) {
            html += '<p class="small mb-2">';
            html += 'OK: <strong class="text-success">' + data.totales.ok + '</strong> · ';
            html += 'Error: <strong class="text-danger">' + data.totales.error + '</strong> · ';
            html += 'Omitidas: <strong>' + (data.totales.omitidas || 0) + '</strong> · ';
            html += 'Total: <strong>' + data.totales.total + '</strong>';
            html += '</p>';
        }

        if (data.errores && data.errores.length) {
            html += '<div class="alert alert-warning py-1 small mb-2"><ul class="mb-0 pl-3">';
            data.errores.forEach(function (e) {
                html += '<li>Fila ' + escHtml(e.fila) + ': ' + escHtml(e.mensaje) + '</li>';
            });
            html += '</ul></div>';
        }

        if (data.filas_preview && data.filas_preview.length) {
            html += '<div class="table-responsive"><table class="table table-sm table-bordered mb-0">';
            html += '<thead style="background:#85C1E9;color:#17202A;"><tr>';
            html += '<th>Fila</th><th>Número</th><th>Pago</th><th class="text-right">Monto</th><th>Banco</th><th>Cliente</th><th>Resultado</th>';
            html += '</tr></thead><tbody>';
            data.filas_preview.forEach(function (fila) {
                var cls = fila.estado === 'ok' ? 'table-success' : (fila.estado === 'error' ? 'table-danger' : '');
                html += '<tr class="' + cls + '">';
                html += '<td>' + escHtml(fila.fila_excel) + '</td>';
                html += '<td>' + escHtml(fila.numerocheque) + '</td>';
                html += '<td>' + escHtml(fila.fechapago) + '</td>';
                html += '<td class="text-right">' + escHtml(fila.monto != null ? fila.monto : '') + '</td>';
                html += '<td><small>' + escHtml(fila.banco) + '</small></td>';
                html += '<td><small>' + escHtml(fila.cliente) + '</small></td>';
                html += '<td><small>' + escHtml(fila.mensaje) + '</small></td>';
                html += '</tr>';
            });
            html += '</tbody></table></div>';
            if (data.hay_mas_filas) {
                html += '<p class="text-muted small mt-1 mb-0">Mostrando primeras ' + data.filas_preview.length + ' filas.</p>';
            }
        }

        $contenido.html(html || '<p class="text-muted small mb-0">Sin filas de datos.</p>');
        $btnConfirmar.prop('disabled', !(data.totales && data.totales.ok > 0));
    }

    function solicitarPreview() {
        if (!window.chequeImportPreviewUrl || !archivoSeleccionado()) {
            return;
        }
        if (previewXhr) {
            previewXhr.abort();
        }

        var formData = new FormData(document.getElementById('form-importar-cheque'));
        $('#panel-preview-import-cheque').show();
        $('#preview-import-cheque-estado').removeClass().addClass('badge badge-secondary').text('Analizando…');
        $('#preview-import-cheque-contenido').html('<p class="text-muted small mb-0"><i class="fa fa-spinner fa-spin"></i> Leyendo archivo…</p>');

        previewXhr = $.ajax({
            url: window.chequeImportPreviewUrl,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function (data) {
            renderPreview(data);
        }).fail(function (xhr) {
            var msg = 'Error al analizar el archivo.';
            if (xhr.responseJSON) {
                msg = xhr.responseJSON.message || xhr.responseJSON.mensaje || msg;
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
        $('#file').on('change', function () {
            var ok = archivoSeleccionado();
            $('#btn-preview-import-cheque').prop('disabled', !ok);
            $('#btn-confirmar-import-cheque').prop('disabled', true);
            if (ok) {
                $('#hoja_indice').val(1);
                ultimasHojasDetectadas = null;
                programarPreview();
            } else {
                $('#panel-preview-import-cheque').hide();
            }
        });

        $('#hoja_indice_select').on('change', function () {
            $('#hoja_indice').val($(this).val());
            if (archivoSeleccionado()) {
                programarPreview();
            }
        });

        $('#btn-preview-import-cheque').on('click', solicitarPreview);

        $('#form-importar-cheque').on('change', '#fila_encabezado, #empresa_id, .map-campo', function () {
            if (sincronizandoMapa) {
                return;
            }
            if (archivoSeleccionado()) {
                programarPreview();
            }
        });
    });
}(jQuery));
