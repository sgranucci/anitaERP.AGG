<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-4">
    <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="codigo" class="col-lg-3 col-form-label">C&oacute;digo</label>
    <div class="col-lg-2">
        <input type="text" name="codigo" id="codigo" class="form-control" value="{{old('codigo', $data->codigo ?? '')}}">
    </div>
</div>
<div class="form-group row">
    <label for="abreviatura" class="col-lg-3 col-form-label">Abreviatura</label>
    <div class="col-lg-2">
        <input type="text" name="abreviatura" id="abreviatura" class="form-control" value="{{old('abreviatura', $data->abreviatura ?? '')}}">
    </div>
</div>
<div class="form-group row">
    <label for="tipoiva" class="col-lg-3 col-form-label requerido">Ajusta m/e</label>
    <select id="tipoiva" name="tipoiva" class="col-lg-4 form-control" required>
        <option value="">-- Elija tipo de iva --</option>
        @foreach($tipoiva_enum as $tipoiva)
            @if ($tipoiva['nombre'] == old('tipoiva',$data->tipoiva??''))
                <option value="{{ $tipoiva['nombre'] }}" selected>{{ $tipoiva['nombre'] }}</option>    
            @else
                <option value="{{ $tipoiva['nombre'] }}">{{ $tipoiva['nombre'] }}</option>
            @endif
        @endforeach
    </select>
</div>
