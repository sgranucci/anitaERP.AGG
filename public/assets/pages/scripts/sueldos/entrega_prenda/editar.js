(function ($) {
    'use strict';

    var $tabla = $('#editar-entrega-tabla-lineas');
    if (!$tabla.length) {
        return;
    }

    var $tbody = $tabla.find('tbody');
    var tplRenglon = document.getElementById('editar-entrega-template-renglon');

    function escapar(txt) {
        return $('<div>').text(txt == null ? '' : String(txt)).html();
    }

    function leerFilasIniciales() {
        var el = document.getElementById('editar-entrega-filas-json');
        if (!el) {
            return [];
        }
        try {
            var parsed = JSON.parse(el.textContent || '[]');
            return Array.isArray(parsed) ? parsed : [];
        } catch (e) {
            return [];
        }
    }

    function mostrarSaldo($tr) {
        var $campo = $tr.find('.tm-variante-prenda-campo');
        var $s = $tr.find('.linea-saldo');
        var vid = String($campo.find('.prenda_articulo_id').val() || '');
        var saldo = $campo.attr('data-saldo');
        var art = $campo.attr('data-articulo');
        if (!vid) {
            $s.text('');
            return;
        }
        if (art === '0' || art === '') {
            $s.html('<span class="text-danger">Variante sin SKU de stock</span>');
        } else if (saldo !== undefined && saldo !== '') {
            $s.html('Saldo disponible: <strong>' + escapar(saldo) + '</strong>');
        } else {
            $s.text('');
        }
    }

    function nuevaLinea(fila) {
        var $tr = $(tplRenglon.content.querySelector('tr').cloneNode(true));
        $tbody.append($tr);
        if (fila) {
            $tr.find('.prenda_id').val(fila.prenda_id || '');
            $tr.find('.codigoprenda').val(fila.prenda_codigo || '');
            $tr.find('.descripcionprenda').val(fila.prenda_descripcion || '');
            $tr.find('.prenda_articulo_id').val(fila.variante_id || '');
            $tr.find('.codigovarianteprenda').val(fila.variante_sku || '');
            $tr.find('.descripcionvarianteprenda').val(fila.variante_etiqueta || '');
            $tr.find('.linea-cantidad').val(fila.cantidad > 0 ? fila.cantidad : 1);
            if (fila.variante_id) {
                // Refresca saldo desde el maestro (sin pisar etiqueta ya cargada)
                $.get(carpetaBase + '/sueldos/indumentaria/resolvervariante', {
                    prenda_id: fila.prenda_id,
                    codigo: fila.variante_sku || String(fila.variante_id)
                }).done(function (r) {
                    if (r && r.ok && typeof window.aplicarVarianteEnCampo === 'function') {
                        window.aplicarVarianteEnCampo($tr.find('.tm-variante-prenda-campo'), r);
                    }
                    mostrarSaldo($tr);
                });
            }
        }
        return $tr;
    }

    function reindexar() {
        var i = 0;
        $tbody.find('tr').each(function () {
            var $tr = $(this);
            var vid = $tr.find('.prenda_articulo_id').val();
            var cant = parseFloat($tr.find('.linea-cantidad').val());
            $tr.find('.prenda_articulo_id').removeAttr('name');
            $tr.find('.linea-cantidad').removeAttr('name');
            if (vid && cant > 0) {
                $tr.find('.prenda_articulo_id').attr('name', 'lineas[' + i + '][prenda_articulo_id]');
                $tr.find('.linea-cantidad').attr('name', 'lineas[' + i + '][cantidad]');
                i++;
            }
        });
        return i;
    }

    var filas = leerFilasIniciales();
    filas.forEach(function (fila) { nuevaLinea(fila); });
    if (!filas.length) {
        nuevaLinea(null);
    }

    $('#editar-entrega-agregar-linea').on('click', function () {
        nuevaLinea(null).find('.codigoprenda').trigger('focus');
    });

    $tbody.on('click', '.btn-quitar-linea', function () {
        var $tr = $(this).closest('tr');
        if ($tbody.find('tr').length <= 1) {
            $tr.find('.prenda_id').val('');
            $tr.find('.codigoprenda').val('');
            $tr.find('.descripcionprenda').val('');
            if (typeof window.limpiarVariantePrendaCampo === 'function') {
                window.limpiarVariantePrendaCampo($tr.find('.tm-variante-prenda-campo'));
            }
            $tr.find('.linea-cantidad').val(1);
            $tr.find('.linea-saldo').text('');
            return;
        }
        $tr.remove();
    });

    $tbody.on('prenda:seleccionada', '.tm-prenda-campo', function (e, data) {
        var $tr = $(this).closest('tr');
        if (typeof window.limpiarVariantePrendaCampo === 'function') {
            window.limpiarVariantePrendaCampo($tr.find('.tm-variante-prenda-campo'));
        }
        $tr.find('.linea-saldo').text('');
        if (data && data.id) {
            $tr.find('.codigovarianteprenda').trigger('focus');
        }
    });

    $tbody.on('variante:seleccionada', '.tm-variante-prenda-campo', function () {
        mostrarSaldo($(this).closest('tr'));
    });

    $('#form-editar-entrega-prenda').on('submit', function (e) {
        var cantidad = reindexar();
        if (cantidad === 0) {
            e.preventDefault();
            window.alert('Agregue al menos una prenda con variante y cantidad.');
            return;
        }
        $('#editar-entrega-guardar').prop('disabled', true);
    });
})(jQuery);
