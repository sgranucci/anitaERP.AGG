(function ($) {
    'use strict';

    function valoresDatosActuales() {
        var map = {};
        $('#tbody-cv-dato-table tr.item-cv-dato').each(function () {
            var clave = $.trim($(this).find('.cv-dato-clave').val() || '').toLowerCase();
            var valor = $.trim($(this).find('input[name="dato_valores[]"]').val() || '');
            if (clave) {
                map[clave] = valor;
            }
        });
        return map;
    }

    function armarFilasDatos(tags, valoresPrevios) {
        var $tbody = $('#tbody-cv-dato-table');
        var $tpl = $('#cv-template-renglon-dato');
        if (!$tbody.length || !$tpl.length) {
            return;
        }
        valoresPrevios = valoresPrevios || {};
        $tbody.empty();

        if (!Array.isArray(tags) || !tags.length) {
            return;
        }

        tags.forEach(function (tag) {
            var origen = String(tag.origen || 'pedible').toLowerCase();
            if (origen === 'sistema') {
                return;
            }
            var clave = String(tag.clave || '').toLowerCase();
            if (!clave) {
                return;
            }
            var $row = $($tpl.html());
            $row.find('.cv-dato-clave').val(clave);
            $row.find('.cv-dato-etiqueta').text(tag.etiqueta || clave);
            var placeholder = (clave === 'periodo')
                ? 'Opcional (se calcula al facturar)'
                : '';
            $row.find('input[name="dato_valores[]"]')
                .attr('placeholder', placeholder)
                .val(valoresPrevios[clave] || '');
            $tbody.append($row);
        });
    }

    function cargarTagsDesdeConcepto(conceptoId, codigo) {
        var prev = valoresDatosActuales();
        var url;
        if (conceptoId) {
            url = carpetaBase + '/ventas/concepto-venta/por-codigo/' + encodeURIComponent(codigo || '');
        } else if (codigo) {
            url = carpetaBase + '/ventas/concepto-venta/por-codigo/' + encodeURIComponent(codigo);
        } else {
            armarFilasDatos([], prev);
            return;
        }

        $.ajax({
            url: url,
            type: 'GET',
            dataType: 'json'
        }).done(function (resp) {
            if (!resp || !resp.ok) {
                return;
            }
            armarFilasDatos(resp.tags || [], prev);
            if (!$('#precio').val() && resp.precio) {
                $('#precio').val(resp.precio);
            }
        });
    }

    $(function () {
        if (!$('#form-general').length || !$('#cv-dato-table').length) {
            return;
        }

        $(document).on('click', '.eliminar_cv_dato', function () {
            $(this).closest('tr').remove();
        });

        $(document).on('conceptoVentaElegido', function (e, data) {
            if (!data) {
                return;
            }
            armarFilasDatos(data.tags || [], valoresDatosActuales());
            if (!$('#precio').val() && data.precio) {
                $('#precio').val(data.precio);
            }
        });

        // Al elegir vía modal concepto (patrón consulta.js)
        $(document).on('click', '.eligeconsultaconceptoventa', function () {
            var $row = $(this).closest('tr');
            var tags = [];
            try {
                tags = JSON.parse($row.attr('data-concepto-tags') || '[]') || [];
            } catch (err) {
                tags = [];
            }
            setTimeout(function () {
                armarFilasDatos(tags, valoresDatosActuales());
            }, 50);
        });

        // Al resolver por código (blur/enter) del campo concepto
        $(document).on('change blur', '.tm-concepto-venta-campo .codigoconceptoventa', function () {
            var $ctx = $(this).closest('.tm-concepto-venta-campo');
            var id = $.trim($ctx.find('.concepto_venta_id').val() || '');
            var codigo = $.trim($(this).val() || '');
            if (codigo) {
                setTimeout(function () {
                    cargarTagsDesdeConcepto(id, codigo);
                }, 200);
            }
        });
    });
})(jQuery);
