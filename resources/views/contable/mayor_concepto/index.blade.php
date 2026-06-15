@extends("theme.$theme.layout")
@section('titulo')
    Mayor por concepto
@endsection

@section('scripts')
<script>
(function () {
    function togglePeriodo() {
        var mes = document.getElementById('modo_mes').checked;
        document.getElementById('panel-mes').style.display = mes ? '' : 'none';
        document.getElementById('panel-rango').style.display = mes ? 'none' : '';
    }
    document.querySelectorAll('input[name="modo_periodo"]').forEach(function (el) {
        el.addEventListener('change', togglePeriodo);
    });
    togglePeriodo();

    var form = document.getElementById('form-mayor-concepto');
    if (form) {
        form.addEventListener('submit', function () {
            var btn = document.getElementById('btn-consultar');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Procesando… (puede tardar varios minutos)';
            }
        });
    }

    window.cambiarAgrupacionResumen = function (modo) {
        var input = document.getElementById('agrupacion_resumen');
        var formConsulta = document.getElementById('form-mayor-concepto');
        if (! input || ! formConsulta) {
            return;
        }
        input.value = modo;
        var consultar = formConsulta.querySelector('input[name="consultar"]');
        if (consultar) {
            consultar.remove();
        }
        formConsulta.submit();
    };
})();
</script>
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Mayor por concepto</h3>
                <div class="card-tools">
                    <a href="{{ route('mayor_concepto') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('mayor_concepto') }}" id="form-mayor-concepto" class="mb-0">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Imputación contable por concepto de gasto (motor Anita). Los importes se expresan en la moneda elegida
                        según cotización de cada movimiento.
                    </p>

                    @include('includes.form-empresa-asignada', [
                        'empresa_query' => $empresa_query,
                        'empresa_id' => $filtros['empresa_id'] ?? null,
                        'required' => true,
                        'col_label' => 'col-lg-2',
                        'col_input' => 'col-lg-4',
                    ])

                    <div class="form-group row">
                        <label class="col-lg-2 control-label requerido">Período</label>
                        <div class="col-lg-9">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="modo_periodo" id="modo_mes" value="mes"
                                    {{ ($filtros['modo_periodo'] ?? 'mes') === 'mes' ? 'checked' : '' }}>
                                <label class="form-check-label" for="modo_mes">Mes completo</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="modo_periodo" id="modo_rango" value="rango"
                                    {{ ($filtros['modo_periodo'] ?? '') === 'rango' ? 'checked' : '' }}>
                                <label class="form-check-label" for="modo_rango">Rango de fechas</label>
                            </div>
                        </div>
                    </div>

                    <div id="panel-mes" class="form-group row">
                        <label class="col-lg-2 control-label requerido">Mes / Año</label>
                        <div class="col-lg-9">
                            <div class="row">
                                <div class="col-md-3">
                                    <select name="mes" class="form-control">
                                        @for ($m = 1; $m <= 12; $m++)
                                            <option value="{{ $m }}" @selected((int) ($filtros['mes'] ?? $mes_actual) === $m)>
                                                {{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}
                                            </option>
                                        @endfor
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <input type="number" name="anio" class="form-control" min="2000" max="2100"
                                        value="{{ $filtros['anio'] ?? $anio_actual }}">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="panel-rango" class="form-group row" style="display:none;">
                        <label class="col-lg-2 control-label requerido">Desde / Hasta</label>
                        <div class="col-lg-9">
                            <div class="row">
                                <div class="col-md-3">
                                    <input type="date" name="fecha_desde" class="form-control"
                                        value="{{ $filtros['fecha_desde'] ?? '' }}">
                                </div>
                                <div class="col-md-3">
                                    <input type="date" name="fecha_hasta" class="form-control"
                                        value="{{ $filtros['fecha_hasta'] ?? '' }}">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="moneda_id" class="col-lg-2 control-label requerido">Expresar en</label>
                        <div class="col-lg-4">
                            <select name="moneda_id" id="moneda_id" class="form-control" required>
                                @foreach ($moneda_query as $mon)
                                    <option value="{{ $mon->id }}" @selected((int) ($filtros['moneda_id'] ?? 1) === (int) $mon->id)>
                                        {{ $mon->nombre }} ({{ $mon->abreviatura }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group row mb-0">
                        <label class="col-lg-2 control-label">Filtro moneda</label>
                        <div class="col-lg-9">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="solo_moneda_origen" id="solo_moneda_origen" value="1"
                                    @checked(! empty($filtros['solo_moneda_origen']))>
                                <label class="form-check-label" for="solo_moneda_origen">
                                    Solo movimientos en moneda origen (equivalente Anita «Origen»)
                                </label>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" name="agrupacion_resumen" id="agrupacion_resumen"
                        value="{{ $filtros['agrupacion_resumen'] ?? 'concepto_cuenta' }}">

                    <div class="form-group row mb-0 mt-3">
                        <div class="col-lg-2"></div>
                        <div class="col-lg-10">
                            <input type="hidden" name="consultar" value="1">
                            <button type="submit" class="btn btn-primary btn-sm" id="btn-consultar">
                                <i class="fa fa-search"></i> Consultar
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            @if ($consultado)
                @php $tot = $totales ?? []; @endphp
                <div class="card-body p-0 border-top">
                    @if (!empty($errores_bridge))
                        <div class="alert alert-warning mx-3 mt-3 mb-0">
                            <strong>Advertencias bridge Anita:</strong>
                            <ul class="mb-0">
                                @foreach ($errores_bridge as $err)
                                    <li>{{ $err }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="px-3 py-2 border-bottom bg-light">
                        <p class="mb-1 small">
                            <strong>Empresa:</strong> {{ $empresa->nombre ?? '—' }}
                            · <strong>Período:</strong> {{ $periodo_texto }}
                            · <strong>Expresado en:</strong> {{ $moneda->nombre ?? '' }} ({{ $moneda->abreviatura ?? '' }})
                            @if (! empty($filtros['solo_moneda_origen']))
                                — solo moneda origen
                            @endif
                        </p>
                        <p class="mb-0 small text-muted">
                            {{ $tot['stats']['subdiario_filas'] ?? 0 }} movimientos subdiario ·
                            {{ $tot['stats']['ctamov_filas'] ?? 0 }} ctamov ·
                            {{ $tot['stats']['auxpag_filas'] ?? 0 }} auxpag ·
                            {{ $tot['stats']['operaciones_procesadas'] ?? 0 }} operaciones procesadas
                        </p>
                    </div>

                    <div class="d-flex flex-wrap align-items-center justify-content-between px-3 py-2 border-bottom bg-light">
                        <div class="mb-1 mb-md-0">
                            @include('includes.exportar-tabla-queryparams', [
                                'ruta' => 'listar_mayor_concepto',
                                'queryparams' => $filtrosQuery ?? [],
                            ])
                        </div>
                        <div class="small mb-1 mb-md-0 text-md-right">
                            <span class="text-muted">Totales filtro:</span>
                            <strong>{{ (int) ($tot['cantidad_filas'] ?? 0) }}</strong> líneas
                            · Debe <strong>{{ number_format((float) ($tot['total_debe'] ?? 0), 2, ',', '.') }}</strong>
                            · Haber <strong>{{ number_format((float) ($tot['total_haber'] ?? 0), 2, ',', '.') }}</strong>
                        </div>
                    </div>

                    @php
                        $filasVista = $filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator
                            ? $filas->items()
                            : ($filas ?? []);
                        $logosVista = \App\Support\Configuracion\EmpresaLogoArchivo::logosCabeceraDesdeColeccion($filasVista);
                    @endphp
                    @if (count($logosVista) > 0)
                        <div class="border-bottom px-3 py-2 d-flex flex-wrap align-items-center">
                            @foreach ($logosVista as $logo)
                                <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" class="mr-2 mb-1" style="max-height: 48px; max-width: 140px;">
                            @endforeach
                        </div>
                    @endif

                    @include('contable.mayor_concepto.partials.resumen_totales', [
                        'resumen' => $resumen ?? [],
                        'resumen_por_cuenta' => $resumen_por_cuenta ?? [],
                        'agrupacion_resumen' => $agrupacion_resumen ?? 'concepto_cuenta',
                        'puede_ver_asiento' => $puede_ver_asiento ?? false,
                        'puede_ver_cuenta' => $puede_ver_cuenta ?? false,
                        'puede_ver_concepto' => $puede_ver_concepto ?? false,
                    ])

                    @include('contable.mayor_concepto.partials.auditoria_panel', [
                        'auditoria_panel' => $auditoria_panel ?? null,
                        'auditoria' => $auditoria ?? null,
                    ])

                    <div class="px-3 pt-2 pb-1">
                        <h6 class="mb-0 font-weight-bold">Detalle de movimientos</h6>
                        <small class="text-muted">Listado paginado de cada imputación</small>
                    </div>

                    <style>
                        #tabla-mayor-concepto thead tr { background-color: #85C1E9; color: #17202A; }
                        #tabla-mayor-concepto thead th { font-weight: 600; border-color: #7fb3d5; }
                    </style>
                    <div class="table-responsive">
                        <table id="tabla-mayor-concepto" class="table table-striped table-bordered table-hover table-sm mb-0" style="font-size: 0.8rem;">
                            @include('contable.mayor_concepto.partials.tabla_datos', [
                                'filas' => $filasVista,
                                'puede_ver_asiento' => $puede_ver_asiento ?? false,
                                'puede_ver_cuenta' => $puede_ver_cuenta ?? false,
                                'puede_ver_concepto' => $puede_ver_concepto ?? false,
                            ])
                        </table>
                    </div>

                    @if ($filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
                        <div class="card-footer clearfix d-flex flex-wrap align-items-center justify-content-between">
                            <span class="small text-muted mb-2 mb-md-0">
                                @if ($filas->total() > 0)
                                    Mostrando {{ $filas->firstItem() }}–{{ $filas->lastItem() }} de {{ $filas->total() }} registros
                                @else
                                    Sin registros
                                @endif
                            </span>
                            {{ $filas->appends($filtrosQuery ?? [])->links() }}
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
