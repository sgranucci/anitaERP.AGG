(function ($) {
    'use strict';

    var previewTimer = null;
    var previewXhr = null;
    var previewOk = false;

    function escHtml(texto) {
        return $('<div/>').text(texto == null ? '' : String(texto)).html();
    }

    function archivoSeleccionado() {
        var input = document.getElementById('file');
        return input && input.files && input.files.length > 0;
    }

    function actualizarBotones() {
        var hayArchivo = archivoSeleccionado();
        $('#btn-preview-lista-archivo').prop('disabled', !hayArchivo);
        var listaOk = parseInt($('#listaprecio_id').val(), 10) > 0;
        var monedaOk = parseInt($('#moneda_id').val(), 10) > 0;
        $('#btn-importar-lista-archivo').prop('disabled', !(hayArchivo && listaOk && monedaOk && previewOk));
    }

    function setBadge(tipo, texto) {
        var $b = $('#badge-preview-lista-archivo');
        $b.removeClass('badge-secondary badge-success badge-warning badge-danger badge-info')
            .addClass('badge-' + tipo)
            .text(texto);
    }

    function mostrarOverlay(titulo) {
        var overlay = document.getElementById('lista-archivo-import-overlay');
        if (!overlay) {
            return;
        }
        if (titulo) {
            var t = document.getElementById('lista-archivo-import-titulo');
            if (t) {
                t.textContent = titulo;
            }
        }
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }

    function ocultarOverlay() {
        var overlay = document.getElementById('lista-archivo-import-overlay');
        if (!overlay) {
            return;
        }
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }

    function renderPreview(data) {
        var html = '';

        if (data.titulo || data.fuente) {
            html += '<p class="small mb-2">';
            if (data.titulo) {
                html += '<strong>' + escHtml(data.titulo) + '</strong> · ';
            }
            html += 'Fuente: <strong>' + escHtml((data.fuente || '').toUpperCase()) + '</strong>';
            if (data.vigencia) {
                html += ' · Vigencia archivo: <strong>' + escHtml(data.vigencia) + '</strong>';
            }
            html += '</p>';
        }

        if (data.lista_destino) {
            html += '<p class="small mb-2">Lista destino: <strong>'
                + escHtml(data.lista_destino.codigo + ' — ' + data.lista_destino.nombre)
                + '</strong></p>';
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
            html += 'Ítems: <strong>' + data.resumen.total_filas + '</strong> · ';
            html += 'Importables: <strong class="text-success">' + data.resumen.importables + '</strong> · ';
            html += 'Omitidos: <strong class="text-muted">' + data.resumen.omitidas + '</strong>';
            if (data.resumen.secciones) {
                html += ' · Secciones: <strong>' + data.resumen.secciones + '</strong>';
            }
            html += '</p>';
        }

        if (data.filas && data.filas.length) {
            html += '<div class="table-responsive"><table class="table table-sm table-bordered mb-0">';
            html += '<thead style="background-color:#85C1E9;color:#17202A;"><tr>';
            html += '<th>#</th><th>Sección</th><th>SKU</th><th>Detalle archivo</th>';
            html += '<th>Artículo sistema</th><th class="text-right">Precio Lista</th><th>Resultado</th>';
            html += '</tr></thead><tbody>';
            data.filas.forEach(function (fila) {
                var cls = fila.estado === 'ok' ? 'table-success' : '';
                html += '<tr class="' + cls + '">';
                html += '<td>' + escHtml(fila.fila) + '</td>';
                html += '<td><small>' + escHtml(fila.seccion || '') + '</small></td>';
                html += '<td>' + escHtml(fila.sku) + '</td>';
                html += '<td><small>' + escHtml(fila.detalle_archivo) + '</small></td>';
                html += '<td><small>' + escHtml(fila.articulo_descripcion || '—') + '</small></td>';
                html += '<td class="text-right">$ ' + escHtml(fila.precio_texto || '') + '</td>';
                html += '<td><small>' + escHtml(fila.mensaje) + '</small></td>';
                html += '</tr>';
            });
            html += '</tbody></table></div>';
            if (data.hay_mas_filas) {
                html += '<p class="text-muted small mt-2 mb-0">Mostrando las primeras '
                    + data.filas.length + ' filas.</p>';
            }
        }

        return html;
    }

    function aplicarVigenciaSugerida(ymd) {
        if (!ymd) {
            return;
        }
        var $fecha = $('#fechavigencia');
        if (!$fecha.length) {
            return;
        }
        if ($fecha.data('tocada-manual') === 1) {
            $('#ayuda-vigencia-archivo').text(
                'El archivo declara vigencia ' + ymd + ' (no se pisó porque la fecha se editó a mano).'
            );
            return;
        }
        $fecha.val(ymd);
        $('#ayuda-vigencia-archivo').text('Vigencia tomada del archivo: ' + ymd + '.');
    }

    function solicitarPreview() {
        if (!archivoSeleccionado()) {
            previewOk = false;
            actualizarBotones();
            return;
        }
        if (!window.precioArchivoPreviewUrl) {
            return;
        }

        var form = document.getElementById('form-import-lista-archivo');
        var fd = new FormData(form);
        var token = $('meta[name="csrf-token"]').attr('content')
            || $('input[name="_token"]').val();

        $('#panel-preview-lista-archivo').removeClass('d-none');
        setBadge('info', 'Analizando…');
        $('#contenido-preview-lista-archivo').html(
            '<p class="text-muted small mb-0"><i class="fa fa-spinner fa-spin"></i> Leyendo archivo…</p>'
        );

        if (previewXhr && previewXhr.readyState !== 4) {
            previewXhr.abort();
        }

        previewXhr = $.ajax({
            url: window.precioArchivoPreviewUrl,
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            headers: token ? { 'X-CSRF-TOKEN': token } : {},
            success: function (data) {
                if (!data || !data.ok) {
                    previewOk = false;
                    setBadge('danger', 'Error');
                    $('#contenido-preview-lista-archivo').html(
                        '<div class="alert alert-danger py-2 mb-0 small">'
                        + escHtml((data && data.mensaje) || 'No se pudo analizar el archivo.')
                        + '</div>'
                    );
                    actualizarBotones();
                    return;
                }

                previewOk = (data.resumen && data.resumen.importables > 0);
                var badge = previewOk ? 'success' : 'warning';
                var texto = previewOk
                    ? (data.resumen.importables + ' importables')
                    : 'Sin filas importables';
                setBadge(badge, texto);
                aplicarVigenciaSugerida(data.vigencia_ymd);
                $('#contenido-preview-lista-archivo').html(renderPreview(data));
                actualizarBotones();
            },
            error: function (xhr) {
                if (xhr.statusText === 'abort') {
                    return;
                }
                previewOk = false;
                setBadge('danger', 'Error');
                var msg = 'Error al previsualizar.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                } else if (xhr.responseJSON && xhr.responseJSON.mensaje) {
                    msg = xhr.responseJSON.mensaje;
                }
                $('#contenido-preview-lista-archivo').html(
                    '<div class="alert alert-danger py-2 mb-0 small">' + escHtml(msg) + '</div>'
                );
                actualizarBotones();
            }
        });
    }

    function programarPreview() {
        if (previewTimer) {
            clearTimeout(previewTimer);
        }
        previewTimer = setTimeout(solicitarPreview, 350);
    }

    $(function () {
        $('#file').on('change', function () {
            previewOk = false;
            actualizarBotones();
            programarPreview();
        });

        $('#listaprecio_id').on('change', function () {
            actualizarBotones();
            if (archivoSeleccionado()) {
                programarPreview();
            }
        });

        $(document).on('change input', '.codigolistaprecio, #listaprecio_id, #moneda_id', function () {
            actualizarBotones();
        });

        window.onListaprecioSeleccionado = function () {
            actualizarBotones();
            if (archivoSeleccionado()) {
                programarPreview();
            }
        };

        $('#fechavigencia').on('change', function () {
            $(this).data('tocada-manual', 1);
        });

        $('#btn-preview-lista-archivo').on('click', function () {
            solicitarPreview();
        });

        $('#form-import-lista-archivo').on('submit', function (e) {
            if (!this.checkValidity()) {
                return;
            }
            if (!previewOk) {
                e.preventDefault();
                alert('Genere una vista previa con filas importables antes de grabar.');
                return;
            }
            mostrarOverlay('Importando lista de precios…');
        });

        window.addEventListener('pageshow', ocultarOverlay);
        actualizarBotones();
    });
})(jQuery);
