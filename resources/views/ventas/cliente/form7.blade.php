<div class="card form7" style="display: none">
    <div class="card-body">
    	<table class="table" id="articulo-suspendido-table">
    		<thead>
    			<tr>
    				<th style="width: 15%;">Artículo</th>
    				<th style="width: 35%;">Descripción</th>
					<th style="width: 15%;">Fecha suspensión</th>
					<th style="width: 15%;">Usuario</th>
    				<th></th>
    			</tr>
    		</thead>
    		<tbody id="tbody-tabla-articulo-suspendido">
		 		@if ($data->cliente_articulo_suspendidos ?? '') 
					@foreach (old('articulo_suspendidos', $data->cliente_articulo_suspendidos->count() ? $data->cliente_articulo_suspendidos : ['']) as $suspendido)
            			<tr class="item-articulo-suspendido">
                			<td>
                				<input type="hidden" name="articulo_suspendidos[]" class="form-control iiarticulo-suspendido" readonly value="{{ $loop->index+1 }}" />
                                <div class="form-group row" id="articulo">
                                    <input type="hidden" class="articulo_id" name="articulo_ids[]" value="{{$suspendido->articulo_id ?? ''}}" >
                                    <input type="hidden" class="articulo_id_previa" name="articulo_id_previa[]" value="{{$suspendido->articulo_id ?? ''}}" >
                                    <button type="button" title="Consulta articulos" style="padding:1;" class="btn-accion-tabla consultaarticulo tooltipsC">
                                            <i class="fa fa-search text-primary"></i>
                                    </button>
                                    <input type="text" style="WIDTH: 150px;HEIGHT: 38px" class="codigoarticulo form-control" name="codigoarticulos[]" value="{{$suspendido->articulos->sku ?? ''}}" >
                                    <input type="hidden" class="codigo_previo_articulo" name="codigo_previo_articulos[]" value="{{$suspendido->articulos->codigo ?? ''}}" >
                                </div>
                            </td>							
                            <td>
                                <input type="text" style="WIDTH: 350px; HEIGHT: 38px" class="descripcionarticulo form-control" name="descripcionarticulos[]" value="{{$suspendido->articulos->descripcion ?? ''}}" readonly>
                            </td>
							<td>
								<input type="datetime" class="fechasuspension form-control" name="fechasuspensiones[]" value="{{$suspendido->created_at ?? date('d-m-Y H:i:s')}}" readonly>
							</td>
							<td>
								<input type="hidden" name="creousuario_articulo_suspendido_ids[]" class="form-control creousuario_articulo_suspendido_riesgo_id" value="{{ $suspendido->creousuario_id ?? auth()->id()}}"/>
								<input type="text" name="creousuario_articulo_suspendidos[]" class="form-control creousuario_articulo_suspendido" value="{{ $suspendido->creousuarios->nombre ?? '' }}" readonly/>
							</td>												
                			<td>
								<button style="width: 7%;" type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_articulo_suspendido tooltipsC">
                            		<i class="fa fa-times-circle text-danger"></i>
								</button>
                			</td>
                		</tr>
           			@endforeach
				@endif
       		</tbody>
       	</table>
		@include('ventas.cliente.template7')
        <div class="row">
        	<div class="col-md-12">
        		<button id="agrega_renglon_articulo_suspendido" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
        	</div>
        </div>
    </div>
</div>
@include('includes.stock.modalconsultaarticulo')
