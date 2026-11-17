/**
 * Filtros e overlay del informe historial de precios por artículo/proveedor.
 */
(function ($) {
    'use strict';

    function hpaNormalizar(valor) {
        return String(valor || '').trim();
    }

    function hpaEsTeclaF1(e) {
        return e.key === 'F1' || e.code === 'F1' || e.keyCode === 112;
    }

    function hpaPantallaActiva() {
        return $('#form-historial-precios-articulo').length > 0;
    }

    function hpaTextoCelda($tr, selector) {
        var $td = $tr.find(selector).first();
        if (!$td.length) {
            return '';
        }
        return $.trim($td.clone().children().remove().end().text());
    }

    function hpaMostrarOverlay(titulo, subtitulo) {
        var overlay = document.getElementById('hpa-reporte-overlay');
        if (!overlay) {
            return;
        }
        if (titulo) {
            var t = document.getElementById('hpa-reporte-overlay-titulo');
            if (t) {
                t.textContent = titulo;
            }
        }
        if (subtitulo) {
            var s = document.getElementById('hpa-reporte-overlay-subtitulo');
            if (s) {
                s.textContent = subtitulo;
            }
        }
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }

    function hpaOcultarOverlay() {
        var overlay = document.getElementById('hpa-reporte-overlay');
        if (!overlay) {
            return;
        }
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }

    function hpaResolverProveedores($campo) {
        var valor = hpaNormalizar($campo.find('.hpa-codigo-proveedor').val());
        var $meta = $campo.find('.metaproveedor');

        if (valor === '') {
            $meta.val('Todos los proveedores');
            return;
        }

        if (valor.indexOf(',') >= 0 || valor.indexOf(';') >= 0) {
            var codigos = valor.split(/[,;]+/).map(function (s) { return s.trim(); }).filter(Boolean);
            $meta.val(codigos.length > 1
                ? 'Lista proveedores (' + codigos.length + '): ' + codigos.join(', ')
                : 'Lista proveedores');
            return;
        }

        $meta.val(valor);
    }

    function hpaAgregarProveedor(codigo, nombre) {
        var $campo = $('#hpa-reporte-proveedor-campo');
        var $inp = $campo.find('.hpa-codigo-proveedor');
        var actual = hpaNormalizar($inp.val());
        var codigos = actual === '' ? [] : actual.split(/[,;]+/).map(function (s) { return s.trim(); }).filter(Boolean);
        var codigoStr = String(codigo).trim();
        var nombreStr = String(nombre || '').trim();

        if (codigoStr !== '' && codigos.indexOf(codigoStr) < 0) {
            codigos.push(codigoStr);
        }

        $inp.val(codigos.join(','));
        if (codigos.length === 1 && nombreStr !== '') {
            $campo.find('.metaproveedor').val(codigoStr + ' — ' + nombreStr);
            return;
        }
        hpaResolverProveedores($campo);
    }

    // La consulta general escribe en #codigoproveedor. Este filtro es lista de códigos.
    window.onProveedorElegidoEnConsulta = function (fila) {
        if (!document.getElementById('form-historial-precios-articulo')) {
            return false;
        }
        var codigo = fila && fila.codigo ? String(fila.codigo).trim() : '';
        if (codigo !== '') {
            hpaAgregarProveedor(codigo, fila.nombre || '');
        }
        $('#consultaproveedorModal').modal('hide');
        return true;
    };

    function hpaBuscarProveedoresModal(consulta) {
        $.ajax({
            url: carpetaBase + '/compras/proveedor/consultaproveedor',
            type: 'POST',
            dataType: 'HTML',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            },
            data: { consulta: consulta },
        })
            .done(function (respuesta) {
                var html = '';
                try {
                    html = JSON.parse(respuesta).data || '';
                } catch (e) {
                    html = respuesta;
                }
                $('#datosproveedor').html(html);
            })
            .fail(function () {
                $('#datosproveedor').html('<tr><td colspan="6">Error al consultar proveedores</td></tr>');
            });
    }

    function hpaAbrirModalProveedor() {
        var valor = $('#hpa-reporte-proveedor-campo .hpa-codigo-proveedor').val().trim();
        hpaBuscarProveedoresModal(valor);
        $('#consultaproveedorModal').modal('show');
    }

    function hpaAtajoF1Handler(e) {
        if (!hpaEsTeclaF1(e) || !hpaPantallaActiva()) {
            return;
        }
        var target = e.target;
        if (!target || !target.classList) {
            return;
        }
        if (target.classList.contains('hpa-codigo-proveedor')) {
            e.preventDefault();
            e.stopPropagation();
            hpaAbrirModalProveedor();
        }
    }

    function activaEventosHistorialPreciosFiltro() {
        var $form = $('#form-historial-precios-articulo');
        if (!$form.length) {
            return;
        }

        hpaResolverProveedores($('#hpa-reporte-proveedor-campo'));

        $form.on('submit', function () {
            if (typeof this.checkValidity === 'function' && !this.checkValidity()) {
                return;
            }
            hpaMostrarOverlay(
                'Consultando historial de precios…',
                'Puede demorar según el período. No cierre la página.'
            );
        });

        // Descarga sin navegación: sin Esc/focus el aviso queda pegado.
        $(document)
            .off('click.hpareporte', 'a[href*="listar-historial-precios-articulo"]')
            .on('click.hpareporte', 'a[href*="listar-historial-precios-articulo"]', function () {
                hpaMostrarOverlay(
                    'Exportando…',
                    'El archivo se descarga al terminar. Pulse Esc para cerrar este aviso.'
                );
                window.addEventListener('focus', hpaOcultarOverlay, { once: true });
            });

        $(document)
            .off('change.hpareporte', '#hpa-reporte-proveedor-campo .hpa-codigo-proveedor')
            .on('change.hpareporte', '#hpa-reporte-proveedor-campo .hpa-codigo-proveedor', function () {
                hpaResolverProveedores($('#hpa-reporte-proveedor-campo'));
            });

        $(document)
            .off('click.hpareporte', '#hpa-reporte-proveedor-campo .consultaproveedor-hpa')
            .on('click.hpareporte', '#hpa-reporte-proveedor-campo .consultaproveedor-hpa', function (e) {
                e.preventDefault();
                hpaAbrirModalProveedor();
            });

        $('#consultaproveedorModal')
            .off('shown.bs.modal.hpareporte')
            .on('shown.bs.modal.hpareporte', function () {
                if (!$('#form-historial-precios-articulo').length) {
                    return;
                }
                var valor = $('#hpa-reporte-proveedor-campo .hpa-codigo-proveedor').val().trim();
                $('#consultaproveedor').val(valor);
                hpaBuscarProveedoresModal(valor);
                $(this).find('#consultaproveedor').focus();
            });

        $(document)
            .off('keyup.hpareporte', '#consultaproveedor')
            .on('keyup.hpareporte', '#consultaproveedor', function () {
                if (!$('#form-historial-precios-articulo').length) {
                    return;
                }
                hpaBuscarProveedoresModal($(this).val().trim());
            });

        $(document)
            .off('click.hpareporte', '.eligeconsultaproveedor')
            .on('click.hpareporte', '.eligeconsultaproveedor', function (e) {
                if (!$('#form-historial-precios-articulo').length) {
                    return;
                }
                e.stopImmediatePropagation();

                var $trModal = $(this).closest('tr');
                var codigo = hpaTextoCelda($trModal, 'td.codigoproveedor, td.codigo');
                var nombre = hpaTextoCelda($trModal, 'td.nombreproveedor, td.nombre');

                if (codigo !== '') {
                    hpaAgregarProveedor(codigo, nombre);
                }

                $('#consultaproveedorModal').modal('hide');
                return false;
            });

        document.removeEventListener('keydown', hpaAtajoF1Handler, true);
        document.addEventListener('keydown', hpaAtajoF1Handler, true);
    }

    $(document).ready(activaEventosHistorialPreciosFiltro);
    window.addEventListener('pageshow', hpaOcultarOverlay);
    window.addEventListener('pagehide', hpaOcultarOverlay);
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' || event.keyCode === 27) {
            hpaOcultarOverlay();
        }
    });
})(jQuery);
