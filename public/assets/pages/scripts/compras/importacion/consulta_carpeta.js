(function ($) {
    'use strict';

    var ptrId = $();
    var ptrCodigo = $();
    var ptrEstado = $();
    var modalAbriendo = false;

    function carpeta() {
        return (typeof carpetaBase !== 'undefined' && carpetaBase) ? carpetaBase : '';
    }

    function csrf() {
        return $('meta[name="csrf-token"]').attr('content')
            || $('input[name="_token"]').first().val()
            || '';
    }

    function empresaId() {
        return $('#empresa_id').val()
            || $('select[name="empresa_id"]').val()
            || $('input[name="empresa_id"]').val()
            || '';
    }

    function apuntarDesde(el) {
        var $ctx = $(el).closest('.tm-importacion-campo');
        ptrId = $ctx.find('.importacion_id');
        ptrCodigo = $ctx.find('.codigoimportacion');
        ptrEstado = $ctx.find('.estadoimportacion');
        return $ctx;
    }

    function aplicar(id, codigo, estado) {
        if (!ptrId.length) {
            return;
        }
        ptrId.val(id || '');
        ptrCodigo.val(codigo || '');
        ptrEstado.val(estado || '');
        var $link = ptrId.closest('.tm-importacion-campo').find('.btn-link-editar-importacion');
        if (id) {
            var base = carpeta() + '/compras/importacion/' + id + '/editar?origen=modal_consulta&vista=consulta';
            $link.attr('href', base).removeClass('d-none');
        } else {
            $link.addClass('d-none');
        }
    }

    function buscar(consulta) {
        $.ajax({
            url: carpeta() + '/compras/importacion/consulta-carpeta',
            type: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': csrf() },
            data: { consulta: consulta || '', empresa_id: empresaId(), _token: csrf() },
        })
            .done(function (r) {
                $('#datos-importacion-carpeta').html((r && r.data) || '');
            })
            .fail(function () {
                $('#datos-importacion-carpeta').html('<tr><td colspan="5">Error al consultar carpetas</td></tr>');
            });
    }

    function resolverPorCodigo(codigo) {
        if (modalAbriendo || $('#consultaImportacionCarpetaModal').hasClass('show')) {
            return;
        }
        codigo = String(codigo || '').trim();
        if (!codigo) {
            aplicar('', '', '');
            return;
        }
        $.ajax({
            url: carpeta() + '/compras/importacion/resolver-carpeta',
            type: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': csrf() },
            data: { valor: codigo, empresa_id: empresaId(), _token: csrf() },
        })
            .done(function (r) {
                if (r && r.ok && r.item) {
                    aplicar(r.item.id, r.item.codigo, r.item.estado);
                } else {
                    aplicar('', codigo, '');
                }
            })
            .fail(function () {
                aplicar('', codigo, '');
            });
    }

    window.activa_eventos_consultaimportacion = function () {
        $(document)
            .off('click.consultaImportacion', '.consultaimportacion')
            .on('click.consultaImportacion', '.consultaimportacion', function (e) {
                e.preventDefault();
                modalAbriendo = true;
                apuntarDesde(this);
                $('#consulta-importacion-carpeta').val('');
                buscar('');
                $('#consultaImportacionCarpetaModal').modal('show');
            });

        $(document)
            .off('keydown.consultaImportacionF1', '.codigoimportacion')
            .on('keydown.consultaImportacionF1', '.codigoimportacion', function (e) {
                if (e.key === 'F1' || e.which === 112) {
                    e.preventDefault();
                    $(this).closest('.tm-importacion-campo').find('.consultaimportacion').trigger('click');
                }
            });

        $(document)
            .off('blur.consultaImportacion', '.codigoimportacion')
            .on('blur.consultaImportacion', '.codigoimportacion', function () {
                if ($(this).prop('readonly')) {
                    return;
                }
                apuntarDesde(this);
                resolverPorCodigo($(this).val());
            });

        $(document)
            .off('click.eligeImportacion', '.eligeconsulta-importacion-carpeta')
            .on('click.eligeImportacion', '.eligeconsulta-importacion-carpeta', function () {
                var $tr = $(this).closest('tr');
                aplicar($tr.data('id'), $tr.data('codigo'), $tr.data('estado'));
                $('#consultaImportacionCarpetaModal').modal('hide');
            });

        $('#consulta-importacion-carpeta')
            .off('keyup.consultaImportacion')
            .on('keyup.consultaImportacion', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    var $first = $('#datos-importacion-carpeta .eligeconsulta-importacion-carpeta').first();
                    if ($first.length) {
                        $first.trigger('click');
                    }
                    return;
                }
                buscar($(this).val());
            });

        $('#consultaImportacionCarpetaModal')
            .off('shown.bs.modal.importacion')
            .on('shown.bs.modal.importacion', function () {
                modalAbriendo = false;
                $('#consulta-importacion-carpeta').trigger('focus');
            })
            .off('hidden.bs.modal.importacion')
            .on('hidden.bs.modal.importacion', function () {
                modalAbriendo = false;
            });
    };

    $(function () {
        if (typeof window.activa_eventos_consultaimportacion === 'function') {
            window.activa_eventos_consultaimportacion();
        }
    });
})(jQuery);
