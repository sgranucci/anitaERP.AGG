@extends("theme.$theme.layout")
@section('titulo')
    Solicitudes de indumentaria
@endsection

@section('contenido')
@php
    $exportQuery = http_build_query($filtrosQuery ?? []);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Reporte de solicitudes de indumentaria</h3>
                @can('aprobar-solicitud-indumentaria')
                    <div class="card-tools">
                        <a href="{{ route('bandeja_solicitud_indumentaria') }}" class="btn btn-sm btn-warning"><i class="fa fa-inbox"></i> Bandeja de aprobación</a>
                    </div>
                @endcan
            </div>
            <div class="card-body">
                <form method="get" action="{{ route('reporte_solicitud_indumentaria') }}" class="mb-3">
                    <div class="form-row">
                        <div class="col-lg-4">
                            @include('includes.form-empresa-asignada', [
                                'empresa_query' => $empresa_query,
                                'empresa_id' => $filtros['empresa_id'] ?? null,
                                'required' => false,
                                'permite_vacio' => true,
                                'opcion_vacia' => '— Todas —',
                                'col_label' => 'col-lg-3',
                                'col_input' => 'col-lg-9',
                            ])
                        </div>
                        <div class="form-group col-lg-3 col-sm-6 mb-2">
                            <label class="small mb-1">Agrupamiento</label>
                            <select name="agrupamiento_id" class="form-control form-control-sm">
                                <option value="">— Todos —</option>
                                @foreach ($agrupamientos as $a)
                                    <option value="{{ $a->id }}" {{ (int) ($filtros['agrupamiento_id'] ?? 0) === (int) $a->id ? 'selected' : '' }}>{{ $a->descripcion }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-lg-2 col-sm-6 mb-2">
                            <label class="small mb-1">Estado</label>
                            <select name="estado" class="form-control form-control-sm">
                                <option value="">— Todos —</option>
                                @foreach ($estados as $k => $v)
                                    <option value="{{ $k }}" {{ ($filtros['estado'] ?? '') === $k ? 'selected' : '' }}>{{ $v }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-lg-3 col-sm-12 mb-2">
                            <label class="small mb-1">Fecha</label>
                            <div class="input-group input-group-sm">
                                <input type="date" name="desde" class="form-control form-control-sm" value="{{ $filtros['desde'] ?? '' }}">
                                <input type="date" name="hasta" class="form-control form-control-sm" value="{{ $filtros['hasta'] ?? '' }}">
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="col-lg-12">
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-search"></i> Consultar</button>
                            <a href="{{ route('reporte_solicitud_indumentaria') }}" class="btn btn-outline-secondary btn-sm"><i class="fa fa-eraser"></i> Limpiar</a>
                        </div>
                    </div>
                </form>

                <div class="mb-3">
                    <a href="{{ route('listar_solicitud_indumentaria', ['formato' => 'PDF']) }}{{ $exportQuery ? '?'.$exportQuery : '' }}" class="btn btn-app bg-danger"><i class="fas fa-file-pdf"></i> Pdf</a>
                    <a href="{{ route('listar_solicitud_indumentaria', ['formato' => 'EXCEL']) }}{{ $exportQuery ? '?'.$exportQuery : '' }}" class="btn btn-app bg-success"><i class="fas fa-file-excel"></i> Excel</a>
                    <a href="{{ route('listar_solicitud_indumentaria', ['formato' => 'CSV']) }}{{ $exportQuery ? '?'.$exportQuery : '' }}" class="btn btn-app bg-warning"><i class="fas fa-file-csv"></i> Csv</a>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-hover">
                        <thead style="background:#85C1E9;color:#17202A">
                            <tr><th>#</th><th>Fecha</th><th>Legajo</th><th>Empleado</th><th>Estado</th><th>Prendas</th><th>Solicitante</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($solicitudes as $s)
                                <tr>
                                    <td>{{ $s->id }}</td>
                                    <td>{{ optional($s->fecha)->format('d/m/Y') }}</td>
                                    <td>{{ optional($s->empleado)->legajo }}</td>
                                    <td>
                                        @if (optional($s->empleado)->id)
                                            <a href="{{ route('editar_empleado_sueldos', ['id' => $s->empleado->id]) }}?origen=modal_consulta&vista=consulta" target="_blank" rel="noopener" class="text-primary">{{ $s->empleado->nombre }}</a>
                                        @endif
                                    </td>
                                    <td><span class="badge badge-secondary">{{ $estados[$s->estado] ?? $s->estado }}</span>
                                        @if ($s->estado === \App\Models\Sueldos\Solicitud_Prenda_Sueldos::PENDIENTE)<small class="text-info">Nv {{ $s->nivel_actual }}</small>@endif
                                    </td>
                                    <td>
                                        @foreach ($s->articulos as $a)
                                            <div>{{ optional($a->prenda)->descripcion }} <small class="text-muted">{{ optional($a->color)->nombre }} {{ optional($a->talle)->nombre }}</small> × {{ rtrim(rtrim(number_format($a->cantidad,2,',','.'),'0'),',') }}</div>
                                        @endforeach
                                    </td>
                                    <td><small>{{ optional($s->solicitante)->nombre }}</small></td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted">Sin solicitudes.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $solicitudes->appends($filtrosQuery)->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
