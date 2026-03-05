<div class="form4" style="display: none">    
    <div class="card-body">
        <div class="row">
            <div class="col-sm-6">
                <div class="form-group row">
    				<label for="sku" class="col-lg-4 col-form-label requerido">Sku</label>
    				<div class="col-lg-5">
    					<input type="text" name="sku" id="sku" class="form-control sku" value="{{old('sku', $producto->sku ?? '')}}" required readonly/>
                	</div>
                </div>
                <div class="form-group row">
    				<label for="sku" class="col-lg-4 col-form-label requerido">Descripci&oacute;n</label>
    				<div class="col-lg-8">
    					<input type="text" name="descripcion" id="descripcion" class="form-control descripcion" value="{{old('descripcion', $producto->descripcion ?? '')}}" required disabled/>
                	</div>
                </div>
				<div class="form-group row">
    				<label for="nofactura" class="col-lg-4 col-form-label requerido">Facturable</label>
					<select id="nofactura" name="nofactura" class="col-lg-8 form-control">
                        <option value="">-- Seleccionar --</option>
                        @foreach($nofactura_enum as $key => $value)
                            @if( isset($producto) && (int) $value['id'] == (int) old('nofactura', $producto->nofactura ?? ''))
                                <option value="{{ $value['id'] }}" selected="select">{{ $value['nombre'] }}</option>    
                            @else
                                <option value="{{ $value['id'] }}">{{ $value['nombre'] }}</option>    
                            @endif
                        @endforeach
                    </select>
              	</div>				
            </div>
            <div class="col-sm-6">
				<div class="form-group row">
    				<label for="impuesto_id" class="col-lg-4 col-form-label requerido">Impuesto aplicado</label>
					<select id="impuesto_id" name="impuesto_id" class="col-lg-8 form-control">
                        <option value="">-- Seleccionar --</option>
                        @foreach($codimp as $key => $value)
                            @if( isset($producto) && (int) $value->id == (int) old('impuesto_id', $producto->impuesto_id ?? ''))
                                <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
                            @else
                                <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                            @endif
                        @endforeach
                    </select>
              	</div>
                @if (strtoupper(config('app.empresa')) == 'CALZADOS FERLI S.A.')
                    <div class="form-group row">
                        <label for="nomenclador" class="col-lg-4 col-form-label requerido">Nomenclador</label>
                        <div class="col-lg-8">
                            <input type="text" name="nomenclador" id="nomenclador" class="form-control" value="{{old('nomenclador', $producto->nomenclador ?? '')}}" required/>
                        </div>
                    </div>
                @else
                    <div class="form-group row">
                        <label for="nomenclador" class="col-lg-4 col-form-label">Nomenclador</label>
                        <div class="col-lg-8">
                            <input type="text" name="nomenclador" id="nomenclador" class="form-control" value="{{old('nomenclador', $producto->nomenclador ?? '')}}"/>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
    <div class="card-body">
    	<table class="table" id="cuentacontable-table">
    		<thead>
    			<tr>
					<th>Empresa</th>
                    <th>Tipo Imputación</th>
    				<th>Cuenta Contable</th>
    			</tr>
    		</thead>
    		<tbody id="tbody-cuentacontable-table">
				@if ($producto->articulo_cuentacontables ?? '')
				@if (count($producto->articulo_cuentacontables) > 0)
					@foreach (old('tasa', $producto->articulo_cuentacontables->count() ? $producto->articulo_cuentacontables : ['']) as $cuentacontable)
            			<tr class="item-cuentacontable">
							<td>
								<select name="empresa_ids[]" data-placeholder="Empresa" class="empresa form-control" data-fouc>
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
                                <select name="tipoimputaciones[]" data-placeholder="Tipo de Imputación" class="tipoimputacion form-control required" required data-fouc>
                                    <option value="">-- Seleccionar --</option>
										@foreach($tipoimputacion_enum as $tipoimputacion)
											@if ($tipoimputacion['nombre'] == old('tipocuenta',$cuentacontable->tipoimputacion??''))
												<option value="{{ $tipoimputacion['nombre'] }}" selected>{{ $tipoimputacion['nombre'] }}</option>    
											@else
												<option value="{{ $tipoimputacion['nombre'] }}">{{ $tipoimputacion['nombre'] }}</option>
											@endif
										@endforeach		
                                </select>
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
								<button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_cuentacontable tooltipsC">
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
		@include('stock.articulo.template4')
        <div class="row">
        	<div class="col-md-12">
        		<button id="agrega_renglon_cuentacontable" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
        	</div>
        </div>
    </div>    
</div>

