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
            recetaArticulo: @json(url('ventas/gastronomia-crown/api/articulo')),
            estacion: @json(route('gastronomia_crown_kds_api_estacion')),
        },
        salaId: {{ (int) ($salaId ?: 0) }},
        areaId: {{ (int) ($areaId ?: 0) }},
        identificadorPc: @json($identificadorPc ?? ''),
        refrescoSegundos: {{ (int) $refrescoSegundos }},
    };
</script>
<script src="{{ asset('assets/pages/scripts/ventas/gastronomia_crown/kds.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/gastronomia_crown/kds.js')) }}"></script>
@endsection

@section('contenido')
@php
    $estacion = $estacion ?? null;
    $areaEstacion = $estacion?->areaComanda;
    $salaEstacion = $estacion?->sala;
    $estacionFija = $estacion && $areaEstacion;
    $labelAreaEstacion = $estacionFija
        ? trim(($areaEstacion->codigo ? $areaEstacion->codigo.' — ' : '').$areaEstacion->nombre)
        : '';
    $labelSalaEstacion = $salaEstacion
        ? trim(($salaEstacion->codigo ? $salaEstacion->codigo.' — ' : '').$salaEstacion->nombre)
        : 'Todas las salas';
@endphp
<div class="gck" id="gck-root">
    <div class="gck-topbar">
        <div class="gck-brand">
            <span class="gck-brand-mark">KDS</span>
            <span class="gck-brand-sub">Crown</span>
        </div>

        <div class="gck-topbar-filtros" title="{{ $estacionFija ? 'Sala/área de esta PC (cambiar con Esta PC)' : 'Filtro temporal hasta configurar Esta PC' }}">
            @if ($estacionFija)
                <span class="gck-filtros-title">Sala</span>
                <span class="gck-chip gck-chip-fijo activo">{{ $labelSalaEstacion }}</span>
                <span class="gck-filtros-title">Área</span>
                <span class="gck-chip gck-chip-fijo activo">{{ $labelAreaEstacion }}</span>
            @else
                <span class="gck-filtros-title">Sala</span>
                <a href="{{ route('gastronomia_crown_kds', array_filter(['area_id' => $areaId])) }}"
                   class="gck-chip{{ $salaId ? '' : ' activo' }}">Todas</a>
                @foreach ($salas as $sala)
                    <a href="{{ route('gastronomia_crown_kds', array_filter(['sala_id' => $sala['id'], 'area_id' => $areaId])) }}"
                       class="gck-chip{{ (int) $salaId === (int) $sala['id'] ? ' activo' : '' }}">
                        {{ $sala['codigo'] ?: $sala['nombre'] }}
                    </a>
                @endforeach
                @if (count($areas) > 0)
                    <span class="gck-filtros-sep"></span>
                    <span class="gck-filtros-title">Área</span>
                    <a href="{{ route('gastronomia_crown_kds', array_filter(['sala_id' => $salaId])) }}"
                       class="gck-chip{{ $areaId ? '' : ' activo' }}">Todas</a>
                    @foreach ($areas as $area)
                        <a href="{{ route('gastronomia_crown_kds', array_filter(['sala_id' => $salaId, 'area_id' => $area['id']])) }}"
                           class="gck-chip{{ (int) $areaId === (int) $area['id'] ? ' activo' : '' }}">
                            {{ trim(($area['codigo'] ? $area['codigo'].' — ' : '').$area['nombre']) }}
                        </a>
                    @endforeach
                @endif
            @endif
        </div>

        <div class="gck-meta">
            <span class="badge badge-light" id="gck-pc" title="Identificador de esta PC (IP/host)">PC {{ $identificadorPc ?: '—' }}</span>
            <span class="badge badge-info" id="gck-contador">0 comandas</span>
            <span class="text-muted small" id="gck-actualizado">—</span>
            <div class="custom-control custom-switch ml-1">
                <input type="checkbox" class="custom-control-input" id="gck-auto" checked>
                <label class="custom-control-label" for="gck-auto">Auto {{ (int) $refrescoSegundos }}s</label>
            </div>
            <button type="button" class="btn btn-sm btn-outline-light" id="gck-btn-refresh" title="Actualizar">
                <i class="fa fa-sync-alt"></i>
            </button>
            <button type="button"
                    class="btn btn-sm {{ $estacionFija ? 'btn-outline-warning' : 'btn-warning' }}"
                    id="gck-btn-config"
                    title="Fijar sala/área de esta PC">
                <i class="fa fa-cog"></i> Esta PC
            </button>
        </div>
    </div>

    <div class="gck-grid" id="gck-grid">
        <div class="gck-vacio">Cargando comandas…</div>
    </div>
</div>

<div class="modal fade" id="gck-modal-estacion" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info">
                <h5 class="modal-title">Configurar estación KDS de esta PC</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2">
                    Esta PC ({{ $identificadorPc ?: 'IP actual' }}) queda fija en el área elegida.
                    El POS puede estar en otra máquina: al marchar, el ítem cae acá según su categoría.
                </p>
                <div class="form-group">
                    <label for="gck-cfg-area">Área que lee esta PC</label>
                    <select id="gck-cfg-area" class="form-control">
                        <option value="">-- Elija área --</option>
                        @foreach ($areas as $area)
                            <option value="{{ $area['id'] }}"
                                {{ (int) ($estacion->area_comanda_gastronomia_id ?? $areaId ?? 0) === (int) $area['id'] ? 'selected' : '' }}>
                                {{ trim(($area['codigo'] ? $area['codigo'].' — ' : '').$area['nombre']) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group mb-0">
                    <label for="gck-cfg-sala">Sala (opcional)</label>
                    <select id="gck-cfg-sala" class="form-control">
                        <option value="">Todas las salas</option>
                        @foreach ($salas as $sala)
                            <option value="{{ $sala['id'] }}"
                                {{ (int) ($estacion->sala_id ?? $salaId ?? 0) === (int) $sala['id'] ? 'selected' : '' }}>
                                {{ trim(($sala['codigo'] ? $sala['codigo'].' — ' : '').$sala['nombre']) }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="gck-cfg-guardar">Guardar estación</button>
            </div>
        </div>
    </div>
</div>
@endsection
