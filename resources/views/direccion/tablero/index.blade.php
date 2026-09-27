@extends("theme.$theme.layout")
@section('titulo')
    Tablero de dirección
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/tablero-direccion.css') }}?v={{ filemtime(public_path('assets/css/tablero-direccion.css')) }}">
@endsection

@section('scripts')
<script src="{{ asset('assets/lte/plugins/chart.js/Chart.min.js') }}"></script>
<script>
window.TABLERO_DIRECCION = @json([
    'serie' => $tablero['serie'] ?? [],
    'vencimientos' => $tablero['vencimientos'] ?? [],
]);
</script>
<script src="{{ asset('assets/pages/scripts/direccion/tablero.js') }}?v={{ filemtime(public_path('assets/pages/scripts/direccion/tablero.js')) }}"></script>
@endsection

@section('contenido')
<div class="container-fluid td-tablero">
    <div class="td-hero">
        <h1>Tablero de dirección</h1>
        <p>{{ $periodo['etiqueta'] }} · comparado con {{ $periodo['etiqueta_anterior'] }}</p>
    </div>

    <form id="form-tablero" method="get" action="{{ route('tablero_direccion') }}" class="td-filtros" data-detalle="{{ route('tablero_direccion_detalle') }}">
        <div class="form-row align-items-end">
            <div class="form-group col-md-2 mb-md-0">
                <label class="mb-1" for="td-preset">Atajo</label>
                <select id="td-preset" name="preset" class="form-control">
                    <option value="hoy" @selected($periodo['preset'] === 'hoy')>Hoy</option>
                    <option value="ayer" @selected($periodo['preset'] === 'ayer')>Ayer</option>
                    <option value="dia" @selected($periodo['preset'] === 'dia')>Un día</option>
                    <option value="rango" @selected($periodo['preset'] === 'rango')>Rango de días</option>
                    <option value="mes" @selected($periodo['preset'] === 'mes')>Este mes</option>
                    <option value="mes_anterior" @selected($periodo['preset'] === 'mes_anterior')>Mes anterior</option>
                    <option value="anio" @selected($periodo['preset'] === 'anio')>Año en curso</option>
                </select>
            </div>
            <div class="form-group col-md-2 mb-md-0">
                <label class="mb-1" for="td-desde">Desde</label>
                <input id="td-desde" type="date" name="desde" class="form-control" value="{{ $periodo['desde'] }}" required>
            </div>
            <div class="form-group col-md-2 mb-md-0">
                <label class="mb-1" for="td-hasta">Hasta</label>
                <input id="td-hasta" type="date" name="hasta" class="form-control" value="{{ $periodo['hasta'] }}" required>
            </div>
            @if ($empresas->count() > 1)
            <div class="form-group col-md-3 mb-md-0">
                <label class="mb-1" for="td-empresa">Empresa</label>
                <select id="td-empresa" name="empresa_id" class="form-control">
                    <option value="0">Todas mis empresas</option>
                    @foreach ($empresas as $empresa)
                        <option value="{{ $empresa->id }}" @selected((int) $empresaId === (int) $empresa->id)>{{ $empresa->nombre }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="form-group col-md-2 mb-md-0">
                <button type="submit" class="btn btn-info btn-block">Actualizar</button>
            </div>
        </div>
    </form>

    @php
        $paraDepositar = (int) ($tablero['alertas']['para_depositar'] ?? 0);
        $vencenSemana = (int) ($tablero['alertas']['vencen_semana'] ?? 0);
    @endphp
    @if ($paraDepositar > 0 || $vencenSemana > 0)
    <div class="td-alerta">
        @if ($paraDepositar > 0)
            <a href="{{ route('cheque', ['para_depositar' => 1]) }}">{{ $paraDepositar }} cheques para depositar</a>
        @endif
        @if ($vencenSemana > 0)
            <a href="{{ route('cheque', ['origen' => 'E']) }}">{{ $vencenSemana }} cheques propios vencen en 7 días</a>
        @endif
    </div>
    @endif

    <div class="td-kpis">
        @foreach ($tablero['kpis'] as $kpi)
            @php
                $var = $kpi['variacion'];
                $sube = $var !== null && $var > 0.05;
                $baja = $var !== null && $var < -0.05;
                $favorable = $kpi['sentido'] === 'mas_es_mejor' ? $sube : ($kpi['sentido'] === 'menos_es_mejor' ? $baja : false);
                $desfavorable = $kpi['sentido'] === 'mas_es_mejor' ? $baja : ($kpi['sentido'] === 'menos_es_mejor' ? $sube : false);
                $claseVar = $favorable ? 'td-var-up' : ($desfavorable ? 'td-var-down' : 'td-var-flat');
            @endphp
            <button type="button" class="td-kpi" data-bloque="{{ $kpi['clave'] }}" data-titulo="{{ $kpi['titulo'] }}">
                <div class="td-kpi-titulo">{{ $kpi['titulo'] }}</div>
                <div class="td-kpi-monto">$ {{ $kpi['monto_fmt'] }}</div>
                <div class="td-kpi-extra">
                    @if ($var === null)
                        <span class="td-var td-var-flat">sin base</span>
                    @else
                        <span class="td-var {{ $claseVar }}">{{ $var > 0 ? '+' : '' }}{{ number_format($var, 1, ',', '.') }}%</span>
                    @endif
                    {{ $kpi['extra'] }}
                </div>
            </button>
        @endforeach
    </div>

    <div class="td-atajos">
        @foreach ($atajos as $atajo)
            <a href="{{ $atajo['url'] }}" target="_blank" rel="noopener"><i class="fas {{ $atajo['icono'] }} mr-1"></i>{{ $atajo['titulo'] }}</a>
        @endforeach
    </div>

    @foreach ($tablero['avisos'] as $aviso)
        <div class="alert alert-warning py-2">{{ $aviso }}</div>
    @endforeach

    <div class="row">
        <div class="col-lg-8">
            <div class="td-panel">
                <h2>Ventas y compras · últimos 12 meses</h2>
                <div class="td-cuerpo"><div class="td-chart"><canvas id="td-chart-serie"></canvas></div></div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="td-panel">
                <h2>Cartera por vencimiento</h2>
                <div class="td-cuerpo"><div class="td-chart"><canvas id="td-chart-cartera"></canvas></div></div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            @include('direccion.tablero.partials.ranking', ['titulo' => 'Clientes que más compraron', 'filas' => $tablero['clientes']])
        </div>
        <div class="col-lg-6">
            @include('direccion.tablero.partials.ranking', ['titulo' => 'Proveedores que más facturaron', 'filas' => $tablero['proveedores']])
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            @include('direccion.tablero.partials.articulos', ['titulo' => 'Artículos más vendidos', 'filas' => $tablero['articulos_vendidos']])
        </div>
        <div class="col-lg-6">
            @include('direccion.tablero.partials.articulos', ['titulo' => 'Artículos más comprados (COM de stock)', 'filas' => $tablero['articulos_comprados']])
        </div>
    </div>

    <p class="td-nota">Importes en pesos. La cotización del comprobante manda; si vino en cero se usa la vigente. Compras son las facturas de proveedor; un remito COM cargado en movimientos de stock suma solo si no tiene esa factura. Las tarjetas de stock (cheques y deudas) comparan el cierre de este período con el cierre del período anterior. Un click en la tarjeta abre el detalle.</p>
</div>

<div class="modal fade" id="modal-tablero-detalle" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5 mb-0">Detalle</h2>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body"></div>
        </div>
    </div>
</div>
@endsection
