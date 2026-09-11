(function ($) {
    'use strict';

    var previewTimer = null;
    var previewXhr = null;
    var ultimoPreview = null;
    var ultimasHojasDetectadas = null;

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta && meta.content) {
            return meta.content;
        }
        var hidden = document.querySelector('#form-general input[name="_token"]');
        return hidden ? hidden.value : '';
    }

    function archivoInput() {
        return document.getElementById('archivoexcel');
    }

    function archivoSeleccionado() {
        var input = archivoInput();
        return input && input.files && input.files.length > 0;
    }

    function escHtml(texto) {
        return $('<div/>').text(texto == null ? '' : String(texto)).html();
    }

    function mostrarPanelHoja(mostrar) {
        var $panel = $('#lp-panel-hoja-excel');
        if (mostrar) {
            $panel.removeClass('d-none');
        } else {
            $panel.addClass('d-none');
        }
    }

    function marcarSelectHojasCargando() {
        $('#lp-hoja-indice-select')
            .prop('disabled', true)
            .html('<option value="">Detectando hojas…</option>');
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

    function fechaVigencia() {
        if (typeof window.lpFechaVigenciaExcel === 'function') {
            return window.lpFechaVigenciaExcel();
        }
        return $('#fechavigencia_excel').val() || $('#fecha').val() || '';
    }

    function proveedorId() {
        var v = parseInt(String($('#proveedor_id').val() || '0'), 10);
        return v > 0 ? v : 0;
    }

    function armarFormDataPreview() {
        var fd = new FormData();
        fd.append('_token', csrfToken());
        var input = archivoInput();
        if (input && input.files[0]) {
            fd.append('archivoexcel', input.files[0]);
        }
        var prov = proveedorId();
        if (prov) {
            fd.append('proveedor_id', String(prov));
        }
        fd.append('col_sku', $('#lp-col-sku').val() || 'sku');
        fd.append('col_descripcion', $('#lp-col-descripcion').val() || 'descripcion');
        fd.append('col_precio', $('#lp-col-precio').val() || 'precio');
        fd.append('col_descuento', $('#lp-col-descuento').val() || 'descuento');
        fd.append('col_codigo_proveedor', $('#lp-col-codigo-proveedor').val() || 'codigo_proveedor');
        var fila = $.trim($('#lp-fila-encabezado').val() || '');
        if (fila !== '') {
            fd.append('fila_encabezado', fila);
        }
        fd.append('hoja_indice', $('#lp-hoja-indice').val() || '1');
        return fd;
    }

    function actualizarSelectorHojas(data) {
        var $select = $('#lp-hoja-indice-select');
        var $hidden = $('#lp-hoja-indice');
        var $ayuda = $('#lp-hoja-indice-ayuda');
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
        $ayuda.text('Este archivo tiene ' + hojas.length + ' hojas. Elija cuál contiene los precios.');
        mostrarPanelHoja(true);
    }

    function renderPreviewHtml(data) {
        var html = '';
        if (data.hoja_nombre) {
            html += '<p class="small mb-2">Hoja analizada: <strong>' + escHtml(data.hoja_seleccionada) + ' — ' + escHtml(data.hoja_nombre) + '</strong></p>';
        }
        if (data.sin_encabezado) {
            html += '<p class="small mb-2">Sin encabezado: se usa el orden de columnas A–D.</p>';
        } else if (data.fila_encabezado) {
            html += '<p class="small mb-2">Encabezado detectado en fila <strong>' + escHtml(data.fila_encabezado) + '</strong>';
            if (data.fila_encabezado_automatica) {
                html += ' (automático)';
            }
            html += '.</p>';
        }

        if (data.columnas) {
            html += '<div class="row small mb-2">';
            html += '<div class="col-md-4"><strong>SKU</strong> (' + escHtml(data.columnas.sku.configurado) + '): ' + badgeColumna(data.columnas.sku) + '</div>';
            html += '<div class="col-md-4"><strong>Descripción</strong> (' + escHtml(data.columnas.descripcion.configurado) + '): ' + badgeColumna(data.columnas.descripcion) + '</div>';
            html += '<div class="col-md-4"><strong>Precio</strong> (' + escHtml(data.columnas.precio.configurado) + '): ' + badgeColumna(data.columnas.precio) + '</div>';
            html += '<div class="col-md-4"><strong>% Desc.</strong> (' + escHtml(data.columnas.descuento.configurado) + '): ' + badgeColumna(data.columnas.descuento) + '</div>';
            html += '<div class="col-md-4"><strong>Cód. proveedor</strong> (' + escHtml(data.columnas.codigo_proveedor.configurado) + '): ' + badgeColumna(data.columnas.codigo_proveedor) + '</div>';
            html += '</div>';
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
            html += 'Con SKU: <strong class="text-success">' + data.resumen.importables + '</strong> · ';
            html += 'Sin SKU (informativo): <strong class="text-warning">' + (data.resumen.sin_sku || 0) + '</strong> · ';
            html += 'Omitidas: <strong class="text-muted">' + data.resumen.omitidas + '</strong>';
            html += '</p>';
        }

        if (data.filas && data.filas.length) {
            html += '<div class="table-responsive"><table class="table table-sm table-bordered mb-0">';
            html += '<thead style="background-color:#85C1E9;color:#17202A;"><tr>';
            html += '<th>Fila</th><th>SKU</th><th>Descripción Excel</th><th>Artículo sistema</th>';
            html += '<th class="text-right">Precio</th><th class="text-right">% Desc.</th><th>Cód. prov.</th><th>Resultado</th>';
            html += '</tr></thead><tbody>';
            data.filas.forEach(function (fila) {
                var cls = '';
                if (fila.estado === 'ok') {
                    cls = 'table-success';
                } else if (fila.estado === 'sin_sku') {
                    cls = 'table-warning';
                }
                html += '<tr class="' + cls + '">';
                html += '<td>' + escHtml(fila.fila_excel) + '</td>';
                html += '<td>' + escHtml(fila.sku) + '</td>';
                html += '<td><small>' + escHtml(fila.descripcion) + '</small></td>';
                html += '<td><small>' + escHtml(fila.articulo_descripcion || '—') + '</small></td>';
                html += '<td class="text-right">' + escHtml(fila.precio_texto || '') + '</td>';
                html += '<td class="text-right">' + escHtml(fila.descuento != null ? fila.descuento : '') + '</td>';
                html += '<td><small>' + escHtml(fila.codigo_proveedor || '') + '</small></td>';
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

    function habilitarAcciones(data) {
        var hayLineas = !!(data && data.lineas && data.lineas.length);
        var haySinSku = !!(data && data.lineas_sin_sku && data.lineas_sin_sku.length);
        $('#lp-btn-cargar-grilla').prop('disabled', !hayLineas && !haySinSku);
        $('#lp-btn-importar-grabar').prop('disabled', !hayLineas);
    }

    function renderPreview(data) {
        var $panel = $('#lp-panel-preview');
        var $contenido = $('#lp-preview-contenido');
        var $estado = $('#lp-preview-estado');

        $panel.show();
        actualizarSelectorHojas(data);

        if (!data || (data.mensaje && !data.resumen)) {
            $estado.removeClass().addClass('badge badge-danger').text('Error');
            $contenido.html('<p class="text-danger small mb-0">' + escHtml(data && data.mensaje ? data.mensaje : 'No se pudo analizar el archivo.') + '</p>');
            ultimoPreview = null;
            habilitarAcciones(null);
            return;
        }

        ultimoPreview = data;
        if (data.ok && data.resumen && data.resumen.importables > 0) {
            $estado.removeClass().addClass('badge badge-success').text('Listo para cargar');
        } else {
            $estado.removeClass().addClass('badge badge-warning').text('Revisar configuración');
        }

        $contenido.html(renderPreviewHtml(data));
        habilitarAcciones(data);
    }

    function solicitarPreview() {
        if (!window.lpImportPreviewUrl || !archivoSeleccionado()) {
            return;
        }

        if (previewXhr) {
            previewXhr.abort();
        }

        var $contenido = $('#lp-preview-contenido');
        var $estado = $('#lp-preview-estado');

        $('#lp-panel-preview').show();
        $estado.removeClass().addClass('badge badge-secondary').text('Analizando…');
        $contenido.html('<p class="text-muted small mb-0"><i class="fa fa-spinner fa-spin"></i> Leyendo archivo…</p>');
        $('#lp-btn-cargar-grilla, #lp-btn-importar-grabar').prop('disabled', true);
        ultimoPreview = null;

        previewXhr = $.ajax({
            url: window.lpImportPreviewUrl,
            method: 'POST',
            data: armarFormDataPreview(),
            processData: false,
            contentType: false,
            dataType: 'json',
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        }).done(function (data) {
            renderPreview(data);
        }).fail(function (xhr) {
            var msg = 'Error al analizar el archivo.';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                msg = xhr.responseJSON.message;
            } else if (xhr.responseJSON && xhr.responseJSON.mensaje) {
                msg = xhr.responseJSON.mensaje;
            } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                msg = Object.values(xhr.responseJSON.errors).flat().join(' ');
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

    function filaGrillaVacia($tr) {
        var art = $.trim($tr.find('.articulo_id').val() || '');
        var sku = $.trim($tr.find('.codigoarticulo').val() || '');
        return art === '' && sku === '';
    }

    function aplicarLineasSinSku(lineas) {
        var $card = $('#lp-card-sin-sku');
        var $tbody = $('#lp-tbody-sin-sku');
        if (!$card.length || !$tbody.length) {
            return 0;
        }
        $tbody.empty();
        if (!lineas || !lineas.length) {
            $card.addClass('d-none');
            return 0;
        }
        lineas.forEach(function (ln) {
            var tr = '<tr>';
            tr += '<td>' + escHtml(ln.sku || '') + '</td>';
            tr += '<td>' + escHtml(ln.descripcion || '') + '</td>';
            tr += '<td class="text-right">' + escHtml(ln.precio != null ? ln.precio : '') + '</td>';
            tr += '<td class="text-right">' + escHtml(ln.descuento != null ? ln.descuento : 0) + '</td>';
            tr += '<td>' + escHtml(ln.codigo_articulo_proveedor || '') + '</td>';
            tr += '</tr>';
            $tbody.append(tr);
        });
        $('#lp-sin-sku-contador').text(lineas.length);
        $card.removeClass('d-none');
        return lineas.length;
    }

    function aplicarLineas(lineas, lineasSinSku) {
        lineas = lineas || [];
        lineasSinSku = lineasSinSku || [];
        if (!lineas.length && !lineasSinSku.length) {
            return { grabables: 0, informativos: 0 };
        }
        var $tbody = $('#tabla-articulos-listaprecio tbody');
        var $existentes = $tbody.find('tr.item-listaprecio-articulo');
        var llenas = $existentes.filter(function () {
            return !filaGrillaVacia($(this));
        });
        var msgConfirm = 'Se cargarán ' + lineas.length + ' renglón(es) con SKU';
        if (lineasSinSku.length) {
            msgConfirm += ' y ' + lineasSinSku.length + ' precio(s) sin SKU (informativos, no se graban)';
        }
        msgConfirm += '. Los precios actuales de la grilla no se borran.';
        if (llenas.length && lineas.length && !window.confirm(msgConfirm)) {
            return { grabables: 0, informativos: 0 };
        }
        if (lineas.length && $existentes.length === 1 && filaGrillaVacia($existentes.first())) {
            $existentes.first().remove();
        }

        var fecha = fechaVigencia();
        var agregados = 0;
        lineas.forEach(function (ln) {
            if (typeof window.lpAgregarRenglonArticulo === 'function') {
                window.lpAgregarRenglonArticulo();
            }
            var $tr = $('#tabla-articulos-listaprecio tbody tr.item-listaprecio-articulo').last();
            if (!$tr.length) {
                return;
            }
            $tr.find('.articulo_id').val(ln.articulo_id || '');
            $tr.find('.codigoarticulo').val(ln.sku || '');
            $tr.find('.descripcionarticulo').val(ln.descripcion || '');
            $tr.find('input[name="precios[]"]').val(ln.precio != null ? ln.precio : '');
            $tr.find('input[name="descuentos[]"]').val(ln.descuento != null ? ln.descuento : 0);
            $tr.find('input[name="codigos_articulo_proveedor[]"]').val(ln.codigo_articulo_proveedor || '');
            if (fecha) {
                $tr.find('input[name="fechavigencias[]"]').val(fecha);
            }
            agregados++;
        });

        var informativos = aplicarLineasSinSku(lineasSinSku);

        var input = archivoInput();
        if (input) {
            input.value = '';
        }
        $('#lp-btn-preview').prop('disabled', true);
        $('#lp-btn-cargar-grilla, #lp-btn-importar-grabar').prop('disabled', true);
        $('a[href="#tab-precios"]').tab('show');

        return { grabables: agregados, informativos: informativos };
    }

    function importarYGrabar() {
        if (!window.lpImportExcelUrl || !archivoSeleccionado()) {
            return;
        }
        var fecha = fechaVigencia();
        if (!fecha) {
            alert('Indique la fecha de vigencia.');
            return;
        }
        if (!ultimoPreview || !ultimoPreview.lineas || !ultimoPreview.lineas.length) {
            alert('No hay ítems con SKU para grabar. Los precios sin SKU son solo informativos.');
            return;
        }
        var extra = '';
        if (ultimoPreview.lineas_sin_sku && ultimoPreview.lineas_sin_sku.length) {
            extra = ' Los ' + ultimoPreview.lineas_sin_sku.length + ' precio(s) sin SKU no se graban.';
        }
        if (!window.confirm('Se grabarán ' + ultimoPreview.lineas.length + ' precio(s) con SKU en esta lista.' + extra + ' ¿Continuar?')) {
            return;
        }

        var fd = armarFormDataPreview();
        fd.append('fechavigencia', fecha);

        $('#lp-btn-importar-grabar').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Grabando…');

        $.ajax({
            url: window.lpImportExcelUrl,
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            dataType: 'json',
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        }).done(function (data) {
            var msg = (data && data.mensaje) ? data.mensaje : 'Importación realizada.';
            alert(msg);
            window.location.reload();
        }).fail(function (xhr) {
            var msg = 'Error al importar.';
            if (xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.mensaje)) {
                msg = xhr.responseJSON.message || xhr.responseJSON.mensaje;
            }
            alert(msg);
            $('#lp-btn-importar-grabar').prop('disabled', false).html('<i class="fa fa-upload"></i> Importar y grabar');
        });
    }

    $(function () {
        if (!$('#lp-card-importar-excel').length) {
            return;
        }

        $('#archivoexcel').on('change', function () {
            var tieneArchivo = archivoSeleccionado();
            $('#lp-btn-preview').prop('disabled', !tieneArchivo);
            if (tieneArchivo) {
                $('#lp-hoja-indice').val(1);
                ultimasHojasDetectadas = null;
                mostrarPanelHoja(true);
                marcarSelectHojasCargando();
                programarPreview();
            } else {
                $('#lp-panel-preview').hide();
                ultimasHojasDetectadas = null;
                mostrarPanelHoja(false);
                ultimoPreview = null;
                habilitarAcciones(null);
            }
        });

        $('#lp-hoja-indice-select').on('change', function () {
            $('#lp-hoja-indice').val($(this).val());
            if (archivoSeleccionado()) {
                programarPreview();
            }
        });

        $('#lp-btn-preview').on('click', function () {
            solicitarPreview();
        });

        $('#form-general').on(
            'change input',
            '#lp-col-sku, #lp-col-descripcion, #lp-col-precio, #lp-col-descuento, #lp-col-codigo-proveedor, #lp-fila-encabezado, #proveedor_id',
            function () {
                if (archivoSeleccionado()) {
                    programarPreview();
                }
            }
        );

        $('#lp-btn-cargar-grilla').on('click', function () {
            var lineas = (ultimoPreview && ultimoPreview.lineas) ? ultimoPreview.lineas : [];
            var sinSku = (ultimoPreview && ultimoPreview.lineas_sin_sku) ? ultimoPreview.lineas_sin_sku : [];
            if (!lineas.length && !sinSku.length) {
                alert('No hay ítems para cargar. Revise la vista previa.');
                return;
            }
            var n = aplicarLineas(lineas, sinSku);
            if (n.grabables > 0 || n.informativos > 0) {
                var msg = n.grabables + ' renglón(es) con SKU en la grilla (se graban al Guardar).';
                if (n.informativos > 0) {
                    msg += ' ' + n.informativos + ' precio(s) sin SKU en el bloque informativo (no se graban).';
                }
                alert(msg);
            }
        });

        $('#lp-btn-importar-grabar').on('click', function () {
            importarYGrabar();
        });

        if (window.location.hash === '#importar-excel') {
            $('a[href="#tab-precios"]').tab('show');
            var card = document.getElementById('lp-card-importar-excel');
            if (card && card.scrollIntoView) {
                setTimeout(function () {
                    card.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }, 150);
            }
        }
    });
}(jQuery));
