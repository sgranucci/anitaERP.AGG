$(document).ready(function () {
    function refrescarBotonConsultaFormula() {
        var $btn = $('#btn-consulta-formula-articulo');
        if (!$btn.length) {
            return;
        }
        var v = parseInt($('#formula').val(), 10) || 0;
        $btn.prop('disabled', v <= 0);
    }

    refrescarBotonConsultaFormula();
    $(document).on('input change', '#formula', refrescarBotonConsultaFormula);

    $(document).on('click', '#btn-consulta-formula-articulo', function (e) {
        e.preventDefault();
        var fid = parseInt($('#formula').val(), 10) || 0;
        if (fid <= 0) {
            return;
        }
        var url = carpetaBase + '/stock/formula-articulo/' + fid + '/modal';
        $('#modalVerFormulaArticuloBody').html('<p class="text-muted">Cargando...</p>');
        var crudUrl = carpetaBase + '/stock/formula-articulo/' + fid + '/editar';
        $('#modalVerFormulaArticuloIrCrud').attr('href', crudUrl).removeClass('d-none');
        $('#modalVerFormulaArticulo').modal('show');
        $.get(url, function (html) {
            $('#modalVerFormulaArticuloBody').html(html);
        }).fail(function () {
            $('#modalVerFormulaArticuloBody').html('<p class="text-danger">No se pudo cargar la fórmula.</p>');
        });
    });
});
