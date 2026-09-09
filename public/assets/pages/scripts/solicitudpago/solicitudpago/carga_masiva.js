/**
 * Carga masiva SP desde CSV Anita (p-cargasolpm).
 * Modal en index: subir → preview (con asiento expandible) → confirmar.
 */
(function ($) {
    'use strict';

    var token = null;
    var aGenerar = 0;
    var filasPreview = [];
    var filtroActual = 'todas';

    function csrfToken() {
        var $t = $('input[name="_token"]').first();
        if ($t.length) {
            return $t.val();
        }
        var meta = $('meta[name="csrf-token"]').attr('content');
        return meta || '';
    }

    function overlayMostrar(titulo, subtitulo) {
        var $o = $('#sp-carga-masiva-overlay');
        if (titulo) {
            $('#sp-carga-masiva-overlay-titulo').text(titulo);
        }
        if (subtitulo) {
            $('#sp-carga-masiva-overlay-subtitulo').text(subtitulo);
        }
        $o.removeClass('d-none').css('display', 'flex').attr('aria-hidden', 'false');
    }

    function overlayOcultar() {
        var $o = $('#sp-carga-masiva-overlay');
        $o.addClass('d-none').css('display', '').attr('aria-hidden', 'true');
    }

    function fmtMonto(n) {
        var v = Number(n) || 0;
        return v.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function esc(s) {
        return $('<div>').text(s == null ? '' : String(s)).html();
    }

    function mostrarPaso(paso) {
        $('#sp-carga-paso-archivo').toggleClass('d-none', paso !== 'archivo');
        $('#sp-carga-paso-preview').toggleClass('d-none', paso !== 'preview');
        $('#sp-carga-paso-resultado').toggleClass('d-none', paso !== 'resultado');

        $('#sp-carga-btn-analizar').toggleClass('d-none', paso !== 'archivo');
        $('#sp-carga-btn-volver').toggleClass('d-none', paso !== 'preview');
        $('#sp-carga-btn-generar').toggleClass('d-none', paso !== 'preview');
        $('#sp-carga-btn-cancelar').toggleClass('d-none', paso === 'resultado');
        $('#sp-carga-btn-cerrar').toggleClass('d-none', paso !== 'resultado');
    }

    function resetModal() {
        token = null;
        aGenerar = 0;
        filasPreview = [];
        filtroActual = 'todas';
        $('#sp-carga-archivo').val('');
        $('#sp-carga-archivo-error').addClass('d-none').text('');
        $('#sp-carga-resumen').empty();
        $('#sp-carga-por-empresa').empty();
        $('#sp-carga-errores').empty();
        $('#sp-carga-tabla tbody').empty();
        $('#sp-carga-resultado-body').empty();
        $('#sp-carga-preview-estado').removeClass().addClass('badge badge-secondary').text('—');
        $('#sp-carga-filtro-info').text('');
        $('[data-sp-filtro]').removeClass('active');
        $('[data-sp-filtro="todas"]').addClass('active');
        $('#sp-carga-btn-generar').prop('disabled', true).html('<i class="fa fa-check"></i> Generar solicitudes');
        mostrarPaso('archivo');
    }

    function htmlAsientoDetalle(f) {
        var cuentas = f.cuentas || [];
        if (!cuentas.length) {
            return '<div class="small text-muted mb-0">Sin líneas de asiento.</div>';
        }

        var html =
            '<div class="small mb-2">' +
            'Debe: <strong>$ ' + fmtMonto(f.total_debe) + '</strong> · ' +
            'Haber: <strong>$ ' + fmtMonto(f.total_haber) + '</strong> · ';
        if (f.balanceado) {
            html += '<span class="badge badge-success">Balanceado</span>';
        } else {
            html += '<span class="badge badge-danger">Desbalance</span>';
        }
        html +=
            ' · Monto SP: <strong>$ ' + fmtMonto(f.monto) + '</strong>' +
            '</div>';

        html +=
            '<div class="table-responsive"><table class="table table-sm table-bordered mb-0 bg-white">' +
            '<thead style="background:#D6EAF8;color:#17202A"><tr>' +
            '<th>Cuenta</th><th>Nombre</th><th class="text-center">D/H</th>' +
            '<th class="text-right">Debe</th><th class="text-right">Haber</th><th>Origen</th>' +
            '</tr></thead><tbody>';

        cuentas.forEach(function (c) {
            var dh = String(c.debe_haber || 'D').toUpperCase() === 'H' ? 'H' : 'D';
            var debe = dh === 'D' ? fmtMonto(c.monto) : '';
            var haber = dh === 'H' ? fmtMonto(c.monto) : '';
            var origen = c.desde_concepto
                ? '<span class="badge badge-info">Concepto</span>'
                : '<span class="badge badge-secondary">CSV</span>';
            html +=
                '<tr>' +
                '<td>' + esc(c.codigo) + '</td>' +
                '<td><small>' + esc(c.nombre || '—') + '</small></td>' +
                '<td class="text-center">' + esc(dh) + '</td>' +
                '<td class="text-right">' + debe + '</td>' +
                '<td class="text-right">' + haber + '</td>' +
                '<td>' + origen + '</td>' +
                '</tr>';
        });

        html += '</tbody></table></div>';

        if (f.errores && f.errores.length) {
            html += '<ul class="mb-0 mt-2 pl-3 text-danger small">';
            f.errores.forEach(function (e) {
                html += '<li>' + esc(e) + '</li>';
            });
            html += '</ul>';
        }

        return html;
    }

    function filaPasaFiltro(f) {
        if (filtroActual === 'ok') {
            return !!f.ok;
        }
        if (filtroActual === 'error') {
            return !f.ok;
        }
        return true;
    }

    function renderTablaFilas() {
        var $tb = $('#sp-carga-tabla tbody').empty();
        var visibles = 0;

        filasPreview.forEach(function (f, idx) {
            if (!filaPasaFiltro(f)) {
                return;
            }
            visibles++;

            var detalle = String(f.detalle || '');
            if (detalle.length > 48) {
                detalle = detalle.substring(0, 45) + '…';
            }

            var badge = f.ok
                ? '<span class="badge badge-success">OK</span>'
                : '<span class="badge badge-danger">Error</span>';

            var balBadge = '';
            if (f.n_cuentas > 0) {
                balBadge = f.balanceado
                    ? ' <span class="badge badge-light border text-success" title="Asiento balanceado">' +
                      esc(f.n_cuentas) + ' ctas</span>'
                    : ' <span class="badge badge-light border text-danger" title="Asiento desbalanceado">' +
                      esc(f.n_cuentas) + ' ctas</span>';
            } else {
                balBadge = ' <span class="badge badge-light border">0</span>';
            }

            var detId = 'sp-carga-det-' + idx;
            $tb.append(
                '<tr class="sp-carga-fila-cab ' + (f.ok ? '' : 'table-danger') + '" data-idx="' + idx + '">' +
                '<td class="text-center">' +
                '<button type="button" class="btn btn-link btn-sm p-0 sp-carga-toggle" data-target="#' + detId + '" ' +
                'title="Ver asiento" aria-expanded="false">' +
                '<i class="fa fa-chevron-right"></i></button></td>' +
                '<td>' + esc(f.nro_linea) + '</td>' +
                '<td>' + esc(f.empresa_codigo) + (f.empresa_nombre ? ' <small class="text-muted">' + esc(f.empresa_nombre) + '</small>' : '') + '</td>' +
                '<td>' + esc(f.proveedor_codigo) + ' ' + esc(f.proveedor_nombre) + '</td>' +
                '<td>' + esc(f.concepto_codigo) + ' ' + esc(f.concepto_nombre) + '</td>' +
                '<td title="' + esc(f.detalle) + '">' + esc(detalle) + '</td>' +
                '<td>' + esc(f.fecha_vencimiento || '—') + '</td>' +
                '<td class="text-right">' + fmtMonto(f.monto) + '</td>' +
                '<td class="text-center">' + balBadge + '</td>' +
                '<td>' + badge + '</td>' +
                '</tr>' +
                '<tr class="sp-carga-fila-det d-none" id="' + detId + '">' +
                '<td colspan="10" class="bg-light">' + htmlAsientoDetalle(f) + '</td>' +
                '</tr>'
            );
        });

        var total = filasPreview.length;
        var label = filtroActual === 'ok' ? 'OK' : (filtroActual === 'error' ? 'con error' : '');
        $('#sp-carga-filtro-info').text(
            visibles === total
                ? (total + ' fila' + (total === 1 ? '' : 's'))
                : (visibles + ' de ' + total + (label ? ' (' + label + ')' : ''))
        );
    }

    function renderPreview(data) {
        token = data.token;
        var r = data.resumen || {};
        aGenerar = Number(r.a_generar) || 0;
        filasPreview = data.filas || [];
        filtroActual = 'todas';
        $('[data-sp-filtro]').removeClass('active');
        $('[data-sp-filtro="todas"]').addClass('active');

        var $estado = $('#sp-carga-preview-estado');
        if (aGenerar > 0 && !(r.con_error > 0)) {
            $estado.removeClass().addClass('badge badge-success').text('Listo para generar');
        } else if (aGenerar > 0) {
            $estado.removeClass().addClass('badge badge-warning').text('Revisar filas con error');
        } else {
            $estado.removeClass().addClass('badge badge-danger').text('Nada para generar');
        }

        var cards = [
            { label: 'Leídas', value: r.leidas, cls: 'secondary' },
            { label: 'A generar', value: r.a_generar, cls: 'success' },
            { label: 'Con error', value: r.con_error, cls: r.con_error > 0 ? 'danger' : 'secondary' },
            { label: 'Monto total', value: '$ ' + fmtMonto(r.monto_total), cls: 'info' },
            { label: 'Empresas', value: r.empresas, cls: 'primary' }
        ];
        if (r.codigo_desde_estimado) {
            cards.push({
                label: 'Códigos estimados',
                value: r.codigo_desde_estimado + ' → ' + r.codigo_hasta_estimado,
                cls: 'dark'
            });
        }

        var htmlCards = '';
        cards.forEach(function (c) {
            htmlCards +=
                '<div class="col-6 col-md-4 col-lg-2 mb-2">' +
                '<div class="border rounded p-2 h-100 text-center bg-light">' +
                '<div class="small text-muted">' + esc(c.label) + '</div>' +
                '<div class="h5 mb-0 text-' + c.cls + '">' + esc(c.value) + '</div>' +
                '</div></div>';
        });
        $('#sp-carga-resumen').html(htmlCards);

        var porEmp = data.por_empresa || [];
        if (porEmp.length) {
            var pe =
                '<div class="small font-weight-bold mb-1">Totales por empresa</div>' +
                '<div class="table-responsive"><table class="table table-sm table-bordered mb-0">' +
                '<thead style="background:#85C1E9;color:#17202A"><tr>' +
                '<th>Empresa</th><th class="text-right">Cantidad</th><th class="text-right">Monto</th>' +
                '</tr></thead><tbody>';
            porEmp.forEach(function (e) {
                pe +=
                    '<tr><td>' + esc(e.empresa_codigo) + ' — ' + esc(e.empresa_nombre) + '</td>' +
                    '<td class="text-right">' + esc(e.cantidad) + '</td>' +
                    '<td class="text-right">$ ' + fmtMonto(e.monto) + '</td></tr>';
            });
            pe += '</tbody></table></div>';
            $('#sp-carga-por-empresa').html(pe);
        } else {
            $('#sp-carga-por-empresa').empty();
        }

        var errs = data.errores || [];
        if (errs.length) {
            var eh = '<div class="alert alert-warning py-2 mb-0"><strong>' + errs.length +
                ' observación(es)</strong><ul class="mb-0 pl-3 small" style="max-height:120px;overflow:auto">';
            errs.slice(0, 50).forEach(function (m) {
                eh += '<li>' + esc(m) + '</li>';
            });
            if (errs.length > 50) {
                eh += '<li>… y ' + (errs.length - 50) + ' más</li>';
            }
            eh += '</ul></div>';
            $('#sp-carga-errores').html(eh);
        } else {
            $('#sp-carga-errores').html(
                '<div class="alert alert-success py-2 mb-0 small"><i class="fa fa-check-circle"></i> Todas las filas son válidas.</div>'
            );
        }

        renderTablaFilas();

        $('#sp-carga-btn-generar')
            .prop('disabled', aGenerar < 1)
            .html('<i class="fa fa-check"></i> Generar ' + aGenerar + ' solicitud' + (aGenerar === 1 ? '' : 'es'));

        mostrarPaso('preview');
    }

    function renderResultado(data) {
        var desde = data.desde_codigo;
        var hasta = data.hasta_codigo;
        var creadas = data.creadas || 0;
        var base = $('#modal-carga-masiva-sp').data('editar-url-base');
        var ids = data.ids || [];
        var codigos = data.codigos || [];

        var html =
            '<div class="text-center py-3">' +
            '<i class="fa fa-check-circle text-success fa-3x mb-3"></i>' +
            '<h4 class="mb-2">Se generaron ' + esc(creadas) + ' solicitud' + (creadas === 1 ? '' : 'es') + '</h4>';

        if (desde && hasta) {
            html += '<p class="lead mb-3">Códigos <strong>' + esc(desde) + '</strong> a <strong>' + esc(hasta) + '</strong></p>';
        }

        if (ids.length) {
            html += '<p class="small mb-2">Abrir:</p><div class="mb-3">';
            var mostrar = Math.min(ids.length, 5);
            for (var i = 0; i < mostrar; i++) {
                html +=
                    '<a class="btn btn-sm btn-outline-primary mr-1 mb-1" target="_blank" rel="noopener" href="' +
                    esc(base) + '/' + esc(ids[i]) + '/editar">' +
                    'SP ' + esc(codigos[i] || ids[i]) + '</a>';
            }
            if (ids.length > 5) {
                html += '<span class="text-muted small">… y ' + (ids.length - 5) + ' más en el listado</span>';
            }
            html += '</div>';
        }

        var errs = data.errores_runtime || [];
        if (errs.length) {
            html += '<div class="alert alert-warning text-left"><strong>Errores en generación:</strong><ul class="mb-0">';
            errs.forEach(function (e) {
                html += '<li>' + esc(e) + '</li>';
            });
            html += '</ul></div>';
        }

        html += '</div>';
        $('#sp-carga-resultado-body').html(html);
        mostrarPaso('resultado');
    }

    function analizar() {
        var $err = $('#sp-carga-archivo-error').addClass('d-none').text('');
        var file = $('#sp-carga-archivo')[0].files[0];
        if (!file) {
            $err.removeClass('d-none').text('Seleccione un archivo CSV.');
            return;
        }

        var fd = new FormData();
        fd.append('archivo', file);
        fd.append('_token', csrfToken());

        var url = $('#modal-carga-masiva-sp').data('preview-url');
        overlayMostrar('Analizando CSV…', 'Validando maestros, concepto y asiento.');

        $.ajax({
            url: url,
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function (resp) {
            if (!resp || !resp.ok) {
                $err.removeClass('d-none').text((resp && resp.message) || 'No se pudo analizar el archivo.');
                return;
            }
            renderPreview(resp);
        }).fail(function (xhr) {
            var msg = (xhr.responseJSON && (xhr.responseJSON.message || (xhr.responseJSON.errors && xhr.responseJSON.errors.archivo && xhr.responseJSON.errors.archivo[0]))) ||
                'Error al analizar el archivo.';
            $err.removeClass('d-none').text(msg);
        }).always(function () {
            overlayOcultar();
        });
    }

    function generar() {
        if (!token || aGenerar < 1) {
            return;
        }
        if (!window.confirm('¿Generar ' + aGenerar + ' solicitud' + (aGenerar === 1 ? '' : 'es') + ' de pago en estado AUTORIZADA?')) {
            return;
        }

        var url = $('#modal-carga-masiva-sp').data('confirmar-url');
        overlayMostrar(
            'Generando solicitudes…',
            'Puede demorar según la cantidad. No cierre la página.'
        );

        $.ajax({
            url: url,
            method: 'POST',
            data: {
                _token: csrfToken(),
                token: token
            },
            dataType: 'json'
        }).done(function (resp) {
            if (!resp || !resp.ok) {
                alert((resp && resp.message) || 'No se pudieron generar las solicitudes.');
                return;
            }
            renderResultado(resp);
        }).fail(function (xhr) {
            var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Error al generar las solicitudes.';
            alert(msg);
        }).always(function () {
            overlayOcultar();
        });
    }

    $(function () {
        $('#btn-carga-masiva-sp').on('click', function () {
            resetModal();
            $('#modal-carga-masiva-sp').modal('show');
        });

        $('#sp-carga-btn-analizar').on('click', analizar);
        $('#sp-carga-btn-volver').on('click', function () {
            token = null;
            mostrarPaso('archivo');
        });
        $('#sp-carga-btn-generar').on('click', generar);
        $('#sp-carga-btn-cerrar').on('click', function () {
            window.location.reload();
        });

        $(document).on('click', '#sp-carga-tabla .sp-carga-toggle', function (e) {
            e.preventDefault();
            var $btn = $(this);
            var $det = $($btn.data('target'));
            var abierto = !$det.hasClass('d-none');
            $det.toggleClass('d-none', abierto);
            $btn.attr('aria-expanded', abierto ? 'false' : 'true');
            $btn.find('i').toggleClass('fa-chevron-right', abierto).toggleClass('fa-chevron-down', !abierto);
        });

        $(document).on('click', '[data-sp-filtro]', function () {
            filtroActual = String($(this).data('sp-filtro') || 'todas');
            $('[data-sp-filtro]').removeClass('active');
            $(this).addClass('active');
            renderTablaFilas();
        });

        $('#modal-carga-masiva-sp').on('hidden.bs.modal', function () {
            if ($('#sp-carga-paso-resultado').hasClass('d-none') === false) {
                window.location.reload();
            }
        });

        window.addEventListener('pageshow', overlayOcultar);
    });
})(jQuery);
