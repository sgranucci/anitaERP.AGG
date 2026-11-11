(function ($) {
    'use strict';

    var $modal = $('#modal-bienes-uso-provisorios');
    if (!$modal.length) {
        return;
    }

    var nextIdx = $('#tbody-bienes-uso-provisorios .js-renglon-bien-uso').length;

    function mostrarError(msg) {
        var $err = $('#bienes-uso-provisorios-error');
        if (!msg) {
            $err.addClass('d-none').text('');
            return;
        }
        $err.removeClass('d-none').text(msg);
    }

    function reindexarIds($tr, idx) {
        var inputId = 'bu_cc_' + idx;
        $tr.find('.tm-centrocosto-campo').attr('id', 'tm_centrocosto_bu_' + idx);
        $tr.find('.centrocosto_id').attr('id', inputId);
        $tr.find('.codigocentrocosto').attr('id', inputId + '_codigo');
        $tr.find('.descripcioncentrocosto').attr('id', inputId + '_descripcion');
    }

    function agregarRenglon() {
        var tpl = document.getElementById('template-renglon-bien-uso-provisorio');
        if (!tpl) {
            return;
        }
        var html = tpl.innerHTML.replace(/__IDX__/g, String(nextIdx));
        var $tr = $(html);
        reindexarIds($tr, nextIdx);
        nextIdx += 1;
        $('#tbody-bienes-uso-provisorios').append($tr);
        if (typeof window.activa_eventos_consultacentrocosto === 'function') {
            window.activa_eventos_consultacentrocosto();
        }
    }

    function recolectarRenglones() {
        var rows = [];
        $('#tbody-bienes-uso-provisorios .js-renglon-bien-uso').each(function () {
            var $tr = $(this);
            rows.push({
                descripcion: $.trim($tr.find('.js-bu-descripcion').val() || ''),
                valor_origen: $tr.find('.js-bu-valor').val() || '',
                tipo_bien: $tr.find('.js-bu-tipo-bien').val() || 'I',
                centrocosto_id: $tr.find('.js-bu-centrocosto-id').val() || '',
            });
        });
        return rows;
    }

    $('#btn-agregar-renglon-bien-uso').on('click', function () {
        agregarRenglon();
    });

    $(document).on('click', '#tbody-bienes-uso-provisorios .js-bu-quitar-renglon', function () {
        var $tbody = $('#tbody-bienes-uso-provisorios');
        if ($tbody.find('.js-renglon-bien-uso').length <= 1) {
            var $tr = $(this).closest('.js-renglon-bien-uso');
            $tr.find('.js-bu-descripcion').val('');
            $tr.find('.js-bu-valor').val('');
            return;
        }
        $(this).closest('.js-renglon-bien-uso').remove();
    });

    $('#btn-abrir-modal-bienes-uso').on('click', function () {
        mostrarError('');
        $modal.modal('show');
    });

    $('#btn-guardar-bienes-uso-provisorios').on('click', function () {
        mostrarError('');
        var renglones = recolectarRenglones();
        if (!renglones.length) {
            mostrarError('Indique al menos un renglón.');
            return;
        }
        var $btn = $(this).prop('disabled', true);
        $.ajax({
            url: $modal.data('url'),
            type: 'POST',
            dataType: 'json',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                Accept: 'application/json',
            },
            data: { renglones: renglones },
        })
            .done(function (resp) {
                $modal.modal('hide');
                // Reload: el mensaje queda en session flash (includes.mensaje).
                // Si hay URL de listado Provisorio, ir ahí en solapa nueva.
                if (resp && resp.url_listado) {
                    window.open(resp.url_listado, '_blank', 'noopener');
                }
                window.location.reload();
            })
            .fail(function (xhr) {
                var msg = 'No se pudieron guardar los bienes.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                mostrarError(msg);
            })
            .always(function () {
                $btn.prop('disabled', false);
            });
    });

    if (String($modal.data('abrir')) === '1') {
        $(function () {
            $modal.modal('show');
        });
    }
})(jQuery);
