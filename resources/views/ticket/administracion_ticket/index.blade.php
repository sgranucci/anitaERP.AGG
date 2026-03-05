@extends("theme.$theme.layout")
@section('titulo')
    Administración de Tickets
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ticket/administracion_ticket/filtro.js")}}" type="text/javascript"></script>
<script>
    function limpiaFiltros(){
        $('#estado').val('');

        var token = $("meta[name='csrf-token']").attr("content");
        var data = "_token="+token;

        $.ajax({
            type: "POST",
            url: '/anitaERP/public/ticket/administracion_ticket/limpiafiltro',
            data: data,
            success: function(response){
                window.location.replace(window.location.pathname);
            }
        });
    }

    function eliminarTicket(event) {
        var opcion = confirm("Desea eliminar el ticket?");
        if(!opcion) {
            event.preventDefault();
        }
    }
</script>

@endsection

<?php use App\Helpers\biblioteca ?>

@section('contenido')
<meta name="csrf-token" content="{{ csrf_token() }}" />
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Administración de Tickets</h3>
                <div class="card-tools">
                    @if (session()->get('filtrosTickets') == '')
						<a href="javascript:void(0)" class="btn btn-outline-secondary btn-sm" id='btn_advanced_filter' data-url-parameter='' 
							title='Filtros y búsquedas avanzadas' class="btn btn-sm btn-default ">
								<i class="fa fa-filter"></i> Filtros
						</a>
					@endif
					@if (session()->get('filtrosTickets') != '') 
                    	<span id="container-button-state">
                            <button class="btn btn-outline-secondary btn-sm" style="color:white" onclick="limpiaFiltros()">Limpiar filtros</button>
                    	</span>
					@endif                    
                    <a href="{{route('crea_administracion_ticket')}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('crear-ticket', false))
                        	<i class="fa fa-fw fa-plus-circle"></i> Nuevo registro
						@endif
                    </a>
                </div>
                <div class="d-md-flex justify-content-md-end">
					<form action="{{ route('consulta_administracion_ticket') }}" method="GET">
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
                @include('includes.exportar-tabla', ['ruta' => 'lista_ticket', 'busqueda' => $busqueda])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th>Fecha</th>
                            <th>Sala</th>
                            <th>Sector</th>
                            <th>Area de destino</th>
                            <th>Generó Usuario</th>
                            <th>Categoría</th>
                            <th>Subcategoría</th>
                            <th>Estado</th>
                            <th>Detalle</th>
                            <th>Técnico asignado</th>
                            <th class="width40" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($ticket as $data)
                        <tr>
                            <td>{{$data->id}}</td>
                            <td>{{date("d/m/Y", strtotime($data->fecha ?? ''))}}</td>
                            <td>{{$data->nombresala ?? ''}}</td>
                            <td>{{$data->nombresector ?? ''}}</td>
                            <td>{{$data->nombreareadestino ?? ''}}</td>
                            <td>{{$data->nombreusuario ?? '' }}</td>
                            <td>{{$data->nombrecategoria_ticket ?? ''}}</td>
                            <td>{{$data->nombresubcategoria_ticket ?? ''}}</td>
                            <td>{{$data->estado}}</td>
                            <td>{{$data->detalle}}</td>
                            <td>
                                @php $nombreTecnico = []; @endphp

                                @foreach ($data->ticket_tareas as $tarea)

                                    @if (!in_array($tarea->tecnicos->nombre ?? '', $nombreTecnico))
                                        @php $nombreTecnico[] = $tarea->tecnicos->nombre ?? ''; @endphp
                                    @endif

                                @endforeach

                                {{ implode(',', $nombreTecnico) }}
                            </td>
                            <td>
                       			@if (can('editar-ticket', false))
                                	<a href="{{route('edita_administracion_ticket', ['id' => $data->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-ticket', false))
                                <form action="{{route('elimina_administracion_ticket', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
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

@include('includes.filtroticket')

{{ $ticket->appends(['busqueda' => $busqueda])->links() }}
@endsection
