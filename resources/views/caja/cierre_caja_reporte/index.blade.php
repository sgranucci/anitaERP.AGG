@extends("theme.$theme.layout")
@section('titulo')
    Cierre de caja
@endsection

@section('scripts')
<style>
    #tabla-cierre-caja-reporte thead th { background: #85C1E9; color: #17202A; }
    #tabla-cierre-caja-reporte .cierre-seccion td { background: #D6EAF8; font-weight: 600; }
    #tabla-cierre-caja-reporte .cierre-total td { background: #D5D8DC; font-weight: 600; }
</style>
<script src="{{ asset('assets/pages/scripts/reportes/empresas_checkboxes.js') }}" type="text/javascript"></script>
<script>
(function () {
    var overlay = document.getElementById('cierre-caja-reporte-overlay');
    function mostrar(titulo) {
        if (!overlay) { return; }
        if (titulo) {
            document.getElementById('cierre-caja-reporte-overlay-titulo').textContent = titulo;
        }
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }
    function ocultar() {
        if (!overlay) { return; }
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }
    var form = document.getElementById('form-cierre-caja-reporte');
    if (form) {
        form.addEventListener('submit', function (event) {
            var btnConsol = form.querySelector('.btn-toggle-consolidar-empresas');
            var inputConsol = form.querySelector('input[name="consolidar_empresas"]');
            if (btnConsol && inputConsol) {
                inputConsol.value = btnConsol.classList.contains('btn-success') ? '1' : '0';
            }
            if (!form.checkValidity()) { return; }
            var checks = form.querySelectorAll('input[name="empresa_ids[]"]:checked');
            var unica = form.querySelector('input[name="empresa_ids[]"][type="hidden"]');
            if (!unica && checks.length === 0) {
                event.preventDefault();
                alert('Seleccione al menos una empresa.');
                return;
            }
            mostrar('Consultando cierre de caja…');
        });
    }
    document.querySelectorAll('a[href*="listar-cierre-caja-reporte"]').forEach(function (link) {
        link.addEventListener('click', function () {
            mostrar('Generando exportación…');
            var sub = document.getElementById('cierre-caja-reporte-overlay-subtitulo');
            if (sub) {
                sub.textContent = 'El archivo se descarga al terminar. Pulse Esc para cerrar este aviso.';
            }
            window.addEventListener('focus', ocultar, { once: true });
        });
    });
    window.addEventListener('pageshow', ocultar);
    window.addEventListener('pagehide', ocultar);
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' || event.keyCode === 27) { ocultar(); }
    });
})();
</script>
@endsection

@section('contenido')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'cierre-caja-reporte-overlay',
    'tituloId' => 'cierre-caja-reporte-overlay-titulo',
    'subtituloId' => 'cierre-caja-reporte-overlay-subtitulo',
    'titulo' => 'Consultando cierre de caja…',
    'subtitulo' => 'Puede demorar según el período y cheques. No cierre la página.',
])
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Cierre de caja</h3>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    Informe de cierre de caja diaria (Anita l-ciecaja): saldos por cuenta,
                    cheques emitidos, depósitos, cobranzas/pagos y cartera de cheques del período.
                </p>

                @if (($empresa_query ?? collect())->isEmpty())
                    <div class="alert alert-warning">
                        Su usuario no tiene empresas asignadas para este reporte.
                    </div>
                @endif

                <form method="get" action="{{ route('cierre_caja_reporte') }}" id="form-cierre-caja-reporte" class="form-horizontal mb-4" autocomplete="off">
                    <input type="hidden" name="consultar" value="1">

                    @include('includes.reportes.asignacion_empresas_checkboxes', [
                        'empresa_query' => $empresa_query,
                        'empresa_ids_seleccionados' => $filtros['empresa_ids'] ?? [],
                        'consolidar_empresas' => $filtros['consolidar_empresas'] ?? true,
                        'reporte_clave' => 'cierre_caja_reporte',
                        'id_prefix' => 'cierre_caja_rep',
                        'col_label' => 'col-lg-2 text-right pr-2',
                    ])

                    <div class="form-group row">
                        <label for="fecha_desde" class="col-lg-2 control-label text-right pr-2 requerido">Desde</label>
                        <div class="col-lg-3">
                            <input type="date" name="fecha_desde" id="fecha_desde" class="form-control"
                                   value="{{ $filtros['fecha_desde'] }}" required>
                        </div>
                        <label for="fecha_hasta" class="col-lg-2 control-label text-right pr-2 requerido">Hasta</label>
                        <div class="col-lg-3">
                            <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control"
                                   value="{{ $filtros['fecha_hasta'] }}" required>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="cuentacaja_id" class="col-lg-2 control-label text-right pr-2">Cuenta de caja</label>
                        <div class="col-lg-6">
                            <select name="cuentacaja_id" id="cuentacaja_id" class="form-control">
                                <option value="0">Todas</option>
                                @foreach ($cuentas as $cuenta)
                                    <option value="{{ $cuenta->id }}" @selected((int) ($filtros['cuentacaja_id'] ?? 0) === (int) $cuenta->id)>
                                        {{ $cuenta->codigo }} — {{ $cuenta->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <div class="col-lg-2"></div>
                        <div class="col-lg-6">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="incluir_sin_movimiento" id="incluir_sin_movimiento" value="1"
                                    @checked(!empty($filtros['incluir_sin_movimiento']))>
                                <label class="form-check-label" for="incluir_sin_movimiento">
                                    Incluir cuentas sin movimiento ni saldo en el período
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group row mb-0">
                        <div class="col-lg-2"></div>
                        <div class="col-lg-10">
                            <button type="submit" class="btn btn-primary btn-sm" {{ ($empresa_query ?? collect())->isEmpty() ? 'disabled' : '' }}>
                                <i class="fa fa-search"></i> Consultar
                            </button>
                        </div>
                    </div>
                </form>

                @if ($consultado && empty($filtros['empresa_ids']))
                    <div class="alert alert-warning">Seleccione al menos una empresa.</div>
                @endif

                @if ($consultado && $resultado)
                    <div class="mb-2">
                        <span class="badge badge-info mr-1">Líneas: {{ $resultado['total_registros'] }}</span>
                        @if (!empty($resultado['cobro_pago']))
                            <span class="badge badge-success mr-1">Cobros: {{ number_format((float) ($resultado['cobro_pago']['cobro'] ?? 0), 2, ',', '.') }}</span>
                            <span class="badge badge-danger">Pagos: {{ number_format((float) ($resultado['cobro_pago']['pago'] ?? 0), 2, ',', '.') }}</span>
                        @endif
                    </div>

                    @include('includes.exportar-tabla-queryparams', [
                        'ruta' => 'listar_cierre_caja_reporte',
                        'queryparams' => $filtrosQuery,
                    ])

                    @php
                        $logosVista = \App\Support\Configuracion\EmpresaLogoArchivo::logosCabeceraDesdeColeccion(
                            collect($resultado['filas'] ?? [])->map(fn ($f) => (object) $f)
                        );
                    @endphp
                    <div class="border-bottom pb-2 mb-3 d-flex flex-wrap align-items-center">
                        @foreach ($logosVista as $logo)
                            <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" class="mr-2 mb-1" style="max-height: 48px; max-width: 140px;">
                        @endforeach
                    </div>

                    <div class="table-responsive">
                        @include('caja.cierre_caja_reporte.partials.tabla_datos', [
                            'filas' => $filasPaginadas,
                            'puede_ver_cuentacaja' => $puede_ver_cuentacaja ?? false,
                            'puede_ver_cheque' => $puede_ver_cheque ?? false,
                        ])
                    </div>
                @endif
            </div>
            @if ($consultado && $filasPaginadas)
                <div class="card-footer clearfix">
                    @if ($filasPaginadas->total() > 0)
                        <span class="text-muted small mr-2">
                            {{ $filasPaginadas->firstItem() }}–{{ $filasPaginadas->lastItem() }}
                            de {{ $filasPaginadas->total() }}
                        </span>
                    @endif
                    {{ $filasPaginadas->appends($filtrosQuery)->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
