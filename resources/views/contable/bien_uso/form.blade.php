<div class="form-group row">
    <label for="codigo_inventario" class="col-lg-3 col-form-label">Cód. inventario</label>
    <div class="col-lg-3">
        <input type="number" name="codigo_inventario" id="codigo_inventario" class="form-control" min="1"
               value="{{ old('codigo_inventario', $data->codigo_inventario ?? '') }}">
    </div>
</div>
<div class="form-group row">
    <label for="hostname" class="col-lg-3 col-form-label requerido">Hostname</label>
    <div class="col-lg-6">
        <input type="text" name="hostname" id="hostname" class="form-control" required
               value="{{ old('hostname', $data->hostname ?? '') }}">
    </div>
</div>
<div class="form-group row">
    <label for="ip" class="col-lg-3 col-form-label">IP</label>
    <div class="col-lg-4">
        <input type="text" name="ip" id="ip" class="form-control"
               value="{{ old('ip', $data->ip ?? '') }}">
    </div>
</div>
<div class="form-group row">
    <label for="modelo" class="col-lg-3 col-form-label">Modelo</label>
    <div class="col-lg-6">
        <input type="text" name="modelo" id="modelo" class="form-control"
               value="{{ old('modelo', $data->modelo ?? '') }}">
    </div>
</div>
<div class="form-group row">
    <label for="numero_serie" class="col-lg-3 col-form-label">Número de serie</label>
    <div class="col-lg-4">
        <input type="text" name="numero_serie" id="numero_serie" class="form-control"
               value="{{ old('numero_serie', $data->numero_serie ?? '') }}">
    </div>
</div>
<div class="form-group row">
    <label for="estado" class="col-lg-3 col-form-label requerido">Estado</label>
    <div class="col-lg-3">
        <select id="estado" name="estado" class="form-control" required>
            <option value="">-- Elija estado --</option>
            @foreach($estado_enum as $item)
                @if ($item['valor'] == old('estado', $data->estado ?? 'A'))
                    <option value="{{ $item['valor'] }}" selected>{{ $item['nombre'] }}</option>
                @else
                    <option value="{{ $item['valor'] }}">{{ $item['nombre'] }}</option>
                @endif
            @endforeach
        </select>
    </div>
</div>
<div class="form-group row">
    <label for="centrocosto_id" class="col-lg-3 col-form-label requerido">Centro de costo</label>
    <div class="col-lg-6">
        <select id="centrocosto_id" name="centrocosto_id" class="form-control" required>
            <option value="">-- Elija centro de costo --</option>
            @foreach($centrocosto_opciones as $cc)
                @if ((int) $cc->id === (int) old('centrocosto_id', $data->centrocosto_id ?? ($centrocosto_opciones->first()->id ?? 0)))
                    <option value="{{ $cc->id }}" selected>{{ $cc->codigo }} — {{ $cc->nombre }}</option>
                @else
                    <option value="{{ $cc->id }}">{{ $cc->codigo }} — {{ $cc->nombre }}</option>
                @endif
            @endforeach
        </select>
    </div>
</div>
<div class="form-group row">
    <label for="tipo_bien" class="col-lg-3 col-form-label requerido">Tipo de bien</label>
    <div class="col-lg-3">
        <select id="tipo_bien" name="tipo_bien" class="form-control" required>
            <option value="">-- Elija tipo --</option>
            @foreach($tipo_bien_enum as $item)
                @if ($item['valor'] == old('tipo_bien', $data->tipo_bien ?? ''))
                    <option value="{{ $item['valor'] }}" selected>{{ $item['nombre'] }}</option>
                @else
                    <option value="{{ $item['valor'] }}">{{ $item['nombre'] }}</option>
                @endif
            @endforeach
        </select>
    </div>
</div>
<div class="form-group row">
    <label for="observaciones" class="col-lg-3 col-form-label">Observaciones</label>
    <div class="col-lg-8">
        <textarea name="observaciones" id="observaciones" class="form-control" rows="3">{{ old('observaciones', $data->observaciones ?? '') }}</textarea>
    </div>
</div>
