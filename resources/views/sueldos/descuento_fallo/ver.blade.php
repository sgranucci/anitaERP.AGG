@extends("theme.$theme.layout")

@section('titulo')
    Cierre de descuento por fallo #{{ $cierre->numero_cierre }}
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Detalle del cierre #{{ $cierre->numero_cierre }}</h3>
                <div class="card-tools">
                    <a href="{{ route('consultar_descuento_fallo_sueldos') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-reply-all"></i> Volver
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="mb-3 small text-muted">
                    {{ optional($cierre->empresa)->nombre }} ·
                    Período de descuento {{ $cierre->periodo_descuento }} ·
                    Fallos {{ optional($cierre->fecha_fallo_desde)->format('d/m/Y') }}
                    a {{ optional($cierre->fecha_fallo_hasta)->format('d/m/Y') }} ·
                    {{ $cierre->observacion }}
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-striped">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Legajo</th>
                                <th>Empleado</th>
                                <th>Fecha</th>
                                <th>Período</th>
                                <th>Tipo</th>
                                <th class="text-right">Importe</th>
                                <th>Observación</th>
                                <th>Novedad</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($cierre->movimientos as $m)
                                <tr>
                                    <td>{{ optional($m->empleado)->legajo }}</td>
                                    <td>{{ optional($m->empleado)->nombre }}</td>
                                    <td>{{ optional($m->fecha)->format('d/m/Y') }}</td>
                                    <td>{{ $m->periodo }}</td>
                                    <td>{{ $m->tipoLabel() }}</td>
                                    <td class="text-right">{{ number_format((float)$m->importe, 2, ',', '.') }}</td>
                                    <td>{{ $m->observacion }}</td>
                                    <td>
                                        @if ($m->novedad)
                                            #{{ $m->novedad->id }} · {{ $m->novedad->estado }} ·
                                            $ {{ number_format((float)$m->novedad->valor1, 2, ',', '.') }}
                                        @else
                                            —
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
</div>
@endsection
