@extends("theme.$theme.layout")
@section('titulo')
    Vouchers
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>

<script>
    function eliminarVoucher(event) {
        var opcion = confirm("Desea eliminar el pedido?");
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
                <h3 class="card-title">Vouchers</h3>
                <div class="card-tools">
                    <a href="{{route('crear_voucher')}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('crear-voucher', false))
                        	<i class="fa fa-fw fa-plus-circle"></i> Nuevo registro
						@endif
                    </a>
                </div>
                <div class="d-md-flex justify-content-md-end">
					<form action="{{ route('voucher') }}" method="GET">
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
                @include('includes.exportar-tabla', ['ruta' => 'lista_voucher', 'busqueda' => $busqueda])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th>Número</th>
                            <th>Fecha</th>
                            <th>Talonario Vouchers</th>
                            <th>Cantidad</th>
                            <th>Proveedor</th>
                            <th>Servicio</th>
                            <th>Monto Voucher</th>
                            <th>Guías</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($vouchers as $data)
                        <tr>
                            <td>{{$data->id}}</td>
                            <td>{{$data->idtalonario}}-{{$data->numerovoucher}}</td>
                            <td>{{date("d/m/Y", strtotime($data->fecha ?? ''))}}</td>
                            <td>{{$data->nombretalonario}}</td>
                            <td>{{$data->pax}}</td>
                            <td>{{$data->nombreproveedor ?? ''}}</td>
                            <td>{{$data->nombreservicio ?? ''}}</td>
                            <td>{{number_format($data->montovoucher,2)}}</td>
                            <td>
                                <ul>
                                @foreach($data->voucher_guias as $guia)
                                    <li>{{ $guia->guias->nombre }} Porc. {{ number_format($guia->porcentajecomision,2) }} Comis. {{ number_format($guia->montocomision,2) }} OS {{ $guia->ordenservicio_id }}</li>
                                @endforeach
                                </ul>
                            </td>
                            <td>
                       			@if (can('editar-voucher', false))
                                	<a href="{{route('editar_voucher', ['id' => $data->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-voucher', false))
                                <form action="{{route('eliminar_voucher', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
                                    @csrf @method("delete")
                                    <button type="submit" onclick="eliminarVoucher(event)" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar este registro">
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
{{ $vouchers->appends(['busqueda' => $busqueda])->links() }}
@endsection
