var factycobrRepartoCampoActivo = null;

function factycobrOverlay() {
    return document.getElementById('factycobr-overlay');
}

function factycobrMostrar(titulo, subtitulo) {
    var overlay = factycobrOverlay();
    if (!overlay) {
        return;
    }
    if (titulo) {
        var t = document.getElementById('factycobr-titulo');
        if (t) {
            t.textContent = titulo;
        }
    }
    if (subtitulo) {
        var s = document.getElementById('factycobr-subtitulo');
        if (s) {
            s.textContent = subtitulo;
        }
    }
    overlay.classList.remove('d-none');
    overlay.style.display = 'flex';
    overlay.setAttribute('aria-hidden', 'false');
}

function factycobrOcultar() {
    var overlay = factycobrOverlay();
    if (!overlay) {
        return;
    }
    overlay.classList.add('d-none');
    overlay.style.display = '';
    overlay.setAttribute('aria-hidden', 'true');
}

function factycobrNombreDesdeDisposition(header, fallback) {
    if (!header) {
        return fallback;
    }
    var m = /filename\*?=(?:UTF-8''|")?([^\";]+)/i.exec(header);
    if (!m) {
        return fallback;
    }
    try {
        return decodeURIComponent(m[1].replace(/"/g, '').trim());
    } catch (e) {
        return m[1].replace(/"/g, '').trim() || fallback;
    }
}

function factycobrDispararDescargaBlob(blob, filename) {
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = filename || 'facturacion_cobranzas';
    document.body.appendChild(a);
    a.click();
    setTimeout(function () {
        URL.revokeObjectURL(url);
        a.remove();
    }, 1500);
}

function factycobrResolverReparto($campo) {
    var codigo = String($campo.find('.codigotransporte').val() || '').trim();
    if (codigo === '') {
        $campo.find('.nombretransporte').val('');
        return;
    }
    $.get(carpetaBase + '/ventas/leertransporte/' + encodeURIComponent(codigo), function (data) {
        if (data && data.id) {
            $campo.find('.codigotransporte').val(data.codigo || codigo);
            $campo.find('.nombretransporte').val(data.nombre || '');
        } else {
            $campo.find('.nombretransporte').val('');
        }
    });
}

function factycobrBuscarTransportesModal(consulta) {
    $.ajax({
        url: carpetaBase + '/ventas/transporte/consultatransporte',
        type: 'POST',
        dataType: 'HTML',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
        },
        data: { consulta: consulta },
    })
        .done(function (respuesta) {
            $('#datostransporte').html(respuesta.replace(/\\/g, ''));
        })
        .fail(function () {
            $('#datostransporte').html('<tr><td colspan="8">Error al consultar repartos</td></tr>');
        });
}

function activaEventosFactycobrFiltro() {
    var abortController = null;

    $('#form-facturacion-cobranzas').on('submit', function (e) {
        var form = this;
        if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
            return;
        }
        factycobrMostrar(
            'Consultando…',
            'Puede demorar según el período y la lectura Anita. No cierre la página.'
        );
    });

    $(document)
        .off('change.fc', '.factycobr-reparto-campo .codigotransporte')
        .on('change.fc', '.factycobr-reparto-campo .codigotransporte', function () {
            factycobrResolverReparto($(this).closest('.factycobr-reparto-campo'));
        });

    $(document)
        .off('keydown.fc', '.factycobr-reparto-campo .codigotransporte')
        .on('keydown.fc', '.factycobr-reparto-campo .codigotransporte', function (e) {
            if (e.which === 13) {
                e.preventDefault();
                factycobrResolverReparto($(this).closest('.factycobr-reparto-campo'));
                return false;
            }
            if (e.which === 112) {
                e.preventDefault();
                factycobrRepartoCampoActivo = $(this).closest('.factycobr-reparto-campo');
                $('#consultatransporteModal').modal('show');
                return false;
            }
        });

    $(document)
        .off('click.fc', '.factycobr-reparto-campo .consultatransporte')
        .on('click.fc', '.factycobr-reparto-campo .consultatransporte', function (e) {
            e.preventDefault();
            factycobrRepartoCampoActivo = $(this).closest('.factycobr-reparto-campo');
            $('#consultatransporteModal').modal('show');
        });

    $('#consultatransporteModal')
        .off('shown.bs.modal.fc')
        .on('shown.bs.modal.fc', function () {
            var valor = '';
            if (factycobrRepartoCampoActivo && factycobrRepartoCampoActivo.length) {
                valor = String(factycobrRepartoCampoActivo.find('.codigotransporte').val() || '').trim();
            }
            $('#consultatransporte').val(valor);
            factycobrBuscarTransportesModal(valor);
            $(this).find('[autofocus]').focus();
        });

    $(document)
        .off('keyup.fc', '#consultatransporte')
        .on('keyup.fc', '#consultatransporte', function () {
            factycobrBuscarTransportesModal($(this).val().trim());
        });

    $(document)
        .off('click.fc', '.eligeconsultatransporte')
        .on('click.fc', '.eligeconsultatransporte', function () {
            var $tr = $(this).closest('tr');
            var codigo = $tr.find('.codigo').first().text().trim();
            var nombre = $tr.find('.nombre').first().text().trim();
            if (factycobrRepartoCampoActivo && factycobrRepartoCampoActivo.length) {
                factycobrRepartoCampoActivo.find('.codigotransporte').val(codigo);
                factycobrRepartoCampoActivo.find('.nombretransporte').val(nombre);
            }
            $('#consultatransporteModal').modal('hide');
        });

    $(document)
        .off('click.fcExport', 'a[href*="listar-facturacion-cobranzas"]')
        .on('click.fcExport', 'a[href*="listar-facturacion-cobranzas"]', function (e) {
            e.preventDefault();
            var a = this;
            if (abortController) {
                abortController.abort();
            }
            abortController = typeof AbortController !== 'undefined' ? new AbortController() : null;
            factycobrMostrar('Exportando…', 'Generando archivo. Pulse Esc para cerrar este aviso.');
            fetch(a.href, {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: abortController ? abortController.signal : undefined,
            })
                .then(function (res) {
                    if (!res.ok) {
                        throw new Error('HTTP ' + res.status);
                    }
                    return res.blob().then(function (blob) {
                        return {
                            blob: blob,
                            filename: factycobrNombreDesdeDisposition(
                                res.headers.get('Content-Disposition'),
                                'facturacion_cobranzas'
                            ),
                        };
                    });
                })
                .then(function (pack) {
                    factycobrDispararDescargaBlob(pack.blob, pack.filename);
                    factycobrOcultar();
                })
                .catch(function (err) {
                    factycobrOcultar();
                    if (err && err.name === 'AbortError') {
                        return;
                    }
                    alert((err && err.message) || 'No se pudo exportar');
                });
        });

    $(document).on('keydown.fcEsc', function (e) {
        if (e.key === 'Escape' || e.which === 27) {
            if (abortController) {
                abortController.abort();
            }
            factycobrOcultar();
        }
    });

    window.addEventListener('pageshow', factycobrOcultar);
    window.addEventListener('pagehide', factycobrOcultar);
}

$(function () {
    activaEventosFactycobrFiltro();
});
