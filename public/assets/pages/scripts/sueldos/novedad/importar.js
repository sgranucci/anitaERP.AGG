/**
 * Importación de novedades de sueldos: vista previa AJAX + mapeo de columnas.
 */
(function ($) {
    'use strict';

    var previewTimer = null;
    var previewXhr = null;
    var ultimasHojasDetectadas = null;
    var sincronizandoMapa = false;
    // No usar el disabled del botón para autorizar el submit: grabacion-bloqueo-submit
    // lo apaga en captura antes de que corra este handler, y eso abortaba el POST.
    var importacionHabilitada = false;

    function archivoSeleccionado() {
        var input = document.getElementById('archivo');
        return input && input.files && input.files.length > 0;
    }

    function modoColumnasActual() {
        var checked = document.querySelector('input[name="modo_columnas"]:checked');
        return checked ? checked.value : 'titulo';
    }

    function esArchivoCsv() {
        var input = document.getElementById('archivo');
        if (!input || !input.files || !input.files.length) {
            return false;
        }
        var nombre = String(input.files[0].name || '').toLowerCase();
        return nombre.endsWith('.csv') || nombre.endsWith('.txt');
    }

    function sincronizarPanelCsvSeparador() {
        var $select = $('#csv_separador');
        var $ayuda = $('#ayuda-csv-separador');
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
            $('#label-fila-encabezado').text('Primera fila de datos');
            $('#ayuda-fila-encabezado').text('Vacío = fila 1. No hay fila de títulos: se leen todas las filas desde ahí.');
            $('#fila_encabezado').attr('placeholder', '1');
            $('#ayuda-modo-columnas').text('Sin título: en el mapeo tipá el número de columna (1 = primera) o letra Excel (A, B…).');
            $('#ayuda-mapeo-columnas').html('Sin título: número (1 = primera) o letra Excel (A, B…). Vacío = sin mapear ese campo.');
            $('.map-campo').attr('placeholder', 'ej. 1 o A');
        } else {
            $('#label-fila-encabezado').text('Fila del encabezado');
            $('#ayuda-fila-encabezado').text('Vacío = detectar automáticamente');
            $('#fila_encabezado').attr('placeholder', 'Auto');
            $('#ayuda-modo-columnas').text('Con título: se detecta la fila de encabezados. Si el Excel usa otros nombres, tipá el nombre en el mapeo.');
            $('#ayuda-mapeo-columnas').html('Con título: nombre del encabezado (ej. <code>legajo</code>). También acepta número/letra. Vacío = usa la detección automática.');
            $('.map-campo').attr('placeholder', 'ej. legajo');
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
        var overlay = document.getElementById('novedad-import-overlay');
        if (!overlay) {
            return;
        }
        if (titulo) {
            document.getElementById('novedad-import-overlay-titulo').textContent = titulo;
        }
        if (subtitulo) {
            document.getElementById('novedad-import-overlay-subtitulo').textContent = subtitulo;
        }
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }

    function ocultarOverlay() {
        var overlay = document.getElementById('novedad-import-overlay');
        if (!overlay) {
            return;
        }
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
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

    function poblarInputsMapeo(mappingUi) {
        sincronizandoMapa = true;
        $('.map-campo').each(function () {
            var $input = $(this);
            var campo = $input.data('campo');
            var actual = mappingUi && mappingUi[campo] != null ? String(mappingUi[campo]) : '';
            // No pisa lo que el operador está tipando si el foco está en ese campo.
            if (document.activeElement === this) {
                return;
            }
            // Conserva valor tipado/recordado si la detección no resolvió este campo.
            if (actual === '' && $.trim(String($input.val() || '')) !== '') {
                return;
            }
            $input.val(actual);
        });
        sincronizandoMapa = false;
    }

    function renderPreview(data) {
        var $contenido = $('#preview-import-novedad-contenido');
        var $estado = $('#preview-import-novedad-estado');
        var $btnConfirmar = $('#btn-confirmar-import-novedad');

        $('#panel-preview-import-novedad').show();
        actualizarSelectorHojas(data);

        if (!data || (data.mensaje && !data.headers)) {
            $estado.removeClass().addClass('badge badge-danger').text('Error');
            $contenido.html('<p class="text-danger small mb-0">' + escHtml(data && data.mensaje ? data.mensaje : 'No se pudo analizar.') + '</p>');
            importacionHabilitada = false;
            $btnConfirmar.prop('disabled', true);
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
            html += ' · Valor 1 (OK): <strong>' + escHtml(fmtImporte(data.totales.suma_valor1)) + '</strong>';
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
            html += '<th>Fila</th><th>Legajo</th><th>Empleado</th><th>Concepto</th><th>Liquidación</th>';
            html += '<th class="text-right">Valor 1</th><th class="text-right">Valor 2</th><th>Vigencia</th><th>Estado</th><th>Resultado</th>';
            html += '</tr></thead><tbody>';
            data.filas_preview.forEach(function (fila) {
                var cls = fila.estado === 'ok' ? 'table-success' : (fila.estado === 'error' ? 'table-danger' : '');
                html += '<tr class="' + cls + '">';
                html += '<td>' + escHtml(fila.fila_excel) + '</td>';
                html += '<td>' + escHtml(fila.legajo) + '</td>';
                html += '<td><small>' + escHtml(fila.empleado) + '</small></td>';
                html += '<td><small>' + escHtml(fila.concepto) + '</small></td>';
                html += '<td><small>' + escHtml(fila.liquidacion) + '</small></td>';
                html += '<td class="text-right">' + escHtml(fila.valor1) + '</td>';
                html += '<td class="text-right">' + escHtml(fila.valor2) + '</td>';
                html += '<td><small>' + escHtml(fila.vigencia) + '</small></td>';
                html += '<td><small>' + escHtml(fila.estado_novedad) + '</small></td>';
                html += '<td><small>' + escHtml(fila.mensaje) + '</small></td>';
                html += '</tr>';
            });
            html += '</tbody></table></div>';
            if (data.hay_mas_filas) {
                html += '<p class="text-muted small mt-1 mb-0">Mostrando las primeras ' + data.filas_preview.length + ' filas. Al confirmar se procesa el archivo completo.</p>';
            }
        }

        $contenido.html(html || '<p class="text-muted small mb-0">Sin filas de datos.</p>');
        importacionHabilitada = !!hayOk;
        $btnConfirmar.prop('disabled', !importacionHabilitada);
    }

    function solicitarPreview() {
        if (!window.novedadImportPreviewUrl || !archivoSeleccionado()) {
            return;
        }
        if (previewXhr) {
            previewXhr.abort();
        }

        var formData = new FormData(document.getElementById('form-importar-novedad'));
        $('#panel-preview-import-novedad').show();
        $('#preview-import-novedad-estado').removeClass().addClass('badge badge-secondary').text('Analizando…');
        $('#preview-import-novedad-contenido').html('<p class="text-muted small mb-0"><i class="fa fa-spinner fa-spin"></i> Leyendo archivo…</p>');
        mostrarOverlay('Analizando archivo…', 'Leyendo filas y resolviendo legajos y conceptos.');

        previewXhr = $.ajax({
            url: window.novedadImportPreviewUrl,
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

    $(function () {
        sincronizarUiModoColumnas();
        sincronizarPanelCsvSeparador();

        $('#archivo').on('change', function () {
            var ok = archivoSeleccionado();
            $('#btn-preview-import-novedad').prop('disabled', !ok);
            importacionHabilitada = false;
            $('#btn-confirmar-import-novedad').prop('disabled', true);
            sincronizarPanelCsvSeparador();
            if (ok) {
                $('#hoja_indice').val(1);
                ultimasHojasDetectadas = null;
                programarPreview();
            } else {
                $('#panel-preview-import-novedad').hide();
            }
        });

        $('#csv_separador').on('change', function () {
            if (archivoSeleccionado()) {
                programarPreview();
            }
        });

        $('#hoja_indice_select').on('change', function () {
            $('#hoja_indice').val($(this).val());
            if (archivoSeleccionado()) {
                programarPreview();
            }
        });

        $('#btn-preview-import-novedad').on('click', solicitarPreview);

        $('#form-importar-novedad').on('change', 'input[name="modo_columnas"]', function () {
            sincronizarUiModoColumnas();
            // Al cambiar de modo, el mapeo anterior no aplica.
            $('.map-campo').val('');
            if (archivoSeleccionado()) {
                programarPreview();
            }
        });

        $('#form-importar-novedad').on('change', '#fila_encabezado, #empresa_id, .map-campo', function () {
            if (sincronizandoMapa) {
                return;
            }
            if (archivoSeleccionado()) {
                programarPreview();
            }
        });

        // Mientras tipan el mapeo, no disparamos preview en cada tecla: solo al salir del campo (change).

        $('#form-importar-novedad').on('keydown', 'input', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (archivoSeleccionado()) {
                    solicitarPreview();
                }
            }
        });

        $('#form-importar-novedad').on('submit', function (e) {
            if (!importacionHabilitada || !archivoSeleccionado()) {
                e.preventDefault();
                return;
            }
            var form = this;
            if (form.checkValidity && !form.checkValidity()) {
                return;
            }
            mostrarOverlay('Importando novedades…', 'Puede demorar según la cantidad de filas. No cierre la página.');
        });

        window.addEventListener('pageshow', ocultarOverlay);
    });
}(jQuery));
