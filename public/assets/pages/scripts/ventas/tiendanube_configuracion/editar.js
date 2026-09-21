(function ($) {
    'use strict';

    var ptrCuentacajaTn = null;
    var CODIGOS_CONSULTA = '.codigopuntoventa, .codigodeposito, .codigocuentacaja, .codigolistaprecio, .tn-gateway-key';

    function carpeta() {
        return typeof carpetaBase !== 'undefined' ? carpetaBase : '';
    }

    function modalAbierto() {
        return $('.modal.show, .modal.in').length > 0;
    }

    function reindexRadios() {
        $('#tbody-tn-pv-dep .tn-pv-dep-row').each(function (i) {
            $(this).find('.tn-pv-dep-default').val(i);
        });
    }

    function syncDefaultsHidden() {
        var $row = $('#tbody-tn-pv-dep .tn-pv-dep-default:checked').closest('.tn-pv-dep-row');
        if (!$row.length) {
            $row = $('#tbody-tn-pv-dep .tn-pv-dep-row').first();
            $row.find('.tn-pv-dep-default').prop('checked', true);
        }
        $('#puntoventa_id').val($row.find('.puntoventa_id').val() || '');
        $('#deposito_id').val($row.find('.deposito_id').val() || '');
    }

    function moverModalesAlBody() {
        ['#consultadepositoModal', '#consultapuntoventaModal', '#consultacuentacajaModal', '#consultalistaprecioModal']
            .forEach(function (sel) {
                var $m = $(sel);
                if ($m.length && $m.parent()[0] !== document.body) {
                    $m.appendTo('body');
                }
            });
    }

    function activaConsultas() {
        if (typeof window.activa_eventos_consultadeposito === 'function') {
            window.activa_eventos_consultadeposito();
        }
        if (typeof window.activa_eventos_consultapuntoventa === 'function') {
            window.activa_eventos_consultapuntoventa();
        }
        if (typeof window.activa_eventos_consultalistaprecio === 'function') {
            window.activa_eventos_consultalistaprecio();
        }
    }

    function asignarCuentacaja($ctx, data) {
        if (!$ctx || !$ctx.length) {
            return;
        }
        var id = data.id || '';
        $ctx.find('.cuentacaja_id').val(id);
        $ctx.find('.codigocuentacaja').val(data.codigo || '');
        $ctx.find('.descripcioncuentacaja').val(data.nombre || '');
        var $edit = $ctx.find('.btn-link-editar-cuentacaja');
        if ($edit.length) {
            if (id) {
                var base = carpeta() + '/caja/cuentacaja/' + id + '/editar';
                $edit.attr('href', base + '?origen=modal_consulta&vista=consulta').removeClass('d-none');
            } else {
                $edit.attr('href', '#').addClass('d-none');
            }
        }
    }

    function limpiarCuentacaja($ctx) {
        if (!$ctx || !$ctx.length) {
            return;
        }
        $ctx.find('.cuentacaja_id').val('');
        $ctx.find('.codigocuentacaja').val('');
        $ctx.find('.descripcioncuentacaja').val('');
        $ctx.find('.btn-link-editar-cuentacaja').attr('href', '#').addClass('d-none');
    }

    function buscarCuentacajaModal(texto) {
        if (typeof buscar_datos_cuentacaja === 'function') {
            buscar_datos_cuentacaja(String(texto || '').trim());
            return;
        }
        $.ajax({
            type: 'POST',
            url: carpeta() + '/caja/cuentacaja/consultacuentacaja',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val(),
                consulta: texto || '',
                empresa_id: $('#empresa_id').val() || '',
            },
            success: function (respuesta) {
                $('#datoscuentacaja').html((respuesta && respuesta.data) ? respuesta.data : (respuesta || ''));
            },
        });
    }

    function resolverCuentacajaPorCodigo($ctx, alertar) {
        var codigo = String($ctx.find('.codigocuentacaja').val() || '').trim();
        if (codigo === '') {
            limpiarCuentacaja($ctx);
            return;
        }
        $.ajax({
            type: 'GET',
            url: carpeta() + '/caja/cuentacaja/leercuentacajaporcodigo/' + encodeURIComponent(codigo),
            data: { empresa_id: $('#empresa_id').val() || '' },
            success: function (data) {
                if (data && data.id > 0) {
                    asignarCuentacaja($ctx, data);
                    var $next = $ctx.closest('tr').next('.tn-gateway-row').find('.tn-gateway-key');
                    if ($next.length) {
                        $next.trigger('focus').select();
                    }
                } else {
                    limpiarCuentacaja($ctx);
                    $ctx.find('.codigocuentacaja').val(codigo);
                    if (alertar) {
                        alert('Cuenta de caja no encontrada.');
                        $ctx.find('.codigocuentacaja').trigger('focus');
                    }
                }
            },
            error: function () {
                limpiarCuentacaja($ctx);
                $ctx.find('.codigocuentacaja').val(codigo);
                if (alertar) {
                    alert('Cuenta de caja no encontrada.');
                    $ctx.find('.codigocuentacaja').trigger('focus');
                }
            },
        });
    }

    function agregarDesdeTemplate(templateId, tbodySelector) {
        var tpl = document.getElementById(templateId);
        if (!tpl) {
            return;
        }
        var idx = $(tbodySelector + ' tr').length;
        var html = tpl.innerHTML.replace(/__IDX__/g, String(idx));
        var $row = $(html);
        $(tbodySelector).append($row);
        reindexRadios();
        var $focus = $row.find('.codigopuntoventa, .tn-gateway-key').first();
        if ($focus.length) {
            $focus.trigger('focus');
        }
    }

    $(function () {
        moverModalesAlBody();
        activaConsultas();
        reindexRadios();
        syncDefaultsHidden();

        // Enter no envía el form salvo en el botón Guardar
        $('#form-config-tiendanube').on('keydown', 'input', function (e) {
            if (e.which !== 13 && e.key !== 'Enter') {
                return;
            }
            if ($(this).is('button, [type=submit]')) {
                return;
            }
            // Los códigos de consulta y el buscador de modal tienen su propio handler
            if ($(this).is(CODIGOS_CONSULTA) || $(this).closest('.modal').length) {
                return;
            }
            e.preventDefault();
            return false;
        });

        $('#tn-pv-dep-agregar').on('click', function () {
            agregarDesdeTemplate('tn-template-fila-pv-dep', '#tbody-tn-pv-dep');
        });

        $('#tn-gateway-agregar').on('click', function () {
            agregarDesdeTemplate('tn-template-fila-gateway', '#tbody-tn-gateway');
        });

        $(document).on('click', '.tn-pv-dep-quitar', function () {
            var $tbody = $('#tbody-tn-pv-dep');
            if ($tbody.find('.tn-pv-dep-row').length <= 1) {
                var $row = $(this).closest('.tn-pv-dep-row');
                $row.find('input[type=text], input[type=hidden]').val('');
                $row.find('.puntoventa_id, .deposito_id').val('');
                return;
            }
            $(this).closest('.tn-pv-dep-row').remove();
            reindexRadios();
            syncDefaultsHidden();
        });

        $(document).on('click', '.tn-gateway-quitar', function () {
            var $tbody = $('#tbody-tn-gateway');
            if ($tbody.find('.tn-gateway-row').length <= 1) {
                limpiarCuentacaja($(this).closest('.tn-gateway-row').find('.tm-cuentacaja-campo'));
                $(this).closest('.tn-gateway-row').find('.tn-gateway-key').val('');
                return;
            }
            $(this).closest('.tn-gateway-row').remove();
        });

        $(document).on('change', '.tn-pv-dep-default', syncDefaultsHidden);
        $(document).on('change', '#tbody-tn-pv-dep .puntoventa_id, #tbody-tn-pv-dep .deposito_id', syncDefaultsHidden);

        // Tras validar PV con Enter, pasar al depósito de la misma fila
        $(document).on('keydown.tnCfgPvEnter', '.codigopuntoventa', function (e) {
            if (e.which !== 13 && e.key !== 'Enter') {
                return;
            }
            var $dep = $(this).closest('.tn-pv-dep-row').find('.codigodeposito');
            if ($dep.length) {
                setTimeout(function () {
                    if (parseInt(String($(e.target).closest('.tm-puntoventa-campo').find('.puntoventa_id').val() || '0'), 10) > 0) {
                        $dep.trigger('focus').select();
                    }
                }, 120);
            }
        });

        // Enter en clave gateway → código cuenta
        $(document).on('keydown.tnCfgGwKey', '.tn-gateway-key', function (e) {
            if (e.which !== 13 && e.key !== 'Enter') {
                return;
            }
            e.preventDefault();
            $(this).closest('.tn-gateway-row').find('.codigocuentacaja').trigger('focus').select();
        });

        // ——— Cuenta de caja (F1 / lupa / Enter / modal Enter) ———
        $(document).on('click.tnCfgCc', '.tm-cuentacaja-campo .consultacuentacaja', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (modalAbierto() && !$('#consultacuentacajaModal').hasClass('show')) {
                // evitar abrir sobre otro modal en transición
            }
            ptrCuentacajaTn = $(this).closest('.tm-cuentacaja-campo');
            $('#consultacuentacajaModal').modal('show');
        });

        $('#consultacuentacajaModal')
            .off('shown.bs.modal.tnCfgCc')
            .on('shown.bs.modal.tnCfgCc', function () {
                var $input = $('#consultacuentacaja');
                setTimeout(function () { $input.trigger('focus').select(); }, 0);
                buscarCuentacajaModal($input.val());
            });

        $(document)
            .off('click.tnCfgCcElige', '.eligeconsultacuentacaja')
            .on('click.tnCfgCcElige', '.eligeconsultacuentacaja', function (e) {
                e.preventDefault();
                e.stopImmediatePropagation();
                var $tr = $(this).closest('tr');
                asignarCuentacaja(ptrCuentacajaTn, {
                    id: $tr.find('.cuentacaja_id').text().trim(),
                    codigo: $tr.find('.codigo').text().trim(),
                    nombre: $tr.find('.nombre').text().trim(),
                });
                $('#consultacuentacajaModal').modal('hide');
                setTimeout(function () {
                    var $next = (ptrCuentacajaTn || $()).closest('tr').next('.tn-gateway-row').find('.tn-gateway-key');
                    if ($next.length) {
                        $next.trigger('focus');
                    }
                }, 50);
            });

        $(document).on('keydown.tnCfgCc', '.tm-cuentacaja-campo .codigocuentacaja', function (e) {
            if (e.key === 'F1' || e.code === 'F1' || e.keyCode === 112) {
                e.preventDefault();
                e.stopPropagation();
                $(this).closest('.tm-cuentacaja-campo').find('.consultacuentacaja').trigger('click');
                return;
            }
            if (e.which === 13 || e.key === 'Enter') {
                e.preventDefault();
                e.stopPropagation();
                resolverCuentacajaPorCodigo($(this).closest('.tm-cuentacaja-campo'), true);
            }
        });

        $(document).on('blur.tnCfgCc', '.tm-cuentacaja-campo .codigocuentacaja', function () {
            if ($('#consultacuentacajaModal').hasClass('show') || modalAbierto()) {
                return;
            }
            var $ctx = $(this).closest('.tm-cuentacaja-campo');
            if (parseInt(String($ctx.find('.cuentacaja_id').val() || '0'), 10) > 0) {
                return;
            }
            resolverCuentacajaPorCodigo($ctx, false);
        });

        $(document).on('input.tnCfgCc', '.tm-cuentacaja-campo .codigocuentacaja', function () {
            var $ctx = $(this).closest('.tm-cuentacaja-campo');
            $ctx.find('.cuentacaja_id').val('');
            $ctx.find('.descripcioncuentacaja').val('');
            $ctx.find('.btn-link-editar-cuentacaja').addClass('d-none');
        });

        $('#form-config-tiendanube').on('submit', function () {
            reindexRadios();
            syncDefaultsHidden();
        });
    });
})(jQuery);
