@php
    use App\Support\Seguridad\IngresoProveedorVisitanteSupport;
    $mostrarEstado = $mostrarEstado ?? true;
    $mostrarTipo = $mostrarTipo ?? true;
@endphp
@include('includes.form-empresa-asignada', [
    'empresa_query' => $empresa_query,
    'empresa_id' => $filtros['empresa_id'] ?? null,
    'required' => false,
    'col_label' => 'col-lg-2',
    'col_input' => 'col-lg-4',
])
<div class="form-group row">
    <label for="fecha_desde" class="col-lg-2 control-label text-right pr-2">Desde</label>
    <div class="col-lg-2">
        <input type="date" name="fecha_desde" id="fecha_desde" class="form-control" value="{{ $filtros['fecha_desde'] ?? '' }}">
    </div>
    <label for="fecha_hasta" class="col-lg-1 control-label text-right pr-2">Hasta</label>
    <div class="col-lg-2">
        <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control" value="{{ $filtros['fecha_hasta'] ?? '' }}">
    </div>
</div>
<div class="form-group row">
    @if ($mostrarEstado)
        <label for="estado" class="col-lg-2 control-label text-right pr-2">Estado</label>
        <div class="col-lg-2">
            <select name="estado" id="estado" class="form-control">
                <option value="">Todos</option>
                @foreach ($estados ?? [] as $cod => $meta)
                    <option value="{{ $cod }}" @selected(($filtros['estado'] ?? '') === $cod)>{{ $meta['label'] }}</option>
                @endforeach
            </select>
        </div>
    @endif
    @if ($mostrarTipo)
        <label for="tipo" class="col-lg-1 control-label text-right pr-2">Tipo</label>
        <div class="col-lg-2">
            <select name="tipo" id="tipo" class="form-control">
                <option value="">Todos</option>
                <option value="{{ IngresoProveedorVisitanteSupport::PROVEEDOR }}" @selected(($filtros['tipo'] ?? '') === IngresoProveedorVisitanteSupport::PROVEEDOR)>Proveedor</option>
                <option value="{{ IngresoProveedorVisitanteSupport::VISITANTE }}" @selected(($filtros['tipo'] ?? '') === IngresoProveedorVisitanteSupport::VISITANTE)>Visitante</option>
            </select>
        </div>
    @endif
</div>
<div class="form-group row">
    <label for="motivo_id" class="col-lg-2 control-label text-right pr-2">Motivo</label>
    <div class="col-lg-2">
        <select name="motivo_id" id="motivo_id" class="form-control">
            <option value="">Todos</option>
            @foreach ($motivos as $item)
                <option value="{{ $item->id }}" @selected((int) ($filtros['motivo_id'] ?? 0) === (int) $item->id)>{{ $item->nombre }}</option>
            @endforeach
        </select>
    </div>
    <label for="punto_id" class="col-lg-1 control-label text-right pr-2">Punto</label>
    <div class="col-lg-2">
        <select name="punto_id" id="punto_id" class="form-control">
            <option value="">Todos</option>
            @foreach ($puntos as $item)
                <option value="{{ $item->id }}" @selected((int) ($filtros['punto_id'] ?? 0) === (int) $item->id)>{{ $item->nombre }}</option>
            @endforeach
        </select>
    </div>
</div>
<div class="form-group row">
    <label for="sector_id" class="col-lg-2 control-label text-right pr-2">Sector</label>
    <div class="col-lg-2">
        <select name="sector_id" id="sector_id" class="form-control">
            <option value="">Todos</option>
            @foreach ($sectores as $item)
                <option value="{{ $item->id }}" @selected((int) ($filtros['sector_id'] ?? 0) === (int) $item->id)>{{ $item->nombre }}</option>
            @endforeach
        </select>
    </div>
    <label for="area_id" class="col-lg-1 control-label text-right pr-2">&Aacute;rea</label>
    <div class="col-lg-2">
        <select name="area_id" id="area_id" class="form-control">
            <option value="">Todas</option>
            @foreach ($areas as $item)
                <option value="{{ $item->id }}" @selected((int) ($filtros['area_id'] ?? 0) === (int) $item->id)>{{ $item->nombre }}</option>
            @endforeach
        </select>
    </div>
</div>
<div class="form-group row mb-0">
    <div class="col-lg-2"></div>
    <div class="col-lg-10">
        <input type="hidden" name="consultar" value="1">
        <button type="submit" class="btn btn-primary btn-sm">
            <i class="fa fa-search"></i> Consultar
        </button>
    </div>
</div>
