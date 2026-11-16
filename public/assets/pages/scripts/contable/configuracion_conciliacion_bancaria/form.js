(function ($) {
    'use strict';

    var $cuentaTarget = $();
    var $conceptoTarget = $();

    function reindexar($tbody) {
        var prefix = $tbody.data('prefix');
        if (!prefix) {
            return;
        }
        var re = new RegExp(prefix.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\[\\d+\\]');
        $tbody.find('tr').each(function (idx) {
            $(this).find('[name]').each(function () {
                var name = $(this).attr('name') || '';
                $(this).attr('name', name.replace(re, prefix + '[' + idx + ']'));
            });
        });
    }

    function csrfToken() {
        return $('meta[name="csrf-token"]').attr('content') || '';
    }

    function urls() {
        return window.conciliacionBancariaConfigUrls || {};
    }

    function abrirConsultaCuenta($input) {
        $cuentaTarget = $input;
        var $bridge = $('#bridge-cuenta-doc-gasto');
        $bridge.find('.cuentacontable_id').val('');
        $bridge.find('.codigocuentacontable').val(String($input.val() || '').trim());
        $bridge.find('.nombrecuentacontable').val('');
        if (typeof window.ptrCuentacontableContext !== 'undefined') {
            window.ptrCuentacontableContext = $bridge;
        }
        $('#bridge-btn-consulta-cuenta').trigger('click');
    }

    function copiarCuentaDesdeBridge() {
        if (!$cuentaTarget.length) {
            return;
        }
        var codigo = String($('#bridge-cuenta-doc-gasto .codigocuentacontable').val() || '').trim();
        if (codigo !== '') {
            $cuentaTarget.val(codigo);
        }
        $cuentaTarget = $();
    }

    function buscarConceptoCatalogo(consulta) {
        var $aviso = $('#consultaconcepto_ivacompra-aviso');
        if ($aviso.length) {
            $aviso.text('Catálogo completo de conceptos IVA compras.');
        }
        $('#datosconcepto_ivacompra').html('<tr><td colspan="5" class="text-muted">Buscando…</td></tr>');
        $.ajax({
            url: urls().consultaConcepto || '/compras/concepto_ivacompra/consulta',
            type: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
            data: {
                consulta: consulta || '',
                catalogo: 1,
                _token: csrfToken()
            }
        }).done(function (respuesta) {
            var html = (respuesta && respuesta.data) ? respuesta.data : '';
            if (!html) {
                html = '<tr><td colspan="5" class="text-muted">Sin resultados</td></tr>';
            }
            $('#datosconcepto_ivacompra').html(html);
        }).fail(function () {
            $('#datosconcepto_ivacompra').html('<tr><td colspan="5" class="text-danger">No se pudo consultar.</td></tr>');
        });
    }

    function abrirConsultaConcepto($input) {
        $conceptoTarget = $input;
        $('#consultaconcepto_ivacompra').val(String($input.val() || '').trim());
        $('#consultaconcepto_ivacompraModal').modal('show');
        buscarConceptoCatalogo($('#consultaconcepto_ivacompra').val());
    }

    function resolverConceptoBlur($input) {
        var codigo = String($input.val() || '').trim();
        if (codigo === '') {
            return;
        }
        $.ajax({
            url: urls().resolverConcepto || '/compras/concepto_ivacompra/resolver',
            type: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
            data: {
                valor: codigo,
                catalogo: 1,
                _token: csrfToken()
            }
        }).done(function (r) {
            if (r && (r.codigo || (r.concepto && r.concepto.codigo))) {
                $input.val(r.codigo || r.concepto.codigo);
            }
        });
    }

    $(function () {
        $(document).on('click', '.js-agregar-fila', function () {
            var tbodyId = $(this).data('tbody');
            var templateId = $(this).data('template');
            var tpl = document.getElementById(templateId);
            var $tbody = $('#' + tbodyId);
            if (!tpl || !$tbody.length) {
                return;
            }
            $tbody.append(tpl.content.cloneNode(true));
            reindexar($tbody);
            $tbody.find('tr:last input[type=text]:first').focus();
        });

        $(document).on('click', '.js-quitar-fila', function () {
            var $tr = $(this).closest('tr');
            var $tbody = $tr.closest('tbody');
            if ($tbody.find('tr').length <= 1) {
                $tr.find('input[type=text]').val('');
                $tr.find('input[type=hidden]').each(function () {
                    var name = $(this).attr('name') || '';
                    if (name.indexOf('[id]') !== -1) {
                        $(this).val('');
                    } else if (name.indexOf('es_acumulado') !== -1) {
                        $(this).val('0');
                    }
                });
                $tr.find('select').prop('selectedIndex', 0);
                $tr.find('input[type=checkbox]').prop('checked', false);
                return;
            }
            $tr.remove();
            reindexar($tbody);
        });

        $(document).on('click', '.js-consulta-cuenta-doc', function (e) {
            e.preventDefault();
            abrirConsultaCuenta($(this).closest('td').find('.js-doc-cuenta-codigo'));
        });

        $(document).on('keydown', '.js-doc-cuenta-codigo', function (e) {
            if (e.key === 'F1' || e.keyCode === 112) {
                e.preventDefault();
                abrirConsultaCuenta($(this));
            }
        });

        $('#consultacuentaModal').on('hidden.bs.modal.concConfig', function () {
            copiarCuentaDesdeBridge();
        });

        $(document).on('click', '.eligeconsultacuentacontable', function () {
            setTimeout(copiarCuentaDesdeBridge, 50);
        });

        $(document).on('click', '.js-consulta-concepto-doc', function (e) {
            e.preventDefault();
            abrirConsultaConcepto($(this).closest('td').find('.js-doc-concepto-codigo'));
        });

        $(document).on('keydown', '.js-doc-concepto-codigo', function (e) {
            if (e.key === 'F1' || e.keyCode === 112) {
                e.preventDefault();
                abrirConsultaConcepto($(this));
            }
        });

        $(document).on('blur', '.js-doc-concepto-codigo', function () {
            resolverConceptoBlur($(this));
        });

        $(document).off('keyup.concConfigConcepto', '#consultaconcepto_ivacompra')
            .on('keyup.concConfigConcepto', '#consultaconcepto_ivacompra', function () {
                if (!$conceptoTarget.length) {
                    return;
                }
                buscarConceptoCatalogo($(this).val());
            });

        $(document).on('click', '.eligeconsultaconcepto_ivacompra', function () {
            if (!$conceptoTarget.length) {
                return;
            }
            var codigo = String($(this).closest('tr').find('.codigo').text() || '').trim();
            if (codigo !== '') {
                $conceptoTarget.val(codigo);
            }
            $conceptoTarget = $();
            $('#consultaconcepto_ivacompraModal').modal('hide');
        });
    });
})(jQuery);
