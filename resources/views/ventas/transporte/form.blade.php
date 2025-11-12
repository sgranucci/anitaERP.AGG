<div class="form1">
	<div class="mt-2">
		<div class="row">
			<div class="col-sm-6">
				<div class="form-group row">
    				<label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    				<div class="col-lg-8">
    					<input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
    				</div>
				</div>
				<div class="form-group row">
    				<label for="codigo" class="col-lg-3 col-form-label">C&oacute;digo</label>
    				<div class="col-lg-4">
    					<input type="text" name="codigo" id="codigo" class="form-control" value="{{old('codigo', $data->codigo ?? '')}}" readonly>
    				</div>
				</div>
				<div class="form-group row">
    				<label for="nroinscripcion" class="col-lg-3 col-form-label requerido">C.U.I.T.</label>
    				<div class="col-lg-8">
    					<input type="text" name="nroinscripcion" id="nroinscripcion" class="form-control" value="{{old('nroinscripcion', $data->nroinscripcion ?? '')}}" required/>
    				</div>
				</div>
        		<div class="form-group row">
    				<label for="condicioniva_id" class="col-lg-3 col-form-label requerido">Condicion de iva.</label>
        			<select name="condicioniva_id" id="condicioniva_id" data-placeholder="Condicion de iva" class="col-lg-5 form-control required" data-fouc>
        				<option value="">-- Seleccionar --</option>
        				@foreach($condicionesiva_query as $key => $value)
        					@if( isset($data) && (int) $value->id == (int) old('condicioniva_id', $data->condicioniva_id ?? '') )
        						<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
        					@else
        						<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
        					@endif
        				@endforeach
        			</select>
        		</div>
				<div class="form-group row">
					<label for="tipoexpreso" class="col-lg-3 col-form-label requerido">Tipo de expreso</label>
					<select name="tipoexpreso" class="col-lg-3 form-control" required>
						<option value="">-- Elija tipo de expreso --</option>
						@foreach($tipoexpresoEnum as $value => $tipoexpreso)
							@if( $value == old('tipoexpreso', $data->tipoexpreso ?? ''))
								<option value="{{ $value }}" selected="select">{{ $tipoexpreso }}</option>    
							@else
								<option value="{{ $value }}">{{ $tipoexpreso }}</option>    
							@endif
						@endforeach
					</select>
				</div>
				<div class="form-group row">
					<label for="copiapedido" class="col-lg-4 col-form-label">Copia Pedido</label>
					<div class="col-lg-2">
						<input type="number" name="copiapedido" id="copiapedido" class="form-control" value="{{old('copiapedido', $data->copiapedido ?? '1')}}">
					</div>
				</div>
			</div>
			<div class="col-sm-6">
				<div class="form-group row">
    				<label for="telefono" class="col-lg-3 col-form-label requerido">Telefono</label>
                    <span class="input-group-text"><i class="fas fa-phone"></i></span>
    				<div class="col-lg-8">
    				<input type="text" name="telefono" id="telefono" class="form-control" value="{{old('telefono', $data->telefono ?? '')}}" required/>
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
    				<label for="patentevehiculo" class="col-lg-4 col-form-label">Patente veh&iacute;culo</label>
    				<div class="col-lg-4">
    					<input type="text" name="patentevehiculo" id="patentevehiculo" class="form-control" value="{{old('patentevehiculo', $data->patentevehiculo ?? '')}}">
    				</div>
				</div>
				<div class="form-group row">
    				<label for="patenteacoplado" class="col-lg-4 col-form-label">Patente acoplado</label>
    				<div class="col-lg-4">
    					<input type="text" name="patenteacoplado" id="patenteacoplado" class="form-control" value="{{old('patenteacoplado', $data->patenteacoplado ?? '')}}">
    				</div>
				</div>
				<div class="form-group row">
    				<label for="horarioentrega" class="col-lg-4 col-form-label">Horario entrega</label>
    				<div class="col-lg-8">
    					<input type="text" name="horarioentrega" id="horarioentrega" class="form-control" value="{{old('horarioentrega', $data->horarioentrega ?? '')}}">
    				</div>
				</div>
				<div class="form-group row">
					<label for="copiaremito" class="col-lg-4 col-form-label">Copia Remito</label>
					<div class="col-lg-2">
						<input type="number" name="copiaremito" id="copiaremito" class="form-control" value="{{old('copiaremito', $data->copiaremito ?? '1')}}">
					</div>
				</div>
			</div>
		</div>
		<h4>Domicilio</h4>
        <div class='col-md-12'>
        	<div class="row mt-0">
        		<div class="col-md-3" id='prov'>
        			<div class="form-group">
        				<label>Provincia</label>
        				<select name="provincia_id" id="provincia_id" data-placeholder="Provincia" class="form-control required" data-fouc>
        					<option value="">-- Seleccionar --</option>
        					@foreach($provincias_query as $key => $value)
        						@if( isset($data) && (int) $value->id == (int) old('provincia_id', $data->provincia_id ?? ''))
        							<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
        						@else
        							<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
        						@endif
        					@endforeach
        				</select>
        				<input type="hidden" id="desc_provincia" name="desc_provincia" value="{{old('desc_provincia', $data->desc_provincia ?? '')}}" >
        			</div>
        		</div>
        		<div class="col-md-3" id='loc'>
        			<div class="form-group">
        				<label>Localidad</label>
        				<select name="localidad_id" id='localidad_id' data-placeholder="Localidad" class="form-control required" data-fouc>
        					@if($data->localidad_id ?? '')
								@if($data->localidad_id == "")
        							<option selected></option>
        						@else
        							<option value="{{old('localidad_id', $data['localidad_id'])}}" selected>{{$data['desc_localidad']}}</option>
								@endif
        					@endif
        				</select>
        				<input type="hidden" id="localidad_id_previa" name="localidad_id_previa" value="{{old('localidad_id', $data->localidad_id ?? '')}}" >
        				<input type="hidden" id="desc_localidad" name="desc_localidad" value="{{old('desc_localidad', $data->desc_localidad ?? '')}}" >
        			</div>
        		</div>
        		<div class="col-md-3">
        			<div class="form-group">
        				<label>Código Postal</label>
        				<input type="text" name="codigopostal" id="codigopostal" value="{{old('codigopostal', $data->codigopostal ?? '')}}" class="form-control required" placeholder="Codigo Postal">
        			</div>
        		</div>
			</div>
		</div>
		<div class="row">
			<div class="col-lg-12">
				<div class="form-group row">
    				<label for="domicilio" class="col-lg-3 col-form-label requerido">Direcci&oacute;n</label>
    				<div class="col-lg-6">
    					<input type="text" name="domicilio" id="domicilio" class="form-control" value="{{old('domicilio', $data->domicilio ?? '')}}" required/>
    				</div>
				</div>
			</div>
		</div>
	</div>
</div>

