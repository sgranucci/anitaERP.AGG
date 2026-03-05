<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-4">
       <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="abreviatura" class="col-lg-3 col-form-label requerido">Abreviatura</label>
    <div class="col-lg-2">
       <input type="text" name="abreviatura" id="abreviatura" class="form-control" value="{{old('abreviatura', $data->abreviatura ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="operacion" class="col-lg-3 col-form-label requerido">Operaci&oacute;n</label>
    <select name="operacion" class="col-lg-3 form-control" required>
        <option value="">-- Elija operaci&oacute;n --</option>
        @foreach($operacionEnum as $value => $operacion)
            @if( $value == old('operacion', $data->operacion ?? ''))
                <option value="{{ $value }}" selected="select">{{ $operacion }}</option>    
            @else
                <option value="{{ $value }}">{{ $operacion }}</option>    
            @endif
        @endforeach
    </select>
</div>
<div class="form-group row">
    <label for="signo" class="col-lg-3 col-form-label requerido">Signo</label>
    <select name="signo" class="col-lg-3 form-control" required>
        <option value="">-- Elija signo --</option>
        @foreach($signoEnum as $value => $signo)
            @if( $value == old('signo', $data->signo ?? ''))
                <option value="{{ $value }}" selected="select">{{ $signo }}</option>    
            @else
                <option value="{{ $value }}">{{ $signo }}</option>    
            @endif
        @endforeach
    </select>
</div>
<div class="form-group row">
    <label for="estado" class="col-lg-3 col-form-label requerido">Estado</label>
    <select name="estado" class="col-lg-3 form-control" required>
        <option value="">-- Elija estado --</option>
        @foreach($estadoEnum as $value => $estado)
            @if( $value == old('estado', $data->estado ?? ''))
                <option value="{{ $value }}" selected="select">{{ $estado }}</option>    
            @else
                <option value="{{ $value }}">{{ $estado }}</option>    
            @endif
        @endforeach
    </select>
</div>
