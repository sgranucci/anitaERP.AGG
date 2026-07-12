(function ($) {
    'use strict';

    var CFG = window.RENDICION_BINGO_CAJA || {};

    function csrfToken() {
        return $('meta[name="csrf-token"]').attr('content')
            || $('#form-rendicion-bingo-caja input[name="_token"]').val()
            || '';
    }

    function empresaIdConsulta() {
        var el = document.getElementById('empresa_id');
        return el ? parseInt(el.value, 10) || 0 : 0;
    }

    function resetSolapaComprobante() {
        $('#consultacierre-pdf-iframe').attr('src', 'about:blank');
        $('#consultacierre-pdf-titulo').text('Seleccione «Ver PDF» en un cierre de la búsqueda.');
        $('#consultacierre-pdf-nueva-pestana').addClass('d-none').attr('href', '#');
    }

    function mostrarComprobanteEnSolapa(urlPdf, titulo) {
        if (!urlPdf) {
            return;
        }
        $('#consultacierre-pdf-titulo').text(titulo || 'Comprobante de cierre');
        $('#consultacierre-pdf-nueva-pestana').attr('href', urlPdf).removeClass('d-none');
        $('#consultacierre-pdf-iframe').attr('src', urlPdf);
        $('#consultacierre-tab-pdf').tab('show');
    }

    function buscarCierres(consulta) {
        return $.ajax({
            url: CFG.urlConsultaCierre,
            type: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
            data: {
                consulta: consulta || '',
                empresa_id: empresaIdConsulta(),
            },
        }).done(function (resp) {
            $('#datoscierre').html(resp.data || '');
        });
    }

    $(document).on('click', '.consultacierrebingo', function (e) {
        e.preventDefault();
        if (empresaIdConsulta() <= 0) {
            alert('Seleccione la empresa.');
            return;
        }
        resetSolapaComprobante();
        buscarCierres('').always(function () {
            $('#consultacierreModal').modal('show');
        });
    });

    $(document).on('keyup', '#consultacierre', function () {
        buscarCierres($(this).val());
    });

    $(document).on('click', '.js-ver-comprobante-cierre-modal', function () {
        mostrarComprobanteEnSolapa($(this).data('url'), $(this).data('titulo'));
    });

    $(document).on('click', '.eligeconsultacierre', function (e) {
        e.preventDefault();
        var $tr = $(this).closest('tr');
        var turnoId = parseInt($tr.find('.id').first().text().trim(), 10) || 0;
        if (turnoId <= 0) {
            return;
        }

        $.ajax({
            url: CFG.urlDatosTurno,
            type: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
            data: { turno_operativo_bingo_id: turnoId },
        }).done(function (resp) {
            if (!resp.ok) {
                alert(resp.mensaje || 'No se pudieron cargar los datos.');
                return;
            }
            if (typeof window.rendicionBingoAplicarDatos === 'function') {
                window.rendicionBingoAplicarDatos(resp.datos);
            }
            $('#consultacierreModal').modal('hide');
        }).fail(function (xhr) {
            var msg = (xhr.responseJSON && xhr.responseJSON.mensaje) ? xhr.responseJSON.mensaje : 'Error al cargar el cierre.';
            alert(msg);
        });
    });

    $('#consultacierreModal').on('hidden.bs.modal', function () {
        resetSolapaComprobante();
        $('#consultacierre-tab-busqueda').tab('show');
    });
})(jQuery);
