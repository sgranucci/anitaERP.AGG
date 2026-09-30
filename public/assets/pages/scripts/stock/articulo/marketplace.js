/**
 * Grilla de marketplaces del artículo (canal Local) + modal de consulta.
 */
(function (window, $) {
    'use strict';

    if (!$) {
        return;
    }

    var ptrCampo = null;
    var modalAbriendo = false;

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function urlConsulta() {
        return $('#articulo-marketplace-consulta-url').val() || '';
    }

    function urlResolver() {
        return $('#articulo-marketplace-resolver-url').val() || '';
    }

    function token() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta && meta.getAttribute('content')) {
            return meta.getAttribute('content');
        }
        var input = document.querySelector('#form-general input[name="_token"]')
            || document.querySelector('#form-config-tiendanube input[name="_token"]')
            || document.querySelector('input[name="_token"]');
        return input ? input.value : '';
    }

    function checkboxCanalLocal() {
        return $('input[name="canal_ids[]"][data-canal-codigo="LOCAL"]');
    }

    function canalLocalMarcado() {
        var $chk = checkboxCanalLocal();
        return $chk.length > 0 && $chk.is(':checked');
    }

    function sincronizarSolapa() {
        var visible = canalLocalMarcado();
        var $li = $('#li-botonform10');
        var $fs = $('#fieldset-articulo-marketplace');
        $li.toggle(visible);
        if ($fs.length) {
            $fs.prop('disabled', !visible);
        }
        if (!visible && $('#botonform10').hasClass('active') && typeof window.mostrarSolapaArticulo === 'function') {
            window.mostrarSolapaArticulo(1);
        }
    }

    function aplicarMarketplace($campo, id, codigo, nombre, url) {
        $campo.find('.marketplace_id').val(id || '');
        $campo.find('.codigomarketplace').val(codigo || '').removeData('invalido');
        $campo.find('.descripcionmarketplace').val(nombre || '');
        var $link = $campo.find('.btn-link-editar-marketplace');
        if (id && url) {
            $link.attr('href', url).removeClass('d-none');
        } else {
            $link.attr('href', '#').addClass('d-none');
        }
    }

    function modalAbierto() {
        return $('#consultamarketplaceModal').hasClass('show') || modalAbriendo;
    }

    function renderFilas(filas) {
        var $tb = $('#datosmarketplace');
        if (!filas || !filas.length) {
            $tb.html('<tr><td colspan="3" class="text-muted">Sin resultados</td></tr>');
            return;
        }
        $tb.html(filas.map(function (f) {
            var consultar = '';
            if (f.url_consultar) {
                consultar = ' <a class="btn btn-info btn-sm" href="' + escapeHtml(f.url_consultar) + '" target="_blank" rel="noopener">Consultar</a>';
            }
            return '<tr data-id="' + escapeHtml(f.id) + '" data-codigo="' + escapeHtml(f.codigo) + '" data-nombre="' + escapeHtml(f.nombre) + '" data-url="' + escapeHtml(f.url_consultar || '') + '">'
                + '<td>' + escapeHtml(f.codigo) + '</td>'
                + '<td>' + escapeHtml(f.nombre) + '</td>'
                + '<td class="text-nowrap"><button type="button" class="btn btn-warning btn-sm eligeconsultamarketplace">Elegir</button>' + consultar + '</td>'
                + '</tr>';
        }).join(''));
    }

    function buscarModal(texto) {
        var url = urlConsulta();
        if (!url) {
            return;
        }
        $.ajax({
            url: url,
            method: 'POST',
            dataType: 'json',
            data: { texto: texto || '', _token: token() }
        }).done(function (res) {
            renderFilas((res && res.filas) || []);
        }).fail(function () {
            $('#datosmarketplace').html('<tr><td colspan="3" class="text-danger">No se pudo consultar.</td></tr>');
        });
    }

    function resolverCodigo($campo, avisar) {
        var codigo = String($campo.find('.codigomarketplace').val() || '').trim();
        if (codigo === '') {
            aplicarMarketplace($campo, '', '', '', '');
            return;
        }
        if ($campo.find('.codigomarketplace').data('invalido') === codigo && !avisar) {
            return;
        }
        var url = urlResolver();
        if (!url) {
            return;
        }
        $.ajax({
            url: url,
            method: 'GET',
            dataType: 'json',
            data: { codigo: codigo }
        }).done(function (res) {
            if (!res || !res.ok) {
                $campo.find('.marketplace_id').val('');
                $campo.find('.descripcionmarketplace').val('');
                $campo.find('.btn-link-editar-marketplace').addClass('d-none');
                $campo.find('.codigomarketplace').data('invalido', codigo);
                if (avisar) {
                    window.setTimeout(function () {
                        alert((res && res.error) || 'Marketplace no encontrado.');
                        $campo.find('.codigomarketplace').trigger('focus');
                    }, 0);
                }
                return;
            }
            aplicarMarketplace($campo, res.id, res.codigo, res.nombre, res.url_consultar || '');
        });
    }

    function agregarFila() {
        var tpl = document.getElementById('template-articulo-marketplace');
        if (!tpl) {
            return;
        }
        var nodo = tpl.content ? tpl.content.cloneNode(true) : null;
        if (!nodo) {
            return;
        }
        $('#tbody-articulo-marketplace').append(nodo);
        var $ultima = $('#tbody-articulo-marketplace .item-articulo-marketplace').last();
        $ultima.find('.codigomarketplace').trigger('focus');
    }

    $(function () {
        if (!$('#tabla-articulo-marketplace').length && !$('#li-botonform10').length && !$('.tm-marketplace-campo').length) {
            return;
        }

        window.mostrarSolapaArticulo = window.mostrarSolapaArticulo || function () {};

        sincronizarSolapa();
        $(document).on('change', 'input[name="canal_ids[]"]', sincronizarSolapa);

        $('#btn-agregar-marketplace-articulo').on('click', function () {
            agregarFila();
        });

        $(document).on('click', '.btn-quitar-marketplace-articulo', function () {
            $(this).closest('tr').remove();
        });

        $(document).on('mousedown', '.consultamarketplace', function () {
            modalAbriendo = true;
        });

        $(document).on('click', '.consultamarketplace', function (e) {
            e.preventDefault();
            ptrCampo = $(this).closest('.tm-marketplace-campo');
            modalAbriendo = true;
            $('#consultamarketplace-buscar').val('');
            $('#consultamarketplaceModal').modal('show');
            buscarModal('');
        });

        $('#consultamarketplaceModal').on('shown.bs.modal', function () {
            modalAbriendo = false;
            $('#consultamarketplace-buscar').trigger('focus');
        }).on('hidden.bs.modal', function () {
            modalAbriendo = false;
        });

        $('#consultamarketplace-buscar').on('input', function () {
            buscarModal(String($(this).val() || '').trim());
        }).on('keydown', function (e) {
            if (e.which !== 13) {
                return;
            }
            e.preventDefault();
            var $btn = $('#datosmarketplace .eligeconsultamarketplace').first();
            if ($btn.length) {
                $btn.trigger('click');
            }
        });

        $(document).on('click', '.eligeconsultamarketplace', function () {
            var $tr = $(this).closest('tr');
            var $campo = ptrCampo && ptrCampo.length ? ptrCampo : $('.tm-marketplace-campo').first();
            aplicarMarketplace($campo, $tr.data('id'), $tr.data('codigo'), $tr.data('nombre'), $tr.data('url'));
            $('#consultamarketplaceModal').modal('hide');
            $campo.find('.am-orden, .codigomarketplace').filter('.am-orden').trigger('focus');
            $campo.closest('tr').find('.am-orden').trigger('focus');
        });

        $(document).on('keydown', '.codigomarketplace', function (e) {
            if (e.which === 112) {
                e.preventDefault();
                $(this).closest('.tm-marketplace-campo').find('.consultamarketplace').trigger('click');
                return;
            }
            if (e.which === 13) {
                e.preventDefault();
                var $campo = $(this).closest('.tm-marketplace-campo');
                resolverCodigo($campo, true);
            }
        });

        $(document).on('input', '.codigomarketplace', function () {
            $(this).removeData('invalido');
        });

        $(document).on('blur', '.codigomarketplace', function () {
            if (modalAbierto()) {
                return;
            }
            resolverCodigo($(this).closest('.tm-marketplace-campo'), false);
        });
    });
})(window, window.jQuery);
