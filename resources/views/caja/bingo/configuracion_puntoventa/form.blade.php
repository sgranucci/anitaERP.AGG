<div class="form-group row">
    <label for="identificador_pc" class="col-lg-3 col-form-label requerido">Identificador de PC</label>
    <div class="col-lg-8">
        <input type="text" name="identificador_pc" id="identificador_pc" class="form-control"
            value="{{ old('identificador_pc', $data->identificador_pc ?? \App\Support\Caja\Bingo\BingoIdentificadorPc::sugerirEnFormularioAlta(request())) }}" required maxlength="100"/>
        <small class="form-text text-muted">IP, hostname o código único de la terminal. Al crear desde el navegador de la caja se sugiere la IP detectada por el servidor. En operación, con <code>BINGO_IDENTIFICADOR_USAR_IP_CLIENTE=true</code>, debe coincidir con esa IP.</small>
    </div>
</div>
<div class="form-group row">
    <label for="descripcion" class="col-lg-3 col-form-label">Descripción</label>
    <div class="col-lg-8">
        <input type="text" name="descripcion" id="descripcion" class="form-control"
            value="{{ old('descripcion', $data->descripcion ?? '') }}" maxlength="255"/>
        <small class="form-text text-muted">Opcional: nombre amigable (ej. Caja bingo, Terminal sala).</small>
    </div>
</div>
@include('includes.form-empresa-asignada', [
    'empresa_query' => $empresa_query,
    'empresa_id' => $data->empresa_id ?? null,
    'solo_lectura' => ! empty($data->id),
    'col_input' => 'col-lg-8',
])
@php
    $cuentacajaIdVal = old('cuentacaja_id', $data->cuentacaja_id ?? '');
    $cuentacajaModel = null;
    if ((int) $cuentacajaIdVal > 0) {
        $cuentacajaModel = $data->cuentacaja ?? \App\Models\Caja\Cuentacaja::find($cuentacajaIdVal);
    }
@endphp
@include('caja.partials.campo_consulta_cuentacaja', [
    'prefix' => 'bingo_cfg',
    'layout' => 'form_row',
    'cuentacajaId' => $cuentacajaIdVal,
    'codigo' => $cuentacajaModel->codigo ?? '',
    'nombre' => $cuentacajaModel->nombre ?? '',
    'col_label' => 'col-lg-3',
    'col_input' => 'col-lg-8',
    'ayuda' => 'Cuenta Anita donde se presentará la rendición. Se filtra por la empresa asignada al formulario.',
])
