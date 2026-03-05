<div class="form-group row">
    <label for="fecha" class="col-lg-3 col-form-label">Fecha</label>
    <div class="col-lg-3">
        <input type="date" name="fecha" id="fecha" class="form-control" value="{{old('fecha', $data->fecha ?? date('Y-m-d'))}}">
    </div>
</div>
<div class="form-group row">
    <label for="empresa" class="col-lg-3 col-form-label">Empresa</label>
    <select name="empresa_id" id="empresa_id" data-placeholder="Empresa" class="col-lg-7 form-control required" data-fouc required>
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
<div class="form-group row">
    <label for="caja" class="col-lg-3 col-form-label">Caja</label>
    <select name="caja_id" id="caja_id" data-placeholder="Caja" class="col-lg-7 form-control required" data-fouc required>
        <option value="">-- Seleccionar caja --</option>
        @foreach($caja_query as $key => $value)
            @if( (int) $value->id == (int) old('caja_id', $data->caja_id ?? session('caja_id')))
                <option value="{{ $value->id }}" selected="select">{{ $value->id }} {{ $value->nombre }}</option>    
            @else
                <option value="{{ $value->id }}">{{ $value->id }} {{ $value->nombre }}</option>    
            @endif
        @endforeach
    </select>
</div>
<div class="form-group row">
    <label for="usuario" class="col-lg-3 col-form-label">Usuario</label>
    <select name="usuario_id" id="usuario_id" data-placeholder="Usuario" class="col-lg-7 form-control required" data-fouc required>
        <option value="">-- Seleccionar caja --</option>
        @foreach($usuario_query as $key => $value)
            @if( (int) $value->id == (int) old('usuario_id', $data->usuario_id ?? session('usuario_id')))
                <option value="{{ $value->id }}" selected="select">{{ $value->id }} {{ $value->nombre }}</option>    
            @else
                <option value="{{ $value->id }}">{{ $value->id }} {{ $value->nombre }}</option>    
            @endif
        @endforeach
    </select>
</div>