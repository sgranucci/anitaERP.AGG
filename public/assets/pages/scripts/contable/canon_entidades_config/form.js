(function ($) {
    'use strict';

    function listaConceptos() {
        return (window.CANON_CONFIG && window.CANON_CONFIG.conceptos) || [];
    }

    function resolverPorCodigo(lista, codigo) {
        var c = String(codigo || '').trim();
        if (!c) {
            return null;
        }
        for (var i = 0; i < lista.length; i++) {
            if (String(lista[i].codigo) === c) {
                return lista[i];
            }
        }
        return null;
    }

    function aplicarConcepto(target, item) {
        $('#' + target).val(item ? item.id : '');
        $('#' + target + '_codigo').val(item ? item.codigo : '');
        $('#' + target + '_nombre').val(item ? item.nombre : '');
    }

    function marcarInvalido($input, invalido) {
        $input.toggleClass('is-invalid', !!invalido);
    }

    $(function () {
        $(document).on('blur', '.codigo-concepto-canon-ent', function () {
            var $input = $(this);
            var target = $input.data('target');
            var codigo = String($input.val() || '').trim();
            if (codigo === '') {
                aplicarConcepto(target, null);
                marcarInvalido($input, false);
                return;
            }
            var item = resolverPorCodigo(listaConceptos(), codigo);
            if (!item) {
                aplicarConcepto(target, null);
                marcarInvalido($input, true);
                return;
            }
            marcarInvalido($input, false);
            aplicarConcepto(target, item);
        });

        $(document).on('input', '.codigo-concepto-canon-ent', function () {
            marcarInvalido($(this), false);
        });

        $(document).on('click', '.consulta-concepto-canon-ent', function () {
            var target = $(this).data('target');
            var $modal = $('#modal-canon-ent-concepto');
            if (!$modal.length) {
                return;
            }
            var $tbody = $modal.find('tbody').empty();
            listaConceptos().forEach(function (row) {
                var $btn = $('<button type="button" class="btn btn-warning btn-sm">Elegir</button>');
                $btn.on('click', function () {
                    aplicarConcepto(target, row);
                    marcarInvalido($('#' + target + '_codigo'), false);
                    $modal.modal('hide');
                });
                $tbody.append(
                    $('<tr>')
                        .append($('<td>').text(row.codigo))
                        .append($('<td>').text(row.nombre))
                        .append($('<td>').append($btn))
                );
            });
            $modal.modal('show');
        });
    });
})(jQuery);
