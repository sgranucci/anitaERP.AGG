@extends("theme.$theme.layout")
@section('titulo')
Requisiciones
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Requisiciones</h3>
                <div class="card-tools">
                    @if (can('crear-requisicion', false))
                    <a href="{{ route('crear_requisicion') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fa fa-fw fa-plus-circle"></i> Nuevo registro
                    </a>
                    @endif
                </div>
                <div class="d-md-flex justify-content-md-end">
					<form action="{{ route('consultar_requisicion') }}" method="GET">
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
                @include('includes.exportar-tabla', ['ruta' => 'listar_requisicion', 'busqueda' => $busqueda])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th class="width10">Número</th>
                            <th>Fecha</th>
                            <th>Empresa</th>
                            <th>Centro costo</th>
                            <th>Proveedor</th>
                            <th>Estado</th>
                            <th class="text-right">Total</th>
                            <th>Items</th>
                            <th class="width40" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($requisicion as $data)
                        <tr>
                            <td>{{ $data->numerorequisicion }}</td>
                            <td>{{ date('d/m/Y', strtotime($data->fecha)) }}</td>
                            <td>{{ $data->nombreempresa }}</td>
                            <td><small>{{ $data->nombrecentrocosto }}</small></td>
                            <td><small>{{ $data->nombreproveedor }}</small></td>
                            <td><small>{{ $data->estado }}</small></td>
                            <td class="text-right text-nowrap">
                                <small>{{ number_format((float) ($data->monto ?? 0), 2, ',', '.') }} {{ $data->monedacabecera_abreviatura ?? '' }}</small>
                            </td>
                            <td>
                                @foreach ($data->requisicion_articulos as $item)
                                    <small>{{ $item->articulos->sku ?? '' }}-{{ $item->articulos->descripcion ?? '' }}-Cant.:{{ $item->cantidad }}-Precio:{{ $item->precio }}</small><br>
                                @endforeach
                            </td>
                            <td>
                                @if (can('editar-requisicion', false))
                                <a href="{{ route('editar_requisicion', ['id' => $data->id]) }}" class="btn-accion-tabla tooltipsC" title="Editar">
                                    <i class="fa fa-edit"></i>
                                </a>
                                @if (($data->estado ?? '') === ($estado_en_compras ?? 'EN COMPRAS'))
                                <form action="{{ route('enviar_arbol_requisicion', ['id' => $data->id]) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Enviar esta requisición al árbol de aprobación para continuar el circuito?');">
                                    @csrf
                                    <button type="submit" class="btn-accion-tabla tooltipsC text-success" title="Envía al árbol de aprobación">
                                        <i class="fa fa-sitemap"></i>
                                    </button>
                                </form>
                                @endif
                                @endif
                                @if (can('listar-requisicion', false) || can('editar-requisicion', false))
                                <a href="{{ route('imprimir_pdf_requisicion', ['id' => $data->id]) }}" class="btn-accion-tabla tooltipsC" title="Listar la requisición (PDF)" target="_blank" rel="noopener noreferrer">
                                    <i class="fa fa-print"></i>
                                </a>
                                @endif                                
                                @if (can('borrar-requisicion', false))
                                <form action="{{ route('eliminar_requisicion', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
                                    @csrf @method("delete")
                                    <button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar">
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
            @if(method_exists($requisicion, 'links'))
            <div class="card-footer">
                {{ $requisicion->appends(request()->query())->links() }}
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
