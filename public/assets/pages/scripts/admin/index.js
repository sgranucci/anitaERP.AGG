var idioma=

            {
                "sProcessing":     "Procesando...",
                "sLengthMenu":     "Mostrar _MENU_ registros",
                "sZeroRecords":    "No se encontraron resultados",
                "sEmptyTable":     "Ningun dato disponible en esta tabla",
                "sInfo":           "Mostrando registros del _START_ al _END_ de un total de _TOTAL_ registros",
                "sInfoEmpty":      "Mostrando registros del 0 al 0 de un total de 0 registros",
                "sInfoFiltered":   "(filtrado de un total de _MAX_ registros)",
                "sInfoPostFix":    "",
                "sSearch":         "Buscar:",
                "sUrl":            "",
                "sInfoThousands":  ",",
                "sLoadingRecords": "Cargando...",
                "oPaginate": {
                    "sFirst":    "Primero",
                    "sLast":     "..ltimo",
                    "sNext":     "Siguiente",
                    "sPrevious": "Anterior"
                },
                "oAria": {
                    "sSortAscending":  ": Activar para ordenar la columna de manera ascendente",
                    "sSortDescending": ": Activar para ordenar la columna de manera descendente"
                },
                "buttons": {
                    "copyTitle": 'Informacion copiada',
                    "copyKeys": 'Use your keyboard or menu to select the copy command',
                    "copySuccess": {
                        "_": '%d filas copiadas al portapapeles',
                        "1": '1 fila copiada al portapapeles'
                    },

                    "pageLength": {
                    "_": "Mostrar %d filas",
                    "-1": "Mostrar Todo"
                    }
                }
            };

function resolverTituloExportListado() {
    if (typeof window.tituloExportListado === 'string' && window.tituloExportListado.trim() !== '') {
        return window.tituloExportListado.trim();
    }
    var textoCard = $('.card-title').first().text();
    if (textoCard && textoCard.trim() !== '') {
        return textoCard.trim();
    }
    return 'Listado';
}

function resolverNombreArchivoExportListado(titulo) {
    if (typeof window.nombreArchivoExportListado === 'string' && window.nombreArchivoExportListado.trim() !== '') {
        return window.nombreArchivoExportListado.trim();
    }
    return titulo
        .replace(/[/\\:*?"<>|]/g, '')
        .replace(/\s+/g, '_')
        .substring(0, 100) || 'listado';
}

function tituloExportAlMomento() {
    return resolverTituloExportListado();
}

function nombreArchivoExportAlMomento() {
    return resolverNombreArchivoExportListado(tituloExportAlMomento());
}

function configuracionBotonesExportDataTable() {
    return {
        dom: {
            container: {
                tag: 'div',
                className: 'dataTables_filter'
            },
            buttonLiner: {
                tag: null
            }
        },
        buttons: [
            {
                extend: 'copyHtml5',
                text: '<i class="fa fa-clipboard" style="color: white"></i><p style="color:white";>Copiar</p>',
                title: tituloExportAlMomento,
                titleAttr: 'Copiar',
                className: 'btn btn-app export barras',
                exportOptions: {
                    columns: ':visible'
                }
            },
            {
                extend: 'pdfHtml5',
                text: '<i class="fa fa-file-pdf" style="color: white;"></i><p style="color:white";>PDF</p>',
                title: tituloExportAlMomento,
                filename: nombreArchivoExportAlMomento,
                titleAttr: 'PDF',
                className: 'btn btn-app export pdf',
                exportOptions: {
                    columns: ':visible'
                },
                customize: function (doc) {
                    var titulo = tituloExportAlMomento();
                    if (doc.content && doc.content.length && doc.content[0].text !== undefined) {
                        doc.content[0].text = titulo;
                    }
                    doc.styles.title = {
                        color: '#4c8aa0',
                        fontSize: '30',
                        alignment: 'center'
                    };
                    doc.styles['td:nth-child(2)'] = {
                        width: '100px',
                        'max-width': '100px'
                    };
                    doc.styles.tableHeader = {
                        fillColor: '#4c8aa0',
                        color: 'white',
                        alignment: 'center'
                    };
                    if (doc.content[1]) {
                        doc.content[1].margin = [100, 0, 100, 0];
                    }
                }
            },
            {
                extend: 'excelHtml5',
                text: '<i class="fa fa-file-excel" style="color: white;"></i><p style="color:white";>Excel</p>',
                title: tituloExportAlMomento,
                filename: nombreArchivoExportAlMomento,
                titleAttr: 'Excel',
                className: 'btn btn-app export excel',
                exportOptions: {
                    columns: ':visible'
                }
            },
            {
                extend: 'csvHtml5',
                text: '<i class="fa fa-file" style="color: white;"></i><p style="color:white";>CSV</p>',
                title: tituloExportAlMomento,
                filename: nombreArchivoExportAlMomento,
                titleAttr: 'CSV',
                className: 'btn btn-app export csv',
                exportOptions: {
                    columns: ':visible'
                }
            },
            {
                extend: 'print',
                text: '<i class="fa fa-print" style="color: white;"></i><p style="color:white";>Imprimir</p>',
                title: tituloExportAlMomento,
                titleAttr: 'Imprimir',
                className: 'btn btn-app export imprimir',
                exportOptions: {
                    columns: ':visible'
                }
            },
            {
                extend: 'pageLength',
                titleAttr: 'Registros a mostrar',
                className: 'selectTable'
            }
        ]
    };
}

$(document).ready(function () {
    $("#tabla-data").on('submit', '.form-eliminar', function () {
        event.preventDefault();
        const form = $(this);
        swal({
            title: '¿ Está seguro que desea eliminar el registro ?',
            text: "Esta acción no se puede deshacer!",
            icon: 'warning',
            buttons: {
                cancel: "Cancelar",
                confirm: "Aceptar"
            },
        }).then((value) => {
            if (value) {
                ajaxRequest(form);
            }
        });
    });

    $("#tabla-data-2").on('submit', '.form-eliminar', function () {
        event.preventDefault();
        const form = $(this);
        swal({
            title: '¿ Está seguro que desea eliminar el registro ?',
            text: "Esta acción no se puede deshacer!",
            icon: 'warning',
            buttons: {
                cancel: "Cancelar",
                confirm: "Aceptar"
            },
        }).then((value) => {
            if (value) {
                ajaxRequest(form);
            }
        });
    });

    $("#tabla-data-3").on('submit', '.form-eliminar', function () {
        event.preventDefault();
        const form = $(this);
        swal({
            title: '¿ Está seguro que desea eliminar el registro ?',
            text: "Esta acción no se puede deshacer!",
            icon: 'warning',
            buttons: {
                cancel: "Cancelar",
                confirm: "Aceptar"
            },
        }).then((value) => {
            if (value) {
                ajaxRequest(form);
            }
        });
    });

    $("#tabla-paginada").on('submit', '.form-eliminar', function () {
        event.preventDefault();
        const form = $(this);
        swal({
            title: '¿ Está seguro que desea eliminar el registro ?',
            text: "Esta acción no se puede deshacer!",
            icon: 'warning',
            buttons: {
                cancel: "Cancelar",
                confirm: "Aceptar"
            },
        }).then((value) => {
            if (value) {
                ajaxRequest(form);
            }
        });
    });

    function mensajeErrorEliminacion(respuesta, xhr) {
        if (respuesta && respuesta.error) {
            return respuesta.error;
        }
        if (xhr && xhr.responseJSON) {
            if (xhr.responseJSON.error) {
                return xhr.responseJSON.error;
            }
            if (xhr.responseJSON.message) {
                return xhr.responseJSON.message;
            }
        }
        return 'El registro no pudo ser eliminado porque está en uso o ocurrió un error interno.';
    }

    function ajaxRequest(form) {
        $.ajax({
            url: form.attr('action'),
            type: 'POST',
            data: form.serialize(),
            success: function (respuesta) {
                if (respuesta.mensaje == "ok") {
                    form.parents('tr').remove();
                    Biblioteca.notificaciones('El registro fue eliminado correctamente', 'anitaERP', 'success');
                } else {
                    Biblioteca.notificaciones(mensajeErrorEliminacion(respuesta), 'anitaERP', 'error');
                }
            },
            error: function (xhr) {
                Biblioteca.notificaciones(mensajeErrorEliminacion(null, xhr), 'anitaERP', 'error');
            }
        });
    }

    if ($("#tabla-data").length && !$.fn.DataTable.isDataTable("#tabla-data")) {
        $("#tabla-data").DataTable({
            "processing": true,
            "paging": true,
            "lengthChange": true,
            "searching": true,
            "ordering": true,
            "info": true,
            "autoWidth": true,
            "language": idioma,
            "lengthMenu": [[10, 5, 50, -1], [10, 5, 50, "Mostrar Todo"]],
            dom: 'Bfrt<"col-md-6 inline"i> <"col-md-6 inline"p>',
            buttons: configuracionBotonesExportDataTable()
        });
    }

    if ($("#tabla-data-2").length && !$.fn.DataTable.isDataTable("#tabla-data-2")) {
        $("#tabla-data-2").DataTable({
            "processing": true,
            "paging": true,
            "lengthChange": true,
            "searching": true,
            "ordering": true,
            "order": [0, 'desc'],
            "info": true,
            "autoWidth": true,
            "language": idioma,
            "lengthMenu": [[10, 5, 50, -1], [10, 5, 50, "Mostrar Todo"]],
            dom: 'Bfrt<"col-md-6 inline"i> <"col-md-6 inline"p>',
            buttons: configuracionBotonesExportDataTable()
        });
    }
});

if ($("#tabla-data-3").length && !$.fn.DataTable.isDataTable("#tabla-data-3")) {
    $("#tabla-data-3").DataTable({
        "processing": true,
        "paging": true,
        "lengthChange": true,
        "searching": true,
        "ordering": true,
        "order": [0, 'desc'],
        "info": true,
        "autoWidth": true,
        "language": idioma,
        "lengthMenu": [[-1, 10, 5, 50], ["Mostrar todo", 10, 5, 50]],
        dom: 'Bfrt<"col-md-6 inline"i> <"col-md-6 inline"p>',
        buttons: configuracionBotonesExportDataTable()
    });
}

if ($("#tabla-data-sin-ordenar").length && !$.fn.DataTable.isDataTable("#tabla-data-sin-ordenar")) {
    $("#tabla-data-sin-ordenar").DataTable({
        "processing": true,
        "paging": true,
        "lengthChange": true,
        "searching": true,
        "ordering": false,
        "info": true,
        "autoWidth": true,
        "language": idioma,
        "lengthMenu": [[-1, 10, 5, 50], ["Mostrar todo", 10, 5, 50]],
        dom: 'Bfrt<"col-md-6 inline"i> <"col-md-6 inline"p>',
        buttons: configuracionBotonesExportDataTable()
    });
}

function downloadPDFWithBrowserPrint() {
    window.print();
}

var browserPrint = document.querySelector('#browserPrint');
if (browserPrint) {
    browserPrint.addEventListener('click', downloadPDFWithBrowserPrint);
}

var nombreArchivoExcellentExport = resolverNombreArchivoExportListado(resolverTituloExportListado());

var download_xls = document.querySelector("#download_xls");
if (download_xls) {
    download_xls.addEventListener("click", function () {
        ExcellentExport.excel(download_xls, 'tabla-paginada');
    });
}

var download_csv = document.querySelector("#download_csv");
if (download_csv) {
    download_csv.addEventListener("click", function () {
        ExcellentExport.csv(download_csv, 'tabla-paginada');
    });
}

var download_xlsx = document.querySelector("#download_xlsx");
if (download_xlsx) {
    download_xlsx.addEventListener("click", function () {
        ExcellentExport.convert({
            anchor: download_xlsx,
            filename: nombreArchivoExcellentExport,
            format: 'xlsx'
        }, [{
            name: resolverTituloExportListado(),
            from: { table: 'tabla-paginada' }
        }]);
    });
}
