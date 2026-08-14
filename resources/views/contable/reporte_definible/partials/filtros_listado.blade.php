@php
    use App\Support\Contable\ReporteDefinibleListadoFiltros;
    $f = $filtros ?? [];
    $tieneCriteriosPanel = ReporteDefinibleListadoFiltros::tieneCriteriosAplicados($f);
    $limpiarUrlPanel = $limpiarUrl ?? route('reporte_definible');
@endphp
<div class="collapse border-bottom" id="panel-filtros-reporte-definible" data-listado-filtros-panel>
    <div class="card-body bg-light py-2 text-body">
        @if ($tieneCriteriosPanel)
            <div class="mb-2">
                @include('includes.listado.filtros_aviso_activos', [
                    'tieneCriterios' => true,
                    'limpiarUrl' => $limpiarUrlPanel,
                    'compact' => true,
                ])
            </div>
        @endif
        <div class="form-row align-items-end">
            <div class="form-group col-md-4 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_valor_panel">Texto</label>
                <input type="text" name="filtro_valor" id="filtro_valor_panel" class="form-control form-control-sm"
                       value="{{ $f['filtro_valor'] ?? '' }}" placeholder="Nombre, código, título…" autocomplete="off">
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_tipo">Tipo</label>
                <select name="tipo" id="filtro_tipo" class="form-control form-control-sm">
                    <option value="">Todos</option>
                    @foreach ($tiposReporte as $k => $label)
                        <option value="{{ $k }}" {{ ($f['tipo'] ?? '') === $k ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_origen">Origen</label>
                <select name="origen" id="filtro_origen" class="form-control form-control-sm">
                    <option value="">Todos</option>
                    <option value="anita" {{ ($f['origen'] ?? '') === 'anita' ? 'selected' : '' }}>Anita</option>
                    <option value="manual" {{ ($f['origen'] ?? '') === 'manual' ? 'selected' : '' }}>Manual</option>
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_activo">Activo</label>
                <select name="activo" id="filtro_activo" class="form-control form-control-sm">
                    <option value="">Todos</option>
                    <option value="1" {{ ($f['activo'] ?? '') === '1' ? 'selected' : '' }}>Sí</option>
                    <option value="0" {{ ($f['activo'] ?? '') === '0' ? 'selected' : '' }}>No</option>
                </select>
            </div>
            <div class="form-group col-md-auto col-sm-6 mb-2">
                <div class="custom-control custom-checkbox mb-2">
                    <input type="checkbox" class="custom-control-input" id="solo_publicado" name="solo_publicado" value="1"
                           {{ ($f['solo_publicado'] ?? '') === '1' ? 'checked' : '' }}>
                    <label class="custom-control-label" for="solo_publicado">Solo publicados</label>
                </div>
            </div>
            <div class="form-group col-md-auto mb-2">
                <button type="submit" class="btn btn-primary btn-sm" data-aplicar-filtros-panel="1">
                    <i class="fa fa-search"></i> Aplicar filtros
                </button>
            </div>
        </div>
    </div>
</div>
