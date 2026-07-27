/**
 * Chincheta de favoritos de auditoría (mismo espíritu que barra de tareas).
 */
(function ($) {
    'use strict';

    function csrfToken() {
        return $('meta[name="csrf-token"]').attr('content')
            || $('input[name="_token"]').first().val()
            || '';
    }

    function escHtml(text) {
        return String(text || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function $bar() {
        return $('#auditoria-fav-bar');
    }

    function urls() {
        var $b = $bar();
        return {
            anclar: $b.data('url-anclar'),
            desanclar: $b.data('url-desanclar'),
            listar: $b.data('url-listar'),
        };
    }

    function confirmar(nombre, anclar) {
        var deferred = $.Deferred();
        var titulo = anclar ? '¿Anclar en favoritos?' : '¿Quitar de favoritos?';
        var texto = anclar
            ? '«' + nombre + '» quedará arriba en tus favoritos de auditoría.'
            : '«' + nombre + '» dejará de mostrarse en tus favoritos.';
        var ok = anclar ? 'Anclar' : 'Quitar';

        if (typeof swal !== 'function') {
            if (window.confirm(titulo + '\n\n' + texto)) {
                deferred.resolve();
            } else {
                deferred.reject();
            }
            return deferred.promise();
        }

        swal({
            title: titulo,
            text: texto,
            icon: 'warning',
            buttons: { cancel: 'Cancelar', confirm: { text: ok, value: true } },
            dangerMode: !anclar,
        }).then(function (value) {
            if (value) {
                deferred.resolve();
            } else {
                deferred.reject();
            }
        });

        return deferred.promise();
    }

    function postToggle(anclar, type) {
        var u = urls();
        return $.ajax({
            url: anclar ? u.anclar : u.desanclar,
            method: 'POST',
            data: {
                _token: csrfToken(),
                auditable_type: type,
            },
        });
    }

    function queryBaseFavorito(type) {
        var params = new URLSearchParams(window.location.search);
        params.set('pestana', 'datos');
        params.set('consultar', '1');
        params.set('auditable_type', type);
        return window.location.pathname + '?' + params.toString();
    }

    function renderChips(favoritos) {
        var $chips = $('#auditoria-fav-chips');
        var actual = $('#auditable_type').val() || '';
        $chips.empty();

        if (!favoritos || !favoritos.length) {
            $chips.append(
                $('<span>', {
                    class: 'text-muted small',
                    id: 'auditoria-fav-vacio',
                    text: 'Todavía no tenés favoritos. Usá la chincheta o «Gestionar favoritos».',
                })
            );
            return;
        }

        favoritos.forEach(function (fav) {
            var active = fav.auditable_type === actual ? ' is-active' : '';
            var $a = $('<a>', {
                href: queryBaseFavorito(fav.auditable_type),
                class: 'auditoria-fav-chip' + active,
                'data-type': fav.auditable_type,
                title: (fav.modulo || '') + ' · ' + (fav.tabla || ''),
            });
            $a.append(
                $('<i>', { class: 'fas fa-thumbtack' }),
                $('<span>', { text: fav.etiqueta || fav.tabla || fav.auditable_type })
            );
            $chips.append($a);
        });
    }

    function syncPinButtons(favoritos) {
        var types = {};
        (favoritos || []).forEach(function (f) {
            types[f.auditable_type] = true;
        });

        $('.auditoria-pin-btn[data-type]').each(function () {
            var type = $(this).data('type');
            var pinned = !!types[type];
            $(this).toggleClass('is-pinned', pinned);
            $(this).attr('title', pinned ? 'Quitar de favoritos' : 'Anclar en favoritos');
        });

        var sel = $('#auditable_type').val() || '';
        var $btn = $('#btn-pin-modelo-actual');
        $btn.prop('disabled', sel === '');
        $btn.toggleClass('is-pinned', !!types[sel]);
        $btn.attr('title', types[sel] ? 'Quitar de favoritos' : 'Anclar en favoritos');
    }

    function rebuildFavoritosOptgroup(favoritos) {
        var $sel = $('#auditable_type');
        var current = $sel.val();
        var $og = $('#optgroup-favoritos-auditoria');
        if (!$og.length) {
            return;
        }
        $og.empty();
        (favoritos || []).forEach(function (fav) {
            $og.append(
                $('<option>', {
                    value: fav.auditable_type,
                    text: fav.etiqueta + (fav.tabla ? ' (' + fav.tabla + ')' : ''),
                })
            );
        });
        if (current) {
            $sel.val(current);
        }
    }

    function aplicarFavoritos(favoritos) {
        renderChips(favoritos);
        syncPinButtons(favoritos);
        rebuildFavoritosOptgroup(favoritos);
    }

    function toggleType(type, nombre, ancladoAhora) {
        var anclar = !ancladoAhora;
        confirmar(nombre || type, anclar).then(function () {
            return postToggle(anclar, type);
        }).done(function (resp) {
            if (!resp || !resp.ok) {
                var msg = (resp && resp.mensaje) ? resp.mensaje : 'No se pudo actualizar el favorito.';
                if (typeof swal === 'function') {
                    swal('Error', msg, 'error');
                } else {
                    alert(msg);
                }
                return;
            }
            aplicarFavoritos(resp.favoritos || []);
        }).fail(function (xhr) {
            var msg = (xhr.responseJSON && xhr.responseJSON.mensaje)
                ? xhr.responseJSON.mensaje
                : 'Error de red al actualizar favorito.';
            if (typeof swal === 'function') {
                swal('Error', msg, 'error');
            } else {
                alert(msg);
            }
        });
    }

    $(function () {
        if (!$bar().length) {
            return;
        }

        $('#auditable_type').on('change', function () {
            var sel = $(this).val() || '';
            var $btn = $('#btn-pin-modelo-actual');
            $btn.prop('disabled', sel === '');
            var pinned = false;
            $('#auditoria-fav-chips .auditoria-fav-chip').each(function () {
                if ($(this).data('type') === sel) {
                    pinned = true;
                }
            });
            $btn.toggleClass('is-pinned', pinned);
            $btn.attr('title', pinned ? 'Quitar de favoritos' : 'Anclar en favoritos');
        });

        $('#btn-pin-modelo-actual').on('click', function () {
            var type = $('#auditable_type').val();
            if (!type) {
                return;
            }
            var nombre = $('#auditable_type option:selected').text().trim();
            var anclado = $(this).hasClass('is-pinned');
            toggleType(type, nombre, anclado);
        });

        $(document).on('click', '#lista-modal-fav-auditoria .auditoria-pin-btn', function () {
            var type = $(this).data('type');
            var nombre = $(this).data('nombre') || type;
            var anclado = $(this).hasClass('is-pinned');
            toggleType(type, nombre, anclado);
        });

        $('#filtro-modal-fav-auditoria').on('input', function () {
            var q = String($(this).val() || '').toLowerCase().trim();
            $('#lista-modal-fav-auditoria .auditoria-fav-mod-row').each(function () {
                var hay = !q || String($(this).data('search') || '').indexOf(q) !== -1;
                $(this).toggle(hay);
            });
            $('#lista-modal-fav-auditoria .auditoria-fav-mod-block').each(function () {
                var visibles = $(this).find('.auditoria-fav-mod-row:visible').length;
                $(this).toggle(visibles > 0);
            });
        });
    });
})(jQuery);
