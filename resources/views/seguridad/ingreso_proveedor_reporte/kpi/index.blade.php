@extends("theme.$theme.layout")
@section('titulo')
    Reporte tickets e ingresos
@endsection

@section('contenido')
@php
    use App\Support\Seguridad\IngresoProveedorEstados;
    $k = $resultado['kpis'] ?? [];
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Reporte tickets e ingresos</h3>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    Detalle por persona para KPIs: tickets pedidos, ingresos reales, tiempo en planta,
                    proveedores vs visitantes y desglose por motivo / punto / empresa.
                </p>
                <form method="get" action="{{ route('reporte_tickets_ingreso') }}" class="mb-4">
                    @include('seguridad.ingreso_proveedor_reporte.partials.filtros')
                </form>

                @if ($consultado && $resultado)
                    @php
                        $logosVista = \App\Support\Configuracion\EmpresaLogoArchivo::logosCabeceraDesdeColeccion($resultado['filas'] ?? []);
                    @endphp
                    <div class="row mb-3">
                        <div class="col-md-2 col-sm-4 mb-2">
                            <div class="small text-muted">Tickets</div>
                            <div class="h4 mb-0">{{ $k['tickets'] ?? 0 }}</div>
                        </div>
                        <div class="col-md-2 col-sm-4 mb-2">
                            <div class="small text-muted">Personas</div>
                            <div class="h4 mb-0">{{ $k['personas'] ?? 0 }}</div>
                        </div>
                        <div class="col-md-2 col-sm-4 mb-2">
                            <div class="small text-muted">Con ingreso</div>
                            <div class="h4 mb-0">{{ $k['con_ingreso'] ?? 0 }}</div>
                        </div>
                        <div class="col-md-2 col-sm-4 mb-2">
                            <div class="small text-muted">En planta</div>
                            <div class="h4 mb-0">{{ $k['en_planta'] ?? 0 }}</div>
                        </div>
                        <div class="col-md-2 col-sm-4 mb-2">
                            <div class="small text-muted">Proveedor / Visitante</div>
                            <div class="h4 mb-0">{{ $k['proveedor'] ?? 0 }} / {{ $k['visitante'] ?? 0 }}</div>
                        </div>
                        <div class="col-md-2 col-sm-4 mb-2">
                            <div class="small text-muted">Tiempo promedio</div>
                            <div class="h4 mb-0">{{ isset($k['minutos_promedio']) ? $k['minutos_promedio'].' min' : '—' }}</div>
                        </div>
                    </div>
                    <div class="mb-3">
                        @foreach (IngresoProveedorEstados::META as $cod => $meta)
                            <span class="badge badge-{{ $meta['badge'] }} mr-1">{{ $meta['label'] }}: {{ $k['por_estado'][$cod] ?? 0 }}</span>
                        @endforeach
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <h6>Por motivo</h6>
                            <ul class="small mb-0 pl-3">
                                @foreach (($k['por_motivo'] ?? []) as $nom => $cant)
                                    <li>{{ $nom }}: {{ $cant }}</li>
                                @endforeach
                            </ul>
                        </div>
                        <div class="col-md-4">
                            <h6>Por punto</h6>
                            <ul class="small mb-0 pl-3">
                                @foreach (($k['por_punto'] ?? []) as $nom => $cant)
                                    <li>{{ $nom }}: {{ $cant }}</li>
                                @endforeach
                            </ul>
                        </div>
                        <div class="col-md-4">
                            <h6>Por empresa</h6>
                            <ul class="small mb-0 pl-3">
                                @foreach (($k['por_empresa'] ?? []) as $nom => $cant)
                                    <li>{{ $nom }}: {{ $cant }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>

                    <div class="border-bottom pb-2 mb-2 d-flex flex-wrap align-items-center">
                        @foreach ($logosVista as $logo)
                            <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" class="mr-2 mb-1" style="max-height: 48px; max-width: 140px;">
                        @endforeach
                        <span class="badge badge-info ml-auto">Registros: {{ count($resultado['filas'] ?? []) }}</span>
                    </div>
                    @include('includes.exportar-tabla-queryparams', [
                        'ruta' => 'listar_reporte_tickets_ingreso',
                        'queryparams' => $filtrosQuery ?? [],
                    ])
                    <div class="table-responsive">
                        <table id="tabla-paginada" class="table table-bordered table-hover table-sm mb-0" style="font-size: 0.8rem;">
                            @include('seguridad.ingreso_proveedor_reporte.kpi.partials.tabla_datos', [
                                'filas' => $filasPaginadas ?? [],
                                'puede_ver_ticket' => $puede_ver_ticket ?? false,
                            ])
                        </table>
                    </div>
                    @if ($filasPaginadas)
                        <div class="d-flex flex-wrap align-items-center mt-2">
                            <small class="text-muted mr-3">
                                {{ $filasPaginadas->firstItem() }}–{{ $filasPaginadas->lastItem() }}
                                de {{ $filasPaginadas->total() }}
                            </small>
                            {{ $filasPaginadas->links() }}
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
