@extends("theme.$theme.layout")
@section('titulo')
    Asientos pendientes de aprobación
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-check-circle"></i> Asientos pendientes de aprobación</h3>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover" id="tabla-data">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Número</th>
                            <th>Fecha</th>
                            <th>Empresa</th>
                            <th>Tipo</th>
                            <th>Usuario</th>
                            <th>Cargado</th>
                            <th class="width80"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($asientos as $asiento)
                            <tr>
                                <td>{{ $asiento->id }}</td>
                                <td>{{ $asiento->numeroasiento }}</td>
                                <td>{{ optional($asiento->fecha)->format('d/m/Y') ?? $asiento->fecha }}</td>
                                <td>{{ optional($asiento->empresas)->nombre }}</td>
                                <td>{{ optional($asiento->tipoasientos)->nombre }}</td>
                                <td>{{ optional($asiento->usuarios)->nombre }}</td>
                                <td>{{ optional($asiento->created_at)->format('d/m/Y H:i') }}</td>
                                <td>
                                    @if (can('listar-aprobacion-asiento', false))
                                        <a href="{{ route('ver_aprobacion_asiento', ['id' => $asiento->id]) }}" class="btn-accion-tabla tooltipsC" title="Revisar y aprobar">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted">No hay asientos pendientes de aprobación.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
