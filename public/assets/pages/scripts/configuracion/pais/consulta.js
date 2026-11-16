(function ($) {
    'use strict';

    var ptrId = $();
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
        var $ctx = $(el).closest('.tm-pais-campo');
        ptrId = $ctx.find('.pais_id');
        ptrCodigo = $ctx.find('.codigopais');
        ptrNombre = $ctx.find('.nombrepais');
        return $ctx;
    }

    function aplicar(id, codigo, nombre) {
        if (!ptrId.length) {
            return;
        }
        ptrId.val(id || '');
        ptrCodigo.val(codigo || '');
        ptrNombre.val(nombre || '');
        var $link = ptrId.closest('.tm-pais-campo').find('.btn-link-editar-pais');
        if (id) {
            $link.removeClass('d-none');
        } else {
            $link.addClass('d-none');
        }
    }

    function buscar(consulta) {
        $.ajax({
            url: carpeta() + '/configuracion/pais/consulta',
            type: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': csrf() },
            data: { consulta: consulta || '', _token: csrf() },
        })
            .done(function (r) {
                $('#datospais').html((r && r.data) || '');
            })
            .fail(function () {
                $('#datospais').html('<tr><td colspan="4">Error al consultar países</td></tr>');
            });
    }

    function resolverPorCodigo(codigo) {
        if (modalAbriendo || $('#consultapaisModal').hasClass('show')) {
            return;
        }
        codigo = String(codigo || '').trim();
        if (!codigo) {
            aplicar('', '', '');
            return;
        }
        $.get(carpeta() + '/configuracion/pais/leer-por-codigo/' + encodeURIComponent(codigo))
            .done(function (data) {
                if (data && data.id) {
                    aplicar(data.id, data.codigo || codigo, data.nombre || '');
                } else {
                    aplicar('', codigo, '');
                }
            })
            .fail(function () {
                aplicar('', codigo, '');
            });
    }

    window.activa_eventos_consultapais = function () {
        $(document)
            .off('click.consultaPais', '.consultapais')
            .on('click.consultaPais', '.consultapais', function (e) {
                e.preventDefault();
                modalAbriendo = true;
                apuntarDesde(this);
                $('#consultapais').val('');
                buscar('');
                $('#consultapaisModal').modal('show');
            });

        $(document)
            .off('keydown.consultaPaisF1', '.codigopais')
            .on('keydown.consultaPaisF1', '.codigopais', function (e) {
                if (e.key === 'F1' || e.which === 112) {
                    e.preventDefault();
                    $(this).closest('.tm-pais-campo').find('.consultapais').trigger('click');
                }
            });

        $(document)
            .off('blur.consultaPais', '.codigopais')
            .on('blur.consultaPais', '.codigopais', function () {
                if ($(this).prop('readonly')) {
                    return;
                }
                apuntarDesde(this);
                resolverPorCodigo($(this).val());
            });

        $(document)
            .off('click.eligePais', '.eligeconsultapais')
            .on('click.eligePais', '.eligeconsultapais', function () {
                var $tr = $(this).closest('tr');
                aplicar($tr.data('id'), $tr.data('codigo'), $tr.data('nombre'));
                $('#consultapaisModal').modal('hide');
            });

        $('#consultapais')
            .off('keyup.consultaPais')
            .on('keyup.consultaPais', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    $('#datospais .eligeconsultapais').first().trigger('click');
                    return;
                }
                buscar($(this).val());
            });

        $('#aceptaconsultapaisModal')
            .off('click.aceptaPais')
            .on('click.aceptaPais', function () {
                $('#datospais .eligeconsultapais').first().trigger('click');
            });

        $('#consultapaisModal')
            .off('shown.bs.modal.pais')
            .on('shown.bs.modal.pais', function () {
                modalAbriendo = false;
                $('#consultapais').trigger('focus');
            })
            .off('hidden.bs.modal.pais')
            .on('hidden.bs.modal.pais', function () {
                modalAbriendo = false;
            });
    };

    $(function () {
        if (typeof window.activa_eventos_consultapais === 'function') {
            window.activa_eventos_consultapais();
        }
    });
})(jQuery);
