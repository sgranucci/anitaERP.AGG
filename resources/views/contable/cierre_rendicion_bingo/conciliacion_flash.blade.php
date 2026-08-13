@extends("theme.$theme.layout")
@section('titulo')
    Conciliación bingo vs flash
@endsection

@section('scripts')
<meta name="csrf-token" content="{{ csrf_token() }}">
@if (can('ejecutar-cierre-rendicion-bingo-contable', false))
<script>
    window.CIERRE_REND_BINGO_CONC = {
        urlEjecutarJornada: @json(route('api_cierre_rendicion_bingo_ejecutar')),
        urlEjecutarPeriodo: @json(route('api_cierre_rendicion_bingo_ejecutar_rango')),
    };
</script>
<script src="{{ asset('assets/pages/scripts/contable/cierre_rendicion_bingo/conciliacion_flash.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/cierre_rendicion_bingo/conciliacion_flash.js')) ?: time() }}" type="text/javascript"></script>
@endif
<script>
(function () {
    function overlayEl() {
        return document.getElementById('bingo-conc-overlay');
    }
    function mostrar() {
        var overlay = overlayEl();
        if (! overlay) return;
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }
    function ocultar() {
        var overlay = overlayEl();
        if (! overlay) return;
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }
    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('form-conciliacion-bingo');
        if (form) {
            form.addEventListener('submit', function () {
                if (form.checkValidity()) {
                    mostrar();
                }
            });
        }
        document.querySelectorAll('a[href*="listar-cierre-rendiciones-bingo-conciliacion-flash"]').forEach(function (a) {
            a.addEventListener('click', function () { mostrar(); });
        });
    });
    window.addEventListener('pageshow', ocultar);
})();
</script>
@endsection

@section('contenido')
@php
    $tolConfig = (float) config('bingo.cierre_rendicion_contable.conciliacion_flash_tolerancia', 0.02);
@endphp
<style>
    .tabla-conc-bingo .conc-bingo-flash { background-color: #D6EAF8; }
</style>
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'bingo-conc-overlay',
    'tituloId' => 'bingo-conc-titulo',
    'subtituloId' => 'bingo-conc-subtitulo',
    'titulo' => 'Armando conciliación…',
    'subtitulo' => 'Puede demorar según el período. No cierre la página.',
])
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header d-flex align-items-center flex-wrap">
                <h3 class="card-title mb-0">Conciliación venta de sala de bingo vs flash</h3>
                <div class="card-tools ml-auto">
                    <a href="{{ route('cierre_rendicion_bingo_contable', $retornoListadoQuery ?? []) }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="alert alert-info py-2 small mb-3">
                    Réplica del informe Anita <strong>p-vtabingo</strong> (recaudación, premios, pozos, efectivo, cánones, hospital)
                    más columnas de <strong>flash</strong> (venta, cartones, resultado) para cruzar día a día.
                    Tolerancia: {{ number_format($tolConfig, 2, ',', '.') }}.
                </div>
                <form method="get" action="{{ route('cierre_rendicion_bingo_conciliacion_flash') }}" id="form-conciliacion-bingo" class="mb-4">
                    @foreach ($retornoListadoQuery ?? [] as $retornoKey => $retornoVal)
                        <input type="hidden" name="retorno[{{ $retornoKey }}]" value="{{ $retornoVal }}">
                    @endforeach
                    <input type="hidden" name="consultar" value="1">
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-4">
                            <label for="empresa_id" class="control-label text-right pr-2 d-block">Empresa</label>
                            <select name="empresa_id" id="empresa_id" class="form-control" required>
                                <option value="">— Seleccione —</option>
                                @foreach ($empresa_query as $emp)
                                    <option value="{{ $emp->id }}" @selected((int) ($empresa_id ?? 0) === (int) $emp->id)>
                                        {{ $emp->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="fecha_desde" class="control-label text-right pr-2 d-block">Jornada desde</label>
                            <input type="date" name="fecha_desde" id="fecha_desde" class="form-control"
                                   value="{{ $fecha_desde ?? '' }}" required>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="fecha_hasta" class="control-label text-right pr-2 d-block">Jornada hasta</label>
                            <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control"
                                   value="{{ $fecha_hasta ?? '' }}" required>
                        </div>
                        <div class="form-group col-md-2">
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fa fa-search"></i> Consultar
                            </button>
                        </div>
                    </div>
                </form>

                @if (! empty($error_flash))
                    <div class="alert alert-danger">{{ $error_flash }}</div>
                @endif

                @if ($consultar && empty($error_flash) && $resultado !== null)
                    @php
                        $resumen = $resultado['resumen'] ?? [];
                    @endphp
                    <div class="d-flex flex-wrap align-items-start justify-content-between mb-3">
                        <div>
                            <strong>{{ $resultado['empresa_nombre'] ?? '' }}</strong>
                            — {{ \Carbon\Carbon::parse($resultado['fecha_desde'])->format('d/m/Y') }}
                            al {{ \Carbon\Carbon::parse($resultado['fecha_hasta'])->format('d/m/Y') }}
                            <br>
                            <span class="text-muted">
                                {{ (int) ($resumen['total_dias'] ?? 0) }} jornada(s) —
                                {{ (int) ($resumen['dias_ok'] ?? 0) }} OK,
                                {{ (int) ($resumen['dias_dif'] ?? 0) }} con diferencia
                                @if ((int) ($resumen['total_grupos_pendientes'] ?? 0) > 0)
                                    — <span class="text-warning font-weight-bold">
                                        {{ (int) $resumen['total_pendiente_cierre'] }} rend. pendiente(s)
                                    </span>
                                @endif
                            </span>
                        </div>
                        <div class="d-flex flex-wrap align-items-center">
                            @if (can('exportar-cierre-rendicion-bingo-contable', false))
                                <div class="mr-2 mb-1">
                                    @include('includes.exportar-tabla-queryparams', [
                                        'ruta' => 'listar_cierre_rendicion_bingo_conciliacion_flash',
                                        'queryparams' => $filtrosQueryConciliacion ?? [],
                                    ])
                                </div>
                            @endif
                            @if (can('ejecutar-cierre-rendicion-bingo-contable', false)
                                && (int) ($resumen['total_grupos_pendientes'] ?? 0) > 0)
                                <button type="button"
                                        class="btn btn-success btn-sm mb-1"
                                        id="btn-cerrar-periodo-conc"
                                        data-empresa-id="{{ (int) ($resultado['empresa_id'] ?? 0) }}"
                                        data-fecha-desde="{{ $resultado['fecha_desde'] ?? '' }}"
                                        data-fecha-hasta="{{ $resultado['fecha_hasta'] ?? '' }}"
                                        data-grupos="{{ (int) ($resumen['total_grupos_pendientes'] ?? 0) }}"
                                        data-pendientes="{{ (int) ($resumen['total_pendiente_cierre'] ?? 0) }}"
                                        title="Generar cierres pendientes del periodo">
                                    <i class="fa fa-lock"></i> Cerrar periodo completo
                                </button>
                            @endif
                        </div>
                    </div>

                    <div class="table-responsive">
                        @include('contable.cierre_rendicion_bingo.partials.tabla_conciliacion', [
                            'resultado' => $resultado,
                            'modoPantalla' => true,
                        ])
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
