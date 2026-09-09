@extends("theme.$theme.layout")
@section('titulo')
    Técnicos
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
@endsection

<?php use App\Helpers\biblioteca ?>
@php
    use App\Support\Ticket\TecnicoTicketVinculoSupport;
    $conteoPorUsuario = $vinculoResumen['conteo_por_usuario'] ?? [];
    $hayProblemas = (($vinculoResumen['compartidos'] ?? 0) + ($vinculoResumen['suspendidos'] ?? 0) + ($vinculoResumen['sin_usuario'] ?? 0)) > 0;
@endphp

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')

        @if ($hayProblemas)
        <div class="alert alert-warning">
            <strong>Vínculos técnico ↔ usuario a corregir</strong>
            <p class="mb-2 mt-1">
                Para que un técnico pueda <em>Tomar</em> un ticket en la bandeja, su ficha debe estar
                asociada al <strong>usuario ERP con el que inicia sesión</strong>, y ese usuario no debe
                estar compartido con otras fichas del mismo área.
            </p>
            <ul class="mb-1">
                <li>Total fichas: {{ (int) ($vinculoResumen['total'] ?? 0) }}</li>
                <li>OK (único + operativo): {{ (int) ($vinculoResumen['ok'] ?? 0) }}</li>
                <li>Usuario compartido: {{ (int) ($vinculoResumen['compartidos'] ?? 0) }}</li>
                <li>Usuario suspendido: {{ (int) ($vinculoResumen['suspendidos'] ?? 0) }}</li>
                <li>Sin usuario: {{ (int) ($vinculoResumen['sin_usuario'] ?? 0) }}</li>
            </ul>
            @if (($usuariosSinFicha ?? collect())->isNotEmpty())
                <p class="mb-0 mt-2">
                    Usuarios activos con rol de mantenimiento/obras sin ficha única:
                    {{ $usuariosSinFicha->map(fn ($u) => $u->nombre.' ('.$u->usuario.')')->implode(', ') }}.
                </p>
            @endif
        </div>
        @endif

        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Técnicos</h3>
                <div class="card-tools">
                    <a href="{{route('crea_tecnico_ticket')}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('crear-tecnico-ticket', false))
                        	<i class="fa fa-fw fa-plus-circle"></i> Nuevo registro
						@endif
                    </a>
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover" id="tabla-data">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th>Nombre</th>
                            <th>Area de destino</th>
                            <th>Usuario</th>
                            <th>Vínculo</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        @php
                            $estado = TecnicoTicketVinculoSupport::estadoFila($data, $conteoPorUsuario);
                        @endphp
                        <tr>
                            <td>{{$data->id}}</td>
                            <td>{{$data->nombre}}</td>
                            <td>{{$data->areadestinos->nombre}}</td>
                            <td>
                                {{ $data->usuarios->nombre ?? '—' }}
                                @if ($data->usuarios && (int) ($data->usuarios->suspendido ?? 0) === 1)
                                    <span class="text-muted">(suspendido)</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge badge-{{ $estado['clase'] }}" title="{{ $estado['titulo'] }}">
                                    {{ $estado['texto'] }}
                                </span>
                            </td>
                            <td>
                       			@if (can('editar-tecnico-ticket', false))
                                	<a href="{{route('edita_tecnico_ticket', ['id' => $data->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-tecnico-ticket', false))
                                <form action="{{route('elimina_tecnico_ticket', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
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
@endsection
