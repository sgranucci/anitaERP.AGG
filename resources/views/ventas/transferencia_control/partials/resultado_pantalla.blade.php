@php
    $resumen = $resultado['resumen'] ?? [];
    $filas = $filas ?? ($resultado['filas'] ?? []);
    $tipos = $tipos ?? \App\Support\Ventas\TransferenciaCrown\TransferenciaCrownControlSupport::TIPOS;
    $titulo = $titulo ?? 'Control de importación';
    $subtitulo = $subtitulo ?? null;
    $filtrosQuery = $filtrosQuery ?? [];
    $mostrarExport = $mostrarExport ?? false;
    $linkControlCompleto = $linkControlCompleto ?? null;
    $limiteFilas = $limiteFilas ?? null;
    $filasMostrar = $filas;
    if ($limiteFilas !== null && is_array($filasMostrar) && count($filasMostrar) > (int) $limiteFilas) {
        $filasMostrar = array_slice($filasMostrar, 0, (int) $limiteFilas);
    }
@endphp
<div class="card card-outline {{ (int) ($resumen['errores'] ?? 0) > 0 ? 'card-danger' : ((int) ($resumen['total'] ?? 0) > 0 ? 'card-warning' : 'card-success') }} mb-3" id="control-post-import">
    <div class="card-header d-flex align-items-center flex-wrap" style="gap:.5rem;">
        <h3 class="card-title mb-0">
            <i class="fa fa-check-square-o"></i> {{ $titulo }}
        </h3>
        <div class="ml-auto d-flex align-items-center flex-wrap" style="gap:.35rem;">
            @if ($linkControlCompleto)
                <a href="{{ $linkControlCompleto }}" class="btn btn-outline-info btn-sm" target="_blank" rel="noopener">
                    Abrir control completo
                </a>
            @endif
            @if ($mostrarExport && $filtrosQuery !== [])
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'listar_transferencia_control_crown',
                    'queryparams' => $filtrosQuery,
                    'variant' => 'compact',
                ])
            @endif
        </div>
    </div>
    <div class="card-body">
        @if ($subtitulo)
            <p class="text-muted small mb-3">{{ $subtitulo }}</p>
        @endif
        <div class="row mb-3">
            <div class="col-md-3 col-6">
                <div class="small-box bg-info mb-2">
                    <div class="inner">
                        <h3>{{ (int) ($resumen['total'] ?? 0) }}</h3>
                        <p>Incongruencias</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="small-box bg-danger mb-2">
                    <div class="inner">
                        <h3>{{ (int) ($resumen['errores'] ?? 0) }}</h3>
                        <p>Errores</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="small-box bg-warning mb-2">
                    <div class="inner">
                        <h3>{{ (int) ($resumen['avisos'] ?? 0) }}</h3>
                        <p>Avisos</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="small-box bg-secondary mb-2">
                    <div class="inner">
                        <h3>{{ (int) ($resumen['movimiento_sin_cc'] ?? 0) }}</h3>
                        <p>Sin centro de costo</p>
                    </div>
                </div>
            </div>
        </div>

        @if ((int) ($resumen['total'] ?? 0) === 0)
            <div class="alert alert-success mb-0">No hay incongruencias con estos filtros.</div>
        @else
            <div class="mb-2">
                @foreach ($tipos as $clave => $label)
                    @if ((int) ($resumen[$clave] ?? 0) > 0)
                        <span class="badge badge-light border mr-1 mb-1">{{ $label }}: {{ (int) $resumen[$clave] }}</span>
                    @endif
                @endforeach
            </div>
            @include('ventas.transferencia_control.partials.tabla_datos', [
                'filas' => $filasMostrar,
                'paraPantalla' => true,
            ])
            @if ($limiteFilas !== null && (int) ($resumen['total'] ?? 0) > (int) $limiteFilas)
                <div class="alert alert-light border mt-2 mb-0 small">
                    Se muestran las primeras {{ (int) $limiteFilas }} de {{ (int) $resumen['total'] }}.
                    @if ($linkControlCompleto)
                        <a href="{{ $linkControlCompleto }}" class="text-primary" target="_blank" rel="noopener">Ver todas en el control</a>.
                    @endif
                </div>
            @endif
            @isset ($paginador)
                <div class="mt-2 d-flex justify-content-between align-items-center flex-wrap">
                    <div class="small text-muted">
                        {{ $paginador->firstItem() }}–{{ $paginador->lastItem() }} de {{ $paginador->total() }}
                    </div>
                    <div>{{ $paginador->appends($filtrosQuery)->links() }}</div>
                </div>
            @endisset
        @endif
    </div>
</div>
