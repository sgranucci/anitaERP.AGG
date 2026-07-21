<div id="tab2" class="form2 tab-content" style="display: none">    
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
    				<label for="unidadmedida2_id" class="col-lg-4 col-form-label requerido">Unidad de medida</label>
					<div class="col-lg-4">
					<select id="unidadmedida2_id" name="unidadmedida2_id" class="form-control unidadmedida" required>
                        <option value="">-- Seleccionar --</option>
                        @foreach($unidadmedida as $key => $value)
                            @if( isset($producto) && (int) $value->id == (int) old('unidadmedida_id', $producto->unidadmedida_id ?? ''))
                                <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
                            @else
                            	@if( !isset($producto) && (int) $value->abreviatura == "PAR" )
                                	<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
								@else
                                	<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                            	@endif
                            @endif
                        @endforeach
                    </select>
                    </div>
                </div>
				<div class="form-group row">
    				<label for="unidadmedidaalternativa2_id" class="col-lg-4 col-form-label requerido">Unidad de medida alternativa</label>
					<div class="col-lg-4">
					<select id="unidadmedidaalternativa2_id" name="unidadmedidaalternativa2_id" class="form-control unidadmedidaalternativa" required>
                        <option value="">-- Seleccionar --</option>
                        @foreach($unidadmedida as $key => $value)
                            @if( isset($producto) && (int) $value->id == (int) old('unidadmedidaalternativa_id', $producto->unidadmedidaalternativa_id ?? ''))
                                <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
                            @else
                            	@if( !isset($producto) && (int) $value->abreviatura == "PAR" )
                                	<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
								@else
                                	<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                            	@endif
                            @endif
                        @endforeach
                    </select>
                    </div>
                </div>   
                @if (config('app.empresa') == 'FRASLE')                       
                    <div class="form-group row">
                        <label for="tipoproducto_id" class="col-lg-4 col-form-label">Tipo de producto</label>
                        <div class="col-lg-8">
                            <select id="tipoproducto_id" name="tipoproducto_id" class="form-control">
                                <option value="">-- Seleccionar --</option>
                                @foreach($tipoproducto_query as $key => $value)
                                    @if( isset($producto) && (int) $value->id == (int) old('tipoproducto_id', $producto->tipoproducto_id ?? ''))
                                        <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
                                    @else
                                        <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                                    @endif
                                @endforeach
                            </select>
                        </div>
                    </div>
                @endif
				@if (config('app.empresa') == 'FRASLE')
					<div class="form-group row">
						<label for="capacidad_id" class="col-lg-4 col-form-label">Capacidad</label>
						<div class="col-lg-8">
							<select id="capacidad_id" name="capacidad_id" class="form-control">
								<option value="">-- Seleccionar --</option>
								@foreach($capacidad_query as $key => $value)
									@if( isset($producto) && (int) $value->id == (int) old('capacidad_id', $producto->capacidad_id ?? ''))
										<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
									@else
										<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
									@endif
								@endforeach
							</select>
						</div>
					</div>
				@endif                             
            </div>
            <div class="col-sm-6">
                <div class="form-group row">
    				<label for="skualternativo" class="col-lg-4 col-form-label" title="SKU alternativo / insumo">SKU Alt./Insumo</label>
    				<div class="col-lg-5">
    					<input type="text" name="skualternativo" id="skualternativo" class="form-control" value="{{old('skualternativo', $producto->skualternativo ?? '')}}"/>
                	</div>
                    @php
                        $articuloEsCompraConInsumo = isset($producto)
                            && \App\Support\Stock\RecepcionProveedorDepositoSupport::resolverArticuloInsumo($producto) !== null;
                        $mostrarBtnArticulosCompraInsumo = isset($producto)
                            && ! $articuloEsCompraConInsumo
                            && (can('listar-formula-articulo', false) || can('editar-formula-articulo', false) || can('listar-articulos', false));
                    @endphp
                    @if ($mostrarBtnArticulosCompraInsumo)
                    <div class="col-lg-3 d-flex align-items-center">
                        <button type="button"
                            id="btn-articulos-compra-insumo-articulo"
                            class="btn btn-outline-info btn-sm tooltipsC"
                            title="Art&iacute;culos de compra con SKU alt./insumo apuntando a este insumo">
                            <i class="fa fa-shopping-cart"></i> Art. compra
                        </button>
                    </div>
                    @endif
                </div>
                <div class="form-group row">
                    <div class="col-lg-4"></div>
                    <div class="col-lg-8">
                        <small class="form-text text-muted">Si este art&iacute;culo debe apuntar a un insumo, ingrese aqu&iacute; el SKU de ese insumo.</small>
                    </div>
                </div>
                <div class="form-group row">
    				<label for="unidadesxenvase" class="col-lg-4 col-form-label">Unidades x envase</label>
    				<div class="col-lg-2">
    					<input type="number" name="unidadesxenvase" id="unidadesxenvase" class="form-control" value="{{old('unidadesxenvase', $producto->unidadesxenvase ?? '')}}"/>
                	</div>

					<label for="peso" class="col-lg-2 col-form-label">Peso</label>
    				<div class="col-lg-2">
    					<input type="number" name="peso" id="peso" class="form-control" value="{{old('peso', $producto->peso ?? '')}}"/>
                	</div>
                </div>
                <div class="form-group row">
    				<label for="coeficienteconversion" class="col-lg-4 col-form-label">Coeficiente de Conversión</label>
    				<div class="col-lg-2">
    					<input type="number" name="coeficienteconversion" id="coeficienteconversion" class="form-control" value="{{old('coeficienteconversion', $producto->coeficienteconversion ?? '')}}" step="any" min="0"/>
                	</div>
                    @if (isset($producto) && (int) ($producto->id ?? 0) > 0 && \App\Support\Stock\TransferenciaMercaderiaRepararCostosSupport::puedeRecalcularDesdeArticulo())
                    <div class="col-lg-6 d-flex align-items-center">
                        <button type="button"
                            id="btn-recalcular-transferencias-formula"
                            class="btn btn-outline-warning btn-sm tooltipsC"
                            title="Recalcula cantidad y costo destino de TRA a depósito fórmulas con el coeficiente actual">
                            <i class="fa fa-sync"></i> Recalcular TRA fórmulas
                        </button>
                    </div>
                    @endif
                </div>    
                <div class="form-group row">
    				<label for="formula" class="col-lg-4 col-form-label">Fórmula (id Anita / ERP)</label>
    				<div class="col-lg-2">
    					<input type="number" name="formula" id="formula" class="form-control" value="{{old('formula', $producto->formula ?? '')}}"/>
                	</div>
                    <div class="col-lg-4 d-flex align-items-end">
                        @if (can('listar-formula-articulo', false) || can('listar-articulos', false))
                        <span class="d-inline-block ml-2 tooltipsC" id="tooltip-consulta-formula-articulo" title="Consultar fórmula vinculada a este artículo">
                            <button type="button" class="btn btn-outline-info btn-sm" id="btn-consulta-formula-articulo">
                                <i class="fa fa-flask"></i> Consultar fórmula
                            </button>
                        </span>
                        @endif
                    </div>
                </div>
                @if (can('listar-formula-articulo', false) || can('listar-articulos', false))
                <div class="form-group row">
                    <div class="col-lg-4"></div>
                    <div class="col-lg-8">
                        <small class="form-text text-muted">La consulta busca la fórmula por vínculo articulo_id en el CRUD de fórmulas; el número del campo solo se usa si coincide con este artículo.</small>
                    </div>
                </div>
                @endif

				@if (config('app.empresa') == 'FRASLE')
					<div class="form-group row">
						<label for="color_id" class="col-lg-4 col-form-label">Color</label>
						<div class="col-lg-8">
							<select id="color_id" name="color_id" class="form-control">
								<option value="">-- Seleccionar --</option>
								@foreach($color_query as $key => $value)
									@if( isset($producto) && (int) $value->id == (int) old('color_id', $producto->color_id ?? ''))
										<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>
									@else
										<option value="{{ $value->id }}">{{ $value->nombre }}</option>
									@endif
								@endforeach
							</select>
						</div>
					</div>
				@endif
				@if (config('app.empresa') == 'FRASLE')
					<div class="form-group row">
						<label for="tipoliquido_id" class="col-lg-4 col-form-label">Tipo l&iacute;quido</label>
						<div class="col-lg-8">
							<select id="tipoliquido_id" name="tipoliquido_id" class="form-control">
								<option value="">-- Seleccionar --</option>
								@foreach($tipoliquido_query as $key => $value)
									@if( isset($producto) && (int) $value->id == (int) old('tipoliquido_id', $producto->tipoliquido_id ?? ''))
										<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>
									@else
										<option value="{{ $value->id }}">{{ $value->nombre }}</option>
									@endif
								@endforeach
							</select>
						</div>
					</div>
				@endif
            </div>
        </div>
    </div>
</div>