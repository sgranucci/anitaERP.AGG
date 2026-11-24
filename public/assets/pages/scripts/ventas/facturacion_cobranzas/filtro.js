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

function factycobrEsF1(e) {
    return !!(e && (e.key === 'F1' || e.code === 'F1' || e.keyCode === 112 || e.which === 112));
}

function factycobrEsEnter(e) {
    return !!(e && (e.key === 'Enter' || e.which === 13 || e.keyCode === 13));
}

function factycobrModalAbierto() {
    var m = document.getElementById('consultatransporteModal');
    return !!(m && (m.classList.contains('show') || m.classList.contains('in')));
}

function factycobrModoReparto() {
    var $checked = $('input[name="reparto_modo"]:checked');
    return $checked.length ? String($checked.val() || 'todos') : 'todos';
}

function factycobrActualizarBloquesReparto() {
    var modo = factycobrModoReparto();
    $('#bloque-rango-reparto').toggle(modo === 'rango');
    $('#bloque-particular-reparto').toggle(modo === 'particular');
}

function factycobrSincronizarHiddenRepartos() {
    var codigos = [];
    var pares = [];
    $('#factycobr-tbody-repartos-seleccionados tr').each(function () {
        var codigo = parseInt($(this).attr('data-codigo'), 10) || 0;
        if (codigo <= 0) {
            return;
        }
        codigos.push(codigo);
        var nombre = $(this).find('td').eq(1).text().trim();
        if (nombre !== '') {
            pares.push(codigo + ':' + nombre);
        }
    });
    $('#factycobr_repartos').val(codigos.join(','));
    $('#factycobr_repartos_nombres').val(pares.join('|'));
    $('#factycobr-aviso-sin-repartos').toggle(codigos.length === 0);
}

function factycobrAgregarReparto(codigo, nombre) {
    var cod = parseInt(codigo, 10) || 0;
    if (cod <= 0) {
        return false;
    }
    if ($('#factycobr-tbody-repartos-seleccionados tr[data-codigo="' + cod + '"]').length) {
        return false;
    }
    var $tr = $('<tr>').attr('data-codigo', cod);
    $tr.append($('<td>').text(cod));
    $tr.append($('<td>').text(nombre || ''));
    $tr.append(
        $('<td class="text-center">').append(
            $('<button type="button" class="btn btn-outline-danger btn-xs factycobr-btn-quitar-reparto" title="Quitar">')
                .append('<i class="fa fa-times"></i>')
        )
    );
    $('#factycobr-tbody-repartos-seleccionados').append($tr);
    factycobrSincronizarHiddenRepartos();
    return true;
}

function factycobrLimpiarCampoPuntual() {
    var $campo = $('.factycobr-reparto-puntual');
    $campo.find('.codigotransporte').val('');
    $campo.find('.nombretransporte').val('');
}

function factycobrResolverReparto($campo, opciones) {
    opciones = opciones || {};
    var codigo = String($campo.find('.codigotransporte').val() || '').trim();
    if (codigo === '') {
        $campo.find('.nombretransporte').val('');
        if (typeof opciones.onDone === 'function') {
            opciones.onDone(null);
        }
        return;
    }
    $.get(carpetaBase + '/ventas/leertransporte/' + encodeURIComponent(codigo), function (data) {
        if (data && data.id) {
            $campo.find('.codigotransporte').val(data.codigo || codigo);
            $campo.find('.nombretransporte').val(data.nombre || '');
            if (typeof opciones.onDone === 'function') {
                opciones.onDone({ codigo: data.codigo || codigo, nombre: data.nombre || '' });
            }
        } else {
            $campo.find('.nombretransporte').val('');
            if (typeof opciones.onDone === 'function') {
                opciones.onDone(null);
            }
        }
    }).fail(function () {
        $campo.find('.nombretransporte').val('');
        if (typeof opciones.onDone === 'function') {
            opciones.onDone(null);
        }
    });
}

function factycobrAgregarDesdeCampoPuntual() {
    var $campo = $('.factycobr-reparto-puntual');
    var codigo = String($campo.find('.codigotransporte').val() || '').trim();
    if (codigo === '') {
        return;
    }
    var nombre = String($campo.find('.nombretransporte').val() || '').trim();
    if (nombre !== '') {
        if (!factycobrAgregarReparto(codigo, nombre)) {
            alert('El reparto ya está en la lista');
        }
        factycobrLimpiarCampoPuntual();
        $campo.find('.codigotransporte').trigger('focus');
        return;
    }
    factycobrResolverReparto($campo, {
        onDone: function (data) {
            if (!data) {
                $campo.find('.codigotransporte').trigger('focus');
                return;
            }
            if (!factycobrAgregarReparto(data.codigo, data.nombre)) {
                alert('El reparto ya está en la lista');
            }
            factycobrLimpiarCampoPuntual();
            $campo.find('.codigotransporte').trigger('focus');
        },
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

function factycobrAbrirModalReparto($campo) {
    factycobrRepartoCampoActivo = $campo && $campo.length ? $campo : null;
    $('#consultatransporteModal').modal('show');
}

function factycobrElegirPrimeraFilaModal() {
    var $btn = $('#datostransporte .eligeconsultatransporte').first();
    if (!$btn.length) {
        return false;
    }
    $btn.trigger('click');
    return true;
}

function factycobrAplicarEleccionModal(codigo, nombre) {
    if (!(factycobrRepartoCampoActivo && factycobrRepartoCampoActivo.length)) {
        return;
    }
    if (factycobrRepartoCampoActivo.hasClass('factycobr-reparto-puntual')
        || factycobrRepartoCampoActivo.data('campo') === 'puntual') {
        if (!factycobrAgregarReparto(codigo, nombre)) {
            alert('El reparto ya está en la lista');
        } else {
            factycobrLimpiarCampoPuntual();
        }
        return;
    }
    factycobrRepartoCampoActivo.find('.codigotransporte').val(codigo);
    factycobrRepartoCampoActivo.find('.nombretransporte').val(nombre);
}

function factycobrOnKeydownF1Capture(e) {
    if (!factycobrEsF1(e)) {
        return;
    }
    var target = e.target;
    if (!target || !target.classList || !target.classList.contains('codigotransporte')) {
        return;
    }
    if (!$(target).closest('.factycobr-reparto-campo').length) {
        return;
    }
    if (target.readOnly || target.disabled) {
        return;
    }
    if (factycobrModalAbierto()) {
        return;
    }
    e.preventDefault();
    e.stopImmediatePropagation();
    factycobrAbrirModalReparto($(target).closest('.factycobr-reparto-campo'));
}

function activaEventosFactycobrFiltro() {
    var abortController = null;

    factycobrActualizarBloquesReparto();
    factycobrSincronizarHiddenRepartos();

    $('input[name="reparto_modo"]').on('change', factycobrActualizarBloquesReparto);

    $('#form-facturacion-cobranzas').on('submit', function () {
        var form = this;
        if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
            return;
        }
        if (factycobrModoReparto() === 'particular') {
            factycobrSincronizarHiddenRepartos();
        }
        factycobrMostrar(
            'Consultando…',
            'Puede demorar según el período y la lectura Anita. No cierre la página.'
        );
    });

    $(document)
        .off('change.fc blur.fc', '.factycobr-reparto-campo .codigotransporte')
        .on('change.fc blur.fc', '.factycobr-reparto-campo .codigotransporte', function () {
            var $campo = $(this).closest('.factycobr-reparto-campo');
            if ($campo.hasClass('factycobr-reparto-puntual') || $campo.data('campo') === 'puntual') {
                return;
            }
            factycobrResolverReparto($campo);
        });

    $(document)
        .off('keydown.fc', '.factycobr-reparto-campo .codigotransporte')
        .on('keydown.fc', '.factycobr-reparto-campo .codigotransporte', function (e) {
            if (factycobrEsF1(e)) {
                if (factycobrModalAbierto()) {
                    return;
                }
                e.preventDefault();
                e.stopPropagation();
                factycobrAbrirModalReparto($(this).closest('.factycobr-reparto-campo'));
                return false;
            }
            if (!factycobrEsEnter(e)) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            var $campo = $(this).closest('.factycobr-reparto-campo');
            if ($campo.hasClass('factycobr-reparto-puntual') || $campo.data('campo') === 'puntual') {
                factycobrAgregarDesdeCampoPuntual();
                return false;
            }
            factycobrResolverReparto($campo, {
                onDone: function () {
                    if ($campo.data('campo') === 'desde') {
                        $('#reparto_hasta').trigger('focus').trigger('select');
                    }
                },
            });
            return false;
        });

    document.removeEventListener('keydown', factycobrOnKeydownF1Capture, true);
    document.addEventListener('keydown', factycobrOnKeydownF1Capture, true);

    $(document)
        .off('click.fc', '.factycobr-reparto-campo .consultatransporte')
        .on('click.fc', '.factycobr-reparto-campo .consultatransporte', function (e) {
            e.preventDefault();
            factycobrAbrirModalReparto($(this).closest('.factycobr-reparto-campo'));
        });

    $(document)
        .off('click.fcAdd', '#factycobr-btn-agregar-reparto')
        .on('click.fcAdd', '#factycobr-btn-agregar-reparto', function (e) {
            e.preventDefault();
            factycobrAgregarDesdeCampoPuntual();
        });

    $(document)
        .off('click.fcQuitar', '.factycobr-btn-quitar-reparto')
        .on('click.fcQuitar', '.factycobr-btn-quitar-reparto', function (e) {
            e.preventDefault();
            $(this).closest('tr').remove();
            factycobrSincronizarHiddenRepartos();
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
            var $buscar = $(this).find('#consultatransporte');
            setTimeout(function () {
                $buscar.trigger('focus');
                var el = $buscar.get(0);
                if (el && typeof el.select === 'function') {
                    el.select();
                }
            }, 0);
        });

    $('#consultatransporteModal')
        .off('submit.fc', 'form')
        .on('submit.fc', 'form', function (e) {
            e.preventDefault();
        });

    $(document)
        .off('keydown.fcModalEnter', '#consultatransporte')
        .on('keydown.fcModalEnter', '#consultatransporte', function (e) {
            if (!factycobrEsEnter(e)) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            if (!factycobrElegirPrimeraFilaModal()) {
                factycobrBuscarTransportesModal(String($(this).val() || '').trim());
            }
            return false;
        });

    $(document)
        .off('keyup.fc', '#consultatransporte')
        .on('keyup.fc', '#consultatransporte', function (e) {
            if (factycobrEsEnter(e)) {
                return;
            }
            factycobrBuscarTransportesModal($(this).val().trim());
        });

    $(document)
        .off('click.fc', '.eligeconsultatransporte')
        .on('click.fc', '.eligeconsultatransporte', function () {
            var $tr = $(this).closest('tr');
            var codigo = $tr.find('.codigo').first().text().trim();
            var nombre = $tr.find('.nombre').first().text().trim();
            factycobrAplicarEleccionModal(codigo, nombre);
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
