(function ($) {
    'use strict';

    function actualizarEtiqueta($input) {
        var activo = $input.is(':checked');
        var $row = $input.closest('tr');
        var $label = $row.find('.ticket-cfg-toggle-label');

        $label.text(activo ? 'Activo' : 'Como ahora');
        $row.toggleClass('ticket-cfg-row-on', activo);
        $row.attr('data-activo', activo ? '1' : '0');
    }

    function contarActivos() {
        var total = $('#tabla-ticket-configuracion .ticket-cfg-toggle:checked').length;
        $('#ticket-cfg-stat-activos').text(total);
    }

    function contarClaim() {
        var total = 0;
        $('#tabla-ticket-configuracion-area .ticket-cfg-modo').each(function () {
            if ($(this).val() === 'claim') {
                total += 1;
            }
        });
        $('#ticket-cfg-stat-claim').text(total);
    }

    function aplicarFiltros() {
        var texto = $.trim($('#ticket-cfg-filtro').val() || '').toLowerCase();
        var modo = $('.ticket-cfg-filter .btn.active').data('filtro') || 'todos';
        var visibles = 0;

        $('#tabla-ticket-configuracion tbody tr[data-nombre]').each(function () {
            var $row = $(this);
            var nombre = String($row.data('nombre') || '');
            var activo = String($row.attr('data-activo') || '0') === '1';
            var okTexto = !texto || nombre.indexOf(texto) !== -1;
            var okModo = modo === 'todos'
                || (modo === 'activos' && activo)
                || (modo === 'inactivos' && !activo);
            var mostrar = okTexto && okModo;

            $row.toggleClass('ticket-cfg-row-hidden', !mostrar);
            if (mostrar) {
                visibles += 1;
            }
        });

        $('#ticket-cfg-sin-resultados').toggle(visibles === 0);
    }

    $(function () {
        $('#tabla-ticket-configuracion').on('change', '.ticket-cfg-toggle', function () {
            actualizarEtiqueta($(this));
            contarActivos();
            aplicarFiltros();
        });

        $('#tabla-ticket-configuracion-area').on('change', '.ticket-cfg-modo', function () {
            var $row = $(this).closest('tr');
            var esClaim = $(this).val() === 'claim';
            $row.toggleClass('ticket-cfg-row-claim', esClaim);
            $row.attr('data-modo', $(this).val());
            contarClaim();
        });

        $('#ticket-cfg-filtro').on('input', aplicarFiltros);

        $('.ticket-cfg-filter').on('click', '.btn', function () {
            $('.ticket-cfg-filter .btn').removeClass('active');
            $(this).addClass('active');
            aplicarFiltros();
        });

        function actualizarStatExclusion() {
            var total = $('#tabla-ticket-exclusion tbody tr[data-usuario-id]').length;
            $('#ticket-cfg-stat-exclusion').text(total);
        }

        function asegurarFilaVaciaExclusion() {
            var $tbody = $('#tabla-ticket-exclusion tbody');
            var hayFilas = $tbody.find('tr[data-usuario-id]').length > 0;
            $tbody.find('tr.ticket-cfg-exclusion-vacia').remove();
            if (!hayFilas) {
                $tbody.append(
                    '<tr class="ticket-cfg-exclusion-vacia">' +
                    '<td colspan="4" class="ticket-cfg-empty">Nadie excluido. El CC llega a todos los usuarios del centro.</td>' +
                    '</tr>'
                );
            }
        }

        function escaparHtml(valor) {
            return $('<div>').text(valor == null ? '' : String(valor)).html();
        }

        $('#ticket-cfg-exclusion-agregar').on('click', function () {
            var $select = $('#ticket-cfg-exclusion-usuario');
            var $option = $select.find('option:selected');
            var id = $.trim($option.val() || '');
            if (!id) {
                return;
            }
            if ($('#tabla-ticket-exclusion tbody tr[data-usuario-id="' + id + '"]').length) {
                $select.val('');
                return;
            }

            var nombre = $option.data('nombre') || $option.text();
            var usuario = $option.data('usuario') || '';
            var email = $option.data('email') || '—';
            var centrocosto = $option.data('centrocosto') || '—';

            $('#tabla-ticket-exclusion tbody tr.ticket-cfg-exclusion-vacia').remove();
            $('#tabla-ticket-exclusion tbody').append(
                '<tr data-usuario-id="' + escaparHtml(id) + '">' +
                '<td><input type="hidden" name="usuario_ids[]" value="' + escaparHtml(id) + '">' +
                '<strong>' + escaparHtml(nombre) + '</strong>' +
                '<div class="text-muted small">' + escaparHtml(usuario) + '</div></td>' +
                '<td>' + escaparHtml(email) + '</td>' +
                '<td>' + escaparHtml(centrocosto) + '</td>' +
                '<td class="text-center">' +
                '<button type="button" class="btn btn-link btn-sm text-danger ticket-cfg-exclusion-quitar" title="Quitar">' +
                '<i class="fa fa-times"></i></button></td></tr>'
            );
            $option.remove();
            $select.val('');
            actualizarStatExclusion();
        });

        $('#tabla-ticket-exclusion').on('click', '.ticket-cfg-exclusion-quitar', function () {
            var $row = $(this).closest('tr');
            var id = $row.attr('data-usuario-id');
            var nombre = $.trim($row.find('strong').first().text());
            var usuario = $.trim($row.find('.text-muted.small').first().text());
            var email = $.trim($row.find('td').eq(1).text());
            var centrocosto = $.trim($row.find('td').eq(2).text());
            $row.remove();

            var $select = $('#ticket-cfg-exclusion-usuario');
            if (id && $select.find('option[value="' + id + '"]').length === 0) {
                var etiqueta = nombre + (usuario ? ' (' + usuario + ')' : '');
                if (centrocosto && centrocosto !== '—') {
                    etiqueta += ' — ' + centrocosto;
                }
                var $option = $('<option></option>')
                    .val(id)
                    .text(etiqueta)
                    .attr('data-usuario', usuario)
                    .attr('data-nombre', nombre)
                    .attr('data-email', email === '—' ? '' : email)
                    .attr('data-centrocosto', centrocosto === '—' ? '' : centrocosto);
                $select.append($option);
            }

            asegurarFilaVaciaExclusion();
            actualizarStatExclusion();
        });
    });
})(jQuery);
