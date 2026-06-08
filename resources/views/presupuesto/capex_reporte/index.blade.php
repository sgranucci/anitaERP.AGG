@extends("theme.$theme.layout")
@section('titulo')
    Reporte CAPEX
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Reporte CAPEX — OC, facturas y pagos</h3>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    Cruza los proyectos CAPEX del ERP con órdenes de compra (bridge Anita),
                    facturas aplicadas (<code>aplicped</code>), pagos (<code>aplmovp</code>)
                    y datos contables de factura (<code>subdiario</code> / <code>ctamae</code>).
                    Cada factura y cada pago genera un renglón; los importes OC/FC se cargan una sola vez
                    por comprobante para que en Excel pueda sumar por proyecto y contrastar con el
                    <strong>Monto CAPEX</strong> grabado (misma lógica que el listado de partidas).
                </p>

                <form method="get" action="{{ route('capex_reporte') }}" class="mb-4">
                    @include('includes.form-empresa-asignada', [
                        'empresa_query' => $empresa_query,
                        'empresa_id' => $filtros['empresa_id'] ?? null,
                        'required' => false,
                        'col_label' => 'col-lg-2',
                        'col_input' => 'col-lg-4',
                    ])

                    <div class="form-group row">
                        <label for="presupuesto_id" class="col-lg-2 control-label">Presupuesto</label>
                        <div class="col-lg-4">
                            <select name="presupuesto_id" id="presupuesto_id" class="form-control">
                                <option value="">— Todos —</option>
                                @foreach ($presupuesto_query as $value)
                                    <option value="{{ $value->id }}" @selected((int) ($filtros['presupuesto_id'] ?? 0) === (int) $value->id)>
                                        {{ $value->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="centrocosto_id" class="col-lg-2 control-label">Centro de costo</label>
                        <div class="col-lg-4">
                            <select name="centrocosto_id" id="centrocosto_id" class="form-control">
                                <option value="">— Todos —</option>
                                @foreach ($centrocosto_query as $value)
                                    <option value="{{ $value->id }}" @selected((int) ($filtros['centrocosto_id'] ?? 0) === (int) $value->id)>
                                        {{ $value->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="capex_id" class="col-lg-2 control-label">CAPEX</label>
                        <div class="col-lg-6">
                            <select name="capex_id" id="capex_id" class="form-control">
                                <option value="">— Todos (según filtros) —</option>
                                @foreach ($capex_opciones as $opcion)
                                    <option value="{{ $opcion['id'] }}" @selected((int) ($filtros['capex_id'] ?? 0) === (int) $opcion['id'])>
                                        {{ $opcion['label'] }}
                                    </option>
                                @endforeach
                            </select>
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
                </form>

                @if ($consultado && $resultado)
                    @php
                        $exportQuery = http_build_query(array_filter([
                            'empresa_id' => $filtros['empresa_id'] ?? null,
                            'presupuesto_id' => $filtros['presupuesto_id'] ?? null,
                            'centrocosto_id' => $filtros['centrocosto_id'] ?? null,
                            'capex_id' => $filtros['capex_id'] ?? null,
                        ], fn ($v) => $v !== null && $v !== ''));
                    @endphp

                    <div class="mb-2">
                        <span class="badge badge-info mr-1">Filas: {{ $resultado['total'] ?? 0 }}</span>
                    </div>

                    <div class="mb-3">
                        <a href="{{ route('listar_capex_reporte', ['formato' => 'PDF']) }}?{{ $exportQuery }}" class="btn btn-app bg-danger">
                            <i class="fas fa-file-pdf"></i> Pdf
                        </a>
                        <a href="{{ route('listar_capex_reporte', ['formato' => 'EXCEL']) }}?{{ $exportQuery }}" class="btn btn-app bg-success">
                            <i class="fas fa-file-excel"></i> Excel
                        </a>
                        <a href="{{ route('listar_capex_reporte', ['formato' => 'CSV']) }}?{{ $exportQuery }}" class="btn btn-app bg-warning">
                            <i class="fas fa-file-csv"></i> Csv
                        </a>
                    </div>

                    @php
                        $filasVista = $resultado['filas'] ?? [];
                        $logosVista = \App\Support\Configuracion\EmpresaLogoArchivo::logosCabeceraDesdeColeccion($filasVista);
                    @endphp
                    <div class="border-bottom pb-2 mb-3 d-flex flex-wrap align-items-center">
                        @foreach ($logosVista as $logo)
                            <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" class="mr-2 mb-1" style="max-height: 48px; max-width: 140px;">
                        @endforeach
                        <div class="ml-auto text-muted small">
                            Vista previa — columnas alineadas al reporte exportable
                        </div>
                    </div>
                    <style>
                        #tabla-capex-reporte thead tr { background-color: #85C1E9; color: #17202A; }
                        #tabla-capex-reporte thead th { font-weight: 600; border-color: #7fb3d5; }
                        #tabla-capex-reporte .num { text-align: right; }
                        #tabla-capex-reporte .cell-partidas { white-space: pre-line; max-width: 220px; }
                    </style>
                    <div class="table-responsive">
                        <table id="tabla-capex-reporte" class="table table-bordered table-hover table-sm mb-0" style="font-size: 0.8rem;">
                            @include('presupuesto.capex_reporte.partials.tabla_datos', ['filas' => $filasVista])
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
