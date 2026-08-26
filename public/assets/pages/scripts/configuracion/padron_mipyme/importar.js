(function ($) {
    'use strict';

    var previewXhr = null;

    function archivoSeleccionado() {
        var input = document.getElementById('file');
        return input && input.files && input.files.length > 0;
    }

    function escHtml(texto) {
        return $('<div/>').text(texto == null ? '' : String(texto)).html();
    }

    function overlay() {
        return document.getElementById('padron-mipyme-import-overlay');
    }

    function setOverlayTextos(titulo, subtitulo) {
        var t = document.getElementById('padron-mipyme-import-titulo');
        var s = document.getElementById('padron-mipyme-import-subtitulo');
        if (t && titulo) {
            t.textContent = titulo;
        }
        if (s && subtitulo) {
            s.textContent = subtitulo;
        }
    }

    function mostrarOverlay(titulo, subtitulo) {
        var el = overlay();
        if (!el) {
            return;
        }
        setOverlayTextos(titulo, subtitulo);
        el.classList.remove('d-none');
        el.style.display = 'flex';
        el.setAttribute('aria-hidden', 'false');
    }

    function ocultarOverlay() {
        var el = overlay();
        if (!el) {
            return;
        }
        el.classList.add('d-none');
        el.style.display = '';
        el.setAttribute('aria-hidden', 'true');
    }

    function renderPreanalisis(data) {
        var $panel = $('#panel-preanalisis-padron-mipyme');
        var $contenido = $('#preanalisis-padron-mipyme-contenido');
        var $estado = $('#preanalisis-padron-mipyme-estado');

        $panel.show();

        if (!data || data.ok === false) {
            $estado.removeClass().addClass('badge badge-danger').text('No importable');
            $contenido.html(
                '<p class="text-danger small mb-0">' +
                escHtml(data && data.mensaje ? data.mensaje : 'No se pudo analizar el archivo.') +
                '</p>'
            );
            return;
        }

        $estado.removeClass().addClass('badge badge-success').text('Listo para importar');

        var html = '';
        html += '<p class="small mb-2">';
        if (data.era_zip) {
            html += '<span class="badge badge-warning mr-1">ZIP detectado</span> ';
            html += 'Origen <code>' + escHtml(data.nombre_origen || '') + '</code>';
            if (data.tamanio_origen_texto) {
                html += ' (' + escHtml(data.tamanio_origen_texto) + ')';
            }
            html += ' → se descomprimió <code>' + escHtml(data.nombre_extraido || '') + '</code>';
            if (data.tamanio_datos_texto) {
                html += ' (' + escHtml(data.tamanio_datos_texto) + ')';
            }
        } else {
            html += '<span class="badge badge-info mr-1">Archivo plano</span> ';
            html += '<code>' + escHtml(data.nombre_origen || '') + '</code>';
            if (data.tamanio_datos_texto) {
                html += ' (' + escHtml(data.tamanio_datos_texto) + ')';
            }
        }
        html += '</p>';

        html += '<p class="small mb-2">';
        html += 'Encoding: <strong>' + escHtml(data.encoding || 'UTF-8') + '</strong> · ';
        html += 'Separador: <code>' + escHtml(data.delimitador === '\t' ? '\\t' : (data.delimitador || ';')) + '</code> · ';
        html += 'Líneas: <strong>' + escHtml(data.lineas_totales) + '</strong>';
        if (data.lineas_datos) {
            html += ' (datos: ' + escHtml(data.lineas_datos) + ')';
        }
        if (data.mapeo && data.mapeo.tiene_cabecera) {
            html += ' · Cabecera detectada';
        }
        html += '</p>';

        if (data.columnas && data.columnas.length) {
            html += '<p class="small mb-2">Columnas: ';
            data.columnas.forEach(function (col, i) {
                if (i > 0) {
                    html += ' · ';
                }
                html += '<strong>' + escHtml(col.campo) + '</strong>';
                if (col.titulo) {
                    html += ' <span class="text-muted">(' + escHtml(col.titulo) + ')</span>';
                }
            });
            html += '</p>';
        }

        if (data.advertencias && data.advertencias.length) {
            html += '<div class="alert alert-warning py-2 small mb-2"><ul class="mb-0 pl-3">';
            data.advertencias.forEach(function (msg) {
                html += '<li>' + escHtml(msg) + '</li>';
            });
            html += '</ul></div>';
        }

        if (data.muestra && data.muestra.length) {
            html += '<div class="table-responsive"><table class="table table-sm table-bordered mb-0">';
            html += '<thead style="background-color:#85C1E9;color:#17202A;"><tr>';
            html += '<th>CUIT</th><th>Nombre</th><th>Actividad</th><th>Fecha inicio</th>';
            html += '</tr></thead><tbody>';
            data.muestra.forEach(function (fila) {
                html += '<tr>';
                html += '<td>' + escHtml(fila.cuit) + '</td>';
                html += '<td><small>' + escHtml(fila.nombre) + '</small></td>';
                html += '<td><small>' + escHtml(fila.actividad) + '</small></td>';
                html += '<td>' + escHtml(fila.fechainicio) + '</td>';
                html += '</tr>';
            });
            html += '</tbody></table></div>';
            html += '<p class="text-muted small mt-2 mb-0">Muestra de las primeras filas válidas. Al importar se recorre el archivo completo.</p>';
        }

        $contenido.html(html);
    }

    function solicitarPreanalisis() {
        if (!window.padronMipymePreanalisisUrl || !archivoSeleccionado()) {
            return;
        }

        if (previewXhr) {
            previewXhr.abort();
        }

        var form = document.getElementById('form-general');
        var formData = new FormData(form);
        var $contenido = $('#preanalisis-padron-mipyme-contenido');
        var $estado = $('#preanalisis-padron-mipyme-estado');

        $('#panel-preanalisis-padron-mipyme').show();
        $estado.removeClass().addClass('badge badge-secondary').text('Analizando…');
        $contenido.html('<p class="text-muted small mb-0"><i class="fa fa-spinner fa-spin"></i> Detectando ZIP y leyendo el archivo…</p>');
        mostrarOverlay(
            'Analizando archivo…',
            'Si es un ZIP se descomprime primero. No cierre la página.'
        );

        previewXhr = $.ajax({
            url: window.padronMipymePreanalisisUrl,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function (data) {
            renderPreanalisis(data);
        }).fail(function (xhr) {
            var msg = 'Error al analizar el archivo.';
            if (xhr.statusText === 'abort') {
                return;
            }
            if (xhr.responseJSON && xhr.responseJSON.message) {
                msg = xhr.responseJSON.message;
            } else if (xhr.responseJSON && xhr.responseJSON.mensaje) {
                msg = xhr.responseJSON.mensaje;
            } else if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                var first = Object.values(xhr.responseJSON.errors)[0];
                if (first && first[0]) {
                    msg = first[0];
                }
            }
            renderPreanalisis({ ok: false, mensaje: msg });
        }).always(function () {
            previewXhr = null;
            ocultarOverlay();
        });
    }

    $(function () {
        $('#file').on('change', function () {
            var tieneArchivo = archivoSeleccionado();
            $('#btn-preanalizar-padron-mipyme').prop('disabled', !tieneArchivo);
            if (tieneArchivo) {
                solicitarPreanalisis();
            } else {
                $('#panel-preanalisis-padron-mipyme').hide();
            }
        });

        $('#btn-preanalizar-padron-mipyme').on('click', function () {
            solicitarPreanalisis();
        });

        $('#form-general').on('submit', function () {
            if (!this.checkValidity()) {
                return true;
            }
            mostrarOverlay(
                'Importando padrón…',
                'Si el archivo es un ZIP se descomprime primero. Puede demorar. No cierre la página.'
            );
            return true;
        });

        window.addEventListener('pageshow', ocultarOverlay);
    });
}(jQuery));
