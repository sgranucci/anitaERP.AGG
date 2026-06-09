<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
        <input type="text" name="nombre" id="nombre" class="form-control" value="{{ old('nombre', $data->nombre ?? '') }}" required maxlength="255"/>
    </div>
</div>
@include('includes.form-empresa-asignada', [
    'empresa_query' => $empresa_query,
    'empresa_id' => $data->empresa_id ?? null,
    'solo_lectura' => isset($data->id),
])
<div class="form-group row">
    <label for="estado" class="col-lg-3 col-form-label requerido">Estado</label>
    <div class="col-lg-4">
        <select id="estado" name="estado" class="form-control" required>
            <option value="">-- Elija estado --</option>
            @foreach($estado_enum as $estado)
                @if ($estado['valor'] == old('estado', $data->estado ?? \App\Models\Caja\Estacionamiento\ItemEstacionamiento::ESTADO_ACTIVO))
                    <option value="{{ $estado['valor'] }}" selected>{{ $estado['nombre'] }}</option>
                @else
                    <option value="{{ $estado['valor'] }}">{{ $estado['nombre'] }}</option>
                @endif
            @endforeach
        </select>
    </div>
</div>
