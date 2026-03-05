@extends("theme.$theme.layout")
@section('titulo')
Comprobantes de Venta
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/excellentexport@3.4.3/dist/excellentexport.min.js"></script>
@endsection

<?php use App\Helpers\biblioteca ?>

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Comprobantes de venta</h3>

				<div class="card-tools">
					<a href="{{route('crear_factura')}}" class="btn btn-outline-secondary btn-gt">
                       	@if (can('crear-movimientos-de-stock', false))
                        	<i class="fa fa-fw fa-plus-circle"></i> Nuevo comprobante
						@endif
                    </a>
                </div>
				<div class="d-md-flex justify-content-md-end">
					<form action="{{ route('factura') }}" method="GET">
						<div class="btn-group">
							<input type="text" name="busqueda" class="form-control" placeholder="Busqueda ..."> 
							<button type="submit" class="btn btn-default">
								<span class="fa fa-search"></span>
							</button>
						</div>
					</form>
                </div>
            </div>
            <div class="card-body table-responsive p-0">
				@include('includes.exportar-tabla', ['ruta' => 'listar_factura', 'busqueda' => $busqueda])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th>Fecha</th>
							<th>Comprobante</th>
							<th>Cliente</th>
							<th>Empresa</th>
							<th>Total</th>
                            <th data-orderable="false">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
						@foreach($ventas as $comprobante)
    						<tr data-entry-id="{{ $comprobante->id }}">
        						<td>
            						{{ $comprobante->id ?? '' }}
        						</td>
								<td>
            						{{date("d/m/Y", strtotime($comprobante->fecha ?? ''))}} 
        						</td>
								<td>
									{{ $comprobante->tipotransacciones->nombre ?? '' }}&nbsp;
									{{ $comprobante->clientes->condicionivas->letra ?? '' }}
									{{ $comprobante->puntoventas->codigo }}-{{ $comprobante->numerocomprobante }}
        						</td>
        						<td>
									{{ $comprobante->clientes->nombre ?? '' }}
        						</td>
								<td>{{ $comprobante->puntoventas->empresas->nombre ?? '' }}</td>
								<td>
									{{ number_format($comprobante->total, 2) }}
								</td>
        						<td>
                       			@if (can('editar-factura', false))
                                	<a href="{{route('editar_factura', ['id' => $comprobante->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                   	<i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('generar-nota-de-credito', false))
									@if ($comprobante->total > 0)
                                		<a href="{{route('generar_notadecredito', ['id' => $comprobante->id])}}" class="btn-accion-tabla tooltipsC" title="Generar nota de crédito">
                                   		<i class="fa fa-undo text-danger"></i>
                                		</a>
									@endif
								@endif
                       			@if (can('listar-factura', false))
                                	<a href="{{route('lista_una_factura', ['id' => $comprobante->id])}}" class="btn-accion-tabla tooltipsC" title="Listar el Comprobante de Venta">
                                   	<i class="fa fa-print"></i>
                                	</a>
								@endif
                            	</td>
                        	</tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
{{ $ventas->appends(['busqueda' => $busqueda]) }}
@endsection
