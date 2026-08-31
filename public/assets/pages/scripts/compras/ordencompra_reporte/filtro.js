function ocReporteNormalizarValor(valor) {
    return String(valor || '').trim();
}

function ocReporteEsTeclaF1(e) {
    return e.key === 'F1' || e.code === 'F1' || e.keyCode === 112;
}

function ocReporteModalAbierto(selector) {
    var $m = $(selector);
    return $m.length && $m.hasClass('show');
}

function ocReporteEsPantallaActiva() {
    return $('#form-ordencompra-reporte').length > 0;
}

function ocReporteMostrarOverlay(titulo, subtitulo) {
    var overlay = document.getElementById('oc-reporte-overlay');
    if (!overlay) {
        return;
    }
    if (titulo) {
        var t = document.getElementById('oc-reporte-overlay-titulo');
        if (t) {
            t.textContent = titulo;
        }
    }
    if (subtitulo) {
        var s = document.getElementById('oc-reporte-overlay-subtitulo');
        if (s) {
            s.textContent = subtitulo;
        }
    }
    overlay.classList.remove('d-none');
    overlay.style.display = 'flex';
    overlay.setAttribute('aria-hidden', 'false');
}

function ocReporteOcultarOverlay() {
    var overlay = document.getElementById('oc-reporte-overlay');
    if (!overlay) {
        return;
    }
    if (window.__ocReporteExportSafetyTimer) {
        clearTimeout(window.__ocReporteExportSafetyTimer);
        window.__ocReporteExportSafetyTimer = null;
    }
    overlay.classList.add('d-none');
    overlay.style.display = '';
    overlay.setAttribute('aria-hidden', 'true');
}

function ocReporteNombreArchivoDesdeDisposition(disposition, fallback) {
    if (!disposition) {
        return fallback;
    }
    var match = /filename\*=UTF-8''([^;]+)|filename="([^"]+)"|filename=([^;]+)/i.exec(disposition);
    if (!match) {
        return fallback;
    }
    var raw = (match[1] || match[2] || match[3] || '').trim();
    try {
        return decodeURIComponent(raw.replace(/['"]/g, ''));
    } catch (e) {
        return raw.replace(/['"]/g, '') || fallback;
    }
}

function ocReporteDispararDescargaBlob(blob, filename) {
    var url = window.URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = filename || 'ordenes_compra';
    a.style.display = 'none';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.setTimeout(function () {
        window.URL.revokeObjectURL(url);
    }, 1500);
}

function ocReporteDescargarExportacion(href) {
    var lower = String(href).toLowerCase();
    var formato = 'archivo';
    var fallback = 'ordenes_compra';
    if (lower.indexOf('/excel') !== -1) {
        formato = 'Excel';
        fallback += '.xlsx';
    } else if (lower.indexOf('/pdf') !== -1) {
        formato = 'PDF';
        fallback += '.pdf';
    } else if (lower.indexOf('/csv') !== -1) {
        formato = 'CSV';
        fallback += '.csv';
    }

    ocReporteMostrarOverlay(
        'Exportando…',
        'Generando ' + formato + '… Puede demorar según el volumen. Pulse Esc para cerrar este aviso.'
    );

    if (window.__ocReporteExportAbort) {
        try {
            window.__ocReporteExportAbort.abort();
        } catch (e) {}
    }
    var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
    window.__ocReporteExportAbort = controller;

    if (window.__ocReporteExportSafetyTimer) {
        clearTimeout(window.__ocReporteExportSafetyTimer);
    }
    window.__ocReporteExportSafetyTimer = setTimeout(ocReporteOcultarOverlay, 600000);

    fetch(href, {
        method: 'GET',
        credentials: 'same-origin',
        signal: controller ? controller.signal : undefined,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': '*/*',
        },
    }).then(function (res) {
        if (res.status === 419) {
            throw new Error('Sesión expirada. Recargue la página (F5) e intente de nuevo.');
        }
        if (res.redirected && res.url && res.url.indexOf('listar-ordencompra-reporte') === -1) {
            throw new Error('No se pudo generar la exportación. Verifique los filtros y vuelva a consultar.');
        }
        if (!res.ok) {
            throw new Error('Error HTTP ' + res.status + ' al exportar.');
        }
        var filename = ocReporteNombreArchivoDesdeDisposition(
            res.headers.get('Content-Disposition'),
            fallback
        );
        return res.blob().then(function (blob) {
            return { blob: blob, filename: filename };
        });
    }).then(function (pack) {
        if (!pack || !pack.blob || pack.blob.size === 0) {
            throw new Error('La exportación vino vacía. Reintente.');
        }
        if (pack.blob.type && pack.blob.type.indexOf('text/html') !== -1) {
            throw new Error('La sesión o el permiso fallaron al exportar. Recargue e intente de nuevo.');
        }
        ocReporteDispararDescargaBlob(pack.blob, pack.filename);
        ocReporteOcultarOverlay();
    }).catch(function (err) {
        if (err && err.name === 'AbortError') {
            ocReporteOcultarOverlay();
            return;
        }
        ocReporteOcultarOverlay();
        window.alert(err && err.message ? err.message : 'No se pudo descargar la exportación.');
    }).finally(function () {
        if (window.__ocReporteExportSafetyTimer) {
            clearTimeout(window.__ocReporteExportSafetyTimer);
            window.__ocReporteExportSafetyTimer = null;
        }
        window.__ocReporteExportAbort = null;
    });
}

function ocReporteAbrirModalUsuario() {
    var valor = $('#oc-reporte-usuario-campo .codigousuario').val().trim();
    ocReporteBuscarUsuariosModal(valor);
    $('#consultausuarioModal').modal('show');
}

function ocReporteAbrirModalCentrocosto() {
    var valor = $('#oc-reporte-centrocosto-campo .codigocentrocosto').val().trim();
    ocReporteBuscarCentrocostosModal(valor);
    $('#consultacentrocostoModal').modal('show');
}

function ocReporteAbrirModalProveedor() {
    var valor = $('#oc-reporte-proveedor-campo .codigoproveedor').val().trim();
    ocReporteBuscarProveedoresModal(valor);
    $('#consultaproveedorModal').modal('show');
}

function ocReporteExpandirRangoOrdencompra($campo) {
    var $desde = $campo.find('.codigonumero-desde');
    var $hasta = $campo.find('.codigonumero-hasta');
    var desde = ocReporteNormalizarValor($desde.val());

    if (desde.indexOf('/') < 0) {
        return;
    }

    var partes = desde.split('/');
    var valorDesde = ocReporteNormalizarValor(partes[0]);
    var valorHasta = ocReporteNormalizarValor(partes[1] || '');

    if (valorDesde !== '') {
        $desde.val(valorDesde);
    }

    if (valorHasta !== '') {
        $hasta.val(valorHasta);
    }
}

function ocReporteResolverCentrocostos($campo) {
    var valor = ocReporteNormalizarValor($campo.find('.codigocentrocosto').val());
    var $meta = $campo.find('.metacentrocosto');

    if (valor === '') {
        $meta.val('Todos los centros de costo');
        return;
    }

    if (valor.indexOf(',') >= 0 || valor.indexOf(';') >= 0) {
        var codigos = valor.split(/[,;]+/).map(function (s) { return s.trim(); }).filter(Boolean);
        $meta.val(codigos.length > 1 ? 'Lista CC (' + codigos.length + '): ' + codigos.join(', ') : 'Lista CC');
        return;
    }

    $.getJSON(carpetaBase + '/contable/centrocosto/resolvercentrocosto', { valor: valor })
        .done(function (data) {
            if (data && data.ok) {
                $campo.find('.codigocentrocosto').val(String(data.codigo));
                $meta.val((data.codigo || '') + ' — ' + (data.nombre || ''));
            } else {
                $meta.val('');
            }
        })
        .fail(function () {
            $meta.val('');
        });
}

function ocReporteAgregarCentrocosto(codigo) {
    var $campo = $('#oc-reporte-centrocosto-campo');
    var $inp = $campo.find('.codigocentrocosto');
    var actual = ocReporteNormalizarValor($inp.val());
    var codigos = actual === '' ? [] : actual.split(/[,;]+/).map(function (s) { return s.trim(); }).filter(Boolean);
    var codigoStr = String(codigo).trim();

    if (codigoStr !== '' && codigos.indexOf(codigoStr) < 0) {
        codigos.push(codigoStr);
    }

    $inp.val(codigos.join(','));
    ocReporteResolverCentrocostos($campo);
}

function ocReporteBuscarCentrocostosModal(consulta) {
    $.ajax({
        url: carpetaBase + '/contable/centrocosto/consultacentrocosto',
        type: 'POST',
        dataType: 'HTML',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
        },
        data: { consulta: consulta },
    })
        .done(function (respuesta) {
            var html = '';
            try {
                html = JSON.parse(respuesta).data || '';
            } catch (e) {
                html = respuesta;
            }
            $('#datoscentrocosto').html(html);
        })
        .fail(function () {
            $('#datoscentrocosto').html('<tr><td colspan="5">Error al consultar centros de costo</td></tr>');
        });
}

function ocReporteResolverUsuarios($campo) {
    var valor = ocReporteNormalizarValor($campo.find('.codigousuario').val());
    var $meta = $campo.find('.metausuario');

    if (valor === '') {
        $meta.val('Todos los usuarios');
        return;
    }

    if (valor.indexOf(',') >= 0 || valor.indexOf(';') >= 0) {
        var ids = valor.split(/[,;]+/).map(function (s) { return s.trim(); }).filter(Boolean);
        $meta.val(ids.length > 1 ? 'Lista de usuarios (' + ids.length + ')' : 'Lista de usuarios');
        return;
    }

    if (valor.indexOf('/') >= 0) {
        var partes = valor.split('/');
        $meta.val('Rango ' + ocReporteNormalizarValor(partes[0]) + ' al ' + ocReporteNormalizarValor(partes[1] || ''));
        return;
    }

    $.getJSON(carpetaBase + '/configuracion/resolverusuario', { valor: valor })
        .done(function (data) {
            if (data && data.ok) {
                $campo.find('.codigousuario').val(String(data.id));
                $meta.val(data.nombre || '');
            } else {
                $meta.val('');
            }
        })
        .fail(function () {
            $meta.val('');
        });
}

function ocReporteAgregarUsuario(id) {
    var $campo = $('#oc-reporte-usuario-campo');
    var $inp = $campo.find('.codigousuario');
    var actual = ocReporteNormalizarValor($inp.val());
    var ids = actual === '' ? [] : actual.split(/[,;]+/).map(function (s) { return s.trim(); }).filter(Boolean);
    var idStr = String(id);

    if (ids.indexOf(idStr) < 0) {
        ids.push(idStr);
    }

    $inp.val(ids.join(','));
    ocReporteResolverUsuarios($campo);
}

function ocReporteBuscarUsuariosModal(consulta) {
    $.ajax({
        url: carpetaBase + '/configuracion/consultausuario',
        type: 'POST',
        dataType: 'HTML',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
        },
        data: { consulta: consulta },
    })
        .done(function (respuesta) {
            var html = '';
            try {
                html = JSON.parse(respuesta).data || '';
            } catch (e) {
                html = respuesta;
            }
            $('#datosusuario').html(html);
        })
        .fail(function () {
            $('#datosusuario').html('<tr><td colspan="4">Error al consultar usuarios</td></tr>');
        });
}

function ocReporteResolverProveedores($campo) {
    var valor = ocReporteNormalizarValor($campo.find('.codigoproveedor').val());
    var $meta = $campo.find('.metaproveedor');

    if (valor === '') {
        $meta.val('Todos los proveedores');
        return;
    }

    if (valor.indexOf(',') >= 0 || valor.indexOf(';') >= 0) {
        var codigos = valor.split(/[,;]+/).map(function (s) { return s.trim(); }).filter(Boolean);
        $meta.val(codigos.length > 1 ? 'Lista proveedores (' + codigos.length + '): ' + codigos.join(', ') : 'Lista proveedores');
        return;
    }

    $meta.val(valor);
}

function ocReporteAgregarProveedor(codigo) {
    var $campo = $('#oc-reporte-proveedor-campo');
    var $inp = $campo.find('.codigoproveedor');
    var actual = ocReporteNormalizarValor($inp.val());
    var codigos = actual === '' ? [] : actual.split(/[,;]+/).map(function (s) { return s.trim(); }).filter(Boolean);
    var codigoStr = String(codigo).trim();

    if (codigoStr !== '' && codigos.indexOf(codigoStr) < 0) {
        codigos.push(codigoStr);
    }

    $inp.val(codigos.join(','));
    ocReporteResolverProveedores($campo);
}

function ocReporteBuscarProveedoresModal(consulta) {
    $.ajax({
        url: carpetaBase + '/compras/proveedor/consultaproveedor',
        type: 'POST',
        dataType: 'HTML',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
        },
        data: { consulta: consulta },
    })
        .done(function (respuesta) {
            var html = '';
            try {
                html = JSON.parse(respuesta).data || '';
            } catch (e) {
                html = respuesta;
            }
            $('#datosproveedor').html(html);
        })
        .fail(function () {
            $('#datosproveedor').html('<tr><td colspan="6">Error al consultar proveedores</td></tr>');
        });
}

function ocReporteToggleGrupo(grupoId, colapsar) {
    var $detalle = $('.oc-reporte-grupo-' + grupoId);
    var $cabecera = $('.oc-reporte-grupo-cabecera[data-grupo-id="' + grupoId + '"]');

    if (colapsar === undefined) {
        colapsar = !$cabecera.hasClass('oc-reporte-colapsado');
    }

    if (colapsar) {
        $cabecera.addClass('oc-reporte-colapsado');
        $detalle.addClass('oc-reporte-colapsado');
        $cabecera.find('.oc-reporte-grupo-icon')
            .removeClass('fa-chevron-down')
            .addClass('fa-chevron-right');
    } else {
        $cabecera.removeClass('oc-reporte-colapsado');
        $detalle.removeClass('oc-reporte-colapsado');
        $cabecera.find('.oc-reporte-grupo-icon')
            .removeClass('fa-chevron-right')
            .addClass('fa-chevron-down');
    }
}

function ocReporteToggleTodosGrupos() {
    var $cabeceras = $('#tabla-ordencompra-reporte .oc-reporte-grupo-cabecera');
    if (!$cabeceras.length) {
        return;
    }

    var algunoExpandido = $cabeceras.filter(':not(.oc-reporte-colapsado)').length > 0;
    $cabeceras.each(function () {
        ocReporteToggleGrupo($(this).data('grupo-id'), algunoExpandido);
    });
}

function activaEventosOrdencompraReporteFiltro() {
    var $form = $('#form-ordencompra-reporte');
    if (!$form.length) {
        return;
    }

    ocReporteExpandirRangoOrdencompra($('#oc-reporte-ordencompra-campo'));
    ocReporteResolverUsuarios($('#oc-reporte-usuario-campo'));
    ocReporteResolverCentrocostos($('#oc-reporte-centrocosto-campo'));
    ocReporteResolverProveedores($('#oc-reporte-proveedor-campo'));

    $form.on('submit', function () {
        if (!this.checkValidity()) {
            return;
        }
        ocReporteMostrarOverlay(
            'Consultando pedidos…',
            'Puede demorar según el período y el volumen. No cierre la página.'
        );
    });

    // Descarga sin navegación: fetch+blob y ocultar el aviso al terminar.
    $(document)
        .off('click.ocreporte', 'a[href*="listar-ordencompra-reporte"]')
        .on('click.ocreporte', 'a[href*="listar-ordencompra-reporte"]', function (e) {
            var href = $(this).attr('href') || '';
            if (!href || href === '#') {
                return;
            }
            e.preventDefault();
            ocReporteDescargarExportacion(href);
        });

    window.addEventListener('pageshow', ocReporteOcultarOverlay);
    window.addEventListener('pagehide', ocReporteOcultarOverlay);
    $(document)
        .off('keydown.ocreporte-overlay')
        .on('keydown.ocreporte-overlay', function (e) {
            if (e.key === 'Escape' || e.keyCode === 27) {
                if (window.__ocReporteExportAbort) {
                    try {
                        window.__ocReporteExportAbort.abort();
                    } catch (err) {}
                }
                ocReporteOcultarOverlay();
            }
        });

    $form.on('keydown', 'input:not([type="submit"])', function (e) {
        if (e.which === 13) {
            e.preventDefault();
            var $campoOc = $(this).closest('#oc-reporte-ordencompra-campo');
            if ($campoOc.length) {
                ocReporteExpandirRangoOrdencompra($campoOc);
                return false;
            }
            var $campoUsr = $(this).closest('#oc-reporte-usuario-campo');
            if ($campoUsr.length) {
                ocReporteResolverUsuarios($campoUsr);
                return false;
            }
            var $campoCc = $(this).closest('#oc-reporte-centrocosto-campo');
            if ($campoCc.length) {
                ocReporteResolverCentrocostos($campoCc);
                return false;
            }
            var $campoProv = $(this).closest('#oc-reporte-proveedor-campo');
            if ($campoProv.length) {
                ocReporteResolverProveedores($campoProv);
                return false;
            }
        }
    });

    $(document)
        .off('change.ocreporte', '#oc-reporte-ordencompra-campo .codigonumero-desde')
        .on('change.ocreporte', '#oc-reporte-ordencompra-campo .codigonumero-desde', function () {
            ocReporteExpandirRangoOrdencompra($('#oc-reporte-ordencompra-campo'));
        });

    $(document)
        .off('blur.ocreporte', '#oc-reporte-ordencompra-campo .codigonumero-desde')
        .on('blur.ocreporte', '#oc-reporte-ordencompra-campo .codigonumero-desde', function () {
            ocReporteExpandirRangoOrdencompra($('#oc-reporte-ordencompra-campo'));
        });

    $(document)
        .off('change.ocreporte', '#oc-reporte-usuario-campo .codigousuario')
        .on('change.ocreporte', '#oc-reporte-usuario-campo .codigousuario', function () {
            ocReporteResolverUsuarios($('#oc-reporte-usuario-campo'));
        });

    $(document)
        .off('change.ocreporte blur.ocreporte', '#oc-reporte-centrocosto-campo .codigocentrocosto')
        .on('change.ocreporte blur.ocreporte', '#oc-reporte-centrocosto-campo .codigocentrocosto', function () {
            ocReporteResolverCentrocostos($('#oc-reporte-centrocosto-campo'));
        });

    $(document)
        .off('change.ocreporte', '#oc-reporte-proveedor-campo .codigoproveedor')
        .on('change.ocreporte', '#oc-reporte-proveedor-campo .codigoproveedor', function () {
            ocReporteResolverProveedores($('#oc-reporte-proveedor-campo'));
        });

    $(document)
        .off('click.ocreporte', '#oc-reporte-centrocosto-campo .consultacentrocosto-oc')
        .on('click.ocreporte', '#oc-reporte-centrocosto-campo .consultacentrocosto-oc', function (e) {
            e.preventDefault();
            ocReporteAbrirModalCentrocosto();
        });

    $('#consultacentrocostoModal')
        .off('shown.bs.modal.ocreporte')
        .on('shown.bs.modal.ocreporte', function () {
            if (!$('#form-ordencompra-reporte').length) {
                return;
            }
            var valor = $('#oc-reporte-centrocosto-campo .codigocentrocosto').val().trim();
            $('#consultacentrocosto').val(valor);
            ocReporteBuscarCentrocostosModal(valor);
            $(this).find('#consultacentrocosto').focus();
        });

    $(document)
        .off('keyup.ocreporte', '#consultacentrocosto')
        .on('keyup.ocreporte', '#consultacentrocosto', function () {
            if (!$('#form-ordencompra-reporte').length) {
                return;
            }
            ocReporteBuscarCentrocostosModal($(this).val().trim());
        });

    $(document)
        .off('click.ocreporte', '.eligeconsultacentrocosto')
        .on('click.ocreporte', '.eligeconsultacentrocosto', function (e) {
            if (!$('#form-ordencompra-reporte').length) {
                return;
            }
            e.stopImmediatePropagation();

            var $trModal = $(this).closest('tr');
            var codigo = $trModal.find('.codigo').first().text().trim();

            if (codigo !== '') {
                ocReporteAgregarCentrocosto(codigo);
            }

            $('#consultacentrocostoModal').modal('hide');
            return false;
        });

    $(document)
        .off('click.ocreporte', '#oc-reporte-usuario-campo .consultausuario-oc')
        .on('click.ocreporte', '#oc-reporte-usuario-campo .consultausuario-oc', function (e) {
            e.preventDefault();
            ocReporteAbrirModalUsuario();
        });

    $('#consultausuarioModal')
        .off('shown.bs.modal.ocreporte')
        .on('shown.bs.modal.ocreporte', function () {
            if (!$('#form-ordencompra-reporte').length) {
                return;
            }
            var valor = $('#oc-reporte-usuario-campo .codigousuario').val().trim();
            $('#consultausuario').val(valor);
            ocReporteBuscarUsuariosModal(valor);
            $(this).find('[autofocus]').focus();
        });

    $(document)
        .off('keyup.ocreporte', '#consultausuario')
        .on('keyup.ocreporte', '#consultausuario', function () {
            if (!$('#form-ordencompra-reporte').length) {
                return;
            }
            ocReporteBuscarUsuariosModal($(this).val().trim());
        });

    $(document)
        .off('click.ocreporte', '.eligeconsultausuario')
        .on('click.ocreporte', '.eligeconsultausuario', function (e) {
            if (!$('#form-ordencompra-reporte').length) {
                return;
            }
            e.stopImmediatePropagation();

            var $trModal = $(this).closest('tr');
            var id = $trModal.find('.id').first().text().trim();

            if (id !== '') {
                ocReporteAgregarUsuario(id);
            }

            $('#consultausuarioModal').modal('hide');
            return false;
        });

    $(document)
        .off('click.ocreporte', '#oc-reporte-proveedor-campo .consultaproveedor-oc')
        .on('click.ocreporte', '#oc-reporte-proveedor-campo .consultaproveedor-oc', function (e) {
            e.preventDefault();
            ocReporteAbrirModalProveedor();
        });

    $('#consultaproveedorModal')
        .off('shown.bs.modal.ocreporte')
        .on('shown.bs.modal.ocreporte', function () {
            if (!$('#form-ordencompra-reporte').length) {
                return;
            }
            var valor = $('#oc-reporte-proveedor-campo .codigoproveedor').val().trim();
            $('#consultaproveedor').val(valor);
            ocReporteBuscarProveedoresModal(valor);
            $(this).find('#consultaproveedor').focus();
        });

    $(document)
        .off('keyup.ocreporte', '#consultaproveedor')
        .on('keyup.ocreporte', '#consultaproveedor', function () {
            if (!$('#form-ordencompra-reporte').length) {
                return;
            }
            ocReporteBuscarProveedoresModal($(this).val().trim());
        });

    $(document)
        .off('click.ocreporte', '.eligeconsultaproveedor')
        .on('click.ocreporte', '.eligeconsultaproveedor', function (e) {
            if (!$('#form-ordencompra-reporte').length) {
                return;
            }
            e.stopImmediatePropagation();

            var $trModal = $(this).closest('tr');
            var codigo = $trModal.find('.codigo').first().text().trim();

            if (codigo !== '') {
                ocReporteAgregarProveedor(codigo);
            }

            $('#consultaproveedorModal').modal('hide');
            return false;
        });

    $(document)
        .off('click.ocreporte', '#tabla-ordencompra-reporte .oc-reporte-grupo-cabecera')
        .on('click.ocreporte', '#tabla-ordencompra-reporte .oc-reporte-grupo-cabecera', function () {
            ocReporteToggleGrupo($(this).data('grupo-id'));
        });

    $(document)
        .off('click.ocreporte', '#oc-reporte-toggle-grupos')
        .on('click.ocreporte', '#oc-reporte-toggle-grupos', function () {
            ocReporteToggleTodosGrupos();
        });

    document.removeEventListener('keydown', ocReporteAtajoF1Handler, true);
    document.addEventListener('keydown', ocReporteAtajoF1Handler, true);
}

function ocReporteAtajoF1Handler(e) {
    if (!ocReporteEsTeclaF1(e) || !ocReporteEsPantallaActiva()) {
        return;
    }

    var target = e.target;
    if (!target || !target.closest('#form-ordencompra-reporte')) {
        return;
    }
    if (target.readOnly || target.disabled) {
        return;
    }

    if (target.classList.contains('codigousuario')) {
        if (ocReporteModalAbierto('#consultausuarioModal')) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        ocReporteAbrirModalUsuario();
        return;
    }

    if (target.classList.contains('codigocentrocosto')) {
        if (ocReporteModalAbierto('#consultacentrocostoModal')) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        ocReporteAbrirModalCentrocosto();
        return;
    }

    if (target.classList.contains('codigoproveedor')) {
        if (ocReporteModalAbierto('#consultaproveedorModal')) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        ocReporteAbrirModalProveedor();
    }
}

$(function () {
    activaEventosOrdencompraReporteFiltro();
});
