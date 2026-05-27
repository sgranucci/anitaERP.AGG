<div class="form-group row">
    <label for="fecha" class="col-lg-3 col-form-label requerido">Fecha</label>
    <div class="col-lg-3">
        <input type="date" name="fecha" id="fecha" class="form-control" value="{{ old('fecha', $data->fecha ?? date('Y-m-d')) }}" required>
    </div>
</div>
@include('includes.form-empresa-asignada', [
    'empresa_query' => $empresa_query,
    'empresa_id' => $data->empresa_id ?? null,
    'solo_lectura' => isset($data) && $data->id,
    'col_input' => 'col-lg-8',
])
<div class="form-group row">
    <label for="caja_id" class="col-lg-3 col-form-label requerido">Caja</label>
    <div class="col-lg-8">
        <select name="caja_id" id="caja_id" data-placeholder="Caja" class="form-control" data-fouc required>
            <option value="">— Seleccionar caja —</option>
            @foreach ($caja_query as $value)
                <option value="{{ $value->id }}" @selected((int) old('caja_id', $data->caja_id ?? 0) === (int) $value->id)>
                    {{ $value->id }} {{ $value->nombre }}
                </option>
            @endforeach
        </select>
    </div>
</div>
<div class="form-group row">
    <label for="usuario_id" class="col-lg-3 col-form-label requerido">Usuario</label>
    <div class="col-lg-8">
        <select name="usuario_id" id="usuario_id" data-placeholder="Usuario" class="form-control" data-fouc required>
            <option value="">— Seleccionar usuario —</option>
            @foreach ($usuario_query as $value)
                <option value="{{ $value->id }}" @selected((int) old('usuario_id', $data->usuario_id ?? 0) === (int) $value->id)>
                    {{ $value->id }} {{ $value->nombre }}
                </option>
            @endforeach
        </select>
    </div>
</div>
