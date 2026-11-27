/* Tablero de compras: filtros (comprador, proveedor, período), overlay y exportación. */

function tcNormalizar(valor) {
    return String(valor || '').trim();
}

function tcEsF1(e) {
    return e.key === 'F1' || e.code === 'F1' || e.keyCode === 112;
}

function tcModalAbierto(selector) {
    var $m = $(selector);
    return $m.length && $m.hasClass('show');
}

function tcMostrarOverlay(titulo, subtitulo) {
    var overlay = document.getElementById('tablero-compras-overlay');
    if (!overlay) {
        return;
    }
    if (titulo) {
        var t = document.getElementById('tablero-compras-overlay-titulo');
        if (t) {
            t.textContent = titulo;
        }
    }
    if (subtitulo) {
        var s = document.getElementById('tablero-compras-overlay-subtitulo');
        if (s) {
            s.textContent = subtitulo;
        }
    }
    overlay.classList.remove('d-none');
    overlay.style.display = 'flex';
    overlay.setAttribute('aria-hidden', 'false');
}

function tcOcultarOverlay() {
    var overlay = document.getElementById('tablero-compras-overlay');
    if (!overlay) {
        return;
    }
    if (window.__tcExportSafetyTimer) {
        clearTimeout(window.__tcExportSafetyTimer);
        window.__tcExportSafetyTimer = null;
    }
    overlay.classList.add('d-none');
    overlay.style.display = '';
    overlay.setAttribute('aria-hidden', 'true');
}

function tcNombreDesdeDisposition(disposition, fallback) {
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

function tcDispararDescarga(blob, filename) {
    var url = window.URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = filename || 'tablero_compras';
    a.style.display = 'none';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.setTimeout(function () {
        window.URL.revokeObjectURL(url);
    }, 1500);
}

function tcDescargarExportacion(href) {
    var lower = String(href).toLowerCase();
    var formato = 'archivo';
    var fallback = 'tablero_compras';
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

    tcMostrarOverlay(
        'Exportando…',
        'Generando ' + formato + '… Puede demorar según el volumen. Pulse Esc para cerrar este aviso.'
    );

    if (window.__tcExportAbort) {
        try {
            window.__tcExportAbort.abort();
        } catch (e) {}
    }
    var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
    window.__tcExportAbort = controller;

    if (window.__tcExportSafetyTimer) {
        clearTimeout(window.__tcExportSafetyTimer);
    }
    window.__tcExportSafetyTimer = setTimeout(tcOcultarOverlay, 600000);

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
        if (res.redirected && res.url && res.url.indexOf('listar-tablero-compras') === -1) {
            throw new Error('No se pudo generar la exportación. Verifique los filtros y vuelva a consultar.');
        }
        // PDF con muchas líneas: el servidor lo manda a segundo plano y avisa por JSON.
        var tipo = res.headers.get('Content-Type') || '';
        if (tipo.indexOf('application/json') !== -1) {
            return res.json().then(function (datos) {
                return { aviso: datos };
            });
        }
        if (!res.ok) {
            throw new Error('Error HTTP ' + res.status + ' al exportar.');
        }
        var filename = tcNombreDesdeDisposition(res.headers.get('Content-Disposition'), fallback);
        return res.blob().then(function (blob) {
            return { blob: blob, filename: filename };
        });
    }).then(function (pack) {
        if (pack && pack.aviso) {
            tcOcultarOverlay();
            window.alert(pack.aviso.mensaje || 'El PDF se generará en segundo plano y te llegará por mail.');
            return;
        }
        if (!pack || !pack.blob || pack.blob.size === 0) {
            throw new Error('La exportación vino vacía. Reintente.');
        }
        if (pack.blob.type && pack.blob.type.indexOf('text/html') !== -1) {
            throw new Error('La sesión o el permiso fallaron al exportar. Recargue e intente de nuevo.');
        }
        tcDispararDescarga(pack.blob, pack.filename);
        tcOcultarOverlay();
    }).catch(function (err) {
        tcOcultarOverlay();
        if (err && err.name === 'AbortError') {
            return;
        }
        window.alert(err && err.message ? err.message : 'No se pudo descargar la exportación.');
    }).finally(function () {
        if (window.__tcExportSafetyTimer) {
            clearTimeout(window.__tcExportSafetyTimer);
            window.__tcExportSafetyTimer = null;
        }
        window.__tcExportAbort = null;
    });
}

/* ---- Lista de ids / códigos (comprador y proveedor) ---- */

function tcLista(valor) {
    return tcNormalizar(valor).split(/[,;]+/).map(function (s) { return s.trim(); }).filter(Boolean);
}

function tcResolverUsuarios() {
    var $campo = $('#tablero-usuario-campo');
    var valor = tcNormalizar($campo.find('.codigousuario').val());
    var $meta = $campo.find('.metausuario');

    if (valor === '') {
        $meta.val('Todos los compradores');
        return;
    }
    if (valor.indexOf(',') >= 0 || valor.indexOf(';') >= 0) {
        var ids = tcLista(valor);
        $meta.val(ids.length > 1 ? 'Lista de compradores (' + ids.length + ')' : 'Lista de compradores');
        return;
    }
    if (valor.indexOf('/') >= 0) {
        var partes = valor.split('/');
        $meta.val('Rango ' + tcNormalizar(partes[0]) + ' al ' + tcNormalizar(partes[1] || ''));
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

function tcAgregarUsuario(id) {
    var $inp = $('#tablero-usuario-campo .codigousuario');
    var ids = tcLista($inp.val());
    var idStr = String(id);
    if (ids.indexOf(idStr) < 0) {
        ids.push(idStr);
    }
    $inp.val(ids.join(','));
    tcResolverUsuarios();
}

function tcResolverProveedores() {
    var $campo = $('#tablero-proveedor-campo');
    var valor = tcNormalizar($campo.find('.tablero-codigo-proveedor').val());
    var $meta = $campo.find('.metaproveedor');

    if (valor === '') {
        $meta.val('Todos los proveedores');
        return;
    }
    var codigos = tcLista(valor);
    $meta.val(codigos.length > 1 ? 'Lista proveedores (' + codigos.length + '): ' + codigos.join(', ') : valor);
}

function tcAgregarProveedor(codigo, nombre) {
    var $campo = $('#tablero-proveedor-campo');
    var $inp = $campo.find('.tablero-codigo-proveedor');
    var codigos = tcLista($inp.val());
    var codigoStr = String(codigo).trim();
    var nombreStr = String(nombre || '').trim();

    if (codigoStr !== '' && codigos.indexOf(codigoStr) < 0) {
        codigos.push(codigoStr);
    }
    $inp.val(codigos.join(','));
    if (codigos.length === 1 && nombreStr !== '') {
        $campo.find('.metaproveedor').val(codigoStr + ' — ' + nombreStr);
        return;
    }
    tcResolverProveedores();
}

// La consulta general de proveedores escribe en #codigoproveedor. Este filtro es una
// lista de códigos: al elegir, queda acá y no dispara esa carga.
window.onProveedorElegidoEnConsulta = function (fila) {
    if (!document.getElementById('form-tablero-compras')) {
        return false;
    }
    var codigo = fila && fila.codigo ? String(fila.codigo).trim() : '';
    if (codigo !== '') {
        tcAgregarProveedor(codigo, fila.nombre || '');
    }
    $('#consultaproveedorModal').modal('hide');
    return true;
};

function tcCargarModal(url, consulta, destino, colspan, mensajeError) {
    $.ajax({
        url: carpetaBase + url,
        type: 'POST',
        dataType: 'HTML',
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        data: { consulta: consulta },
    })
        .done(function (respuesta) {
            var html = '';
            try {
                html = JSON.parse(respuesta).data || '';
            } catch (e) {
                html = respuesta;
            }
            $(destino).html(html);
        })
        .fail(function () {
            $(destino).html('<tr><td colspan="' + colspan + '">' + mensajeError + '</td></tr>');
        });
}

function tcAbrirModalUsuario() {
    tcCargarModal('/configuracion/consultausuario', tcNormalizar($('#tablero-usuario-campo .codigousuario').val()), '#datosusuario', 4, 'Error al consultar usuarios');
    $('#consultausuarioModal').modal('show');
}

function tcAbrirModalProveedor() {
    tcCargarModal('/compras/proveedor/consultaproveedor', tcNormalizar($('#tablero-proveedor-campo .tablero-codigo-proveedor').val()), '#datosproveedor', 6, 'Error al consultar proveedores');
    $('#consultaproveedorModal').modal('show');
}

function tcTextoCelda($tr, selector) {
    var $td = $tr.find(selector).first();
    if (!$td.length) {
        return '';
    }
    return $.trim($td.clone().children().remove().end().text());
}

/* ---- Período: el día solo existe si hay mes elegido ---- */

function tcActualizarDias() {
    var mes = parseInt($('#mes').val(), 10) || 0;
    var anio = parseInt($('#anio').val(), 10) || new Date().getFullYear();
    var $dia = $('#dia');
    if (!$dia.length) {
        return;
    }
    if (mes === 0) {
        $dia.val('0').prop('disabled', true);
        return;
    }
    var maximo = new Date(anio, mes, 0).getDate();
    $dia.prop('disabled', false);
    $dia.find('option').each(function () {
        var n = parseInt($(this).val(), 10);
        $(this).prop('disabled', n > maximo);
    });
    if (parseInt($dia.val(), 10) > maximo) {
        $dia.val('0');
    }
}

function tcAtajoF1(e) {
    if (!tcEsF1(e)) {
        return;
    }
    var target = e.target;
    if (!target || !target.closest || !target.closest('#form-tablero-compras')) {
        return;
    }
    if (target.readOnly || target.disabled) {
        return;
    }
    if (target.classList.contains('codigousuario')) {
        if (tcModalAbierto('#consultausuarioModal')) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        tcAbrirModalUsuario();
        return;
    }
    if (target.classList.contains('tablero-codigo-proveedor')) {
        if (tcModalAbierto('#consultaproveedorModal')) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        tcAbrirModalProveedor();
    }
}

function activaEventosTableroCompras() {
    var $form = $('#form-tablero-compras');
    if (!$form.length) {
        return;
    }

    tcResolverUsuarios();
    tcResolverProveedores();
    tcActualizarDias();

    $('#mes, #anio').on('change', tcActualizarDias);

    $form.on('submit', function () {
        if (!this.checkValidity()) {
            return;
        }
        tcMostrarOverlay('Armando el tablero…', 'Puede demorar según el período y las empresas elegidas. No cierre la página.');
    });

    // Buscar otro artículo descarta el artículo elegido antes.
    $('#buscar_articulo').on('input', function () {
        $('#tablero-articulo-id').val('0');
    });

    // La solapa activa viaja en la consulta: se vuelve a la misma al recargar.
    $('#tablero-tabs a[data-toggle="tab"]').on('shown.bs.tab', function () {
        $('#tablero-tab').val($(this).data('tab'));
    });

    $(document)
        .off('click.tablero', 'a[href*="listar-tablero-compras"]')
        .on('click.tablero', 'a[href*="listar-tablero-compras"]', function (e) {
            var href = $(this).attr('href') || '';
            if (!href || href === '#') {
                return;
            }
            e.preventDefault();
            tcDescargarExportacion(href);
        });

    window.addEventListener('pageshow', tcOcultarOverlay);
    window.addEventListener('pagehide', tcOcultarOverlay);
    $(document)
        .off('keydown.tablero-overlay')
        .on('keydown.tablero-overlay', function (e) {
            if (e.key === 'Escape' || e.keyCode === 27) {
                if (window.__tcExportAbort) {
                    try {
                        window.__tcExportAbort.abort();
                    } catch (err) {}
                }
                tcOcultarOverlay();
            }
        });

    // Enter en comprador/proveedor resuelve el campo; no envía el formulario.
    $form.on('keydown', '.codigousuario, .tablero-codigo-proveedor', function (e) {
        if (e.which === 13) {
            e.preventDefault();
            if ($(this).hasClass('codigousuario')) {
                tcResolverUsuarios();
            } else {
                tcResolverProveedores();
            }
            return false;
        }
    });

    $(document)
        .off('change.tablero', '#tablero-usuario-campo .codigousuario')
        .on('change.tablero', '#tablero-usuario-campo .codigousuario', tcResolverUsuarios);
    $(document)
        .off('change.tablero', '#tablero-proveedor-campo .tablero-codigo-proveedor')
        .on('change.tablero', '#tablero-proveedor-campo .tablero-codigo-proveedor', tcResolverProveedores);

    // Comprador
    $(document)
        .off('click.tablero', '#tablero-usuario-campo .consultausuario-tablero')
        .on('click.tablero', '#tablero-usuario-campo .consultausuario-tablero', function (e) {
            e.preventDefault();
            tcAbrirModalUsuario();
        });

    $('#consultausuarioModal')
        .off('shown.bs.modal.tablero')
        .on('shown.bs.modal.tablero', function () {
            if (!$('#form-tablero-compras').length) {
                return;
            }
            var valor = tcNormalizar($('#tablero-usuario-campo .codigousuario').val());
            $('#consultausuario').val(valor);
            tcCargarModal('/configuracion/consultausuario', valor, '#datosusuario', 4, 'Error al consultar usuarios');
            $(this).find('[autofocus]').focus();
        });

    $(document)
        .off('keyup.tablero', '#consultausuario')
        .on('keyup.tablero', '#consultausuario', function () {
            tcCargarModal('/configuracion/consultausuario', tcNormalizar($(this).val()), '#datosusuario', 4, 'Error al consultar usuarios');
        });

    $(document)
        .off('click.tablero', '.eligeconsultausuario')
        .on('click.tablero', '.eligeconsultausuario', function (e) {
            e.stopImmediatePropagation();
            var id = $(this).closest('tr').find('.id').first().text().trim();
            if (id !== '') {
                tcAgregarUsuario(id);
            }
            $('#consultausuarioModal').modal('hide');
            return false;
        });

    // Proveedor
    $(document)
        .off('click.tablero', '#tablero-proveedor-campo .consultaproveedor-tablero')
        .on('click.tablero', '#tablero-proveedor-campo .consultaproveedor-tablero', function (e) {
            e.preventDefault();
            tcAbrirModalProveedor();
        });

    $('#consultaproveedorModal')
        .off('shown.bs.modal.tablero')
        .on('shown.bs.modal.tablero', function () {
            if (!$('#form-tablero-compras').length) {
                return;
            }
            var valor = tcNormalizar($('#tablero-proveedor-campo .tablero-codigo-proveedor').val());
            $('#consultaproveedor').val(valor);
            tcCargarModal('/compras/proveedor/consultaproveedor', valor, '#datosproveedor', 6, 'Error al consultar proveedores');
            $(this).find('#consultaproveedor').focus();
        });

    $(document)
        .off('keyup.tablero', '#consultaproveedor')
        .on('keyup.tablero', '#consultaproveedor', function () {
            tcCargarModal('/compras/proveedor/consultaproveedor', tcNormalizar($(this).val()), '#datosproveedor', 6, 'Error al consultar proveedores');
        });

    $(document)
        .off('click.tablero', '.eligeconsultaproveedor')
        .on('click.tablero', '.eligeconsultaproveedor', function (e) {
            e.stopImmediatePropagation();
            var $tr = $(this).closest('tr');
            var codigo = tcTextoCelda($tr, 'td.codigoproveedor, td.codigo');
            var nombre = tcTextoCelda($tr, 'td.nombreproveedor, td.nombre');
            if (codigo !== '') {
                tcAgregarProveedor(codigo, nombre);
            }
            $('#consultaproveedorModal').modal('hide');
            return false;
        });

    document.removeEventListener('keydown', tcAtajoF1, true);
    document.addEventListener('keydown', tcAtajoF1, true);
}

$(function () {
    activaEventosTableroCompras();
});
