@extends("theme.$theme.layout")
@section('titulo')
Administradores de depósito
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-users"></i> Administradores de depósito</h3>
                <div class="card-tools">
                    @if (can('crear-deposito-administrador', false))
                        <a href="{{ route('crear_deposito_administrador') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="fa fa-fw fa-plus-circle"></i> Asignar
                        </a>
                    @endif
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-hover" id="tabla-data-2">
                    <thead>
                        <tr>
                            <th>Depósito</th>
                            <th>Usuario</th>
                            <th>Email</th>
                            <th>Principal</th>
                            <th>Recibe avisos</th>
                            <th>Aprueba</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $row)
                            <tr>
                                <td>{{ optional($row->depositos)->nombre }}</td>
                                <td>{{ optional($row->usuarios)->nombre }}</td>
                                <td>{{ optional($row->usuarios)->email }}</td>
                                <td>@if ($row->principal) <i class="fa fa-check text-success"></i> @endif</td>
                                <td>@if ($row->recibe_avisos) <i class="fa fa-check text-success"></i> @endif</td>
                                <td>@if ($row->aprueba_recepcion) <i class="fa fa-check text-success"></i> @endif</td>
                                <td>
                                    @if (can('editar-deposito-administrador', false))
                                        <a href="{{ route('editar_deposito_administrador', ['id' => $row->id]) }}" class="btn-accion-tabla tooltipsC" title="Editar">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @endif
                                    @if (can('borrar-deposito-administrador', false))
                                        <form action="{{ route('eliminar_deposito_administrador', ['id' => $row->id]) }}" class="d-inline form-eliminar" method="POST">
                                            @csrf @method('delete')
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
        </div>
    </div>
</div>
@endsection
