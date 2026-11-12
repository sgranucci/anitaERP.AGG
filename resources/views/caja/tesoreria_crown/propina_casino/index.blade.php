@extends("theme.$theme.layout")

@section('titulo')
    Propinas por casino
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/caja/tesoreria_crown/propina_casino.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/caja/tesoreria_crown/propina_casino.js')) ?: time() }}" type="text/javascript"></script>
@endsection

@section('contenido')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'propina-casino-overlay',
    'tituloId' => 'propina-casino-titulo',
    'subtituloId' => 'propina-casino-subtitulo',
    'titulo' => 'Consultando propinas…',
    'subtitulo' => 'Puede demorar según el período. No cierre la página.',
])
@php
    $f = $filtros ?? [];
    $consultado = ! empty($consultado);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Propina de empleados por casino</h3>
            </div>
            <div class="card-body">
                <form method="get" action="{{ route('tesoreria_crown_propina_casino') }}" id="form-propina-casino" class="mb-3">
                    <input type="hidden" name="consultar" value="1">
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-2 mb-2">
                            <label class="small mb-1" for="fecha_desde">Desde fecha</label>
                            <input type="date" name="fecha_desde" id="fecha_desde" class="form-control form-control-sm"
                                   value="{{ $f['fecha_desde'] ?? '' }}" required>
                        </div>
                        <div class="form-group col-md-2 mb-2">
                            <label class="small mb-1" for="fecha_hasta">Hasta fecha</label>
                            <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control form-control-sm"
                                   value="{{ $f['fecha_hasta'] ?? '' }}" required>
                        </div>
                        <div class="form-group col-md-4 mb-2">
                            <button type="submit" class="btn btn-primary btn-sm mr-2">
                                <i class="fa fa-search"></i> Consultar
                            </button>
                            <a href="{{ route('tesoreria_crown_propina_casino') }}" class="btn btn-outline-secondary btn-sm"
                               title="Limpiar filtros">
                                <i class="fa fa-eraser"></i> Limpiar
                            </a>
                        </div>
                    </div>
                    <p class="small text-muted mb-0">
                        Comparativo diario de caja empleados (propina) por casino, equivalente a Anita <code>l-propinaxcas</code>.
                    </p>
                </form>

                @if ($consultado && $resultado)
                    <div class="small text-muted mb-2">
                        {{ $resultado['subtitulo'] }} ·
                        {{ (int) ($resultado['total_dias'] ?? 0) }} día(s) ·
                        Total $ {{ number_format((float) ($resultado['totales']['total'] ?? 0), 2, ',', '.') }}
                    </div>

                    <div class="mb-3 js-export-propina-casino">
                        @include('includes.exportar-tabla-queryparams', [
                            'ruta' => 'listar_tesoreria_crown_propina_casino',
                            'queryparams' => $filtrosQuery,
                        ])
                    </div>

                    @include('caja.tesoreria_crown.propina_casino.partials.tabla_datos', [
                        'filas' => $filasPaginadas,
                        'casinos' => $resultado['casinos'] ?? [],
                        'totales' => $resultado['totales'] ?? [],
                        'mostrarTotales' => true,
                    ])

                    <div class="mt-2 d-flex justify-content-between align-items-center flex-wrap">
                        <div class="small text-muted mb-1">
                            @if ($filasPaginadas && $filasPaginadas->total() > 0)
                                {{ $filasPaginadas->firstItem() }}–{{ $filasPaginadas->lastItem() }}
                                de {{ $filasPaginadas->total() }}
                            @endif
                        </div>
                        <div>
                            {{ $filasPaginadas->appends($filtrosQuery)->links() }}
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
