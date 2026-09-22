(function ($) {
    'use strict';

    function csrf() {
        return $('meta[name="csrf-token"]').attr('content') || $('#porteria-form-dni input[name=_token]').val();
    }

    function alerta(texto, ok) {
        var $el = $('#porteria-alerta');
        if (!texto) {
            $el.prop('hidden', true).text('');
            return;
        }
        $el.prop('hidden', false).toggleClass('is-ok', !!ok).text(texto);
    }

    function setTexto($el, valor) {
        $el.text(valor || '—');
    }

    function esc(texto) {
        return $('<div>').text(texto == null ? '' : String(texto)).html();
    }

    function badgeClase(codigo) {
        var map = {
            PENDIENTE: 'warning',
            AUTORIZADO: 'success',
            INGRESADO: 'info',
            FINALIZADO: 'secondary',
            RECHAZADO: 'danger'
        };
        return map[codigo] || 'light';
    }

    function pintarTicket(p) {
        if (!p) {
            $('#porteria-ticket').removeClass('is-rechazado is-pendiente').prop('hidden', true);
            $('#porteria-acciones-autorizar').prop('hidden', true);
            return;
        }
        $('#porteria-ticket').prop('hidden', false);
        $('#porteria-persona-id').val(p.persona_id);
        setTexto($('#porteria-nombre'), p.nombre);
        setTexto($('#porteria-doc'), p.documento);
        setTexto($('#porteria-empresa'), p.empresa);
        setTexto($('#porteria-ticket-id'), p.ticket_id);
        var $estado = $('#porteria-estado');
        $estado.text(p.estado || '—')
            .removeClass('badge-warning badge-success badge-info badge-secondary badge-danger badge-light')
            .addClass('badge badge-' + badgeClase(p.estado_codigo));
        $('#porteria-ticket')
            .toggleClass('is-rechazado', p.estado_codigo === 'RECHAZADO')
            .toggleClass('is-pendiente', p.estado_codigo === 'PENDIENTE');
        setTexto($('#porteria-fecha'), p.fecha);
        setTexto($('#porteria-proveedor'), p.proveedor);
        setTexto($('#porteria-motivo'), p.motivo);
        setTexto($('#porteria-punto'), p.punto);
        setTexto($('#porteria-sector'), p.sector);
        setTexto($('#porteria-area'), p.area);
        setTexto($('#porteria-patente'), p.patente);
        setTexto($('#porteria-titulo'), p.titulo);
        $('#porteria-comentario').text(p.comentario || '');
        $('#porteria-btn-entro').prop('disabled', !p.puede_entro);
        $('#porteria-btn-salio').prop('disabled', !p.puede_salio);
        var puedeRevisar = !!p.puede_autorizar_puerta;
        $('#porteria-acciones-autorizar').prop('hidden', !puedeRevisar);
        if (p.mensaje_bloqueo && !p.puede_entro) {
            alerta(p.mensaje_bloqueo, false);
        }
        var reloj = [];
        if (p.hora_ingreso) {
            reloj.push('Entró ' + p.hora_ingreso);
        }
        if (p.hora_egreso) {
            reloj.push('Salió ' + p.hora_egreso);
        }
        if (p.minutos_en_planta) {
            reloj.push(p.minutos_en_planta + ' min en planta');
        }
        $('#porteria-reloj').text(reloj.join(' · '));
    }

    function pintarGrilla(filas) {
        if (!$.isArray(filas)) {
            return;
        }
        var html = '';
        var enPlanta = 0;
        filas.forEach(function (p) {
            if (p.en_planta) {
                enPlanta += 1;
            }
            html += '<tr class="' + (p.en_planta ? 'porteria-fila-en-planta' : '') + '">' +
                '<td>' + (p.ticket_id || '') + '</td>' +
                '<td>' + (p.empresa || '') + '</td>' +
                '<td>' + (p.documento || '') + '</td>' +
                '<td>' + (p.nombre || '') + '</td>' +
                '<td>' + (p.proveedor || '') + '</td>' +
                '<td>' + (p.motivo || '') + '</td>' +
                '<td>' + (p.punto || '') + '</td>' +
                '<td><span class="badge badge-' + badgeClase(p.estado_codigo) + '">' + (p.estado || '') + '</span></td>' +
                '<td>' + (p.hora_ingreso || '') + '</td>' +
                '<td>' + (p.hora_egreso || '') + '</td>' +
                '<td>' + (p.minutos_en_planta || '') + '</td>' +
                '</tr>';
        });
        $('#porteria-tbody').html(html);
        $('#porteria-en-planta-count').text(enPlanta + ' en planta');
    }

    function extraEmpresa() {
        var $root = $('.porteria');
        var extra = {};
        if ($root.data('empresa-todas') == '1' || $root.data('empresa-todas') === 1) {
            extra.empresa_todas = 1;
        } else if ($root.data('empresa-id')) {
            extra.empresa_id = $root.data('empresa-id');
        }
        return extra;
    }

    function postJson(url, data) {
        return $.ajax({
            url: url,
            method: 'POST',
            data: $.extend({ _token: csrf() }, extraEmpresa(), data),
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
    }

    function pintarArchivosModal(archivos) {
        var $wrap = $('#porteria-auth-archivos').empty();
        var $vacio = $('#porteria-auth-sin-archivos');
        if (!$.isArray(archivos) || !archivos.length) {
            $vacio.removeClass('d-none');
            return;
        }
        $vacio.addClass('d-none');
        archivos.forEach(function (a) {
            var nombre = esc(a.nombre_original || 'archivo');
            var etiqueta = esc(a.tipo_etiqueta || '');
            var vence = a.vencimiento ? esc(a.vencimiento) : '';
            var tituloDoc = etiqueta
                ? '<div class="font-weight-bold mb-1">' + etiqueta +
                    (vence
                        ? ' <span class="badge badge-' + (a.vencido ? 'danger' : 'secondary') + '">Vence ' + vence + '</span>'
                        : '') +
                    '</div>'
                : '';
            var urlAbrir = a.url_abrir || '#';
            var urlDesc = a.url_descargar || urlAbrir;
            var preview;
            if (a.es_imagen) {
                preview = '<div class="text-center bg-light rounded mb-2" style="min-height:120px;">' +
                    '<a href="' + esc(urlAbrir) + '" target="_blank" rel="noopener noreferrer">' +
                    '<img src="' + esc(urlAbrir) + '" alt="" class="img-fluid rounded" style="max-height:160px;object-fit:contain;">' +
                    '</a></div>';
            } else if (a.es_pdf) {
                preview = '<div class="mb-2" style="min-height:180px;">' +
                    '<iframe src="' + esc(urlAbrir) + '" class="w-100 rounded border-0 bg-secondary" style="height:180px;" title="Vista previa PDF"></iframe>' +
                    '</div>';
            } else {
                preview = '<div class="text-center text-muted py-4 mb-2 bg-light rounded">' +
                    '<i class="fa fa-file-o fa-3x"></i>' +
                    '<div class="small mt-2">Vista previa no disponible</div></div>';
            }
            $wrap.append(
                '<div class="col-md-6 mb-3">' +
                '<div class="card card-outline card-secondary h-100 mb-0">' +
                '<div class="card-body p-2 d-flex flex-column">' +
                tituloDoc +
                '<div class="small text-truncate mb-2" title="' + nombre + '">' +
                '<i class="fa fa-paperclip text-muted mr-1"></i>' + nombre + '</div>' +
                preview +
                '<div class="mt-auto pt-1">' +
                '<a href="' + esc(urlDesc) + '" class="btn btn-sm btn-outline-primary" download="' + nombre + '">' +
                '<i class="fa fa-download"></i> Descargar</a> ' +
                '<a href="' + esc(urlAbrir) + '" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener noreferrer">' +
                '<i class="fa fa-external-link-alt"></i> Abrir</a>' +
                '</div></div></div></div>'
            );
        });
    }

    function pintarDetalleModal(d) {
        setTexto($('#porteria-auth-ticket-id'), '#' + (d.ticket_id || '—'));
        setTexto($('#porteria-auth-nombre'), d.nombre);
        setTexto($('#porteria-auth-doc'), d.documento);
        setTexto($('#porteria-auth-empresa'), d.empresa);
        setTexto($('#porteria-auth-proveedor'), d.proveedor);
        setTexto($('#porteria-auth-solicitante'), d.generado_por);
        setTexto($('#porteria-auth-fecha'), d.fecha);
        setTexto($('#porteria-auth-motivo'), d.motivo);
        setTexto($('#porteria-auth-punto'), d.punto);
        setTexto($('#porteria-auth-sector'), d.sector);
        setTexto($('#porteria-auth-area'), d.area);
        setTexto($('#porteria-auth-patente'), d.patente);
        setTexto($('#porteria-auth-titulo'), d.titulo);
        setTexto($('#porteria-auth-comentario'), d.comentario);
        pintarArchivosModal(d.archivos || []);
    }

    function mostrarVistaDatos() {
        $('#porteria-auth-vista-datos').removeClass('d-none');
        $('#porteria-auth-vista-rechazo').addClass('d-none');
        $('#porteria-auth-footer-acciones').removeClass('d-none');
        $('#porteria-auth-footer-rechazo').addClass('d-none');
        $('#porteria-auth-error').addClass('d-none').text('');
        $('#porteria-auth-motivo-rechazo').val('');
    }

    function mostrarVistaRechazo() {
        $('#porteria-auth-vista-datos').addClass('d-none');
        $('#porteria-auth-vista-rechazo').removeClass('d-none');
        $('#porteria-auth-footer-acciones').addClass('d-none');
        $('#porteria-auth-footer-rechazo').removeClass('d-none');
        $('#porteria-auth-error').addClass('d-none').text('');
        $('#porteria-auth-motivo-rechazo').trigger('focus');
    }

    function errorModal(texto) {
        var $el = $('#porteria-auth-error');
        if (!texto) {
            $el.addClass('d-none').text('');
            return;
        }
        $el.removeClass('d-none').text(texto);
    }

    function setBotonesModalDisabled(disabled) {
        $('#porteria-auth-btn-autorizar, #porteria-auth-btn-autorizar-ingresar, #porteria-auth-btn-rechazar, #porteria-auth-btn-confirmar-rechazo')
            .prop('disabled', !!disabled);
    }

    $(function () {
        var $root = $('.porteria');
        if (!$root.length) {
            return;
        }

        var urlBuscar = $root.data('url-buscar');
        var urlEntro = $root.data('url-entro');
        var urlSalio = $root.data('url-salio');
        var urlDetalle = $root.data('url-detalle-pendiente');
        var urlAutorizar = $root.data('url-autorizar-puerta');
        var urlAutorizarIngresar = $root.data('url-autorizar-e-ingresar');
        var urlRechazar = $root.data('url-rechazar-puerta');
        var puedeAutorizarPuerta = $root.data('puede-autorizar-puerta') == '1'
            || $root.data('puede-autorizar-puerta') === 1;
        var abriendoModal = false;

        function contarEnPlantaInicial() {
            var n = $('#porteria-tbody tr.porteria-fila-en-planta').length;
            $('#porteria-en-planta-count').text(n + ' en planta');
        }
        contarEnPlantaInicial();

        function abrirModalAutorizacion(personaId, autoAbrir) {
            if (!puedeAutorizarPuerta || !personaId || !urlDetalle) {
                return;
            }
            if (abriendoModal) {
                return;
            }
            abriendoModal = true;
            mostrarVistaDatos();
            errorModal('');
            setBotonesModalDisabled(true);
            postJson(urlDetalle, { persona_id: personaId })
                .done(function (res) {
                    pintarDetalleModal(res.detalle || {});
                    setBotonesModalDisabled(false);
                    $('#porteriaAutorizacionModal').modal('show');
                })
                .fail(function (xhr) {
                    var msg = (xhr.responseJSON && xhr.responseJSON.mensaje)
                        || 'No se pudo cargar el detalle del ticket.';
                    if (autoAbrir) {
                        alerta(msg, false);
                    } else {
                        alert(msg);
                    }
                })
                .always(function () {
                    abriendoModal = false;
                });
        }

        $('#porteria-form-dni').on('submit', function (e) {
            e.preventDefault();
            alerta('');
            postJson(urlBuscar, { documento: $('#porteria-dni').val() })
                .done(function (res) {
                    pintarTicket(res.persona);
                    if (!res.persona || !res.persona.mensaje_bloqueo) {
                        alerta('');
                    }
                    $('#porteria-dni').trigger('select');
                    if (res.persona && res.persona.puede_autorizar_puerta) {
                        abrirModalAutorizacion(res.persona.persona_id, true);
                    }
                })
                .fail(function (xhr) {
                    pintarTicket(null);
                    alerta((xhr.responseJSON && xhr.responseJSON.mensaje)
                        || 'No se encontró una visita abierta para ese DNI.');
                    $('#porteria-dni').trigger('focus');
                });
        });

        function marcar(url) {
            var id = $('#porteria-persona-id').val();
            if (!id) {
                alerta('Busque primero el DNI.');
                return;
            }
            postJson(url, { persona_id: id })
                .done(function (res) {
                    pintarTicket(res.persona);
                    pintarGrilla(res.filas);
                    alerta(res.mensaje, true);
                    $('#porteria-dni').val('').trigger('focus');
                })
                .fail(function (xhr) {
                    alerta((xhr.responseJSON && xhr.responseJSON.mensaje) || 'No se pudo registrar.');
                });
        }

        $('#porteria-btn-entro').on('click', function () {
            marcar(urlEntro);
        });
        $('#porteria-btn-salio').on('click', function () {
            marcar(urlSalio);
        });

        $('#porteria-btn-revisar').on('click', function () {
            var id = $('#porteria-persona-id').val();
            abrirModalAutorizacion(id, false);
        });

        $('#porteria-auth-btn-rechazar').on('click', function () {
            mostrarVistaRechazo();
        });
        $('#porteria-auth-btn-volver').on('click', function () {
            mostrarVistaDatos();
        });

        function aplicarResultadoPuerta(res, ok) {
            if (res.persona) {
                pintarTicket(res.persona);
            }
            if (res.filas) {
                pintarGrilla(res.filas);
            }
            $('#porteriaAutorizacionModal').modal('hide');
            alerta(res.mensaje || (ok ? 'Listo.' : 'No se pudo completar.'), !!ok);
            if (ok && res.persona && res.persona.en_planta) {
                $('#porteria-dni').val('').trigger('focus');
            }
        }

        $('#porteria-auth-btn-autorizar').on('click', function () {
            var id = $('#porteria-persona-id').val();
            if (!id) {
                return;
            }
            errorModal('');
            setBotonesModalDisabled(true);
            postJson(urlAutorizar, { persona_id: id })
                .done(function (res) {
                    aplicarResultadoPuerta(res, true);
                })
                .fail(function (xhr) {
                    errorModal((xhr.responseJSON && xhr.responseJSON.mensaje) || 'No se pudo autorizar.');
                    setBotonesModalDisabled(false);
                });
        });

        $('#porteria-auth-btn-autorizar-ingresar').on('click', function () {
            var id = $('#porteria-persona-id').val();
            if (!id) {
                return;
            }
            errorModal('');
            setBotonesModalDisabled(true);
            postJson(urlAutorizarIngresar, { persona_id: id })
                .done(function (res) {
                    aplicarResultadoPuerta(res, true);
                })
                .fail(function (xhr) {
                    var body = xhr.responseJSON || {};
                    if (body.autorizado && body.persona) {
                        aplicarResultadoPuerta(body, false);
                        return;
                    }
                    errorModal(body.mensaje || 'No se pudo autorizar e ingresar.');
                    setBotonesModalDisabled(false);
                });
        });

        $('#porteria-auth-btn-confirmar-rechazo').on('click', function () {
            var id = $('#porteria-persona-id').val();
            var motivo = $.trim($('#porteria-auth-motivo-rechazo').val() || '');
            if (!motivo) {
                errorModal('Indique el motivo del rechazo.');
                $('#porteria-auth-motivo-rechazo').trigger('focus');
                return;
            }
            errorModal('');
            setBotonesModalDisabled(true);
            postJson(urlRechazar, { persona_id: id, motivo_rechazo: motivo })
                .done(function (res) {
                    aplicarResultadoPuerta(res, true);
                })
                .fail(function (xhr) {
                    errorModal((xhr.responseJSON && xhr.responseJSON.mensaje) || 'No se pudo rechazar.');
                    setBotonesModalDisabled(false);
                });
        });

        $('#porteriaAutorizacionModal').on('hidden.bs.modal', function () {
            mostrarVistaDatos();
            setBotonesModalDisabled(false);
        });
    });
})(jQuery);
