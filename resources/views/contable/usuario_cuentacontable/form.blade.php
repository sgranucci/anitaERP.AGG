<div class="form-group row">
	<label for="usuario" class="col-lg-4 col-form-label">Usuario</label>
	<input type="hidden" name="usuario_id" class="form-control" value="{{ $usuario_id }}" />
	<select name="usuarioselect_id" id="usuarioselect_id" data-placeholder="Usuario" class="col-lg-6 form-control required" data-fouc disabled="true">
		<option value="">-- Seleccionar --</option>
		@foreach($usuario_query as $key => $value)
			@if( (int) $value->id == (int) old('usuario_id', $usuario_id ?? session('usuario_id')))
				<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
			@else
				<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
			@endif
		@endforeach
	</select>
</div>
<div class="card">
    <div class="card-header">
    	Cuentas Contables
    </div>

    <div class="card-body">
    	<table class="table" id="usuariocc-table">
    		<thead>
    			<tr>
					<th>Empresa</th>
    				<th>Cuenta Contable</th>
    			</tr>
    		</thead>
    		<tbody id="tbody-cuenta-table">
		 		@if ($usuario_cuentacontable ?? '') 
					@foreach (old('cuentas', $usuario_cuentacontable->count() ? $usuario_cuentacontable : ['']) as $cuentacontable)
            			<tr class="item-cuenta">
							<td>
								<select name="empresa_ids[]" data-placeholder="Empresa" class="empresa form-control required" required data-fouc>
									<option value="">-- Seleccionar --</option>
									@foreach($empresa_query as $value)
										@if( (int) $value->id == (int) old('empresa_ids[]', $cuentacontable->cuentacontables->empresa_id ?? ''))
											<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
										@else
											<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
										@endif
									@endforeach
								</select>
							</td>
							<td>
								<div class="form-group row" id="cuenta">
									<input type="hidden" name="cuenta[]" class="form-control iicuenta" readonly value="{{ $loop->index+1 }}" />
									<input type="hidden" class="cuentacontable_id" name="cuentacontable_ids[]" value="{{$cuentacontable->cuentacontable_id ?? ''}}" >
									<input type="hidden" class="cuentacontable_id_previa" name="cuentacontable_id_previa[]" value="{{$cuentacontable->cuentacontable_id ?? ''}}" >
									<button type="button" title="Consulta cuentas" style="padding:1;" class="btn-accion-tabla consultacuenta tooltipsC">
											<i class="fa fa-search text-primary"></i>
									</button>
									<input type="text" style="WIDTH: 200px;HEIGHT: 38px" class="codigo form-control" name="codigos[]" value="{{$cuentacontable->cuentacontables->codigo ?? ''}}" >
									<input type="text" style="WIDTH: 400px;HEIGHT: 38px" class="nombre form-control" name="nombres[]" value="{{$cuentacontable->cuentacontables->nombre ?? ''}}" >
									<input type="hidden" class="codigo_previo" name="codigo_previos[]" value="{{$cuentacontable->cuentacontables->codigo ?? ''}}" >
								</div>
							</td>
                			<td>
								<button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_cuenta tooltipsC">
                            		<i class="fa fa-times-circle text-danger"></i>
								</button>
                			</td>
                		</tr>
           			@endforeach
				@endif
       		</tbody>
       	</table>
		<template id="template-renglon-cuenta">
            	<tr class="item-cuenta">
					<td>
						<select name="empresa_ids[]" data-placeholder="Empresa" class="empresa form-control required" required data-fouc>
							<option value="">-- Seleccionar --</option>
							@foreach($empresa_query as $value)
								<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
							@endforeach
						</select>
					</td>
					<td>
						<div class="form-group row" id="cuenta">
							<input type="hidden" name="cuenta[]" class="form-control iicuenta" readonly value="1" />
							<input type="hidden" class="cuentacontable_id" name="cuentacontable_ids[]" value="" >
							<input type="hidden" class="cuentacontable_id_previa" name="cuentacontable_id_previa[]" value="" >
							<button type="button" title="Consulta cuentas" style="padding:1;" class="btn-accion-tabla consultacuenta tooltipsC">
									<i class="fa fa-search text-primary"></i>
							</button>
							<input type="text" style="WIDTH: 200px;HEIGHT: 38px" class="codigo form-control" name="codigos[]" value="" >
							<input type="text" style="WIDTH: 400px;HEIGHT: 38px" class="nombre form-control" name="nombres[]" value="" >
							<input type="hidden" class="codigo_previo" name="codigo_previos[]" value="" >
						</div>
					</td>	
                	<td>
						<button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_cuenta tooltipsC">
                            <i class="fa fa-times-circle text-danger"></i>
						</button>
                	</td>
           		</tr>
		</template>
        <div class="row">
        	<div class="col-md-12">
        		<button id="agrega_renglon_cuenta" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
        	</div>
        </div>
    </div>
</div>
@include('includes.contable.modalconsultacuentacontable')
