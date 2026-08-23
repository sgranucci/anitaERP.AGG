(function ($) {
    'use strict';

    function contexto() {
        var $ctx = $('#ingreso-ticket-contexto');
        var $solapa = $('.ingreso-solapa-vinculo').first();
        return {
            empresa_id: $solapa.data('empresa-id') || $ctx.data('empresa-id') || '',
            proveedor_id: $solapa.data('proveedor-id') || $ctx.data('proveedor-id') || '',
            ordencompra_id: $solapa.data('ordencompra-id') || $ctx.data('ordencompra-id') || '',
            urlForm: $ctx.data('url-form'),
            urlGuardar: $ctx.data('url-guardar'),
            urlActualizar: $ctx.data('url-actualizar'),
            urlGrilla: $ctx.data('url-grilla')
        };
    }

    function csrfToken() {
        return $('meta[name="csrf-token"]').attr('content')
            || $('input[name="_token"]').first().val()
            || '';
    }

    function mostrarErrores(errores) {
        var $box = $('#ingreso-modal-errores');
        if (!$box.length) {
            return;
        }
        var items = [];
        $.each(errores || {}, function (_, msgs) {
            if ($.isArray(msgs)) {
                items = items.concat(msgs);
            } else if (msgs) {
                items.push(msgs);
            }
        });
        if (!items.length) {
            $box.addClass('d-none').empty();
            return;
        }
        $box.removeClass('d-none').html('<ul class="mb-0 pl-3">' + items.map(function (m) {
            return '<li>' + $('<div>').text(m).html() + '</li>';
        }).join('') + '</ul>');
    }

    function bindFormulario() {
        $(document).off('click.ingresoModalPersona', '.js-ingreso-modal-agregar-persona');
        $(document).on('click.ingresoModalPersona', '.js-ingreso-modal-agregar-persona', function () {
            var tpl = document.getElementById('ingreso-modal-template-persona');
            if (!tpl) {
                return;
            }
            $('#ingreso-modal-personas .js-ingreso-modal-agregar-persona').before(tpl.content.cloneNode(true));
        });

        $(document).off('click.ingresoModalArchivo', '.js-ingreso-modal-agrega-archivo');
        $(document).on('click.ingresoModalArchivo', '.js-ingreso-modal-agrega-archivo', function () {
            var tpl = document.getElementById('ingreso-modal-template-archivo');
            if (!tpl) {
                return;
            }
            $('#ingreso-modal-tbody-archivo').append(tpl.content.cloneNode(true));
        });

        $(document).off('click.ingresoModalArchivoDel', '.js-ingreso-modal-eliminar-archivo');
        $(document).on('click.ingresoModalArchivoDel', '.js-ingreso-modal-eliminar-archivo', function () {
            var $filas = $('#ingreso-modal-tbody-archivo tr.item-archivo-ingreso');
            if ($filas.length <= 1) {
                $filas.find('input[type=file]').val('');
                return;
            }
            $(this).closest('tr').remove();
        });

        $(document).off('click.ingresoModalArchivoQuitar', '#form-ingreso-proveedor-modal .ingreso-quitar-archivo');
        $(document).on('click.ingresoModalArchivoQuitar', '#form-ingreso-proveedor-modal .ingreso-quitar-archivo', function () {
            $(this).closest('.ingreso-archivo-item').remove();
        });

        $('#form-ingreso-proveedor-modal').off('submit.ingresoModal').on('submit.ingresoModal', function (e) {
            e.preventDefault();
            enviarFormulario($(this));
        });
    }

    function abrirModal(params, titulo) {
        var ctx = contexto();
        if (!ctx.urlForm) {
            return;
        }
        $('#ingresoProveedorModalTitulo').text(titulo || 'Ticket de ingreso');
        $('#ingresoProveedorModalBody').html('<p class="text-muted mb-0">Cargando…</p>');
        $('#ingresoProveedorModal').modal('show');
        $.get(ctx.urlForm, params)
            .done(function (html) {
                $('#ingresoProveedorModalBody').html(html);
                bindFormulario();
            })
            .fail(function (xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'No se pudo abrir el ticket.';
                $('#ingresoProveedorModalBody').html('<div class="alert alert-danger mb-0">' + $('<div>').text(msg).html() + '</div>');
            });
    }

    function enviarFormulario($form) {
        var ctx = contexto();
        var fd = new FormData($form[0]);
        if (ctx.empresa_id && !fd.get('empresa_id')) {
            fd.set('empresa_id', ctx.empresa_id);
        }
        if (ctx.proveedor_id) {
            fd.set('proveedor_id', ctx.proveedor_id);
        }
        if (ctx.ordencompra_id) {
            fd.set('ordencompra_id', ctx.ordencompra_id);
        }
        fd.set('es_visitante', '0');
        var $btn = $form.find('button[type="submit"]').prop('disabled', true);
        $.ajax({
            url: $form.attr('action'),
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken()
            }
        }).done(function (resp) {
            if (resp && resp.html) {
                var $grilla = $('.ingreso-solapa-vinculo .ingreso-solapa-grilla');
                if ($grilla.length) {
                    $grilla.replaceWith(resp.html);
                }
            }
            if (resp && typeof resp.cantidad !== 'undefined') {
                $('.ingreso-solapa-badge-count').text(resp.cantidad);
            }
            $('#ingresoProveedorModal').modal('hide');
            if (resp && resp.mensaje) {
                if (typeof toastr !== 'undefined') {
                    toastr.success(resp.mensaje);
                } else {
                    alert(resp.mensaje);
                }
            }
        }).fail(function (xhr) {
            if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                mostrarErrores(xhr.responseJSON.errors);
                return;
            }
            var msg = (xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.mensaje)) || 'No se pudo guardar el ticket.';
            mostrarErrores({ general: [msg] });
        }).always(function () {
            $btn.prop('disabled', false);
        });
    }

    $(function () {
        $(document).on('click', '.js-ingreso-ticket-nuevo', function (e) {
            e.preventDefault();
            var ctx = contexto();
            abrirModal({
                empresa_id: ctx.empresa_id,
                proveedor_id: ctx.proveedor_id,
                ordencompra_id: ctx.ordencompra_id
            }, 'Solicitar ticket de ingreso');
        });

        $(document).on('click', '.js-ingreso-ticket-ver', function (e) {
            e.preventDefault();
            var id = $(this).data('id');
            if (!id) {
                return;
            }
            var ctx = contexto();
            abrirModal({
                id: id,
                empresa_id: ctx.empresa_id,
                proveedor_id: ctx.proveedor_id,
                ordencompra_id: ctx.ordencompra_id
            }, 'Ticket de ingreso #' + id);
        });
    });
})(jQuery);
