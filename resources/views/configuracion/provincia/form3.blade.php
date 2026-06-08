<div class="card form3" style="display: none">
    <div class="card-body">
    	<table class="table" id="cuentacontableiibb-table">
    		<thead>
    			<tr>
					<th>Empresa</th>
    				<th>Cuenta Contable</th>
    			</tr>
    		</thead>
    		<tbody id="tbody-cuentacontableiibb-table">
		 		@if ($data->provincia_cuentacontableiibbs ?? '') 
				@if (count($data->provincia_cuentacontableiibbs) > 0)
					@foreach (old('tasa', $data->provincia_cuentacontableiibbs->count() ? $data->provincia_cuentacontableiibbs : ['']) as $cuentacontable)
            			<tr class="item-cuentacontableiibb">
							<td>
								@include('includes.form-empresa-asignada-control', [
									'empresa_query' => $empresa_query,
									'empresa_id' => $cuentacontable->cuentacontables->empresa_id ?? null,
									'name' => 'empresa_ids[]',
									'select_class' => 'empresa',
									'permite_vacio' => true,
									'opcion_vacia' => '-- Seleccionar --',
									'required' => false,
								])
							</td>
							<td>
								<div class="form-group row" id="cuenta">
									<input type="hidden" name="cuenta[]" class="form-control iicuenta" readonly value="{{ $loop->index+1 }}" />
									<input type="hidden" class="cuentacontable_id" name="cuentacontable_ids[]" value="{{$cuentacontable->cuentacontable_id ?? ''}}" >
									<input type="hidden" class="cuentacontable_id_previa" name="cuentacontable_id_previa[]" value="{{$cuentacontable->cuentacontable_id ?? ''}}" >
									<button type="button" title="Consulta cuentas" style="padding:1;" class="btn-accion-tabla consultacuentacontable tooltipsC">
											<i class="fa fa-search text-primary"></i>
									</button>
									<input type="text" style="WIDTH: 200px;HEIGHT: 38px" class="codigocuentacontable form-control" name="codigos[]" value="{{$cuentacontable->cuentacontables->codigo ?? ''}}" >
									<input type="text" style="WIDTH: 400px;HEIGHT: 38px" class="nombrecuentacontable form-control" name="nombres[]" value="{{$cuentacontable->cuentacontables->nombre ?? ''}}" >
									<input type="hidden" class="codigo_previo" name="codigo_previos[]" value="{{$cuentacontable->cuentacontables->codigo ?? ''}}" >
								</div>
							</td>                                                      
                			<td>
								<button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_cuentacontableiibb tooltipsC">
                            		<i class="fa fa-times-circle text-danger"></i>
								</button>
								<input type="hidden" name="creousuario_cuentacontable_ids[]" class="form-control creousuario_cuentacontable_id" value="{{ $cuentacontable->creousuario_id ?? ''}}"/>
                			</td>
                		</tr>
           			@endforeach
				@endif
				@endif
       		</tbody>
       	</table>
		@include('configuracion.provincia.template3')
        <div class="row">
        	<div class="col-md-12">
        		<button id="agrega_renglon_cuentacontableiibb" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
        	</div>
        </div>
    </div>
</div>
@include('includes.contable.modalconsultacuentacontable')
