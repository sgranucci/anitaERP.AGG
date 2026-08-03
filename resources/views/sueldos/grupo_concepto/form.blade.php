@php
    $d = $data ?? null;
    $sel = old('conceptos', $seleccionados ?? []);
    if (! is_array($sel)) { $sel = []; }
    $sel = array_map('intval', $sel);
@endphp

@include('includes.form-empresa-asignada', [
    'empresa_query' => $empresa_query ?? collect(),
    'empresa_id' => old('empresa_id', $d->empresa_id ?? null),
    'label' => 'Empresa',
    'col_label' => 'col-lg-3 control-label text-right pr-2',
    'col_input' => 'col-lg-5',
    'required' => false,
    'permite_vacio' => true,
    'opcion_vacia' => '— Todas —',
])

<div class="form-group row">
    <label class="col-lg-3 control-label text-right pr-2 requerido">C&oacute;digo</label>
    <div class="col-lg-3">
        <input type="number" name="codigo" class="form-control" required min="1"
               value="{{ old('codigo', $d->codigo ?? '') }}">
        <small class="text-muted">Anita: grp_codigo / emp_grp1..3</small>
    </div>
</div>
<div class="form-group row">
    <label class="col-lg-3 control-label text-right pr-2">Descripci&oacute;n</label>
    <div class="col-lg-6">
        <input type="text" name="descripcion" class="form-control" maxlength="80"
               value="{{ old('descripcion', $d->descripcion ?? '') }}">
    </div>
    <div class="col-lg-2">
        <div class="custom-control custom-checkbox mt-2">
            <input type="checkbox" class="custom-control-input" name="activo" id="activo" value="1"
                   {{ old('activo', $d->activo ?? true) ? 'checked' : '' }}>
            <label class="custom-control-label" for="activo">Activo</label>
        </div>
    </div>
</div>
<div class="form-group row">
    <label class="col-lg-3 control-label text-right pr-2">Conceptos del grupo</label>
    <div class="col-lg-8">
        <select name="conceptos[]" class="form-control" multiple size="16">
            @foreach(($conceptos ?? []) as $c)
                <option value="{{ $c->id }}" {{ in_array((int) $c->id, $sel, true) ? 'selected' : '' }}>
                    {{ str_pad((string) $c->codigo, 4, '0', STR_PAD_LEFT) }} — {{ $c->descripcion }} ({{ $c->tipo }})
                </option>
            @endforeach
        </select>
        <small class="text-muted">Ctrl/Cmd + clic para seleccionar varios. Este set es la base del legajo (luego elegibilidad y +/- expl&iacute;citos).</small>
    </div>
</div>
