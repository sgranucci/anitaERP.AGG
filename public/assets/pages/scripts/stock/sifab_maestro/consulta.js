(function ($) {
    'use strict';

    var ptrCampoSifab = null;
    var titulosModal = {
        clasematerial: 'Clases de material (SIFAB)',
        lineamaterial: 'Líneas de material (SIFAB)',
        gestioncompra: 'Gestiones de compra (SIFAB)',
        rubro: 'Rubros compra (SIFAB)',
        subrubro: 'Subrubros (SIFAB)',
        grupoproducto: 'Grupos producto (SIFAB)'
    };

    function csrfToken() {
        return $('meta[name="csrf-token"]').attr('content')
            || $('input[name="_token"]').first().val()
            || '';
    }

    function recursoActual() {
        return String($('#consultasifabmaestroModal').attr('data-recurso') || '');
    }

    function rubroCodigoInternoActual() {
        var v = $('#rubro_sifab').val();
        return v != null ? String(v).trim() : '';
    }

    function buscarDatosSifabMaestro(consulta) {
        var recurso = recursoActual();
        if (!recurso) {
            return;
        }
        var payload = {
            consulta: consulta || '',
            _token: csrfToken()
        };
        if (recurso === 'subrubro') {
            payload.rubro_codigo_interno = rubroCodigoInternoActual();
        }

        $.ajax({
            url: carpetaBase + '/stock/sifab-maestro/' + encodeURIComponent(recurso) + '/consulta',
            type: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
            data: payload
        })
            .done(function (respuesta) {
                var html = (respuesta && respuesta.data) ? respuesta.data : '<tr><td colspan="5">Sin resultados</td></tr>';
                $('#datossifabmaestro').html(html);
            })
            .fail(function () {
                $('#datossifabmaestro').html('<tr><td colspan="5">Error al consultar maestro SIFAB</td></tr>');
            });
    }

    function aplicarEnCampo($campo, data) {
        if (!$campo || !$campo.length || !data) {
            return;
        }
        var codigoInterno = data.codigo_interno_sifab != null ? String(data.codigo_interno_sifab) : '';
        var nombre = data.nombre || data.descripcion || '';
        var id = data.id != null ? String(data.id) : '';
        var codigoHuman = data.codigo != null ? String(data.codigo) : '';
        var desc = nombre;
        if (codigoHuman && codigoHuman !== codigoInterno) {
            desc = codigoHuman + (nombre ? ' — ' + nombre : '');
        }

        $campo.find('.sifab-maestro-codigo-interno').val(codigoInterno).attr('data-maestro-id', id);
        $campo.find('.sifab-maestro-codigo').val(codigoInterno);
        $campo.find('.sifab-maestro-nombre').val(desc);

        if ($campo.data('recurso') === 'rubro') {
            var $sub = $('#tm_sifab_subrubro');
            if ($sub.length) {
                limpiarCampo($sub);
            }
        }

        var $link = $campo.find('.btn-link-editar-sifab-maestro');
        if ($link.length) {
            var routeName = $link.data('edit-route');
            if (id && routeName && typeof carpetaBase !== 'undefined') {
                // URL relativa estándar del ABM
                var baseMap = {
                    editar_clasematerial: '/stock/clasematerial/',
                    editar_lineamaterial: '/stock/lineamaterial/',
                    editar_gestioncompra: '/stock/gestioncompra/',
                    editar_rubro: '/stock/rubro/',
                    editar_subrubro: '/stock/subrubro/',
                    editar_grupoproducto: '/stock/grupoproducto/'
                };
                var base = baseMap[routeName] || '';
                if (base) {
                    $link.attr('href', carpetaBase + base + id + '/editar?origen=modal_consulta&vista=consulta');
                    $link.removeClass('d-none');
                }
            } else {
                $link.addClass('d-none').attr('href', '#');
            }
        }
    }

    function limpiarCampo($campo) {
        if (!$campo || !$campo.length) {
            return;
        }
        var esRubro = $campo.data('recurso') === 'rubro';
        $campo.find('.sifab-maestro-codigo-interno').val('').attr('data-maestro-id', '');
        $campo.find('.sifab-maestro-codigo').val('');
        $campo.find('.sifab-maestro-nombre').val('');
        $campo.find('.btn-link-editar-sifab-maestro').addClass('d-none').attr('href', '#');
        if (esRubro) {
            var $sub = $('#tm_sifab_subrubro');
            if ($sub.length) {
                $sub.find('.sifab-maestro-codigo-interno').val('').attr('data-maestro-id', '');
                $sub.find('.sifab-maestro-codigo').val('');
                $sub.find('.sifab-maestro-nombre').val('');
                $sub.find('.btn-link-editar-sifab-maestro').addClass('d-none').attr('href', '#');
            }
        }
    }

    function leerPorCodigo(codigo, $campo, done) {
        var recurso = $campo.data('recurso');
        if (!recurso) {
            return;
        }
        codigo = String(codigo || '').trim();
        if (codigo === '') {
            limpiarCampo($campo);
            if (typeof done === 'function') {
                done(null);
            }
            return;
        }

        var url = carpetaBase + '/stock/sifab-maestro/' + encodeURIComponent(recurso)
            + '/resolver/' + encodeURIComponent(codigo);
        var data = {};
        if (recurso === 'subrubro') {
            data.rubro_codigo_interno = rubroCodigoInternoActual();
        }

        $.ajax({
            url: url,
            type: 'GET',
            dataType: 'json',
            data: data,
            headers: { 'X-CSRF-TOKEN': csrfToken() }
        })
            .done(function (resp) {
                aplicarEnCampo($campo, resp);
                if (typeof done === 'function') {
                    done(resp);
                }
            })
            .fail(function () {
                alert('No se encontró el registro SIFAB');
                limpiarCampo($campo);
                $campo.find('.sifab-maestro-codigo').val(codigo);
                if (typeof done === 'function') {
                    done(null);
                }
            });
    }

    function activa_eventos_consultasifabmaestro() {
        $('.consultasifabmaestro')
            .off('click.consultaSifabMaestro')
            .on('click.consultaSifabMaestro', function () {
                var $campo = $(this).closest('.tm-sifab-maestro-campo');
                ptrCampoSifab = $campo;
                var recurso = String($campo.data('recurso') || '');
                $('#consultasifabmaestroModal')
                    .attr('data-recurso', recurso)
                    .removeAttr('inert')
                    .css('display', '')
                    .modal('show');
                $('#consultasifabmaestroModalLabel').text(titulosModal[recurso] || 'Maestros SIFAB');
            });

        $('#consultasifabmaestroModal')
            .off('shown.bs.modal.consultaSifabMaestro')
            .on('shown.bs.modal.consultaSifabMaestro', function () {
                var $input = $('#consultasifabmaestro');
                setTimeout(function () {
                    $input.trigger('focus').select();
                }, 0);
                buscarDatosSifabMaestro($input.val());
            });

        $('#aceptaconsultasifabmaestroModal')
            .off('click.consultaSifabMaestro')
            .on('click.consultaSifabMaestro', function () {
                $('#consultasifabmaestroModal').modal('hide');
            });

        $(document)
            .off('keyup.consultaSifabMaestro', '#consultasifabmaestro')
            .on('keyup.consultaSifabMaestro', '#consultasifabmaestro', function () {
                buscarDatosSifabMaestro($(this).val());
            });

        $(document)
            .off('click.eligeconsultasifabmaestro')
            .on('click.eligeconsultasifabmaestro', '.eligeconsultasifabmaestro', function () {
                var $tr = $(this).closest('tr');
                var data = {
                    id: $tr.find('.id').text(),
                    codigo_interno_sifab: $tr.find('.codigo-interno').text(),
                    codigo: $tr.find('.codigo').text(),
                    nombre: $tr.find('.nombre').text()
                };
                aplicarEnCampo(ptrCampoSifab, data);
                $('#consultasifabmaestroModal').modal('hide');
            });

        $(document)
            .off('change.leerSifabMaestro', '.sifab-maestro-codigo')
            .on('change.leerSifabMaestro', '.sifab-maestro-codigo', function () {
                var $campo = $(this).closest('.tm-sifab-maestro-campo');
                leerPorCodigo($(this).val(), $campo);
            });

        $(document)
            .off('keydown.leerSifabMaestroEnter', '.sifab-maestro-codigo')
            .on('keydown.leerSifabMaestroEnter', '.sifab-maestro-codigo', function (e) {
                if (e.which !== 13 && e.key !== 'Enter') {
                    return;
                }
                e.preventDefault();
                e.stopImmediatePropagation();
                var $campo = $(this).closest('.tm-sifab-maestro-campo');
                leerPorCodigo($(this).val(), $campo);
            });

        // F1 abre modal del campo enfocado
        $(document)
            .off('keydown.f1SifabMaestro', '.sifab-maestro-codigo')
            .on('keydown.f1SifabMaestro', '.sifab-maestro-codigo', function (e) {
                if (e.which !== 112 && e.key !== 'F1') {
                    return;
                }
                e.preventDefault();
                $(this).closest('.tm-sifab-maestro-campo').find('.consultasifabmaestro').trigger('click');
            });

        // Al cambiar rubro vía código, limpiar subrubro (también en aplicarEnCampo)
        $(document)
            .off('change.limpiarSubrubroSifab', '#rubro_sifab')
            .on('change.limpiarSubrubroSifab', '#rubro_sifab', function () {
                var $sub = $('#tm_sifab_subrubro');
                if ($sub.length) {
                    limpiarCampo($sub);
                }
            });
    }

    window.activa_eventos_consultasifabmaestro = activa_eventos_consultasifabmaestro;

    $(function () {
        if ($('.tm-sifab-maestro-campo').length) {
            activa_eventos_consultasifabmaestro();
        }
    });
})(jQuery);
