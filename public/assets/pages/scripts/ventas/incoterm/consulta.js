(function ($) {
    'use strict';

    var ptrIncotermId = $();
    var ptrCodigo = $();
    var ptrNombre = $();
    var modalAbriendo = false;

    function carpeta() {
        return (typeof carpetaBase !== 'undefined' && carpetaBase) ? carpetaBase : '';
    }

    function csrf() {
        return $('meta[name="csrf-token"]').attr('content')
            || $('input[name="_token"]').first().val()
            || '';
    }

    function apuntarDesde(el) {
        var $ctx = $(el).closest('.tm-incoterm-campo');
        ptrIncotermId = $ctx.find('.incoterm_id');
        ptrCodigo = $ctx.find('.codigoincoterm');
        ptrNombre = $ctx.find('.nombreincoterm');
        return $ctx;
    }

    function aplicar(id, codigo, nombre) {
        if (!ptrIncotermId.length) {
            return;
        }
        ptrIncotermId.val(id || '');
        ptrCodigo.val(codigo || '');
        ptrNombre.val(nombre || '');
        var $link = ptrIncotermId.closest('.tm-incoterm-campo').find('.btn-link-editar-incoterm');
        if (id) {
            var href = $link.attr('href') || '#';
            if (href.indexOf('/editar') === -1 && href !== '#') {
                // keep
            }
            $link.removeClass('d-none');
        } else {
            $link.addClass('d-none');
        }
    }

    function buscar(consulta) {
        $.ajax({
            url: carpeta() + '/ventas/incoterm/consulta',
            type: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': csrf() },
            data: { consulta: consulta || '', _token: csrf() },
        })
            .done(function (r) {
                $('#datosincoterm').html((r && r.data) || '');
            })
            .fail(function () {
                $('#datosincoterm').html('<tr><td colspan="4">Error al consultar Incoterms</td></tr>');
            });
    }

    function resolverPorCodigo(codigo) {
        if (modalAbriendo || $('#consultaincotermModal').hasClass('show')) {
            return;
        }
        codigo = String(codigo || '').trim();
        if (!codigo) {
            aplicar('', '', '');
            return;
        }
        $.get(carpeta() + '/ventas/incoterm/leer-por-abreviatura/' + encodeURIComponent(codigo))
            .done(function (data) {
                if (data && data.id) {
                    aplicar(data.id, data.abreviatura || codigo, data.nombre || '');
                } else {
                    aplicar('', codigo, '');
                }
            })
            .fail(function () {
                aplicar('', codigo, '');
            });
    }

    window.activa_eventos_consultaincoterm = function () {
        $(document)
            .off('click.consultaIncoterm', '.consultaincoterm')
            .on('click.consultaIncoterm', '.consultaincoterm', function (e) {
                e.preventDefault();
                modalAbriendo = true;
                apuntarDesde(this);
                $('#consultaincoterm').val('');
                buscar('');
                $('#consultaincotermModal').modal('show');
            });

        $(document)
            .off('keydown.consultaIncotermF1', '.codigoincoterm')
            .on('keydown.consultaIncotermF1', '.codigoincoterm', function (e) {
                if (e.key === 'F1' || e.which === 112) {
                    e.preventDefault();
                    $(this).closest('.tm-incoterm-campo').find('.consultaincoterm').trigger('click');
                }
            });

        $(document)
            .off('blur.consultaIncoterm', '.codigoincoterm')
            .on('blur.consultaIncoterm', '.codigoincoterm', function () {
                apuntarDesde(this);
                resolverPorCodigo($(this).val());
            });

        $(document)
            .off('click.eligeIncoterm', '.eligeconsultaincoterm')
            .on('click.eligeIncoterm', '.eligeconsultaincoterm', function () {
                var $tr = $(this).closest('tr');
                aplicar($tr.data('id'), $tr.data('abreviatura'), $tr.data('nombre'));
                $('#consultaincotermModal').modal('hide');
            });

        $('#consultaincoterm')
            .off('keyup.consultaIncoterm')
            .on('keyup.consultaIncoterm', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    var $first = $('#datosincoterm .eligeconsultaincoterm').first();
                    if ($first.length) {
                        $first.trigger('click');
                    }
                    return;
                }
                buscar($(this).val());
            });

        $('#aceptaconsultaincotermModal')
            .off('click.aceptaIncoterm')
            .on('click.aceptaIncoterm', function () {
                $('#datosincoterm .eligeconsultaincoterm').first().trigger('click');
            });

        $('#consultaincotermModal')
            .off('shown.bs.modal.incoterm')
            .on('shown.bs.modal.incoterm', function () {
                modalAbriendo = false;
                $('#consultaincoterm').trigger('focus');
            })
            .off('hidden.bs.modal.incoterm')
            .on('hidden.bs.modal.incoterm', function () {
                modalAbriendo = false;
            });
    };

    $(function () {
        if (typeof window.activa_eventos_consultaincoterm === 'function') {
            window.activa_eventos_consultaincoterm();
        }
    });
})(jQuery);
