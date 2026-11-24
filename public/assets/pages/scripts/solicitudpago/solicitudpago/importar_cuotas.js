/**
 * Importación de cuotas SP: modal con vista previa, mapeo y preferencias (estilo novedades sueldos).
 */
(function ($) {
    'use strict';

    var previewTimer = null;
    var previewXhr = null;
    var ultimasHojasDetectadas = null;
    var sincronizandoMapa = false;
    var filasAplicar = [];
    var importacionHabilitada = false;

    function $modal() {
        return $('#modal-importar-cuotas-sp');
    }

    function previewUrl() {
        return $modal().data('preview-url') || window.spCuotasImportPreviewUrl || '';
    }

    function archivoSeleccionado() {
        var input = document.getElementById('sp_cuotas_archivo');
        return input && input.files && input.files.length > 0;
    }

    function modoColumnasActual() {
        var checked = document.querySelector('#form-importar-cuotas-sp input[name="modo_columnas"]:checked');
        return checked ? checked.value : 'titulo';
    }

    function esArchivoCsv() {
        var input = document.getElementById('sp_cuotas_archivo');
        if (!input || !input.files || !input.files.length) {
            return false;
        }
        var nombre = String(input.files[0].name || '').toLowerCase();
        return nombre.endsWith('.csv') || nombre.endsWith('.txt');
    }

    function sincronizarPanelCsvSeparador() {
        var $select = $('#sp_cuotas_csv_separador');
        var $ayuda = $('#sp-cuotas-ayuda-csv-separador');
        if (esArchivoCsv()) {
            $select.addClass('border-primary');
            $ayuda.text('CSV/TXT: si el preview sale vacío o en una sola columna, cambiá el separador (coma o punto y coma) y volvé a previsualizar.');
        } else if (archivoSeleccionado()) {
            $select.removeClass('border-primary');
            $ayuda.text('Archivo Excel: el separador no se usa.');
        } else {
            $select.removeClass('border-primary');
            $ayuda.text('Para CSV/TXT. Con «Automático» se detecta por el contenido. En Excel se ignora.');
        }
    }

    function sincronizarUiModoColumnas() {
        var porNumero = modoColumnasActual() === 'numero';
        if (porNumero) {
            $('#sp-cuotas-label-fila-encabezado').text('Primera fila de datos');
            $('#sp-cuotas-ayuda-fila-encabezado').text('Vacío = fila 1. No hay fila de títulos: se leen todas las filas desde ahí.');
            $('#sp_cuotas_fila_encabezado').attr('placeholder', '1');
            $('#sp-cuotas-ayuda-modo-columnas').text('Sin título: en el mapeo tipá el número de columna (1 = primera) o letra Excel (A, B…).');
            $('#sp-cuotas-ayuda-mapeo-columnas').html('Sin título: número (1 = primera) o letra Excel (A, B…). Vacío = sin mapear ese campo.');
            $('.sp-cuotas-map-campo').attr('placeholder', 'ej. 1 o A');
        } else {
            $('#sp-cuotas-label-fila-encabezado').text('Fila del encabezado');
            $('#sp-cuotas-ayuda-fila-encabezado').text('Vacío = detectar automáticamente');
            $('#sp_cuotas_fila_encabezado').attr('placeholder', 'Auto');
            $('#sp-cuotas-ayuda-modo-columnas').text('Con título: se detecta la fila de encabezados. Si el Excel usa otros nombres, tipá el nombre en el mapeo.');
            $('#sp-cuotas-ayuda-mapeo-columnas').html('Con título: nombre del encabezado (ej. <code>vencimiento</code>). También acepta número/letra. Vacío = usa la detección automática.');
            $('.sp-cuotas-map-campo').attr('placeholder', 'ej. vencimiento');
        }
    }

    function escHtml(texto) {
        return $('<div/>').text(texto == null ? '' : String(texto)).html();
    }

    function fmtImporte(n) {
        var num = Number(n || 0);
        if (!isFinite(num)) {
            return '0,00';
        }
        return num.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function mostrarOverlay(titulo, subtitulo) {
        var overlay = document.getElementById('sp-cuotas-import-overlay');
        if (!overlay) {
            return;
        }
        if (titulo) {
            document.getElementById('sp-cuotas-import-overlay-titulo').textContent = titulo;
        }
        if (subtitulo) {
            document.getElementById('sp-cuotas-import-overlay-subtitulo').textContent = subtitulo;
        }
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }

    function ocultarOverlay() {
        var overlay = document.getElementById('sp-cuotas-import-overlay');
        if (!overlay) {
            return;
        }
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }

    function mostrarPanelHoja(mostrar) {
        $('#sp-cuotas-panel-hoja-excel').toggleClass('d-none', !mostrar);
    }

    function actualizarSelectorHojas(data) {
        var $select = $('#sp_cuotas_hoja_indice_select');
        var $hidden = $('#sp_cuotas_hoja_indice');
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

    function poblarInputsMapeo(mappingUi) {
        sincronizandoMapa = true;
        $('.sp-cuotas-map-campo').each(function () {
            var $input = $(this);
            var campo = $input.data('campo');
            var actual = mappingUi && mappingUi[campo] != null ? String(mappingUi[campo]) : '';
            if (document.activeElement === this) {
                return;
            }
            if (actual === '' && $.trim(String($input.val() || '')) !== '') {
                return;
            }
            $input.val(actual);
        });
        sincronizandoMapa = false;
    }

    function renderPreview(data) {
        var $contenido = $('#sp-cuotas-preview-contenido');
        var $estado = $('#sp-cuotas-preview-estado');
        var $btnAplicar = $('#btn-aplicar-import-cuotas-sp');

        $('#sp-cuotas-panel-preview').show();
        actualizarSelectorHojas(data);
        filasAplicar = (data && data.filas_aplicar) ? data.filas_aplicar : [];

        if (!data || (data.mensaje && !data.headers)) {
            $estado.removeClass().addClass('badge badge-danger').text('Error');
            $contenido.html('<p class="text-danger small mb-0">' + escHtml(data && data.mensaje ? data.mensaje : 'No se pudo analizar.') + '</p>');
            importacionHabilitada = false;
            $btnAplicar.prop('disabled', true);
            return;
        }

        if (data.mapping_ui) {
            poblarInputsMapeo(data.mapping_ui);
        }

        var hayOk = data.totales && data.totales.ok > 0;
        if (data.ok && hayOk && !(data.totales && data.totales.error > 0)) {
            $estado.removeClass().addClass('badge badge-success').text('Listo');
        } else if (hayOk) {
            $estado.removeClass().addClass('badge badge-warning').text('Revisar');
        } else {
            $estado.removeClass().addClass('badge badge-danger').text('Sin filas válidas');
        }

        var html = '';
        if (data.sin_encabezado) {
            html += '<p class="small mb-1"><strong>Sin fila de títulos</strong> · datos desde la fila 1';
            if (data.hoja_nombre) {
                html += ' · hoja ' + escHtml(data.hoja_nombre);
            }
            html += '</p>';
        } else if (data.fila_encabezado) {
            html += '<p class="small mb-1">Encabezado fila <strong>' + escHtml(data.fila_encabezado) + '</strong>';
            if (data.fila_encabezado_automatica) {
                html += ' <span class="text-muted">(detectada)</span>';
            }
            if (data.hoja_nombre) {
                html += ' · hoja ' + escHtml(data.hoja_nombre);
            }
            html += '</p>';
        }

        if (data.csv_separador_etiqueta && esArchivoCsv()) {
            html += '<p class="small mb-2">Separador CSV: <strong>' + escHtml(data.csv_separador_etiqueta) + '</strong>';
            if (data.csv_separador_automatico) {
                html += ' <span class="text-muted">(detectado)</span>';
            }
            html += '</p>';
        }

        if (data.advertencias && data.advertencias.length) {
            html += '<div class="alert alert-warning py-1 small mb-2">';
            data.advertencias.forEach(function (a) {
                html += '<div>' + escHtml(a) + '</div>';
            });
            html += '</div>';
        }

        if (data.totales) {
            html += '<p class="small mb-2">';
            html += 'OK: <strong class="text-success">' + data.totales.ok + '</strong> · ';
            html += 'Error: <strong class="text-danger">' + data.totales.error + '</strong> · ';
            html += 'Omitidas: <strong>' + (data.totales.omitidas || 0) + '</strong> · ';
            html += 'Total: <strong>' + data.totales.total + '</strong>';
            html += ' · Suma monto (OK): <strong>' + escHtml(fmtImporte(data.totales.suma_monto)) + '</strong>';
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
            html += '<div class="table-responsive" style="max-height:280px;">';
            html += '<table class="table table-sm table-bordered mb-0">';
            html += '<thead style="background:#85C1E9;color:#17202A;position:sticky;top:0;z-index:1;"><tr>';
            html += '<th>Fila</th><th class="text-right">Nro</th><th>Vencimiento</th>';
            html += '<th class="text-right">Monto</th><th>Resultado</th>';
            html += '</tr></thead><tbody>';
            data.filas_preview.forEach(function (fila) {
                var cls = fila.estado === 'ok' ? 'table-success' : (fila.estado === 'error' ? 'table-danger' : '');
                html += '<tr class="' + cls + '">';
                html += '<td>' + escHtml(fila.fila_excel) + '</td>';
                html += '<td class="text-right">' + escHtml(fila.nro_cuota) + '</td>';
                html += '<td>' + escHtml(fila.fecha_vencimiento) + '</td>';
                html += '<td class="text-right">' + escHtml(fila.monto) + '</td>';
                html += '<td><small>' + escHtml(fila.mensaje) + '</small></td>';
                html += '</tr>';
            });
            html += '</tbody></table></div>';
            if (data.hay_mas_filas) {
                html += '<p class="text-muted small mt-1 mb-0">Mostrando las primeras ' + data.filas_preview.length + ' filas. Al aplicar se cargan todas las válidas.</p>';
            }
        }

        $contenido.html(html || '<p class="text-muted small mb-0">Sin filas de datos.</p>');
        importacionHabilitada = !!hayOk && filasAplicar.length > 0;
        $btnAplicar.prop('disabled', !importacionHabilitada);
        if (importacionHabilitada) {
            $btnAplicar.removeAttr('title');
        } else if (!hayOk) {
            $btnAplicar.attr('title', 'No hay filas OK para aplicar. Revisá el mapeo y volvé a generar la vista previa.');
        } else {
            $btnAplicar.attr('title', 'Esperá a que termine la vista previa o volvé a analizar el archivo.');
        }
    }

    function solicitarPreview() {
        if (!previewUrl() || !archivoSeleccionado()) {
            return;
        }
        if (previewXhr) {
            previewXhr.abort();
        }

        var formData = new FormData(document.getElementById('form-importar-cuotas-sp'));
        $('#sp-cuotas-panel-preview').show();
        $('#sp-cuotas-preview-estado').removeClass().addClass('badge badge-secondary').text('Analizando…');
        $('#sp-cuotas-preview-contenido').html('<p class="text-muted small mb-0"><i class="fa fa-spinner fa-spin"></i> Leyendo archivo…</p>');
        mostrarOverlay('Analizando archivo…', 'Leyendo cuotas y resolviendo columnas.');

        previewXhr = $.ajax({
            url: previewUrl(),
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function (data) {
            renderPreview(data);
        }).fail(function (xhr) {
            if (xhr.statusText === 'abort') {
                return;
            }
            var msg = 'Error al analizar el archivo.';
            if (xhr.responseJSON) {
                if (xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                } else if (xhr.responseJSON.mensaje) {
                    msg = xhr.responseJSON.mensaje;
                } else if (xhr.responseJSON.errors) {
                    var partes = [];
                    $.each(xhr.responseJSON.errors, function (_, lista) {
                        (lista || []).forEach(function (item) {
                            partes.push(item);
                        });
                    });
                    if (partes.length) {
                        msg = partes.join(' ');
                    }
                }
            }
            renderPreview({ mensaje: msg });
        }).always(function () {
            previewXhr = null;
            ocultarOverlay();
        });
    }

    function programarPreview() {
        clearTimeout(previewTimer);
        previewTimer = setTimeout(solicitarPreview, 450);
    }

    function fmtMontoGrilla(n) {
        if (typeof spFmtMonto === 'function') {
            return spFmtMonto(n);
        }
        return fmtImporte(n);
    }

    function aplicarFilasAGrilla() {
        if (!importacionHabilitada || !filasAplicar.length) {
            return;
        }
        var $tbody = $('#tbody-solicitudpago-cuota-table');
        if (!$tbody.length) {
            alert('No se encontró la grilla de cuotas.');
            return;
        }
        var template = $('#template-renglon-sp-cuota').html();
        if (!template) {
            alert('No se encontró la plantilla de cuota.');
            return;
        }

        $tbody.empty();
        filasAplicar.forEach(function (fila) {
            $tbody.append(template);
            var $row = $tbody.find('tr.item-sp-cuota').last();
            $row.find('input[name="nro_cuotas[]"]').val(fila.nro_cuota);
            $row.find('input[name="fecha_vencimientos_cuota[]"]').val(fila.fecha_vencimiento);
            $row.find('input[name="montos_cuota[]"]').val(fmtMontoGrilla(fila.monto));
            $row.find('input[name="solicitudpago_hija_ids[]"]').val('');
        });

        if (typeof renumerarCuotas === 'function') {
            // Conserva nros del archivo si vienen; no reescribe.
        }

        $modal().modal('hide');
        var $tab = $('#tab-cuotas-link');
        if ($tab.length) {
            if (typeof $tab.tab === 'function') {
                $tab.tab('show');
            } else {
                $tab.trigger('click');
            }
        }
    }

    function resetModalUi() {
        filasAplicar = [];
        importacionHabilitada = false;
        $('#btn-aplicar-import-cuotas-sp').prop('disabled', true);
        $('#btn-preview-import-cuotas-sp').prop('disabled', !archivoSeleccionado());
        sincronizarUiModoColumnas();
        sincronizarPanelCsvSeparador();
    }

    $(function () {
        if (!$modal().length) {
            return;
        }

        $(document).on('click', '#btn-abrir-importar-cuotas-sp', function (e) {
            e.preventDefault();
            resetModalUi();
            $modal().modal('show');
        });

        $('#sp_cuotas_archivo').on('change', function () {
            var ok = archivoSeleccionado();
            $('#btn-preview-import-cuotas-sp').prop('disabled', !ok);
            importacionHabilitada = false;
            $('#btn-aplicar-import-cuotas-sp').prop('disabled', true);
            sincronizarPanelCsvSeparador();
            if (ok) {
                $('#sp_cuotas_hoja_indice').val(1);
                ultimasHojasDetectadas = null;
                programarPreview();
            } else {
                $('#sp-cuotas-panel-preview').hide();
            }
        });

        $('#sp_cuotas_csv_separador').on('change', function () {
            if (archivoSeleccionado()) {
                programarPreview();
            }
        });

        $('#sp_cuotas_hoja_indice_select').on('change', function () {
            $('#sp_cuotas_hoja_indice').val($(this).val());
            if (archivoSeleccionado()) {
                programarPreview();
            }
        });

        $('#btn-preview-import-cuotas-sp').on('click', solicitarPreview);
        $('#btn-aplicar-import-cuotas-sp').on('click', aplicarFilasAGrilla);

        $('#form-importar-cuotas-sp').on('change', 'input[name="modo_columnas"]', function () {
            sincronizarUiModoColumnas();
            $('.sp-cuotas-map-campo').val('');
            if (archivoSeleccionado()) {
                programarPreview();
            }
        });

        $('#form-importar-cuotas-sp').on('change', '#sp_cuotas_fila_encabezado, .sp-cuotas-map-campo', function () {
            if (sincronizandoMapa) {
                return;
            }
            if (archivoSeleccionado()) {
                programarPreview();
            }
        });

        $('#form-importar-cuotas-sp').on('keydown', 'input', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (archivoSeleccionado()) {
                    solicitarPreview();
                }
            }
        });

        $modal().on('hidden.bs.modal', function () {
            ocultarOverlay();
        });

        sincronizarUiModoColumnas();
        sincronizarPanelCsvSeparador();
    });
}(jQuery));
