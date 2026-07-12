@php
    use App\Models\Caja\Bingo\BingoConceptoRendicion;
@endphp
<div class="form-group row">
    <label for="codigo" class="col-lg-3 col-form-label">C&oacute;digo</label>
    <div class="col-lg-4">
        <input type="text" name="codigo" id="codigo" class="form-control" value="{{ old('codigo', $data->codigo ?? '') }}" maxlength="20"/>
    </div>
</div>
<div class="form-group row">
    <label for="codigo_anita" class="col-lg-3 col-form-label">C&oacute;digo Anita</label>
    <div class="col-lg-4">
        <input type="number" name="codigo_anita" id="codigo_anita" class="form-control text-right" value="{{ old('codigo_anita', $data->codigo_anita ?? '') }}" min="0" step="1"/>
        <small class="form-text text-muted">Concepto entero de Anita (concbingo). Se graba en rendpremio.</small>
    </div>
</div>
<div class="form-group row">
    <label for="signo" class="col-lg-3 col-form-label requerido">Signo</label>
    <div class="col-lg-4">
        <select id="signo" name="signo" class="form-control" required>
            @foreach($signo_enum as $signo)
                @if ($signo['valor'] == old('signo', $data->signo ?? BingoConceptoRendicion::SIGNO_RESTA))
                    <option value="{{ $signo['valor'] }}" selected>{{ $signo['nombre'] }}</option>
                @else
                    <option value="{{ $signo['valor'] }}">{{ $signo['nombre'] }}</option>
                @endif
            @endforeach
        </select>
    </div>
</div>
<div class="form-group row">
    <label for="detalle" class="col-lg-3 col-form-label requerido">Detalle</label>
    <div class="col-lg-8">
        <input type="text" name="detalle" id="detalle" class="form-control" value="{{ old('detalle', $data->detalle ?? '') }}" required maxlength="255"/>
    </div>
</div>
<div class="form-group row">
    <label for="porcentaje" class="col-lg-3 col-form-label">Porcentaje</label>
    <div class="col-lg-4">
        <div class="input-group">
            <input type="number" name="porcentaje" id="porcentaje" class="form-control text-right" value="{{ old('porcentaje', $data->porcentaje ?? '') }}" min="0" max="100" step="0.0001"/>
            <div class="input-group-append">
                <span class="input-group-text">%</span>
            </div>
        </div>
    </div>
</div>
<div class="form-group row">
    <label for="base_calculo" class="col-lg-3 col-form-label requerido">Base de c&aacute;lculo</label>
    <div class="col-lg-8">
        <select id="base_calculo" name="base_calculo" class="form-control" required>
            @foreach($base_calculo_enum as $base)
                @if ($base['valor'] == old('base_calculo', $data->base_calculo ?? BingoConceptoRendicion::BASE_TOTAL_CARTONES))
                    <option value="{{ $base['valor'] }}" selected>{{ $base['nombre'] }}</option>
                @else
                    <option value="{{ $base['valor'] }}">{{ $base['nombre'] }}</option>
                @endif
            @endforeach
        </select>
    </div>
</div>
<div class="form-group row">
    <label for="monto_fijo" class="col-lg-3 col-form-label">Monto fijo</label>
    <div class="col-lg-4">
        <input type="number" name="monto_fijo" id="monto_fijo" class="form-control text-right" value="{{ old('monto_fijo', $data->monto_fijo ?? '') }}" min="0" step="0.01"/>
    </div>
</div>
<div class="form-group row">
    <label for="orden" class="col-lg-3 col-form-label">Orden</label>
    <div class="col-lg-4">
        <input type="number" name="orden" id="orden" class="form-control" value="{{ old('orden', $data->orden ?? 0) }}" min="0" step="1"/>
    </div>
</div>
<div class="form-group row">
    <label for="es_saldo_rendicion" class="col-lg-3 col-form-label">Saldo rendici&oacute;n</label>
    <div class="col-lg-8">
        <div class="form-check mt-2">
            <input type="hidden" name="es_saldo_rendicion" value="0">
            <input type="checkbox" class="form-check-input" id="es_saldo_rendicion" name="es_saldo_rendicion" value="1"
                   {{ old('es_saldo_rendicion', $data->es_saldo_rendicion ?? false) ? 'checked' : '' }}>
            <label class="form-check-label" for="es_saldo_rendicion">
                Es el total final de la rendici&oacute;n (dep&oacute;sito). Solo uno por empresa; se calcula autom&aacute;ticamente.
            </label>
        </div>
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
                @if ($estado['valor'] == old('estado', $data->estado ?? BingoConceptoRendicion::ESTADO_ACTIVO))
                    <option value="{{ $estado['valor'] }}" selected>{{ $estado['nombre'] }}</option>
                @else
                    <option value="{{ $estado['valor'] }}">{{ $estado['nombre'] }}</option>
                @endif
            @endforeach
        </select>
    </div>
</div>
