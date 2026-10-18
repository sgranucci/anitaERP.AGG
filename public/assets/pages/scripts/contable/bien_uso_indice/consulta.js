(function () {
    var ptrId, ptrCodigo, ptrNombre;

    function actualizarLink($ctx, id) {
        var $link = $ctx.find('.btn-link-editar-bien-uso-indice');
        if (!$link.length) return;
        id = parseInt(id, 10) || 0;
        if (id > 0) {
            $link.attr('href', carpetaBase + '/contable/bien-uso-indice/' + id + '/editar?origen=modal_consulta&vista=consulta').removeClass('d-none');
        } else {
            $link.attr('href', '#').addClass('d-none');
        }
    }

    function aplicar($ctx, data) {
        if (!$ctx.length || !data) return;
        $ctx.find('.bien_uso_indice_id').val(data.id || '').trigger('change');
        $ctx.find('.codigobienusoindice').val(data.codigo || '').removeData('valorInvalido');
        $ctx.find('.nombrebienusoindice').val(data.nombre || '');
        actualizarLink($ctx, data.id);
    }

    function buscar(consulta) {
        $.ajax({
            url: carpetaBase + '/contable/bien-uso-indice/consulta',
            type: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            data: { consulta: consulta || '' },
        }).done(function (r) {
            $('#datosbienusoindice').html((r && r.data) ? r.data : '<tr><td colspan="5">Sin resultados</td></tr>');
        }).fail(function () {
            $('#datosbienusoindice').html('<tr><td colspan="5">Error al consultar</td></tr>');
        });
    }

    function resolver(codigo, $input, onDone) {
        var cod = (codigo || '').trim();
        var $ctx = $input.closest('.tm-bien-uso-indice-campo');
        if (!cod) {
            $ctx.find('.bien_uso_indice_id').val('');
            $ctx.find('.nombrebienusoindice').val('');
            actualizarLink($ctx, 0);
            if (onDone) onDone(null);
            return;
        }
        $.getJSON(carpetaBase + '/contable/bien-uso-indice/resolver', { valor: cod })
            .done(function (data) {
                if (!data || !data.ok) {
                    alert((data && data.mensaje) || 'Índice no encontrado');
                    $input.data('valorInvalido', cod).trigger('focus');
                    if (onDone) onDone(null);
                    return;
                }
                aplicar($ctx, data);
                if (onDone) onDone(data);
            })
            .fail(function () {
                alert('Error al resolver índice');
                if (onDone) onDone(null);
            });
    }

    window.activa_eventos_consultabienusoindice = function () {
        $(document).off('click.buindice', '.consultabienusoindice').on('click.buindice', '.consultabienusoindice', function (e) {
            e.preventDefault();
            var $ctx = $(this).closest('.tm-bien-uso-indice-campo');
            ptrId = $ctx.find('.bien_uso_indice_id');
            ptrCodigo = $ctx.find('.codigobienusoindice');
            ptrNombre = $ctx.find('.nombrebienusoindice');
            var valor = (ptrCodigo.val() || '').trim();
            buscar(valor);
            $('#consultabienusoindice').val(valor);
            $('#consultabienusoindiceModal').modal('show');
        });

        $('#consultabienusoindiceModal').off('shown.bs.modal.buindice').on('shown.bs.modal.buindice', function () {
            $(this).find('#consultabienusoindice').focus().select();
        });

        $(document).off('keyup.buindice', '#consultabienusoindice').on('keyup.buindice', '#consultabienusoindice', function (e) {
            if (e.key === 'Enter') return;
            buscar($(this).val());
        });

        $(document).off('keydown.buindiceModal', '#consultabienusoindice').on('keydown.buindiceModal', '#consultabienusoindice', function (e) {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            $('#datosbienusoindice .eligeconsultabienusoindice').first().trigger('click');
        });

        $(document).off('click.buindiceElegir', '.eligeconsultabienusoindice').on('click.buindiceElegir', '.eligeconsultabienusoindice', function (e) {
            e.preventDefault();
            var $tr = $(this).closest('tr');
            var data = { id: $tr.find('.id').text().trim(), codigo: $tr.find('.codigo').text().trim(), nombre: $tr.find('.nombre').text().trim() };
            if (ptrId) ptrId.val(data.id).trigger('change');
            if (ptrCodigo) ptrCodigo.val(data.codigo);
            if (ptrNombre) ptrNombre.val(data.nombre);
            actualizarLink(ptrId ? ptrId.closest('.tm-bien-uso-indice-campo') : $(), data.id);
            $('#consultabienusoindiceModal').modal('hide');
        });

        $(document).off('mousedown.buindice', '.consultabienusoindice').on('mousedown.buindice', '.consultabienusoindice', function () {
            $(this).closest('.tm-bien-uso-indice-campo').find('.codigobienusoindice').data('abriendoModal', true);
        });

        $(document).off('blur.buindice', '.tm-bien-uso-indice-campo .codigobienusoindice').on('blur.buindice', '.tm-bien-uso-indice-campo .codigobienusoindice', function () {
            var $input = $(this);
            if ($input.data('abriendoModal')) { $input.removeData('abriendoModal'); return; }
            var modal = document.getElementById('consultabienusoindiceModal');
            if (modal && (modal.classList.contains('show') || modal.classList.contains('in'))) return;
            resolver($input.val(), $input);
        });

        $(document).off('keydown.buindiceCod', '.tm-bien-uso-indice-campo .codigobienusoindice').on('keydown.buindiceCod', '.tm-bien-uso-indice-campo .codigobienusoindice', function (e) {
            if (e.key === 'F1') {
                e.preventDefault();
                $(this).closest('.tm-bien-uso-indice-campo').find('.consultabienusoindice').trigger('click');
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
