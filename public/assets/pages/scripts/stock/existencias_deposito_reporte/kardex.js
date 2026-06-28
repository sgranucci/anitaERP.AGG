(function ($) {
    'use strict';

    function volverUrl() {
        return window.location.pathname + window.location.search;
    }

    function datosDesdeCelda(el) {
        return {
            articuloId: parseInt(el.getAttribute('data-articulo-id'), 10) || 0,
            depositoId: parseInt(el.getAttribute('data-deposito-id'), 10) || 0,
            sku: el.getAttribute('data-articulo-sku') || '',
            descripcion: el.getAttribute('data-articulo-descripcion') || '',
        };
    }

    $(document).on('click', '.btn-kardex-existencias-deposito', function (e) {
        e.preventDefault();
        e.stopPropagation();

        if (typeof window.abrirKardexArticulo !== 'function') {
            return;
        }

        var btn = this;
        var articuloId = parseInt(btn.getAttribute('data-articulo-id'), 10) || 0;
        if (articuloId <= 0) {
            return;
        }

        window.abrirKardexArticulo(articuloId, 0, [], {
            sku: btn.getAttribute('data-articulo-sku') || '',
            descripcion: btn.getAttribute('data-articulo-descripcion') || '',
            volverUrl: volverUrl(),
        });
    });

    $(document).on('click', '.celda-saldo-kardex', function (e) {
        if (e.target.closest('.btn-kardex-existencias-deposito')) {
            return;
        }

        e.preventDefault();
        e.stopPropagation();

        if (typeof window.abrirUrlKardex !== 'function') {
            return;
        }

        var datos = datosDesdeCelda(this);
        if (datos.articuloId <= 0 || datos.depositoId <= 0) {
            return;
        }

        window.abrirUrlKardex(datos.articuloId, datos.depositoId, volverUrl());
    });
})(jQuery);
