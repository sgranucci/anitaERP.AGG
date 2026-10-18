(function () {
    var ptrId, ptrCodigo, ptrNombre;

    function actualizarLink($ctx, id) {
        var $link = $ctx.find('.btn-link-editar-bien-uso-subrubro');
        if (!$link.length) return;
        id = parseInt(id, 10) || 0;
        if (id > 0) {
            $link.attr('href', carpetaBase + '/contable/bien-uso-subrubro/' + id + '/editar?origen=modal_consulta&vista=consulta').removeClass('d-none');
        } else {
            $link.attr('href', '#').addClass('d-none');
        }
    }

    function aplicar($ctx, data) {
        if (!$ctx.length || !data) return;
        $ctx.find('.bien_uso_subrubro_id').val(data.id || '').trigger('change');
        $ctx.find('.codigobienusosubrubro').val(data.codigo || '').removeData('valorInvalido');
        $ctx.find('.nombrebienusosubrubro').val(data.nombre || '');
        actualizarLink($ctx, data.id);
    }

    function rubroFiltroId() {
        var $rubro = $('.tm-bien-uso-rubro-campo').first().find('.bien_uso_rubro_id');
        return parseInt($rubro.val(), 10) || 0;
    }

    function buscar(consulta) {
        $.ajax({
            url: carpetaBase + '/contable/bien-uso-subrubro/consulta',
            type: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            data: { consulta: consulta || '', bien_uso_rubro_id: rubroFiltroId() || '' },
        }).done(function (r) {
            $('#datosbienusosubrubro').html((r && r.data) ? r.data : '<tr><td colspan="5">Sin resultados</td></tr>');
        }).fail(function () {
            $('#datosbienusosubrubro').html('<tr><td colspan="5">Error al consultar</td></tr>');
        });
    }

    function resolver(codigo, $input, onDone) {
        var cod = (codigo || '').trim();
        var $ctx = $input.closest('.tm-bien-uso-subrubro-campo');
        if (!cod) {
            $ctx.find('.bien_uso_subrubro_id').val('');
            $ctx.find('.nombrebienusosubrubro').val('');
            actualizarLink($ctx, 0);
            if (onDone) onDone(null);
            return;
        }
        $.getJSON(carpetaBase + '/contable/bien-uso-subrubro/resolver', {
            valor: cod,
            bien_uso_rubro_id: rubroFiltroId() || '',
        }).done(function (data) {
            if (!data || !data.ok) {
                alert((data && data.mensaje) || 'Subrubro no encontrado');
                $input.data('valorInvalido', cod).trigger('focus');
                if (onDone) onDone(null);
                return;
            }
            aplicar($ctx, data);
            if (onDone) onDone(data);
        }).fail(function () {
            alert('Error al resolver subrubro');
            if (onDone) onDone(null);
        });
    }

    window.activa_eventos_consultabienusosubrubro = function () {
        $(document).off('click.busubro', '.consultabienusosubrubro').on('click.busubro', '.consultabienusosubrubro', function (e) {
            e.preventDefault();
            var $ctx = $(this).closest('.tm-bien-uso-subrubro-campo');
            ptrId = $ctx.find('.bien_uso_subrubro_id');
            ptrCodigo = $ctx.find('.codigobienusosubrubro');
            ptrNombre = $ctx.find('.nombrebienusosubrubro');
            var valor = (ptrCodigo.val() || '').trim();
            buscar(valor);
            $('#consultabienusosubrubro').val(valor);
            $('#consultabienusosubrubroModal').modal('show');
        });

        $('#consultabienusosubrubroModal').off('shown.bs.modal.busubro').on('shown.bs.modal.busubro', function () {
            $(this).find('#consultabienusosubrubro').focus().select();
        });

        $(document).off('keyup.busubro', '#consultabienusosubrubro').on('keyup.busubro', '#consultabienusosubrubro', function (e) {
            if (e.key === 'Enter') return;
            buscar($(this).val());
        });

        $(document).off('keydown.busubroModal', '#consultabienusosubrubro').on('keydown.busubroModal', '#consultabienusosubrubro', function (e) {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            $('#datosbienusosubrubro .eligeconsultabienusosubrubro').first().trigger('click');
        });

        $(document).off('click.busubroElegir', '.eligeconsultabienusosubrubro').on('click.busubroElegir', '.eligeconsultabienusosubrubro', function (e) {
            e.preventDefault();
            var $tr = $(this).closest('tr');
            var data = { id: $tr.find('.id').text().trim(), codigo: $tr.find('.codigo').text().trim(), nombre: $tr.find('.nombre').text().trim() };
            if (ptrId) ptrId.val(data.id).trigger('change');
            if (ptrCodigo) ptrCodigo.val(data.codigo);
            if (ptrNombre) ptrNombre.val(data.nombre);
            actualizarLink(ptrId ? ptrId.closest('.tm-bien-uso-subrubro-campo') : $(), data.id);
            $('#consultabienusosubrubroModal').modal('hide');
        });

        $(document).off('mousedown.busubro', '.consultabienusosubrubro').on('mousedown.busubro', '.consultabienusosubrubro', function () {
            $(this).closest('.tm-bien-uso-subrubro-campo').find('.codigobienusosubrubro').data('abriendoModal', true);
        });

        $(document).off('blur.busubro', '.tm-bien-uso-subrubro-campo .codigobienusosubrubro').on('blur.busubro', '.tm-bien-uso-subrubro-campo .codigobienusosubrubro', function () {
            var $input = $(this);
            if ($input.data('abriendoModal')) { $input.removeData('abriendoModal'); return; }
            var modal = document.getElementById('consultabienusosubrubroModal');
            if (modal && (modal.classList.contains('show') || modal.classList.contains('in'))) return;
            resolver($input.val(), $input);
        });

        $(document).off('keydown.busubroCod', '.tm-bien-uso-subrubro-campo .codigobienusosubrubro').on('keydown.busubroCod', '.tm-bien-uso-subrubro-campo .codigobienusosubrubro', function (e) {
            if (e.key === 'F1') {
                e.preventDefault();
                $(this).closest('.tm-bien-uso-subrubro-campo').find('.consultabienusosubrubro').trigger('click');
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
