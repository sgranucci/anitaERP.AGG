<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
    <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="comisionventa" class="col-lg-3 col-form-label">Comisi&oacute;n Ventas</label>
    <div class="col-lg-8">
    	<input type="number" name="comisionventa" id="comisionventa" class="form-control" value="{{old('comisionventa', $data->comisionventa ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="comisioncobranza" class="col-lg-3 col-form-label">Comisi&oacute;n Cobranzas</label>
    <div class="col-lg-8">
    	<input type="number" name="comisioncobranza" id="comisioncobranza" class="form-control" value="{{old('comisioncobranza', $data->comisioncobranza ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="aplicasobre" class="col-lg-3 col-form-label">Aplica sobre</label>
    <select name="aplicasobre" id="aplicasobre" data-placeholder="aplicasobre" class="col-lg-2 form-control required" data-fouc required>
        @foreach($aplicasobre_enum as $value)
            @if( $value['nombre'] == old('aplicasobre', $data->aplicasobre ?? ''))
                <option value="{{ $value['nombre'] }}" selected="select">{{ $value['nombre'] }}</option>    
            @else
                <option value="{{ $value['nombre'] }}">{{ $value['nombre'] }}</option>    
            @endif
        @endforeach
    </select>
</div>    
<div class="form-group row">
    <label for="empresa" class="col-lg-3 col-form-label">Empresa</label>
    <select name="empresa_id" id="empresa_id" data-placeholder="Empresa" class="col-lg-3 form-control" data-fouc>
        @foreach($empresa_query as $key => $value)
            @if( (int) $value->id == (int) old('empresa_id', $data->empresa_id ?? ''))
                <option value="{{ $value->id }}" selected="select">{{ $value->id }} {{ $value->nombre }}</option>    
            @else
                <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
            @endif
        @endforeach
    </select>
</div>
<div class="form-group row">
    <label for="legajo_id" class="col-lg-3 col-form-label">Legajo</label>
    <div class="col-lg-2">
    	<input type="number" name="legajo_id" id="legajo_id" class="form-control" value="{{old('legajo_id', $data->legajo_id ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="email" class="col-lg-3 col-form-label">Email</label>
    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
    <div class="col-lg-8">
        <input type="email" name="email" id="email" class="form-control" value="{{old('email', $data->email ?? '')}}" placeholder="Ingrese email">
    </div>
</div>
<div class="form-group row">
    <label for="codigo" class="col-lg-3 col-form-label">Código Anita</label>
    <div class="col-lg-2">
    	<input type="text" name="codigo" id="codigo" class="form-control" value="{{old('codigo', $data->codigo ?? '')}}" readonly/>
    </div>
</div>
<div class="form-group row">
    <label for="estado" class="col-lg-3 col-form-label">Estado</label>
    <select name="estado" id="estado" data-placeholder="estado" class="col-lg-2 form-control required" data-fouc required>
        @foreach($estado_enum as $value)
            @if( $value['nombre'] == old('estado', $data->estado ?? ''))
                <option value="{{ $value['nombre'] }}" selected="select">{{ $value['nombre'] }}</option>    
            @else
                <option value="{{ $value['nombre'] }}">{{ $value['nombre'] }}</option>    
            @endif
        @endforeach
    </select>
</div> 
