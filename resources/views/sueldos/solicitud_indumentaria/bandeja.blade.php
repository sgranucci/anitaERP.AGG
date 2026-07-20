@extends("theme.$theme.layout")
@section('titulo')
    Bandeja de aprobación de indumentaria
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-warning">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-inbox"></i> Solicitudes pendientes de mi aprobación</h3>
                <div class="card-tools">
                    <a href="{{ route('reporte_solicitud_indumentaria') }}" class="btn btn-sm btn-outline-secondary"><i class="fa fa-list"></i> Reporte</a>
                </div>
            </div>
            <div class="card-body">
                @if ($pendientes->isEmpty())
                    <div class="alert alert-light border mb-0">No ten&eacute;s solicitudes pendientes de aprobaci&oacute;n.</div>
                @else
                    <table class="table table-sm table-bordered table-hover">
                        <thead style="background:#85C1E9;color:#17202A">
                            <tr><th>#</th><th>Fecha</th><th>Legajo</th><th>Empleado</th><th>Nivel</th><th>Prendas</th><th style="width:180px">Acción</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($pendientes as $s)
                                <tr>
                                    <td>{{ $s->id }}</td>
                                    <td>{{ optional($s->fecha)->format('d/m/Y') }}</td>
                                    <td>{{ optional($s->empleado)->legajo }}</td>
                                    <td>{{ optional($s->empleado)->nombre }}</td>
                                    <td>{{ $s->nivel_actual }}</td>
                                    <td>
                                        @foreach ($s->articulos as $a)
                                            <div>{{ optional($a->prenda)->descripcion }} × {{ rtrim(rtrim(number_format($a->cantidad,2,',','.'),'0'),',') }}</div>
                                        @endforeach
                                    </td>
                                    <td class="text-nowrap">
                                        <form method="post" action="{{ route('aprobar_bandeja_solicitud_indumentaria', $s->id) }}" class="d-inline" onsubmit="return confirm('¿Aprobar la solicitud #{{ $s->id }}?');">
                                            @csrf
                                            <button class="btn btn-xs btn-success"><i class="fa fa-check"></i> Aprobar</button>
                                        </form>
                                        <form method="post" action="{{ route('rechazar_bandeja_solicitud_indumentaria', $s->id) }}" class="d-inline" onsubmit="return confirm('¿Rechazar la solicitud #{{ $s->id }}?');">
                                            @csrf
                                            <input type="hidden" name="observacion" value="">
                                            <button class="btn btn-xs btn-danger"><i class="fa fa-ban"></i> Rechazar</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
