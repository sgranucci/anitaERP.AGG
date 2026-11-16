@extends("theme.$theme.layout")
@section('titulo')
KPI SLA aprobaciones
@endsection

@section('contenido')
<div class="container-fluid">
    @include('includes.mensaje')
    <div class="card card-info">
        <div class="card-header">
            <h3 class="card-title mb-0"><i class="fa fa-chart-bar"></i> Cumplimiento SLA — Mis aprobaciones</h3>
            <div class="card-tools">
                <a href="{{ route('editar_user_task_sla') }}" class="btn btn-outline-light btn-sm">SLA config</a>
                <a href="{{ route('exportar_user_task_sla_kpi', ['desde' => $desde, 'hasta' => $hasta]) }}" class="btn btn-outline-light btn-sm">CSV</a>
            </div>
        </div>
        <div class="card-body">
            <form method="get" class="form-inline mb-3" data-enter-campos="1">
                <label class="mr-2">Desde</label>
                <input type="date" name="desde" class="form-control form-control-sm mr-2" value="{{ $desde }}">
                <label class="mr-2">Hasta</label>
                <input type="date" name="hasta" class="form-control form-control-sm mr-2" value="{{ $hasta }}">
                <button type="submit" class="btn btn-sm btn-primary">Consultar</button>
            </form>

            <div class="row mb-4">
                @foreach ([
                    ['Pendientes', $resumen['pendientes'] ?? 0],
                    ['Vencidas abiertas', $resumen['vencidas_abiertas'] ?? 0],
                    ['Escaladas (período)', $resumen['escaladas_periodo'] ?? 0],
                    ['Cumplimiento SLA %', $resumen['cumplimiento_sla_pct'] ?? '—'],
                    ['Prom. horas cierre', $resumen['promedio_horas_cierre'] ?? '—'],
                    ['Cerradas período', $resumen['cerradas_periodo'] ?? 0],
                ] as [$label, $val])
                    <div class="col-6 col-md-4 col-lg-2 mb-2">
                        <div class="border rounded p-2 text-center h-100">
                            <div class="h4 mb-0">{{ $val }}</div>
                            <small class="text-muted">{{ $label }}</small>
                        </div>
                    </div>
                @endforeach
            </div>

            @if (!empty($tendencia))
                <h5>Tendencia semanal (cierres del período)</h5>
                <table class="table table-sm table-bordered mb-4">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Semana</th>
                            <th>Desde</th>
                            <th>Hasta</th>
                            <th class="text-right">Cerradas</th>
                            <th class="text-right">Dentro SLA</th>
                            <th class="text-right">Fuera SLA</th>
                            <th class="text-right">Cumpl. %</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tendencia as $t)
                            <tr>
                                <td>{{ $t['semana'] }}</td>
                                <td>{{ $t['desde'] }}</td>
                                <td>{{ $t['hasta'] }}</td>
                                <td class="text-right">{{ $t['cerradas'] }}</td>
                                <td class="text-right">{{ $t['dentro_sla'] }}</td>
                                <td class="text-right">{{ $t['fuera_sla'] }}</td>
                                <td class="text-right">{{ $t['cumplimiento_pct'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            @if (!empty($por_empresa))
                <h5>Vencidas por empresa (pendientes hoy)</h5>
                <table class="table table-sm table-bordered mb-4">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr><th>Empresa</th><th class="text-right">Pendientes</th><th class="text-right">Vencidas</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($por_empresa as $e)
                            <tr>
                                <td>#{{ $e['empresa_id'] }} {{ $e['nombre'] }}</td>
                                <td class="text-right">{{ $e['total'] }}</td>
                                <td class="text-right">{{ $e['vencidas'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            <h5>Por fuente (pendientes hoy)</h5>
            <table class="table table-sm table-bordered">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr><th>Fuente</th><th class="text-right">Total</th><th class="text-right">Vencidas</th><th class="text-right">Escaladas</th></tr>
                </thead>
                <tbody>
                    @forelse ($por_fuente as $f)
                        <tr>
                            <td>{{ $f['nombre'] }}</td>
                            <td class="text-right">{{ $f['total'] }}</td>
                            <td class="text-right">{{ $f['vencidas'] }}</td>
                            <td class="text-right">{{ $f['escaladas'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-muted">Sin pendientes.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <h5 class="mt-4">Usuarios con más vencidas</h5>
            <table class="table table-sm table-bordered">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr><th>Usuario</th><th class="text-right">Pendientes</th><th class="text-right">Vencidas</th></tr>
                </thead>
                <tbody>
                    @forelse ($por_usuario as $u)
                        <tr>
                            <td>#{{ $u['usuario_id'] }} {{ $u['nombre'] }} <span class="text-muted small">({{ $u['login'] }})</span></td>
                            <td class="text-right">{{ $u['total'] }}</td>
                            <td class="text-right">{{ $u['vencidas'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-muted">Sin datos.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <h5 class="mt-4">Detalle vencidas (máx. 200)</h5>
            <table class="table table-sm table-bordered">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr><th>ID</th><th>Fuente</th><th>Título</th><th>Usuario</th><th>Venció</th></tr>
                </thead>
                <tbody>
                    @forelse ($vencidas as $t)
                        <tr>
                            <td>{{ $t->id }}</td>
                            <td>{{ $t->fuente }}</td>
                            <td>{{ $t->titulo }}</td>
                            <td>{{ $t->usuarios->nombre ?? $t->usuario_id }}</td>
                            <td>{{ optional($t->vence_en)->format('d/m/Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-muted">Ninguna vencida.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
