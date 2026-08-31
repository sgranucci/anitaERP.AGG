@extends("theme.$theme.layout")
@section('titulo')
    Remesas por cuenta de caja
@endsection

@section('scripts')
<style>
    #tabla-remesa-reporte thead th { background: #85C1E9; color: #17202A; }
    #tabla-remesa-reporte .remesa-reporte-grupo td { background: #D6EAF8; }
    #tabla-remesa-reporte .remesa-reporte-total td { background: #f5f5f5; }
    #tabla-remesa-reporte .remesa-reporte-total-general td { background: #D5D8DC; }
</style>
<script src="{{ asset('assets/pages/scripts/reportes/empresas_checkboxes.js') }}" type="text/javascript"></script>
<script>
(function () {
    var overlay = document.getElementById('remesa-reporte-overlay');
    function mostrar(titulo) {
        if (!overlay) {
            return;
        }
        if (titulo) {
            document.getElementById('remesa-reporte-overlay-titulo').textContent = titulo;
        }
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }
    function ocultar() {
        if (!overlay) {
            return;
        }
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }
    var form = document.getElementById('form-remesa-reporte');
    if (form) {
        form.addEventListener('submit', function (event) {
            var btnConsol = form.querySelector('.btn-toggle-consolidar-empresas');
            var inputConsol = form.querySelector('input[name="consolidar_empresas"]');
            if (btnConsol && inputConsol) {
                inputConsol.value = btnConsol.classList.contains('btn-success') ? '1' : '0';
            }
            if (!form.checkValidity()) {
                return;
            }
            var checks = form.querySelectorAll('input[name="empresa_ids[]"]:checked');
            var unica = form.querySelector('input[name="empresa_ids[]"][type="hidden"]');
            if (!unica && checks.length === 0) {
                event.preventDefault();
                alert('Seleccione al menos una empresa.');
                return;
            }
            mostrar('Consultando remesas…');
        });
    }
    document.querySelectorAll('a[href*="listar-remesa-reporte"]').forEach(function (link) {
        link.addEventListener('click', function () {
            mostrar('Generando exportación…');
            var sub = document.getElementById('remesa-reporte-overlay-subtitulo');
            if (sub) {
                sub.textContent = 'El archivo se descarga al terminar. Pulse Esc para cerrar este aviso.';
            }
            window.addEventListener('focus', ocultar, { once: true });
        });
    });
    window.addEventListener('pageshow', ocultar);
    window.addEventListener('pagehide', ocultar);
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' || event.keyCode === 27) {
            ocultar();
        }
    });
})();
</script>
@endsection

@section('contenido')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'remesa-reporte-overlay',
    'tituloId' => 'remesa-reporte-overlay-titulo',
    'subtituloId' => 'remesa-reporte-overlay-subtitulo',
    'titulo' => 'Consultando remesas…',
    'subtitulo' => 'Puede demorar si hay que leer Anita. No cierre la página.',
])
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Remesas por cuenta de caja</h3>
                <div class="card-tools">
                    <a href="{{ route('remesa') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-reply-all"></i> Carga de remesas
                    </a>
                </div>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    Listado de remesas agrupadas por cuenta de caja destino.
                    Suma las cargadas en el ERP y completa con Anita las que todavía no están en el ERP
                    (sin duplicar). Puede consultar una o varias empresas autorizadas.
                </p>

                @if (($empresa_query ?? collect())->isEmpty())
                    <div class="alert alert-warning">
                        Su usuario no tiene empresas asignadas para este reporte.
                    </div>
                @endif

                <form method="get" action="{{ route('remesa_reporte') }}" id="form-remesa-reporte" class="form-horizontal mb-4" autocomplete="off">
                    <input type="hidden" name="consultar" value="1">

                    @include('includes.reportes.asignacion_empresas_checkboxes', [
                        'empresa_query' => $empresa_query,
                        'empresa_ids_seleccionados' => $filtros['empresa_ids'] ?? [],
                        'consolidar_empresas' => $filtros['consolidar_empresas'] ?? true,
                        'reporte_clave' => 'remesa_reporte',
                        'id_prefix' => 'remesa_rep',
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
                                <option value="0">Todas las cuentas destino</option>
                                @foreach ($cuentas as $cuenta)
                                    <option value="{{ $cuenta->id }}" @selected((int) ($filtros['cuentacaja_id'] ?? 0) === (int) $cuenta->id)>
                                        {{ $cuenta->codigo }} — {{ $cuenta->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="tipo" class="col-lg-2 control-label text-right pr-2">Tipo</label>
                        <div class="col-lg-3">
                            <select name="tipo" id="tipo" class="form-control">
                                <option value="M" @selected(($filtros['tipo'] ?? '') === 'M')>Externas</option>
                                <option value="I" @selected(($filtros['tipo'] ?? '') === 'I')>Internas</option>
                                <option value="" @selected(($filtros['tipo'] ?? 'M') === '')>Todas</option>
                            </select>
                        </div>
                        <label for="fuente" class="col-lg-2 control-label text-right pr-2">Fuente</label>
                        <div class="col-lg-3">
                            <select name="fuente" id="fuente" class="form-control">
                                <option value="todas" @selected(($filtros['fuente'] ?? '') === 'todas')>ERP + Anita</option>
                                <option value="erp" @selected(($filtros['fuente'] ?? '') === 'erp')>Solo ERP</option>
                                <option value="anita" @selected(($filtros['fuente'] ?? '') === 'anita')>Solo Anita</option>
                            </select>
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
                    @foreach ($resultado['advertencias'] ?? [] as $aviso)
                        <div class="alert alert-warning py-2">{{ $aviso }}</div>
                    @endforeach

                    <div class="mb-2">
                        <span class="badge badge-info mr-1">Movimientos: {{ $resultado['total_movimientos'] }}</span>
                        <span class="badge badge-secondary mr-1">
                            Origen: {{ number_format((float) $resultado['total_importe_origen'], 2, ',', '.') }}
                        </span>
                        <span class="badge badge-secondary">
                            Importe: {{ number_format((float) $resultado['total_importe'], 2, ',', '.') }}
                        </span>
                    </div>

                    @include('includes.exportar-tabla-queryparams', [
                        'ruta' => 'listar_remesa_reporte',
                        'queryparams' => $filtrosQuery,
                    ])

                    @php
                        $logosVista = \App\Support\Configuracion\EmpresaLogoArchivo::logosCabeceraDesdeColeccion($resultado['filas'] ?? []);
                    @endphp
                    <div class="border-bottom pb-2 mb-3 d-flex flex-wrap align-items-center">
                        @foreach ($logosVista as $logo)
                            <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" class="mr-2 mb-1" style="max-height: 48px; max-width: 140px;">
                        @endforeach
                    </div>

                    <div class="table-responsive">
                        @include('caja.remesa_reporte.partials.tabla_datos', [
                            'filas' => $filasPaginadas,
                            'puede_ver_remesa' => $puede_ver_remesa ?? false,
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
