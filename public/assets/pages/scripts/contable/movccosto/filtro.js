/**
 * Totales por centro de costo (l-movccosto): filtros de centros / grupos, períodos rápidos,
 * overlay al consultar y export por fetch + blob.
 * La consulta de cuentas (F1 / lupa) la resuelve contable/sumas_saldos/filtro.js;
 * la de centros de costo, contable/centrocosto/consulta.js.
 */
var movccostoCentrosSel = {};
var movccostoGruposSel = {};
var movccostoGrupoCampoActivo = null;

function mccEscape(texto) {
    return $('<div>').text(texto == null ? '' : String(texto)).html();
}

function mccOrdenNatural(a, b) {
    return a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' });
}

/* ---------- Centros de costo (lista) ---------- */

function mccRenderCentros() {
    var html = '';
    Object.keys(movccostoCentrosSel).sort(mccOrdenNatural).forEach(function (codigo) {
        html += '<tr data-codigo="' + mccEscape(codigo) + '">'
            + '<td>' + mccEscape(codigo) + '</td>'
            + '<td>' + mccEscape((movccostoCentrosSel[codigo] || {}).nombre) + '</td>'
            + '<td class="text-center"><button type="button" class="btn btn-outline-danger btn-xs mcc-btn-quitar-cc" title="Quitar">'
            + '<i class="fa fa-times"></i></button></td></tr>';
    });
    $('#mcc-tbody-cc').html(html);
    $('#mcc_centrocostos_codigo').val(Object.keys(movccostoCentrosSel).sort(mccOrdenNatural).join(','));
}

function mccAgregarCentro(codigo, nombre) {
    codigo = String(codigo || '').trim();
    if (!codigo || movccostoCentrosSel[codigo]) {
        return false;
    }
    movccostoCentrosSel[codigo] = { nombre: nombre || '' };
    mccRenderCentros();
    return true;
}

function mccCargarCentrosDesdeDom() {
    movccostoCentrosSel = {};
    $('#mcc-tbody-cc tr').each(function () {
        var codigo = String($(this).attr('data-codigo') || '').trim();
        if (codigo) {
            movccostoCentrosSel[codigo] = { nombre: $(this).find('td').eq(1).text().trim() };
        }
    });
    mccRenderCentros();
}

function mccLimpiarCcPuntual() {
    $('.mcc-cc-puntual').first().find('input').val('');
}

function mccAgregarCcDesdeCampo() {
    var $campo = $('.mcc-cc-puntual').first();
    var codigo = String($campo.find('.codigocentrocosto').val() || '').trim();
    if (!codigo) {
        return;
    }
    var nombre = String($campo.find('.descripcioncentrocosto').val() || '').trim();
    if (nombre) {
        mccAgregarCentro(codigo, nombre);
        mccLimpiarCcPuntual();
        return;
    }
    if (typeof leerCentrocostoPorCodigo === 'function') {
        leerCentrocostoPorCodigo(codigo, $campo.find('.codigocentrocosto').get(0), function (data) {
            if (data && data.codigo) {
                mccAgregarCentro(data.codigo, data.nombre);
                mccLimpiarCcPuntual();
            }
        });
    }
}

/** Consultar sin pulsar Agregar: lo tipeado se suma a la lista. */
function mccAdoptarPendientes() {
    var $cc = $('.mcc-cc-puntual').first();
    var codigoCc = String($cc.find('.codigocentrocosto').val() || '').trim();
    if (codigoCc) {
        mccAgregarCentro(codigoCc, String($cc.find('.descripcioncentrocosto').val() || '').trim());
        mccLimpiarCcPuntual();
    }

    var $gr = $('.mcc-grupo-campo').first();
    var codigoGr = String($gr.find('.codigogrupocc').val() || '').trim();
    if (codigoGr) {
        mccAgregarGrupo(codigoGr, String($gr.find('.descripciongrupocc').val() || '').trim());
        $gr.find('input').val('');
    }
}

/* ---------- Grupos de centros de costo ---------- */

function mccNormalizarGrupo(codigo) {
    codigo = String(codigo || '').trim();
    if (codigo === '') {
        return '';
    }
    return /^\d+$/.test(codigo) ? String(parseInt(codigo, 10)) : codigo;
}

function mccRenderGrupos() {
    var html = '';
    Object.keys(movccostoGruposSel).sort(mccOrdenNatural).forEach(function (codigo) {
        html += '<tr data-codigo="' + mccEscape(codigo) + '">'
            + '<td>' + mccEscape(codigo) + '</td>'
            + '<td>' + mccEscape((movccostoGruposSel[codigo] || {}).nombre) + '</td>'
            + '<td class="text-center"><button type="button" class="btn btn-outline-danger btn-xs mcc-btn-quitar-grupo" title="Quitar">'
            + '<i class="fa fa-times"></i></button></td></tr>';
    });
    $('#mcc-tbody-grupos').html(html);
    $('#mcc_grupos_codigo').val(Object.keys(movccostoGruposSel).sort(mccOrdenNatural).join(','));
}

function mccAgregarGrupo(codigo, nombre) {
    codigo = mccNormalizarGrupo(codigo);
    if (!codigo || movccostoGruposSel[codigo]) {
        return false;
    }
    movccostoGruposSel[codigo] = { nombre: nombre || '' };
    mccRenderGrupos();
    return true;
}

function mccCargarGruposDesdeDom() {
    movccostoGruposSel = {};
    $('#mcc-tbody-grupos tr').each(function () {
        var codigo = String($(this).attr('data-codigo') || '').trim();
        if (codigo) {
            movccostoGruposSel[codigo] = { nombre: $(this).find('td').eq(1).text().trim() };
        }
    });
    mccRenderGrupos();
}

function mccBuscarGrupos(consulta) {
    $.ajax({
        url: carpetaBase + '/contable/centrocosto/consultagrupocentrocosto',
        type: 'POST',
        dataType: 'json',
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        data: { consulta: consulta || '' },
    })
        .done(function (respuesta) {
            var html = '';
            if (respuesta && typeof respuesta === 'object' && respuesta.data !== undefined) {
                html = respuesta.data;
            }
            $('#datosgrupocc').html(html);
        })
        .fail(function () {
            $('#datosgrupocc').html('<tr><td colspan="4">Error al consultar grupos</td></tr>');
        });
}

function mccAbrirModalGrupos($campo) {
    movccostoGrupoCampoActivo = $campo && $campo.length ? $campo : $('.mcc-grupo-campo').first();
    var valor = String(movccostoGrupoCampoActivo.find('.codigogrupocc').val() || '').trim();
    $('#consultagrupocc').val(valor);
    mccBuscarGrupos(valor);
    $('#consultagrupoccModal').modal('show');
}

/** Resuelve el nombre del grupo por código. Con onOk agrega a la lista. */
function mccResolverGrupo($campo, agregar) {
    var $input = $campo.find('.codigogrupocc');
    var codigo = String($input.val() || '').trim();
    if (!codigo) {
        $campo.find('.descripciongrupocc').val('');
        return;
    }
    $.getJSON(carpetaBase + '/contable/centrocosto/resolvergrupocentrocosto', { valor: codigo })
        .done(function (data) {
            if (data && data.ok) {
                $input.val(data.codigo).removeData('mccInvalido');
                $campo.find('.descripciongrupocc').val(data.nombre || '');
                if (agregar) {
                    mccAgregarGrupo(data.codigo, data.nombre);
                    $campo.find('input').val('');
                }
            } else {
                $campo.find('.descripciongrupocc').val('');
                $input.data('mccInvalido', codigo);
                setTimeout(function () {
                    $input.trigger('focus');
                    if ($input.get(0) && typeof $input.get(0).select === 'function') {
                        $input.get(0).select();
                    }
                }, 0);
            }
        });
}

/* ---------- Períodos rápidos ---------- */

function mccFechaIso(d) {
    var m = String(d.getMonth() + 1).padStart(2, '0');
    var dia = String(d.getDate()).padStart(2, '0');
    return d.getFullYear() + '-' + m + '-' + dia;
}

function mccPeriodoRapido(tipo) {
    var hoy = new Date();
    var desde;
    var hasta;
    if (tipo === 'mes_anterior') {
        desde = new Date(hoy.getFullYear(), hoy.getMonth() - 1, 1);
        hasta = new Date(hoy.getFullYear(), hoy.getMonth(), 0);
    } else if (tipo === 'anio') {
        desde = new Date(hoy.getFullYear(), 0, 1);
        hasta = hoy;
    } else {
        desde = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
        hasta = hoy;
    }
    $('#mcc_fecha_desde').val(mccFechaIso(desde));
    $('#mcc_fecha_hasta').val(mccFechaIso(hasta));
}

/* ---------- Overlay y export ---------- */

function mccMostrarOverlay(titulo, subtitulo) {
    var overlay = document.getElementById('movccosto-overlay');
    if (!overlay) {
        return;
    }
    var tit = document.getElementById('movccosto-titulo');
    var sub = document.getElementById('movccosto-subtitulo');
    if (tit && titulo) {
        tit.textContent = titulo;
    }
    if (sub && subtitulo) {
        sub.textContent = subtitulo;
    }
    overlay.classList.remove('d-none');
    overlay.style.display = 'flex';
    overlay.setAttribute('aria-hidden', 'false');
}

function mccOcultarOverlay() {
    var overlay = document.getElementById('movccosto-overlay');
    if (!overlay) {
        return;
    }
    overlay.classList.add('d-none');
    overlay.style.display = '';
    overlay.setAttribute('aria-hidden', 'true');
    var tit = document.getElementById('movccosto-titulo');
    var sub = document.getElementById('movccosto-subtitulo');
    if (tit) {
        tit.textContent = 'Calculando totales…';
    }
    if (sub) {
        sub.textContent = 'Puede demorar según el período y la cantidad de centros. No cierre la página.';
    }
    var btn = document.getElementById('btn-consultar');
    if (btn && btn.disabled) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-search"></i> Consultar';
    }
}

function mccNombreArchivo(disposition, fallback) {
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

function mccDescargarBlob(blob, filename) {
    var url = window.URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = filename || 'totales_centro_costo';
    a.style.display = 'none';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.setTimeout(function () {
        window.URL.revokeObjectURL(url);
    }, 1500);
}

function mccExportar(href) {
    var lower = String(href).toLowerCase();
    var formato = 'archivo';
    var ext = '';
    if (lower.indexOf('/excel') !== -1) {
        formato = 'Excel';
        ext = '.xlsx';
    } else if (lower.indexOf('/pdf') !== -1) {
        formato = 'PDF';
        ext = '.pdf';
    } else if (lower.indexOf('/csv') !== -1) {
        formato = 'CSV';
        ext = '.csv';
    }

    mccMostrarOverlay('Exportando…', 'Generando ' + formato + '… Pulse Esc para cerrar este aviso.');

    if (window.__mccExportAbort) {
        try { window.__mccExportAbort.abort(); } catch (e) { /* ignore */ }
    }
    var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
    window.__mccExportAbort = controller;

    fetch(href, {
        method: 'GET',
        credentials: 'same-origin',
        signal: controller ? controller.signal : undefined,
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': '*/*' },
    }).then(function (res) {
        if (res.status === 419) {
            throw new Error('Sesión expirada. Recargue la página (F5) e intente de nuevo.');
        }
        if (res.redirected && res.url && res.url.indexOf('listar-movimientos-centro-costo') === -1) {
            throw new Error('No se pudo generar la exportación. Verifique los filtros y vuelva a consultar.');
        }
        if (!res.ok) {
            throw new Error('Error HTTP ' + res.status + ' al exportar.');
        }
        var filename = mccNombreArchivo(res.headers.get('Content-Disposition'), 'totales_centro_costo' + ext);
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
        mccDescargarBlob(pack.blob, pack.filename);
        mccOcultarOverlay();
    }).catch(function (err) {
        mccOcultarOverlay();
        if (err && err.name === 'AbortError') {
            return;
        }
        window.alert(err && err.message ? err.message : 'No se pudo descargar la exportación.');
    }).finally(function () {
        window.__mccExportAbort = null;
    });
}

/* ---------- Hook de consulta.js: Enter en el CC puntual agrega a la lista ---------- */

window.afterCentrocostoEnterOk = function (data, inputCodigo) {
    var $campo = $(inputCodigo).closest('.mcc-cc-puntual');
    if (!$campo.length || !data || !data.codigo) {
        return;
    }
    mccAgregarCentro(data.codigo, data.nombre);
    mccLimpiarCcPuntual();
    $campo.find('.codigocentrocosto').trigger('focus');
};

$(function () {
    mccCargarCentrosDesdeDom();
    mccCargarGruposDesdeDom();

    $(document).on('click.mccPeriodo', '.mcc-periodo-rapido', function (e) {
        e.preventDefault();
        mccPeriodoRapido(String($(this).attr('data-periodo') || 'mes'));
    });

    // Centros de costo
    $(document).on('click.mccCc', '#mcc-btn-agregar-cc', function (e) {
        e.preventDefault();
        mccAgregarCcDesdeCampo();
    });
    $(document).on('click.mccCc', '.mcc-btn-quitar-cc', function (e) {
        e.preventDefault();
        delete movccostoCentrosSel[String($(this).closest('tr').attr('data-codigo') || '')];
        mccRenderCentros();
    });
    $(document).on('click.mccCc', '.eligeconsultacentrocosto', function () {
        var $campo = (typeof ptrCentrocosto_id !== 'undefined' && ptrCentrocosto_id && ptrCentrocosto_id.length)
            ? ptrCentrocosto_id.closest('.mcc-cc-puntual')
            : $();
        if (!$campo.length) {
            return;
        }
        var $tr = $(this).closest('tr');
        if (mccAgregarCentro($tr.find('.codigo').text().trim(), $tr.find('.nombre').text().trim())) {
            mccLimpiarCcPuntual();
        }
    });

    // Grupos
    $(document).on('click.mccGr', '#mcc-btn-agregar-grupo', function (e) {
        e.preventDefault();
        var $campo = $('.mcc-grupo-campo').first();
        var codigo = String($campo.find('.codigogrupocc').val() || '').trim();
        if (!codigo) {
            return;
        }
        var nombre = String($campo.find('.descripciongrupocc').val() || '').trim();
        if (nombre) {
            mccAgregarGrupo(codigo, nombre);
            $campo.find('input').val('');
        } else {
            mccResolverGrupo($campo, true);
        }
    });
    $(document).on('click.mccGr', '.mcc-btn-quitar-grupo', function (e) {
        e.preventDefault();
        delete movccostoGruposSel[String($(this).closest('tr').attr('data-codigo') || '')];
        mccRenderGrupos();
    });
    $(document).on('click.mccGr', '.mcc-grupo-campo .consultagrupocc', function (e) {
        e.preventDefault();
        mccAbrirModalGrupos($(this).closest('.mcc-grupo-campo'));
    });
    $(document).on('keydown.mccGr', '.mcc-grupo-campo .codigogrupocc', function (e) {
        if (e.key === 'F1' || e.keyCode === 112) {
            e.preventDefault();
            mccAbrirModalGrupos($(this).closest('.mcc-grupo-campo'));
            return;
        }
        if (e.key === 'Enter' || e.keyCode === 13) {
            e.preventDefault();
            e.stopPropagation();
            mccResolverGrupo($(this).closest('.mcc-grupo-campo'), true);
        }
    });
    $(document).on('change.mccGr', '.mcc-grupo-campo .codigogrupocc', function () {
        var modal = document.getElementById('consultagrupoccModal');
        if (modal && modal.classList.contains('show')) {
            return;
        }
        mccResolverGrupo($(this).closest('.mcc-grupo-campo'), false);
    });
    $('#consultagrupoccModal').on('shown.bs.modal', function () {
        $('#consultagrupocc').trigger('focus').trigger('select');
    });
    $(document).on('keyup.mccGr', '#consultagrupocc', function (e) {
        if (e.key === 'Enter' || e.keyCode === 13) {
            return;
        }
        mccBuscarGrupos($(this).val());
    });
    $(document).on('keydown.mccGr', '#consultagrupocc', function (e) {
        if (e.key === 'Enter' || e.keyCode === 13) {
            e.preventDefault();
            e.stopPropagation();
            $('#datosgrupocc .eligeconsultagrupocentrocosto').first().trigger('click');
        }
    });
    $(document).on('click.mccGr', '.eligeconsultagrupocentrocosto', function (e) {
        e.preventDefault();
        var $tr = $(this).closest('tr');
        mccAgregarGrupo($tr.find('.codigo').text().trim(), $tr.find('.nombre').text().trim());
        if (movccostoGrupoCampoActivo && movccostoGrupoCampoActivo.length) {
            movccostoGrupoCampoActivo.find('input').val('');
        }
        $('#consultagrupoccModal').modal('hide');
    });

    // Enviar: adopta lo tipeado y muestra el aviso de espera.
    var form = document.getElementById('form-movccosto');
    if (form) {
        form.addEventListener('submit', function () {
            mccAdoptarPendientes();
            if (!form.checkValidity()) {
                return;
            }
            mccMostrarOverlay();
            var btn = document.getElementById('btn-consultar');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Procesando…';
            }
        });
    }

    window.addEventListener('pageshow', mccOcultarOverlay);
    window.addEventListener('pagehide', mccOcultarOverlay);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            if (window.__mccExportAbort) {
                try { window.__mccExportAbort.abort(); } catch (err) { /* ignore */ }
            }
            mccOcultarOverlay();
        }
    });

    // Export PDF/Excel/CSV: descarga sin navegar, el banner se corta al terminar.
    document.addEventListener('click', function (event) {
        var enlace = event.target && event.target.closest
            ? event.target.closest('a[href*="listar-movimientos-centro-costo"]')
            : null;
        if (!enlace) {
            return;
        }
        var href = enlace.getAttribute('href') || enlace.href || '';
        if (!href) {
            return;
        }
        event.preventDefault();
        event.stopPropagation();
        mccExportar(href);
    }, true);
});
