/**
 * Filtros e overlay del informe cuentas contables de artículos vs OC Anita.
 */
(function ($) {
    'use strict';

    function acoNormalizar(valor) {
        return String(valor || '').trim();
    }

    function acoEsTeclaF1(e) {
        return e.key === 'F1' || e.code === 'F1' || e.keyCode === 112;
    }

    function acoPantallaActiva() {
        return $('#form-articulo-cuenta-oc').length > 0;
    }

    function acoMostrarOverlay(titulo, subtitulo) {
        var overlay = document.getElementById('aco-reporte-overlay');
        if (!overlay) {
            return;
        }
        if (titulo) {
            var t = document.getElementById('aco-reporte-overlay-titulo');
            if (t) {
                t.textContent = titulo;
            }
        }
        if (subtitulo) {
            var s = document.getElementById('aco-reporte-overlay-subtitulo');
            if (s) {
                s.textContent = subtitulo;
            }
        }
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }

    function acoOcultarOverlay() {
        var overlay = document.getElementById('aco-reporte-overlay');
        if (!overlay) {
            return;
        }
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }

    function acoResolverProveedores($campo) {
        var valor = acoNormalizar($campo.find('.codigoproveedor').val());
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

    function acoAgregarProveedor(codigo) {
        var $campo = $('#aco-reporte-proveedor-campo');
        var $inp = $campo.find('.codigoproveedor');
        var actual = acoNormalizar($inp.val());
        var codigos = actual === '' ? [] : actual.split(/[,;]+/).map(function (s) { return s.trim(); }).filter(Boolean);
        var codigoStr = String(codigo).trim();

        if (codigoStr !== '' && codigos.indexOf(codigoStr) < 0) {
            codigos.push(codigoStr);
        }

        $inp.val(codigos.join(','));
        acoResolverProveedores($campo);
    }

    function acoBuscarProveedoresModal(consulta) {
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

    function acoAbrirModalProveedor() {
        var valor = $('#aco-reporte-proveedor-campo .codigoproveedor').val().trim();
        acoBuscarProveedoresModal(valor);
        $('#consultaproveedorModal').modal('show');
    }

    function acoAtajoF1Handler(e) {
        if (!acoEsTeclaF1(e) || !acoPantallaActiva()) {
            return;
        }
        var target = e.target;
        if (!target || !target.classList) {
            return;
        }
        if (target.classList.contains('codigoproveedor')) {
            e.preventDefault();
            e.stopPropagation();
            acoAbrirModalProveedor();
        }
    }

    function activaEventosArticuloCuentaOcFiltro() {
        var $form = $('#form-articulo-cuenta-oc');
        if (!$form.length) {
            return;
        }

        acoResolverProveedores($('#aco-reporte-proveedor-campo'));

        $form.on('submit', function () {
            if (typeof this.checkValidity === 'function' && !this.checkValidity()) {
                return;
            }
            acoMostrarOverlay(
                'Consultando OC en Anita…',
                'Puede demorar según el período. No cierre la página.'
            );
        });

        // Descarga sin navegación: sin Esc/focus el aviso queda pegado.
        $(document)
            .off('click.acoreporte', 'a[href*="listar-articulo-cuenta-oc-reporte"]')
            .on('click.acoreporte', 'a[href*="listar-articulo-cuenta-oc-reporte"]', function () {
                acoMostrarOverlay(
                    'Exportando…',
                    'El archivo se descarga al terminar. Pulse Esc para cerrar este aviso.'
                );
                window.addEventListener('focus', acoOcultarOverlay, { once: true });
            });

        $(document)
            .off('change.acoreporte', '#aco-reporte-proveedor-campo .codigoproveedor')
            .on('change.acoreporte', '#aco-reporte-proveedor-campo .codigoproveedor', function () {
                acoResolverProveedores($('#aco-reporte-proveedor-campo'));
            });

        $(document)
            .off('click.acoreporte', '#aco-reporte-proveedor-campo .consultaproveedor-aco')
            .on('click.acoreporte', '#aco-reporte-proveedor-campo .consultaproveedor-aco', function (e) {
                e.preventDefault();
                acoAbrirModalProveedor();
            });

        $('#consultaproveedorModal')
            .off('shown.bs.modal.acoreporte')
            .on('shown.bs.modal.acoreporte', function () {
                if (!$('#form-articulo-cuenta-oc').length) {
                    return;
                }
                var valor = $('#aco-reporte-proveedor-campo .codigoproveedor').val().trim();
                $('#consultaproveedor').val(valor);
                acoBuscarProveedoresModal(valor);
                $(this).find('#consultaproveedor').focus();
            });

        $(document)
            .off('keyup.acoreporte', '#consultaproveedor')
            .on('keyup.acoreporte', '#consultaproveedor', function () {
                if (!$('#form-articulo-cuenta-oc').length) {
                    return;
                }
                acoBuscarProveedoresModal($(this).val().trim());
            });

        $(document)
            .off('click.acoreporte', '.eligeconsultaproveedor')
            .on('click.acoreporte', '.eligeconsultaproveedor', function (e) {
                if (!$('#form-articulo-cuenta-oc').length) {
                    return;
                }
                e.stopImmediatePropagation();

                var $trModal = $(this).closest('tr');
                var codigo = $trModal.find('.codigo').first().text().trim();

                if (codigo !== '') {
                    acoAgregarProveedor(codigo);
                }

                $('#consultaproveedorModal').modal('hide');
                return false;
            });

        document.removeEventListener('keydown', acoAtajoF1Handler, true);
        document.addEventListener('keydown', acoAtajoF1Handler, true);
    }

    $(document).ready(activaEventosArticuloCuentaOcFiltro);
    window.addEventListener('pageshow', acoOcultarOverlay);
    window.addEventListener('pagehide', acoOcultarOverlay);
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' || event.keyCode === 27) {
            acoOcultarOverlay();
        }
    });
})(jQuery);
