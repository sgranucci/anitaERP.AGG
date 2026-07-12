@php
    use App\Models\Caja\Bingo\BingoCarton;
    $lineasActual = (int) old('lineas', $data->lineas ?? 4);
    $esAzarActual = (bool) old('es_azar', $data->es_azar ?? false);
@endphp
<div class="form-group row">
    <label for="codigo" class="col-lg-3 col-form-label requerido">C&oacute;digo</label>
    <div class="col-lg-4">
        <input type="text" name="codigo" id="codigo" class="form-control" value="{{ old('codigo', $data->codigo ?? '') }}" required maxlength="20"/>
    </div>
</div>
<div class="form-group row">
    <label for="codigo_anita" class="col-lg-3 col-form-label">C&oacute;digo Anita</label>
    <div class="col-lg-4">
        <input type="number" name="codigo_anita" id="codigo_anita" class="form-control text-right" value="{{ old('codigo_anita', $data->codigo_anita ?? '') }}" min="0" step="1"/>
        <small class="form-text text-muted">C&oacute;digo entero de cart&oacute;n en Anita. Se graba en rendcarton.</small>
    </div>
</div>
<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
        <input type="text" name="nombre" id="nombre" class="form-control" value="{{ old('nombre', $data->nombre ?? '') }}" required maxlength="255"/>
    </div>
</div>
<div class="form-group row">
    <label for="precio_unitario" class="col-lg-3 col-form-label">Precio unitario</label>
    <div class="col-lg-4">
        <input type="number" name="precio_unitario" id="precio_unitario" class="form-control text-right" value="{{ old('precio_unitario', $data->precio_unitario ?? 0) }}" min="0" step="0.01"/>
    </div>
</div>
<div class="form-group row">
    <label for="lineas" class="col-lg-3 col-form-label requerido">L&iacute;neas</label>
    <div class="col-lg-4">
        <select id="lineas" name="lineas" class="form-control" required>
            @foreach ([3, 4, 5] as $opcionLineas)
                @if ((int) $opcionLineas === $lineasActual)
                    <option value="{{ $opcionLineas }}" selected>{{ $opcionLineas }}</option>
                @else
                    <option value="{{ $opcionLineas }}">{{ $opcionLineas }}</option>
                @endif
            @endforeach
        </select>
    </div>
</div>
<div class="form-group row">
    <label for="es_azar" class="col-lg-3 col-form-label">Azar</label>
    <div class="col-lg-8">
        <div class="form-check mt-2">
            <input type="hidden" name="es_azar" value="0">
            <input type="checkbox" name="es_azar" id="es_azar" class="form-check-input" value="1"
                @if ($esAzarActual)
                    checked
                @endif
            >
            <label class="form-check-label" for="es_azar">Cart&oacute;n de azar (n&uacute;meros aleatorios por venta)</label>
        </div>
    </div>
</div>
<div class="form-group row">
    <label for="orden" class="col-lg-3 col-form-label">Orden</label>
    <div class="col-lg-4">
        <input type="number" name="orden" id="orden" class="form-control" value="{{ old('orden', $data->orden ?? 0) }}" min="0" step="1"/>
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
                @if ($estado['valor'] == old('estado', $data->estado ?? BingoCarton::ESTADO_ACTIVO))
                    <option value="{{ $estado['valor'] }}" selected>{{ $estado['nombre'] }}</option>
                @else
                    <option value="{{ $estado['valor'] }}">{{ $estado['nombre'] }}</option>
                @endif
            @endforeach
        </select>
    </div>
</div>
<div class="form-group row">
    <label class="col-lg-3 col-form-label">Vista previa</label>
    <div class="col-lg-8">
        @include('caja.bingo.carton.partials.vista_previa_carton', [
            'data' => $data,
            'mini' => false,
        ])
    </div>
</div>
