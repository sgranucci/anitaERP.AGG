@extends("theme.$theme.layout")
@section('titulo')
    Precarga de Comprobantes de Proveedores
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
@endsection

<?php use App\Helpers\biblioteca ?>

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Precarga de Comprobantes de Proveedores</h3>
                <div class="card-tools">
                    <a href="{{route('crear_precarga_comprobante_proveedor')}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('crear-concepto-iva-compra', false))
                        	<i class="fa fa-fw fa-plus-circle"></i> Nuevo registro
						@endif
                    </a>
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla', ['ruta' => 'lista_precarga_comprobante_proveedor', 'busqueda' => $busqueda])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">                
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th>Empresa</th>
                            <th>Proveedor</th>
                            <th>Tipo de comprobante</th>
                            <th>Número</th>
                            <th>Fecha</th>
                            <th>Fecha Email</th>
                            <th>Número de OC</th>
                            <th>Total</th>
                            <th>Estado</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{$data->id}}</td>
                            <td>{{$data->empresas->nombre??''}}</td>
                            <td>{{$data->proveedores->nombre??''}}</td>
                            <td>{{$data->tipotransaccion_compras->nombre??''}}</td>
                            <td>{{$data->letra}}{{$data->sucursal}}-{{$data->numerocomprobante}}</td>
                            <td>{{$data->fechafactura}}</td>
                            <td>{{$data->fecharecepcionemail}}</td>
                            <td>{{$data->numeroordencompra ?? ''}}</td>
                            <td>{{$data->total}}</td>
                            <td>{{$data->estado}}</td>
                            <td>
                                @if (filled($data->rutaalmacenamiento) && puedeVerPrecargaFacturaPdf())
                                <a href="{{ urlAppCarpeta('compras/precarga_comprobante_proveedor/'.$data->id.'/factura-pdf?inline=1') }}"
                                   class="btn-accion-tabla tooltipsC"
                                   title="Ver PDF escaneado"
                                   target="_blank"
                                   rel="noopener noreferrer">
                                    <i class="fa fa-file-pdf-o text-danger"></i>
                                </a>
                                @endif
                       			@if (can('editar-precarga-proveedores', false))
                                	<a href="{{route('editar_precarga_comprobante_proveedor', ['id' => $data->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-precarga-proveedores', false))
                                <form action="{{route('eliminar_precarga_comprobante_proveedor', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
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
{{ $datas->appends(['busqueda' => $busqueda])->links() }}
@endsection
