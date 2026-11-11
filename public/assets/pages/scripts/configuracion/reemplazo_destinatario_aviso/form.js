$(function () {
    window._consultaUsuarioOmitirFiltroEmpresa = true;
    window._consultaUsuarioOmitirFiltroEmpresaFijo = true;

    if (typeof activa_eventos_consultausuario === 'function') {
        activa_eventos_consultausuario();
    }

    function modoActual() {
        return $('input[name="modo"]:checked').val() || 'reemplazo';
    }

    function syncModoUi() {
        var modo = modoActual();
        var esRestaurar = modo === 'restaurar';
        $('#ra-bloque-reemplazo').toggleClass('d-none', esRestaurar);
        $('#ra-bloque-restaurar').toggleClass('d-none', !esRestaurar);
        $('#ra-btn-aplicar-texto').text(esRestaurar ? 'Restaurar titular' : 'Aplicar reemplazo');
        $('#btn-aplicar-reemplazo')
            .toggleClass('btn-danger', !esRestaurar)
            .toggleClass('btn-success', esRestaurar);
    }

    $('input[name="modo"]').on('change', syncModoUi);
    syncModoUi();

    function syncFechaTopeUi() {
        var on = $('#con_fecha_tope').is(':checked');
        $('#ra-vence-wrap').toggleClass('d-none', !on);
        $('#ra-sin-vence-ayuda').toggleClass('d-none', on);
        if (!on) {
            $('#vence_el').val('');
        }
    }
    $('#con_fecha_tope').on('change', syncFechaTopeUi);
    syncFechaTopeUi();

    function formatearFechaTope(ymd) {
        var m = String(ymd || '').match(/^(\d{4})-(\d{2})-(\d{2})$/);
        if (!m) {
            return '';
        }
        return m[3] + '/' + m[2] + '/' + m[1];
    }

    var raOverlayTimer = null;

    function raOverlayEl() {
        return document.getElementById('ra-reemplazo-overlay');
    }

    function mostrarRaOverlay(on, titulo, subtitulo) {
        var el = raOverlayEl();
        if (!el) {
            return;
        }
        if (titulo) {
            var $t = document.getElementById('ra-reemplazo-overlay-titulo');
            if ($t) {
                $t.textContent = titulo;
            }
        }
        if (subtitulo) {
            var $s = document.getElementById('ra-reemplazo-overlay-subtitulo');
            if ($s) {
                $s.textContent = subtitulo;
            }
        }
        if (on) {
            el.classList.remove('d-none');
            el.style.display = 'flex';
            el.setAttribute('aria-hidden', 'false');
        } else {
            el.classList.add('d-none');
            el.style.display = '';
            el.setAttribute('aria-hidden', 'true');
        }
    }

    function mensajesVivosRa(modo) {
        if (modo === 'restaurar') {
            return [
                'Restaurando titular…',
                'Devolviendo destinatarios de aviso…',
                'Casi listo, no cierre la página…',
            ];
        }
        return [
            'Aplicando reemplazo de destinatario…',
            'Actualizando avisos por módulo…',
            'Casi listo, no cierre la página…',
        ];
    }

    function iniciarMensajesVivosRa(modo) {
        detenerMensajesVivosRa();
        var msgs = mensajesVivosRa(modo);
        var idx = 0;
        var $t = document.getElementById('ra-reemplazo-overlay-titulo');
        if ($t) {
            $t.textContent = msgs[0];
        }
        raOverlayTimer = setInterval(function () {
            idx = (idx + 1) % msgs.length;
            if ($t) {
                $t.textContent = msgs[idx];
            }
        }, 2200);
    }

    function detenerMensajesVivosRa() {
        if (raOverlayTimer) {
            clearInterval(raOverlayTimer);
            raOverlayTimer = null;
        }
    }

    function ocultarRaOverlay() {
        detenerMensajesVivosRa();
        mostrarRaOverlay(false);
        $('#btn-aplicar-reemplazo, #btn-preview-reemplazo').prop('disabled', false);
    }

    $('#btn-aplicar-reemplazo').on('click', function (e) {
        var modo = modoActual();
        var venceRaw = $.trim($('#vence_el').val() || '');
        var venceFmt = formatearFechaTope(venceRaw);

        if (modo === 'reemplazo' && $('#con_fecha_tope').is(':checked')) {
            if (!venceRaw) {
                alert('Indicó fecha tope: complete el último día del reemplazo.');
                e.preventDefault();
                $('#vence_el').focus();
                return false;
            }
            if (!venceFmt) {
                alert('La fecha tope no es válida. Use el selector de fecha (formato AAAA-MM-DD).');
                e.preventDefault();
                $('#vence_el').focus();
                return false;
            }
        }
        var msg;
        if (modo === 'restaurar') {
            msg = '¿Confirma restaurar al titular sus destinatarios de aviso (fin de suplencia)?';
        } else if ($('#con_fecha_tope').is(':checked')) {
            msg = '¿Confirma el reemplazo? Vigente hasta el ' + venceFmt
                + ' inclusive; se restaurará automáticamente al día siguiente.';
        } else {
            msg = '¿Confirma el reemplazo sin fecha tope? Quedará activo hasta que restaure el titular manualmente.';
        }
        if (!confirm(msg)) {
            e.preventDefault();
            return false;
        }
        return true;
    });

    $('#form-reemplazo-aviso').on('submit', function () {
        var modo = modoActual();
        var titulo = modo === 'restaurar'
            ? 'Restaurando titular…'
            : 'Aplicando reemplazo de destinatario…';
        $('#btn-aplicar-reemplazo, #btn-preview-reemplazo').prop('disabled', true);
        mostrarRaOverlay(
            true,
            titulo,
            'Puede demorar según la cantidad de avisos. No cierre la página.'
        );
        iniciarMensajesVivosRa(modo);
    });

    window.addEventListener('pageshow', function () {
        ocultarRaOverlay();
    });
    $(window).on('unload pagehide', function () {
        detenerMensajesVivosRa();
    });
    ocultarRaOverlay();

    $('#btn-preview-reemplazo').on('click', function () {
        var $btn = $(this);
        var url = $('#form-reemplazo-aviso').data('preview-url');
        var data = $('#form-reemplazo-aviso').serialize();
        $btn.prop('disabled', true);
        $('#ra-preview').removeClass('d-none');
        $('#ra-preview-body').html('<p class="text-muted mb-0"><i class="fa fa-spinner fa-spin"></i> Calculando impacto…</p>');

        $.ajax({
            url: url,
            method: 'POST',
            data: data,
            dataType: 'json'
        }).done(function (resp) {
            if (!resp || !resp.ok) {
                $('#ra-preview-body').html(
                    '<div class="alert alert-warning mb-0">' +
                    $('<div>').text((resp && resp.mensaje) || 'No se pudo previsualizar.').html() +
                    '</div>'
                );
                return;
            }
            $('#ra-preview-body').html(renderPreviewAviso(resp.preview));
        }).fail(function (xhr) {
            var msg = (xhr.responseJSON && xhr.responseJSON.mensaje) || 'Error al previsualizar.';
            $('#ra-preview-body').html(
                '<div class="alert alert-danger mb-0">' + $('<div>').text(msg).html() + '</div>'
            );
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });
});

function renderPreviewAviso(p) {
    if (!p) {
        return '';
    }
    var c = p.conteos || {};
    var o = p.opciones || {};
    var esRestaurar = (p.operacion || '') === 'restaurar';
    var html = '';

    if (esRestaurar) {
        html += '<p class="mb-2"><span class="badge badge-success">Restaurar</span> titular ';
        html += '<strong>' + escAviso(p.usuario_titular && p.usuario_titular.nombre) + '</strong></p>';
        if (p.suplentes && p.suplentes.length) {
            html += '<p class="small text-muted">Suplentes actuales: ';
            html += p.suplentes.map(function (s) { return escAviso(s.nombre); }).join(', ');
            html += '</p>';
        }
    } else {
        html += '<p class="mb-2"><span class="badge badge-secondary">Reemplazo</span> ';
        html += '<strong>' + escAviso(p.usuario_origen && p.usuario_origen.nombre) + '</strong>';
        html += ' → <strong>' + escAviso(p.usuario_destino && p.usuario_destino.nombre) + '</strong></p>';
        if (o.con_fecha_tope && o.vence_el) {
            var venceMostrar = String(o.vence_el);
            var mVence = venceMostrar.match(/^(\d{4})-(\d{2})-(\d{2})$/);
            if (mVence) {
                venceMostrar = mVence[3] + '/' + mVence[2] + '/' + mVence[1];
            }
            html += '<p class="small mb-2">Vigente hasta <strong>' + escAviso(venceMostrar) + '</strong> inclusive'
                + ' (restauración automática al día siguiente).</p>';
        } else {
            html += '<p class="small text-muted mb-2">Sin fecha tope: restauración solo manual.</p>';
        }
    }

    html += '<ul class="mb-3">';
    html += '<li>Destinatarios: <strong>' + (c.destinatarios || 0) + '</strong></li>';
    if (c.omitidos_duplicado) {
        html += '<li>Omitidos por duplicado: <strong>' + c.omitidos_duplicado + '</strong></li>';
    }
    html += '<li>Total aplicable estimado: <strong>' + (c.total_aplicable || 0) + '</strong></li>';
    html += '</ul>';

    var headers = esRestaurar
        ? ['ID', 'Módulo', 'Aviso', 'Código', 'Empresa', 'CC', 'Suplente']
        : ['ID', 'Módulo', 'Aviso', 'Código', 'Empresa', 'CC', 'Activo'];
    html += renderTablaMuestraAviso('Destinatarios (muestra)', headers,
        (p.muestras && p.muestras.destinatarios) || [],
        function (r) {
            return esRestaurar
                ? [r.id, r.modulo, r.aviso, r.codigo, r.empresa, r.centrocosto, r.suplente]
                : [r.id, r.modulo, r.aviso, r.codigo, r.empresa, r.centrocosto, r.activo ? 'Sí' : 'No'];
        });

    return html;
}

function renderTablaMuestraAviso(titulo, headers, rows, mapRow) {
    if (!rows || !rows.length) {
        return '<p class="text-muted small mb-2">' + escAviso(titulo) + ': sin filas.</p>';
    }
    var html = '<h6 class="mb-1">' + escAviso(titulo) + '</h6>';
    html += '<div class="table-responsive mb-3"><table class="table table-sm table-bordered bg-white">';
    html += '<thead><tr>';
    headers.forEach(function (h) {
        html += '<th>' + escAviso(h) + '</th>';
    });
    html += '</tr></thead><tbody>';
    rows.forEach(function (r) {
        html += '<tr>';
        mapRow(r).forEach(function (cell) {
            html += '<td>' + escAviso(cell) + '</td>';
        });
        html += '</tr>';
    });
    html += '</tbody></table></div>';
    return html;
}

function escAviso(v) {
    if (v === null || v === undefined) {
        return '';
    }
    return $('<div>').text(String(v)).html();
}
