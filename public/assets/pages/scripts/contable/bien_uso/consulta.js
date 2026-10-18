(function () {
    var ptrId, ptrCodigo, ptrDesc;

    function actualizarLink($ctx, id) {
        var $link = $ctx.find('.btn-link-editar-bien-uso');
        if (!$link.length) return;
        id = parseInt(id, 10) || 0;
        if (id > 0) {
            $link.attr('href', carpetaBase + '/contable/bien-uso/' + id + '/editar?origen=modal_consulta&vista=consulta').removeClass('d-none');
        } else {
            $link.attr('href', '#').addClass('d-none');
        }
    }

    function aplicar($ctx, data) {
        if (!$ctx.length || !data) return;
        $ctx.find('.bien_uso_id').val(data.id || '').trigger('change');
        $ctx.find('.codigobienuso').val(data.codigo || '').removeData('valorInvalido');
        $ctx.find('.descripcionbienuso').val(data.descripcion || data.nombre || '');
        actualizarLink($ctx, data.id);
    }

    function buscar(consulta) {
        $.ajax({
            url: carpetaBase + '/contable/bien-uso/consulta',
            type: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            data: { consulta: consulta || '' },
        }).done(function (r) {
            $('#datosbienuso').html((r && r.data) ? r.data : '<tr><td colspan="4">Sin resultados</td></tr>');
        }).fail(function () {
            $('#datosbienuso').html('<tr><td colspan="4">Error al consultar</td></tr>');
        });
    }

    function resolver(codigo, $input, onDone) {
        var cod = (codigo || '').trim();
        var $ctx = $input.closest('.tm-bien-uso-campo');
        if (!cod) {
            $ctx.find('.bien_uso_id').val('');
            $ctx.find('.descripcionbienuso').val('');
            actualizarLink($ctx, 0);
            if (onDone) onDone(null);
            return;
        }
        $.getJSON(carpetaBase + '/contable/bien-uso/resolver', { valor: cod })
            .done(function (data) {
                if (!data || !data.ok) {
                    alert((data && data.mensaje) || 'Bien de uso no encontrado');
                    $input.data('valorInvalido', cod).trigger('focus');
                    if (onDone) onDone(null);
                    return;
                }
                aplicar($ctx, data);
                if (onDone) onDone(data);
            })
            .fail(function () {
                alert('Error al resolver bien de uso');
                if (onDone) onDone(null);
            });
    }

    window.activa_eventos_consultabienuso = function () {
        $(document).off('click.bubien', '.consultabienuso').on('click.bubien', '.consultabienuso', function (e) {
            e.preventDefault();
            var $ctx = $(this).closest('.tm-bien-uso-campo');
            ptrId = $ctx.find('.bien_uso_id');
            ptrCodigo = $ctx.find('.codigobienuso');
            ptrDesc = $ctx.find('.descripcionbienuso');
            var valor = (ptrCodigo.val() || '').trim();
            buscar(valor);
            $('#consultabienuso').val(valor);
            $('#consultabienusoModal').modal('show');
        });

        $('#consultabienusoModal').off('shown.bs.modal.bubien').on('shown.bs.modal.bubien', function () {
            $(this).find('#consultabienuso').focus().select();
        });

        $(document).off('keyup.bubien', '#consultabienuso').on('keyup.bubien', '#consultabienuso', function (e) {
            if (e.key === 'Enter') return;
            buscar($(this).val());
        });

        $(document).off('keydown.bubienModal', '#consultabienuso').on('keydown.bubienModal', '#consultabienuso', function (e) {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            $('#datosbienuso .eligeconsultabienuso').first().trigger('click');
        });

        $(document).off('click.bubienElegir', '.eligeconsultabienuso').on('click.bubienElegir', '.eligeconsultabienuso', function (e) {
            e.preventDefault();
            var $tr = $(this).closest('tr');
            var data = {
                id: $tr.find('.id').text().trim(),
                codigo: $tr.find('.codigo').text().trim(),
                descripcion: $tr.find('.descripcion').text().trim(),
            };
            if (ptrId) ptrId.val(data.id).trigger('change');
            if (ptrCodigo) ptrCodigo.val(data.codigo);
            if (ptrDesc) ptrDesc.val(data.descripcion);
            actualizarLink(ptrId ? ptrId.closest('.tm-bien-uso-campo') : $(), data.id);
            $('#consultabienusoModal').modal('hide');
        });

        $(document).off('mousedown.bubien', '.consultabienuso').on('mousedown.bubien', '.consultabienuso', function () {
            $(this).closest('.tm-bien-uso-campo').find('.codigobienuso').data('abriendoModal', true);
        });

        $(document).off('blur.bubien', '.tm-bien-uso-campo .codigobienuso').on('blur.bubien', '.tm-bien-uso-campo .codigobienuso', function () {
            var $input = $(this);
            if ($input.data('abriendoModal')) { $input.removeData('abriendoModal'); return; }
            var modal = document.getElementById('consultabienusoModal');
            if (modal && (modal.classList.contains('show') || modal.classList.contains('in'))) return;
            resolver($input.val(), $input);
        });

        $(document).off('keydown.bubienCod', '.tm-bien-uso-campo .codigobienuso').on('keydown.bubienCod', '.tm-bien-uso-campo .codigobienuso', function (e) {
            if (e.key === 'F1') {
                e.preventDefault();
                $(this).closest('.tm-bien-uso-campo').find('.consultabienuso').trigger('click');
                return;
            }
            if (e.key === 'Enter') {
                e.preventDefault();
                var $input = $(this);
                resolver($input.val(), $input, function (data) {
                    if (!data) return;
                    var $next = $input.closest('.form-group').nextAll('.form-group').find('input:visible, select:visible').first();
                    if ($next.length) $next.trigger('focus');
                });
            }
        });
    };
})();
