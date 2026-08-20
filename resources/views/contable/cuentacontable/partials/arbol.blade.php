@php
    $busquedaArbol = trim((string) ($filtros['valor'] ?? ''));
@endphp
<div class="pc-arbol-toolbar px-3 py-2 border-bottom d-flex flex-wrap align-items-center">
    <span class="text-muted small mr-3">{{ (int) ($arbolCount ?? 0) }} cuentas en el árbol</span>
    <button type="button" class="btn btn-outline-secondary btn-sm mr-1" id="pc-expandir-todo">Expandir todo</button>
    <button type="button" class="btn btn-outline-secondary btn-sm" id="pc-contraer-todo">Contraer</button>
    <span class="text-muted small ml-auto">Clic en una cuenta para asignar nivel y ver el bloque. Las totalizadoras se ocultan salvo que pida verlas.</span>
</div>
@if (($arbol ?? []) === [])
    <div class="p-4 text-center text-muted">
        No hay cuentas para esta empresa con los filtros actuales.
    </div>
@else
    <ul class="pc-arbol mb-0" id="pc-arbol">
        @foreach ($arbol as $nodo)
            @include('contable.cuentacontable.partials.arbol_nodo', ['nodo' => $nodo])
        @endforeach
    </ul>
@endif
