(function ($) {
    'use strict';

    function carpeta() {
        return (typeof carpetaBase !== 'undefined' && carpetaBase) ? carpetaBase : '';
    }

    function csrf() {
        return $('meta[name="csrf-token"]').attr('content')
            || $('input[name="_token"]').first().val()
            || '';
    }

    function aplicarTipo(id, codigo, nombre) {
        $('#cp_importacion_gasto_tipo_id').val(id || '');
        $('#cp_importacion_gasto_tipo_codigo').val(codigo || '');
        $('#cp_importacion_gasto_tipo_nombre').val(nombre || '');
    }

    function buscarTipo(consulta) {
        $.ajax({
            url: carpeta() + '/compras/importacion-gasto-tipo/consulta',
            type: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': csrf() },
            data: { consulta: consulta || '', _token: csrf() },
        })
            .done(function (r) {
                $('#datos-importacion-gasto-tipo').html((r && r.data) || '');
            })
            .fail(function () {
                $('#datos-importacion-gasto-tipo').html('<tr><td colspan="4">Error al consultar tipos</td></tr>');
            });
    }

    function resolverTipo() {
        if ($('#consultaImportacionGastoTipoModal').hasClass('show')) {
            return;
        }
        var codigo = String($('#cp_importacion_gasto_tipo_codigo').val() || '').trim();
        if (!codigo) {
            aplicarTipo('', '', '');
            return;
        }
        $.ajax({
            url: carpeta() + '/compras/importacion-gasto-tipo/resolver',
            type: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': csrf() },
            data: { codigo: codigo, _token: csrf() },
        })
            .done(function (r) {
                if (r && r.ok && r.item) {
                    aplicarTipo(r.item.id, r.item.codigo, r.item.nombre);
                } else {
                    aplicarTipo('', codigo, '');
                }
            })
            .fail(function () {
                aplicarTipo('', codigo, '');
            });
    }

    $(function () {
        if (!$('#cp-panel-importacion').length) {
            return;
        }

        if (typeof window.activa_eventos_consultaimportacion === 'function') {
            window.activa_eventos_consultaimportacion();
        }

        $('#btn-cp-consulta-gasto-tipo').on('click', function () {
            $('#consultaImportacionGastoTipoModal').modal('show');
            buscarTipo('');
            setTimeout(function () { $('#consulta-importacion-gasto-tipo').focus(); }, 300);
        });

        $('#consulta-importacion-gasto-tipo').on('keyup', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                var $first = $('#datos-importacion-gasto-tipo .eligeconsulta-importacion-gasto-tipo').first();
                if ($first.length) {
                    $first.trigger('click');
                }
                return;
            }
            buscarTipo($(this).val());
        });

        $(document).on('click', '.eligeconsulta-importacion-gasto-tipo', function () {
            var $tr = $(this).closest('tr');
            aplicarTipo($tr.data('id'), $tr.data('codigo'), $tr.data('nombre'));
            $('#consultaImportacionGastoTipoModal').modal('hide');
        });

        $('#cp_importacion_gasto_tipo_codigo').on('keydown', function (e) {
            if (e.key === 'F1') {
                e.preventDefault();
                $('#btn-cp-consulta-gasto-tipo').trigger('click');
            }
        }).on('blur', function () {
            resolverTipo();
        });
    });
})(jQuery);
