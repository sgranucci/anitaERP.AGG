{{-- Requiere: tieneCriterios, limpiarUrl. Opcional: showLimpiar (default true), compact (panel más chico) --}}
@include('includes.listado.filtros_estilos_activos')
@if(!empty($tieneCriterios))
    <span class="listado-filtros-aviso-activos d-inline-flex align-items-center{{ !empty($compact) ? ' listado-filtros-aviso-panel w-100 justify-content-between' : '' }}">
        <span class="listado-filtros-icono-activo{{ empty($compact) ? ' mr-1' : ' mr-2' }}"
              title="Filtros activos: el listado está acotado por criterios de búsqueda"
              aria-label="Filtros activos">
            <i class="fa fa-filter" aria-hidden="true"></i>
        </span>
        @if(($showLimpiar ?? true) && !empty($limpiarUrl))
            <a href="{{ $limpiarUrl }}"
               class="btn btn-warning btn-sm font-weight-bold text-dark shadow-sm listado-filtros-btn-limpiar{{ !empty($compact) ? ' py-0' : ' mr-1' }}"
               title="Quitar todos los criterios y ver el listado completo">
                <i class="fa fa-times-circle" aria-hidden="true"></i> Limpiar filtros
            </a>
        @endif
    </span>
@endif
