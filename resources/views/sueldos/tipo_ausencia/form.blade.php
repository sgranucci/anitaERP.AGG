<?php use App\Models\Sueldos\Tipo_Ausencia_Sueldos; ?>
<div class="row">
    <div class="col-lg-6">
        <div class="form-group row">
            <label for="codigo" class="col-lg-4 col-form-label">C&oacute;digo</label>
            <div class="col-lg-4">
                @if (isset($data))
                    <input type="text" id="codigo" class="form-control" value="{{ $data->codigo }}" readonly/>
                @else
                    <input type="number" name="codigo" id="codigo" class="form-control" min="1"
                           value="{{ old('codigo') }}"
                           placeholder="Autom&aacute;tico si se deja vac&iacute;o"/>
                @endif
            </div>
        </div>
        <div class="form-group row">
            <label for="nombre" class="col-lg-4 col-form-label requerido">Nombre</label>
            <div class="col-lg-8">
                <input type="text" name="nombre" id="nombre" class="form-control" maxlength="60" required
                       value="{{ old('nombre', $data->nombre ?? '') }}"/>
            </div>
        </div>
        <div class="form-group row">
            <label for="categoria" class="col-lg-4 col-form-label requerido">Categor&iacute;a</label>
            <div class="col-lg-8">
                <select name="categoria" id="categoria" class="form-control" required>
                    @foreach (Tipo_Ausencia_Sueldos::CATEGORIAS as $val => $label)
                        <option value="{{ $val }}" {{ old('categoria', $data->categoria ?? 'licencia') === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-group row">
            <label for="tipo_dias" class="col-lg-4 col-form-label requerido">C&oacute;mputo de d&iacute;as</label>
            <div class="col-lg-8">
                <select name="tipo_dias" id="tipo_dias" class="form-control" required>
                    @foreach (Tipo_Ausencia_Sueldos::TIPOS_DIA as $val => $label)
                        <option value="{{ $val }}" {{ old('tipo_dias', $data->tipo_dias ?? 'corridos') === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-group row">
            <label for="tope_dias_anio" class="col-lg-4 col-form-label">Tope d&iacute;as/a&ntilde;o</label>
            <div class="col-lg-4">
                <input type="number" name="tope_dias_anio" id="tope_dias_anio" class="form-control" min="0" max="9999"
                       value="{{ old('tope_dias_anio', $data->tope_dias_anio ?? '') }}"
                       placeholder="Sin tope"/>
                <small class="form-text text-muted">Vac&iacute;o = sin l&iacute;mite anual.</small>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="form-group row">
            <label for="concepto_id" class="col-lg-4 col-form-label">Concepto liquidaci&oacute;n</label>
            <div class="col-lg-8">
                <select name="concepto_id" id="concepto_id" class="form-control">
                    <option value="">— Ninguno —</option>
                    @foreach ($conceptos ?? [] as $concepto)
                        <option value="{{ $concepto->id }}" {{ (int) old('concepto_id', $data->concepto_id ?? 0) === (int) $concepto->id ? 'selected' : '' }}>
                            {{ $concepto->codigo }} - {{ $concepto->descripcion }}
                        </option>
                    @endforeach
                </select>
                <small class="form-text text-muted">Concepto que liquida esta ausencia (opcional).</small>
            </div>
        </div>
        <div class="form-group row">
            <label for="color" class="col-lg-4 col-form-label">Color</label>
            <div class="col-lg-3">
                <input type="color" name="color" id="color" class="form-control"
                       value="{{ old('color', $data->color ?? '#3c8dbc') }}"/>
                <small class="form-text text-muted">Para calendario / planning.</small>
            </div>
        </div>
        <div class="form-group row">
            <label for="orden" class="col-lg-4 col-form-label">Orden</label>
            <div class="col-lg-4">
                <input type="number" name="orden" id="orden" class="form-control" min="0"
                       value="{{ old('orden', $data->orden ?? 0) }}"/>
            </div>
        </div>
        <div class="form-group row">
            <div class="col-lg-4 col-form-label">Reglas</div>
            <div class="col-lg-8">
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" name="afecta_saldo_vacaciones" id="afecta_saldo_vacaciones" value="1"
                           {{ old('afecta_saldo_vacaciones', $data->afecta_saldo_vacaciones ?? false) ? 'checked' : '' }}>
                    <label class="custom-control-label" for="afecta_saldo_vacaciones">Descuenta saldo de vacaciones</label>
                </div>
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" name="goza_sueldo" id="goza_sueldo" value="1"
                           {{ old('goza_sueldo', $data->goza_sueldo ?? true) ? 'checked' : '' }}>
                    <label class="custom-control-label" for="goza_sueldo">Es paga (goza de sueldo)</label>
                </div>
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" name="computa_antiguedad" id="computa_antiguedad" value="1"
                           {{ old('computa_antiguedad', $data->computa_antiguedad ?? true) ? 'checked' : '' }}>
                    <label class="custom-control-label" for="computa_antiguedad">Computa antig&uuml;edad</label>
                </div>
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" name="requiere_certificado" id="requiere_certificado" value="1"
                           {{ old('requiere_certificado', $data->requiere_certificado ?? false) ? 'checked' : '' }}>
                    <label class="custom-control-label" for="requiere_certificado">Requiere certificado</label>
                </div>
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" name="activo" id="activo" value="1"
                           {{ old('activo', $data->activo ?? true) ? 'checked' : '' }}>
                    <label class="custom-control-label" for="activo">Activo</label>
                </div>
            </div>
        </div>
    </div>
</div>
