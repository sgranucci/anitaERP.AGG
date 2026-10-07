$(function () {
    var ccSeq = $('#acl-cc-lista .tm-centrocosto-campo').length;
    var habSeq = $('#acl-hab-body tr').length;

    function csrf() {
        return $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val();
    }

    function postJson(url, data, done) {
        $.ajax({
            url: carpetaBase + url,
            type: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': csrf() },
            data: data,
        }).done(done);
    }

    function mostrarAlcance($fila) {
        var alcance = $fila.find('.acl-hab-alcance').val();
        $fila.find('.acl-hab-rol').toggle(alcance === 'rol');
        $fila.find('.acl-hab-usuario').toggle(alcance === 'usuario');
    }

    $('#acl-hab-body tr').each(function () { mostrarAlcance($(this)); });

    $('#acl-agregar-cc').on('click', function () {
        var html = $('#acl-template-cc').html().replace(/__IDX__/g, String(ccSeq++));
        var $nodo = $(html);
        $nodo.append('<div class="col-lg-12 text-right mb-2"><button type="button" class="btn btn-outline-danger btn-sm acl-quitar-cc">Quitar centro</button></div>');
        $('#acl-cc-lista').append($nodo);
    });

    $(document).on('click', '.acl-quitar-cc', function () {
        $(this).closest('.tm-centrocosto-campo').next('.col-lg-12').remove();
        $(this).closest('.tm-centrocosto-campo').remove();
    });

    $('#acl-agregar-hab').on('click', function () {
        var html = $('#acl-template-hab').html().replace(/__IDX__/g, String(habSeq++));
        $('#acl-hab-body').append(html);
        mostrarAlcance($('#acl-hab-body tr').last());
        if (typeof activa_eventos_consultausuario === 'function') {
            activa_eventos_consultausuario();
        }
    });

    $(document).on('change', '.acl-hab-alcance', function () {
        mostrarAlcance($(this).closest('tr'));
    });

    $(document).on('click', '.acl-quitar-hab', function () {
        $(this).closest('tr').remove();
    });

    var $categoriaDestino = null;

    $(document).on('click', '.consultacatalogocategoria', function () {
        $categoriaDestino = $(this).closest('.tm-catalogo-categoria-campo');
        $('#consultacatalogocategoriaModal').modal('show');
        postJson('/logistica/consulta-categoria-catalogo', { consulta: '' }, function (r) {
            $('#datoscatalogocategoria').html((r && r.data) || '');
        });
    });

    $('#consultacatalogocategoria').on('keyup', function () {
        postJson('/logistica/consulta-categoria-catalogo', { consulta: $(this).val() }, function (r) {
            $('#datoscatalogocategoria').html((r && r.data) || '');
        });
    });

    $(document).on('click', '.eligeconsultacatalogocategoria', function () {
        var $tr = $(this).closest('tr');
        if ($categoriaDestino && $categoriaDestino.length) {
            $categoriaDestino.find('.catalogo-categoria-id').val($tr.find('.idcategoria').text());
            $categoriaDestino.find('.catalogo-categoria-codigo').val($tr.find('.codigocategoria').text());
            $categoriaDestino.find('.catalogo-categoria-nombre').val($tr.find('.nombrecategoria').text());
        }
        $('#consultacatalogocategoriaModal').modal('hide');
    });

    $(document).on('blur', '.catalogo-categoria-codigo', function () {
        var $campo = $(this).closest('.tm-catalogo-categoria-campo');
        var codigo = ($(this).val() || '').trim();
        if (!codigo) {
            $campo.find('.catalogo-categoria-id, .catalogo-categoria-nombre').val('');
            return;
        }
        postJson('/logistica/resolver-categoria-catalogo', { codigo: codigo }, function (data) {
            if (!data) {
                $campo.find('.catalogo-categoria-id, .catalogo-categoria-nombre').val('');
                return;
            }
            $campo.find('.catalogo-categoria-id').val(data.id);
            $campo.find('.catalogo-categoria-codigo').val(data.codigo);
            $campo.find('.catalogo-categoria-nombre').val(data.nombre);
        });
    });

    var $rolFila = null;

    $(document).on('click', '.consultalogisticarol', function () {
        $rolFila = $(this).closest('tr');
        $('#consultalogisticarolModal').modal('show');
        postJson('/logistica/consulta-rol', { consulta: '' }, function (r) {
            $('#datoslogisticarol').html((r && r.data) || '');
        });
    });

    $('#consultalogisticarol').on('keyup', function () {
        postJson('/logistica/consulta-rol', { consulta: $(this).val() }, function (r) {
            $('#datoslogisticarol').html((r && r.data) || '');
        });
    });

    $(document).on('click', '.eligeconsultalogisticarol', function () {
        var $tr = $(this).closest('tr');
        if ($rolFila && $rolFila.length) {
            $rolFila.find('.acl-rol-id, .hab-rol-id').val($tr.find('.idrol').text());
            $rolFila.find('.acl-rol-nombre, .hab-rol-nombre').val($tr.find('.nombrerol').text());
        }
        $('#consultalogisticarolModal').modal('hide');
    });

    $(document).on('blur', '.acl-rol-nombre, .hab-rol-nombre', function () {
        var $fila = $(this).closest('tr');
        var nombre = ($(this).val() || '').trim();
        if (!nombre) {
            $fila.find('.acl-rol-id, .hab-rol-id').val('');
            return;
        }
        postJson('/logistica/resolver-rol', { nombre: nombre }, function (data) {
            if (!data) {
                $fila.find('.acl-rol-id, .hab-rol-id').val('');
                return;
            }
            $fila.find('.acl-rol-id, .hab-rol-id').val(data.id);
            $fila.find('.acl-rol-nombre, .hab-rol-nombre').val(data.nombre);
        });
    });

    if (typeof activa_eventos_consultausuario === 'function') {
        activa_eventos_consultausuario();
    }

    $(document).on('blur', '.hab-ref-codigo', function () {
        var $fila = $(this).closest('tr');
        var codigo = ($(this).val() || '').trim();
        var nivel = $fila.find('.hab-nivel').val();
        if (!codigo) {
            $fila.find('.hab-ref-id, .hab-ref-nombre').val('');
            return;
        }
        var url = nivel === 'tipo' ? '/logistica/resolver-tipo-solicitud' : '/logistica/resolver-categoria-catalogo';
        postJson(url, { codigo: codigo }, function (data) {
            if (!data) {
                $fila.find('.hab-ref-id, .hab-ref-nombre').val('');
                return;
            }
            $fila.find('.hab-ref-id').val(data.id);
            $fila.find('.hab-ref-codigo').val(data.codigo);
            $fila.find('.hab-ref-nombre').val(data.nombre);
        });
    });

    $(document).on('change', '.hab-alcance', function () {
        var $fila = $(this).closest('tr');
        var alcance = $(this).val();
        $fila.find('.hab-box-rol').toggle(alcance === 'rol');
        $fila.find('.hab-box-usuario').toggle(alcance === 'usuario');
    });

    $('.hab-config-fila').each(function () {
        var alcance = $(this).find('.hab-alcance').val();
        $(this).find('.hab-box-rol').toggle(alcance === 'rol');
        $(this).find('.hab-box-usuario').toggle(alcance === 'usuario');
    });

    $(document).on('click', '.hab-quitar', function () {
        $(this).closest('tr').remove();
    });

    $('#hab-agregar').on('click', function () {
        var html = $('#hab-template').html().replace(/__IDX__/g, String(Date.now()));
        $('#hab-body').append(html);
        var $fila = $('#hab-body tr').last();
        $fila.find('.hab-box-usuario').hide();
        if (typeof activa_eventos_consultausuario === 'function') {
            activa_eventos_consultausuario();
        }
    });

    $('#cat-agregar').on('click', function () {
        $('#cat-body').append(
            '<tr><td><input type="hidden" name="cat_id[]" value=""><input class="form-control form-control-sm" name="cat_codigo[]"></td>' +
            '<td><input class="form-control form-control-sm" name="cat_nombre[]"></td>' +
            '<td><input class="form-control form-control-sm" name="cat_icono[]" value="fa-cube"></td>' +
            '<td><input class="form-control form-control-sm" name="cat_orden[]" value="0" style="width:5rem;"></td>' +
            '<td><select class="form-control form-control-sm" name="cat_activo[]"><option value="1">Sí</option><option value="0">No</option></select></td>' +
            '<td><button type="button" class="btn-accion-tabla hab-quitar"><i class="fa fa-times-circle text-danger"></i></button></td></tr>'
        );
    });

    $('#trab-agregar').on('click', function () {
        $('#trab-body').append(
            '<tr><td><input type="hidden" name="trab_id[]" value=""><input class="form-control form-control-sm" name="trab_codigo[]"></td>' +
            '<td><input class="form-control form-control-sm" name="trab_nombre[]"></td>' +
            '<td><input class="form-control form-control-sm" name="trab_icono[]" value="fa-wrench"></td>' +
            '<td><input class="form-control form-control-sm" name="trab_responsable[]"></td>' +
            '<td><input class="form-control form-control-sm" name="trab_email[]"></td>' +
            '<td><select class="form-control form-control-sm" name="trab_piso[]"><option>Baja</option><option selected>Media</option><option>Alta</option></select></td>' +
            '<td><input class="form-control form-control-sm" name="trab_orden[]" value="0" style="width:4rem;"></td>' +
            '<td><select class="form-control form-control-sm" name="trab_activo[]"><option value="1">Sí</option><option value="0">No</option></select></td>' +
            '<td><button type="button" class="btn-accion-tabla hab-quitar"><i class="fa fa-times-circle text-danger"></i></button></td></tr>'
        );
    });

    $('#form-ver-como-usuario').on('keydown', '.usuario_codigo_arbol', function (e) {
        if ((e.which === 13 || e.key === 'Enter') && $.trim($(this).val())) {
            $('#form-ver-como-usuario').data('enviar-al-resolver', 1);
        }
    });

    $(document).ajaxComplete(function (_e, _xhr, settings) {
        var url = settings && settings.url ? String(settings.url) : '';
        if (url.indexOf('resolverusuario') === -1) {
            return;
        }
        var $form = $('#form-ver-como-usuario');
        if (!$form.length || !$form.data('enviar-al-resolver')) {
            return;
        }
        $form.removeData('enviar-al-resolver');
        if ($('#ver_catalogo_usuario_id').val()) {
            $form.trigger('submit');
        }
    });

    $(document).on('click', '.eligeconsultausuario', function () {
        if (ptrusuario_id !== '#ver_catalogo_usuario_id') {
            return;
        }
        setTimeout(function () {
            if ($('#ver_catalogo_usuario_id').val()) {
                $('#form-ver-como-usuario').trigger('submit');
            }
        }, 0);
    });

    $('#ubi-agregar').on('click', function () {
        $('#ubi-body').append(
            '<tr><td><input type="hidden" name="ubi_id[]" value=""><input class="form-control form-control-sm" name="ubi_nombre[]"></td>' +
            '<td><input class="form-control form-control-sm" name="ubi_icono[]" value="fa-map-marker"></td>' +
            '<td><input class="form-control form-control-sm" name="ubi_orden[]" value="0" style="width:4rem;"></td>' +
            '<td><select class="form-control form-control-sm" name="ubi_activo[]"><option value="1">Sí</option><option value="0">No</option></select></td>' +
            '<td><button type="button" class="btn-accion-tabla hab-quitar"><i class="fa fa-times-circle text-danger"></i></button></td></tr>'
        );
    });
});
