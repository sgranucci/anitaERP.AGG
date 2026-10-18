@php
    $rubro = optional($data->rubro ?? null);
@endphp
@include('contable.partials.campo_consulta_bien_uso_rubro', [
    'prefix' => 'subrubro',
    'label' => 'Rubro',
    'required' => true,
    'rubroId' => old('bien_uso_rubro_id', $data->bien_uso_rubro_id ?? ''),
    'codigo' => old('rubro_codigo', $rubro->codigo ?? ''),
    'nombre' => old('rubro_nombre', $rubro->nombre ?? ''),
    'col_label' => 'col-lg-4 control-label text-right pr-2',
    'col_input' => 'col-lg-7',
])
<div class="form-group row">
    <label for="codigo" class="col-lg-4 control-label text-right pr-2 requerido">C&oacute;digo</label>
    <div class="col-lg-3">
        <input type="number" name="codigo" id="codigo" class="form-control" min="1" required
               value="{{ old('codigo', $data->codigo ?? '') }}">
    </div>
</div>
<div class="form-group row">
    <label for="nombre" class="col-lg-4 control-label text-right pr-2 requerido">Nombre</label>
    <div class="col-lg-7">
        <input type="text" name="nombre" id="nombre" class="form-control" maxlength="80" required
               value="{{ old('nombre', $data->nombre ?? '') }}">
    </div>
</div>
