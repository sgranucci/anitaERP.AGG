<div class="row">
    <div class="col-sm-6">
		<div class="form-group row">
			<label for="empresa_id" class="col-lg-3 col-form-label requerido">Empresa</label>
			<div class="col-lg-8">
				<select name="empresa_id" id="empresa_id" class="form-control" required>
					<option value="">Seleccione la empresa</option>
					@foreach($empresa_query as $id => $empresa)
						@if( isset($data) && (int) $empresa->id == (int) $data->empresa_id )
							<option value="{{$empresa->id}}" selected>{{$empresa->nombre}}</option>
						@else
							<option value="{{$empresa->id}}">{{$empresa->nombre}}</option>
						@endif
					@endforeach
				</select>
			</div>
		</div>
		<div class="form-group row">
			<label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
			<div class="col-lg-8">
			<input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
			</div>
		</div>
		<div class="form-group row">
			<label for="codigo" class="col-lg-3 col-form-label requerido">C&oacute;digo</label>
			<div class="col-lg-3">
			<input type="text" name="codigo" id="codigo" class="form-control" value="{{old('codigo', $data->codigo ?? '')}}" required/>
			</div>
		</div>
		<div class="form-group row">
			<label for="nivel" class="col-lg-3 col-form-label requerido">Nivel</label>
			<div class="col-lg-3">
			<input type="text" name="nivel" id="nivel" class="form-control" value="{{old('nivel', $data->nivel ?? '')}}" required/>
			</div>
		</div>
		<div class="form-group row">
			<label for="rubrocontable_id" class="col-lg-3 col-form-label requerido">Rubro contable</label>
			<div class="col-lg-8">
				<select name="rubrocontable_id" id="rubrocontable_id" class="form-control" required>
					<option value="">Seleccione el rubro contable</option>
					@foreach($rubrocontable_query as $id => $rubrocontable)
						@if( isset($data) && (int) $rubrocontable->id == (int) $data->rubrocontable_id )
							<option value="{{$rubrocontable->id}}" selected>{{$rubrocontable->nombre}}</option>
						@else
							<option value="{{$rubrocontable->id}}">{{$rubrocontable->nombre}}</option>
						@endif
					@endforeach
				</select>
			</div>
		</div>
		<div class="form-group row">
			<label for="tipocuenta" class="col-lg-3 col-form-label requerido">Tipo de cuenta</label>
			<select name="tipocuenta" class="col-lg-3 form-control">
				@if (empty($data['tipocuenta']))
					<option value="">-- Elija el tipo de cuenta --</option>
				@endif
				@for ($i = 1; $i <= 3; $i++)
					@if (!empty($data['tipocuenta']) && $data['tipocuenta'] == $i)
						<option selected value="{{$i}}">{{($i==1?'Imputable':($i==2?'No imputable':'Totalizadora'))}}</option>
					@else
						<option value="{{$i}}">{{($i==1?'Imputable':($i==2?'No imputable':'Totalizadora'))}}</option>
					@endif
				@endfor
			</select>
		</div>
	</div>
	<div class="col-sm-6">
		<div class="form-group row">
			<label for="monetaria" class="col-lg-3 col-form-label requerido">Cuenta monetaria</label>
			<select name="monetaria" class="col-lg-3 form-control">
				@if (empty($data['tipocuenta']))
					<option value="">-- Elija si la cuenta es monetaria --</option>
				@endif
				@for ($i = 1; $i <= 2; $i++)
					@if (!empty($data['monetaria']) && $data['monetaria'] == ($i == 1 ? 'S' : 'N')))
						<option selected value="{{$i==1?'S':'N'}}">{{$i==1?'Monetaria':'No monetaria'}}</option>
					@else
						<option value="{{$i==1?'S':'N'}}">{{$i==1?'Monetaria':'No monetaria'}}</option>
					@endif
				@endfor
			</select>
		</div>
		<div class="form-group row">
			<label for="manejaccosto" class="col-lg-3 col-form-label requerido">Maneja centros de costo</label>
			<select id="manejaccosto" name="manejaccosto" class="col-lg-3 form-control">
				@if (empty($data['manejaccosto']))
					<option value="">-- Elija si maneja centros de costo --</option>
				@endif
				@for ($i = 1; $i <= 2; $i++)
					@if (!empty($data['manejaccosto']) && $data['manejaccosto'] == ($i == 1 ? 'S' : 'N')))
						<option selected value="{{$i==1?'S':'N'}}">{{$i==1?'Maneja c.costo':'No maneja c.costo'}}</option>
					@else
						<option value="{{$i==1?'S':'N'}}">{{$i==1?'Maneja c.costo':'No maneja c.costo'}}</option>
					@endif
				@endfor
			</select>
		</div>
		<div class="form-group row">
			<label for="ajustamonedaextranjera" class="col-lg-3 col-form-label requerido">Ajusta m/e</label>
			<select id="ajustamonedaextranjera" name="ajustamonedaextranjera" class="col-lg-4 form-control" required>
				<option value="">-- Elija si ajusta moneda extranjera --</option>
				@foreach($ajustamonedaextranjera_enum as $ajustamonedaextranjera)
					@if ($ajustamonedaextranjera['valor'] == old('ajustamonedaextranjera',$data->ajustamonedaextranjera??''))
						<option value="{{ $ajustamonedaextranjera['valor'] }}" selected>{{ $ajustamonedaextranjera['nombre'] }}</option>    
					@else
						<option value="{{ $ajustamonedaextranjera['valor'] }}">{{ $ajustamonedaextranjera['nombre'] }}</option>
					@endif
				@endforeach
			</select>
		</div>
		<div class="form-group row">
			@if (config('app.empresa') == "AGG")
				<label for="conceptogasto_id" class="col-lg-3 col-form-label requerido">Concepto Cash Flow</label>
				<div class="col-lg-8">
					<select name="conceptogasto_id" id="conceptogasto_id" class="form-control" required>
						<option value="">Seleccione el concepto gasto</option>
						@foreach($conceptogasto_query as $id => $conceptogasto)
							@if( isset($data) && (int) $conceptogasto->id == (int) $data->conceptogasto_id )
								<option value="{{$conceptogasto->id}}" selected>{{$conceptogasto->nombre}}</option>
							@else
								<option value="{{$conceptogasto->id}}">{{$conceptogasto->nombre}}</option>
							@endif
						@endforeach
					</select>
				</div>
			@else
				<label for="conceptogasto_id" class="col-lg-3 col-form-label">Concepto Cash Flow</label>
				<div class="col-lg-8">
					<select name="conceptogasto_id" id="conceptogasto_id" class="form-control">
						<option value="">Seleccione el concepto gasto</option>
						@foreach($conceptogasto_query as $id => $conceptogasto)
							@if( isset($data) && (int) $conceptogasto->id == (int) $data->conceptogasto_id )
								<option value="{{$conceptogasto->id}}" selected>{{$conceptogasto->nombre}}</option>
							@else
								<option value="{{$conceptogasto->id}}">{{$conceptogasto->nombre}}</option>
							@endif
						@endforeach
					</select>
				</div>
			@endif
		</div>
		<div class="form-group row">
			<label for="cuentacontable_difcambio_id" class="col-lg-3 col-form-label">Cuenta contable dif. cbio.</label>
			<div class="col-lg-8">
				<select name="cuentacontable_difcambio_id" id="cuentacontable_difcambio_id" class="form-control">
					<option value="">Seleccione cuenta contable dif. cambio</option>
					@foreach($cuentacontable_query as $id => $cuentacontable_difcambio)
						@if( isset($data) && (int) $cuentacontable_difcambio->id == (int) $data->cuentacontable_difcambio_id )
							<option value="{{$cuentacontable_difcambio->id}}" selected>{{$cuentacontable_difcambio->nombre}}</option>
						@else
							<option value="{{$cuentacontable_difcambio->id}}">{{$cuentacontable_difcambio->nombre}}</option>
						@endif
					@endforeach
				</select>
			</div>
		</div>
	</div>
</div>
<div class="card-body" id="divcentrocosto">
	<h3>Centros de costo válidos</h3>
	<table class="table" id="centrocosto-table">
		<thead>
			<tr>
				<th style="width: 50%;">Centro de Costo</th>
				<th></th>
			</tr>
		</thead>
		<tbody id="tbody-centrocosto-table">
		@if ($data->cuentacontable_centrocostos ?? '') 
			@foreach (old('centrocostos', $data->cuentacontable_centrocostos->count() ? $data->cuentacontable_centrocostos : ['']) as $centrocosto)
				<tr class="item-centrocosto">
					<td>
						<select name="centrocosto_ids[]" id="centrocosto_ids" data-placeholder="Centro de costo" class="form-control centrocosto" data-fouc>
							<option value="">-- Elija centro de costo --</option>
							@foreach($centrocosto_query as $key => $value)
								@if( (int) $value->id == (int) old('centrocosto_id', $centrocosto->centrocosto_id ?? ''))
									<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
								@else
									<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
								@endif
							@endforeach
						</select>
					</td>
					<td>
						<button style="width: 7%;" type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_centrocosto tooltipsC">
							<i class="fa fa-times-circle text-danger"></i>
						</button>
					</td>
				</tr>
			@endforeach
		@endif
		</tbody>
	</table>
	@include('contable.cuentacontable.template1')
	<div class="row">
		<div class="col-md-12">
			<button id="agrega_renglon_centrocosto" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
		</div>
	</div>
</div>

