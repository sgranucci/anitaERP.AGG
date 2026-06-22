/**
 * SuiteCRM en solapa del CRUD de clientes: cuenta (accounts) y notas.
 */
(function ($) {
    'use strict';

    var notasCargadas = false;
    var cuentaCargada = false;
    var notasPorId = {};

    function clienteId() {
        return $('#cliente_id').val() || $('input[name="id"]').val();
    }

    function urlNotas() {
        return (typeof carpetaBase !== 'undefined' ? carpetaBase : '') + '/ventas/cliente/' + clienteId() + '/suitecrm-notas';
    }

    function urlCuenta() {
        return (typeof carpetaBase !== 'undefined' ? carpetaBase : '') + '/ventas/cliente/' + clienteId() + '/suitecrm-cuenta';
    }

    function urlSincronizarCuenta() {
        return urlCuenta() + '/sincronizar';
    }

    function csrfToken() {
        return $('meta[name="csrf-token"]').attr('content');
    }

    function escHtml(text) {
        if (text == null) {
            return '';
        }
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function escAttr(text) {
        return escHtml(text).replace(/'/g, '&#39;');
    }

    function nl2br(text) {
        return escHtml(text).replace(/\n/g, '<br>');
    }

    function valorCuenta(valor) {
        if (valor == null || String(valor).trim() === '') {
            return '—';
        }
        return String(valor);
    }

    function mostrarAlerta(msg, tipo) {
        var $a = $('#suitecrm-alerta');
        $a.removeClass('alert-warning alert-danger alert-info alert-success')
            .addClass('alert-' + (tipo || 'warning'))
            .html(escHtml(msg))
            .show();
    }

    function ocultarAlerta() {
        $('#suitecrm-alerta').hide();
    }

    function filaCuentaPar(campo1, campo2) {
        var c1 = campo1
            ? '<th class="text-right" style="width:14%">' +
              escHtml(campo1[0]) +
              '</th><td style="width:36%">' +
              escHtml(valorCuenta(campo1[1])) +
              '</td>'
            : '<th style="width:14%"></th><td style="width:36%"></td>';
        var c2 = campo2
            ? '<th class="text-right" style="width:14%">' +
              escHtml(campo2[0]) +
              '</th><td style="width:36%">' +
              escHtml(valorCuenta(campo2[1])) +
              '</td>'
            : '<th style="width:14%"></th><td style="width:36%"></td>';
        return '<tr>' + c1 + c2 + '</tr>';
    }

    function renderCuenta(c) {
        var campos = [
            ['ID CRM', c.id],
            ['Nombre', c.name],
            ['Código Anita', c.codigo_c],
            ['CUIT', c.cuit_c],
            ['Teléfono', c.phone_office],
            ['Web', c.website],
            ['Domicilio', c.billing_address_street],
            ['Localidad', c.billing_address_city],
            ['Provincia', c.billing_address_state],
            ['CP', c.billing_address_postalcode],
            ['Contacto (notas esp.)', c.notas_especiales_c],
            ['Últ. modificación', c.date_modified],
        ];
        var html = '';
        for (var i = 0; i < campos.length; i += 2) {
            html += filaCuentaPar(campos[i], campos[i + 1] || null);
        }

        $('#suitecrm-cuenta-tbody').html(html);
        $('#suitecrm-cuenta-datos').show();
        $('#suitecrm-cuenta-sin-enlace').hide();
    }

    function cargarCuenta() {
        if (cuentaCargada) {
            return;
        }
        $('#suitecrm-cuenta-cargando').show();
        $('#suitecrm-cuenta-datos').hide();
        $('#suitecrm-cuenta-sin-enlace').hide();

        $.ajax({
            url: urlCuenta(),
            method: 'GET',
            dataType: 'json',
        })
            .done(function (data) {
                cuentaCargada = true;
                $('#suitecrm-cuenta-cargando').hide();
                if (!data.ok) {
                    $('#suitecrm-cuenta-sin-enlace')
                        .text(data.mensaje || 'No se pudo consultar la cuenta.')
                        .show();
                    return;
                }
                if (!data.enlazada || !data.cuentas || data.cuentas.length === 0) {
                    $('#suitecrm-cuenta-sin-enlace')
                        .text(data.mensaje || 'Sin cuenta en SuiteCRM.')
                        .show();
                    return;
                }
                renderCuenta(data.cuentas[0]);
                if (data.cuentas.length > 1) {
                    mostrarAlerta(
                        'Hay ' + data.cuentas.length + ' cuentas CRM con el mismo código/CUIT; se muestra la más reciente.',
                        'info'
                    );
                }
            })
            .fail(function (xhr) {
                $('#suitecrm-cuenta-cargando').hide();
                var msg = 'Error al consultar cuenta SuiteCRM.';
                if (xhr.responseJSON && xhr.responseJSON.mensaje) {
                    msg = xhr.responseJSON.mensaje;
                }
                $('#suitecrm-cuenta-sin-enlace').text(msg).show();
            });
    }

    function sincronizarCuenta() {
        var $btn = $('#suitecrm-cuenta-sincronizar');
        $btn.prop('disabled', true);
        ocultarAlerta();

        $.ajax({
            url: urlSincronizarCuenta(),
            method: 'POST',
            data: { _token: csrfToken() },
            dataType: 'json',
        })
            .done(function (data) {
                cuentaCargada = false;
                notasCargadas = false;
                cargarCuenta();
                cargarNotas();
                mostrarAlerta(data.mensaje || 'Cuenta sincronizada.', 'success');
            })
            .fail(function (xhr) {
                var msg = 'No se pudo sincronizar la cuenta.';
                if (xhr.responseJSON && xhr.responseJSON.mensaje) {
                    msg = xhr.responseJSON.mensaje;
                }
                mostrarAlerta(msg, 'danger');
            })
            .always(function () {
                $btn.prop('disabled', false);
            });
    }

    function limpiarModalNota() {
        $('#suitecrm-nota-modal-id').val('');
        $('#suitecrm-nota-modal-name').val('');
        $('#suitecrm-nota-modal-description').val('');
    }

    function abrirModalNota(modo, nota) {
        limpiarModalNota();
        if (modo === 'editar' && nota) {
            $('#suitecrm-nota-modal-id').val(nota.id);
            $('#suitecrm-nota-modal-name').val(nota.name || '');
            $('#suitecrm-nota-modal-description').val(nota.description || '');
            $('#suitecrmNotaModalLabel').text('Editar nota SuiteCRM');
        } else {
            $('#suitecrmNotaModalLabel').text('Nueva nota SuiteCRM');
        }
        $('#suitecrmNotaModal').modal('show');
    }

    function renderFilasNotas(notas) {
        var $tb = $('#suitecrm-notas-tbody');
        $tb.empty();
        notasPorId = {};
        var puedeEditar = $('#suitecrmNotaModal').length > 0;

        if (!notas || notas.length === 0) {
            $tb.append(
                '<tr><td colspan="5" class="text-muted text-center">Sin notas en SuiteCRM para esta cuenta.</td></tr>'
            );
            return;
        }

        notas.forEach(function (n) {
            notasPorId[String(n.id)] = n;
            var acciones = '';
            if (puedeEditar) {
                acciones =
                    '<button type="button" class="btn btn-xs btn-primary suitecrm-nota-editar mr-1" data-id="' +
                    escAttr(n.id) +
                    '" title="Editar"><i class="fa fa-edit"></i></button>' +
                    '<button type="button" class="btn btn-xs btn-danger suitecrm-nota-borrar" data-id="' +
                    escAttr(n.id) +
                    '" title="Eliminar"><i class="fa fa-trash"></i></button>';
            }
            $tb.append(
                '<tr data-nota-id="' +
                    escAttr(n.id) +
                    '">' +
                    '<td>' +
                    escHtml(n.date_entered || '') +
                    '</td>' +
                    '<td>' +
                    escHtml(n.date_modified || '') +
                    '</td>' +
                    '<td>' +
                    escHtml(n.name) +
                    '</td>' +
                    '<td class="small">' +
                    nl2br(n.description) +
                    '</td>' +
                    '<td class="text-nowrap">' +
                    acciones +
                    '</td>' +
                    '</tr>'
            );
        });
    }

    function cargarNotas() {
        if (notasCargadas) {
            return;
        }
        $('#suitecrm-notas-cargando').show();
        $('#suitecrm-notas-tbody').empty();

        $.ajax({
            url: urlNotas(),
            method: 'GET',
            dataType: 'json',
        })
            .done(function (data) {
                notasCargadas = true;
                $('#suitecrm-notas-cargando').hide();
                if (!data.ok) {
                    renderFilasNotas([]);
                    return;
                }
                renderFilasNotas(data.notas || []);
            })
            .fail(function () {
                $('#suitecrm-notas-cargando').hide();
                renderFilasNotas([]);
            });
    }

    function recargarNotas() {
        notasCargadas = false;
        cargarNotas();
    }

    function guardarNotaModal() {
        var id = $('#suitecrm-nota-modal-id').val();
        var payload = {
            _token: csrfToken(),
            name: $('#suitecrm-nota-modal-name').val(),
            description: $('#suitecrm-nota-modal-description').val(),
        };
        var url = urlNotas();
        var method = 'POST';

        if (id) {
            url += '/' + encodeURIComponent(id);
            method = 'PUT';
            payload._method = 'PUT';
        }

        var $btn = $('#suitecrm-nota-modal-guardar');
        $btn.prop('disabled', true);

        $.ajax({
            url: url,
            method: method,
            data: payload,
            dataType: 'json',
        })
            .done(function () {
                $('#suitecrmNotaModal').modal('hide');
                limpiarModalNota();
                recargarNotas();
            })
            .fail(function (xhr) {
                var msg = 'No se pudo guardar la nota.';
                if (xhr.responseJSON && xhr.responseJSON.mensaje) {
                    msg = xhr.responseJSON.mensaje;
                }
                mostrarAlerta(msg, 'danger');
            })
            .always(function () {
                $btn.prop('disabled', false);
            });
    }

    function borrarNota(notaId) {
        if (!confirm('¿Eliminar esta nota en SuiteCRM?')) {
            return;
        }
        $.ajax({
            url: urlNotas() + '/' + encodeURIComponent(notaId),
            method: 'DELETE',
            data: { _token: csrfToken() },
            dataType: 'json',
        })
            .done(function () {
                $('#suitecrmNotaModal').modal('hide');
                limpiarModalNota();
                recargarNotas();
            })
            .fail(function (xhr) {
                var msg = 'No se pudo eliminar la nota.';
                if (xhr.responseJSON && xhr.responseJSON.mensaje) {
                    msg = xhr.responseJSON.mensaje;
                }
                mostrarAlerta(msg, 'danger');
            });
    }

    function cargarSolapaCrm() {
        ocultarAlerta();
        cargarCuenta();
        cargarNotas();
    }

    window.suitecrmNotasCargar = cargarNotas;
    window.suitecrmNotasRecargar = recargarNotas;

    $(function () {
        if (!$('#tab9').length) {
            return;
        }

        var $modal = $('#suitecrmNotaModal');
        if ($modal.length && !$modal.parent().is('body')) {
            $modal.appendTo('body');
        }

        $('#tab-suitecrm-link').on('shown.bs.tab', function () {
            cargarSolapaCrm();
        });

        $('#suitecrm-cuenta-sincronizar').on('click', sincronizarCuenta);
        $('#suitecrm-nota-nueva').on('click', function () {
            abrirModalNota('nueva');
        });
        $('#suitecrm-nota-modal-guardar').on('click', guardarNotaModal);
        $modal.on('hidden.bs.modal', limpiarModalNota);

        $(document).on('click', '.suitecrm-nota-editar', function (e) {
            e.preventDefault();
            var notaId = String($(this).attr('data-id') || '');
            var nota = notasPorId[notaId];
            if (!nota) {
                mostrarAlerta('No se pudo abrir la nota para editar.', 'warning');
                return;
            }
            abrirModalNota('editar', nota);
        });

        $(document).on('click', '.suitecrm-nota-borrar', function () {
            borrarNota($(this).attr('data-id'));
        });
    });
})(jQuery);
