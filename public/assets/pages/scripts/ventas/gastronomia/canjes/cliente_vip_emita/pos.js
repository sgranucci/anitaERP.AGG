(function ($) {
    'use strict';

    const G = window.CANJE_MARKETING || {};
    const apiBase = (G.rutas && G.rutas.apiBase) ? G.rutas.apiBase.replace(/\/$/, '') : '';
    let modo = 'nombre';

    function csrf() {
        return G.csrfToken || $('meta[name="csrf-token"]').attr('content') || '';
    }

    function escapar(texto) {
        return $('<div>').text(texto == null ? '' : String(texto)).html();
    }

    function aviso(texto) {
        const $aviso = $('#cm-vip-emita-aviso');
        if (!texto) {
            $aviso.addClass('d-none').text('');
            return;
        }
        $aviso.removeClass('d-none').text(texto);
    }

    function abrir(nuevoModo) {
        modo = nuevoModo === 'alias' ? 'alias' : 'nombre';
        $('#modal-cm-vip-emita-title').text(modo === 'alias' ? 'Clientes VIP Emita por alias' : 'Clientes VIP Emita por nombre');
        $('#cm-vip-emita-label').text(modo === 'alias' ? 'Alias' : 'Nombre y apellido');
        $('#cm-vip-emita-texto').val('');
        aviso('');
        $('#cm-vip-emita-tbody').html('<tr><td colspan="10" class="text-muted text-center">Indique un texto y consulte.</td></tr>');
        $('#modal-cm-vip-emita').modal('show');
    }

    function pintar(filas, total) {
        if (!filas || !filas.length) {
            $('#cm-vip-emita-tbody').html('<tr><td colspan="10" class="text-muted text-center">Sin resultados.</td></tr>');
            return;
        }
        let html = '';
        filas.forEach(function (fila) {
            const elegir = '<button type="button" class="btn btn-warning btn-sm cm-emita-elegir"'
                + ' data-sala="' + escapar(fila.sala) + '"'
                + ' data-origen="' + escapar(fila.origen) + '"'
                + ' data-cuenta="' + escapar(fila.cuenta_wigos) + '"'
                + ' data-nombre="' + escapar(fila.nombre_apellido) + '"'
                + ' data-documento="' + escapar(fila.documento) + '"'
                + ' data-alias="' + escapar(fila.alias) + '">Elegir</button>';
            html += '<tr>'
                + '<td>' + escapar(fila.sala) + '</td>'
                + '<td>' + escapar(fila.origen) + '</td>'
                + '<td>' + escapar(fila.cuenta_wigos) + '</td>'
                + '<td>' + escapar(fila.nombre_apellido) + '</td>'
                + '<td>' + escapar(fila.documento) + '</td>'
                + '<td>' + escapar(fila.alias) + '</td>'
                + '<td>' + escapar(fila.nivel_tarjeta) + '</td>'
                + '<td>' + escapar(fila.vip) + '</td>'
                + '<td>' + escapar(fila.ultima_visita) + '</td>'
                + '<td class="text-nowrap">' + elegir + '</td>'
                + '</tr>';
        });
        $('#cm-vip-emita-tbody').html(html);
        if (total > filas.length) {
            aviso('Hay ' + total + ' resultados. Se muestran los primeros ' + filas.length + '. Afine el texto.');
        }
    }

    function buscar() {
        const texto = ($('#cm-vip-emita-texto').val() || '').trim();
        if (texto.length < 3) {
            aviso('Indique al menos 3 caracteres.');
            return;
        }
        aviso('');
        $('#cm-vip-emita-tbody').html('<tr><td colspan="10" class="text-center text-muted">Buscando en Emita…</td></tr>');
        $.ajax({
            url: apiBase + '/consulta-cliente-vip-emita',
            type: 'POST',
            dataType: 'json',
            headers: {
                'X-CSRF-TOKEN': csrf(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            data: { _token: csrf(), modo: modo, texto: texto },
            success: function (resp) {
                pintar(resp.filas || [], resp.total || 0);
            },
            error: function (xhr) {
                const j = xhr && xhr.responseJSON;
                const msg = (j && (j.error || j.mensaje)) || 'No se pudo consultar Emita.';
                aviso(msg);
                $('#cm-vip-emita-tbody').html('<tr><td colspan="10" class="text-center text-danger">' + escapar(msg) + '</td></tr>');
            },
        });
    }

    $('#cm-btn-vip-emita-nombre').on('click', function () { abrir('nombre'); });
    $('#cm-btn-vip-emita-alias').on('click', function () { abrir('alias'); });
    $('#cm-vip-emita-buscar').on('click', buscar);
    $('#cm-vip-emita-texto').on('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            buscar();
        }
    });
    $('#modal-cm-vip-emita').on('shown.bs.modal', function () {
        $('#cm-vip-emita-texto').trigger('focus');
    });

    $(document).on('click', '.cm-emita-elegir', function () {
        const $btn = $(this);
        if (!apiBase || $btn.prop('disabled')) {
            return;
        }
        aviso('');
        $btn.prop('disabled', true);
        $.ajax({
            url: apiBase + '/cliente-vip/desde-emita',
            type: 'POST',
            dataType: 'json',
            headers: {
                'X-CSRF-TOKEN': csrf(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            data: {
                _token: csrf(),
                sala: $btn.attr('data-sala') || '',
                origen: $btn.attr('data-origen') || '',
                cuenta_wigos: $btn.attr('data-cuenta') || '',
                nombre_apellido: $btn.attr('data-nombre') || '',
                documento: $btn.attr('data-documento') || '',
                alias: $btn.attr('data-alias') || '',
            },
            success: function (data) {
                if (data && data.cliente_vip && typeof window.cmAplicarClienteVip === 'function') {
                    window.cmAplicarClienteVip(data.cliente_vip);
                    $('#modal-cm-vip-emita').modal('hide');
                }
            },
            error: function (xhr) {
                const j = xhr && xhr.responseJSON;
                aviso((j && (j.error || j.mensaje)) || 'No se pudo elegir el cliente de Emita.');
                $btn.prop('disabled', false);
            },
        });
    });
}(jQuery));
