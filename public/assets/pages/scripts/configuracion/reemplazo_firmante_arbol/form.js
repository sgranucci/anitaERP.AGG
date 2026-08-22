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
        $('#rf-bloque-reemplazo').toggleClass('d-none', esRestaurar);
        $('#rf-bloque-restaurar').toggleClass('d-none', !esRestaurar);
        $('#rf-btn-aplicar-texto').text(esRestaurar ? 'Restaurar titular' : 'Aplicar reemplazo');
        $('#btn-aplicar-reemplazo')
            .toggleClass('btn-danger', !esRestaurar)
            .toggleClass('btn-success', esRestaurar);
    }

    $('input[name="modo"]').on('change', syncModoUi);
    syncModoUi();

    $('#actualizar_pendientes').on('change', function () {
        var on = $(this).is(':checked');
        $('#reenviar_correo').prop('disabled', !on);
        if (!on) {
            $('#reenviar_correo').prop('checked', false);
        }
    }).trigger('change');

    function syncFechaTopeUi() {
        var on = $('#con_fecha_tope').is(':checked');
        $('#rf-vence-wrap').toggleClass('d-none', !on);
        $('#rf-sin-vence-ayuda').toggleClass('d-none', on);
        if (!on) {
            $('#vence_el').val('');
        }
    }
    $('#con_fecha_tope').on('change', syncFechaTopeUi);
    syncFechaTopeUi();

    $('#incluir_globales').on('change', function () {
        var on = $(this).is(':checked');
        $('#rf-tipos-wrap').toggleClass('text-muted', !on);
        $('.rf-tipo').prop('disabled', !on);
    }).trigger('change');

    function formatearFechaTope(ymd) {
        var m = String(ymd || '').match(/^(\d{4})-(\d{2})-(\d{2})$/);
        if (!m) {
            return '';
        }
        return m[3] + '/' + m[2] + '/' + m[1];
    }

    var rfOverlayTimer = null;
    var rfOverlayActivo = false;

    function rfOverlayEl() {
        return document.getElementById('rf-reemplazo-overlay');
    }

    function mostrarRfOverlay(on, titulo, subtitulo) {
        var el = rfOverlayEl();
        if (!el) {
            return;
        }
        if (titulo) {
            var $t = document.getElementById('rf-reemplazo-overlay-titulo');
            if ($t) {
                $t.textContent = titulo;
            }
        }
        if (subtitulo) {
            var $s = document.getElementById('rf-reemplazo-overlay-subtitulo');
            if ($s) {
                $s.textContent = subtitulo;
            }
        }
        if (on) {
            el.classList.remove('d-none');
            el.style.display = 'flex';
            el.setAttribute('aria-hidden', 'false');
            rfOverlayActivo = true;
        } else {
            el.classList.add('d-none');
            el.style.display = '';
            el.setAttribute('aria-hidden', 'true');
            rfOverlayActivo = false;
        }
    }

    function mensajesVivosRf(modo) {
        if (modo === 'restaurar') {
            return [
                'Restaurando titular…',
                'Devolviendo niveles del árbol…',
                'Actualizando conceptos SP…',
                'Reasignando pendientes…',
            ];
        }
        return [
            'Aplicando reemplazo de firmante…',
            'Actualizando niveles del árbol…',
            'Actualizando conceptos SP…',
            'Reasignando movimientos pendientes…',
            'Casi listo, no cierre la página…',
        ];
    }

    function iniciarMensajesVivosRf(modo) {
        detenerMensajesVivosRf();
        var msgs = mensajesVivosRf(modo);
        var idx = 0;
        var $t = document.getElementById('rf-reemplazo-overlay-titulo');
        if ($t) {
            $t.textContent = msgs[0];
        }
        rfOverlayTimer = setInterval(function () {
            idx = (idx + 1) % msgs.length;
            if ($t) {
                $t.textContent = msgs[idx];
            }
        }, 2200);
    }

    function detenerMensajesVivosRf() {
        if (rfOverlayTimer) {
            clearInterval(rfOverlayTimer);
            rfOverlayTimer = null;
        }
    }

    function ocultarRfOverlay() {
        detenerMensajesVivosRf();
        mostrarRfOverlay(false);
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
            msg = '¿Confirma restaurar al titular sus posiciones en el árbol (fin de suplencia)?';
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

    $('#form-reemplazo-firmante').on('submit', function () {
        var modo = modoActual();
        var titulo = modo === 'restaurar'
            ? 'Restaurando titular…'
            : 'Aplicando reemplazo de firmante…';
        $('#btn-aplicar-reemplazo, #btn-preview-reemplazo').prop('disabled', true);
        mostrarRfOverlay(
            true,
            titulo,
            'Puede demorar según la cantidad de niveles, conceptos y pendientes. No cierre la página.'
        );
        iniciarMensajesVivosRf(modo);
    });

    // bfcache / atrás del navegador: no dejar el overlay pegado
    window.addEventListener('pageshow', function () {
        ocultarRfOverlay();
    });
    $(window).on('unload pagehide', function () {
        // Al navegar por el POST exitoso el overlay ya se va con la página;
        // por las dudas frenamos el timer.
        detenerMensajesVivosRf();
    });
    ocultarRfOverlay();

    $('#btn-preview-reemplazo').on('click', function () {
        var $btn = $(this);
        var url = $('#form-reemplazo-firmante').data('preview-url');
        var data = $('#form-reemplazo-firmante').serialize();
        $btn.prop('disabled', true);
        $('#rf-preview').removeClass('d-none');
        $('#rf-preview-body').html('<p class="text-muted mb-0"><i class="fa fa-spinner fa-spin"></i> Calculando impacto…</p>');

        $.ajax({
            url: url,
            method: 'POST',
            data: data,
            dataType: 'json'
        }).done(function (resp) {
            if (!resp || !resp.ok) {
                $('#rf-preview-body').html(
                    '<div class="alert alert-warning mb-0">' +
                    $('<div>').text((resp && resp.mensaje) || 'No se pudo previsualizar.').html() +
                    '</div>'
                );
                return;
            }
            $('#rf-preview-body').html(renderPreview(resp.preview));
        }).fail(function (xhr) {
            var msg = (xhr.responseJSON && xhr.responseJSON.mensaje) || 'Error al previsualizar.';
            $('#rf-preview-body').html(
                '<div class="alert alert-danger mb-0">' + $('<div>').text(msg).html() + '</div>'
            );
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });
});

function renderPreview(p) {
    if (!p) {
        return '';
    }
    var c = p.conteos || {};
    var o = p.opciones || {};
    var esRestaurar = (p.operacion || '') === 'restaurar';
    var html = '';

    if (esRestaurar) {
        html += '<p class="mb-2"><span class="badge badge-success">Restaurar</span> titular ';
        html += '<strong>' + esc(p.usuario_titular && p.usuario_titular.nombre) + '</strong></p>';
        if (p.suplentes && p.suplentes.length) {
            html += '<p class="small text-muted">Suplentes actuales: ';
            html += p.suplentes.map(function (s) { return esc(s.nombre); }).join(', ');
            html += '</p>';
        }
    } else {
        html += '<p class="mb-2"><span class="badge badge-secondary">Reemplazo</span> ';
        html += '<strong>' + esc(p.usuario_origen && p.usuario_origen.nombre) + '</strong>';
        html += ' → <strong>' + esc(p.usuario_destino && p.usuario_destino.nombre) + '</strong></p>';
        if (o.con_fecha_tope && o.vence_el) {
            var venceMostrar = String(o.vence_el);
            var mVence = venceMostrar.match(/^(\d{4})-(\d{2})-(\d{2})$/);
            if (mVence) {
                venceMostrar = mVence[3] + '/' + mVence[2] + '/' + mVence[1];
            }
            html += '<p class="small mb-2">Vigente hasta <strong>' + esc(venceMostrar) + '</strong> inclusive'
                + ' (restauración automática al día siguiente).</p>';
        } else {
            html += '<p class="small text-muted mb-2">Sin fecha tope: restauración solo manual.</p>';
        }
    }

    html += '<ul class="mb-3">';
    html += '<li>Niveles globales: <strong>' + (c.niveles_globales || 0) + '</strong></li>';
    html += '<li>Conceptos SP: <strong>' + (c.conceptos_sp || 0) + '</strong></li>';
    html += '<li>Pendientes a reasignar: <strong>' + (c.pendientes || 0) + '</strong>';
    if (o.reenviar_correo) {
        html += ' <span class="badge badge-info">con reenvío de correo</span>';
    }
    html += '</li>';
    html += '<li>Total aplicable estimado: <strong>' + (c.total_aplicable || 0) + '</strong></li>';
    html += '</ul>';

    var headersNivel = esRestaurar
        ? ['ID', 'Árbol', 'Tipo', 'Nivel', 'CC', 'Suplente']
        : ['ID', 'Árbol', 'Tipo', 'Nivel', 'CC'];
    html += renderTablaMuestra('Niveles globales (muestra)', headersNivel,
        (p.muestras && p.muestras.niveles_globales) || [],
        function (r) {
            return esRestaurar
                ? [r.id, r.arbol, r.tipo, r.nivel, r.centrocosto, r.suplente]
                : [r.id, r.arbol, r.tipo, r.nivel, r.centrocosto];
        });

    var headersConcepto = esRestaurar
        ? ['ID', 'Concepto', 'Nivel', 'Desde monto', 'Suplente']
        : ['ID', 'Concepto', 'Nivel', 'Desde monto'];
    html += renderTablaMuestra('Conceptos SP (muestra)', headersConcepto,
        (p.muestras && p.muestras.conceptos_sp) || [],
        function (r) {
            return esRestaurar
                ? [r.id, r.concepto, r.nivel, r.desde_monto, r.suplente]
                : [r.id, r.concepto, r.nivel, r.desde_monto];
        });

    html += renderTablaMuestra('Pendientes (muestra)', [
        'Mov.', 'Tipo', 'Comprobante', 'Nivel', 'Envío'
    ], (p.muestras && p.muestras.pendientes) || [], function (r) {
        return [r.id, r.tipo, r.comprobante_id, r.nivel, r.fechaenvio];
    });

    return html;
}

function renderTablaMuestra(titulo, headers, rows, mapRow) {
    if (!rows || !rows.length) {
        return '<p class="text-muted small mb-2">' + esc(titulo) + ': sin filas.</p>';
    }
    var html = '<h6 class="mb-1">' + esc(titulo) + '</h6>';
    html += '<div class="table-responsive mb-3"><table class="table table-sm table-bordered bg-white">';
    html += '<thead><tr>';
    headers.forEach(function (h) {
        html += '<th>' + esc(h) + '</th>';
    });
    html += '</tr></thead><tbody>';
    rows.forEach(function (r) {
        html += '<tr>';
        mapRow(r).forEach(function (cell) {
            html += '<td>' + esc(cell) + '</td>';
        });
        html += '</tr>';
    });
    html += '</tbody></table></div>';
    return html;
}

function esc(v) {
    if (v === null || v === undefined) {
        return '';
    }
    return $('<div>').text(String(v)).html();
}
