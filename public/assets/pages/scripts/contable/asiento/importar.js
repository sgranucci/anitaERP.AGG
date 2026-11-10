(function ($) {
    'use strict';

    var previewTimer = null;
    var previewXhr = null;
    var ultimasHojasDetectadas = null;
    var ajustandoDefaultsColumnas = false;

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

    function modoColumnasActual() {
        return $('input[name="modo_columnas"]:checked').val() || 'titulo';
    }

    function modoImporteActual() {
        return $('input[name="modo_importe"]:checked').val() || 'debe_haber';
    }

    function esArchivoCsv() {
        var input = document.getElementById('file');
        if (!input || !input.files || !input.files.length) {
            return false;
        }
        var nombre = String(input.files[0].name || '').toLowerCase();
        return nombre.endsWith('.csv') || nombre.endsWith('.txt');
    }

    function sincronizarPanelCsvSeparador() {
        var $panel = $('#panel-csv-separador');
        var $select = $('#csv_separador');
        // Siempre visible: en CSV queda marcado como aplicable; en Excel se ignora.
        $panel.removeClass('d-none');
        if (esArchivoCsv()) {
            $select.addClass('border-primary');
            $panel.find('.col-form-label').text('Obligatorio para este CSV/TXT. Cambiá el separador y revisá la vista previa.');
        } else if (archivoSeleccionado()) {
            $select.removeClass('border-primary');
            $panel.find('.col-form-label').text('Archivo Excel: el separador no se usa.');
        } else {
            $select.removeClass('border-primary');
            $panel.find('.col-form-label').text('Obligatorio para CSV/TXT. En Excel se ignora.');
        }
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

    function esValorDefaultColumna($input, modo) {
        var valor = $.trim(String($input.val() || ''));
        var defTitulo = String($input.data('default-titulo') || '');
        var defNumero = String($input.data('default-numero') || '');
        if (valor === '') {
            return true;
        }
        if (modo === 'numero') {
            // Cambiando a número: si todavía tiene el default de título, se puede reemplazar.
            return valor === defTitulo || valor.toLowerCase() === defTitulo.toLowerCase();
        }
        // Cambiando a título: vacío o el default numérico.
        return valor === defNumero;
    }

    function aplicarDefaultsColumnas(forzar) {
        var porNumero = modoColumnasActual() === 'numero';
        ajustandoDefaultsColumnas = true;
        $('.js-col-config').each(function () {
            var $input = $(this);
            var defTitulo = String($input.data('default-titulo') || '');
            var defNumero = String($input.data('default-numero') || '');
            var destino = porNumero ? defNumero : defTitulo;
            if (forzar || esValorDefaultColumna($input, porNumero ? 'numero' : 'titulo')) {
                $input.val(destino);
            }
            $input.attr('placeholder', destino !== '' ? destino : (porNumero ? 'nº col.' : defTitulo));
        });
        ajustandoDefaultsColumnas = false;
    }

    function sincronizarUiModos() {
        var porNumero = modoColumnasActual() === 'numero';
        var montoDh = modoImporteActual() === 'monto_dh';

        if (montoDh) {
            $('.js-panel-debe-haber').hide();
            $('.js-panel-monto-dh').show();
        } else {
            $('.js-panel-monto-dh').hide();
            $('.js-panel-debe-haber').show();
        }

        if (porNumero) {
            $('#label-fila-encabezado').text('Primera fila de datos');
            $('#ayuda-fila-encabezado').text('Vacío = fila 1. No hay fila de títulos: se leen todas las filas desde ahí.');
            $('#fila_encabezado').attr('placeholder', '1');
            $('.js-ayuda-col-cuenta').html('Número de columna (1 = primera) o letra Excel (A, B…).');
        } else {
            $('#label-fila-encabezado').text('Fila del encabezado');
            $('#ayuda-fila-encabezado').text('Vacío = detectar automáticamente (título arriba, encabezado en fila 2, 3, etc.)');
            $('#fila_encabezado').attr('placeholder', 'Auto');
            $('.js-ayuda-col-cuenta').html('Código de cuenta contable. Alias: <code>cuenta</code>, <code>codigo_cuenta</code>, <code>cta</code>…');
        }
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

    function tablaPreviewFilas(filas, listas) {
        var html = '<div class="table-responsive"><table class="table table-sm table-bordered mb-0">';
        html += '<thead style="background-color:#85C1E9;color:#17202A;"><tr>';
        html += '<th>Fila</th><th>Cuenta</th><th>Nombre</th><th>CC</th><th>Mon.</th>';
        html += '<th class="text-right">Debe</th><th class="text-right">Haber</th><th>D/H</th><th>Detalle</th><th>Resultado</th>';
        html += '</tr></thead><tbody>';
        filas.forEach(function (fila) {
            var cls = listas ? 'table-success' : 'table-warning';
            html += '<tr class="' + cls + '">';
            html += '<td>' + escHtml(fila.fila_excel) + '</td>';
            html += '<td>' + escHtml(fila.codigo_cuenta) + '</td>';
            html += '<td><small>' + escHtml(fila.cuenta_nombre || '—') + '</small></td>';
            html += '<td><small>' + escHtml(fila.codigo_centrocosto || '—') + '</small></td>';
            html += '<td>' + escHtml(fila.moneda_abreviatura || '') + '</td>';
            html += '<td class="text-right">' + escHtml(fila.debe_texto || '') + '</td>';
            html += '<td class="text-right">' + escHtml(fila.haber_texto || '') + '</td>';
            html += '<td>' + escHtml(fila.dh || fila.dh_texto || '') + '</td>';
            html += '<td><small>' + escHtml(fila.detalle || '') + '</small></td>';
            html += '<td><small>' + escHtml(fila.mensaje) + '</small></td>';
            html += '</tr>';
        });
        html += '</tbody></table></div>';
        return html;
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

        var hayErroresFila = data.filas_error && data.filas_error.length;
        var paraCompletar = data.ok && data.resumen && (!data.resumen.balanceado || (data.resumen.sin_importe || 0) > 0);
        if (paraCompletar) {
            $estado.removeClass().addClass('badge badge-warning').text('Se abre el asiento para completar');
        } else if (data.ok) {
            $estado.removeClass().addClass('badge badge-success').text('Listo para importar');
        } else if (hayErroresFila) {
            $estado.removeClass().addClass('badge badge-danger').text('Errores en filas');
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

        if (data.sin_encabezado) {
            html += '<p class="small mb-2">Sin fila de títulos. Datos desde la fila <strong>'
                + escHtml(data.fila_inicio_datos || 1) + '</strong>.</p>';
        } else {
            html += '<p class="small mb-2">Encabezado detectado en fila <strong>' + escHtml(data.fila_encabezado) + '</strong>';
            if (data.fila_encabezado_automatica) {
                html += ' (automático)';
            }
            html += '.</p>';
        }

        if (data.modo_importe === 'monto_dh') {
            html += '<p class="small mb-2">Modo importes: <strong>monto + D/H</strong>.</p>';
        } else {
            html += '<p class="small mb-2">Modo importes: <strong>Debe / Haber</strong>.</p>';
        }

        if (esArchivoCsv() && data.csv_separador_etiqueta) {
            html += '<p class="small mb-2">Separador CSV: <strong>' + escHtml(data.csv_separador_etiqueta) + '</strong>';
            if (data.csv_separador_detectado && data.csv_separador_automatico) {
                html += ' <span class="text-muted">(' + escHtml(data.csv_separador_detectado) + ')</span>';
            }
            html += '.</p>';
        }

        if (data.columnas) {
            html += '<div class="row small mb-2">';
            html += '<div class="col-md-4"><strong>Cuenta</strong> (' + escHtml(data.columnas.cuenta.configurado) + '): ' + badgeColumna(data.columnas.cuenta) + '</div>';
            if (data.modo_importe === 'monto_dh') {
                html += '<div class="col-md-4"><strong>Monto</strong> (' + escHtml(data.columnas.monto.configurado) + '): ' + badgeColumna(data.columnas.monto) + '</div>';
                html += '<div class="col-md-4"><strong>D/H</strong> (' + escHtml(data.columnas.dh.configurado) + '): ' + badgeColumna(data.columnas.dh) + '</div>';
            } else {
                html += '<div class="col-md-4"><strong>Debe</strong> (' + escHtml(data.columnas.debe.configurado) + '): ' + badgeColumna(data.columnas.debe) + '</div>';
                html += '<div class="col-md-4"><strong>Haber</strong> (' + escHtml(data.columnas.haber.configurado) + '): ' + badgeColumna(data.columnas.haber) + '</div>';
            }
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
            html += 'Omitidas: <strong class="' + (data.resumen.omitidas > 0 ? 'text-danger' : 'text-muted') + '">' + data.resumen.omitidas + '</strong>';
            html += '</p>';
            html += '<p class="small mb-2">';
            html += 'Total Debe: <strong>' + escHtml(data.resumen.total_debe_texto) + '</strong> · ';
            html += 'Total Haber: <strong>' + escHtml(data.resumen.total_haber_texto) + '</strong>';
            if (data.resumen.balanceado) {
                html += ' · <span class="badge badge-success">Balanceado</span>';
            } else if (data.resumen.desbalance_por_filas_omitidas) {
                html += ' · <span class="badge badge-danger">Desbalance ' + escHtml(data.resumen.diferencia_texto) + '</span>';
                html += ' <span class="text-danger">igual a las filas que no entran</span>';
            } else {
                html += ' · <span class="badge badge-danger">Desbalance ' + escHtml(data.resumen.diferencia_texto) + '</span>';
            }
            html += '</p>';
        }

        if (hayErroresFila) {
            html += '<p class="small mb-1 text-danger"><strong>Filas que no entran (' + data.filas_error.length + ')</strong></p>';
            html += tablaPreviewFilas(data.filas_error, false);
        }

        if (data.filas && data.filas.length) {
            html += '<p class="small mb-1 mt-2"><strong>Muestra de filas listas para cargar</strong></p>';
            html += tablaPreviewFilas(data.filas, true);
            if (data.hay_mas_filas) {
                var totalOk = data.resumen && data.resumen.importables != null
                    ? data.resumen.importables
                    : data.filas.length;
                html += '<p class="text-muted small mt-2 mb-0">Mostrando las primeras ' + data.filas.length
                    + ' filas listas de ' + totalOk + '.</p>';
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
        sincronizarUiModos();
        sincronizarPanelCsvSeparador();

        $('#file').on('change', function () {
            var tieneArchivo = archivoSeleccionado();
            $('#btn-preview-import-asiento').prop('disabled', !tieneArchivo);
            sincronizarPanelCsvSeparador();
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

        $('#btn-preview-import-asiento').on('click', function () {
            solicitarPreview();
        });

        $('input[name="modo_columnas"]').on('change', function () {
            aplicarDefaultsColumnas(false);
            sincronizarUiModos();
            if (archivoSeleccionado()) {
                programarPreview();
            }
        });

        $('input[name="modo_importe"]').on('change', function () {
            sincronizarUiModos();
            if (archivoSeleccionado()) {
                programarPreview();
            }
        });

        $('#form-general').on(
            'change input',
            '#empresa_id, #moneda_id, #col_cuenta, #col_debe, #col_haber, #col_monto, #col_dh, #col_centrocosto, #col_moneda, #col_cotizacion, #col_detalle, #fila_encabezado',
            function () {
                if (ajustandoDefaultsColumnas) {
                    return;
                }
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
