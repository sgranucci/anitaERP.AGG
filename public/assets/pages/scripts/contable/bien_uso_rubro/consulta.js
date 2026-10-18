(function () {
    var ptrId, ptrCodigo, ptrNombre;

    function actualizarLink($ctx, id) {
        var $link = $ctx.find('.btn-link-editar-bien-uso-rubro');
        if (!$link.length) return;
        id = parseInt(id, 10) || 0;
        if (id > 0) {
            $link.attr('href', carpetaBase + '/contable/bien-uso-rubro/' + id + '/editar?origen=modal_consulta&vista=consulta').removeClass('d-none');
        } else {
            $link.attr('href', '#').addClass('d-none');
        }
    }

    function aplicar($ctx, data) {
        if (!$ctx.length || !data) return;
        $ctx.find('.bien_uso_rubro_id').val(data.id || '').trigger('change');
        $ctx.find('.codigobienusorubro').val(data.codigo || '').removeData('valorInvalido');
        $ctx.find('.nombrebienusorubro').val(data.nombre || '');
        actualizarLink($ctx, data.id);
    }

    function buscar(consulta) {
        $.ajax({
            url: carpetaBase + '/contable/bien-uso-rubro/consulta',
            type: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            data: { consulta: consulta || '' },
        }).done(function (r) {
            $('#datosbienusorubro').html((r && r.data) ? r.data : '<tr><td colspan="4">Sin resultados</td></tr>');
        }).fail(function () {
            $('#datosbienusorubro').html('<tr><td colspan="4">Error al consultar</td></tr>');
        });
    }

    function resolver(codigo, $input, onDone) {
        var cod = (codigo || '').trim();
        var $ctx = $input.closest('.tm-bien-uso-rubro-campo');
        if (!cod) {
            $ctx.find('.bien_uso_rubro_id').val('');
            $ctx.find('.nombrebienusorubro').val('');
            actualizarLink($ctx, 0);
            if (onDone) onDone(null);
            return;
        }
        $.getJSON(carpetaBase + '/contable/bien-uso-rubro/resolver', { valor: cod })
            .done(function (data) {
                if (!data || !data.ok) {
                    alert((data && data.mensaje) || 'Rubro no encontrado');
                    $input.data('valorInvalido', cod).trigger('focus');
                    if (onDone) onDone(null);
                    return;
                }
                aplicar($ctx, data);
                if (onDone) onDone(data);
            })
            .fail(function () {
                alert('Error al resolver rubro');
                if (onDone) onDone(null);
            });
    }

    window.activa_eventos_consultabienusorubro = function () {
        $(document).off('click.burubro', '.consultabienusorubro').on('click.burubro', '.consultabienusorubro', function (e) {
            e.preventDefault();
            var $ctx = $(this).closest('.tm-bien-uso-rubro-campo');
            ptrId = $ctx.find('.bien_uso_rubro_id');
            ptrCodigo = $ctx.find('.codigobienusorubro');
            ptrNombre = $ctx.find('.nombrebienusorubro');
            var valor = (ptrCodigo.val() || '').trim();
            buscar(valor);
            $('#consultabienusorubro').val(valor);
            $('#consultabienusorubroModal').modal('show');
        });

        $('#consultabienusorubroModal').off('shown.bs.modal.burubro').on('shown.bs.modal.burubro', function () {
            $(this).find('#consultabienusorubro').focus().select();
        });

        $(document).off('keyup.burubro', '#consultabienusorubro').on('keyup.burubro', '#consultabienusorubro', function (e) {
            if (e.key === 'Enter') return;
            buscar($(this).val());
        });

        $(document).off('keydown.burubroModal', '#consultabienusorubro').on('keydown.burubroModal', '#consultabienusorubro', function (e) {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            $('#datosbienusorubro .eligeconsultabienusorubro').first().trigger('click');
        });

        $(document).off('click.burubroElegir', '.eligeconsultabienusorubro').on('click.burubroElegir', '.eligeconsultabienusorubro', function (e) {
            e.preventDefault();
            var $tr = $(this).closest('tr');
            var data = { id: $tr.find('.id').text().trim(), codigo: $tr.find('.codigo').text().trim(), nombre: $tr.find('.nombre').text().trim() };
            if (ptrId) ptrId.val(data.id).trigger('change');
            if (ptrCodigo) ptrCodigo.val(data.codigo);
            if (ptrNombre) ptrNombre.val(data.nombre);
            actualizarLink(ptrId ? ptrId.closest('.tm-bien-uso-rubro-campo') : $(), data.id);
            $('#consultabienusorubroModal').modal('hide');
        });

        $(document).off('mousedown.burubro', '.consultabienusorubro').on('mousedown.burubro', '.consultabienusorubro', function () {
            $(this).closest('.tm-bien-uso-rubro-campo').find('.codigobienusorubro').data('abriendoModal', true);
        });

        $(document).off('blur.burubro', '.tm-bien-uso-rubro-campo .codigobienusorubro').on('blur.burubro', '.tm-bien-uso-rubro-campo .codigobienusorubro', function () {
            var $input = $(this);
            if ($input.data('abriendoModal')) { $input.removeData('abriendoModal'); return; }
            var modal = document.getElementById('consultabienusorubroModal');
            if (modal && (modal.classList.contains('show') || modal.classList.contains('in'))) return;
            resolver($input.val(), $input);
        });

        $(document).off('keydown.burubroCod', '.tm-bien-uso-rubro-campo .codigobienusorubro').on('keydown.burubroCod', '.tm-bien-uso-rubro-campo .codigobienusorubro', function (e) {
            if (e.key === 'F1') {
                e.preventDefault();
                $(this).closest('.tm-bien-uso-rubro-campo').find('.consultabienusorubro').trigger('click');
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

        $(document).off('input.burubro', '.tm-bien-uso-rubro-campo .codigobienusorubro').on('input.burubro', '.tm-bien-uso-rubro-campo .codigobienusorubro', function () {
            $(this).removeData('valorInvalido');
        });
    };
})();
