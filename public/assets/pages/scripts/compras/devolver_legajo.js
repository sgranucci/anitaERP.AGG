(function (window, $) {
    'use strict';

    function labelsFor(destino) {
        if (destino === 'compras') {
            return {
                titulo: 'Devolver a Compras',
                submit: 'Devolver a Compras',
            };
        }
        return {
            titulo: 'Devolver a Cuentas a pagar',
            submit: 'Devolver a Cuentas a pagar',
        };
    }

    function syncForm($form) {
        if (!$form || !$form.length) {
            return;
        }
        var urlCxp = $form.attr('data-url-cxp') || '';
        var urlCompras = $form.attr('data-url-compras') || '';
        var $visibleItems = $form.find('.js-devolver-destino-item:visible');
        var destino = $form.find('input[name="_destino_devolver"]:checked').val();

        if ($visibleItems.length === 1) {
            destino = $visibleItems.first().data('destino');
            $visibleItems.find('input[name="_destino_devolver"]').prop('checked', true);
        } else if (!destino || !$form.find('input[name="_destino_devolver"][value="' + destino + '"]:visible').length) {
            destino = urlCxp ? 'cxp' : 'compras';
            $form.find('input[name="_destino_devolver"][value="' + destino + '"]').prop('checked', true);
        }

        var url = destino === 'compras' ? urlCompras : urlCxp;
        if (url) {
            $form.attr('action', url);
        }

        var labels = labelsFor(destino);
        var $modal = $form.closest('.modal');
        var ambosVisibles = $visibleItems.length > 1;
        $modal.find('.js-devolver-titulo').text(ambosVisibles ? 'Devolver legajo' : labels.titulo);
        $form.find('.js-devolver-submit').text(labels.submit);
        $form.find('.js-devolver-intro').toggle(ambosVisibles);
        $form.find('.js-devolver-ayuda-cxp').toggle(!ambosVisibles && destino === 'cxp');
        $form.find('.js-devolver-ayuda-compras').toggle(!ambosVisibles && destino === 'compras');

        $form.find('.js-devolver-destino-item').each(function () {
            var $item = $(this);
            var checked = $item.find('input[name="_destino_devolver"]').is(':checked');
            $item.toggleClass('active', checked && $item.is(':visible'));
        });
    }

    function applyUrls($form, urlCxp, urlCompras) {
        urlCxp = urlCxp || '';
        urlCompras = urlCompras || '';
        $form.attr('data-url-cxp', urlCxp);
        $form.attr('data-url-compras', urlCompras);

        var $itemCxp = $form.find('.js-devolver-destino-item[data-destino="cxp"]');
        var $itemCompras = $form.find('.js-devolver-destino-item[data-destino="compras"]');
        $itemCxp.toggle(!!urlCxp);
        $itemCompras.toggle(!!urlCompras);

        var ambos = !!(urlCxp && urlCompras);
        // El selector visual solo tiene sentido con 2 destinos; con uno alcanza el texto de ayuda.
        $form.find('.js-devolver-destinos').toggle(ambos);

        if (ambos) {
            $form.find('input[name="_destino_devolver"][value="cxp"]').prop('checked', true);
        } else if (urlCompras) {
            $form.find('input[name="_destino_devolver"][value="compras"]').prop('checked', true);
        } else {
            $form.find('input[name="_destino_devolver"][value="cxp"]').prop('checked', true);
        }
    }

    function bindModal($modal) {
        if (!$modal || !$modal.length || $modal.data('devolver-legajo-bound')) {
            return;
        }
        $modal.data('devolver-legajo-bound', true);
        var $form = $modal.find('form.js-devolver-legajo-form');
        $form.on('change', 'input[name="_destino_devolver"]', function () {
            syncForm($form);
        });
        $form.on('submit', function (e) {
            syncForm($form);
            if (!$form.attr('action')) {
                e.preventDefault();
                alert('No se pudo determinar el destino de la devolución.');
            }
        });
        $modal.on('show.bs.modal', function () {
            syncForm($form);
        });
        syncForm($form);
    }

    /**
     * @param {jQuery|string} modal
     * @param {{urlCxp?: string, urlCompras?: string, reset?: boolean}} opts
     */
    function open(modal, opts) {
        opts = opts || {};
        var $modal = $(modal);
        var $form = $modal.find('form.js-devolver-legajo-form');
        bindModal($modal);

        if (opts.urlCxp !== undefined || opts.urlCompras !== undefined) {
            applyUrls(
                $form,
                opts.urlCxp !== undefined ? opts.urlCxp : $form.attr('data-url-cxp'),
                opts.urlCompras !== undefined ? opts.urlCompras : $form.attr('data-url-compras')
            );
        }

        if (opts.reset !== false) {
            $form.find('input[name=observacion]').val('');
            $form.find('textarea[name=leyenda]').val('');
        }

        syncForm($form);
        $modal.modal('show');
    }

    function initAll() {
        $('.modal').has('form.js-devolver-legajo-form').each(function () {
            bindModal($(this));
        });
    }

    window.OcDevolverLegajo = {
        bindModal: bindModal,
        open: open,
        syncForm: syncForm,
        applyUrls: applyUrls,
        initAll: initAll,
    };

    $(initAll);
})(window, jQuery);
