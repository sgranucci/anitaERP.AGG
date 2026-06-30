@extends("theme.$theme.layout")
@section('titulo')
    Transferencias pendientes de aprobación
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-warning">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Transferencias en stand-by (pendientes de aprobación)</h3>
                <a href="{{ route('transferencia_mercaderia_pendientes') }}" class="btn btn-outline-info btn-sm">
                    <i class="fa fa-check"></i> Pantalla de aprobación
                </a>
            </div>
            <form method="get" action="{{ route('reporte_transferencias_pendientes') }}" class="mb-0">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Lista transferencias con estado <strong>Pendiente de recepción</strong>: ya se descontó el origen (depósito o bien)
                        y falta que el receptor confirme el ingreso en destino.
                        El checkbox filtra por tipos configurados con <strong>requiere aprobación</strong>
                        (en la práctica, todas las pendientes deberían tenerlo marcado).
                    </p>
                    @include('includes.form-empresa-asignada', [
                        'empresa_query' => $empresa_query,
                        'empresa_id' => $filtros['empresa_id'] ?? null,
                        'required' => false,
                        'col_label' => 'col-lg-2',
                        'col_input' => 'col-lg-4',
                    ])
                    <div class="form-group row">
                        <label for="bien_uso_destino_id" class="col-lg-2 control-label">Bien destino</label>
                        <div class="col-lg-4">
                            <select name="bien_uso_destino_id" id="bien_uso_destino_id" class="form-control">
                                <option value="">— Todos —</option>
                                @foreach ($bienesUso as $bien)
                                    <option value="{{ $bien->id }}" @selected((int) ($filtros['bien_uso_destino_id'] ?? 0) === (int) $bien->id)>
                                        {{ $bien->etiqueta() }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-4">
                            <div class="form-check mt-2">
                                <input type="hidden" name="solo_requiere_aprobacion" value="0">
                                <input type="checkbox" class="form-check-input" name="solo_requiere_aprobacion" id="solo_requiere_aprobacion" value="1"
                                    @if ($filtros['solo_requiere_aprobacion'] ?? true) checked @endif>
                                <label class="form-check-label" for="solo_requiere_aprobacion">Solo las que requieren aprobación del receptor</label>
                            </div>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="fecha_desde" class="col-lg-2 control-label">Desde fecha</label>
                        <div class="col-lg-3">
                            <input type="date" name="fecha_desde" id="fecha_desde" class="form-control"
                                value="{{ $filtros['fecha_desde'] ?? '' }}">
                        </div>
                        <label for="fecha_hasta" class="col-lg-2 control-label">Hasta fecha</label>
                        <div class="col-lg-3">
                            <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control"
                                value="{{ $filtros['fecha_hasta'] ?? '' }}">
                        </div>
                    </div>
                    <div class="form-group row mb-0">
                        <div class="col-lg-2"></div>
                        <div class="col-lg-10">
                            <input type="hidden" name="consultar" value="1">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fa fa-search"></i> Consultar
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            @if ($consultado)
                <div class="card-body pt-0">
                    @if ($totales)
                        <div class="mb-2">
                            <span class="badge badge-warning mr-1">Transferencias: {{ $totales['total'] ?? 0 }}</span>
                            <span class="badge badge-info">Ítems: {{ $totales['total_items'] ?? 0 }}</span>
                        </div>
                    @endif

                    @include('includes.exportar-tabla-queryparams', [
                        'ruta' => 'listar_reporte_transferencias_pendientes',
                        'queryparams' => $filtrosQuery ?? [],
                    ])

                    <div class="table-responsive">
                        @include('stock.transferencia_pendiente_reporte.partials.tabla_datos', [
                            'filas' => $filas,
                            'puede_aprobar' => $puede_aprobar ?? false,
                        ])
                    </div>

                    @if ($filas instanceof \Illuminate\Pagination\LengthAwarePaginator)
                        <div class="mt-2">
                            Mostrando {{ $filas->firstItem() }}–{{ $filas->lastItem() }} de {{ $filas->total() }}
                            {{ $filas->appends($filtrosQuery ?? [])->links() }}
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
