$(function () {
    var $tabla = $('#tabla-waitry-conciliacion');
    if ($tabla.length === 0 || $.fn.DataTable.isDataTable($tabla)) {
        return;
    }

    $tabla.DataTable({
        processing: true,
        paging: true,
        lengthChange: true,
        searching: true,
        ordering: true,
        order: [[0, 'desc']],
        info: true,
        autoWidth: false,
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'Todo']],
        language: typeof idioma !== 'undefined' ? idioma : undefined,
        columnDefs: [
            { targets: [3, 6, 10], className: 'text-right' },
        ],
    });
});
