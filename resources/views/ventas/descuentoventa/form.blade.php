<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-5">
        <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="tipodescuento" class="col-lg-3 col-form-label requerido">Tipo de Descuento</label>
    <select name="tipodescuento" id="tipodescuento" class="col-lg-3 form-control">
        <option value="">-- Elija tipo de descuento --</option>
        @foreach($tipodescuento_enum as $value => $tipodescuento)
            @if( $tipodescuento == old('tipodescuento', $data->tipodescuento ?? ''))
                <option value="{{ $tipodescuento }}" selected="select">{{ $tipodescuento }}</option>    
            @else
                <option value="{{ $tipodescuento }}">{{ $tipodescuento }}</option>    
            @endif
        @endforeach
    </select>
</div>
<div class="form-group row" id="div-porcentajedescuento">
    <label for="porcentajedescuento" class="col-lg-3 col-form-label requerido">Porcentaje de Descuento</label>
    <div class="col-lg-3">
        <input type="number" name="porcentajedescuento" id="porcentajedescuento" class="form-control" value="{{old('porcentajedescuento', $data->porcentajedescuento ?? '0')}}"/>
    </div>
</div>
<div class="form-group row" id="div-montodescuento">
    <label for="montodescuento" class="col-lg-3 col-form-label requerido">Monto de Descuento</label>
    <div class="col-lg-3">
        <input type="number" name="montodescuento" id="montodescuento" class="form-control" value="{{old('montodescuento', $data->montodescuento ?? '0')}}"/>
    </div>
</div>
<div class="form-group row" id="div-cantidadventa">
    <label for="cantidadventa" class="col-lg-3 col-form-label requerido">Cantidad Venta</label>
    <div class="col-lg-3">
        <input type="number" name="cantidadventa" id="cantidadventa" class="form-control" value="{{old('cantidadventa', $data->cantidadventa ?? '0')}}"/>
    </div>
</div>
<div class="form-group row" id="div-cantidaddescuento">
    <label for="cantidaddescuento" class="col-lg-3 col-form-label requerido">Cantidad Descuento</label>
    <div class="col-lg-3">
        <input type="number" name="cantidaddescuento" id="cantidaddescuento" class="form-control" value="{{old('cantidaddescuento', $data->cantidaddescuento ?? '0')}}"/>
    </div>
</div>
<div class="form-group row">
    <label for="estado" class="col-lg-3 col-form-label requerido">Estado</label>
    <select name="estado" class="col-lg-3 form-control">
        <option value="">-- Elija estado --</option>
        @foreach($estado_enum as $value => $estado)
            @if( $estado == old('estado', $data->estado ?? ''))
                <option value="{{ $estado }}" selected="select">{{ $estado }}</option>    
            @else
                <option value="{{ $estado }}">{{ $estado }}</option>    
            @endif
        @endforeach
    </select>
</div>
