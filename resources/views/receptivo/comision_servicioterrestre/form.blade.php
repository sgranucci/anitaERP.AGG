<div class="form1">
    <div class="form-group row">
        <label for="servicioterrestre" class="col-lg-3 col-form-label requerido">Servicio terrestre</label>
        <select name="servicioterrestre_id" id="servicioterrestre_id" data-placeholder="Servicio terrestre" class="col-lg-5 form-control" data-fouc>
            <option value="">-- Seleccionar Servicio --</option>
            @foreach($servicioterrestre_query as $key => $value)
                @if( (int) $value->id == (int) old('servicioterrestre_id', $comision_servicioterrestre->servicioterrestre_id ?? ''))
                    <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
                @else
                    <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                @endif
            @endforeach
        </select>
    </div>
    <div class="form-group row">
        <label for="formapago" class="col-lg-3 col-form-label requerido">Forma de pago</label>
        <select name="formapago_id" id="formapago_id" data-placeholder="Moneda del costo" class="col-lg-5 form-control" data-fouc>
            <option value="">-- Seleccionar formapago --</option>
            @foreach($formapago_query as $key => $value)
                @if( (int) $value->id == (int) old('formapago_id', $comision_servicioterrestre->formapago_id ?? ''))
                    <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
                @else
                    <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                @endif
            @endforeach
        </select>
    </div>
    <div class="form-group row">
        <label for="tipocomision" class="col-lg-3 col-form-label requerido">Tipo comision</label>
        <select id="tipocomision" name="tipocomision" class="col-lg-4 form-control" required>
            <option value="">-- Elija tipo de comisión --</option>
            @foreach($tipocomision_enum as $tipocomision)
                @if ($tipocomision['valor'] == old('tipocomision',$comision_servicioterrestre->tipocomision??''))
                    <option value="{{ $tipocomision['valor'] }}" selected>{{ $tipocomision['nombre'] }}</option>    
                @else
                    <option value="{{ $tipocomision['valor'] }}">{{ $tipocomision['nombre'] }}</option>
                @endif
            @endforeach
        </select>
    </div>
    <div class="form-group row">
        <label for="porcentajecomision" class="col-lg-3 col-form-label">Porcentaje comisión</label>
        <div class="col-lg-3">
        <input type="number" name="porcentajecomision" id="porcentajecomision" class="form-control" value="{{old('porcentajecomision', $comision_servicioterrestre->porcentajecomision ?? '')}}">
        </div>
    </div>
</div>