@php
    $f = $filtros ?? [];
    $tipoActivo = (int) ($f['tipo_deposito_id'] ?? 0);
@endphp
<div class="collapse border-bottom" id="panel-filtros-tesoreria-crown-deposito" data-listado-filtros-panel>
    <input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida_deposito" value="">
    <input type="hidden" name="sala_id" value="{{ (int) ($sala?->id ?? 0) }}">
    <div class="card-body bg-light py-2">
        <div class="form-row align-items-end">
            @include('includes.listado.filtro_empresa_asignada', ['f' => $f])
            <div class="form-group col-md-4 mb-2">
                <label class="small mb-1" for="filtro_valor_panel">Texto</label>
                <input type="text" id="filtro_valor_panel" class="form-control form-control-sm" value="{{ $f['valor'] ?? '' }}" autocomplete="off">
            </div>
        </div>
        <div class="mb-2">
            <label class="small mb-1 d-block">Tipo</label>
            <label class="mr-3 mb-0">
                <input type="radio" name="tipo_deposito_id" value="0" @checked($tipoActivo === 0)> Todos
            </label>
            @foreach ($tipos as $tipo)
                <label class="mr-3 mb-0">
                    <input type="radio" name="tipo_deposito_id" value="{{ $tipo->id }}" @checked($tipoActivo === (int) $tipo->id)>
                    {{ $tipo->etiqueta() }}
                </label>
            @endforeach
        </div>
        <button type="submit" class="btn btn-sm btn-info" data-aplicar-filtros-panel>Aplicar filtros</button>
    </div>
</div>
