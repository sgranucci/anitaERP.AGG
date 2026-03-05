<div class="form1">
    <div class="row">
        <div class="col-sm-6">
            <div class="form-group row">
                <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
                <div class="col-lg-5">
                <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $servicioterrestre->nombre ?? '')}}" required/>
                </div>
            </div>
            <div class="form-group row">
                <label for="tiposervicioterrestre" class="col-lg-3 col-form-label requerido">Tipo de servicio</label>
                <select name="tiposervicioterrestre_id" id="tiposervicioterrestre_id" data-placeholder="Tipo de servicio terrestre" class="col-lg-5 form-control" data-fouc>
                    <option value="">-- Seleccionar Tipo de servicio --</option>
                    @foreach($tiposervicioterrestre_query as $key => $value)
                        @if( (int) $value->id == (int) old('tiposervicioterrestre_id', $servicioterrestre->tiposervicioterrestre_id ?? ''))
                            <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
                        @else
                            <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                        @endif
                    @endforeach
                </select>
            </div>
            <div class="form-group row">
                <label for="costoconiva" class="col-lg-3 col-form-label requerido">Costo con iva</label>
                <div class="col-lg-3">
                    <input type="number" name="costoconiva" id="costoconiva" class="form-control" value="{{old('costoconiva', $servicioterrestre->costoconiva ?? '')}}">
                </div>
            </div>
            <div class="form-group row">
                <label for="monedacosto" class="col-lg-3 col-form-label requerido">Moneda del costo</label>
                <select name="monedacosto_id" id="monedacosto_id" data-placeholder="Moneda del costo" class="col-lg-5 form-control" data-fouc>
                    <option value="">-- Seleccionar moneda --</option>
                    @foreach($moneda_query as $key => $value)
                        @if( (int) $value->id == (int) old('monedacosto_id', $servicioterrestre->monedacosto_id ?? ''))
                            <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
                        @else
                            <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                        @endif
                    @endforeach
                </select>
            </div>
            <div class="form-group row">
                <label for="modoexento" class="col-lg-3 col-form-label requerido">Modo exento</label>
                <select id="modoexento" name="modoexento" class="col-lg-4 form-control" required>
                    <option value="">-- Elija modo exento --</option>
                    @foreach($modoexento_enum as $modoexento)
                        @if ($modoexento['valor'] == old('modoexento',$servicioterrestre->modoexento??''))
                            <option value="{{ $modoexento['valor'] }}" selected>{{ $modoexento['nombre'] }}</option>    
                        @else
                            <option value="{{ $modoexento['valor'] }}">{{ $modoexento['nombre'] }}</option>
                        @endif
                    @endforeach
                </select>
            </div>
            <div class="form-group row">
                <label for="valorexento" class="col-lg-3 col-form-label requerido">Valor exento</label>
                <div class="col-lg-3">
                <input type="number" name="valorexento" id="valorexento" class="form-control" value="{{old('valorexento', $servicioterrestre->valorexento ?? '')}}">
                </div>
            </div>
        </div>
        <div class="col-sm-6">
            <div class="form-group row">
                <label for="porcentajeganancia" class="col-lg-3 col-form-label">Porcentaje ganancia</label>
                <div class="col-lg-3">
                <input type="number" name="porcentajeganancia" id="porcentajeganancia" class="form-control" value="{{old('porcentajeganancia', $servicioterrestre->porcentajeganancia ?? '')}}">
                </div>
            </div>
            <div class="form-group row">
                <label for="precioindividual" class="col-lg-3 col-form-label">Precio con IVA</label>
                <div class="col-lg-3">
                <input type="number" name="precioindividual" id="precioindividual" class="form-control" value="{{old('precioindividual', $servicioterrestre->precioindividual ?? '')}}">
                </div>
            </div>
            <div class="form-group row">
                <label for="moneda" class="col-lg-3 col-form-label">Moneda del precio</label>
                <select name="moneda_id" id="moneda_id" data-placeholder="Moneda del precio" class="col-lg-5 form-control" data-fouc>
                    <option value="">-- Seleccionar moneda --</option>
                    @foreach($moneda_query as $key => $value)
                        @if( (int) $value->id == (int) old('moneda_id', $servicioterrestre->moneda_id ?? ''))
                            <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
                        @else
                            <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                        @endif
                    @endforeach
                </select>
            </div>
            <div class="form-group row">
                <label for="impuesto" class="col-lg-3 col-form-label requerido">Tasa de iva</label>
                <select name="impuesto_id" id="impuesto_id" data-placeholder="Tasa de iva" class="col-lg-5 form-control" data-fouc>
                    <option value="">-- Seleccionar impuesto --</option>
                    @foreach($impuesto_query as $key => $value)
                        @if( (int) $value->id == (int) old('impuesto_id', $servicioterrestre->impuesto_id ?? ''))
                            <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
                        @else
                            <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                        @endif
                    @endforeach
                </select>
            </div>
            <div class="form-group row">
                <label for="abreviatura" class="col-lg-3 col-form-label requerido">Abreviatura</label>
                <div class="col-lg-2">
                <input type="text" name="abreviatura" id="abreviatura" class="form-control" value="{{old('abreviatura', $servicioterrestre->abreviatura ?? '')}}" required/>
                </div>
            </div>
            <div class="form-group row">
                <label for="ubicacion" class="col-lg-3 col-form-label requerido">Ubicación</label>
                <select id="ubicacion" name="ubicacion" class="col-lg-3 form-control" required>
                    <option value="">-- Elija ubicación --</option>
                    @foreach($ubicacion_enum as $ubicacion)
                        @if ($ubicacion['valor'] == old('ubicacion',$servicioterrestre->ubicacion??''))
                            <option value="{{ $ubicacion['valor'] }}" selected>{{ $ubicacion['nombre'] }}</option>    
                        @else
                            <option value="{{ $ubicacion['valor'] }}">{{ $ubicacion['nombre'] }}</option>
                        @endif
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="form-group row">
        <label for="observacion" class="col-lg-3 col-form-label requerido">Observaciones</label>
        <div class="col-lg-8">
            <input type="text" name="observacion" id="observacion" class="form-control" value="{{old('observacion', $servicioterrestre->observacion ?? '')}}"/>
        </div>
    </div>
    <div class="form-group row">
        <label for="prepago" class="col-lg-3 col-form-label requerido">Prepago</label>
        <select id="prepago" name="prepago" class="col-lg-3 form-control" required>
            <option value="">-- Elija prepago --</option>
            @foreach($prepago_enum as $prepago)
                @if ($prepago['valor'] == old('prepago',$servicioterrestre->prepago??''))
                    <option value="{{ $prepago['valor'] }}" selected>{{ $prepago['nombre'] }}</option>    
                @else
                    <option value="{{ $prepago['valor'] }}">{{ $prepago['nombre'] }}</option>
                @endif
            @endforeach
        </select>
    </div>
    <div class="form-group row">
        <label for="codigo" class="col-lg-3 col-form-label requerido">Código Anita</label>
        <div class="col-lg-1">
            <input type="text" name="codigo" id="codigo" class="form-control" value="{{old('codigo', $servicioterrestre->codigo ?? '')}}" readonly>
        </div>
    </div>
</div>