<div class="row">
    <div class="col-sm-6">
        <div class="form-group row">
            <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
            <div class="col-lg-4">
            <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
            </div>
        </div>
        <div class="form-group row">
            <label for="abreviatura" class="col-lg-3 col-form-label requerido">Abreviatura</label>
            <div class="col-lg-2">
            <input type="text" name="abreviatura" id="abreviatura" class="form-control" value="{{old('abreviatura', $data->abreviatura ?? '')}}" required/>
            </div>
        </div>
        <div class="form-group row">
            <label for="codigoafip" class="col-lg-3 col-form-label requerido">Tipo AFIP</label>
            <div class="col-lg-2">
            <input type="text" name="codigoafip" id="codigoafip" class="form-control" value="{{old('codigoafip', $data->codigoafip ?? '')}}" required/>
            </div>
        </div>
        <div class="form-group row">
            <label for="operacion" class="col-lg-3 col-form-label requerido">Operaci&oacute;n</label>
            <select name="operacion" class="col-lg-3 form-control" required>
                <option value="">-- Elija operaci&oacute;n --</option>
                @foreach($operacionEnum as $value => $operacion)
                    @if( $value == old('operacion', $data->operacion ?? ''))
                        <option value="{{ $value }}" selected="select">{{ $operacion }}</option>    
                    @else
                        <option value="{{ $value }}">{{ $operacion }}</option>    
                    @endif
                @endforeach
            </select>
        </div>
        <div class="form-group row">
            <label for="signo" class="col-lg-3 col-form-label requerido">Signo</label>
            <select name="signo" class="col-lg-3 form-control" required>
                <option value="">-- Elija signo --</option>
                @foreach($signoEnum as $value => $signo)
                    @if( $value == old('signo', $data->signo ?? ''))
                        <option value="{{ $value }}" selected="select">{{ $signo }}</option>    
                    @else
                        <option value="{{ $value }}">{{ $signo }}</option>    
                    @endif
                @endforeach
            </select>
        </div>
    </div>
    <div class="col-sm-6">
        <div class="form-group row">
            <label for="subdiario" class="col-lg-3 col-form-label requerido">Subdiario Iva</label>
            <select name="subdiario" class="col-lg-3 form-control" required>
                <option value="">-- Elija subdiario --</option>
                @foreach($subdiarioEnum as $value => $subdiario)
                    @if( $value == old('subdiario', $data->subdiario ?? ''))
                        <option value="{{ $value }}" selected="select">{{ $subdiario }}</option>    
                    @else
                        <option value="{{ $value }}">{{ $subdiario }}</option>    
                    @endif
                @endforeach
            </select>
        </div>
        <div class="form-group row">
            <label for="asientocontable" class="col-lg-3 col-form-label requerido">Asiento contable</label>
            <select name="asientocontable" class="col-lg-4 form-control" required>
                <option value="">-- Elija asiento contable --</option>
                @foreach($asientocontableEnum as $value => $asientocontable)
                    @if( $value == old('asientocontable', $data->asientocontable ?? ''))
                        <option value="{{ $value }}" selected="select">{{ $asientocontable }}</option>    
                    @else
                        <option value="{{ $value }}">{{ $asientocontable }}</option>    
                    @endif
                @endforeach
            </select>
        </div>
        <div class="form-group row">
            <label for="retieneganancia" class="col-lg-3 col-form-label requerido">Retiene ganancias</label>
            <select name="retieneganancia" class="col-lg-3 form-control" required>
                <option value="">-- Elija si retiene ganancias --</option>
                @foreach($retieneEnum as $value => $retieneganancia)
                    @if( $value == old('retieneganancia', $data->retieneganancia ?? ''))
                        <option value="{{ $value }}" selected="select">{{ $retieneganancia }}</option>    
                    @else
                        <option value="{{ $value }}">{{ $retieneganancia }}</option>    
                    @endif
                @endforeach
            </select>
        </div>
        <div class="form-group row">
            <label for="retieneiva" class="col-lg-3 col-form-label requerido">Retiene iva</label>
            <select name="retieneiva" class="col-lg-3 form-control" required>
                <option value="">-- Elija si retiene ganancias --</option>
                @foreach($retieneEnum as $value => $retieneiva)
                    @if( $value == old('retieneiva', $data->retieneiva ?? ''))
                        <option value="{{ $value }}" selected="select">{{ $retieneiva }}</option>    
                    @else
                        <option value="{{ $value }}">{{ $retieneiva }}</option>    
                    @endif
                @endforeach
            </select>
        </div>
        <div class="form-group row">
            <label for="retieneIIBB" class="col-lg-3 col-form-label requerido">Retiene IIBB</label>
            <select name="retieneIIBB" class="col-lg-3 form-control" required>
                <option value="">-- Elija si retiene ganancias --</option>
                @foreach($retieneEnum as $value => $retieneIIBB)
                    @if( $value == old('retieneIIBB', $data->retieneIIBB ?? ''))
                        <option value="{{ $value }}" selected="select">{{ $retieneIIBB }}</option>    
                    @else
                        <option value="{{ $value }}">{{ $retieneIIBB }}</option>    
                    @endif
                @endforeach
            </select>
        </div>
    </div>
    <div class="col-sm-12">
        <div class="form-group row">
            <label for="estado" class="col-lg-3 col-form-label requerido">Estado</label>
            <select name="estado" class="col-lg-3 form-control" required>
                <option value="">-- Elija estado --</option>
                @foreach($estadoEnum as $value => $estado)
                    @if( $value == old('estado', $data->estado ?? ''))
                        <option value="{{ $value }}" selected="select">{{ $estado }}</option>    
                    @else
                        <option value="{{ $value }}">{{ $estado }}</option>    
                    @endif
                @endforeach
            </select>
        </div>
    </div>
	<div class="card-body">
    	<table class="table" id="centrocosto-table">
    		<thead>
    			<tr>
					<th style="width: 10%;"></th>
    				<th style="width: 50%;">Centro de Costo</th>
    				<th></th>
				</tr>
    		</thead>
    		<tbody id="tbody-centrocosto-table">
			@if ($data->tipotransaccion_compra_centrocostos ?? '') 
				@foreach (old('centrocostos', $data->tipotransaccion_compra_centrocostos->count() ? $data->tipotransaccion_compra_centrocostos : ['']) as $centrocosto)
					<tr class="item-centrocosto">
						<td>
							<input type="text" name="centrocostos[]" class="form-control iicentrocosto" readonly value="{{ $loop->index+1 }}" />
						</td>
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
		@include('compras.tipotransaccion_compra.template1')
        <div class="row">
        	<div class="col-md-12">
        		<button id="agrega_renglon_centrocosto" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
        	</div>
        </div>
	</div>
	<div class="card-body">
    	<table class="table" id="concepto_ivacompra-table">
    		<thead>
    			<tr>
					<th style="width: 10%;"></th>
    				<th style="width: 50%;">Conceptos de Iva Compras</th>
    				<th></th>
				</tr>
    		</thead>
    		<tbody id="tbody-concepto-ivacompra-table">
			@if ($data->tipotransaccion_compra_concepto_ivacompras ?? '') 
				@foreach (old('concepto_ivacompras', $data->tipotransaccion_compra_concepto_ivacompras->count() ? $data->tipotransaccion_compra_concepto_ivacompras : ['']) as $concepto)
					<tr class="item-concepto_ivacompra">
						<td>
							<input type="text" name="concepto_ivacompras[]" class="form-control iiconcepto_ivacompra" readonly value="{{ $loop->index+1 }}" />
						</td>
						<td>
							<select name="concepto_ivacompra_ids[]" id="concepto_ivacompra_ids" data-placeholder="Concepto de iva compras" class="form-control concepto_ivacompra" data-fouc>
								<option value="">-- Elija concepto iva compras --</option>
                                @foreach($concepto_ivacompra_query as $key => $value)
                                    @if( (int) $value->id == (int) old('concepto_ivacompra_id', $concepto->concepto_ivacompra_id ?? ''))
                                        <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
                                    @else
                                        <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                                    @endif
                                @endforeach
							</select>
						</td>
						<td>
							<button style="width: 7%;" type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_concepto_ivacompra tooltipsC">
								<i class="fa fa-times-circle text-danger"></i>
							</button>
						</td>
					</tr>
				@endforeach
			@endif
			</tbody>
		</table>
		@include('compras.tipotransaccion_compra.template2')
        <div class="row">
        	<div class="col-md-12">
        		<button id="agrega_renglon_concepto_ivacompra" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
        	</div>
        </div>
	</div>
