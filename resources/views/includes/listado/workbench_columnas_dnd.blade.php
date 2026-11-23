{{--
  Workbench estándar: form oculto + config JS para drag/ocultar columnas en thead.
  Requiere: $rutaGuardarColumnas, $grillaLayout, $filtros (o $filtrosQuery), opcional $vistaActiva.
  Script: listado/workbench-columnas-dnd.js (cargarlo en @section scripts).
--}}
@php
    $rutaGuardarColumnasDnd = $rutaGuardarColumnasDnd ?? $rutaGuardarColumnas ?? null;
    $filtrosDnd = $filtrosDnd ?? $filtros ?? [];
    $paraQsDnd = $paraQueryStringDnd ?? null;
@endphp
@if ($rutaGuardarColumnasDnd)
<form method="post" action="{{ route($rutaGuardarColumnasDnd) }}" id="form-lw-columnas-dnd" class="d-none" aria-hidden="true">
    @csrf
    @if (is_callable($paraQsDnd))
        {{-- Un solo argumento: no todos los ListadoFiltros aceptan el 2.º ($consultar). --}}
        @foreach ($paraQsDnd($filtrosDnd) as $k => $v)
            @if ($k === 'columnas')
                @continue
            @endif
            @include('includes.listado.hidden_nested', ['prefix' => '', 'data' => [$k => $v]])
        @endforeach
    @else
        @foreach (($filtrosQuery ?? []) as $k => $v)
            @if ($k === 'columnas')
                @continue
            @endif
            @include('includes.listado.hidden_nested', ['prefix' => '', 'data' => [$k => $v]])
        @endforeach
    @endif
    @if ($vistaActiva ?? null)
        <input type="hidden" name="vista_id" value="{{ $vistaActiva->id }}">
        <input type="hidden" name="actualizar_vista" value="1">
    @endif
</form>
{{-- Confirmación antes de ocultar con × (evita clics involuntarios). --}}
<div class="modal fade" id="modal-lw-ocultar-columna" tabindex="-1" role="dialog"
     aria-labelledby="modal-lw-ocultar-columna-titulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-lw-ocultar-columna-titulo">Ocultar columna</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="mb-0">
                    ¿Ocultar la columna <strong id="modal-lw-ocultar-columna-nombre"></strong>?
                </p>
                <p class="text-muted small mt-2 mb-0">
                    Podés volver a mostrarla desde «Diseñar vista».
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning btn-sm" id="btn-lw-ocultar-columna-confirmar">
                    Ocultar columna
                </button>
            </div>
        </div>
    </div>
</div>
<script>
window.lwColumnasDnd = {
    url: @json(route($rutaGuardarColumnasDnd)),
    layout: @json($grillaLayout ?? [])
};
</script>
@endif
