$(function () {
    $('.periodo').datepicker({
        format: 'yyyy-mm',
        minViewMode: 'months',
        autoclose: true,
        changeMonth: true,
        changeYear: true,
        clearBtn: false,
        todayHighlight: true,
    });

    if (window.descargarExcelConciliacionUrl) {
        window.setTimeout(function () {
            window.location.href = window.descargarExcelConciliacionUrl;
        }, 600);
    }
});
