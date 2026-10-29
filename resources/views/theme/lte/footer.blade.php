<footer class="main-footer anita-footer-taskbar">
    <div class="anita-footer-inner">
        <a href="{{ urlAppDesdeRoute('inicio') }}" class="anita-footer-brand" title="{{ config('app.empresa') }}">
            @if (config('app.empresa') == 'AGG')
                <img src="{{ asset('storage/imagenes/logos/AGG.png') }}" alt="AGG" class="anita-footer-logo">
            @elseif (config('app.empresa') == 'EL BIERZO')
                <img src="{{ asset('storage/imagenes/logos/logo-bierzo.png') }}" alt="El Bierzo" class="anita-footer-logo">
            @elseif (config('app.empresa') == 'CROWN')
                <img src="{{ asset('storage/imagenes/logos/CROWN.jpg') }}?v={{ @filemtime(public_path('storage/imagenes/logos/CROWN.jpg')) ?: time() }}" alt="CROWN" class="anita-footer-logo">
            @else
                <span class="anita-footer-brand-text">{{ config('app.empresa') }}</span>
            @endif
        </a>

        @auth
            <div class="anita-taskbar" id="anita-taskbar"
                data-url-anclar="{{ route('barra_tareas_anclar') }}"
                data-url-desanclar="{{ route('barra_tareas_desanclar') }}"
                data-url-menus="{{ route('barra_tareas_menus') }}"
                data-max-anclados="{{ \App\Support\Seguridad\BarraTareasSupport::MAX_ANCLADOS }}">
                <div class="anita-taskbar-track">
                    <div class="anita-taskbar-pins" id="anita-taskbar-pins">
                        @foreach ($barraTareasAnclados ?? [] as $pin)
                            <a href="{{ url($pin['url']) }}"
                                class="anita-taskbar-pin{{ $pin['activo'] ? ' is-active' : '' }}"
                                data-menu-id="{{ $pin['menu_id'] }}"
                                title="{{ $pin['nombre'] }} — clic derecho para quitar de la barra">
                                <span class="anita-taskbar-pin-icon">
                                    <i class="{{ $pin['icono_clases'] }} fa-fw"></i>
                                </span>
                                <span class="anita-taskbar-pin-label">{{ $pin['nombre'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
                <button type="button"
                    class="anita-taskbar-add"
                    id="anita-taskbar-add"
                    aria-label="Anclar programa">
                    <span class="anita-taskbar-add-icon">
                        <i class="fas fa-plus"></i>
                    </span>
                    <span class="anita-taskbar-add-label">Agregar</span>
                </button>
            </div>
        @endauth

        <div class="anita-footer-meta">
            <div class="anita-footer-version d-none d-sm-inline">
                <b>Version</b> 1.0.0
            </div>
            <strong class="align-middle anita-footer-copy">Copyright Sysgran SRL 2021-2026</strong>
        </div>
    </div>
</footer>

@auth
    <div class="anita-accesos-panel d-none" id="anita-accesos-panel" role="dialog" aria-labelledby="anita-accesos-titulo" aria-hidden="true">
        <div class="anita-accesos-panel-header">
            <h6 class="anita-accesos-panel-titulo" id="anita-accesos-titulo">
                <i class="fas fa-thumbtack" aria-hidden="true"></i> Accesos anclados
            </h6>
            <button type="button" class="anita-accesos-cerrar" id="anita-accesos-cerrar" aria-label="Cerrar">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </div>
        <div class="anita-accesos-lista" id="anita-accesos-lista"></div>
        <div class="anita-accesos-panel-footer">
            <button type="button" class="btn btn-sm btn-outline-primary" id="anita-accesos-agregar">
                <i class="fas fa-plus"></i> Agregar
            </button>
            <span class="anita-accesos-ayuda">Clic derecho sobre un acceso para quitarlo.</span>
        </div>
    </div>
    <div class="modal fade" id="modal-barra-tareas" tabindex="-1" role="dialog" aria-labelledby="modal-barra-tareas-label" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="modal-barra-tareas-label">
                        <i class="fas fa-thumbtack text-primary"></i> Anclar programas
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body py-2">
                    <div class="input-group input-group-sm mb-2">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                        </div>
                        <input type="search" class="form-control" id="barra-tareas-buscar" placeholder="Buscar programa…" autocomplete="off">
                    </div>
                    <p class="text-muted small mb-2">
                        Seleccione los programas que desea tener como acceso directo en la barra inferior (máx. {{ \App\Support\Seguridad\BarraTareasSupport::MAX_ANCLADOS }}).
                        Para quitar uno ya anclado: clic derecho sobre el ícono en la barra inferior, o use «Quitar» en esta lista.
                    </p>
                    <div class="anita-taskbar-picker-list" id="barra-tareas-lista"></div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
@endauth
