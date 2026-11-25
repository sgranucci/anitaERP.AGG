@php
    $empresaSel = old('empresa_id', $data->empresa_id ?? '');
    $sindicatoSel = old('sindicato_id', $data->sindicato_id ?? '');
    $motivoSelId = (int) old('motivoegreso_id', $data->motivoegreso_id ?? 0);
    $motivoSel = $motivoSelId > 0
        ? (isset($data) && optional($data->motivoegreso)->id === $motivoSelId
            ? $data->motivoegreso
            : \App\Models\Sueldos\Motivoegreso_Sueldos::query()->find($motivoSelId))
        : null;
    $grupoSel = old('grupo_concepto_id', $data->grupo_concepto_id ?? '');
    $activoSel = old('activo', isset($data) ? ($data->activo ? '1' : '0') : '1');
@endphp

<div class="form-group row">
    <label for="empresa_id" class="col-lg-3 col-form-label requerido">Empresa</label>
    <div class="col-lg-6">
        <select name="empresa_id" id="empresa_id" class="form-control" required>
            <option value="">— Seleccionar —</option>
            @foreach ($empresas as $e)
                <option value="{{ $e->id }}" {{ (string)$empresaSel === (string)$e->id ? 'selected' : '' }}>{{ $e->nombre }}</option>
            @endforeach
        </select>
    </div>
</div>

<div class="form-group row">
    <label for="sindicato_id" class="col-lg-3 col-form-label requerido">Sindicato</label>
    <div class="col-lg-6">
        <select name="sindicato_id" id="sindicato_id" class="form-control" required>
            <option value="">— Seleccionar —</option>
            @foreach ($sindicatos as $s)
                <option value="{{ $s->id }}" {{ (string)$sindicatoSel === (string)$s->id ? 'selected' : '' }}>
                    {{ $s->codigo }} — {{ $s->descripcion }}
                </option>
            @endforeach
        </select>
    </div>
</div>

@include('sueldos.partials.campo_consulta_maestro_sueldos', [
    'maestro' => 'motivoegreso',
    'registroId' => $motivoSel->id ?? '',
    'codigo' => $motivoSel->codigo ?? '',
    'descripcion' => $motivoSel->descripcion ?? '',
    'label' => 'Motivo de egreso',
    'inputName' => 'motivoegreso_id',
    'inputId' => 'motivoegreso_id',
    'col_label' => 'col-lg-3',
    'col_input' => 'col-lg-6',
])
<div class="form-group row">
    <div class="col-lg-3"></div>
    <div class="col-lg-6">
        <small class="form-text text-muted mt-n2">
            Vac&iacute;o = cualquier motivo (fallback del sindicato). Si el legajo tiene un motivo con regla propia, usa esa.
        </small>
    </div>
</div>

<div class="form-group row">
    <label for="grupo_concepto_id" class="col-lg-3 col-form-label requerido">Grupo de conceptos</label>
    <div class="col-lg-6">
        <select name="grupo_concepto_id" id="grupo_concepto_id" class="form-control" required>
            <option value="">— Seleccionar —</option>
            @foreach ($grupos as $g)
                <option value="{{ $g->id }}"
                        data-empresa="{{ $g->empresa_id }}"
                        {{ (string)$grupoSel === (string)$g->id ? 'selected' : '' }}>
                    [Emp {{ $g->empresa_id }}] {{ $g->codigo }} — {{ $g->descripcion }}
                </option>
            @endforeach
        </select>
    </div>
</div>

<div class="form-group row">
    <label for="activo" class="col-lg-3 col-form-label">Activo</label>
    <div class="col-lg-6">
        <select name="activo" id="activo" class="form-control">
            <option value="1" {{ (string)$activoSel === '1' ? 'selected' : '' }}>Sí</option>
            <option value="0" {{ (string)$activoSel === '0' ? 'selected' : '' }}>No</option>
        </select>
    </div>
</div>
