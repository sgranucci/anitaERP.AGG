@extends("theme.$theme.layout")
@section('titulo')
    Movimientos sin conciliar
@endsection

@section('styles')
<style>
    #tabla-sin-conciliar a.text-primary { font-weight: 600; }
    #tabla-sin-conciliar td.num { text-align: right; white-space: nowrap; }
    #tabla-sin-conciliar thead th { white-space: nowrap; }
    .sin-conciliar-meta { font-family: Consolas, "Courier New", monospace; font-size: 13px; }
</style>
@endsection

@section('scripts')
<meta name="csrf-token" content="{{ csrf_token() }}">
<script>
    window.conciliacionBancariaCuentacajaPorCodigoUrl = @json(route('conciliacion_bancaria_api_cuentacaja_por_codigo', ['codigo' => '__CODIGO__']));
</script>
<script src="{{ asset('assets/pages/scripts/contable/conciliacion_bancaria/filtro.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/conciliacion_bancaria/filtro.js')) ?: time() }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @if (!empty($errores))
            <div class="alert alert-danger">
                <ul class="mb-0">@foreach ($errores as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Movimientos de Cuentas sin conciliar</h3>
                <div class="card-tools">
                    <a href="{{ route('conciliacion_bancaria', ['empresa_id' => $empresa_id, 'cuentacaja_id' => $cuentacaja_id, 'mes' => $mes, 'anio' => $anio, 'consultar' => 1]) }}"
                       class="btn btn-app bg-secondary" title="Volver a conciliación">
                        <i class="fas fa-university"></i> Conciliación
                    </a>
                    <a href="{{ route('conciliacion_bancaria_aging', ['empresa_id' => $empresa_id, 'cuentacaja_id' => $cuentacaja_id, 'mes' => $mes, 'anio' => $anio, 'consultar' => 1]) }}"
                       class="btn btn-app bg-secondary" title="Aging">
                        <i class="fas fa-hourglass-half"></i> Aging
                    </a>
                    @if ($cuentacaja_id && $empresa_id)
                        <a href="{{ route('conciliacion_bancaria_manual', ['empresa_id' => $empresa_id, 'cuentacaja_id' => $cuentacaja_id, 'mes' => $mes, 'anio' => $anio]) }}"
                           class="btn btn-app bg-primary" title="Conciliación manual">
                            <i class="fas fa-columns"></i> Manual
                        </a>
                    @endif
                </div>
            </div>
            <form method="get" action="{{ route('conciliacion_bancaria_sin_conciliar') }}" class="mb-0" data-enter-campos="1">
                <input type="hidden" name="consultar" value="1">
                <div class="card-body">
                    @include('includes.form-empresa-asignada', [
                        'empresa_query' => $empresa_query,
                        'empresa_id' => $empresa_id ?: null,
                        'col_label' => 'col-lg-2',
                        'col_input' => 'col-lg-4',
                    ])
                    <div class="form-group row">
                        <label class="col-lg-2 control-label text-right pr-2 requerido">Cuenta de caja</label>
                        <input type="hidden" name="cuentacaja_id" id="cuentacaja_id" value="{{ $cuentacaja_id ?: '' }}">
                        <div class="col-lg-2">
                            <input type="text" class="form-control" id="codigo_cuentacaja" autocomplete="off"
                                value="{{ $cuentacaja->codigo ?? '' }}" placeholder="Código"
                                title="Código. F1 = consulta">
                        </div>
                        <div class="col-lg-1">
                            <button type="button" class="btn-accion-tabla consultacuentacaja tooltipsC" title="Consultar cuentas de caja (F1)">
                                <i class="fa fa-search text-primary"></i>
                            </button>
                        </div>
                        <div class="col-lg-5">
                            <input type="text" class="form-control" id="nombre_cuentacaja" readonly
                                value="{{ $cuentacaja->nombre ?? '' }}">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-2 control-label text-right pr-2">Período</label>
                        <div class="col-lg-2">
                            <select name="mes" class="form-control">
                                @for ($m = 1; $m <= 12; $m++)
                                    <option value="{{ $m }}" @selected($mes === $m)>{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}</option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-lg-2">
                            <input type="number" name="anio" class="form-control" value="{{ $anio }}">
                        </div>
                        <div class="col-lg-4">
                            <button type="submit" class="btn btn-primary">Consultar</button>
                            <button type="submit" name="recalcular" value="1" class="btn btn-outline-secondary" title="Ignora cache y vuelve a calcular">
                                Recalcular
                            </button>
                        </div>
                    </div>

                    @if ($reporte)
                        @php
                            $t = $reporte['totales'] ?? [];
                            $m = $reporte['meta'] ?? [];
                            $hasta = $m['fecha_hasta'] ?? '';
                            try {
                                $hastaTxt = $hasta !== '' ? \Carbon\Carbon::parse($hasta)->format('d/m/y') : '';
                            } catch (\Throwable) {
                                $hastaTxt = (string) $hasta;
                            }
                            $codigoPad = str_pad((string) ($m['cuentacaja_codigo'] ?? $cuentacaja->codigo ?? ''), 8, '0', STR_PAD_LEFT);
                        @endphp
                        <div class="d-flex flex-wrap align-items-center mb-2">
                            @include('includes.exportar-tabla-queryparams', [
                                'ruta' => 'listar_conciliacion_bancaria_sin_conciliar',
                                'queryparams' => $filtrosQuery ?? [],
                                'variant' => 'compact',
                            ])
                            <span class="ml-3 text-muted small">
                                {{ (int) ($t['n'] ?? 0) }} movimientos ·
                                Débitos {{ number_format((float) ($t['debitos'] ?? 0), 2, ',', '.') }} ·
                                Créditos {{ number_format((float) ($t['creditos'] ?? 0), 2, ',', '.') }}
                            </span>
                        </div>
                        <div class="sin-conciliar-meta mb-2">
                            <div>{{ now()->translatedFormat('D M j H:i:s e Y') }}</div>
                            <div><strong>Movimientos de Cuentas sin conciliar</strong></div>
                            <div>Desde primer mov. hasta {{ $hastaTxt }}</div>
                            <div>Cuenta: ({{ $codigoPad }}) {{ $m['cuentacaja_nombre'] ?? ($cuentacaja->nombre ?? '') }}</div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered table-striped mb-0" id="tabla-sin-conciliar">
                                <thead style="background:#85C1E9;color:#17202A;">
                                    <tr>
                                        <th>Tip</th>
                                        <th>Numero</th>
                                        <th>N.Orig.</th>
                                        <th>F.Mov.</th>
                                        <th>F.Dev.</th>
                                        <th>F.Entr.</th>
                                        <th>F.Conc.</th>
                                        <th>Detalle</th>
                                        <th class="text-right">Debitos</th>
                                        <th class="text-right">Creditos</th>
                                        <th>N.Con.</th>
                                        <th>Detalle del concepto</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($reporte['filas'] as $fila)
                                        <tr>
                                            <td>{{ $fila['tip'] ?? '' }}</td>
                                            <td>
                                                @if (!empty($fila['url_numero']))
                                                    <a href="{{ $fila['url_numero'] }}" target="_blank" rel="noopener" class="text-primary">{{ $fila['numero'] ?? '' }}</a>
                                                @else
                                                    {{ $fila['numero'] ?? '' }}
                                                @endif
                                            </td>
                                            <td>{{ $fila['n_orig'] ?? '' }}</td>
                                            <td>{{ $fila['f_mov'] ?? '' }}</td>
                                            <td>{{ $fila['f_dev'] ?? '' }}</td>
                                            <td>{{ $fila['f_entr'] ?? '' }}</td>
                                            <td>{{ $fila['f_conc'] ?? '' }}</td>
                                            <td>
                                                @if (!empty($fila['url_detalle']))
                                                    <a href="{{ $fila['url_detalle'] }}" target="_blank" rel="noopener" class="text-primary">{{ $fila['detalle'] ?? '' }}</a>
                                                @else
                                                    {{ $fila['detalle'] ?? '' }}
                                                @endif
                                            </td>
                                            <td class="num">
                                                @if (abs((float) ($fila['debito'] ?? 0)) > 0.005)
                                                    {{ number_format((float) $fila['debito'], 2, ',', '.') }}
                                                @endif
                                            </td>
                                            <td class="num">
                                                @if (abs((float) ($fila['credito'] ?? 0)) > 0.005)
                                                    {{ number_format((float) $fila['credito'], 2, ',', '.') }}
                                                @endif
                                            </td>
                                            <td>
                                                @if (!empty($fila['url_concepto']))
                                                    <a href="{{ $fila['url_concepto'] }}" target="_blank" rel="noopener" class="text-primary">{{ $fila['n_con'] ?? '' }}</a>
                                                @else
                                                    {{ $fila['n_con'] ?? '' }}
                                                @endif
                                            </td>
                                            <td>{{ $fila['detalle_concepto'] ?? '' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="12" class="text-center text-muted">Sin movimientos pendientes para este período.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                @if (($t['n'] ?? 0) > 0)
                                    <tfoot>
                                        <tr class="font-weight-bold" style="background:#eaf2f8;">
                                            <td colspan="8" class="text-right">Totales</td>
                                            <td class="num">{{ number_format((float) ($t['debitos'] ?? 0), 2, ',', '.') }}</td>
                                            <td class="num">{{ number_format((float) ($t['creditos'] ?? 0), 2, ',', '.') }}</td>
                                            <td colspan="2"></td>
                                        </tr>
                                    </tfoot>
                                @endif
                            </table>
                        </div>
                        <p class="text-muted small mt-2 mb-0">
                            Links azules abren cheque, orden de pago, asiento o concepto en consulta.
                            Filas tip <code>IB</code> son movimientos de extracto bancario aún sin emparejar.
                        </p>
                    @endif
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
