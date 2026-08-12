<form id="form-filtros-reporte-definible" method="get" action="{{ route('reporte_definible') }}" class="collapse mb-3" data-parent="">
    <div class="card card-outline card-info">
        <div class="card-body py-2">
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Texto</label>
                    <input type="text" name="filtro_valor" id="filtro_valor_panel" class="form-control form-control-sm"
                           value="{{ $filtros['filtro_valor'] ?? '' }}" placeholder="Nombre, código, título…">
                </div>
                <div class="form-group col-md-2">
                    <label>Tipo</label>
                    <select name="tipo" class="form-control form-control-sm">
                        <option value="">Todos</option>
                        @foreach ($tiposReporte as $k => $label)
                            <option value="{{ $k }}" @if (($filtros['tipo'] ?? '') === $k) selected @endif>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-2">
                    <label>Origen</label>
                    <select name="origen" class="form-control form-control-sm">
                        <option value="">Todos</option>
                        <option value="anita" @if (($filtros['origen'] ?? '') === 'anita') selected @endif>Anita</option>
                        <option value="manual" @if (($filtros['origen'] ?? '') === 'manual') selected @endif>Manual</option>
                    </select>
                </div>
                <div class="form-group col-md-2">
                    <label>Activo</label>
                    <select name="activo" class="form-control form-control-sm">
                        <option value="">Todos</option>
                        <option value="1" @if (($filtros['activo'] ?? '') === '1') selected @endif>Sí</option>
                        <option value="0" @if (($filtros['activo'] ?? '') === '0') selected @endif>No</option>
                    </select>
                </div>
                <div class="form-group col-md-2 d-flex align-items-end">
                    <div class="custom-control custom-checkbox mb-2">
                        <input type="checkbox" class="custom-control-input" id="solo_publicado" name="solo_publicado" value="1"
                               @if (($filtros['solo_publicado'] ?? '') === '1') checked @endif>
                        <label class="custom-control-label" for="solo_publicado">Solo publicados</label>
                    </div>
                </div>
                <div class="form-group col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-info btn-sm">Aplicar filtros</button>
                </div>
            </div>
        </div>
    </div>
</form>
@include('includes.listado.filtros_aviso_activos', [
    'tieneFiltros' => \App\Support\Contable\ReporteDefinibleListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
    'rutaLimpiar' => route('reporte_definible'),
])
