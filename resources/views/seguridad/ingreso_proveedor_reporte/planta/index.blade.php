@extends("theme.$theme.layout")
@section('titulo')
    Ingresos de planta
@endsection

@section('contenido')
@php
    $k = $resultado['kpis'] ?? [];
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Ingresos de planta</h3>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    Solo personas que efectivamente ingresaron. Datos operativos de porter&iacute;a:
                    qui&eacute;n, DNI, horario, punto, patente. Sin comentarios comerciales ni &oacute;rdenes de compra.
                </p>
                <form method="get" action="{{ route('reporte_ingresos_planta') }}" class="mb-4">
                    @include('seguridad.ingreso_proveedor_reporte.partials.filtros', [
                        'mostrarEstado' => false,
                        'estados' => [],
                    ])
                </form>

                @if ($consultado && $resultado)
                    @php
                        $logosVista = \App\Support\Configuracion\EmpresaLogoArchivo::logosCabeceraDesdeColeccion($resultado['filas'] ?? []);
                    @endphp
                    <div class="row mb-3">
                        <div class="col-md-3 col-sm-6 mb-2">
                            <div class="small text-muted">Ingresos</div>
                            <div class="h4 mb-0">{{ $k['ingresos'] ?? 0 }}</div>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-2">
                            <div class="small text-muted">A&uacute;n en planta</div>
                            <div class="h4 mb-0">{{ $k['en_planta'] ?? 0 }}</div>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-2">
                            <div class="small text-muted">Ya salieron</div>
                            <div class="h4 mb-0">{{ $k['salieron'] ?? 0 }}</div>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-2">
                            <div class="small text-muted">Tiempo promedio</div>
                            <div class="h4 mb-0">{{ isset($k['minutos_promedio']) ? $k['minutos_promedio'].' min' : '—' }}</div>
                        </div>
                    </div>
                    @if (!empty($k['por_punto']))
                        <div class="mb-3">
                            @foreach ($k['por_punto'] as $nom => $cant)
                                <span class="badge badge-secondary mr-1">{{ $nom }}: {{ $cant }}</span>
                            @endforeach
                        </div>
                    @endif

                    <div class="border-bottom pb-2 mb-2 d-flex flex-wrap align-items-center">
                        @foreach ($logosVista as $logo)
                            <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" class="mr-2 mb-1" style="max-height: 48px; max-width: 140px;">
                        @endforeach
                        <span class="badge badge-info ml-auto">Registros: {{ count($resultado['filas'] ?? []) }}</span>
                    </div>
                    @include('includes.exportar-tabla-queryparams', [
                        'ruta' => 'listar_reporte_ingresos_planta',
                        'queryparams' => $filtrosQuery ?? [],
                    ])
                    <div class="table-responsive">
                        <table id="tabla-paginada" class="table table-bordered table-hover table-sm mb-0" style="font-size: 0.8rem;">
                            @include('seguridad.ingreso_proveedor_reporte.planta.partials.tabla_datos', [
                                'filas' => $filasPaginadas ?? [],
                                'en_pantalla' => true,
                                'puede_ver_ticket' => $puede_ver_ticket ?? false,
                                'puede_ver_proveedor' => $puede_ver_proveedor ?? false,
                                'puede_ver_empresa' => $puede_ver_empresa ?? false,
                                'puede_ver_usuario' => $puede_ver_usuario ?? false,
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
