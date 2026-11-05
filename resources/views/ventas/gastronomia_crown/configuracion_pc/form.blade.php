@php
    $lbl = 'col-lg-4 control-label text-right pr-2';
    $inp = 'col-lg-7';
@endphp
<div class="form-group row">
    <label for="identificador_pc" class="{{ $lbl }} requerido">Identificador de PC</label>
    <div class="{{ $inp }}">
        <input type="text" name="identificador_pc" id="identificador_pc" class="form-control"
            value="{{ old('identificador_pc', $data->exists ? $data->identificador_pc : \App\Support\Ventas\GastronomiaIdentificadorPc::resolver(request())) }}" required maxlength="100"/>
        <small class="form-text text-muted">IP, hostname o código de la terminal. El POS Crown resuelve el puesto desde esta fila.</small>
    </div>
</div>
<div class="form-group row">
    <label for="descripcion" class="{{ $lbl }}">Descripción</label>
    <div class="{{ $inp }}">
        <input type="text" name="descripcion" id="descripcion" class="form-control"
            value="{{ old('descripcion', $data->descripcion ?? '') }}" maxlength="255"/>
    </div>
</div>
@include('includes.form-empresa-asignada', [
    'empresa_query' => $empresa_query,
    'empresa_id' => $data->empresa_id ?? null,
    'solo_lectura' => ! empty($data->id),
    'col_label' => 'col-lg-4 text-right pr-2',
    'col_input' => $inp,
])
@include('ventas.configuracion_puntoventa_gastronomia.partials.form_modos_crown')
