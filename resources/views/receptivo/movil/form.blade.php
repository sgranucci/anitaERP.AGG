<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
    <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="codigo" class="col-lg-3 col-form-label">Código Anita</label>
    <div class="col-lg-4">
        <input type="text" name="codigo" id="codigo" class="form-control" value="{{old('codigo', $data->codigo ?? '')}}" readonly>
    </div>
</div>
<div class="form-group row">
    <label for="dominio" class="col-lg-3 col-form-label requerido">Dominio</label>
    <div class="col-lg-1">
        <input type="text" name="dominio" id="dominio" class="form-control" value="{{old('dominio', $data->dominio ?? '')}}">
    </div>
</div>
<div class="form-group row">
    <label for="tipomovil" class="col-lg-3 col-form-label requerido">Tipo de móvil</label>
    <select id="tipomovil" name="tipomovil" class="col-lg-4 form-control" required>
        <option value="">-- Elija tipo de móvil --</option>
        @foreach($tipomovil_enum as $tipomovil)
            @if ($tipomovil['valor'] == old('tipomovil',$servicioterrestre->tipomovil??''))
                <option value="{{ $tipomovil['valor'] }}" selected>{{ $tipomovil['nombre'] }}</option>    
            @else
                <option value="{{ $tipomovil['valor'] }}">{{ $tipomovil['nombre'] }}</option>
            @endif
        @endforeach
    </select>
</div>
<div class="form-group row">
    <label for="vencimientoverificacionmunicipal" class="col-lg-3 col-form-label">Vto. Verif. Municipal</label>
    <div class="col-lg-3">
        <input type="date" name="vencimientoverificacionmunicipal" id="vencimientoverificacionmunicipal" class="form-control" value="{{old('vencimientoverificacionmunicipal', $data->vencimientoverificacionmunicipal ?? date('Y-m-d'))}}">
    </div>
</div>
<div class="form-group row">
    <label for="vencimientoverificaciontecnica" class="col-lg-3 col-form-label">Vto. Verif. Técnica</label>
    <div class="col-lg-3">
        <input type="date" name="vencimientoverificaciontecnica" id="vencimientoverificaciontecnica" class="form-control" value="{{old('vencimientoverificaciontecnica', $data->vencimientoverificaciontecnica ?? date('Y-m-d'))}}">
    </div>
</div>
<div class="form-group row">
    <label for="vencimientoservice" class="col-lg-3 col-form-label">Vto. Service</label>
    <div class="col-lg-3">
        <input type="date" name="vencimientoservice" id="vencimientoservice" class="form-control" value="{{old('vencimientoservice', $data->vencimientoservice ?? date('Y-m-d'))}}">
    </div>
</div>
<div class="form-group row">
    <label for="vencimientocorredor" class="col-lg-3 col-form-label">Vto. Corredor Turístico</label>
    <div class="col-lg-3">
        <input type="date" name="vencimientocorredor" id="vencimientocorredor" class="form-control" value="{{old('vencimientocorredor', $data->vencimientocorredor ?? date('Y-m-d'))}}">
    </div>
</div>
<div class="form-group row">
    <label for="vencimientoingresoparque" class="col-lg-3 col-form-label">Vto. Ingreso Parque Nac.</label>
    <div class="col-lg-3">
        <input type="date" name="vencimientoingresoparque" id="vencimientoingresoparque" class="form-control" value="{{old('vencimientoingresoparque', $data->vencimientoingresoparque ?? date('Y-m-d'))}}">
    </div>
</div>
<div class="form-group row">
    <label for="vencimientoseguro" class="col-lg-3 col-form-label">Vto. Seguro</label>
    <div class="col-lg-3">
        <input type="date" name="vencimientoseguro" id="vencimientoseguro" class="form-control" value="{{old('vencimientoseguro', $data->vencimientoseguro ?? date('Y-m-d'))}}">
    </div>
</div>