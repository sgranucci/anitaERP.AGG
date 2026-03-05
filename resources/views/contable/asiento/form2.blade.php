<div class="card form2" style="display: none">
    <div class="card-body">
    	<table class="table" id="archivo-table">
    		<thead>
    			<tr>
    				<th>Archivo</th>
    				<th>Vista</th>
    				<th></th>
    			</tr>
    		</thead>
    		<tbody id="tbody-tabla-archivo">
		 		@if ($data->asiento_archivos ?? '') 
					@foreach (old('archivos', $data->asiento_archivos->count() ? $data->asiento_archivos : ['']) as $archivo)
            			<tr class="item-archivo">
                			<td>
								<input type="file" name="nombrearchivos[]" class="form-control nombrearchivos" 
									onchange='actualizaArchivo(this)'
									data-initial-preview="{{isset($archivo->nombrearchivo) ? asset("storage/archivos/asientos/$data->id/$archivo->nombrearchivo") : ''}}" \>
								<input type="hidden" name="nombresanteriores[]" class="form-control nombresanteriores" 
									value="{{isset($archivo->nombrearchivo) ? $archivo->nombrearchivo : ''}}" />
                			</td>
                			<td>
								@if ($archivo->nombrearchivo ?? '')
									@if (substr($archivo->nombrearchivo??'', -3) == "pdf")
                                		<img heigh=40px width=100px class="img-fluid rounded" src="{{asset('storage/imagenes/pdf.png')}}" alt="Archivo del cliente" />
                                	@else
										<img heigh=100px width=100px src="{{ asset("storage/archivos/asientos/$data->id/$archivo->nombrearchivo") }}" alt="image">
									@endif
									{{ "$archivo->nombrearchivo??''" }}
								@endif
                			</td>
                			<td>
								@if ($archivo->nombrearchivo ?? '')
									<a download="{{$archivo->nombrearchivo}}" href="{{ asset("storage/archivos/asientos/$data->id/$archivo->nombrearchivo") }}" title='Descargar' /><i class="fa fa-download"></i>
									<button style="width: 7%;" type="button" title="Elimina esta linea" class="btn-accion-tabla eliminararchivo tooltipsC">
                            			<i class="fa fa-times-circle text-danger"></i>
									</button>
								@endif
                			</td>
                		</tr>
           			@endforeach
				@endif
       		</tbody>
       	</table>
		@include('contable.asiento.template2')
        <div class="row">
        	<div class="col-md-12">
        		<button id="agrega_renglon_archivo" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
        	</div>
        </div>
    </div>
</div>
