{{--
    Datos SENASA / Anita «Datos adicionales».
    Variables opcionales: $certificado_senasa, $tropa, $temperatura_ingreso,
    $destino_senasa, $camara, $nro_establecimiento, $inputNavClass
--}}
@php
    $certificadoSenasa = old('certificado_senasa', $certificado_senasa ?? '');
    $tropaVal = old('tropa', $tropa ?? '');
    $tempIngreso = old('temperatura_ingreso', $temperatura_ingreso ?? '');
    $destinoSenasa = old('destino_senasa', $destino_senasa ?? 'Consumo interno');
    $camaraVal = old('camara', $camara ?? '');
    $nroEstablecimiento = old('nro_establecimiento', $nro_establecimiento ?? '');
    $navClass = trim((string) ($inputNavClass ?? 'surmar-enc-nav'));
@endphp
<div class="card card-outline card-primary mb-3">
    <div class="card-header py-2">
        <strong>Datos SENASA</strong>
        <span class="text-muted small ml-1">— Anita «Datos adicionales» (certificado / tropa / temperatura / destino / cámara / establecimiento)</span>
    </div>
    <div class="card-body">
        <div class="form-group row">
            <label class="col-lg-4 control-label text-right pr-2">Nº certificado</label>
            <div class="col-lg-3">
                <input type="text" name="certificado_senasa" id="certificado_senasa" class="form-control {{ $navClass }}" maxlength="30"
                       value="{{ $certificadoSenasa }}"
                       title="Se usa como lote por defecto en cada ítem">
            </div>
        </div>
        <div class="form-group row">
            <label class="col-lg-4 control-label text-right pr-2">Nº de tropa</label>
            <div class="col-lg-2">
                <input type="number" name="tropa" id="tropa" class="form-control {{ $navClass }}" min="0" max="999999"
                       value="{{ $tropaVal }}">
            </div>
        </div>
        <div class="form-group row">
            <label class="col-lg-4 control-label text-right pr-2">Temperatura ingreso</label>
            <div class="col-lg-2">
                <input type="number" step="0.01" name="temperatura_ingreso" id="temperatura_ingreso" class="form-control {{ $navClass }}"
                       value="{{ $tempIngreso }}">
            </div>
        </div>
        <div class="form-group row">
            <label class="col-lg-4 control-label text-right pr-2">Destino</label>
            <div class="col-lg-4">
                <input type="text" name="destino_senasa" id="destino_senasa" class="form-control {{ $navClass }}" maxlength="60"
                       value="{{ $destinoSenasa }}">
            </div>
        </div>
        <div class="form-group row">
            <label class="col-lg-4 control-label text-right pr-2">Cámara de depósito</label>
            <div class="col-lg-4">
                <input type="text" name="camara" id="camara" class="form-control {{ $navClass }}" maxlength="60"
                       value="{{ $camaraVal }}">
            </div>
        </div>
        <div class="form-group row mb-0">
            <label class="col-lg-4 control-label text-right pr-2">Nº establecimiento</label>
            <div class="col-lg-2">
                <input type="number" name="nro_establecimiento" id="nro_establecimiento" class="form-control {{ $navClass }}" min="0" max="10000"
                       value="{{ $nroEstablecimiento }}">
            </div>
        </div>
    </div>
</div>
