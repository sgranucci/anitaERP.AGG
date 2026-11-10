(function ($) {
    'use strict';

    var CFG = window.GastroCrownSalaCuentacaja || {};
    var $campoActivo = null;

    function carpetaBase() {
        if (typeof window.resolverCarpetaBaseApp === 'function') {
            return window.resolverCarpetaBaseApp();
        }
        if (window.carpetaBase) {
            return String(window.carpetaBase).replace(/\/$/, '');
        }
        return '';
    }

    function csrf() {
        return $('meta[name="csrf-token"]').attr('content') || '';
    }

    function empresaDelPanel($panel) {
        var v = parseInt(String($panel.data('empresa-id') || '0'), 10);
        return v > 0 ? String(v) : '';
    }

    function panelActivo() {
        return $('.gc-sala-panel.is-active').first();
    }

    function actualizarContador(salaId) {
        var n = $('.gc-sala-tbody[data-sala-id="' + salaId + '"] tr.gc-sala-linea').filter(function () {
            return parseInt($(this).find('.cuentacaja_id').val() || '0', 10) > 0;
        }).length;
        $('.gc-sala-count[data-sala-id="' + salaId + '"]').text(n);
    }

    function seleccionarSala(salaId) {
        $('#gc-sala-master .list-group-item').removeClass('active');
        $('#gc-sala-master .list-group-item[data-sala-id="' + salaId + '"]').addClass('active');
        $('.gc-sala-panel').removeClass('is-active');
        $('.gc-sala-panel[data-sala-id="' + salaId + '"]').addClass('is-active');
    }

    function reindexarFilas($tbody) {
        var salaId = String($tbody.data('sala-id'));
        $tbody.find('tr.gc-sala-linea').each(function (idx) {
            var $tr = $(this);
            var prefix = 'sala_' + salaId + '_' + idx;
            $tr.attr('data-prefix', prefix);
            $tr.find('.cuentacaja_id').attr({
                name: 'salas[' + salaId + '][lineas][' + idx + '][cuentacaja_id]',
                id: 'cuentacaja_id_' + prefix
            });
            $tr.find('.codigocuentacaja').attr('id', 'cuentacaja_id_' + prefix + '_codigo');
            $tr.find('.descripcioncuentacaja').attr('id', 'cuentacaja_id_' + prefix + '_nombre');
            $tr.find('.gc-default-f5').attr('name', 'salas[' + salaId + '][lineas][' + idx + '][es_default_f5]');
            $tr.find('.gc-orden').attr('name', 'salas[' + salaId + '][lineas][' + idx + '][orden]');
            if (!$tr.find('.gc-orden').val()) {
                $tr.find('.gc-orden').val(idx + 1);
            }
            var $wrap = $tr.find('.tm-cuentacaja-campo');
            $wrap.attr('id', 'tm_cuentacaja_' + prefix);
        });
        actualizarContador(salaId);
    }

    function htmlFilasConsulta(respuesta) {
        if (respuesta && typeof respuesta === 'object') {
            return respuesta.data || '';
        }
        return '';
    }

    function buscarModal(consulta) {
        var $panel = panelActivo();
        var empresa_id = empresaDelPanel($panel);
        $('#datoscuentacaja').html('<tr><td colspan="10" class="text-muted">Buscando…</td></tr>');
        $.ajax({
            url: (CFG.urls && CFG.urls.consulta) || (carpetaBase() + '/caja/cuentacaja/consultacuentacaja'),
            type: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': csrf() },
            data: {
                consulta: consulta || '',
                empresa_id: empresa_id
            }
        })
            .done(function (resp) {
                var html = htmlFilasConsulta(resp);
                $('#datoscuentacaja').html(html || '<tr><td colspan="10" class="text-muted">Sin resultados</td></tr>');
            })
            .fail(function () {
                $('#datoscuentacaja').html('<tr><td colspan="10" class="text-danger">Error al consultar</td></tr>');
            });
    }

    function abrirModal($campo) {
        $campoActivo = $campo && $campo.length ? $campo : null;
        var $panel = panelActivo();
        $('#consultaempresacaja_id').val(empresaDelPanel($panel));
        $('#consultacuentacaja').val('');
        $('#datoscuentacaja').html('');
        $('#consultacuentacajaModal').one('shown.bs.modal.gcSalaCc', function () {
            $(this).find('#consultacuentacaja').trigger('focus');
            buscarModal('');
        });
        $('#consultacuentacajaModal').modal('show');
    }

    function asignarEnCampo($campo, data) {
        if (!$campo || !$campo.length || !data || !data.id) {
            return;
        }
        $campo.find('.cuentacaja_id').val(data.id);
        $campo.find('.codigocuentacaja').val(data.codigo || '');
        $campo.find('.descripcioncuentacaja').val(data.nombre || '');
        var $tr = $campo.closest('tr');
        $tr.find('.gc-moneda-label').text(data.moneda_abreviatura || data.moneda || '—');
        var $link = $campo.find('.btn-link-editar-cuentacaja');
        if ($link.length) {
            var base = (CFG.urls && CFG.urls.editarBase) || (carpetaBase() + '/caja/cuentacaja');
            $link.attr('href', base + '/' + encodeURIComponent(String(data.id)) + '/editar?origen=modal_consulta&vista=consulta');
            $link.removeClass('d-none');
        }
        var salaId = $campo.closest('.gc-sala-tbody').data('sala-id');
        if (salaId) {
            actualizarContador(salaId);
        }
    }

    function leerPorCodigo($campo) {
        var codigo = $.trim($campo.find('.codigocuentacaja').val() || '');
        if (!codigo) {
            $campo.find('.cuentacaja_id').val('');
            $campo.find('.descripcioncuentacaja').val('');
            $campo.closest('tr').find('.gc-moneda-label').text('—');
            return;
        }
        var $panel = $campo.closest('.gc-sala-panel');
        var empresa_id = empresaDelPanel($panel);
        var base = (CFG.urls && CFG.urls.leerPorCodigo) || (carpetaBase() + '/caja/cuentacaja/leercuentacajaporcodigo');
        $.ajax({
            url: base + '/' + encodeURIComponent(codigo),
            type: 'GET',
            dataType: 'json',
            data: { empresa_id: empresa_id }
        })
            .done(function (data) {
                if (data && data.id > 0) {
                    asignarEnCampo($campo, data);
                } else {
                    $campo.find('.cuentacaja_id').val('');
                    $campo.find('.descripcioncuentacaja').val('');
                    $campo.closest('tr').find('.gc-moneda-label').text('—');
                }
            })
            .fail(function () {
                $campo.find('.cuentacaja_id').val('');
                $campo.find('.descripcioncuentacaja').val('');
            });
    }

    function agregarLinea(salaId) {
        var $tbody = $('.gc-sala-tbody[data-sala-id="' + salaId + '"]');
        $tbody.find('tr.gc-sala-vacia').remove();
        var tpl = document.getElementById('gc-template-fila-cuenta');
        if (!tpl || !tpl.content) {
            return;
        }
        var html = tpl.innerHTML
            .replace(/__SALA__/g, String(salaId))
            .replace(/__IDX__/g, String($tbody.find('tr.gc-sala-linea').length));
        var $tr = $(html);
        $tbody.append($tr);
        reindexarFilas($tbody);
        $tr.find('.codigocuentacaja').trigger('focus');
    }

    $(function () {
        $('#gc-sala-master').on('click', '.list-group-item', function () {
            seleccionarSala($(this).data('sala-id'));
        });

        $(document).on('click', '.gc-agregar-linea', function () {
            agregarLinea($(this).data('sala-id'));
        });

        $(document).on('click', '.gc-quitar-linea', function () {
            var $tbody = $(this).closest('tbody');
            var salaId = $tbody.data('sala-id');
            $(this).closest('tr').remove();
            if (!$tbody.find('tr.gc-sala-linea').length) {
                agregarLinea(salaId);
            } else {
                reindexarFilas($tbody);
            }
        });

        $(document).on('change', '.gc-default-f5', function () {
            if (!this.checked) {
                return;
            }
            var $tbody = $(this).closest('tbody');
            $tbody.find('.gc-default-f5').not(this).prop('checked', false);
        });

        $(document).on('click', '.gc-sala-panel .consultacuentacaja', function (e) {
            e.preventDefault();
            e.stopPropagation();
            abrirModal($(this).closest('.tm-cuentacaja-campo'));
        });

        $(document).on('keydown', '.gc-sala-panel .codigocuentacaja', function (e) {
            if (e.key === 'F1' || e.keyCode === 112) {
                e.preventDefault();
                abrirModal($(this).closest('.tm-cuentacaja-campo'));
                return;
            }
            if (e.key === 'Enter' || e.keyCode === 13) {
                e.preventDefault();
                leerPorCodigo($(this).closest('.tm-cuentacaja-campo'));
            }
        });

        $(document).on('blur', '.gc-sala-panel .codigocuentacaja', function () {
            if ($('#consultacuentacajaModal').hasClass('show')) {
                return;
            }
            leerPorCodigo($(this).closest('.tm-cuentacaja-campo'));
        });

        $(document).on('keyup', '#consultacuentacaja', function (e) {
            if (e.which === 13) {
                return;
            }
            buscarModal($.trim($(this).val() || ''));
        });

        $(document).on('click', '#datoscuentacaja .eligeconsultacuentacaja', function () {
            var $tr = $(this).closest('tr');
            asignarEnCampo($campoActivo, {
                id: $.trim($tr.find('td.cuentacaja_id').text() || ''),
                codigo: $.trim($tr.find('td.codigo').text() || ''),
                nombre: $.trim($tr.find('td.nombre').text() || ''),
                moneda_abreviatura: $.trim($tr.find('td.nombremoneda').text() || '')
            });
            $('#consultacuentacajaModal').modal('hide');
            $campoActivo = null;
        });

        $('#aceptaconsultacuentacajaModal').on('click', function () {
            var $btn = $('#datoscuentacaja .eligeconsultacuentacaja').first();
            if ($btn.length) {
                $btn.trigger('click');
            } else {
                $('#consultacuentacajaModal').modal('hide');
            }
        });

        $('#form-gc-sala-cuentacaja').on('submit', function () {
            $('.gc-sala-tbody').each(function () {
                reindexarFilas($(this));
            });
        });
    });
})(jQuery);
