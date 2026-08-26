(function ($) {
    'use strict';

    function esc(s) {
        return $('<div>').text(s == null ? '' : String(s)).html();
    }

    function csrf() {
        return $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val() || '';
    }

    function abrirPdf(url) {
        if (!url) {
            return;
        }
        window.open(url, '_blank', 'noopener');
    }

    function renderHistoria(rows) {
        var $tb = $('#tablaBandejaHistoria tbody').empty();
        if (!rows || !rows.length) {
            $tb.append('<tr><td colspan="5" class="text-center text-muted">Sin movimientos de sector.</td></tr>');
            return;
        }
        rows.forEach(function (r) {
            var sec = (r.sector_legajocompras && r.sector_legajocompras.nombre) ? r.sector_legajocompras.nombre : '';
            var usr = (r.usuarios && r.usuarios.nombre) ? r.usuarios.nombre : '';
            var f = r.fecha ? String(r.fecha).replace('T', ' ').substring(0, 19) : '';
            $tb.append(
                '<tr><td>' + esc(f) + '</td><td>' + esc(sec) + '</td><td>' + esc(r.observacion || '') +
                '</td><td>' + esc(r.leyenda || '') + '</td><td>' + esc(usr) + '</td></tr>'
            );
        });
    }

    function mostrarPdf($iframe, url) {
        if (!$iframe.length) {
            return;
        }
        $iframe.attr('src', url || 'about:blank');
    }

    function renderComs(paquete) {
        var $tb = $('#tablaBandejaComs tbody').empty();
        var $pdf = $('#bandejaComPdf');
        var coms = (paquete && paquete.coms) || [];
        mostrarPdf($pdf, '');
        if (!coms.length) {
            $tb.append('<tr><td colspan="3" class="text-center text-muted">No hay COM en este legajo.</td></tr>');
            return;
        }
        coms.forEach(function (c, i) {
            var $tr = $('<tr class="js-bandeja-pdf-row" style="cursor:pointer;"></tr>');
            $tr.attr('data-url-pdf', c.url_pdf || '');
            $tr.append('<td>' + esc(c.documento || ('#' + c.id)) + '</td>');
            $tr.append('<td>' + esc(c.fecha || '') + '</td>');
            $tr.append('<td>' + esc(c.estado || '') + '</td>');
            if (i === 0) {
                $tr.addClass('table-info');
            }
            $tb.append($tr);
        });
        if (coms[0] && coms[0].url_pdf) {
            mostrarPdf($pdf, coms[0].url_pdf);
        }
    }

    function renderFacturas(paquete) {
        var $tb = $('#tablaBandejaFacturas tbody').empty();
        var $pdf = $('#bandejaFacturaPdf');
        var facs = (paquete && paquete.facturas) || [];
        mostrarPdf($pdf, '');
        if (!facs.length) {
            $tb.append('<tr><td colspan="3" class="text-center text-muted">No hay PDF de factura (precarga ni escaneo Anita).</td></tr>');
            return;
        }
        facs.forEach(function (f, i) {
            var origen = (f.origen === 'anita') ? 'Anita' : (f.estado || 'Precarga');
            var $tr = $('<tr class="js-bandeja-pdf-row" style="cursor:pointer;"></tr>');
            $tr.attr('data-url-pdf', f.url_pdf || '');
            $tr.append('<td>' + esc(f.etiqueta || ('#' + f.id)) + '</td>');
            $tr.append('<td>' + esc(f.fecha || '') + '</td>');
            $tr.append('<td>' + esc(origen) + '</td>');
            if (i === 0) {
                $tr.addClass('table-info');
            }
            $tb.append($tr);
        });
        if (facs[0] && facs[0].url_pdf) {
            mostrarPdf($pdf, facs[0].url_pdf);
        }
    }

    function renderAsignar(paquete) {
        var $fac = $('#bandejaAsignarPrecarga').empty();
        var $coms = $('#bandejaAsignarComs').empty();
        var facs = (paquete && paquete.facturas) || [];
        var coms = ((paquete && paquete.coms) || []).filter(function (c) { return c.confirmada; });
        var asignadas = (paquete && paquete.asignadas) || {};
        if (!facs.length) {
            $fac.append('<p class="text-muted mb-0">No hay factura precargada. Adjuntela al enviar el legajo o desde la OC.</p>');
        } else {
            facs.forEach(function (f, i) {
                $fac.append(
                    '<div class="form-check">' +
                    '<input class="form-check-input" type="radio" name="precarga_id" id="ban_pre_' + f.id + '" value="' + f.id + '"' + (i === 0 ? ' checked' : '') + '>' +
                    '<label class="form-check-label" for="ban_pre_' + f.id + '">' + esc(f.etiqueta) +
                    (f.fecha ? ' <small class="text-muted">' + esc(f.fecha) + '</small>' : '') + '</label></div>'
                );
            });
        }
        if (!coms.length) {
            $coms.append('<p class="text-muted mb-0">No hay COM confirmada para asignar.</p>');
        } else {
            var preId = facs.length ? facs[0].id : 0;
            var idsAsig = asignadas[preId] || [];
            coms.forEach(function (c) {
                var checked = idsAsig.indexOf(c.id) !== -1 || idsAsig.indexOf(String(c.id)) !== -1;
                $coms.append(
                    '<div class="form-check">' +
                    '<input class="form-check-input" type="checkbox" name="recepcion_ids[]" id="ban_com_' + c.id + '" value="' + c.id + '"' + (checked ? ' checked' : '') + '>' +
                    '<label class="form-check-label" for="ban_com_' + c.id + '">' + esc(c.documento) +
                    (c.fecha ? ' <small class="text-muted">' + esc(c.fecha) + '</small>' : '') + '</label></div>'
                );
            });
        }
        var $atajos = $('#bandejaAsignarAtajos').empty();
        if (paquete && paquete.url_cargar_cxp) {
            $atajos.append('<a href="' + esc(paquete.url_cargar_cxp) + '" class="btn btn-sm btn-primary mr-1"><i class="fa fa-plus"></i> Cargar factura en CxP</a>');
        }
        if (paquete && paquete.comprobantes) {
            paquete.comprobantes.forEach(function (cp) {
                $atajos.append('<a href="' + esc(cp.url) + '" class="btn btn-sm btn-outline-info mr-1">FC ' + esc(cp.etiqueta) + '</a>');
            });
        }
        if (paquete && paquete.pagos) {
            paquete.pagos.forEach(function (op) {
                $atajos.append('<a href="' + esc(op.url) + '" class="btn btn-sm btn-outline-success mr-1">OP ' + esc(op.etiqueta) + '</a>');
            });
        }
    }

    function cargarPaquete(url, done) {
        $.get(url).done(done).fail(function () {
            alert('No se pudo leer el paquete del legajo.');
        });
    }

    $(function () {
        if (window.OcCambiarSectorLegajo) {
            window.OcCambiarSectorLegajo.initForm($('#formBandejaEnviarGastro'), { forzarPaquete: true });
            window.OcCambiarSectorLegajo.initForm($('#formBandejaEnviarCxp'), { forzarPaquete: true });
        }

        $('.js-bandeja-enviar-gastro').on('click', function () {
            var $form = $('#formBandejaEnviarGastro');
            $form.attr('action', $(this).data('url'));
            $form.find('input[name=observacion]').val('');
            $form.find('textarea[name=leyenda]').val('');
            $form.find('input[type=file]').val('');
            $form.data('ordencompra-id', $(this).data('ordencompra-id') || '');
            if (window.OcCambiarSectorLegajo) {
                window.OcCambiarSectorLegajo.initForm($form, { forzarPaquete: true });
            }
            $('#modalBandejaEnviarGastro').modal('show');
        });

        $('.js-bandeja-enviar-cxp').on('click', function () {
            var $form = $('#formBandejaEnviarCxp');
            $form.attr('action', $(this).data('url'));
            $form.find('input[name=observacion]').val('');
            $form.find('textarea[name=leyenda]').val('');
            $form.find('input[type=file]').val('');
            $form.data('ordencompra-id', $(this).data('ordencompra-id') || '');
            if (window.OcCambiarSectorLegajo) {
                window.OcCambiarSectorLegajo.initForm($form, { forzarPaquete: true });
                window.OcCambiarSectorLegajo.setOrdencompraId($form, $(this).data('ordencompra-id') || '');
            }
            $('#modalBandejaEnviarCxp').modal('show');
        });

        $('.js-bandeja-historia').on('click', function () {
            var numero = $(this).data('numero') || '';
            $('#modalBandejaHistoria .modal-title').text('Historia del legajo OC ' + numero);
            $('#tablaBandejaHistoria tbody').html('<tr><td colspan="5" class="text-center text-muted">Cargando…</td></tr>');
            $('#modalBandejaHistoria').modal('show');
            $.get($(this).data('url')).done(renderHistoria).fail(function () {
                $('#tablaBandejaHistoria tbody').html('<tr><td colspan="5" class="text-center text-danger">No se pudo leer la historia.</td></tr>');
            });
        });

        $(document).on('click', '.js-bandeja-pdf-row', function () {
            var url = $(this).data('url-pdf');
            var $modal = $(this).closest('.modal');
            $(this).addClass('table-info').siblings().removeClass('table-info');
            mostrarPdf($modal.find('iframe'), url);
        });

        $('.js-bandeja-ver-com').on('click', function () {
            var urlPaquete = $(this).data('url-paquete');
            var numero = $(this).data('numero') || '';
            $('#modalBandejaComs .modal-title').text('COM del legajo OC ' + numero);
            $('#tablaBandejaComs tbody').html('<tr><td colspan="3" class="text-center text-muted">Cargando…</td></tr>');
            mostrarPdf($('#bandejaComPdf'), '');
            $('#modalBandejaComs').modal('show');
            cargarPaquete(urlPaquete, renderComs);
        });

        $('.js-bandeja-ver-factura').on('click', function () {
            var urlPaquete = $(this).data('url-paquete');
            var numero = $(this).data('numero') || '';
            $('#modalBandejaFacturas .modal-title').text('Factura del legajo OC ' + numero);
            $('#tablaBandejaFacturas tbody').html('<tr><td colspan="3" class="text-center text-muted">Cargando…</td></tr>');
            mostrarPdf($('#bandejaFacturaPdf'), '');
            $('#modalBandejaFacturas').modal('show');
            cargarPaquete(urlPaquete, renderFacturas);
        });

        $('.js-bandeja-asignar-com').on('click', function () {
            var $btn = $(this);
            var numero = $btn.data('numero') || '';
            $('#formBandejaAsignarCom').attr('action', $btn.data('url-asignar'));
            $('#modalBandejaAsignarCom .modal-title').text('Asignar COM a la factura — OC ' + numero);
            $('#bandejaAsignarPrecarga, #bandejaAsignarComs').html('<p class="text-muted">Cargando…</p>');
            $('#bandejaAsignarAtajos').empty();
            $('#modalBandejaAsignarCom').modal('show');
            cargarPaquete($btn.data('url-paquete'), renderAsignar);
        });

        $('#formBandejaAsignarCom').on('submit', function (e) {
            e.preventDefault();
            var $form = $(this);
            $.ajax({
                url: $form.attr('action'),
                method: 'POST',
                data: $form.serialize(),
                headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' }
            }).done(function (resp) {
                $('#modalBandejaAsignarCom').modal('hide');
                if (resp && resp.mensaje) {
                    alert(resp.mensaje);
                }
                window.location.reload();
            }).fail(function (xhr) {
                var msg = 'No se pudo asignar la COM.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                    msg = Object.values(xhr.responseJSON.errors).join(' ');
                }
                alert(msg);
            });
        });
    });
})(jQuery);
