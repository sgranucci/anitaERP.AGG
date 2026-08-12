@extends("theme.$theme.layout")
@section('titulo')
    Sets de cuentas — reportes definibles
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-layer-group"></i> Sets reutilizables de cuentas</h3>
                <div class="card-tools">
                    <a href="{{ route('reporte_definible') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-reply-all"></i> Volver a informes
                    </a>
                    @if ($puede_actualizar)
                        <a href="{{ route('crear_reporte_definible_conjunto') }}" class="btn btn-primary btn-sm">
                            <i class="fa fa-plus"></i> Nuevo set
                        </a>
                    @endif
                </div>
            </div>
            <div class="card-body">
                @include('includes.mensaje')
                <p class="text-muted">
                    Un set agrupa cuentas (con signo y origen) para vincularlas a varios rubros de distintos informes
                    sin copiarlas a mano. Al ejecutar, las cuentas del set se suman a las del rubro.
                </p>
                <div class="table-responsive">
                    <table id="tabla-paginada" class="table table-sm table-hover">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Código</th>
                                <th>Nombre</th>
                                <th class="text-center">Cuentas</th>
                                <th>Estado</th>
                                <th class="text-center" style="width:100px">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($coleccion as $row)
                                <tr>
                                    <td>{{ $row->codigo }}</td>
                                    <td>{{ $row->nombre }}</td>
                                    <td class="text-center">{{ $row->cuentas_count }}</td>
                                    <td>{{ $row->activo ? 'Activo' : 'Inactivo' }}</td>
                                    <td class="text-center">
                                        <a href="{{ route('editar_reporte_definible_conjunto', $row->id) }}"
                                           class="btn-accion-tabla tooltipsC" title="Editar">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted">Sin sets. Cree el primero.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $coleccion->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
