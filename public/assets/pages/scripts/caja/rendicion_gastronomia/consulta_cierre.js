/* global carpetaBase, RENDICION_GASTRONOMIA */

(function ($) {
    'use strict';

    function csrfToken() {
        var meta = $('meta[name="csrf-token"]').attr('content');
        if (meta) {
            return meta;
        }
        var $form = $('#form-rendicion-gastronomia');
        if ($form.length) {
            return $form.find('input[name="_token"]').val() || '';
        }
        return $('input[name="_token"]').first().val() || '';
    }

    function urlConsultaCierre() {
        if (typeof RENDICION_GASTRONOMIA !== 'undefined' && RENDICION_GASTRONOMIA.urlConsultaCierre) {
            return RENDICION_GASTRONOMIA.urlConsultaCierre;
        }
        return (typeof carpetaBase !== 'undefined' ? carpetaBase : '') + '/caja/rendiciongastronomia/api/consulta-cierre';
    }

    function empresaIdConsulta() {
        var el = document.getElementById('empresa_id');
        if (!el) {
            return 0;
        }
        return parseInt(el.value, 10) || 0;
    }

    function exceptoRendicionId() {
        return typeof RENDICION_GASTRONOMIA !== 'undefined' && RENDICION_GASTRONOMIA.rendicionId
            ? RENDICION_GASTRONOMIA.rendicionId
            : '';
    }

    function resetSolapaComprobante() {
        var $iframe = $('#consultacierre-pdf-iframe');
        $iframe.attr('src', 'about:blank');
        $('#consultacierre-pdf-titulo').text('Seleccione «Ver PDF» en un cierre de la búsqueda.');
        var $lnk = $('#consultacierre-pdf-nueva-pestana');
        $lnk.addClass('d-none').attr('href', '#');
    }

    function mostrarComprobanteEnSolapa(urlPdf, titulo) {
        if (!urlPdf) {
            return;
        }
        $('#consultacierre-pdf-titulo').text(titulo || 'Comprobante de cierre');
        var $lnk = $('#consultacierre-pdf-nueva-pestana');
        $lnk.attr('href', urlPdf).removeClass('d-none');
        $('#consultacierre-pdf-iframe').attr('src', urlPdf);
        $('#consultacierre-tab-pdf').tab('show');
    }

    function alcanceConsulta() {
        if (typeof RENDICION_GASTRONOMIA !== 'undefined' && RENDICION_GASTRONOMIA.alcanceConsulta) {
            return RENDICION_GASTRONOMIA.alcanceConsulta;
        }
        var inp = document.getElementById('tipo_rendicion');
        if (inp && inp.value === 'jornada') {
            return 'jornada';
        }
        return 'turno';
    }

    function actualizarTituloModal() {
        var esJornada = alcanceConsulta() === 'jornada';
        $('#consultacierreModalLabel').text(
            esJornada ? 'Jornadas cerradas pendientes de rendir' : 'Cierres de turno pendientes de rendir'
        );
        var $thead = $('#tabla-data-cierre thead tr');
        if (esJornada) {
            $thead.html(
                '<th>Nº jorn.</th><th>Fecha jornada</th><th>Cierre</th><th>Waitry hasta</th><th>Usuario cierre</th><th class="width120">Acciones</th>'
            );
        } else {
            $thead.html(
                '<th>Nº op.</th><th>Turno</th><th>Terminal</th><th>Cierre</th><th>Jornada</th><th class="width120">Acciones</th>'
            );
        }
    }

    function buscarCierres(consulta) {
        var empresaId = empresaIdConsulta();
        if (!empresaId) {
            $('#datoscierre').html('<tr><td colspan="6">Seleccione una empresa.</td></tr>');
            return;
        }

        $.ajax({
            url: urlConsultaCierre(),
            type: 'POST',
            dataType: 'json',
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            data: {
                consulta: consulta || '',
                empresa_id: empresaId,
                excepto_rendicion_id: exceptoRendicionId(),
                alcance: alcanceConsulta(),
                _token: csrfToken(),
            },
        })
            .done(function (respuesta) {
                var html = '';
                if (typeof respuesta === 'string') {
                    try {
                        html = JSON.parse(respuesta).data || '';
                    } catch (e) {
                        html = respuesta;
                    }
                } else if (respuesta && typeof respuesta.data === 'string') {
                    html = respuesta.data;
                }
                $('#datoscierre').html(html || '<tr><td colspan="6">Sin resultados.</td></tr>');
            })
            .fail(function (xhr) {
                var msg = 'Error al consultar cierres.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                } else if (xhr.status === 403) {
                    msg = 'Sin permiso para consultar cierres.';
                } else if (xhr.status === 419) {
                    msg = 'Sesión expirada. Recargue la página.';
                }
                $('#datoscierre').html('<tr><td colspan="6">' + msg + '</td></tr>');
            });
    }

    function activarEventosConsultaCierre() {
        $(document)
            .off('click.consultaCierre', '.consultacierre')
            .on('click.consultaCierre', '.consultacierre', function (e) {
                e.preventDefault();
                if (typeof RENDICION_GASTRONOMIA !== 'undefined') {
                    RENDICION_GASTRONOMIA.alcanceConsulta = 'turno';
                }
                if (!empresaIdConsulta()) {
                    alert('Seleccione una empresa antes de consultar cierres.');
                    return;
                }
                actualizarTituloModal();
                $('#consultacierre').val('');
                resetSolapaComprobante();
                $('#consultacierre-tab-busqueda').tab('show');
                $('#consultacierreModal').modal('show');
            })
            .on('click.consultaCierre', '.consultacierrejornada', function (e) {
                e.preventDefault();
                if (typeof RENDICION_GASTRONOMIA !== 'undefined') {
                    RENDICION_GASTRONOMIA.alcanceConsulta = 'jornada';
                }
                if (!empresaIdConsulta()) {
                    alert('Seleccione una empresa antes de consultar jornadas.');
                    return;
                }
                actualizarTituloModal();
                $('#consultacierre').val('');
                resetSolapaComprobante();
                $('#consultacierre-tab-busqueda').tab('show');
                $('#consultacierreModal').modal('show');
            });

        $('#consultacierreModal')
            .off('shown.bs.modal.consultaCierre')
            .on('shown.bs.modal.consultaCierre', function () {
                buscarCierres($('#consultacierre').val());
                window.setTimeout(function () {
                    $('#consultacierre').trigger('focus');
                }, 0);
            })
            .off('hidden.bs.modal.consultaCierre')
            .on('hidden.bs.modal.consultaCierre', function () {
                resetSolapaComprobante();
                $('#consultacierre-tab-busqueda').tab('show');
            });

        $(document)
            .off('keyup.consultaCierre', '#consultacierre')
            .on('keyup.consultaCierre', '#consultacierre', function () {
                buscarCierres($(this).val());
            });

        $(document)
            .off('keydown.consultaCierre', '#consultacierre')
            .on('keydown.consultaCierre', '#consultacierre', function (e) {
                if (e.key === 'Enter' || e.which === 13) {
                    e.preventDefault();
                    e.stopPropagation();
                }
            });

        $(document)
            .off('click.consultaCierre', '.js-ver-comprobante-cierre-modal')
            .on('click.consultaCierre', '.js-ver-comprobante-cierre-modal', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var url = $(this).data('url') || $(this).attr('data-url') || '';
                var titulo = $(this).data('titulo') || $(this).attr('data-titulo') || '';
                mostrarComprobanteEnSolapa(url, titulo);
            });

        $(document)
            .off('click.consultaCierre', '.js-ver-cierre-turno-detalle')
            .on('click.consultaCierre', '.js-ver-cierre-turno-detalle', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var url = $(this).data('url') || $(this).attr('data-url') || '';
                if (!url) {
                    return;
                }
                if (window.ModoConsulta) {
                    url = window.ModoConsulta.url(url);
                }
                window.open(url, '_blank', 'noopener');
            });

        $(document)
            .off('click.consultaCierre', '.eligeconsultacierre')
            .on('click.consultaCierre', '.eligeconsultacierre', function (e) {
                e.preventDefault();
                var $tr = $(this).closest('tr');
                var id = $tr.find('.id').text().trim();
                var etiqueta = 'Op. #' + id
                    + ' — ' + $tr.find('.turno_nombre').text().trim()
                    + ' — ' + $tr.find('.identificador_pc').text().trim()
                    + ' — cierre ' + $tr.find('.cierre_en').text().trim();

                $('#turno_operativo_gastronomia_id').val(id);
                $('#turno_operativo_numero').val(id);
                $('#lbl-turno-seleccionado').text(etiqueta);

                $('#consultacierreModal').modal('hide');

                if (typeof window.rendicionGastronomiaFijarTipo === 'function') {
                    window.rendicionGastronomiaFijarTipo('turno');
                }
                $('#tipo_rendicion').val('turno');

                if (typeof window.rendicionGastronomiaCargarTurno === 'function') {
                    window.rendicionGastronomiaCargarTurno(id);
                } else {
                    alert('No se pudo cargar el cierre. Recargue la página.');
                }
            })
            .on('click.consultaCierre', '.eligeconsultacierrejornada', function (e) {
                e.preventDefault();
                var $tr = $(this).closest('tr');
                var id = $tr.find('.id').text().trim();
                var etiqueta = 'Jornada #' + id
                    + ' — ' + $tr.find('.fecha_jornada').text().trim()
                    + ' — cierre ' + $tr.find('.cierre_en').text().trim();

                $('#jornada_gastronomia_id').val(id);
                $('#jornada_gastronomia_numero').val(id);
                $('#lbl-jornada-seleccionada').text(etiqueta);

                $('#consultacierreModal').modal('hide');

                if (typeof window.rendicionGastronomiaFijarTipo === 'function') {
                    window.rendicionGastronomiaFijarTipo('jornada');
                }
                $('#tipo_rendicion').val('jornada');

                if (typeof window.rendicionGastronomiaCargarJornada === 'function') {
                    window.rendicionGastronomiaCargarJornada(id);
                } else {
                    alert('No se pudo cargar la jornada. Recargue la página.');
                }
            });
    }

    $(function () {
        activarEventosConsultaCierre();
    });
})(jQuery);
