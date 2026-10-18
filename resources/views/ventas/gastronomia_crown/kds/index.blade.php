@extends("theme.$theme.layout")
@section('titulo')
    KDS Crown
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/gastronomia-crown-kds.css') }}?v={{ @filemtime(public_path('assets/css/gastronomia-crown-kds.css')) }}">
@endsection

@section('scripts')
<script>
    window.GastroCrownKds = {
        urls: {
            estado: @json(route('gastronomia_crown_kds_api_estado')),
            comandaBase: @json(url('ventas/gastronomia-crown/kds/api/comanda')),
        },
        salaId: {{ (int) ($salaId ?: 0) }},
        areaId: {{ (int) ($areaId ?: 0) }},
        refrescoSegundos: {{ (int) $refrescoSegundos }},
    };
</script>
<script src="{{ asset('assets/pages/scripts/ventas/gastronomia_crown/kds.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/gastronomia_crown/kds.js')) }}"></script>
@endsection

@section('contenido')
<div class="gck" id="gck-root">
    <div class="gck-topbar">
        <div class="gck-brand">
            <span class="gck-brand-mark">KDS</span>
            <span class="gck-brand-sub">Cocina · gastronomía Crown</span>
        </div>
        <div class="gck-meta">
            <span class="badge badge-info" id="gck-contador">0 comandas</span>
            <span class="text-muted small" id="gck-actualizado">—</span>
            <div class="custom-control custom-switch ml-2">
                <input type="checkbox" class="custom-control-input" id="gck-auto" checked>
                <label class="custom-control-label" for="gck-auto">Auto {{ (int) $refrescoSegundos }}s</label>
            </div>
            <button type="button" class="btn btn-sm btn-outline-light ml-2" id="gck-btn-refresh" title="Actualizar">
                <i class="fa fa-sync-alt"></i>
            </button>
        </div>
    </div>

    <div class="gck-filtros">
        <span class="gck-filtros-title"><i class="fa fa-door-open"></i> Sala</span>
        <a href="{{ route('gastronomia_crown_kds', array_filter(['area_id' => $areaId])) }}"
           class="gck-chip{{ $salaId ? '' : ' activo' }}">Todas</a>
        @foreach ($salas as $sala)
            <a href="{{ route('gastronomia_crown_kds', array_filter(['sala_id' => $sala['id'], 'area_id' => $areaId])) }}"
               class="gck-chip{{ (int) $salaId === (int) $sala['id'] ? ' activo' : '' }}">
                {{ trim(($sala['codigo'] ? $sala['codigo'].' — ' : '').$sala['nombre']) }}
            </a>
        @endforeach
    </div>

    @if (count($areas) > 0)
        <div class="gck-filtros">
            <span class="gck-filtros-title"><i class="fa fa-utensils"></i> Área</span>
            <a href="{{ route('gastronomia_crown_kds', array_filter(['sala_id' => $salaId])) }}"
               class="gck-chip{{ $areaId ? '' : ' activo' }}">Todas</a>
            @foreach ($areas as $area)
                <a href="{{ route('gastronomia_crown_kds', array_filter(['sala_id' => $salaId, 'area_id' => $area['id']])) }}"
                   class="gck-chip{{ (int) $areaId === (int) $area['id'] ? ' activo' : '' }}">
                    {{ trim(($area['codigo'] ? $area['codigo'].' — ' : '').$area['nombre']) }}
                </a>
            @endforeach
        </div>
    @endif

    <div class="gck-grid" id="gck-grid">
        <div class="gck-vacio">Cargando comandas…</div>
    </div>
</div>
@endsection
