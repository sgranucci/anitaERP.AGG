<div class="form-group row">
    <label for="articulo_id" class="col-lg-3 col-form-label requerido">Art&iacute;culo</label>
	<select name="articulo_id" id="articulo_id" class="col-lg-3 form-control" required>
		<option value="">Seleccione el art&iacute;culo</option>
		@foreach($articulo_query as $id => $articulo)
			@if ( old('articulo_id', $precio->articulo_id ?? '') == $articulo->id )
           		<option value="{{$articulo->id}}" selected>{{$articulo->descripcion}} {{' - '.$articulo->sku}}</option>
           	@else
           		<option value="{{$articulo->id}}">{{$articulo->descripcion}} {{' - '.$articulo->sku}}</option>
			@endif
		@endforeach
	</select>
</div>
<div class="form-group row">
    <label for="listaprecio_id" class="col-lg-3 col-form-label requerido">Lista de precios</label>
	<select name="listaprecio_id" class="col-lg-3 form-control">
		<option value="">-- Elija lista de precios --</option>
		@foreach ($listaprecio_query as $listaprecio)
			<option value="{{ $listaprecio->id }}"
				@if (old('listaprecio_id', $precio->listaprecios->id ?? '') == $listaprecio->id) selected @endif
				>{{ $listaprecio->nombre }}
			</option>
		@endforeach
	</select>
</div>
<div class="form-group row">
	<label for="fechavigencia" class="col-lg-3 col-form-label requerido">Fecha de vigencia</label>
	<div class="col-lg-8">
		<input type="text" name="fechavigencia" id="fechavigencia" class="form-control" value="{{old('fechavigencia', \Carbon\Carbon::parse(strtotime($precio->fechavigencia ?? date('d-m-Y')))->formatLocalized('%d-%m-%Y') ?? date('d-m-Y'))}}" required/>
	</div>
</div>
<div class="form-group row">
    <label for="moneda_id" class="col-lg-3 col-form-label requerido">Moneda</label>
	<select name="moneda_id" class="col-lg-3 form-control">
		<option value="">-- Elija moneda --</option>
		@foreach ($moneda_query as $moneda)
			<option value="{{ $moneda->id }}"
				@if (old('moneda', $precio->monedas->id ?? '') == $moneda->id) selected @endif
				>{{ $moneda->nombre }}
			</option>
		@endforeach
	</select>
</div>
<div class="form-group row">
    <label for="precio" class="col-lg-3 col-form-label requerido">Precio</label>
    <div class="col-lg-8">
    	<input type="number" name="precio" id="precio" class="form-control" value="{{old('precio', $precio->precio ?? '')}}" required/>
    </div>
</div>

