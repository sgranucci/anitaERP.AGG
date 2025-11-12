<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-4">
    <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="registro" class="col-lg-3 col-form-label requerido">Registro</label>
    <div class="col-lg-3">
    <input type="text" name="registro" id="registro" class="form-control" value="{{old('registro', $data->registro ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="envasesenasa_id" class="col-lg-3 col-form-label">Código de Envase Senasa</label>
    <select name="envasesenasa_id" id="envasesenasa_id" data-placeholder="Transpore" class="col-lg-3 form-control" data-fouc>
        <option value="">-- Seleccionar Envase Senasa --</option>
        @foreach($envasesenasa_query as $key => $value)
            @if( (int) $value->id == (int) old('envasesenasa_id', $data->envasesenasa_id ?? ''))
                <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
            @else
                <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
            @endif
        @endforeach
    </select>
</div>
<div class="form-group row">
    <label for="llevafrio" class="col-lg-3 col-form-label">Lleva Frío</label>
    <select name="llevafrio" class="col-lg-3 form-control">
        <option value="">-- Elija bonificación --</option>
        @foreach ($llevafrio_enum as $value => $llevafrio)
            <option value="{{ $llevafrio }}"
                @if (old('llevafrio', $data->llevafrio ?? '') == $llevafrio) selected @endif
                >{{ $llevafrio }}</option>
        @endforeach
    </select>
</div>
<div class="form-group row">
    <label for="prefijo" class="col-lg-3 col-form-label requerido">Prefijo Cód. Producto</label>
    <div class="col-lg-4">
    <input type="text" name="prefijo" id="prefijo" class="form-control" value="{{old('prefijo', $data->prefijo ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="codigo" class="col-lg-3 col-form-label requerido">Código Anita</label>
    <div class="col-lg-2">
    <input type="text" name="codigo" id="codigo" class="form-control" value="{{old('codigo', $data->codigo ?? '')}}" readonly/>
    </div>
</div>