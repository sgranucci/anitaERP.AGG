(function () {
    'use strict';

    function mostrarOverlay(titulo) {
        var overlay = document.getElementById('cierre-periodo-overlay');
        if (!overlay) {
            return;
        }
        var tituloEl = document.getElementById('cierre-periodo-overlay-titulo');
        if (titulo && tituloEl) {
            tituloEl.textContent = titulo;
        }
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }

    function ocultarOverlay() {
        var overlay = document.getElementById('cierre-periodo-overlay');
        if (!overlay) {
            return;
        }
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }

    function setGrupoExpandido(grupo, expandido) {
        if (!grupo) {
            return;
        }
        document.querySelectorAll('.agenda-fila-submodulo[data-grupo-padre="' + grupo + '"]').forEach(function (tr) {
            if (expandido) {
                tr.classList.remove('d-none');
                tr.hidden = false;
                tr.style.display = '';
            } else {
                tr.classList.add('d-none');
                tr.hidden = true;
                tr.style.display = 'none';
            }
        });
        document.querySelectorAll('.agenda-toggle-submodulos[data-grupo="' + grupo + '"]').forEach(function (btn) {
            btn.setAttribute('aria-expanded', expandido ? 'true' : 'false');
            var icon = btn.querySelector('.agenda-toggle-icon');
            if (icon) {
                icon.classList.toggle('fa-chevron-right', !expandido);
                icon.classList.toggle('fa-chevron-down', expandido);
            }
            var filaModulo = btn.closest('tr');
            if (filaModulo) {
                var folder = filaModulo.querySelector('.fa-folder, .fa-folder-open');
                if (folder) {
                    folder.classList.toggle('fa-folder', !expandido);
                    folder.classList.toggle('fa-folder-open', expandido);
                }
            }
        });
    }

    function todosExpandidos() {
        var botones = document.querySelectorAll('.agenda-toggle-submodulos');
        if (!botones.length) {
            return false;
        }
        for (var i = 0; i < botones.length; i++) {
            if (botones[i].getAttribute('aria-expanded') !== 'true') {
                return false;
            }
        }
        return true;
    }

    function actualizarBotonGlobal() {
        var btn = document.getElementById('btn-expandir-todos-submodulos');
        if (!btn) {
            return;
        }
        var abierto = todosExpandidos();
        btn.setAttribute('aria-expanded', abierto ? 'true' : 'false');
        btn.innerHTML = abierto
            ? '<i class="fa fa-compress"></i> Colapsar'
            : '<i class="fa fa-expand"></i> Submódulos';
        btn.title = abierto ? 'Ocultar todos los submódulos' : 'Mostrar todos los submódulos';
    }

    function toggleGrupoDesdeBoton(btn) {
        var grupo = btn.getAttribute('data-grupo');
        var expandido = btn.getAttribute('aria-expanded') === 'true';
        setGrupoExpandido(grupo, !expandido);
        actualizarBotonGlobal();
    }

    function expandirOColapsarTodos() {
        var abrir = !todosExpandidos();
        document.querySelectorAll('.agenda-toggle-submodulos').forEach(function (btn) {
            setGrupoExpandido(btn.getAttribute('data-grupo'), abrir);
        });
        actualizarBotonGlobal();
    }

    function initAgendaToggle() {
        if (window.__cierrePeriodoAgendaToggleInit) {
            return;
        }
        window.__cierrePeriodoAgendaToggleInit = true;

        document.addEventListener('click', function (event) {
            var toggle = event.target.closest('.agenda-toggle-submodulos');
            if (toggle) {
                event.preventDefault();
                toggleGrupoDesdeBoton(toggle);
                return;
            }
            if (event.target.closest('#btn-expandir-todos-submodulos')) {
                event.preventDefault();
                expandirOColapsarTodos();
            }
        });

        document.querySelectorAll('form.form-proceso-cierre').forEach(function (form) {
            form.addEventListener('submit', function () {
                if (form.checkValidity && !form.checkValidity()) {
                    return;
                }
                var titulo = form.getAttribute('data-overlay-titulo') || 'Procesando…';
                mostrarOverlay(titulo);
            });
        });

        window.addEventListener('pageshow', function () {
            ocultarOverlay();
        });

        // Estado inicial: submódulos ocultos (por si el HTML no trajo d-none).
        document.querySelectorAll('.agenda-fila-submodulo').forEach(function (tr) {
            if (tr.getAttribute('data-inicializado-collapse') === '1') {
                return;
            }
            tr.setAttribute('data-inicializado-collapse', '1');
            if (!tr.classList.contains('d-none') && !tr.hidden) {
                return;
            }
            tr.classList.add('d-none');
            tr.hidden = true;
            tr.style.display = 'none';
        });

        actualizarBotonGlobal();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAgendaToggle);
    } else {
        initAgendaToggle();
    }
})();
