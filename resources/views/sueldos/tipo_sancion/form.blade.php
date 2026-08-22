@php use App\Models\Sueldos\Tipo_Sancion_Sueldos; @endphp
<div class="form-group row">
    <label for="codigo" class="col-lg-4 control-label text-right pr-2">Código</label>
    <div class="col-lg-3">
        @if (isset($data))
            <input type="text" id="codigo" class="form-control" value="{{ $data->codigo }}" readonly/>
        @else
            <input type="number" name="codigo" id="codigo" class="form-control" min="1"
                   value="{{ old('codigo') }}"
                   placeholder="Automático si se deja vacío"/>
        @endif
    </div>
</div>
<div class="form-group row">
    <label for="nombre" class="col-lg-4 control-label text-right pr-2 requerido">Nombre</label>
    <div class="col-lg-6">
        <input type="text" name="nombre" id="nombre" class="form-control" maxlength="60" required
               value="{{ old('nombre', $data->nombre ?? '') }}"/>
    </div>
</div>
<div class="form-group row">
    <label for="clase" class="col-lg-4 control-label text-right pr-2 requerido">Clase</label>
    <div class="col-lg-6">
        <select name="clase" id="clase" class="form-control" required>
            @foreach (Tipo_Sancion_Sueldos::CLASES as $val => $label)
                <option value="{{ $val }}" {{ old('clase', $data->clase ?? 'otro') === $val ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </div>
</div>
<div class="form-group row">
    <label for="tipo_dias" class="col-lg-4 control-label text-right pr-2 requerido">Cómputo de días</label>
    <div class="col-lg-4">
        <select name="tipo_dias" id="tipo_dias" class="form-control" required>
            @foreach (Tipo_Sancion_Sueldos::TIPOS_DIA as $val => $label)
                <option value="{{ $val }}" {{ old('tipo_dias', $data->tipo_dias ?? 'corridos') === $val ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </div>
</div>
<div class="form-group row">
    <label for="tope_dias" class="col-lg-4 control-label text-right pr-2">Tope de días</label>
    <div class="col-lg-3">
        <input type="number" name="tope_dias" id="tope_dias" class="form-control" min="0" max="999"
               value="{{ old('tope_dias', $data->tope_dias ?? '') }}"/>
    </div>
</div>
<div class="form-group row">
    <label for="orden_progresivo" class="col-lg-4 control-label text-right pr-2">Orden progresivo</label>
    <div class="col-lg-3">
        <input type="number" name="orden_progresivo" id="orden_progresivo" class="form-control" min="1" max="99"
               value="{{ old('orden_progresivo', $data->orden_progresivo ?? 1) }}"/>
    </div>
</div>
<div class="form-group row">
    <label for="plazo_descargo_dias" class="col-lg-4 control-label text-right pr-2">Plazo descargo (días)</label>
    <div class="col-lg-3">
        <input type="number" name="plazo_descargo_dias" id="plazo_descargo_dias" class="form-control" min="0" max="30"
               value="{{ old('plazo_descargo_dias', $data->plazo_descargo_dias ?? 2) }}"/>
    </div>
</div>
@php
    $conceptoSelId = old('concepto_id', $data->concepto_id ?? '');
    $conceptoModel = $data->concepto ?? null;
@endphp
@include('sueldos.partials.campo_consulta_concepto_sueldos', [
    'layout' => 'form_row',
    'label' => 'Concepto liquidación',
    'inputName' => 'concepto_id',
    'inputId' => 'concepto_sueldos_id',
    'conceptoId' => $conceptoSelId,
    'codigo' => $conceptoModel->codigo ?? '',
    'descripcion' => $conceptoModel->descripcion ?? '',
    'required' => false,
    'col_label' => 'col-lg-4 control-label text-right pr-2',
    'col_input' => 'col-lg-8',
])
<div class="form-group row">
    <label class="col-lg-4 control-label text-right pr-2">Opciones</label>
    <div class="col-lg-8">
        <div class="form-check">
            <input type="hidden" name="requiere_dias" value="0">
            <input type="checkbox" name="requiere_dias" id="requiere_dias" value="1" class="form-check-input"
                   {{ old('requiere_dias', $data->requiere_dias ?? false) ? 'checked' : '' }}>
            <label class="form-check-label" for="requiere_dias">Requiere días (suspensión)</label>
            <span class="text-muted small ml-2">— hay que cargar días o período; no en una notificación</span>
        </div>
        <div class="form-check">
            <input type="hidden" name="goza_sueldo" value="0">
            <input type="checkbox" name="goza_sueldo" id="goza_sueldo" value="1" class="form-check-input"
                   {{ old('goza_sueldo', $data->goza_sueldo ?? false) ? 'checked' : '' }}>
            <label class="form-check-label" for="goza_sueldo">Goza sueldo</label>
            <span class="text-muted small ml-2">— cobra esos días; suspensión sin goce va destildada</span>
        </div>
        <div class="form-check">
            <input type="hidden" name="genera_novedad" value="0">
            <input type="checkbox" name="genera_novedad" id="genera_novedad" value="1" class="form-check-input"
                   {{ old('genera_novedad', $data->genera_novedad ?? false) ? 'checked' : '' }}>
            <label class="form-check-label" for="genera_novedad">Genera novedad de liquidación</label>
            <span class="text-muted small ml-2">— impacta el recibo; hace falta el concepto de arriba</span>
        </div>
        <div class="form-check">
            <input type="hidden" name="activo" value="0">
            <input type="checkbox" name="activo" id="activo" value="1" class="form-check-input"
                   {{ old('activo', $data->activo ?? true) ? 'checked' : '' }}>
            <label class="form-check-label" for="activo">Activo</label>
            <span class="text-muted small ml-2">— destildar oculta el tipo en cargas nuevas</span>
        </div>
    </div>
</div>
<div class="form-group row">
    <label for="plantilla_notificacion" class="col-lg-4 control-label text-right pr-2">Plantilla de notificación</label>
    <div class="col-lg-8">
        <textarea name="plantilla_notificacion" id="plantilla_notificacion" class="form-control" rows="4"
                  maxlength="4000">{{ old('plantilla_notificacion', $data->plantilla_notificacion ?? '') }}</textarea>
    </div>
</div>
