<div class="row">
	<div class="col-sm-6" id="datosfactura" data-puntoventa="{{$puntoventa_query}}" data-tipotransaccion="{{$tipotransaccion_query}}" data-incoterm="{{$incoterm_query}}" data-formapago="{{$formapago_query}}" data-transporte="{{$transporte_query}}">
        <input type="hidden" id="codigoordentrabajo" class="form-control" value="{{old('codigoordentrabajo', $ordentrabajo->codigo ?? '')}}" />
        <input type="hidden" id="ordentrabajo_id" class="form-control" value="{{old('ordentrabajo_id', $ordentrabajo->id ?? '')}}" />
		<input type="hidden" id="puntoventadefault_id" class="form-control" value="{{$puntoventadefault_id}}" />
		<input type="hidden" id="puntoventaremitodefault_id" class="form-control" value="{{$puntoventaremitodefault_id}}" />
		<input type="hidden" id="transportedefault_id" class="form-control" value="{{$transporte_id}}" />
		<input type="hidden" id="tipotransacciondefault_id" class="form-control" value="{{$tipotransacciondefault_id}}" />
		<input type="hidden" id="csrf_token" class="form-control" value="{{csrf_token()}}" />
		<div class="form-group row" id="marca">
   			<label for="mventa" class="col-lg-3 col-form-label text-right pr-2">Marca</label>
        	<select name="mventa_id" id="mventa_id" data-placeholder="Marca de Venta" class="col-lg-8 form-control required" data-fouc>
        		<option value="">-- Seleccionar marca --</option>
        		@foreach($mventa_query as $key => $value)
        			@if( (int) $value->id == (int) old('mventa_id', $mventa_id ?? ''))
        				<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
        			@else
        				<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
        			@endif
        		@endforeach
        	</select>
		</div>
        <div class="form-group row">
    		<label for="articulo_id" class="col-lg-3 col-form-label text-right pr-2">Art&iacute;culo</label>
			<select name="articulo_id" class="col-lg-8 form-control articulo required">
           		<option value="">-- Elija art&iacute;culo --</option>
           		@foreach ($articulo_query as $articulo)
        			@if( (int) $articulo['id'] == (int) old('articulo_id', $articulo_id ?? ''))
           				<option value="{{ $articulo['id'] }}" selected="select">{{ $articulo['descripcion'] }}</option>
					else
           				<option value="{{ $articulo['id'] }}">{{ $articulo['descripcion'] }}</option>
        			@endif
           		@endforeach
        	</select>
		</div>
        <div class="form-group row">
    		<label for="combinacion" class="col-lg-3 col-form-label text-right pr-2">Combinaci&oacute;n</label>
        	<select id="combinacion_id" name="combinacion_id" data-placeholder="Combinaciones" class="col-lg-8 form-control combinacion" data-fouc>
           		<option value="">-- Elija combinaci&iacute;n --</option>
           		@foreach ($combinacion_query as $combinacion)
        			@if( (int) $combinacion['id'] == (int) old('combinacion_id', $combinacion_id ?? ''))
           				<option value="{{ $combinacion['id'] }}" selected="select">{{ $combinacion['nombre'] }}</option>
					else
           				<option value="{{ $combinacion['id'] }}">{{ $combinacion['nombre'] }}</option>
        			@endif
           		@endforeach
        	</select>
        </div>
	</div>
	<div class="col-sm-6">
		<div class="form-group row">
    		<label for="fecha" class="col-lg-3 col-form-label text-right pr-2">Fecha</label>
    		<div class="col-lg-5">
    			<input type="date" name="fecha" id="fecha" class="form-control" value="{{substr(old('fecha', $ordentrabajo->fecha ?? date('Y-m-d')),0,10)}}" required>
    		</div>
		</div>
		<div class="form-group row">
    		<label for="estado" class="col-lg-3 col-form-label text-right pr-2">Estado</label>
    		<div class="col-lg-5">
    			<input type="text" name="estado" id="estado" class="form-control" value="{{$ordentrabajo->estado}}" readonly>
    		</div>
		</div>
		<div class="form-group row">
    		<label for="tipoot" class="col-lg-3 col-form-label text-right pr-2">Tipo de OT</label>
    		<div class="col-lg-5">
    			<input type="text" name="tipoot" id="tipoot" class="form-control" value="{{$ordentrabajo->tipoot}}" readonly>
    		</div>
		</div>
	</div>
</div>

<div class="card card-outline card-info">
<div class="row">
    <div class="card-body col-sm-7">
    	<table class="table table-sm table-bordered" id="itemsordentrabajo-table">
    		<thead style="background:#85C1E9;color:#17202A;">
    			<tr>
    				<th>Cliente</th>
    				<th>Pedido</th>
					<th>Item ID</th>
    				<th>Art&iacute;culo</th>
					<th>Combinaci&oacute;n</th>
    				<th class="text-right" style="width: 10%;">Pares</th>
    				<th class="text-nowrap">Acciones</th>
    			</tr>
    		</thead>
    		<tbody id="tbody-tabla">
		 		@if ($data ?? '') 
					@foreach ($data as $ordentrabajoitem)
						@php
							$esPicking = ! empty($ordentrabajoitem['es_picking']);
						@endphp
						<tr class="item-ordentrabajo{{ $esPicking ? ' fila-picking' : '' }}">
							<td>
								@unless ($esPicking)
									<input type="hidden" name="ids[]" class="form-control id" value="{{ $ordentrabajoitem['id'] }}">
								@endunless
								<input type="text" @unless($esPicking) name="clientes[]" @endunless class="form-control cliente" value="{{ $ordentrabajoitem['cliente'] }}" readonly>
								<input type="hidden" @unless($esPicking) name="cliente_ids[]" @endunless class="form-control cliente_id" value="{{ $ordentrabajoitem['cliente_id'] }}">
								<input type="hidden" @unless($esPicking) name="estadoclientes[]" @endunless class="form-control estadocliente" value="{{ $ordentrabajoitem['estadocliente'] }}">
								<input type="hidden" @unless($esPicking) name="nombretiposuspensionclientes[]" @endunless class="form-control nombretiposuspensioncliente" value="{{ $ordentrabajoitem['nombretiposuspensioncliente'] }}">
								<input type="hidden" @unless($esPicking) name="tiposuspensioncliente_ids[]" @endunless class="form-control tiposuspensioncliente_id" value="{{ $ordentrabajoitem['tiposuspensioncliente_id'] }}">
								<input type="hidden" class="form-control politica_comercial" value='@json($ordentrabajoitem["politica_comercial"] ?? [])'>
							</td>
							<td>
								<input type="text" @unless($esPicking) name="codigos[]" @endunless class="form-control codigo" value="{{ $ordentrabajoitem['codigo'] }}" readonly>
								<input type="hidden" @unless($esPicking) name="descuentopies[]" @endunless class="form-control descuentopie" value="{{ $ordentrabajoitem['descuentopie'] }}">
							</td>
							<td>
								<input type="text" @unless($esPicking) name="idpedido[]" @endunless class="form-control idpedido" value="{{ $ordentrabajoitem['pedidocombinacion_id'] }}" readonly>
							</td>							
							<td>
								<input type="hidden" @unless($esPicking) name="articulo_ids[]" @endunless class="form-control articulo_id" value="{{ $ordentrabajoitem['articulo_id'] }}">
								<input type="text" @unless($esPicking) name="articulos[]" @endunless class="form-control articulo" value="{{ $ordentrabajoitem['articulo'] }}" readonly>
								<input type="hidden" @unless($esPicking) name="skus[]" @endunless class="form-control sku" value="{{ $ordentrabajoitem['sku'] }}">
							</td>
							<td>
								<input type="hidden" @unless($esPicking) name="combinacion_ids[]" @endunless class="form-control combinacion_id" value="{{ $ordentrabajoitem['combinacion_id'] }}">
								<input type="text" @unless($esPicking) name="combinaciones[]" @endunless class="form-control nombre_combinacion" value="{{ $ordentrabajoitem['nombre_combinacion'] }}" readonly>
							</td>
							<td>
								<input type="hidden" @unless($esPicking) name="modulos[]" @endunless class="form-control modulo_id" value="{{ $ordentrabajoitem['modulo_id'] }}" />
								<input type="hidden" @unless($esPicking) name="medidas[]" @endunless class="form-control medidas" value="{{ json_encode($ordentrabajoitem['medidas']) }}" />
								@if (isset($ordentrabajoitem['pares_fabricacion']))
									<input type="hidden" name="pares[]" class="pares-fabricacion" value="{{ number_format($ordentrabajoitem['pares_fabricacion'], 0, '.', '') }}" />
									<input type="text" class="form-control pares" readonly value="{{ number_format($ordentrabajoitem['pares'], 0, '.', '') }}" title="{{ $ordentrabajoitem['pares_titulo'] ?? '' }}" />
								@else
									<input type="text" @unless($esPicking) name="pares[]" @endunless class="form-control pares" readonly value="{{ number_format($ordentrabajoitem['pares'], 0, '.', '') }}" title="{{ $ordentrabajoitem['pares_titulo'] ?? '' }}" />
								@endif
								@if (! empty($ordentrabajoitem['pares_titulo']))
									<small class="text-muted d-block">{{ $ordentrabajoitem['pares_titulo'] }}</small>
								@endif
							</td>
							<td>
							@unless ($esPicking)
							@if (can('editar-ordenes-de-trabajo', false))
                               	<a href="#" class="btn-accion-tabla tooltipsC editar" title="Editar el Pedido">
                                	<i class="fa fa-edit"></i>
								</a>
							@endif
							@if (can('facturar-ordenes-de-trabajo', false))
                                <a href="#" class="btn-accion-tabla tooltipsC facturar" title="Facturar el Pedido">
                                   	<i class="fa fa-calculator"></i>
								</a>
							@endif
							@if (can('empacar-ordenes-de-trabajo', false))
								<a href="#" class="btn-accion-tabla tooltipsC botonempacar" style="color:green;" title="Empacar la OT">
                                   	<i class="fa fa-archive"></i>
                               	</a>
							@endif
							@endunless
							@if ($esPicking)
								@php
									$pickingCodigoAccion = (int) ($ordentrabajoitem['picking_codigo'] ?? 0);
									$pickingIdAccion = (int) ($ordentrabajoitem['picking_id'] ?? 0);
									$puedeAbrirPicking = $pickingCodigoAccion > 0
										&& \App\Support\Ventas\PedidoPickingFerliSupport::habilitado()
										&& can('listar-reporte-picking-pedido', false);
								@endphp
								@if ($puedeAbrirPicking)
									<a href="{{ route('picking_pedido', [
										'consultar' => 1,
										'estado' => 'todos',
										'picking_codigo' => $pickingCodigoAccion,
										'picking_id' => $pickingIdAccion > 0 ? $pickingIdAccion : null,
									]) }}"
									   class="btn-accion-tabla tooltipsC"
									   title="Abrir picking #{{ $pickingCodigoAccion }}"
									   target="_blank"
									   rel="noopener">
										<i class="fa fa-dolly"></i>
									</a>
								@endif
							@endif
							</td>
						</tr>
					@endforeach
				@endif
       		</tbody>
       	</table>
	</div>
    <div class="card-body col-sm-5">
        @php
            $mostrarQuitarTerminadaStock = can('editar-ordenes-de-trabajo', false)
                && app(\App\Support\Ventas\QuitarTerminadaStockOtSupport::class)->sePuedeQuitar($ordentrabajo);
        @endphp
        @if ($mostrarQuitarTerminadaStock)
            <div class="alert alert-warning py-2 px-3">
                Esta OT figura como <strong>Terminada stock</strong> y sigue en fabricación.
                <button type="submit" form="form-quitar-terminada-stock" class="btn btn-outline-danger btn-sm ml-2">
                    Quitar Terminada stock
                </button>
            </div>
        @endif
        <div class="d-flex justify-content-end mb-2">
            <a href="{{ route('consultamovimientoordentrabajo', ['id' => $ordentrabajo->id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}" class="btn btn-outline-info btn-sm" target="_blank" rel="noopener">
                <i class="fa fa-fw fa-file"></i> Ver movimientos
            </a>
        </div>
    	<table class="table table-sm table-bordered table-hover" id="tareasordentrabajo-table">
    		<thead style="background:#85C1E9;color:#17202A;">
    			<tr>
					<th style="width: 10%;">Id</th>
    				<th style="width: 30%;">Tarea</th>
					<th style="width: 20%;">Empleado</th>
    				<th style="width: 12%;">Inicio</th>
    				<th style="width: 12%;">Fin</th>
    			</tr>
    		</thead>
    		<tbody id="tbody-tareas">
		 		@if ($ordentrabajo->ordentrabajo_tareas ?? '') 
					@foreach (old('items', $ordentrabajo->ordentrabajo_tareas->count() ? $ordentrabajo->ordentrabajo_tareas : ['']) as $ordentrabajotarea)
            			<tr class="item-tarea">
							<td>
								<input style="width: 100%;" class="pedido_id" type="text" class="id" value="{{old('id', $ordentrabajotarea->pedido_combinacion_id ?? '')}}" readonly>
        					</td>
                			<td>
								<input type="hidden" class="tarea_id" value="{{old('tarea_id', $ordentrabajotarea->tareas->id ?? '')}}">
								<input style="width: 100%;" type="text" class="tarea" value="{{old('tarea', $ordentrabajotarea->tareas->nombre ?? '')}}" readonly>
        					</td>
							<td>
								<input style="width: 100%;" type="text" class="empleado" value="{{old('empleado', $ordentrabajotarea->empleados->nombre ?? '')}}" readonly>
        					</td>

                			<td>
        						<input style="width: 100%;" type="date" class="desdefecha" value="{{old('desdefecha', $ordentrabajotarea->desdefecha ?? '')}}" readonly>
							</td>
                			<td>
        						<input style="width: 100%;" type="date" class="hastafecha" value="{{old('hastafecha', $ordentrabajotarea->hastafecha ?? '')}}" readonly>
							</td>
						</tr>
					@endforeach
				@endif
    		</tbody>
		</table>
	</div>
		<div class="row">
           	<div class="col-md-3"></div>
				<div class="col-sm-12">
                	<!-- textarea -->
                	<div class="form-group">
                		<label>Leyendas</label>
                		<textarea name="leyenda" class="form-control" rows="3" placeholder="Leyendas ...">{{old('leyenda', $ordentrabajo->leyenda ?? '')}}</textarea>
                	</div>
				</div>
            </div>
        </div>
</div>

@include('ventas.pedido.modal')
@include('ventas.ordentrabajo_ferli.modalfacturaordentrabajo')