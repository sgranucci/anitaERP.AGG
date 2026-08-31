(function ($) {
    'use strict';

    var estado = {
        conservar: null,
        absorber: null,
        preview: null
    };

    function csrfToken() {
        return $('meta[name="csrf-token"]').attr('content')
            || $('input[name="_token"]').first().val()
            || '';
    }

    function mostrarOverlay(titulo) {
        var overlay = document.getElementById('uif-unificar-overlay');
        if (!overlay) {
            return;
        }
        if (titulo) {
            var t = document.getElementById('uif-unificar-titulo');
            if (t) {
                t.textContent = titulo;
            }
        }
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }

    function ocultarOverlay() {
        var overlay = document.getElementById('uif-unificar-overlay');
        if (!overlay) {
            return;
        }
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }

    function esc(s) {
        return $('<div>').text(s == null ? '' : String(s)).html();
    }

    function renderFicha(targetId, data) {
        var $el = $(targetId);
        if (!data) {
            $el.addClass('d-none').empty();
            return;
        }
        var ultimo = data.ultimo_premio
            ? (data.ultimo_premio.fechaentrega || '') +
                (data.ultimo_premio.monto_fmt ? ' · $ ' + data.ultimo_premio.monto_fmt : '') +
                (data.ultimo_premio.juego ? ' · ' + data.ultimo_premio.juego : '')
            : '—';

        var html = ''
            + '<span class="origen-chip mb-2">' + esc(data.origen_label || data.anita_origen || '') + '</span>'
            + '<dl class="row uif-ficha-meta mb-2 mt-2">'
            + '<dt class="col-5">Documento</dt><dd class="col-7">' + esc(data.tipodocumento) + ' ' + esc(data.numerodocumento) + '</dd>'
            + '<dt class="col-5">Domicilio</dt><dd class="col-7">' + esc(data.domicilio || '—') + '</dd>'
            + '<dt class="col-5">Teléfono</dt><dd class="col-7">' + esc(data.telefono || '—') + '</dd>'
            + '<dt class="col-5">Email</dt><dd class="col-7">' + esc(data.email || '—') + '</dd>'
            + '<dt class="col-5">Anita ID</dt><dd class="col-7">' + esc(data.inroclienteid != null ? data.inroclienteid : '—') + '</dd>'
            + '<dt class="col-5">Último premio</dt><dd class="col-7">' + esc(ultimo) + '</dd>'
            + '</dl>'
            + '<div>'
            + '<span class="stat-pill"><i class="fa fa-trophy"></i> ' + esc(data.premios_count) + ' premios</span>'
            + '<span class="stat-pill"><i class="fa fa-paperclip"></i> ' + esc(data.archivos_count) + ' archivos</span>'
            + '<span class="stat-pill"><i class="fa fa-shield-alt"></i> ' + esc(data.riesgos_count) + ' riesgos</span>'
            + '</div>';

        $el.removeClass('d-none').html(html);
    }

    function setBanner(tipo, mensaje) {
        var $b = $('#banner-validacion');
        if (!mensaje) {
            $b.addClass('d-none').removeClass('alert-success alert-danger alert-warning alert-info').empty();
            return;
        }
        $b.removeClass('d-none alert-success alert-danger alert-warning alert-info')
            .addClass('alert-' + tipo)
            .html(mensaje);
    }

    function filaVacia(cols, texto) {
        return '<tr><td colspan="' + cols + '" class="text-center text-muted">' + esc(texto) + '</td></tr>';
    }

    function renderPreview(preview) {
        estado.preview = preview;
        var $btn = $('#btn-abrir-confirmar');

        if (!preview || !preview.ok) {
            $('#preview-contenido').addClass('d-none');
            $('#preview-vacio').removeClass('d-none');
            $btn.prop('disabled', true);
            if (preview && preview.errores && preview.errores.length) {
                setBanner('danger', '<i class="fa fa-times-circle"></i> ' + esc(preview.errores.join(' ')));
            }
            return;
        }

        if (preview.cross_origen) {
            var adv = (preview.advertencias && preview.advertencias.length)
                ? preview.advertencias.join(' ')
                : 'Clientes de distintas salas: los premios conservan su sala.';
            setBanner('warning',
                '<i class="fa fa-exclamation-triangle"></i> ' + esc(adv));
        } else {
            setBanner('success',
                '<i class="fa fa-check-circle"></i> Mismo origen (' +
                esc((preview.conservar && preview.conservar.origen_label) || '') +
                '). Listo para unificar.');
        }

        $('#preview-vacio').addClass('d-none');
        $('#preview-contenido').removeClass('d-none');

        var r = preview.resumen || {};
        $('#preview-resumen-pills').html(
            '<span class="stat-pill"><i class="fa fa-trophy"></i> Premios a mover: <strong>' + esc(r.premios_mover || 0) + '</strong></span>' +
            '<span class="stat-pill"><i class="fa fa-paperclip"></i> Archivos: <strong>' + esc(r.archivos_mover || 0) + '</strong></span>' +
            '<span class="stat-pill"><i class="fa fa-shield-alt"></i> Riesgos: <strong>' + esc(r.riesgos_mover || 0) + '</strong></span>' +
            ((r.premios_conflicto || 0) > 0
                ? '<span class="stat-pill text-danger">Premios conflicto: <strong>' + esc(r.premios_conflicto) + '</strong></span>'
                : '') +
            ((r.riesgos_conflicto || 0) > 0
                ? '<span class="stat-pill text-danger">Riesgos conflicto: <strong>' + esc(r.riesgos_conflicto) + '</strong></span>'
                : '')
        );

        var premiosHtml = '';
        (preview.premios || []).forEach(function (p) {
            premiosHtml += '<tr>'
                + '<td>' + esc(p.id) + '</td>'
                + '<td>' + esc(p.fechaentrega) + '</td>'
                + '<td class="text-right">$ ' + esc(p.monto_fmt) + '</td>'
                + '<td>' + esc(p.sala || '—') + '</td>'
                + '<td>' + esc(p.juego) + '</td>'
                + '<td>' + esc(p.anita_inropremioid != null ? p.anita_inropremioid : '—') + '</td>'
                + '</tr>';
        });
        $('#preview-premios').html(premiosHtml || filaVacia(6, 'Sin premios para mover'));

        if ((preview.premios_conflicto || []).length) {
            var pc = '';
            preview.premios_conflicto.forEach(function (p) {
                pc += '<tr>'
                    + '<td>' + esc(p.id) + '</td>'
                    + '<td>' + esc(p.fechaentrega) + '</td>'
                    + '<td class="text-right">$ ' + esc(p.monto_fmt) + '</td>'
                    + '<td>' + esc(p.sala || '—') + '</td>'
                    + '<td>' + esc(p.anita_inropremioid) + '</td>'
                    + '</tr>';
            });
            $('#preview-premios-conflicto').html(pc);
            $('#bloque-premios-conflicto').removeClass('d-none');
        } else {
            $('#bloque-premios-conflicto').addClass('d-none');
        }

        var archHtml = '';
        (preview.archivos || []).forEach(function (a) {
            archHtml += '<tr><td>' + esc(a.id) + '</td><td>' + esc(a.nombrearchivo) + '</td></tr>';
        });
        $('#preview-archivos').html(archHtml || filaVacia(2, 'Sin archivos para mover'));

        var riesHtml = '';
        (preview.riesgos || []).forEach(function (x) {
            riesHtml += '<tr><td>' + esc(x.id) + '</td><td>' + esc(x.periodo) + '</td><td>' + esc(x.riesgo) + '</td></tr>';
        });
        $('#preview-riesgos').html(riesHtml || filaVacia(3, 'Sin riesgos para mover'));

        if ((preview.riesgos_conflicto || []).length) {
            var rc = '';
            preview.riesgos_conflicto.forEach(function (x) {
                rc += '<tr><td>' + esc(x.id) + '</td><td>' + esc(x.periodo) + '</td><td>' + esc(x.riesgo) + '</td></tr>';
            });
            $('#preview-riesgos-conflicto').html(rc);
            $('#bloque-riesgos-conflicto').removeClass('d-none');
        } else {
            $('#bloque-riesgos-conflicto').addClass('d-none');
        }

        if (preview.copiara_inroclienteid) {
            $('#preview-nota-inro').text(
                'Se copiará el ID Anita ' + preview.copiara_inroclienteid + ' al cliente conservado (hoy no lo tiene).'
            );
        } else {
            $('#preview-nota-inro').text('');
        }

        $btn.prop('disabled', false);
    }

    function solicitarPreview() {
        var conservarId = parseInt($('#cliente_uif_conservar_id').val(), 10) || 0;
        var absorberId = parseInt($('#cliente_uif_absorber_id').val(), 10) || 0;

        if (!conservarId || !absorberId) {
            estado.preview = null;
            $('#preview-contenido').addClass('d-none');
            $('#preview-vacio').removeClass('d-none');
            $('#btn-abrir-confirmar').prop('disabled', true);
            if (conservarId || absorberId) {
                setBanner('info', 'Seleccione el otro cliente para validar y previsualizar.');
            } else {
                setBanner(null);
            }
            return;
        }

        if (conservarId === absorberId) {
            renderPreview({ ok: false, errores: ['No se puede unificar un cliente consigo mismo.'] });
            return;
        }

        $.ajax({
            url: (window.uifUnificarUrls && window.uifUnificarUrls.preview) || (carpetaBase + '/uif/unificar-clientes/preview'),
            type: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
            data: {
                _token: csrfToken(),
                conservar_id: conservarId,
                absorber_id: absorberId
            }
        })
        .done(function (data) {
            renderPreview(data);
        })
        .fail(function (xhr) {
            var msg = (xhr.responseJSON && xhr.responseJSON.message)
                || 'No se pudo obtener el preview.';
            renderPreview({ ok: false, errores: [msg] });
        });
    }

    window.onClienteUifElegido = function (prefix, data) {
        if (prefix === 'conservar') {
            estado.conservar = data;
            renderFicha('#ficha-conservar', data);
        } else if (prefix === 'absorber') {
            estado.absorber = data;
            renderFicha('#ficha-absorber', data);
        }
        solicitarPreview();
    };

    function actualizarBotonConfirmacion() {
        var ok = ($('#confirmacion-unificar').val() || '').toUpperCase().trim() === 'UNIFICAR';
        $('#btn-ejecutar-unificar').prop('disabled', !ok);
    }

    $(function () {
        if (typeof activa_eventos_consultacliente_uif === 'function') {
            activa_eventos_consultacliente_uif();
        }

        window.payloadExtraConsultaClienteUif = function () {
            // Cross-sala: no restringir el modal al origen del primero elegido.
            return {};
        };

        $('#btn-abrir-confirmar').on('click', function () {
            if (!estado.preview || !estado.preview.ok) {
                return;
            }
            var c = estado.preview.conservar || {};
            var a = estado.preview.absorber || {};
            var r = estado.preview.resumen || {};
            $('#confirmar-resumen-lista').html(
                '<li>Conservar: <strong>#' + esc(c.id) + '</strong> ' + esc(c.nombre) +
                ' (DNI ' + esc(c.numerodocumento) + ', ' + esc(c.origen_label) + ')</li>' +
                '<li>Absorber y eliminar: <strong>#' + esc(a.id) + '</strong> ' + esc(a.nombre) +
                ' (DNI ' + esc(a.numerodocumento) + ', ' + esc(a.origen_label) + ')</li>' +
                '<li>Mover: ' + esc(r.premios_mover || 0) + ' premios (conservan su sala), ' +
                esc(r.archivos_mover || 0) + ' archivos, ' + esc(r.riesgos_mover || 0) + ' riesgos</li>' +
                (estado.preview.cross_origen
                    ? '<li class="text-warning"><strong>Cross-sala:</strong> la ficha queda con origen del conservado; el sync del origen absorbido puede recrear esa ficha.</li>'
                    : '')
            );
            $('#confirmacion-unificar').val('');
            actualizarBotonConfirmacion();
            $('#modal-confirmar-unificar').modal('show');
        });

        $('#confirmacion-unificar').on('input keyup', actualizarBotonConfirmacion);

        $('#btn-ejecutar-unificar').on('click', function () {
            if (($('#confirmacion-unificar').val() || '').toUpperCase().trim() !== 'UNIFICAR') {
                return;
            }
            $('#modal-confirmar-unificar').modal('hide');
            mostrarOverlay('Unificando clientes…');

            $.ajax({
                url: (window.uifUnificarUrls && window.uifUnificarUrls.ejecutar) || (carpetaBase + '/uif/unificar-clientes'),
                type: 'POST',
                dataType: 'json',
                headers: { 'X-CSRF-TOKEN': csrfToken() },
                data: {
                    _token: csrfToken(),
                    conservar_id: $('#cliente_uif_conservar_id').val(),
                    absorber_id: $('#cliente_uif_absorber_id').val(),
                    confirmacion: 'UNIFICAR'
                }
            })
            .done(function (data) {
                ocultarOverlay();
                if (data && data.ok && data.redirect) {
                    window.location.href = data.redirect;
                    return;
                }
                alert((data && data.mensaje) || 'No se pudo unificar.');
            })
            .fail(function (xhr) {
                ocultarOverlay();
                var msg = (xhr.responseJSON && (xhr.responseJSON.mensaje || xhr.responseJSON.message))
                    || 'Error al unificar.';
                alert(msg);
            });
        });

        window.addEventListener('pageshow', ocultarOverlay);
    });
})(jQuery);
