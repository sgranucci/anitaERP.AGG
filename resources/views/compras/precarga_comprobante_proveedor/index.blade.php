@extends("theme.$theme.layout")
@section('titulo')
    Precarga de Comprobantes de Proveedores
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
@if (!empty($pdfIaHabilitado) && can('crear-precarga-proveedores', false))
<script src="{{ asset('assets/pages/scripts/compras/precarga_comprobante_proveedor/pdf_ia.js') }}" type="text/javascript"></script>
@endif
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
                    <a href="{{ route('comprobante_proveedor_opciones_carga') }}" class="btn btn-outline-success btn-sm mr-1">
                        <i class="fa fa-file-text-o"></i> Cargar factura
                    </a>
                    @if (!empty($pdfIaHabilitado) && can('crear-precarga-proveedores', false))
                    <button type="button" class="btn btn-info btn-sm mr-1" data-toggle="modal" data-target="#modal-precarga-pdf-ia">
                        <i class="fa fa-magic"></i> PDF (IA)
                    </button>
                    @endif
                    <a href="{{route('crear_precarga_comprobante_proveedor')}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('crear-precarga-proveedores', false))
                        	<i class="fa fa-fw fa-plus-circle"></i> Precarga manual
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
                            <th>Origen</th>
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
                            <td><small>{{ \App\Support\Compras\PrecargaComprobanteOrigenEntrada::etiqueta($data->origen_entrada ?? null) }}</small></td>
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
                                @if (can('crear-comprobante-proveedor', false))
                                <form action="{{ route('generar_comprobante_desde_precarga', ['id' => $data->id]) }}" method="POST" class="d-inline"
                                    onsubmit="return confirm('¿Generar comprobante de proveedor en borrador desde esta precarga?');">
                                    @csrf
                                    <button type="submit" class="btn-accion-tabla tooltipsC text-success" title="Generar comprobante proveedor">
                                        <i class="fa fa-file-text-o"></i>
                                    </button>
                                </form>
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
@if (!empty($pdfIaHabilitado) && can('crear-precarga-proveedores', false))
    @include('compras.precarga_comprobante_proveedor.partials.modal_pdf_ia')
@endif
@endsection
