var idioma = {
    "sProcessing": "Procesando...",
    "sLengthMenu": "Mostrar _MENU_ registros",
    "sZeroRecords": "No se encontraron resultados",
    "sEmptyTable": "Ningun dato disponible en esta tabla",
    "sInfo": "Mostrando registros del _START_ al _END_ de un total de _TOTAL_ registros",
    "sInfoEmpty": "Mostrando registros del 0 al 0 de un total de 0 registros",
    "sInfoFiltered": "(filtrado de un total de _MAX_ registros)",
    "sInfoPostFix": "",
    "sSearch": "Buscar:",
    "sUrl": "",
    "sInfoThousands": ",",
    "sLoadingRecords": "Cargando...",
    "oPaginate": {
        "sFirst": "Primero",
        "sLast": "Ultimo",
        "sNext": "Siguiente",
        "sPrevious": "Anterior"
    },
    "oAria": {
        "sSortAscending": ": Activar para ordenar la columna de manera ascendente",
        "sSortDescending": ": Activar para ordenar la columna de manera descendente"
    },
    "buttons": {
        "copyTitle": "Informacion copiada",
        "copyKeys": "Use your keyboard or menu to select the copy command",
        "copySuccess": {
            "_": "%d filas copiadas al portapapeles",
            "1": "1 fila copiada al portapapeles"
        },
        "pageLength": {
            "_": "Mostrar %d filas",
            "-1": "Mostrar Todo"
        }
    }
};

$(document).ready(function () {
    var urlDatatable = $("#tabla-data").data("url");

    $("#tabla-data").on("submit", ".form-eliminar", function (event) {
        event.preventDefault();
        var form = $(this);
        swal({
            title: "¿ Está seguro que desea eliminar el registro ?",
            text: "Esta acción no se puede deshacer!",
            icon: "warning",
            buttons: {
                cancel: "Cancelar",
                confirm: "Aceptar"
            }
        }).then(function (value) {
            if (value) {
                $.ajax({
                    url: form.attr("action"),
                    type: "POST",
                    data: form.serialize(),
                    success: function (respuesta) {
                        if (respuesta.mensaje == "ok") {
                            $("#tabla-data").DataTable().ajax.reload(null, false);
                            Biblioteca.notificaciones("El registro fue eliminado correctamente", "anitaERP", "success");
                        } else {
                            Biblioteca.notificaciones("El registro no pudo ser eliminado, hay recursos usandolo", "anitaERP", "error");
                        }
                    }
                });
            }
        });
    });

    $("#tabla-data").DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: urlDatatable,
            type: "GET"
        },
        paging: true,
        lengthChange: true,
        searching: true,
        ordering: true,
        info: true,
        autoWidth: false,
        language: idioma,
        order: [[0, "desc"]],
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        columnDefs: [
            { targets: 5, className: "text-right" },
            { targets: 6, className: "text-right" },
            { targets: 7, orderable: false, searchable: false }
        ],
        dom: 'Bfrt<"col-md-6 inline"i> <"col-md-6 inline"p>',
        buttons: {
            dom: {
                container: {
                    tag: "div",
                    className: "dataTables_filter"
                },
                buttonLiner: {
                    tag: null
                }
            },
            buttons: [
                {
                    extend: "copyHtml5",
                    text: '<i class="fa fa-clipboard" style="color: white"></i><p style="color:white";>Copiar</p>',
                    titleAttr: "Copiar",
                    className: "btn btn-app export barras",
                    exportOptions: { columns: ":visible" }
                },
                {
                    extend: "excelHtml5",
                    text: '<i class="fa fa-file-excel" style="color: white"></i><p style="color:white";>Excel</p>',
                    titleAttr: "Excel",
                    className: "btn btn-app export excel",
                    exportOptions: { columns: ":visible" }
                },
                {
                    extend: "csvHtml5",
                    text: '<i class="fa fa-file-text" style="color: white"></i><p style="color:white";>CSV</p>',
                    titleAttr: "CSV",
                    className: "btn btn-app export csv",
                    exportOptions: { columns: ":visible" }
                },
                {
                    extend: "pdfHtml5",
                    text: '<i class="fa fa-file-pdf" style="color: white;"></i><p style="color:white";>PDF</p>',
                    titleAttr: "PDF",
                    className: "btn btn-app export pdf",
                    exportOptions: { columns: ":visible" }
                },
                {
                    extend: "pageLength",
                    titleAttr: "Registros a mostrar",
                    className: "selectTable"
                }
            ]
        }
    });
});
