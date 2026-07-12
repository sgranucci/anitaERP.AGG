(function () {
    'use strict';

    var $url = $('#articulo-partes-unicas-url');
    if (!$url.length) {
        return;
    }

    var baseUrl = $url.val();
    var puedeEditar = $('#articulo-partes-unicas-puede-editar').val() === '1';
    var paginaActual = 1;

    function escHtml(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
    }

    function estadoFiltro() {
        return ($('#filtro-estado-partes-unicas').val() || 'A').trim();
    }

    function badgeEstado(estado) {
        if (estado === 'B') {
            return '<span class="badge badge-danger">Dado de baja</span>';
        }

        return '<span class="badge badge-success">Activo</span>';
    }

    function cargarPartes(page) {
        paginaActual = page || 1;
        $('#partes-unicas-loading').show();
        $.getJSON(baseUrl, { page: paginaActual, estado: estadoFiltro() })
            .done(function (data) {
                var cols = puedeEditar ? 5 : 4;
                var rows = '';
                (data.data || []).forEach(function (p) {
                    var estado = (p.estado || 'A');
                    rows += '<tr>';
                    rows += '<td><strong>' + escHtml(p.numeroparte) + '</strong></td>';
                    rows += '<td>' + badgeEstado(estado) + '</td>';
                    rows += '<td>' + escHtml((p.created_at || '').substring(0, 16).replace('T', ' ')) + '</td>';
                    rows += '<td>' + escHtml((p.fecha_baja || '').substring(0, 16).replace('T', ' ')) + '</td>';
                    if (puedeEditar) {
                        if (estado === 'B') {
                            rows += '<td class="text-muted small">—</td>';
                        } else {
                            rows += '<td><button type="button" class="btn btn-xs btn-danger btn-eliminar-parte" data-id="' + p.id + '" title="Eliminar"><i class="fa fa-trash"></i></button></td>';
                        }
                    }
                    rows += '</tr>';
                });
                if (!rows) {
                    rows = '<tr><td colspan="' + cols + '" class="text-muted text-center">Sin números de parte</td></tr>';
                }
                $('#tbody-partes-unicas').html(rows);

                var pag = '';
                if (data.last_page > 1) {
                    for (var i = 1; i <= data.last_page; i++) {
                        pag += '<button type="button" class="btn btn-sm ' + (i === data.current_page ? 'btn-primary' : 'btn-outline-secondary') + ' mx-1 btn-pag-parte" data-page="' + i + '">' + i + '</button>';
                    }
                }
                $('#partes-unicas-paginacion').html(pag);
            })
            .always(function () {
                $('#partes-unicas-loading').hide();
            });
    }

    $(document).on('click', '#botonform9', function () {
        cargarPartes(1);
    });

    $(document).on('change', '#filtro-estado-partes-unicas', function () {
        cargarPartes(1);
    });

    $(document).on('click', '.btn-pag-parte', function () {
        cargarPartes(parseInt($(this).data('page'), 10));
    });

    $(document).on('click', '#btn-agregar-parte-unica', function () {
        if (!confirm('¿Asignar el siguiente número de parte global?')) {
            return;
        }
        $.ajax({
            url: baseUrl,
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        }).done(function () {
            cargarPartes(paginaActual);
        }).fail(function (xhr) {
            alert(xhr.responseJSON && xhr.responseJSON.mensaje ? xhr.responseJSON.mensaje : 'Error al crear NPU');
        });
    });

    $(document).on('click', '.btn-eliminar-parte', function () {
        var id = $(this).data('id');
        if (!confirm('¿Eliminar este número de parte? Se borrará también en Anita.')) {
            return;
        }
        $.ajax({
            url: baseUrl.replace(/\/partes-unicas\/?$/, '/parte-unica/' + id),
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        }).done(function () {
            cargarPartes(paginaActual);
        }).fail(function (xhr) {
            alert(xhr.responseJSON && xhr.responseJSON.mensaje ? xhr.responseJSON.mensaje : 'Error al eliminar');
        });
    });
})();
