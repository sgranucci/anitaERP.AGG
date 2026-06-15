@extends("theme.$theme.layout")
@section('titulo')
    Cierre de período contable
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')

        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Cierre de período contable</h3>
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    Al cerrar un período, ningún proceso del sistema puede generar información contable
                    con fecha anterior o igual al cierre, salvo usuarios con apertura programada aprobada
                    o permiso de operación en período cerrado.
                    La facturación electrónica (CAE/WSFE) mantiene la validación de fecha de AFIP/ARCA.
                </p>

                <form method="get" action="{{ route('cierre_periodo_contable') }}" class="form-horizontal mb-4">
                    @include('includes.form-empresa-asignada', [
                        'empresa_query' => $empresa_query,
                        'empresa_id' => $empresa_id ?: null,
                        'col_label' => 'col-md-2',
                        'col_input' => 'col-md-4',
                    ])
                    <div class="form-group row mb-0">
                        <div class="col-md-2"></div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fa fa-search"></i> Consultar
                            </button>
                        </div>
                    </div>
                </form>

                @if ($empresa_id > 0)
                    <div class="alert alert-secondary">
                        <strong>Cierre vigente:</strong>
                        @if ($resumen_vigente)
                            hasta {{ \Carbon\Carbon::parse($resumen_vigente['fecha_hasta'])->format('d/m/Y') }}
                            @if (!empty($resumen_vigente['observacion']))
                                — {{ $resumen_vigente['observacion'] }}
                            @endif
                        @else
                            sin cierre registrado para esta empresa.
                        @endif
                    </div>

                    @if ($puede_ejecutar_cierre)
                        <div class="card card-outline card-warning mb-4">
                            <div class="card-header">
                                <h3 class="card-title">Registrar nuevo cierre</h3>
                            </div>
                            <form method="post" action="{{ route('ejecutar_cierre_periodo_contable') }}">
                                @csrf
                                <input type="hidden" name="empresa_id" value="{{ $empresa_id }}">
                                <div class="card-body">
                                    <div class="form-group row">
                                        <label class="col-md-2 control-label requerido">Fecha hasta</label>
                                        <div class="col-md-3">
                                            <input type="date" name="fecha_hasta" class="form-control" required
                                                max="{{ date('Y-m-d') }}"
                                                value="{{ old('fecha_hasta', $resumen_vigente['fecha_hasta'] ?? date('Y-m-d')) }}">
                                        </div>
                                        <div class="col-md-7">
                                            <small class="text-muted">Última fecha incluida en el cierre (inclusive).</small>
                                        </div>
                                    </div>
                                    <div class="form-group row mb-0">
                                        <label class="col-md-2 control-label">Observación</label>
                                        <div class="col-md-6">
                                            <textarea name="observacion" class="form-control" rows="2" maxlength="2000">{{ old('observacion') }}</textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <button type="submit" class="btn btn-warning"
                                        onclick="return confirm('¿Confirma el cierre contable hasta la fecha indicada?');">
                                        <i class="fa fa-lock"></i> Ejecutar cierre
                                    </button>
                                </div>
                            </form>
                        </div>
                    @endif
                @endif

                <div class="table-responsive">
                    <table class="table table-striped table-bordered table-sm" id="tabla-paginada">
                        <thead style="background-color:#85C1E9;color:#17202A;">
                            <tr>
                                <th>ID</th>
                                <th>Empresa</th>
                                <th>Fecha hasta</th>
                                <th>Observación</th>
                                <th>Usuario</th>
                                <th>Registrado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($cierres as $cierre)
                                <tr>
                                    <td>{{ $cierre->id }}</td>
                                    <td>{{ $cierre->empresa?->nombre }}</td>
                                    <td>{{ optional($cierre->fecha_hasta)->format('d/m/Y') }}</td>
                                    <td>{{ $cierre->observacion }}</td>
                                    <td>{{ $cierre->usuario?->nombre }}</td>
                                    <td>{{ optional($cierre->created_at)->format('d/m/Y H:i') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted">Sin registros de cierre.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($cierres->hasPages())
                    <div class="d-flex justify-content-between align-items-center">
                        <small>
                            {{ $cierres->firstItem() }}–{{ $cierres->lastItem() }} de {{ $cierres->total() }}
                        </small>
                        {{ $cierres->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
