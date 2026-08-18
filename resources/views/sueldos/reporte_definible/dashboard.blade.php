@extends("theme.$theme.layout")
@section('titulo')
    Dashboard {{ $data->codigo }}
@endsection

@section('scripts')
<script src="{{ asset('assets/lte/plugins/chart.js/Chart.min.js') }}"></script>
<script src="{{ asset('assets/pages/scripts/sueldos/reporte_definible/dashboard.js') }}"></script>
@endsection

@section('contenido')
@include('includes.mensaje')
<div class="row">
    <div class="col-lg-12">
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Dashboard — {{ $data->titulo }}</h3>
                <div class="card-tools">
                    <a href="{{ route('editar_reporte_sueldos_definible', ['id' => $data->id]) }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-reply-all"></i> Volver al ABM
                    </a>
                </div>
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    Dataset:
                    @if ($dataset)
                        <code>{{ $dataset->uuid }}</code> ({{ $dataset->estado }}) — {{ $dataset->cantidad_filas }} filas
                    @else
                        sin dataset materializado publicado (usando ejecución publicada si existe)
                    @endif
                </p>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <div class="small-box bg-info">
                            <div class="inner">
                                <h3 id="rsd-kpi-filas">{{ count($resultado['filas'] ?? []) }}</h3>
                                <p>Filas</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="small-box bg-success">
                            <div class="inner">
                                <h3 id="rsd-kpi-cols">{{ count($resultado['columnas'] ?? []) }}</h3>
                                <p>Columnas</p>
                            </div>
                        </div>
                    </div>
                </div>

                <form id="rsd-pivot-form" class="card card-outline card-primary mb-3">
                    <div class="card-header py-2"><strong>Pivot configurable</strong> <span class="small text-muted">2 dimensiones + 1 medida</span></div>
                    <div class="card-body">
                        <input type="hidden" name="dataset_id" value="{{ $dataset->id ?? '' }}">
                        <div class="form-row">
                            <div class="form-group col-md-3">
                                <label class="small">Filas (dimensión)</label>
                                <select name="dim_fila" class="form-control form-control-sm">
                                    <option value="grupo_label">Grupo</option>
                                    <option value="centrocosto_id">Centro de costo</option>
                                    <option value="lugartrabajo_id">Lugar de trabajo</option>
                                    <option value="agrupamiento_id">Agrupamiento</option>
                                </select>
                            </div>
                            <div class="form-group col-md-3">
                                <label class="small">Columnas (dimensión)</label>
                                <select name="dim_columna" class="form-control form-control-sm">
                                    <option value="">Sin dimensión</option>
                                    <option value="centrocosto_id">Centro de costo</option>
                                    <option value="lugartrabajo_id">Lugar de trabajo</option>
                                    <option value="agrupamiento_id">Agrupamiento</option>
                                </select>
                            </div>
                            <div class="form-group col-md-3">
                                <label class="small">Medida</label>
                                <select name="medida" class="form-control form-control-sm">
                                    @foreach (($resultado['columnas'] ?? []) as $col)
                                        @if (!empty($col['numerica']))
                                            <option value="c{{ $col['nro'] }}">C{{ $col['nro'] }} — {{ $col['descripcion'] }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-2">
                                <label class="small">Agregación</label>
                                <select name="agregacion" class="form-control form-control-sm">
                                    <option value="sum">Suma</option>
                                    <option value="avg">Promedio</option>
                                    <option value="count">Conteo</option>
                                    <option value="min">Mín</option>
                                    <option value="max">Máx</option>
                                </select>
                            </div>
                            <div class="form-group col-md-2">
                                <label class="small">Gráfico</label>
                                <select name="chart_tipo" class="form-control form-control-sm">
                                    <option value="bar">Barras</option>
                                    <option value="line">Línea</option>
                                    <option value="pie">Torta</option>
                                </select>
                            </div>
                            <div class="form-group col-md-2 align-self-end">
                                <button type="submit" class="btn btn-primary btn-sm btn-block">Calcular</button>
                            </div>
                        </div>
                    </div>
                </form>

                <div class="row">
                    <div class="col-lg-6">
                        <div class="table-responsive rsd-scroll" style="max-height:420px;overflow:auto;">
                            <table class="table table-sm table-bordered" id="rsd-pivot-table">
                                <thead style="background:#85C1E9;color:#17202A;"></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <canvas id="rsd-pivot-chart" height="220"></canvas>
                    </div>
                </div>

                @if ($dashboards->isNotEmpty())
                    <hr>
                    <h5>Dashboards guardados</h5>
                    <ul>
                        @foreach ($dashboards as $d)
                            <li>{{ $d->nombre }} — {{ $d->widgets->count() }} widget(s)
                                @if ($d->compartida) <span class="badge badge-info">compartida</span> @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</div>
<script>
window.rsdDashboardConfig = {
    pivotUrl: @json(route('pivot_reporte_sueldos_definible', ['id' => $data->id])),
    pivotEstadoUrl: @json(route('estado_pivot_reporte_sueldos_definible', ['id' => $data->id, 'uuid' => '__UUID__'])),
    csrf: @json(csrf_token())
};
</script>
@endsection
