@php
    $esAlta = empty($data->id);
    $codigoValor = old('codigo', $esAlta ? ($codigoSugerido ?? '') : ($data->codigo ?? ''));
    $fechaUsoRaw = old('fechauso', $data->fechauso ?? '');
    $fechaUsoInput = '';
    if ($fechaUsoRaw !== null && $fechaUsoRaw !== '') {
        $fechaParseada = \DateTime::createFromFormat('Y-m-d', substr((string) $fechaUsoRaw, 0, 10))
            ?: \DateTime::createFromFormat('d/m/Y', (string) $fechaUsoRaw)
            ?: \DateTime::createFromFormat('d-m-Y', (string) $fechaUsoRaw);
        if ($fechaParseada instanceof \DateTime && (int) $fechaParseada->format('Y') > 1971) {
            $fechaUsoInput = $fechaParseada->format('Y-m-d');
        }
    }
    $cuentacajaIdVal = old('cuentacaja_id', $data->cuentacaja_id ?? '');
    $cuentacajaModel = null;
    if ((int) $cuentacajaIdVal > 0) {
        if (isset($data) && (int) ($data->cuentacaja_id ?? 0) === (int) $cuentacajaIdVal) {
            $cuentacajaModel = $data->cuentacajas;
        }
        if ($cuentacajaModel === null) {
            $cuentacajaModel = \App\Models\Caja\Cuentacaja::query()->find($cuentacajaIdVal);
        }
    }
@endphp

<div class="form-group row">
    <label for="tipochequera" class="col-lg-3 col-form-label text-right pr-2 requerido">Tipo de chequera</label>
    <div class="col-lg-4">
        <select id="tipochequera" name="tipochequera" class="form-control" required>
            <option value="">-- Elija tipo de chequera --</option>
            @foreach ($tipochequera_enum as $tipochequera)
                <option value="{{ $tipochequera['valor'] }}" @selected((string) $tipochequera['valor'] === (string) old('tipochequera', $data->tipochequera ?? ''))>
                    {{ $tipochequera['nombre'] }}
                </option>
            @endforeach
        </select>
    </div>
</div>
<div class="form-group row">
    <label for="tipocheque" class="col-lg-3 col-form-label text-right pr-2 requerido">Tipo de cheque</label>
    <div class="col-lg-4">
        <select id="tipocheque" name="tipocheque" class="form-control" required>
            <option value="">-- Elija tipo de cheque --</option>
            @foreach ($tipocheque_enum as $tipocheque)
                <option value="{{ $tipocheque['valor'] }}" @selected((string) $tipocheque['valor'] === (string) old('tipocheque', $data->tipocheque ?? ''))>
                    {{ $tipocheque['nombre'] }}
                </option>
            @endforeach
        </select>
    </div>
</div>
<div class="form-group row">
    <label for="codigo" class="col-lg-3 col-form-label text-right pr-2">Código chequera</label>
    <div class="col-lg-3">
        <input type="text" name="codigo" id="codigo" class="form-control" value="{{ $codigoValor }}" readonly>
        <small class="form-text text-muted">Lo asigna el sistema. Si ese número ya existe en Anita, al guardar se usa el siguiente libre.</small>
    </div>
</div>
@include('caja.partials.campo_consulta_cuentacaja', [
    'prefix' => 'chequera',
    'layout' => 'form_row',
    'label' => 'Cuenta de caja',
    'cuentacajaId' => $cuentacajaIdVal,
    'codigo' => $cuentacajaModel->codigo ?? '',
    'nombre' => $cuentacajaModel->nombre ?? '',
    'required' => true,
    'col_label' => 'col-lg-3 text-right pr-2',
    'col_input' => 'col-lg-8',
    'ayuda' => 'Código, Enter o F1. La lupa abre el listado.',
])
<div class="form-group row">
    <label for="desdenumerocheque" class="col-lg-3 col-form-label text-right pr-2 requerido">Desde nro. de cheque</label>
    <div class="col-lg-3">
        <input type="text" name="desdenumerocheque" id="desdenumerocheque" class="form-control chequera-nro" inputmode="numeric" autocomplete="off" value="{{ old('desdenumerocheque', $data->desdenumerocheque ?? '') }}" required>
    </div>
</div>
<div class="form-group row">
    <label for="hastanumerocheque" class="col-lg-3 col-form-label text-right pr-2 requerido">Hasta nro. de cheque</label>
    <div class="col-lg-3">
        <input type="text" name="hastanumerocheque" id="hastanumerocheque" class="form-control chequera-nro" inputmode="numeric" autocomplete="off" value="{{ old('hastanumerocheque', $data->hastanumerocheque ?? '') }}" required>
        <small class="form-text text-muted" id="chequera-cantidad"></small>
    </div>
</div>
<div class="form-group row">
    <label for="fechauso" class="col-lg-3 col-form-label text-right pr-2">Fecha de uso</label>
    <div class="col-lg-3">
        <input type="date" name="fechauso" id="fechauso" class="form-control" value="{{ $fechaUsoInput }}">
        <small class="form-text text-muted">Opcional. Si la chequera todavía no se usó, dejala vacía.</small>
    </div>
</div>
<div class="form-group row">
    <label for="estado" class="col-lg-3 col-form-label text-right pr-2 requerido">Estado</label>
    <div class="col-lg-4">
        <select id="estado" name="estado" class="form-control" required>
            <option value="">-- Elija estado --</option>
            @foreach ($estado_enum as $estado)
                <option value="{{ $estado['valor'] }}" @selected((string) $estado['valor'] === (string) old('estado', $data->estado ?? 'A'))>
                    {{ $estado['nombre'] }}
                </option>
            @endforeach
        </select>
    </div>
</div>
