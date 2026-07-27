(function ($) {
    'use strict';

    var previewTimer = null;
    var previewXhr = null;

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
        if (!col.requerida) {
            return '<span class="badge badge-info">Opcional — se genera</span>';
        }
        return '<span class="badge badge-danger">No encontrada (requerida)</span>';
    }

    function renderPreview(data) {
        var $panel = $('#panel-preview-import-usuario');
        var $contenido = $('#preview-import-usuario-contenido');
        var $estado = $('#preview-import-usuario-estado');

        $panel.show();
        actualizarSelectorHojas(data);

        if (!data || (data.mensaje && !data.resumen)) {
            $estado.removeClass().addClass('badge badge-danger').text('Error');
            $contenido.html('<p class="text-danger small mb-0">' + escHtml(data && data.mensaje ? data.mensaje : 'No se pudo analizar el archivo.') + '</p>');
            return;
        }

        if (data.ok) {
            $estado.removeClass().addClass('badge badge-success').text('Listo para importar');
        } else {
            $estado.removeClass().addClass('badge badge-warning').text('Revisar configuración');
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
            html += '<div class="col-md-4"><strong>Usuario</strong> (' + escHtml(data.columnas.usuario.configurado) + '): ' + badgeColumna(data.columnas.usuario) + '</div>';
            html += '<div class="col-md-4"><strong>Nombre</strong> (' + escHtml(data.columnas.nombre.configurado) + '): ' + badgeColumna(data.columnas.nombre) + '</div>';
            html += '<div class="col-md-4"><strong>Email</strong> (' + escHtml(data.columnas.email.configurado) + '): ' + badgeColumna(data.columnas.email) + '</div>';
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
            html += 'Importables: <strong class="text-success">' + data.resumen.importables + '</strong> · ';
            html += 'Omitidas: <strong class="text-muted">' + data.resumen.omitidas + '</strong>';
            if (data.resumen.usuarios_existentes) {
                html += ' · Ya existentes: <strong class="text-warning">' + data.resumen.usuarios_existentes + '</strong>';
            }
            if (data.resumen.logins_generados || data.resumen.emails_generados) {
                html += ' · Auto: <strong>' + (data.resumen.logins_generados || 0) + '</strong> login(s), <strong>' + (data.resumen.emails_generados || 0) + '</strong> email(s)';
            }
            html += '</p>';
        }

        if (data.dominio_email) {
            html += '<p class="small mb-2 text-muted">Dominio email: <code>' + escHtml(data.dominio_email) + '</code></p>';
        }

        if (data.filas && data.filas.length) {
            html += '<div class="table-responsive"><table class="table table-sm table-bordered mb-0">';
            html += '<thead style="background-color:#85C1E9;color:#17202A;"><tr>';
            html += '<th>Fila</th><th>Usuario</th><th>Nombre</th><th>Email</th><th>Resultado</th>';
            html += '</tr></thead><tbody>';
            data.filas.forEach(function (fila) {
                var cls = fila.estado === 'ok' ? 'table-success' : '';
                var loginTxt = escHtml(fila.usuario);
                var emailTxt = escHtml(fila.email);
                if (fila.login_generado) {
                    loginTxt += ' <span class="badge badge-info">auto</span>';
                }
                if (fila.email_generado) {
                    emailTxt += ' <span class="badge badge-info">auto</span>';
                }
                html += '<tr class="' + cls + '">';
                html += '<td>' + escHtml(fila.fila_excel) + '</td>';
                html += '<td>' + loginTxt + '</td>';
                html += '<td><small>' + escHtml(fila.nombre) + '</small></td>';
                html += '<td><small>' + emailTxt + '</small></td>';
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

    function actualizarSelectorHojas(data) {
        var hojas = (data && data.hojas) || [];
        var $select = $('#hoja_indice_select');
        var $hidden = $('#hoja_indice');
        var $ayuda = $('#hoja_indice_ayuda');

        if (!hojas.length) {
            mostrarPanelHoja(false);
            return;
        }

        if (hojas.length <= 1) {
            $hidden.val(String(hojas[0].indice || 1));
            mostrarPanelHoja(false);
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
        $ayuda.text('Este archivo tiene ' + hojas.length + ' hojas. Elija cuál contiene los usuarios a importar.');
        mostrarPanelHoja(true);
    }

    function solicitarPreview() {
        if (!window.usuarioImportPreviewUrl || !archivoSeleccionado()) {
            return;
        }

        if (previewXhr) {
            previewXhr.abort();
        }

        var formData = new FormData(document.getElementById('form-general'));
        var $contenido = $('#preview-import-usuario-contenido');
        var $estado = $('#preview-import-usuario-estado');

        $('#panel-preview-import-usuario').show();
        $estado.removeClass().addClass('badge badge-secondary').text('Analizando…');
        $contenido.html('<p class="text-muted small mb-0"><i class="fa fa-spinner fa-spin"></i> Leyendo archivo…</p>');

        previewXhr = $.ajax({
            url: window.usuarioImportPreviewUrl,
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
            } else if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                var first = Object.values(xhr.responseJSON.errors)[0];
                if (first && first[0]) {
                    msg = first[0];
                }
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

    function mostrarOverlayImportacion() {
        var overlay = document.getElementById('usuario-import-overlay');
        if (!overlay) {
            return;
        }
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }

    function ocultarOverlayImportacion() {
        var overlay = document.getElementById('usuario-import-overlay');
        if (!overlay) {
            return;
        }
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }

    function sincronizarAsignacionesAntesDeEnviar() {
        var $rolesHidden = $('#roles_asignados_hidden');
        if ($rolesHidden.length) {
            $rolesHidden.empty();
            $('#roles_asignados_list option').each(function () {
                $rolesHidden.append($('<input>', { type: 'hidden', name: 'rol_id[]', value: $(this).val() }));
            });
        }
        var $empresasHidden = $('#empresas_asignadas_hidden');
        if ($empresasHidden.length) {
            $empresasHidden.empty();
            $('#empresas_asignadas_list option').each(function () {
                $empresasHidden.append($('<input>', { type: 'hidden', name: 'empresa_ids[]', value: $(this).val() }));
            });
        }
    }

    $(function () {
        $('#file').on('change', function () {
            var tieneArchivo = archivoSeleccionado();
            $('#btn-preview-import-usuario').prop('disabled', !tieneArchivo);
            if (tieneArchivo) {
                $('#hoja_indice').val(1);
                mostrarPanelHoja(true);
                marcarSelectHojasCargando();
                programarPreview();
            } else {
                $('#panel-preview-import-usuario').hide();
                mostrarPanelHoja(false);
            }
        });

        $('#hoja_indice_select').on('change', function () {
            $('#hoja_indice').val($(this).val());
            if (archivoSeleccionado()) {
                programarPreview();
            }
        });

        $('#btn-preview-import-usuario').on('click', function () {
            solicitarPreview();
        });

        $('#form-general').on('change input', '#col_usuario, #col_nombre, #col_email, #fila_encabezado, #dominio_email, #generar_login_si_falta, #generar_email_si_falta', function () {
            if (archivoSeleccionado()) {
                programarPreview();
            }
        });

        $('#form-general').on('submit', function (e) {
            sincronizarAsignacionesAntesDeEnviar();

            var roles = $('input[name="rol_id[]"]').filter(function () {
                return $(this).val();
            }).length;
            var empresas = $('input[name="empresa_ids[]"]').filter(function () {
                return $(this).val();
            }).length;

            if (roles < 1) {
                e.preventDefault();
                alert('Debe asignar al menos un rol.');
                return false;
            }
            if (empresas < 1) {
                e.preventDefault();
                alert('Debe asignar al menos una empresa.');
                return false;
            }
            if ($('#password').val() !== $('#re_password').val()) {
                e.preventDefault();
                alert('Las contraseñas no coinciden.');
                return false;
            }
            if (!this.checkValidity()) {
                return true;
            }
            mostrarOverlayImportacion();
            return true;
        });

        window.addEventListener('pageshow', ocultarOverlayImportacion);
    });
}(jQuery));
