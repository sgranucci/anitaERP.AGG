<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
    <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
    </div>
</div>
<input type="hidden" id="id" name="id" value="{{ $data->id ?? '' }}" />
<div class="card">
    <div class="card-header">
    	Cuentas Contables
    </div>
    <div class="card-body">
    	<table class="table" id="conceptogastocc-table">
    		<thead>
    			<tr>
					<th>Empresa</th>
    				<th>Cuenta Contable</th>
    			</tr>
    		</thead>
    		<tbody id="tbody-cuentacontable-table">
		 		@if ($data->conceptogasto_cuentacontables ?? '') 
					@foreach (old('cuentas', $data->conceptogasto_cuentacontables->count() ? $data->conceptogasto_cuentacontables : ['']) as $cuentacontable)
            			<tr class="item-cuentacontable">
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
		@include('caja.conceptogasto.templatecuentacontable')
        <div class="row">
        	<div class="col-md-12">
        		<button id="agrega_renglon_cuenta" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
        	</div>
        </div>
    </div>
</div>
@include('includes.contable.modalconsultacuentacontable')
