<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
        <input type="text" name="nombre" id="nombre" class="form-control" value="{{ old('nombre', $data->nombre ?? '') }}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="ubicacion_id" class="col-lg-3 col-form-label">Ubicación</label>
    <div class="col-lg-8">
        <select name="ubicacion_id" id="ubicacion_id" class="form-control">
            <option value="">Sin ubicación</option>
            @foreach ($ubicacion_query as $ubicacion)
                <option value="{{ $ubicacion->id }}" {{ (int) old('ubicacion_id', $data->ubicacion_id ?? 0) === (int) $ubicacion->id ? 'selected' : '' }}>
                    {{ $ubicacion->nombre }}
                </option>
            @endforeach
        </select>
    </div>
</div>
<div class="form-group row">
    <label for="numeromesa" class="col-lg-3 col-form-label requerido">Número de mesa</label>
    <div class="col-lg-8">
        <input type="text" name="numeromesa" id="numeromesa" class="form-control" value="{{ old('numeromesa', $data->numeromesa ?? '') }}" required maxlength="50"/>
    </div>
</div>
<div class="form-group row">
    <label for="codigo" class="col-lg-3 col-form-label">Código Anita</label>
    <div class="col-lg-8">
        <input type="text" name="codigo" id="codigo" class="form-control" value="{{ old('codigo', $data->codigo ?? '') }}" maxlength="50"/>
        <small class="form-text text-muted">Corresponde a mes_codigo en Anita (clave de sincronización).</small>
    </div>
</div>
@include('includes.form-empresa-asignada', [
    'empresa_query' => $empresa_query,
    'empresa_id' => $data->empresa_id ?? null,
    'col_input' => 'col-lg-8',
])
