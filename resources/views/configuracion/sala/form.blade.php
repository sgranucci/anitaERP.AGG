<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-4">
       <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="codigo" class="col-lg-3 col-form-label requerido">Código</label>
    <div class="col-lg-4">
       <input type="text" name="codigo" id="codigo" class="form-control" value="{{old('codigo', $data->codigo ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="empresa" class="col-lg-3 col-form-label">Empresa</label>
    <select name="empresa_id" id="empresa_id" data-placeholder="Empresa" class="col-lg-3 form-control required" data-fouc required>
        <option value="">-- Seleccionar empresa --</option>
        @foreach($empresa_query as $key => $value)
            @if( (int) $value->id == (int) old('empresa_id', $data->empresa_id ?? session('empresa_id')))
                <option value="{{ $value->id }}" selected="select">{{ $value->id }} {{ $value->nombre }}</option>    
            @else
                <option value="{{ $value->id }}">{{ $value->id }} {{ $value->nombre }}</option>    
            @endif
        @endforeach
    </select>
</div>
