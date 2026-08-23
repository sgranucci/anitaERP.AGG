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

    function pintarTicket(p) {
        if (!p) {
            $('#porteria-ticket').removeClass('is-rechazado is-pendiente').prop('hidden', true);
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
                '<td>' + (p.sector || '') + '</td>' +
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

    $(function () {
        var $root = $('.porteria');
        if (!$root.length) {
            return;
        }

        var urlBuscar = $root.data('url-buscar');
        var urlEntro = $root.data('url-entro');
        var urlSalio = $root.data('url-salio');

        function contarEnPlantaInicial() {
            var n = $('#porteria-tbody tr.porteria-fila-en-planta').length;
            $('#porteria-en-planta-count').text(n + ' en planta');
        }
        contarEnPlantaInicial();

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
                })
                .fail(function (xhr) {
                    pintarTicket(null);
                    alerta((xhr.responseJSON && xhr.responseJSON.mensaje) || 'No se encontró el DNI.');
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
    });
})(jQuery);
