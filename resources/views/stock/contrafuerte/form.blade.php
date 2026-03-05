<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
    	<input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
	<label for="articulo_id" class="col-lg-3 col-form-label requerido">Art&iacute;culo</label>
	<div class="col-lg-8">
		<select name="articulo_id" id="articulo_id" class="form-control" required>
			<option value="">Seleccione el art&iacute;culo</option>
			@foreach($articulo_query as $id => $articulo)
				@if( isset($data) && (int) $articulo->id == (int) $data->articulo_id )
            		<option value="{{$articulo->id}}" selected>{{$articulo->descripcion}} {{' - '.$articulo->sku}}</option>
            	@else
            		<option value="{{$articulo->id}}">{{$articulo->descripcion}} {{' - '.$articulo->sku}}</option>
				@endif
			@endforeach
		</select>
	</div>
</div>

