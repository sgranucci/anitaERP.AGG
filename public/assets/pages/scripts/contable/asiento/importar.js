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
        $ayuda.text('Este archivo tiene ' + hojas.length + ' hojas. Elija cuál contiene los movimientos.');
        mostrarPanelHoja(true);
    }

    function renderPreview(data) {
        var $panel = $('#panel-preview-import-asiento');
        var $contenido = $('#preview-import-asiento-contenido');
        var $estado = $('#preview-import-asiento-estado');
        var $panelAprob = $('#panel-confirm-aprobacion');

        $panel.show();
        actualizarSelectorHojas(data);

        if (!data || (data.mensaje && !data.resumen)) {
            $estado.removeClass().addClass('badge badge-danger').text('Error');
            $contenido.html('<p class="text-danger small mb-0">' + escHtml(data && data.mensaje ? data.mensaje : 'No se pudo analizar el archivo.') + '</p>');
            $panelAprob.addClass('d-none');
            return;
        }

        if (data.ok) {
            $estado.removeClass().addClass('badge badge-success').text('Listo para importar');
        } else {
            $estado.removeClass().addClass('badge badge-warning').text('Revisar configuración');
        }

        if (data.requiere_aprobacion) {
            $panelAprob.removeClass('d-none');
        } else {
            $panelAprob.addClass('d-none');
            $('#confirmar_pendiente_aprobacion').prop('checked', false);
        }

        var html = '';
        if (data.hoja_nombre) {
            html += '<p class="small mb-2">Hoja analizada: <strong>' + escHtml(data.hoja_seleccionada) + ' — ' + escHtml(data.hoja_nombre) + '</strong></p>';
        }
        html += '<p class="small mb-2">Encabezado detectado en fila <strong>' + escHtml(data.fila_encabezado) + '</strong>';
        if (data.fila_encabezado_automatica) {
            html += ' (automático)';
        }
        html += '.</p>';

        if (data.columnas) {
            html += '<div class="row small mb-2">';
            html += '<div class="col-md-4"><strong>Cuenta</strong> (' + escHtml(data.columnas.cuenta.configurado) + '): ' + badgeColumna(data.columnas.cuenta) + '</div>';
            html += '<div class="col-md-4"><strong>Debe</strong> (' + escHtml(data.columnas.debe.configurado) + '): ' + badgeColumna(data.columnas.debe) + '</div>';
            html += '<div class="col-md-4"><strong>Haber</strong> (' + escHtml(data.columnas.haber.configurado) + '): ' + badgeColumna(data.columnas.haber) + '</div>';
            html += '<div class="col-md-4 mt-1"><strong>Centro costo</strong>: ' + badgeColumna(data.columnas.centrocosto) + '</div>';
            html += '<div class="col-md-4 mt-1"><strong>Moneda</strong>: ' + badgeColumna(data.columnas.moneda) + '</div>';
            html += '<div class="col-md-4 mt-1"><strong>Detalle</strong>: ' + badgeColumna(data.columnas.detalle) + '</div>';
            html += '</div>';
        }

        if (data.moneda_default) {
            html += '<p class="small mb-2">Moneda por defecto: <strong>' + escHtml(data.moneda_default.abreviatura + ' — ' + data.moneda_default.nombre) + '</strong></p>';
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
            html += '<p class="small mb-2">';
            html += 'Total Debe: <strong>' + escHtml(data.resumen.total_debe_texto) + '</strong> · ';
            html += 'Total Haber: <strong>' + escHtml(data.resumen.total_haber_texto) + '</strong>';
            if (data.resumen.balanceado) {
                html += ' · <span class="badge badge-success">Balanceado</span>';
            } else {
                html += ' · <span class="badge badge-danger">Desbalance ' + escHtml(data.resumen.diferencia_texto) + '</span>';
            }
            html += '</p>';
        }

        if (data.filas && data.filas.length) {
            html += '<div class="table-responsive"><table class="table table-sm table-bordered mb-0">';
            html += '<thead style="background-color:#85C1E9;color:#17202A;"><tr>';
            html += '<th>Fila</th><th>Cuenta</th><th>Nombre</th><th>CC</th><th>Mon.</th>';
            html += '<th class="text-right">Debe</th><th class="text-right">Haber</th><th>Detalle</th><th>Resultado</th>';
            html += '</tr></thead><tbody>';
            data.filas.forEach(function (fila) {
                var cls = fila.estado === 'ok' ? 'table-success' : '';
                html += '<tr class="' + cls + '">';
                html += '<td>' + escHtml(fila.fila_excel) + '</td>';
                html += '<td>' + escHtml(fila.codigo_cuenta) + '</td>';
                html += '<td><small>' + escHtml(fila.cuenta_nombre || '—') + '</small></td>';
                html += '<td><small>' + escHtml(fila.codigo_centrocosto || '—') + '</small></td>';
                html += '<td>' + escHtml(fila.moneda_abreviatura || '') + '</td>';
                html += '<td class="text-right">' + escHtml(fila.debe_texto || '') + '</td>';
                html += '<td class="text-right">' + escHtml(fila.haber_texto || '') + '</td>';
                html += '<td><small>' + escHtml(fila.detalle || '') + '</small></td>';
                html += '<td><small>' + escHtml(fila.mensaje) + '</small></td>';
                html += '</tr>';
            });
            html += '</tbody></table></div>';
            if (data.hay_mas_filas) {
                html += '<p class="text-muted small mt-2 mb-0">Mostrando las primeras ' + data.filas.length + ' filas de datos.</p>';
            }
        }

        if (data.mensaje && !data.ok) {
            html += '<p class="text-warning small mt-2 mb-0">' + escHtml(data.mensaje) + '</p>';
        }

        $contenido.html(html);
    }

    function solicitarPreview() {
        if (!window.asientoImportPreviewUrl || !archivoSeleccionado()) {
            return;
        }

        if (previewXhr) {
            previewXhr.abort();
        }

        var formData = new FormData(document.getElementById('form-general'));
        var $contenido = $('#preview-import-asiento-contenido');
        var $estado = $('#preview-import-asiento-estado');

        $('#panel-preview-import-asiento').show();
        $estado.removeClass().addClass('badge badge-secondary').text('Analizando…');
        $contenido.html('<p class="text-muted small mb-0"><i class="fa fa-spinner fa-spin"></i> Leyendo archivo…</p>');

        previewXhr = $.ajax({
            url: window.asientoImportPreviewUrl,
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

    function mostrarOverlayImport() {
        var overlay = document.getElementById('asiento-import-overlay');
        if (!overlay) {
            return;
        }
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }

    function ocultarOverlayImport() {
        var overlay = document.getElementById('asiento-import-overlay');
        if (!overlay) {
            return;
        }
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }

    $(function () {
        $('#file').on('change', function () {
            var tieneArchivo = archivoSeleccionado();
            $('#btn-preview-import-asiento').prop('disabled', !tieneArchivo);
            if (tieneArchivo) {
                $('#hoja_indice').val(1);
                ultimasHojasDetectadas = null;
                mostrarPanelHoja(true);
                marcarSelectHojasCargando();
                programarPreview();
            } else {
                $('#panel-preview-import-asiento').hide();
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

        $('#btn-preview-import-asiento').on('click', function () {
            solicitarPreview();
        });

        $('#form-general').on(
            'change input',
            '#empresa_id, #moneda_id, #col_cuenta, #col_debe, #col_haber, #col_centrocosto, #col_moneda, #col_cotizacion, #col_detalle, #fila_encabezado',
            function () {
                if (archivoSeleccionado()) {
                    programarPreview();
                }
            }
        );

        $('#form-general').on('submit', function () {
            if (!this.checkValidity()) {
                return;
            }
            mostrarOverlayImport();
        });

        window.addEventListener('pageshow', ocultarOverlayImport);
    });
}(jQuery));
