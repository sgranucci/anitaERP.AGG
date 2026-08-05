<div id="tab4" class="form4 tab-content" style="display: none">    
    <div class="card-body">
        <div class="row">
            <div class="col-sm-6">
                <div class="form-group row">
    				<label for="sku" class="col-lg-4 col-form-label text-right pr-2 requerido">Sku</label>
    				<div class="col-lg-5">
    					<input type="text" name="sku" id="sku" class="form-control sku" value="{{old('sku', $producto->sku ?? '')}}" required readonly/>
                	</div>
                </div>
                <div class="form-group row">
    				<label for="sku" class="col-lg-4 col-form-label text-right pr-2 requerido">Descripci&oacute;n</label>
    				<div class="col-lg-8">
    					<input type="text" name="descripcion" id="descripcion" class="form-control descripcion" value="{{old('descripcion', $producto->descripcion ?? '')}}" required disabled/>
                	</div>
                </div>
				<div class="form-group row">
    				<label for="nofactura" class="col-lg-4 col-form-label text-right pr-2 requerido">Facturable</label>
					<div class="col-lg-8">
					<select id="nofactura" name="nofactura" class="form-control">
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
                <div class="form-group row">
                    <div class="col-lg-4 col-form-label text-right pr-2"></div>
                    <div class="col-lg-8">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="fl_precio_promedio_transferencia"
                                id="fl_precio_promedio_transferencia" value="1"
                                @if (old('fl_precio_promedio_transferencia', $producto->fl_precio_promedio_transferencia ?? false)) checked @endif>
                            <label class="form-check-label" for="fl_precio_promedio_transferencia">
                                Art&iacute;culo TITO (precio promedio 3 &uacute;lt. compras en TRCONT)
                            </label>
                        </div>
                        <small class="text-muted">Art&iacute;culos TITO: promedio de exactamente 3 recepciones COM en el ERP; si no hay 3, promedio Anita stkm_pre_compra1/2/3. Los dem&aacute;s contabilizables usan &uacute;ltima compra (ERP &rarr; Anita compra3).</small>
                    </div>
                </div>
            </div>
            <div class="col-sm-6">
				<div class="form-group row">
    				<label for="impuesto_id" class="col-lg-4 col-form-label text-right pr-2 requerido">Impuesto aplicado</label>
					<div class="col-lg-8">
					<select id="impuesto_id" name="impuesto_id" class="form-control">
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
              	</div>
                @if (strtoupper(config('app.empresa')) == 'CALZADOS FERLI S.A.')
                    <div class="form-group row">
                        <label for="nomenclador" class="col-lg-4 col-form-label text-right pr-2 requerido">Nomenclador</label>
                        <div class="col-lg-8">
                            <input type="text" name="nomenclador" id="nomenclador" class="form-control" value="{{old('nomenclador', $producto->nomenclador ?? '')}}" required/>
                        </div>
                    </div>
                @else
                    <div class="form-group row">
                        <label for="nomenclador" class="col-lg-4 col-form-label text-right pr-2">Nomenclador</label>
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
    		<thead style="background:#85C1E9;color:#17202A;">
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
								@include('includes.form-empresa-asignada-control', [
									'empresa_query' => $empresa_query,
									'empresa_id' => $cuentacontable->empresa_id ?? null,
									'name' => 'empresa_ids[]',
									'select_class' => 'empresa',
									'permite_vacio' => true,
									'opcion_vacia' => '-- Seleccionar --',
									'required' => false,
								])
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
								<button type="button" title="Replica la cuenta al resto de las empresas" class="btn-accion-tabla replicar_cuentacontable tooltipsC">
                            		<i class="fa fa-clone text-success"></i>
								</button>

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

