{{-- Chips originales (Estado / Ámbito / Atajos). Siempre visibles; AND con QBE. --}}
@php
    use App\Support\Compras\OrdencompraLegajoBandejaFiltros;
@endphp
<div class="lw-filtros-prefijados">
    <div class="d-flex flex-wrap align-items-center">
        <div class="d-flex flex-wrap align-items-center mb-2">
            <span class="text-muted small mr-2 mb-0"><i class="fa fa-filter"></i> Estado:</span>
            <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="Estado del legajo">
                @foreach ($vistas as $key => $label)
                    <a href="{{ $qs(['vista' => $key]) }}"
                       class="btn {{ $vista === $key ? 'btn-primary' : 'btn-outline-primary' }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>
        <div class="d-none d-md-block mx-3 mb-2 align-self-center" style="width:1px;height:28px;background:#dee2e6;"></div>
        <div class="d-flex flex-wrap align-items-center mb-2">
            <span class="text-muted small mr-2 mb-0"><i class="fa fa-cutlery"></i> Ámbito:</span>
            <div class="btn-group btn-group-sm" role="group" aria-label="Ámbito gastronomía">
                <a href="{{ $qs(['tab' => OrdencompraLegajoBandejaFiltros::TAB_TODOS]) }}"
                   class="btn {{ $tab === OrdencompraLegajoBandejaFiltros::TAB_TODOS ? 'btn-info' : 'btn-outline-info' }}">Todas</a>
                <a href="{{ $qs(['tab' => OrdencompraLegajoBandejaFiltros::TAB_GASTRONOMIA]) }}"
                   class="btn {{ $tab === OrdencompraLegajoBandejaFiltros::TAB_GASTRONOMIA ? 'btn-info' : 'btn-outline-info' }}">Gastronomía</a>
                <a href="{{ $qs(['tab' => OrdencompraLegajoBandejaFiltros::TAB_RESTO]) }}"
                   class="btn {{ $tab === OrdencompraLegajoBandejaFiltros::TAB_RESTO ? 'btn-info' : 'btn-outline-info' }}">Resto</a>
            </div>
        </div>
    </div>
    <div class="d-flex flex-wrap align-items-center">
        <span class="text-muted small mr-2 mb-1">Atajos:</span>
        <div class="btn-group btn-group-sm flex-wrap mb-1" role="group" aria-label="Atajos de documentos">
            @foreach ($atajos as $key => $label)
                <a href="{{ $qsAtajo($key) }}"
                   class="btn {{ $atajo === $key ? 'btn-secondary' : 'btn-outline-secondary' }}">{{ $label }}</a>
            @endforeach
        </div>
    </div>
</div>
