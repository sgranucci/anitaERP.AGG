@if (empty($esPdf))
    @extends("theme.$theme.layout")
    @section('titulo')
        Paridad Anita — {{ $data->titulo }}
    @endsection
    @section('contenido')
@else
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 4px; }
        th { background: #85C1E9; color: #17202A; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
@endif

@if (empty($esPdf))
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Paridad Anita — {{ $data->codigo }} · {{ $data->titulo }}</h3>
                <div class="card-tools">
                    <a href="{{ route('listar_paridad_reporte_sueldos_definible', ['id' => $data->id, 'formato' => 'PDF'] + request()->query()) }}"
                       class="btn btn-outline-danger btn-sm"><i class="fas fa-file-pdf"></i> PDF</a>
                    <a href="{{ route('listar_paridad_reporte_sueldos_definible', ['id' => $data->id, 'formato' => 'EXCEL'] + request()->query()) }}"
                       class="btn btn-outline-success btn-sm"><i class="fas fa-file-excel"></i> Excel</a>
                    <a href="{{ route('editar_reporte_sueldos_definible', ['id' => $data->id]) }}"
                       class="btn btn-outline-info btn-sm"><i class="fa fa-reply-all"></i> Volver</a>
                </div>
            </div>
            <div class="card-body">
                <form method="get" class="form-inline mb-3">
                    <input type="number" name="liquidacion_id" value="{{ request('liquidacion_id') }}"
                           class="form-control form-control-sm mr-2" placeholder="ID liquidación">
                    <input type="number" name="empresa" value="{{ request('empresa') }}"
                           class="form-control form-control-sm mr-2" placeholder="Empresa Anita">
                    <input type="number" step="0.01" name="tolerancia" value="{{ request('tolerancia', '0.01') }}"
                           class="form-control form-control-sm mr-2" placeholder="Tolerancia">
                    <div class="custom-control custom-checkbox mr-3">
                        <input type="checkbox" class="custom-control-input" id="solo_diferencias"
                               name="solo_diferencias" value="1" {{ $soloDiferencias ? 'checked' : '' }}>
                        <label class="custom-control-label" for="solo_diferencias">Solo diferencias</label>
                    </div>
                    <button class="btn btn-primary btn-sm">Consultar</button>
                </form>
@else
    <h2>Paridad Anita — {{ $data->codigo }} · {{ $data->titulo }}</h2>
@endif

<p>
    Ejecución: {{ $ejecucion ? '#'.$ejecucion->id : 'comparación en línea' }} ·
    Columnas: {{ $resumen['columnas'] }} · Coinciden: {{ $resumen['coinciden'] }} ·
    Diferencias: {{ $resumen['diferencias'] }} ·
    Máxima diferencia: {{ number_format($resumen['max_diferencia'], 4, ',', '.') }}
</p>
<div class="table-responsive">
    <table class="table table-sm table-bordered">
        <thead style="background:#85C1E9;color:#17202A;">
            <tr>
                <th>Col.</th><th>Descripción</th><th class="text-right">ERP</th>
                <th class="text-right">Anita</th><th class="text-right">Diferencia</th>
                <th class="text-right">Tolerancia</th><th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($filas as $fila)
                <tr>
                    <td>{{ $fila->columna_nro }}</td>
                    <td>{{ $fila->columna_descripcion }}</td>
                    <td class="text-right">{{ number_format($fila->total_erp, 4, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($fila->total_anita, 4, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($fila->diferencia, 4, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($fila->tolerancia, 4, ',', '.') }}</td>
                    <td>{{ $fila->coincide ? 'Coincide' : 'Diferencia' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted">Sin datos de paridad. Indique una liquidación.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if (empty($esPdf))
                @if (can('actualizar-reporte-sueldos-definible', false))
                    @if ($ejecucion && $filas->isNotEmpty() && (int) $resumen['diferencias'] === 0)
                        <div class="card card-outline card-primary mt-3">
                            <div class="card-header py-2"><strong>Certificar paridad</strong></div>
                            <div class="card-body">
                                <form method="post" action="{{ route('certificar_paridad_reporte_sueldos_definible', $data->id) }}" class="form-inline">
                                    @csrf
                                    <input type="hidden" name="ejecucion_id" value="{{ $ejecucion->id }}">
                                    <input type="number" name="liquidacion_id" required
                                           value="{{ request('liquidacion_id', $ejecucion->filtros['liquidacion_id'] ?? '') }}"
                                           class="form-control form-control-sm mr-2" placeholder="ID liquidación">
                                    <select name="nomina" class="form-control form-control-sm mr-2">
                                        @foreach(\App\Models\Sueldos\ReporteSueldosDefinibleCertificacion::nominas() as $k => $v)
                                            <option value="{{ $k }}" @selected(($nominaDefault ?? 'normal') === $k)>{{ $v }}</option>
                                        @endforeach
                                    </select>
                                    <input type="text" name="comentario" class="form-control form-control-sm mr-2" style="min-width:220px"
                                           placeholder="Comentario (opcional)" maxlength="2000">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="fas fa-stamp"></i> Certificar
                                    </button>
                                </form>
                                <p class="text-muted small mb-0 mt-2">
                                    El acta exige matriz persistida sin diferencias fuera de tolerancia.
                                    Publicar dataset con confidencial o alerta bloqueante exige certificación vigente.
                                </p>
                            </div>
                        </div>
                    @endif
                @endif

                <div class="card card-outline card-info mt-3">
                    <div class="card-header py-2"><strong>Certificaciones</strong></div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0">
                                <thead style="background:#85C1E9;color:#17202A;">
                                    <tr>
                                        <th>#</th>
                                        <th>Estado</th>
                                        <th>Liquidación</th>
                                        <th>Nómina</th>
                                        <th>Ejecución</th>
                                        <th class="text-right">Δ máx</th>
                                        <th>Quién</th>
                                        <th>Cuándo</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse(($certificaciones ?? []) as $cert)
                                        <tr>
                                            <td>{{ $cert->id }}</td>
                                            <td>{{ $cert->estado }}</td>
                                            <td>#{{ $cert->liquidacion_id }}{{ $cert->liquidacion?->numero ? ' · '.$cert->liquidacion->numero : '' }}</td>
                                            <td>{{ $cert->nomina }}</td>
                                            <td>#{{ $cert->ejecucion_id }}</td>
                                            <td class="text-right">{{ number_format($cert->max_diferencia, 4, ',', '.') }}</td>
                                            <td>{{ $cert->usuario?->nombre ?? '—' }}</td>
                                            <td>{{ optional($cert->certificada_at)->format('d/m/Y H:i') }}</td>
                                            <td>
                                                <a class="btn btn-outline-danger btn-sm"
                                                   href="{{ route('acta_paridad_reporte_sueldos_definible', ['id' => $data->id, 'certificacionId' => $cert->id]) }}">
                                                    Acta PDF
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="9" class="text-center text-muted">Sin certificaciones.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
    @endsection
@else
</body>
</html>
@endif
