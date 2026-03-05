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

var titulo = $('.card-title').val();

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
                    Biblioteca.notificaciones('El registro no pudo ser eliminado, hay recursos usandolo', 'anitaERP', 'error');
                }
            },
            error: function () {

            }
        });
    }

  var table = $("#tabla-data").DataTable({
	"processing": true,
    "paging": true,
    "lengthChange": true,
    "searching": true,
    "ordering": true,
    "info": true,
    "autoWidth": true,
	"language": idioma,
    "lengthMenu": [[10,5,50, -1],[10,5,50,"Mostrar Todo"]],
    dom: 'Bfrt<"col-md-6 inline"i> <"col-md-6 inline"p>',
    
    buttons: {
          dom: {
            container:{
              tag:'div',
              className:'dataTables_filter'
            },
            buttonLiner: {
              tag: null
            }
          },
          buttons: [
                    {
                        extend:    'copyHtml5',
                        text:      '<i class="fa fa-clipboard" style="color: white"></i><p style="color:white";>Copiar</p>',
                        title: 'Titulo de tabla copiada',
                        titleAttr: 'Copiar',
                        className: 'btn btn-app export barras',
                        exportOptions: {
                            columns: ':visible'
                        }
                    },

                    {
                        extend:    'pdfHtml5',
                        text:      '<i class="fa fa-file-pdf" style="color: white;"></i><p style="color:white";>PDF</p>',
                        title:'Titulo de tabla en pdf',
                        titleAttr: 'PDF',
                        className: 'btn btn-app export pdf',
                        exportOptions: {
                            columns: ':visible'
                        },
                        customize:function(doc) {

                            doc.styles.title = {
                                color: '#4c8aa0',
                                fontSize: '30',
                                alignment: 'center'
                            }
                            doc.styles['td:nth-child(2)'] = { 
                                width: '100px',
                                'max-width': '100px'
                            },
                            doc.styles.tableHeader = {
                                fillColor:'#4c8aa0',
                                color:'white',
                                alignment:'center'
                            },
                            doc.content[1].margin = [ 100, 0, 100, 0 ]

                        }

                    },
                    {
                        extend:    'excelHtml5',
                        text:      '<i class="fa fa-file-excel" style="color: white;"></i><p style="color:white";>Excel</p>',
                        title:'Titulo de tabla en excel',
                        titleAttr: 'Excel',
                        className: 'btn btn-app export excel',
                        exportOptions: {
                            columns: ':visible'
                        },
                    },
                    {
                        extend:    'csvHtml5',
                        text:      '<i class="fa fa-file" style="color: white;"></i><p style="color:white";>CSV</p>',
                        title:'Titulo de tabla en CSV',
                        titleAttr: 'CSV',
                        className: 'btn btn-app export csv',
                        exportOptions: {
                            columns: ':visible'
                        }
                    },
                    {
                        extend:    'print',
                        text:      '<i class="fa fa-print" style="color: white;"></i><p style="color:white";>Imprimir</p>',
                        title:'Titulo de tabla en impresion',
                        titleAttr: 'Imprimir',
                        className: 'btn btn-app export imprimir',
                        exportOptions: {
                            columns: ':visible'
                        }
                    },
                    {
                        extend:    'pageLength',
                        titleAttr: 'Registros a mostrar',
                        className: 'selectTable'
                    }
                ]
          
          
        }
    });

  var table2 = $("#tabla-data-2").DataTable({
    
	"processing": true,
    "paging": true,
    "lengthChange": true,
    "searching": true,
    "ordering": true,
    "order": [ 0, 'desc' ],
    "info": true,
    "autoWidth": true,
	"language": idioma,
    "lengthMenu": [[10,5,50, -1],[10,5,50,"Mostrar Todo"]],
    dom: 'Bfrt<"col-md-6 inline"i> <"col-md-6 inline"p>',
    
    buttons: {
          dom: {
            container:{
              tag:'div',
              className:'dataTables_filter'
            },
            buttonLiner: {
              tag: null
            }
          },
          buttons: [
                    {
                        extend:    'copyHtml5',
                        text:      '<i class="fa fa-clipboard" style="color: white"></i><p style="color:white";>Copiar</p>',
                        title: 'Titulo de tabla copiada',
                        titleAttr: 'Copiar',
                        className: 'btn btn-app export barras',
                        exportOptions: {
                            columns: ':visible'
                        }
                    },

                    {
                        extend:    'pdfHtml5',
                        text:      '<i class="fa fa-file-pdf" style="color: white;"></i><p style="color:white";>PDF</p>',
                        title:'Titulo de tabla en pdf',
                        titleAttr: 'PDF',
                        className: 'btn btn-app export pdf',
                        exportOptions: {
                            columns: ':visible'
                        },
                        customize:function(doc) {

                            doc.styles.title = {
                                color: '#4c8aa0',
                                fontSize: '30',
                                alignment: 'center'
                            }
                            doc.styles['td:nth-child(2)'] = { 
                                width: '100px',
                                'max-width': '100px'
                            },
                            doc.styles.tableHeader = {
                                fillColor:'#4c8aa0',
                                color:'white',
                                alignment:'center'
                            },
                            doc.content[1].margin = [ 100, 0, 100, 0 ]

                        }

                    },
                    {
                        extend:    'excelHtml5',
                        text:      '<i class="fa fa-file-excel" style="color: white;"></i><p style="color:white";>Excel</p>',
                        title:'Titulo de tabla en excel',
                        titleAttr: 'Excel',
                        className: 'btn btn-app export excel',
                        exportOptions: {
                            columns: ':visible'
                        },
                    },
                    {
                        extend:    'csvHtml5',
                        text:      '<i class="fa fa-file" style="color: white;"></i><p style="color:white";>CSV</p>',
                        title:'Titulo de tabla en CSV',
                        titleAttr: 'CSV',
                        className: 'btn btn-app export csv',
                        exportOptions: {
                            columns: ':visible'
                        }
                    },
                    {
                        extend:    'print',
                        text:      '<i class="fa fa-print" style="color: white;"></i><p style="color:white";>Imprimir</p>',
                        title:'Titulo de tabla en impresion',
                        titleAttr: 'Imprimir',
                        className: 'btn btn-app export imprimir',
                        exportOptions: {
                            columns: ':visible'
                        }
                    },
                    {
                        extend:    'pageLength',
                        titleAttr: 'Registros a mostrar',
                        className: 'selectTable'
                    }
                ]
          
          
        }
    });
});

var table3 = $("#tabla-data-3").DataTable({
    
	"processing": true,
    "paging": true,
    "lengthChange": true,
    "searching": true,
    "ordering": true,
    "order": [ 0, 'desc' ],
    "info": true,
    "autoWidth": true,
	"language": idioma,
    "lengthMenu": [[-1,10,5,50],["Mostrar todo",10,5,50]],
    dom: 'Bfrt<"col-md-6 inline"i> <"col-md-6 inline"p>',
    
    buttons: {
          dom: {
            container:{
              tag:'div',
              className:'dataTables_filter'
            },
            buttonLiner: {
              tag: null
            }
          },
          buttons: [
                    {
                        extend:    'copyHtml5',
                        text:      '<i class="fa fa-clipboard" style="color: white"></i><p style="color:white";>Copiar</p>',
                        title: 'Titulo de tabla copiada',
                        titleAttr: 'Copiar',
                        className: 'btn btn-app export barras',
                        exportOptions: {
                            columns: ':visible'
                        }
                    },

                    {
                        extend:    'pdfHtml5',
                        text:      '<i class="fa fa-file-pdf" style="color: white;"></i><p style="color:white";>PDF</p>',
                        title:'Titulo de tabla en pdf',
                        titleAttr: 'PDF',
                        className: 'btn btn-app export pdf',
                        exportOptions: {
                            columns: ':visible'
                        },
                        customize:function(doc) {

                            doc.styles.title = {
                                color: '#4c8aa0',
                                fontSize: '30',
                                alignment: 'center'
                            }
                            doc.styles['td:nth-child(2)'] = { 
                                width: '100px',
                                'max-width': '100px'
                            },
                            doc.styles.tableHeader = {
                                fillColor:'#4c8aa0',
                                color:'white',
                                alignment:'center'
                            },
                            doc.content[1].margin = [ 100, 0, 100, 0 ]

                        }

                    },
                    {
                        extend:    'excelHtml5',
                        text:      '<i class="fa fa-file-excel" style="color: white;"></i><p style="color:white";>Excel</p>',
                        title:'Titulo de tabla en excel',
                        titleAttr: 'Excel',
                        className: 'btn btn-app export excel',
                        exportOptions: {
                            columns: ':visible'
                        },
                    },
                    {
                        extend:    'csvHtml5',
                        text:      '<i class="fa fa-file" style="color: white;"></i><p style="color:white";>CSV</p>',
                        title:'Titulo de tabla en CSV',
                        titleAttr: 'CSV',
                        className: 'btn btn-app export csv',
                        exportOptions: {
                            columns: ':visible'
                        }
                    },
                    {
                        extend:    'print',
                        text:      '<i class="fa fa-print" style="color: white;"></i><p style="color:white";>Imprimir</p>',
                        title:'Titulo de tabla en impresion',
                        titleAttr: 'Imprimir',
                        className: 'btn btn-app export imprimir',
                        exportOptions: {
                            columns: ':visible'
                        }
                    },
                    {
                        extend:    'pageLength',
                        titleAttr: 'Registros a mostrar',
                        className: 'selectTable'
                    }
                ]
          
          
        }
    });



function downloadPDFWithBrowserPrint() {
    window.print();
}
document.querySelector('#browserPrint').addEventListener('click', downloadPDFWithBrowserPrint);

let download_xls = document.querySelector("#download_xls")
download_xls.addEventListener("click", ()=>{                     
  ExcellentExport.excel(download_xls, 'tabla-paginada')
})

let download_csv = document.querySelector("#download_csv")
download_csv.addEventListener("click", ()=>{                     
  ExcellentExport.csv(download_csv, 'tabla-paginada');
})

let download_xlsx = document.querySelector("#download_xlsx")
download_xlsx.addEventListener("click", ()=>{                     
  ExcellentExport.convert({ anchor: download_xlsx, filename: 'filename', format: 'xlsx'},[{name: 'Sheet Name Here 1', from: {table: 'tabla-paginada'}}])
})

//$(function () {
		//$("#tabla-data").DataTable({
		//dom: 'Bfrtip',
		//language: {
				//"url": "//cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"
		//},
      	//"responsive": true, 
		//"lengthChange": false, 
		//"autoWidth": false,
		//"buttons": [
            //'copyHtml5',
            //'excelHtml5',
            //'csvHtml5',
            //'pdfHtml5'
        //],
		//"buttons": {
        	//"pageLength": {
            //_: "Mostrar %d Registros"
        	//}
		//},
    //}).buttons().container().appendTo('#example1_wrapper .col-md-6:eq(0)');
  //});
//});

