<div class="form1">
    <div class="form-group row">
        <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
        <div class="col-lg-8">
            <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
        </div>
    </div>
    <div class="form-group row">
        <label for="anio" class="col-lg-3 col-form-label requerido">Año</label>
        <div class="col-lg-2">
            <input type="number" id="anio" name="anio" min="1900" max="2199" step="1" value="{{$data->anio ?? ''}}" placeholder="AAAA">
        </div>
    </div>    
    <div class="form-group row">
        <label for="detalle" class="col-lg-3 col-form-label">Detalle</label>
        <textarea name="detalle" id="detalle" class="col-lg-4 form-control" rows="6" placeholder="Leyendas ...">{{old('detalle', $data->detalle ?? '')}}</textarea>
    </div>
    <div class="form-group row">
        <label for="estado" class="col-lg-3 col-form-label requerido">Estado</label>
        <select id="estado" name="estado" class="col-lg-2 form-control">
            @foreach ($estado_enum as $value => $estado)
                <option value="{{ $estado }}"
                    @if (old('estado', $data->estado ?? '') == $estado) selected @endif
                >{{ $estado }}</option>
            @endforeach
        </select>
    </div>
    <div class="form-group row">
        <label for="codigo" class="col-lg-3 col-form-label requerido">Código</label>
        <div class="col-lg-1">
            <input type="text" name="codigo" id="codigo" class="form-control" value="{{old('codigo', $data->codigo ?? '')}}" readonly/>
        </div>
    </div>    
    <div class="card-body col-md-8">
        <h4>Escenarios</h4>
    	<table class="table" id="escenario-table">
    		<thead>
    			<tr>
					<th>Nombre</th>
    				<th>Tipo</th>
                    <th style="width: 10%;">Código</th>
    			</tr>
    		</thead>
    		<tbody id="tbody-escenario-table">
		 		@if ($data->presupuesto_escenarios ?? '') 
				@if (count($data->presupuesto_escenarios) > 0)
					@foreach (old('tasa', $data->presupuesto_escenarios->count() ? $data->presupuesto_escenarios : ['']) as $escenario)
            			<tr class="item-escenario">
                            <td>
                                <input type="hidden" name="escenario[]" class="form-control iiescenario" readonly value="{{ $loop->index+1 }}" />
                                <input type="text" name="nombres[]" class="form-control" value="{{old('nombreescenario', $escenario->nombre ?? '')}}"/>
                            </td>
							<td>
                                <select name="tipos[]" data-placeholder="Tipo de Escenario" class="tipo form-control required" required data-fouc>
                                    <option value="">-- Elija tipo --</option>
                                    @foreach ($tipo_enum as $value => $tipo)
                                        <option value="{{ $tipo }}"
                                            @if (old('tipo', $escenario->tipo ?? '') == $tipo) selected @endif
                                        >{{ $tipo }}</option>
                                    @endforeach
                                </select>     
							</td>
							<td>
                                <input type="text" name="codigos[]" class="form-control" value="{{old('codigoescenario', $escenario->codigo ?? '')}}" readonly/>
							</td>                                                      
                			<td>
								<button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_escenario tooltipsC">
                            		<i class="fa fa-times-circle text-danger"></i>
								</button>
								<input type="hidden" name="creousuario_escenario_ids[]" class="form-control creousuario_escenario_id" value="{{ $escenario->creousuario_id ?? ''}}"/>
                			</td>
                		</tr>
           			@endforeach
				@endif
				@endif
       		</tbody>
       	</table>
		@include('presupuesto.presupuesto.template')
        <div class="row">
        	<div class="col-md-12">
        		<button id="agrega_renglon_escenario" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
        	</div>
        </div>
    </div>    
</div>
