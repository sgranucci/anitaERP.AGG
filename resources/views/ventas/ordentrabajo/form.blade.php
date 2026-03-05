<div class="card">
    <div class="card-body">
        <div class="row">
            <div class="col-sm-6">
				<div class="form-group row" id="marca" data-articulo="{{$articulo_query}}">
   					<label for="mventa" class="col-lg-3 col-form-label requerido">Marca</label>
        			<select name="mventa_id" id="mventa_id" data-placeholder="Marca de Venta" class="col-lg-8 form-control required" data-fouc>
        				<option value="">-- Seleccionar marca --</option>
        				@foreach($mventa_query as $key => $value)
        					@if( (int) $value->id == (int) old('mventa_id', $pedido->mventa_id ?? ''))
        						<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
        					@else
        						<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
        					@endif
        				@endforeach
        			</select>
				</div>
                <div class="form-group row">
    				<label for="articulo_id" class="col-lg-3 col-form-label requerido">Art&iacute;culo</label>
					<select name="articulo_id" class="col-lg-8 form-control articulo required">
                		<option value="">-- Elija art&iacute;culo --</option>
                		@foreach ($articulo_query as $articulo)
                			<option value="{{ $articulo['id'] }}">{{ $articulo['descripcion'] }}-{{$articulo['sku']}}</option>
                		@endforeach
            		</select>
                </div>
                <div class="form-group row">
    				<label for="combinacion" class="col-lg-3 col-form-label requerido">Combinaci&oacute;n</label>
        			<select id="combinacion_id" name="combinacion_id" data-placeholder="Combinaciones" class="col-lg-8 form-control combinacion" data-fouc></select>
                </div>
            </div>
        </div>
		<div class="card-footer">
        	<div class="row">
				<input type="submit" name="extension" id="extension" class="btn-sm btn-info" value="Consulta Pedidos"></input>
        	</div>
        </div>
    </div>
</div>
