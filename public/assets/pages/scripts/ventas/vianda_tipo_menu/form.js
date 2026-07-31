$(function () {
    function clonarFilaDia(dia) {
        const html = $('#template-vianda-articulo-dia').html().replace(/__DIA__/g, String(dia));
        return $(html.trim());
    }

    function esTeclaF1(e) {
        return e && (e.key === 'F1' || e.code === 'F1' || e.keyCode === 112);
    }

    function esTeclaEnter(e) {
        return e && (e.key === 'Enter' || e.which === 13 || e.keyCode === 13);
    }

    function contextoArticuloVianda(el) {
        if (!el || !el.closest) {
            return null;
        }
        return el.closest('#tabla-vianda-semana .item-vianda-articulo-dia, #tabla-vianda-semana .tm-articulo-campo');
    }

    function modalConsultaArticuloAbierto() {
        var $m = $('#consultaarticuloModal');
        return $m.length && $m.hasClass('show');
    }

    $(document).on('click', '.agrega-articulo-dia', function (e) {
        e.preventDefault();
        const dia = $(this).data('dia');
        const $contenedor = $('#vianda-dia-items-' + dia);
        $contenedor.append(clonarFilaDia(dia));
    });

    $(document).on('click', '.eliminar-articulo-dia', function () {
        const $contenedor = $(this).closest('.vianda-dia-items');
        const $filas = $contenedor.find('.item-vianda-articulo-dia');
        if ($filas.length <= 1) {
            const $row = $(this).closest('.item-vianda-articulo-dia');
            $row.find('input').val('');
            return;
        }
        $(this).closest('.item-vianda-articulo-dia').remove();
    });

    // Al editar el SKU a mano, invalidar id/descripción para forzar revalidación con Enter.
    $(document).on('input', '#tabla-vianda-semana .codigoarticulo', function () {
        var $ctx = $(this).closest('.item-vianda-articulo-dia, .tm-articulo-campo');
        if (!$ctx.length) {
            return;
        }
        $ctx.find('.articulo_id').val('');
        $ctx.find('.descripcionarticulo').val('');
    });

    // Capture: gana a bloqueos legacy de Enter/F1 en handlers globales de consulta.
    document.addEventListener('keydown', function (e) {
        var target = e.target;
        if (!target || !target.classList || !target.classList.contains('codigoarticulo')) {
            return;
        }
        var ctx = contextoArticuloVianda(target);
        if (!ctx) {
            return;
        }

        if (esTeclaF1(e)) {
            if (modalConsultaArticuloAbierto()) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            if (typeof e.stopImmediatePropagation === 'function') {
                e.stopImmediatePropagation();
            }
            var $btn = $(ctx).find('.consultaarticulo').first();
            if ($btn.length) {
                $btn.trigger('click');
            }
            return;
        }

        if (esTeclaEnter(e)) {
            e.preventDefault();
            e.stopPropagation();
            if (typeof e.stopImmediatePropagation === 'function') {
                e.stopImmediatePropagation();
            }
            $(target).trigger('change');
        }
    }, true);

    if (typeof activa_eventos_consultaarticulo === 'function') {
        activa_eventos_consultaarticulo();
    }
});
