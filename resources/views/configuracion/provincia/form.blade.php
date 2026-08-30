<div class="form-group row">
    <label for="nombre" class="col-lg-3 control-label text-right pr-2 requerido">Nombre</label>
    <div class="col-lg-8">
        <input type="text" name="nombre" id="nombre" class="form-control" value="{{ old('nombre', $data->nombre ?? '') }}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="abreviatura" class="col-lg-3 control-label text-right pr-2">Abreviatura</label>
    <div class="col-lg-2">
        <input type="text" name="abreviatura" id="abreviatura" class="form-control" value="{{ old('abreviatura', $data->abreviatura ?? '') }}">
    </div>
</div>
<div class="form-group row">
    <label for="jurisdiccion" class="col-lg-3 control-label text-right pr-2">Jurisdicción</label>
    <div class="col-lg-2">
        <input type="text" name="jurisdiccion" id="jurisdiccion" class="form-control" value="{{ old('jurisdiccion', $data->jurisdiccion ?? '') }}">
    </div>
</div>
<div class="form-group row">
    <label for="codigo" class="col-lg-3 control-label text-right pr-2">Código Anita</label>
    <div class="col-lg-2">
        <input type="text" name="codigo" id="codigo" class="form-control" value="{{ old('codigo', $data->codigo ?? '') }}">
    </div>
</div>
<div class="form-group row">
    <label for="pais_id" class="col-lg-3 control-label text-right pr-2 requerido">País</label>
    <div class="col-lg-4">
        <select name="pais_id" id="pais_id" class="form-control" required>
            <option value="">-- Elija país --</option>
            @foreach ($pais_query as $pais)
                <option value="{{ $pais->id }}"
                    @if (old('pais_id', $data->pais_id ?? '') == $pais->id)
                        selected
                    @endif
                    >{{ $pais->nombre }}
                </option>
            @endforeach
        </select>
    </div>
</div>
<div class="form-group row">
    <label for="codigoexterno" class="col-lg-3 control-label text-right pr-2">Código externo</label>
    <div class="col-lg-2">
        <input type="text" name="codigoexterno" id="codigoexterno" class="form-control" value="{{ old('codigoexterno', $data->codigoexterno ?? '') }}">
    </div>
</div>
<div class="form-group row">
    <label for="minimocoeficientecm05" class="col-lg-3 control-label text-right pr-2">Mínimo Coef. CM05</label>
    <div class="col-lg-2">
        <input type="text" name="minimocoeficientecm05" id="minimocoeficientecm05" class="form-control" value="{{ old('minimocoeficientecm05', $data->minimocoeficientecm05 ?? '') }}">
    </div>
</div>
<div class="form-group row">
    <label for="tope_alicuota_percepcion" class="col-lg-3 control-label text-right pr-2">Tope alícuota percepción</label>
    <div class="col-lg-2">
        <input type="text" name="tope_alicuota_percepcion" id="tope_alicuota_percepcion" class="form-control" value="{{ old('tope_alicuota_percepcion', $data->tope_alicuota_percepcion ?? '') }}" placeholder="vacío = sin tope">
    </div>
    <div class="col-lg-6 col-form-label text-muted small">
        Si tiene valor, la alícuota (padrón o descarte) no supera este tope. Córdoba Anita: 0,40.
    </div>
</div>
