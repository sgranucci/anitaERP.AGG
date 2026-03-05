<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
    <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $modulo->nombre ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="codigo" class="col-lg-3 col-form-label requerido">C&oacute;digo</label>
    <div class="col-lg-8">
    <input type="text" name="codigo" id="codigo" class="form-control" value="{{old('codigo', $modulo->codigo ?? '')}}" required/>
    </div>
</div>
<div class="card">
    <div class="card-header">
    	Medidas
    </div>

    <div class="card-body">
    <table class="table" id="medidas_table">
    	<thead>
    		<tr>
    			<th>Medida</th>
    			<th>Cantidad</th>
    		</tr>
    	</thead>
    	<tbody>
		 	@if (!empty($modulo))
				@foreach (old('talles', $modulo->talles->count() ? $modulo->talles : ['']) as $modulo_talle)
               		<tr id="talle{{ $loop->index }}">
                		<td>
                			<select name="talles[]" class="form-control">
                				<option value="">-- Elija medida/talle --</option>
                				@foreach ($talles as $talle)
                					<option value="{{ $talle->id }}"
                					@if (old('talles.' . $loop->parent->index, optional($modulo_talle)->id) == $talle->id) selected @endif
                				>{{ $talle->nombre }}</option>
                				@endforeach
                			</select>
                		</td>
                		<td>
                			<input type="number" name="cantidades[]" class="form-control"
                				value="{{ (old('cantidades.' . $loop->index) ?? optional(optional($modulo_talle)->pivot)->cantidad) ?? '1' }}" />
                		</td>
                	</tr>
           		@endforeach
				<tr id="talle{{ count(old('talles', $modulo->talles->count() ? $modulo->talles : [''])) }}"></tr>
			@else
            	<tr id="medida0">
            		<td>
						<select name="talles[]" class="form-control">
                			<option value="">-- Elija medida/talle --</option>
                			@foreach ($talles as $medida)
                				<option value="{{ $medida->id }}">
                					{{ $medida->nombre }}
                				</option>
                			@endforeach
                		</select>
               		</td>
               		<td>
                    	<input type="number" name="cantidades[]" class="form-control" value="1" />
                	</td>
           		</tr>
            	<tr id="medida1"></tr>
			@endif
            </tbody>
            </table>
            <div class="row">
                <div class="col-md-12">
                    <button id="add_row" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
                    <button id='delete_row' class="pull-right btn btn-danger">- Borra rengl&oacute;n</button>
                </div>
            </div>
        </div>
    </div>
</div>
