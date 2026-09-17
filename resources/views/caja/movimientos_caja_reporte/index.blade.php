@extends("theme.$theme.layout")
@section('titulo')
    Movimientos de caja
@endsection

@section('scripts')
<style>
    #tabla-movimientos-caja-reporte thead th { background: #85C1E9; color: #17202A; }
    #tabla-movimientos-caja-reporte .mov-caja-grupo td { background: #D6EAF8; }
    #tabla-movimientos-caja-reporte .mov-caja-total td { background: #f5f5f5; font-weight: 600; }
    #tabla-movimientos-caja-reporte .mov-caja-total-general td { background: #D5D8DC; font-weight: 600; }
</style>
<script src="{{ asset('assets/pages/scripts/reportes/empresas_checkboxes.js') }}" type="text/javascript"></script>
<script>
(function () {
    var overlay = document.getElementById('movimientos-caja-reporte-overlay');
    function mostrar(titulo) {
        if (!overlay) { return; }
        if (titulo) {
            document.getElementById('movimientos-caja-reporte-overlay-titulo').textContent = titulo;
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
    var form = document.getElementById('form-movimientos-caja-reporte');
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
            mostrar('Consultando movimientos de caja…');
        });
    }
    document.querySelectorAll('a[href*="listar-movimientos-caja-reporte"]').forEach(function (link) {
        link.addEventListener('click', function () {
            mostrar('Generando exportación…');
            var sub = document.getElementById('movimientos-caja-reporte-overlay-subtitulo');
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
    'overlayId' => 'movimientos-caja-reporte-overlay',
    'tituloId' => 'movimientos-caja-reporte-overlay-titulo',
    'subtituloId' => 'movimientos-caja-reporte-overlay-subtitulo',
    'titulo' => 'Consultando movimientos de caja…',
    'subtitulo' => 'Puede demorar según el período. No cierre la página.',
])
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Movimientos de caja</h3>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    Informe de cobros, pagos e ingresos/egresos varios (Anita l-movim).
                    Vista previa en pantalla con exportación PDF, Excel y CSV.
                </p>

                @if (($empresa_query ?? collect())->isEmpty())
                    <div class="alert alert-warning">
                        Su usuario no tiene empresas asignadas para este reporte.
                    </div>
                @endif

                <form method="get" action="{{ route('movimientos_caja_reporte') }}" id="form-movimientos-caja-reporte" class="form-horizontal mb-4" autocomplete="off">
                    <input type="hidden" name="consultar" value="1">

                    @include('includes.reportes.asignacion_empresas_checkboxes', [
                        'empresa_query' => $empresa_query,
                        'empresa_ids_seleccionados' => $filtros['empresa_ids'] ?? [],
                        'consolidar_empresas' => $filtros['consolidar_empresas'] ?? true,
                        'reporte_clave' => 'movimientos_caja_reporte',
                        'id_prefix' => 'mov_caja_rep',
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
                        <label for="tipo_listado" class="col-lg-2 control-label text-right pr-2">Tipo de listado</label>
                        <div class="col-lg-3">
                            <select name="tipo_listado" id="tipo_listado" class="form-control">
                                <option value="todos" @selected(($filtros['tipo_listado'] ?? '') === 'todos')>Todos</option>
                                <option value="cobro" @selected(($filtros['tipo_listado'] ?? '') === 'cobro')>Cobros</option>
                                <option value="pago" @selected(($filtros['tipo_listado'] ?? '') === 'pago')>Pagos</option>
                                <option value="iev" @selected(($filtros['tipo_listado'] ?? '') === 'iev')>Ingresos y egresos varios</option>
                            </select>
                        </div>
                        <label for="estado" class="col-lg-2 control-label text-right pr-2">Estado</label>
                        <div class="col-lg-3">
                            <select name="estado" id="estado" class="form-control">
                                <option value="activos" @selected(($filtros['estado'] ?? '') === 'activos')>Activos</option>
                                <option value="anulados" @selected(($filtros['estado'] ?? '') === 'anulados')>Anulados / revertidos</option>
                                <option value="todos" @selected(($filtros['estado'] ?? '') === 'todos')>Todos</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="formato" class="col-lg-2 control-label text-right pr-2">Modelo</label>
                        <div class="col-lg-3">
                            <select name="formato" id="formato" class="form-control">
                                <option value="resumido" @selected(($filtros['formato'] ?? '') === 'resumido')>Resumido</option>
                                <option value="completo" @selected(($filtros['formato'] ?? '') === 'completo')>Completo (detalle cuentas)</option>
                            </select>
                        </div>
                        <label for="orden" class="col-lg-2 control-label text-right pr-2">Orden</label>
                        <div class="col-lg-3">
                            <select name="orden" id="orden" class="form-control">
                                <option value="fecha" @selected(($filtros['orden'] ?? '') === 'fecha')>Fecha</option>
                                <option value="numero" @selected(($filtros['orden'] ?? '') === 'numero')>Número</option>
                                <option value="tipo" @selected(($filtros['orden'] ?? '') === 'tipo')>Tipo</option>
                            </select>
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
                        <span class="badge badge-info mr-1">Registros: {{ $resultado['total_registros'] }}</span>
                        <span class="badge badge-success mr-1">Ingresos: {{ number_format((float) $resultado['total_ingreso'], 2, ',', '.') }}</span>
                        <span class="badge badge-danger mr-1">Egresos: {{ number_format((float) $resultado['total_egreso'], 2, ',', '.') }}</span>
                        <span class="badge badge-secondary">Neto: {{ number_format((float) $resultado['total_neto'], 2, ',', '.') }}</span>
                    </div>

                    @include('includes.exportar-tabla-queryparams', [
                        'ruta' => 'listar_movimientos_caja_reporte',
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
                        @include('caja.movimientos_caja_reporte.partials.tabla_datos', [
                            'filas' => $filasPaginadas,
                            'puede_ver_ingresoegreso' => $puede_ver_ingresoegreso ?? false,
                            'puede_ver_cobranza' => $puede_ver_cobranza ?? false,
                            'puede_ver_pagoproveedor' => $puede_ver_pagoproveedor ?? false,
                            'puede_ver_cuentacaja' => $puede_ver_cuentacaja ?? false,
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
