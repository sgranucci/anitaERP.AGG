$(function () {
    $('#form-ventas-por-concepto').on('submit', function () {
        var $campo = $(this).find('.tm-concepto-venta-campo');
        $('#concepto_codigo_filtro').val($.trim($campo.find('.codigoconceptoventa').val() || ''));
        $('#concepto_nombre_filtro').val($.trim($campo.find('.nombreconceptoventa').val() || ''));
    });
});
