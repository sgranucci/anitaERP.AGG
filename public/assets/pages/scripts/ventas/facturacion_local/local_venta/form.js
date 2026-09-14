(function ($) {
    'use strict';

    var ptrCuentacajaLocalContext = null;

    function carpeta() {
        if (typeof window.carpetaBase !== 'undefined' && window.carpetaBase) {
            return String(window.carpetaBase).replace(/\/$/, '');
        }
        return '';
    }

    function csrf() {
        return $('meta[name="csrf-token"]').attr('content')
            || $('#form-general input[name="_token"]').val()
            || '';
    }

    function empresaId() {
        return String($('#empresa_id').val() || '').trim();
    }

    function usocuentacajaLocalId() {
        var cfg = window.localVentaFormCfg || {};
        var fromCfg = parseInt(cfg.usocuentacajaLocalId, 10) || 0;
        if (fromCfg > 0) {
            return fromCfg;
        }
        if (typeof window.FACTURACION_LOCAL !== 'undefined') {
            return parseInt(window.FACTURACION_LOCAL.usocuentacajaLocalId, 10) || 0;
        }
        return 0;
    }

    function sincronizarPuntoventaDefault() {
        var $checked = $('#tbody-local-puntoventa .local-pv-default:checked').closest('tr');
        if (!$checked.length) {
            $checked = $('#tbody-local-puntoventa tr.local-pv-row').first();
            $checked.find('.local-pv-default').prop('checked', true);
        }
        var id = $checked.find('.puntoventa_id').val() || '';
        $('#puntoventa_id').val(id);
    }

    window.onPuntoventaSeleccionadoLocalVenta = function ($ctx) {
        var $row = $ctx.closest('tr.local-pv-row');
        if ($row.length && $row.find('.local-pv-default').is(':checked')) {
            sincronizarPuntoventaDefault();
        } else if (!$('#tbody-local-puntoventa .local-pv-default:checked').length) {
            $row.find('.local-pv-default').prop('checked', true);
            sincronizarPuntoventaDefault();
        }
    };

    function agregarFilaPv() {
        var tpl = document.getElementById('template-local-pv-row');
        if (!tpl) {
            return;
        }
        var $row = $(tpl.content.cloneNode(true));
        $('#tbody-local-puntoventa').append($row);
        if (typeof activa_eventos_consultapuntoventa === 'function') {
            activa_eventos_consultapuntoventa();
        }
        sincronizarPuntoventaDefault();
    }

    function agregarFilaCc() {
        var tpl = document.getElementById('template-local-cc-row');
        if (!tpl) {
            return;
        }
        $('#tbody-local-cuentacaja').append($(tpl.content.cloneNode(true)));
    }

    function parseConsultaCc(respuesta) {
        if (respuesta && typeof respuesta === 'object') {
            return respuesta.data || '';
        }
        try {
            return JSON.parse(String(respuesta || '')).data || '';
        } catch (e) {
            return String(respuesta || '');
        }
    }

    function buscarCuentacaja(consulta) {
        $.ajax({
            url: carpeta() + '/caja/cuentacaja/consultacuentacaja',
            type: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': csrf() },
            data: {
                consulta: consulta || '',
                empresa_id: empresaId(),
                usocuentacaja_id: usocuentacajaLocalId() || '',
                _token: csrf(),
            },
        })
            .done(function (r) {
                $('#datoscuentacaja').html(parseConsultaCc(r));
            })
            .fail(function () {
                $('#datoscuentacaja').html('<tr><td colspan="10">Error al consultar cuentas de caja</td></tr>');
            });
    }

    function asignarCuentacaja($ctx, data) {
        if (!$ctx || !$ctx.length || !data) {
            return;
        }
        $ctx.find('.cuentacaja_id').val(data.id || '');
        $ctx.find('.codigocuentacaja').val(data.codigo || '');
        $ctx.find('.descripcioncuentacaja').val(data.nombre || '');
        var $link = $ctx.find('.btn-link-editar-cuentacaja');
        if ($link.length) {
            var id = parseInt(data.id, 10) || 0;
            if (id > 0) {
                $link.attr('href', carpeta() + '/caja/cuentacaja/' + id + '/editar?origen=modal_consulta&vista=consulta').removeClass('d-none');
            } else {
                $link.attr('href', '#').addClass('d-none');
            }
        }
    }

    function limpiarCuentacaja($ctx, mantenerCodigo) {
        if (!$ctx || !$ctx.length) {
            return;
        }
        $ctx.find('.cuentacaja_id').val('');
        if (!mantenerCodigo) {
            $ctx.find('.codigocuentacaja').val('');
        }
        $ctx.find('.descripcioncuentacaja').val('');
        $ctx.find('.btn-link-editar-cuentacaja').attr('href', '#').addClass('d-none');
    }

    function resolverCuentacajaPorCodigo($ctx, alertar) {
        var codigo = $.trim($ctx.find('.codigocuentacaja').val() || '');
        if (codigo === '') {
            limpiarCuentacaja($ctx, false);
            return;
        }
        $.ajax({
            url: carpeta() + '/caja/cuentacaja/leercuentacajaporcodigo/' + encodeURIComponent(codigo),
            type: 'GET',
            dataType: 'json',
            data: {
                empresa_id: empresaId(),
                usocuentacaja_id: usocuentacajaLocalId() || '',
            },
        })
            .done(function (data) {
                if (data && data.id) {
                    asignarCuentacaja($ctx, data);
                } else {
                    limpiarCuentacaja($ctx, true);
                    if (alertar) {
                        alert('Cuenta de caja no encontrada');
                    }
                }
            })
            .fail(function () {
                limpiarCuentacaja($ctx, true);
                if (alertar) {
                    alert('Cuenta de caja no encontrada');
                }
            });
    }

    function enfocarCodigoLocal() {
        var el = document.getElementById('codigo');
        if (!el || el.readOnly || el.disabled) {
            return false;
        }
        try {
            el.focus({ preventScroll: true });
        } catch (e) {
            el.focus();
        }
        try {
            el.select();
        } catch (e2) {}
        return document.activeElement === el;
    }

    $(function () {
        // Foco en código: el SW cachea /assets/ sin query; reforzar varias veces.
        enfocarCodigoLocal();
        [50, 150, 400].forEach(function (ms) {
            setTimeout(enfocarCodigoLocal, ms);
        });
        $(window).on('load.localVentaFoco', function () {
            enfocarCodigoLocal();
            $(window).off('load.localVentaFoco');
        });

        if (typeof activa_eventos_consultadeposito === 'function') {
            activa_eventos_consultadeposito();
        }
        if (typeof activa_eventos_consultalistaprecio === 'function') {
            activa_eventos_consultalistaprecio();
        }
        if (typeof activa_eventos_consultapuntoventa === 'function') {
            activa_eventos_consultapuntoventa();
        }
        if (typeof activa_eventos_consultatipotransaccionventa === 'function') {
            activa_eventos_consultatipotransaccionventa();
        }
        if (typeof activa_eventos_consulta_cuentacontable === 'function') {
            activa_eventos_consulta_cuentacontable();
        }

        sincronizarPuntoventaDefault();

        $('#local-pv-agregar').on('click', function () {
            agregarFilaPv();
        });

        $(document).on('click', '.local-pv-quitar', function () {
            var $tbody = $('#tbody-local-puntoventa');
            if ($tbody.find('tr.local-pv-row').length <= 1) {
                var $row = $(this).closest('tr');
                $row.find('.puntoventa_id').val('');
                $row.find('.codigopuntoventa').val('');
                $row.find('.descripcionpuntoventa').val('');
                $row.find('.local-pv-default').prop('checked', true);
                sincronizarPuntoventaDefault();
                return;
            }
            var eraDefault = $(this).closest('tr').find('.local-pv-default').is(':checked');
            $(this).closest('tr').remove();
            if (eraDefault) {
                $tbody.find('tr.local-pv-row').first().find('.local-pv-default').prop('checked', true);
            }
            sincronizarPuntoventaDefault();
        });

        $(document).on('change', '.local-pv-default', function () {
            sincronizarPuntoventaDefault();
        });

        $('#local-cc-agregar').on('click', function () {
            agregarFilaCc();
        });

        $(document).on('click', '.local-cc-quitar', function () {
            var $tbody = $('#tbody-local-cuentacaja');
            if ($tbody.find('tr.local-cc-row').length <= 1) {
                limpiarCuentacaja($(this).closest('tr').find('.tm-cuentacaja-campo'), false);
                return;
            }
            $(this).closest('tr').remove();
        });

        $(document).on('click', '.tm-cuentacaja-campo .consultacuentacaja', function (e) {
            e.preventDefault();
            e.stopPropagation();
            ptrCuentacajaLocalContext = $(this).closest('.tm-cuentacaja-campo');
            $('#consultacuentacajaModal').modal('show');
        });

        $('#consultacuentacajaModal')
            .off('shown.bs.modal.localVentaCc')
            .on('shown.bs.modal.localVentaCc', function () {
                var $input = $('#consultacuentacaja');
                setTimeout(function () { $input.trigger('focus'); }, 0);
                buscarCuentacaja($input.val());
            });

        $(document)
            .off('keyup.localVentaCc', '#consultacuentacaja')
            .on('keyup.localVentaCc', '#consultacuentacaja', function (e) {
                if (e.which === 13 || e.key === 'Enter') {
                    return;
                }
                buscarCuentacaja($(this).val());
            });

        $(document)
            .off('click.localVentaCcElige', '.eligeconsultacuentacaja')
            .on('click.localVentaCcElige', '.eligeconsultacuentacaja', function (e) {
                e.preventDefault();
                e.stopImmediatePropagation();
                var $tr = $(this).closest('tr');
                asignarCuentacaja(ptrCuentacajaLocalContext, {
                    id: $tr.find('.cuentacaja_id').text().trim(),
                    codigo: $tr.find('.codigo').text().trim(),
                    nombre: $tr.find('.nombre').text().trim(),
                });
                $('#consultacuentacajaModal').modal('hide');
            });

        $(document).on('keydown', '.tm-cuentacaja-campo .codigocuentacaja', function (e) {
            if (e.key === 'F1' || e.keyCode === 112) {
                e.preventDefault();
                $(this).closest('.tm-cuentacaja-campo').find('.consultacuentacaja').trigger('click');
                return;
            }
            if (e.which === 13 || e.key === 'Enter') {
                e.preventDefault();
                resolverCuentacajaPorCodigo($(this).closest('.tm-cuentacaja-campo'), true);
            }
        });

        $(document).on('blur', '.tm-cuentacaja-campo .codigocuentacaja', function () {
            if ($('#consultacuentacajaModal').hasClass('show')) {
                return;
            }
            var $ctx = $(this).closest('.tm-cuentacaja-campo');
            if (parseInt(String($ctx.find('.cuentacaja_id').val() || '0'), 10) > 0) {
                return;
            }
            resolverCuentacajaPorCodigo($ctx, false);
        });

        $(document).on('input', '.tm-cuentacaja-campo .codigocuentacaja', function () {
            var $ctx = $(this).closest('.tm-cuentacaja-campo');
            $ctx.find('.cuentacaja_id').val('');
            $ctx.find('.descripcioncuentacaja').val('');
        });

        $('#empresa_id').on('change', function () {
            $('.tm-deposito-campo').each(function () {
                $(this).find('.deposito_id').val('');
                $(this).find('.codigodeposito').val('');
                $(this).find('.descripciondeposito').val('');
            });
            $('.tm-cuentacaja-campo').each(function () {
                limpiarCuentacaja($(this), false);
            });
        });

        $('#local-sync-depositos-anita').on('click', function () {
            var cfg = window.localVentaFormCfg || {};
            var url = cfg.syncDepositosUrl || (carpeta() + '/ventas/facturacion-local/locales/sync-depositos-anita');
            var $msg = $('#local-sync-depositos-msg');
            var $btn = $(this);
            $btn.prop('disabled', true);
            $msg.removeClass('text-danger text-success').text('Importando…');

            $.ajax({
                url: url,
                type: 'POST',
                dataType: 'json',
                headers: { 'X-CSRF-TOKEN': csrf() },
                data: {
                    _token: csrf(),
                    empresa_id: empresaId(),
                    codigo: $('#codigo').val() || '',
                    anita_servidor: $('#anita_servidor').val() || '',
                    anita_ifx_server: $('#anita_ifx_server').val() || '',
                },
            })
                .done(function (r) {
                    if (!r || !r.ok) {
                        $msg.addClass('text-danger').text((r && r.error) ? r.error : 'No se pudo importar');
                        return;
                    }
                    var txt = 'Anita: ' + (r.en_anita || 0)
                        + ' · importados: ' + (r.importados || 0)
                        + ' · ya existían: ' + (r.omitidos_existentes || 0);
                    if (r.errores && r.errores.length) {
                        txt += ' · errores: ' + r.errores.length;
                    }
                    $msg.addClass('text-success').text(txt);
                    if (r.creados && r.creados.length === 1 && !$('#deposito_id').val()) {
                        var c = r.creados[0];
                        $('#deposito_id').val(c.id);
                        $('#deposito_id_codigo').val(c.codigo);
                        $('#deposito_id_descripcion').val(c.nombre);
                        if (!$('#anita_deposito').val() && c.anita_codigo) {
                            $('#anita_deposito').val(c.anita_codigo);
                        }
                    }
                })
                .fail(function (xhr) {
                    var err = (xhr.responseJSON && xhr.responseJSON.error)
                        ? xhr.responseJSON.error
                        : 'Error al importar depósitos Anita';
                    $msg.addClass('text-danger').text(err);
                })
                .always(function () {
                    $btn.prop('disabled', false);
                });
        });

        $('#form-general').on('submit', function () {
            sincronizarPuntoventaDefault();
            $('#tbody-local-puntoventa tr.local-pv-row').each(function () {
                var id = parseInt(String($(this).find('.puntoventa_id').val() || '0'), 10);
                if (id <= 0) {
                    $(this).find('.puntoventa_id').prop('disabled', true);
                }
            });
            $('#tbody-local-cuentacaja tr.local-cc-row').each(function () {
                var id = parseInt(String($(this).find('.cuentacaja_id').val() || '0'), 10);
                if (id <= 0) {
                    $(this).find('.cuentacaja_id').prop('disabled', true);
                }
            });
        });
    });
})(jQuery);
