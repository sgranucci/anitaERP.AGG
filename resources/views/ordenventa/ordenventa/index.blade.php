@extends("theme.$theme.layout")
@section('titulo')
    Ordenes de Venta
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>

<script>
    function eliminarOrdenventa(event) {
        var opcion = confirm("Desea eliminar la orden de venta?");
        if(!opcion) {
            event.preventDefault();
        }
    }
</script>

@endsection

<?php use App\Helpers\biblioteca ?>

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Ordenes de Venta</h3>
                <div class="card-tools">
                    <a href="{{route('crea_ordenventa')}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('ingresar-orden-de-venta', false))
                        	<i class="fa fa-fw fa-plus-circle"></i> Nuevo registro
						@endif
                    </a>
                </div>
                <div class="d-md-flex justify-content-md-end">
					<form action="{{ route('consulta_ordenventa') }}" method="GET">
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
                @include('includes.exportar-tabla', ['ruta' => 'lista_ordenventa', 'busqueda' => $busqueda])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th>Fecha</th>
                            <th>Nro. Orden Vta.</th>
                            <th>Empresa</th>
                            <th>Tratamiento</th>
                            <th>Centro de Costo</th>
                            <th>Cliente</th>
                            <th>Monto</th>
                            <th>Moneda</th>
                            <th>Estado</th>
                            <th>Detalle</th>
                            <th class="width40" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($ordenventa as $data)
                        <tr>
                            <td>{{$data->id}}</td>
                            <td>{{date("d/m/Y", strtotime($data->fecha ?? ''))}}</td>
                            <td>{{$data->numeroordenventa ?? ''}}</td>
                            <td>{{$data->nombreempresa ?? ''}}</td>
                            <td>{{$data->tratamiento ?? ''}}</td>
                            <td>{{$data->nombrecentrocosto ?? '' }}</td>
                            <td>{{$data->nombrecliente ?? ''}}</td>
                            <td>{{number_format($data->monto,2) ?? ''}}</td>
                            <td>{{$data->abreviaturamoneda}}</td>
                            <td>{{$data->estado}}</td>
                            <td>{{$data->detalle}}</td>
                            <td>
                       			@if (can('editar-orden-de-venta', false))
                                	<a href="{{route('edita_ordenventa', ['id' => $data->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-orden-de-venta', false))
                                <form action="{{route('elimina_ordenventa', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
                                    @csrf @method("delete")
                                    <button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar este registro">
                                        <i class="fa fa-times-circle text-danger"></i>
                                    </button>
                                </form>
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
{{ $ordenventa->appends(['busqueda' => $busqueda])->links() }}
@endsection
